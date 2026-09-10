<?php
/**
 * GestorADS — Cron de Limpeza Automática de Dados Antigos
 *
 * Configure no cron-job.org para rodar 1x por dia (ex: às 04:00):
 * GET https://seudominio.com.br/cron/cleanup.php?key=CRON_SECRET
 *
 * O CRON_SECRET é gerado automaticamente em config/config.php:
 *   define('CRON_SECRET', 'GST_'.hash('sha256', APP_SECRET.'_cron_2025'));
 */

define('CLI_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Valida chave de segurança
$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$db      = Database::getInstance();
$results = [];
$errors  = [];

// ── 1. activity_log: apaga registros com mais de 90 dias ─────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );
    $results['activity_log'] = $stmt->rowCount() . ' registros removidos';
} catch (Throwable $e) {
    $errors['activity_log'] = $e->getMessage();
}

// ── 2. alert_logs: apaga registros com mais de 180 dias ─────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM alert_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
    );
    $results['alert_logs'] = $stmt->rowCount() . ' registros removidos';
} catch (Throwable $e) {
    $errors['alert_logs'] = $e->getMessage();
}

// ── 3. report_logs: apaga registros com mais de 180 dias ────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM report_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
    );
    $results['report_logs'] = $stmt->rowCount() . ' registros removidos';
} catch (Throwable $e) {
    $errors['report_logs'] = $e->getMessage();
}

// ── 4. password_resets: apaga usados ou expirados há mais de 7 dias ──────────
try {
    $stmt = $db->query(
        "DELETE FROM password_resets
         WHERE used = 1 OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $results['password_resets'] = $stmt->rowCount() . ' registros removidos';
} catch (Throwable $e) {
    $errors['password_resets'] = $e->getMessage();
}

// ── 5. notifications: apaga lidas com mais de 30 dias ───────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM notifications
         WHERE read_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $results['notifications'] = $stmt->rowCount() . ' registros removidos';
} catch (Throwable $e) {
    $errors['notifications'] = $e->getMessage();
}


// ── 6. dashboard_cache: apaga entradas expiradas ────────────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM dashboard_cache WHERE expires_at < NOW()"
    );
    $results['dashboard_cache'] = $stmt->rowCount() . ' entradas expiradas removidas';
} catch (Throwable $e) {
    $errors['dashboard_cache'] = $e->getMessage();
}

// ── 7. campaign_metrics: apaga métricas com mais de 180 dias ───────────────
try {
    $stmt = $db->query(
        "DELETE FROM campaign_metrics WHERE date < DATE_SUB(CURDATE(), INTERVAL 180 DAY)"
    );
    $results['campaign_metrics'] = $stmt->rowCount() . ' métricas antigas removidas';
} catch (Throwable $e) {
    $errors['campaign_metrics'] = $e->getMessage();
}

// ── 8. ai_logs: apaga logs de IA com mais de 60 dias ────────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM ai_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
    );
    $results['ai_logs'] = $stmt->rowCount() . ' logs de IA removidos';
} catch (Throwable $e) {
    $errors['ai_logs'] = $e->getMessage();
}

// ── 9. integration_logs: apaga logs com mais de 30 dias ─────────────────────
try {
    $stmt = $db->query(
        "DELETE FROM integration_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $results['integration_logs'] = $stmt->rowCount() . ' logs de integração removidos';
} catch (Throwable $e) {
    $errors['integration_logs'] = $e->getMessage();
}

// ── Resposta ─────────────────────────────────────────────────────────────────
$status = empty($errors) ? 'ok' : 'partial';

echo json_encode([
    'status'    => $status,
    'timestamp' => date('Y-m-d H:i:s'),
    'results'   => $results,
    'errors'    => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
