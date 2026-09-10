<?php
/**
 * GestorPro — Cron de Alertas de Saldo & Métricas (HTTP)
 *
 * Configure no cron-job.org para rodar a CADA 1 MINUTO:
 * GET https://seudominio.com.br/cron/run-alerts.php?key=CRON_SECRET
 *
 * O CRON_SECRET é gerado automaticamente em config/config.php:
 *   define('CRON_SECRET', 'GST_'.hash('sha256', APP_SECRET.'_cron_2025'));
 */

// ── Carrega config ANTES de checar a chave ────────────────────────────────────
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');


// ── Notificação WhatsApp de falha no cron ────────────────────────────────────
function notificarFalhaCron(string $tipo, string $erro): void {
    try {
        require_once __DIR__.'/../config/database.php';
        $db = Database::getInstance();
        // Pega primeira instância conectada e o dono do sistema
        $inst = $db->query(
            "SELECT wi.instance_name, u.phone, u.id as user_id
             FROM whatsapp_instances wi
             JOIN users u ON wi.user_id = u.id
             WHERE wi.status = 'connected'
             ORDER BY wi.id ASC LIMIT 1"
        )->fetch();
        if (!$inst || empty($inst['phone']) || empty($inst['instance_name'])) return;
        $phone    = preg_replace('/\D/', '', $inst['phone']);
        $instance = $inst['instance_name'];
        $msg = "⚠️ *CRON FALHOU — GestorADS*\n\n"
             . "🔧 *Tipo:* $tipo\n"
             . "❌ *Erro:* " . mb_substr($erro, 0, 300) . "\n"
             . "🕐 *Hora:* " . date('d/m/Y H:i:s') . "\n\n"
             . "_Verifique o sistema._";
        require_once __DIR__.'/../controllers/ReportController.php';
        ReportController::sendWhatsAppStatic($instance, $phone, $msg);
    } catch (\Throwable $e) {
        // silencia — não pode causar loop
    }
}

