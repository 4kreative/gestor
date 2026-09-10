<?php
class AlertController {

    /** Verifica se coluna client_id existe em alerts */
    private static function hasClientId(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $cache = (bool)Database::getInstance()->query("SHOW COLUMNS FROM alerts LIKE 'client_id'")->fetch();
        } catch (\Throwable $e) { $cache = false; }
        return $cache;
    }

    public function index(): void {
        requireAuth();
        $uid    = currentUser()['id'];
        $db     = Database::getInstance();
        $q      = sanitize($_GET['q'] ?? '');
        $status = sanitize($_GET['status'] ?? '');
        $plat   = sanitize($_GET['platform'] ?? '');

        $where  = "WHERE a.user_id=?";
        $params = [$uid];
        if ($q)      { $where .= " AND a.name LIKE ?"; $params[] = "%$q%"; }
        if ($status === 'ativo')   $where .= " AND a.ativo=1";
        if ($status === 'inativo') $where .= " AND a.ativo=0";
        if ($plat)   { $where .= " AND a.platform=?"; $params[] = $plat; }

        $alerts = $db->query(
            "SELECT a.*, aa.account_name, aa.account_id AS acc_id,
                    wgc.group_name AS nome_grupo,
                    (SELECT status    FROM alert_logs WHERE alert_id=a.id ORDER BY id DESC LIMIT 1) AS ultimo_status,
                    (SELECT erro_msg  FROM alert_logs WHERE alert_id=a.id ORDER BY id DESC LIMIT 1) AS ultimo_erro,
                    (SELECT tipo_envio FROM alert_logs WHERE alert_id=a.id ORDER BY id DESC LIMIT 1) AS ultimo_tipo,
                    (SELECT saldo     FROM alert_logs WHERE alert_id=a.id AND status='enviado' ORDER BY id DESC LIMIT 1) AS ultimo_saldo,
                    (SELECT erro_msg  FROM alert_logs WHERE alert_id=a.id AND status='enviado' ORDER BY id DESC LIMIT 1) AS ultimo_erro_enviado
             FROM alerts a
             LEFT JOIN ad_accounts aa ON a.ad_account_id = aa.id
             LEFT JOIN whatsapp_groups_cache wgc ON a.recipient_type='group' AND wgc.group_id = a.recipient_phone AND wgc.whatsapp_id = a.whatsapp_id
             $where ORDER BY a.created_at DESC",
            $params
        )->fetchAll();

        $accounts  = decryptTokens($db->query("SELECT * FROM ad_accounts WHERE user_id=? AND status='active' ORDER BY platform,account_name", [$uid])->fetchAll());
        $instances = $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected'", [$uid])->fetchAll();
        $clients   = $db->query("SELECT id, name, phone, company FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        $templates = $db->query("SELECT id, name, content FROM message_templates WHERE user_id=? ORDER BY is_default DESC, name ASC", [$uid])->fetchAll();

        require_once __DIR__.'/../views/alerts/index.php';
    }

    public function store(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();

        // Verifica limite de alertas do plano
        $count = (int)$db->query("SELECT COUNT(*) FROM alerts WHERE user_id=? AND ativo=1", [$uid])->fetchColumn();
        if (!planAllows('alerts', $count)) {
            flash('error', 'Limite de alertas do seu plano atingido. Faça upgrade!');
            redirect('/alerts/create');
        }

        $name     = sanitize($_POST['name']            ?? '');
        $type     = sanitize($_POST['type']            ?? 'saldo_minimo');
        $plat     = sanitize($_POST['platform']        ?? 'meta');
        $accId    = (int)($_POST['ad_account_id']      ?? 0);
        $saldo    = (float)($_POST['saldo_minimo']     ?? 0);
        $wpId     = (int)($_POST['whatsapp_id']        ?? 0);
        $clientId = (int)($_POST['client_id']          ?? 0);
        $recType  = sanitize($_POST['recipient_type']  ?? 'phone');
        $phone    = sanitize($_POST['recipient_phone'] ?? '');
        $msg      = $_POST['message']                  ?? '';
        $disparoImediato = !empty($_POST['disparo_imediato']) ? 1 : 0;
        $periodType = sanitize($_POST['period_type'] ?? 'last_7_days');

        $horariosList = $_POST['horarios_list'] ?? [];
        if (!empty($horariosList)) {
            $horarios = implode(',', array_filter(array_map('trim', $horariosList)));
        } elseif (!empty($_POST['horarios'])) {
            // Fallback: hidden input preenchido pelo collectHorarios() no JS
            $horarios = sanitize($_POST['horarios']);
        } else {
            $horarios = '12:00';
        }

        $dias    = implode(',', array_map('intval', (array)($_POST['dias_semana'] ?? [1,2,3,4,5])));
        $inativar= !empty($_POST['inativar_apos']) ? 1 : 0;
        $email   = !empty($_POST['receber_email'])  ? 1 : 0;

        if (!$name) { flash('error','Nome obrigatório.'); redirect('/alerts'); }

        if (self::hasClientId()) {
            $threshold = (float)($_POST['threshold'] ?? 0);
            $modoDisparo = in_array($_POST['modo_disparo']??'agendado',['agendado','inteligente'])
                ? sanitize($_POST['modo_disparo']) : 'agendado';
            $db->query(
                "INSERT INTO alerts (user_id,client_id,name,type,platform,ad_account_id,saldo_minimo,valor_threshold,whatsapp_id,
                 recipient_type,recipient_phone,message,horarios,dias_semana,inativar_apos,receber_email,disparo_imediato,period_type,modo_disparo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$uid,$clientId?:null,$name,$type,$plat,$accId?:null,$saldo,$threshold,$wpId?:null,
                 $recType,$phone,$msg,$horarios,$dias,$inativar,$email,$disparoImediato,$periodType,$modoDisparo]
            );
        } else {
            $threshold = (float)($_POST['threshold'] ?? 0);
            $modoDisparo = in_array($_POST['modo_disparo']??'agendado',['agendado','inteligente'])
                ? sanitize($_POST['modo_disparo']) : 'agendado';
            $db->query(
                "INSERT INTO alerts (user_id,name,type,platform,ad_account_id,saldo_minimo,valor_threshold,whatsapp_id,
                 recipient_type,recipient_phone,message,horarios,dias_semana,inativar_apos,receber_email,disparo_imediato,period_type,modo_disparo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$uid,$name,$type,$plat,$accId?:null,$saldo,$threshold,$wpId?:null,
                 $recType,$phone,$msg,$horarios,$dias,$inativar,$email,$disparoImediato,$periodType,$modoDisparo]
            );
        }

        flash('success','Alerta criado com sucesso!');
        redirect('/alerts');
    }

    public function sendManual(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['_token'] ?? '')) {
            echo json_encode(['success'=>false,'error'=>'Token inválido.']); exit;
        }

        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        $db  = Database::getInstance();

        $clientJoin = self::hasClientId() ? "LEFT JOIN clients c ON a.client_id = c.id" : "";
        $clientCols = self::hasClientId()
            ? ", c.name AS client_name, c.phone AS client_phone, c.company AS client_company"
            : ", NULL AS client_name, NULL AS client_phone, NULL AS client_company";
        $alert = $db->query(
            "SELECT a.*, aa.account_id, aa.account_name, aa.access_token,
                    wi.instance_name $clientCols
             FROM alerts a
             LEFT JOIN ad_accounts aa ON a.ad_account_id = aa.id
             LEFT JOIN whatsapp_instances wi ON a.whatsapp_id = wi.id
             $clientJoin
             WHERE a.id=? AND a.user_id=?",
            [$id, $uid]
        )->fetch();
        $alert = $alert ? decryptTokens($alert) : null;

        if (!$alert) {
            echo json_encode(['success'=>false,'error'=>'Alerta não encontrado.']); exit;
        }

        $now          = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
        $saldoAtual   = null;
        $mData        = [];
        $erroContaInfo = [];
        $mStart       = date('Y-m-d', strtotime('-7 days'));
        $mEnd         = date('Y-m-d');

        require_once __DIR__.'/ReportController.php';

        if ($alert['type'] === 'saldo_minimo' && !empty($alert['access_token'])) {
            $saldoAtual = self::buscarSaldoMeta($alert['account_id'], $alert['access_token']);
            if ($saldoAtual === null) {
                $erro = 'Não foi possível buscar o saldo da conta Meta Ads.';
                $db->query("INSERT INTO alert_logs (alert_id,user_id,alert_name,status,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'erro',?,'manual',?)",
                    [$id,$uid,$alert['name'],$alert['recipient_phone'],$erro]);
                echo json_encode(['success'=>false,'error'=>$erro]); exit;
            }
        } elseif ($alert['type'] === 'erro_conta' && !empty($alert['access_token'])) {
            // Busca erros da conta e anuncios com problema
            require_once __DIR__.'/../core/MetaHelpers.php';
            $erroContaInfo = verificarErroMeta($alert['account_id'], $alert['access_token']);
        } elseif (in_array($alert['type'], ['ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo']) && !empty($alert['access_token'])) {
            // Busca métricas para tipos de performance
            $periodType = $alert['period_type'] ?? 'last_7_days';
            [$mStart, $mEnd] = ReportController::calcPeriodDates($periodType);
            if ($mStart === 'MAX') {
                $mStart = ReportController::fetchCampaignStartDate($alert['account_id'], $alert['access_token'], [], (int)($alert['ad_account_id'] ?? 0));
            }
            $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, ['__ALL_STATUS__']) ?? [];
            if (empty($mData)) $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, []) ?? [];

            // Fallback para períodos menores se não tiver dados
            if (empty($mData) && !in_array($periodType, ['last_7_days','last_14_days','last_30_days','today','yesterday'])) {
                foreach (['last_30_days','last_7_days'] as $fb) {
                    [$fbStart, $fbEnd] = ReportController::calcPeriodDates($fb);
                    $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $fbStart, $fbEnd, ['__ALL_STATUS__']) ?? [];
                    if (empty($mData)) $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $fbStart, $fbEnd, []) ?? [];
                    if (!empty($mData)) { $mStart = $fbStart; $mEnd = $fbEnd; break; }
                }
            }
        }

        // Monta texto de erros para {erros_conta}
        $errosContaTexto = '';
        if (!empty($erroContaInfo)) {
            $linhas = [];
            foreach ($erroContaInfo as $e) $linhas[] = '• ' . $e;
            $errosContaTexto = implode("\n", $linhas);
        }

        $mensagem = ReportController::buildMessage($alert['message'] ?? '', [
            'metrics'        => array_merge($mData, ['saldo' => $saldoAtual ?? 0, 'saldo_minimo' => (float)$alert['saldo_minimo']]),
            'client_name'    => trim($alert['client_name'] ?? ''),
            'client_company' => trim($alert['client_company'] ?? ''),
            'account_name'   => $alert['account_name'] ?? '',
            'periodo'        => date('d/m/Y', strtotime($mStart)) . ' a ' . date('d/m/Y', strtotime($mEnd)),
            'erros_conta'    => $errosContaTexto,
            'campaign_name'  => '',
            'observacoes'    => '',
        ]);

        $phone    = $alert['recipient_phone'] ?? '';
        $instance = $alert['instance_name']   ?? '';

        if (!$phone || !$instance) {
            $erro = 'Telefone/grupo ou instância WhatsApp não configurados.';
            $db->query("INSERT INTO alert_logs (alert_id,user_id,alert_name,status,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'erro',?,'manual',?)",
                [$id,$uid,$alert['name'],$phone,$erro]);
            echo json_encode(['success'=>false,'error'=>$erro]); exit;
        }

        if (($alert['recipient_type'] ?? 'phone') === 'group' || strpos($phone, '@g.us') !== false) {
            $result = ReportController::sendWhatsAppGroup($instance, $phone, $mensagem);
        } else {
            $result = ReportController::sendWhatsAppStatic($instance, $phone, $mensagem);
        }

        if ($result['ok']) {
            $nowStr = $now->format('Y-m-d H:i:s');
            $db->query("UPDATE alerts SET ultimo_envio=? WHERE id=?", [$nowStr, $id]);
            // Para erro_conta: salva resumo dos erros detectados no erro_msg
            $erroMsgManual = null;
            if (($alert['type'] ?? '') === 'erro_conta' && !empty($erroContaInfo)) {
                $erroMsgManual = implode(' | ', array_slice($erroContaInfo, 0, 3));
            }
            $db->query("INSERT INTO alert_logs (alert_id,user_id,alert_name,status,saldo,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'enviado',?,?,'manual',?)",
                [$id,$uid,$alert['name'],$saldoAtual,$phone,$erroMsgManual]);
            echo json_encode([
                'success' => true,
                'message' => 'Enviado com sucesso!',
                'saldo'   => $saldoAtual !== null ? 'R$ '.number_format($saldoAtual,2,',','.') : null,
            ]);
        } else {
            $erro = $result['error'] ?? 'Erro desconhecido no envio';
            $db->query("INSERT INTO alert_logs (alert_id,user_id,alert_name,status,saldo,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'erro',?,?,'manual',?)",
                [$id,$uid,$alert['name'],$saldoAtual,$phone,$erro]);
            echo json_encode(['success'=>false,'error'=>$erro]);
        }
        exit;
    }

    public function getClientPhone(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid      = currentUser()['id'];
        $clientId = (int)($_GET['client_id'] ?? 0);
        if (!$clientId) { echo json_encode(['success'=>false]); exit; }
        $client = Database::getInstance()->query(
            "SELECT id, name, phone, company FROM clients WHERE id=? AND user_id=? AND status='active'",
            [$clientId, $uid]
        )->fetch();
        if (!$client) { echo json_encode(['success'=>false,'error'=>'Cliente não encontrado.']); exit; }
        echo json_encode([
            'success'       => true,
            'phone'         => $client['phone'] ?? '',
            'name'          => $client['name']  ?? '',
            'company'       => $client['company'] ?? '',
            'primeiro_nome' => explode(' ', trim($client['name'] ?? ''))[0] ?? '',
        ]);
        exit;
    }

    public function fetchGroups(): void {
        requireAuth();
        header('Content-Type: application/json');

        $uid  = currentUser()['id'];
        $wpId = (int)($_GET['whatsapp_id'] ?? 0);
        $db   = Database::getInstance();

        $inst = $db->query(
            "SELECT * FROM whatsapp_instances WHERE id=? AND user_id=? AND status='connected'",
            [$wpId, $uid]
        )->fetch();

        if (!$inst) {
            echo json_encode(['success'=>false,'error'=>'Instância não encontrada ou desconectada.']); exit;
        }

        // Carrega do cache se disponível
        $cached = $db->query(
            "SELECT group_id, group_name FROM whatsapp_groups_cache WHERE whatsapp_id = ? ORDER BY group_name",
            [$wpId]
        )->fetchAll();

        if (!empty($cached)) {
            $grupos = array_map(fn($g) => ['id' => $g['group_id'], 'nome' => $g['group_name']], $cached);
            echo json_encode(['success'=>true,'grupos'=>$grupos]);
            exit;
        }

        // Cache vazio — busca na API e salva no cache
        $res = EvolutionApi::fetchGroups($inst['instance_name']);

        if (empty($res) || !is_array($res)) {
            echo json_encode(['success'=>false,'error'=>'Erro ao buscar grupos.']); exit;
        }

        $grupos = [];
        foreach ($res as $g) {
            if (!is_array($g)) continue;
            $gid   = $g['id'] ?? '';
            $gname = $g['subject'] ?? $g['id'] ?? '';
            if (!$gid) continue;
            $grupos[] = ['id' => $gid, 'nome' => $gname];
            // Salva no cache
            $db->query(
                "INSERT INTO whatsapp_groups_cache (user_id, whatsapp_id, group_id, group_name)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE group_name = VALUES(group_name), synced_at = NOW()",
                [$uid, $wpId, $gid, $gname]
            );
        }
        usort($grupos, fn($a,$b) => strcmp($a['nome'],$b['nome']));
        echo json_encode(['success'=>true,'grupos'=>$grupos]);
        exit;
    }

    public function logs(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $id  = (int)($_GET['id'] ?? 0);
        $db  = Database::getInstance();
        $alert = $db->query("SELECT id FROM alerts WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if (!$alert) { echo json_encode(['success'=>false]); exit; }
        $logs = $db->query("SELECT * FROM alert_logs WHERE alert_id=? ORDER BY id DESC LIMIT 20", [$id])->fetchAll();
        echo json_encode(['success'=>true,'logs'=>$logs]);
        exit;
    }

    public function toggle(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        $db  = Database::getInstance();
        $alert = $db->query("SELECT ativo FROM alerts WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if ($alert) {
            $db->query("UPDATE alerts SET ativo=? WHERE id=? AND user_id=?", [$alert['ativo']?0:1,$id,$uid]);
        }
        redirect('/alerts');
    }

    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        Database::getInstance()->query("DELETE FROM alerts WHERE id=? AND user_id=?", [$id,$uid]);
        flash('success','Alerta excluído.');
        redirect('/alerts');
    }

    public function edit(): void {
        requireAuth();
        $uid   = currentUser()['id'];
        $db    = Database::getInstance();
        $alert = $db->query("SELECT * FROM alerts WHERE id=? AND user_id=?",[(int)($_GET['id']??0),$uid])->fetch();
        if (!$alert) { flash('error','Alerta não encontrado.'); redirect('/alerts'); }
        $accounts  = $db->query("SELECT * FROM ad_accounts WHERE user_id=? AND status='active'", [$uid])->fetchAll();
        $instances = $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected'", [$uid])->fetchAll();
        $clients   = $db->query("SELECT id, name, phone, company FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        // Templates de mensagem para alertas
        $alertTemplates = $db->query("SELECT id, name, content FROM message_templates WHERE user_id=? ORDER BY is_default DESC, name ASC", [$uid])->fetchAll();
        require_once __DIR__.'/../views/alerts/form.php';
    }

    public function update(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id'] ?? 0);
        $db  = Database::getInstance();

        // Horários: lê horarios_list[] enviado pelo form
        $horariosList = $_POST['horarios_list'] ?? [];
        $horarios = !empty($horariosList)
            ? implode(',', array_filter(array_map('trim', $horariosList)))
            : sanitize($_POST['horarios'] ?? '12:00');
        $dias     = implode(',', array_map('intval', (array)($_POST['dias_semana'] ?? [1,2,3,4,5])));
        $clientId = (int)($_POST['client_id'] ?? 0);

        $periodTypeUpd = sanitize($_POST['period_type'] ?? 'last_7_days');
        $thresholdUpd  = strlen(trim($_POST['threshold']??''))>0 ? (float)$_POST['threshold'] : null;
        $baseParams = [
            sanitize($_POST['name']??''),sanitize($_POST['type']??'saldo_minimo'),
            sanitize($_POST['platform']??'meta'),(int)($_POST['ad_account_id']??0)?:null,
            (float)($_POST['saldo_minimo']??0),$thresholdUpd,(int)($_POST['whatsapp_id']??0)?:null,
        ];
        $modoDisparoUpd = in_array($_POST['modo_disparo']??'agendado',['agendado','inteligente'])
            ? sanitize($_POST['modo_disparo']) : 'agendado';
        // ORDEM CORRETA: modo_disparo ANTES de id e uid (WHERE params)
        $endParams = [
            sanitize($_POST['recipient_type']??'phone'),sanitize($_POST['recipient_phone']??''),
            $_POST['message']??'',$horarios,$dias,
            !empty($_POST['inativar_apos'])?1:0,!empty($_POST['receber_email'])?1:0,
            !empty($_POST['disparo_imediato'])?1:0,$periodTypeUpd,$modoDisparoUpd,$id,$uid
        ];
        if (self::hasClientId()) {
            $db->query(
                "UPDATE alerts SET name=?,type=?,platform=?,ad_account_id=?,saldo_minimo=?,valor_threshold=?,
                 whatsapp_id=?,client_id=?,recipient_type=?,recipient_phone=?,message=?,horarios=?,
                 dias_semana=?,inativar_apos=?,receber_email=?,disparo_imediato=?,period_type=?,modo_disparo=? WHERE id=? AND user_id=?",
                array_merge($baseParams, [$clientId?:null], $endParams)
            );
        } else {
            $db->query(
                "UPDATE alerts SET name=?,type=?,platform=?,ad_account_id=?,saldo_minimo=?,valor_threshold=?,
                 whatsapp_id=?,recipient_type=?,recipient_phone=?,message=?,horarios=?,
                 dias_semana=?,inativar_apos=?,receber_email=?,disparo_imediato=?,period_type=?,modo_disparo=? WHERE id=? AND user_id=?",
                array_merge($baseParams, $endParams)
            );
        }
        flash('success','Alerta atualizado!');
        redirect('/alerts');
    }

    public static function buscarSaldoMeta(string $accountId, string $token): ?float {
        if (!$accountId || !$token) return null;
        $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}?fields=balance,spend_cap,amount_spent,currency&access_token=".urlencode($token);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
        $res  = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$res) return null;
        if (!empty($res['spend_cap']) && (int)$res['spend_cap'] > 0 && isset($res['amount_spent'])) {
            return max(0, ((float)$res['spend_cap'] - (float)$res['amount_spent']) / 100);
        }
        if (isset($res['balance'])) return (float)$res['balance'] / 100;
        return null;
    }
}
