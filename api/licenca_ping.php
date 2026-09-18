<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once dirname(__DIR__) . '/core/App.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'licenca' => false]);
    exit;
}

try {
    $db  = Database::getInstance()->getConnection();
    $st  = $db->query("SELECT * FROM sys_licenca ORDER BY id DESC LIMIT 1");
    $lic = $st->fetch();
} catch (Exception $e) {
    echo json_encode(['ok' => true]);
    exit;
}

if (!$lic || !$lic->ativo) {
    echo json_encode(['ok' => false, 'licenca' => false]);
    exit;
}

if ($lic->data_vencimento < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'licenca' => false]);
    exit;
}

if (!defined('LH_API_URL') || !defined('LH_SECRET_KEY')) {
    echo json_encode(['ok' => true]);
    exit;
}

$chave  = $lic->chave;
$domain = strtolower(trim(preg_replace(['#^https?://#i','#^www\.#i','#/.*$#','#:\d+$#'], '', $_SERVER['HTTP_HOST'] ?? '')));
$ts     = time();
$sig    = hash_hmac('sha256', $chave . '|' . $domain . '|' . $ts, LH_SECRET_KEY);

$ch = curl_init(LH_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'acao' => 'validar', 'chave' => $chave, 'dominio' => $domain,
        'ts' => $ts, 'sig' => $sig, 'sistema' => 'gestorads',
    ]),
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$resp = curl_exec($ch);
curl_close($ch);

$ret = json_decode($resp ?: '{}', true);

if (!empty($ret['ok'])) {
    try { $db->exec("UPDATE sys_licenca SET ultima_checagem=UTC_TIMESTAMP()"); } catch(Exception $e){}
    echo json_encode(['ok' => true]);
} else {
    session_destroy();
    echo json_encode(['ok' => false, 'licenca' => false]);
}
