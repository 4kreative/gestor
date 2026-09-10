<?php
// VERSION: CLEAN-20260619-v1
/**
 * AdsAgentController — Agente IA completo para Meta Ads
 * Suporte: OpenAI (GPT) + Anthropic (Claude) com tool use
 */

if (!class_exists('TokenCrypto')) {
    require_once __DIR__.'/../core/TokenCrypto.php';
}

class AdsAgentController {

    public function index(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        self::ensureTables($db);

        $accounts = $db->query(
            "SELECT aa.*, c.name AS client_name
             FROM ad_accounts aa
             LEFT JOIN clients c ON c.id = aa.client_id
             WHERE aa.user_id=? AND aa.status='active' AND aa.platform='meta'
             ORDER BY c.name, aa.account_name",
            [$uid]
        )->fetchAll();

        $aiSettings   = AiController::getAiSettings($uid, $db);
        $hasOpenAI    = !empty(self::getKey($aiSettings, 'openai'));
        $hasAnthropic = !empty(self::getKey($aiSettings, 'anthropic'));

        $recentConvs = $db->query(
            "SELECT c.id, c.title, c.provider, c.model, c.created_at, aa.account_name
             FROM ads_agent_conversations c
             LEFT JOIN ad_accounts aa ON aa.id = c.account_id
             WHERE c.user_id=? ORDER BY c.updated_at DESC LIMIT 15",
            [$uid]
        )->fetchAll();

        $models      = self::getAvailableModels();
        $pageTitle   = 'Agente ADS IA';
        $currentPage = 'ads_agent';
        ob_start();
        require_once __DIR__.'/../views/ads_agent/index.php';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

    public function history(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        self::ensureTables($db);

        $actions = $db->query(
            "SELECT a.*, aa.account_name FROM ads_agent_actions a
             LEFT JOIN ad_accounts aa ON aa.id = a.account_id
             WHERE a.user_id=? ORDER BY a.executed_at DESC LIMIT 100",
            [$uid]
        )->fetchAll();

        $pageTitle   = 'Histórico Agente ADS';
        $currentPage = 'ads_agent';
        ob_start();
        require_once __DIR__.'/../views/ads_agent/history.php';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

    public function saveAnthropicKey(): void {
        ob_start();
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        set_error_handler(function($errno, $errstr) { return true; });
        $key = trim($_POST['anthropic_key'] ?? '');
        if (!$key) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Chave vazia']); return; }
        if (!TokenCrypto::isEncrypted($key)) $key = TokenCrypto::encrypt($key);
        $db->query("INSERT INTO user_ai_settings (user_id,setting_key,setting_value) VALUES(?,?,?)
                    ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                   [$uid, 'ai_anthropic_key', $key]);
        restore_error_handler();
        echo json_encode(['ok'=>true]);
    }

    public function chat(): void {
        ob_start();
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        // Descarta qualquer saída acumulada (redirecionamentos, warnings) antes do JSON
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        set_error_handler(function($errno, $errstr) {
            // Swallow non-fatal PHP warnings/notices so they don't corrupt JSON
            return true;
        });
        try {
            self::ensureTables($db);
        } catch (\Throwable $e) {
            // ensureTables failing is non-fatal — tables may already exist
        }

        $provider  = sanitize($_POST['provider']   ?? 'openai');
        $model     = sanitize($_POST['model']       ?? 'gpt-4o-mini');
        $accountId = (int)($_POST['account_id']    ?? 0);
        $convId    = (int)($_POST['conv_id']       ?? 0);
        $history   = json_decode($_POST['history'] ?? '[]', true) ?: [];
        $message   = trim($_POST['message']        ?? '');

        if (!$message)   { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Mensagem vazia']); return; }
        if (!$accountId) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Selecione uma conta']); return; }

        $aiSettings = AiController::getAiSettings($uid, $db);
        $apiKey     = self::getKey($aiSettings, $provider);
        if (!$apiKey) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>"Chave $provider não configurada."]); return; }

        $acc = $db->query(
            "SELECT aa.*, c.name AS client_name FROM ad_accounts aa
             LEFT JOIN clients c ON c.id=aa.client_id
             WHERE aa.id=? AND aa.user_id=? AND aa.platform='meta'",
            [$accountId, $uid]
        )->fetch();
        if (!$acc) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Conta não encontrada']); return; }
        $acc = decryptTokens($acc);
        if (empty($acc['access_token'])) {
            restore_error_handler();
            echo json_encode(['ok'=>false,'error'=>'Token expirado. Reconecte em Contas de Anúncio.']); return;
        }

        try {
            $result = $provider === 'anthropic'
                ? self::runAnthropicAgent($apiKey, $model, $message, $history, $acc)
                : self::runOpenAIAgent($apiKey, $model, $message, $history, $acc);
        } catch (\Throwable $e) {
            restore_error_handler();
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); return;
        }

