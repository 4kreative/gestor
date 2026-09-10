<?php
/**
 * clear_cache_status.php — Status read-only do cache (sem limpar nada)
 * Requer autenticação de admin.
 */
require_once __DIR__ . '/core/App.php';
requireAdmin();

header('Content-Type: application/json');

// ── OPcache stats ──────────────────────────────────────────────────────────
$opcache_stats = ['enabled' => false];
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    if ($s && !empty($s['opcache_enabled'])) {
        $opcache_stats = [
            'enabled'         => true,
            'scripts_cached'  => $s['opcache_statistics']['num_cached_scripts'] ?? 0,
            'memory_used'     => round((($s['memory_usage']['used_memory'] ?? 0)) / 1048576, 1),
        ];
    }
}

// ── Tamanho do log de erros ──────────────────────────────────────────────────
$logPath = ini_get('error_log');
if (!$logPath) {
    $user = explode('/', __DIR__)[2] ?? '';
    $logPath = "/home/{$user}/.logs/error_log_gestorads";
}
$error_log_kb = 0;
if ($logPath && file_exists($logPath)) {
    $error_log_kb = round(filesize($logPath) / 1024, 1);
}

// ── Contagem de logs de erro ──────────────────────────────────────────────────
$logs_erros = 0;
try {
    $db = Database::getInstance();
    $alert_erros   = $db->query("SELECT COUNT(*) as c FROM alert_logs WHERE status='erro'")->fetch()['c'] ?? 0;
    $report_erros  = $db->query("SELECT COUNT(*) as c FROM report_logs WHERE status='erro'")->fetch()['c'] ?? 0;
    // Erros visíveis na tela (relatórios com falha de envio + alertas com reenvio pendente)
    $reports_erros = $db->query("SELECT COUNT(*) as c FROM reports WHERE last_send_status = 'error'")->fetch()['c'] ?? 0;
    $alerts_erros  = $db->query("SELECT COUNT(*) as c FROM alerts WHERE ultimo_erros_hash IS NOT NULL")->fetch()['c'] ?? 0;
    $logs_erros    = (int)$alert_erros + (int)$report_erros + (int)$reports_erros + (int)$alerts_erros;
} catch (\Throwable $e) {}

echo json_encode([
    'ok'             => true,
    'opcache_stats'  => $opcache_stats,
    'error_log_kb'   => $error_log_kb,
    'logs_erros'     => $logs_erros,
    'time'           => date('H:i:s'),
]);
