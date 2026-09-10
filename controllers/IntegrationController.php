<?php
class IntegrationController {

    // ─── API: busca templates do usuário ────────────────────
    public function apiTemplates(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $templates = Database::getInstance()->query(
            "SELECT id, name, content, is_default FROM message_templates WHERE user_id=? ORDER BY is_default DESC, name",
            [$uid]
        )->fetchAll();
        echo json_encode(['success' => true, 'templates' => $templates]);
        exit;
    }

    // ─── API: busca clientes do usuário ──────────────────────
    public function apiClients(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid     = currentUser()['id'];
        $clients = Database::getInstance()->query(
            "SELECT id, name, phone, email FROM clients WHERE user_id=? AND status='active' ORDER BY name",
            [$uid]
        )->fetchAll();
        echo json_encode(['success'=>true,'clients'=>$clients]);
        exit;
    }

    // ─── API: busca grupos da Evolution API ──────────────────
    public function apiGroups(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid  = currentUser()['id'];
        $wpId = (int)($_GET['whatsapp_id'] ?? 0);
        $db   = Database::getInstance();

        $inst = $db->query(
            "SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",
            [$wpId, $uid]
        )->fetch();

        if (!$inst) {
            echo json_encode(['success'=>false,'error'=>'Instância não encontrada.']); exit;
        }

        if ($inst['status'] !== 'connected') {
            echo json_encode(['success'=>false,'error'=>'WhatsApp não está conectado.']); exit;
        }

        // ── Tenta ler do cache local (banco) primeiro ──────────
        $cached = $db->query(
            "SELECT group_id AS id, group_name AS nome
             FROM whatsapp_groups_cache
             WHERE whatsapp_id = ?
             ORDER BY group_name ASC",
            [$wpId]
        )->fetchAll();

        if (!empty($cached)) {
            echo json_encode(['success'=>true,'grupos'=>$cached,'fonte'=>'cache']);
            exit;
        }

        // ── Cache vazio: busca na Evolution API (primeira vez ou cron ainda não rodou) ──
        $list = EvolutionApi::fetchGroups($inst['instance_name']);

        if (empty($list) || !is_array($list)) {
            echo json_encode(['success'=>false,'error'=>'Nenhum grupo encontrado. O cron ainda não sincronizou — aguarde ou acesse o cron manualmente.']); exit;
        }

        $grupos = [];
        foreach ($list as $g) {
            if (!is_array($g)) continue;
            $id   = $g['id'] ?? $g['remoteJid'] ?? '';
            $nome = $g['subject'] ?? $g['name'] ?? $id;
            if (!$id) continue;
            $grupos[] = ['id'=>$id,'nome'=>$nome];
            // Salva no cache para as próximas chamadas
            $db->query(
                "INSERT INTO whatsapp_groups_cache (user_id, whatsapp_id, group_id, group_name)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE group_name = VALUES(group_name), synced_at = NOW()",
                [$uid, $wpId, $id, $nome]
            );
        }
        usort($grupos, fn($a,$b) => strcmp($a['nome'],$b['nome']));

        echo json_encode(['success'=>true,'grupos'=>$grupos]);
        exit;
    }

    // ─── LIST ────────────────────────────────────────────────
    public function index(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $integrations = $db->query(
            "SELECT * FROM integrations WHERE user_id=? ORDER BY type, created_at DESC", [$uid]
        )->fetchAll();
        $whatsapps = $db->query(
            "SELECT * FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC", [$uid]
        )->fetchAll();
        require_once __DIR__.'/../views/integrations/index.php';
    }

