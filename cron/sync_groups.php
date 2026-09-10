<?php
/**
 * cron/sync_groups.php
 * Sincroniza grupos do WhatsApp — versão incremental para cron-job.org
 * 
 * Lógica: só busca da API se o cache tiver mais de X horas
 * Rode a cada 1h no cron-job.org — só vai chamar a API 1x por dia
 * 
 * URL: /cron/sync_groups.php?key=SEU_CRON_SECRET
 * Via Hostinger CLI: php .../cron/sync_groups.php cron
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/EvolutionApi.php';

$isCli = (php_sapi_name() === 'cli');
$key   = $isCli ? CRON_SECRET : ($_GET['key'] ?? '');
if (!$isCli) header('Content-Type: application/json');
if ($key !== CRON_SECRET) { http_response_code(403); die(json_encode(['error'=>'Unauthorized'])); }

// Quantas horas entre cada sync real (padrão 23h — 1x por dia)
$SYNC_INTERVAL_HOURS = 23;

// Timeout da chamada Evolution — reduzido para não estourar o cron-job.org
define('GROUPS_CURL_TIMEOUT', 25);

set_time_limit(60);

$db  = Database::getInstance();
$log = [];
$log[] = 'sync_groups — ' . date('Y-m-d H:i:s');

// Busca instâncias conectadas
$instancias = $db->query(
    "SELECT wi.*, u.id as user_id
     FROM whatsapp_instances wi
     JOIN users u ON wi.user_id = u.id
     WHERE wi.status = 'connected'
     ORDER BY wi.id"
)->fetchAll();

$log[] = "Instâncias: " . count($instancias);

$totalGrupos = 0;
$totalSkip   = 0;
$totalErros  = 0;

foreach ($instancias as $inst) {
    $uid  = $inst['user_id'];
    $wpId = $inst['id'];
    $name = $inst['instance_name'];

    // Verifica quando foi o último sync desta instância
    $lastSync = $db->query(
        "SELECT MAX(synced_at) as last FROM whatsapp_groups_cache WHERE whatsapp_id = ?",
        [$wpId]
    )->fetchColumn();

    // Força sync se ?force=1 na URL
    $force = isset($_GET['force']) || (isset($argv[1]) && $argv[1] === 'force');

    if ($lastSync && !$force) {
        $diffHoras = (time() - strtotime($lastSync)) / 3600;
        if ($diffHoras < $SYNC_INTERVAL_HOURS) {
            $log[] = "SKIP instância #{$wpId} ({$name}): último sync há " . round($diffHoras, 1) . "h (< {$SYNC_INTERVAL_HOURS}h)";
            $totalSkip++;
            continue;
        }
    }

    $log[] = "Sincronizando instância #{$wpId} ({$name})...";

    // Busca grupos com timeout reduzido
    $url = rtrim(EVOLUTION_API_URL, '/') . '/group/fetchAllGroups/' . $name . '?getParticipants=false';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GROUPS_CURL_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['apikey: ' . EVOLUTION_API_KEY, 'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$raw) {
        $log[] = "ERRO #{$wpId} ({$name}): " . ($err ?: 'resposta vazia');
        $totalErros++;
        continue;
    }

    $res = json_decode($raw, true);
    if (!is_array($res)) {
        $log[] = "ERRO #{$wpId} ({$name}): JSON inválido";
        $totalErros++;
        continue;
    }

    // Apaga cache antigo e regrava
    $db->query("DELETE FROM whatsapp_groups_cache WHERE whatsapp_id = ?", [$wpId]);

    $count = 0;
    foreach ($res as $g) {
        if (!is_array($g)) continue;
        $gid   = $g['id']      ?? '';
        $gname = $g['subject'] ?? $g['id'] ?? '';
        if (!$gid || !str_contains($gid, '@g.us')) continue; // só grupos válidos

        $db->query(
            "INSERT INTO whatsapp_groups_cache (user_id, whatsapp_id, group_id, group_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE group_name = VALUES(group_name), synced_at = NOW()",
            [$uid, $wpId, $gid, $gname]
        );
        $count++;
    }

    $log[] = "Instância #{$wpId} ({$name}): {$count} grupos salvos";
    $totalGrupos += $count;
}

$log[] = "FIM — Sincronizados: {$totalGrupos} grupos | Skipped: {$totalSkip} | Erros: {$totalErros}";

$out = json_encode([
    'success' => true,
    'grupos'  => $totalGrupos,
    'skip'    => $totalSkip,
    'erros'   => $totalErros,
    'log'     => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo $out;
