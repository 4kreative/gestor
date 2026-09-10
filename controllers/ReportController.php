<?php
// VERSION_MARKER_v20260411_1603
class ReportController {
    public function index(): void {
        requireAuth();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $templates = $db->query("SELECT id,name,content FROM message_templates WHERE user_id=? ORDER BY name",[$uid])->fetchAll();
        $accounts  = $db->query("SELECT id,account_name,account_id,platform FROM ad_accounts WHERE user_id=? AND status='active' ORDER BY account_name",[$uid])->fetchAll();
        $instances = $db->query("SELECT id,instance_name,phone_number FROM whatsapp_instances WHERE user_id=? AND status='connected'",[$uid])->fetchAll();
        $clients   = $db->query("SELECT id,name,phone,company FROM clients WHERE user_id=? AND status='active' ORDER BY name",[$uid])->fetchAll();
        $campaigns = $db->query("SELECT DISTINCT campaign_name, campaign_id, ad_account_id FROM campaign_metrics cm JOIN ad_accounts aa ON cm.ad_account_id=aa.id WHERE aa.user_id=? ORDER BY campaign_name",[$uid])->fetchAll();
        $wpGroups  = []; // carregado via JS lazy para não bloquear page load
        // Busca templates PDF para seleção no modal de envio
        $pdfTemplates = [];
        try {
            $pdfTemplates = $db->query(
                "SELECT id, name, updated_at FROM pdf_templates WHERE user_id=? ORDER BY updated_at DESC",
                [$uid]
            )->fetchAll();
        } catch (\Throwable $e) {}

        $page = max(1, (int)($_GET['page'] ?? 1));
        $q    = sanitize($_GET['q'] ?? '');
        $plat = sanitize($_GET['platform'] ?? '');

        $where = "WHERE r.user_id=?";
        $params = [$uid];
        if ($q)    { $where .= " AND (r.title LIKE ? OR c.name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
        if ($plat) { $where .= " AND r.platform=?"; $params[]=$plat; }

        $total   = $db->query("SELECT COUNT(*) FROM reports r LEFT JOIN clients c ON r.client_id=c.id $where", $params)->fetchColumn();
        $pages   = max(1, ceil($total / ITEMS_PER_PAGE));
        $offset  = ($page - 1) * ITEMS_PER_PAGE;

        $reports = $db->query(
            "SELECT r.*, c.name AS client_name, c.phone AS client_phone
             FROM reports r LEFT JOIN clients c ON r.client_id=c.id
             $where ORDER BY r.created_at DESC LIMIT ".ITEMS_PER_PAGE." OFFSET $offset",
            $params
        )->fetchAll();

        require_once __DIR__.'/../views/reports/index.php';
    }

    public function create(): void {
        requireAuth();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $clients  = $db->query("SELECT id,name FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        $accounts = $db->query("SELECT id,account_name,platform FROM ad_accounts WHERE user_id=? AND status='active' ORDER BY account_name", [$uid])->fetchAll();
        $templates= $db->query("SELECT id,name,content FROM message_templates WHERE user_id=?", [$uid])->fetchAll();
        require_once __DIR__.'/../views/reports/create.php';
    }

    public function store(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();

        // Garante que a coluna followup_message existe
        try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS followup_message TEXT NULL DEFAULT NULL"); } catch (\Throwable $e) {}

        $title       = sanitize($_POST['title']          ?? '');
        $accId       = (int)($_POST['ad_account_id']     ?? 0);
        $platform    = sanitize($_POST['platform']       ?? 'meta');
        $periodType  = sanitize($_POST['period_type']    ?? 'last_7_days');
        $msg         = $_POST['message_text']            ?? '';
        $frequency   = sanitize($_POST['frequency']      ?? 'once');
        $sendTime    = sanitize($_POST['send_time']      ?? '08:00');
        $sendDays    = sanitize($_POST['send_days']      ?? '1,2,3,4,5');
        $phone       = sanitize($_POST['recipient_phone']?? '');
        $wpId        = (int)($_POST['whatsapp_id']       ?? 0);

        if (!$title) { flash('error','Nome do relatório obrigatório.'); redirect('/reports'); }

        // Período personalizado vindo do wizard: custom|YYYY-MM-DD|YYYY-MM-DD
        if ($periodType === 'custom') {
            $cs = sanitize($_POST['custom_start'] ?? '');
            $ce = sanitize($_POST['custom_end']   ?? '');
            if ($cs && $ce) { $periodType = 'custom|'.$cs.'|'.$ce; }
        }
        // Calcula datas baseado no tipo de período
        [$start, $end] = self::calcPeriodDates($periodType);
        // Para MAX, period_start no DB fica como '0001-01-01' (placeholder válido)
        $dbStart = ($start === 'MAX') ? '0001-01-01' : $start;

        // Calcula próximo envio
        $nextSend = self::calcNextSend($sendTime, $sendDays, $frequency);

        $status = $frequency === 'once' ? 'scheduled' : 'active';

        $objetivo     = sanitize($_POST['objetivo']       ?? 'todos');
        $campIds      = sanitize($_POST['camp_ids']       ?? '');
        $campLabels   = sanitize($_POST['camp_labels']    ?? '');
        $followupMsg  = $_POST['followup_message']        ?? '';
        $clientId     = (int)($_POST['client_id']         ?? 0);
        $groupId      = sanitize($_POST['group_id']       ?? '');
        $groupInst    = sanitize($_POST['group_instance'] ?? '');
        $recvType     = sanitize($_POST['recv_type']      ?? 'phone');

        // Se cliente selecionado, pega telefone do cliente
        if ($recvType === 'client' && $clientId) {
            $cli = $db->query("SELECT phone FROM clients WHERE id=? AND user_id=?",[$clientId,$uid])->fetch();
            if ($cli && $cli['phone']) $phone = $cli['phone'];
        }

        // Para grupo, armazena group_id no recipient_phone por compatibilidade
        if ($recvType === 'group' && $groupId) {
            $phone = $groupId; // group JID
        }
        // Salva nome do grupo para exibição sem precisar buscar da API
        $groupName = sanitize($_POST['group_name'] ?? '');

        // Detecta instância do grupo se não informada
        if ($recvType === 'group' && $groupInst && !$wpId) {
            $inst = $db->query("SELECT id FROM whatsapp_instances WHERE instance_name=? AND user_id=?",[$groupInst,$uid])->fetch();
            if ($inst) $wpId = $inst['id'];
        }

        $pdfTplIdStore = (int)($_POST['pdf_tpl_id'] ?? 0);

        $db->query(
            "INSERT INTO reports (user_id,ad_account_id,client_id,title,period_start,period_end,period_type,platform,
             objetivo,camp_ids,camp_labels,message_text,followup_message,frequency,send_time,send_days,recipient_phone,whatsapp_id,
             recv_type,group_id,group_instance,group_name,next_send_at,status,pdf_tpl_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$uid, $accId?:null, $clientId?:null, $title, $dbStart, $end, $periodType, $platform,
             $objetivo, $campIds?:null, $campLabels?:null, $msg, $followupMsg?:null, $frequency, $sendTime, $sendDays, $phone, $wpId?:null,
             $recvType, $groupId?:null, $groupInst?:null, $groupName?:null, $nextSend, $status, $pdfTplIdStore?:null]
        );

        // Vincula automaticamente a conta ao cliente se ambos estão preenchidos
        if ($accId && $clientId) {
            $db->query(
                "UPDATE ad_accounts SET client_id=? WHERE id=? AND user_id=? AND (client_id IS NULL OR client_id=0)",
                [$clientId, $accId, $uid]
            );
        }

        flash('success', 'Relatório criado com sucesso!');
        redirect('/reports');
    }

    public static function calcPeriodDates(string $type): array {
        $today = date('Y-m-d');
        // Período personalizado: custom|2024-01-01|2024-03-31
        if (str_starts_with($type, 'custom|')) {
            $parts = explode('|', $type);
            $s = isset($parts[1]) && $parts[1] ? $parts[1] : date('Y-m-d', strtotime('-7 days'));
            $e = isset($parts[2]) && $parts[2] ? $parts[2] : $today;
            return [$s, $e];
        }
        $map = [
            'today'        => [$today, $today],
            'yesterday'    => [date('Y-m-d',strtotime('-1 day')), date('Y-m-d',strtotime('-1 day'))],
            'this_week'    => [date('Y-m-d',strtotime('sunday this week -6 days')), $today],
            'this_week_mon'=> [date('Y-m-d',strtotime('monday this week')), $today],
            'last_week'    => [date('Y-m-d',strtotime('sunday last week')), date('Y-m-d',strtotime('saturday last week'))],
            'last_week_mon'=> [date('Y-m-d',strtotime('monday last week')), date('Y-m-d',strtotime('sunday last week'))],
            'this_month'   => [date('Y-m-01'), $today],
            'last_month'   => [date('Y-m-01',strtotime('first day of last month')), date('Y-m-t',strtotime('last month'))],
            'last_3_days'  => [date('Y-m-d',strtotime('-3 days')), $today],
            'last_7_days'  => [date('Y-m-d',strtotime('-7 days')), $today],
            'last_14_days' => [date('Y-m-d',strtotime('-14 days')), $today],
            'last_30_days' => [date('Y-m-d',strtotime('-30 days')), $today],
            'last_90_days' => [date('Y-m-d',strtotime('-90 days')), $today],
            'last_year'    => [date('Y-m-d',strtotime('-1 year')), $today],
            'this_year'    => [date('Y-01-01'), $today],
            'max'          => ['MAX', $today], // MAX = buscar data real da campanha via API
        ];
        return $map[$type] ?? [date('Y-m-d',strtotime('-7 days')), $today];
    }

    // Calcula o período anterior equivalente para comparativo
    public static function calcPreviousPeriod(string $start, string $end): array {
        $days    = (int)((strtotime($end) - strtotime($start)) / 86400) + 1;
        $pEnd    = date('Y-m-d', strtotime($start) - 86400);
        $pStart  = date('Y-m-d', strtotime($pEnd) - ($days - 1) * 86400);
        return [$pStart, $pEnd];
    }

    // Calcula variação percentual entre dois valores
    public static function calcVariation(float $current, float $previous): ?float {
        if ($previous == 0) return null;
        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * Busca a data de início real da(s) campanha(s) selecionada(s) via API Meta.
     * Usado quando period_type = 'max' para pegar desde o primeiro dia do anúncio.
     */
    /**
     * Retorna a data de início real da(s) campanha(s) para o período MAX.
     *
     * Prioridade:
     *   1. API Meta — start_time da campanha específica (data exata de criação)
     *   2. Banco local campaign_metrics — MIN(date) da campanha específica
     *   3. Banco local — MIN(date) de toda a conta
     *   4. Fallback: hoje menos 2 anos
     *
     * Se camp_ids forem passados, a data é a da campanha específica.
     * Se não, pega a mais antiga de todas as campanhas da conta.
     */
    public static function fetchCampaignStartDate(string $accountId, string $token, array $campIds = [], int $adAccountId = 0): string {

        // --- 1. BANCO LOCAL PRIMEIRO (mais confiável — só tem dados reais) ---
        if ($adAccountId > 0) {
            $db = Database::getInstance();

            if (!empty($campIds)) {
                // Campanhas específicas selecionadas no relatório
                $placeholders = implode(',', array_fill(0, count($campIds), '?'));
                $params = array_merge([$adAccountId], $campIds);
                $minDate = $db->query(
                    "SELECT MIN(date) FROM campaign_metrics
                     WHERE ad_account_id=? AND campaign_id IN ($placeholders)
                     AND spend > 0",
                    $params
                )->fetchColumn();
            } else {
                // Sem filtro — pega campanhas com gasto nos últimos 90 dias (ativas recentemente)
                // Evita pegar campanhas pausadas muito antigas que distorcem a data
                $recentCutoff = date('Y-m-d', strtotime('-90 days'));
                $minDate = $db->query(
                    "SELECT MIN(date) FROM campaign_metrics
                     WHERE ad_account_id=? AND spend > 0
                     AND campaign_id IN (
                         SELECT DISTINCT campaign_id FROM campaign_metrics
                         WHERE ad_account_id=? AND date >= ? AND spend > 0
                     )",
                    [$adAccountId, $adAccountId, $recentCutoff]
                )->fetchColumn();

                // Se não achar nada recente, pega qualquer uma com gasto
                if (!$minDate || $minDate === '0000-00-00') {
                    $minDate = $db->query(
                        "SELECT MIN(date) FROM campaign_metrics
                         WHERE ad_account_id=? AND spend > 0",
                        [$adAccountId]
                    )->fetchColumn();
                }
            }

            if ($minDate && $minDate !== '0000-00-00') {
                // Banco tem dado real — tenta refinar com API para pegar data exata de criação
                // Busca campanhas com spend > 0 no período do banco para usar como filtro
                if (!empty($campIds)) {
                    $apiDate = self::fetchStartDateFromAPI($accountId, $token, $campIds);
                } else {
                    // Busca apenas campanhas com gasto nos últimos 90 dias (recentemente ativas)
                    $recentCutoff2 = date('Y-m-d', strtotime('-90 days'));
                    $activeCampsRaw = $db->query(
                        "SELECT DISTINCT campaign_id FROM campaign_metrics
                         WHERE ad_account_id=? AND spend > 0 AND date >= ?
                         ORDER BY date ASC LIMIT 50",
                        [$adAccountId, $recentCutoff2]
                    )->fetchAll();
                    $activeCamps = array_column($activeCampsRaw, 'campaign_id');
                    $apiDate = !empty($activeCamps)
                        ? self::fetchStartDateFromAPI($accountId, $token, $activeCamps)
                        : null;
                }
                // Usa a API se retornar data mais antiga — mas nunca antes de 5 anos
                $fiveYearsAgo = date('Y-m-d', strtotime('-5 years'));
                if ($apiDate && $apiDate > $fiveYearsAgo && $apiDate < $minDate) {
                    return $apiDate;
                }
                return $minDate;
            }
        }

        // --- 2. SEM DADOS NO BANCO: tenta API apenas para campanhas específicas ---
        if (!empty($campIds)) {
            $apiDate = self::fetchStartDateFromAPI($accountId, $token, $campIds);
            if ($apiDate) return $apiDate;
        }

        // --- 3. Fallback: 90 dias atrás (seguro para qualquer campanha ativa) ---
        return date('Y-m-d', strtotime('-90 days'));
    }

    /**
     * Busca start_time via API Meta para campanhas ESPECÍFICAS (por ID).
     * Nunca busca "todas as campanhas da conta" — isso pega arquivadas de anos atrás.
     */
    private static function fetchStartDateFromAPI(string $accountId, string $token, array $campIds = []): ?string {
        if (!$accountId || !$token || empty($campIds)) return null;

        // Busca start_time das campanhas específicas pelo ID
        $ids = implode(',', array_map('strval', $campIds));
        $url = "https://graph.facebook.com/" . META_API_VERSION . "/"
             . "?ids=" . urlencode($ids)
             . "&fields=start_time,created_time"
             . "&access_token=" . urlencode($token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true]);
        $res  = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !is_array($res)) return null;

        $earliest = null;
        // ?ids= retorna {campId: {start_time:...}}
        foreach (array_values($res) as $c) {
            if (!is_array($c)) continue;
            $ts = strtotime($c['start_time'] ?? $c['created_time'] ?? '');
            if ($ts && ($earliest === null || $ts < $earliest)) {
                $earliest = $ts;
            }
        }

        if (!$earliest) return null;
        $result = date('Y-m-d', $earliest);
        // Sanidade: nunca retornar data antes de 2015 (campanhas Meta não existiam)
        if ($result < '2015-01-01') return null;
        return $result;
    }

    public static function calcNextSend(string $time, string $days, string $freq): string {
        $now    = new \DateTime('now', new \DateTimeZone(APP_TIMEZONE));
        $target = \DateTime::createFromFormat('H:i', $time);
        if (!$target) $target = new \DateTime();
        $target->setDate((int)$now->format('Y'),(int)$now->format('m'),(int)$now->format('d'));
        if ($target <= $now) $target->modify('+1 day');
        // Verifica dias da semana
        $allowedDays = array_map('intval', explode(',', $days));
        for ($i=0;$i<7;$i++) {
            $dow = (int)$target->format('w');
            if (in_array($dow, $allowedDays)) break;
            $target->modify('+1 day');
        }
        return $target->format('Y-m-d H:i:s');
    }

    public static function replaceVars(string $msg, array $rows, string $start, string $end, string $clientName=''): string {
        $roas = ($rows['spend']??0) > 0 ? round(($rows['revenue']??0)/($rows['spend']),2) : 0;
        $replacements = [
            '{nome_cliente}'  => $clientName,
            '{primeiro_nome}' => explode(' ', trim($clientName))[0],
            '{first_name}'    => explode(' ', trim($clientName))[0],
            '{periodo}'       => date('d/m/Y',strtotime($start)).' a '.date('d/m/Y',strtotime($end)),
            '{mes_atual}'     => strftime('%B/%Y') ?: date('m/Y'),
            '{hoje}'          => date('d/m/Y'),
            '{investimento}'  => number_format($rows['spend']??0,2,',','.'),
            '{inv}'           => number_format($rows['spend']??0,2,',','.'),
            '{total_spend}'   => number_format($rows['spend']??0,2,',','.'),
            '{alcance}'       => number_format($rows['reach']??0,0,'.',','),
            '{alcan}'         => number_format($rows['reach']??0,0,'.',','),
            '{impressoes}'    => number_format($rows['impressions']??0,0,'.',','),
            '{imp}'           => number_format($rows['impressions']??0,0,'.',','),
            '{cliques}'       => number_format($rows['clicks']??0,0,'.',','),
            '{cliq}'          => number_format($rows['clicks']??0,0,'.',','),
            '{clicks_all}'    => number_format($rows['clicks']??0,0,'.',','),
            '{ctr}'           => number_format($rows['ctr']??0,2,',','.'),
            '{ctr_all}'       => number_format($rows['ctr']??0,2,',','.'),
            '{cpm}'           => number_format($rows['cpm']??0,2,',','.'),
            '{cpc}'           => number_format($rows['cpc']??0,2,',','.'),
            '{conversoes}'    => number_format($rows['conversions']??0,0,'.',','),
            '{results}'       => number_format($rows['conversions']??0,0,'.',','),
            '{leads}'         => number_format($rows['conversions']??0,0,'.',','),
            '{roas}'          => number_format($roas,2,',','.'),
            '{receita}'       => number_format($rows['revenue']??0,2,',','.'),
            '{cpl}'           => ($rows['conversions']??0)>0 ? number_format(($rows['spend']??0)/($rows['conversions']),2,',','.') : '0,00',
            '{cpv}'           => ($rows['conversions']??0)>0 ? number_format(($rows['spend']??0)/($rows['conversions']),2,',','.') : '0,00',
            '{results_cost}'  => ($rows['conversions']??0)>0 ? number_format(($rows['spend']??0)/($rows['conversions']),2,',','.') : '0,00',
        ];
        return strtr($msg, $replacements);
    }

    public function send(): void {
        requireAuth(); csrfCheck();
        // Captura erros fatais — retorna JSON para AJAX, redirect para normal
        set_exception_handler(function($e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
                exit;
            }
            flash('error', 'Erro interno: ' . $e->getMessage());
            redirect('/reports');
        });
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $reportId = (int)($_POST['report_id'] ?? 0);
        $phone    = sanitize($_POST['phone'] ?? '');
        if ($phone === '__grupo__') $phone = ''; // sentinel value from JS for group reports

        $report = decryptTokens($db->query("SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token, aa.account_name, c.name AS client_name, c.company AS client_company FROM reports r LEFT JOIN ad_accounts aa ON r.ad_account_id=aa.id LEFT JOIN clients c ON r.client_id=c.id WHERE r.id=? AND r.user_id=?", [$reportId,$uid])->fetch() ?: []);
        if (!$report) { flash('error','Relatório não encontrado.'); redirect('/reports'); }