    // ─── CREATE ───────────────────────────────────────────────
    public function create(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $type = sanitize($_POST['type'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $wpId = (int)($_POST['whatsapp_id'] ?? 0);
        $dest = sanitize($_POST['recipient_phone'] ?? '');
        $destType = sanitize($_POST['recipient_type'] ?? 'phone');
        $msg  = trim($_POST['message'] ?? '');

        if (!$type || !$name) { jsonResponse(['error'=>'Dados inválidos'],400); }

        $uuid = bin2hex(random_bytes(16));
        // Gera secret_key automaticamente — obrigatório para validação HMAC
        $secretKey = bin2hex(random_bytes(32));

        $endpointPath = match($type) {
            'elementor'     => '/api/integrations/elementor',
            'tintim'        => '/api/integrations/tintim',
            'facebook_lead' => '/api/integrations/facebook_lead',
            'autentique'    => '/api/integrations/autentique',
            default         => '/api/integrations/webhook',
        };
        $endpointBase = APP_URL . $endpointPath . '?uuid=' . $uuid;

        $db->query(
            "INSERT INTO integrations (user_id,type,name,uuid,endpoint,whatsapp_id,recipient_phone,recipient_type,message,secret_key,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,'active')",
            [$uid, $type, $name, $uuid, $endpointBase, $wpId ?: null, $dest ?: null, $destType, $msg ?: null, $secretKey]
        );
        $id = $db->lastId();

        $row = $db->query("SELECT * FROM integrations WHERE id=?", [$id])->fetch();
        jsonResponse(['success'=>true, 'integration'=>$row]);
    }

    // ─── UPDATE ───────────────────────────────────────────────
    public function update(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id  = (int)($_POST['id'] ?? 0);
        $row = $db->query("SELECT * FROM integrations WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if (!$row) { jsonResponse(['error'=>'Not found'],404); }

        $wpId     = (int)($_POST['whatsapp_id'] ?? 0);
        $dest     = sanitize($_POST['recipient_phone'] ?? '');
        $destType = sanitize($_POST['recipient_type'] ?? 'phone');
        $msg      = trim($_POST['message'] ?? '');
        $status   = sanitize($_POST['status'] ?? 'active');
        $name     = sanitize($_POST['name'] ?? '');

        // Atualiza nome se enviado
        if ($name) {
            $db->query(
                "UPDATE integrations SET name=?,whatsapp_id=?,recipient_phone=?,recipient_type=?,message=?,status=?,updated_at=NOW() WHERE id=? AND user_id=?",
                [$name, $wpId ?: null, $dest ?: null, $destType, $msg, $status, $id, $uid]
            );
        } else {
            $db->query(
                "UPDATE integrations SET whatsapp_id=?,recipient_phone=?,recipient_type=?,message=?,status=?,updated_at=NOW() WHERE id=? AND user_id=?",
                [$wpId ?: null, $dest ?: null, $destType, $msg, $status, $id, $uid]
            );
        }
        jsonResponse(['success'=>true]);
    }

    // ─── DELETE ───────────────────────────────────────────────
    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id  = (int)($_POST['id'] ?? 0);
        $db->query("DELETE FROM integrations WHERE id=? AND user_id=?", [$id,$uid]);
        jsonResponse(['success'=>true]);
    }

    // ─── TOGGLE ───────────────────────────────────────────────
    public function toggle(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id  = (int)($_POST['id'] ?? 0);
        $row = $db->query("SELECT status FROM integrations WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if (!$row) { jsonResponse(['error'=>'Not found'],404); }
        $new = $row['status'] === 'active' ? 'inactive' : 'active';
        $db->query("UPDATE integrations SET status=? WHERE id=?", [$new,$id]);
        jsonResponse(['success'=>true,'status'=>$new]);
    }

    // ─── WEBHOOK ENDPOINT (público) ───────────────────────────
    // ─── HELPERS DE SEGURANÇA ─────────────────────────────────

    /**
     * Valida assinatura HMAC de um webhook.
     * Retorna true se a assinatura bater ou se o integration não tiver secret configurado.
     * Formato suportado: X-Hub-Signature-256: sha256=<hex>
     *                    X-Webhook-Signature: <hex>
     *                    X-Signature: <hex>
     */
    private function verificarHmac(string $rawBody, string $secret, string $algo = 'sha256'): bool {
        if (empty($secret)) return false; // sem secret = bloqueia sempre (secret agora é obrigatório)

        $expectedHex = hash_hmac($algo, $rawBody, $secret);

        // Tenta os headers mais comuns em ordem
        $headers = [
            'HTTP_X_HUB_SIGNATURE_256',   // Facebook / GitHub
            'HTTP_X_WEBHOOK_SIGNATURE',    // genérico
            'HTTP_X_SIGNATURE',            // genérico
            'HTTP_X_HUB_SIGNATURE',        // Facebook legacy (sha1)
        ];

        foreach ($headers as $h) {
            $val = $_SERVER[$h] ?? '';
            if (!$val) continue;
            // Remove prefixo "sha256=" ou "sha1=" se existir
            $val = preg_replace('/^[a-z0-9]+=/', '', $val);
            if (hash_equals($expectedHex, $val)) return true;
        }
        return false;
    }

    // Recebe POST de qualquer sistema externo
    public function webhookReceive(): void {
        $uuid = sanitize($_GET['uuid'] ?? '');
        if (!$uuid) { http_response_code(400); echo 'invalid'; exit; }

        $db  = Database::getInstance();
        $row = $db->query(
            "SELECT i.*, i.user_id, wi.instance_name FROM integrations i
             LEFT JOIN whatsapp_instances wi ON wi.id=i.whatsapp_id
             WHERE i.uuid=? AND i.type IN ('webhook','autentique') AND i.status='active' LIMIT 1", [$uuid]
        )->fetch();

        if (!$row) { http_response_code(404); echo 'not found'; exit; }

        $rawBody = file_get_contents('php://input');

        // Valida assinatura HMAC se secret estiver configurado
        if (!empty($row['secret_key']) && !$this->verificarHmac($rawBody, $row['secret_key'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        // Tenta JSON primeiro, depois form_params (Autentique, RD Station, etc.)
        $body = json_decode($rawBody, true);
        if (empty($body)) {
            parse_str($rawBody, $body);
        }
        if (empty($body)) {
            $body = $_POST;
        }

        // Normalização automática para Autentique
        // Detecta tanto form_params quanto JSON direto da Autentique
        $isAutentique = (($body['format'] ?? '') === 'form_params' && ($body['object'] ?? '') === 'webhook')
                     || isset($body['event']['type'])   // JSON direto: { "event": { "type": "signature.accepted" } }
                     || isset($body['event']['data']);  // JSON direto com estrutura de data
        if ($isAutentique) {
            $body = $this->normalizeAutentique($body);
        }

        $this->processAndSend($row, $body);
        http_response_code(200);
        echo json_encode(['ok' => true]);
    }

    // ─── NORMALIZA PAYLOAD DA AUTENTIQUE ──────────────────────
    private function normalizeAutentique(array $body): array {
        // Suporta tanto payload form_params quanto JSON direto da Autentique
        $event     = $body['event'] ?? [];
        $data      = $event['data'] ?? [];
        $user      = $data['user'] ?? [];
        $eventType = $event['type'] ?? '';

        // Fallback: alguns webhooks JSON da Autentique colocam o user direto em $data
        if (empty($user) && !empty($data['name'])) {
            $user = $data;
        }

        // Nome do documento — tenta vários caminhos possíveis da API Autentique
        $docName = $data['document']['name']
                ?? $data['name']
                ?? (is_array($body['document'] ?? null) ? ($body['document']['name'] ?? '') : '')
                ?? $body['name']
                ?? '';

        $signedRaw = $data['signed'] ?? '';
        // Converte UTC para horário de Brasília (UTC-3)
        if ($signedRaw) {
            try {
                $dt = new \DateTime($signedRaw, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                $signedFmt = $dt->format('d/m/Y \\às H:i');
            } catch (\Exception $e) {
                $signedFmt = '';
            }
        } else {
            $signedFmt = '';
        }

        $eventLabel = match($eventType) {
            'signature.accepted' => '✅ Documento Assinado',
            'signature.rejected' => '❌ Documento Recusado',
            'document.viewed'    => '👁️ Documento Visualizado',
            'document.finished'  => '🎉 Documento Finalizado',
            default               => $eventType,
        };

        // Nome do signatário — vários caminhos possíveis
        $signerName = $user['name']
                   ?? (is_array($data['signer'] ?? null) ? ($data['signer']['name'] ?? '') : '')
                   ?? '';

        return array_merge($body, [
            'signer_name'    => $signerName,
            'signer_email'   => $user['email'] ?? (is_array($data['signer'] ?? null) ? ($data['signer']['email'] ?? '') : '') ?? '',
            'signer_cpf'     => $user['cpf']       ?? '',
            'signer_phone'   => $user['phone']     ?? '',
            'signer_company' => $user['company']   ?? '',
            'doc_id'         => $data['public_id'] ?? (is_array($data['document'] ?? null) ? ($data['document']['public_id'] ?? '') : ($data['document'] ?? '')) ?? '',
            'doc_name'       => $docName,
            'doc_action'     => $data['action']    ?? '',
            'doc_signed_at'  => $signedFmt,
            'doc_created_at' => $data['created_at'] ?? '',
            'event_type'     => $eventType,
            'event_label'    => $eventLabel,
            'webhook_name'   => $docName ?: ($body['name'] ?? ''),
        ]);
    }

        // ─── ELEMENTOR ENDPOINT (público) ─────────────────────────
    public function elementorReceive(): void {
        $uuid = sanitize($_GET['uuid'] ?? '');
        if (!$uuid) { http_response_code(400); echo 'invalid'; exit; }

        $db  = Database::getInstance();
        $row = $db->query(
            "SELECT i.*, wi.instance_name FROM integrations i
             LEFT JOIN whatsapp_instances wi ON wi.id=i.whatsapp_id
             WHERE i.uuid=? AND i.type='elementor' AND i.status='active' LIMIT 1", [$uuid]
        )->fetch();

        if (!$row) { http_response_code(404); echo 'not found'; exit; }

        $raw  = file_get_contents('php://input');

        // CORREÇÃO: valida assinatura HMAC se secret estiver configurado
        if (!empty($row['secret_key']) && !$this->verificarHmac($raw, $row['secret_key'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        $body = json_decode($raw, true) ?? [];
        if (empty($body)) { parse_str($raw, $body); }
        if (empty($body)) { $body = $_POST; }

        // Normaliza campos do Elementor para padrão interno
        $normalized = [
            'lead_name'     => $body['fields']['name']['value'] ?? $body['name'] ?? $body['lead_name'] ?? '',
            'lead_email'    => $body['fields']['email']['value'] ?? $body['email'] ?? $body['lead_email'] ?? '',
            'lead_phone'    => $body['fields']['phone']['value'] ?? $body['phone'] ?? $body['lead_phone'] ?? '',
            'lead_formid'   => $body['id'] ?? $body['form_id'] ?? '',
            'lead_formName' => $body['form_name'] ?? '',
            'utm_source'    => $body['utm_source'] ?? '',
            'utm_medium'    => $body['utm_medium'] ?? '',
            'utm_campaign'  => $body['utm_campaign'] ?? '',
            'utm_content'   => $body['utm_content'] ?? '',
            'utm_term'      => $body['utm_term'] ?? '',
        ];
        $data = array_merge($body, $normalized);

        $this->processAndSend($row, $data);
        echo json_encode(['success'=>true]);
    }

    // ─── TINTIM ENDPOINT (público) ────────────────────────────
    public function tintimReceive(): void {
        $uuid = sanitize($_GET['uuid'] ?? '');
        if (!$uuid) { http_response_code(400); echo 'invalid'; exit; }

        $db  = Database::getInstance();
        $row = $db->query(
            "SELECT i.*, wi.instance_name FROM integrations i
             LEFT JOIN whatsapp_instances wi ON wi.id=i.whatsapp_id
             WHERE i.uuid=? AND i.type='tintim' AND i.status='active' LIMIT 1", [$uuid]
        )->fetch();

        if (!$row) { http_response_code(404); echo 'not found'; exit; }

        $rawBody = file_get_contents('php://input');

        // CORREÇÃO: valida assinatura HMAC se secret estiver configurado
        if (!empty($row['secret_key']) && !$this->verificarHmac($rawBody, $row['secret_key'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        $body = json_decode($rawBody, true) ?? [];

        // Normaliza campos do Tintim
        $normalized = [
            'lead_name'  => $body['contact']['name'] ?? $body['name'] ?? '',
            'lead_phone' => $body['contact']['phone'] ?? $body['phone'] ?? '',
            'lead_email' => $body['contact']['email'] ?? $body['email'] ?? '',
            'tintim_id'  => $body['uuid'] ?? $body['id'] ?? '',
            'event'      => $body['event'] ?? 'new_conversation',
        ];
        $data = array_merge($body, $normalized);

        $this->processAndSend($row, $data);
        echo json_encode(['success'=>true]);
    }

    // ─── FACEBOOK LEAD ADS ENDPOINT (público) ─────────────────
    public function facebookLeadReceive(): void {
        // Verificação do webhook do Facebook (GET)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $uuid      = sanitize($_GET['uuid'] ?? '');
            $mode      = $_GET['hub_mode'] ?? '';
            $token     = $_GET['hub_verify_token'] ?? '';
            $challenge = $_GET['hub_challenge'] ?? '';

            $db  = Database::getInstance();
            $row = $db->query("SELECT verify_token FROM integrations WHERE uuid=? AND type='facebook_lead'", [$uuid])->fetch();

            if ($mode === 'subscribe' && $row && $token === $row['verify_token']) {
                echo $challenge;
            } else {
                http_response_code(403);
                echo 'Forbidden';
            }
            exit;
        }

        // Recebe lead (POST)
        $uuid    = sanitize($_GET['uuid'] ?? '');
        $db      = Database::getInstance();
        $row     = $db->query(
            "SELECT i.*, wi.instance_name FROM integrations i
             LEFT JOIN whatsapp_instances wi ON wi.id=i.whatsapp_id
             WHERE i.uuid=? AND i.type='facebook_lead' AND i.status='active' LIMIT 1", [$uuid]
        )->fetch();

        if (!$row) { http_response_code(404); exit; }

        $rawBody = file_get_contents('php://input');

        // CORREÇÃO: valida X-Hub-Signature-256 do Facebook usando META_APP_SECRET
        $fbSig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($fbSig) {
            $expectedFb = 'sha256=' . hash_hmac('sha256', $rawBody, META_APP_SECRET);
            if (!hash_equals($expectedFb, $fbSig)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid Facebook signature']);
                exit;
            }
        }

        $body = json_decode($rawBody, true) ?? [];

        // Extrai campos do lead form do Facebook
        $leadData   = $body['entry'][0]['changes'][0]['value'] ?? [];
        $fieldData  = $leadData['field_data'] ?? [];
        $normalized = ['lead_formid' => $leadData['leadgen_id'] ?? ''];
        foreach ($fieldData as $field) {
            $key = strtolower(str_replace(' ','_',$field['name']));
            $val = $field['values'][0] ?? '';
            $normalized['lead_'.$key] = $val;
            if (in_array($key,['full_name','nome','name'])) $normalized['lead_name'] = $val;
            if (in_array($key,['email','e-mail'])) $normalized['lead_email'] = $val;
            if (in_array($key,['phone_number','telefone','phone','celular'])) $normalized['lead_phone'] = $val;
        }
        $data = array_merge($normalized, $leadData);

        $this->processAndSend($row, $data);
        echo json_encode(['success'=>true]);
    }

    // ─── PROCESSA E ENVIA WHATSAPP ────────────────────────────
    private function processAndSend(array $row, array $data): void {
        if (!$row['whatsapp_id'] || !$row['instance_name']) return;

        $recipient = $row['recipient_phone'];

        // Se tipo cliente, tenta puxar telefone do cliente vinculado à conta de anúncio
        if ($row['recipient_type'] === 'client') {
            $db = Database::getInstance();
            // Tenta identificar a conta de anúncio pelo account_id nos dados recebidos
            $accountId = $data['account_id'] ?? $data['ad_account_id'] ?? null;
            if ($accountId) {
                $client = $db->query(
                    "SELECT c.phone FROM clients c
                     INNER JOIN ad_accounts aa ON aa.client_id = c.id
                     WHERE aa.account_id = ? AND aa.user_id = ? AND c.phone IS NOT NULL LIMIT 1",
                    [$accountId, $row['user_id']]
                )->fetch();
                if ($client && $client['phone']) {
                    $recipient = preg_replace('/\D/', '', $client['phone']);
                }
            }
            // Se não achou pelo account_id, usa o número padrão configurado
            if (!$recipient) return;
        }

        if (!$recipient) return;

        // Monta mensagem substituindo variáveis
        $msg = $row['message'] ?: $this->defaultMessage($row['type'], $data);
        $msg = $this->replaceVars($msg, $data);

        // Salva log e envia
        $db = Database::getInstance();
        $sendError = null;

        try {
            $this->sendWhatsApp($row['instance_name'], $recipient, $row['recipient_type'] === 'group' ? 'group' : 'phone', $msg);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
        }

        $logStatus = $sendError ? 'error' : 'sent';
        $db->query(
            "INSERT INTO integration_logs (integration_id,payload,message_sent,status) VALUES (?,?,?,?)",
            [$row['id'], json_encode($data), $msg, $logStatus]
        );

        // ── Monta info do signatário/documento para notificação ──
        $signerName = $data['signer_name'] ?? $data['lead_name'] ?? $data['name'] ?? '';
        $docName    = $data['doc_name']    ?? $data['webhook_name'] ?? $data['doc_id'] ?? '';
        $eventLabel = $data['event_label'] ?? '';

        // Linha de detalhe: "João Silva · 📄 Contrato X · ✅ Documento Assinado"
        $detail = trim(implode(' · ', array_filter([
            $signerName,
            $docName    ? '📄 ' . $docName    : '',
            $eventLabel ?: '',
        ])));

        // Notificação interna — sucesso E erro ambos aparecem no sino
        try {
            if ($sendError) {
                $notifTitle = '⚠️ Falha na integração: ' . $row['name'];
                $notifBody  = ($detail ? $detail . "
" : '') . 'Erro: ' . mb_substr($sendError, 0, 180);
                $notifType  = 'error';
            } else {
                $notifTitle = '🔗 Disparo enviado: ' . $row['name'];
                $notifBody  = $detail ?: 'Webhook recebido e mensagem enviada com sucesso.';
                $notifType  = 'success';
            }
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, ?, ?, ?)",
                [$row['user_id'], $notifType, $notifTitle, $notifBody]
            );
        } catch (\Throwable $_e) {}
    }

    private function replaceVars(string $msg, array $data): string {
        // Suporta {{var}} e {var}
        $flat = $this->flatten($data);
        foreach ($flat as $k => $v) {
            $msg = str_replace(['{{'.$k.'}}', '{'.$k.'}'], $v, $msg);
        }
        return $msg;
    }

    private function flatten(array $arr, string $prefix = ''): array {
        $result = [];
        foreach ($arr as $k => $v) {
            $key = $prefix ? $prefix.'_'.$k : $k;
            if (is_array($v)) {
                $result += $this->flatten($v, $key);
            } else {
                $result[$key] = (string)$v;
            }
        }
        return $result;
    }

    private function defaultMessage(string $type, array $data): string {
        $name  = $data['lead_name'] ?? $data['name'] ?? 'Visitante';
        $email = $data['lead_email'] ?? $data['email'] ?? '';
        $phone = $data['lead_phone'] ?? $data['phone'] ?? '';

        return match($type) {
            'tintim'        => "💬 *Nova conversa no Tintim!*\n\nContato: {$name}\nTelefone: {$phone}",
            'facebook_lead' => "📋 *Novo lead do Facebook Ads!*\n\nNome: {$name}\nEmail: {$email}\nTelefone: {$phone}",
            default         => "🔔 *Novo lead recebido!*\n\nNome: {$name}\nEmail: {$email}\nTelefone: {$phone}",
        };
    }

    private function sendWhatsApp(string $instance, string $recipient, string $type, string $message): void {
        if ($type === 'group') {
            $endpoint = '/message/sendText/'.$instance;
            $payload  = ['number'=>$recipient,'text'=>$message];
        } else {
            $endpoint = '/message/sendText/'.$instance;
            $clean    = preg_replace('/\D/','',$recipient);
            $payload  = ['number'=>$clean,'text'=>$message];
        }

        $url = rtrim(EVOLUTION_API_URL,'/').$endpoint;
        $ch  = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json','apikey: '.EVOLUTION_API_KEY],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
