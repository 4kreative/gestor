<?php
class DashboardController {
    public function index(): void {
        requireAuth();
        $user = currentUser();
        $db   = Database::getInstance();
        $uid  = $user['id'];

        // KPIs
        $data['total_clients']  = $db->query("SELECT COUNT(*) FROM clients WHERE user_id=? AND status='active'",[$uid])->fetchColumn();
        $data['total_accounts'] = $db->query("SELECT COUNT(*) FROM ad_accounts WHERE user_id=? AND status='active'",[$uid])->fetchColumn();
        $data['total_reports']  = $db->query("SELECT COUNT(*) FROM reports WHERE user_id=?",[$uid])->fetchColumn();
        $data['reports_sent']   = $db->query("SELECT COUNT(*) FROM reports WHERE user_id=? AND sent_whatsapp=1",[$uid])->fetchColumn();
        $data['wp_instances']   = $db->query("SELECT COUNT(*) FROM whatsapp_instances WHERE user_id=? AND status='connected'",[$uid])->fetchColumn();
        // Token expiry warning
        $data['tokens_expiring'] = $db->query(
            "SELECT account_name, token_expires FROM ad_accounts WHERE user_id=? AND platform='meta' AND status='active' AND token_expires IS NOT NULL AND token_expires < DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY token_expires ASC LIMIT 3",
            [$uid]
        )->fetchAll();
        $data['unread_notifs']  = $db->query("SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL",[$uid])->fetchColumn();

        // Integrações
        try {
            $data['total_integrations']  = $db->query("SELECT COUNT(*) FROM integrations WHERE user_id=? AND status='active'",[$uid])->fetchColumn();
            $data['integration_logs_today'] = $db->query("SELECT COUNT(*) FROM integration_logs il INNER JOIN integrations i ON i.id=il.integration_id WHERE i.user_id=? AND DATE(il.created_at)=CURDATE()",[$uid])->fetchColumn();
            $data['integration_errors_today'] = $db->query("SELECT COUNT(*) FROM integration_logs il INNER JOIN integrations i ON i.id=il.integration_id WHERE i.user_id=? AND DATE(il.created_at)=CURDATE() AND il.status='error'",[$uid])->fetchColumn();
            $data['recent_integration_logs'] = $db->query(
                "SELECT il.*, i.name AS int_name, i.type AS int_type
                  FROM integration_logs il
                  INNER JOIN integrations i ON i.id=il.integration_id
                  WHERE i.user_id=?
                  ORDER BY il.id DESC LIMIT 10",[$uid]
            )->fetchAll();
        } catch (\Throwable $e) {
            $data['total_integrations'] = 0;
            $data['integration_logs_today'] = 0;
            $data['integration_errors_today'] = 0;
            $data['recent_integration_logs'] = [];
        }

        // Análises IA
        try {
            $data['ai_analyses'] = $db->query("SELECT COUNT(*) FROM ai_logs WHERE user_id=?",[$uid])->fetchColumn();
            $data['ai_today']    = $db->query("SELECT COUNT(*) FROM ai_logs WHERE user_id=? AND DATE(created_at)=CURDATE()",[$uid])->fetchColumn();
        } catch (\Throwable $e) { $data['ai_analyses']=0; $data['ai_today']=0; }

        // Relatórios recentes (mantido para compatibilidade)
        $data['recent_reports'] = $db->query(
            "SELECT r.*, c.name AS client_name
             FROM reports r LEFT JOIN clients c ON r.client_id=c.id
             WHERE r.user_id=? ORDER BY r.updated_at DESC LIMIT 200", [$uid]
        )->fetchAll();

        // Feed unificado: Relatórios + Alertas de Saldo (para o card "Saldo de Envios")
        try {
            $data['feed_envios'] = $db->query(
                "SELECT
                    'relatorio' AS tipo,
                    r.id,
                    r.title AS nome,
                    COALESCE(c.name, r.title) AS cliente,
                    r.objetivo,
                    r.frequency,
                    r.status,
                    r.next_send_at,
                    r.sent_at,
                    r.last_send_status,
                    r.sent_whatsapp,
                    NULL AS tipo_envio
                 FROM reports r LEFT JOIN clients c ON r.client_id = c.id
                 WHERE r.user_id = ?
                 UNION ALL
                 SELECT
                    a.type AS tipo,
                    a.id,
                    a.name AS nome,
                    COALESCE(c.name, a.name) AS cliente,
                    CASE a.type
                        WHEN 'saldo_minimo' THEN CONCAT('Saldo mín. R$ ', FORMAT(a.saldo_minimo, 2))
                        WHEN 'ctr_baixo' THEN CONCAT('CTR mín. ', a.valor_threshold, '%')
                        WHEN 'cpc_alto' THEN CONCAT('CPC máx. R$ ', FORMAT(a.valor_threshold, 2))
                        WHEN 'custo_conv_alto' THEN CONCAT('Custo/conv máx. R$ ', FORMAT(a.valor_threshold, 2))
                        WHEN 'roas_baixo' THEN CONCAT('ROAS mín. ', a.valor_threshold)
                        WHEN 'erro_conta' THEN 'Monitorar erros'
                        ELSE a.name
                    END AS objetivo,
                    'daily' AS frequency,
                    IF(a.ativo, 'active', 'paused') AS status,
                    NULL AS next_send_at,
                    al.created_at AS sent_at,
                    al.status AS last_send_status,
                    IF(al.status='enviado', 1, 0) AS sent_whatsapp,
                    al.tipo_envio
                 FROM alerts a
                 LEFT JOIN clients c ON a.client_id = c.id
                 LEFT JOIN alert_logs al ON al.alert_id = a.id AND al.id = (
                    SELECT MAX(al2.id) FROM alert_logs al2 WHERE al2.alert_id = a.id
                 )
                 WHERE a.user_id = ?
                 ORDER BY sent_at DESC, nome ASC",
                [$uid, $uid]
            )->fetchAll();
        } catch (\Throwable $e) {
            try {
                $data['feed_envios'] = $db->query(
                    "SELECT 'relatorio' AS tipo, r.id,
                            r.title AS nome,
                            COALESCE(c.name, r.title) AS cliente,
                            r.objetivo, r.frequency, r.status,
                            r.next_send_at, r.sent_at,
                            r.last_send_status, r.sent_whatsapp,
                            NULL AS tipo_envio
                     FROM reports r LEFT JOIN clients c ON r.client_id = c.id
                     WHERE r.user_id = ?
                     ORDER BY r.updated_at DESC", [$uid]
                )->fetchAll();
            } catch (\Throwable $e2) { $data['feed_envios'] = []; }
        }

        // Gastos 30 dias
        $data['spend_meta']   = $db->query(
            "SELECT COALESCE(SUM(cm.spend),0) FROM campaign_metrics cm
             JOIN ad_accounts aa ON cm.ad_account_id=aa.id
             WHERE aa.user_id=? AND aa.platform='meta' AND cm.date >= DATE_SUB(NOW(),INTERVAL 30 DAY)", [$uid]
        )->fetchColumn();
        $data['spend_google'] = $db->query(
            "SELECT COALESCE(SUM(cm.spend),0) FROM campaign_metrics cm
             JOIN ad_accounts aa ON cm.ad_account_id=aa.id
             WHERE aa.user_id=? AND aa.platform='google' AND cm.date >= DATE_SUB(NOW(),INTERVAL 30 DAY)", [$uid]
        )->fetchColumn();

        // Gasto 7 dias por dia (para gráfico)
        $data['spend_7days'] = $db->query(
            "SELECT DATE(cm.date) as d, aa.platform,
                    SUM(cm.spend) as total
             FROM campaign_metrics cm
             JOIN ad_accounts aa ON cm.ad_account_id=aa.id
             WHERE aa.user_id=? AND cm.date >= DATE_SUB(NOW(),INTERVAL 7 DAY)
             GROUP BY DATE(cm.date), aa.platform ORDER BY d", [$uid]
        )->fetchAll();

        // WhatsApp instâncias detalhes
        $data['wp_detail'] = $db->query(
            "SELECT instance_name, status, phone_number FROM whatsapp_instances WHERE user_id=? LIMIT 3", [$uid]
        )->fetchAll();

        // Notificações recentes
        $data['notifications'] = $db->query(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8", [$uid]
        )->fetchAll();

        // Logs de envio recentes para o dashboard
        $data['dash_send_logs'] = [];
        try {
            $data['dash_send_logs'] = $db->query(
                "SELECT al.*, a.name AS nome, a.type AS tipo_alerta, 'alert' AS source, COALESCE(aa.account_name, a.name, al.alert_name) AS nome_conta FROM alert_logs al LEFT JOIN alerts a ON al.alert_id = a.id LEFT JOIN ad_accounts aa ON a.ad_account_id = aa.id WHERE al.user_id = ? ORDER BY al.id DESC LIMIT 10",
                [$uid]
            )->fetchAll();
        } catch (\Throwable $e) { $data['dash_send_logs_err'] = 'alert_logs: '.$e->getMessage(); }
        try {
            $repLogs = $db->query(
                "SELECT rl.*, r.title AS nome, NULL AS tipo_alerta, 'report' AS source, r.title AS nome_conta FROM report_logs rl LEFT JOIN reports r ON rl.report_id = r.id WHERE rl.user_id = ? ORDER BY rl.id DESC LIMIT 10",
                [$uid]
            )->fetchAll();
            $data['dash_send_logs'] = array_merge($data['dash_send_logs'], $repLogs);
            usort($data['dash_send_logs'], fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
            $data['dash_send_logs'] = array_slice($data['dash_send_logs'], 0, 10);
        } catch (\Throwable $e) {}

        // Post-its salvos
        try {
            $data['postits'] = $db->query(
                "SELECT * FROM dashboard_notes WHERE user_id=? ORDER BY created_at DESC LIMIT 6", [$uid]
            )->fetchAll();
        } catch (\Throwable $e) { $data['postits'] = []; }

        // Análises IA — mais recente por conta+campanha (sem duplicar análises da mesma campanha)
        try {
            // Hoje: MAX(id) por conta+campanha garante que cada campanha aparece só uma vez
            $aiQuery = "SELECT al.*, aa.account_name,
                        COALESCE(al.objective,
                            JSON_UNQUOTE(JSON_EXTRACT(al.metrics_json, '$.objective')),
                            (SELECT r.objetivo FROM reports r
                             WHERE r.ad_account_id = al.ad_account_id
                               AND r.user_id = al.user_id
                             ORDER BY r.updated_at DESC LIMIT 1)
                        ) AS objetivo
                 FROM ai_logs al
                 JOIN ad_accounts aa ON aa.id = al.ad_account_id
                 WHERE al.user_id = ? AND DATE(al.created_at) = CURDATE()
                   AND al.id IN (
                       SELECT MAX(id) FROM ai_logs
                       WHERE user_id = ? AND DATE(created_at) = CURDATE()
                       GROUP BY ad_account_id, campaign_id
                   )
                 ORDER BY al.created_at DESC";
            $data['ai_today_list'] = $db->query($aiQuery, [$uid, $uid])->fetchAll();
            if (empty($data['ai_today_list'])) {
                // Fallback: análise mais recente por conta+campanha (qualquer data)
                $aiQuery2 = "SELECT al.*, aa.account_name,
                        COALESCE(al.objective,
                            JSON_UNQUOTE(JSON_EXTRACT(al.metrics_json, '$.objective')),
                            (SELECT r.objetivo FROM reports r
                             WHERE r.ad_account_id = al.ad_account_id
                               AND r.user_id = al.user_id
                             ORDER BY r.updated_at DESC LIMIT 1)
                        ) AS objetivo
                 FROM ai_logs al
                 JOIN ad_accounts aa ON aa.id = al.ad_account_id
                 WHERE al.user_id = ?
                   AND al.id IN (
                       SELECT MAX(id) FROM ai_logs
                       WHERE user_id = ?
                       GROUP BY ad_account_id, campaign_id
                   )
                 ORDER BY al.created_at DESC LIMIT 5";
                $data['ai_today_list'] = $db->query($aiQuery2, [$uid, $uid])->fetchAll();
            }
            $data['last_ai'] = $data['ai_today_list'][0] ?? null;
        } catch (\Throwable $e) { $data['ai_today_list'] = []; $data['last_ai'] = null; }

        // Saldo & Envios — dados para o card novo
        try {
            // Enviados este mês
            $data['sent_this_month'] = $db->query(
                "SELECT COUNT(*) FROM reports WHERE user_id=? AND sent_whatsapp=1 AND MONTH(sent_at)=MONTH(NOW()) AND YEAR(sent_at)=YEAR(NOW())",
                [$uid]
            )->fetchColumn();

            // Agendados ativos
            $data['reports_scheduled'] = $db->query(
                "SELECT COUNT(*) FROM reports WHERE user_id=? AND status IN ('active','scheduled') AND next_send_at IS NOT NULL",
                [$uid]
            )->fetchColumn();

            // Próximos 3 envios agendados
            $data['next_sends'] = $db->query(
                "SELECT r.title, r.next_send_at, r.frequency, c.name AS client_name
                  FROM reports r LEFT JOIN clients c ON r.client_id=c.id
                  WHERE r.user_id=? AND r.status IN ('active','scheduled') AND r.next_send_at >= NOW()
                  ORDER BY r.next_send_at ASC LIMIT 3",
                [$uid]
            )->fetchAll();

            // Relatórios atrasados (next_send_at no passado, cron não rodou)
            $data['overdue_reports'] = $db->query(
                "SELECT r.title, r.next_send_at, c.name AS client_name
                  FROM reports r LEFT JOIN clients c ON r.client_id=c.id
                  WHERE r.user_id=? AND r.status IN ('active','scheduled')
                  AND r.next_send_at IS NOT NULL AND r.next_send_at < NOW()
                  ORDER BY r.next_send_at ASC",
                [$uid]
            )->fetchAll();

            // Última vez que o cron rodou (arquivo gravado pelo cron)
            try {
                $cronFile = sys_get_temp_dir() . '/gestorpro_cron_last_run.txt';
                $data['cron_last_run'] = file_exists($cronFile) ? (int)file_get_contents($cronFile) : null;
            } catch (\Throwable $_e) { $data['cron_last_run'] = null; }

            // Gera notificação automática para relatórios em atraso (máx 1x por hora)
            if (!empty($data['overdue_reports'])) {
                $notifKey = sys_get_temp_dir() . '/gestorpro_overdue_notif_' . $uid . '.txt';
                $lastNotif = file_exists($notifKey) ? (int)file_get_contents($notifKey) : 0;
                if (time() - $lastNotif > 3600) { // 1x por hora no máximo
                    $titles = array_map(fn($r) => $r['title'], array_slice($data['overdue_reports'], 0, 3));
                    $body = count($data['overdue_reports']) . ' relatório(s) não enviado(s): ' . implode(', ', $titles);
                    try {
                        $db->query(
                            "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                            [$uid, '⚠ Cron não está rodando — envios atrasados', $body]
                        );
                        file_put_contents($notifKey, time());
                    } catch (\Throwable $_e) {}
                }
            }

            // Último envio realizado
            $data['last_sent'] = $db->query(
                "SELECT r.title, r.sent_at, c.name AS client_name
                  FROM reports r LEFT JOIN clients c ON r.client_id=c.id
                  WHERE r.user_id=? AND r.sent_whatsapp=1 AND r.sent_at IS NOT NULL
                  ORDER BY r.sent_at DESC LIMIT 1",
                [$uid]
            )->fetch();

            // Com falha (status sent mas sem confirmação — sent_at nulo com sent_whatsapp=0 e status=draft pode indicar erro)
            $data['reports_failed'] = $db->query(
                "SELECT COUNT(*) FROM reports WHERE user_id=? AND status='draft' AND sent_whatsapp=0 AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
                [$uid]
            )->fetchColumn();
        } catch (\Throwable $e) {
            $data['sent_this_month']   = 0;
            $data['reports_scheduled'] = 0;
            $data['next_sends']        = [];
            $data['last_sent']         = null;
            $data['reports_failed']    = 0;
        }

        $data['user'] = $user;

        // ══════════════════════════════════════════════════════════════
        // WIDGETS: Budget, Top Campanhas, Saúde, Projeção, Semana
        // Cache de 30 minutos no banco — evita chamadas repetidas à API Meta
        // ══════════════════════════════════════════════════════════════
        $cacheKey = 'widget_metrics_' . $uid . '_' . date('Y-m-d');
        $cachedMetrics = null;
        $cacheStale    = false; // cache existe mas expirou (stale)
        try {
            // 1º: tenta cache válido (não expirado)
            $cacheRow = $db->query(
                "SELECT payload, expires_at FROM dashboard_cache WHERE cache_key=? AND expires_at > NOW() LIMIT 1",
                [$cacheKey]
            )->fetch();
            if ($cacheRow) {
                $cachedMetrics = json_decode($cacheRow['payload'], true);
            } else {
                // 2º: tenta cache stale (expirado mas existente) — serve rápido e cron renova
                $staleRow = $db->query(
                    "SELECT payload FROM dashboard_cache WHERE cache_key=? ORDER BY expires_at DESC LIMIT 1",
                    [$cacheKey]
                )->fetch();
                if ($staleRow) {
                    $cachedMetrics = json_decode($staleRow['payload'], true);
                    $cacheStale    = true; // sinaliza que está desatualizado
                }
            }
        } catch (\Throwable $e) { $cachedMetrics = null; }

        // Se tem cache (válido ou stale): serve imediatamente, sem chamar API
        if ($cachedMetrics !== null) {
            $data['widget_metrics']       = $cachedMetrics;
            $data['metrics_cache_stale']  = $cacheStale;
        } elseif (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) {
            // Sem cache: serve banco imediatamente e atualiza via API em background
            // O admin vê dados do banco na hora, API atualiza em segundo plano
            // Na próxima visita já vai ter o cache com valores corretos da API
            $data['widget_metrics']      = []; // será preenchido do banco abaixo
            $data['metrics_cache_stale'] = true;
            $data['_bg_refresh']         = true; // sinaliza para fazer refresh em background
        } else {
        try {
            require_once __DIR__.'/ReportController.php';

            $activeReports = $db->query(
                "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token,
                        aa.account_name, c.name AS client_name
                 FROM reports r
                 LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
                 LEFT JOIN clients c ON r.client_id = c.id
                 WHERE r.user_id = ? AND r.status IN ('active','scheduled')
                 ORDER BY r.updated_at DESC LIMIT 200",
                [$uid]
            )->fetchAll();
            $activeReports = decryptTokens($activeReports);

            $widgetMetrics = []; // dados por relatório para os widgets

            foreach ($activeReports as $rep) {
                if (empty($rep['meta_token'])) continue;

                $periodType = $rep['period_type'] ?? 'last_7_days';
                [$start, $end] = ReportController::calcPeriodDates($periodType);

                $campIds = !empty($rep['camp_ids'])
                    ? array_filter(explode(',', $rep['camp_ids']))
                    : [];

                // Para MAX: usa a data mais antiga do banco para essa conta/campanha
                if ($start === 'MAX') {
                    $params2 = [$rep['ad_account_id']];
                    $cw2 = '';
                    if (!empty($campIds)) {
                        $cw2 = ' AND campaign_id IN (' . implode(',', array_fill(0, count($campIds), '?')) . ')';
                        $params2 = array_merge($params2, $campIds);
                    }
                    $minDate = $db->query(
                        "SELECT MIN(date) FROM campaign_metrics WHERE ad_account_id=?{$cw2}",
                        $params2
                    )->fetchColumn();
                    $start = $minDate ?: date('Y-m-d', strtotime('-90 days'));
                }

                // API com cache de 6h — rápido nas recargas, correto nos valores
                // Com 30 clientes: primeira carga do dia demora ~30s, depois instantâneo
                $m = null;
                try {
                    $m = ReportController::fetchMetricsCached(
                        $rep['meta_account_id'], $rep['meta_token'],
                        $start, $end, $campIds,
                        (int)$rep['ad_account_id'], $uid, 360
                    );
                } catch (\Throwable $_em) { $m = null; }
                // Fallback banco se API falhar
                if (!$m || (float)($m['spend'] ?? 0) <= 0) {
                    $m = ReportController::fetchMetricsFromDB(
                        (int)$rep['ad_account_id'], $start, $end, $campIds
                    );
                }

                if (!$m || (float)($m['spend'] ?? 0) <= 0) continue;

                $spend    = (float)($m['spend']       ?? 0);
                $ctr      = (float)($m['ctr']         ?? 0);
                $freq     = (float)($m['frequency']   ?? 0);
                $cpm      = (float)($m['cpm']         ?? 0);
                $msgs     = (int)  ($m['messages']    ?? $m['msg'] ?? 0);
                $leads    = (int)  ($m['leads'] ?? $m['conversions'] ?? 0);
                $profVisit= (int)  ($m['profile_visits'] ?? $m['profile_visit'] ?? 0);
                // Usa objetivo do relatório diretamente (agora com mensagem como opção)
                $objetivo = strtolower($rep['objetivo'] ?? 'trafego');

                // Dias ativos no período
                $daysInPeriod = max(1, (int)((strtotime($end) - strtotime($start)) / 86400));

                // ── PROJEÇÃO: mês atual e semana anterior com cache ──────────────────
                $mesAtualStart  = date('Y-m-01');
                $mesAtualEnd    = date('Y-m-d');
                $diasComGasto   = 0;
                $spendMesAtual  = 0;
                $ultimoDiaGasto = null;

                // ✅ Mês atual — só banco
                $mMesAtual = ReportController::fetchMetricsFromDB(
                    (int)$rep['ad_account_id'], $mesAtualStart, $mesAtualEnd, $campIds
                );
                if ($mMesAtual) {
                    $spendMesAtual = (float)($mMesAtual['spend'] ?? 0);
                }
                $diaAtualTs   = strtotime($mesAtualEnd);
                $mesInicioTs  = strtotime($mesAtualStart);
                $diasComGasto = max(1, (int)(($diaAtualTs - $mesInicioTs) / 86400) + 1);

                // Semana anterior com cache
                $prevEnd   = date('Y-m-d', strtotime('-7 days'));
                $prevStart = date('Y-m-d', strtotime('-14 days'));
                // ✅ Semana anterior — só banco
                $mPrev = ReportController::fetchMetricsFromDB(
                    (int)$rep['ad_account_id'], $prevStart, $prevEnd, $campIds
                );

                $spendPrev   = (float)($mPrev['spend'] ?? 0);
                $spendChange = ($spendPrev > 0)
                    ? round((($spend - $spendPrev) / $spendPrev) * 100)
                    : 0;

                // Score de saúde
                $score = 50;
                if ($ctr >= 2.0)   $score += 20; elseif ($ctr >= 1.0) $score += 10; elseif ($ctr > 0) $score -= 10;
                if ($freq <= 2.0)  $score += 15; elseif ($freq <= 3.0) $score += 5; elseif ($freq > 3.5) $score -= 15;
                if ($cpm > 0 && $cpm <= 15) $score += 15; elseif ($cpm > 30) $score -= 10;
                $score = max(10, min(100, $score));

                // Custo por resultado — baseado no objetivo detectado
                $costResult = 0; $costLabel = ''; $costColor = '#8b949e';
                $_clicks = (int)($m['clicks'] ?? 0);
                $_cpc    = ($_clicks > 0 && $spend > 0) ? round($spend / $_clicks, 2) : 0;
                if ($objetivo === 'mensagem') {
                    if ($msgs > 0)        { $costResult = round($spend / $msgs, 2); $costLabel = 'msg';    $costColor = '#1ABC9C'; }
                    elseif ($_cpc > 0)    { $costResult = $_cpc;                    $costLabel = 'clique'; $costColor = '#388bfd'; }
                } elseif ($objetivo === 'leads' || $objetivo === 'lead') {
                    if ($leads > 0)       { $costResult = round($spend / $leads, 2); $costLabel = 'lead';   $costColor = '#8b5cf6'; }
                    elseif ($_cpc > 0)    { $costResult = $_cpc;                     $costLabel = 'clique'; $costColor = '#388bfd'; }
                } elseif ($objetivo === 'vendas') {
                    if ($leads > 0)       { $costResult = round($spend / $leads, 2); $costLabel = 'venda';  $costColor = '#e67e22'; }
                    elseif ($_cpc > 0)    { $costResult = $_cpc;                     $costLabel = 'clique'; $costColor = '#388bfd'; }
                } elseif ($objetivo === 'engajamento') {
                    $eng = (int)($m['engagement'] ?? 0);
                    if ($eng > 0)         { $costResult = round($spend / $eng, 2); $costLabel = 'eng';    $costColor = '#F39C12'; }
                    elseif ($_cpc > 0)    { $costResult = $_cpc;                   $costLabel = 'clique'; $costColor = '#388bfd'; }
                } elseif ($objetivo === 'trafego') {
                    if ($profVisit > 0)   { $costResult = round($spend / $profVisit, 2); $costLabel = 'vis';    $costColor = '#388bfd'; }
                    elseif ($msgs > 0)    { $costResult = round($spend / $msgs, 2);      $costLabel = 'msg';    $costColor = '#1ABC9C'; }
                    elseif ($_cpc > 0)    { $costResult = $_cpc;                          $costLabel = 'clique'; $costColor = '#388bfd'; }
                } else {
                    if ($_cpc > 0)        { $costResult = $_cpc; $costLabel = 'clique'; $costColor = '#388bfd'; }
                }
                if (!$costLabel) {
                    if ($_cpc > 0) { $costResult = $_cpc; $costLabel = 'clique'; $costColor = '#388bfd'; }
                    else           { $costResult = (float)($m['ctr'] ?? 0); $costLabel = 'CTR%'; $costColor = '#F39C12'; }
                }

                // ── Detecta pausa e calcula peso da projeção dinamicamente ──────
                $diasPausada   = 0;
                $estaPausada   = false;
                $pesoPausada   = 0.0; // 0.0 = ativa (projeta normal) → 1.0 = parada total
                if ($ultimoDiaGasto !== null) {
                    $diasPausada = max(0, (int)((strtotime($mesAtualEnd) - strtotime($ultimoDiaGasto)) / 86400));

                    // Peso cresce linearmente de 0 (1 dia) até 1.0 (7+ dias parada)
                    // 1 dia  → peso 0.0  (pode ser atraso de dados, projeta normal)
                    // 2 dias → peso 0.17 (leve redução)
                    // 3 dias → peso 0.33
                    // 4 dias → peso 0.50 (projeção pela metade)
                    // 5 dias → peso 0.67
                    // 6 dias → peso 0.83
                    // 7+ dias→ peso 1.0  (projeta só o que já gastou)
                    $pesoPausada = min(1.0, max(0.0, ($diasPausada - 1) / 6));
                    $estaPausada = $diasPausada >= 2;
                }

                // Ritmo: gasto do mês ÷ dias que realmente houve spend (ignora dias zerados)
                $diaAtual      = (int)date('j');
                $diasMes       = (int)date('t');
                $diasRestantes = $diasMes - $diaAtual;
                $ritmo         = ($diasComGasto > 0 && $spendMesAtual > 0)
                    ? $spendMesAtual / $diasComGasto
                    : 0;

                // Projeção com peso dinâmico da pausa:
                // projecao_ativa   = gasto atual + ritmo × dias restantes  (campanha rodando)
                // projecao_parada  = só o que já gastou                    (campanha parada)
                // projecao_final   = interpolação entre os dois pelo peso
                //   peso 0.0 → 100% projeção ativa
                //   peso 0.5 → média entre as duas
                //   peso 1.0 → 100% projeção parada (só o gasto atual)
                if ($ritmo <= 0) {
                    $projecao = round($spendMesAtual, 2);
                } else {
                    $projecaoAtiva  = $spendMesAtual + ($ritmo * $diasRestantes);
                    $projecaoParada = $spendMesAtual;
                    $projecao = round(
                        $projecaoAtiva * (1 - $pesoPausada) + $projecaoParada * $pesoPausada,
                        2
                    );
                }

                // Gasto total desde o início (para widget Gasto da Campanha)
                $spendTotal = $spend;
                try {
                    $paramsTotal = [$rep['ad_account_id']];
                    $cwTotal = '';
                    if (!empty($campIds)) {
                        $cwTotal = ' AND campaign_id IN (' . implode(',', array_fill(0, count($campIds), '?')) . ')';
                        $paramsTotal = array_merge($paramsTotal, $campIds);
                    }
                    $minDateTotal = $db->query(
                        "SELECT MIN(date) FROM campaign_metrics WHERE ad_account_id=?{$cwTotal}",
                        $paramsTotal
                    )->fetchColumn();
                    if ($minDateTotal) {
                        $mTotal = ReportController::fetchMetricsFromDB(
                            (int)$rep['ad_account_id'], $minDateTotal, $end, $campIds
                        );
                        $spendTotal = (float)($mTotal['spend'] ?? $spend);
                    }
                } catch (\Throwable $_et) {}

                $widgetMetrics[] = [
                    'report_id'    => $rep['id'],
                    'title'        => $rep['title'],
                    'camp_labels'  => $rep['camp_labels'] ?? '',
                    'client_name'  => $rep['client_name'] ?? $rep['account_name'] ?? '',
                    'account_name' => $rep['account_name'] ?? '',
                    'objetivo'     => $objetivo,
                    'period_start' => $start,
                    'period_end'   => $end,
                    'spend'        => $spend,
                    'spend_total'  => $spendTotal,
                    'spend_prev'   => $spendPrev,
                    'spend_change' => $spendChange,
                    'ctr'          => $ctr,
                    'freq'         => $freq,
                    'cpm'          => $cpm,
                    'score'        => $score,
                    'cost_result'  => $costResult,
                    'cost_label'   => $costLabel,
                    'cost_color'   => $costColor,
                    'projecao'     => $projecao,
                    'ritmo_dia'    => round($ritmo, 2),
                    'dias_ativos'  => $diasComGasto,  // dias com spend > 0 no mês atual
                    'esta_pausada' => $estaPausada,   // true se parada há 2+ dias
                    'dias_pausada' => $diasPausada,   // quantos dias sem gasto
                    'peso_pausada' => round($pesoPausada, 2), // 0.0=ativa 1.0=parada total
                ];
            }

            $data['widget_metrics'] = $widgetMetrics;

            // Se foi chamado em background (fastcgi já terminou), apenas salva cache
            // Salva no cache por 30 minutos
            try {
                $payload = json_encode($widgetMetrics, JSON_UNESCAPED_UNICODE);
                $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $db->query(
                    "INSERT INTO dashboard_cache (cache_key, user_id, payload, expires_at)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE payload=VALUES(payload), expires_at=VALUES(expires_at), created_at=NOW()",
                    [$cacheKey, $uid, $payload, $expires]
                );
            } catch (\Throwable $e) { /* cache opcional, não bloqueia */ }

        } catch (\Throwable $e) {
            $data['widget_metrics'] = [];
        }
        } // end cache else

        // ── Widget Saldo Pré-pago ─────────────────────────────────────────────
        try {
            require_once __DIR__.'/BudgetController.php';
            $data['prepago_widget'] = BudgetController::getPrepagoWidget($uid);
        } catch (\Throwable $e) {
            $data['prepago_widget'] = [];
        }

        // ── Widget Campanhas com Problema (derivado dos relatórios cadastrados) ──
        // Usa widget_metrics já calculado — sem chamadas extras à API Meta
        try {
            $data['campanhas_problema'] = self::buildCampanhasProblemaFromWidgets(
                $data['widget_metrics'] ?? []
            );
        } catch (\Throwable $e) {
            $data['campanhas_problema'] = [];
        }

        // Dispara refresh em background se não tinha cache
        if (!empty($data['_bg_refresh']) && function_exists('fastcgi_finish_request')) {
            register_shutdown_function(function() use ($db, $uid) {
                ignore_user_abort(true);
                try {
                    require_once __DIR__.'/ReportController.php';
                    $reps = $db->query(
                        "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token AS meta_token
                         FROM reports r
                         LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
                         WHERE r.user_id=? AND r.status IN ('active','scheduled') LIMIT 50",
                        [$uid]
                    )->fetchAll();
                    $reps = decryptTokens($reps);
                    foreach ($reps as $rep) {
                        if (empty($rep['meta_token'])) continue;
                        try {
                            [$s2, $e2] = ReportController::calcPeriodDates($rep['period_type'] ?? 'last_7_days');
                            if ($s2 === 'MAX') $s2 = date('Y-m-d', strtotime('-90 days'));
                            $cIds = !empty($rep['camp_ids']) ? array_filter(explode(',', $rep['camp_ids'])) : [];
                            ReportController::fetchMetricsCached(
                                $rep['meta_account_id'], $rep['meta_token'],
                                $s2, $e2, $cIds, (int)$rep['ad_account_id'], $uid, 360
                            );
                        } catch (\Throwable $_ex) {}
                        usleep(300000);
                    }
                    // Limpa widget_metrics para recalcular na próxima visita com dados frescos
                    $cacheKeyBg = 'widget_metrics_' . $uid . '_' . date('Y-m-d');
                    $db->query("DELETE FROM dashboard_cache WHERE cache_key=?", [$cacheKeyBg]);
                } catch (\Throwable $_ex) {}
            });
        }

        require_once __DIR__.'/../views/dashboard/index.php';
    }

    // AJAX: salvar post-it
    public function saveNote(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $id      = (int)($_POST['id']??0);
        $content = trim($_POST['content']??'');
        $color   = trim($_POST['color']??'#F7DC6F');
        $tc      = trim($_POST['text_color']??'#2c2a00');
        try {
            if ($id) {
                $db->query("UPDATE dashboard_notes SET content=?,color=?,text_color=?,updated_at=NOW() WHERE id=? AND user_id=?",[$content,$color,$tc,$id,$uid]);
            } else {
                $db->query("INSERT INTO dashboard_notes (user_id,content,color,text_color,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())",[$uid,$content,$color,$tc]);
                $id = $db->lastInsertId();
            }
            echo json_encode(['success'=>true,'id'=>$id]);
        } catch (\Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    }

    // AJAX: deletar post-it
    public function deleteNote(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id']??0);
        try {
            Database::getInstance()->query("DELETE FROM dashboard_notes WHERE id=? AND user_id=?",[$id,$uid]);
            echo json_encode(['success'=>true]);
        } catch (\Throwable $e) { echo json_encode(['success'=>false]); }
    }

    // Dashboard por cliente
    public function clientDashboard(): void {
        requireAuth();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $clientId = (int)($_GET['id'] ?? 0);
        header('Content-Type: application/json');

        if (!$clientId) { echo json_encode(['success'=>false,'error'=>'Cliente não informado']); return; }

        $client = $db->query("SELECT * FROM clients WHERE id=? AND user_id=?", [$clientId,$uid])->fetch();
        if (!$client) { echo json_encode(['success'=>false,'error'=>'Cliente não encontrado']); return; }

        // ── Período selecionado ────────────────────────────────────────────────
        $period      = sanitize($_GET['period'] ?? 'last_30_days');
        $customStart = sanitize($_GET['custom_start'] ?? '');
        $customEnd   = sanitize($_GET['custom_end']   ?? '');
        $today       = date('Y-m-d');

        $periodMap = [
            'last_7_days'  => [date('Y-m-d', strtotime('-7 days')),  $today],
            'last_15_days' => [date('Y-m-d', strtotime('-15 days')), $today],
            'last_30_days' => [date('Y-m-d', strtotime('-30 days')), $today],
            'last_90_days' => [date('Y-m-d', strtotime('-90 days')), $today],
            'this_month'   => [date('Y-m-01'), $today],
            'last_month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
            'maximum'      => [date('Y-m-d', strtotime('-5 years')), $today],
        ];

        if ($period === 'custom' && $customStart && $customEnd) {
            $start = $customStart;
            $end   = $customEnd;
        } elseif (isset($periodMap[$period])) {
            [$start, $end] = $periodMap[$period];
        } else {
            [$start, $end] = $periodMap['last_30_days'];
        }

        $periodLabels = [
            'last_7_days'  => 'Últimos 7 dias',
            'last_15_days' => 'Últimos 15 dias',
            'last_30_days' => 'Últimos 30 dias',
            'last_90_days' => 'Últimos 90 dias',
            'this_month'   => 'Este mês',
            'last_month'   => 'Mês passado',
            'maximum'      => 'Máximo (desde o início)',
            'custom'       => 'Personalizado',
        ];
        $periodoLabel = ($periodLabels[$period] ?? 'Período')
            . ': ' . date('d/m/Y', strtotime($start))
            . ' a ' . date('d/m/Y', strtotime($end));

        // Contas de anúncio do cliente
        $accounts = $db->query(
            "SELECT id, account_name, platform, status FROM ad_accounts WHERE client_id=? AND user_id=? ORDER BY account_name",
            [$clientId, $uid]
        )->fetchAll();

        // Se não há contas vinculadas pelo client_id, buscar pelos relatórios
        if (empty($accounts)) {
            $accFromReports = $db->query(
                "SELECT DISTINCT aa.id, aa.account_name, aa.platform, aa.status
                 FROM reports r
                 JOIN ad_accounts aa ON r.ad_account_id = aa.id
                 WHERE r.client_id=? AND r.user_id=?",
                [$clientId, $uid]
            )->fetchAll();
            if (!empty($accFromReports)) {
                $accounts = $accFromReports;
            }
        }
        $accIds = array_column($accounts, 'id');

        // Relatórios do cliente
        $reports = $db->query(
            "SELECT id, title, frequency, send_time, send_days, status, sent_at, next_send_at, last_send_status
             FROM reports WHERE client_id=? AND user_id=? ORDER BY created_at DESC LIMIT 20",
            [$clientId, $uid]
        )->fetchAll();

        // Alertas do cliente
        $alerts = $db->query(
            "SELECT id, name, type, ativo, ultimo_envio FROM alerts WHERE client_id=? AND user_id=?",
            [$clientId, $uid]
        )->fetchAll();

        // ── Métricas: API ao vivo para cada conta ─────────────────────────────
        $metricas   = [];
        $apiMetrics = null;

        require_once __DIR__.'/ReportController.php';

        foreach ($accounts as $acc) {
            $accFull = decryptTokens($db->query("SELECT * FROM ad_accounts WHERE id=?", [$acc['id']])->fetch() ?: []);
            if (!$accFull || empty($accFull['access_token'])) continue;
            try {
                // Para período máximo, usa fetchMetricsMeta sem filtro de campanhas
                if ($period === 'maximum') {
                    $m = ReportController::fetchMetricsMetaAllStatus(
                        $accFull['account_id'], $accFull['access_token'], $start, $end
                    );
                } else {
                    $m = ReportController::fetchMetricsMetaAllStatus(
                        $accFull['account_id'], $accFull['access_token'], $start, $end
                    );
                    if ($m === null) {
                        $m = ReportController::fetchMetricsMeta(
                            $accFull['account_id'], $accFull['access_token'], $start, $end
                        );
                    }
                }
                if ($m !== null) {
                    if (!$apiMetrics) {
                        $apiMetrics = $m;
                    } else {
                        // Soma métricas de múltiplas contas
                        foreach (['spend','impressions','clicks','reach','msg','msg_all','leads','conversions','purchase','profile_visit'] as $k) {
                            $apiMetrics[$k] = ($apiMetrics[$k] ?? 0) + ($m[$k] ?? 0);
                        }
                        // Recalcula médias
                        $apiMetrics['ctr']       = $apiMetrics['impressions'] > 0
                            ? round($apiMetrics['clicks']      / $apiMetrics['impressions'] * 100, 2) : 0;
                        $apiMetrics['cpc']       = $apiMetrics['clicks'] > 0
                            ? round($apiMetrics['spend']       / $apiMetrics['clicks'], 2) : 0;
                        $apiMetrics['cpm']       = $apiMetrics['impressions'] > 0
                            ? round($apiMetrics['spend']       / $apiMetrics['impressions'] * 1000, 2) : 0;
                        $apiMetrics['frequency'] = $apiMetrics['reach'] > 0
                            ? round($apiMetrics['impressions'] / $apiMetrics['reach'], 2) : 0;
                    }
                }
            } catch (\Throwable $e) {}
        }

        if ($apiMetrics) {
            $msgs = (int)(max($apiMetrics['msg'] ?? 0, $apiMetrics['msg_all'] ?? 0));
            $metricas = [
                'spend'          => (float)($apiMetrics['spend']         ?? 0),
                'impressions'    => (int)  ($apiMetrics['impressions']   ?? 0),
                'clicks'         => (int)  ($apiMetrics['clicks']        ?? 0),
                'reach'          => (int)  ($apiMetrics['reach']         ?? 0),
                'ctr'            => (float)($apiMetrics['ctr']           ?? 0),
                'cpc'            => (float)($apiMetrics['cpc']           ?? 0),
                'cpm'            => (float)($apiMetrics['cpm']           ?? 0),
                'frequency'      => (float)($apiMetrics['frequency']     ?? 0),
                'messages'       => $msgs,
                'leads'          => (int)  ($apiMetrics['leads']         ?? 0),
                'conversions'    => (int)  ($apiMetrics['conversions']   ?? 0),
                'purchases'      => (int)  ($apiMetrics['purchase']      ?? 0),
                'profile_visits' => (int)  ($apiMetrics['profile_visit'] ?? 0),
                'roas'           => (float)($apiMetrics['roas']          ?? 0),
            ];
        } elseif (!empty($accIds)) {
            // Fallback: banco local
            $phs = implode(',', array_fill(0, count($accIds), '?'));
            $params = array_merge($accIds, [$start, $end]);
            $row = $db->query(
                "SELECT SUM(spend) AS spend, SUM(impressions) AS impressions,
                        SUM(clicks) AS clicks, SUM(conversions) AS conversions,
                        SUM(messages) AS messages, SUM(leads) AS leads,
                        SUM(reach) AS reach, SUM(purchases) AS purchases,
                        SUM(profile_visits) AS profile_visits,
                        AVG(ctr) AS ctr, AVG(cpc) AS cpc, AVG(cpm) AS cpm,
                        CASE WHEN SUM(reach)>0 THEN ROUND(SUM(impressions)/SUM(reach),2) ELSE 0 END AS frequency,
                        AVG(roas) AS roas
                 FROM campaign_metrics
                 WHERE ad_account_id IN ($phs) AND date BETWEEN ? AND ?",
                $params
            )->fetch();
            $metricas = $row ?: [];
        }

        // Análises IA do cliente (últimas 5)
        $aiLogs = [];
        if (!empty($accIds)) {
            $phs = implode(',', array_fill(0, count($accIds), '?'));
            $aiLogs = $db->query(
                "SELECT account_name, campaign_name, period, objective, created_at
                 FROM ai_logs WHERE ad_account_id IN ($phs) AND user_id=?
                 ORDER BY created_at DESC LIMIT 5",
                array_merge($accIds, [$uid])
            )->fetchAll();
        }

        echo json_encode([
            'success'       => true,
            'client'        => $client,
            'accounts'      => $accounts,
            'reports'       => $reports,
            'alerts'        => $alerts,
            'metricas'      => $metricas,
            'ai_logs'       => $aiLogs,
            'periodo_label' => $periodoLabel,
        ]);
    }

    // ── Busca campanhas com problema em tempo real da API Meta ──────────────
    /**
     * Deriva "campanhas com problema" a partir do widget_metrics já calculado,
     * sem fazer nenhuma chamada extra à API Meta.
     * Usa as mesmas métricas (ctr, freq, spend, esta_pausada) que já foram
     * buscadas durante a montagem dos widgets do dashboard.
     */
    public static function buildCampanhasProblemaFromWidgets(array $widgetMetrics): array {
        $problemas = [];

        foreach ($widgetMetrics as $wm) {
            $issues   = [];
            $urgencia = 0;

            $ctr         = (float)($wm['ctr']         ?? 0);
            $freq        = (float)($wm['freq']         ?? 0);
            $estaPausada = (bool) ($wm['esta_pausada'] ?? false);
            $diasPausada = (int)  ($wm['dias_pausada'] ?? 0);
            $spend       = (float)($wm['spend']        ?? 0);

            // Sem entrega (campanha parada há 2+ dias com gasto > 0 no período)
            if ($estaPausada && $spend > 0) {
                $issues[] = [
                    'label' => 'Sem entrega hoje',
                    'color' => '#e74c3c',
                    'bg'    => 'rgba(231,76,60,.18)',
                ];
                $urgencia += 80;
            }

            // CTR fraco / baixo (últimos 7 dias via period do relatório)
            if ($ctr > 0 && $ctr < 0.5) {
                $issues[] = [
                    'label' => 'CTR baixo (' . round($ctr, 2) . '%)',
                    'color' => '#e74c3c',
                    'bg'    => 'rgba(231,76,60,.18)',
                ];
                $urgencia += 40;
            } elseif ($ctr >= 0.5 && $ctr < 1.0) {
                $issues[] = [
                    'label' => 'CTR fraco (' . round($ctr, 2) . '%)',
                    'color' => '#F39C12',
                    'bg'    => 'rgba(243,156,18,.18)',
                ];
                $urgencia += 20;
            }

            // Frequência alta / elevada
            if ($freq > 3.5) {
                $issues[] = [
                    'label' => 'Freq. alta (' . round($freq, 1) . ')',
                    'color' => '#e74c3c',
                    'bg'    => 'rgba(231,76,60,.18)',
                ];
                $urgencia += 35;
            } elseif ($freq > 2.5) {
                $issues[] = [
                    'label' => 'Freq. elevada (' . round($freq, 1) . ')',
                    'color' => '#8b5cf6',
                    'bg'    => 'rgba(139,92,246,.18)',
                ];
                $urgencia += 15;
            }

            if (empty($issues)) continue;

            $problemas[] = [
                'camp_id'     => $wm['report_id'],
                'camp_name'   => $wm['title'],
                'client_name' => $wm['client_name'] ?? '',
                'account_name'=> $wm['account_name'] ?? '',
                'camp_labels' => $wm['camp_labels'] ?? '',
                'status'      => 'ACTIVE',
                'issues'      => $issues,
                'urgencia'    => $urgencia,
            ];
        }

        usort($problemas, fn($a, $b) => $b['urgencia'] - $a['urgencia']);
        return array_slice($problemas, 0, 100);
    }

    /**
     * @deprecated Substituída por buildCampanhasProblemaFromWidgets.
     * Mantida apenas para referência — não é mais chamada pelo dashboard.
     */
    public static function fetchCampanhasComProblema(int $userId, $db): array {
        $accounts = $db->query(
            "SELECT aa.id, aa.account_id, aa.access_token, aa.account_name, c.name AS client_name
             FROM ad_accounts aa
             LEFT JOIN clients c ON aa.client_id = c.id
             WHERE aa.user_id = ? AND aa.status = 'active' AND aa.platform = 'meta'",
            [$userId]
        )->fetchAll();
        $accounts = decryptTokens($accounts);

        if (empty($accounts)) return [];

        $problemas = [];

        foreach ($accounts as $acc) {
            if (empty($acc['access_token'])) continue;
            try {
                // Busca campanhas ativas/com problema
                $url = "https://graph.facebook.com/v19.0/act_{$acc['account_id']}/campaigns"
                     . "?fields=id,name,status,effective_status,daily_budget"
                     . "&effective_status=[\"ACTIVE\",\"WITH_ISSUES\",\"IN_PROCESS\"]"
                     . "&limit=50"
                     . "&access_token=" . urlencode($acc['access_token']);
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);
                $raw  = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code !== 200 || !$raw) continue;
                $camps = json_decode($raw, true);
                if (empty($camps['data'])) continue;

                 // ── Insights HOJE: spend e impressões para checar entrega ──────────
                 $today = date('Y-m-d');
                 $urlInsHoje = "https://graph.facebook.com/v19.0/act_{$acc['account_id']}/insights"
                         . "?fields=campaign_id,spend,impressions"
                         . "&time_range={\"since\":\"{$today}\",\"until\":\"{$today}\"}"
                         . "&level=campaign&limit=50"
                         . "&access_token=" . urlencode($acc['access_token']);
                 $chH = curl_init($urlInsHoje);
                 curl_setopt_array($chH, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);
                 $rawH  = curl_exec($chH);
                 $codeH = curl_getinfo($chH, CURLINFO_HTTP_CODE);
                 curl_close($chH);
                 $insHoje = [];
                 if ($codeH === 200 && $rawH) {
                     $dH = json_decode($rawH, true);
                     foreach (($dH['data'] ?? []) as $i) {
                         $insHoje[$i['campaign_id']] = $i;
                     }
                 }

                 // ── Insights 7 DIAS: CTR e frequência ───────────────────────────────
                 $since7 = date('Y-m-d', strtotime('-7 days'));
                 $urlIns7 = "https://graph.facebook.com/v19.0/act_{$acc['account_id']}/insights"
                         . "?fields=campaign_id,spend,ctr,frequency"
                         . "&time_range={\"since\":\"{$since7}\",\"until\":\"{$today}\"}"
                         . "&level=campaign&limit=50"
                         . "&access_token=" . urlencode($acc['access_token']);
                 $ch2 = curl_init($urlIns7);
                 curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);
                 $rawI  = curl_exec($ch2);
                 $codeI = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                 curl_close($ch2);
                 $ins7Dias = [];
                 if ($codeI === 200 && $rawI) {
                     $d7 = json_decode($rawI, true);
                     foreach (($d7['data'] ?? []) as $i) {
                         $ins7Dias[$i['campaign_id']] = $i;
                     }
                 }

                 foreach ($camps['data'] as $camp) {
                     $issues   = [];
                     $urgencia = 0;
                     $status   = $camp['effective_status'] ?? $camp['status'] ?? '';
                     $iHoje    = $insHoje[$camp['id']]  ?? null;
                     $i7       = $ins7Dias[$camp['id']] ?? null;
                     $spend7   = (float)($i7['spend'] ?? 0);

                     // Ignora campanhas sem gasto nos últimos 7 dias (antigas/encerradas)
                     if ($status === 'ACTIVE' && $spend7 <= 0) continue;

                     if ($status === 'WITH_ISSUES') {
                         $issues[] = ['label'=>'Com erro','color'=>'#e74c3c','bg'=>'rgba(231,76,60,.18)'];
                         $urgencia += 100;
                     }
                     if ($status === 'IN_PROCESS') {
                         $issues[] = ['label'=>'Em análise','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.18)'];
                         $urgencia += 20;
                     }

                     // ── Entrega HOJE (só alerta se estava rodando na semana) ──────────
                     if ($status === 'ACTIVE') {
                         $spendHoje = (float)($iHoje['spend'] ?? 0);
                         $imprHoje  = (int)($iHoje['impressions'] ?? 0);
                         if ($spendHoje <= 0 && $imprHoje <= 0) {
                             $issues[] = ['label'=>'Sem entrega hoje','color'=>'#e74c3c','bg'=>'rgba(231,76,60,.18)'];
                             $urgencia += 80;
                         }
                     }

                     // ── CTR e Frequência dos últimos 7 dias ──────────────────────────
                     if ($i7) {
                         $ctr  = (float)($i7['ctr']      ?? 0);
                         $freq = (float)($i7['frequency'] ?? 0);

                         // CTR: < 0.5% = baixo, 0.5%-1% = fraco, >= 1% não alerta
                         if ($ctr > 0 && $ctr < 0.5) {
                             $issues[] = ['label'=>'CTR baixo ('.round($ctr,2).'%)','color'=>'#e74c3c','bg'=>'rgba(231,76,60,.18)'];
                             $urgencia += 40;
                         } elseif ($ctr >= 0.5 && $ctr < 1.0) {
                             $issues[] = ['label'=>'CTR fraco ('.round($ctr,2).'%)','color'=>'#F39C12','bg'=>'rgba(243,156,18,.18)'];
                             $urgencia += 20;
                         }

                         // Frequência: > 3.5 = alta, 2.5-3.5 = elevada
                         if ($freq > 3.5) {
                             $issues[] = ['label'=>'Freq. alta ('.round($freq,1).')','color'=>'#e74c3c','bg'=>'rgba(231,76,60,.18)'];
                             $urgencia += 35;
                         } elseif ($freq > 2.5) {
                             $issues[] = ['label'=>'Freq. elevada ('.round($freq,1).')','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.18)'];
                             $urgencia += 15;
                         }
                     }

                    if (empty($issues)) continue;

                    $problemas[] = [
                        'camp_id'     => $camp['id'],
                        'camp_name'   => $camp['name'],
                        'client_name' => $acc['client_name'] ?? $acc['account_name'],
                        'status'      => $status,
                        'issues'      => $issues,
                        'urgencia'    => $urgencia,
                    ];
                }
            } catch (\Throwable $e) { continue; }
        }