        if ($result['ok'] ?? false) {
            $allMsgs = array_merge($history, [
                ['role'=>'user',      'content'=>$message],
                ['role'=>'assistant', 'content'=>$result['reply'] ?? ''],
            ]);
            $title = mb_substr(strip_tags($message), 0, 80);
            if ($convId) {
                $db->query("UPDATE ads_agent_conversations SET messages=?,title=?,updated_at=NOW() WHERE id=? AND user_id=?",
                           [json_encode($allMsgs, JSON_UNESCAPED_UNICODE), $title, $convId, $uid]);
            } else {
                $db->query("INSERT INTO ads_agent_conversations (user_id,account_id,title,provider,model,messages) VALUES(?,?,?,?,?,?)",
                           [$uid, $accountId, $title, $provider, $model, json_encode($allMsgs, JSON_UNESCAPED_UNICODE)]);
                $result['conv_id'] = $db->lastId();
            }
        }
        restore_error_handler();
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            echo json_encode(['ok'=>false,'error'=>'Falha ao serializar resposta: '.json_last_error_msg()]);
        } else {
            echo $json;
        }
    }

    public function loadConv(): void {
        requireAuth();
        $uid    = currentUser()['id'];
        $db     = Database::getInstance();
        $convId = (int)($_GET['id'] ?? 0);
        header('Content-Type: application/json');
        $conv = $db->query("SELECT * FROM ads_agent_conversations WHERE id=? AND user_id=?", [$convId, $uid])->fetch();
        if (!$conv) { echo json_encode(['ok'=>false]); return; }
        echo json_encode(['ok'=>true,'conv'=>$conv,'messages'=>json_decode($conv['messages']??'[]',true)]);
    }

    public function deleteConv(): void {
        requireAuth(); csrfCheck();
        $uid    = currentUser()['id'];
        $db     = Database::getInstance();
        $convId = (int)($_POST['id'] ?? 0);
        header('Content-Type: application/json');
        if (!$convId) { echo json_encode(['ok'=>false,'error'=>'ID inválido']); return; }
        try {
            $db->query("DELETE FROM ads_agent_conversations WHERE id=? AND user_id=?", [$convId, $uid]);
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>'Erro ao excluir']);
        }
    }

    public function execute(): void {
        ob_start();
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        set_error_handler(function($errno, $errstr) { return true; });
        try { self::ensureTables($db); } catch (\Throwable $e) {}

        $accountId = (int)($_POST['account_id'] ?? 0);
        $action    = sanitize($_POST['action']  ?? '');
        $params    = json_decode($_POST['params'] ?? '{}', true) ?: [];
        $convId    = (int)($_POST['conv_id']    ?? 0);

        if (!$accountId || !$action) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Parâmetros inválidos']); return; }

        $acc = $db->query("SELECT * FROM ad_accounts WHERE id=? AND user_id=? AND platform='meta'", [$accountId, $uid])->fetch();
        if (!$acc) { restore_error_handler(); echo json_encode(['ok'=>false,'error'=>'Conta não encontrada']); return; }
        $acc = decryptTokens($acc);

        $logId = null;
        try {
            $db->query("INSERT INTO ads_agent_actions (user_id,account_id,conversation_id,action_type,entity_type,entity_id,entity_name,params_json,status) VALUES(?,?,?,?,?,?,?,?,'pending')",
                       [$uid, $accountId, $convId ?: null, $action, self::getEntityType($action),
                        $params['campaign_id'] ?? $params['adset_id'] ?? $params['ad_id'] ?? '',
                        $params['name'] ?? '', json_encode($params, JSON_UNESCAPED_UNICODE)]);
            $logId = $db->lastId();
        } catch (\Throwable $e) {}

        try {
            $result = self::callMetaApi($action, $params, $acc);
            // Para create_campaign_with_adset: pode ter criado a campanha mas falhado no conjunto
            $isPartial = in_array($action, ['create_campaign_with_adset','create_full_campaign']) && !empty($result['campaign_id']) && !empty($result['error']);
            $status = isset($result['error']) && !$isPartial ? 'error' : 'success';
            $errMsg = $result['error'] ?? null;
            // Normaliza o ID retornado pela Meta para que o frontend sempre use data.id
            if (!isset($result['id'])) {
                $result['id'] = $result['campaign_id']
                             ?? $result['copied_campaign_id']
                             ?? $result['copied_adset_id']
                             ?? $result['copied_ad_id']
                             ?? null;
            }
        } catch (\Throwable $e) {
            $result = ['ok'=>false,'error'=>$e->getMessage()];
            $status = 'error'; $errMsg = $e->getMessage();
        }

        if ($logId) $db->query("UPDATE ads_agent_actions SET status=?,error_msg=?,result_json=? WHERE id=?",
                               [$status, $errMsg, json_encode($result, JSON_UNESCAPED_UNICODE), $logId]);

        $result['ok'] = ($status === 'success');
        restore_error_handler();
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        echo ($json !== false) ? $json : json_encode(['ok'=>false,'error'=>'Falha ao serializar resposta']);
    }

    // ── OpenAI Agent ─────────────────────────────────────────────────────────
    private static function runOpenAIAgent(string $key, string $model, string $msg, array $history, array $acc): array {
        $tools    = self::getOpenAITools();
        $messages = [['role'=>'system','content'=>self::buildSystemPrompt($acc)]];
        foreach ($history as $h) { if (!empty($h['role'])&&!empty($h['content'])) $messages[]=$h; }
        $messages[] = ['role'=>'user','content'=>$msg];

        $iter=0; $toolsExec=[]; $pending=[];
        while ($iter++<12) {
            $resp = self::openaiReq($key,['model'=>$model,'messages'=>$messages,'tools'=>$tools,'tool_choice'=>'auto','max_tokens'=>3000,'temperature'=>0.2]);
            if (!$resp['ok']) return $resp;
            $rmsg   = $resp['data']['choices'][0]['message'] ?? [];
            $finish = $resp['data']['choices'][0]['finish_reason'] ?? '';
            $messages[] = $rmsg;
            if ($finish==='stop'||empty($rmsg['tool_calls'])) {
                return ['ok'=>true,'reply'=>$rmsg['content']??'','tools_executed'=>$toolsExec,'pending_actions'=>$pending];
            }
            foreach ($rmsg['tool_calls'] as $tc) {
                $fname=$tc['function']['name']??''; $fargs=json_decode($tc['function']['arguments']??'{}',true)?:[]; $tcId=$tc['id']??'';
                if (self::isWrite($fname)) { $pending[]=['tool'=>$fname,'params'=>$fargs,'label'=>self::label($fname,$fargs)]; $tr=['status'=>'pending_confirmation']; }
                else { $tr=self::callMetaApi($fname,$fargs,$acc); $toolsExec[]=['tool'=>$fname,'summary'=>self::summary($tr)]; }
                $messages[]=['role'=>'tool','tool_call_id'=>$tcId,'content'=>json_encode($tr,JSON_UNESCAPED_UNICODE)];
            }
            if (!empty($pending)) {
                $r2=self::openaiReq($key,['model'=>$model,'messages'=>$messages,'max_tokens'=>3000,'temperature'=>0.2]);
                return ['ok'=>true,'reply'=>$r2['data']['choices'][0]['message']['content']??'','tools_executed'=>$toolsExec,'pending_actions'=>$pending];
            }
        }
        return ['ok'=>false,'error'=>'Limite de iterações atingido.'];
    }

    private static function openaiReq(string $key, array $body, int $maxRetries = 4): array {
        $attempt = 0;
        $waitMs  = 8000; // começa em 8s (cobre o caso típico de ~6.6s do erro)

        while ($attempt <= $maxRetries) {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$key, 'Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 90,
            ]);
            $resp    = curl_exec($ch);
            $curlErr = curl_error($ch);
            $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false || $resp === '') {
                return ['ok' => false, 'error' => 'OpenAI: Falha de conexão. '.($curlErr ?: 'Resposta vazia')];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['ok' => false, 'error' => 'OpenAI: Resposta inválida (não é JSON). HTTP '.$code];
            }

            // Rate limit (429) → aguarda e tenta novamente com backoff exponencial
            if ($code === 429) {
                $attempt++;
                if ($attempt > $maxRetries) break;

                // Respeita retry_after da OpenAI se disponível, senão usa backoff
                $retryAfter = isset($data['error']['retry_after'])
                    ? (int)($data['error']['retry_after'] * 1000)
                    : $waitMs;

                usleep($retryAfter * 1000); // usleep recebe microsegundos
                $waitMs = min($waitMs * 2, 60000); // dobra o wait, máximo 60s
                continue;
            }

            if ($code !== 200) {
                return ['ok' => false, 'error' => 'OpenAI: '.($data['error']['message'] ?? ($data['error']['type'] ?? "HTTP $code"))];
            }

            return ['ok' => true, 'data' => $data];
        }

        return ['ok' => false, 'error' => 'OpenAI: Limite de taxa (TPM) excedido após '.$maxRetries.' tentativas. Tente novamente em instantes ou troque para GPT-4.1 Mini.'];
    }

    private static function runAnthropicAgent(string $key, string $model, string $msg, array $history, array $acc): array {
        $tools=$tools=self::getAnthropicTools(); $system=self::buildSystemPrompt($acc);
        $messages=[];
        foreach ($history as $h) { if (!empty($h['role'])&&!empty($h['content'])) $messages[]=$h; }
        $messages[]=['role'=>'user','content'=>$msg];

        $iter=0; $toolsExec=[]; $pending=[];
        while ($iter++<12) {
            $resp=self::anthropicReq($key,['model'=>$model,'max_tokens'=>3000,'system'=>$system,'tools'=>$tools,'messages'=>$messages]);
            if (!$resp['ok']) return $resp;
            $data=$resp['data']; $stop=$data['stop_reason']??''; $content=$data['content']??[];
            $messages[]=['role'=>'assistant','content'=>$content];
            $uses=array_filter($content,fn($c)=>$c['type']==='tool_use');
            if ($stop==='end_turn'||empty($uses)) {
                $text=implode('',array_map(fn($c)=>$c['type']==='text'?$c['text']:'',$content));
                return ['ok'=>true,'reply'=>$text,'tools_executed'=>$toolsExec,'pending_actions'=>$pending];
            }
            $results=[];
            foreach ($uses as $b) {
                $fname=$b['name']??''; $fargs=$b['input']??[]; $tId=$b['id']??'';
                if (self::isWrite($fname)) { $pending[]=['tool'=>$fname,'params'=>$fargs,'label'=>self::label($fname,$fargs)]; $tr=['status'=>'pending_confirmation']; }
                else { $tr=self::callMetaApi($fname,$fargs,$acc); $toolsExec[]=['tool'=>$fname,'summary'=>self::summary($tr)]; }
                $results[]=['type'=>'tool_result','tool_use_id'=>$tId,'content'=>json_encode($tr,JSON_UNESCAPED_UNICODE)];
            }
            $messages[]=['role'=>'user','content'=>$results];
            if (!empty($pending)) {
                $r2=self::anthropicReq($key,['model'=>$model,'max_tokens'=>3000,'system'=>$system,'tools'=>$tools,'messages'=>$messages]);
                $t2=implode('',array_map(fn($c)=>$c['type']==='text'?$c['text']??'':'',$r2['data']['content']??[]));
                return ['ok'=>true,'reply'=>$t2,'tools_executed'=>$toolsExec,'pending_actions'=>$pending];
            }
        }
        return ['ok'=>false,'error'=>'Limite de iterações atingido.'];
    }

    private static function anthropicReq(string $key, array $body): array {
        $ch=curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER=>['x-api-key: '.$key,'anthropic-version: 2023-06-01','Content-Type: application/json'],CURLOPT_TIMEOUT=>90]);
        $resp=curl_exec($ch);$curlErr=curl_error($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if ($resp === false || $resp === '') return ['ok'=>false,'error'=>'Anthropic: Falha de conexão. '.($curlErr?:"Resposta vazia")];
        $data=json_decode($resp,true);
        if (json_last_error() !== JSON_ERROR_NONE) return ['ok'=>false,'error'=>'Anthropic: Resposta inválida (não é JSON). HTTP '.$code];
        if($code!==200) return ['ok'=>false,'error'=>'Anthropic: '.($data['error']['message']??"HTTP $code")];
        return ['ok'=>true,'data'=>$data];
    }

    // ── Meta API ──────────────────────────────────────────────────────────────
    // ── Sanitiza posicionamentos para valores válidos da API Meta ────────────
    private static function sanitizePlacements(array $tgt): array {
        $fbMap = [
            'reels'             => 'video_feeds',
            'reel'              => 'video_feeds',
            'videos'            => 'video_feeds',
            'video_feeds'       => 'video_feeds',
            'feed'              => 'feed',
            'story'             => 'story',
            'stories'           => 'story',
            'marketplace'       => 'marketplace',
            'search'            => 'search',
            'right_hand_column' => 'right_hand_column',
            'instream_video'    => 'instream_video',
            'instant_article'   => 'instant_article',
        ];
        $igMap = [
            'feed'          => 'stream',
            'stream'        => 'stream',
            'story'         => 'story',
            'stories'       => 'story',
            'reels'         => 'reels',
            'reel'          => 'reels',
            'explore'       => 'explore',
            'profile_feed'  => 'profile_feed',
            'ig_search'     => 'ig_search',
        ];
        if (!empty($tgt['facebook_positions'])) {
            $mapped = [];
            foreach ($tgt['facebook_positions'] as $p) {
                $key = strtolower(trim($p));
                if (isset($fbMap[$key])) $mapped[] = $fbMap[$key];
            }
            $tgt['facebook_positions'] = array_values(array_unique($mapped));
            if (empty($tgt['facebook_positions'])) unset($tgt['facebook_positions']);
        }
        if (!empty($tgt['instagram_positions'])) {
            $mapped = [];
            foreach ($tgt['instagram_positions'] as $p) {
                $key = strtolower(trim($p));
                if (isset($igMap[$key])) $mapped[] = $igMap[$key];
            }
            $tgt['instagram_positions'] = array_values(array_unique($mapped));
            if (empty($tgt['instagram_positions'])) unset($tgt['instagram_positions']);
        }
        return $tgt;
    }

    private static function callMetaApi(string $tool, array $a, array $acc): array {
        $tk=$acc['access_token']; $act='act_'.$acc['account_id'];
        $b='https://graph.facebook.com/'.META_API_VERSION;

        return match($tool) {
            'get_account_info'     => (function() use($b,$act,$tk) {
                $info = self::mGet("{$b}/{$act}?fields=name,account_status,balance,currency,spend_cap,amount_spent&access_token=".urlencode($tk));
                // Saldo e valores financeiros: API retorna em centavos → converte para reais
                foreach (['balance','spend_cap','amount_spent'] as $f2) {
                    if (isset($info[$f2]) && is_numeric($info[$f2])) {
                        $info[$f2.'_brl'] = round((float)$info[$f2] / 100, 2);
                    }
                }
                // Busca página vinculada e inclui no retorno
                $pageId = self::getPageId($b, $act, $tk);
                $info['page_id_found'] = $pageId ?: null;
                if ($pageId) {
                    $pg = self::mGet("{$b}/{$pageId}?fields=id,name&access_token=".urlencode($tk));
                    $info['page_name'] = $pg['name'] ?? '';
                }
                return $info;
            })(),
            'get_account_balance'  => (function() use($b,$act,$tk) {
                $info = self::mGet("{$b}/{$act}?fields=balance,currency,spend_cap,amount_spent&access_token=".urlencode($tk));
                // Converte centavos → reais
                foreach (['balance','spend_cap','amount_spent'] as $f2) {
                    if (isset($info[$f2]) && is_numeric($info[$f2])) {
                        $info[$f2.'_brl'] = round((float)$info[$f2] / 100, 2);
                    }
                }
                return $info;
            })(),
            'list_campaigns'       => (function() use($b,$act,$tk,$a) {
                $st=$a['status']??'ACTIVE'; $f='id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time,effective_status,created_time,account_id';
                $ef=$st==='ALL'?'ACTIVE","PAUSED","ARCHIVED':$st;
                $res = self::mGet("{$b}/{$act}/campaigns?fields={$f}&effective_status=[\"{$ef}\"]&limit=200&access_token=".urlencode($tk));
                if (!empty($res['data'])) {
                    foreach ($res['data'] as &$camp) {
                        foreach (['daily_budget','lifetime_budget'] as $bf) {
                            if (isset($camp[$bf]) && is_numeric($camp[$bf])) {
                                $camp[$bf.'_brl'] = round((float)$camp[$bf] / 100, 2);
                            }
                        }
                        $campName = $camp['name'] ?? '';
                        $hasBudget = isset($camp['daily_budget']) || (isset($camp['lifetime_budget']) && (float)$camp['lifetime_budget'] > 0);
                        $isBoost = stripos($campName, 'Publicação do Instagram') !== false
                            || stripos($campName, 'Publication from Instagram') !== false
                            || (isset($camp['objective']) && in_array($camp['objective'], ['LINK_CLICKS','POST_ENGAGEMENT']) && !$hasBudget);
                        $camp['_is_boost'] = $isBoost;
                        $camp['_type'] = $isBoost ? 'boost_publicacao' : 'campanha_normal';
                    }
                    unset($camp);
                    if ($st === 'ACTIVE') {
                        $normal = array_values(array_filter($res['data'], fn($c) => !$c['_is_boost']));
                        $boosts = array_values(array_filter($res['data'], fn($c) => $c['_is_boost']));
                        $res['_normal_campaigns'] = $normal;
                        $res['_boost_count'] = count($boosts);
                        $res['data'] = $normal;
                        if (!empty($boosts)) $res['_boost_note'] = count($boosts) . ' publicações impulsionadas ocultas. Peça "mostrar boosts" para vê-las.';
                    }
                }
                return $res;
            })(),
            'get_campaign_detail'  => (function() use($b,$act,$tk,$a) {
                $f='id,name,status,objective,daily_budget,lifetime_budget,budget_remaining,start_time,stop_time,effective_status,bid_strategy';
                $res = self::mGet("{$b}/{$a['campaign_id']}?fields={$f}&access_token=".urlencode($tk));
                // Fallback: se o ID não suportar acesso direto, busca na listagem da conta e filtra
                if (!empty($res['error'])) {
                    $all = self::mGet("{$b}/{$act}/campaigns?fields={$f}&effective_status=[\"ACTIVE\",\"PAUSED\",\"ARCHIVED\"]&limit=200&access_token=".urlencode($tk));
                    if (empty($all['error'])) {
                        foreach ($all['data'] ?? [] as $camp) {
                            if (($camp['id'] ?? '') === $a['campaign_id']) { $res = $camp; break; }
                        }
                    }
                }
                // Converte orçamentos de centavos para reais
                foreach (['daily_budget','lifetime_budget','budget_remaining'] as $bf) {
                    if (isset($res[$bf]) && is_numeric($res[$bf])) {
                        $res[$bf.'_brl'] = round((float)$res[$bf] / 100, 2);
                    }
                }
                return $res;
            })(),
            'list_adsets'          => (function() use($b,$act,$tk,$a) {
                $f='id,name,status,daily_budget,lifetime_budget,optimization_goal,billing_event,targeting,device_platforms,start_time,stop_time,effective_status,destination_type,campaign_id';
                $st=$a['status']??'ACTIVE';
                if (!empty($a['campaign_id'])) {
                    $res = self::mGet("{$b}/{$a['campaign_id']}/adsets?fields={$f}&limit=100&access_token=".urlencode($tk));
                    // Fallback: se o ID não suportar /adsets diretamente, lista pela conta e filtra por campaign_id
                    if (!empty($res['error'])) {
                        $all = self::mGet("{$b}/{$act}/adsets?fields={$f}&effective_status=[\"ACTIVE\",\"PAUSED\"]&limit=200&access_token=".urlencode($tk));
                        if (empty($all['error'])) {
                            $filtered = array_values(array_filter($all['data'] ?? [], fn($x) => ($x['campaign_id'] ?? '') === $a['campaign_id']));
                            $res = ['data' => $filtered];
                        }
                    }
                    return $res;
                }
                return self::mGet("{$b}/{$act}/adsets?fields={$f}&effective_status=[\"{$st}\"]&limit=100&access_token=".urlencode($tk));
            })(),
            'get_adset_detail'     => (function() use($b,$act,$tk,$a) {
                $f='id,name,status,daily_budget,lifetime_budget,optimization_goal,billing_event,targeting,device_platforms,start_time,stop_time,effective_status,destination_type,promoted_object,campaign_id';
                $res = self::mGet("{$b}/{$a['adset_id']}?fields={$f}&access_token=".urlencode($tk));
                if (!empty($res['error'])) {
                    $all = self::mGet("{$b}/{$act}/adsets?fields={$f}&effective_status=[\"ACTIVE\",\"PAUSED\",\"ARCHIVED\"]&limit=200&access_token=".urlencode($tk));
                    if (empty($all['error'])) {
                        foreach ($all['data'] ?? [] as $adset) {
                            if (($adset['id'] ?? '') === $a['adset_id']) { $res = $adset; break; }
                        }
                    }
                }
                foreach (['daily_budget','lifetime_budget'] as $bf) {
                    if (isset($res[$bf]) && is_numeric($res[$bf])) {
                        $res[$bf.'_brl'] = round((float)$res[$bf] / 100, 2);
                    }
                }
                return $res;
            })(),
            'list_ads'             => (function() use($b,$act,$tk,$a) {
                $f='id,name,status,effective_status,adset_id,campaign_id,creative{id,name,body,title,call_to_action_type,thumbnail_url}';
                if (!empty($a['adset_id'])) {
                    $res = self::mGet("{$b}/{$a['adset_id']}/ads?fields={$f}&limit=100&access_token=".urlencode($tk));
                    if (!empty($res['error'])) {
                        $all = self::mGet("{$b}/{$act}/ads?fields={$f}&effective_status=[\"ACTIVE\",\"PAUSED\",\"ARCHIVED\"]&limit=200&access_token=".urlencode($tk));
                        if (empty($all['error'])) {
                            $res = ['data' => array_values(array_filter($all['data'] ?? [], fn($x) => ($x['adset_id'] ?? '') === $a['adset_id']))];
                        }
                    }
                    return $res;
                }
                if (!empty($a['campaign_id'])) {
                    $res = self::mGet("{$b}/{$a['campaign_id']}/ads?fields={$f}&limit=100&access_token=".urlencode($tk));
                    if (!empty($res['error'])) {
                        $all = self::mGet("{$b}/{$act}/ads?fields={$f}&effective_status=[\"ACTIVE\",\"PAUSED\",\"ARCHIVED\"]&limit=200&access_token=".urlencode($tk));
                        if (empty($all['error'])) {
                            $res = ['data' => array_values(array_filter($all['data'] ?? [], fn($x) => ($x['campaign_id'] ?? '') === $a['campaign_id']))];
                        }
                    }
                    return $res;
                }
                $st2=$a['status']??'ACTIVE';
                return self::mGet("{$b}/{$act}/ads?fields={$f}&effective_status=[\"{$st2}\"]&limit=50&access_token=".urlencode($tk));
            })(),
            'get_insights'         => (function() use($b,$act,$tk,$a,$acc) {
                $p = $a['date_preset'] ?? 'last_30d';
                // Converte date_preset para datas reais (igual ReportController)
                $today = date('Y-m-d');
                $presetMap = [
                    'today'      => [$today, $today],
                    'yesterday'  => [date('Y-m-d',strtotime('-1 day')), date('Y-m-d',strtotime('-1 day'))],
                    'last_3d'    => [date('Y-m-d',strtotime('-3 days')), $today],
                    'last_7d'    => [date('Y-m-d',strtotime('-7 days')), $today],
                    'last_14d'   => [date('Y-m-d',strtotime('-14 days')), $today],
                    'last_28d'   => [date('Y-m-d',strtotime('-28 days')), $today],
                    'last_30d'   => [date('Y-m-d',strtotime('-30 days')), $today],
                    'last_90d'   => [date('Y-m-d',strtotime('-90 days')), $today],
                    'this_month' => [date('Y-m-01'), $today],
                    'last_month' => [date('Y-m-01',strtotime('first day of last month')), date('Y-m-t',strtotime('last month'))],
                ];
                // Para maximum com campaign_id: usa start_time real
                if ($p === 'maximum' && !empty($a['campaign_id'])) {
                    $ci = self::mGet("{$b}/{$a['campaign_id']}?fields=start_time&access_token=".urlencode($tk));
                    $since = !empty($ci['start_time']) ? date('Y-m-d', strtotime($ci['start_time'])) : date('Y-m-d', strtotime('-37 months'));
                    $until = $today;
                } else {
                    [$since, $until] = $presetMap[$p] ?? [date('Y-m-d', strtotime('-30 days')), $today];
                }
                // Usa ReportController::fetchMetricsMeta — mesma lógica das outras IAs
                // SEMPRE busca campIds da API — não confiar no campaign_id passado pela IA
                // (IA pode passar IDs inventados ou de outras conversas)
                $campUrl2 = "https://graph.facebook.com/v25.0/act_{$acc['account_id']}/campaigns"
                          . "?fields=id&effective_status=[\"ACTIVE\"]&limit=50"
                          . "&access_token=" . urlencode($acc['access_token']);
                $chC2 = curl_init($campUrl2);
                curl_setopt_array($chC2,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
                $rawC2 = curl_exec($chC2);
                curl_close($chC2);
                $campRes2 = $rawC2 ? json_decode($rawC2, true) : [];
                $allActiveCampIds = array_column($campRes2['data'] ?? [], 'id');

                if (!empty($a['campaign_id'])) {
                    // Valida se o campaign_id passado pela IA existe nos ativos
                    if (in_array($a['campaign_id'], $allActiveCampIds)) {
                        $campIds = [$a['campaign_id']];
                    } else {
                        // ID inválido — usa todos os ativos
                        $campIds = $allActiveCampIds;
                    }
                } else {
                    $campIds = $allActiveCampIds;
                }
                if (empty($campIds)) $campIds = ['__ALL_STATUS__'];
                $metaData = \ReportController::fetchMetricsMeta($acc['account_id'], $acc['access_token'], $since, $until, $campIds);
                // Se today/yesterday vazio, tenta last_7d como fallback
                if ((!$metaData || ($metaData['spend'] ?? 0) == 0) && in_array($p, ['today','yesterday'])) {
                    $metaData = \ReportController::fetchMetricsMeta($acc['account_id'], $acc['access_token'],
                        date('Y-m-d', strtotime('-7 days')), $today, $campIds);
                    if ($metaData && ($metaData['spend'] ?? 0) > 0) {
                        $since = date('Y-m-d', strtotime('-7 days'));
                        $until = $today;
                        $metaData['_period_note'] = 'Dados dos últimos 7 dias (hoje ainda sem dados consolidados pela Meta)';
                    }
                }
                if (!$metaData || ($metaData['spend'] ?? 0) == 0) {
                    $label = match($p) {
                        'today' => 'hoje', 'yesterday' => 'ontem',
                        'maximum' => 'todo o período', default => $p
                    };
                    return ['no_data'=>true,'date_preset'=>$p,'message'=>"Sem dados para '{$label}'. Tente last_7d ou last_30d."];
                }
                // Formata igual ao padrão _metrics que a IA já conhece
                $spend = (float)($metaData['spend'] ?? 0);
                $msgs  = (int)($metaData['msg_all'] ?? $metaData['messages'] ?? 0);
                $profV = (int)($metaData['profile_visit'] ?? $metaData['profile_visits'] ?? 0);
                $waCl  = (int)($metaData['whatsapp_clicks'] ?? 0);
                if (!$msgs && $waCl > 0) $msgs = $waCl;
                $best  = self::bestResult(array_merge($metaData['all_actions'] ?? [], [
                    'messages' => $msgs, 'profile_visits' => $profV,
                    'whatsapp_clicks' => $waCl,
                    'leads' => (int)($metaData['leads'] ?? 0),
                    'purchases' => (int)($metaData['purchase'] ?? $metaData['purchases'] ?? 0),
                    'link_clicks' => (int)($metaData['link_click'] ?? 0),
                    'landing_views' => (int)($metaData['landing_page_view'] ?? 0),
                ]), $spend);
                $best['cost_per_result'] = $best['qty'] > 0 ? round($spend / $best['qty'], 2) : 0;
                $row = [
                    'campaign_id'   => $a['campaign_id'] ?? '',
                    'date_start'    => $since,
                    'date_stop'     => $until,
                    '_metrics' => [
                        'spend'           => $spend,
                        'impressions'     => (int)($metaData['impressions'] ?? 0),
                        'reach'           => (int)($metaData['reach'] ?? 0),
                        'clicks'          => (int)($metaData['clicks'] ?? 0),
                        'ctr'             => (float)($metaData['ctr'] ?? 0),
                        'cpc'             => (float)($metaData['cpc'] ?? 0),
                        'cpm'             => (float)($metaData['cpm'] ?? 0),
                        'frequency'       => (float)($metaData['frequency'] ?? 0),
                        'messages'        => $msgs,
                        'whatsapp_clicks' => $waCl,
                        'profile_visits'  => $profV,
                        'link_clicks'     => (int)($metaData['link_click'] ?? 0),
                        'landing_views'   => (int)($metaData['landing_page_view'] ?? 0),
                        'leads'           => (int)($metaData['leads'] ?? 0),
                        'purchases'       => (int)($metaData['purchase'] ?? $metaData['purchases'] ?? 0),
                        'purchase_value'  => (float)($metaData['revenue'] ?? 0),
                        'video_views'     => (int)($metaData['vplay'] ?? $metaData['video_views'] ?? 0),
                        'post_engagement' => (int)($metaData['engajamento'] ?? 0),
                        'unique_clicks'   => (int)($metaData['unique_clicks'] ?? 0),
                        'cost_per_message'=> $msgs > 0 ? round($spend / $msgs, 2) : 0,
                        'cost_per_lead'   => (int)($metaData['leads']??0) > 0 ? round($spend/(int)$metaData['leads'],2) : 0,
                        'cost_per_result' => $best['cost_per_result'],
                        'roas'            => (float)($metaData['roas'] ?? 0),
                        'daily_budget_brl'=> null,
                    ],
                    '_main_result'    => $best,
                    '_parsed_actions' => $metaData['all_actions'] ?? [],
                ];
                if (!empty($metaData['_period_note'])) $row['_period_note'] = $metaData['_period_note'];
                return ['data' => [$row], 'period' => $p, '_total_rows' => 1,
                        '_since' => $since, '_until' => $until];
            })(),
            'get_insights_by_date' => (function() use($b,$act,$tk,$a) {
                $fBase2 = 'impressions,reach,clicks,spend,ctr,cpc,cpm,frequency,actions,action_values,unique_clicks,instagram_profile_visits,cost_per_action_type';
                $minDate = date('Y-m-d', strtotime('-37 months'));
                $since = $a['since'] ?? date('Y-m-d', strtotime('-30 days'));
                $until = $a['until'] ?? date('Y-m-d');
                if (!empty($a['campaign_id']) && ($since === 'start' || $since === '' || strtotime($since) < strtotime($minDate))) {
                    $campInfo = self::mGet("{$b}/{$a['campaign_id']}?fields=start_time&access_token=".urlencode($tk));
                    $campStart = !empty($campInfo['start_time']) ? date('Y-m-d', strtotime($campInfo['start_time'])) : $minDate;
                    $since = $campStart < $minDate ? $minDate : $campStart;
                }
                if (strtotime($since) < strtotime($minDate)) $since = $minDate;
                $tr = urlencode(json_encode(['since'=>$since,'until'=>$until]));
                if (!empty($a['campaign_id'])) {
                    $filtering = urlencode(json_encode([['field'=>'campaign.id','operator'=>'IN','value'=>[$a['campaign_id']]]]));
                    $raw = self::mGet("{$b}/{$act}/insights?fields={$fBase2}&time_range={$tr}&level=campaign&filtering={$filtering}&limit=10&access_token=".urlencode($tk));
                    if (!empty($raw['error']) || empty($raw['data'])) {
                        $raw2 = self::mGet("{$b}/{$a['campaign_id']}/insights?fields={$fBase2}&time_range={$tr}&access_token=".urlencode($tk));
                        if (empty($raw2['error']) && (isset($raw2['impressions']) || !empty($raw2['data']))) {
                            if (isset($raw2['impressions'])) $raw2 = ['data'=>[$raw2]];
                            $raw = $raw2;
                        }
                    }
                    if (!empty($raw['error']) || empty($raw['data'])) {
                        $raw3 = self::mGet("{$b}/{$a['campaign_id']}/insights?fields={$fBase2}&date_preset=maximum&access_token=".urlencode($tk));
                        if (empty($raw3['error'])) {
                            if (isset($raw3['impressions'])) $raw3 = ['data'=>[$raw3]];
                            if (!empty($raw3['data'])) $raw = $raw3;
                        }
                    }
                    if (!empty($raw['error'])) return $raw;
                } else {
                    $raw = self::mGet("{$b}/{$act}/insights?fields={$fBase2}&time_range={$tr}&level=campaign&limit=100&access_token=".urlencode($tk));
                    if (!empty($raw['error'])) return $raw;
                }
                if (isset($raw['impressions'])) $raw = ['data'=>[$raw]];
                $rows = $raw['data'] ?? [];
                if (empty($rows)) return ['no_data'=>true,'since'=>$since,'until'=>$until,'message'=>'Sem dados para o período.'];
                $parsed2 = [];
                foreach ($rows as $row) {
                    $actMap=[]; $valMap=[];
                    foreach ($row['actions']??[] as $ac) { $actMap[$ac['action_type']]=($actMap[$ac['action_type']]??0)+(float)$ac['value']; }
                    foreach ($row['action_values']??[] as $av) { $valMap[$av['action_type']]=($valMap[$av['action_type']]??0)+(float)$av['value']; }
                    $spend=(float)($row['spend']??0); $impr=(int)($row['impressions']??0);
                    $clks=(int)($row['clicks']??0); $reach=(int)($row['reach']??0);
                    $msgs=(int)($actMap['onsite_conversion.messaging_conversation_started_7d']??$actMap['onsite_conversion.messaging_first_reply']??$actMap['onsite_conversion.total_messaging_connection']??0);
                    $profV=(int)($row['instagram_profile_visits']??$actMap['ig_profile_visit']??$actMap['profile_visit']??0);
                    $wCpa=(int)($actMap['click_to_whatsapp_all']??0);
                    if (!$wCpa && !empty($row['cost_per_action_type'])) {
                        foreach ($row['cost_per_action_type'] as $cpa) {
                            if (($cpa['action_type']??'')==='click_to_whatsapp_all' && (float)($cpa['value']??0)>0) {
                                $wCpa=(int)round($spend/(float)$cpa['value']); break;
                            }
                        }
                    }
                    if (!$msgs && $wCpa>0) $msgs=$wCpa;
                    $best=self::bestResult(array_merge($actMap,['profile_visits'=>$profV,'messages'=>$msgs,'whatsapp_clicks'=>$wCpa]),$spend);
                    $best['cost_per_result']=$best['qty']>0?round($spend/$best['qty'],2):0;
                    $row['_metrics']=['spend'=>$spend,'impressions'=>$impr,'reach'=>$reach,'clicks'=>$clks,
                        'ctr'=>$impr>0?round($clks/$impr*100,2):(float)($row['ctr']??0),
                        'cpc'=>$clks>0?round($spend/$clks,2):(float)($row['cpc']??0),
                        'cpm'=>$impr>0?round($spend/$impr*1000,2):(float)($row['cpm']??0),
                        'frequency'=>($impr>0&&$reach>0)?round($impr/$reach,2):(float)($row['frequency']??0),
                        'messages'=>$msgs,'profile_visits'=>$profV,'whatsapp_clicks'=>$wCpa,
                        'link_clicks'=>(int)($actMap['link_click']??0),
                        'landing_views'=>(int)($actMap['landing_page_view']??0),
                        'leads'=>(int)($actMap['lead']??0),'purchases'=>(int)($actMap['purchase']??0),
                        'post_engagement'=>(int)($actMap['post_engagement']??$actMap['page_engagement']??0),
                        'cost_per_message'=>$msgs>0?round($spend/$msgs,2):0,
                        'cost_per_result'=>$best['cost_per_result'],
                    ];
                    $row['_main_result']=$best; $row['_since']=$since; $row['_until']=$until;
                    unset($row['actions'],$row['action_values'],$row['cost_per_action_type']);
                    $parsed2[]=$row;
                }
                return ['data'=>$parsed2,'_since'=>$since,'_until'=>$until,'_total_rows'=>count($parsed2)];
            })(),
            'get_insights_breakdown'=> (function() use($b,$act,$tk,$a) {
                $bd=$a['breakdown']??'age,gender'; $p=$a['date_preset']??'last_30d';
                $fl=!empty($a['campaign_id'])?'&filtering='.urlencode(json_encode([['field'=>'campaign.id','operator'=>'IN','value'=>[$a['campaign_id']]]])) : '';
                return self::mGet("{$b}/{$act}/insights?fields=impressions,reach,clicks,spend,ctr,cpc,actions&breakdowns={$bd}&date_preset={$p}{$fl}&access_token=".urlencode($tk));
            })(),
            'compare_campaigns'    => (function() use($b,$act,$tk,$a) {
                $p=$a['date_preset']??'last_30d'; $out=[];
                foreach(array_slice($a['campaign_ids']??[],0,8) as $cid) {
                    $camp = self::mGet("{$b}/{$cid}?fields=name,status,objective,daily_budget&access_token=".urlencode($tk));
                    $ins  = self::mGet("{$b}/{$cid}/insights?fields=campaign_name,impressions,reach,spend,ctr,cpc,cpm,frequency,actions&date_preset={$p}&access_token=".urlencode($tk));
                    // Fallback: se algum falhar, busca pela conta com filtering
                    if (!empty($camp['error']) || !empty($ins['error'])) {
                        $filtering = urlencode(json_encode([['field'=>'campaign.id','operator'=>'IN','value'=>[$cid]]]));
                        if (!empty($camp['error'])) {
                            $all = self::mGet("{$b}/{$act}/campaigns?fields=id,name,status,objective,daily_budget&effective_status=[\"ACTIVE\",\"PAUSED\",\"ARCHIVED\"]&limit=200&access_token=".urlencode($tk));
                            foreach ($all['data'] ?? [] as $c) { if (($c['id'] ?? '') === $cid) { $camp = $c; break; } }
                        }
                        if (!empty($ins['error'])) {
                            $ins = self::mGet("{$b}/{$act}/insights?fields=campaign_name,impressions,reach,spend,ctr,cpc,cpm,frequency,actions&date_preset={$p}&level=campaign&filtering={$filtering}&limit=10&access_token=".urlencode($tk));
                            if (!empty($ins['data'][0])) $ins = $ins['data'][0];
                        }
                    }
                    $out[]=['campaign'=>$camp, 'insights'=>$ins];
                }
                return ['comparisons'=>$out];
            })(),
            'find_underperforming' => (function() use($b,$act,$tk,$a) {
                $lv=$a['level']??'campaign'; $p=$a['date_preset']??'last_7d';
                $ef=$lv==='ad'?'ad_id,ad_name':($lv==='adset'?'adset_id,adset_name':'campaign_id,campaign_name');
                $raw=self::mGet("{$b}/{$act}/insights?fields=impressions,spend,ctr,cpc,cpm,frequency,actions,{$ef}&date_preset={$p}&level={$lv}&limit=100&access_token=".urlencode($tk));
                $bad=[];
                foreach($raw['data']??[] as $row) {
                    $bad_flag=false;
                    if(isset($a['max_cpc'])&&(float)($row['cpc']??0)>(float)$a['max_cpc']) $bad_flag=true;
                    if(isset($a['min_ctr'])&&(float)($row['ctr']??0)<(float)$a['min_ctr']) $bad_flag=true;
                    if(isset($a['max_cpm'])&&(float)($row['cpm']??0)>(float)$a['max_cpm']) $bad_flag=true;
                    if($bad_flag) $bad[]=$row;
                }
                return ['underperforming'=>$bad,'total_analyzed'=>count($raw['data']??[]),'criteria'=>$a];
            })(),
            'list_audiences'       => self::mGet("{$b}/{$act}/customaudiences?fields=id,name,subtype,approximate_count_lower_bound,approximate_count_upper_bound&limit=100&access_token=".urlencode($tk)),
            'list_pixels'          => self::mGet("{$b}/{$act}/adspixels?fields=id,name,last_fired_time&limit=50&access_token=".urlencode($tk)),
            'list_creatives'       => self::mGet("{$b}/{$act}/adcreatives?fields=id,name,body,title,call_to_action_type,thumbnail_url&limit=100&access_token=".urlencode($tk)),
            'search_interests'     => self::mGet("{$b}/search?type=adinterest&q=".urlencode($a['query']??'')."&access_token=".urlencode($tk)),

            // Escrita
            'update_campaign_status' => (function() use($b,$tk,$a) {
                $s=strtoupper($a['status']??''); if(!in_array($s,['ACTIVE','PAUSED'])) return ['error'=>'Status inválido'];
                return self::mPost("{$b}/{$a['campaign_id']}?access_token=".urlencode($tk),['status'=>$s]);
            })(),
            'update_campaign_budget' => (function() use($b,$tk,$a) {
                $v=(int)(($a['amount']??0)*100); if($v<=0) return ['error'=>'Valor inválido'];
                return self::mPost("{$b}/{$a['campaign_id']}?access_token=".urlencode($tk),[($a['budget_type']??'daily')==='lifetime'?'lifetime_budget':'daily_budget'=>$v]);
            })(),
            'update_adset_status'  => (function() use($b,$tk,$a) {
                $s=strtoupper($a['status']??''); if(!in_array($s,['ACTIVE','PAUSED'])) return ['error'=>'Status inválido'];
                return self::mPost("{$b}/{$a['adset_id']}?access_token=".urlencode($tk),['status'=>$s]);
            })(),
            'update_adset_budget'  => (function() use($b,$tk,$a) {
                $v=(int)(($a['amount']??0)*100); if($v<=0) return ['error'=>'Valor inválido'];
                return self::mPost("{$b}/{$a['adset_id']}?access_token=".urlencode($tk),[($a['budget_type']??'daily')==='lifetime'?'lifetime_budget':'daily_budget'=>$v]);
            })(),
            'update_ad_status'     => (function() use($b,$tk,$a) {
                $s=strtoupper($a['status']??''); if(!in_array($s,['ACTIVE','PAUSED'])) return ['error'=>'Status inválido'];
                return self::mPost("{$b}/{$a['ad_id']}?access_token=".urlencode($tk),['status'=>$s]);
            })(),
            'bulk_update_status'   => (function() use($b,$tk,$a) {
                $s=strtoupper($a['status']??'PAUSED'); $out=[];
                foreach(array_slice($a['ids']??[],0,20) as $id) $out[$id]=self::mPost("{$b}/{$id}?access_token=".urlencode($tk),['status'=>$s]);
                return ['bulk_results'=>$out];
            })(),
            'duplicate_campaign'   => (function() use($b,$act,$tk,$a) {
                // Duplica campanha completa incluindo conjuntos e anúncios (deep copy)
                // account_id sem prefixo "act_" conforme exigido pela API Meta
                $body = [
                    'status_option'    => 'PAUSED',
                    'account_id'       => str_replace('act_', '', $act),
                    'deep_copy'        => true,   // copia adsets + ads juntos
                ];
                // Passa novo nome se fornecido
                if (!empty($a['name'])) $body['name'] = $a['name'];
                $result = self::mPost("{$b}/{$a['campaign_id']}/copies?access_token=".urlencode($tk), $body, true);

                // Se deep_copy não for suportado pela versão da API, refaz sem ele
                if (!empty($result['error'])) {
                    unset($body['deep_copy']);
                    $result = self::mPost("{$b}/{$a['campaign_id']}/copies?access_token=".urlencode($tk), $body, true);
                }
                return $result;
            })(),
            'duplicate_adset'      => (function() use($b,$act,$tk,$a) {
                // A API do Meta exige campaign_id no body ao copiar um adset
                $campaignId = $a['campaign_id'] ?? '';
                if (!$campaignId) {
                    // Busca o campaign_id do adset via API
                    $detail = self::mGet("{$b}/{$a['adset_id']}?fields=campaign_id&access_token=".urlencode($tk));
                    $campaignId = $detail['campaign_id'] ?? '';
                }
                $body = ['status_option' => 'PAUSED'];
                if ($campaignId) $body['campaign_id'] = $campaignId;
                return self::mPost("{$b}/{$a['adset_id']}/copies?access_token=".urlencode($tk), $body, true);
            })(),
            'create_campaign'      => (function() use($b,$act,$tk,$a) {
                // Mapeia objetivos amigáveis para os valores corretos da API Meta
                $objMap = [
                    'MESSAGES'            => 'OUTCOME_ENGAGEMENT',
                    'WHATSAPP'            => 'OUTCOME_ENGAGEMENT',
                    'MENSAGENS'           => 'OUTCOME_ENGAGEMENT',
                    'OUTCOME_MESSAGES'    => 'OUTCOME_ENGAGEMENT',
                    'LEADS'               => 'OUTCOME_LEADS',
                    'TRAFFIC'             => 'OUTCOME_TRAFFIC',
                    'SALES'               => 'OUTCOME_SALES',
                    'CONVERSIONS'         => 'OUTCOME_SALES',
                    'ENGAGEMENT'          => 'OUTCOME_ENGAGEMENT',
                    'AWARENESS'           => 'OUTCOME_AWARENESS',
                    'APP_PROMOTION'       => 'OUTCOME_APP_PROMOTION',
                ];
                $rawObj = strtoupper($a['objective'] ?? 'OUTCOME_TRAFFIC');
                $objective = $objMap[$rawObj] ?? $rawObj;
                $body = [
                    'name'                             => $a['name'] ?? '',
                    'objective'                        => $objective,
                    'status'                           => 'PAUSED',
                    'special_ad_categories'            => $a['special_ad_categories'] ?? [],
                    'is_adset_budget_sharing_enabled'  => false, // orçamento gerenciado no conjunto
                ];
                if (isset($a['daily_budget'])) $body['daily_budget'] = (int)($a['daily_budget'] * 100);
                // bid_strategy na campanha: bloquear para OUTCOME_ENGAGEMENT (WhatsApp/Mensagens)
                // Meta rejeita bid_strategy em campanhas OUTCOME_ENGAGEMENT sem bid_amount no conjunto
                $campBidStrat = strtoupper($a['bid_strategy'] ?? '');
                $engagementObjectives = ['OUTCOME_ENGAGEMENT','OUTCOME_AWARENESS'];
                if ($campBidStrat && !in_array($objective, $engagementObjectives)) {
                    $body['bid_strategy'] = $campBidStrat;
                }
                return self::mPost("{$b}/{$act}/campaigns?access_token=".urlencode($tk), $body, true);
            })(),
            'create_adset'         => (function() use($b,$act,$tk,$a) {
                $tgt=[];
                if(!empty($a['age_min'])) $tgt['age_min']=(int)$a['age_min'];
                if(!empty($a['age_max'])) $tgt['age_max']=(int)$a['age_max'];
                if(!empty($a['genders'])) $tgt['genders']=$a['genders'];
                if(!empty($a['countries'])) $tgt['geo_locations']['countries']=$a['countries'];
                if(!empty($a['cities']))    $tgt['geo_locations']['cities']=$a['cities'];
                if(!empty($a['interests'])) $tgt['flexible_spec']=[['interests'=>$a['interests']]];
                if(!empty($a['custom_audiences'])) $tgt['custom_audiences']=array_map(fn($id)=>['id'=>$id],$a['custom_audiences']);
                if(!empty($a['excluded_audiences'])) $tgt['exclusions']['custom_audiences']=array_map(fn($id)=>['id'=>$id],$a['excluded_audiences']);
                if(!empty($a['publisher_platforms'])) $tgt['publisher_platforms']=$a['publisher_platforms'];
                if(!empty($a['device_platforms']))    $tgt['device_platforms']=$a['device_platforms'];
                $tgt = self::sanitizePlacements($tgt);

                // Normaliza destination_type - Meta API aceita WHATSAPP (nao WHATSAPP_BUSINESS)
                $destType = strtoupper($a['destination_type'] ?? '');
                if ($destType === 'WHATSAPP_BUSINESS') $destType = 'WHATSAPP';

                // Para WhatsApp: ajusta publisher_platforms, optimization_goal e billing_event
                $isWhatsApp = in_array($destType, ['WHATSAPP']);
                if ($isWhatsApp && empty($tgt['publisher_platforms'])) {
                    $tgt['publisher_platforms'] = ['facebook']; // WhatsApp só roda via Facebook
                }

                $optimizationGoal = strtoupper($a['optimization_goal'] ?? ($isWhatsApp ? 'CONVERSATIONS' : 'REACH'));
                // Mapeia goals amigáveis
                $goalMap = [
                    'MESSAGES'      => 'CONVERSATIONS',
                    'MENSAGENS'     => 'CONVERSATIONS',
                    'WHATSAPP'      => 'CONVERSATIONS',
                    'CONVERSATIONS' => 'CONVERSATIONS',
                ];
                $optimizationGoal = $goalMap[$optimizationGoal] ?? $optimizationGoal;

                $billingEvent = strtoupper($a['billing_event'] ?? 'IMPRESSIONS');

                $body=[
                    'name'             => $a['name'] ?? '',
                    'campaign_id'      => $a['campaign_id'] ?? '',
                    'daily_budget'     => isset($a['daily_budget']) ? (int)($a['daily_budget']*100) : 5000,
                    'optimization_goal'=> $optimizationGoal,
                    'billing_event'    => $billingEvent,
                    'status'           => 'PAUSED',
                    'targeting'        => empty($tgt) ? ['geo_locations'=>['countries'=>['BR']]] : $tgt,
                ];
                if(!empty($a['start_time'])) $body['start_time']=$a['start_time'];
                if(!empty($a['end_time']))   $body['end_time']=$a['end_time'];
                if(!empty($destType))        $body['destination_type']=$destType;
                // bid_strategy: NÃO enviar quando objetivo é CONVERSATIONS/mensagens/engajamento
                // Meta rejeita bid_strategy sem bid_amount; nesses objetivos usa LOWEST_COST automático
                $bidStrat = strtoupper($a['bid_strategy'] ?? '');
                $bidAmt   = isset($a['bid_amount']) ? (int)($a['bid_amount'] * 100) : 0;
                $needsBid = in_array($bidStrat, ['COST_CAP','LOWEST_COST_WITH_BID_CAP','TARGET_COST','MINIMUM_ROAS']);
                $safeBidGoals = ['CONVERSATIONS','REPLIES','POST_ENGAGEMENT','REACH','IMPRESSIONS','QUALITY_CALL','LEAD_GENERATION'];
                $goalIncompatible = in_array($optimizationGoal, $safeBidGoals);
                if ($bidStrat && !$goalIncompatible && !$needsBid) {
                    $body['bid_strategy'] = $bidStrat;
                }
                if ($bidStrat && !$goalIncompatible && $needsBid && $bidAmt > 0) {
                    $body['bid_strategy'] = $bidStrat;
                    $body['bid_amount']   = $bidAmt;
                }
                // Se needsBid mas sem bid_amount: omite bid_strategy (evita erro da API)

                // promoted_object obrigatório para WhatsApp — inclui whatsapp_phone_number (exigido pela API v21+)
                $safePageId = $a['page_id'] ?? '';
                $safeAccId  = str_replace('act_', '', $act);
                if ($safePageId === $safeAccId || $safePageId === 'act_'.$safeAccId) $safePageId = '';
                if (empty($safePageId) && ($isWhatsApp || $optimizationGoal === 'CONVERSATIONS')) {
                    $safePageId = self::getPageId($b, $act, $tk);
                }
                if (!empty($safePageId)) {
                    // click-to-WhatsApp via wa.me: apenas page_id (sem whatsapp_phone_number = evita erro WABA)
                    $body['promoted_object'] = ['page_id' => $safePageId];
                }

                return self::mPost("{$b}/{$act}/adsets?access_token=".urlencode($tk),$body, true);
            })(),
            'create_campaign_with_adset' => (function() use($b,$act,$tk,$a) {
                // Cria campanha + conjunto em uma única operação atômica
                $objMap = [
                    'MESSAGES'=>'OUTCOME_ENGAGEMENT','WHATSAPP'=>'OUTCOME_ENGAGEMENT',
                    'MENSAGENS'=>'OUTCOME_ENGAGEMENT','OUTCOME_MESSAGES'=>'OUTCOME_ENGAGEMENT',
                    'LEADS'=>'OUTCOME_LEADS','TRAFFIC'=>'OUTCOME_TRAFFIC',
                    'SALES'=>'OUTCOME_SALES','CONVERSIONS'=>'OUTCOME_SALES',
                    'ENGAGEMENT'=>'OUTCOME_ENGAGEMENT','AWARENESS'=>'OUTCOME_AWARENESS',
                    'APP_PROMOTION'=>'OUTCOME_APP_PROMOTION',
                ];
                $rawObj    = strtoupper($a['objective'] ?? 'OUTCOME_ENGAGEMENT');
                $objective = $objMap[$rawObj] ?? $rawObj;
                $campBody  = [
                    'name'                             => $a['campaign_name'] ?? ($a['name'] ?? ''),
                    'objective'                        => $objective,
                    'status'                           => 'PAUSED',
                    'special_ad_categories'            => [],
                    'is_adset_budget_sharing_enabled'  => false,
                ];
                $campResult = self::mPost("{$b}/{$act}/campaigns?access_token=".urlencode($tk), $campBody, true);
                if (!empty($campResult['error'])) {
                    return ['error'=>'Erro ao criar campanha: '.$campResult['error'], 'step'=>'campaign'];
                }
                $campaignId = $campResult['id'] ?? null;
                if (!$campaignId) return ['error'=>'Campanha criada mas sem ID retornado'];

                $tgt = [];
                if (!empty($a['age_min']))  $tgt['age_min']  = (int)$a['age_min'];
                if (!empty($a['age_max']))  $tgt['age_max']  = (int)$a['age_max'];
                if (!empty($a['genders']))  $tgt['genders']  = $a['genders'];
                if (!empty($a['countries'])) $tgt['geo_locations']['countries'] = $a['countries'];
                if (!empty($a['cities']))    $tgt['geo_locations']['cities']    = $a['cities'];
                if (!empty($a['publisher_platforms'])) $tgt['publisher_platforms'] = $a['publisher_platforms'];
                if (!empty($a['device_platforms']))    $tgt['device_platforms']    = $a['device_platforms'];
                $tgt = self::sanitizePlacements($tgt);

                $destType   = strtoupper($a['destination_type'] ?? '');
                if ($destType === 'WHATSAPP_BUSINESS') $destType = 'WHATSAPP'; // Meta API usa WHATSAPP
                $isWhatsApp = ($destType === 'WHATSAPP');
                if ($isWhatsApp && empty($tgt['publisher_platforms'])) {
                    $tgt['publisher_platforms'] = ['facebook'];
                }

                $goalMap = ['MESSAGES'=>'CONVERSATIONS','MENSAGENS'=>'CONVERSATIONS','WHATSAPP'=>'CONVERSATIONS'];
                $optGoal = strtoupper($a['optimization_goal'] ?? ($isWhatsApp ? 'CONVERSATIONS' : 'LINK_CLICKS'));
                $optGoal = $goalMap[$optGoal] ?? $optGoal;

                $adsetBody = [
                    'name'              => $a['adset_name'] ?? (($a['campaign_name'] ?? ($a['name'] ?? '')).' - Conjunto 1'),
                    'campaign_id'       => $campaignId,
                    'daily_budget'      => isset($a['daily_budget']) ? (int)($a['daily_budget']*100) : 1200,
                    'optimization_goal' => $optGoal,
                    'billing_event'     => 'IMPRESSIONS',
                    'status'            => 'PAUSED',
                    'targeting'         => empty($tgt) ? ['geo_locations'=>['countries'=>['BR']]] : $tgt,
                ];
                if (!empty($destType))     $adsetBody['destination_type'] = $destType;
                // bid_strategy: NÃO enviar quando objetivo é CONVERSATIONS/mensagens/engajamento
                $bidStrat2 = strtoupper($a['bid_strategy'] ?? '');
                $bidAmt2   = isset($a['bid_amount']) ? (int)($a['bid_amount'] * 100) : 0;
                $needsBid2 = in_array($bidStrat2, ['COST_CAP','LOWEST_COST_WITH_BID_CAP','TARGET_COST','MINIMUM_ROAS']);
                $safeBidGoals2 = ['CONVERSATIONS','REPLIES','POST_ENGAGEMENT','REACH','IMPRESSIONS','QUALITY_CALL','LEAD_GENERATION'];
                $goalIncompatible2 = in_array($optGoal, $safeBidGoals2);
                if ($bidStrat2 && !$goalIncompatible2 && !$needsBid2) {
                    $adsetBody['bid_strategy'] = $bidStrat2;
                }
                if ($bidStrat2 && !$goalIncompatible2 && $needsBid2 && $bidAmt2 > 0) {
                    $adsetBody['bid_strategy'] = $bidStrat2;
                    $adsetBody['bid_amount']   = $bidAmt2;
                }
                $safePageId2 = $a['page_id'] ?? '';
                $safeAccId2  = str_replace('act_', '', $act);
                if ($safePageId2 === $safeAccId2 || $safePageId2 === 'act_'.$safeAccId2) $safePageId2 = '';
                if (empty($safePageId2) && ($isWhatsApp || $optGoal === 'CONVERSATIONS')) {
                    $safePageId2 = self::getPageId($b, $act, $tk);
                }
                if (!empty($safePageId2)) {
                    // click-to-WhatsApp via wa.me: apenas page_id (sem whatsapp_phone_number = evita erro WABA)
                    $adsetBody['promoted_object'] = ['page_id' => $safePageId2];
                }

                $adsetResult = self::mPost("{$b}/{$act}/adsets?access_token=".urlencode($tk), $adsetBody, true);
                $adsetErr    = $adsetResult['error'] ?? null;

                return [
                    'success'     => true,
                    'id'          => $campaignId,
                    'campaign_id' => $campaignId,
                    'adset_id'    => $adsetResult['id'] ?? null,
                    'campaign'    => $campResult,
                    'adset'       => $adsetResult,
                    'error'       => $adsetErr ? 'Campanha criada (ID:'.$campaignId.') porém erro no conjunto: '.$adsetErr : null,
                ];
            })(),
            'upload_image'         => (function() use($b,$act,$tk,$a) {
                // Faz upload de imagem por URL pública para a conta de anúncios
                $body   = ['url' => $a['image_url'] ?? ''];
                $result = self::mPost("{$b}/{$act}/adimages?access_token=".urlencode($tk), $body);
                $images = $result['images'] ?? [];
                $first  = !empty($images) ? reset($images) : [];
                $hash   = $first['hash'] ?? null;
                if (!$hash) return ['error' => 'Upload falhou. URL inacessível ou inválida.', 'raw' => $result];
                return ['success' => true, 'image_hash' => $hash, 'image_url' => $first['url'] ?? ''];
            })(),

            'create_adcreative'    => (function() use($b,$act,$tk,$a) {
                $pageId      = $a['page_id']      ?? '';
                $imageHash   = $a['image_hash']   ?? '';
                $videoId     = $a['video_id']      ?? '';
                $message     = $a['message']       ?? '';
                $title       = $a['title']          ?? '';
                $description = $a['description']   ?? '';
                $linkUrl     = $a['link_url']       ?? '';
                $ctaType     = strtoupper($a['cta_type'] ?? 'LEARN_MORE');
                $destType    = strtoupper($a['destination_type'] ?? 'WEBSITE');
                $igUserId    = $a['instagram_user_id'] ?? '';

                if (!empty($videoId)) {
                    $storySpec = [
                        'page_id'    => $pageId,
                        'video_data' => [
                            'video_id'       => $videoId,
                            'message'        => $message,
                            'title'          => $title,
                            'call_to_action' => self::buildCTA($ctaType, $destType, $linkUrl, $a),
                        ],
                    ];
                } else {
                    $linkData = [
                        'message'        => $message,
                        'name'           => $title,
                        'description'    => $description,
                        'call_to_action' => self::buildCTA($ctaType, $destType, $linkUrl, $a),
                    ];
                    if (!empty($imageHash)) $linkData['image_hash'] = $imageHash;
                    if (!empty($linkUrl))   $linkData['link']       = $linkUrl;
                    if ($destType === 'WHATSAPP_BUSINESS' || $destType === 'WHATSAPP') {
                        $wNum = preg_replace('/\D/', '', $a['whatsapp_number'] ?? '');
                        if ($wNum) {
                            $linkData['link']                 = 'https://wa.me/'.$wNum;
                            $linkData['page_welcome_message'] = $a['welcome_message'] ?? 'Olá! Gostaria de mais informações.';
                        }
                    }
                    $storySpec = ['page_id' => $pageId, 'link_data' => $linkData];
                    if (empty($pageId)) unset($storySpec['page_id']); // API aceita sem page_id em alguns objetivos
                    if (!empty($igUserId)) $storySpec['instagram_user_id'] = $igUserId;
                }

                $body = [
                    'name'              => $a['name'] ?? ($title ?: 'Criativo '.date('d/m/Y H:i')),
                    'object_story_spec' => $storySpec,
                ];
                return self::mPost("{$b}/{$act}/adcreatives?access_token=".urlencode($tk), $body, true);
            })(),

            'create_ad'            => (function() use($b,$act,$tk,$a) {
                $body = [
                    'name'     => $a['name']      ?? 'Anúncio '.date('d/m/Y H:i'),
                    'adset_id' => $a['adset_id']  ?? '',
                    'creative' => ['creative_id'  => $a['creative_id'] ?? ''],
                    'status'   => 'PAUSED',
                ];
                return self::mPost("{$b}/{$act}/ads?access_token=".urlencode($tk), $body, true);
            })(),

            'create_full_campaign' => (function() use($b,$act,$tk,$a) {
                // ⭐ Cria TUDO: campanha + conjunto + criativo + anúncio (todos PAUSADOS)
                $rawDest  = strtoupper($a['destination_type'] ?? 'WEBSITE');
                $isWppDest = in_array($rawDest, ['WHATSAPP', 'WHATSAPP_BUSINESS']);

                // WhatsApp via wa.me: usa OUTCOME_TRAFFIC + LINK_CLICKS + destination_type=WEBSITE
                // Meta rejeita OUTCOME_ENGAGEMENT+CONVERSATIONS sem WABA registrado na maioria das contas
                // O link wa.me vai no criativo — funciona em qualquer conta sem WABA
                if ($isWppDest) {
                    $destType  = 'WEBSITE';
                    $objective = 'OUTCOME_TRAFFIC';
                    $optGoal   = 'LINK_CLICKS';
                    $ctaType   = 'WHATSAPP_MESSAGE';
                } else {
                    $destType = $rawDest;
                    $objMap = [
                        'MESSAGES'=>'OUTCOME_ENGAGEMENT','MENSAGENS'=>'OUTCOME_ENGAGEMENT',
                        'OUTCOME_MESSAGES'=>'OUTCOME_ENGAGEMENT',
                        'LEADS'=>'OUTCOME_LEADS','TRAFFIC'=>'OUTCOME_TRAFFIC',
                        'SALES'=>'OUTCOME_SALES','CONVERSIONS'=>'OUTCOME_SALES',
                        'ENGAGEMENT'=>'OUTCOME_ENGAGEMENT','AWARENESS'=>'OUTCOME_AWARENESS',
                        'APP_PROMOTION'=>'OUTCOME_APP_PROMOTION',
                        'PROFILE_VISIT'=>'OUTCOME_ENGAGEMENT','INSTAGRAM_PROFILE'=>'OUTCOME_ENGAGEMENT',
                    ];
                    $objective = $objMap[strtoupper($a['objective']??'')] ?? strtoupper($a['objective']??'OUTCOME_TRAFFIC');

                    $rawGoal    = strtoupper($a['optimization_goal'] ?? '');
                    $optGoalMap = [
                        'MESSAGES'=>'CONVERSATIONS','MENSAGENS'=>'CONVERSATIONS',
                        'WHATSAPP'=>'CONVERSATIONS','LINK_CLICKS'=>'LINK_CLICKS',
                        'LANDING_PAGE_VIEWS'=>'LANDING_PAGE_VIEWS','LEAD_GENERATION'=>'LEAD_GENERATION',
                        'CONVERSIONS'=>'OFFSITE_CONVERSIONS','REACH'=>'REACH',
                        'POST_ENGAGEMENT'=>'POST_ENGAGEMENT','REPLIES'=>'REPLIES',
                        'CONVERSATIONS'=>'CONVERSATIONS','OFFSITE_CONVERSIONS'=>'OFFSITE_CONVERSIONS',
                    ];
                    $goalDefault = ['WEBSITE'=>'LINK_CLICKS','MESSENGER'=>'REPLIES',
                        'INSTAGRAM_DIRECT'=>'REPLIES','INSTAGRAM_PROFILE'=>'POST_ENGAGEMENT'];
                    $optGoal  = $rawGoal ? ($optGoalMap[$rawGoal] ?? $rawGoal) : ($goalDefault[$destType] ?? 'LINK_CLICKS');

                    $ctaMap  = ['MESSENGER'=>'MESSAGE_PAGE','INSTAGRAM_DIRECT'=>'INSTAGRAM_MESSAGE',
                        'INSTAGRAM_PROFILE'=>'INSTAGRAM_MESSAGE'];
                    $ctaType = strtoupper($a['cta_type'] ?? ($ctaMap[$destType] ?? 'LEARN_MORE'));
                }

                // ── 1. Campanha ───────────────────────────────────────────
                $campResult = self::mPost("{$b}/{$act}/campaigns?access_token=".urlencode($tk), [
                    'name'                             => $a['campaign_name'] ?? ($a['name'] ?? ''),
                    'objective'                        => $objective,
                    'status'                           => 'PAUSED',
                    'special_ad_categories'            => [],
                    'is_adset_budget_sharing_enabled'  => false,
                ], true);
                if (!empty($campResult['error'])) return ['error'=>'[Campanha] '.$campResult['error'],'step'=>'campaign'];
                $campaignId = $campResult['id'] ?? null;
                if (!$campaignId) return ['error'=>'Campanha criada sem ID'];

                // ── 2. Conjunto ───────────────────────────────────────────
                $tgt = [];
                if (!empty($a['age_min']))    $tgt['age_min']  = (int)$a['age_min'];
                if (!empty($a['age_max']))    $tgt['age_max']  = (int)$a['age_max'];
                if (!empty($a['genders']))    $tgt['genders']  = $a['genders'];
                if (!empty($a['countries'])) $tgt['geo_locations']['countries'] = $a['countries'];
                if (!empty($a['cities']))     $tgt['geo_locations']['cities']   = $a['cities'];
                if (!empty($a['interests']))  $tgt['flexible_spec'] = [['interests'=>$a['interests']]];
                if (!empty($a['custom_audiences'])) $tgt['custom_audiences'] = array_map(fn($id)=>['id'=>$id],$a['custom_audiences']);
                $isWpp = $isWppDest; // definido antes da conversão de destType
                $tgt['publisher_platforms'] = $a['publisher_platforms'] ?? ($isWpp ? ['facebook'] : ['facebook','instagram']);
                if (!empty($a['facebook_positions']))  $tgt['facebook_positions']  = $a['facebook_positions'];
                if (!empty($a['instagram_positions'])) $tgt['instagram_positions'] = $a['instagram_positions'];
                $tgt = self::sanitizePlacements($tgt);

                $pageId = $a['page_id'] ?? '';
                // Valida page_id: ignora se for igual ao account_id (erro comum do agente)
                $rawAccId = $acc['account_id'] ?? str_replace('act_', '', $act);
                if ($pageId && ($pageId === $rawAccId || $pageId === 'act_'.$rawAccId)) {
                    $pageId = ''; // inválido — busca via API
                }
                if (!$pageId) {
                    $pageId = self::getPageId($b, $act, $tk);
                }

                $adsetBody = [
                    'name'              => $a['adset_name'] ?? (($a['campaign_name']??($a['name']??'')).' - Conjunto 1'),
                    'campaign_id'       => $campaignId,
                    'daily_budget'      => isset($a['daily_budget']) ? (int)($a['daily_budget']*100) : 1200,
                    'optimization_goal' => $optGoal,
                    'billing_event'     => 'IMPRESSIONS',
                    'status'            => 'PAUSED',
                    'targeting'         => empty($tgt) ? ['geo_locations'=>['countries'=>['BR']]] : $tgt,
                ];
                // Para WhatsApp (wa.me): NÃO enviar destination_type — usa LINK_CLICKS simples
                // Para outros destinos: envia normalmente
                if (!empty($destType) && $destType !== 'WEBSITE' && !$isWppDest) {
                    $adsetBody['destination_type'] = $destType;
                }
                // bid_strategy: NÃO enviar quando objetivo é CONVERSATIONS/mensagens/engajamento
                $bidStrat3 = strtoupper($a['bid_strategy'] ?? '');
                $bidAmt3   = isset($a['bid_amount']) ? (int)($a['bid_amount'] * 100) : 0;
                $needsBid3 = in_array($bidStrat3, ['COST_CAP','LOWEST_COST_WITH_BID_CAP','TARGET_COST','MINIMUM_ROAS']);
                $safeBidGoals3 = ['CONVERSATIONS','REPLIES','POST_ENGAGEMENT','REACH','IMPRESSIONS','QUALITY_CALL','LEAD_GENERATION'];
                $goalIncompatible3 = in_array($optGoal, $safeBidGoals3);
                if ($bidStrat3 && !$goalIncompatible3 && !$needsBid3) {
                    $adsetBody['bid_strategy'] = $bidStrat3;
                }
                if ($bidStrat3 && !$goalIncompatible3 && $needsBid3 && $bidAmt3 > 0) {
                    $adsetBody['bid_strategy'] = $bidStrat3;
                    $adsetBody['bid_amount']   = $bidAmt3;
                }
                // promoted_object — para WhatsApp inclui whatsapp_phone_number (obrigatório na API v21+)
                if (!empty($pageId)) {
                    // Para click-to-WhatsApp via wa.me: apenas page_id no promoted_object
                    // whatsapp_phone_number só é necessário para WABA (WhatsApp Business API)
                    $adsetBody['promoted_object'] = ['page_id' => $pageId];
                }
                if (!empty($a['pixel_id']) && in_array($optGoal,['OFFSITE_CONVERSIONS','LANDING_PAGE_VIEWS'])) {
                    $adsetBody['promoted_object'] = array_merge($adsetBody['promoted_object']??[], ['pixel_id'=>$a['pixel_id'],'custom_event_type'=>$a['custom_event_type']??'PURCHASE']);
                }

                $adsetResult = self::mPost("{$b}/{$act}/adsets?access_token=".urlencode($tk), $adsetBody, true);
                if (!empty($adsetResult['error'])) return ['error'=>'[Conjunto] '.$adsetResult['error'].' | PAYLOAD: '.json_encode($adsetBody, JSON_UNESCAPED_UNICODE),'campaign_id'=>$campaignId,'step'=>'adset'];
                $adsetId = $adsetResult['id'] ?? null;
                if (!$adsetId) return ['error'=>'Conjunto criado sem ID','campaign_id'=>$campaignId];

                // ── 3. Upload de mídia (imagem ou vídeo) ──────────────
                $imageHash = $a['image_hash'] ?? '';
                $videoId   = $a['video_id']   ?? '';

                if (empty($imageHash) && empty($videoId) && !empty($a['image_url'])) {
                    $imgUp     = self::mPost("{$b}/{$act}/adimages?access_token=".urlencode($tk), ['url'=>$a['image_url']]);
                    $first     = !empty($imgUp['images']) ? reset($imgUp['images']) : [];
                    $imageHash = $first['hash'] ?? '';
                    if (!$imageHash) return ['error'=>'[Imagem] '.($imgUp['error']??'URL inacessível'),'campaign_id'=>$campaignId,'adset_id'=>$adsetId,'step'=>'image'];
                }

                // ── 4. Criativo ───────────────────────────────────────────
                $message     = $a['ad_message']    ?? ($a['message']     ?? '');
                $title       = $a['ad_title']       ?? ($a['title']       ?? '');
                $description = $a['ad_description'] ?? ($a['description'] ?? '');
                $cta         = self::buildCTA($ctaType, $destType, $a['link_url']??'', $a);

                if (!empty($videoId)) {
                    // Criativo de VÍDEO
                    $videoData = [
                        'video_id'       => $videoId,
                        'message'        => $message,
                        'title'          => $title,
                        'call_to_action' => $cta,
                    ];
                    if ($isWpp) {
                        $wNum = preg_replace('/\D/', '', $a['whatsapp_number'] ?? '');
                        $videoData['call_to_action']['value']['link'] = 'https://wa.me/'.$wNum;
                    }
                    $storySpec = ['page_id' => $pageId, 'video_data' => $videoData];
                    if (empty($pageId)) unset($storySpec['page_id']); // sem page_id em alguns objetivos
                    if (empty($pageId)) unset($storySpec['page_id']);
                } else {
                    // Criativo de IMAGEM
                    $linkData = [
                        'message'        => $message,
                        'name'           => $title,
                        'description'    => $description,
                        'call_to_action' => $cta,
                    ];
                    if (!empty($imageHash))     $linkData['image_hash'] = $imageHash;
                    if (!empty($a['link_url'])) $linkData['link']       = $a['link_url'];
                    if ($isWpp) {
                        $wNum = preg_replace('/\D/', '', $a['whatsapp_number'] ?? '');
                        $linkData['link']                 = 'https://wa.me/'.$wNum;
                        $linkData['page_welcome_message'] = $a['welcome_message'] ?? 'Olá! Gostaria de mais informações.';
                    }
                    $storySpec = ['page_id' => $pageId, 'link_data' => $linkData];
                    if (empty($pageId)) unset($storySpec['page_id']); // API aceita sem page_id em alguns objetivos
                }
                if (!empty($a['instagram_user_id'])) $storySpec['instagram_user_id'] = $a['instagram_user_id'];

                $creativeResult = self::mPost("{$b}/{$act}/adcreatives?access_token=".urlencode($tk), [
                    'name'              => $a['creative_name'] ?? ($a['ad_title'] ?? ('Criativo '.date('d/m/Y H:i'))),
                    'object_story_spec' => $storySpec,
                ], true);
                if (!empty($creativeResult['error'])) return ['error'=>'[Criativo] '.$creativeResult['error'],'campaign_id'=>$campaignId,'adset_id'=>$adsetId,'step'=>'creative'];
                $creativeId = $creativeResult['id'] ?? null;
                if (!$creativeId) return ['error'=>'Criativo criado sem ID','campaign_id'=>$campaignId,'adset_id'=>$adsetId];

                // ── 5. Anúncio ────────────────────────────────────────────
                $adResult = self::mPost("{$b}/{$act}/ads?access_token=".urlencode($tk), [
                    'name'     => $a['ad_name'] ?? ($a['ad_title'] ?? ('Anúncio '.date('d/m/Y H:i'))),
                    'adset_id' => $adsetId,
                    'creative' => ['creative_id' => $creativeId],
                    'status'   => 'PAUSED',
                ], true);

                return [
                    'success'     => true,
                    'id'          => $campaignId,
                    'campaign_id' => $campaignId,
                    'adset_id'    => $adsetId,
                    'creative_id' => $creativeId,
                    'ad_id'       => $adResult['id'] ?? null,
                    'page_id_used'=> $pageId ?: 'não encontrado',
                    'campaign'    => $campResult,
                    'adset'       => $adsetResult,
                    'creative'    => $creativeResult,
                    'ad'          => $adResult,
                    'error'       => !empty($adResult['error']) ? '[Anúncio] '.$adResult['error'] : null,
                    'summary'     => sprintf('✅ Campanha %s | Conjunto %s | Criativo %s | Anúncio %s — todos PAUSADOS',
                        $campaignId, $adsetId, $creativeId, $adResult['id']??'(erro)'),
                ];
            })(),

            default => ['error'=>"Ferramenta '$tool' não reconhecida"],
        };
    }

    public static function executeAction(string $action, array $params, array $acc): array {
        return self::callMetaApi($action, $params, $acc);
    }

    // ── Upload de imagem ou vídeo direto do computador do usuário ─────────────
    public function uploadImage(): void {
        ob_start();
        requireAuth(); csrfCheck();
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');

        $uid       = currentUser()['id'];
        $db        = Database::getInstance();
        $accountId = (int)($_POST['account_id'] ?? 0);
        $imageData = $_POST['image_data'] ?? ''; // base64 data URI

        if (!$accountId) { echo json_encode(['ok'=>false,'error'=>'Conta não selecionada']); return; }
        if (!$imageData) { echo json_encode(['ok'=>false,'error'=>'Nenhuma imagem recebida']); return; }

        // Decode base64 data URI  (data:image/jpeg;base64,XXXX  ou  data:video/mp4;base64,XXXX)
        if (!preg_match('/^data:((?:image|video)\/[a-z0-9]+);base64,(.+)$/s', $imageData, $m)) {
            echo json_encode(['ok'=>false,'error'=>'Formato de arquivo inválido (use JPG, PNG, MP4, MOV)']); return;
        }
        $mime     = $m[1];  // image/jpeg, video/mp4, etc
        $isVideo  = str_starts_with($mime, 'video/');
        $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
                     'video/mp4'=>'mp4','video/quicktime'=>'mov','video/mpeg'=>'mpeg','video/avi'=>'avi'];
        $ext      = $extMap[$mime] ?? ($isVideo ? 'mp4' : 'jpg');
        $binary   = base64_decode($m[2]);
        if (!$binary || strlen($binary) < 100) {
            echo json_encode(['ok'=>false,'error'=>'Arquivo corrompido ou muito pequeno']); return;
        }

        // Get account token
        $acc = $db->query(
            "SELECT aa.*, c.name AS client_name FROM ad_accounts aa
             LEFT JOIN clients c ON c.id=aa.client_id
             WHERE aa.id=? AND aa.user_id=? AND aa.platform='meta'",
            [$accountId, $uid]
        )->fetch();
        if (!$acc) { echo json_encode(['ok'=>false,'error'=>'Conta não encontrada']); return; }
        $acc = decryptTokens($acc);
        if (empty($acc['access_token'])) {
            echo json_encode(['ok'=>false,'error'=>'Token expirado. Reconecte em Contas de Anúncio.']); return;
        }

        // Save to temp file for multipart upload
        $tmpFile = sys_get_temp_dir() . '/agimg_' . uniqid() . '.' . $ext;
        file_put_contents($tmpFile, $binary);

        // Upload to Meta Ads via multipart/form-data
        $actId = 'act_' . $acc['account_id'];

        if ($isVideo) {
            // ── Vídeo: POST para /advideos ─────────────────────────────
            $url = 'https://graph.facebook.com/' . META_API_VERSION . '/' . $actId . '/advideos?access_token=' . urlencode($acc['access_token']);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'source'    => new \CURLFile($tmpFile, $mime, 'video.' . $ext),
                    'title'     => $_POST['image_name'] ?? 'video',
                ],
                CURLOPT_TIMEOUT => 300, // vídeos grandes precisam de mais tempo
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            @unlink($tmpFile);

            $data    = json_decode($resp, true);
            $videoId = $data['id'] ?? null;

            if (!$videoId) {
                $errMsg = $data['error']['message'] ?? 'Upload de vídeo falhou (HTTP '.$code.')';
                echo json_encode(['ok'=>false,'error'=>$errMsg]);
                return;
            }
            echo json_encode(['ok'=>true,'type'=>'video','video_id'=>$videoId]);

        } else {
            // ── Imagem: POST para /adimages ────────────────────────────
            $url = 'https://graph.facebook.com/' . META_API_VERSION . '/' . $actId . '/adimages?access_token=' . urlencode($acc['access_token']);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'filename' => new \CURLFile($tmpFile, $mime, 'image.' . $ext),
                ],
                CURLOPT_TIMEOUT => 60,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            @unlink($tmpFile);

            $data   = json_decode($resp, true);
            $images = $data['images'] ?? [];
            $first  = !empty($images) ? reset($images) : [];
            $hash   = $first['hash'] ?? null;
            $imgUrl = $first['url']  ?? null;

            if (!$hash) {
                $errMsg = $data['error']['message'] ?? 'Upload de imagem falhou (HTTP '.$code.')';
                echo json_encode(['ok'=>false,'error'=>$errMsg]);
                return;
            }
            echo json_encode(['ok'=>true,'type'=>'image','hash'=>$hash,'url'=>$imgUrl]);
        }
    }

    // ── Tools definitions ─────────────────────────────────────────────────────
    private static function toolDefs(): array {
        return [
            ['name'=>'get_account_info','desc'=>'Informações gerais da conta: nome, status, saldo, moeda','p'=>[],'r'=>[]],
            ['name'=>'get_account_balance','desc'=>'Saldo atual da conta','p'=>[],'r'=>[]],
            ['name'=>'list_campaigns','desc'=>'Lista campanhas. status: ACTIVE(padrão),PAUSED,ARCHIVED,ALL','p'=>['status'=>['type'=>'string']],'r'=>[]],
            ['name'=>'get_campaign_detail','desc'=>'Detalhes completos de uma campanha','p'=>['campaign_id'=>['type'=>'string']],'r'=>['campaign_id']],
            ['name'=>'list_adsets','desc'=>'Lista conjuntos. Filtre por campaign_id ou status','p'=>['campaign_id'=>['type'=>'string'],'status'=>['type'=>'string','description'=>'ACTIVE,PAUSED,ALL']],'r'=>[]],
            ['name'=>'get_adset_detail','desc'=>'Detalhes de um conjunto incluindo segmentação completa','p'=>['adset_id'=>['type'=>'string']],'r'=>['adset_id']],
            ['name'=>'list_ads','desc'=>'Lista anúncios. Filtre por adset_id, campaign_id ou status','p'=>['adset_id'=>['type'=>'string'],'campaign_id'=>['type'=>'string'],'status'=>['type'=>'string']],'r'=>[]],
            ['name'=>'get_insights','desc'=>'Métricas de desempenho. date_preset: today,yesterday,last_3d,last_7d,last_14d,last_28d,last_30d,last_90d,this_month,last_month,maximum','p'=>['campaign_id'=>['type'=>'string'],'adset_id'=>['type'=>'string'],'ad_id'=>['type'=>'string'],'date_preset'=>['type'=>'string','description'=>'Período padrão: last_30d']],'r'=>[]],
            ['name'=>'get_insights_by_date','desc'=>'Métricas por intervalo de datas (YYYY-MM-DD)','p'=>['campaign_id'=>['type'=>'string'],'since'=>['type'=>'string'],'until'=>['type'=>'string']],'r'=>[]],
            ['name'=>'get_insights_breakdown','desc'=>'Métricas segmentadas. breakdown: age,gender | publisher_platform,platform_position | device_platform','p'=>['campaign_id'=>['type'=>'string'],'breakdown'=>['type'=>'string'],'date_preset'=>['type'=>'string']],'r'=>[]],
            ['name'=>'compare_campaigns','desc'=>'Compara métricas de até 8 campanhas lado a lado','p'=>['campaign_ids'=>['type'=>'array','items'=>['type'=>'string']],'date_preset'=>['type'=>'string']],'r'=>['campaign_ids']],
            ['name'=>'find_underperforming','desc'=>'Encontra campanhas/conjuntos/anúncios com baixo desempenho baseado em thresholds de CPC, CTR e CPM. Use para identificar o que otimizar ou pausar.','p'=>['level'=>['type'=>'string','description'=>'campaign, adset ou ad'],'max_cpc'=>['type'=>'number','description'=>'CPC máximo em reais'],'min_ctr'=>['type'=>'number','description'=>'CTR mínimo em %'],'max_cpm'=>['type'=>'number','description'=>'CPM máximo em reais'],'date_preset'=>['type'=>'string']],'r'=>[]],
            ['name'=>'list_audiences','desc'=>'Lista públicos customizados','p'=>[],'r'=>[]],
            ['name'=>'list_pixels','desc'=>'Lista pixels de conversão','p'=>[],'r'=>[]],
            ['name'=>'list_creatives','desc'=>'Lista criativos disponíveis','p'=>[],'r'=>[]],
            ['name'=>'search_interests','desc'=>'Pesquisa interesses para segmentação','p'=>['query'=>['type'=>'string','description'=>'Ex: fitness, culinária, empreendedorismo']],'r'=>['query']],
            // ESCRITA
            ['name'=>'update_campaign_status','desc'=>'Ativa ou pausa uma campanha','p'=>['campaign_id'=>['type'=>'string'],'status'=>['type'=>'string','enum'=>['ACTIVE','PAUSED']]],'r'=>['campaign_id','status']],
            ['name'=>'update_campaign_budget','desc'=>'Altera orçamento de uma campanha','p'=>['campaign_id'=>['type'=>'string'],'budget_type'=>['type'=>'string','enum'=>['daily','lifetime']],'amount'=>['type'=>'number','description'=>'Valor em reais']],'r'=>['campaign_id','budget_type','amount']],
            ['name'=>'update_adset_status','desc'=>'Ativa ou pausa um conjunto de anúncios','p'=>['adset_id'=>['type'=>'string'],'status'=>['type'=>'string','enum'=>['ACTIVE','PAUSED']]],'r'=>['adset_id','status']],
            ['name'=>'update_adset_budget','desc'=>'Altera orçamento de um conjunto','p'=>['adset_id'=>['type'=>'string'],'budget_type'=>['type'=>'string','enum'=>['daily','lifetime']],'amount'=>['type'=>'number','description'=>'Valor em reais']],'r'=>['adset_id','budget_type','amount']],
            ['name'=>'update_ad_status','desc'=>'Ativa ou pausa um anúncio específico','p'=>['ad_id'=>['type'=>'string'],'status'=>['type'=>'string','enum'=>['ACTIVE','PAUSED']]],'r'=>['ad_id','status']],
            ['name'=>'bulk_update_status','desc'=>'Pausa ou ativa múltiplos itens de uma vez. Use após find_underperforming.','p'=>['ids'=>['type'=>'array','items'=>['type'=>'string']],'entity_type'=>['type'=>'string','description'=>'campaign, adset ou ad'],'status'=>['type'=>'string','enum'=>['ACTIVE','PAUSED']]],'r'=>['ids','entity_type','status']],
            ['name'=>'duplicate_campaign','desc'=>'Duplica campanha completa (criada PAUSADA)','p'=>['campaign_id'=>['type'=>'string']],'r'=>['campaign_id']],
            ['name'=>'duplicate_adset','desc'=>'Duplica conjunto de anúncios (criado PAUSADO). Passe campaign_id se disponível para evitar lookup extra.','p'=>['adset_id'=>['type'=>'string'],'campaign_id'=>['type'=>'string','description'=>'ID da campanha pai (opcional, mas recomendado)']],'r'=>['adset_id']],
            ['name'=>'create_campaign','desc'=>'Cria nova campanha (SEMPRE PAUSADA). Objetivos disponíveis: OUTCOME_TRAFFIC, OUTCOME_LEADS, OUTCOME_SALES, OUTCOME_ENGAGEMENT (use para Mensagens/WhatsApp), OUTCOME_AWARENESS, OUTCOME_APP_PROMOTION. IMPORTANTE: para campanhas de WhatsApp ou mensagens, sempre use objective=OUTCOME_ENGAGEMENT','p'=>['name'=>['type'=>'string'],'objective'=>['type'=>'string'],'daily_budget'=>['type'=>'number','description'=>'Orçamento diário em reais'],'special_ad_categories'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'[] para a maioria dos casos']],'r'=>['name','objective']],
            ['name'=>'create_adset','desc'=>'Cria conjunto com segmentação completa (SEMPRE PAUSADO). Informe segmentação desejada.','p'=>[
                'campaign_id'=>['type'=>'string'],'name'=>['type'=>'string'],
                'daily_budget'=>['type'=>'number','description'=>'Orçamento diário em reais'],
                'optimization_goal'=>['type'=>'string','description'=>'CONVERSATIONS (WhatsApp/Mensagens), REACH, LINK_CLICKS, LANDING_PAGE_VIEWS, LEAD_GENERATION, CONVERSIONS, VIDEO_VIEWS, POST_ENGAGEMENT'],
                'billing_event'=>['type'=>'string','description'=>'IMPRESSIONS (padrão) ou LINK_CLICKS'],
                'age_min'=>['type'=>'integer'],'age_max'=>['type'=>'integer'],
                'genders'=>['type'=>'array','items'=>['type'=>'integer'],'description'=>'[1]=Masc [2]=Fem [1,2]=Todos'],
                'countries'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'["BR"]'],
                'interests'=>['type'=>'array','items'=>['type'=>'object'],'description'=>'[{id,name}] — use search_interests primeiro'],
                'custom_audiences'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'IDs de públicos — use list_audiences'],
                'excluded_audiences'=>['type'=>'array','items'=>['type'=>'string']],
                'publisher_platforms'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'["facebook","instagram"] — para WhatsApp use apenas ["facebook"]'],
                'device_platforms'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'["mobile","desktop"]'],
                'destination_type'=>['type'=>'string','description'=>'WEBSITE, MESSENGER, INSTAGRAM_DIRECT, WHATSAPP_BUSINESS (para WhatsApp), APP'],
                'page_id'=>['type'=>'string','description'=>'ID da pagina do Facebook - obrigatorio para WhatsApp/Mensagens (optimization_goal=CONVERSATIONS)'],
                'start_time'=>['type'=>'string'],'end_time'=>['type'=>'string'],
            ],'r'=>['campaign_id','name','optimization_goal']],
            // FERRAMENTA COMBINADA — use esta quando o usuário pedir campanha completa (campanha + conjunto)
            ['name'=>'create_campaign_with_adset','desc'=>'⭐ USA ESTA quando criar campanha completa com conjunto. Cria campanha E conjunto em uma única ação atômica (ambos PAUSADOS). SEMPRE use esta no lugar de create_campaign+create_adset separados quando o usuário pedir para criar uma campanha nova. Para WhatsApp: objective=OUTCOME_ENGAGEMENT, optimization_goal=CONVERSATIONS, destination_type=WHATSAPP',
             'p'=>[
                'campaign_name'=>['type'=>'string','description'=>'Nome da campanha'],
                'adset_name'=>['type'=>'string','description'=>'Nome do conjunto (opcional, usa campaign_name + Conjunto 1 se omitido)'],
                'objective'=>['type'=>'string','description'=>'OUTCOME_ENGAGEMENT (WhatsApp/Mensagens), OUTCOME_TRAFFIC, OUTCOME_LEADS, OUTCOME_SALES, OUTCOME_AWARENESS'],
                'optimization_goal'=>['type'=>'string','description'=>'CONVERSATIONS (WhatsApp), LINK_CLICKS, REACH, LEAD_GENERATION'],
                'destination_type'=>['type'=>'string','description'=>'WHATSAPP (para WhatsApp, nunca WHATSAPP_BUSINESS), WEBSITE, MESSENGER'],
                'daily_budget'=>['type'=>'number','description'=>'Orçamento diário em reais (vai para o conjunto)'],
                'page_id'=>['type'=>'string','description'=>'ID da página do Facebook (obrigatório para WhatsApp)'],
                'countries'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'Ex: ["BR"]'],
                'cities'=>['type'=>'array','items'=>['type'=>'object'],'description'=>'Lista de cidades. Ex: [{"key":"2479291","radius":25,"distance_unit":"kilometer"}] — use a chave da cidade do Meta'],
                'age_min'=>['type'=>'integer'],'age_max'=>['type'=>'integer'],
                'genders'=>['type'=>'array','items'=>['type'=>'integer'],'description'=>'[1]=Masc [2]=Fem'],
                'publisher_platforms'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'["facebook"] para WhatsApp'],
            ],'r'=>['campaign_name','objective']],
            // ── CRIATIVOS E ANÚNCIOS ──────────────────────────────────────────────
            ['name'=>'upload_image','desc'=>'Faz upload de uma imagem por URL pública para a conta. Retorna image_hash para usar no criativo.','p'=>['image_url'=>['type'=>'string','description'=>'URL pública da imagem (JPG, PNG)']],'r'=>['image_url']],

            ['name'=>'create_adcreative','desc'=>'Cria um criativo de anúncio. Funciona para qualquer destino: WhatsApp (destination_type=WHATSAPP_BUSINESS, cta_type=WHATSAPP_MESSAGE), Website (WEBSITE, LEARN_MORE), Messenger (MESSENGER, MESSAGE_PAGE), Perfil Instagram (INSTAGRAM_PROFILE). Requer page_id e image_hash (use upload_image antes se não tiver).','p'=>[
                'name'               =>['type'=>'string','description'=>'Nome do criativo'],
                'page_id'            =>['type'=>'string','description'=>'ID da página do Facebook (obrigatório)'],
                'image_hash'         =>['type'=>'string','description'=>'Hash da imagem (obtido via upload_image)'],
                'video_id'           =>['type'=>'string','description'=>'ID do vídeo (alternativa à imagem)'],
                'message'            =>['type'=>'string','description'=>'Texto principal do anúncio'],
                'title'              =>['type'=>'string','description'=>'Título/headline'],
                'description'        =>['type'=>'string','description'=>'Descrição secundária'],
                'link_url'           =>['type'=>'string','description'=>'URL de destino (para website/tráfego)'],
                'whatsapp_number'    =>['type'=>'string','description'=>'Número WhatsApp com DDI. Ex: 5511999999999'],
                'welcome_message'    =>['type'=>'string','description'=>'Mensagem de boas-vindas no WhatsApp'],
                'instagram_user_id'  =>['type'=>'string','description'=>'ID do usuário Instagram (para anúncios no IG)'],
                'destination_type'   =>['type'=>'string','description'=>'WHATSAPP_BUSINESS, WEBSITE, MESSENGER, INSTAGRAM_DIRECT, INSTAGRAM_PROFILE'],
                'cta_type'           =>['type'=>'string','description'=>'WHATSAPP_MESSAGE, LEARN_MORE, SHOP_NOW, SIGN_UP, CALL_NOW, MESSAGE_PAGE, INSTAGRAM_MESSAGE, DOWNLOAD, SUBSCRIBE'],
            ],'r'=>['page_id']],

            ['name'=>'create_ad','desc'=>'Cria o anúncio vinculando um criativo existente a um conjunto. Anúncio criado PAUSADO.','p'=>[
                'name'        =>['type'=>'string','description'=>'Nome do anúncio'],
                'adset_id'    =>['type'=>'string','description'=>'ID do conjunto de anúncios'],
                'creative_id' =>['type'=>'string','description'=>'ID do criativo (obtido via create_adcreative)'],
            ],'r'=>['adset_id','creative_id']],

            // ⭐ FERRAMENTA PRINCIPAL — usa esta para criar campanha completa com anúncio
            ['name'=>'create_full_campaign','desc'=>'⭐ PRINCIPAL: Cria TUDO em uma só operação: campanha + conjunto + criativo + anúncio (todos PAUSADOS). Suporta imagem (image_hash ou image_url) E vídeo (video_id). Use SEMPRE que o usuário pedir para criar uma campanha com criativo. Adapta para: WhatsApp (destination_type=WHATSAPP), Tráfego (WEBSITE), Leads, Perfil Instagram. Se vier video_id, usa criativo de vídeo automaticamente.','p'=>[
                'campaign_name'      =>['type'=>'string','description'=>'Nome da campanha'],
                'adset_name'         =>['type'=>'string','description'=>'Nome do conjunto (opcional)'],
                'ad_name'            =>['type'=>'string','description'=>'Nome do anúncio (opcional)'],
                'creative_name'      =>['type'=>'string','description'=>'Nome do criativo (opcional)'],
                'objective'          =>['type'=>'string','description'=>'OUTCOME_ENGAGEMENT (WhatsApp/Mensagens/Perfil IG), OUTCOME_TRAFFIC, OUTCOME_LEADS, OUTCOME_SALES, OUTCOME_AWARENESS'],
                'destination_type'   =>['type'=>'string','description'=>'WHATSAPP_BUSINESS, WEBSITE, MESSENGER, INSTAGRAM_DIRECT, INSTAGRAM_PROFILE'],
                'optimization_goal'  =>['type'=>'string','description'=>'CONVERSATIONS (WhatsApp), LINK_CLICKS, LANDING_PAGE_VIEWS, LEAD_GENERATION, OFFSITE_CONVERSIONS, REACH, POST_ENGAGEMENT'],
                'cta_type'           =>['type'=>'string','description'=>'WHATSAPP_MESSAGE, LEARN_MORE, SHOP_NOW, SIGN_UP, CALL_NOW, MESSAGE_PAGE, INSTAGRAM_MESSAGE'],
                'daily_budget'       =>['type'=>'number','description'=>'Orçamento diário em reais'],
                'page_id'            =>['type'=>'string','description'=>'ID da página do Facebook'],
                'instagram_user_id'  =>['type'=>'string','description'=>'ID do usuário Instagram'],
                'whatsapp_number'    =>['type'=>'string','description'=>'Número WhatsApp com DDI. Ex: 5511999999999'],
                'welcome_message'    =>['type'=>'string','description'=>'Mensagem de boas-vindas WhatsApp'],
                'link_url'           =>['type'=>'string','description'=>'URL de destino (website/tráfego/vendas)'],
                'image_url'          =>['type'=>'string','description'=>'URL pública da imagem (o sistema faz upload automático)'],
                'image_hash'         =>['type'=>'string','description'=>'Hash da imagem se já foi feito upload'],
                'video_id'           =>['type'=>'string','description'=>'ID do vídeo (use quando o usuário enviar vídeo em vez de imagem — campo video_id retornado pelo upload)'],
                'ad_message'         =>['type'=>'string','description'=>'Texto principal do anúncio'],
                'ad_title'           =>['type'=>'string','description'=>'Título/headline do anúncio'],
                'ad_description'     =>['type'=>'string','description'=>'Descrição secundária do anúncio'],
                'countries'          =>['type'=>'array','items'=>['type'=>'string'],'description'=>'Ex: ["BR"]'],
                'cities'             =>['type'=>'array','items'=>['type'=>'object'],'description'=>'Cidades. Ex: [{"key":"2479291","radius":25,"distance_unit":"kilometer"}]'],
                'age_min'            =>['type'=>'integer'],'age_max'=>['type'=>'integer'],
                'genders'            =>['type'=>'array','items'=>['type'=>'integer'],'description'=>'[1]=Masc [2]=Fem'],
                'interests'          =>['type'=>'array','items'=>['type'=>'object'],'description'=>'[{id,name}]'],
                'publisher_platforms'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'["facebook","instagram"] ou ["facebook"] para WhatsApp'],
                'facebook_positions' =>['type'=>'array','items'=>['type'=>'string'],'description'=>'feed, story, reels, right_hand_column, marketplace'],
                'instagram_positions'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'stream (feed), story, reels, explore'],
                'pixel_id'           =>['type'=>'string','description'=>'ID do pixel (para objetivos de conversão)'],
                'custom_event_type'  =>['type'=>'string','description'=>'PURCHASE, LEAD, COMPLETE_REGISTRATION (para conversões)'],
            ],'r'=>['campaign_name','objective','destination_type']],

        ];
    }

    private static function getOpenAITools(): array {
        return array_map(function($t) {
            $props=[];
            foreach($t['p'] as $k=>$v) $props[$k]=is_array($v)?$v:['type'=>'string','description'=>$v];
            return ['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['desc'],'parameters'=>['type'=>'object','properties'=>empty($props)?new \stdClass():$props,'required'=>$t['r']]]];
        }, self::toolDefs());
    }

    private static function getAnthropicTools(): array {
        return array_map(function($t) {
            $props=[];
            foreach($t['p'] as $k=>$v) $props[$k]=is_array($v)?$v:['type'=>'string','description'=>$v];
            return ['name'=>$t['name'],'description'=>$t['desc'],'input_schema'=>['type'=>'object','properties'=>empty($props)?new \stdClass():$props,'required'=>$t['r']]];
        }, self::toolDefs());
    }

    private static function isWrite(string $t): bool {
        return in_array($t,['update_campaign_status','update_campaign_budget','update_adset_status','update_adset_budget','update_ad_status','bulk_update_status','duplicate_campaign','duplicate_adset','create_campaign','create_adset','create_campaign_with_adset','create_adcreative','create_ad','create_full_campaign']);
    }

    private static function label(string $t, array $a): string {
        return match($t) {
            'update_campaign_status' => ($a['status']==='ACTIVE'?'▶ Ativar':'⏸ Pausar').' campanha '.$a['campaign_id'],
            'update_campaign_budget' => '💰 Orçamento campanha '.$a['campaign_id'].' → R$ '.number_format($a['amount']??0,2,',','.').'/'.$a['budget_type'],
            'update_adset_status'    => ($a['status']==='ACTIVE'?'▶ Ativar':'⏸ Pausar').' conjunto '.$a['adset_id'],
            'update_adset_budget'    => '💰 Orçamento conjunto '.$a['adset_id'].' → R$ '.number_format($a['amount']??0,2,',','.').'/'.$a['budget_type'],
            'update_ad_status'       => ($a['status']==='ACTIVE'?'▶ Ativar':'⏸ Pausar').' anúncio '.$a['ad_id'],
            'bulk_update_status'     => ($a['status']==='ACTIVE'?'▶ Ativar em lote':'⏸ Pausar em lote').' '.count($a['ids']??[]).' '.($a['entity_type']??'itens'),
            'duplicate_campaign'     => '📋 Duplicar campanha '.$a['campaign_id'].' (criada pausada)',
            'duplicate_adset'        => '📋 Duplicar conjunto '.$a['adset_id'].' (criado pausado)',
            'create_campaign'        => '🆕 Criar campanha "'.$a['name'].'" ['.$a['objective'].']'.(isset($a['daily_budget'])?' R$'.number_format($a['daily_budget'],2,',','.'):''),
            'create_adset'           => '🆕 Criar conjunto "'.$a['name'].'" › campanha '.$a['campaign_id'],
            'create_campaign_with_adset' => '🆕 Criar campanha completa "'.($a['campaign_name']??$a['name']??'').'" + conjunto ['.($a['objective']??'OUTCOME_ENGAGEMENT').'] R$'.number_format($a['daily_budget']??0,2,',','.'),
            'upload_image'           => '🖼️ Fazer upload de imagem: '.($a['image_url']??''),
            'create_adcreative'      => '🎨 Criar criativo "'.($a['name']??$a['title']??'').'" ['.($a['destination_type']??'WEBSITE').']',
            'create_ad'              => '📢 Criar anúncio "'.($a['name']??'').'" › conjunto '.$a['adset_id'],
            'create_full_campaign'   => '🚀 Criar campanha COMPLETA "'.($a['campaign_name']??$a['name']??'').'" ['.($a['objective']??'').'] + conjunto + criativo + anúncio — R$'.number_format($a['daily_budget']??0,2,',','.'),
                        default => str_replace('_',' ',$t),
        };
    }

    private static function getEntityType(string $t): string {
        if(str_contains($t,'_adset')||$t==='create_adset') return 'adset';
        if($t==='create_campaign_with_adset') return 'campaign';
        if(in_array($t,['create_adcreative','upload_image'])) return 'creative';
        if($t==='create_ad') return 'ad';
        if($t==='create_full_campaign') return 'campaign';
        if(str_contains($t,'_ad')&&!str_contains($t,'account')) return 'ad';
        return 'campaign';
    }

    private static function summary(array $r): string {
        if(isset($r['error'])) return '❌ '.$r['error'];
        $c=count($r['data']??$r['comparisons']??$r['underperforming']??$r['bulk_results']??[]);
        return $c>0?"✅ {$c} item(s)":(isset($r['success'])?'✅ Executado':'✅ OK');
    }

    // Extrai a métrica de resultado principal baseado nas ações disponíveis
    private static function bestResult(array $acts, float $spend): array {
        // profile_visits dominante (campanha de visitas ao perfil IG)
        $profV2 = (int)($acts['profile_visits'] ?? 0);
        $msgs2  = (int)($acts['messages'] ?? 0);
        if ($profV2 > 0 && $profV2 > $msgs2 * 10) {
            $cpr = $spend > 0 ? round($spend / $profV2, 2) : 0;
            return ['key'=>'profile_visits','label'=>'Visitas perfil','emoji'=>'👤','qty'=>$profV2,'cost_per_result'=>$cpr,'roas'=>0];
        }
        $priority = [
            'purchases'     => ['label'=>'Compras',          'emoji'=>'🛒'],
            'leads'         => ['label'=>'Leads',            'emoji'=>'📋'],
            'registrations' => ['label'=>'Cadastros',        'emoji'=>'📝'],
            'messages'      => ['label'=>'Mensagens',        'emoji'=>'💬'],
            'whatsapp_clicks'=> ['label'=>'Cliques WhatsApp','emoji'=>'📱'],
            'landing_views' => ['label'=>'Visitas LP',       'emoji'=>'🌐'],
            'link_clicks'   => ['label'=>'Cliques no link',  'emoji'=>'🔗'],
            'post_engagement'=>['label'=>'Engajamento',      'emoji'=>'❤️'],
            'video_views'   => ['label'=>'Visualiz. vídeo',  'emoji'=>'▶️'],
            'profile_visits'=> ['label'=>'Visitas perfil',   'emoji'=>'👤'],
        ];
        foreach ($priority as $key => $meta) {
            $qty = (int)($acts[$key] ?? 0);
            if ($qty > 0) {
                $cpr = $spend > 0 ? round($spend / $qty, 2) : 0;
                return ['key'=>$key,'label'=>$meta['label'],'emoji'=>$meta['emoji'],'qty'=>$qty,'cost_per_result'=>$cpr,'roas'=>$acts['roas']??0];
            }
        }
        return ['key'=>'impressions','label'=>'Impressões','emoji'=>'👁️','qty'=>0,'cost_per_result'=>0,'roas'=>0];
    }

    private static function buildSystemPrompt(array $acc): string {
        $today = date('d/m/Y H:i');
        $acct  = $acc['account_name'] ?? '';
        $cli   = $acc['client_name']  ?? '';
        return <<<SYSPROMPT
Você é especialista sênior em Meta Ads com acesso DIRETO à API desta conta.

CONTA: {$acct} | CLIENTE: {$cli} | HOJE: {$today}

══════════════════════════════════════════════════════════════
REGRA ANTI-LOOP — LEIA ANTES DE TUDO
══════════════════════════════════════════════════════════════
- Você tem NO MÁXIMO 12 chamadas de ferramenta por resposta. USE COM ECONOMIA.
- NUNCA chame a mesma ferramenta 2x em sequência com os mesmos parâmetros.
- Se uma ferramenta retornar erro, NÃO repita — informe o erro ao usuário.
- Se não souber o page_id: chame get_account_info UMA VEZ. Se não vier, OMITA o campo.
- Se não souber o whatsapp_number: use o número que o usuário mencionou em qualquer parte do contexto. Se não houver nenhum, pergunte UMA VEZ.
- NUNCA entre em loop buscando informações que não existem na API.

══════════════════════════════════════════════════════════════
FLUXO "FAÇA TUDO" — quando o usuário pedir para criar sem dar todos os dados
══════════════════════════════════════════════════════════════
Quando o usuário disser "faça tudo", "crie você mesmo", "use o mesmo número", "use a mesma página" ou similar:
1. Use get_account_info UMA VEZ para pegar page_id e dados da conta
2. Use o image_hash/video_id que já está no contexto da conversa
3. Crie copy profissional automaticamente baseado no segmento do cliente
4. Use whatsapp_number do contexto (se disser "mesmo número", procure na conversa anterior)
5. Execute create_full_campaign DIRETAMENTE — não fique pedindo confirmações intermediárias
6. Mostre o resultado ao usuário

NUNCA pergunte pelo page_id, image_hash, ou copy se já estiverem no contexto.
NUNCA pergunte pelo whatsapp_number se o usuário disse "mesmo número" — procure no histórico.

══════════════════════════════════════════════════════════════
CAPACIDADES
══════════════════════════════════════════════════════════════
✅ Ler campanhas, conjuntos, anúncios, métricas, públicos, pixels, criativos
✅ Comparar campanhas e encontrar o que está com baixo desempenho (find_underperforming)
✅ Criar campanhas completas com criativo (imagem/vídeo/texto/CTA) para qualquer objetivo
✅ Pausar/ativar individualmente ou em lote (bulk_update_status)
✅ Alterar orçamentos, duplicar campanhas/conjuntos, pesquisar interesses

══════════════════════════════════════════════════════════════
REGRAS ABSOLUTAS
══════════════════════════════════════════════════════════════
1. Para QUALQUER escrita: explique e aguarde confirmação — NUNCA execute sem confirmar
2. Campanhas criadas são SEMPRE PAUSADAS — nunca ative automaticamente
3. Responda em português (pt-BR), valores em R$ 1.234,56, dados em tabelas
4. Use IDs reais retornados pelas ferramentas

══════════════════════════════════════════════════════════════
CRIAÇÃO DE CAMPANHAS — OBJETIVOS E PARÂMETROS
══════════════════════════════════════════════════════════════
WHATSAPP:
- objective=OUTCOME_ENGAGEMENT, optimization_goal=CONVERSATIONS
- destination_type=WHATSAPP (NUNCA WHATSAPP_BUSINESS)
- publisher_platforms=["facebook"] apenas
- whatsapp_number com DDI (ex: 5581999999999)

TRÁFEGO/SITE:
- objective=OUTCOME_TRAFFIC, destination_type=WEBSITE, link_url=https://...

LEADS:
- objective=OUTCOME_LEADS, destination_type=WEBSITE ou ON_AD

INSTAGRAM PROFILE:
- objective=OUTCOME_ENGAGEMENT, destination_type=INSTAGRAM_PROFILE

══════════════════════════════════════════════════════════════
POSICIONAMENTOS — VALORES EXATOS DA API
══════════════════════════════════════════════════════════════
Facebook (facebook_positions): feed | story | video_feeds | marketplace | right_hand_column
  ⚠️ NUNCA use "reels" em facebook_positions — o correto é "video_feeds"

Instagram (instagram_positions): stream | story | reels | explore
  ✅ "reels" é válido aqui | feed do Instagram = "stream" (não "feed")

══════════════════════════════════════════════════════════════
CRIATIVO
══════════════════════════════════════════════════════════════
- Se vier image_hash no contexto → use create_full_campaign com image_hash
- Se vier video_id no contexto → use create_full_campaign com video_id
- NUNCA extraia page_id de URLs de imagem — são CDN, não IDs de página
- Se não tiver page_id, OMITA o campo — o backend busca automaticamente
- Orçamento SEMPRE no conjunto (adset), nunca na campanha

══════════════════════════════════════════════════════════════
ORÇAMENTOS E SALDO — LEIA COM ATENÇÃO
══════════════════════════════════════════════════════════════
Os campos raw (daily_budget, lifetime_budget, balance) vêm em CENTAVOS.
O sistema já calcula os campos convertidos com sufixo _brl (ex: daily_budget_brl, balance_brl).
SEMPRE use o campo _brl para exibir valores ao usuário.
Exemplos:
- daily_budget=2100, daily_budget_brl=21.0  → exiba "R$ 21,00/dia"
- balance=150000, balance_brl=1500.0        → exiba "R$ 1.500,00"

SIGNIFICADO EXATO DE CADA CAMPO DE ORÇAMENTO:
- daily_budget_brl    → orçamento diário configurado na campanha (ex: R$ 21,00/dia)
- lifetime_budget_brl → orçamento total vitalício configurado na campanha
- budget_remaining_brl→ quanto FALTA gastar do orçamento DIÁRIO DE HOJE (se for R$ 1,38 significa que já gastou quase tudo do dia)
- balance_brl         → saldo FINANCEIRO da conta (crédito pré-pago disponível para pagar os anúncios)
- amount_spent_brl    → total já gasto na conta no período de faturamento

NUNCA confunda budget_remaining (saldo do dia) com balance (saldo da conta).

REGRAS DE EXIBIÇÃO:
- Ao listar campanhas (list_campaigns): mostre APENAS nome, objetivo, orçamento diário, status e data de início. NÃO mostre budget_remaining a menos que o usuário pergunte explicitamente.
- O campo `_boost_note` indica quantas publicações impulsionadas foram ocultadas. Se existir, mencione ao usuário.
- Campanhas com `_is_boost: true` são boosts de publicações — NÃO as trate como campanhas normais.

══════════════════════════════════════════════════════════════
REGRA ANTI-ALUCINAÇÃO
══════════════════════════════════════════════════════════════
NUNCA invente ou reutilize métricas de conversas anteriores.
Se get_insights retornar no_data:true ou data vazio para 'today':
- NÃO diga apenas 'sem dados'. A Meta demora 1-3h para consolidar dados do dia.
- Chame AUTOMATICAMENTE get_insights com date_preset=yesterday e mostre esses dados.
- Informe o usuário: 'Os dados de hoje ainda não foram consolidados pela Meta (delay normal de 1-3h). Mostrando dados de ontem:'
- Para campanhas de tráfego com mensagens (OUTCOME_TRAFFIC + WhatsApp), o resultado correto é 'messages' (conversas iniciadas), não 'whatsapp_clicks'.

REGRA PRINCIPAL — RESULTADO DE CADA CAMPANHA
══════════════════════════════════════════════════════════════
SEMPRE use `_main_result` para o resultado principal de cada campanha:
- _main_result.key = métrica (messages, whatsapp_clicks, profile_visits, link_clicks...)
- _main_result.qty = quantidade | _main_result.cost_per_result = custo por resultado

Métricas por objetivo:
- OUTCOME_TRAFFIC + WhatsApp (conversas) → messages/messaging_conversation (NÃO whatsapp_clicks)
- OUTCOME_TRAFFIC + Site → link_clicks ou landing_views
- OUTCOME_ENGAGEMENT + Mensagens → messages
- OUTCOME_ENGAGEMENT + Perfil IG → profile_visits
- OUTCOME_TRAFFIC + Site/URL → link_clicks ou landing_views

IMPORTANTE sobre get_insights:
- Para métricas GERAIS da conta (total de msgs, spend, etc): chame UMA VEZ sem campaign_id.
- Para métricas de UMA campanha específica: passe o campaign_id dessa campanha.
- NUNCA chame get_insights em loop para cada campanha — isso duplica os dados.
- Exemplo correto: 'quantas mensagens hoje?' → get_insights(date_preset=today) SEM campaign_id.
- Exemplo correto: 'métricas da campanha X?' → get_insights(campaign_id=ID_DE_X, date_preset=last_7d).
Buscar métricas sem campaign_id pode retornar zero — é limitação da API Meta.
Se o usuário pedir métricas gerais da conta: chame get_campaigns primeiro, depois get_insights para cada campanha.
Para 'desde o início': use get_insights com campaign_id + date_preset=maximum.
Quando _period_note estiver presente nos dados: avise o usuário com a nota e mostre os números normalmente.
Para datas customizadas: use get_insights_by_date com campaign_id + since='start'.
SEMPRE passe campaign_id quando o usuário pede de uma campanha específica.

- budget_remaining só aparece se o usuário perguntar "quanto falta do orçamento de hoje" ou similar. Nesse caso explique: "Faltam R$ X,XX para completar o orçamento diário de hoje (R$ Y,YY)".
- balance (saldo da conta) só aparece se o usuário perguntar "qual meu saldo" ou "quanto tenho de crédito".

Ao ALTERAR orçamento: o usuário informa em reais → você passa em reais para a ferramenta.

══════════════════════════════════════════════════════════════
MÉTRICAS — CAMPOS JÁ PROCESSADOS EM _metrics E _parsed_actions
══════════════════════════════════════════════════════════════
Todos os campos abaixo já estão em unidades finais — NÃO recalcule:

FINANCEIROS (em R$, use diretamente):
- spend          → gasto total em R$ (ex: 94.04 → "R$ 94,04")
- cpc            → custo por clique em R$ (ex: 2.14 → "R$ 2,14")
- cpm            → custo por mil impressões em R$ (ex: 6.97 → "R$ 6,97")
- cost_per_message, cost_per_lead, cost_per_result → em R$
- purchase_value, roas → valor de compras e retorno

PERCENTUAIS (use direto, já em %):
- ctr            → ex: 0.87 → exiba "0,87%"  (NÃO multiplique por 100)
- frequency      → ex: 2.94 → exiba "2,94x"

CONTAGENS (inteiros):
- impressions, reach, clicks, unique_clicks, link_clicks, landing_views

CONVERSÕES E AÇÕES:
- messages       → conversas WhatsApp/Messenger iniciadas (resultado principal de campanhas de mensagem)
- whatsapp_clicks → cliques no botão WhatsApp
- leads, purchases, registrations, cart, checkout
- post_engagement, post_reactions, comments, shares
- video_views, video_p25/50/75/100, thruplay
- profile_visits → visitas ao perfil do Instagram

O campo _main_result traz: key, label, emoji, qty, cost_per_result.
SEMPRE use _metrics (ou _parsed_actions) — NUNCA tente parsear o array actions[] bruto.

EXEMPLO CORRETO:
"Campanha X: gasto R$ 94,04 | 10 mensagens (R$ 9,40/msg) | alcance 5.283 | impressões 15.713 | CTR 0,87% | CPM R$ 6,97 | CPC R$ 2,14 | orçamento diário R$ 21,00/dia"
SYSPROMPT;
    }


    // ── Helpers de criativo ───────────────────────────────────────────────────
    private static function buildCTA(string $ctaType, string $destType, string $linkUrl, array $a): array {
        $value = [];

        switch ($destType) {
            case 'WHATSAPP':
            case 'WHATSAPP_BUSINESS':
                $wNum = preg_replace('/\D/', '', $a['whatsapp_number'] ?? '');
                $value = ['app_destination' => 'WHATSAPP', 'link' => 'https://wa.me/'.$wNum];
                break;
            case 'MESSENGER':
                $value = ['app_destination' => 'MESSENGER'];
                break;
            case 'INSTAGRAM_DIRECT':
            case 'INSTAGRAM_PROFILE':
                $value = ['app_destination' => 'INSTAGRAM_DIRECT'];
                break;
            default:
                // WhatsApp via wa.me com destType=WEBSITE (sem WABA)
                if ($ctaType === 'WHATSAPP_MESSAGE' && empty($value)) {
                    $wNum = preg_replace('/\D/', '', $a['whatsapp_number'] ?? '');
                    if ($wNum) $value = ['link' => 'https://wa.me/'.$wNum];
                    elseif (!empty($linkUrl)) $value = ['link' => $linkUrl];
                } elseif (!empty($linkUrl)) {
                    $value = ['link' => $linkUrl];
                }
                break;
        }

        // CTA map: normaliza tipos comuns
        $ctaMap = [
            'WHATSAPP'         => 'WHATSAPP_MESSAGE',
            'WHATSAPP_MESSAGE' => 'WHATSAPP_MESSAGE',
            'MESSAGE'          => 'MESSAGE_PAGE',
            'MESSENGER'        => 'MESSAGE_PAGE',
            'COMPRAR'          => 'SHOP_NOW',
            'COMPRE'           => 'SHOP_NOW',
            'SAIBA_MAIS'       => 'LEARN_MORE',
            'SAIBA MAIS'       => 'LEARN_MORE',
            'CADASTRE'         => 'SIGN_UP',
            'INSCREVA'         => 'SUBSCRIBE',
            'BAIXAR'           => 'DOWNLOAD',
            'LIGAR'            => 'CALL_NOW',
            'VER_MAIS'         => 'SEE_MORE',
        ];
        $normalizedCta = $ctaMap[$ctaType] ?? $ctaType;

        return ['type' => $normalizedCta, 'value' => $value];
    }

    // ── Busca page_id vinculado à conta de anúncios ─────────────────────────
    private static function getPageId(string $b, string $act, string $tk): string {
        // 1. /me/accounts — páginas do usuário dono do token
        $r1 = self::mGet("{$b}/me/accounts?fields=id,name&access_token=".urlencode($tk));
        if (!empty($r1['data'])) {
            foreach ($r1['data'] as $page) {
                if (!empty($page['id'])) return (string)$page['id'];
            }
        }

        // 2. páginas vinculadas à conta de anúncios diretamente
        $r2 = self::mGet("{$b}/{$act}/promoted_objects?access_token=".urlencode($tk));
        if (!empty($r2['data'])) {
            foreach ($r2['data'] as $obj) {
                if (!empty($obj['page_id'])) return (string)$obj['page_id'];
            }
        }

        // 3. Tenta via business do adaccount
        $r3 = self::mGet("{$b}/{$act}?fields=business&access_token=".urlencode($tk));
        $bizId = $r3['business']['id'] ?? '';
        if ($bizId) {
            $r4 = self::mGet("{$b}/{$bizId}/owned_pages?fields=id,name&access_token=".urlencode($tk));
            if (!empty($r4['data'][0]['id'])) return (string)$r4['data'][0]['id'];
            $r5 = self::mGet("{$b}/{$bizId}/client_pages?fields=id,name&access_token=".urlencode($tk));
            if (!empty($r5['data'][0]['id'])) return (string)$r5['data'][0]['id'];
        }

        // 4. Busca campanhas existentes e pega page_id de promoted_object
        $r6 = self::mGet("{$b}/{$act}/campaigns?fields=promoted_object&limit=5&access_token=".urlencode($tk));
        if (!empty($r6['data'])) {
            foreach ($r6['data'] as $camp) {
                $pid = $camp['promoted_object']['page_id'] ?? '';
                if ($pid) return (string)$pid;
            }
        }

        return '';
    }

    /**
     * Busca o número de WhatsApp vinculado à página do Facebook na conta.
     * O promoted_object.whatsapp_phone_number DEVE ser um número já cadastrado
     * no Meta Business — não pode ser qualquer número informado pelo usuário.
     * Retorna o número no formato E.164 sem '+' (ex: 5581999999999) ou '' se não encontrado.
     */
    private static function getLinkedWhatsAppNumber(string $b, string $pageId, string $tk): string {
        if (empty($pageId)) return '';

        // Busca o número de WhatsApp vinculado à página via /page_id?fields=whatsapp_number
        $r = self::mGet("{$b}/{$pageId}?fields=whatsapp_number&access_token=".urlencode($tk));
        $num = $r['whatsapp_number'] ?? '';
        if ($num) {
            // Remove tudo que não é dígito e garante DDI
            $num = preg_replace('/\D/', '', $num);
            return $num;
        }

        // Fallback: busca via connected_instagram_account ou whatsapp_business_account
        $r2 = self::mGet("{$b}/{$pageId}?fields=connected_instagram_account,whatsapp_business_account&access_token=".urlencode($tk));
        $wba = $r2['whatsapp_business_account']['id'] ?? '';
        if ($wba) {
            $r3 = self::mGet("{$b}/{$wba}/phone_numbers?fields=display_phone_number&access_token=".urlencode($tk));
            $phone = $r3['data'][0]['display_phone_number'] ?? '';
            if ($phone) return preg_replace('/\D/', '', $phone);
        }

        return '';
    }

        private static function mGet(string $url): array {
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>false]);
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $d=json_decode($resp,true)??[];
        if($code!==200) return ['error'=>$d['error']['message']??"HTTP $code",'code'=>$code];
        return $d;
    }

    private static function mPost(string $url, array $body, bool $useJson = false): array {
        if ($useJson) {
            // Extrai o token da URL e move para o header Authorization (exigido pela API Meta v21+ com JSON body)
            $token = '';
            if (preg_match('/[?&]access_token=([^&]+)/', $url, $m)) {
                $token = urldecode($m[1]);
                $url = preg_replace('/([?&])access_token=[^&]+(&|$)/', '$1', $url);
                $url = rtrim($url, '?&');
            }
            // DEBUG LOG — captura payload enviado para a API Meta (remover em produção)
            $debugBody = $body;
            error_log('[AdsAgent][mPost] URL: ' . $url . ' | BODY: ' . json_encode($debugBody, JSON_UNESCAPED_UNICODE));
            $headers = ['Content-Type: application/json'];
            if ($token) $headers[] = 'Authorization: Bearer ' . $token;
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>json_encode($body),
                CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_TIMEOUT=>25, CURLOPT_SSL_VERIFYPEER=>false]);
        } else {
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>http_build_query($body),CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>false]);
        }
        $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $d=json_decode($resp,true)??[];
        if($code!==200) {
            $msg = $d['error']['message'] ?? "HTTP $code";
            $sub = $d['error']['error_user_msg'] ?? ($d['error']['error_subcode'] ?? '');
            return ['error'=> $msg.($sub?" ($sub)":''), 'code'=>$code, 'meta_error'=>$d['error']??[]];
        }
        return array_merge(['success'=>true],$d);
    }

    private static function getKey(array $s, string $p): string {
        $m=['openai'=>'ai_openai_key','anthropic'=>'ai_anthropic_key','groq'=>'ai_groq_key','gemini'=>'ai_gemini_key'];
        $v=$s[$m[$p]??'']??''; return $v?TokenCrypto::decrypt($v):'';
    }

    private static function getAvailableModels(): array {
        return [
            'openai'=>[['id'=>'gpt-4.1','name'=>'GPT-4.1 ✨','price'=>'~$0.002/1k'],['id'=>'gpt-4o','name'=>'GPT-4o','price'=>'~$0.0025/1k'],['id'=>'gpt-4.1-mini','name'=>'GPT-4.1 Mini','price'=>'~$0.0004/1k'],['id'=>'gpt-4o-mini','name'=>'GPT-4o Mini','price'=>'~$0.00015/1k']],
            'anthropic'=>[['id'=>'claude-sonnet-4-20250514','name'=>'Claude Sonnet 4 ✨','price'=>'~$0.003/1k'],['id'=>'claude-haiku-4-5-20251001','name'=>'Claude Haiku 4.5','price'=>'~$0.0008/1k']],
        ];
    }

    private static function ensureTables($db): void {
        try {
            $db->query("CREATE TABLE IF NOT EXISTS ads_agent_conversations (id int UNSIGNED NOT NULL AUTO_INCREMENT, user_id int UNSIGNED NOT NULL, account_id int UNSIGNED NOT NULL, title varchar(255) NOT NULL DEFAULT 'Nova conversa', provider varchar(30) NOT NULL DEFAULT 'openai', model varchar(100) NOT NULL DEFAULT 'gpt-4o-mini', messages longtext DEFAULT NULL, total_tokens int UNSIGNED NOT NULL DEFAULT 0, created_at datetime NOT NULL DEFAULT current_timestamp(), updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (id), KEY idx_ua (user_id, account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->query("CREATE TABLE IF NOT EXISTS ads_agent_actions (id int UNSIGNED NOT NULL AUTO_INCREMENT, user_id int UNSIGNED NOT NULL, account_id int UNSIGNED NOT NULL, conversation_id int UNSIGNED DEFAULT NULL, action_type varchar(80) NOT NULL, entity_type varchar(30) NOT NULL DEFAULT '', entity_id varchar(100) NOT NULL DEFAULT '', entity_name varchar(255) NOT NULL DEFAULT '', params_json text DEFAULT NULL, result_json text DEFAULT NULL, status enum('success','error','pending','cancelled') NOT NULL DEFAULT 'pending', error_msg text DEFAULT NULL, confirmed_by varchar(30) NOT NULL DEFAULT 'user', executed_at datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id), KEY idx_ua (user_id, account_id), KEY idx_st (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {}
    }
}
