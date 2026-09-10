<?php
/**
 * Webhook WhatsApp — Evolution API
 * Este arquivo é chamado diretamente pela Evolution API via POST
 * Configure o webhook no Evolution: POST http://seudominio.com.br/api/webhook/whatsapp
 */

require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/helpers.php';

// Ignora GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    exit;
}

$event        = $payload['event']    ?? '';
$instanceName = $payload['instance'] ?? '';

try {
    $db = Database::getInstance();

    switch ($event) {
        case 'connection.update':
        case 'status.instance':
            $state  = $payload['data']['state'] ?? 'unknown';
            $phone  = $payload['data']['profileName'] ?? null;
            $status = match($state) {
                'open'  => 'connected',
                'close' => 'disconnected',
                default => 'qr_pending',
            };
            if ($instanceName) {
                $db->query(
                    "UPDATE whatsapp_instances SET status=?, phone_number=? WHERE instance_name=?",
                    [$status, $phone, $instanceName]
                );
            }
            break;

        case 'qrcode.updated':
            $qr = $payload['data']['qrcode']['base64'] ?? null;
            if ($instanceName && $qr) {
                $db->query(
                    "UPDATE whatsapp_instances SET qr_code=?, status='qr_pending' WHERE instance_name=?",
                    [$qr, $instanceName]
                );
            }
            break;

        case 'messages.upsert':
            // Futuro: processar mensagens recebidas
            break;
    }

} catch (\Exception $e) {
    // Log silencioso em produção
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log('Webhook error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);
