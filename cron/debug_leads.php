<?php
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_GET['key'] ?? '';
if ($key !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

require_once __DIR__.'/../config/database.php';
$db = Database::getInstance();

// Pega a primeira conta ativa
$acc = $db->query("SELECT * FROM ad_accounts WHERE status='active' LIMIT 1")->fetch();
if (!$acc) die('Nenhuma conta ativa');

require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/TokenCrypto.php';
$acc = decryptTokens([$acc])[0];

$token     = $acc['access_token'];
$accountId = $acc['account_id'];
$start     = '2026-03-19';
$end       = date('Y-m-d');

$fields = 'actions,action_values';
$tr = urlencode(json_encode(['since' => $start, 'until' => $end]));
$attribution = urlencode(json_encode(['1d_click','7d_click']));

$url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}/insights"
     . "?fields={$fields}&time_range={$tr}&level=campaign&limit=100"
     . "&action_attribution_windows={$attribution}"
     . "&access_token=" . urlencode($token);

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

// Agrega todos os action_types
$actionMap = [];
foreach ($res['data'] ?? [] as $row) {
    foreach ($row['actions'] ?? [] as $a) {
        $actionMap[$a['action_type']] = ($actionMap[$a['action_type']] ?? 0) + (float)$a['value'];
    }
}

// Mostra só os action_types relacionados a leads
$leadTypes = [];
foreach ($actionMap as $type => $val) {
    if (stripos($type, 'lead') !== false || stripos($type, 'form') !== false || stripos($type, 'conv') !== false) {
        $leadTypes[$type] = (int)$val;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'conta'       => $acc['account_name'],
    'periodo'     => "$start a $end",
    'lead_types'  => $leadTypes,
    'total_rows'  => count($res['data'] ?? []),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
