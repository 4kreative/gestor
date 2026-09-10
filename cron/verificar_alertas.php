<?php
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) { die('nope'); }

header('Content-Type: application/json');
require_once __DIR__.'/../config/database.php';
$db = Database::getInstance();

$info  = $db->query("SELECT DATABASE() as db")->fetch();
$total = $db->query("SELECT COUNT(*) as n FROM alerts")->fetch();
$lista = $db->query("SELECT id, name, ativo FROM alerts")->fetchAll();

echo json_encode(['db'=>$info,'total'=>$total,'lista'=>$lista], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
