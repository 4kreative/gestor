<?php
/**
 * GestorPro — Cron de Sincronização de Métricas (HTTP)
 *
 * Configure no cron-job.org para rodar a CADA 1 HORA:
 * GET https://gestorads.j6digital.com.br/cron/run-metrics.php?key=CRON_SECRET
 *
 * CORREÇÕES APLICADAS:
 *  - Reflection/setAccessible removido: métodos sync tornados public static
 *    no AccountController (ver AccountController.php)
 *  - Lock file deletado no shutdown
 */

define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

set_exception_handler(function(Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../controllers/AccountController.php';
require_once __DIR__.'/../controllers/MetaSyncService.php';

// ── LOCK DE PROCESSO ──────────────────────────────────────────────────────────
$lockFile = sys_get_temp_dir() . '/gestorpro_run_metrics.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) > 120) {
    @unlink($lockFile);
}
$fp = fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    echo json_encode([
        'success' => true,
        'log'     => ['SKIP: outra execução já está rodando (lock ativo)'],
        'ok'      => 0,
        'erros'   => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
touch($lockFile);
// CORREÇÃO: deletar lockFile no shutdown
register_shutdown_function(function() use ($fp, $lockFile) {
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);
});
// ─────────────────────────────────────────────────────────────────────────────

$now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$log = [];
$ok  = 0;
$err = 0;

$log[] = "Métricas — " . $now->format('Y-m-d H:i:s');

$db   = Database::getInstance();
// CORREÇÃO: busca contas agrupadas por user_id para manter segregação
$accs = $db->query("SELECT * FROM ad_accounts WHERE status='active' ORDER BY user_id, id")->fetchAll();
$log[] = "Contas ativas: " . count($accs);

foreach ($accs as $acc) {
    try {
        $start = date('Y-m-d', strtotime('-30 days'));
        $end   = date('Y-m-d');

        if ($acc['platform'] === 'meta') {
            // CORREÇÃO: chama método público estático — sem Reflection
            AccountController::syncMetaPublic($acc, $start, $end, $db);
        } else {
            AccountController::syncGooglePublic($acc, $start, $end, $db);
        }

        $log[] = "OK #{$acc['id']} — {$acc['account_name']} ({$acc['platform']})";

        // Notificação de sucesso (1x por dia por conta)
        try {
            $hoje = $now->format('Y-m-d');
            $jaNotificou = $db->query(
                "SELECT id FROM notifications WHERE user_id=? AND type='success'
                 AND title=? AND DATE(created_at)=? LIMIT 1",
                [$acc['user_id'], "📊 Métricas sincronizadas: {$acc['account_name']}", $hoje]
            )->fetch();
            if (!$jaNotificou) {
                $db->query(
                    "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'success', ?, ?)",
                    [
                        $acc['user_id'],
                        "📊 Métricas sincronizadas: {$acc['account_name']}",
                        "Dados dos últimos 30 dias atualizados em " . $now->format('H:i') . ".",
                    ]
                );
            }
        } catch (\Throwable $_e) {}

        $ok++;
    } catch (\Exception $e) {
        $log[] = "ERRO #{$acc['id']} {$acc['account_name']}: " . $e->getMessage();
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                [
                    $acc['user_id'],
                    "❌ Falha ao sincronizar: {$acc['account_name']}",
                    mb_substr($e->getMessage(), 0, 200),
                ]
            );
        } catch (\Throwable $_e) {}
        $err++;
    }
}

