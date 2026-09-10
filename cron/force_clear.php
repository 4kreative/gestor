<?php
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_GET['key'] ?? '';
if ($key !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

require_once __DIR__.'/../config/database.php';
$db = Database::getInstance();

// Apaga TUDO do dashboard_cache exceto cron_sync_log
$db->query("DELETE FROM dashboard_cache WHERE cache_key != 'cron_sync_log'");

// Limpa OPcache
if (function_exists('opcache_reset')) opcache_reset();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'msg' => 'Cache completo limpo + OPcache resetado']);
