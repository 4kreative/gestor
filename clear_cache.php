<?php
/**
 * clear_cache.php — Limpeza completa de cache do GestorADS
 * Requer autenticação de admin.
 */
require_once __DIR__ . '/core/App.php';
requireAdmin();

header('Content-Type: application/json');

// Só aceita POST com CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}
csrfCheck();

$results = [];
$errors  = [];

$type = sanitize($_POST['type'] ?? 'all');
$skip = [
    'opcache'   => !empty($_POST['skip_opcache']),
    'session'   => !empty($_POST['skip_session']),
    'errorlog'  => !empty($_POST['skip_errorlog']),
    'campaigns' => !empty($_POST['skip_campaigns']),
    'db'        => !empty($_POST['skip_db']),
    'logs'      => !empty($_POST['skip_logs']),
];

// ── 1. OPcache ────────────────────────────────────────────────────────────────
if (($type === 'all' || $type === 'opcache') && empty($skip['opcache'])) {
    if (function_exists('opcache_reset')) {
        $ok = opcache_reset();
        $results['opcache'] = $ok ? 'limpo' : 'falhou';
    } elseif (function_exists('apc_clear_cache')) {
        apc_clear_cache();
        apc_clear_cache('user');
        $results['opcache'] = 'APC limpo';
    } else {
        $results['opcache'] = 'não disponível';
    }
}

// ── 2. Cache de sessão PHP (dados do usuário, plan check) ────────────────────
if (($type === 'all' || $type === 'session') && empty($skip['session'])) {
    $keysToReset = [
        '_plan_last_check',
        '_last_activity',
        'plan_expires_at',
    ];
    foreach ($keysToReset as $k) {
        unset($_SESSION[$k]);
    }
    $results['session'] = 'cache de sessão limpo (' . implode(', ', $keysToReset) . ')';
}

// ── 3. Log de erros PHP ──────────────────────────────────────────────────────
if (($type === 'all' || $type === 'errorlog') && empty($skip['errorlog'])) {
    $logPath = ini_get('error_log');
    if (!$logPath) {
        // Tentar o caminho padrão da Hostinger
        $user = explode('/', __DIR__)[2] ?? '';
        $logPath = "/home/{$user}/.logs/error_log_gestorads";
    }
    if ($logPath && file_exists($logPath)) {
        if (is_writable($logPath)) {
            file_put_contents($logPath, '');
            $results['errorlog'] = 'log de erros zerado (' . basename($logPath) . ')';
        } else {
            $errors[] = 'Log sem permissão de escrita: ' . $logPath;
            $results['errorlog'] = 'sem permissão';
        }
    } else {
        $results['errorlog'] = 'arquivo não encontrado';
    }
}

// ── 4. Cache de campanhas na sessão ──────────────────────────────────────────
if (($type === 'all' || $type === 'campaigns') && empty($skip['campaigns'])) {
    $removed = 0;
    foreach ($_SESSION as $k => $v) {
        if (str_starts_with($k, 'camps_') || str_starts_with($k, 'metrics_') || str_starts_with($k, 'rt_')) {
            unset($_SESSION[$k]);
            $removed++;
        }
    }
    $results['campaigns'] = "cache de campanhas limpo ({$removed} entradas)";
}

// ── 5. Tabelas temporárias do banco ──────────────────────────────────────────
if (($type === 'all' || $type === 'db') && empty($skip['db'])) {
    try {
        $db = Database::getInstance();
        $uid = currentUser()['id'];
        // Limpa notificações lidas com mais de 7 dias
        $r1 = $db->query("DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id = ?", [$uid]);
        // Limpa logs de alertas com mais de 30 dias
        $r2 = $db->query("DELETE FROM alert_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_id = ?", [$uid]);
        // Limpa cache do dashboard (widget_metrics, metrics_*, cron_sync_log)
        $r3 = $db->query("DELETE FROM dashboard_cache WHERE user_id = ? OR cache_key LIKE 'widget_metrics_%' OR cache_key LIKE 'metrics_%' OR cache_key IN ('cron_sync_log','cron_sync_idx')", [$uid]);
        $results['db'] = 'banco limpo (notificações lidas antigas, logs de alertas antigos e todo o cache do dashboard)';
    } catch (\Throwable $e) {
        $errors[] = 'DB: ' . $e->getMessage();
        $results['db'] = 'erro ao limpar banco';
    }
}

// ── 6. Logs de erro de envios (alertas, relatórios, integrações) ─────────────
if (($type === 'all' || $type === 'logs') && empty($skip['logs'])) {
    try {
        $db  = $db ?? Database::getInstance();
        $uid = $uid ?? currentUser()['id'];
        // Apaga todos os registros de erro de alerta e relatório
        $l1 = $db->query("DELETE FROM alert_logs WHERE status = 'erro' AND user_id = ?", [$uid]);
        $l2 = $db->query("DELETE FROM report_logs WHERE status = 'erro' AND user_id = ?", [$uid]);
        // Limpa o erro visível na tela de relatórios (coluna "PRÓX. ENVIO")
        $l3 = $db->query("UPDATE reports SET last_send_status = NULL, last_send_error = NULL WHERE last_send_status = 'error' AND user_id = ?", [$uid]);
        // Reseta o erro pendente de reenvio inteligente nos alertas
        $l4 = $db->query("UPDATE alerts SET ultimo_erros_hash = NULL, proximo_reenvio_erro = NULL WHERE ultimo_erros_hash IS NOT NULL AND user_id = ?", [$uid]);
        // Apaga logs de integração antigos (> 90 dias)
        try { $db->query("DELETE FROM integration_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"); } catch (\Throwable $e) {}
        // Apaga ai_logs antigos (> 60 dias)
        try { $db->query("DELETE FROM ai_logs WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)", [$uid]); } catch (\Throwable $e) {}
        $total = ($l1 ? $l1->rowCount() : 0) + ($l2 ? $l2->rowCount() : 0)
               + ($l3 ? $l3->rowCount() : 0) + ($l4 ? $l4->rowCount() : 0);
        $results['logs'] = "logs de erro limpos ({$total} registros removidos/atualizados)";
    } catch (\Throwable $e) {
        $errors[] = 'Logs: ' . $e->getMessage();
        $results['logs'] = 'erro ao limpar logs';
    }
}

// ── 7. Estatísticas ─────────────────────────────────────────────────────────
$stats = [];
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    if ($s) {
        $stats['opcache_hits']   = number_format($s['opcache_statistics']['hits'] ?? 0);
        $stats['opcache_misses'] = number_format($s['opcache_statistics']['misses'] ?? 0);
        $stats['scripts_cached'] = number_format($s['opcache_statistics']['num_cached_scripts'] ?? 0);
    }
}

echo json_encode([
    'ok'      => empty($errors),
    'results' => $results,
    'errors'  => $errors,
    'stats'   => $stats,
    'time'    => date('H:i:s'),
]);