        // Busca instância — usa a do relatório ou a padrão
        $wpId = $report['whatsapp_id'] ?? null;
        $wp   = null;
        if ($wpId) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",[$wpId,$uid])->fetch() ?: null;
        }
        if (empty($wp)) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id LIMIT 1",[$uid])->fetch() ?: null;
        }
        if (!$wp) { flash('error','Nenhuma instância WhatsApp conectada.'); redirect('/reports'); }

        // Busca métricas para substituir variáveis
        [$start,$end] = self::calcPeriodDates($report['period_type'] ?? 'last_7_days');
        $campIds = !empty($report['camp_ids']) ? array_filter(explode(',', $report['camp_ids'])) : [];
        $metrics = null;
        // Período 'max': resolve data real ANTES de qualquer uso de $start
        if ($start === 'MAX') {
            if (!empty($report['meta_account_id']) && !empty($report['meta_token'])) {
                $start = self::fetchCampaignStartDate($report['meta_account_id'], $report['meta_token'], $campIds, (int)($report['ad_account_id'] ?? 0));
            } else {
                $start = date('Y-m-d', strtotime('-90 days'));
            }
        }
        if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
            $start = date('Y-m-d', strtotime('-90 days')); // sanidade anti-1969
        }
        if (!empty($report['meta_account_id']) && !empty($report['meta_token'])) {
            // Usa cache (15 min) para evitar chamada desnecessária à API Meta a cada PDF
            $metrics = self::fetchMetricsCached(
                $report['meta_account_id'],
                $report['meta_token'],
                $start, $end, $campIds,
                (int)($report['ad_account_id'] ?? 0),
                (int)($report['user_id'] ?? 0),
                15
            );
        }
        // Fallback: se API retornou null, usa dados do banco campaign_metrics
        if ($metrics === null && !empty($report['ad_account_id'])) {
            $metrics = self::fetchMetricsFromDB((int)$report['ad_account_id'], $start, $end, $campIds);
        }

        $periodo_str = date('d/m/Y',strtotime($start)).' a '.date('d/m/Y',strtotime($end));
        // Busca nome(s) da(s) campanha(s) selecionada(s)
        $campNome = '';
        if (!empty($campIds)) {
            // 1. Tenta no banco local primeiro (mais rápido)
            $placeholders = implode(',', array_fill(0, count($campIds), '?'));
            $campRows = $db->query("SELECT DISTINCT campaign_name FROM campaign_metrics WHERE campaign_id IN ($placeholders) ORDER BY campaign_name", $campIds)->fetchAll();
            $campNome = implode(', ', array_column($campRows, 'campaign_name'));
            // 2. Se não achou no banco, busca direto na API Meta
            if (empty($campNome) && !empty($report['meta_token'])) {
                $names = [];
                foreach ($campIds as $cid) {
                    $url = "https://graph.facebook.com/".META_API_VERSION."/{$cid}?fields=name&access_token=".urlencode($report['meta_token']);
                    $ch  = curl_init($url);
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true]);
                    $res = json_decode(curl_exec($ch), true);
                    curl_close($ch);
                    if (!empty($res['name'])) $names[] = $res['name'];
                }
                $campNome = implode(', ', $names);
            }
        }
        // Gera link público para a variável {link} no envio manual
        $linkManual = '';
        try {
            $shareTokenM = $report['share_token'] ?? null;
            if (!$shareTokenM) {
                $shareTokenM = bin2hex(random_bytes(16));
                $db->query("UPDATE reports SET share_token=? WHERE id=?", [$shareTokenM, $report['id']]);
            }
            $tplIdM = (int)($report['pdf_tpl_id'] ?? 0);
            $tplParamM = $tplIdM ? '&tpl=' . $tplIdM : '';
            $accentParamM = '';
            try {
                if ($tplIdM) {
                    $tplRowM = $db->query("SELECT config FROM pdf_templates WHERE id=? AND user_id=?", [$tplIdM, $uid])->fetch();
                    if ($tplRowM && !empty($tplRowM['config'])) {
                        $tplCfgM = json_decode($tplRowM['config'], true) ?: [];
                        $accentParamM = !empty($tplCfgM['palette']['accent']) ? '&ac=' . urlencode($tplCfgM['palette']['accent']) : '';
                    }
                }
                // Sem template selecionado no relatório = link sem template
            } catch (\Throwable $_tam) {}
            $slugM = self::makeSlug(trim($report['client_company'] ?? '') ?: trim($report['client_name'] ?? '') ?: trim($report['title'] ?? ''));
            $nParamM = $slugM ? '&n=' . urlencode($slugM) : '';
            $linkManual = APP_URL . '/r?t=' . $shareTokenM . $tplParamM . $nParamM . $accentParamM;
        } catch (\Throwable $_tlm) {}

        $msgFinal = self::buildMessage($report['message_text'], [
            'metrics'        => $metrics,
            'periodo'        => $periodo_str,
            'account_name'   => $report['account_name']   ?? '',
            'client_name'    => $report['client_name']    ?? '',
            'client_company' => $report['client_company'] ?? '',
            'observacoes'    => '',
            'campaign_name'  => $campNome,
            'link'           => $linkManual,
        ]);

        // Determina destino: grupo ou número
        $recvType   = $report['recv_type'] ?? 'phone';
        $groupId    = $report['group_id']  ?? '';
        $groupInst  = $report['group_instance'] ?? '';

        if ($recvType === 'group' && $groupId) {
            // Usa instância do grupo se disponível
            $instName = !empty($groupInst) ? $groupInst : $wp['instance_name'];
            $result   = self::sendWhatsAppGroup($instName, $groupId, $msgFinal);
        } else {
            $dest   = $phone ?: $report['recipient_phone'];
            $result = $this->sendWhatsApp($wp['instance_name'], $dest, $msgFinal);
        }

        if ($result['ok']) {
            $db->query("UPDATE reports SET sent_whatsapp=1, sent_at=?, last_send_status='ok', last_send_error=NULL WHERE id=?", [date('Y-m-d H:i:s'), $reportId]);

            // Envia mensagem complementar se existir
            $followupText = trim($report['followup_message'] ?? '');

            // LOG TEMPORÁRIO — remover após confirmar funcionamento
            error_log("[FOLLOWUP DEBUG] report_id={$reportId} followup_text=".substr($followupText,0,80)." recvType={$recvType} groupId={$groupId}");

            if ($followupText !== '') {
                // Aplica substituição de variáveis igual à mensagem principal
                $followupFinal = self::buildMessage($followupText, [
                    'metrics'        => $metrics,
                    'periodo'        => $periodo_str,
                    'account_name'   => $report['account_name']   ?? '',
                    'client_name'    => $report['client_name']    ?? '',
                    'client_company' => $report['client_company'] ?? '',
                    'observacoes'    => '',
                    'campaign_name'  => $campNome,
                    'link'           => $linkManual,
                ]);
                sleep(1); // pausa de 1s entre mensagens
                if ($recvType === 'group' && $groupId) {
                    $instName = !empty($groupInst) ? $groupInst : $wp['instance_name'];
                    $followupResult = self::sendWhatsAppGroup($instName, $groupId, $followupFinal);
                } else {
                    $followupResult = $this->sendWhatsApp($wp['instance_name'], $dest ?? $report['recipient_phone'], $followupFinal);
                }
                // LOG TEMPORÁRIO — remover após confirmar funcionamento
                error_log("[FOLLOWUP DEBUG] send result=".json_encode($followupResult ?? []));
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            try {
                $destinatario = ($recvType === 'group' && $groupId) ? $groupId : ($dest ?? $report['recipient_phone'] ?? '');
                $db->query(
                    "INSERT INTO report_logs (report_id,user_id,status,destinatario,tipo_envio,report_title,client_name,company,periodo,canal) VALUES (?,?,'enviado',?,'manual',?,?,?,?,?)",
                    [
                        $reportId, $uid, $destinatario,
                        $report['title'] ?? '',
                        $report['client_name'] ?? '',
                        $report['client_company'] ?? '',
                        $periodo_str ?? '',
                        ($recvType === 'group') ? 'grupo' : 'numero',
                    ]
                );
            } catch (\Throwable $_rl) {}
            flash('success', 'Relatório enviado com sucesso!');
        } else {
            $db->query("UPDATE reports SET last_send_status='error', last_send_error=? WHERE id=?",
                [mb_substr($result['error'], 0, 255), $reportId]);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $result['error']]);
                exit;
            }
            flash('error', 'Erro ao enviar: '.$result['error']);
        }
        redirect('/reports');
    }

    /**
     * Monta todos os formatos possíveis de número para tentar envio
     * Suporta: Brasil (55) com/sem nono dígito, EUA (1), e outros países
     */
    private static function buildPhoneVariants(string $phone): array {
        $phone = preg_replace('/\D/', '', $phone);
        $variants = [];

        // Detecta país pelo prefixo DDI
        if (str_starts_with($phone, '55') && strlen($phone) >= 12) {
            // BRASIL
            $ddd    = substr($phone, 2, 2);
            $numero = substr($phone, 4);

            // Com 9 dígito (celular moderno)
            if (strlen($numero) === 9) {
                $variants[] = $phone;                          // 5581999999999  (com 9)
                $variants[] = '55'.$ddd.substr($numero, 1);   // 558199999999   (sem 9, antigo)
            }
            // Sem 9 dígito (número antigo ou fixo)
            elseif (strlen($numero) === 8) {
                $variants[] = '55'.$ddd.'9'.$numero;          // 5581999999999  (adiciona 9)
                $variants[] = $phone;                          // 558199999999   (como veio)
            } else {
                $variants[] = $phone;
            }

        } elseif (str_starts_with($phone, '1') && strlen($phone) >= 11) {
            // EUA / CANADÁ — formato fixo, sem variação
            $variants[] = $phone;

        } else {
            // Outros países — usa como veio
            $variants[] = $phone;
        }

        return array_unique($variants);
    }

    public static function sendWhatsAppStatic(string $instanceName, string $phone, string $message): array {
        // Se vier um JID de grupo por engano, redireciona para sendWhatsAppGroup
        if (strpos($phone, '@g.us') !== false) {
            return self::sendWhatsAppGroup($instanceName, $phone, $message);
        }

        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) < 10) return ['ok'=>false, 'error'=>'Telefone inválido: '.$phone];

        $baseUrl  = rtrim(EVOLUTION_API_URL, '/');
        $url      = $baseUrl.'/message/sendText/'.$instanceName;
        $variants = self::buildPhoneVariants($phone);

        $lastRes  = '';
        $lastCode = 0;

        // Tenta cada variante de número com cada formato de body (v2 e v1)
        foreach ($variants as $number) {
            $bodies = [
                // Formato v2 (Evolution API 2.x) — sem @s.whatsapp.net
                json_encode(['number'=>$number, 'text'=>$message]),
                // Formato v2 alternativo — com @s.whatsapp.net
                json_encode(['number'=>$number.'@s.whatsapp.net', 'text'=>$message]),
                // Formato v1 (Evolution API 1.x) — com @s.whatsapp.net
                json_encode(['number'=>$number.'@s.whatsapp.net', 'textMessage'=>['text'=>$message]]),
            ];

            foreach ($bodies as $body) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'apikey: '.EVOLUTION_API_KEY,
                    ],
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $res      = curl_exec($ch);
                $lastCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);

                if ($curlErr) return ['ok'=>false, 'error'=>'cURL: '.$curlErr];

                $data = json_decode($res, true);
                if ($lastCode >= 200 && $lastCode < 300) {
                    return ['ok'=>true, 'number_used'=>$number, 'response'=>$data];
                }
                $lastRes = $res;
            }
        }

        // Todos os formatos falharam
        $data   = json_decode($lastRes, true);
        $errMsg = $data['message'] ?? $data['error'] ?? $data['response']['message'] ?? "HTTP $lastCode";
        if (is_array($errMsg)) $errMsg = implode(', ', $errMsg);
        return ['ok'=>false, 'error'=>$errMsg.' (HTTP '.$lastCode.') — tentados: '.implode(', ', $variants)];
    }

    private function sendWhatsApp(string $instance, string $phone, string $message): array {
        $maxTries = 3;
        $result   = ['ok'=>false,'error'=>''];
        for ($try = 1; $try <= $maxTries; $try++) {
            $result = self::sendWhatsAppStatic($instance, $phone, $message);
            if ($result['ok']) return $result;
            if ($try < $maxTries) usleep(5000000);
        }
        return $result;
    }

    /**
     * Envia mensagem para um grupo do WhatsApp via JID (ex: 120363xxxxxx@g.us)
     * Não trata o JID como número de telefone — mantém o formato original
     */
    public static function sendWhatsAppGroup(string $instanceName, string $groupJid, string $message): array {
        $baseUrl = rtrim(EVOLUTION_API_URL, '/');
        $url     = $baseUrl . '/message/sendText/' . $instanceName;

        $bodies = [
            // Evolution API v2
            json_encode(['number' => $groupJid, 'text' => $message]),
            // Evolution API v1
            json_encode(['number' => $groupJid, 'textMessage' => ['text' => $message]]),
        ];

        $lastRes  = '';
        $lastCode = 0;

        foreach ($bodies as $body) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . EVOLUTION_API_KEY,
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $res      = curl_exec($ch);
            $lastCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) return ['ok' => false, 'error' => 'cURL: ' . $curlErr];

            $data = json_decode($res, true);
            if ($lastCode >= 200 && $lastCode < 300) {
                return ['ok' => true, 'group' => $groupJid, 'response' => $data];
            }
            $lastRes = $res;
        }

        $data   = json_decode($lastRes, true);
        $errMsg = $data['message'] ?? $data['error'] ?? "HTTP $lastCode";
        if (is_array($errMsg)) $errMsg = implode(', ', $errMsg);
        return ['ok' => false, 'error' => "Grupo $groupJid — $errMsg (HTTP $lastCode)"];
    }

    public function toggleStatus(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status']??'', ['active','paused']) ? $_POST['status'] : 'active';
        $db  = Database::getInstance();
        // Busca send_time e send_days salvos para recalcular corretamente
        $rep = $db->query("SELECT id, frequency, send_time, send_days FROM reports WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if (!$rep) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }
        // Se ativando, recalcula o próximo envio usando o horário e dias configurados pelo usuário
        if ($status === 'active') {
            $sendTime = !empty($rep['send_time']) ? $rep['send_time'] : '08:00';
            $sendDays = !empty($rep['send_days']) ? $rep['send_days'] : '1,2,3,4,5';
            $next = self::calcNextSend($sendTime, $sendDays, $rep['frequency']);
            $db->query("UPDATE reports SET status=?, next_send_at=? WHERE id=? AND user_id=?", [$status, $next, $id, $uid]);
        } else {
            $db->query("UPDATE reports SET status=? WHERE id=? AND user_id=?", [$status, $id, $uid]);
        }
        echo json_encode(['success'=>true]);
    }

    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        Database::getInstance()->query("DELETE FROM reports WHERE id=? AND user_id=?", [$id,$uid]);
        flash('success','Relatório excluído.');
        redirect('/reports');
    }

    public function apiData(): void {
        requireAuth();
        $uid     = currentUser()['id'];
        $db      = Database::getInstance();
        $accId   = (int)($_GET['account_id'] ?? 0);
        $start   = sanitize($_GET['start'] ?? date('Y-m-01'));
        $end     = sanitize($_GET['end']   ?? date('Y-m-d'));

        $rows = $db->query(
            "SELECT date, SUM(spend) spend, SUM(clicks) clicks, SUM(impressions) impressions, SUM(conversions) conversions
             FROM campaign_metrics cm JOIN ad_accounts aa ON cm.ad_account_id=aa.id
             WHERE aa.user_id=? AND cm.ad_account_id=? AND cm.date BETWEEN ? AND ?
             GROUP BY date ORDER BY date",
            [$uid,$accId,$start,$end]
        )->fetchAll();

        jsonResponse(['success'=>true,'data'=>$rows]);
    }

    public function edit(): void { $this->index(); }

    public function update(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id  = (int)($_POST['id'] ?? 0);

        // Garante que a coluna followup_message existe
        try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS followup_message TEXT NULL DEFAULT NULL"); } catch (\Throwable $e) {}

        $sendDays = sanitize($_POST['send_days'] ?? '1,2,3,4,5');
        $sendTime = sanitize($_POST['send_time'] ?? '08:00');
        $freq     = sanitize($_POST['frequency'] ?? 'once');
        $nextSend = self::calcNextSend($sendTime, $sendDays, $freq);
        $status   = in_array($freq,['daily','weekly','monthly']) ? 'active' : 'scheduled';

        $periodType = sanitize($_POST['period_type'] ?? 'last_7_days');
        if ($periodType === 'custom') {
            $cs = sanitize($_POST['custom_start'] ?? '');
            $ce = sanitize($_POST['custom_end']   ?? '');
            if ($cs && $ce) { $periodType = 'custom|'.$cs.'|'.$ce; }
        }

        $campIds      = sanitize($_POST['camp_ids']        ?? '');
        $campLabels   = sanitize($_POST['camp_labels']    ?? '');
        $followupMsg  = $_POST['followup_message']        ?? '';
        $recvType     = sanitize($_POST['recv_type']      ?? 'phone');
        $groupId   = sanitize($_POST['group_id']         ?? '');
        $groupInst = sanitize($_POST['group_instance']   ?? '');
        $wpId      = (int)($_POST['whatsapp_id']         ?? 0);

        // Resolve recipient_phone baseado no tipo de recebedor
        $phone = sanitize($_POST['recipient_phone'] ?? '');
        if ($recvType === 'group' && $groupId) {
            $phone = $groupId; // group JID
        } elseif ($recvType === 'client') {
            $clientId = (int)($_POST['client_id'] ?? 0);
            if ($clientId) {
                $cli = $db->query("SELECT phone FROM clients WHERE id=? AND user_id=?",[$clientId,$uid])->fetch();
                if ($cli && $cli['phone']) $phone = $cli['phone'];
            }
        }

        // Detecta instância do grupo se não informada
        if ($recvType === 'group' && $groupInst && !$wpId) {
            $inst = $db->query("SELECT id FROM whatsapp_instances WHERE instance_name=? AND user_id=?",[$groupInst,$uid])->fetch();
            if ($inst) $wpId = $inst['id'];
        }

        $pdfTplIdUpdate = (int)($_POST['pdf_tpl_id'] ?? 0);

        $db->query(
            "UPDATE reports SET title=?,recipient_phone=?,period_type=?,frequency=?,
             send_time=?,send_days=?,objetivo=?,camp_ids=?,camp_labels=?,message_text=?,followup_message=?,
             whatsapp_id=?,recv_type=?,group_id=?,group_instance=?,group_name=?,
             next_send_at=?,status=?,pdf_tpl_id=?,client_id=?,ad_account_id=?,platform=?,
             last_send_status=NULL,last_send_error=NULL,updated_at=NOW()
             WHERE id=? AND user_id=?",
            [
                sanitize($_POST['title']??''),
                $phone,
                $periodType,
                $freq, $sendTime, $sendDays,
                sanitize($_POST['objetivo']??'todos'),
                $campIds ?: null,
                $campLabels ?: null,
                $_POST['message_text']??'',
                $followupMsg ?: null,
                $wpId ?: null,
                $recvType,
                $groupId ?: null,
                $groupInst ?: null,
                sanitize($_POST['group_name'] ?? '') ?: null,
                $nextSend, $status, $pdfTplIdUpdate?:null,
                // #fix — salva client_id, ad_account_id e platform ao editar
                (int)($_POST['client_id']??0) ?: null,
                (int)($_POST['ad_account_id']??0) ?: null,
                sanitize($_POST['platform']??'meta'),
                $id, $uid
            ]
        );
        flash('success','Relatório atualizado!');
        redirect('/reports');
    }

    public function preview(): void { $this->index(); }

    // Busca métricas SEM filtro de status — inclui campanhas pausadas
    public static function fetchMetricsMetaAllStatus(string $accountId, string $token, string $start, string $end): ?array {
        if (!$accountId || !$token) return null;

        // A API do Meta limita time_range a ~37 meses por request.
        // Para períodos longos (Máximo), quebramos em chunks anuais e somamos.
        $chunks = [];
        $chunkStart = new \DateTime($start);
        $chunkEnd   = new \DateTime($end);
        $today      = new \DateTime('today');
        if ($chunkEnd > $today) $chunkEnd = $today;

        // Dividir em períodos de até 12 meses
        $cur = clone $chunkStart;
        while ($cur <= $chunkEnd) {
            $segEnd = clone $cur;
            $segEnd->modify('+11 months')->modify('last day of this month');
            if ($segEnd > $chunkEnd) $segEnd = clone $chunkEnd;
            $chunks[] = [$cur->format('Y-m-d'), $segEnd->format('Y-m-d')];
            $cur = clone $segEnd;
            $cur->modify('+1 day');
        }

        $fields = implode(',', [
            'impressions','clicks','spend','reach',
            'actions','action_values',
        ]);

        // Agrega todos os chunks
        $spend = 0; $impressions = 0; $clicks = 0; $reach = 0;
        $actionMap = []; $actionValueMap = [];

        foreach ($chunks as [$segStart, $segEnd]) {
            $timeRange = json_encode(['since'=>$segStart,'until'=>$segEnd]);
            $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}/insights"
                 . "?fields="    . urlencode($fields)
                 . "&time_range=". urlencode($timeRange)
                 . "&level=campaign"
                 . "&limit=500"
                 . "&access_token=" . urlencode($token);

            // Paginação completa por chunk
            $nextUrl = $url;
            for ($p = 0; $p < 20 && $nextUrl; $p++) {
                $ch = curl_init($nextUrl);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
                $res  = json_decode(curl_exec($ch), true);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code !== 200 || empty($res['data'])) break;
                foreach ($res['data'] as $row) {
                    $spend       += (float)($row['spend']       ?? 0);
                    $impressions += (int)  ($row['impressions'] ?? 0);
                    $clicks      += (int)  ($row['clicks']      ?? 0);
                    $reach       += (int)  ($row['reach']       ?? 0);
                    foreach ($row['actions']       ?? [] as $a) {
                        $actionMap[$a['action_type']]      = ($actionMap[$a['action_type']]      ?? 0) + (float)$a['value'];
                    }
                    foreach ($row['action_values'] ?? [] as $a) {
                        $actionValueMap[$a['action_type']] = ($actionValueMap[$a['action_type']] ?? 0) + (float)$a['value'];
                    }
                }
                $nextUrl = $res['paging']['next'] ?? null;
            }
        }

        if ($spend == 0 && $impressions == 0) return null;

        // messaging_conversation_started_7d = "Resultado" exibido no painel da Meta
        // total_messaging_connection = conexões totais (inclui recorrentes, não é o painel)
        // messaging_first_reply = primeiras respostas (sem atribuição de janela)
        $msg = (int)($actionMap['onsite_conversion.messaging_conversation_started_7d'] ?? 0);
        if (!$msg) $msg = (int)($actionMap['onsite_conversion.messaging_first_reply'] ?? 0);
        if (!$msg) $msg = (int)($actionMap['onsite_conversion.total_messaging_connection'] ?? 0);

        $leads_fb = (int)($actionMap['leadgen_grouped'] ?? $actionMap['onsite_conversion.lead_grouped'] ?? 0);
        $leads_gn = (int)($actionMap['lead'] ?? 0);
        $leads    = $leads_fb ?: ($leads_gn ?: (int)($actionMap['offsite_conversion.fb_pixel_lead'] ?? 0));
        $purchase = (int)($actionMap['purchase'] ?? $actionMap['omni_purchase'] ?? 0);
        $revenue  = (float)($actionValueMap['purchase'] ?? 0);
        $roas     = $spend > 0 && $revenue > 0 ? round($revenue/$spend, 2) : 0;
        $conv     = max($leads, $purchase, (int)($actionMap['offsite_conversion.fb_pixel_lead'] ?? 0));
        $profV    = (int)($actionMap['ig_profile_visit'] ?? $actionMap['profile_visit'] ?? 0);

        return [
            'spend'             => round($spend, 2),
            'impressions'       => $impressions,
            'clicks'            => $clicks,
            'reach'             => $reach,
            'ctr'               => $impressions > 0 ? round($clicks/$impressions*100, 2) : 0,
            'cpc'               => $clicks > 0      ? round($spend/$clicks, 2)           : 0,
            'cpm'               => $impressions > 0 ? round($spend/$impressions*1000, 2) : 0,
            'frequency'         => $reach > 0       ? round($impressions/$reach, 2)      : 0,
            'msg'               => $msg,
            'msg_all'           => $msg,
            'messages'          => $msg,
            'messaging_conversations' => $msg,
            'cmsg'              => $msg > 0   ? round($spend / $msg, 2)   : 0,
            'leads'             => $leads,
            'all_leads'         => $leads,
            'conversions'       => $conv,
            'cpl'               => $leads > 0 ? round($spend / $leads, 2) : 0,
            'purchase'          => $purchase,
            'purchases'         => $purchase,
            'profile_visit'     => $profV,
            'profile_visits'    => $profV,
            'custo_por_visita'  => $profV > 0 ? round($spend / $profV, 2) : 0,
            'roas'              => $roas,
            'revenue'           => $revenue,
            'link_click'        => $clicks,
            'all_actions'       => $actionMap,
            '_source'           => 'api_all_campaigns',
        ];
    }

    public static function fetchMetricsMeta(string $accountId, string $token, string $start, string $end, array $campIds=[]): ?array {
        if (!$accountId || !$token) return null;

        // Campos completos para buscar TODAS as métricas
        $fields = implode(',', [
            'impressions','clicks','spend','reach','cpm','cpc','ctr','frequency',
            'actions','action_values','unique_clicks','cost_per_unique_click',
            'outbound_clicks','outbound_clicks_ctr',
            'video_p25_watched_actions','video_p50_watched_actions',
            'video_p75_watched_actions','video_p95_watched_actions',
            'video_p100_watched_actions','video_avg_time_watched_actions',
            'video_thruplay_watched_actions',
            'instagram_profile_visits',
        ]);

        // Monta URL — level=campaign para capturar instagram_profile_visits por campanha
        $timeRange = json_encode(['since'=>$start,'until'=>$end]);
        $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}/insights"
             . "?fields="   . urlencode($fields)
             . "&time_range=" . urlencode($timeRange)
             . "&level=campaign"
             . "&limit=500"
             . "&access_token=" . urlencode($token);

        // Filtra por campanhas específicas ou apenas ativas
        if (!empty($campIds)) {
            // Campanhas específicas selecionadas no relatório
            $filtering = json_encode([
                ['field'=>'campaign.id','operator'=>'IN','value'=>array_values($campIds)]
            ]);
        } elseif (in_array('__SKIP_FILTER__', $campIds) || in_array('__ALL_STATUS__', $campIds)) {
            // Modo especial: SEM filtering — pega todos os dados do período
            $campIds = [];
            $filtering = null; // sem filtro
        } else {
            // Sem seleção: só campanhas ATIVAS (exclui pausadas e arquivadas)
            $filtering = json_encode([
                ['field'=>'effective_status','operator'=>'IN',
                 'value'=>['ACTIVE']]
            ]);
        }
        if ($filtering !== null) {
            $url .= "&filtering=" . urlencode($filtering);
        }

        // Paginação: coleta todos os dados
        $allData  = [];
        $nextUrl  = $url;
        for ($p=0; $p<20 && $nextUrl; $p++) {
            $ch = curl_init($nextUrl);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true]);
            $res  = json_decode(curl_exec($ch), true);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || empty($res['data'])) break;
            foreach ($res['data'] as $row) $allData[] = $row;
            $nextUrl = $res['paging']['next'] ?? null;
        }

        if (empty($allData)) {
            return null;
        }

        // Agrega dados de todas as campanhas (como o n8n faz)
        $d = [
            'spend' => 0, 'impressions' => 0, 'clicks' => 0, 'reach' => 0,
            'cpm' => 0, 'cpc' => 0, 'ctr' => 0, 'frequency' => 0,
            'instagram_profile_visits' => 0,
            'outbound_clicks' => [],
        ];
        $actionMap      = [];
        $actionValueMap = [];
        $campCount      = 0;

        foreach ($allData as $row) {
            $d['spend']       += (float)($row['spend']       ?? 0);
            $d['impressions'] += (int)  ($row['impressions'] ?? 0);
            $d['clicks']      += (int)  ($row['clicks']      ?? 0);
            $d['reach']       += (int)  ($row['reach']       ?? 0);
            // instagram_profile_visits é campo direto (como n8n usa)
            $d['instagram_profile_visits'] += (int)($row['instagram_profile_visits'] ?? 0);
            // outbound_clicks é array
            foreach ($row['outbound_clicks'] ?? [] as $oc) {
                $d['outbound_clicks'][] = $oc;
            }
            // actions — agrega por tipo
            foreach ($row['actions'] ?? [] as $a) {
                $actionMap[$a['action_type']] = ($actionMap[$a['action_type']] ?? 0) + (float)$a['value'];
            }
            foreach ($row['action_values'] ?? [] as $a) {
                $actionValueMap[$a['action_type']] = ($actionValueMap[$a['action_type']] ?? 0) + (float)$a['value'];
            }
            // vídeo — agrega
            foreach (['video_p25_watched_actions','video_p50_watched_actions','video_p75_watched_actions',
                      'video_p95_watched_actions','video_p100_watched_actions',
                      'video_thruplay_watched_actions','video_avg_time_watched_actions'] as $vf) {
                if (!isset($d[$vf])) $d[$vf] = [['value'=>0]];
                $v = is_array($row[$vf] ?? null)
                    ? (float)($row[$vf][0]['value'] ?? 0)
                    : 0;
                $d[$vf][0]['value'] = (float)($d[$vf][0]['value'] ?? 0) + $v;
            }
            $campCount++;
        }

        // frequency e CPM recalculados
        if ($campCount > 0) {
            $d['frequency'] = $d['impressions'] > 0 && $d['reach'] > 0
                ? $d['impressions'] / $d['reach'] : 0;
        }

        // Conversões: soma de todos os tipos relevantes
        $convTypes = ['lead','purchase','complete_registration','offsite_conversion.fb_pixel_lead',
                      'offsite_conversion.fb_pixel_purchase','onsite_web_lead','contact','schedule',
                      'submit_application','donate','find_location','start_trial','subscribe',
                      'add_payment_info','initiate_checkout'];
        $conversions = 0;
        foreach ($convTypes as $t) {
            $conversions += (int)($actionMap[$t] ?? 0);
        }
        if ($conversions === 0) {
            // Fallback: usa o total de todas as actions se não encontrou específicas
            $conversions = (int)($actionMap['offsite_conversion'] ?? 0);
        }

        $revenue      = (float)($actionValueMap['purchase'] ?? 0);
        $leads_meta   = (int)($actionMap['lead'] ?? 0);
        $leads_fb     = (int)($actionMap['leadgen_grouped'] ?? $actionMap['onsite_conversion.lead_grouped'] ?? 0);
        // Prioridade: leadgen_grouped = formulário nativo (valor exibido no painel Meta como "Resultados")
        // Fallback: lead genérico (pixel offsite) → offsite_conversion.fb_pixel_lead
        $leads        = $leads_fb ?: ($leads_meta ?: (int)($actionMap['offsite_conversion.fb_pixel_lead'] ?? 0));
        $purchase     = (int)($actionMap['purchase'] ?? $actionMap['offsite_conversion.fb_pixel_purchase'] ?? 0);
        $app_purchase = (int)($actionMap['app_purchase'] ?? $actionMap['omni_purchase'] ?? 0);
        $app_purchase_value = (float)($actionValueMap['app_purchase'] ?? $actionValueMap['omni_purchase'] ?? 0);
        $cart         = (int)($actionMap['add_to_cart'] ?? $actionMap['omni_add_to_cart'] ?? 0);
        $checkout     = (int)($actionMap['initiate_checkout'] ?? $actionMap['omni_initiated_checkout'] ?? 0);
        $add_pay      = (int)($actionMap['add_payment_info'] ?? 0);
        $contact      = (int)($actionMap['contact'] ?? 0);
        $complete_reg = (int)($actionMap['complete_registration'] ?? 0);
        $conv_pixel   = (int)($actionMap['offsite_conversion.fb_pixel_lead'] ?? 0);
        $conv_custom  = (int)($actionMap['offsite_conversion'] ?? 0);
        $msg_new      = (int)($actionMap['onsite_conversion.messaging_first_reply'] ?? 0);
        // {msg} = messaging_conversation_started_7d = "Resultado" do painel da Meta
        // É o número que aparece na coluna "Resultados" para campanhas de mensagem
        // total_messaging_connection inclui recorrentes e não corresponde ao painel
        $msg_all      = (int)($actionMap['onsite_conversion.messaging_conversation_started_7d'] ?? 0);
        if ($msg_all === 0) {
            // Fallback: first_reply
            $msg_all = $msg_new;
        }
        if ($msg_all === 0) {
            // Último fallback: total_messaging_connection
            $msg_all = (int)($actionMap['onsite_conversion.total_messaging_connection'] ?? 0);
        }
        $call_20      = (int)($actionMap['call_20_seconds'] ?? 0);
        $call_60      = (int)($actionMap['call_60_seconds'] ?? 0);
        $click_call   = (int)($actionMap['click_to_call_call_confirm'] ?? $actionMap['click_to_call'] ?? 0);
        $click_call_c = (int)($actionMap['phone_call_s2s_7d'] ?? 0);
        $conv_lead_px = (int)($actionMap['offsite_conversion.fb_pixel_lead'] ?? 0);
        $pageview     = (int)($actionMap['landing_page_view'] ?? $actionMap['page_view'] ?? 0);
        // outbound_clicks é array de arrays — soma todos os valores
        $outboundTotal = 0;
        foreach ($d['outbound_clicks'] ?? [] as $oc) {
            $outboundTotal += (int)($oc['value'] ?? 0);
        }
        $click_saida = $outboundTotal;
        $vplay        = (int)($actionMap['video_view'] ?? 0);

        $spend       = (float)($d['spend'] ?? 0);
        $clicks      = (int)($d['clicks'] ?? 0);
        $impressions = (int)($d['impressions'] ?? 0);
        $reach       = (int)($d['reach'] ?? 0);
        $frequency   = (float)($d['frequency'] ?? 0);
        $cpm         = $impressions > 0 ? ($spend / $impressions * 1000) : 0;
        $cpc         = $clicks > 0     ? ($spend / $clicks) : 0;
        $ctr         = $impressions > 0 ? ($clicks / $impressions * 100) : 0;

        $link_click   = (int)($actionMap['link_click'] ?? 0);
        $engajamento  = (int)($actionMap['post_engagement'] ?? $actionMap['page_engagement'] ?? 0);
        // Visitas ao perfil IG
        // O n8n usa o campo direto 'instagram_profile_visits' da resposta — é o mais preciso
        // Fallback: actions[], depois profile_visits
        // instagram_profile_visits é o campo correto e válido para o endpoint /insights
        // Visitas ao perfil: instagram_profile_visits com level=campaign pode subcontar.
        // Fazemos chamada extra com level=account que é o valor correto (igual ao painel Meta).
        $profile_v = (int)($d['instagram_profile_visits'] ?? 0);
        if (!$profile_v) {
            $profile_v = (int)($actionMap['ig_profile_visit']
                            ?? $actionMap['profile_visit']
                            ?? $actionMap['instagram_profile_visit']
                            ?? 0);
        }
        // Chamada extra removida — profile_visits já vem agregado na chamada principal
        $app_install  = (int)($actionMap['app_install'] ?? $actionMap['mobile_app_install'] ?? 0);
        $download     = (int)($actionMap['app_custom_event.fb_mobile_complete_registration'] ?? $actionMap['mobile_app_install'] ?? 0);
        $search_v     = (int)($actionMap['onsite_web_app_search'] ?? $actionMap['search'] ?? 0);

        // Vídeo
        $v25   = (int)(($d['video_p25_watched_actions'][0]['value']      ?? $d['video_p25_watched_actions']['value']      ?? 0));
        $v50   = (int)(($d['video_p50_watched_actions'][0]['value']      ?? $d['video_p50_watched_actions']['value']      ?? 0));
        $v75   = (int)(($d['video_p75_watched_actions'][0]['value']      ?? $d['video_p75_watched_actions']['value']      ?? 0));
        $v95   = (int)(($d['video_p95_watched_actions'][0]['value']      ?? $d['video_p95_watched_actions']['value']      ?? 0));
        $v100  = (int)(($d['video_p100_watched_actions'][0]['value']     ?? $d['video_p100_watched_actions']['value']     ?? 0));
        $thru  = (int)(($d['video_thruplay_watched_actions'][0]['value'] ?? $d['video_thruplay_watched_actions']['value'] ?? 0));
        $vavg  = round((float)($d['video_avg_time_watched_actions'][0]['value'] ?? $d['video_avg_time_watched_actions']['value'] ?? 0), 1);

        // Taxas derivadas
        $vview_p      = $impressions > 0 ? round($v100 / $impressions * 100, 2) : 0;
        $cr           = $link_click  > 0 ? round($pageview   / $link_click * 100, 2) : 0;
        $conv_msg_clk = $link_click  > 0 ? round($msg_all    / $link_click * 100, 2) : 0;
        $conv_lead_clk= $link_click  > 0 ? round($leads      / $link_click * 100, 2) : 0;
        $conv_checkout= $link_click  > 0 ? round($checkout   / $link_click * 100, 2) : 0;
        $conv_pg      = $link_click  > 0 ? round($leads      / $link_click * 100, 2) : 0;
        $conv_pg_vend = $link_click  > 0 ? round($purchase   / $link_click * 100, 2) : 0;
        $checkout_rate= $link_click  > 0 ? round($checkout   / $link_click * 100, 2) : 0;
        $tm           = $purchase    > 0 ? round($revenue    / $purchase, 2)          : 0;
        $fci_tm_rate  = $tm          > 0 ? round($cpc        / $tm * 100, 2)          : 0;
        $cpma         = $reach       > 0 ? round($spend      / $reach * 1000, 2)      : 0;

        return [
            // Básicos
            'impressions'       => $impressions,
            'clicks'            => $clicks,
            'spend'             => round($spend, 2),
            'reach'             => $reach,
            'cpm'               => round($cpm, 2),
            'cpc'               => round($cpc, 2),
            'ctr'               => round($ctr, 2),
            'frequency'         => round($frequency, 2),
            'link_click'        => $link_click,
            'click_saida'       => $click_saida,
            'pageview'          => $pageview,
            // Conversões
            'conversions'       => $conversions,
            'leads'             => $leads,
            'leads_meta'        => $leads_meta,
            'lead_facebook'     => $leads_fb,
            'purchase'          => $purchase,
            'app_purchase'      => $app_purchase,
            'app_purchase_value'=> round($app_purchase_value, 2),
            'cart'              => $cart,
            'inicheckout'       => $checkout,
            'add_payment_info'  => $add_pay,
            'contact'           => $contact,
            'complete_registration' => $complete_reg,
            'conv_pixel_custom' => $conv_pixel,
            'conversion_custom' => $conv_custom,
            'conversion_lead_pixel' => $conv_lead_px,
            'call_20_seconds'   => $call_20,
            'call_60_seconds'   => $call_60,
            'click_to_call'     => $click_call,
            'click_to_call_confirm' => $click_call_c,
            'revenue'           => round($revenue, 2),
            // Mensagens
            'msg'               => $msg_all,
            'msg_new'           => $msg_new,
            'msg_all'           => $msg_all,
            'messages'          => $msg_all,
            'messaging_conversations' => $msg_all,
            // Engajamento
            'engajamento'       => $engajamento,
            'app_install'       => $app_install,
            'comment'           => (int)($actionMap['comment'] ?? 0),
            'post_reaction'     => (int)($actionMap['post_reaction'] ?? 0),
            'post_save'         => (int)($actionMap['onsite_conversion.post_save'] ?? 0),
            // Tráfego
            'profile_visit'     => $profile_v,
            'profile_visits'    => $profile_v,
            'search'            => $search_v,
            'download'          => $download,
            'vplay'             => $vplay,
            // Custos derivados (calculados aqui para o buildMessage não precisar recalcular)
            'cmsg'              => $msg_all > 0  ? round($spend / $msg_all, 2)   : 0,
            'cpl'               => $leads > 0    ? round($spend / $leads, 2)     : 0,
            'all_leads'         => $leads,
            'custo_por_visita'  => $profile_v > 0 ? round($spend / $profile_v, 2) : 0,
            // Vídeo
            'view_25'   => $v25, 'view_50' => $v50, 'view_75' => $v75,
            'view_95'   => $v95, 'view_100'=> $v100,'thruplay' => $thru,
            'v_avg'     => $vavg,'vview_p' => $vview_p,
            // Taxas derivadas
            'cr'              => $cr,
            'conv_msg_click'  => $conv_msg_clk,
            'conv_lead_click' => $conv_lead_clk,
            'conv_checkout'   => $conv_checkout,
            'conv_pg'         => $conv_pg,
            'conv_pg_vendas'  => $conv_pg_vend,
            'checkout_rate'   => $checkout_rate,
            'tm'              => round($tm, 2),
            'fci_tm_rate'     => $fci_tm_rate,
            'cpma'            => $cpma,
            'all_actions'     => $actionMap,
        ];
    }

    /**
     * Retorna breakdown DIÁRIO de spend via API Meta (time_increment=1).
     * Usado pela projeção do dashboard para contar dias ativos reais no mês.
     * Cada item retornado: ['date_start'=>'Y-m-d', 'date_stop'=>'Y-m-d', 'spend'=>'12.34']
     * Respeita filtro de campanhas específicas (campIds) quando fornecido.
     */
    public static function fetchMetricsMetaDaily(
        string $accountId,
        string $token,
        string $start,
        string $end,
        array $campIds = []
    ): array {
        if (!$accountId || !$token) return [];

        $timeRange = json_encode(['since' => $start, 'until' => $end]);
        $url = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$accountId}/insights"
             . "?fields=spend"
             . "&time_range=" . urlencode($timeRange)
             . "&time_increment=1"
             . "&level=account"
             . "&limit=90"
             . "&access_token=" . urlencode($token);

        // Se há campanhas específicas, filtra por elas
        if (!empty($campIds)) {
            $filtering = json_encode([[
                'field'    => 'campaign.id',
                'operator' => 'IN',
                'value'    => array_values($campIds),
            ]]);
            $url .= '&filtering=' . urlencode($filtering);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$raw) return [];
        $res = json_decode($raw, true);
        return $res['data'] ?? [];
    }

    /**
     * Fallback: busca métricas diretamente do banco campaign_metrics
     * Usado quando a API Meta retorna null (token expirado, rate limit, etc.)
     */
    public static function fetchMetricsFromDB(int $adAccountId, string $start, string $end, array $campIds=[]): ?array {
        if (!$adAccountId) return null;
        $db = Database::getInstance();

        $params = [$adAccountId];
        $campWhere = '';
        if (!empty($campIds)) {
            $placeholders = implode(',', array_fill(0, count($campIds), '?'));
            $campWhere = " AND campaign_id IN ($placeholders)";
            $params = array_merge($params, $campIds);
        }
        $params[] = $start;
        $params[] = $end;

        $row = $db->query(
            "SELECT
                SUM(impressions)    AS impressions,
                SUM(clicks)         AS clicks,
                SUM(spend)          AS spend,
                SUM(reach)          AS reach,
                SUM(conversions)    AS conversions,
                SUM(revenue)        AS revenue,
                AVG(cpm)            AS cpm,
                AVG(cpc)            AS cpc,
                AVG(ctr)            AS ctr,
                SUM(profile_visits) AS profile_visits,
                SUM(messages)       AS messages,
                SUM(purchases)      AS purchases,
                SUM(leads)          AS leads,
                SUM(all_leads)      AS all_leads,
                SUM(engagement)     AS engagement,
                SUM(post_comments)  AS post_comments,
                SUM(post_reactions) AS post_reactions,
                SUM(post_saves)     AS post_saves,
                SUM(video_p25)      AS video_p25,
                SUM(video_p50)      AS video_p50,
                SUM(video_p75)      AS video_p75,
                SUM(video_p100)     AS video_p100,
                SUM(thruplay)       AS thruplay
             FROM campaign_metrics
             WHERE ad_account_id=?$campWhere AND date BETWEEN ? AND ?",
            $params
        )->fetch();

        if (!$row || (float)($row['spend'] ?? 0) == 0) return null;

        $spend       = (float)($row['spend']       ?? 0);
        $clicks      = (int)  ($row['clicks']      ?? 0);
        $impressions = (int)  ($row['impressions'] ?? 0);
        $reach       = (int)  ($row['reach']       ?? 0);
        $conversions = (int)  ($row['conversions'] ?? 0);
        $revenue     = (float)($row['revenue']     ?? 0);
        $cpm  = $impressions > 0 ? ($spend / $impressions * 1000) : (float)($row['cpm'] ?? 0);
        $cpc  = $clicks > 0     ? ($spend / $clicks) : (float)($row['cpc'] ?? 0);
        $ctr  = $impressions > 0 ? ($clicks / $impressions * 100) : (float)($row['ctr'] ?? 0);
        $roas = $spend > 0      ? round($revenue / $spend, 2) : 0;

        $profile_v   = (int)($row['profile_visits'] ?? 0);
        $messages    = (int)($row['messages'] ?? 0);
        $engagement  = (int)($row['engagement'] ?? 0);
        $cmsg        = $messages > 0 ? round($spend / $messages, 2) : 0;
        $cpl         = $conversions > 0 ? round($spend / $conversions, 2) : 0;
        $cpv         = $profile_v > 0  ? round($spend / $profile_v, 2)   : 0;
        $frequency   = $reach > 0 ? round($impressions / $reach, 2) : 0;

        return [
            'impressions'       => $impressions,
            'clicks'            => $clicks,
            'spend'             => round($spend, 2),
            'reach'             => $reach,
            'cpm'               => round($cpm, 2),
            'cpc'               => round($cpc, 2),
            'ctr'               => round($ctr, 2),
            'frequency'         => $frequency,
            'conversions'       => $conversions,
            'leads'             => (int)($row['leads'] ?? $conversions),
            'all_leads'         => (int)($row['all_leads'] ?? $conversions),
            'purchase'          => (int)($row['purchases'] ?? 0),
            'purchases'         => (int)($row['purchases'] ?? 0),
            'revenue'           => round($revenue, 2),
            'roas'              => $roas,
            'msg'               => $messages,
            'messages'          => $messages,
            'msg_all'           => $messages,
            'messaging_conversations' => $messages,
            'cmsg'              => $cmsg,
            'cpl'               => $cpl,
            'cpv'               => $cpv,
            'custo_por_visita'  => $cpv,
            'profile_visit'     => $profile_v,
            'profile_visits'    => $profile_v,
            'engajamento'       => $engagement,
            'engagement'        => $engagement,
            'post_engagement'   => $engagement,
            'comment'           => (int)($row['post_comments'] ?? 0),
            'post_reaction'     => (int)($row['post_reactions'] ?? 0),
            'post_save'         => (int)($row['post_saves'] ?? 0),
            'link_click'        => $clicks,
            'view_25'           => (int)($row['video_p25'] ?? 0),
            'view_50'           => (int)($row['video_p50'] ?? 0),
            'view_75'           => (int)($row['video_p75'] ?? 0),
            'view_95'           => 0,
            'view_100'          => (int)($row['video_p100'] ?? 0),
            'thruplay'          => (int)($row['thruplay'] ?? 0),
            'v_avg'             => 0,
            'all_actions'       => [],
            '_source'           => 'db',
        ];
    }

    // ── Cache de métricas — evita N×60 chamadas na listagem de relatórios ──────
    public static function fetchMetricsCached(
        string $accountId,
        string $token,
        string $start,
        string $end,
        array  $campIds,
        int    $adAccountId,
        int    $userId,
        int    $ttlMinutes = 15
    ): ?array {
        $db = Database::getInstance();
        $cacheKey = 'metrics_' . md5($accountId . $start . $end . implode(',', $campIds));
        try {
            $cached = $db->query(
                "SELECT payload FROM dashboard_cache
                 WHERE cache_key = ? AND user_id = ? AND expires_at > NOW() LIMIT 1",
                [$cacheKey, $userId]
            )->fetch();
            if ($cached) return json_decode($cached['payload'], true);
        } catch (\Throwable $e) {}

        $metrics = self::fetchMetricsMeta($accountId, $token, $start, $end, $campIds);
        if ($metrics === null && $adAccountId > 0) {
            $metrics = self::fetchMetricsFromDB($adAccountId, $start, $end, $campIds);
        }
        if ($metrics !== null) {
            try {
                $exp = date('Y-m-d H:i:s', strtotime("+{$ttlMinutes} minutes"));
                $db->query(
                    "INSERT INTO dashboard_cache (cache_key, user_id, payload, expires_at)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE payload=VALUES(payload), expires_at=VALUES(expires_at)",
                    [$cacheKey, $userId, json_encode($metrics), $exp]
                );
            } catch (\Throwable $e) {}
        }
        return $metrics;
    }

    public static function invalidateMetricsCache(int $userId): void {
        try {
            Database::getInstance()->query(
                "DELETE FROM dashboard_cache WHERE user_id = ?", [$userId]
            );
        } catch (\Throwable $e) {}
    }

    // ── Google Ads API — busca métricas reais via API v18 ────────────────────
    public static function fetchMetricsGoogle(string $customerId, string $accessToken, string $start, string $end, array $campIds=[]): ?array {
        if (!$customerId || !$accessToken) return null;

        $customerId = preg_replace('/[^0-9]/', '', $customerId);
        $devToken   = defined('GOOGLE_DEVELOPER_TOKEN') ? GOOGLE_DEVELOPER_TOKEN : '';
        if (!$devToken || str_contains($devToken, 'seu-google')) return null;

        // GAQL query
        $campFilter = '';
        if (!empty($campIds)) {
            $ids = implode(',', array_map('intval', $campIds));
            $campFilter = " AND campaign.id IN ($ids)";
        }
        $query = "SELECT "
            . "metrics.impressions,metrics.clicks,metrics.cost_micros,"
            . "metrics.conversions,metrics.conversions_value,"
            . "metrics.ctr,metrics.average_cpc,metrics.average_cpm,"
            . "metrics.video_views,metrics.video_quartile_p25_rate,"
            . "metrics.video_quartile_p50_rate,metrics.video_quartile_p75_rate,"
            . "metrics.video_quartile_p100_rate "
            . "FROM campaign "
            . "WHERE segments.date BETWEEN '$start' AND '$end'"
            . " AND campaign.status != 'REMOVED'"
            . $campFilter;

        $url = "https://googleads.googleapis.com/v18/customers/{$customerId}/googleAds:searchStream";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['query' => $query]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'developer-token: ' . $devToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$resp) return null;

        $data = json_decode($resp, true);
        if (empty($data)) return null;

        $impressions = 0; $clicks = 0; $costMicros = 0;
        $conversions = 0; $convValue = 0; $vViews = 0;
        $p25 = 0; $p50 = 0; $p75 = 0; $p100 = 0; $rows = 0;

        foreach ($data as $batch) {
            foreach ($batch['results'] ?? [] as $row) {
                $m = $row['metrics'] ?? [];
                $impressions += (int)($m['impressions']   ?? 0);
                $clicks      += (int)($m['clicks']        ?? 0);
                $costMicros  += (int)($m['costMicros']    ?? 0);
                $conversions += (float)($m['conversions'] ?? 0);
                $convValue   += (float)($m['conversionsValue'] ?? 0);
                $vViews      += (int)($m['videoViews']    ?? 0);
                $rows++;
            }
        }

        if ($rows === 0) return null;

        $spend = round($costMicros / 1_000_000, 2);
        if ($spend <= 0) return null;

        $ctr  = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;
        $cpc  = $clicks > 0     ? round($spend / $clicks, 2) : 0;
        $cpm  = $impressions > 0 ? round($spend / $impressions * 1000, 2) : 0;
        $roas = $spend > 0      ? round($convValue / $spend, 2) : 0;

        return [
            'impressions'  => $impressions,
            'clicks'       => $clicks,
            'link_click'   => $clicks,
            'spend'        => $spend,
            'reach'        => 0,
            'cpm'          => $cpm,
            'cpc'          => $cpc,
            'ctr'          => $ctr,
            'frequency'    => 0,
            'conversions'  => (int)$conversions,
            'leads'        => (int)$conversions,
            'purchase'     => 0,
            'revenue'      => round($convValue, 2),
            'roas'         => $roas,
            'msg'          => 0, 'engajamento' => 0,
            'vplay'        => $vViews,
            'video_views'  => $vViews,
            'view_25'      => 0, 'view_50' => 0, 'view_75' => 0,
            'view_95'      => 0, 'view_100' => 0,
            'thruplay'     => 0, 'v_avg'   => 0,
            'profile_visit'=> 0,
            'all_actions'  => [],
            '_source'      => 'google_api',
        ];
    }

    public static function fetchWpGroups(array $instances): array {
        $groups = [];
        foreach ($instances as $inst) {
            $res = EvolutionApi::fetchGroups($inst['instance_name']);
            if (is_array($res)) {
                foreach ($res as $g) {
                    $groups[] = [
                        'id'          => $g['id'] ?? '',
                        'name'        => $g['subject'] ?? $g['name'] ?? 'Grupo sem nome',
                        'instance_id' => $inst['id'],
                        'instance'    => $inst['instance_name'],
                    ];
                }
            }
        }
        return $groups;
    }

    public static function buildMessage(string $template, array $data): string {
        $m   = $data['metrics'] ?? [];
        $n   = function(float $v, int $dec=2): string { return number_format($v,$dec,',','.'); };
        $ni  = function(int $v): string { return number_format($v,0,',','.'); };

        $spend        = (float)($m['spend']       ?? 0);
        $revenue      = (float)($m['revenue']     ?? 0);
        $conv         = (int)  ($m['conversions'] ?? 0);
        $leads        = (int)  ($m['leads'] ?? $m['all_leads'] ?? $conv);
        $reach        = (int)  ($m['reach']       ?? 0);
        $impr         = (int)  ($m['impressions'] ?? 0);
        $clicks       = (int)  ($m['clicks']      ?? 0);
        $ctr          = (float)($m['ctr']         ?? ($impr>0 ? $clicks/$impr*100 : 0));
        $cpm          = (float)($m['cpm']         ?? ($impr>0 ? $spend/$impr*1000 : 0));
        $cpc_val      = (float)($m['cpc']         ?? ($clicks>0 ? $spend/$clicks : 0));
        $freq         = (float)($m['frequency']   ?? 0);
        $roas         = $spend > 0 ? round($revenue/$spend,2) : 0;
        $eng          = (int)  ($m['engajamento'] ?? $m['engagement'] ?? $m['page_engagement'] ?? 0);
        $msg_v        = (int)  ($m['msg'] ?? $m['messages'] ?? $m['messaging_conversations'] ?? 0);
        $tm_val       = (float)($m['tm']          ?? ($conv>0 && $revenue>0 ? $revenue/$conv : 0));
        $thruplay_v   = (int)  ($m['thruplay']    ?? 0);
        $profile_v_i  = (int)  ($m['profile_visit'] ?? $m['profile_visits'] ?? 0);
        $search_v_i   = (int)  ($m['search']      ?? 0);
        $app_inst_i   = (int)  ($m['app_install'] ?? 0);
        $pageview_v   = (int)  ($m['pageview']    ?? 0);
        $vview_p_val  = (float)($m['vview_p']     ?? 0);
        $conv_lead_px_i = (int)($m['conversion_lead_pixel'] ?? 0);

        $clientName  = $data['client_name']   ?? '';
        $clientCompany = $data['client_company'] ?? '';
        $accName    = $data['account_name'] ?? '';
        $periodo    = $data['periodo']      ?? '';
        $obs        = $data['observacoes']  ?? '';
        $campanha   = $data['campaign_name']?? '';
        $linkPublico = $data['link']        ?? '';

        // Mapa completo: suporta {var}, {var_sem_underscore} e <VAR>
        $pairs = [
            // ── Gerais ────────────────────────────────────────────
            'nome_cliente'      => $clientName,
            'customer_name'     => $clientName,
            'cliente'           => $clientName,
            'primeiro_nome'     => explode(' ', trim($clientName))[0],
            'first_name'        => explode(' ', trim($clientName))[0],
            'empresa'           => $clientCompany,
            'company'           => $clientCompany,
            'nome_empresa'      => $clientCompany,
            'periodo'           => $periodo,
            'data'              => $periodo,
            'hoje'              => date('d/m/Y'),
            'ontem'             => date('d/m/Y', strtotime('-1 day')),
            'yesterday'         => date('d/m/Y', strtotime('-1 day')),
            'hora'              => date('H:i'),
            'mes_atual'         => date('m/Y'),
            'mes_anterior'      => date('m/Y', strtotime('first day of last month')),
            'ano'               => date('Y'),
            'conta'             => $accName,
            'conta_anuncio'     => $accName,
            'ca'                => $accName,
            'campanha'          => $campanha,
            'observacoes'       => $obs,
            'saudacao'          => (function() {
                $h = (int)date('H');
                if ($h >= 5  && $h < 12) return 'Bom dia';
                if ($h >= 12 && $h < 18) return 'Boa tarde';
                return 'Boa noite';
            })(),
            'greeting'          => (function() {
                $h = (int)date('H');
                if ($h >= 5  && $h < 12) return 'Bom dia';
                if ($h >= 12 && $h < 18) return 'Boa tarde';
                return 'Boa noite';
            })(),
            'objective'         => $campanha,
            // ── Alcance / Impressões / Cliques ────────────────────
            'alcance'           => $ni($reach),
            'alcan'             => $ni($reach),
            'impressoes'        => $ni($impr),
            'imp'               => $ni($impr),
            'impress'           => $ni($impr),
            'cliques'           => $ni($clicks),
            'cliq'              => $ni($clicks),
            'clicks_all'        => $ni($clicks),
            'cliques_all'       => $ni($clicks),
            'clicks'            => $ni($clicks),
            'link_click'        => $ni((int)($m['link_click']  ?? $clicks)),
            'click_saida'       => $ni((int)($m['click_saida'] ?? 0)),
            'pageview'          => $ni((int)($m['pageview']    ?? 0)),
            'cpvp'              => ($pageview_v > 0 ? $n($spend/$pageview_v) : '0,00'),
            // ── Taxas ─────────────────────────────────────────────
            'ctr'               => $n($ctr),
            'ctr_all'           => $n($ctr),
            'frequencia'        => $n($freq),
            'cr'                => $n((float)($m['cr']              ?? 0)),
            'conv_msg_click'    => $n((float)($m['conv_msg_click']   ?? 0)),
            'conv_lead_click'   => $n((float)($m['conv_lead_click']  ?? 0)),
            'conv_checkout'     => $n((float)($m['conv_checkout']    ?? 0)),
            'conv_pg'           => $n((float)($m['conv_pg']          ?? 0)),
            'conv_pg_vendas'    => $n((float)($m['conv_pg_vendas']   ?? 0)),
            'checkout_rate'     => $n((float)($m['checkout_rate']    ?? 0)),
            'fci_tm_rate'       => $n((float)($m['fci_tm_rate']      ?? 0)),
            // ── Custos ────────────────────────────────────────────
            'cpm'               => $n($cpm),
            'cpc'               => $n($cpc_val),
            'cpma'              => $n((float)($m['cpma']         ?? 0)),
            'investimento'      => $n($spend),
            'inv'               => $n($spend),
            'total_spend'       => $n($spend),
            'clicks_all_cost'   => ($clicks   > 0 ? $n($spend/$clicks)   : '0,00'),
            'thruplay_cost'     => ($thruplay_v>0 ? $n($spend/$thruplay_v): '0,00'),
            'profile_visit_cost'=> ($profile_v_i>0? $n($spend/$profile_v_i):'0,00'),
            'custo_por_visita'  => ($profile_v_i>0? 'R$ '.$n($spend/$profile_v_i):'N/A'),
            'c_search'          => ($search_v_i >0 ? $n($spend/$search_v_i): '0,00'),
            // ── Conversões ────────────────────────────────────────
            'conversoes'        => $ni($conv),
            'conversao'         => $ni($conv),
            'conversion'        => $ni($conv),
            'conversions'       => $ni($conv),
            'results'           => $ni($conv),
            'leads'             => $ni($leads),
            'leads_meta'        => $ni((int)($m['leads_meta']   ?? $leads)),
            'lead_facebook'     => $ni((int)($m['lead_facebook']?? 0)),
            'all_leads'         => $ni($conv),
            'vendas'            => $ni((int)($m['purchase']     ?? $conv)),
            'vend'              => $ni((int)($m['purchase']     ?? $conv)),
            'purchase'          => $ni((int)($m['purchase']     ?? 0)),
            'app_purchase'      => $ni((int)($m['app_purchase'] ?? 0)),
            'app_purchase_value'=> $n((float)($m['app_purchase_value'] ?? 0)),
            'cart'              => $ni((int)($m['cart']         ?? 0)),
            'inicheckout'       => $ni((int)($m['inicheckout']  ?? 0)),
            'add_payment_info'  => $ni((int)($m['add_payment_info'] ?? 0)),
            'contact'           => $ni((int)($m['contact']      ?? 0)),
            'complete_registration' => $ni((int)($m['complete_registration'] ?? 0)),
            'conv_pixel_custom' => $ni((int)($m['conv_pixel_custom'] ?? 0)),
            'conversion_custom' => $ni((int)($m['conversion_custom']?? 0)),
            'conversion_lead_pixel' => $ni((int)($m['conversion_lead_pixel'] ?? 0)),
            'call_20_seconds'   => $ni((int)($m['call_20_seconds']   ?? 0)),
            'call_60_seconds'   => $ni((int)($m['call_60_seconds']   ?? 0)),
            'click_to_call'     => $ni((int)($m['click_to_call']     ?? 0)),
            'click_to_call_confirm' => $ni((int)($m['click_to_call_confirm'] ?? 0)),
            'fat'               => $n($revenue),
            'receita'           => $n($revenue),
            'purchase_revenue'  => $n($revenue),
            'roas'              => $n($roas),
            'tm'                => $n($tm_val),
            // Custo por conversão
            // {cpl} usa $leads (leadgen_grouped = formulário nativo) — bate com painel Meta
            'cpl'               => ($leads > 0 ? $n($spend/$leads)  : ($conv > 0 ? $n($spend/$conv) : '0,00')),
            'cpv'               => ($leads > 0 ? $n($spend/$leads)  : ($conv > 0 ? $n($spend/$conv) : '0,00')),
            'custo_result'      => ($leads > 0 ? $n($spend/$leads)  : ($conv > 0 ? $n($spend/$conv) : '0,00')),
            'results_cost'      => ($leads > 0 ? $n($spend/$leads)  : ($conv > 0 ? $n($spend/$conv) : '0,00')),
            'conversion_cost'   => ($leads > 0 ? $n($spend/$leads)  : ($conv > 0 ? $n($spend/$conv) : '0,00')),
            'all_leads_cost'    => ($conv  > 0 ? $n($spend/$conv)   : '0,00'),
            'leads_meta_cost'   => ($leads > 0 ? $n($spend/$leads)  : '0,00'),
            'lead_facebook_cost'=> ((int)($m['lead_facebook']??0)>0  ? $n($spend/(int)$m['lead_facebook']) : '0,00'),
            'conversion_lead_pixel_cost' => ($conv_lead_px_i>0 ? $n($spend/$conv_lead_px_i) : '0,00'),
            'cpcart'            => ((int)($m['cart']??0)>0       ? $n($spend/(int)$m['cart'])         : '0,00'),
            'cpcheck'           => ((int)($m['inicheckout']??0)>0? $n($spend/(int)$m['inicheckout'])  : '0,00'),
            'add_payment_info_cost' => ((int)($m['add_payment_info']??0)>0 ? $n($spend/(int)$m['add_payment_info']) : '0,00'),
            'contact_cost'      => ((int)($m['contact']??0)>0    ? $n($spend/(int)$m['contact'])      : '0,00'),
            'complete_registration_cost' => ((int)($m['complete_registration']??0)>0 ? $n($spend/(int)$m['complete_registration']) : '0,00'),
            'call_20_seconds_cost' => ((int)($m['call_20_seconds']??0)>0   ? $n($spend/(int)$m['call_20_seconds'])  : '0,00'),
            'call_60_seconds_cost' => ((int)($m['call_60_seconds']??0)>0   ? $n($spend/(int)$m['call_60_seconds'])  : '0,00'),
            'click_to_call_cost'   => ((int)($m['click_to_call']??0)>0     ? $n($spend/(int)$m['click_to_call'])    : '0,00'),
            'click_to_call_confirm_cost' => ((int)($m['click_to_call_confirm']??0)>0 ? $n($spend/(int)$m['click_to_call_confirm']) : '0,00'),
            'conv_pixel_custom_cost' => ((int)($m['conv_pixel_custom']??0)>0 ? $n($spend/(int)$m['conv_pixel_custom']) : '0,00'),
            'conversion_custom_cost' => ((int)($m['conversion_custom']??0)>0 ? $n($spend/(int)$m['conversion_custom']) : '0,00'),
            'app_install_cost'  => ($app_inst_i  > 0 ? $n($spend/$app_inst_i)  : '0,00'),
            'app_purchase_cost' => ((int)($m['app_purchase']??0)>0 ? $n($spend/(int)$m['app_purchase']) : '0,00'),
            'download_cost'     => ((int)($m['download']??0) > 0 ? $n($spend/(int)$m['download']) : '0,00'),
            // ── Engajamento ───────────────────────────────────────
            'engajamento'       => $ni($eng),
            'engagement'        => $ni($eng),
            'page_engagement'   => $ni($eng),
            'comment'           => $ni((int)($m['comment']      ?? 0)),
            'post_reaction'     => $ni((int)($m['post_reaction']?? 0)),
            'post_save'         => $ni((int)($m['post_save']    ?? 0)),
            'engajamento_cost'  => ($eng   > 0 ? $n($spend/$eng) : '0,00'),
            'engagement_cost'   => ($eng   > 0 ? $n($spend/$eng) : '0,00'),
            // ── Mensagens ─────────────────────────────────────────
            'msg'               => $ni($msg_v),
            'mensagens'         => $ni($msg_v),
            'msg_new'           => $ni((int)($m['msg_new']  ?? 0)),
            'msg_all'           => $ni($msg_v),
            'cmsg'              => ($msg_v > 0 ? $n($spend/$msg_v) : '0,00'),
            'msg_new_cost'      => ((int)($m['msg_new']??0) > 0 ? $n($spend/(int)$m['msg_new']) : '0,00'),
            'msg_all_cost'      => ($msg_v > 0 ? $n($spend/$msg_v) : '0,00'),
            // ── App ───────────────────────────────────────────────
            'app_install'       => $ni($app_inst_i),
            // ── Tráfego ───────────────────────────────────────────
            'profile_visit'     => $ni($profile_v_i),
            'visitas_perfil'    => $ni($profile_v_i),
            'search'            => $ni($search_v_i),
            'download'          => $ni((int)($m['download']    ?? 0)),
            'vplay'             => $ni((int)($m['vplay']       ?? 0)),
            // ── Vídeo ─────────────────────────────────────────────
            'view_25'           => $ni((int)($m['view_25']  ?? 0)),
            'view_50'           => $ni((int)($m['view_50']  ?? 0)),
            'view_75'           => $ni((int)($m['view_75']  ?? 0)),
            'view_95'           => $ni((int)($m['view_95']  ?? 0)),
            'view_100'          => $ni((int)($m['view_100'] ?? 0)),
            'thruplay'          => $ni($thruplay_v),
            'v_avg'             => $n((float)($m['v_avg']   ?? 0)),
            'vview'             => $ni((int)($m['vplay']    ?? 0)),
            'vview_p'           => $n($vview_p_val),
            'custo_mensagem'    => ($msg_v > 0 ? $n($spend/$msg_v) : '0,00'),
            'custo_conversa'    => ($msg_v > 0 ? $n($spend/$msg_v) : '0,00'),
            'custo_visita_perfil'=> ($profile_v_i > 0 ? $n($spend/$profile_v_i) : '0,00'),
            'taxa_conversao'    => ($impr > 0 ? $n($clicks/$impr*100) : '0,00'),
            'saldo'             => $n((float)($m['saldo'] ?? 0)),
            'saldo_minimo'      => $n((float)($m['saldo_minimo'] ?? 0)),
            'add_to_cart'       => $ni((int)($m['add_to_cart'] ?? $m['cart'] ?? 0)),
            // ── Link público do relatório ──────────────────────────
            'link'              => $linkPublico,
            'url'               => $linkPublico,
            'link_relatorio'    => $linkPublico,
            'pdf'               => $linkPublico,
            'link_pdf'          => $linkPublico,
            // ── Erros da conta Meta (para alerta erro_conta) ──────
            'erros_conta'       => $data['erros_conta'] ?? '',
            'erro_conta'        => $data['erros_conta'] ?? '',
            'erros'             => $data['erros_conta'] ?? '',
        ];

        $map = [];
        foreach ($pairs as $k => $v) {
            $map['{'.$k.'}']                     = $v;
            $map['{'.str_replace('_','',$k).'}'] = $v;
            $map['<'.strtoupper($k).'>']         = $v;
            $map['<'.strtoupper(str_replace('_','',$k)).'>'] = $v;
        }

        return strtr($template, $map);
    }
    // ══════════════════════════════════════════════════════════════════
    // MÉTRICAS EM TEMPO REAL — cards RT na tela de relatórios
    // ══════════════════════════════════════════════════════════════════

    public function realtimeMetrics(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $reports = $db->query(
            "SELECT r.*, c.name AS client_name,
                    aa.account_name, aa.account_id AS meta_account_id, aa.access_token AS meta_token
             FROM reports r
             LEFT JOIN clients c ON r.client_id = c.id
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             WHERE r.user_id = ? AND r.status IN ('active','scheduled')
             ORDER BY r.updated_at DESC
             LIMIT 20",
            [$uid]
        )->fetchAll();
        $reports = decryptTokens($reports); // decrypt meta_token antes de usar na API

        // Mapa variável → [label, campo_no_array_métricas, formato]
        // formato: r=moeda, n=inteiro, p=percentual, x=vezes, d=decimal
        // Mapa COMPLETO: variável (minúsculo, sem < >) → [label, chave_allMetrics, formato]
        // formatos: r=moeda R$, n=inteiro, p=percentual%, x=vezes x, d=decimal, s=texto
        $varMap = [
            // ── Investimento / Custo base ──────────────────────────────
            'investimento'           => ['Investimento',          'spend',               'r'],
            'inv'                    => ['Investimento',          'spend',               'r'],
            'total_spend'            => ['Investimento',          'spend',               'r'],
            // ── Alcance / Impressões / Cliques ─────────────────────────
            'alcance'                => ['Alcance',               'reach',               'n'],
            'alcan'                  => ['Alcance',               'reach',               'n'],
            'impressoes'             => ['Impressões',            'impressions',         'n'],
            'imp'                    => ['Impressões',            'impressions',         'n'],
            'impress'                => ['Impressões',            'impressions',         'n'],
            'cliques'                => ['Cliques',               'clicks',              'n'],
            'cliq'                   => ['Cliques',               'clicks',              'n'],
            'clicks'                 => ['Cliques',               'clicks',              'n'],
            'clicks_all'             => ['Todos Cliques',         'clicks',              'n'],
            'cliques_all'            => ['Todos Cliques',         'clicks',              'n'],
            'link_click'             => ['Cliques Link',          'clicks',              'n'],
            'click_saida'            => ['Cliques Saída',         'click_saida',         'n'],
            'pageview'               => ['Views Página',          'pageview',            'n'],
            'search'                 => ['Pesquisa',              'search',              'n'],
            // ── Taxas ──────────────────────────────────────────────────
            'ctr'                    => ['CTR',                   'ctr',                 'p'],
            'ctr_all'                => ['CTR Total',             'ctr',                 'p'],
            'frequencia'             => ['Frequência',            'frequency',           'x'],
            'cr'                     => ['Taxa Conversão',        'cr',                  'p'],
            'conv_msg_click'         => ['Conv. Msg/Click',       'conv_msg_click',       'p'],
            'conv_lead_click'        => ['Conv. Lead/Click',      'conv_lead_click',      'p'],
            'conv_checkout'          => ['Conv. Checkout',        'conv_checkout',        'p'],
            'conv_pg'                => ['Taxa Conv. Pág.',       'conv_pg',              'p'],
            'conv_pg_vendas'         => ['Conv. Pág. Vendas',     'conv_pg_vendas',       'p'],
            'checkout_rate'          => ['Taxa Checkout',         'checkout_rate',        'p'],
            'fci_tm_rate'            => ['Taxa Custo/TM',         'fci_tm_rate',          'p'],
            'vview_p'                => ['Taxa View 100%',        'vview_p',              'p'],
            'taxa_conversao'         => ['Taxa Conversão',        'ctr',                  'p'],
            // ── CPM / CPC ──────────────────────────────────────────────
            'cpm'                    => ['CPM',                   'cpm',                 'r'],
            'cpc'                    => ['CPC',                   'cpc',                 'r'],
            'cpma'                   => ['Custo/Mil Alcançados',  'cpma',                'r'],
            'clicks_all_cost'        => ['Custo/Todos Cliques',   'clicks_all_cost',     'r'],
            'c_search'               => ['Custo/Pesquisa',        'c_search',            'r'],
            'cpvp'                   => ['Custo/View Página',     'cpvp',                'r'],
            'thruplay_cost'          => ['Custo/Thruplay',        'thruplay_cost',       'r'],
            'profile_visit_cost'     => ['Custo/Visita Perfil',   'custo_por_visita',    'r'],
            // ── Conversões / Resultados ────────────────────────────────
            'conversoes'             => ['Conversões',            'conversions',         'n'],
            'conversao'              => ['Conversões',            'conversions',         'n'],
            'conversion'             => ['Conversões',            'conversions',         'n'],
            'conversions'            => ['Conversões',            'conversions',         'n'],
            'results'                => ['Resultados',            'leads',               'n'],
            'leads'                  => ['Leads',                 'leads',               'n'],
            'leads_meta'             => ['Leads Meta',            'leads_meta',          'n'],
            'lead_facebook'          => ['Leads Facebook',        'lead_facebook',       'n'],
            'all_leads'              => ['Todos Leads',           'all_leads',           'n'],
            'vendas'                 => ['Vendas',                'purchase',            'n'],
            'vend'                   => ['Vendas',                'purchase',            'n'],
            'purchase'               => ['Compras',               'purchase',            'n'],
            'app_purchase'           => ['Compras App',           'app_purchase',        'n'],
            'cart'                   => ['Carrinho',              'cart',                'n'],
            'inicheckout'            => ['Início Checkout',       'inicheckout',         'n'],
            'add_payment_info'       => ['Inf. Pagamento',        'add_payment_info',    'n'],
            'contact'                => ['Contatos Site',         'contact',             'n'],
            'complete_registration'  => ['Registros Concluídos', 'complete_registration','n'],
            'conv_pixel_custom'      => ['Conv. Pixel',           'conv_pixel_custom',   'n'],
            'conversion_custom'      => ['Conv. Personalizada',   'conversion_custom',   'n'],
            'conversion_lead_pixel'  => ['Conv. Lead Pixel',      'conversion_lead_pixel','n'],
            'call_20_seconds'        => ['Ligações 20s',          'call_20_seconds',     'n'],
            'call_60_seconds'        => ['Ligações 60s',          'call_60_seconds',     'n'],
            'click_to_call'          => ['Ligações Feitas',       'click_to_call',       'n'],
            'click_to_call_confirm'  => ['Confirm. Ligação',      'click_to_call_confirm','n'],
            'download'               => ['Downloads',             'download',            'n'],
            'app_install'            => ['Instalações App',       'app_install',         'n'],
            // ── Receita / ROAS / TM ────────────────────────────────────
            'receita'                => ['Receita',               'revenue',             'r'],
            'fat'                    => ['Faturado',              'revenue',             'r'],
            'purchase_revenue'       => ['Receita',               'revenue',             'r'],
            'roas'                   => ['ROAS',                  'roas',                'x'],
            'tm'                     => ['Ticket Médio',          'tm',                  'r'],
            'app_purchase_value'     => ['Valor Compras App',     'revenue',             'r'],
            // ── Custo por resultado ────────────────────────────────────
            'cpl'                    => ['CPL',                   'cpl',                 'r'],
            'cpv'                    => ['CPV',                   'cpv',                 'r'],
            'cmsg'                   => ['Custo/Mensagem',        'cmsg',                'r'],
            'custo_result'           => ['Custo/Resultado',       'cpl',                 'r'],
            'results_cost'           => ['Custo/Resultado',       'cpl',                 'r'],
            'conversion_cost'        => ['Custo/Conversão',       'cpl',                 'r'],
            'all_leads_cost'         => ['Custo/Todos Leads',     'cpl',                 'r'],
            'leads_meta_cost'        => ['Custo/Lead Meta',       'cpl',                 'r'],
            'lead_facebook_cost'     => ['Custo/Lead Facebook',   'cpl',                 'r'],
            'conversion_lead_pixel_cost' => ['Custo/Lead Pixel',  'cpl',                 'r'],
            'cpcart'                 => ['Custo/Carrinho',        'cpcart',              'r'],
            'cpcheck'                => ['Custo/Checkout',        'cpcheck',             'r'],
            'add_payment_info_cost'  => ['Custo/Inf. Pagamento',  'cpl',                 'r'],
            'contact_cost'           => ['Custo/Contato',         'cpl',                 'r'],
            'complete_registration_cost' => ['Custo/Registro',    'cpl',                 'r'],
            'call_20_seconds_cost'   => ['Custo/Ligação 20s',     'cpl',                 'r'],
            'call_60_seconds_cost'   => ['Custo/Ligação 60s',     'cpl',                 'r'],
            'click_to_call_cost'     => ['Custo/Ligações',        'cpl',                 'r'],
            'click_to_call_confirm_cost' => ['Custo/Confirm. Lig.','cpl',               'r'],
            'conv_pixel_custom_cost' => ['Custo/Conv. Pixel',     'cpl',                 'r'],
            'conversion_custom_cost' => ['Custo/Conv. Person.',   'cpl',                 'r'],
            'app_install_cost'       => ['Custo/Install App',     'cpl',                 'r'],
            'app_purchase_cost'      => ['Custo/Compra App',      'cpl',                 'r'],
            'download_cost'          => ['Custo/Download',        'cpl',                 'r'],
            'engajamento_cost'       => ['Custo/Engajamento',     'cpl',                 'r'],
            'engagement_cost'        => ['Custo/Engajamento',     'cpl',                 'r'],
            'msg_new_cost'           => ['Custo/Nova Msg',        'cmsg',                'r'],
            'msg_all_cost'           => ['Custo/Total Msgs',      'cmsg',                'r'],
            'custo_por_visita'       => ['Custo/Visita Perfil',   'custo_por_visita',    'r'],
            'custo_visita_perfil'    => ['Custo/Visita Perfil',   'custo_por_visita',    'r'],
            'custo_mensagem'         => ['Custo/Mensagem',        'cmsg',                'r'],
            'custo_conversa'         => ['Custo/Conversa',        'cmsg',                'r'],
            // ── Engajamento ────────────────────────────────────────────
            'engajamento'            => ['Engajamento',           'engagement',          'n'],
            'engagement'             => ['Engajamento',           'engagement',          'n'],
            'page_engagement'        => ['Eng. Página',           'engagement',          'n'],
            'comment'                => ['Comentários',           'comment',             'n'],
            'post_reaction'          => ['Reações',               'post_reaction',       'n'],
            'post_save'              => ['Salvamentos',           'post_save',           'n'],
            // ── Mensagens ──────────────────────────────────────────────
            'msg'                    => ['Mensagens',             'messages',            'n'],
            'mensagens'              => ['Mensagens',             'messages',            'n'],
            'msg_new'                => ['Novas Mensagens',       'msg_new',             'n'],
            'msg_all'                => ['Total Mensagens',       'messages',            'n'],
            // ── Visitas ao perfil ──────────────────────────────────────
            'profile_visit'          => ['Visitas Perfil',        'profile_visits',      'n'],
            'visitas_perfil'         => ['Visitas Perfil',        'profile_visits',      'n'],
            // ── Vídeo ──────────────────────────────────────────────────
            'view_25'                => ['Assistiu 25%',          'view_25',             'n'],
            'view_50'                => ['Assistiu 50%',          'view_50',             'n'],
            'view_75'                => ['Assistiu 75%',          'view_75',             'n'],
            'view_95'                => ['Assistiu 95%',          'view_95',             'n'],
            'view_100'               => ['Assistiu 100%',         'view_100',            'n'],
            'thruplay'               => ['Thruplay',              'thruplay',            'n'],
            'vplay'                  => ['Video Play',            'vplay',               'n'],
            'vview'                  => ['Video View',            'vplay',               'n'],
            'v_avg'                  => ['Tempo Médio Assistido', 'v_avg',               'd'],
            // ── Google Ads específicos ─────────────────────────────────
            'impress'                => ['Impressões',            'impressions',         'n'],
            'all_conversions'        => ['Todas Conversões',      'conversions',         'n'],
            'conv_value'             => ['Valor Conversões',      'revenue',             'r'],
            'conv_int_rate'          => ['Taxa Conv. Inter.',     'ctr',                 'p'],
            'video_trueview'         => ['TrueView',              'conversions',         'n'],
        ];

        // Suporte a variáveis no formato <VARIAVEL> além de {variavel}
        $varMapUpper = [];
        foreach ($varMap as $k => $v) {
            $varMapUpper[strtoupper($k)] = $v;
        }

        $cards = [];

        foreach ($reports as $r) {
            $periodType = $r['period_type'] ?? 'last_7_days';
            [$start, $end] = self::calcPeriodDates($periodType);

            if ($start === 'MAX') {
                $campIds = !empty($r['camp_ids']) ? array_filter(explode(',', $r['camp_ids'])) : [];
                if (!empty($r['meta_account_id']) && !empty($r['meta_token'])) {
                    $start = self::fetchCampaignStartDate($r['meta_account_id'], $r['meta_token'], $campIds, (int)$r['ad_account_id']);
                } else {
                    $start = date('Y-m-d', strtotime('-90 days'));
                }
            }
            if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
                $start = date('Y-m-d', strtotime('-90 days')); // sanidade anti-1969
            }

            [$periodLabel, $periodRange] = self::rtFormatPeriod($periodType, $start, $end);

            $campIds = !empty($r['camp_ids']) ? array_filter(explode(',', $r['camp_ids'])) : [];
            $metrics = null;

            if (!empty($r['meta_account_id']) && !empty($r['meta_token'])) {
                $metrics = self::fetchMetricsCached(
                    $r['meta_account_id'], $r['meta_token'], $start, $end, $campIds,
                    (int)($r['ad_account_id'] ?? 0), $uid
                );
            } elseif (!empty($r['ad_account_id'])) {
                $metrics = self::fetchMetricsFromDB((int)$r['ad_account_id'], $start, $end, $campIds);
            }

            $m = $metrics ?? [];
            $spend       = (float)($m['spend']       ?? 0);
            $impressions = (int)  ($m['impressions'] ?? 0);
            $clicks      = (int)  ($m['clicks']      ?? 0);
            $reach       = (int)  ($m['reach']       ?? 0);
            $ctr         = round((float)($m['ctr']   ?? ($impressions > 0 ? $clicks/$impressions*100 : 0)), 2);
            $cpc         = round((float)($m['cpc']   ?? ($clicks > 0 ? $spend/$clicks : 0)), 2);
            $cpm         = round((float)($m['cpm']   ?? ($impressions > 0 ? $spend/$impressions*1000 : 0)), 2);
            $leads       = (int)  ($m['leads']       ?? 0);
            $conversions = (int)  ($m['conversions'] ?? $leads);
            $messages    = (int)  ($m['messages']    ?? $m['msg']   ?? 0);
            $engagement  = (int)  ($m['engagement']  ?? $m['engajamento'] ?? 0);
            $roas        = round((float)($m['roas']  ?? 0), 2);
            $revenue     = round((float)($m['revenue'] ?? 0), 2);
            $frequency   = round((float)($m['frequency'] ?? ($reach > 0 ? $impressions / max($reach,1) : 0)), 1);
            $profile_v   = (int)($m['profile_visits'] ?? $m['profile_visit'] ?? 0);
            $cpl         = $leads > 0 ? round($spend / $leads, 2) : ($conversions > 0 ? round($spend / $conversions, 2) : 0);
            $cmsg        = $messages > 0 ? round($spend / $messages, 2) : 0;
            $tm          = round((float)($m['tm'] ?? 0), 2);
            $cpvp        = $profile_v   > 0 ? round($spend / $profile_v, 2)   : 0;

            // Campos extras do array de métricas
            $cart         = (int)($m['cart']         ?? $m['add_to_cart'] ?? 0);
            $inicheckout  = (int)($m['inicheckout']  ?? 0);
            $add_pay      = (int)($m['add_payment_info'] ?? 0);
            $contact      = (int)($m['contact']      ?? 0);
            $comp_reg     = (int)($m['complete_registration'] ?? 0);
            $conv_px      = (int)($m['conv_pixel_custom'] ?? 0);
            $conv_custom  = (int)($m['conversion_custom'] ?? 0);
            $conv_lead_px = (int)($m['conversion_lead_pixel'] ?? 0);
            $call20       = (int)($m['call_20_seconds'] ?? 0);
            $call60       = (int)($m['call_60_seconds'] ?? 0);
            $call_click   = (int)($m['click_to_call'] ?? 0);
            $call_confirm = (int)($m['click_to_call_confirm'] ?? 0);
            $download     = (int)($m['download']      ?? 0);
            $app_inst     = (int)($m['app_install']   ?? 0);
            $app_purchase = (int)($m['app_purchase']  ?? 0);
            $thruplay     = (int)($m['thruplay']      ?? 0);
            $vplay        = (int)($m['vplay']         ?? 0);
            $view_25      = (int)($m['view_25']       ?? 0);
            $view_50      = (int)($m['view_50']       ?? 0);
            $view_75      = (int)($m['view_75']       ?? 0);
            $view_95      = (int)($m['view_95']       ?? 0);
            $view_100     = (int)($m['view_100']      ?? 0);
            $v_avg        = (float)($m['v_avg']       ?? 0);
            $vview_p      = (float)($m['vview_p']     ?? 0);
            $comment      = (int)($m['comment']       ?? 0);
            $post_react   = (int)($m['post_reaction'] ?? 0);
            $post_save    = (int)($m['post_save']     ?? 0);
            $msg_new      = (int)($m['msg_new']       ?? 0);
            $click_saida  = (int)($m['click_saida']   ?? 0);
            $pageview_v   = (int)($m['pageview']      ?? 0);
            $search_v     = (int)($m['search']        ?? 0);
            $leads_fb     = (int)($m['lead_facebook'] ?? 0);
            $cr_v         = (float)($m['cr']          ?? 0);
            $conv_pg      = (float)($m['conv_pg']     ?? 0);
            $conv_pg_v    = (float)($m['conv_pg_vendas'] ?? 0);
            $conv_msg_c   = (float)($m['conv_msg_click'] ?? 0);
            $conv_lead_c  = (float)($m['conv_lead_click'] ?? 0);
            $conv_chk     = (float)($m['conv_checkout']  ?? 0);
            $checkout_r   = (float)($m['checkout_rate']  ?? 0);
            $fci_tm_r     = (float)($m['fci_tm_rate']    ?? 0);
            $cpma_v       = (float)($m['cpma']           ?? ($reach > 0 ? $spend/$reach*1000 : 0));
            $cpvp_v       = $pageview_v > 0 ? round($spend/$pageview_v, 2) : 0;
            $c_search_v   = $search_v   > 0 ? round($spend/$search_v, 2)   : 0;
            $thruplay_c   = $thruplay   > 0 ? round($spend/$thruplay, 2)   : 0;
            $clicks_all_c = $clicks     > 0 ? round($spend/$clicks, 2)     : 0;
            $cpcart_v     = $cart       > 0 ? round($spend/$cart, 2)       : 0;
            $cpcheck_v    = $inicheckout> 0 ? round($spend/$inicheckout, 2): 0;

            // Métricas brutas para o frontend
            $allMetrics = [
                'spend'          => $spend,
                'impressions'    => $impressions,
                'clicks'         => $clicks,
                'reach'          => $reach,
                'ctr'            => $ctr,
                'cpc'            => $cpc,
                'cpm'            => $cpm,
                'conversions'    => $conversions,
                'leads'          => $leads,
                'leads_meta'     => (int)($m['leads_meta'] ?? $leads),
                'lead_facebook'  => (int)($m['lead_facebook'] ?? 0),
                'all_leads'      => (int)($m['all_leads'] ?? $leads),
                'messages'       => $messages,
                'engagement'     => $engagement,
                'roas'           => $roas,
                'revenue'        => $revenue,
                'frequency'      => $frequency,
                'profile_visits' => $profile_v,
                'cpl'            => $cpl,
                'cpv'            => $cpl,
                'cmsg'           => $cmsg,
                'tm'             => $tm,
                'custo_por_visita' => $cpvp,
                'cart'           => $cart,
                'inicheckout'    => $inicheckout,
                'add_payment_info'=> $add_pay,
                'contact'        => $contact,
                'complete_registration' => $comp_reg,
                'conv_pixel_custom' => $conv_px,
                'conversion_custom' => $conv_custom,
                'conversion_lead_pixel' => $conv_lead_px,
                'call_20_seconds'=> $call20,
                'call_60_seconds'=> $call60,
                'click_to_call'  => $call_click,
                'click_to_call_confirm' => $call_confirm,
                'download'       => $download,
                'app_install'    => $app_inst,
                'app_purchase'   => $app_purchase,
                'thruplay'       => $thruplay,
                'vplay'          => $vplay,
                'view_25'        => $view_25,
                'view_50'        => $view_50,
                'view_75'        => $view_75,
                'view_95'        => $view_95,
                'view_100'       => $view_100,
                'v_avg'          => $v_avg,
                'vview_p'        => $vview_p,
                'comment'        => $comment,
                'post_reaction'  => $post_react,
                'post_save'      => $post_save,
                'msg_new'        => $msg_new,
                'click_saida'    => $click_saida,
                'pageview'       => $pageview_v,
                'search'         => $search_v,
                'leads_facebook' => $leads_fb,
                'cr'             => $cr_v,
                'conv_pg'        => $conv_pg,
                'conv_pg_vendas' => $conv_pg_v,
                'conv_msg_click' => $conv_msg_c,
                'conv_lead_click'=> $conv_lead_c,
                'conv_checkout'  => $conv_chk,
                'checkout_rate'  => $checkout_r,
                'fci_tm_rate'    => $fci_tm_r,
                'cpma'           => round($cpma_v, 2),
                'cpvp'           => $cpvp_v,
                'c_search'       => $c_search_v,
                'thruplay_cost'  => $thruplay_c,
                'clicks_all_cost'=> $clicks_all_c,
                'cpcart'         => $cpcart_v,
                'cpcheck'        => $cpcheck_v,
            ];

            // Detecta variáveis usadas no message_text do relatório
            // Suporta {variavel} e <VARIAVEL>
            $msgText = $r['message_text'] ?? '';
            $usedVars = [];
            // formato {variavel}
            preg_match_all('/\{([a-z0-9_]+)\}/i', $msgText, $m1);
            foreach (($m1[1] ?? []) as $v) $usedVars[] = strtolower($v);
            // formato <VARIAVEL>
            preg_match_all('/<([A-Z][A-Z0-9_]*)>/', $msgText, $m2);
            foreach (($m2[1] ?? []) as $v) $usedVars[] = strtolower($v);
            $usedVars = array_unique($usedVars);

            $tplFields = [];
            $seen = [];
            foreach ($usedVars as $varLower) {
                if (isset($varMap[$varLower]) && !isset($seen[$varMap[$varLower][1]])) {
                    $seen[$varMap[$varLower][1]] = true;
                    $tplFields[] = [
                        'label'  => $varMap[$varLower][0],
                        'key'    => $varMap[$varLower][1],
                        'format' => $varMap[$varLower][2],
                        'value'  => $allMetrics[$varMap[$varLower][1]] ?? 0,
                    ];
                }
            }
            // Garante que Frequência está sempre no grid se não estiver
            $hasFreq = false;
            foreach ($tplFields as $tf) { if ($tf['key'] === 'frequency') { $hasFreq = true; break; } }
            if (!$hasFreq && $frequency > 0) {
                $tplFields[] = ['label'=>'Frequência','key'=>'frequency','format'=>'x','value'=>$frequency];
            }
            // Fallback: se não tem template, usa campos padrão
            if (empty($tplFields)) {
                $tplFields = [
                    ['label'=>'Investimento','key'=>'spend',      'format'=>'r','value'=>$spend],
                    ['label'=>'Alcance',     'key'=>'reach',      'format'=>'n','value'=>$reach],
                    ['label'=>'Impressões',  'key'=>'impressions','format'=>'n','value'=>$impressions],
                    ['label'=>'CTR',         'key'=>'ctr',        'format'=>'p','value'=>$ctr],
                    ['label'=>'CPC',         'key'=>'cpc',        'format'=>'r','value'=>$cpc],
                    ['label'=>'Frequência',  'key'=>'frequency',  'format'=>'x','value'=>$frequency],
                ];
            }

            $status = 'Sem dados';
            if ($spend > 0) {
                if ($ctr >= 1.5 || $roas >= 3)            $status = 'Alta performance';
                elseif ($ctr >= 0.7 || $conversions > 0)  $status = 'Boa performance';
                elseif ($ctr > 0)                         $status = 'Atenção';
                else                                      $status = 'Em análise';
            }

            $cards[] = [
                'id'            => $r['id'],
                'title'         => $r['title'],
                'client_name'   => $r['client_name'] ?? '',
                'platform'      => $r['platform'] ?? 'meta',
                'objetivo'      => $r['objetivo'] ?? 'todos',
                'period_type'   => $periodType,
                'period_label'  => $periodLabel,
                'period_range'  => $periodRange,
                'period_start'  => $start,
                'period_end'    => $end,
                'has_data'      => $spend > 0,
                'prev_metrics'  => null, // calculado sob demanda no frontend para evitar N+1 requests
                'status'        => $status,
                'tpl_fields'    => array_slice($tplFields, 0, 8),
                'metrics'       => $allMetrics,
                // seleção salva pelo usuário no card RT
                'rt_camp_selection' => !empty($r['rt_camp_selection']) ? json_decode($r['rt_camp_selection'], true) : null,
                // configuração de envio
                'recv_type'      => $r['recv_type']       ?? 'phone',
                'recipient_phone'=> $r['recipient_phone'] ?? '',
                'whatsapp_id'    => $r['whatsapp_id']     ?? null,
                'group_id'       => $r['group_id']        ?? '',
                'group_instance' => $r['group_instance']  ?? '',
                'client_id'      => $r['client_id']       ?? null,
            ];
        }

        echo json_encode(['success' => true, 'cards' => $cards, 'total' => count($cards)]);
    }

    private static function rtFormatPeriod(string $type, string $start, string $end): array {
        $range = date('d/m/Y', strtotime($start)) . ' – ' . date('d/m/Y', strtotime($end));
        if (str_starts_with($type, 'custom|')) return ['Período manual', $range];
        $map = [
            'today'=>'Hoje','yesterday'=>'Ontem','this_week'=>'Esta semana',
            'this_week_mon'=>'Esta semana','last_week'=>'Semana passada',
            'last_week_mon'=>'Semana passada','this_month'=>'Este mês',
            'last_month'=>'Mês passado','last_3_days'=>'Últimos 3 dias',
            'last_7_days'=>'Últimos 7 dias','last_14_days'=>'Últimos 15 dias',
            'last_30_days'=>'Últimos 30 dias','last_90_days'=>'Últimos 90 dias',
            'last_year'=>'Último ano','this_year'=>'Este ano','max'=>'Máximo',
        ];
        return [$map[$type] ?? 'Período', $range];
    }

    public function realtimeChat(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $message    = trim($_POST['message'] ?? '');
        $history    = json_decode($_POST['history'] ?? '[]', true) ?: [];
        $ctx        = json_decode($_POST['context'] ?? '{}', true) ?: [];

        if (!$message) { echo json_encode(['success'=>false,'error'=>'Mensagem vazia']); return; }

        $aiSettings = AiController::getAiSettings($uid, $db);

        // Usa provider/model padrão configurado em Análise IA
        $provider = $aiSettings['ai_default_provider'] ?? '';
        $model    = $aiSettings['ai_default_model']    ?? '';
        $keyMap   = ['groq'=>'ai_groq_key','openai'=>'ai_openai_key','gemini'=>'ai_gemini_key'];
        $apiKey   = $aiSettings[$keyMap[$provider] ?? ''] ?? '';

        // Fallback: primeiro provider com chave disponível
        if (!$apiKey) {
            foreach (['groq','gemini','openai'] as $p) {
                $k = $aiSettings[$keyMap[$p]] ?? '';
                if ($k) {
                    $provider = $p;
                    $apiKey   = $k;
                    $models   = AiController::getModels()[$p]['models'] ?? [];
                    $model    = $model ?: ($models[0]['id'] ?? '');
                    break;
                }
            }
        }
        if (!$apiKey) {
            echo json_encode(['success'=>false,'error'=>'Nenhuma chave de IA configurada. Acesse Análise IA → Chaves de API.']);
            return;
        }

        $m = $ctx['metrics'] ?? [];

        // System prompt — simples se for reescrita sem contexto
        if (empty($ctx) || empty($ctx['title'] ?? '')) {
            $system = 'Voce e especialista em trafego pago e copywriting. Responda APENAS com a mensagem reescrita, sem explicacoes adicionais. Mantenha todos os dados e numeros. Use emojis relevantes. Responda em pt-BR.';
        } else {
            $fR = fn($v) => 'R$ '.number_format((float)$v, 2, ',', '.');
            $fN = fn($v) => number_format((int)$v, 0, ',', '.');
            $ms =
                "- Investimento: ".($m['spend']>0 ? $fR($m['spend']) : 'N/D')."
".
                "- Alcance: ".($m['reach']>0 ? $fN($m['reach']) : 'N/D')."
".
                "- Impressoes: ".($m['impressions']>0 ? $fN($m['impressions']) : 'N/D')."
".
                "- Cliques: ".($m['clicks']>0 ? $fN($m['clicks']) : 'N/D')."
".
                "- CTR: ".($m['ctr']>0 ? number_format($m['ctr'],2,',','.').'%' : 'N/D')."
".
                "- CPC: ".($m['cpc']>0 ? $fR($m['cpc']) : 'N/D')."
".
                "- Conversoes/Leads: ".($m['conversions']>0 ? $fN($m['conversions']) : 'N/D')."
".
                "- ROAS: ".($m['roas']>0 ? number_format($m['roas'],2,',','.').'x' : 'N/D')."
".
                "- Frequencia: ".($m['frequency']>0 ? number_format($m['frequency'],1,',','.').'x' : 'N/D');
            $period = ($ctx['period_label']??'').' ('.($ctx['period_range']??'').')';
            $system =
                "Voce e especialista senior em trafego pago. Responda de forma CONCISA (max 3 paragrafos). ".
                "Seja direto, pratico e use os dados. Nao repita a pergunta. Responda em pt-BR.

".
                "RELATORIO: ".($ctx['title']??'')."
".
                "PLATAFORMA: ".strtoupper($ctx['platform']??'meta')."
".
                "OBJETIVO: ".($ctx['objetivo']??'')."
".
                "PERIODO: $period
".
                "STATUS: ".($ctx['status']??'')."

".
                "METRICAS:
$ms";
        }
        $msgs = [];
        foreach ($history as $h) {
            if (!empty($h['role']) && !empty($h['content'])) $msgs[] = $h;
        }
        $msgs[] = ['role'=>'user','content'=>$message];

        // Faz a chamada
        $res = ['success'=>false,'error'=>'Erro desconhecido'];
        try {
            if ($provider === 'gemini') {
                $contents = [];
                foreach ($msgs as $h) $contents[] = ['role'=>$h['role']==='assistant'?'model':'user','parts'=>[['text'=>$h['content']]]];
                $body = ['system_instruction'=>['parts'=>[['text'=>$system]]],'contents'=>$contents];
                $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $ch = curl_init($url);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
                $r = json_decode(curl_exec($ch),true); curl_close($ch);
                $text = $r['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $res = $text ? ['success'=>true,'text'=>trim($text)] : ['success'=>false,'error'=>'Sem resposta da IA'];
            } else {
                $allMsgs = array_merge([['role'=>'system','content'=>$system]], $msgs);
                $url = $provider === 'openai' ? 'https://api.openai.com/v1/chat/completions' : 'https://api.groq.com/openai/v1/chat/completions';
                $ch = curl_init($url);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
                    CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>$allMsgs,'max_tokens'=>600,'temperature'=>0.7]),
                    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
                $r = json_decode(curl_exec($ch),true); curl_close($ch);
                $text = $r['choices'][0]['message']['content'] ?? '';
                $res = $text ? ['success'=>true,'text'=>trim($text)] : ['success'=>false,'error'=>'Sem resposta da IA'];
            }
        } catch (\Throwable $e) {
            $res = ['success'=>false,'error'=>$e->getMessage()];
        }

        echo json_encode($res);
    }

    /**
     * GET /reports/rt-message — retorna a mensagem do relatório com variáveis substituídas
     */
    public function realtimeMessage(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $reportId = (int)($_GET['id'] ?? 0);
        if (!$reportId) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

        $report = $db->query(
            "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token,
                    aa.account_name, c.name AS client_name, c.company AS client_company
             FROM reports r
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE r.id=? AND r.user_id=?",
            [$reportId, $uid]
        )->fetch();

        if (!$report) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }
        $report = decryptTokens($report); // decrypt meta_token

        [$start, $end] = self::calcPeriodDates($report['period_type'] ?? 'last_7_days');
        if ($start === 'MAX') {
            $campIds = !empty($report['camp_ids']) ? array_filter(explode(',', $report['camp_ids'])) : [];
            if (!empty($report['meta_account_id']) && !empty($report['meta_token'])) {
                $start = self::fetchCampaignStartDate($report['meta_account_id'], $report['meta_token'], $campIds, (int)$report['ad_account_id']);
            } else {
                $start = date('Y-m-d', strtotime('-90 days'));
            }
        }
        // Sanidade: nunca usar datas inválidas (0001-01-01, nulos, antes de 2015)
        if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
            $start = date('Y-m-d', strtotime('-90 days'));
        }

        $campIds = !empty($report['camp_ids']) ? array_filter(explode(',', $report['camp_ids'])) : [];
        $metrics = null;
        if (!empty($report['meta_account_id']) && !empty($report['meta_token'])) {
            $metrics = self::fetchMetricsCached(
                $report['meta_account_id'], $report['meta_token'], $start, $end, $campIds,
                (int)($report['ad_account_id'] ?? 0), $uid, 15
            );
        }
        if ($metrics === null && !empty($report['ad_account_id'])) {
            $metrics = self::fetchMetricsFromDB((int)$report['ad_account_id'], $start, $end, $campIds);
        }

        $campNome = '';
        if (!empty($campIds)) {
            $placeholders = implode(',', array_fill(0, count($campIds), '?'));
            $rows = $db->query("SELECT DISTINCT campaign_name FROM campaign_metrics WHERE campaign_id IN ($placeholders)", $campIds)->fetchAll();
            $campNome = implode(', ', array_column($rows, 'campaign_name'));
        }

        // Se métricas da API nulas, tenta banco local com todas as campanhas da conta
        if ($metrics === null && !empty($report['ad_account_id'])) {
            $metrics = self::fetchMetricsFromDB((int)$report['ad_account_id'], $start, $end, []);
        }

        $periodo_str = date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end));
        // Gera link público para {link} no rt-send
        $linkRt = '';
        try {
            $stRt = $report['share_token'] ?? null;
            if (!$stRt) {
                $stRt = bin2hex(random_bytes(16));
                $db->query("UPDATE reports SET share_token=? WHERE id=?", [$stRt, $report['id']]);
            }
            $tplRt = !empty($report['pdf_tpl_id']) ? '&tpl=' . (int)$report['pdf_tpl_id'] : '';
            $slugRt = self::makeSlug(trim($report['client_company'] ?? '') ?: trim($report['client_name'] ?? '') ?: trim($report['title'] ?? ''));
            $nParamRt = $slugRt ? '&n=' . urlencode($slugRt) : '';
            $linkRt = APP_URL . '/r?t=' . $stRt . $tplRt . $nParamRt;
        } catch (\Throwable $_tlr) {}

        $msgFinal = self::buildMessage($report['message_text'] ?? '', [
            'metrics'        => $metrics,
            'periodo'        => $periodo_str,
            'account_name'   => $report['account_name']   ?? '',
            'client_name'    => $report['client_name']    ?? '',
            'client_company' => $report['client_company'] ?? '',
            'observacoes'    => '',
            'campaign_name'  => $campNome,
            'link'           => $linkRt,
        ]);

        // Se message_text vazio, monta mensagem básica com métricas disponíveis
        if (empty(trim($msgFinal)) && $metrics) {
            $m = $metrics;
            $n = fn($v,$d=2) => number_format((float)$v,$d,',','.');
            $ni = fn($v) => number_format((int)$v,0,',','.');
            $msgFinal =
                "📊 *{$campNome}*
".
                "📅 {$periodo_str}

".
                ($m['spend']>0       ? "💰 *Investimento:* R\$ {$n($m['spend'])}
" : '').
                ($m['reach']>0       ? "👁 *Alcance:* {$ni($m['reach'])}
" : '').
                ($m['impressions']>0 ? "📢 *Impressões:* {$ni($m['impressions'])}
" : '').
                ($m['clicks']>0      ? "🖱 *Cliques:* {$ni($m['clicks'])}
" : '').
                ($m['ctr']>0         ? "📈 *CTR:* {$n($m['ctr'])}%
" : '').
                ($m['cpc']>0         ? "💵 *CPC:* R\$ {$n($m['cpc'])}
" : '').
                ($m['conversions']>0 ? "✅ *Conversões:* {$ni($m['conversions'])}
" : '').
                ($m['roas']>0        ? "🚀 *ROAS:* {$n($m['roas'])}x
" : '');
        }

        // Busca instâncias conectadas e clientes do usuário
        $instances = $db->query("SELECT id, instance_name, phone_number, status FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id", [$uid])->fetchAll();
        $clients   = $db->query("SELECT id, name, phone, company FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        $groups    = []; // grupos serão buscados pelo cliente via Evolution API quando selecionar instância

        echo json_encode([
            'success'        => true,
            'message'        => $msgFinal,
            'instances'      => $instances,
            'clients'        => $clients,
            'recv_type'      => $report['recv_type']       ?? 'phone',
            'phone'          => $report['recipient_phone'] ?? '',
            'group_id'       => $report['group_id']        ?? '',
            'wp_id'          => $report['whatsapp_id']     ?? null,
            'client_id'      => $report['client_id']       ?? null,
        ]);
    }

    /**
     * POST /reports/rt-send — envia via WhatsApp a partir do popup RT
     */
    public function realtimeSend(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $reportId = (int)($_POST['report_id'] ?? 0);
        $message  = trim($_POST['message']    ?? '');
        $recvType = sanitize($_POST['recv_type'] ?? 'phone');
        $phone    = sanitize($_POST['phone']   ?? '');
        $groupId  = sanitize($_POST['group_id']  ?? '');
        $wpId     = (int)($_POST['wp_id']      ?? 0);

        if (!$reportId) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        if (!$message)  { echo json_encode(['success'=>false,'error'=>'Mensagem vazia']); return; }

        // Verifica que o relatório pertence ao usuário
        $report = $db->query("SELECT * FROM reports WHERE id=? AND user_id=?", [$reportId, $uid])->fetch();
        if (!$report) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }

        // Busca instância WhatsApp
        $wp = null;
        if ($wpId) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?", [$wpId, $uid])->fetch() ?: null;
        }
        if (!$wp) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id LIMIT 1", [$uid])->fetch() ?: null;
        }
        if (!$wp) { echo json_encode(['success'=>false,'error'=>'Nenhuma instância WhatsApp conectada']); return; }

        // Envia
        if ($recvType === 'group' && $groupId) {
            $instName = $wp['instance_name'];
            $result   = self::sendWhatsAppGroup($instName, $groupId, $message);
        } else {
            $dest   = $phone ?: $report['recipient_phone'];
            if (!$dest) { echo json_encode(['success'=>false,'error'=>'Destinatário não informado']); return; }
            $result = $this->sendWhatsApp($wp['instance_name'], $dest, $message);
        }

        if ($result['ok']) {
            // Atualiza sent_at e status do relatório
            $db->query(
                "UPDATE reports SET sent_whatsapp=1, sent_at=?, last_send_status='ok', last_send_error=NULL WHERE id=?",
                [date('Y-m-d H:i:s'), $reportId]
            );

            // Envia mensagem complementar (followup) se existir
            $followupText = trim($report['followup_message'] ?? '');
            if ($followupText !== '') {
                sleep(1); // pausa de 1s entre mensagens
                if ($recvType === 'group' && $groupId) {
                    self::sendWhatsAppGroup($wp['instance_name'], $groupId, $followupText);
                } else {
                    $destFollowup = $phone ?: ($report['recipient_phone'] ?? '');
                    if ($destFollowup) {
                        $this->sendWhatsApp($wp['instance_name'], $destFollowup, $followupText);
                    }
                }
            }

            echo json_encode(['success'=>true]);
        } else {
            $db->query("UPDATE reports SET last_send_status='error', last_send_error=? WHERE id=?",
                [mb_substr($result['error'], 0, 255), $reportId]);
            echo json_encode(['success'=>false, 'error'=>$result['error']]);
        }
    }

    /**
     * GET /reports/rt-names?id=X — busca nomes de campanha, conjuntos e criativos via API Meta
     */
    public function realtimeNames(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $reportId = (int)($_GET['id'] ?? 0);
        if (!$reportId) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

        $report = $db->query(
            "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token
             FROM reports r
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             WHERE r.id=? AND r.user_id=?",
            [$reportId, $uid]
        )->fetch();

        if (!$report) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }
        if (!class_exists('TokenCrypto')) require_once __DIR__.'/../core/TokenCrypto.php';
        $report = decryptTokens($report);

        $accountId = $report['meta_account_id'] ?? '';
        $token     = $report['meta_token']      ?? '';
        $campIds   = !empty($report['camp_ids']) ? array_values(array_filter(explode(',', $report['camp_ids']))) : [];

        if (!$accountId || !$token) {
            // Sem API Meta — retorna só nomes do banco
            $campaigns = [];
            if (!empty($campIds)) {
                $ph = implode(',', array_fill(0, count($campIds), '?'));
                $rows = $db->query("SELECT DISTINCT campaign_id, campaign_name FROM campaign_metrics WHERE campaign_id IN ($ph) ORDER BY campaign_name", $campIds)->fetchAll();
                foreach ($rows as $r) {
                    $campaigns[] = ['id'=>$r['campaign_id'], 'name'=>$r['campaign_name'], 'adsets'=>[], 'ads'=>[]];
                }
            }
            echo json_encode(['success'=>true, 'campaigns'=>$campaigns]);
            return;
        }

        $base = 'https://graph.facebook.com/' . META_API_VERSION;
        $campaigns = [];

        // Se não há camp_ids, busca campanhas ativas da conta
        if (empty($campIds)) {
            $url = "{$base}/act_{$accountId}/campaigns?fields=id,name,status,objective&effective_status=[\"ACTIVE\"]&limit=20&access_token=".urlencode($token);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);
            foreach (($res['data'] ?? []) as $c) {
                $campIds[] = $c['id'];
                $campaigns[$c['id']] = ['id'=>$c['id'], 'name'=>$c['name'], 'status'=>$c['status'], 'adsets'=>[], 'ads'=>[]];
            }
        } else {
            // Busca nomes das campanhas específicas
            foreach ($campIds as $cid) {
                $url = "{$base}/{$cid}?fields=id,name,status,objective&access_token=".urlencode($token);
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true]);
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if (!empty($res['id'])) {
                    $campaigns[$cid] = ['id'=>$cid, 'name'=>$res['name']??$cid, 'status'=>$res['status']??'', 'adsets'=>[], 'ads'=>[]];
                }
            }
        }

        // Busca conjuntos de anúncios para cada campanha
        foreach ($campIds as $cid) {
            $url = "{$base}/{$cid}/adsets?fields=id,name,status&limit=20&access_token=".urlencode($token);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);
            if (!empty($res['data']) && isset($campaigns[$cid])) {
                foreach ($res['data'] as $as) {
                    $campaigns[$cid]['adsets'][] = ['id'=>$as['id'], 'name'=>$as['name'], 'status'=>$as['status']??''];
                }
            }
        }

        // Busca anúncios (criativos) da conta filtrando pelas campanhas
        if (!empty($campIds)) {
            $filterVal = json_encode(array_values($campIds));
            $filtering = urlencode('[{"field":"campaign.id","operator":"IN","value":'.$filterVal.'}]');
            $url = "{$base}/act_{$accountId}/ads?fields=id,name,status,campaign_id&filtering={$filtering}&limit=50&access_token=".urlencode($token);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_SSL_VERIFYPEER=>true]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);
            foreach (($res['data'] ?? []) as $ad) {
                $cid = $ad['campaign_id'] ?? '';
                if (isset($campaigns[$cid])) {
                    $campaigns[$cid]['ads'][] = ['id'=>$ad['id'], 'name'=>$ad['name'], 'status'=>$ad['status']??''];
                }
            }
        }

        echo json_encode(['success'=>true, 'campaigns'=>array_values($campaigns)]);
    }

    /**
     * POST /reports/rt-save-selection — salva a seleção campanha/conjunto/criativo do card RT
     */
    public function realtimeSaveSelection(): void {
        requireAuth(); csrfCheck();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        header('Content-Type: application/json');

        $reportId = (int)($_POST['report_id'] ?? 0);
        $label    = sanitize($_POST['label']     ?? ''); // Campanha / Conjunto / Criativo
        $name     = sanitize($_POST['name']      ?? '');
        $itemId   = sanitize($_POST['item_id']   ?? '');

        if (!$reportId) { echo json_encode(['success'=>false]); return; }

        // Garante que a coluna existe (migration automática)
        try {
            $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS rt_camp_selection JSON DEFAULT NULL");
        } catch (\Throwable $e) {}

        $selection = json_encode(['label'=>$label,'name'=>$name,'id'=>$itemId,'saved_at'=>date('Y-m-d H:i:s')]);

        $db->query(
            "UPDATE reports SET rt_camp_selection=? WHERE id=? AND user_id=?",
            [$selection, $reportId, $uid]
        );
        echo json_encode(['success'=>true]);
    }

    // ── Gerar/obter token público de compartilhamento ────────────────────────
    public function generateShareToken(): void {
        try {
            requireAuth(); csrfCheck();
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'Auth: '.$e->getMessage()]);
            return;
        }
        header('Content-Type: application/json');
        try {
            $uid      = currentUser()['id'];
            $db       = Database::getInstance();
            $reportId = (int)($_POST['report_id'] ?? 0);
            if (!$reportId) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

            // Garantir que a coluna existe
            try {
                $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL DEFAULT NULL");
            } catch (\Throwable $e) {}

            // Buscar relatório com dados do cliente para o slug do link
            $r = $db->query(
                "SELECT r.id, r.title, r.pdf_tpl_id, c.name AS client_name, c.company AS client_company
                 FROM reports r
                 LEFT JOIN clients c ON r.client_id = c.id
                 WHERE r.id=? AND r.user_id=?",
                [$reportId, $uid]
            )->fetch();
            if (!$r) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }

            // Buscar token existente
            $token = null;
            try {
                $rt = $db->query("SELECT share_token FROM reports WHERE id=?", [$reportId])->fetch();
                $token = $rt['share_token'] ?? null;
            } catch (\Throwable $e) {}

            if (!$token) {
                $token = bin2hex(random_bytes(16));
                $db->query("UPDATE reports SET share_token=? WHERE id=? AND user_id=?", [$token,$reportId,$uid]);
            }

            // Usa SOMENTE o template explicitamente selecionado — sem fallback automático
            $pdfTplId    = (int)($_POST['pdf_tpl_id'] ?? 0);
            // Se não veio pelo POST, usa o que está salvo no relatório
            if (!$pdfTplId) {
                try {
                    $rTplRow = $db->query("SELECT pdf_tpl_id FROM reports WHERE id=? AND user_id=?", [$reportId, $uid])->fetch();
                    $pdfTplId = (int)($rTplRow['pdf_tpl_id'] ?? 0);
                } catch (\Throwable $_rt) {}
            }
            $accentParam = '';
            try {
                if ($pdfTplId) {
                    $tplRow = $db->query("SELECT config FROM pdf_templates WHERE id=? AND user_id=?", [$pdfTplId, $uid])->fetch();
                    if ($tplRow && $tplRow['config']) {
                        $tplCfg = json_decode($tplRow['config'], true) ?: [];
                        $accentParam = $tplCfg['palette']['accent'] ?? '';
                    }
                }
                // Sem template selecionado = sem accentParam, PDF renderiza com cores padrão
            } catch (\Throwable $e2) {}
            // Salva o template escolhido no relatório para o PDF automático também usar
            if ($pdfTplId) {
                try {
                    $db->query("UPDATE reports SET pdf_tpl_id=? WHERE id=? AND user_id=?", [$pdfTplId, $reportId, $uid]);
                } catch (\Throwable $e3) {}
            }
            $tplParam = $pdfTplId ? '&tpl=' . $pdfTplId : '';

            // Gera slug legível com nome do cliente ou empresa
            $slugBase = trim($r['client_company'] ?? '') ?: trim($r['client_name'] ?? '') ?: trim($r['title'] ?? '');
            $slug = $slugBase ? self::makeSlug($slugBase) : '';
            $nParam = $slug ? '&n=' . urlencode($slug) : '';
            $url = APP_URL . '/r?t=' . $token . $tplParam . $nParam . ($accentParam ? '&ac=' . urlencode($accentParam) : '');
            echo json_encode(['success'=>true,'token'=>$token,'url'=>$url,'tpl_id'=>$pdfTplId,'slug'=>$slug]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage().' in '.$e->getFile().':'.$e->getLine()]);
        }
    }

    // ── Enviar link do PDF via WhatsApp ──────────────────────────────────────
    public function sendPdfLink(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $reportId = (int)($_POST['report_id'] ?? 0);
        $message  = trim($_POST['message']    ?? '');
        $dest     = trim($_POST['dest']       ?? ''); // phone or group JID
        $destType = trim($_POST['dest_type']  ?? 'phone'); // phone|group
        $wpId     = (int)($_POST['whatsapp_id'] ?? 0);
        $groupInst= trim($_POST['group_instance'] ?? '');

        if (!$reportId || !$message) { echo json_encode(['success'=>false,'error'=>'Dados incompletos']); return; }

        $r = $db->query(
            "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token, aa.account_name AS acc_name,
                    c.name AS client_name, c.company AS client_company
             FROM reports r
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE r.id = ? AND r.user_id = ?",
            [$reportId,$uid]
        )->fetch();
        if (!$r) { echo json_encode(['success'=>false,'error'=>'Relatório não encontrado']); return; }

        // Resolver variáveis da mensagem com métricas reais
        $m = [];
        try {
            [$start, $end] = self::calcPeriodDates($r['period_type'] ?? 'last_7_days');
            $campIds = !empty($r['camp_ids']) ? array_filter(explode(',', $r['camp_ids'])) : [];
            // Resolver MAX antes de usar $start
            if ($start === 'MAX') {
                if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
                    $start = self::fetchCampaignStartDate($r['meta_account_id'], $r['access_token'], $campIds, (int)($r['ad_account_id'] ?? 0));
                } else {
                    $start = date('Y-m-d', strtotime('-90 days'));
                }
            }
            // Sanidade anti-1969
            if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
                $start = date('Y-m-d', strtotime('-90 days'));
            }
            if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
                $m = self::fetchMetricsMeta($r['meta_account_id'], $r['access_token'], $start, $end, $campIds) ?: [];
            }
            if (empty($m) && !empty($r['ad_account_id'])) {
                $m = self::fetchMetricsFromDB((int)$r['ad_account_id'], $start, $end, $campIds) ?: [];
            }
            $periodo = date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end));
            $message = self::buildMessage($message, [
                'metrics'        => $m,
                'client_name'    => $r['client_name']    ?? '',
                'client_company' => $r['client_company'] ?? '',
                'account_name'   => $r['acc_name']       ?? '',
                'campaign_name'  => $r['title']          ?? '',
                'periodo'        => $periodo,
                'observacoes'    => '',
            ]);
        } catch (\Throwable $e) { /* usa mensagem sem substituição */ }

        // Gerar/obter token
        $token = $r['share_token'];
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL DEFAULT NULL", []); } catch (\Throwable $e) {}
            $db->query("UPDATE reports SET share_token=? WHERE id=? AND user_id=?", [$token,$reportId,$uid]);
        }

        // Instância WP
        $wp = null;
        if ($wpId) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?", [$wpId,$uid])->fetch() ?: null;
        }
        if (!$wp) {
            $wp = $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id LIMIT 1", [$uid])->fetch() ?: null;
        }
        if (!$wp) { echo json_encode(['success'=>false,'error'=>'Nenhuma instância WhatsApp conectada']); return; }

        // Enviar
        if ($destType === 'group' && $dest) {
            $instName = $groupInst ?: $wp['instance_name'];
            $result = self::sendWhatsAppGroup($instName, $dest, $message);
        } else {
            $result = self::sendWhatsAppStatic($wp['instance_name'], $dest, $message);
        }

        if ($result['ok'] ?? false) {
            echo json_encode(['success'=>true,'token'=>$token,'url'=>APP_URL.'/r?t='.$token]);
        } else {
            echo json_encode(['success'=>false,'error'=>$result['error'] ?? 'Falha no envio']);
        }
    }

    // ── Helper: gera slug legível para URL ───────────────────────────────────────
    private static function makeSlug(string $text, int $maxLen = 40): string {
        $s = mb_strtolower(trim($text), 'UTF-8');
        $s = preg_replace('/[áàãâä]/u','a',$s); $s = preg_replace('/[éèêë]/u','e',$s);
        $s = preg_replace('/[íìîï]/u','i',$s);  $s = preg_replace('/[óòõôö]/u','o',$s);
        $s = preg_replace('/[úùûü]/u','u',$s);  $s = preg_replace('/ç/u','c',$s);
        $s = preg_replace('/[^a-z0-9\s-]/u','',$s);
        $s = substr(trim(preg_replace('/[\s-]+/','-',$s),'-'), 0, $maxLen);
        return $s;
    }

    // ── PDF público (sem login) ────────────────────────────────────────────────
    public function publicPdf(): void {
        $db    = Database::getInstance();
        $token = sanitize($_GET['t'] ?? '');
        if (!$token) { http_response_code(404); die('Link inválido'); }

        // Garante coluna pdf_tpl_id existe
        try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS pdf_tpl_id INT UNSIGNED NULL DEFAULT NULL"); } catch (\Throwable $e2) {}
            try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS pdf_accent VARCHAR(20) NULL DEFAULT NULL"); } catch (\Throwable $e2) {}
        $r = $db->query(
            "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token, aa.account_name AS acc_name,
                    c.name AS client_name, c.company AS client_company
             FROM reports r
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE r.share_token = ?",
            [$token]
        )->fetch();
        // Garante que pdf_tpl_id está carregado (pode falhar se coluna foi adicionada recentemente)
        if ($r && !array_key_exists('pdf_tpl_id', $r)) {
            try {
                $rTpl = $db->query("SELECT pdf_tpl_id FROM reports WHERE id=?", [(int)$r['id']])->fetch();
                $r['pdf_tpl_id'] = $rTpl['pdf_tpl_id'] ?? null;
            } catch (\Throwable $e2) { $r['pdf_tpl_id'] = null; }
        }

        if (!$r) { http_response_code(404); die('Link inválido ou expirado'); }

        // Descriptografa tokens antes de usar na API
        $r = decryptTokens($r);

        // Garante pdf_tpl_id no objeto $r (pode não ter vindo se coluna foi adicionada agora)
        if (!isset($r['pdf_tpl_id'])) {
            try {
                $extra = $db->query("SELECT pdf_tpl_id FROM reports WHERE share_token=?", [$token])->fetch();
                if ($extra) $r['pdf_tpl_id'] = $extra['pdf_tpl_id'];
            } catch (\Throwable $e2) {}
        }

        // Segurança: bloqueia datas livres via GET em links públicos.
        // Períodos fixos (last_7_days, last_15_days, etc.) continuam funcionando normalmente.
        if (($_GET['period'] ?? '') === 'custom') {
            unset($_GET['period'], $_GET['custom_start'], $_GET['custom_end']);
        }
        $this->generatePdfInternal($r);
    }

    // ── Geração de PDF do relatório ───────────────────────────────────────────
    public function generatePdf(): void {
        requireAuth();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $reportId = (int)($_GET['id'] ?? 0);
        $tplId    = (int)($_GET['tpl'] ?? 0);
        if (!$reportId) { http_response_code(400); die('ID inválido'); }

        $db2 = Database::getInstance();
        $r = $db2->query(
            "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token, aa.account_name AS acc_name,
                    c.name AS client_name, c.company AS client_company
             FROM reports r
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE r.id = ? AND r.user_id = ?",
            [$reportId, $uid]
        )->fetch();
        if (!$r) { http_response_code(404); die('Relatório não encontrado'); }
        $r = decryptTokens($r);

        $this->generatePdfInternal($r, $tplId, $uid);
    }

    private function generatePdfInternal(array $r, int $tplId = 0, int $uid = 0): void {
        $db       = Database::getInstance();
        $reportId = (int)$r['id'];
        if (!$uid) $uid = (int)$r['user_id'];
        if (!$tplId) $tplId = (int)($_GET['tpl'] ?? 0);

        // Buscar template salvo (pelo ID, pelo pdf_tpl_id do relatório, ou o mais recente)
        $tplConfig = [];
        $tpl       = null; // inicializa para evitar warning se $tplId for 0
        try {
            $tplUserId = $uid ?: (int)($r['user_id'] ?? 0);
            // Tenta pegar pdf_tpl_id do relatório (pode estar na coluna ou precisa de query extra)
            if (!$tplId) {
                $savedTplId = (int)($r['pdf_tpl_id'] ?? 0);
                if (!$savedTplId) {
                    // Busca direto da tabela (caso coluna foi adicionada depois do SELECT r.*)
                    try {
                        $rExtra = $db->query("SELECT pdf_tpl_id FROM reports WHERE id=?", [(int)$r['id']])->fetch();
                        $savedTplId = (int)($rExtra['pdf_tpl_id'] ?? 0);
                    } catch (\Throwable $e2) {}
                }
                if ($savedTplId) $tplId = $savedTplId;
            }
            if ($tplId) {
                $tpl = $db->query("SELECT config FROM pdf_templates WHERE id=? AND user_id=?", [$tplId,$tplUserId])->fetch();
                // Se não achou pelo user_id (acesso público), busca só pelo id
                if (!$tpl) {
                    $tpl = $db->query("SELECT config FROM pdf_templates WHERE id=?", [$tplId])->fetch();
                }
            }
            // Sem template selecionado = renderiza com configuração padrão (sem fallback automático)
            // Isso garante que cada relatório use apenas o template explicitamente vinculado
            if ($tpl && $tpl['config']) {
                $tplConfig = json_decode($tpl['config'], true) ?: [];
            }
        } catch (\Throwable $e) {}

        $tplBlocks      = $tplConfig['blocks']       ?? ['header','kpis','message','footer'];
        $tplPalette     = $tplConfig['palette']      ?? ['accent'=>'#5B8DEF','bg'=>'#ffffff','txt'=>'#1a1a2e'];
        $tplBlockCfgs   = $tplConfig['blockConfigs'] ?? [];
        // Cor do accent: coluna pdf_accent do relatório > URL > palette do template > padrão
        $accentFromUrl = preg_replace('/[^#0-9a-fA-F]/', '', $_GET['ac'] ?? '');
        // Lê pdf_accent direto do relatório (salvo quando usuário salva template)
        $accentFromDb = '';
        try {
            $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS pdf_accent VARCHAR(20) NULL DEFAULT NULL");
        } catch (\Throwable $e2) {}
        try {
            $rAc = $db->query("SELECT pdf_accent FROM reports WHERE id=?", [(int)($r['id']??0)])->fetch();
            $accentFromDb = trim($rAc['pdf_accent'] ?? '');
        } catch (\Throwable $e2) {}
        $paletteAccent = $tplPalette['accent'] ?? '';
        if (!empty($accentFromDb) && $accentFromDb !== '#5B8DEF') {
            $tplAccent = $accentFromDb;
        } elseif (!empty($accentFromUrl)) {
            $tplAccent = $accentFromUrl;
        } elseif (!empty($paletteAccent) && $paletteAccent !== '#5B8DEF') {
            $tplAccent = $paletteAccent;
        } else {
            $tplAccent = '#5B8DEF';
        }


        $campIds = !empty($r['camp_ids']) ? array_filter(explode(',', $r['camp_ids'])) : [];
        $periodGet = sanitize($_GET['period'] ?? '');
        $customStart = sanitize($_GET['custom_start'] ?? '');
        $customEnd   = sanitize($_GET['custom_end']   ?? '');

        if ($periodGet && $periodGet !== 'saved') {
            // Período escolhido pelo usuário no seletor
            if ($periodGet === 'custom' && $customStart && $customEnd) {
                $start = $customStart;
                $end   = $customEnd;
            } else {
                [$start, $end] = self::calcPeriodDates($periodGet);
            }
        } else {
            // Usar período salvo no relatório
            [$start, $end] = self::calcPeriodDates($r['period_type'] ?? 'last_7_days');
        }

        if ($start === 'MAX') {
            $start = !empty($r['access_token'])
                ? self::fetchCampaignStartDate($r['meta_account_id'], $r['access_token'], $campIds, (int)$r['ad_account_id'])
                : date('Y-m-d', strtotime('-90 days'));
        }
        // Sanidade: nunca usar data inválida (null, 0001-01-01, antes de 2015 = causa bug 1969)
        if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
            $start = date('Y-m-d', strtotime('-90 days'));
        }
        $activePeriod = $periodGet ?: ($r['period_type'] ?? 'last_7_days');

        $m = null;
        if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
            try { $m = self::fetchMetricsMeta($r['meta_account_id'], $r['access_token'], $start, $end, $campIds); }
            catch (\Throwable $e) {}
        }
        if (!$m) $m = self::fetchMetricsFromDB((int)$r['ad_account_id'], $start, $end, $campIds) ?? [];

        // Período anterior para comparativo
        [$pStart, $pEnd] = self::calcPreviousPeriod($start, $end);
        $pm = null;
        if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
            try { $pm = self::fetchMetricsMeta($r['meta_account_id'], $r['access_token'], $pStart, $pEnd, $campIds); }
            catch (\Throwable $e) {}
        }
        // Fallback período anterior: banco local
        if (!$pm && !empty($r['ad_account_id'])) {
            $pm = self::fetchMetricsFromDB((int)$r['ad_account_id'], $pStart, $pEnd, $campIds);
        }

        $msgFinal = self::buildMessage($r['message_text'] ?? '', [
            'metrics'        => $m,
            'client_name'    => $r['client_name']    ?? '',
            'client_company' => $r['client_company'] ?? '',
            'account_name'   => $r['acc_name']       ?? '',
            'campaign_name'  => $r['title']          ?? '',
            'periodo'        => date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end)),
            'observacoes'    => '',
        ]);

        $spend   = (float)($m['spend']       ?? 0);
        $impr    = (int)  ($m['impressions'] ?? 0);
        $clicks  = (int)  ($m['clicks']      ?? 0);
        $reach   = (int)  ($m['reach']       ?? 0);
        $ctr     = round((float)($m['ctr']   ?? 0), 2);
        $cpc     = round((float)($m['cpc']   ?? 0), 2);
        $cpm     = round((float)($m['cpm']   ?? 0), 2);
        $msgs    = (int)  ($m['msg']         ?? $m['messages'] ?? 0);
        $freq    = round((float)($m['frequency'] ?? 0), 2);
        $leads   = (int)  ($m['leads']       ?? 0);

        // Variações
        $vSpend  = $pm ? self::calcVariation($spend,  (float)($pm['spend']       ?? 0)) : null;
        $vCtr    = $pm ? self::calcVariation($ctr,    (float)($pm['ctr']         ?? 0)) : null;
        $vCpc    = $pm ? self::calcVariation($cpc,    (float)($pm['cpc']         ?? 0)) : null;

        $n = fn($v,$d=2) => number_format($v,$d,',','.');
        $arrow = function(?float $v, bool $invertido=false) {
            if ($v === null) return '';
            $good = $invertido ? ($v < 0) : ($v > 0);
            $cor  = $good ? '#27ae60' : '#e74c3c';
            $sym  = $v > 0 ? '▲' : '▼';
            return "<span style='color:{$cor};font-size:10px'>{$sym} " . abs($v) . "%</span>";
        };

        $siteName = '';
        try {
            $s = $db->query("SELECT site_name, favicon_path, logo_path FROM system_settings LIMIT 1")->fetch();
            $siteName   = $s['site_name']   ?? '';
            $faviconUrl = !empty($s['favicon_path'])
                ? APP_URL . '/public/img/uploads/' . $s['favicon_path']
                : APP_URL . '/public/img/favicon.ico';
        } catch(\Throwable $e){ $siteName = ''; $faviconUrl = APP_URL . '/public/img/favicon.ico'; }

        // Usar template salvo para gerar o PDF
        $tplBgColor  = $tplPalette['bg']  ?? '#ffffff';
        $tplTxtColor = $tplPalette['txt'] ?? '#1a1a2e';

        // Dados reais para o template
        $profV_am  = (int)($m['profile_visit'] ?? $m['profile_visits'] ?? 0);
        $allMetrics = [
            'spend'          => $spend,
            'impressions'    => $impr,
            'clicks'         => $clicks,
            'reach'          => $reach,
            'ctr'            => $ctr,
            'cpc'            => $cpc,
            'cpm'            => $cpm,
            'frequency'      => $freq,
            'messages'       => $msgs,
            'leads'          => $leads,
            'conversions'    => (int)($m['conversions'] ?? 0),
            'roas'           => round((float)($m['roas'] ?? 0), 2),
            'purchases'      => (int)($m['purchase'] ?? $m['purchases'] ?? 0),
            'purchase'       => (int)($m['purchase'] ?? $m['purchases'] ?? 0),
            // Visitas ao perfil — ambos os aliases
            'profile_visits' => $profV_am,
            'profile_visit'  => $profV_am,
            // Custos derivados — calculados uma vez aqui
            'cpl'            => $leads > 0   ? round($spend / $leads,    2) : 0,
            'cmsg'           => $msgs  > 0   ? round($spend / $msgs,     2) : 0,
            'cpv'            => $profV_am > 0 ? round($spend / $profV_am, 2) : 0,
            'custo_por_visita' => $profV_am > 0 ? round($spend / $profV_am, 2) : 0,
            // Aliases adicionais do buildMessage
            'msg'            => $msgs,
            'msg_all'        => $msgs,
            'all_leads'      => $leads,
        ];
        if ($pm) {
            $pSpend = (float)($pm['spend'] ?? 0);
            $pMsgs  = (int)(max($pm['msg'] ?? 0, $pm['msg_all'] ?? 0, $pm['messages'] ?? 0));
            $pConv  = (int)($pm['conversions'] ?? $pm['leads'] ?? 0);
            $pLeads = (int)($pm['leads'] ?? 0);
            $allMetricsPrev = [
                'spend'       => $pSpend,
                'impressions' => (int)($pm['impressions'] ?? 0),
                'clicks'      => (int)($pm['clicks']      ?? 0),
                'reach'       => (int)($pm['reach']       ?? 0),
                'ctr'         => (float)($pm['ctr']       ?? 0),
                'cpc'         => (float)($pm['cpc']       ?? 0),
                'cpm'         => (float)($pm['cpm']       ?? 0),
                'frequency'   => (float)($pm['frequency'] ?? 0),
                'messages'    => $pMsgs,
                'leads'       => $pLeads,
                'conversions' => $pConv,
                'roas'        => (float)($pm['roas']      ?? 0),
                'purchases'   => (int)($pm['purchase']    ?? $pm['purchases'] ?? 0),
                'cpl'               => $pConv  > 0 ? round($pSpend / $pConv, 2)  : 0,
                'cmsg'              => $pMsgs  > 0 ? round($pSpend / $pMsgs, 2)  : 0,
                'profile_visits'    => (int)($pm['profile_visit'] ?? $pm['profile_visits'] ?? 0),
                'profile_visit'     => (int)($pm['profile_visit'] ?? $pm['profile_visits'] ?? 0),
                'cpv'               => ((int)($pm['profile_visit'] ?? $pm['profile_visits'] ?? 0)) > 0 ? round($pSpend / (int)($pm['profile_visit'] ?? $pm['profile_visits']), 2) : 0,
                'custo_por_visita'  => ((int)($pm['profile_visit'] ?? $pm['profile_visits'] ?? 0)) > 0 ? round($pSpend / (int)($pm['profile_visit'] ?? $pm['profile_visits']), 2) : 0,
                'all_leads'         => (int)($pm['leads'] ?? 0),
                'msg'               => $pMsgs,
                'msg_all'           => $pMsgs,
            ];
        } else {
            $allMetricsPrev = [];
        }

        $clientTitle = $r['client_name'] ? e($r['client_name']).($r['client_company']?' — '.e($r['client_company']):'') : e($r['acc_name'] ?? '');
        $periodo = date('d/m/Y',strtotime($start)).' a '.date('d/m/Y',strtotime($end));

        // Gerar blocos HTML pelo template

        // ── Dados diários para gráficos ──────────────────────────────────────
        $dailyData = [];
        $breakdownAge = [];
        $breakdownGender = [];
        try {
            if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
                // Dados diários de spend — filtra por campanha se houver seleção específica
                $fields = 'spend,impressions,clicks,reach,actions,instagram_profile_visits';
                $timeRange = json_encode(['since'=>$start,'until'=>$end]);
                // Se há campanhas específicas, filtra por elas; caso contrário, usa level=account sem filtro
                if (!empty($campIds)) {
                    $dailyFiltering = json_encode([
                        ['field'=>'campaign.id','operator'=>'IN','value'=>array_values($campIds)]
                    ]);
                    $dailyLevel = 'campaign';
                } else {
                    $dailyFiltering = null;
                    $dailyLevel = 'account';
                }
                $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$r['meta_account_id']}/insights"
                     . "?fields=" . urlencode($fields)
                     . "&time_range=" . urlencode($timeRange)
                     . "&time_increment=1"
                     . "&level=" . $dailyLevel
                     . "&limit=500"
                     . "&access_token=" . urlencode($r['access_token']);
                if ($dailyFiltering) {
                    $url .= "&filtering=" . urlencode($dailyFiltering);
                }
                $ch = curl_init($url);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
                $raw = curl_exec($ch);
                $res = json_decode($raw, true);
                curl_close($ch);
                // Se level=campaign retornou múltiplas linhas por dia, agrega por data
                if (!empty($campIds) && !empty($res['data'])) {
                    $byDate = [];
                    foreach ($res['data'] as $row) {
                        $d = $row['date_start'];
                        if (!isset($byDate[$d])) {
                            $byDate[$d] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'actions'=>[],'instagram_profile_visits'=>0,'date_start'=>$d];
                        }
                        $byDate[$d]['spend']       += (float)($row['spend'] ?? 0);
                        $byDate[$d]['impressions'] += (int)($row['impressions'] ?? 0);
                        $byDate[$d]['clicks']      += (int)($row['clicks'] ?? 0);
                        $byDate[$d]['reach']       += (int)($row['reach'] ?? 0);
                        $byDate[$d]['instagram_profile_visits'] += (int)($row['instagram_profile_visits'] ?? 0);
                        foreach ($row['actions'] ?? [] as $a) {
                            $found = false;
                            foreach ($byDate[$d]['actions'] as &$ea) {
                                if ($ea['action_type'] === $a['action_type']) { $ea['value'] += (float)$a['value']; $found=true; break; }
                            }
                            if (!$found) $byDate[$d]['actions'][] = ['action_type'=>$a['action_type'],'value'=>(float)$a['value']];
                        }
                    }
                    ksort($byDate);
                    $res['data'] = array_values($byDate);
                }
                if (empty($res['data'])) {
                    error_log('[PDF Daily] empty. level='.$dailyLevel.' camps='.implode(',',$campIds).' start='.$start.' end='.$end.' resp='.substr($raw,0,300));
                }
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $row) {
                        $msgs = 0; $leadsD = 0; $leadsD_fb = 0; $leadsD_gn = 0;
                        foreach ($row['actions'] ?? [] as $a) {
                            if (strpos($a['action_type'], 'messaging') !== false) $msgs += (int)$a['value'];
                            // Leads diários — prioridade leadgen_grouped (formulário nativo)
                            if (in_array($a['action_type'], ['leadgen_grouped','onsite_conversion.lead_grouped'])) $leadsD_fb += (int)$a['value'];
                            if ($a['action_type'] === 'lead') $leadsD_gn += (int)$a['value'];
                        }
                        $leadsD = $leadsD_fb ?: $leadsD_gn;
                        $pv = (int)($row['instagram_profile_visits'] ?? 0);
                        if (!$pv) { foreach ($row['actions']??[] as $a) { if(strpos($a['action_type'],'profile_visit')!==false) $pv+=(int)$a['value']; } }
                        // Fallback: se API retornou 0 mas há IG profile visits nas actions como 'view_content' ou 'ig_profile_visit'
                        if (!$pv) { foreach ($row['actions']??[] as $a) { if(in_array($a['action_type'],['ig_profile_visit','view_content','post_engagement'])) $pv = max($pv,(int)$a['value']); } }
                        $dailyData[] = [
                            'date'           => $row['date_start'],
                            'spend'          => (float)($row['spend'] ?? 0),
                            'impressions'    => (int)($row['impressions'] ?? 0),
                            'clicks'         => (int)($row['clicks'] ?? 0),
                            'reach'          => (int)($row['reach'] ?? 0),
                            'messages'       => $msgs,
                            'profile_visits' => $pv,
                            'ctr'         => (int)($row['impressions']??0)>0 ? round((int)($row['clicks']??0)/(int)$row['impressions']*100,2) : 0,
                            'cpc'         => (int)($row['clicks']??0)>0 ? round((float)($row['spend']??0)/(int)$row['clicks'],2) : 0,
                            'cpm'         => (int)($row['impressions']??0)>0 ? round((float)($row['spend']??0)/(int)$row['impressions']*1000,2) : 0,
                            'frequency'   => (int)($row['reach']??0)>0 ? round((int)($row['impressions']??0)/(int)$row['reach'],2) : 0,
                            'leads'       => $leadsD,
                            'conversions' => $leadsD,
                            'roas'        => 0,
                        ];
                    }
                }

                // Fallback: se API não retornou dados diários, usa média do banco
                if (empty($dailyData) && !empty($r['ad_account_id'])) {
                    // Try different column names for date
                    try {
                        $dbRows = $db->query(
                            "SELECT date AS date, spend, impressions, clicks, reach, frequency, messages
                             FROM metrics WHERE ad_account_id=? AND date BETWEEN ? AND ? ORDER BY date ASC LIMIT 90",
                            [(int)$r['ad_account_id'], $start, $end]
                        )->fetchAll();
                    } catch (\Throwable $e) {
                        try {
                            $dbRows = $db->query(
                                "SELECT date_start AS date, spend, impressions, clicks, reach, 0 AS frequency, 0 AS messages
                                 FROM metrics WHERE ad_account_id=? AND date_start BETWEEN ? AND ? ORDER BY date_start ASC LIMIT 90",
                                [(int)$r['ad_account_id'], $start, $end]
                            )->fetchAll();
                        } catch (\Throwable $e2) { $dbRows = []; }
                    }
                    foreach ($dbRows as $row) {
                        $imprD = (int)($row['impressions']??0);
                        $clkD  = (int)($row['clicks']??0);
                        $spD   = (float)($row['spend']??0);
                        $rcD   = (int)($row['reach']??0);
                        $dailyData[] = [
                            'date'        => $row['date'],
                            'spend'       => $spD,
                            'impressions' => $imprD,
                            'clicks'      => $clkD,
                            'reach'       => $rcD,
                            'messages'    => (int)($row['messages']??0),
                            'profile_visits' => 0,
                            'ctr'         => $imprD > 0 ? round($clkD/$imprD*100, 2) : 0,
                            'cpc'         => $clkD  > 0 ? round($spD/$clkD, 2) : 0,
                            'cpm'         => $imprD > 0 ? round($spD/$imprD*1000, 2) : 0,
                            'frequency'   => (float)($row['frequency']??0),
                            'leads'=>0,'conversions'=>0,'roas'=>0,
                        ];
                    }
                }

                // Breakdown por idade
                $urlAge = "https://graph.facebook.com/".META_API_VERSION."/act_{$r['meta_account_id']}/insights"
                        . "?fields=spend,impressions,clicks,reach"
                        . "&time_range=" . urlencode($timeRange)
                        . "&breakdowns=age"
                        . "&level=account&limit=50"
                        . "&access_token=" . urlencode($r['access_token']);
                $ch = curl_init($urlAge);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true]);
                $resAge = json_decode(curl_exec($ch), true);
                curl_close($ch);
                foreach ($resAge['data'] ?? [] as $row) {
                    $breakdownAge[] = ['label'=>$row['age'],'spend'=>(float)($row['spend']??0),'reach'=>(int)($row['reach']??0),'clicks'=>(int)($row['clicks']??0)];
                }

                // Breakdown por sexo
                $urlGen = "https://graph.facebook.com/".META_API_VERSION."/act_{$r['meta_account_id']}/insights"
                        . "?fields=spend,impressions,clicks,reach"
                        . "&time_range=" . urlencode($timeRange)
                        . "&breakdowns=gender"
                        . "&level=account&limit=10"
                        . "&access_token=" . urlencode($r['access_token']);
                $ch = curl_init($urlGen);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true]);
                $resGen = json_decode(curl_exec($ch), true);
                curl_close($ch);
                foreach ($resGen['data'] ?? [] as $row) {
                    $gLabel = $row['gender'] === 'male' ? 'Masculino' : ($row['gender'] === 'female' ? 'Feminino' : 'Outro');
                    $breakdownGender[] = ['label'=>$gLabel,'spend'=>(float)($row['spend']??0),'reach'=>(int)($row['reach']??0),'clicks'=>(int)($row['clicks']??0)];
                }
            }
        } catch (\Throwable $e) {}

        // ── Geo e demográfico ────────────────────────────────────────────────
        $breakdownCity = [];
        $breakdownRegion = [];
        $breakdownCountry = [];
        $breakdownAgeGender = [];
        if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
            try {
                $geoBase2 = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$r['meta_account_id']}/insights"
                          . "?fields=reach,spend"
                          . "&time_range=" . urlencode(json_encode(['since'=>$start,'until'=>$end]))
                          . "&level=account&limit=20"
                          . "&access_token=" . urlencode($r['access_token']);
                // Estados (region)
                $chG = curl_init($geoBase2 . "&breakdowns=region");
                curl_setopt_array($chG, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_SSL_VERIFYPEER=>true]);
                $rgD = json_decode(curl_exec($chG), true);
                curl_close($chG);
                foreach ($rgD['data'] ?? [] as $row) {
                    if (!empty($row['region'])) {
                        $breakdownRegion[] = ['label' => $row['region'], 'reach' => (int)($row['reach'] ?? 0), 'spend' => (float)($row['spend'] ?? 0)];
                    }
                }
                usort($breakdownRegion, function($a, $b) { return $b['reach'] - $a['reach']; });
                $breakdownRegion = array_slice($breakdownRegion, 0, 5);
                // Cidades - busca via adsets targeting geo_locations (única forma confiável no Meta)
                $adsetUrl = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$r['meta_account_id']}/adsets"
                          . "?fields=targeting,insights.time_range(" . urlencode(json_encode(['since'=>$start,'until'=>$end])) . "){reach}"
                          . "&limit=50"
                          . "&access_token=" . urlencode($r['access_token']);
                $chCity = curl_init($adsetUrl);
                curl_setopt_array($chCity, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true]);
                $cityRaw = curl_exec($chCity);
                $cityD = json_decode($cityRaw, true);
                curl_close($chCity);
                $cityAgg = [];
                foreach ($cityD['data'] ?? [] as $adset) {
                    $reach = (int)(($adset['insights']['data'][0]['reach'] ?? 0));
                    $cities = $adset['targeting']['geo_locations']['cities'] ?? [];
                    $numCities = count($cities);
                    if ($numCities === 0) continue;
                    // Acumula alcance real por cidade (divide pelo nº de cidades do adset para evitar duplicação)
                    $reachPerCity = $numCities > 0 ? (int)round($reach / $numCities) : $reach;
                    foreach ($cities as $city) {
                        $lbl = $city['name'] ?? '';
                        if (!empty($lbl)) {
                            if (!isset($cityAgg[$lbl])) $cityAgg[$lbl] = ['reach'=>0,'adsets'=>0];
                            $cityAgg[$lbl]['reach'] += $reachPerCity;
                            $cityAgg[$lbl]['adsets'] += 1;
                        }
                    }
                }
                if (!empty($cityAgg)) {
                    // Ordena por alcance total
                    uasort($cityAgg, function($a,$b){ return $b['reach'] - $a['reach']; });
                    foreach (array_slice($cityAgg, 0, 5, true) as $lbl => $data) {
                        $breakdownCity[] = ['label' => $lbl, 'reach' => $data['reach'], 'spend' => 0];
                    }
                }
                // Fallback: usa estados se não há targeting por cidade
                if (empty($breakdownCity)) {
                    $breakdownCity = $breakdownRegion;
                }
                // Países
                $chC = curl_init($geoBase2 . "&breakdowns=country");
                curl_setopt_array($chC, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_SSL_VERIFYPEER=>true]);
                $ctD = json_decode(curl_exec($chC), true);
                curl_close($chC);
                $cnames = ['BR'=>'Brasil','PT'=>'Portugal','US'=>'EUA','AR'=>'Argentina','MX'=>'México','CL'=>'Chile','CO'=>'Colômbia','ES'=>'Espanha','DE'=>'Alemanha','FR'=>'França'];
                foreach ($ctD['data'] ?? [] as $row) {
                    if (!empty($row['country'])) {
                        $breakdownCountry[] = ['label' => ($cnames[$row['country']] ?? $row['country']), 'reach' => (int)($row['reach'] ?? 0), 'spend' => (float)($row['spend'] ?? 0)];
                    }
                }
                usort($breakdownCountry, function($a, $b) { return $b['reach'] - $a['reach']; });
                $breakdownCountry = array_slice($breakdownCountry, 0, 5);
                // Idade + Gênero
                $urlAG2 = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$r['meta_account_id']}/insights?fields=" . urlencode("reach,impressions,clicks,spend,actions,cost_per_action_type") . "&time_range=" . urlencode(json_encode(['since'=>$start,'until'=>$end])) . "&level=account&breakdowns=age,gender&limit=50&access_token=" . urlencode($r['access_token']);
                $chAG = curl_init($urlAG2);
                curl_setopt_array($chAG, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_SSL_VERIFYPEER=>true]);
                $agD = json_decode(curl_exec($chAG), true);
                curl_close($chAG);
                foreach ($agD['data'] ?? [] as $row) {
                    $gPt  = $row['gender'] === 'female' ? 'Mulheres' : ($row['gender'] === 'male' ? 'Homens' : 'Outro');
                    $msgs = 0; $cpMsg = 0;
                    foreach ($row['actions'] ?? [] as $a) {
                        if (strpos($a['action_type'], 'messaging_conversation') !== false) $msgs += (int)$a['value'];
                    }
                    foreach ($row['cost_per_action_type'] ?? [] as $a) {
                        if (strpos($a['action_type'], 'messaging_conversation') !== false) $cpMsg = (float)$a['value'];
                    }
                    $impr = (int)($row['impressions'] ?? 0);
                    $clk  = (int)($row['clicks'] ?? 0);
                    $ctr  = $impr > 0 ? round($clk / $impr * 100, 2) : 0;
                    $breakdownAgeGender[] = [
                        'label'  => $row['age'] . ', ' . $gPt,
                        'gender' => $gPt,
                        'reach'  => (int)($row['reach'] ?? 0),
                        'msgs'   => $msgs,
                        'cpmsg'  => $cpMsg,
                        'ctr'    => $ctr,
                        'spend'  => (float)($row['spend'] ?? 0),
                    ];
                }
                usort($breakdownAgeGender, function($a, $b) { return $b['reach'] - $a['reach']; });
            } catch (Exception $eGeo) {
                // silently fail - geo data is optional
            }
        }

        

        $dailyJson    = json_encode($dailyData);
        $ageJson      = json_encode($breakdownAge);
        $genderJson   = json_encode($breakdownGender);
        $accentJs     = $tplAccent;
        // Debug info: apenas no log de erros, nunca exposto ao cliente
        $debugInfo = count($dailyData).' dias | meta_id:'.($r['meta_account_id']??'?').' | start:'.$start.' | end:'.$end;
        if (APP_ENV === 'development') {
            error_log("[PDF Debug] $debugInfo");
        }

        $kpiColorMap = [
            'spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6',
            'ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','reach'=>'#f39c12',
            'frequency'=>'#1abc9c','messages'=>'#2ecc71','leads'=>'#3498db',
            'roas'=>'#27ae60','conversions'=>'#27ae60','purchases'=>'#8e44ad',
            'cpl'=>'#e74c3c','cmsg'=>'#e74c3c','profile_visits'=>'#9b59b6','cpv'=>'#e74c3c',
        ];
        function pdfKpiCard($lbl, $val, $accent, $key='') {
            global $kpiColorMap;
            $col = $kpiColorMap[$key] ?? $accent;
            return '<div class="kc" style="border-left:3px solid '.$col.';background:linear-gradient(135deg,'.$col.'08,#f8f9fc)">'
                .'<div class="kl" style="color:'.$col.'88">'.$lbl.'</div>'
                .'<div class="kv" style="color:'.$col.'">'.$val.'</div></div>';
        }
        function pdfArrow($cur, $prev, $higherBetter=true) {
            if(!$prev) return '';
            $v = round(($cur-$prev)/$prev*100,1);
            $good = $higherBetter ? ($v>0) : ($v<0);
            $cor = $good ? '#27ae60' : '#e74c3c';
            $sym = $v>0 ? '▲' : '▼';
            return "<span style='color:$cor;font-size:10px'>$sym ".abs($v)."%</span>";
        }

        // Mapa: chave do editor → chave real nos dados + label + formato
        // O editor salva: spend, impr, clicks, ctr, cpc, cpm, reach, freq, msgs, leads, roas, conv, cpl, cmsg
        // O generatePdf precisa mapear para as chaves reais
        $kpiMap = [
            // chave_editor => [chave_allMetrics, label, formato]
            'spend'         => ['spend',            '💰 Investimento',   'R$'],
            'impr'          => ['impressions',       '👁 Impressões',     'n'],
            'clicks'        => ['clicks',            '🖱 Cliques',        'n'],
            'ctr'           => ['ctr',               '📊 CTR',            '%'],
            'cpc'           => ['cpc',               '💵 CPC',            'R$'],
            'cpm'           => ['cpm',               '📈 CPM',            'R$'],
            'reach'         => ['reach',             '👥 Alcance',        'n'],
            'freq'          => ['frequency',         '🔁 Frequência',     'x'],
            'msgs'          => ['messages',          '💬 Mensagens',      'n'],
            'leads'         => ['leads',             '🎯 Leads',          'n'],
            'roas'          => ['roas',              '📈 ROAS',           'x'],
            'conv'          => ['conversions',       '✅ Conversões',     'n'],
            'cpl'           => ['cpl',               '💵 Custo/Lead',     'R$'],
            'cmsg'          => ['cmsg',              '💵 Custo/Msg',      'R$'],
            'profile_visit' => ['profile_visits',    '👤 Visitas Perfil', 'n'],
            'cpv'           => ['custo_por_visita',  '💵 Custo/Visita',   'R$'],
            // aliases diretos
            'impressions'   => ['impressions',       '👁 Impressões',     'n'],
            'frequency'     => ['frequency',         '🔁 Frequência',     'x'],
            'messages'      => ['messages',          '💬 Mensagens',      'n'],
            'conversions'   => ['conversions',       '✅ Conversões',     'n'],
            'purchases'     => ['purchases',         '🛒 Compras',        'n'],
        ];

        // Garante todos os campos derivados no allMetrics
        $allMetrics['cpl']              = ($allMetrics['conversions']  ?? 0) > 0 ? round(($allMetrics['spend'] ?? 0) / $allMetrics['conversions'],  2) : ($allMetrics['cpl']  ?? 0);
        $allMetrics['cmsg']             = ($allMetrics['messages']     ?? 0) > 0 ? round(($allMetrics['spend'] ?? 0) / $allMetrics['messages'],     2) : ($allMetrics['cmsg'] ?? 0);
        $allMetrics['custo_por_visita'] = ($allMetrics['profile_visits'] ?? $allMetrics['profile_visit'] ?? 0) > 0
            ? round(($allMetrics['spend'] ?? 0) / ($allMetrics['profile_visits'] ?? $allMetrics['profile_visit']), 2)
            : ($allMetrics['custo_por_visita'] ?? 0);
        // Garante alias profile_visits e profile_visit apontam para o mesmo valor
        if (empty($allMetrics['profile_visits']) && !empty($allMetrics['profile_visit'])) {
            $allMetrics['profile_visits'] = $allMetrics['profile_visit'];
        }
        if (empty($allMetrics['profile_visit']) && !empty($allMetrics['profile_visits'])) {
            $allMetrics['profile_visit'] = $allMetrics['profile_visits'];
        }

        // Labels/formatos para comparativo (chaves reais)
        $kpiLabels = [
            'spend'=>'💰 Investimento','impressions'=>'👁 Impressões','clicks'=>'🖱 Cliques',
            'reach'=>'👥 Alcance','ctr'=>'📊 CTR','cpc'=>'💵 CPC','cpm'=>'📈 CPM',
            'frequency'=>'🔁 Frequência','messages'=>'💬 Mensagens','leads'=>'🎯 Leads',
            'conversions'=>'✅ Conversões','roas'=>'📈 ROAS','purchases'=>'🛒 Compras',
            'profile_visits'=>'👤 Visitas Perfil','cpv'=>'💵 Custo/Visita',
        ];
        $kpiFormats = [
            'spend'=>'R$','cpc'=>'R$','cpm'=>'R$','ctr'=>'%','frequency'=>'x','roas'=>'x',
        ];

        $blocksHtml = '';
        foreach ($tplBlocks as $blockIdx => $blockKey) {
            $bCfg = $tplBlockCfgs[$blockIdx] ?? [];
            $ap   = $tplAccent;

            switch ($blockKey) {
                case 'header':
                    $t  = $bCfg['title']    ?? $r['title'];
                    $cl = $bCfg['client']   ?? ($r['client_name'] ?? '');
                    $co = $bCfg['company']  ?? ($r['client_company'] ?? $r['acc_name'] ?? '');
                    // Período SEMPRE vem das datas reais selecionadas (não do bCfg estático)
                    $pr = $periodo;
                    $sn = $bCfg['siteName'] ?? $siteName;
                    $lg = !empty($bCfg['logo']) ? "<img src='{$bCfg['logo']}' style='max-height:44px;max-width:120px;object-fit:contain'>" : "<span style='font-size:16px;font-weight:900;color:$ap'>".e($sn)."</span>";
                    $blocksHtml .= "<div style='display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid $ap;padding-bottom:14px;margin-bottom:20px'>"
                        ."<div><h1 style='font-size:19px;font-weight:700;color:$ap;margin:0'>".e($t)."</h1>"
                        ."<div style='font-size:11px;color:#666;margin-top:3px'>".e($cl).($co?" — ".e($co):"")."</div>"
                        ."<span style='display:inline-block;background:#EEF4FF;color:$ap;font-size:10px;font-weight:600;padding:2px 9px;border-radius:20px;margin-top:5px'>📅 ".e($pr)."</span></div>"
                        ."<div>$lg</div></div>";
                    break;

                case 'kpis':
                    $defaultEditorKeys = ['spend','impr','clicks','ctr','cpc','cpm','reach','freq'];
                    $allKpiKeys = array_keys($kpiMap);
                    // Verifica se alguma chave de KPI foi configurada no editor
                    $hasCfgKeys = false;
                    foreach ($bCfg as $ek => $ev) {
                        if (isset($kpiMap[$ek])) { $hasCfgKeys = true; break; }
                    }
                    if (!$hasCfgKeys) {
                        // Nunca configurado: usa padrão 8
                        $editorKeys = $defaultEditorKeys;
                    } else {
                        // Configurado: só os marcados como true/1
                        $editorKeys = [];
                        foreach ($bCfg as $ek => $ev) {
                            if (($ev === true || $ev === 1 || $ev === '1') && isset($kpiMap[$ek])) {
                                $editorKeys[] = $ek;
                            }
                        }
                        if (empty($editorKeys)) $editorKeys = $defaultEditorKeys;
                    }
                    $cols = min(count($editorKeys), 4);
                    $cards = '';
                    foreach ($editorKeys as $ek) {
                        [$dataKey, $lbl, $fmt] = $kpiMap[$ek];
                        $v = $allMetrics[$dataKey] ?? 0;
                        $valStr = $fmt === 'R$' ? 'R$ '.$n($v) : ($fmt === '%' ? $n($v).'%' : ($fmt === 'x' ? $n($v).'x' : $n($v,0)));
                        $cards .= pdfKpiCard($lbl, $valStr, $ap);
                    }
                    $blocksHtml .= '<div style="margin-bottom:16px"><div class="ps">Métricas Principais</div>'
                        .'<div class="kg" style="grid-template-columns:repeat('.$cols.',1fr)">'.$cards.'</div></div>';
                    break;

                case 'kpis2':
                    $defaultEditorKeys2 = ['msgs','leads','conv'];
                    $hasCfgKeys2 = false;
                    foreach ($bCfg as $ek => $ev) {
                        if (isset($kpiMap[$ek])) { $hasCfgKeys2 = true; break; }
                    }
                    if (!$hasCfgKeys2) {
                        $editorKeys2 = $defaultEditorKeys2;
                    } else {
                        $editorKeys2 = [];
                        foreach ($bCfg as $ek => $ev) {
                            if (($ev === true || $ev === 1 || $ev === '1') && isset($kpiMap[$ek])) {
                                $editorKeys2[] = $ek;
                            }
                        }
                        if (empty($editorKeys2)) $editorKeys2 = $defaultEditorKeys2;
                    }
                    $cols2 = min(count($editorKeys2), 4);
                    $cards2 = '';
                    foreach ($editorKeys2 as $ek) {
                        [$dataKey, $lbl, $fmt] = $kpiMap[$ek];
                        $v = $allMetrics[$dataKey] ?? 0;
                        $valStr = $fmt === 'R$' ? 'R$ '.$n($v) : ($fmt === '%' ? $n($v).'%' : ($fmt === 'x' ? $n($v).'x' : $n($v,0)));
                        $cards2 .= pdfKpiCard($lbl, $valStr, $ap);
                    }
                    $blocksHtml .= '<div style="margin-bottom:16px"><div class="ps">Resultados</div>'
                        .'<div class="kg" style="grid-template-columns:repeat('.$cols2.',1fr)">'.$cards2.'</div></div>';
                    break;

                case 'comparative':
                    $period_lbl = $bCfg['compPeriod'] ?? 'Período Anterior';
                    // Editor usa chaves curtas (spend, impr, clicks...) — mapear para dados reais
                    $allCompMap = [
                        'spend'  => ['spend','💰 Investimento','R$',false],
                        'impr'   => ['impressions','👁 Impressões','n',true],
                        'clicks' => ['clicks','🖱 Cliques','n',true],
                        'ctr'    => ['ctr','📊 CTR','%',true],
                        'cpc'    => ['cpc','💵 CPC','R$',false],
                        'cpm'    => ['cpm','📈 CPM','R$',false],
                        'reach'  => ['reach','👥 Alcance','n',true],
                        'freq'   => ['frequency','🔁 Frequência','x',false],
                        'msgs'   => ['messages','💬 Mensagens','n',true],
                        'leads'  => ['leads','🎯 Leads','n',true],
                        'roas'   => ['roas','📈 ROAS','x',true],
                        'conv'   => ['conversions','✅ Conversões','n',true],
                        'cpl'    => ['cpl','💵 Custo/Lead','R$',false],
                        'cmsg'   => ['cmsg','💵 Custo/Msg','R$',false],
                        'profile_visit' => ['profile_visits','👤 Visitas Perfil','n',true],
                        'cpv'           => ['cpv','💵 Custo/Visita','R$',false],
                    ];
                    // Determinar quais colunas mostrar
                    $hasCfgComp = false;
                    foreach ($bCfg as $ek => $ev) { if (isset($allCompMap[$ek])) { $hasCfgComp = true; break; } }
                    if (!$hasCfgComp) {
                        // Sem config: todas as métricas
                        $compEditorKeys = ['spend','impr','clicks','ctr','cpc','cpm','reach','freq','msgs','leads','roas','conv','cpl','cmsg'];
                    } else {
                        $compEditorKeys = [];
                        foreach ($bCfg as $ek => $ev) {
                            if (($ev === true || $ev === 1 || $ev === '1') && isset($allCompMap[$ek])) $compEditorKeys[] = $ek;
                        }
                        if (empty($compEditorKeys)) $compEditorKeys = ['spend','impr','clicks','ctr','cpc','cpm','reach','freq'];
                    }
                    $rows = '';
                    foreach ($compEditorKeys as $ek) {
                        [$dataKey, $lbl, $fmt, $hiGood] = $allCompMap[$ek];
                        $cur  = $allMetrics[$dataKey]     ?? 0;
                        $prev = $allMetricsPrev[$dataKey] ?? 0;
                        $cs   = $fmt==='R$' ? 'R$ '.$n($cur) : ($fmt==='%' ? $n($cur).'%' : ($fmt==='x' ? $n($cur).'x' : $n($cur,0)));
                        $ps   = $prev > 0 ? ($fmt==='R$' ? 'R$ '.$n($prev) : ($fmt==='%' ? $n($prev).'%' : ($fmt==='x' ? $n($prev).'x' : $n($prev,0)))) : '—';
                        $arr  = $prev > 0 ? pdfArrow($cur, $prev, $hiGood) : '';
                        $rows .= "<tr><td>$lbl</td><td>$cs</td><td>$ps</td><td>$arr</td></tr>";
                    }
                    $pStartFmt = date('d/m/Y', strtotime($pStart));
                    $pEndFmt   = date('d/m/Y', strtotime($pEnd));
                    $startFmt  = date('d/m/Y', strtotime($start));
                    $endFmt    = date('d/m/Y', strtotime($end));
                    $blocksHtml .= '<div style="margin-bottom:16px"><div class="ps">Comparativo de Períodos</div>'
                        .'<table class="pt"><thead><tr>'
                        .'<th>Métrica</th>'
                        .'<th>Atual<br><span style="font-weight:400;font-size:9px;color:#888">'.$startFmt.' a '.$endFmt.'</span></th>'
                        .'<th>Anterior<br><span style="font-weight:400;font-size:9px;color:#888">'.$pStartFmt.' a '.$pEndFmt.'</span></th>'
                        .'<th>Variação</th>'
                        .'</tr></thead>'
                        .'<tbody>'.$rows.'</tbody></table></div>';
                    break;

                case 'message':
                    $msg = $bCfg['msg'] ?? $msgFinal;
                    if ($msg) {
                        $blocksHtml .= '<div style="margin-bottom:16px"><div class="ps">Mensagem do Relatório</div>'
                            .'<div class="pm">'.nl2br(htmlspecialchars($msg)).'</div></div>';
                    }
                    break;

                case 'chart':
                    $chartId = 'ch'.($blockIdx).'_'.rand(1000,9999);
                    if (empty($dailyData)) {
                        $blocksHtml .= "<div style='background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#856404'>⚠️ Sem dados disponíveis para o período selecionado.</div>";
                        break;
                    }
                    $chartMetric = $bCfg['metric'] ?? 'spend';
                    $metricLabels = ['spend'=>'Investimento (R$)','impressions'=>'Impressões','clicks'=>'Cliques','reach'=>'Alcance','messages'=>'Mensagens','ctr'=>'CTR (%)','cpc'=>'CPC (R$)','cpm'=>'CPM (R$)','frequency'=>'Frequência','leads'=>'Leads','conversions'=>'Conversões','roas'=>'ROAS','profile_visits'=>'Visitas ao Perfil'];
                    $metricLbl = $metricLabels[$chartMetric] ?? $chartMetric;
                    $weekdaysL = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                    $chartLabels = array_map(fn($d) => $weekdaysL[date('w', strtotime($d['date']))].' '.date('d/m', strtotime($d['date'])), $dailyData);
                    $chartValues = array_map(fn($d) => (float)($d[$chartMetric] ?? 0), $dailyData);
                    if (empty($chartLabels)) { $chartLabels = ['Sem dados']; $chartValues = [0]; }
                    $metricColorMap = ['spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6','reach'=>'#f39c12','messages'=>'#2ecc71','ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','frequency'=>'#1abc9c','leads'=>'#3498db','conversions'=>'#27ae60','roas'=>'#27ae60','profile_visits'=>'#8e44ad'];
                    $lineColors = ['spend'=>'#e74c3c','impressions'=>'#00B894','clicks'=>'#6C5CE7','reach'=>'#E84393','messages'=>'#FDCB6E','ctr'=>'#00CEC9','cpc'=>'#A29BFE','cpm'=>'#FD79A8','frequency'=>'#55EFC4','leads'=>'#74B9FF','conversions'=>'#F9CA24','roas'=>'#D63031','profile_visits'=>'#6C5CE7'];
                    $chartColorMap = ['spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6','reach'=>'#f39c12','messages'=>'#2ecc71','ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','frequency'=>'#1abc9c','leads'=>'#3498db','conversions'=>'#27ae60','roas'=>'#27ae60','profile_visits'=>'#8e44ad']; $chartColorMap=['spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6','reach'=>'#f39c12','messages'=>'#2ecc71','ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','frequency'=>'#1abc9c','leads'=>'#3498db','conversions'=>'#27ae60','roas'=>'#27ae60','profile_visits'=>'#8e44ad']; $chartColor=$chartColorMap[$chartMetric]??'#e74c3c';
                    $labelsJson = json_encode($chartLabels);
                    $valuesJson = json_encode($chartValues);
                    $blocksHtml .= "<div style='margin-bottom:16px'><div class='ps' style='color:$chartColor'>📈 $metricLbl</div>"
                        ."<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px'>"
                        ."<canvas id='$chartId' height='110'></canvas></div></div>"
                        ."<script>window._cq=window._cq||[];window._cq.push(function(){new Chart(document.getElementById('$chartId'),{type:'line',data:{labels:$labelsJson,datasets:[{label:'$metricLbl',data:$valuesJson,borderColor:'$chartColor',backgroundColor:'${chartColor}33',tension:.4,fill:true,pointRadius:3,pointHoverRadius:5,pointBackgroundColor:'$chartColor'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{font:{size:9}}},x:{ticks:{font:{size:9},maxTicksLimit:12}}}}});});</script>";
                    break;

                case 'chart_bar':
                    $chartId2   = 'cb'.($blockIdx).'_'.rand(1000,9999);
                    if (empty($dailyData)) {
                        $blocksHtml .= "<div style='background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#856404'>⚠️ Sem dados disponíveis para o período selecionado.</div>";
                        break;
                    }
                    $barMetric  = $bCfg['bar_metric']  ?? 'spend';
                    $barMetric2 = $bCfg['bar_metric2'] ?? '';
                    $barMetricLabels = ['spend'=>'Investimento (R$)','impressions'=>'Impressões','clicks'=>'Cliques','reach'=>'Alcance','messages'=>'Mensagens','ctr'=>'CTR (%)','cpc'=>'CPC (R$)','cpm'=>'CPM (R$)','frequency'=>'Frequência','leads'=>'Leads','conversions'=>'Conversões','roas'=>'ROAS','profile_visits'=>'Visitas ao Perfil'];
                    $barLbl  = $barMetricLabels[$barMetric]  ?? $barMetric;
                    $barLbl2 = $barMetricLabels[$barMetric2] ?? '';
                    $weekdays = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                    $barDatesArr = array_map(fn($d) => $weekdays[date('w', strtotime($d['date']))].' '.date('d/m', strtotime($d['date'])), $dailyData);
                    $barVals1Arr = array_map(fn($d) => (float)($d[$barMetric]  ?? 0), $dailyData);
                    if (empty($barDatesArr)) { $barDatesArr = ['Sem dados']; $barVals1Arr = [0]; }
                    $barLabels = json_encode($barDatesArr);
                    $barVals1  = json_encode($barVals1Arr);
                    $barVals2  = $barMetric2 ? json_encode(array_map(fn($d) => (float)($d[$barMetric2] ?? 0), $dailyData)) : 'null';
                    $barColors = ['spend'=>'#e74c3c','impressions'=>'#4ECDC4','clicks'=>'#A29BFE','reach'=>'#FD79A8','messages'=>'#FDCB6E','ctr'=>'#6C5CE7','cpc'=>'#00B894','cpm'=>'#E17055','frequency'=>'#74B9FF','leads'=>'#55EFC4','conversions'=>'#00CEC9','roas'=>'#E84393','profile_visits'=>'#F9CA24'];
                    $barColorMap = ['spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6','reach'=>'#f39c12','messages'=>'#2ecc71','ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','frequency'=>'#1abc9c','leads'=>'#3498db','conversions'=>'#27ae60','roas'=>'#27ae60','profile_visits'=>'#8e44ad']; $barColorMap=['spend'=>'#e74c3c','impressions'=>'#3498db','clicks'=>'#9b59b6','reach'=>'#f39c12','messages'=>'#2ecc71','ctr'=>'#27ae60','cpc'=>'#e67e22','cpm'=>'#e67e22','frequency'=>'#1abc9c','leads'=>'#3498db','conversions'=>'#27ae60','roas'=>'#27ae60','profile_visits'=>'#8e44ad']; $barColor1=$barColorMap[$barMetric]??'#e74c3c';
                    $barColor2 = !empty($bCfg['bar_color2']) ? $bCfg['bar_color2'] : ($metricColorMap[$barMetric2] ?? '#f39c12');
                    if ($barMetric2 && $barVals2 !== 'null') {
                        $ds2Str   = ",{label:'$barLbl2',data:$barVals2,type:'line',borderColor:'$barColor2',backgroundColor:'$barColor2'+'22',borderWidth:2,pointRadius:3,tension:.3,fill:false,yAxisID:'y2'}";
                        $scaleStr = ",y2:{beginAtZero:true,position:'right',ticks:{font:{size:9},color:'$barColor2'},grid:{drawOnChartArea:false}}";
                    } else {
                        $ds2Str   = '';
                        $scaleStr = '';
                        $showLegend = 'false';
                    }
                    $showLegend = $barMetric2 ? 'true' : 'false';
                    $barTitle = $barLbl . ($barMetric2 ? " vs $barLbl2" : ' Diário');
                    $blocksHtml .= "<div style='margin-bottom:16px'><div class='ps' style='color:$barColor1'>📊 $barTitle</div>"
                        ."<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px'>"
                        ."<canvas id='$chartId2' height='110'></canvas></div></div>"
                        ."<script>window._cq=window._cq||[];window._cq.push(function(){new Chart(document.getElementById('$chartId2'),{type:'bar',data:{labels:$barLabels,datasets:[{label:'$barLbl',data:$barVals1,backgroundColor:'${barColor1}BB',borderColor:'$barColor1',borderWidth:1,borderRadius:4,yAxisID:'y'}$ds2Str]},options:{responsive:true,plugins:{legend:{display:$showLegend,labels:{font:{size:9},boxWidth:10}}},scales:{y:{beginAtZero:true,position:'left',ticks:{font:{size:9},color:'$barColor1'},grid:{color:'#f0f0f0'}},x:{ticks:{font:{size:9},maxTicksLimit:12}}$scaleStr}}});});</script>";
                    break;

                case 'audience':
                    $audId1 = 'au1'.($blockIdx).'_'.rand(1000,9999);
                    $audId2 = 'au2'.($blockIdx).'_'.rand(1000,9999);
                    $ageLabels  = json_encode(array_column($breakdownAge, 'label'));
                    $ageSpend   = json_encode(array_column($breakdownAge, 'reach'));
                    $genLabels  = json_encode(array_column($breakdownGender, 'label'));
                    $genReach   = json_encode(array_column($breakdownGender, 'reach'));
                    // Cores por sexo: masculino=azul, feminino=rosa, outro=cinza
                    $genderColorMap = ['Masculino'=>'#3498db', 'Feminino'=>'#e91e8c', 'Outro'=>'#95a5a6', 'male'=>'#3498db', 'female'=>'#e91e8c', 'unknown'=>'#95a5a6'];
                    $genderColors = array_map(fn($g) => $genderColorMap[$g['label']] ?? '#95a5a6', $breakdownGender);
                    $colors = json_encode($genderColors ?: ['#3498db', '#e91e8c', '#95a5a6']);
                    if (!empty($breakdownAge) || !empty($breakdownGender)) {
                        $blocksHtml .= "<div style='margin-bottom:16px'><div class='ps'>Audiência</div>"
                            ."<div style='display:grid;grid-template-columns:1fr 1fr;gap:12px'>";
                        if (!empty($breakdownAge)) {
                            $blocksHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:12px'>"
                                ."<div style='font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:8px'>Por Faixa Etária</div>"
                                ."<canvas id='$audId1' height='180'></canvas></div>"
                                ."<script>window._cq=window._cq||[];window._cq.push(function(){new Chart(document.getElementById('$audId1'),{type:'bar',data:{labels:$ageLabels,datasets:[{label:'Alcance',data:$ageSpend,backgroundColor:$ageLabels.map?$ageLabels.map(function(_,i){var colors=['#3498db','#9b59b6','#e91e8c','#f39c12','#27ae60','#e74c3c','#1abc9c'];return colors[i%colors.length]+'CC';}):'${accentJs}99',borderRadius:5}]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return 'Alcance: '+ctx.parsed.x.toLocaleString('pt-BR');}}}},scales:{x:{beginAtZero:true,ticks:{font:{size:8}}},y:{ticks:{font:{size:8}}}}}});});</script>";
                        }
                        if (!empty($breakdownGender)) {
                            $blocksHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:12px'>"
                                ."<div style='font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:8px'>Por Sexo</div>"
                                ."<canvas id='$audId2' height='180'></canvas></div>"
                                ."<script>window._cq=window._cq||[];window._cq.push(function(){new Chart(document.getElementById('$audId2'),{type:'doughnut',data:{labels:$genLabels,datasets:[{data:$genReach,backgroundColor:$colors,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:9},generateLabels:function(chart){var data=chart.data;var total=data.datasets[0].data.reduce(function(a,b){return a+b;},0);return data.labels.map(function(label,i){var val=data.datasets[0].data[i];var pct=total>0?Math.round(val/total*100):0;return {text:label+' '+pct+'%',fillStyle:data.datasets[0].backgroundColor[i],strokeStyle:'#fff',lineWidth:2,index:i};});}}},tooltip:{callbacks:{label:function(ctx){var total=ctx.dataset.data.reduce(function(a,b){return a+b;},0);var pct=total>0?Math.round(ctx.parsed/total*100):0;return ctx.label+': '+ctx.parsed.toLocaleString('pt-BR')+' ('+pct+'%)';}}}}}});});</script>";
                        }
                        $blocksHtml .= "</div></div>";
                    } else {
                        $blocksHtml .= "<div style='margin-bottom:16px'><div class='ps'>Audiência</div>"
                            ."<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px;text-align:center;color:#aaa;font-size:12px'>Dados de audiência não disponíveis para este período</div></div>";
                    }
                    break;

                case 'demographic':
                    if (empty($breakdownAgeGender)) {
                        $blocksHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px;margin-bottom:16px;text-align:center;color:#aaa;font-size:12px'>Dados demográficos não disponíveis para este período</div>";
                        break;
                    }
                    $demHtml  = "<div style='margin-bottom:16px'><div class='ps' style='color:{$accentJs}'>Dados Demográficos</div>";
                    $demHtml .= "<div style='overflow-x:auto'><table style='width:100%;border-collapse:collapse;font-size:10px'>";
                    $demHtml .= "<thead><tr style='background:#f8f9fc'>"
                        ."<th style='padding:6px 8px;text-align:left;border-bottom:2px solid #e8eaf0;color:#555;font-weight:700'>Segmento</th>"
                        ."<th style='padding:6px 8px;text-align:right;border-bottom:2px solid #e8eaf0;color:#555;font-weight:700'>Alcance</th>"
                        ."<th style='padding:6px 8px;text-align:right;border-bottom:2px solid #e8eaf0;color:#2ecc71;font-weight:700'>Conversas</th>"
                        ."<th style='padding:6px 8px;text-align:right;border-bottom:2px solid #e8eaf0;color:#e74c3c;font-weight:700'>Custo/Conv</th>"
                        ."<th style='padding:6px 8px;text-align:right;border-bottom:2px solid #e8eaf0;color:#3498db;font-weight:700'>CTR</th>"
                        ."</tr></thead><tbody>";
                    $rowColors = ['Mulheres'=>'#e91e8c','Homens'=>'#3498db','Outro'=>'#95a5a6'];
                    foreach (array_slice($breakdownAgeGender, 0, 10) as $i => $row) {
                        $bg = $i % 2 === 0 ? '#fff' : '#fafafa';
                        $gColor = $rowColors[$row['gender']] ?? '#555';
                        $dot = "<span style='display:inline-block;width:7px;height:7px;border-radius:50%;background:{$gColor};margin-right:4px'></span>";
                        $demHtml .= "<tr style='background:{$bg}'>"
                            ."<td style='padding:5px 8px;border-bottom:1px solid #f0f0f0;color:#333;font-weight:500'>{$dot}".htmlspecialchars($row['label'])."</td>"
                            ."<td style='padding:5px 8px;border-bottom:1px solid #f0f0f0;text-align:right;color:#555'>".number_format($row['reach'],0,',','.')."</td>"
                            ."<td style='padding:5px 8px;border-bottom:1px solid #f0f0f0;text-align:right;color:#2ecc71;font-weight:700'>".$row['msgs']."</td>"
                            ."<td style='padding:5px 8px;border-bottom:1px solid #f0f0f0;text-align:right;color:#e74c3c'>".($row['cpmsg']>0 ? 'R$ '.number_format($row['cpmsg'],2,',','.') : '—')."</td>"
                            ."<td style='padding:5px 8px;border-bottom:1px solid #f0f0f0;text-align:right;color:#3498db'>".$row['ctr']."%</td>"
                            ."</tr>";
                    }
                    $demHtml .= "</tbody></table></div></div>";
                    $blocksHtml .= $demHtml;
                    break;

                case 'geo':
                    if (empty($breakdownCity) && empty($breakdownRegion) && empty($breakdownCountry)) {
                        $blocksHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px;margin-bottom:16px;text-align:center;color:#aaa;font-size:12px'>Dados de localização não disponíveis para este período</div>";
                        break;
                    }
                    $geoHtml = "<div style='margin-bottom:16px'><div class='ps' style='color:{$accentJs}'>Localização da Audiência</div>";
                    $geoHtml .= "<div style='display:grid;grid-template-columns:repeat(3,1fr);gap:12px'>";
                    // Build each geo column inline
                    $geoCols = [
                        ['Cidades',  $breakdownCity,    '#5B8DEF'],
                        ['Estados',  $breakdownRegion,  '#9b59b6'],
                        ['Países',   $breakdownCountry, '#27ae60'],
                    ];
                    foreach ($geoCols as $geoCol) {
                        $gcLabel = $geoCol[0]; $gcItems = $geoCol[1]; $gcColor = $geoCol[2];
                        $geoHtml .= "<div><div style='font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:6px'>" . $gcLabel . "</div>";
                        if (empty($gcItems)) {
                            $geoHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:12px;font-size:11px;color:#aaa;text-align:center'>Sem dados</div>";
                        } else {
                            $gcMax = max(array_column($gcItems, 'reach')) ?: 1;
                            $geoHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:12px'><div style='display:flex;flex-direction:column;gap:7px'>";
                            foreach ($gcItems as $gcItem) {
                                $gcPct   = round($gcItem['reach'] / $gcMax * 100);
                                $gcReach = number_format($gcItem['reach'], 0, ',', '.');
                                $gcLbl   = htmlspecialchars($gcItem['label']);
                                $geoHtml .= "<div><div style='display:flex;justify-content:space-between;font-size:10px;margin-bottom:2px'>"
                                          . "<span style='color:#444'>" . $gcLbl . "</span>"
                                          . "<span style='color:" . $gcColor . ";font-weight:700'>" . $gcReach . "</span></div>"
                                          . "<div style='background:#e8eaf0;border-radius:3px;height:4px'>"
                                          . "<div style='background:" . $gcColor . ";width:" . $gcPct . "%;height:4px;border-radius:3px'></div>"
                                          . "</div></div>";
                            }
                            $geoHtml .= "</div></div>";
                        }
                        $geoHtml .= "</div>";
                    }
                    $geoHtml .= "</div></div>";
                    $blocksHtml .= $geoHtml;
                    break;

                case 'footer':
                    $fl = $bCfg['footerL'] ?? ('Gerado em '.date('d/m/Y H:i').' — '.$siteName);
                    $fr = $bCfg['footerR'] ?? 'seudominio.com.br';
                    $blocksHtml .= "<div style='margin-top:20px;padding-top:12px;border-top:1px solid #e8eaf0;font-size:10px;color:#aaa;display:flex;justify-content:space-between'><span>".htmlspecialchars($fl, ENT_QUOTES, 'UTF-8')."</span><span>".htmlspecialchars($fr, ENT_QUOTES, 'UTF-8')."</span></div>";
                    break;

                case 'signature':
                    $cl = $bCfg['client'] ?? ($r['client_name'] ?? 'Cliente');
                    $blocksHtml .= "<div style='background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px;text-align:center;margin-bottom:16px'>"
                        ."<div style='font-size:11px;color:#888'>Aprovado por</div>"
                        ."<div style='width:160px;border-top:1px solid #aaa;margin:14px auto 0'></div>"
                        ."<div style='font-size:13px;font-weight:700;margin-top:6px;color:#333'>".e($cl)."</div></div>";
                    break;

                case 'divider':
                    $blocksHtml .= "<hr style='border:none;border-top:1px solid #e8eaf0;margin:8px 0'>";
                    break;

                case 'spacer':
                    $blocksHtml .= "<div style='height:20px'></div>";
                    break;
            }
        }

        $css = "
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{font-family:Arial,sans-serif;font-size:13px;background:" . htmlspecialchars($tplBgColor) . ";color:" . htmlspecialchars($tplTxtColor) . ";padding:0;margin:0}