set_exception_handler(function(Throwable $e) {
    notificarFalhaCron('Alertas', $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')');
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
require_once __DIR__.'/../core/TokenCrypto.php';
require_once __DIR__.'/../controllers/ReportController.php';

// ── LOCK DE PROCESSO (expira em 90s para não travar) ─────────────────────────
$lockFile = sys_get_temp_dir() . '/gestorpro_run_alerts.lock';
// Se o arquivo de lock existe mas tem mais de 90s, remove (execução anterior travou)
if (file_exists($lockFile) && (time() - filemtime($lockFile)) > 90) {
    @unlink($lockFile);
}
$fp = fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    echo json_encode([
        'success'  => true,
        'hora'     => date('H:i'),
        'enviados' => 0,
        'erros'    => 0,
        'log'      => ['SKIP: outra execução já está rodando (lock ativo — aguarde 90s)'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
touch($lockFile);
register_shutdown_function(function() use ($fp, $lockFile) {
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);
});
// ─────────────────────────────────────────────────────────────────────────────

$now       = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$horaAtual = $now->format('H:i');
$diaAtual  = $now->format('w'); // 0=Dom, 1=Seg…
$log       = [];
$enviados  = 0;
$erros     = 0;

$log[] = "Executado: " . $now->format('Y-m-d H:i:s') . " | Hora: $horaAtual | Dia: $diaAtual";

$db = Database::getInstance();

// ── Grava heartbeat de execução (usado pelo monitor para saber que o cron está vivo) ──
try {
    $db->query(
        "INSERT INTO user_ai_settings (user_id, setting_key, setting_value)
         VALUES (1, 'cron_run_alerts_last_ping', ?)
         ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()",
        [$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]
    );
} catch (\Throwable $_hb) {}

// Migration automática: alert_name em alert_logs
try { $db->query("ALTER TABLE alert_logs ADD COLUMN IF NOT EXISTS alert_name VARCHAR(200) DEFAULT NULL"); } catch (\Throwable $_m) {}
try { $db->query("ALTER TABLE alerts ADD COLUMN IF NOT EXISTS ultimo_erros_hash VARCHAR(64) DEFAULT NULL"); } catch (\Throwable $_m) {}
try { $db->query("ALTER TABLE alerts ADD COLUMN IF NOT EXISTS modo_disparo ENUM('agendado','inteligente') NOT NULL DEFAULT 'agendado'"); } catch (\Throwable $_m) {}
try { $db->query("ALTER TABLE alerts ADD COLUMN IF NOT EXISTS proximo_reenvio_erro DATETIME DEFAULT NULL"); } catch (\Throwable $_m) {}

// ── Detecta colunas opcionais ─────────────────────────────────────────────────
$temClientId = (bool)$db->query("SHOW COLUMNS FROM alerts LIKE 'client_id'")->fetch();
$log[] = "client_id na tabela: " . ($temClientId ? 'SIM' : 'NÃO — execute a migração SQL!');

if ($temClientId) {
    $clientCols = ", c.name AS client_name, c.company AS client_company";
    $clientJoin = "LEFT JOIN clients c ON a.client_id = c.id";
} else {
    $clientCols = ", NULL AS client_name, NULL AS client_company";
    $clientJoin = "";
}

// ── Busca IDs de alertas ativos ───────────────────────────────────────────────
$alertIds = $db->query(
    "SELECT a.id FROM alerts a WHERE a.ativo = 1"
)->fetchAll(PDO::FETCH_COLUMN);

$log[] = "Alertas ativos: " . count($alertIds);

foreach ($alertIds as $alertId) {

    // ── CLAIM ATÔMICO ─────────────────────────────────────────────────────────
    $db->begin();
    $alert = $db->query(
        "SELECT a.*, aa.account_id, aa.account_name, aa.access_token, aa.platform,
                wi.instance_name
                $clientCols
         FROM alerts a
         LEFT JOIN ad_accounts aa ON a.ad_account_id = aa.id
         LEFT JOIN whatsapp_instances wi ON a.whatsapp_id = wi.id
         $clientJoin
         WHERE a.id = ? AND a.ativo = 1
         FOR UPDATE",
        [$alertId]
    )->fetch();
    $alert = $alert ? decryptTokens($alert) : null;

    if (!$alert) {
        $db->rollback();
        $log[] = "SKIP alerta #$alertId: inativo ou já processado";
        continue;
    }
    $db->commit();
    // ─────────────────────────────────────────────────────────────────────────

    $log[] = "--- Alerta #{$alert['id']} '{$alert['name']}' tipo={$alert['type']} ---";
    $log[] = "  account_id=" . ($alert['account_id'] ?? '(vazio)')
           . " | instance=" . ($alert['instance_name'] ?? '(vazio)')
           . " | phone=" . ($alert['recipient_phone'] ?? '(vazio)');

    // ── 1. Verifica condição ──────────────────────────────────────────────────
    $saldoAtual       = null;
    $metricaAtual     = null;
    $condicaoAtingida = false;
    $erroContaInfo    = [];
    $hashAtual        = null;

    if ($alert['type'] === 'saldo_minimo' && !empty($alert['access_token'])) {
        $saldoAtual = buscarSaldoMeta($alert['account_id'], $alert['access_token']);
        $log[] = "  saldo_atual=" . ($saldoAtual !== null
            ? 'R$ ' . number_format($saldoAtual, 2, ',', '.')
            : 'ERRO ao buscar')
            . " | minimo=R$ " . number_format((float)$alert['saldo_minimo'], 2, ',', '.');

        if ($saldoAtual !== null && $saldoAtual <= (float)$alert['saldo_minimo']) {
            $condicaoAtingida = true;
            $log[] = "  CONDIÇÃO ATINGIDA (saldo baixo)";
        } else {
            $log[] = "  condição não atingida";
        }
    } elseif ($alert['type'] === 'erro_conta') {
        require_once __DIR__.'/../core/MetaHelpers.php';
        $erroContaInfo = verificarErroMeta($alert['account_id'], $alert['access_token'] ?? '');
        $condicaoAtingida = !empty($erroContaInfo);
        $log[] = "  tipo=erro_conta | condição=" . ($condicaoAtingida ? 'ATINGIDA: ' . implode(' | ', $erroContaInfo) : 'não atingida');
    } elseif (in_array($alert['type'], ['ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo'])) {
        if (empty($alert['access_token'])) {
            $log[] = "  SKIP: access_token vazio";
        } else {
            // ── FIX: usar valor_threshold; se NULL, NOT configurado (não cair no saldo_minimo) ──
            $limiar = isset($alert['valor_threshold']) && $alert['valor_threshold'] !== null
                ? (float)$alert['valor_threshold']
                : 0.0;
            if ($limiar <= 0) {
                $log[] = "  SKIP: valor_threshold não configurado (NULL ou 0) — edite o alerta e defina o limite";
            } else {
                // Usa o mesmo calcPeriodDates do ReportController — igual ao relatório
                $periodType = $alert['period_type'] ?? 'last_7_days';
                [$mStart, $mEnd] = ReportController::calcPeriodDates($periodType);
                // Para período MAX: busca campanhas ATIVAS, pega o start_time da mais antiga
                // e filtra métricas por essas campanhas — igual ao dashboard/relatório
                $alertCampIds = [];
                if ($mStart === 'MAX') {
                    [$mStart, $alertCampIds] = fetchActiveCampaignsAndStartDate($alert['account_id'], $alert['access_token']);
                    $log[] = "  Período MAX: início campanha ativa = $mStart | camps=" . (empty($alertCampIds) ? 'todas' : implode(',', $alertCampIds));
                }
                try {
                    $diasPeriodo = (int)((strtotime($mEnd) - strtotime($mStart)) / 86400);
                    // Usa os IDs das campanhas ativas como filtro (igual ao relatório)
                    if (!empty($alertCampIds)) {
                        $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, $alertCampIds);
                    } elseif ($diasPeriodo > 90) {
                        $mData = ReportController::fetchMetricsMetaAllStatus($alert['account_id'], $alert['access_token'], $mStart, $mEnd);
                    } else {
                        $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, ['__ALL_STATUS__']);
                        if (!$mData) $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, []);
                    }
                    $log[] = "  Período: $periodType ($mStart a $mEnd) | spend=" . ($mData ? 'R$ '.(float)($mData['spend']??0) : 'sem dados');

                    // FALLBACK: se ainda sem dados, tenta last_30_days e last_7_days
                    if (!$mData) {
                        $fallbacks = ['last_30_days','last_7_days'];
                        foreach ($fallbacks as $fb) {
                            [$fbStart, $fbEnd] = ReportController::calcPeriodDates($fb);
                            $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $fbStart, $fbEnd, ['__ALL_STATUS__']);
                            if (!$mData) $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $fbStart, $fbEnd, []);
                            if ($mData) {
                                $mStart = $fbStart; $mEnd = $fbEnd;
                                $log[] = "  FALLBACK para $fb ($fbStart a $fbEnd): spend=R$ ".(float)($mData['spend']??0);
                                break;
                            }
                        }
                    }

                    if (!$mData) {
                        $log[] = "  SKIP: sem dados na API Meta para o período $periodType ($mStart a $mEnd) — nem nos períodos de fallback";
                    } else {
                        // Usa EXATAMENTE o mesmo cálculo do buildMessage/relatório
                        // para garantir que o valor do alerta bate com o exibido no relatório
                        $spend    = (float)($mData['spend'] ?? 0);
                        $revenue  = (float)($mData['revenue'] ?? 0);

                        // msg_v: mesma ordem de prioridade do buildMessage
                        $msgCount = (int)($mData['msg'] ?? $mData['messages'] ?? $mData['messaging_conversations'] ?? $mData['msg_all'] ?? 0);
                        $cmsg     = $msgCount > 0 ? round($spend / $msgCount, 2) : 0;

                        // ctr/cpc: recalcula igual ao buildMessage (não confia no valor agregado)
                        $impr  = (int)($mData['impressions'] ?? 0);
                        $clks  = (int)($mData['clicks'] ?? 0);
                        $ctr   = $impr  > 0 ? round($clks / $impr * 100, 2) : (float)($mData['ctr'] ?? 0);
                        $cpc   = $clks  > 0 ? round($spend / $clks, 2)      : (float)($mData['cpc'] ?? 0);
                        $roas  = $spend > 0 && $revenue > 0 ? round($revenue / $spend, 2) : 0;

                        if ($alert['type'] === 'ctr_baixo') {
                            $metricaAtual = round($ctr, 2);
                            $condicaoAtingida = $metricaAtual > 0 && $metricaAtual < $limiar;
                            $log[] = "  CTR={$metricaAtual}% | impressoes={$impr} | cliques={$clks} | limiar={$limiar}% | " . ($condicaoAtingida ? 'ATINGIDA' : 'ok');
                        } elseif ($alert['type'] === 'cpc_alto') {
                            $metricaAtual = round($cpc, 2);
                            $condicaoAtingida = $metricaAtual > 0 && $metricaAtual > $limiar;
                            $log[] = "  CPC=R\${$metricaAtual} | cliques={$clks} | spend=R\${$spend} | limiar=R\${$limiar} | " . ($condicaoAtingida ? 'ATINGIDA' : 'ok');
                        } elseif ($alert['type'] === 'custo_conv_alto') {
                            $metricaAtual = $cmsg;
                            $condicaoAtingida = $metricaAtual > 0 && $metricaAtual > $limiar;
                            $log[] = "  Custo/msg=R\${$metricaAtual} | msgs={$msgCount} | spend=R\${$spend} | limiar=R\${$limiar} | " . ($condicaoAtingida ? 'ATINGIDA' : 'ok');
                        } elseif ($alert['type'] === 'roas_baixo') {
                            $metricaAtual = $roas;
                            $condicaoAtingida = $metricaAtual > 0 && $metricaAtual < $limiar;
                            $log[] = "  ROAS={$metricaAtual}x | revenue=R\${$revenue} | spend=R\${$spend} | limiar={$limiar}x | " . ($condicaoAtingida ? 'ATINGIDA' : 'ok');
                        }
                    }
                } catch (\Throwable $pe) {
                    $log[] = "  ERRO métricas: " . $pe->getMessage() . " | account=" . $alert['account_id'] . " | token=" . substr($alert['access_token'], 0, 15) . '...';
                }
            }
        }
    } else {
        $log[] = "  type={$alert['type']} | access_token=" . (!empty($alert['access_token']) ? 'presente' : 'VAZIO');
    }

    if (!$condicaoAtingida) continue;

    // ── 2. Decide se deve disparar ────────────────────────────────────────────
    $deveDisparar = false;
    $horarioBateu = null;

    // ── ERRO_CONTA: modo agendado (padrão) ou inteligente (imediato + reenvio 6h)
    if ($alert['type'] === 'erro_conta') {
        $dias = array_map('trim', explode(',', $alert['dias_semana'] ?? '0,1,2,3,4,5,6'));
        if (!in_array($diaAtual, $dias)) {
            $log[] = "  SKIP erro_conta: dia $diaAtual não programado (" . implode(',', $dias) . ")";
            continue;
        }

        $modoDisparo  = $alert['modo_disparo'] ?? 'agendado';
        $errosAtual   = $erroContaInfo ?? [];
        sort($errosAtual);
        $hashAtual    = md5(implode('|', $errosAtual));
        $hashAnterior = $alert['ultimo_erros_hash'] ?? '';

        if ($modoDisparo === 'inteligente') {
            // ── MODO INTELIGENTE ─────────────────────────────────────────────
            // Janela de envio: 07:00 às 22:00
            // - Detectou erro dentro da janela → envia imediatamente
            // - Reenvio 6h depois, sempre respeitando a janela
            // - Se reenvio cair fora da janela → segura até 07:00
            // - Corrigiu → para automaticamente

            $horaInt        = (int)$now->format('H');
            $minInt         = (int)$now->format('i');
            $dentroJanela   = ($horaInt >= 7 && $horaInt < 22) || ($horaInt === 22 && $minInt === 0);
            $proximoReenvio = $alert['proximo_reenvio_erro'] ?? null;

            if (!$condicaoAtingida) {
                // Erro resolvido — limpa estado
                if ($hashAnterior !== '') {
                    $db->query("UPDATE alerts SET ultimo_erros_hash=NULL, proximo_reenvio_erro=NULL WHERE id=?", [$alert['id']]);
                    $log[] = "  SKIP erro_conta inteligente: erro resolvido — estado limpo";
                } else {
                    $log[] = "  SKIP erro_conta inteligente: sem erros";
                }
                continue;
            }

            // Erro persiste — verifica se deve disparar agora
            if ($hashAtual !== $hashAnterior) {
                // Erros novos/diferentes
                if ($dentroJanela) {
                    // Dentro da janela → dispara imediatamente
                    $horarioBateu = $horaAtual;
                    $deveDisparar = true;
                    $log[] = "  OK erro_conta inteligente: erros novos detectados — disparo imediato";
                } else {
                    // Fora da janela → registra hash mas agenda para 07:00
                    $amanha07 = (clone $now)->modify('tomorrow')->setTime(7, 0, 0)->format('Y-m-d H:i:s');
                    $db->query("UPDATE alerts SET ultimo_erros_hash=?, proximo_reenvio_erro=? WHERE id=?",
                        [$hashAtual, $amanha07, $alert['id']]);
                    $log[] = "  SKIP erro_conta inteligente: erro novo fora da janela — agendado para $amanha07";
                    continue;
                }
            } elseif ($proximoReenvio && $now->getTimestamp() >= strtotime($proximoReenvio)) {
                // Mesmo erro, chegou hora do reenvio
                if ($dentroJanela) {
                    $horarioBateu = $horaAtual;
                    $deveDisparar = true;
                    $log[] = "  OK erro_conta inteligente: reenvio 6h — erro persiste desde último aviso";
                } else {
                    // Reenvio fora da janela → empurra para 07:00
                    $proximo07 = (clone $now)->modify('today')->setTime(7, 0, 0)->format('Y-m-d H:i:s');
                    if ($now->getTimestamp() >= strtotime($proximo07)) {
                        $proximo07 = (clone $now)->modify('tomorrow')->setTime(7, 0, 0)->format('Y-m-d H:i:s');
                    }
                    $db->query("UPDATE alerts SET proximo_reenvio_erro=? WHERE id=?", [$proximo07, $alert['id']]);
                    $log[] = "  SKIP erro_conta inteligente: reenvio fora da janela — reagendado para $proximo07";
                    continue;
                }
            } else {
                $log[] = "  SKIP erro_conta inteligente: mesmo erro, aguardando reenvio em " . ($proximoReenvio ?? 'não agendado');
                continue;
            }
        } else {
            // ── MODO AGENDADO (comportamento original) ───────────────────────
            // Só dispara no horário programado, igual aos outros tipos de alerta
            $horarios = array_map('trim', explode(',', $alert['horarios'] ?? '12:00'));
            $horarioBateu = null;
            foreach ($horarios as $h) {
                $tProg = DateTime::createFromFormat('H:i', $h, new DateTimeZone(APP_TIMEZONE));
                if (!$tProg) continue;
                $tProg->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
                $diffSeg = $now->getTimestamp() - $tProg->getTimestamp();
                if ($diffSeg >= 0 && $diffSeg <= 900) { $horarioBateu = $h; break; }
            }
            if (!$horarioBateu) {
                $log[] = "  SKIP erro_conta agendado: fora da janela dos horários (" . implode(',', $horarios) . ")";
                continue;
            }
            if ($hashAtual === $hashAnterior && $alert['ultimo_envio']) {
                $ultimo = new DateTime($alert['ultimo_envio'], new DateTimeZone(APP_TIMEZONE));
                if ($ultimo->format('Y-m-d') === $now->format('Y-m-d') && $ultimo->format('H:i') === $horarioBateu) {
                    $log[] = "  SKIP erro_conta agendado: já enviado hoje às $horarioBateu";
                    continue;
                }
            }
            $deveDisparar = true;
            $log[] = "  OK erro_conta agendado: horário $horarioBateu atingido";
        }
    } else {
        // ── OUTROS TIPOS: regra de janela de horário normal ──────────────────
        // Só dispara se hora atual estiver dentro da janela de 15 min de um horário programado
        // Nunca envia 2x no mesmo horário programado no mesmo dia

        // Verifica dias da semana
        $dias = array_map('trim', explode(',', $alert['dias_semana'] ?? '1,2,3,4,5'));
        if (!in_array($diaAtual, $dias)) {
            $log[] = "  SKIP: dia $diaAtual não programado (" . implode(',', $dias) . ")";
            continue;
        }

        // Verifica se hora atual bate com algum horário programado (janela de 15 min)
        $horarios = array_map('trim', explode(',', $alert['horarios'] ?? '12:00'));
        foreach ($horarios as $h) {
            $tProg = DateTime::createFromFormat('H:i', $h, new DateTimeZone(APP_TIMEZONE));
            if (!$tProg) continue;
            $tProg->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
            $diffSeg = $now->getTimestamp() - $tProg->getTimestamp();
            if ($diffSeg >= 0 && $diffSeg <= 900) {
                $horarioBateu = $h;
                break;
            }
        }

        if (!$horarioBateu) {
            $log[] = "  SKIP: hora $horaAtual fora da janela dos horários (" . implode(',', $horarios) . ")";
            continue;
        }

        // Verifica se já enviou hoje NESTE horário programado
        if ($alert['ultimo_envio']) {
            $ultimo   = new DateTime($alert['ultimo_envio'], new DateTimeZone(APP_TIMEZONE));
            $hojeData = $now->format('Y-m-d');
            if ($ultimo->format('Y-m-d') === $hojeData && $ultimo->format('H:i') === $horarioBateu) {
                $log[] = "  SKIP: já enviado hoje ($hojeData) no horário $horarioBateu";
                continue;
            }
        }

        $deveDisparar = true;
        $log[] = "  OK: disparando — horário programado $horarioBateu | atual $horaAtual | disparo_imediato=" . (int)($alert['disparo_imediato'] ?? 0);
    }

    if (!$deveDisparar) continue;

    // ── 3. Monta mensagem ─────────────────────────────────────────────────────
    $primeiroNome = explode(' ', trim($alert['client_name'] ?? ''))[0] ?? '';
    $empresa      = $alert['client_company'] ?? '';
    // ── Monta mensagem igual ao send-reports.php ─────────────────────────────
    // Usa fetchMetricsMeta + buildMessage: mesmas variáveis dos templates de relatório
    $mStart = $mStart ?? date('Y-m-d', strtotime('-7 days'));
    $mEnd   = $mEnd   ?? date('Y-m-d');

    // Se mData ainda não foi buscado (alertas de saldo/erro), busca agora para {cmsg} etc
    if (empty($mData) && !empty($alert['access_token']) && !empty($alert['account_id'])) {
        try {
            $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, ['__ALL_STATUS__']);
            if (!$mData) $mData = ReportController::fetchMetricsMeta($alert['account_id'], $alert['access_token'], $mStart, $mEnd, []);
        } catch (\Throwable $eM) {}
    }

    // Monta texto de erros para variavel {erros_conta}
    $errosContaTexto = '';
    if (!empty($erroContaInfo)) {
        $linhas = [];
        foreach ($erroContaInfo as $e) $linhas[] = '• ' . $e;
        $errosContaTexto = implode("\n", $linhas);
    }

    $mensagem = ReportController::buildMessage($alert['message'] ?? '', [
        'metrics'        => array_merge($mData ?? [], ['saldo' => $saldoAtual ?? 0, 'saldo_minimo' => (float)$alert['saldo_minimo']]),
        'client_name'    => trim($alert['client_name'] ?? ''),
        'client_company' => trim($alert['client_company'] ?? ''),
        'account_name'   => $alert['account_name'] ?? '',
        'periodo'        => date('d/m/Y', strtotime($mStart)) . ' a ' . date('d/m/Y', strtotime($mEnd)),
        'campaign_name'  => '',
        'observacoes'    => '',
        'erros_conta'    => $errosContaTexto,
    ]);

    // ── 4. Envia ──────────────────────────────────────────────────────────────
    $isGroup  = ($alert['recipient_type'] ?? 'phone') === 'group';
    $phone    = $isGroup
        ? ($alert['recipient_phone'] ?? '')
        : preg_replace('/\D/', '', $alert['recipient_phone'] ?? '');
    $instance = $alert['instance_name'] ?? '';

    // Fallback: se instância foi deletada, pega a primeira ativa do usuário
    if (!$instance) {
        $fallbackInst = $db->query(
            "SELECT instance_name FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id ASC LIMIT 1",
            [$alert['user_id']]
        )->fetch();
        if ($fallbackInst) {
            $instance = $fallbackInst['instance_name'];
            $log[] = "  AVISO: instância original deletada, usando fallback: $instance";
        }
    }

    if (!$phone || !$instance) {
        $log[] = "  ERRO: phone='$phone' ou instance='$instance' vazio";
        $db->query(
            "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
            [$alert['user_id'], "Falha no alerta: {$alert['name']}", 'Telefone ou instância não configurados']
        );
        $db->query(
            "INSERT INTO alert_logs (alert_id,user_id,alert_name,status,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'erro',?,'automatico',?)",
            [$alert['id'], $alert['user_id'], $alert['name'], $phone, 'Telefone ou instância não configurados']
        );
        $erros++;
        continue;
    }

    $log[] = "  Enviando para $phone via $instance...";

    $result = $isGroup
        ? ReportController::sendWhatsAppGroup($instance, $phone, $mensagem)
        : ReportController::sendWhatsAppStatic($instance, $phone, $mensagem);

    $log[] = "  Resultado: " . json_encode($result);

    if ($result['ok']) {
        // Sempre salva o horário PROGRAMADO (ex: 2026-04-12 14:00:00)
        // Isso garante que: mudar o horário de 14:00 para 16:00 permite enviar às 16:00
        // E que o mesmo horário 14:00 não seja enviado 2x no mesmo dia
        $horarioSalvo = $horarioBateu ?? $horaAtual;
        $nowBr = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d') . ' ' . $horarioSalvo . ':00';
        // Salva hash apenas para erro_conta; outros tipos não usam esse campo
        if (($alert['type'] ?? '') === 'erro_conta') {
            $modoDisparoSalvo = $alert['modo_disparo'] ?? 'agendado';
            if ($modoDisparoSalvo === 'inteligente') {
                // Agenda reenvio para 6h depois, respeitando janela 07:00–22:00
                $proximoReenvioObj = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->modify('+6 hours');
                $proximoH = (int)$proximoReenvioObj->format('H');
                if ($proximoH < 7) {
                    // Cai de madrugada → empurra para 07:00 do mesmo dia
                    $proximoReenvioObj->setTime(7, 0, 0);
                } elseif ($proximoH >= 22) {
                    // Cai após 22:00 → empurra para 07:00 do dia seguinte
                    $proximoReenvioObj->modify('+1 day')->setTime(7, 0, 0);
                }
                $proximoReenvioTs = $proximoReenvioObj->format('Y-m-d H:i:s');
                $db->query(
                    "UPDATE alerts SET ultimo_envio=?, ultimo_erros_hash=?, proximo_reenvio_erro=? WHERE id=?",
                    [$nowBr, $hashAtual ?? null, $proximoReenvioTs, $alert['id']]
                );
                $log[] = "  Reenvio inteligente agendado para: $proximoReenvioTs";
            } else {
                $db->query(
                    "UPDATE alerts SET ultimo_envio=?, ultimo_erros_hash=? WHERE id=?",
                    [$nowBr, $hashAtual ?? null, $alert['id']]
                );
            }
        } else {
            $db->query("UPDATE alerts SET ultimo_envio=? WHERE id=?", [$nowBr, $alert['id']]);
        }
        // saldo pode ser null (tipo erro_conta) — salvar NULL explicitamente
        // Para erro_conta: salva resumo dos erros detectados em erro_msg para exibir na listagem
        $erroMsgLog = null;
        if (($alert['type'] ?? '') === 'erro_conta' && !empty($erroContaInfo)) {
            $erroMsgLog = implode(' | ', array_slice($erroContaInfo, 0, 3));
        }
        $db->query(
            "INSERT INTO alert_logs (alert_id,user_id,alert_name,status,saldo,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'enviado',?,?,'automatico',?)",
            [$alert['id'], $alert['user_id'], $alert['name'], $saldoAtual, $phone, $erroMsgLog]
        );
        if (!empty($alert['inativar_apos'])) {
            $db->query("UPDATE alerts SET ativo=0 WHERE id=?", [$alert['id']]);
            $log[] = "  INATIVADO após envio";
        }
        // ── Notificação no painel (alert + saldo) ─────────────────────────────
        try {
            $tipoLabelMap = [
                'saldo_minimo'    => '💰 Saldo baixo notificado',
                'ctr_baixo'       => '📉 CTR baixo notificado',
                'cpc_alto'        => '💸 CPC alto notificado',
                'custo_conv_alto' => '💰 Custo/Conv alto notificado',
                'roas_baixo'      => '📊 ROAS baixo notificado',
                'erro_conta'      => '⚠️ Erro de conta notificado',
            ];
            $tipoLabel = $tipoLabelMap[$alert['type']] ?? '⚡ Alerta disparado';
            $metricaStr = '';
            if ($saldoAtual !== null) {
                $metricaStr = ' | Saldo: R$ ' . number_format($saldoAtual, 2, ',', '.');
            } elseif ($metricaAtual !== null) {
                $metricaStr = match($alert['type']) {
                    'ctr_baixo'       => ' | CTR: ' . number_format($metricaAtual, 2, ',', '.') . '%',
                    'cpc_alto'        => ' | CPC: R$ ' . number_format($metricaAtual, 2, ',', '.'),
                    'custo_conv_alto' => ' | Custo/Conv: R$ ' . number_format($metricaAtual, 2, ',', '.'),
                    'roas_baixo'      => ' | ROAS: ' . number_format($metricaAtual, 2, ',', '.') . 'x',
                    default           => '',
                };
            }
            $bodyNotif = $tipoLabel . " para $phone" . $metricaStr;
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'alert', ?, ?)",
                [$alert['user_id'], "🚨 Alerta disparado: {$alert['name']}", $bodyNotif]
            );
        } catch (\Throwable $_e) {}
        $log[] = "  ENVIADO ✓";
        $enviados++;
        usleep(1500000);
    } else {
        $db->query(
            "INSERT INTO alert_logs (alert_id,user_id,alert_name,status,destinatario,tipo_envio,erro_msg) VALUES (?,?,?,'erro',?,'automatico',?)",
            [$alert['id'], $alert['user_id'], $alert['name'], $phone, $result['error'] ?? 'falha desconhecida']
        );
        // ── Notificação de erro no painel ─────────────────────────────────────
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                [$alert['user_id'], "❌ Falha no alerta: {$alert['name']}", mb_substr($result['error'] ?? 'falha', 0, 200)]
            );
        } catch (\Throwable $_e) {}
        $log[] = "  ERRO ENVIO: " . ($result['error'] ?? 'falha desconhecida');
        $erros++;
    }
}

