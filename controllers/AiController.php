<?php
if (!class_exists('TokenCrypto')) {
    require_once __DIR__.'/../core/TokenCrypto.php';
}

class AiController {

    public static function getModels(): array {
        return [
            'groq' => [
                'label' => 'Groq', 'icon' => '⚡', 'color' => '#f55036',
                'models' => [
                    ['id'=>'llama-3.1-8b-instant',              'name'=>'Llama 3.1 8B',       'tier'=>'free',  'price'=>'Grátis'],
                    ['id'=>'llama-3.3-70b-versatile',           'name'=>'Llama 3.3 70B',      'tier'=>'cheap', 'price'=>'~$0.00059/1k'],
                    ['id'=>'openai/gpt-oss-20b',                'name'=>'GPT OSS 20B',         'tier'=>'cheap', 'price'=>'~$0.00015/1k'],
                    ['id'=>'openai/gpt-oss-120b',               'name'=>'GPT OSS 120B ✨',     'tier'=>'mid',   'price'=>'~$0.00060/1k'],
                    ['id'=>'meta-llama/llama-4-scout-17b-16e-instruct','name'=>'Llama 4 Scout','tier'=>'mid',   'price'=>'~$0.00011/1k'],
                    ['id'=>'qwen/qwen3-32b',                    'name'=>'Qwen3 32B',           'tier'=>'mid',   'price'=>'~$0.00029/1k'],
                ],
            ],
            'gemini' => [
                'label' => 'Gemini', 'icon' => '✦', 'color' => '#4285f4',
                'models' => [
                    ['id'=>'gemini-1.5-flash-8b',          'name'=>'Flash 8B',      'tier'=>'free',    'price'=>'Grátis'],
                    ['id'=>'gemini-2.0-flash-lite',         'name'=>'Flash 2.0 Lite','tier'=>'free',    'price'=>'Grátis'],
                    ['id'=>'gemini-2.0-flash',              'name'=>'Flash 2.0',     'tier'=>'cheap',   'price'=>'~$0.00010/1k'],
                    ['id'=>'gemini-2.5-flash-preview-04-17','name'=>'Flash 2.5 ✨',  'tier'=>'mid',     'price'=>'~$0.00015/1k'],
                    ['id'=>'gemini-1.5-pro',                'name'=>'Pro 1.5',       'tier'=>'mid',     'price'=>'~$0.00125/1k'],
                    ['id'=>'gemini-2.5-pro-preview-05-06',  'name'=>'Pro 2.5 ✨',    'tier'=>'premium', 'price'=>'~$0.00125/1k'],
                ],
            ],
            'openai' => [
                'label' => 'OpenAI', 'icon' => '◆', 'color' => '#10a37f',
                'models' => [
                    ['id'=>'gpt-4o-mini',  'name'=>'GPT-4o Mini',    'tier'=>'cheap',   'price'=>'~$0.00015/1k'],
                    ['id'=>'gpt-4.1-mini', 'name'=>'GPT-4.1 Mini ✨','tier'=>'cheap',   'price'=>'~$0.00040/1k'],
                    ['id'=>'gpt-4o',       'name'=>'GPT-4o',          'tier'=>'mid',     'price'=>'~$0.00250/1k'],
                    ['id'=>'gpt-4.1',      'name'=>'GPT-4.1 ✨',      'tier'=>'mid',     'price'=>'~$0.00200/1k'],
                    ['id'=>'o4-mini',      'name'=>'o4-mini ✨',      'tier'=>'premium', 'price'=>'~$0.00110/1k'],
                    ['id'=>'gpt-4-turbo',  'name'=>'GPT-4 Turbo',     'tier'=>'premium', 'price'=>'~$0.01000/1k'],
                ],
            ],
        ];
    }

    private static function ensureTable($db): void {
        $db->query("CREATE TABLE IF NOT EXISTS `user_ai_settings` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` int UNSIGNED NOT NULL,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_setting` (`user_id`,`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function getAiSettings($uid, $db): array {
        try {
            self::ensureTable($db);
            return $db->query(
                "SELECT setting_key, setting_value FROM user_ai_settings WHERE user_id=?", [$uid]
            )->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) { return []; }
    }

    private static function getKey(array $settings, string $provider): string {
        $map = ['groq'=>'ai_groq_key','openai'=>'ai_openai_key','gemini'=>'ai_gemini_key'];
        $val = $settings[$map[$provider] ?? ''] ?? '';
        // Descriptografa se estiver criptografado
        return $val ? TokenCrypto::decrypt($val) : '';
    }

    private static function autoProvider(array $settings): array {
        foreach (['groq','gemini','openai'] as $p) {
            $k = self::getKey($settings, $p);
            if ($k) {
                $models = self::getModels()[$p]['models'];
                return [$p, $models[0]['id'], $k];
            }
        }
        return ['','',''];
    }

    private static function resolvePeriod(string $periodo, string $customStart='', string $customEnd=''): array {
        $today = date('Y-m-d');
        switch ($periodo) {
            case 'today':        return [$today, $today];
            case 'yesterday':    $d=date('Y-m-d',strtotime('-1 day')); return [$d,$d];
            case 'last_7_days':  return [date('Y-m-d',strtotime('-7 days')), $today];
            case 'last_15_days': return [date('Y-m-d',strtotime('-15 days')), $today];
            case 'last_30_days': return [date('Y-m-d',strtotime('-30 days')), $today];
            case 'last_90_days': return [date('Y-m-d',strtotime('-90 days')), $today];
            case 'this_month':   return [date('Y-m-01'), $today];
            case 'last_month':   return [date('Y-m-01',strtotime('first day of last month')), date('Y-m-t',strtotime('last month'))];
            case 'maximum':      return ['MAX', $today]; // MAX = resolver data real via API
            case 'custom':       return [$customStart ?: date('Y-m-d',strtotime('-30 days')), $customEnd ?: $today];
            default:             return [date('Y-m-d',strtotime('-30 days')), $today];
        }
    }

    public function saveModel(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $provider = sanitize($_POST['provider'] ?? '');
        $model    = sanitize($_POST['model']    ?? '');
        if (!$provider || !$model) { echo json_encode(['ok'=>false]); return; }
        foreach (['ai_default_provider' => $provider, 'ai_default_model' => $model] as $key => $val) {
            $db->query(
                "INSERT INTO user_ai_settings (user_id, setting_key, setting_value) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [$uid, $key, $val]
            );
        }
        echo json_encode(['ok'=>true]);
    }

    public function index(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $aiSettings = self::getAiSettings($uid, $db);
        $accounts   = $db->query("SELECT id, account_name, platform FROM ad_accounts WHERE user_id=? AND status='active' ORDER BY account_name", [$uid])->fetchAll();
        $instances  = $db->query("SELECT id, instance_name, phone_number FROM whatsapp_instances WHERE user_id=? AND status='connected'", [$uid])->fetchAll();
        $clients    = $db->query("SELECT id, name, phone FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        $wpGroups   = []; // carregado lazy via JS para não bloquear page load
        $allModels   = self::getModels();
        $templates   = $db->query("SELECT id, name, content FROM message_templates WHERE user_id=? ORDER BY is_default DESC, name", [$uid])->fetchAll();
        $pageTitle   = 'Análise com IA';
        $currentPage = 'ai';
        ob_start();
        require_once __DIR__.'/../views/ai/index.php';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

    public function chat(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $provider = sanitize($_POST['provider'] ?? '');
        $model    = sanitize($_POST['model']    ?? '');
        $history  = json_decode($_POST['history'] ?? '[]', true);
        $context  = trim($_POST['context'] ?? '');
        // Also check session for full campaign context
        if (empty($context) && !empty($_SESSION['ai_campaign_context'])) {
            $context = $_SESSION['ai_campaign_context'];
        }
        if (!is_array($history)) $history = [];

        $aiSettings = self::getAiSettings($uid, $db);
        $apiKey = self::getKey($aiSettings, $provider);
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>"Chave de API do $provider não configurada."]); return; }

        $systemPrompt = "Voce e um especialista senior em marketing digital e trafego pago. Responda de forma direta e objetiva em portugues (pt-BR).\n\nREGRA ABSOLUTA: Voce TEM acesso aos dados abaixo. Use-os completamente para responder. NUNCA diga que nao tem acesso a dados que estao listados abaixo. NUNCA invente dados que nao estejam listados.";
        if ($context) {
            $systemPrompt .= "\n\nDADOS COMPLETOS DA CAMPANHA — VOCE TEM ACESSO A TUDO ISSO:\n\n{$context}\n\nINSTRUCAO: Ao responder perguntas sobre alcance, segmentacao, posicionamentos, metricas por anuncio, acoes, engajamento — use os dados acima. Eles estao disponiveis. Nao diga que nao tem acesso se o dado estiver listado acima.";
        }

        try {
            $result = match($provider) {
                'groq'   => self::callGroqChat($apiKey, $model, $systemPrompt, $history),
                'openai' => self::callOpenAIChat($apiKey, $model, $systemPrompt, $history),
                'gemini' => self::callGeminiChat($apiKey, $model, $systemPrompt, $history),
                default  => ['success'=>false,'error'=>'Provedor inválido.'],
            };
        } catch (\Throwable $e) {
            $result = ['success'=>false,'error'=>'Erro: '.$e->getMessage()];
        }
        echo json_encode($result);
    }