/* Container principal — responsivo */
.pdf-wrap{
  width:100%;
  max-width:760px;
  margin:0 auto;
  padding:28px 20px;
  background:" . htmlspecialchars($tplBgColor) . ";
  min-height:100vh;
}

/* KPI cards */
.kc{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:6px;padding:10px 12px}
.kl{font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.kv{font-size:16px;font-weight:700;color:#1a1a2e;line-height:1.2}
.kg{display:grid;gap:7px}

/* Section title */
.ps{font-size:10px;font-weight:700;color:" . $tplAccent . ";border-bottom:1px solid #e8eaf0;padding-bottom:4px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}

/* Message */
.pm{background:#f8f9fc;border-left:4px solid " . $tplAccent . ";border-radius:0 6px 6px 0;padding:13px;font-size:13px;line-height:1.9;white-space:pre-wrap;color:#2c2c3e;word-break:break-word}

/* Comparative table — scrollável no mobile */
.pt-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.pt{width:100%;border-collapse:collapse;font-size:11px;min-width:320px}
.pt th{background:#f0f2f8;text-align:left;padding:6px 8px;font-weight:700;color:#555}
.pt td{padding:6px 8px;border-bottom:1px solid #f0f2f8}

/* Charts */
.cb{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:7px;padding:13px;margin-bottom:14px}
canvas{max-width:100%;height:auto!important}

/* Signature */
.psi{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:7px;padding:13px;text-align:center}

/* Cabeçalho */
.ph{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ph-logo img{max-height:48px;max-width:120px;width:auto}

/* Header info */
.ph h1{font-size:clamp(14px,3.5vw,20px);font-weight:700;margin:0}
.ph .sub{font-size:11px;color:#666;margin-top:2px}

/* ── MOBILE: ≤ 480px ── */
@media(max-width:480px){
  .pdf-wrap{padding:16px 12px}
  .kg{grid-template-columns:repeat(2,1fr)!important}
  .kv{font-size:14px}
  .kl{font-size:8px}
  .ps{font-size:9px}
  .pm{font-size:12px;padding:10px}
  canvas{max-height:180px!important}
  .pt th,.pt td{font-size:9px;padding:4px 5px}
  [style*='grid-template-columns:1fr 1fr']{display:block!important}
  [style*='grid-template-columns:1fr 1fr']>div{margin-bottom:10px}
  .ph{flex-direction:column;gap:8px}
}

/* ── TABLET: 481–768px ── */
@media(min-width:481px) and (max-width:768px){
  .pdf-wrap{padding:20px 16px}
  .kv{font-size:15px}
  canvas{max-height:200px!important}
}

/* ── PRINT ── */
@media print{
  body{margin:0;padding:0;background:#fff!important}
  .pdf-wrap{max-width:100%;padding:16px;margin-top:0!important}
  .no-print{display:none!important}
  canvas{page-break-inside:avoid}
  .cb{page-break-inside:avoid}
}
@page{margin:10mm}
";
        // Período ativo label
        $periodLabels = [
            'last_7_days'=>'Últimos 7 dias','last_14_days'=>'Últimos 14 dias',
            'last_15_days'=>'Últimos 15 dias','last_30_days'=>'Últimos 30 dias',
            'last_90_days'=>'Últimos 90 dias','this_month'=>'Este mês',
            'last_month'=>'Mês passado','this_year'=>'Este ano',
            'max'=>'Máximo','maximum'=>'Máximo','custom'=>'Personalizado','saved'=>'Período do relatório',
        ];

        // URL base para o seletor de período (pública se vier via token)
        $pubToken  = $r['share_token'] ?? null;
        $acParam   = $tplAccent !== '#5B8DEF' ? '&ac='.urlencode($tplAccent) : '';
        $reportUrl = $pubToken
            ? APP_URL.'/r?t='.$pubToken.$acParam
            : APP_URL.'/reports/pdf?id='.$reportId.($tplId?'&tpl='.$tplId:'').$acParam;

        $selectorHtml = '<div class="period-bar no-print">'
            .'<div class="period-bar-inner">'
            .'<span style="font-size:12px;font-weight:600;color:#555;margin-right:10px">📅 Período:</span>'
            .'<select id="periodSel" onchange="changePeriod(this.value)" style="padding:5px 10px;border:1px solid #ddd;border-radius:6px;font-size:12px;background:#fff;cursor:pointer;flex:1;min-width:130px;max-width:220px">';

        $periodOptions = [
            'saved'       => '⭐ Período do relatório',
            'last_7_days' => 'Últimos 7 dias',
            'last_14_days'=> 'Últimos 14 dias',
            'last_30_days'=> 'Últimos 30 dias',
            'last_90_days'=> 'Últimos 90 dias',
            'this_month'  => 'Este mês',
            'last_month'  => 'Mês passado',
            'this_year'   => 'Este ano',
            'max'         => 'Máximo (desde o início)',
            'custom'      => '📅 Personalizado',
        ];
        foreach ($periodOptions as $pv => $pl) {
            $sel = ($activePeriod === $pv) ? ' selected' : '';
            $selectorHtml .= '<option value="'.htmlspecialchars($pv).'"'.$sel.'>'.htmlspecialchars($pl).'</option>';
        }
        $selectorHtml .= '</select>'
            .'<div id="customDates" style="display:'.($activePeriod==='custom'?'flex':'none').';align-items:center;gap:6px;margin-left:8px">'
            .'<input type="date" id="cs" value="'.htmlspecialchars($customStart).'" style="padding:4px 8px;border:1px solid #ddd;border-radius:6px;font-size:12px">'
            .'<span style="font-size:12px;color:#888">até</span>'
            .'<input type="date" id="ce" value="'.htmlspecialchars($customEnd).'" style="padding:4px 8px;border:1px solid #ddd;border-radius:6px;font-size:12px">'
            .'<button onclick="applyCustom()" style="padding:4px 12px;background:#5B8DEF;color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer">Aplicar</button>'
            .'</div>'
            .'<span style="font-size:11px;color:#888;margin-left:12px">'.htmlspecialchars(date('d/m/Y',strtotime($start))).' a '.htmlspecialchars(date('d/m/Y',strtotime($end))).'</span>'
            .'<button onclick="window.print()" style="margin-left:auto;padding:5px 14px;background:#5B8DEF;color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer">🖨 Imprimir</button>'
            .'</div>'
            .'</div>';

        $css .= '
.period-bar{position:fixed;top:0;left:0;right:0;background:#fff;border-bottom:1px solid #e8eaf0;z-index:9999;padding:6px 12px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.period-bar-inner{display:flex;align-items:center;gap:6px;flex-wrap:wrap;max-width:780px;margin:0 auto}
#periodSel{flex:1;min-width:120px;max-width:200px;font-size:11px;padding:4px 6px}
.pdf-wrap{margin-top:52px}
@media(max-width:480px){
  .period-bar{padding:5px 8px}
  .period-bar-inner{gap:4px}
  #periodSel{font-size:11px;min-width:100px}
  .pdf-wrap{margin-top:88px}
  #customDates{flex-wrap:wrap;gap:4px}
  #customDates input{font-size:10px;padding:3px 5px;width:100px}
  .period-bar-inner>span:last-of-type{display:none}
}
@media print{.no-print{display:none!important}.pdf-wrap{margin-top:0!important;padding:0!important}}
';

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">'
            .'<link rel="icon" type="image/png" href="' . htmlspecialchars($faviconUrl) . '">'
            .'<link rel="shortcut icon" href="' . htmlspecialchars($faviconUrl) . '">'
            .'<title>' . htmlspecialchars($r['title']) . '</title>'
            .'<style>' . $css . '</style>'
            .'</head><body>'
            .$selectorHtml
            .'<div class="pdf-wrap">'
            .$blocksHtml
            .'</div>'
            .'<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>'
            .'<script>
// Run chart queue after Chart.js loads
(function runQueue() {
  if (typeof Chart === "undefined") { setTimeout(runQueue, 50); return; }
  (window._cq||[]).forEach(function(f){ try{f();}catch(e){console.error(e);} });
  window._cq = { push: function(f){ try{f();}catch(e){console.error(e);} } };
})();
</script>'
            .'<script>
var BASE_URL = ' . json_encode($reportUrl) . ';
function changePeriod(v) {
    var el = document.getElementById("customDates");
    if(el) el.style.display = v==="custom" ? "flex" : "none";
    if(v !== "custom") {
        window.location.href = BASE_URL + "&period=" + encodeURIComponent(v);
    }
}
function applyCustom() {
    var s = document.getElementById("cs").value;
    var e = document.getElementById("ce").value;
    if(!s||!e){alert("Selecione as datas");return;}
    window.location.href = BASE_URL + "&period=custom&custom_start=" + s + "&custom_end=" + e;
}
</script>'
            .'</body></html>';

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="relatorio-'.$reportId.'.html"');
        echo $html;
    }

    // Editor de template PDF
    public function pdfEditor(): void {
        requireAuth();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $tplId    = (int)($_GET['tpl'] ?? 0);
        $reportId = (int)($_GET['report_id'] ?? 0);
        $forceNew = !empty($_GET['new']); // ?new=1 força template em branco

        $template = ['id'=>0,'name'=>'Novo Template','config'=>''];
        if ($tplId) {
            $t = $db->query("SELECT * FROM pdf_templates WHERE id=? AND user_id=?", [$tplId,$uid])->fetch();
            if ($t) $template = $t;
        } elseif (!$forceNew) {
            // Sem ?new=1: carrega o último template salvo
            try {
                $t = $db->query("SELECT * FROM pdf_templates WHERE user_id=? ORDER BY updated_at DESC LIMIT 1", [$uid])->fetch();
                if ($t) $template = $t;
            } catch (\Throwable $e) {}
        }
        // Com ?new=1: mantém template em branco (id=0, config vazio)

        // Lista de todos os templates para seleção
        $allTemplates = [];
        try {
            $allTemplates = $db->query("SELECT id, name, updated_at FROM pdf_templates WHERE user_id=? ORDER BY updated_at DESC", [$uid])->fetchAll();
        } catch (\Throwable $e) {}

        // Criar tabela se não existir
        try {
            $db->query("CREATE TABLE IF NOT EXISTS `pdf_templates` (
                `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` int(10) UNSIGNED NOT NULL,
                `name` varchar(100) NOT NULL DEFAULT 'Meu Template',
                `config` longtext DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`), KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Throwable $e) { $settings=[]; }
        $siteName = $settings['site_name'] ?? APP_NAME;

        $pageTitle   = 'Editor de Template PDF';
        $currentPage = 'reports';
        ob_start();
        require_once __DIR__.'/../views/reports/pdf_editor.php';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

    // Salvar template PDF
    public function savePdfTemplate(): void {
        requireAuth(); csrfCheck();
        if (function_exists('opcache_reset')) opcache_reset();
        header('Content-Type: application/json');
        $uid    = currentUser()['id'];
        $db     = Database::getInstance();
        $id     = (int)($_POST['id'] ?? 0);
        $name   = sanitize($_POST['name']   ?? 'Meu Template');
        $config = $_POST['config'] ?? '{}';

        // Validar JSON
        if (json_decode($config) === null && json_last_error() !== JSON_ERROR_NONE) { echo json_encode(['success'=>false,'error'=>'Config JSON inválida: '.json_last_error_msg()]); return; }

        try {
            $db->query("CREATE TABLE IF NOT EXISTS `pdf_templates` (
                `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` int(10) UNSIGNED NOT NULL,
                `name` varchar(100) NOT NULL DEFAULT 'Meu Template',
                `config` longtext DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`), KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Garante coluna pdf_tpl_id na tabela reports
            try { $db->query("ALTER TABLE reports ADD COLUMN IF NOT EXISTS pdf_tpl_id INT UNSIGNED NULL DEFAULT NULL"); } catch (\Throwable $e2) {}

            if ($id) {
                $db->query("UPDATE pdf_templates SET name=?, config=?, updated_at=NOW() WHERE id=? AND user_id=?", [$name,$config,$id,$uid]);
                // Marca este template como ativo para todos os relatórios do usuário
                // Extrai accent do config e salva direto nos reports
                $cfgArr = json_decode($config, true) ?: [];
                $savedAccent = $cfgArr['palette']['accent'] ?? '';
                $db->query("UPDATE reports SET pdf_tpl_id=?, pdf_accent=? WHERE user_id=?", [$id, $savedAccent, $uid]);
                echo json_encode(['success'=>true,'id'=>$id]);
            } else {
                $db->query("INSERT INTO pdf_templates (user_id,name,config) VALUES (?,?,?)", [$uid,$name,$config]);
                $newId = (int)$db->lastId();
                $cfgArr = json_decode($config, true) ?: [];
                $savedAccent = $cfgArr['palette']['accent'] ?? '';
                $db->query("UPDATE reports SET pdf_tpl_id=?, pdf_accent=? WHERE user_id=?", [$newId, $savedAccent, $uid]);
                echo json_encode(['success'=>true,'id'=>$newId]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // Excluir template PDF
    public function deletePdfTemplate(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id  = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        try {
            $db->query("DELETE FROM pdf_templates WHERE id=? AND user_id=?", [$id, $uid]);
            echo json_encode(['success'=>true]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
}
