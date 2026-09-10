<?php
/**
 * GestorADS — Monitor de Saúde Completo v3
 * ============================================================
 * Estado salvo no BANCO DE DADOS (não em /tmp que reseta)
 * Detecta QUALQUER erro/falha em todo o sistema:
 *
 *  1. Evolution API online/offline
 *  2. Instâncias WhatsApp desconectadas
 *  3. Erros em alert_logs (últimos 30min)
 *  4. Erros em report_logs (últimos 30min)
 *  5. Erros em integration_logs (últimos 30min)
 *  6. Contas Meta com token erro
 *  7. QUALQUER linha nova no log PHP
 *  8. Crons parados há mais de 4h
 *
 * Configure no cron-job.org a cada 5 minutos:
 * GET https://seudominio.com.br/cron/monitor.php?key=CRON_SECRET
 * ============================================================
 */

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'fatal_error',
        'error'  => $e->getMessage(),
        'file'   => basename($e->getFile()),
        'line'   => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
});

define('CLI_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/EvolutionApi.php';
require_once __DIR__ . '/../core/Mailer.php';

// ── Autenticação ──────────────────────────────────────────────
$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$db       = Database::getInstance();
$problems = [];
$log      = [];

// ── Helpers para salvar/ler estado no banco ───────────────────
function getState(string $key): string {
    global $db;
    try {
        $r = $db->query(
            "SELECT setting_value FROM user_ai_settings WHERE user_id=1 AND setting_key=? LIMIT 1",
            ['monitor_' . $key]
        )->fetch();
        return $r['setting_value'] ?? '';
    } catch (Throwable $e) { return ''; }
}

function saveState(string $key, string $value): void {
    global $db;
    try {
        $db->query(
            "INSERT INTO user_ai_settings (user_id, setting_key, setting_value)
             VALUES (1, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()",
            ['monitor_' . $key, $value, $value]
        );
    } catch (Throwable $e) {}
}

// ── Dados do admin ────────────────────────────────────────────
$admin = $db->query(
    "SELECT u.name, u.email, u.phone,
            wi.instance_name, wi.status AS wa_status
     FROM users u
     LEFT JOIN whatsapp_instances wi ON wi.user_id = u.id AND wi.is_default = 1
     WHERE u.role = 'admin'
     ORDER BY u.id ASC LIMIT 1"
)->fetch();

$adminPhone = preg_replace('/\D/', '', $admin['phone'] ?? '');
$adminEmail = $admin['email'] ?? '';
$adminName  = $admin['name'] ?? 'Admin';
$waInstance = $admin['instance_name'] ?? '';

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 1 — Evolution API online?
// ══════════════════════════════════════════════════════════════
$evolutionOk = false;
try {
    $ch = curl_init(rtrim(EVOLUTION_API_URL, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['apikey: ' . EVOLUTION_API_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $evolutionOk = ($code >= 200 && $code < 500 && !$curlErr);
    $log['1_evolution_api'] = $evolutionOk ? "online ✓ (HTTP $code)" : "OFFLINE ✗ (HTTP $code) $curlErr";
} catch (Throwable $e) {
    $log['1_evolution_api'] = 'ERRO: ' . $e->getMessage();
}

if (!$evolutionOk) {
    $problems[] = [
        'tipo'     => '🔴 Evolution API OFFLINE',
        'detalhe'  => "A API do WhatsApp não está respondendo.\nURL: " . EVOLUTION_API_URL,
        'urgencia' => 'CRÍTICO',
    ];
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 2 — Instâncias WhatsApp conectadas?
// ══════════════════════════════════════════════════════════════
try {
    $instancias = $db->query(
        "SELECT instance_name, status FROM whatsapp_instances WHERE user_id = 1"
    )->fetchAll();

    if (empty($instancias)) {
        // Nenhuma instância cadastrada — alerta crítico
        $problems[] = [
            'tipo'     => '📵 Nenhuma Instância WhatsApp',
            'detalhe'  => "Não há nenhuma instância WhatsApp cadastrada no sistema.
Acesse: " . APP_URL . "/whatsapp para configurar.",
            'urgencia' => 'CRÍTICO',
        ];
        $log['2_whatsapp'] = 'SEM INSTÂNCIAS CADASTRADAS ✗';
    } else {
        foreach ($instancias as $inst) {
            if ($inst['status'] !== 'connected') {
                $problems[] = [
                    'tipo'     => '📵 WhatsApp Desconectado',
                    'detalhe'  => "Instância {$inst['instance_name']} está com status: {$inst['status']}.
Reconecte em: " . APP_URL . "/whatsapp",
                    'urgencia' => 'CRÍTICO',
                ];
                $log['2_whatsapp_' . $inst['instance_name']] = 'DESCONECTADO ✗';
            } else {
                $log['2_whatsapp_' . $inst['instance_name']] = 'conectado ✓';
            }
        }
    }
} catch (Throwable $e) {
    $log['2_whatsapp'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 3 — Erros em alert_logs (últimos 30min)
// ══════════════════════════════════════════════════════════════
try {
    $rows = $db->query(
        "SELECT al.erro_msg, al.created_at, a.name AS alert_name
         FROM alert_logs al
         LEFT JOIN alerts a ON a.id = al.alert_id
         WHERE al.status = 'erro'
           AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY al.created_at DESC LIMIT 10"
    )->fetchAll();

    if (!empty($rows)) {
        $det = '';
        foreach ($rows as $r) {
            $det .= "• [{$r['created_at']}] {$r['alert_name']}: {$r['erro_msg']}\n";
        }
        $problems[] = ['tipo' => '⚠️ Erros em Alertas', 'detalhe' => count($rows) . " erro(s):\n$det", 'urgencia' => 'ALTO'];
        $log['3_alert_logs'] = count($rows) . ' erros ✗';
    } else {
        $log['3_alert_logs'] = 'ok ✓';
    }
} catch (Throwable $e) {
    $log['3_alert_logs'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 4 — Erros em report_logs (últimos 30min)
// ══════════════════════════════════════════════════════════════
try {
    $rows = $db->query(
        "SELECT rl.erro_msg, rl.created_at, r.title
         FROM report_logs rl
         LEFT JOIN reports r ON r.id = rl.report_id
         WHERE rl.status = 'erro'
           AND rl.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY rl.created_at DESC LIMIT 10"
    )->fetchAll();

    if (!empty($rows)) {
        $det = '';
        foreach ($rows as $r) {
            $det .= "• [{$r['created_at']}] {$r['title']}: {$r['erro_msg']}\n";
        }
        $problems[] = ['tipo' => '📋 Erros em Relatórios', 'detalhe' => count($rows) . " erro(s):\n$det", 'urgencia' => 'ALTO'];
        $log['4_report_logs'] = count($rows) . ' erros ✗';
    } else {
        $log['4_report_logs'] = 'ok ✓';
    }
} catch (Throwable $e) {
    $log['4_report_logs'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 5 — Erros em integration_logs (últimos 30min)
// ══════════════════════════════════════════════════════════════
try {
    $rows = $db->query(
        "SELECT il.status, il.created_at, i.name AS integ_name
         FROM integration_logs il
         LEFT JOIN integrations i ON i.id = il.integration_id
         WHERE il.status != 'sent'
           AND il.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY il.created_at DESC LIMIT 10"
    )->fetchAll();

    if (!empty($rows)) {
        $det = '';
        foreach ($rows as $r) {
            $det .= "• [{$r['created_at']}] {$r['integ_name']}: {$r['status']}\n";
        }
        $problems[] = ['tipo' => '🔗 Erros em Integrações', 'detalhe' => count($rows) . " erro(s):\n$det", 'urgencia' => 'MÉDIO'];
        $log['5_integration_logs'] = count($rows) . ' erros ✗';
    } else {
        $log['5_integration_logs'] = 'ok ✓';
    }
} catch (Throwable $e) {
    $log['5_integration_logs'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 6 — Contas Meta com erro de token
// ══════════════════════════════════════════════════════════════
try {
    $rows = $db->query(
        "SELECT account_name FROM ad_accounts WHERE status = 'error'"
    )->fetchAll();

    if (!empty($rows)) {
        $nomes = implode(', ', array_column($rows, 'account_name'));
        $problems[] = ['tipo' => '📘 Token Meta Inválido', 'detalhe' => count($rows) . " conta(s): $nomes", 'urgencia' => 'ALTO'];
        $log['6_meta_tokens'] = count($rows) . ' contas com erro ✗';
    } else {
        $log['6_meta_tokens'] = 'ok ✓';
    }
} catch (Throwable $e) {
    $log['6_meta_tokens'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 7 — Log PHP: QUALQUER linha nova
// Estado salvo no BANCO para não resetar
// ══════════════════════════════════════════════════════════════
try {
    $errorLogPath = _env('ERROR_LOG_PATH', ini_get('error_log') ?: '');

    if ($errorLogPath && file_exists($errorLogPath)) {
        $logSize = filesize($errorLogPath);
        $lastPos = (int) getState('php_log_pos');

        // Primeira execução: grava posição atual e não alarma histórico
        if ($lastPos === 0) {
            saveState('php_log_pos', (string) $logSize);
            $log['7_php_error_log'] = 'primeira execução — posição inicial gravada ✓';
        } elseif ($logSize > $lastPos) {
            // Lê apenas o trecho novo desde a última execução
            $fp           = fopen($errorLogPath, 'r');
            fseek($fp, $lastPos);
            $novoConteudo = fread($fp, min($logSize - $lastPos, 10000));
            fclose($fp);

            // Filtra só linhas com erros reais
            $linhas = explode("\n", $novoConteudo);
            $erros  = array_filter($linhas, function ($l) {
                return preg_match('/(PHP Warning|PHP Fatal|PHP Error|PHP Notice|PHP Parse|PHP Deprecated|Uncaught|Stack trace)/i', $l);
            });
            $erros = array_values(array_filter($erros));

            if (!empty($erros)) {
                $det = implode("\n", array_slice($erros, 0, 15));
                $problems[] = [
                    'tipo'     => '🚨 Erros PHP no Log',
                    'detalhe'  => count($erros) . " linha(s) nova(s):\n" . $det,
                    'urgencia' => 'ALTO',
                ];
                $log['7_php_error_log'] = count($erros) . ' linhas novas ✗';
            } else {
                $log['7_php_error_log'] = 'linhas novas sem erros relevantes ✓';
            }

            // Atualiza posição no banco
            saveState('php_log_pos', (string) $logSize);

        } elseif ($logSize < $lastPos) {
            // Log foi rotacionado/zerado — reseta posição
            saveState('php_log_pos', (string) $logSize);
            $log['7_php_error_log'] = 'log rotacionado — posição resetada ✓';
        } else {
            $log['7_php_error_log'] = 'sem novidades ✓';
        }
    } else {
        $log['7_php_error_log'] = 'arquivo de log não encontrado';
    }
} catch (Throwable $e) {
    $log['7_php_error_log'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÃO 8 — Cron run-alerts.php executando? (apenas 7h–23h)
// Checa o heartbeat gravado pelo próprio cron a cada execução,
// independente de ter alertas para enviar ou não.
// ══════════════════════════════════════════════════════════════
try {
    $hora = (int) date('H');
    if ($hora >= 7 && $hora <= 23) {
        $ping = $db->query(
            "SELECT setting_value FROM user_ai_settings WHERE user_id=1 AND setting_key='cron_run_alerts_last_ping' LIMIT 1"
        )->fetch()['setting_value'] ?? null;

        if ($ping) {
            $diffH = (time() - strtotime($ping)) / 3600;
            if ($diffH > 4) {
                $problems[] = [
                    'tipo'     => '⏰ Cron de Alertas Parado',
                    'detalhe'  => "Última execução foi há " . round($diffH) . "h.\nO cron run-alerts.php não está sendo chamado.\nVerifique no cron-job.org.",
                    'urgencia' => 'MÉDIO',
                ];
                $log['8_cron_alertas'] = round($diffH) . 'h sem executar ✗';
            } else {
                $log['8_cron_alertas'] = 'ativo ✓ (última execução: ' . $ping . ')';
            }
        } else {
            // Heartbeat nunca gravado — cron nunca rodou ou é instalação nova
            $log['8_cron_alertas'] = 'heartbeat ainda não registrado (cron nunca executou ou acabou de ser instalado)';
        }
    } else {
        $log['8_cron_alertas'] = 'fora do horário de verificação (7h–23h)';
    }
} catch (Throwable $e) {
    $log['8_cron_alertas'] = 'ERRO: ' . $e->getMessage();
}

// ══════════════════════════════════════════════════════════════
// ENVIO DE ALERTAS
// ══════════════════════════════════════════════════════════════
$enviados = [];

if (!empty($problems)) {

    // WhatsApp
    $msgWa  = "🚨 *MONITOR — GestorADS*\n";
    $msgWa .= "🕐 " . date('d/m/Y H:i') . "\n";
    $msgWa .= "━━━━━━━━━━━━━━━━━━\n\n";
    foreach ($problems as $i => $p) {
        $msgWa .= "*" . ($i + 1) . ". {$p['tipo']}*\n";
        $msgWa .= "{$p['detalhe']}\n";
        $msgWa .= "🔺 _{$p['urgencia']}_\n\n";
    }
    $msgWa .= "━━━━━━━━━━━━━━━━━━\n";
    $msgWa .= "_GestorADS Monitor v3_";

    // E-mail HTML
    $htmlEmail  = "<div style='font-family:Arial,sans-serif;max-width:600px'>";
    $htmlEmail .= "<h2 style='color:#e74c3c;border-bottom:2px solid #e74c3c;padding-bottom:8px'>🚨 Monitor GestorADS — " . count($problems) . " problema(s)</h2>";
    $htmlEmail .= "<p style='color:#666'><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";
    foreach ($problems as $p) {
        $cor = $p['urgencia'] === 'CRÍTICO' ? '#c0392b' : ($p['urgencia'] === 'ALTO' ? '#e67e22' : '#f39c12');
        $htmlEmail .= "<div style='border-left:4px solid $cor;padding:12px;margin:12px 0;background:#fafafa'>";
        $htmlEmail .= "<h3 style='margin:0 0 8px;color:$cor'>{$p['tipo']}</h3>";
        $htmlEmail .= "<pre style='background:#f0f0f0;padding:8px;border-radius:4px;font-size:12px;white-space:pre-wrap'>" . htmlspecialchars($p['detalhe']) . "</pre>";
        $htmlEmail .= "<p style='margin:4px 0 0;font-size:12px;color:#888'>Urgência: <strong>{$p['urgencia']}</strong></p>";
        $htmlEmail .= "</div>";
    }
    $htmlEmail .= "<hr><p style='color:#aaa;font-size:11px'>GestorADS Monitor v3 — <a href='" . APP_URL . "'>" . APP_URL . "</a></p>";
    $htmlEmail .= "</div>";

    // Envia WhatsApp — busca instância conectada na hora (não depende do admin JOIN)
    try {
        $instConn = $db->query(
            "SELECT instance_name FROM whatsapp_instances WHERE user_id=1 AND status='connected' ORDER BY id ASC LIMIT 1"
        )->fetch();
        $instanciaConectada = $instConn['instance_name'] ?? null;
    } catch (Throwable $e) {
        $instanciaConectada = null;
    }

    if ($evolutionOk && $instanciaConectada && $adminPhone) {
        try {
            $resWa      = EvolutionApi::sendText($instanciaConectada, $adminPhone, $msgWa);
            $enviados[] = ($resWa['ok'] ?? false) ? 'WhatsApp ✓' : 'WhatsApp ✗: ' . ($resWa['error'] ?? '?');
        } catch (Throwable $e) {
            $enviados[] = 'WhatsApp ERRO: ' . $e->getMessage();
        }
    } else {
        $enviados[] = 'WhatsApp indisponível (sem instância conectada) — usando apenas e-mail';
    }

    // Envia E-mail sempre
    if ($adminEmail) {
        try {
            $ok         = Mailer::send($adminEmail, $adminName, '🚨 [GestorADS] ' . count($problems) . ' problema(s) — ' . date('d/m H:i'), $htmlEmail);
            $enviados[] = $ok ? 'E-mail ✓' : 'E-mail ✗ (falha SMTP)';
        } catch (Throwable $e) {
            $enviados[] = 'E-mail ERRO: ' . $e->getMessage();
        }
    }

} else {
    $log['resultado'] = '✓ Tudo ok — nenhum problema encontrado';
}

// ── Resposta ───────────────────────────────────────────────────
echo json_encode([
    'status'    => empty($problems) ? 'ok' : 'problemas_encontrados',
    'timestamp' => date('Y-m-d H:i:s'),
    'problemas' => count($problems),
    'detalhes'  => $problems,
    'enviados'  => $enviados,
    'log'       => $log,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
