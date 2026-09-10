<?php
/**
 * GestorPro — Cron de Sincronização de Métricas
 *
 * Configure no cron-job.org para rodar a CADA 1 HORA:
 * GET https://seudominio.com.br/cron/sync_metrics.php?key={CRON_SECRET}
 *
 * Funcionalidades:
 *  - Lock de processo (evita execuções simultâneas)
 *  - fastcgi_finish_request (responde ao cron-job.org em <1s)
 *  - Processa TODAS as contas em background
 *  - Pré-aquece cache do dashboard para cada usuário
 *  - Limpeza automática de tabelas antigas (1x por dia)
 *  - Salva log no banco para consulta via sync_log.php
 */

define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    $key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
    if ($key !== CRON_SECRET) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }
    header('Content-Type: application/json');
}

set_exception_handler(function(Throwable $e) use ($isCli) {
    $msg = $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine();
    if ($isCli) echo "[ERRO] $msg\n";
    else echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../controllers/AccountController.php';

// ── LOCK DE PROCESSO ──────────────────────────────────────────────────────────
$lockFile = sys_get_temp_dir() . '/gestorpro_sync_metrics.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) > 300) {
    @unlink($lockFile);
}
$fp = fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    $msg = ['success' => true, 'log' => ['SKIP: outra execução já está rodando (lock ativo)'], 'ok' => 0, 'erros' => 0];
    if ($isCli) echo "SKIP: lock ativo\n";
    else echo json_encode($msg, JSON_UNESCAPED_UNICODE);
    exit;
}
touch($lockFile);
register_shutdown_function(function() use ($fp, $lockFile) {
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);
});
// ─────────────────────────────────────────────────────────────────────────────

set_time_limit(0);
ignore_user_abort(true);

// Responde ao cron-job.org imediatamente (evita timeout de 60s)
if (!$isCli) {
    $db0   = Database::getInstance();
    $total0 = (int)$db0->query("SELECT COUNT(*) FROM ad_accounts WHERE status='active'")->fetchColumn();
    $early  = json_encode([
        'success' => true,
        'status'  => 'processing',
        'contas'  => $total0,
        'hora'    => date('H:i'),
    ], JSON_UNESCAPED_UNICODE);
    header('Content-Length: ' . strlen($early));
    echo $early;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('ob_end_flush')) { ob_end_flush(); flush(); }
}

// ── INÍCIO DO PROCESSAMENTO ───────────────────────────────────────────────────
$now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$log = [];
$ok  = 0;
$err = 0;

$log[] = "[" . $now->format('Y-m-d H:i:s') . "] Iniciando sincronização de métricas...";

$db   = Database::getInstance();
$accs = $db->query("SELECT * FROM ad_accounts WHERE status='active' ORDER BY user_id, id")->fetchAll();
$log[] = "Contas ativas: " . count($accs);
$log[] = "Processando todas as " . count($accs) . " contas...";