// ── Limpeza automática de tabelas (1x por dia, às primeiras horas) ───────────
if ((int)$now->format('H') < 4) {
    try {
        // Remove password_resets usados ou expirados há mais de 7 dias
        $del1 = $db->query("DELETE FROM password_resets WHERE used=1 OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->rowCount();
        if ($del1 > 0) $log[] = "Limpeza: $del1 password_resets removidos";

        // Mantém activity_log por 90 dias
        $del2 = $db->query("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
        if ($del2 > 0) $log[] = "Limpeza: $del2 activity_logs removidos";

        // Mantém notifications por 60 dias
        $del3 = $db->query("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)")->rowCount();
        if ($del3 > 0) $log[] = "Limpeza: $del3 notificações removidas";

        // Mantém alert_logs por 90 dias
        $del4 = $db->query("DELETE FROM alert_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
        if ($del4 > 0) $log[] = "Limpeza: $del4 alert_logs removidos";
    } catch (\Throwable $_clean) {
        $log[] = "Limpeza: erro — " . $_clean->getMessage();
    }
}

// ══════════════════════════════════════════════════════════════
// RENOVAÇÃO DO CACHE DO DASHBOARD (widget_metrics)
// Roda a cada execução do cron — mantém cache sempre fresco
// Assim o dashboard nunca precisa esperar pela API Meta
// ══════════════════════════════════════════════════════════════
try {
    require_once __DIR__.'/../controllers/ReportController.php';

    // Busca todos os usuários que têm relatórios ativos
    $usersWithReports = $db->query(
        "SELECT DISTINCT r.user_id, r.id, r.title, r.period_type, r.camp_ids, r.objetivo,
                aa.account_id AS meta_account_id, aa.access_token AS meta_token,
                aa.account_name, c.name AS client_name, r.ad_account_id
         FROM reports r
         LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
         LEFT JOIN clients c ON r.client_id = c.id
         WHERE r.status IN ('active','scheduled') AND aa.access_token IS NOT NULL
         ORDER BY r.user_id, r.updated_at DESC
         LIMIT 500"
    )->fetchAll();
    $usersWithReports = decryptTokens($usersWithReports);

    // Agrupa por user_id
    $byUser = [];
    foreach ($usersWithReports as $rep) {
        $byUser[$rep['user_id']][] = $rep;
    }

    $cacheOk  = 0;
    $cacheErr = 0;

    foreach ($byUser as $uid2 => $reports) {
        try {
            $cacheKey2   = 'widget_metrics_' . $uid2 . '_' . date('Y-m-d');
            $widgetData  = [];

            foreach ($reports as $rep) {
                if (empty($rep['meta_token'])) continue;
                $periodType = $rep['period_type'] ?? 'last_7_days';
                [$start2, $end2] = ReportController::calcPeriodDates($periodType);
                if ($start2 === 'MAX') {
                    try {
                        $campIds2 = !empty($rep['camp_ids']) ? array_filter(explode(',', $rep['camp_ids'])) : [];
                        $start2 = ReportController::fetchCampaignStartDate(
                            $rep['meta_account_id'], $rep['meta_token'], $campIds2, (int)$rep['ad_account_id']
                        );
                    } catch (Throwable $e) { $start2 = date('Y-m-d', strtotime('-90 days')); }
                }
                $campIds2 = !empty($rep['camp_ids']) ? array_filter(explode(',', $rep['camp_ids'])) : [];
                $m2 = ReportController::fetchMetricsCached(
                    $rep['meta_account_id'], $rep['meta_token'],
                    $start2, $end2, $campIds2,
                    (int)$rep['ad_account_id'], $uid2, 60 // cache interno de 60min para o cron
                );
                if (!$m2 || (float)($m2['spend'] ?? 0) <= 0) continue;

                $spend2  = (float)($m2['spend'] ?? 0);
                $ctr2    = (float)($m2['ctr'] ?? 0);
                $freq2   = (float)($m2['frequency'] ?? 0);
                $cpm2    = (float)($m2['cpm'] ?? 0);
                $msgs2   = (int)($m2['messages'] ?? $m2['msg'] ?? 0);
                $leads2  = (int)($m2['leads'] ?? $m2['conversions'] ?? 0);

                // Score de saúde
                $score2 = 50;
                if ($ctr2 >= 2.0)  $score2 += 20; elseif ($ctr2 >= 1.0) $score2 += 10; elseif ($ctr2 > 0) $score2 -= 10;
                if ($freq2 <= 2.0) $score2 += 15; elseif ($freq2 <= 3.0) $score2 += 5;  elseif ($freq2 > 3.5) $score2 -= 15;
                if ($cpm2 > 0 && $cpm2 <= 15) $score2 += 15; elseif ($cpm2 > 30) $score2 -= 10;
                $score2 = max(10, min(100, $score2));

                $mesAtualStart2 = date('Y-m-01');
                $mesAtualEnd2   = date('Y-m-d');
                $mMes2 = ReportController::fetchMetricsCached(
                    $rep['meta_account_id'], $rep['meta_token'],
                    $mesAtualStart2, $mesAtualEnd2, $campIds2,
                    (int)$rep['ad_account_id'], $uid2, 60
                );
                $spendMes2    = (float)($mMes2['spend'] ?? 0);
                $diaAtual2    = (int)date('j');
                $diasMes2     = (int)date('t');
                $diasRestant2 = $diasMes2 - $diaAtual2;
                $diasComGasto2 = max(1, $diaAtual2);
                $ritmo2       = $diasComGasto2 > 0 ? $spendMes2 / $diasComGasto2 : 0;
                $projecao2    = $ritmo2 > 0 ? round($spendMes2 + ($ritmo2 * $diasRestant2), 2) : round($spendMes2, 2);

                $objetivo2 = strtolower($rep['objetivo'] ?? 'trafego');
                $clks2     = (int)($m2['clicks'] ?? 0);
                $cpc2      = ($clks2 > 0 && $spend2 > 0) ? round($spend2 / $clks2, 2) : 0;
                $costResult2 = 0; $costLabel2 = ''; $costColor2 = '#8b949e';
                if ($objetivo2 === 'mensagem' && $msgs2 > 0)  { $costResult2 = round($spend2/$msgs2,2);  $costLabel2='msg';    $costColor2='#1ABC9C'; }
                elseif ($objetivo2 === 'leads'  && $leads2 > 0){ $costResult2 = round($spend2/$leads2,2); $costLabel2='lead';   $costColor2='#8b5cf6'; }
                elseif ($cpc2 > 0)                              { $costResult2 = $cpc2;                    $costLabel2='clique'; $costColor2='#388bfd'; }

                $widgetData[] = [
                    'report_id'    => $rep['id'],
                    'title'        => $rep['title'],
                    'camp_labels'  => $rep['camp_labels'] ?? '',
                    'client_name'  => $rep['client_name'] ?? $rep['account_name'] ?? '',
                    'account_name' => $rep['account_name'] ?? '',
                    'objetivo'     => $objetivo2,
                    'period_start' => $start2,
                    'period_end'   => $end2,
                    'spend'        => $spend2,
                    'ctr'          => $ctr2,
                    'freq'         => $freq2,
                    'cpm'          => $cpm2,
                    'score'        => $score2,
                    'cost_result'  => $costResult2,
                    'cost_label'   => $costLabel2,
                    'cost_color'   => $costColor2,
                    'projecao'     => $projecao2,
                    'ritmo_dia'    => round($ritmo2, 2),
                    'dias_ativos'  => $diasComGasto2,
                    'esta_pausada' => false,
                    'dias_pausada' => 0,
                    'peso_pausada' => 0.0,
                ];
            }

            if (!empty($widgetData)) {
                $payload2 = json_encode($widgetData, JSON_UNESCAPED_UNICODE);
                $expires2 = date('Y-m-d H:i:s', strtotime('+35 minutes')); // 35min > cron de 30min
                $db->query(
                    "INSERT INTO dashboard_cache (cache_key, user_id, payload, expires_at)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE payload=VALUES(payload), expires_at=VALUES(expires_at), created_at=NOW()",
                    [$cacheKey2, $uid2, $payload2, $expires2]
                );
                $cacheOk++;
            }
        } catch (Throwable $ue) {
            $cacheErr++;
            $log[] = "Cache dashboard user {$uid2}: erro — " . $ue->getMessage();
        }
    }

    if ($cacheOk > 0)  $log[] = "Cache dashboard: renovado para $cacheOk usuário(s)";
    if ($cacheErr > 0) $log[] = "Cache dashboard: $cacheErr erro(s)";

} catch (Throwable $ce) {
    $log[] = "Cache dashboard: falha geral — " . $ce->getMessage();
}

$log[] = "FIM — OK: $ok | Erros: $err";

echo json_encode([
    'success' => true,
    'hora'    => $now->format('H:i'),
    'ok'      => $ok,
    'erros'   => $err,
    'log'     => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