$log[] = "FIM — Enviados: $enviados | Erros: $erros";

echo json_encode([
    'success'  => true,
    'hora'     => $horaAtual,
    'enviados' => $enviados,
    'erros'    => $erros,
    'log'      => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ── Funções auxiliares ────────────────────────────────────────────────────────

/**
 * Busca a data de criação da campanha mais antiga da conta via API Meta.
 * Mais confiável que o banco local para o período MAX dos alertas.
 */
function fetchActiveCampaignsAndStartDate(string $accountId, string $token): array {
    // Retorna [startDate, campIds[]] das campanhas ATIVAS
    // Exatamente como o dashboard faz: busca campanhas ACTIVE, usa start_time da mais antiga
    if (!$accountId || !$token) return [date('Y-m-d', strtotime('-30 days')), []];

    $url = "https://graph.facebook.com/" . META_API_VERSION
         . "/act_{$accountId}/campaigns"
         . "?fields=id,start_time,created_time"
         . "&effective_status=" . urlencode('["ACTIVE"]')
         . "&limit=500"
         . "&access_token=" . urlencode($token);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
    $res  = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $campIds  = [];
    $earliest = null;

    if ($code === 200 && !empty($res['data'])) {
        foreach ($res['data'] as $camp) {
            $campIds[] = $camp['id'];
            $dateStr   = $camp['start_time'] ?? $camp['created_time'] ?? null;
            if (!$dateStr) continue;
            $ts = strtotime($dateStr);
            if ($ts && ($earliest === null || $ts < $earliest)) {
                $earliest = $ts;
            }
        }
    }

    // Nenhuma campanha ativa: tenta ACTIVE+PAUSED
    if (empty($campIds)) {
        $url2 = "https://graph.facebook.com/" . META_API_VERSION
              . "/act_{$accountId}/campaigns"
              . "?fields=id,start_time,created_time"
              . "&effective_status=" . urlencode('["ACTIVE","PAUSED"]')
              . "&limit=100"
              . "&access_token=" . urlencode($token);
        $ch2 = curl_init($url2);
        curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
        $res2 = json_decode(curl_exec($ch2), true);
        curl_close($ch2);
        foreach ($res2['data'] ?? [] as $camp) {
            $campIds[] = $camp['id'];
            $dateStr   = $camp['start_time'] ?? $camp['created_time'] ?? null;
            if (!$dateStr) continue;
            $ts = strtotime($dateStr);
            if ($ts && ($earliest === null || $ts < $earliest)) $earliest = $ts;
        }
    }

    $startDate = $earliest ? date('Y-m-d', $earliest) : date('Y-m-d', strtotime('-30 days'));
    return [$startDate, $campIds];
}


function buscarSaldoMeta(string $accountId, string $token): ?float {
    if (!$accountId || !$token) return null;
    $url = "https://graph.facebook.com/" . META_API_VERSION
         . "/act_{$accountId}?fields=balance,spend_cap,amount_spent,currency&access_token=" . urlencode($token);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $res  = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$res) return null;
    if (!empty($res['spend_cap']) && (int)$res['spend_cap'] > 0 && isset($res['amount_spent'])) {
        return max(0, ((float)$res['spend_cap'] - (float)$res['amount_spent']) / 100);
    }
    if (isset($res['balance'])) return (float)$res['balance'] / 100;
    return null;
}