    private static function callGroqChat(string $key, string $model, string $system, array $history): array {
        $messages = [['role'=>'system','content'=>$system]];
        foreach ($history as $h) { if(isset($h['role'],$h['content'])) $messages[] = $h; }
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>$messages,'max_tokens'=>2000,'temperature'=>0.7]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_TIMEOUT=>90]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        if($code!==200||empty($data['choices'][0]['message']['content'])) return ['success'=>false,'error'=>'Groq: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($data['choices'][0]['message']['content'])];
    }

    private static function callOpenAIChat(string $key, string $model, string $system, array $history): array {
        $messages = [['role'=>'system','content'=>$system]];
        foreach ($history as $h) { if(isset($h['role'],$h['content'])) $messages[] = $h; }
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>$messages,'max_tokens'=>2000,'temperature'=>0.7]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_TIMEOUT=>120]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        if($code!==200||empty($data['choices'][0]['message']['content'])) return ['success'=>false,'error'=>'OpenAI: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($data['choices'][0]['message']['content'])];
    }

    private static function callGeminiChat(string $key, string $model, string $system, array $history): array {
        $contents = [];
        foreach ($history as $h) {
            if(!isset($h['role'],$h['content'])) continue;
            $contents[] = ['role'=>$h['role']==='assistant'?'model':'user','parts'=>[['text'=>$h['content']]]];
        }
        if(empty($contents)) return ['success'=>false,'error'=>'Histórico vazio.'];
        $body = ['system_instruction'=>['parts'=>[['text'=>$system]]],'contents'=>$contents];
        $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $ch   = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode($body),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>90]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        $text=$data['candidates'][0]['content']['parts'][0]['text']??'';
        if($code!==200||!$text) return ['success'=>false,'error'=>'Gemini: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($text)];
    }

    public function saveKeys(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        self::ensureTable($db);
        $keys = [
            'ai_groq_key'         => sanitize($_POST['ai_groq_key']        ?? ''),
            'ai_openai_key'       => sanitize($_POST['ai_openai_key']       ?? ''),
            'ai_gemini_key'       => sanitize($_POST['ai_gemini_key']       ?? ''),
            'ai_default_provider' => sanitize($_POST['ai_default_provider'] ?? 'groq'),
            'ai_default_model'    => sanitize($_POST['ai_default_model']    ?? 'llama-3.1-8b-instant'),
        ];
        $keyFields = ['ai_groq_key', 'ai_openai_key', 'ai_gemini_key'];
        foreach ($keys as $k => $v) {
            if ($v === '' || str_repeat('•', strlen($v)) === $v) continue;
            // Criptografa apenas as chaves de API (não provider/model)
            if (in_array($k, $keyFields) && !TokenCrypto::isEncrypted($v)) {
                $v = TokenCrypto::encrypt($v);
            }
            $db->query("INSERT INTO user_ai_settings (user_id,setting_key,setting_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$uid,$k,$v]);
        }
        flash('success', 'Configurações de IA salvas!');
        redirect('/ai');
    }

    public function analyze(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $provider    = sanitize($_POST['provider']      ?? '');
        $model       = sanitize($_POST['model']         ?? '');
        $campId      = sanitize($_POST['campaign_id']   ?? '');
        $campName    = sanitize($_POST['campaign_name'] ?? '');
        $accId       = $_POST['account_id'] ?? 0;
        $periodo     = sanitize($_POST['periodo']       ?? 'last_30_days');
        $customStart = sanitize($_POST['custom_start']  ?? '');
        $customEnd   = sanitize($_POST['custom_end']    ?? '');
        $metrics     = sanitize($_POST['metrics']       ?? '');
        $customPrompt= trim($_POST['custom_prompt']     ?? '');

        [$dateStart, $dateEnd] = self::resolvePeriod($periodo, $customStart, $customEnd);

        if (!$campId || !$accId) { echo json_encode(['success'=>false,'error'=>'Selecione uma campanha.']); return; }

        $aiSettings = self::getAiSettings($uid, $db);
        $apiKey = self::getKey($aiSettings, $provider);
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>"Chave de API do $provider não configurada."]); return; }

        try {
            $r = self::fetchMetricsAndAnalyze($db, $uid, $campId, $campName, $accId, $dateStart, $dateEnd, $provider, $model, $apiKey, $metrics, $customPrompt);
        } catch (\Throwable $e) {
            $r = ['success'=>false,'error'=>'Erro interno: '.$e->getMessage()];
        }

        // Salva no log para exibir no dashboard
        if (!empty($r['success'])) {
            try {
                $db->query(
                    "INSERT INTO ai_logs (user_id, ad_account_id, account_name, campaign_id, campaign_name, provider, model, metrics_json, analysis_text, period, objective)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $uid,
                        (int)$accId,
                        $r['account_name'] ?? '',
                        $campId,
                        $r['campaign_name'] ?? $campName,
                        $r['provider'] ?? $provider,
                        $r['model']    ?? $model,
                        json_encode($r['metrics'] ?? []),
                        $r['analysis'] ?? '',
                        $r['period']   ?? '',
                        $r['objective'] ?? '',
                    ]
                );
            } catch (\Throwable $e) { /* log opcional */ }
        }

        echo json_encode($r);
    }

    public function analyzeCard(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');
        $campId    = sanitize($_POST['campaign_id'] ?? '');
        $accId     = $_POST['account_id'] ?? 0;
        $dateStart = sanitize($_POST['date_start']  ?? date('Y-m-d', strtotime('-30 days')));
        $dateEnd   = sanitize($_POST['date_end']    ?? date('Y-m-d'));
        if (!$campId || !$accId) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); return; }
        $aiSettings = self::getAiSettings($uid, $db);
        [$provider, $model, $apiKey] = self::autoProvider($aiSettings);
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>'Nenhuma chave de IA configurada.']); return; }
        try {
            $r = self::fetchMetricsAndAnalyze($db, $uid, $campId, '', $accId, $dateStart, $dateEnd, $provider, $model, $apiKey);
        } catch (\Throwable $e) {
            $r = ['success'=>false,'error'=>'Erro: '.$e->getMessage()];
        }

        // Salva no log para exibir no dashboard
        if (!empty($r['success'])) {
            try {
                $db->query(
                    "INSERT INTO ai_logs (user_id, ad_account_id, account_name, campaign_id, campaign_name, provider, model, metrics_json, analysis_text, period, objective)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $uid,
                        (int)$accId,
                        $r['account_name'] ?? '',
                        $campId,
                        $r['campaign_name'] ?? '',
                        $r['provider'] ?? $provider,
                        $r['model']    ?? $model,
                        json_encode($r['metrics'] ?? []),
                        $r['analysis'] ?? '',
                        $r['period']   ?? '',
                        $r['objective'] ?? '',
                    ]
                );
            } catch (\Throwable $e) { /* log opcional */ }
        }

        echo json_encode($r);
    }

    public function sendAnalysis(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');
        $wpId     = (int)($_POST['whatsapp_id'] ?? 0);
        $recvType = sanitize($_POST['recv_type'] ?? 'phone');
        $phone    = sanitize($_POST['phone']     ?? '');
        $groupId  = sanitize($_POST['group_id']  ?? '');
        $clientId = sanitize($_POST['client_id'] ?? '');
        $message  = $_POST['message'] ?? '';
        if (!$message) { echo json_encode(['success'=>false,'error'=>'Mensagem vazia.']); return; }

        // Se for cliente, buscar phone direto do banco (mais confiável que POST)
        if ($recvType === 'client' && $clientId) {
            $cli = $db->query("SELECT phone FROM clients WHERE id=? AND user_id=?", [$clientId, $uid])->fetch();
            if ($cli && !empty($cli['phone'])) $phone = $cli['phone'];
        }

        $wp = $wpId
            ? $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?", [$wpId,$uid])->fetch()
            : $db->query("SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC LIMIT 1", [$uid])->fetch();
        if (!$wp) { echo json_encode(['success'=>false,'error'=>'Nenhuma instância WhatsApp disponível.']); return; }

        // Validação do phone antes de enviar
        $phoneLimpo = preg_replace('/\D/', '', $phone);
        if ($recvType !== 'group' && strlen($phoneLimpo) < 10) {
            echo json_encode(['success'=>false,'error'=>'Número inválido ('.$phoneLimpo.'). Edite o número manualmente no campo de mensagem ou atualize o cadastro do cliente.']);
            return;
        }

        $result = ($recvType === 'group' && $groupId)
            ? ReportController::sendWhatsAppGroup($wp['instance_name'], $groupId, $message)
            : ReportController::sendWhatsAppStatic($wp['instance_name'], $phone, $message);
        // Normaliza ok→success para o JS
        if (isset($result['ok']) && !isset($result['success'])) {
            $result['success'] = $result['ok'];
        }
        echo json_encode($result);
    }

    // ── Core: busca métricas via API Meta (igual relatórios) + chama IA ──
    private static function fetchMetricsAndAnalyze($db, $uid, string $campId, string $campName, $accId, string $start, string $end, string $provider, string $model, string $apiKey, string $selectedMetrics='', string $customPrompt=''): array {

        // Busca dados da conta (token, account_id Meta) — decrypt obrigatório
        $acc = $db->query(
            "SELECT id, account_id, access_token, account_name, platform FROM ad_accounts WHERE id=?",
            [$accId]
        )->fetch();
        if ($acc) $acc = decryptTokens($acc);

        // Resolve período MAX igual aos relatórios — busca data real de início da campanha
        if ($start === 'MAX') {
            if ($acc && !empty($acc['access_token'])) {
                $start = ReportController::fetchCampaignStartDate(
                    $acc['account_id'], $acc['access_token'],
                    $campId ? [$campId] : [],
                    (int)$accId
                );
            } else {
                $start = date('Y-m-d', strtotime('-2 years'));
            }
        }

        $m = null; // métricas finais

        // 1. API Meta primeiro — exatamente igual aos relatórios
        if ($acc && !empty($acc['access_token']) && in_array($acc['platform'], ['meta','meta_ads'])) {
            // Busca objective da campanha diretamente
            $campaignObjective = '';
            if ($campId) {
                try {
                    $objCh = curl_init("https://graph.facebook.com/".META_API_VERSION."/{$campId}?fields=objective&access_token=".urlencode($acc['access_token']));
                    curl_setopt_array($objCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
                    $objRes = json_decode(curl_exec($objCh), true); curl_close($objCh);
                    $campaignObjective = strtolower($objRes['objective'] ?? '');
                } catch (\Throwable $e) {}
            }

            $metaData = ReportController::fetchMetricsMeta(
                $acc['account_id'], $acc['access_token'], $start, $end,
                $campId ? [$campId] : []
            );
            // Se profile_visit veio zero, busca direto no endpoint da campanha ou conta
            // A API Meta NÃO retorna instagram_profile_visits com filtering por campaign.id
            if ($metaData && ($metaData['profile_visit'] ?? 0) == 0) {
                try {
                    $pvTotal = 0;
                    // Define endpoint: campanha específica ou conta
                    $pvEndpoints = [];
                    if ($campId) {
                        $pvEndpoints[] = META_API_VERSION."/{$campId}/insights";
                    }
                    $pvEndpoints[] = META_API_VERSION."/act_{$acc['account_id']}/insights";

                    foreach ($pvEndpoints as $pvEndpoint) {
                        foreach (["time_range=".urlencode(json_encode(['since'=>$start,'until'=>$end])), "date_preset=maximum"] as $pvTimeParam) {
                            $pvUrl = "https://graph.facebook.com/{$pvEndpoint}"
                                   . "?fields=instagram_profile_visits,actions"
                                   . "&{$pvTimeParam}"
                                   . ($campId && strpos($pvEndpoint, "act_") === false ? "" :
                                      ($campId ? "&filtering=".urlencode(json_encode([['field'=>'campaign.id','operator'=>'IN','value'=>[$campId]]])) : ""))
                                   . "&access_token=".urlencode($acc['access_token']);
                            $pvCh = curl_init($pvUrl);
                            curl_setopt_array($pvCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>false]);
                            $pvRes = json_decode(curl_exec($pvCh), true);
                            curl_close($pvCh);
                            if (!empty($pvRes['data'])) {
                                foreach ($pvRes['data'] as $pvRow) {
                                    $pv = (int)($pvRow['instagram_profile_visits'] ?? 0);
                                    if (!$pv) {
                                        foreach ($pvRow['actions'] ?? [] as $a) {
                                            if (in_array($a['action_type'], ['ig_profile_visit','profile_visit','instagram_profile_visit','ig_profile_visit_organic'])) {
                                                $pv += (int)$a['value'];
                                            }
                                        }
                                    }
                                    $pvTotal += $pv;
                                }
                            }
                            if ($pvTotal > 0) { $metaData['profile_visit'] = $pvTotal; break 2; }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            if ($metaData && ($metaData['spend'] ?? 0) > 0) {
                $sp = (float)($metaData['spend'] ?? 0);
                $cv = (int)($metaData['conversions'] ?? 0);
                $ld = (int)($metaData['leads'] ?? 0);
                $pu = (int)($metaData['purchase'] ?? 0);
                $mg = (int)($metaData['msg_all'] ?? 0);
                $m = [
                    'campaign_name'          => $campName ?: 'Campanha',
                    'account_name'           => $acc['account_name'],
                    'platform'               => $acc['platform'],
                    'objective'              => $campaignObjective ?: ($metaData['objective'] ?? ''),
                    'spend'                  => $sp,
                    'revenue'                => (float)($metaData['revenue']    ?? 0),
                    'impressions'            => (int)($metaData['impressions']  ?? 0),
                    'clicks'                 => (int)($metaData['clicks']       ?? 0),
                    'link_clicks'            => (int)($metaData['link_click']   ?? 0),
                    'reach'                  => (int)($metaData['reach']        ?? 0),
                    'ctr'                    => (float)($metaData['ctr']        ?? 0),
                    'cpc'                    => (float)($metaData['cpc']        ?? 0),
                    'cpm'                    => (float)($metaData['cpm']        ?? 0),
                    'roas'                   => $sp > 0 ? round(($metaData['revenue'] ?? 0) / $sp, 2) : 0,
                    'frequency'              => ($metaData['frequency'] ?? 0) > 0
                                                    ? (float)$metaData['frequency']
                                                    : ((($metaData['impressions'] ?? 0) > 0 && ($metaData['reach'] ?? 0) > 0)
                                                        ? round($metaData['impressions'] / $metaData['reach'], 2) : 0),
                    'conversions'            => $cv,
                    'purchases'              => $pu,
                    'leads'                  => $ld,
                    'messages'               => $mg,
                    'messaging_conversations'=> $mg,
                    'video_views'            => (int)($metaData['vplay']        ?? 0),
                    'add_to_cart'            => (int)($metaData['cart']         ?? 0),
                    'profile_visits'         => (int)($metaData['profile_visit'] ?? 0)
                                                    ?: (int)(($metaData['all_actions']['ig_profile_visit'] ?? 0)
                                                         ?: ($metaData['all_actions']['profile_visit'] ?? 0)),
                    'engagement'             => (int)($metaData['engajamento']  ?? 0),
                    'cost_per_lead'          => $ld > 0 ? round($sp / $ld, 2) : 0,
                    'cost_per_purchase'      => $pu > 0 ? round($sp / $pu, 2) : 0,
                    'cost_per_messaging_conv'=> $mg > 0 ? round($sp / $mg, 2) : 0,
                    'cost_per_conversion'    => $cv > 0 ? round($sp / $cv, 2) : 0,
                    'view_25'   => (int)($metaData['view_25']  ?? 0),
                    'view_50'   => (int)($metaData['view_50']  ?? 0),
                    'view_75'   => (int)($metaData['view_75']  ?? 0),
                    'view_95'   => (int)($metaData['view_95']  ?? 0),
                    'view_100'  => (int)($metaData['view_100'] ?? 0),
                    'thruplay'  => (int)($metaData['thruplay'] ?? 0),
                    'v_avg'     => (float)($metaData['v_avg']  ?? 0),
                    'days_active' => 1,
                ];
            }
        }

        // 2. Fallback: banco local (Google Ads ou token expirado)
        if (!$m) {
            try {
                $row = $db->query(
                    "SELECT cm.campaign_name, aa.account_name, aa.platform,
                       SUM(cm.impressions) AS impressions, SUM(cm.clicks) AS clicks,
                       SUM(cm.spend) AS spend, SUM(cm.conversions) AS conversions,
                       SUM(cm.reach) AS reach, SUM(COALESCE(cm.revenue,0)) AS revenue,
                       AVG(cm.ctr) AS ctr, AVG(cm.cpc) AS cpc, AVG(cm.cpm) AS cpm,
                       AVG(cm.roas) AS roas, COUNT(DISTINCT cm.date) AS days_active
                     FROM campaign_metrics cm
                     JOIN ad_accounts aa ON cm.ad_account_id=aa.id
                     WHERE cm.campaign_id=? AND cm.ad_account_id=? AND cm.date BETWEEN ? AND ?
                     GROUP BY cm.campaign_id, cm.campaign_name, aa.account_name, aa.platform",
                    [$campId, $accId, $start, $end]
                )->fetch();
                if ($row && ($row['spend'] ?? 0) > 0) $m = $row;
            } catch (\Throwable $e) {}
        }

        if (!$m) {
            $pl  = $acc['platform'] ?? 'desconhecida';
            $tok = !empty($acc['access_token']) ? 'token ok' : 'sem token';
            return ['success'=>false,'error'=>"Sem dados para \"$campName\" no periodo $start a $end. (plataforma=$pl, $tok)"];
        }

        // Dados diários para tendência (banco)
        $daily = [];
        try {
            $daily = $db->query(
                "SELECT date, spend, clicks, impressions, conversions, ctr, cpc, cpm, roas
                 FROM campaign_metrics WHERE ad_account_id=? AND campaign_id=? AND date BETWEEN ? AND ? ORDER BY date",
                [$accId, $campId, $start, $end]
            )->fetchAll();
        } catch (\Throwable $e) {}

        $campaignContext = '';
        if ($acc && !empty($acc['access_token']) && !empty($campId)) {
            $campaignContext = self::fetchCampaignContext($acc['account_id'], $acc['access_token'], $campId);
        }
        $result = self::callAI($provider, $model, $apiKey, self::buildPrompt($m, $daily, $start, $end, $selectedMetrics, $customPrompt, $campaignContext));
        if (!$result['success']) return $result;

        // Normaliza objetivo (inglês Meta → PT-BR) para o display do dashboard
        $rawObj = strtolower(trim($m['objective'] ?? ''));
        $objNormalized = match(true) {
            str_contains($rawObj,'outcome_traffic') || str_contains($rawObj,'link_click') || str_contains($rawObj,'traffic')
                => 'trafego',
            str_contains($rawObj,'outcome_engagement') || str_contains($rawObj,'messages') || str_contains($rawObj,'post_engagement')
                => 'mensagem',
            str_contains($rawObj,'outcome_leads') || str_contains($rawObj,'lead_generation')
                => 'lead',
            str_contains($rawObj,'outcome_sales') || str_contains($rawObj,'conversions') || str_contains($rawObj,'product_catalog_sales')
                => 'venda',
            str_contains($rawObj,'video_view')
                => 'video',
            str_contains($rawObj,'brand_awareness') || str_contains($rawObj,'reach') || str_contains($rawObj,'outcome_awareness')
                => 'alcance',
            str_contains($rawObj,'engagement')
                => 'engajamento',
            $rawObj !== '' => $rawObj,
            default => '',
        };

        // Calcula campos derivados uma única vez
        $_profVisits  = (int)($m['profile_visits'] ?? $m['profile_visit'] ?? 0);
        $_spend       = (float)($m['spend'] ?? 0);
        $_linkClicks  = (int)($m['link_clicks'] ?? $m['clicks'] ?? 0);
        $_allClicks   = (int)($m['clicks'] ?? 0);
        $_impr        = (int)($m['impressions'] ?? 0);
        $_reach2      = (int)($m['reach'] ?? 0);
        $_msgs        = (int)($m['messages'] ?? $m['messaging_conversations'] ?? 0);
        $_leads       = (int)($m['leads'] ?? 0);
        $_conv        = (int)($m['conversions'] ?? 0);
        $_purch       = (int)($m['purchases'] ?? 0);
        $_freq2       = ($m['frequency'] ?? 0) > 0
                            ? (float)$m['frequency']
                            : ($_impr > 0 && $_reach2 > 0 ? round($_impr / $_reach2, 2) : 0);

        // Retorno completo para o frontend — campos alinhados com o que o dashboard espera
        $metricsOut = [
            'spend'                   => $_spend,
            'revenue'                 => (float)($m['revenue']     ?? 0),
            'roas'                    => (float)($m['roas']        ?? 0),
            'impressions'             => $_impr,
            // 'clicks' = link clicks (igual métrica em tempo real e buildMessage {cliques})
            'clicks'                  => $_linkClicks,
            'link_clicks'             => $_linkClicks,
            'clicks_all'              => $_allClicks,
            'reach'                   => $_reach2,
            'ctr'                     => (float)($m['ctr']         ?? 0),
            'cpc'                     => (float)($m['cpc']         ?? 0),
            'cpm'                     => (float)($m['cpm']         ?? 0),
            'frequency'               => $_freq2,
            'conversions'             => $_conv,
            'purchases'               => $_purch,
            'leads'                   => $_leads,
            'messages'                => $_msgs,
            'messaging_conversations' => $_msgs,
            'video_views'             => (int)($m['video_views']   ?? 0),
            'add_to_cart'             => (int)($m['add_to_cart']   ?? 0),
            // profile_visit e profile_visits ambos preenchidos para compatibilidade com dashboard
            'profile_visits'          => $_profVisits,
            'profile_visit'           => $_profVisits,
            'cost_per_profile_visit'  => $_profVisits > 0 ? round($_spend / $_profVisits, 2) : 0,
            'engagement'              => (int)($m['engagement']    ?? 0),
            'cost_per_lead'           => $_leads > 0  ? round($_spend / $_leads,  2) : (float)($m['cost_per_lead']  ?? 0),
            'cost_per_purchase'       => $_purch > 0  ? round($_spend / $_purch,  2) : (float)($m['cost_per_purchase'] ?? 0),
            'cost_per_messaging_conv' => $_msgs  > 0  ? round($_spend / $_msgs,   2) : (float)($m['cost_per_messaging_conv'] ?? 0),
            'cost_per_conversion'     => $_conv  > 0  ? round($_spend / $_conv,   2) : (float)($m['cost_per_conversion'] ?? 0),
            // aliases para compatibilidade com dashboard e buildMessage
            'cpl'                     => $_leads > 0  ? round($_spend / $_leads,  2) : (float)($m['cost_per_lead']  ?? 0),
            'cpv'                     => $_profVisits > 0 ? round($_spend / $_profVisits, 2) : (float)($m['cost_per_profile_visit'] ?? 0), // custo por visita ao perfil
            'cost_per_result'         => $_conv  > 0  ? round($_spend / $_conv,   2) : (float)($m['cost_per_conversion'] ?? 0),
            'cost_per_message'        => $_msgs  > 0  ? round($_spend / $_msgs,   2) : (float)($m['cost_per_messaging_conv'] ?? 0),
            'all_leads'               => $_leads,
            'results'                 => $_conv,
            'days_active'             => (int)($m['days_active']   ?? 0),
            'view_25'                 => (int)($m['view_25']  ?? 0),
            'view_50'                 => (int)($m['view_50']  ?? 0),
            'view_75'                 => (int)($m['view_75']  ?? 0),
            'view_95'                 => (int)($m['view_95']  ?? 0),
            'view_100'                => (int)($m['view_100'] ?? 0),
            'video_view_25'           => (int)($m['view_25']  ?? 0),
            'video_view_50'           => (int)($m['view_50']  ?? 0),
            'video_view_75'           => (int)($m['view_75']  ?? 0),
            'video_view_95'           => (int)($m['view_95']  ?? 0),
            'video_view_100'          => (int)($m['view_100'] ?? 0),
            'thruplay'                => (int)($m['thruplay'] ?? 0),
            'video_avg_time'          => (float)($m['v_avg']  ?? 0),
            // objective normalizado para PT-BR — garante prioridade correta no dashboard
            'objective'               => $objNormalized ?: $rawObj,
        ];

        // Salva contexto completo na sessão — inclui ads buscados diretamente
        $sessionCtx = 'Campanha: '.($m['campaign_name']??'').'\nConta: '.($m['account_name']??'').'\nPeriodo: '.$start.' a '.$end;
        if (!empty($campaignContext)) {
            $sessionCtx .= $campaignContext;
        }
        // Contexto extra removido — 10+ chamadas HTTP síncronas após resposta da IA
        // O contexto já está em $campaignContext via fetchCampaignContext (chamado antes)
        $_SESSION['ai_campaign_context'] = $sessionCtx;
        return [
            'success'          => true,
            'analysis'         => $result['text'],
            'campaign_name'    => $m['campaign_name'],
            'account_name'     => $m['account_name'],
            'objective'        => $metricsOut['objective'], // já normalizado PT-BR
            'metrics'          => $metricsOut,
            'period'           => "$start a $end",
            'provider'         => $provider,
            'model'            => $model,
            'campaign_context' => $campaignContext,
        ];
    }

    // ── Prompt ────────────────────────────────────────────────────────────
    private static function buildPrompt(array $m, array $daily, string $start, string $end, string $selectedMetrics='', string $customPrompt='', string $campaignContext=''): string {
        $sel = $selectedMetrics ? explode(',', $selectedMetrics) : [];
        // Se há prompt personalizado mas nenhuma métrica foi extraída, bloqueia tudo (a IA só responde o texto)
        // Se não há prompt (análise livre), libera tudo para montar análise completa
        $hasPrompt = !empty($customPrompt);
        $has = function(string $k) use ($sel, $hasPrompt): bool {
            if ($hasPrompt) return in_array($k, $sel); // com prompt: só o que foi extraído
            return empty($sel) || in_array($k, $sel);  // sem prompt: tudo (análise livre)
        };

        $spend      = number_format($m['spend'] ?? 0, 2, ',', '.');
        $revenue    = number_format($m['revenue'] ?? 0, 2, ',', '.');
        $roas       = number_format($m['roas'] ?? 0, 2, ',', '.');
        $ctr        = number_format($m['ctr'] ?? 0, 2, ',', '.');
        $cpc        = number_format($m['cpc'] ?? 0, 2, ',', '.');
        $cpm        = number_format($m['cpm'] ?? 0, 2, ',', '.');
        $reach      = number_format($m['reach'] ?? 0, 0, ',', '.');
        $imps       = number_format($m['impressions'] ?? 0, 0, ',', '.');
        $linkClicks = number_format($m['link_clicks'] ?? $m['clicks'] ?? 0, 0, ',', '.');
        $allClicks  = number_format($m['clicks'] ?? 0, 0, ',', '.');
        $clicks     = $linkClicks;
        $convs      = (int)($m['conversions'] ?? 0);
        $cpa        = $convs > 0 ? 'R$ '.number_format(($m['spend'] ?? 0) / $convs, 2, ',', '.') : 'N/A';
        $freqVal    = ($m['frequency'] ?? 0) > 0
                        ? (float)$m['frequency']
                        : ((($m['impressions'] ?? 0) > 0 && ($m['reach'] ?? 0) > 0)
                            ? round((float)$m['impressions'] / (float)$m['reach'], 2) : 0);
        $freq       = number_format($freqVal, 2, ',', '.');
        $msgs       = number_format($m['messages'] ?? $m['messaging_conversations'] ?? 0, 0, ',', '.');
        $leads      = number_format($m['leads'] ?? 0, 0, ',', '.');
        $profvis    = number_format($m['profile_visits'] ?? 0, 0, ',', '.');
        $purchases  = number_format($m['purchases'] ?? 0, 0, ',', '.');
        $videoViews = number_format($m['video_views'] ?? 0, 0, ',', '.');
        $engagement = number_format(($m['engagement'] ?? 0) ?: ($m['add_to_cart'] ?? 0) + $convs, 0, ',', '.');
        $cpl        = isset($m['cost_per_lead']) && $m['cost_per_lead'] > 0 ? 'R$ '.number_format($m['cost_per_lead'], 2, ',', '.') : $cpa;
        $cpv        = ($m['profile_visits'] ?? $m['profile_visit'] ?? 0) > 0 ? 'R$ '.number_format(($m['spend'] ?? 0) / ($m['profile_visits'] ?? $m['profile_visit'] ?? 1), 2, ',', '.') : (($m['cost_per_profile_visit'] ?? 0) > 0 ? 'R$ '.number_format($m['cost_per_profile_visit'], 2, ',', '.') : 'N/A'); // custo por visita ao perfil
        $cmsg       = isset($m['cost_per_messaging_conv']) && $m['cost_per_messaging_conv'] > 0 ? 'R$ '.number_format($m['cost_per_messaging_conv'], 2, ',', '.') : 'N/A';
        $v25  = ($m['view_25']  ?? 0) > 0 ? number_format($m['view_25'],  0, ',', '.') : 'N/A';
        $v50  = ($m['view_50']  ?? 0) > 0 ? number_format($m['view_50'],  0, ',', '.') : 'N/A';
        $v75  = ($m['view_75']  ?? 0) > 0 ? number_format($m['view_75'],  0, ',', '.') : 'N/A';
        $v95  = ($m['view_95']  ?? 0) > 0 ? number_format($m['view_95'],  0, ',', '.') : 'N/A';
        $v100 = ($m['view_100'] ?? 0) > 0 ? number_format($m['view_100'], 0, ',', '.') : 'N/A';
        $thru = ($m['thruplay'] ?? $m['video_views'] ?? 0) > 0 ? number_format($m['thruplay'] ?? $m['video_views'] ?? 0, 0, ',', '.') : 'N/A';
        $vavg = ($m['v_avg']   ?? 0) > 0 ? number_format($m['v_avg'], 1, ',', '.').'s' : 'N/A';

        // Substitui variáveis usando o mesmo buildMessage dos relatórios — 100% igual
        if ($customPrompt) {
            $resolvedPrompt = ReportController::buildMessage($customPrompt, [
                'metrics'       => $m,
                'client_name'   => $m['account_name'] ?? '',
                'client_company'=> $m['account_name'] ?? '',
                'account_name'  => $m['account_name'] ?? '',
                'campaign_name' => $m['campaign_name'] ?? '',
                'periodo'       => date('d/m/Y', strtotime($start)).' a '.date('d/m/Y', strtotime($end)),
                'observacoes'   => '',
            ]);
        } else {
            $resolvedPrompt = '';
        }

        $lines = [];
        if ($has('invest'))   $lines[] = "- Investimento: R\$ {$spend}";
        if ($has('receita') && ($m['revenue'] ?? 0) > 0) $lines[] = "- Receita: R\$ {$revenue}";
        if ($has('roas') && ($m['revenue'] ?? 0) > 0) $lines[] = "- ROAS: {$roas}x";
        if ($has('roas') && ($m['revenue'] ?? 0) == 0) $lines[] = "- ROAS: Nao aplicavel (sem receita rastreada — campanha de alcance/leads)";
        if ($has('cpa'))      $lines[] = "- CPA: {$cpa}";
        if ($has('alcance'))  $lines[] = "- Alcance: {$reach}";
        if ($has('imp'))      $lines[] = "- Impressoes: {$imps}";
        if ($has('clicks'))   $lines[] = "- Cliques no link: {$linkClicks} | Todos: {$allClicks}";
        if ($has('ctr'))      $lines[] = "- CTR: {$ctr}%";
        if ($has('cpc'))      $lines[] = "- CPC: R\$ {$cpc}";
        if ($has('cpm'))      $lines[] = "- CPM: R\$ {$cpm}";
        $freqFinal = $freqVal > 0 ? $freq : number_format(($m['impressions'] ?? 0) > 0 && ($m['reach'] ?? 0) > 0 ? round((float)($m['impressions'] ?? 0) / (float)($m['reach'] ?? 1), 2) : 0, 2, ',', '.');
        if ($has('freq'))     $lines[] = "- Frequencia: {$freqFinal}";
        if ($has('conv'))     $lines[] = "- Conversoes: ".($convs > 0 ? number_format($convs, 0, ',', '.') : "0 (nao ha conversoes registradas para este periodo)");
        if ($has('alcance'))  $lines[] = "- Visitas ao perfil: {$profvis}";
        if ($has('perfil'))   $lines[] = "- Visitas ao perfil: {$profvis} | Custo por visita: ".($m['profile_visits'] > 0 ? 'R$ '.number_format(($m['spend']??0)/($m['profile_visits']), 2, ',', '.') : 'N/A');
        if ($has('conv')  && ($m['leads']       ?? 0) > 0) $lines[] = "- Leads: {$leads}";
        if (!empty($customPrompt) && strpos($customPrompt, '{cmsg}') !== false && ($m['messages'] ?? 0) > 0) $lines[] = "- Mensagens: {$msgs} | Custo/Msg: {$cmsg}";
        if (empty($customPrompt) && $has('cpa') && ($m['messages'] ?? 0) > 0) $lines[] = "- Mensagens: {$msgs} | Custo/Msg: {$cmsg}";
        if ($has('conv')  && ($m['purchases']   ?? 0) > 0) $lines[] = "- Vendas: {$purchases} | CPV: {$cpv}";
        if ($has('tendencia') && ($m['video_views'] ?? 0) > 0) $lines[] = "- Video views: {$videoViews}";
        if ($has('msg') && ($m['messages'] ?? 0) > 0) $lines[] = "- Mensagens: {$msgs} | Custo/Msg: {$cmsg}";

        // Fallback: apenas se não há prompt E nenhuma métrica detectada (análise livre sem variáveis)
        if (empty($lines) && empty($customPrompt)) {
            $lines[] = "- Investimento: R\$ {$spend}";
            if (($m['reach']       ?? 0) > 0) $lines[] = "- Alcance: {$reach}";
            if (($m['impressions'] ?? 0) > 0) $lines[] = "- Impressoes: {$imps}";
            if (($m['link_clicks'] ?? $m['clicks'] ?? 0) > 0) $lines[] = "- Cliques no link: {$linkClicks} | Todos: {$allClicks}";
            if (($m['ctr']         ?? 0) > 0) $lines[] = "- CTR: {$ctr}%";
            if (($m['cpc']         ?? 0) > 0) $lines[] = "- CPC: R\$ {$cpc}";
            if (($m['cpm']         ?? 0) > 0) $lines[] = "- CPM: R\$ {$cpm}";
            if ($freqVal                > 0)   $lines[] = "- Frequencia: {$freqFinal}";
            if (($m['profile_visits']?? 0) > 0) $lines[] = "- Visitas ao perfil: {$profvis}";
            if ($convs                  > 0)   $lines[] = "- Conversoes: ".number_format($convs, 0, ',', '.');
            if (($m['leads']       ?? 0) > 0) $lines[] = "- Leads: {$leads}";
            if (($m['messages']    ?? 0) > 0) $lines[] = "- Mensagens: {$msgs} | Custo/Msg: {$cmsg}";
            if (($m['purchases']   ?? 0) > 0) $lines[] = "- Vendas: {$purchases} | CPV: {$cpv}";
            if (($m['video_views'] ?? 0) > 0) $lines[] = "- Video views: {$videoViews}";
            if (($m['revenue']     ?? 0) > 0) { $lines[] = "- Receita: R\$ {$revenue}"; $lines[] = "- ROAS: {$roas}x"; }
        }

        // Com prompt mas sem métricas detectadas: manda só investimento como contexto mínimo
        if (empty($lines) && !empty($customPrompt)) {
            $lines[] = "- Investimento: R\$ {$spend}";
        }

        $trend = '';
        if ($has('tendencia') && count($daily) >= 3) {
            $half = (int)(count($daily)/2);
            $f = array_slice($daily,0,$half); $s = array_slice($daily,$half);
            $sf  = number_format(array_sum(array_column($f,'spend')),2,',','.');
            $ss  = number_format(array_sum(array_column($s,'spend')),2,',','.');
            $cf  = array_sum(array_column($f,'clicks')); $cs = array_sum(array_column($s,'clicks'));
            $cvF = array_sum(array_column($f,'conversions')); $cvS = array_sum(array_column($s,'conversions'));
            $trend = "\nTendencia: gasto 1a metade R\${$sf} => 2a R\${$ss} | cliques {$cf} => {$cs} | conversoes {$cvF} => {$cvS}";
        }

        $metricsBlock = implode("\n", $lines).$trend;

        // Benchmarks dinâmicos — só os relevantes para as métricas enviadas
        $benchmarks = [];
        if (in_array('ctr', $sel))     $benchmarks[] = "- CTR saudavel: >1,5% (Meta) / >3% (Google Search)";
        if (in_array('cpc', $sel))     $benchmarks[] = "- CPC razoavel: <R\$2,00 (Meta) / <R\$4,00 (Google)";
        if (in_array('roas', $sel))    $benchmarks[] = "- ROAS bom: >3x (e-commerce) / >2x (leads)";
        if (in_array('cpm', $sel))     $benchmarks[] = "- CPM medio: R\$5-40 (Meta)";
        if (in_array('freq', $sel))    $benchmarks[] = "- Frequencia ideal: 1,5 a 3,0 (acima disso = fadiga de anuncio)";
        if (in_array('cpl', $sel))     $benchmarks[] = "- CPL varia por segmento — compare com historico da conta";
        if (in_array('perfil', $sel))  $benchmarks[] = "- Custo por visita ao perfil: varia por setor, compare com historico";
        $benchmarkBlock = $benchmarks ? "\nBENCHMARKS RELEVANTES (Brasil 2025):\n".implode("\n", $benchmarks) : '';

        $campName = $m['campaign_name'] ?? 'Campanha';
        $accName  = $m['account_name']  ?? '';
        $plat     = $m['platform']      ?? '';

        $base = <<<PROMPT
Voce e especialista senior em marketing digital e trafego pago.
Analise APENAS os dados listados abaixo. NAO mencione, NAO estime e NAO invente metricas que nao estejam listadas.

CAMPANHA: {$campName}
CONTA: {$accName} ({$plat})
PERIODO: {$start} a {$end}

DADOS DA CAMPANHA:
{$metricsBlock}{$benchmarkBlock}

REGRA ABSOLUTA: Se uma metrica nao estiver listada acima, NAO a mencione. Analise somente o que foi fornecido. Use TODOS os dados listados acima incluindo criativos e anuncios.
PROMPT;

        if ($resolvedPrompt) {
            return $base . "\n\nINSTRUCAO DO GESTOR:\n{$resolvedPrompt}\n\nResponda em portugues (pt-BR) formatado para WhatsApp com *negrito* e emojis. Responda EXATAMENTE o que foi pedido na instrucao acima, usando apenas os dados fornecidos.";
        }

        return $base . "\n\nGere analise completa em portugues (pt-BR) formatada para WhatsApp com *negrito* e emojis. Estruture EXATAMENTE assim:\n\n📊 *RESUMO EXECUTIVO*\n[2-3 linhas com os numeros mais importantes e avaliacao geral]\n\n✅ *PONTOS POSITIVOS*\n[O que esta funcionando bem comparando com benchmarks]\n\n⚠️ *PONTOS DE ATENCAO*\n[O que precisa melhorar, com diagnostico da causa]\n\n🎯 *DIAGNOSTICO COMPLETO*\n[Analise profunda: eficiencia do gasto, qualidade do trafego, tendencia]\n\n💡 *RECOMENDACOES PRATICAS*\n[3-5 acoes especificas e concretas com prioridade]\n\n📈 *PROXIMOS PASSOS*\n[O que fazer nos proximos 7-14 dias]";
    }

    // ── Busca contexto completo + métricas por anúncio ─────────────
    private static function fetchCampaignContext(string $accountId, string $token, string $campId): string {
        if (!$accountId || !$token || !$campId) return '';
        $ctx  = [];
        $base = "https://graph.facebook.com/v25.0";

        // Campanha
        $ch = curl_init("{$base}/{$campId}?fields=name,objective,status,daily_budget,lifetime_budget&access_token=".urlencode($token));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true]);
        $camp = json_decode(curl_exec($ch),true); curl_close($ch);
        if (!empty($camp['name'])) {
            $bud = !empty($camp['daily_budget']) ? ' | Orcamento diario: R$ '.number_format($camp['daily_budget']/100,2,',','.') : '';
            $ctx[] = "CAMPANHA: {$camp['name']} | Objetivo: ".($camp['objective']??'N/A')." | Status: ".($camp['status']??'N/A').$bud;
        }

        // AdSets com segmentação completa
        $ch = curl_init("{$base}/{$campId}/adsets?fields=id,name,status,daily_budget,lifetime_budget,optimization_goal,device_platforms,targeting&limit=10&access_token=".urlencode($token));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
        $res2 = json_decode(curl_exec($ch),true); curl_close($ch);
        if (empty($res2['data'])) {
            $f3 = urlencode('[{"field":"campaign_id","operator":"IN","value":["'.$campId.'"]}]');
            $ch = curl_init("{$base}/act_{$accountId}/adsets?fields=id,name,status,daily_budget,lifetime_budget,optimization_goal,device_platforms,targeting&filtering={$f3}&limit=10&access_token=".urlencode($token));
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
            $res2 = json_decode(curl_exec($ch),true); curl_close($ch);
        }
        if (!empty($res2['data'])) {
            $ctx[] = "\nCONJUNTOS DE ANUNCIOS (".count($res2['data'])." conjuntos):";
            foreach ($res2['data'] as $adset) {
                $t = $adset['targeting'] ?? [];
                $parts = [];
                if (isset($t['age_min'])) $parts[] = "Idade: {$t['age_min']}-".($t['age_max']??65)." anos";
                if (!empty($t['genders'])) { $g=$t['genders']; $parts[] = "Genero: ".(count($g)>1||in_array(0,$g)?"Todos":(in_array(1,$g)?"Masculino":"Feminino")); } else $parts[] = "Genero: Todos";
                $locs=[];
                if (!empty($t['geo_locations']['countries'])) $locs=array_merge($locs,$t['geo_locations']['countries']);
                if (!empty($t['geo_locations']['regions'])) foreach($t['geo_locations']['regions'] as $r) $locs[]=$r['name']??'';
                if (!empty($t['geo_locations']['cities'])) foreach($t['geo_locations']['cities'] as $c) $locs[]=$c['name']??'';
                if ($locs) $parts[] = "Localizacao: ".implode(', ',array_slice(array_unique($locs),0,5));
                if (!empty($t['locales'])) $parts[] = "Idioma: ".implode(', ',array_slice($t['locales'],0,3));
                $ints=[];$behs=[];$demos=[];
                if (!empty($t['flexible_spec'])) foreach($t['flexible_spec'] as $spec) {
                    if (!empty($spec['interests'])) foreach($spec['interests'] as $i) $ints[]=$i['name']??'';
                    if (!empty($spec['behaviors'])) foreach($spec['behaviors'] as $b) $behs[]=$b['name']??'';
                    if (!empty($spec['demographics'])) foreach($spec['demographics'] as $d) $demos[]=$d['name']??'';
                }
                if ($ints)  $parts[] = "Interesses: ".implode(', ',array_slice($ints,0,8));
                if ($behs)  $parts[] = "Comportamentos: ".implode(', ',array_slice($behs,0,5));
                if ($demos) $parts[] = "Demograficos: ".implode(', ',array_slice($demos,0,5));
                $excl=[];
                if (!empty($t['exclusions']['interests'])) foreach($t['exclusions']['interests'] as $e) $excl[]=$e['name']??'';
                if ($excl) $parts[] = "Exclusoes: ".implode(', ',array_slice($excl,0,4));
                if (!empty($t['custom_audiences'])) { $cn=array_map(fn($a)=>$a['name']??$a['id'],$t['custom_audiences']); $parts[]="Publico customizado: ".implode(', ',array_slice($cn,0,3)); }
                if (!empty($t['lookalike_specs'])) { $ll=$t['lookalike_specs'][0]; $parts[]="Lookalike: ".($ll['ratio']??'')."% ".($ll['country']??''); }
                $pls=[];
                if (!empty($t['publisher_platforms'])) $pls=array_merge($pls,$t['publisher_platforms']);
                if (!empty($t['facebook_positions'])) foreach($t['facebook_positions'] as $p) $pls[]="fb:{$p}";
                if (!empty($t['instagram_positions'])) foreach($t['instagram_positions'] as $p) $pls[]="ig:{$p}";
                if ($pls) $parts[] = "Posicionamentos: ".implode(', ',array_slice($pls,0,5));
                $devs=$adset['device_platforms']??($t['device_platforms']??[]);
                if ($devs) $parts[] = "Dispositivos: ".implode(', ',$devs);
                if (!empty($adset['optimization_goal'])) $parts[] = "Otimizacao: ".$adset['optimization_goal'];
                $bud2 = !empty($adset['daily_budget'])? "R$ ".number_format($adset['daily_budget']/100,2,',','.')." /dia" : '';
                if ($bud2) $parts[] = "Orcamento: {$bud2}";
                $ctx[] = "- [{$adset['status']}] {$adset['name']}: ".implode(' | ',array_filter($parts));
            }
        }

        // Anúncios: busca da conta, filtra por campaign_id no PHP
        $campAds = [];
        $adFlds = 'id,name,status,creative{id,name,body,title,call_to_action_type,object_story_spec,asset_feed_spec,effective_instagram_media_id}';
        $ch = curl_init("{$base}/{$campId}/ads?fields={$adFlds}&limit=20&access_token=".urlencode($token));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>false]);
        $allAdsRaw = curl_exec($ch);
        curl_close($ch);
        $allAds = json_decode($allAdsRaw, true);
        $campAds = $allAds['data'] ?? [];

        // Demographics (age+gender) e Placement em paralelo
        $timeRange = urlencode(json_encode(['since'=>date('Y-m-d',strtotime('-30 days')),'until'=>date('Y-m-d')]));
        $filter    = urlencode(json_encode([['field'=>'campaign.id','operator'=>'IN','value'=>[$campId]]]));

        $mh2 = curl_multi_init();
        $chD = curl_init("{$base}/act_{$accountId}/insights?fields=impressions,spend,ctr,actions&breakdowns=age,gender&filtering={$filter}&time_range={$timeRange}&access_token=".urlencode($token));
        $chP = curl_init("{$base}/act_{$accountId}/insights?fields=impressions,spend,ctr&breakdowns=publisher_platform,platform_position&filtering={$filter}&time_range={$timeRange}&access_token=".urlencode($token));
        curl_setopt_array($chD,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
        curl_setopt_array($chP,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
        curl_multi_add_handle($mh2,$chD); curl_multi_add_handle($mh2,$chP);
        $run2=null; do{curl_multi_exec($mh2,$run2);curl_multi_select($mh2,0.3);}while($run2>0);
        $demog = json_decode(curl_multi_getcontent($chD),true)['data'] ?? [];
        $place = json_decode(curl_multi_getcontent($chP),true)['data'] ?? [];
        curl_multi_remove_handle($mh2,$chD); curl_multi_remove_handle($mh2,$chP); curl_multi_close($mh2);

        if (!empty($demog)) {
            $ctx[] = "
DEMOGRAFIA (ultimos 30 dias):";
            foreach ($demog as $d) {
                $convs=0; foreach(($d['actions']??[]) as $ac) if(str_contains($ac['action_type'],'messaging')) $convs+=(int)$ac['value'];
                $line = "- ".($d['age']??'?')." | ".($d['gender']??'?')." | Gasto: R$ ".number_format((float)($d['spend']??0),2,',','.')." | CTR: ".number_format((float)($d['ctr']??0),2,',','.').'%';
                if($convs>0) $line.=" | Conversas: {$convs}";
                $ctx[] = $line;
            }
        }
        if (!empty($place)) {
            $ctx[] = "
DESEMPENHO POR POSICIONAMENTO:";
            foreach (array_slice($place,0,8) as $p) {
                $ctx[] = "- ".($p['publisher_platform']??'?')."/".($p['platform_position']??'?')." | Gasto: R$ ".number_format((float)($p['spend']??0),2,',','.')." | CTR: ".number_format((float)($p['ctr']??0),2,',','.')."% | Impressoes: ".number_format((int)($p['impressions']??0),0,'.',',');
            }
        }

        // Busca insights por ad_id (sequencial)
        foreach ($campAds as $idx => $ad) {
            try {
                $insCh = curl_init("{$base}/{$ad['id']}/insights?fields=impressions,clicks,spend,ctr,cpm,reach,actions&date_preset=maximum&access_token=".urlencode($token));
                curl_setopt_array($insCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
                $insR = json_decode(curl_exec($insCh),true);
                curl_close($insCh);
                if (!empty($insR['data'])) $campAds[$idx]['insights'] = $insR;
            } catch (\Throwable $e) {}
        }
        $res3 = ['data' => $campAds];
        if (!empty($res3['data'])) {
            $ctx[] = "\nANUNCIOS COM METRICAS (".count($res3['data'])." anuncios):";
            foreach ($res3['data'] as $ad) {
                $cr  = $ad['creative'] ?? [];
                $ins = $ad['insights']['data'][0] ?? [];
                $adParts = [];
                if (!empty($cr['title']))       $adParts[] = "Headline: {$cr['title']}";
                if (!empty($cr['body']))         $adParts[] = "Texto: ".mb_substr($cr['body'],0,400);
                if (!empty($cr['description'])) $adParts[] = "Descricao: {$cr['description']}";
                if (!empty($cr['call_to_action_type'])) $adParts[] = "CTA: {$cr['call_to_action_type']}";
                if (!empty($cr['video_id']))    $adParts[] = "Tipo: VIDEO";
                elseif (!empty($cr['image_url'])) $adParts[] = "Tipo: IMAGEM";
                // object_story_spec fallback
                if (empty($cr['body']) && !empty($cr['object_story_spec'])) {
                    $oss = $cr['object_story_spec'];
                    $m = $oss['link_data']['message']??$oss['photo_data']['caption']??$oss['video_data']['message']??'';
                    if ($m) $adParts[] = "Texto: ".mb_substr($m,0,400);
                }
                // Busca criativo expandido se ainda sem texto
                if (empty(array_filter($adParts,fn($p)=>str_starts_with($p,'Texto:'))) && !empty($cr['id'])) {
                    try {
                        $crCh=curl_init("{$base}/{$cr['id']}?fields=body,title,object_story_spec,asset_feed_spec&access_token=".urlencode($token));
                        curl_setopt_array($crCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>false]);
                        $crD=json_decode(curl_exec($crCh),true); curl_close($crCh);
                        if (!empty($crD['body'])) $adParts[]="Texto: ".mb_substr($crD['body'],0,400);
                        elseif (!empty($crD['object_story_spec'])) {
                            $oss2=$crD['object_story_spec'];
                            $m2=$oss2['link_data']['message']??$oss2['photo_data']['caption']??$oss2['video_data']['message']??'';
                            if ($m2) $adParts[]="Texto: ".mb_substr($m2,0,400);
                        }
                        elseif (!empty($crD['asset_feed_spec']['bodies'][0]['text'])) {
                            $adParts[]="Texto: ".mb_substr($crD['asset_feed_spec']['bodies'][0]['text'],0,400);
                        }
                    } catch (\Throwable $e) {}
                }
                // Métricas do anúncio
                if (!empty($ins)) {
                    $sp  = number_format((float)($ins['spend']??0),2,',','.');
                    $imp = number_format((int)($ins['impressions']??0),0,'.',',');
                    $rea = number_format((int)($ins['reach']??0),0,'.',',');
                    $clk = number_format((int)($ins['clicks']??0),0,'.',',');
                    $ctr2= number_format((float)($ins['ctr']??0),2,',','.');
                    $cpm2= number_format((float)($ins['cpm']??0),2,',','.');
                    $adParts[] = "METRICAS: Gasto R$ {$sp} | Impressoes {$imp} | Alcance {$rea} | Cliques {$clk} | CTR {$ctr2}% | CPM R$ {$cpm2}";
                    $acMap=['onsite_conversion.total_messaging_connection'=>'Conversas','link_click'=>'Cliques no link','post_reaction'=>'Reacoes','video_view'=>'Views video','post_engagement'=>'Engajamento','purchase'=>'Compras','lead'=>'Leads','onsite_conversion.post_net_like'=>'Curtidas','comment'=>'Comentarios','onsite_conversion.messaging_first_reply'=>'Primeiras respostas'];
                    $acs=[]; $convs=0;
                    foreach(($ins['actions']??[]) as $ac) {
                        $lbl=$acMap[$ac['action_type']]??null;
                        if ($lbl) $acs[]="{$lbl}: {$ac['value']}";
                        if ($ac['action_type']==='onsite_conversion.total_messaging_connection') $convs=(int)$ac['value'];
                    }
                    if ($acs) $adParts[]="ACOES: ".implode(' | ',$acs);
                    if ($convs>0) $adParts[]="Custo/conversa: R$ ".number_format((float)($ins['spend']??0)/$convs,2,',','.');
                }
                $ctx[] = "- [{$ad['status']}] {$ad['name']}: ".implode(' | ',array_filter($adParts));
            }
        }

        return empty($ctx) ? '' : "\n\n=== CONTEXTO COMPLETO DA CAMPANHA (API Meta) ===\n".implode("\n",$ctx)."\n=== FIM DO CONTEXTO ===";
    }

    private static function callAI(string $provider, string $model, string $key, string $prompt): array {
        try {
            return match($provider) {
                'groq'   => self::callGroq($key, $model, $prompt),
                'openai' => self::callOpenAI($key, $model, $prompt),
                'gemini' => self::callGemini($key, $model, $prompt),
                default  => ['success'=>false,'error'=>'Provedor invalido.'],
            };
        } catch (\Throwable $e) {
            return ['success'=>false,'error'=>'Erro: '.$e->getMessage()];
        }
    }

    private static function callGroq(string $key, string $model, string $prompt): array {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>3000,'temperature'=>0.7]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_TIMEOUT=>90]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        if($code!==200||empty($data['choices'][0]['message']['content'])) return ['success'=>false,'error'=>'Groq: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($data['choices'][0]['message']['content'])];
    }

    private static function callOpenAI(string $key, string $model, string $prompt): array {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>3000,'temperature'=>0.7]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_TIMEOUT=>120]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        if($code!==200||empty($data['choices'][0]['message']['content'])) return ['success'=>false,'error'=>'OpenAI: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($data['choices'][0]['message']['content'])];
    }

    private static function callGemini(string $key, string $model, string $prompt): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['maxOutputTokens'=>3000,'temperature'=>0.7]]),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>120]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode($resp,true);
        $text=$data['candidates'][0]['content']['parts'][0]['text']??null;
        if($code!==200||!$text) return ['success'=>false,'error'=>'Gemini: '.($data['error']['message']??"HTTP $code")];
        return ['success'=>true,'text'=>trim($text)];
    }

    private static function fetchWpGroups(array $instance): array {
        try {
            $groups = EvolutionApi::fetchGroups($instance['instance_name'] ?? '');
            if (!is_array($groups)) return [];
            return array_filter(array_map(fn($g) => is_array($g) ? ['id' => $g['id'] ?? '', 'name' => $g['subject'] ?? $g['id'] ?? ''] : null, $groups));
        } catch (\Throwable $e) { return []; }
    }

    public function syncAccount(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        header('Content-Type: application/json');
        $accId = (int)($_POST['account_id'] ?? 0);
        if (!$accId) { echo json_encode(['success'=>false,'error'=>'ID invalido.']); return; }
        $acc = decryptTokens($db->query("SELECT * FROM ad_accounts WHERE id=? AND user_id=?", [$accId,$uid])->fetch() ?: []);
        if (!$acc) {
            // Tenta buscar qualquer conta ativa do usuário como fallback
            $acc = $db->query(
                "SELECT * FROM ad_accounts WHERE user_id=? AND status='active' LIMIT 1",
                [$uid]
            )->fetch();
            if (!$acc) {
                echo json_encode(['success'=>false,'error'=>'Conta de anuncio nao encontrada. Edite o relatorio e selecione uma conta valida.']);
                return;
            }
        }
        try {
            $start = date('Y-m-d', strtotime('-365 days'));
            $end   = date('Y-m-d');
            $ctrl  = new AccountController();
            if ($acc['platform'] === 'meta') {
                $m = new ReflectionMethod($ctrl, 'syncMeta');
                $m->setAccessible(true);
                $m->invoke($ctrl, $acc, $start, $end, $db);
            } else {
                $m = new ReflectionMethod($ctrl, 'syncGoogle');
                $m->setAccessible(true);
                $m->invoke($ctrl, $acc, $start, $end, $db);
            }
            $count = $db->query("SELECT COUNT(DISTINCT campaign_id) FROM campaign_metrics WHERE ad_account_id=?", [$accId])->fetchColumn();
            echo json_encode(['success'=>true,'campaigns'=>(int)$count]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    public function chatReport(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        header('Content-Type: application/json');

        $provider    = sanitize($_POST['provider'] ?? '');
        $model       = sanitize($_POST['model']    ?? '');
        $message     = trim($_POST['message']      ?? '');
        $historyRaw  = $_POST['history']           ?? '[]';
        $history     = json_decode($historyRaw, true) ?: [];

        if (!$message) { echo json_encode(['success'=>false,'error'=>'Mensagem vazia']); return; }

        $db = Database::getInstance();
        $aiSettings = self::getAiSettings($uid, $db);
        $apiKey = self::getKey($aiSettings, $provider);
        if (!$apiKey) [$provider, $model, $apiKey] = self::autoProvider($aiSettings);
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>'Nenhuma chave de IA configurada.']); return; }

        // Usa contexto completo da sessão (demografia, posicionamento, criativos, etc)
        $reportCtx = $_SESSION['ai_report_context'] ?? $_SESSION['ai_campaign_context'] ?? '';
        $system = 'Voce e um especialista senior em marketing digital e trafego pago. Responda de forma direta e objetiva em portugues (pt-BR). Quando tiver dados de anuncios com metricas, compare-os e aponte qual performa melhor com base nos numeros.\n\nREGRA ABSOLUTA: (1) Voce TEM acesso aos dados abaixo — USE-OS TODOS para responder. (2) NUNCA diga que nao tem acesso a dados que estao listados abaixo — demografia, posicionamento, metricas por anuncio, desempenho diario, frequencia estao todos disponíveis. (3) So diga que nao tem acesso a: ROI, receita de vendas, dados pos-clique/funil, feedback qualitativo, benchmarks do setor. (4) NUNCA invente dados ausentes.';
        if ($reportCtx) {
            $system .= "\n\nDADOS COMPLETOS DA CAMPANHA (voce tem tudo isso):\n\n{$reportCtx}\n\nEsses dados incluem: metricas gerais, DEMOGRAFIA por idade/genero, POSICIONAMENTO por plataforma, DESEMPENHO DIARIO, FREQUENCIA, ANUNCIOS com metricas individuais e RANKING de criativos. Quando o usuario perguntar sobre qualquer um desses, use os dados acima.";
        }

        // Garante que o histórico está correto
        $cleanHistory = [];
        foreach ($history as $h) {
            if (!empty($h['role']) && !empty($h['content'])) {
                $cleanHistory[] = ['role'=>$h['role'], 'content'=>$h['content']];
            }
        }
        $cleanHistory[] = ['role'=>'user', 'content'=>$message];

        try {
            if ($provider === 'gemini') {
                $res = self::callGeminiChat($apiKey, $model, $system, $cleanHistory);
            } elseif ($provider === 'openai') {
                $res = self::callOpenAIChat($apiKey, $model, $system, $cleanHistory);
            } else {
                $res = self::callGroqChat($apiKey, $model, $system, $cleanHistory);
            }
            if (!$res['success']) { echo json_encode(['success'=>false,'error'=>$res['error']]); return; }
            echo json_encode(['success'=>true, 'reply'=>$res['text']]);
        } catch (\Exception $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    public function analyzeReport(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        header('Content-Type: application/json');

        $accId       = (int)($_POST['account_id']   ?? 0);
        $campIds     = sanitize($_POST['camp_ids']   ?? '');
        $periodo     = sanitize($_POST['periodo']    ?? 'last_30_days');
        $customStart = sanitize($_POST['custom_start'] ?? '');
        $customEnd   = sanitize($_POST['custom_end']   ?? '');
        $provider    = sanitize($_POST['provider']   ?? '');
        $model       = sanitize($_POST['model']      ?? '');
        $customPrompt    = trim($_POST['custom_prompt']  ?? '');
        $templateContent = trim($_POST['template'] ?? '');

        $aiSettings = self::getAiSettings($uid, $db);
        $apiKey = self::getKey($aiSettings, $provider);
        if (!$apiKey) {
            [$provider, $model, $apiKey] = self::autoProvider($aiSettings);
        }
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>'Nenhuma chave de IA configurada. Configure em Analise IA.']); return; }
        if (!$accId)  { echo json_encode(['success'=>false,'error'=>'Conta de anuncios nao encontrada.']); return; }

        $acc = decryptTokens($db->query("SELECT * FROM ad_accounts WHERE id=? AND user_id=?", [$accId,$uid])->fetch() ?: []);
        if (!$acc) {
            // Tenta buscar qualquer conta ativa do usuário como fallback
            $acc = $db->query(
                "SELECT * FROM ad_accounts WHERE user_id=? AND status='active' LIMIT 1",
                [$uid]
            )->fetch();
            if (!$acc) {
                echo json_encode(['success'=>false,'error'=>'Conta de anuncio nao encontrada. Edite o relatorio e selecione uma conta valida.']);
                return;
            }
        }

        [$start, $end] = self::resolvePeriod($periodo === 'max' ? 'maximum' : $periodo, $customStart, $customEnd);

        // Resolve MAX igual aos relatórios
        if ($start === 'MAX') {
            $start = ReportController::fetchCampaignStartDate(
                $acc['account_id'], $acc['access_token'] ?? '',
                $campIds ? array_filter(array_map('trim', explode(',', $campIds))) : [],
                (int)$accId
            );
        }

        $campFilter = '';
        $campParams = [$accId, $start, $end];
        if ($campIds) {
            $ids = array_filter(array_map('trim', explode(',', $campIds)));
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $campFilter = " AND campaign_id IN ($ph)";
                $campParams = array_merge([$accId], $ids, [$start, $end]);
            }
        }

        // Tenta API Meta primeiro para relatórios também
        $metrics = null;
        if (!empty($acc['access_token']) && in_array($acc['platform'], ['meta','meta_ads'])) {
            $campIdsArr = $campIds ? array_filter(array_map('trim', explode(',', $campIds))) : [];
            $metaData = ReportController::fetchMetricsMeta($acc['account_id'], $acc['access_token'], $start, $end, $campIdsArr);
            // Busca extra de profile_visit (API Meta não retorna com filtro por campaign.id)
            if ($metaData && ($metaData['profile_visit'] ?? 0) == 0 && !empty($campIdsArr)) {
                try {
                    $pvTotal = 0;
                    foreach (array_slice($campIdsArr, 0, 3) as $pvCampId) {
                        $pvUrl = "https://graph.facebook.com/".META_API_VERSION."/{\}/insights"
                               . "?fields=instagram_profile_visits,actions"
                               . "&time_range=".urlencode(json_encode(['since'=>$start,'until'=>$end]))
                               . "&access_token=".urlencode($acc['access_token']);
                        $pvCh = curl_init($pvUrl);
                        curl_setopt_array($pvCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);
                        $pvRes = json_decode(curl_exec($pvCh), true);
                        curl_close($pvCh);
                        foreach ($pvRes['data'] ?? [] as $pvRow) {
                            $pv = (int)($pvRow['instagram_profile_visits'] ?? 0);
                            if (!$pv) {
                                foreach ($pvRow['actions'] ?? [] as $a) {
                                    if (in_array($a['action_type'], ['ig_profile_visit','profile_visit','instagram_profile_visit'])) {
                                        $pv += (int)$a['value'];
                                    }
                                }
                            }
                            $pvTotal += $pv;
                        }
                    }
                    if ($pvTotal > 0) $metaData['profile_visit'] = $pvTotal;
                } catch (\Throwable $e) {}
            }
            if ($metaData && ($metaData['spend'] ?? 0) > 0) {
                $sp = (float)($metaData['spend'] ?? 0);
                $cv = (int)($metaData['conversions'] ?? 0);
                $metrics = [
                    'spend'           => $sp,
                    'revenue'         => (float)($metaData['revenue']    ?? 0),
                    'roas'            => $sp > 0 ? round(($metaData['revenue'] ?? 0) / $sp, 2) : 0,
                    'impressions'     => (int)($metaData['impressions']  ?? 0),
                    'clicks'          => (int)($metaData['link_click']   ?? $metaData['clicks'] ?? 0),
                    'clicks_all'      => (int)($metaData['clicks']       ?? 0),
                    'reach'           => (int)($metaData['reach']        ?? 0),
                    'ctr'             => (float)($metaData['ctr']        ?? 0),
                    'cpc'             => (float)($metaData['cpc']        ?? 0),
                    'cpm'             => (float)($metaData['cpm']        ?? 0),
                    'frequency'       => ($metaData['frequency'] ?? 0) > 0 ? (float)$metaData['frequency'] : ((($metaData['impressions'] ?? 0) > 0 && ($metaData['reach'] ?? 0) > 0) ? round($metaData['impressions'] / $metaData['reach'], 2) : 0),
                    'conversions'     => $cv,
                    'purchases'       => (int)($metaData['purchase']     ?? 0),
                    'leads'           => (int)($metaData['leads']        ?? 0),
                    'messages'        => (int)($metaData['msg_all']      ?? 0),
                    'video_views'     => (int)($metaData['vplay']        ?? 0),
                    'profile_visits'  => (int)($metaData['profile_visit']?? 0),
                    'profile_visit'   => (int)($metaData['profile_visit']?? 0),
                    'engagement'      => (int)($metaData['engajamento']  ?? 0),
                    'cost_per_lead'   => (int)($metaData['leads'] ?? 0) > 0 ? round($sp / $metaData['leads'], 2) : 0,
                    'cost_per_purchase'=> (int)($metaData['purchase'] ?? 0) > 0 ? round($sp / $metaData['purchase'], 2) : 0,
                    'cost_per_messaging_conv'=> (int)($metaData['msg_all'] ?? 0) > 0 ? round($sp / $metaData['msg_all'], 2) : 0,
                    'cost_per_message'=> (int)($metaData['msg_all'] ?? 0) > 0 ? round($sp / $metaData['msg_all'], 2) : 0,
                    'cpl'             => (int)($metaData['leads'] ?? 0) > 0 ? round($sp / $metaData['leads'], 2) : 0,
                    'cpv'             => (int)($metaData['profile_visits'] ?? $metaData['profile_visit'] ?? 0) > 0 ? round($sp / ($metaData['profile_visits'] ?? $metaData['profile_visit']), 2) : 0, // custo por visita ao perfil
                    'view_25'         => (int)($metaData['view_25']  ?? 0),
                    'view_50'         => (int)($metaData['view_50']  ?? 0),
                    'view_75'         => (int)($metaData['view_75']  ?? 0),
                    'view_95'         => (int)($metaData['view_95']  ?? 0),
                    'view_100'        => (int)($metaData['view_100'] ?? 0),
                    'thruplay'        => (int)($metaData['thruplay'] ?? 0),
                    'v_avg'           => (float)($metaData['v_avg']  ?? 0),
                    'total_campaigns' => 1,
                    'days_active'     => 1,
                    'all_actions'     => $metaData['all_actions'] ?? [],
                ];
            }
        }

        // Fallback banco local
        if (!$metrics) {
            $metrics = $db->query(
                "SELECT SUM(impressions) AS impressions, SUM(clicks) AS clicks,
                   SUM(spend) AS spend, SUM(conversions) AS conversions,
                   SUM(reach) AS reach, SUM(COALESCE(revenue,0)) AS revenue,
                   AVG(ctr) AS ctr, AVG(cpc) AS cpc, AVG(cpm) AS cpm, AVG(roas) AS roas,
                   COUNT(DISTINCT campaign_id) AS total_campaigns, COUNT(DISTINCT date) AS days_active
                 FROM campaign_metrics WHERE ad_account_id=?{$campFilter} AND date BETWEEN ? AND ?",
                $campParams
            )->fetch();
        }

        if (!$metrics || !($metrics['spend'] ?? 0)) {
            echo json_encode(['success'=>false,'error'=>"Sem dados de metricas no periodo $start a $end."]); return;
        }

        $daily = [];
        try {
            $daily = $db->query(
                "SELECT date, SUM(spend) AS spend, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
                   SUM(conversions) AS conversions, AVG(ctr) AS ctr, AVG(cpc) AS cpc, AVG(roas) AS roas
                 FROM campaign_metrics WHERE ad_account_id=?{$campFilter} AND date BETWEEN ? AND ?
                 GROUP BY date ORDER BY date",
                $campParams
            )->fetchAll();
        } catch (\Throwable $e) {}

        $campNames = [];
        try {
            $campNames = $db->query(
                "SELECT DISTINCT campaign_name FROM campaign_metrics WHERE ad_account_id=?{$campFilter} AND date BETWEEN ? AND ?",
                $campParams
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {}

        $selectedMetrics = sanitize($_POST['metrics'] ?? '');

        // Se tem template: preenche variáveis com dados reais e usa como mensagem base
        $templateFilled = '';
        if (!empty($templateContent) && $metrics) {
            try {
                $clientData = [];
                // Tenta buscar dados do cliente pelo relatório
                if (!empty($campIds)) {
                    $repData = $db->query("SELECT r.*, c.name as client_name, c.company as client_company FROM reports r LEFT JOIN clients c ON c.id=r.client_id WHERE r.ad_account_id=? AND r.user_id=? LIMIT 1", [$accId,$uid])->fetch();
                } else {
                    $repData = $db->query("SELECT r.*, c.name as client_name, c.company as client_company FROM reports r LEFT JOIN clients c ON c.id=r.client_id WHERE r.user_id=? LIMIT 1", [$uid])->fetch();
                }
                $campName   = !empty($campNames) ? implode(', ', array_slice($campNames,0,2)) : ($acc['account_name']??'');
                $clientName = $repData['client_name'] ?? '';
                // empresa = nome do cliente ou conta de anúncio
                $empresa    = $repData['client_company'] ?? $repData['client_name'] ?? $acc['account_name'] ?? '';
                if (empty($empresa)) $empresa = $acc['account_name'] ?? '';
                // Gera link público do relatório para substituir {link} no template
                $aiLinkPublico = '';
                try {
                    if (!empty($repData['share_token'])) {
                        $aiLinkPublico = APP_URL . '/r?t=' . $repData['share_token'];
                        if (!empty($repData['pdf_tpl_id'])) $aiLinkPublico .= '&tpl=' . (int)$repData['pdf_tpl_id'];
                    }
                } catch (\Throwable $_ail) {}
                $templateFilled = ReportController::buildMessage($templateContent, [
                    'metrics'        => $metrics,
                    'client_name'    => $clientName ?: ($acc['account_name']??''),
                    'client_company' => $empresa,
                    'account_name'   => $acc['account_name'] ?? '',
                    'periodo'        => "$start a $end",
                    'campaign_name'  => $campName,
                    'observacoes'    => '',
                    'link'           => $aiLinkPublico,
                ]);
            } catch (\Throwable $e) { $templateFilled = ''; }
        }


        $campaignContextR = '';
        if (!empty($acc['access_token']) && !empty($campIds)) {
            foreach (array_slice(explode(',', $campIds), 0, 3) as $cid) {
                $campaignContextR .= self::fetchCampaignContext($acc['account_id'], $acc['access_token'], trim($cid));
            }
        }
        // Retorno direto quando template já preenchido (sem chamadas extras à API)
        $useTemplateDirect = ($_POST['use_template_direct']??'0') === '1';
        if (!empty($templateFilled) && (empty($customPrompt) || $useTemplateDirect)) {
            $reportSessionCtxDirect = "Conta: {$acc['account_name']}\nPeriodo: {$start} a {$end}\nCampanhas: ".implode(', ', $campNames).$campaignContextR;
            $_SESSION['ai_report_context'] = $reportSessionCtxDirect;
            echo json_encode([
                'success'  => true,
                'analysis' => $templateFilled,
                'metrics'  => $metrics,
                'period'   => "$start a $end",
                'provider' => $provider,
                'model'    => $model,
            ]);
            return;
        }
        $finalPrompt = $customPrompt;
        if (!empty($templateFilled) && !empty($customPrompt)) {
            $finalPrompt = "Baseado nesta mensagem ja preenchida:\n\n{$templateFilled}\n\nInstrucao adicional: {$customPrompt}\n\nRetorne a mensagem melhorada seguindo a instrucao, mantendo o formato WhatsApp.";
        }
        $prompt = self::buildReportPrompt($acc['account_name'], $acc['platform'], $campNames, $metrics, $daily, $start, $end, $finalPrompt, $selectedMetrics, $campaignContextR);
        $result = self::callAI($provider, $model, $apiKey, $prompt);
        if (!$result['success']) { echo json_encode($result); return; }

        // Salva contexto completo na sessão para o chat de relatório
        $reportSessionCtx = "Conta: {$acc['account_name']}\nPeriodo: {$start} a {$end}\nCampanhas: ".implode(', ', $campNames).$campaignContextR;

        $_SESSION['ai_report_context'] = $reportSessionCtx;

        echo json_encode([
            'success'  => true,
            'analysis' => $result['text'],
            'metrics'  => $metrics,
            'period'   => "$start a $end",
            'provider' => $provider,
            'model'    => $model,
        ]);
    }

    private static function buildReportPrompt(string $accName, string $platform, array $campNames, array $m, array $daily, string $start, string $end, string $customPrompt='', string $selectedMetrics='', string $campaignContext=''): string {
        $spend = number_format($m['spend'] ?? 0, 2, ',', '.');
        $rev   = number_format($m['revenue'] ?? 0, 2, ',', '.');
        $roas  = number_format($m['roas'] ?? 0, 2, ',', '.');
        $ctr   = number_format($m['ctr'] ?? 0, 2, ',', '.');
        $cpc   = number_format($m['cpc'] ?? 0, 2, ',', '.');
        $cpm   = number_format($m['cpm'] ?? 0, 2, ',', '.');
        $reach = number_format($m['reach'] ?? 0, 0, ',', '.');
        $imps  = number_format($m['impressions'] ?? 0, 0, ',', '.');
        $linkC = number_format($m['clicks'] ?? 0, 0, ',', '.');
        $convs = (int)($m['conversions'] ?? 0);
        $cpa   = $convs > 0 ? 'R$ '.number_format(($m['spend'] ?? 0) / $convs, 2, ',', '.') : 'N/A';
        $freqVal2 = ($m['frequency'] ?? 0) > 0
                        ? (float)$m['frequency']
                        : ((($m['impressions'] ?? 0) > 0 && ($m['reach'] ?? 0) > 0)
                            ? round((float)$m['impressions'] / (float)$m['reach'], 2) : 0);
        $freq  = number_format($freqVal2, 2, ',', '.');
        $leads = number_format($m['leads'] ?? 0, 0, ',', '.');
        $msgs  = number_format($m['messages'] ?? 0, 0, ',', '.');
        $profvRaw = (int)($m['profile_visit'] ?? $m['profile_visits'] ?? 0);
        $profv = number_format($profvRaw, 0, ',', '.');
        $cprofv = $profvRaw > 0 ? 'R$ '.number_format(($m['spend'] ?? 0) / $profvRaw, 2, ',', '.') : 'N/A';
        $purch = number_format($m['purchases'] ?? 0, 0, ',', '.');
        $cpl   = ($m['cost_per_lead'] ?? 0) > 0 ? 'R$ '.number_format($m['cost_per_lead'], 2, ',', '.') : $cpa;
        $cpv   = $profvRaw > 0 ? 'R$ '.number_format(($m['spend'] ?? 0) / $profvRaw, 2, ',', '.') : (($m['cost_per_profile_visit'] ?? 0) > 0 ? 'R$ '.number_format($m['cost_per_profile_visit'], 2, ',', '.') : 'N/A'); // custo por visita ao perfil
        $cmsg  = ($m['cost_per_messaging_conv'] ?? 0) > 0 ? 'R$ '.number_format($m['cost_per_messaging_conv'], 2, ',', '.') : 'N/A';
        $camps = $campNames ? implode(', ', array_slice($campNames,0,5)).(count($campNames)>5?' e mais '.(count($campNames)-5):'') : 'Todas';

        if ($customPrompt) {
            $resolvedPrompt = ReportController::buildMessage($customPrompt, [
                'metrics'       => $m,
                'client_name'   => $accName,
                'client_company'=> $accName,
                'account_name'  => $accName,
                'campaign_name' => $camps,
                'periodo'       => date('d/m/Y', strtotime($start)).' a '.date('d/m/Y', strtotime($end)),
                'observacoes'   => '',
                'link'          => '', // link não disponível no contexto de prompt livre
            ]);
        } else {
            $resolvedPrompt = '';
        }

        $trend = '';
        if (count($daily) >= 4) {
            $half = (int)(count($daily)/2);
            $f = array_slice($daily,0,$half); $s = array_slice($daily,$half);
            $sf = number_format(array_sum(array_column($f,'spend')),2,',','.');
            $ss = number_format(array_sum(array_column($s,'spend')),2,',','.');
            $cf = array_sum(array_column($f,'clicks')); $cs = array_sum(array_column($s,'clicks'));
            $cvF= array_sum(array_column($f,'conversions')); $cvS= array_sum(array_column($s,'conversions'));
            $trend = "\nTendencia: gasto R\${$sf}=>R\${$ss} | cliques {$cf}=>{$cs} | conversoes {$cvF}=>{$cvS}";
        }

        $totalCamp = $m['total_campaigns'] ?? 0;
        $daysAct   = $m['days_active']     ?? 0;

        // Se o usuário escreveu variáveis no prompt, resolver e usar como instrução principal
        // O buildMessage substitui {alcance}, {investimento}, etc. pelos valores reais
        $extraInstr = $resolvedPrompt ? "\n\nINSTRUCAO PRIORITARIA DO GESTOR (siga a risca):\n{$resolvedPrompt}" : '';

        // Monta bloco de métricas — APENAS as solicitadas via variáveis/métricas selecionadas.
        // Se nenhuma métrica específica foi solicitada E há prompt livre (texto), manda só investimento como contexto mínimo.
        // NUNCA manda métricas não solicitadas — a IA analisa SOMENTE o que foi pedido.
        $sel = $selectedMetrics ? array_flip(array_filter(explode(',', $selectedMetrics))) : null;
        $has = function(string $id) use ($sel): bool { return $sel !== null && isset($sel[$id]); };

        $metricsLines = [];
        if ($sel !== null) {
            // Métricas explicitamente solicitadas via variáveis
            if ($has('invest'))                               $metricsLines[] = "- Investimento: R$ {$spend}";
            if ($has('receita') && ($m['revenue']??0)>0)      $metricsLines[] = "- Receita: R$ {$rev}";
            if ($has('roas')    && ($m['revenue']??0)>0)      $metricsLines[] = "- ROAS: {$roas}x";
            if ($has('roas')    && ($m['revenue']??0)==0)     $metricsLines[] = "- ROAS: nao disponivel (sem receita rastreada)";
            if ($has('cpa'))                                  $metricsLines[] = "- CPA: {$cpa}";
            if ($has('conv'))                                 $metricsLines[] = "- Conversoes: ".($convs > 0 ? $convs : "0");
            if ($has('leads')   && ($m['leads']??0) > 0)     $metricsLines[] = "- Leads: {$leads}";
            if ($has('compras') && ($m['purchases']??0) > 0) $metricsLines[] = "- Compras: {$purch}";
            if ($has('cpl')     && ($m['leads']??0) > 0)     $metricsLines[] = "- CPL: {$cpl}";
            if ($has('alcance'))                              $metricsLines[] = "- Alcance: {$reach}";
            if ($has('imp'))                                  $metricsLines[] = "- Impressoes: {$imps}";
            if ($has('freq'))                                 $metricsLines[] = "- Frequencia: {$freq}";
            if ($has('clicks'))                               $metricsLines[] = "- Cliques: {$linkC}";
            if ($has('ctr'))                                  $metricsLines[] = "- CTR: {$ctr}%";
            if ($has('cpc'))                                  $metricsLines[] = "- CPC: R$ {$cpc}";
            if ($has('cpm'))                                  $metricsLines[] = "- CPM: R$ {$cpm}";
            if ($has('perfil'))                               $metricsLines[] = "- Visitas ao perfil: {$profv} | Custo por visita: {$cprofv}";
            if ($has('msg'))                                  $metricsLines[] = "- Mensagens: {$msgs} | Custo/Msg: {$cmsg}";
            if ($has('video_views'))                          $metricsLines[] = "- Views Video: ".number_format($m['video_views']??0,0,',','.');
            if ($has('thruplay'))                             $metricsLines[] = "- Thruplay: ".number_format($m['thruplay']??0,0,',','.');
            if ($has('view_25'))                              $metricsLines[] = "- 25% assistido: ".number_format($m['view_25']??0,0,',','.');
            if ($has('view_50'))                              $metricsLines[] = "- 50% assistido: ".number_format($m['view_50']??0,0,',','.');
            if ($has('view_75'))                              $metricsLines[] = "- 75% assistido: ".number_format($m['view_75']??0,0,',','.');
            if ($has('criativos'))                            $metricsLines[] = "- Dados de criativos disponiveis no prompt do gestor.";
            if ($has('tendencia') && $trend)                  $metricsLines[] = $trend;
        }

        // Se ainda vazio (prompt livre sem variáveis), manda só investimento como contexto mínimo
        if (empty($metricsLines)) {
            $metricsLines[] = "- Investimento: R$ {$spend}";
        }

        $metricsBlock = implode("\n", $metricsLines);

        // Benchmarks: incluir apenas os relevantes para as métricas solicitadas
        $benchmarks = [];
        if ($sel !== null) {
            if ($has('ctr'))    $benchmarks[] = "- CTR saudavel: >1,5% (Meta) / >3% (Google)";
            if ($has('cpc'))    $benchmarks[] = "- CPC razoavel: <R\$2,00 (Meta) / <R\$4,00 (Google)";
            if ($has('roas'))   $benchmarks[] = "- ROAS bom: >3x (e-commerce) / >2x (leads)";
            if ($has('cpm'))    $benchmarks[] = "- CPM razoavel: <R\$15,00 (Meta)";
            if ($has('freq'))   $benchmarks[] = "- Frequencia ideal: 1,5 a 3,0 (acima disso = fadiga)";
            if ($has('cpl'))    $benchmarks[] = "- CPL varia por segmento; compare com historico da conta.";
        }
        $benchmarkBlock = $benchmarks ? "\n\nBENCHMARKS RELEVANTES (Brasil 2025):\n".implode("\n", $benchmarks) : '';

        $base2 = <<<PROMPT
Voce e especialista senior em marketing digital e trafego pago.
Analise APENAS as metricas informadas abaixo. NAO invente, NAO estime e NAO mencione metricas que nao estejam listadas.
Se uma metrica nao estiver na lista abaixo, simplesmente ignore-a.

CONTA: {$accName} ({$platform})
CAMPANHAS: {$camps}
PERIODO: {$start} a {$end} ({$daysAct} dias | {$totalCamp} campanha(s))

METRICAS PARA ANALISAR:
{$metricsBlock}{$benchmarkBlock}{$campaignContext}
PROMPT;

        if ($resolvedPrompt) {
            return $base2 . "\n\nINSTRUCAO DO GESTOR:\n{$resolvedPrompt}\n\nResponda em portugues (pt-BR) formatado para WhatsApp com *negrito* e emojis. Responda EXATAMENTE o que foi pedido, usando apenas os dados fornecidos acima.";
        }

        return $base2 . "\n\nResponda em portugues (pt-BR) com *negrito* e emojis. Analise somente as metricas listadas acima. Estruture EXATAMENTE assim:\n\n📊 *RESUMO EXECUTIVO*\n[2-3 linhas com os numeros das metricas fornecidas]\n\n✅ *PONTOS POSITIVOS*\n[O que esta funcionando bem]\n\n⚠️ *PONTOS DE ATENCAO*\n[O que precisa melhorar]\n\n🎯 *DIAGNOSTICO*\n[Analise das metricas acima]\n\n💡 *RECOMENDACOES*\n[3-5 acoes concretas]\n\n📈 *PROXIMOS PASSOS*\n[O que fazer nos proximos 7-14 dias]";
    }

    // ── Histórico de Análises IA ──────────────────────────────────────────────

    public function history(): void {
        requireAuth();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $search  = sanitize($_GET['q']    ?? '');
        $accId   = (int)($_GET['acc']     ?? 0);
        $obj     = sanitize($_GET['obj']  ?? '');

        $where  = "WHERE al.user_id = ?";
        $params = [$uid];

        if ($search) {
            $where  .= " AND (al.campaign_name LIKE ? OR al.account_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($accId) {
            $where  .= " AND al.ad_account_id = ?";
            $params[] = $accId;
        }
        if ($obj) {
            $where  .= " AND al.objective LIKE ?";
            $params[] = "%$obj%";
        }

        $total = (int)$db->query(
            "SELECT COUNT(*) FROM ai_logs al $where", $params
        )->fetchColumn();

        $logs = $db->query(
            "SELECT al.*, aa.account_name AS acc_name
             FROM ai_logs al
             LEFT JOIN ad_accounts aa ON aa.id = al.ad_account_id
             $where
             ORDER BY al.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        $accounts  = $db->query(
            "SELECT DISTINCT ad_account_id, account_name FROM ai_logs WHERE user_id=? ORDER BY account_name",
            [$uid]
        )->fetchAll();

        $totalPages = max(1, (int)ceil($total / $perPage));

        // Carrega clientes e grupos WhatsApp para o select de envio
        $clients  = $db->query("SELECT id, name, phone FROM clients WHERE user_id=? AND status='active' ORDER BY name", [$uid])->fetchAll();
        $instances = $db->query("SELECT id, instance_name, phone_number FROM whatsapp_instances WHERE user_id=? AND status='connected'", [$uid])->fetchAll();
        $wpGroups = []; // carregado lazy via JS para não bloquear page load

        $pageTitle   = 'Histórico de Análises IA';
        $currentPage = 'ai';
        ob_start();
        require_once __DIR__.'/../views/ai/history.php';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

}