foreach ($accs as $acc) {
    try {
        $start = date('Y-m-d', strtotime('-30 days'));
        $end   = date('Y-m-d');

        if ($acc['platform'] === 'meta') {
            AccountController::syncMetaPublic($acc, $start, $end, $db);
        } else {
            AccountController::syncGooglePublic($acc, $start, $end, $db);
        }

        $log[] = "[OK] Conta #{$acc['id']} — {$acc['account_name']} ({$acc['platform']})";

        // Notificação de sucesso (1x por dia por conta)
        try {
            $hoje = $now->format('Y-m-d');
            $jaNotif = $db->query(
                "SELECT id FROM notifications WHERE user_id=? AND type='success'
                 AND title=? AND DATE(created_at)=? LIMIT 1",
                [$acc['user_id'], "📊 Métricas sincronizadas: {$acc['account_name']}", $hoje]
            )->fetch();
            if (!$jaNotif) {
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
        $log[] = "[ERRO] Conta #{$acc['id']} {$acc['account_name']}: " . $e->getMessage();
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

$log[] = "[" . date('Y-m-d H:i:s') . "] Concluído. OK: $ok | Erros: $err";

// ── LIMPEZA AUTOMÁTICA (1x por dia, madrugada) ────────────────────────────────
if ((int)$now->format('H') < 4) {
    try {
        $del1 = $db->query("DELETE FROM password_resets WHERE used=1 OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->rowCount();
        if ($del1 > 0) $log[] = "Limpeza: $del1 password_resets removidos";

        $del2 = $db->query("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
        if ($del2 > 0) $log[] = "Limpeza: $del2 activity_logs removidos";

        $del3 = $db->query("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)")->rowCount();
        if ($del3 > 0) $log[] = "Limpeza: $del3 notificações removidas";

        $del4 = $db->query("DELETE FROM alert_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
        if ($del4 > 0) $log[] = "Limpeza: $del4 alert_logs removidos";
    } catch (\Throwable $_clean) {
        $log[] = "Limpeza: erro — " . $_clean->getMessage();
    }
}

// ── PRÉ-AQUECE CACHE DO DASHBOARD ─────────────────────────────────────────────
try {
    require_once __DIR__.'/../controllers/ReportController.php';

    $reps = $db->query(
        "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token,
                aa.account_name, c.name AS client_name
         FROM reports r
         LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
         LEFT JOIN clients c ON r.client_id = c.id
         WHERE r.status IN ('active','scheduled') AND aa.access_token IS NOT NULL
         ORDER BY r.user_id, r.updated_at DESC LIMIT 500"
    )->fetchAll();
    $reps = decryptTokens($reps);

    // Agrupa por user_id
    $byUser = [];
    foreach ($reps as $rep) {
        $byUser[$rep['user_id']][] = $rep;
    }

    $cacheOk = 0; $cacheErr = 0;

    foreach ($byUser as $uid2 => $reports) {
        try {
            $cacheKey2  = 'widget_metrics_' . $uid2 . '_' . date('Y-m-d');
            $widgetData = [];

            foreach ($reports as $rep) {
                if (empty($rep['meta_token'])) continue;
                try {
                    $periodType = $rep['period_type'] ?? 'last_7_days';
                    [$start2, $end2] = ReportController::calcPeriodDates($periodType);
                    if ($start2 === 'MAX') {
                        $campIds2 = !empty($rep['camp_ids']) ? array_filter(explode(',', $rep['camp_ids'])) : [];
                        $paramsMax = [(int)$rep['ad_account_id']];
                        $cwMax = '';
                        if (!empty($campIds2)) {
                            $cwMax = ' AND campaign_id IN (' . implode(',', array_fill(0, count($campIds2), '?')) . ')';
                            $paramsMax = array_merge($paramsMax, $campIds2);
                        }
                        $minD = $db->query("SELECT MIN(date) FROM campaign_metrics WHERE ad_account_id=?{$cwMax}", $paramsMax)->fetchColumn();
                        $start2 = $minD ?: date('Y-m-d', strtotime('-90 days'));
                    }
                    $campIds2 = !empty($rep['camp_ids']) ? array_filter(explode(',', $rep['camp_ids'])) : [];

                    // Chama API e salva no cache
                    $m2 = ReportController::fetchMetricsCached(
                        $rep['meta_account_id'], $rep['meta_token'],
                        $start2, $end2, $campIds2,
                        (int)$rep['ad_account_id'], (int)$uid2, 360
                    );
                    if (!$m2 || (float)($m2['spend'] ?? 0) <= 0) continue;

                    $spend2   = (float)($m2['spend'] ?? 0);
                    $ctr2     = (float)($m2['ctr'] ?? 0);
                    $freq2    = (float)($m2['frequency'] ?? 0);
                    $cpm2     = (float)($m2['cpm'] ?? 0);
                    $msgs2    = (int)($m2['messages'] ?? $m2['msg'] ?? 0);
                    $leads2   = (int)($m2['leads'] ?? $m2['conversions'] ?? 0);
                    $profV2   = (int)($m2['profile_visits'] ?? $m2['profile_visit'] ?? 0);

                    // Score
                    $score2 = 50;
                    if ($ctr2 >= 2.0)  $score2 += 20; elseif ($ctr2 >= 1.0) $score2 += 10; elseif ($ctr2 > 0) $score2 -= 10;
                    if ($freq2 <= 2.0) $score2 += 15; elseif ($freq2 <= 3.0) $score2 += 5;  elseif ($freq2 > 3.5) $score2 -= 15;
                    if ($cpm2 > 0 && $cpm2 <= 15) $score2 += 15; elseif ($cpm2 > 30) $score2 -= 10;
                    $score2 = max(10, min(100, $score2));

                    // Projeção do mês
                    $mMes2 = ReportController::fetchMetricsCached(
                        $rep['meta_account_id'], $rep['meta_token'],
                        date('Y-m-01'), date('Y-m-d'), $campIds2,
                        (int)$rep['ad_account_id'], (int)$uid2, 360
                    );
                    $spendMes2    = (float)($mMes2['spend'] ?? 0);
                    $diasComGasto2 = max(1, (int)date('j'));
                    $diasRestant2  = (int)date('t') - (int)date('j');
                    $ritmo2        = $spendMes2 / $diasComGasto2;
                    $projecao2     = round($spendMes2 + ($ritmo2 * $diasRestant2), 2);

                    // Custo por resultado
                    $objetivo2   = strtolower($rep['objetivo'] ?? 'trafego');
                    $clks2       = (int)($m2['clicks'] ?? 0);
                    $cpc2        = ($clks2 > 0 && $spend2 > 0) ? round($spend2 / $clks2, 2) : 0;
                    $costResult2 = 0; $costLabel2 = ''; $costColor2 = '#8b949e';

                    if ($objetivo2 === 'mensagem') {
                        if ($msgs2 > 0)     { $costResult2 = round($spend2/$msgs2,2);  $costLabel2='msg';    $costColor2='#1ABC9C'; }
                        elseif ($cpc2 > 0)  { $costResult2 = $cpc2;                    $costLabel2='clique'; $costColor2='#388bfd'; }
                    } elseif (in_array($objetivo2, ['leads','lead'])) {
                        if ($leads2 > 0)    { $costResult2 = round($spend2/$leads2,2); $costLabel2='lead';   $costColor2='#8b5cf6'; }
                        elseif ($cpc2 > 0)  { $costResult2 = $cpc2;                    $costLabel2='clique'; $costColor2='#388bfd'; }
                    } elseif ($objetivo2 === 'trafego') {
                        if ($profV2 > 0)    { $costResult2 = round($spend2/$profV2,2); $costLabel2='vis';    $costColor2='#388bfd'; }
                        elseif ($msgs2 > 0) { $costResult2 = round($spend2/$msgs2,2);  $costLabel2='msg';    $costColor2='#1ABC9C'; }
                        elseif ($cpc2 > 0)  { $costResult2 = $cpc2;                    $costLabel2='clique'; $costColor2='#388bfd'; }
                    } else {
                        if ($cpc2 > 0)      { $costResult2 = $cpc2;                    $costLabel2='clique'; $costColor2='#388bfd'; }
                    }

                    // Gasto total (desde o início)
                    $spendTotal2 = $spend2;
                    try {
                        $paramsT = [(int)$rep['ad_account_id']];
                        $cwT = '';
                        if (!empty($campIds2)) {
                            $cwT = ' AND campaign_id IN (' . implode(',', array_fill(0, count($campIds2), '?')) . ')';
                            $paramsT = array_merge($paramsT, $campIds2);
                        }
                        $minDT = $db->query("SELECT MIN(date) FROM campaign_metrics WHERE ad_account_id=?{$cwT}", $paramsT)->fetchColumn();
                        if ($minDT) {
                            $mT = ReportController::fetchMetricsCached(
                                $rep['meta_account_id'], $rep['meta_token'],
                                $minDT, date('Y-m-d'), $campIds2,
                                (int)$rep['ad_account_id'], (int)$uid2, 360
                            );
                            $spendTotal2 = (float)($mT['spend'] ?? $spend2);
                        }
                    } catch (\Throwable $_et) {}

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
                        'spend_total'  => $spendTotal2,
                        'spend_prev'   => 0,
                        'spend_change' => 0,
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
                } catch (\Throwable $_re) {}
                usleep(200000); // 200ms entre chamadas API
            }

            if (!empty($widgetData)) {
                $payload2 = json_encode($widgetData, JSON_UNESCAPED_UNICODE);
                $expires2 = date('Y-m-d H:i:s', strtotime('+70 minutes'));
                $db->query(
                    "INSERT INTO dashboard_cache (cache_key, user_id, payload, expires_at)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE payload=VALUES(payload), expires_at=VALUES(expires_at), created_at=NOW()",
                    [$cacheKey2, $uid2, $payload2, $expires2]
                );
                $cacheOk++;
            }
        } catch (\Throwable $ue) {
            $cacheErr++;
            $log[] = "Cache user {$uid2}: erro — " . $ue->getMessage();
        }
    }

    if ($cacheOk > 0)  $log[] = "Cache pré-aquecido: $cacheOk usuário(s)";
    if ($cacheErr > 0) $log[] = "Cache: $cacheErr erro(s)";

} catch (\Throwable $ce) {
    $log[] = "Cache dashboard: falha — " . $ce->getMessage();
}

// ── RESUMO POR CONTA ──────────────────────────────────────────────────────────
$resumo = [];
try {
    $resumo = $db->query(
        "SELECT aa.account_name, aa.platform, MAX(cm.synced_at) as ultima_sync,
                COUNT(DISTINCT cm.date) as dias, ROUND(SUM(cm.spend),2) as spend,
                SUM(cm.messages) as msgs
         FROM campaign_metrics cm
         JOIN ad_accounts aa ON cm.ad_account_id = aa.id
         WHERE aa.status='active'
         GROUP BY aa.id, aa.account_name, aa.platform
         ORDER BY ultima_sync DESC"
    )->fetchAll();
} catch (\Throwable $_e) {}

// ── SALVA LOG NO BANCO ────────────────────────────────────────────────────────
try {
    $logPayload = json_encode(['log' => $log, 'resumo' => $resumo], JSON_UNESCAPED_UNICODE);
    $db->query(
        "INSERT INTO dashboard_cache (cache_key, user_id, payload, expires_at)
         VALUES ('cron_sync_log', 0, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
         ON DUPLICATE KEY UPDATE payload=VALUES(payload), expires_at=VALUES(expires_at)",
        [$logPayload]
    );
} catch (\Throwable $_e) {}

if ($isCli) {
    foreach ($log as $linha) echo $linha . "\n";
}
