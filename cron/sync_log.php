<?php
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_GET['key'] ?? '';
if ($key !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

require_once __DIR__.'/../config/database.php';
$db = Database::getInstance();

$row = $db->query(
    "SELECT payload, expires_at FROM dashboard_cache WHERE cache_key='cron_sync_log' LIMIT 1"
)->fetch();

header('Content-Type: application/json');
if (!$row) {
    echo json_encode(['error' => 'Nenhum log encontrado. Rode o sync primeiro.']);
} else {
    $data = json_decode($row['payload'], true) ?? [];
    echo json_encode([
        'atualizado_em' => $row['expires_at'],
        'log'           => $data['log'] ?? $data,
        'resumo_contas' => array_map(fn($r) => [
            'conta'    => $r['account_name'],
            'plataforma' => $r['platform'],
            'ultima_sync' => $r['ultima_sync'],
            'dias_banco' => (int)$r['dias'],
            'mensagens'  => (int)$r['msgs'],
            'gasto'      => 'R$'.$r['spend'],
        ], $data['resumo'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
