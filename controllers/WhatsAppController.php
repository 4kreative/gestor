<?php
class WhatsAppController {
    public function index(): void {
        requireAuth();
        $uid       = currentUser()['id'];
        $instances = Database::getInstance()->query(
            "SELECT * FROM whatsapp_instances WHERE user_id=? ORDER BY is_default DESC, created_at DESC", [$uid]
        )->fetchAll();
        require_once __DIR__.'/../views/whatsapp/index.php';
    }

    public function create(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $name = sanitize($_POST['instance_name'] ?? '');

        if (!$name) { flash('error','Nome da instância obrigatório.'); redirect('/whatsapp'); }
        $name = preg_replace('/[^a-z0-9_-]/i','_',$name);

        // Verifica limite do plano
        $count = (int)$db->query("SELECT COUNT(*) FROM whatsapp_instances WHERE user_id=?",[$uid])->fetchColumn();
        if (!planAllows('whatsapp',$count)) { flash('error','Limite de instâncias do seu plano atingido.'); redirect('/whatsapp'); }

        // Cria instância na Evolution API
        $result = EvolutionApi::createInstance($name.'_'.$uid, EVOLUTION_WEBHOOK_URL);

        // Verifica erro explícito da API
        if (isset($result['ok']) && $result['ok'] === false) {
            $errMsg = $result['error'] ?? 'Erro desconhecido';
            flash('error', 'Erro na Evolution API: ' . $errMsg);
            redirect('/whatsapp');
        }

        // Compatibilidade com Evolution API v1 e v2
        $instanceName = null;
        if (isset($result['instance']['instanceName'])) {
            $instanceName = $result['instance']['instanceName'];
        } elseif (isset($result['instanceName'])) {
            $instanceName = $result['instanceName'];
        } elseif (isset($result['instance']['instance']['instanceName'])) {
            $instanceName = $result['instance']['instance']['instanceName'];
        }

        if (!$instanceName) {
            // Fallback: usa o nome enviado e verifica se foi criada
            $instanceName = $name . '_' . $uid;
            $stateResult = EvolutionApi::connectionState($instanceName);
            if (!isset($stateResult['ok']) || $stateResult['ok'] === false) {
                flash('error', 'Não foi possível criar a instância. Verifique se a Evolution API está online e a chave de API está correta.');
                redirect('/whatsapp');
            }
        }

        // Evita duplicata no banco
        $exists = $db->query(
            "SELECT id FROM whatsapp_instances WHERE instance_name=? AND user_id=?",
            [$instanceName, $uid]
        )->fetch();

        if (!$exists) {
            $db->query(
                "INSERT INTO whatsapp_instances (user_id,instance_name,status) VALUES (?,?,'qr_pending')",
                [$uid, $instanceName]
            );
        }

        flash('success','Instância criada! Escaneie o QR Code para conectar.');
        redirect('/whatsapp');
    }

    public function qr(): void {
        requireAuth();
        $uid        = currentUser()['id'];
        $db         = Database::getInstance();
        $instanceId = (int)($_GET['id'] ?? 0);
        $inst       = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",[$instanceId,$uid])->fetch();
        if (!$inst) { flash('error','Instância não encontrada.'); redirect('/whatsapp'); }

        // Busca QR da Evolution (compatível v1/v2)
        $result = EvolutionApi::connectInstance($inst['instance_name']);
        $qr = $result['base64'] ?? $result['qrcode']['base64'] ?? $result['code'] ?? null;

        if ($qr) {
            if (str_starts_with($qr, 'data:')) {
                $qr = preg_replace('/^data:[^;]+;base64,/', '', $qr);
            }
            $db->query("UPDATE whatsapp_instances SET qr_code=?, status='qr_pending' WHERE id=?", [$qr, $instanceId]);
        }

        require_once __DIR__.'/../views/whatsapp/qr.php';
    }

    public function status(): void {
        requireAuth();
        $uid        = currentUser()['id'];
        $db         = Database::getInstance();
        $instanceId = (int)($_GET['id'] ?? 0);
        $inst       = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",[$instanceId,$uid])->fetch();
        if (!$inst) { jsonResponse(['error'=>'not found'],404); }

        $result = EvolutionApi::connectionState($inst['instance_name']);

        // Compatibilidade v1/v2
        $state = $result['instance']['state']
               ?? $result['state']
               ?? $result['instance']['connectionStatus']
               ?? 'unknown';

        $status = match($state) {
            'open'         => 'connected',
            'close','closed' => 'disconnected',
            default        => 'qr_pending',
        };

        $phone = $result['instance']['profileName']
               ?? $result['instance']['wuid']
               ?? $result['profileName']
               ?? null;

        $db->query("UPDATE whatsapp_instances SET status=?, phone_number=? WHERE id=?", [$status, $phone, $instanceId]);

        jsonResponse(['status'=>$status,'phone'=>$phone]);
    }

    public function disconnect(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $id   = (int)($_POST['id'] ?? 0);
        $inst = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?", [$id, $uid])->fetch();
        if (!$inst) {
            flash('error', 'Instância não encontrada.');
            redirect('/whatsapp');
        }
        // Chama logout na Evolution API (desconecta sem deletar)
        EvolutionApi::request('DELETE', '/instance/logout/' . urlencode($inst['instance_name']));
        // Atualiza status no banco para disconnected
        $db->query("UPDATE whatsapp_instances SET status='disconnected', qr_code=NULL WHERE id=?", [$id]);
        flash('success', 'Instância desconectada. Clique em QR Code para reconectar.');
        redirect('/whatsapp');
    }

    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $id   = (int)($_POST['id'] ?? 0);
        $inst = $db->query("SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",[$id,$uid])->fetch();
        if ($inst) {
            EvolutionApi::deleteInstance($inst['instance_name']);
            $db->query("DELETE FROM whatsapp_instances WHERE id=?", [$id]);
        }
        flash('success','Instância removida.');
        redirect('/whatsapp');
    }


    public function test(): void {
        requireAuth();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();

        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['_token'] ?? '';

        // CSRF manual para JSON
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            jsonResponse(['success'=>false,'error'=>'Token inválido.'], 403);
        }

        $instanceId = (int)($input['instance_id'] ?? 0);
        $number     = preg_replace('/\D/', '', $input['number'] ?? '');
        $message    = trim($input['message'] ?? '');

        if (!$number || !$message) {
            jsonResponse(['success'=>false,'error'=>'Número e mensagem são obrigatórios.']);
        }

        $inst = $db->query(
            "SELECT * FROM whatsapp_instances WHERE id=? AND user_id=?",
            [$instanceId, $uid]
        )->fetch();

        if (!$inst) {
            jsonResponse(['success'=>false,'error'=>'Instância não encontrada.']);
        }

        if ($inst['status'] !== 'connected') {
            jsonResponse(['success'=>false,'error'=>'Instância não está conectada. Escaneie o QR Code primeiro.']);
        }

        $result = EvolutionApi::sendText($inst['instance_name'], $number, $message);

        if (!empty($result['ok']) && $result['ok'] === true) {
            jsonResponse(['success'=>true]);
        } else {
            $err = $result['error'] ?? 'Erro ao enviar mensagem.';
            jsonResponse(['success'=>false,'error'=>$err]);
        }
    }

    private function evolutionRequest(string $method, string $endpoint, array $body=[]): ?array {
        return EvolutionApi::request($method, $endpoint, $body);
    }
}