        usort($problemas, fn($a, $b) => $b['urgencia'] - $a['urgencia']);
        return array_slice($problemas, 0, 100);
    }

}

// ============================================================
// ATENÇÃO: adicionar o método abaixo ao ReportController.php
// Ele é necessário para a projeção corrigida funcionar.
// Cole dentro da classe ReportController como método estático.
// ============================================================
//
// /**
//  * Retorna breakdown diário de spend via API Meta (time_increment=1).
//  * Cada item do array retornado tem: ['date_start', 'date_stop', 'spend']
//  */
// public static function fetchMetricsMetaDaily(
//     string $accountId,
//     string $token,
//     string $start,
//     string $end,
//     array $campIds = []
// ): array {
//     $timeRange = urlencode(json_encode(['since' => $start, 'until' => $end]));
//     $url = "https://graph.facebook.com/v19.0/act_{$accountId}/insights"
//          . "?fields=spend"
//          . "&time_range={$timeRange}"
//          . "&time_increment=1"
//          . "&access_token={$token}";
//
//     if (!empty($campIds)) {
//         $filtering = json_encode([[
//             'field'    => 'campaign.id',
//             'operator' => 'IN',
//             'value'    => array_values($campIds),
//         ]]);
//         $url .= '&filtering=' . urlencode($filtering);
//     }
//
//     $ctx  = stream_context_create(['http' => ['timeout' => 15]]);
//     $resp = @file_get_contents($url, false, $ctx);
//     if (!$resp) return [];
//     $data = json_decode($resp, true);
//     return $data['data'] ?? [];
// }