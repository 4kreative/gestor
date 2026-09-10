<?php
/**
 * GestorPro — Cron de Envio Automático de Relatórios
 * URL: https://seudominio.com.br/cron/send-reports.php?key={CRON_SECRET}
 * Configure no cron-job.org para rodar a CADA 1 MINUTO.
 *
 * REGRA DE ENVIO (preservada):
 *  - Envia 1x por dia no horário programado
 *  - Se cron falhou e voltou dentro de 15min da hora programada → envia normalmente
 *  - Se mudou o horário no mesmo dia → envia no novo horário (1x)
 *  - Se mudou o dia → aguarda o próximo dia configurado
 *  - NUNCA envia 2x no mesmo dia no mesmo horário
 *
 * CORREÇÃO APLICADA:
 *  - Bug crítico: rollback() após commit() removido
 *    (a verificação da janela 15min agora ocorre ANTES do commit)
 *  - Lock file deletado no shutdown (igual ao run-alerts.php)
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
require_once __DIR__.'/../core/TokenCrypto.php';
require_once __DIR__.'/../core/EvolutionApi.php';
require_once __DIR__.'/../controllers/ReportController.php';

// ── LOCK DE PROCESSO ──────────────────────────────────────────────────────────
$lockFile = sys_get_temp_dir() . '/gestorpro_send_reports.lock';
// Remove lock travado há mais de 90s (execução anterior crashou)
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
        'log'      => ['SKIP: outra execução já está rodando (lock ativo)'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
touch($lockFile);
// CORREÇÃO: deletar lockFile no shutdown (igual ao run-alerts.php)
register_shutdown_function(function() use ($fp, $lockFile) {
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);
});
// ─────────────────────────────────────────────────────────────────────────────

$now       = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$horaAtual = $now->format('H:i');
$diaAtual  = (int)$now->format('w'); // 0=Dom
$log       = [];
$enviados  = 0;
$erros     = 0;

$log[] = "Relatórios — {$now->format('Y-m-d H:i:s')} | Hora: $horaAtual | Dia: $diaAtual";

$db = Database::getInstance();

// Registra timestamp do último cron bem-sucedido
try {
    $cronFile = sys_get_temp_dir() . '/gestorpro_cron_last_run.txt';
    file_put_contents($cronFile, time());
    $db->query("UPDATE system_settings SET updated_at=NOW() WHERE id=1");
} catch (\Throwable $_e) {}

// ── BUSCA IDs elegíveis ───────────────────────────────────────────────────────
$ids = $db->query(
    "SELECT r.id
     FROM reports r
     WHERE r.status IN ('active','scheduled')
       AND r.next_send_at IS NOT NULL
       AND r.next_send_at <= NOW()
       AND (
         (r.recipient_phone IS NOT NULL AND r.recipient_phone != '')
         OR (r.recv_type = 'group' AND r.group_id IS NOT NULL AND r.group_id != '')
       )"
)->fetchAll(PDO::FETCH_COLUMN);

$log[] = "Relatórios elegíveis: " . count($ids);

// Limite de envios por execução — proteção anti-ban em caso de recuperação após falha do cron
// No dia a dia cada relatório chega individualmente (horários diferentes), então esse limite
// só entra em ação quando vários se acumularam por falha prolongada do cron.
$MAX_POR_EXECUCAO = 3;
if (count($ids) > $MAX_POR_EXECUCAO) {
    $log[] = "AVISO: " . count($ids) . " elegíveis, processando apenas $MAX_POR_EXECUCAO por execução (anti-ban WhatsApp)";
    $ids = array_slice($ids, 0, $MAX_POR_EXECUCAO);
}

foreach ($ids as $rid) {

    // ── Pré-validação ANTES da transação ─────────────────────────────────────
    // Lê o relatório SEM lock para checar janela e duplicata antes de commitar
    $rPre = $db->query(
        "SELECT r.send_time, r.sent_at, r.send_days, r.status, r.next_send_at
         FROM reports r
         WHERE r.id = ? AND r.status IN ('active','scheduled') AND r.next_send_at <= NOW()",
        [$rid]
    )->fetch();

    if (!$rPre) {
        $log[] = "SKIP #{$rid}: já processado por outra execução (pré-check)";
        continue;
    }

    // Verifica janela de 15min ANTES de abrir transação
    $horarioProg = substr($rPre['send_time'] ?? '00:00', 0, 5);
    $tProg = DateTime::createFromFormat('H:i', $horarioProg, new DateTimeZone(APP_TIMEZONE));
    if ($tProg) {
        $tProg->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
        $diffSeg = $now->getTimestamp() - $tProg->getTimestamp();
        if ($diffSeg < 0 || $diffSeg > 10800) {
            // Fora da janela de 3h: corrige next_send_at para o horário programado de hoje
            // (o cron colocou +55min como placeholder, precisa restaurar)
            $hojeProgStr = $now->format('Y-m-d') . ' ' . $horarioProg . ':00';
            $db->query("UPDATE reports SET next_send_at=? WHERE id=?", [$hojeProgStr, $rid]);
            $log[] = "SKIP #{$rid}: fora da janela de 3h do horário $horarioProg (diff={$diffSeg}s) — next_send_at restaurado";
            continue;
        }
    }

    // Verifica duplicata: já enviou hoje neste horário?
        $hojeData   = $now->format('Y-m-d');
        $ultimaHora = '';
        $ultimaData = '';
        if (!empty($rPre['sent_at'])) {
            $ultimoEnvio = new DateTime($rPre['sent_at'], new DateTimeZone(APP_TIMEZONE));
            $ultimaData  = $ultimoEnvio->format('Y-m-d');
            $ultimaHora  = $ultimoEnvio->format('H:i');
        }

        if ($ultimaData === $hojeData && $ultimaHora === $horarioProg) {
            // Já enviou hoje neste horário → agenda próximo
            $next = ReportController::calcNextSend($rPre['send_time'], $rPre['send_days'], 'daily');
            $db->query("UPDATE reports SET next_send_at=? WHERE id=?", [$next, $rid]);
            $log[] = "SKIP #{$rid}: já enviado hoje às $ultimaHora — próximo: $next";
            continue;
        }

        if ($ultimaData === $hojeData && $ultimaHora !== $horarioProg) {
            $log[] = "INFO #{$rid}: horário alterado ($ultimaHora -> $horarioProg), reenviando";
        }

    // Verifica dia da semana ANTES da transação
    $diasConf = array_map('intval', explode(',', $rPre['send_days'] ?? '1,2,3,4,5'));
    if (!in_array($diaAtual, $diasConf)) {
        $next = ReportController::calcNextSend($rPre['send_time'], $rPre['send_days'], 'daily');
        $db->query("UPDATE reports SET next_send_at=? WHERE id=?", [$next, $rid]);
        $log[] = "SKIP #{$rid}: dia $diaAtual não está em [" . implode(',', $diasConf) . "] — próximo: $next";
        continue;
    }

    // ── CLAIM ATÔMICO via transação + FOR UPDATE ──────────────────────────────
    $db->begin();
    $r = $db->query(
        "SELECT r.*, aa.account_id AS meta_account_id, aa.access_token,
                wi.instance_name, u.email AS user_email,
                aa.account_name,
                c.name AS client_name, c.company AS client_company
         FROM reports r
         LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
         LEFT JOIN whatsapp_instances wi ON r.whatsapp_id = wi.id
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN clients c ON r.client_id = c.id
         WHERE r.id = ?
           AND r.status IN ('active','scheduled')
           AND r.next_send_at <= NOW()
         FOR UPDATE",
        [$rid]
    )->fetch();
    $r = $r ? decryptTokens($r) : null;

    if (!$r) {
        $db->rollback();
        $log[] = "SKIP #{$rid}: já processado por outra execução (FOR UPDATE)";
        continue;
    }

    // Empurra next_send_at para evitar reprocessamento durante execução
    $db->query("UPDATE reports SET next_send_at = DATE_ADD(NOW(), INTERVAL 55 MINUTE) WHERE id = ?", [$rid]);
    $db->commit();
    // ── Transação encerrada — daqui em diante sem rollback ───────────────────

    $log[] = "Processando #{$r['id']} '{$r['title']}' | horário: $horarioProg | dia: $diaAtual";

    // Calcula datas do período
    [$start, $end] = ReportController::calcPeriodDates($r['period_type'] ?? 'last_7_days');
    $campIds = !empty($r['camp_ids']) ? array_filter(explode(',', $r['camp_ids'])) : [];

    if ($start === 'MAX') {
        if (!empty($r['meta_account_id']) && !empty($r['access_token'])) {
            $start = ReportController::fetchCampaignStartDate($r['meta_account_id'], $r['access_token'], $campIds, (int)($r['ad_account_id'] ?? 0));
        } else {
            $start = date('Y-m-d', strtotime('-90 days'));
        }
    }
    // Sanidade anti-1969: nunca usar data inválida
    if (!$start || $start < '2015-01-01' || $start === '0001-01-01') {
        $start = date('Y-m-d', strtotime('-90 days'));
    }

    // Busca métricas — Meta ou Google
    $rows  = null;
    $platf = $r['platform'] ?? 'meta';

    if (in_array($platf, ['meta','both']) && !empty($r['meta_account_id']) && !empty($r['access_token'])) {
        $rows = ReportController::fetchMetricsMeta(
            $r['meta_account_id'], $r['access_token'], $start, $end, $campIds
        );
    }
    if ($rows === null && in_array($platf, ['google','both']) && !empty($r['meta_account_id']) && !empty($r['access_token'])) {
        $rows = ReportController::fetchMetricsGoogle(
            $r['meta_account_id'], $r['access_token'], $start, $end, $campIds
        );
        if ($rows !== null) $log[] = "INFO #{$r['id']}: métricas Google Ads API";
    }
    if ($rows === null && !empty($r['ad_account_id'])) {
        $rows = ReportController::fetchMetricsFromDB((int)$r['ad_account_id'], $start, $end, $campIds);
        if ($rows !== null) $log[] = "INFO #{$r['id']}: usando fallback do banco";
    }

    $periodo_str = date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end));
    $campNome = '';
    if (!empty($campIds)) {
        $placeholders = implode(',', array_fill(0, count($campIds), '?'));
        $campRows = $db->query("SELECT DISTINCT campaign_name FROM campaign_metrics WHERE campaign_id IN ($placeholders) ORDER BY campaign_name", $campIds)->fetchAll();
        $campNome = implode(', ', array_column($campRows, 'campaign_name'));
        if (empty($campNome) && !empty($r['access_token'])) {
            $names = [];
            foreach ($campIds as $cid) {
                $url = "https://graph.facebook.com/".META_API_VERSION."/{$cid}?fields=name&access_token=" . urlencode($r['access_token']);
                $ch  = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true]);
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if (!empty($res['name'])) $names[] = $res['name'];
            }
            $campNome = implode(', ', $names);
        }
    }

    // ── Gera (ou reutiliza) o link público do relatório para substituir {link} ──
    $linkPublico = '';
    try {
        $shareToken = $r['share_token'] ?? null;
        if (!$shareToken) {
            $shareToken = bin2hex(random_bytes(16));
            $db->query("UPDATE reports SET share_token=? WHERE id=?", [$shareToken, $r['id']]);
        }
        // Monta URL com template PDF do relatório (se houver)
        $tplParam = !empty($r['pdf_tpl_id']) ? '&tpl=' . (int)$r['pdf_tpl_id'] : '';
        // Usa SOMENTE o template vinculado ao relatório — sem fallback automático
        $accentParam = '';
        try {
            if (!empty($r['pdf_tpl_id'])) {
                $tplRow = $db->query("SELECT config FROM pdf_templates WHERE id=? AND user_id=?", [(int)$r['pdf_tpl_id'], $r['user_id']])->fetch();
                if ($tplRow && !empty($tplRow['config'])) {
                    $tplCfg = json_decode($tplRow['config'], true) ?: [];
                    $accentParam = !empty($tplCfg['palette']['accent']) ? '&ac=' . urlencode($tplCfg['palette']['accent']) : '';
                }
            }
            // Sem pdf_tpl_id = sem template = PDF com layout padrão
        } catch (\Throwable $_ta) {}
        // Adiciona slug legível com nome do cliente/empresa
        $slugCron = '';
        $slugBase = trim($r['client_company'] ?? '') ?: trim($r['client_name'] ?? '') ?: trim($r['title'] ?? '');
        if ($slugBase) {
            $slugCron = mb_strtolower($slugBase, 'UTF-8');
            $slugCron = preg_replace('/[áàãâä]/u','a',$slugCron);$slugCron = preg_replace('/[éèêë]/u','e',$slugCron);
            $slugCron = preg_replace('/[íìîï]/u','i',$slugCron);$slugCron = preg_replace('/[óòõôö]/u','o',$slugCron);
            $slugCron = preg_replace('/[úùûü]/u','u',$slugCron);$slugCron = preg_replace('/[ç]/u','c',$slugCron);
            $slugCron = preg_replace('/[^a-z0-9\s-]/u','',$slugCron);
            $slugCron = substr(trim(preg_replace('/[\s-]+/','-',$slugCron),'-'),0,40);
        }
        $nParamCron = $slugCron ? '&n='.urlencode($slugCron) : '';
        $linkPublico = APP_URL . '/r?t=' . $shareToken . $tplParam . $nParamCron . $accentParam;
    } catch (\Throwable $_tl) {}

    $msg = ReportController::buildMessage($r['message_text'] ?? '', [
        'metrics'        => $rows,
        'periodo'        => $periodo_str,
        'account_name'   => $r['account_name']   ?? '',
        'client_name'    => $r['client_name']     ?? '',
        'client_company' => $r['client_company']  ?? '',
        'observacoes'    => '',
        'campaign_name'  => $campNome,
        'link'           => $linkPublico,
    ]);

    $phone    = $r['recipient_phone'] ?? '';
    $recvType = $r['recv_type'] ?? 'phone';
    $groupId  = $r['group_id']  ?? '';
    $groupInst= $r['group_instance'] ?? '';
    $instance = $r['instance_name'] ?? '';

    // Fallback: se instância foi deletada, pega a primeira ativa do usuário
    if (!$instance) {
        $fallbackInst = $db->query(
            "SELECT instance_name FROM whatsapp_instances WHERE user_id=? AND status='connected' ORDER BY is_default DESC, id ASC LIMIT 1",
            [$r['user_id']]
        )->fetch();
        if ($fallbackInst) {
            $instance = $fallbackInst['instance_name'];
            $log[] = "AVISO #{$r['id']}: instância original deletada, usando fallback: $instance";
        }
    }

    if (!$instance) {
        $log[] = "ERRO #{$r['id']}: instância WhatsApp não configurada";
        $erros++;
        atualizarProximoEnvio($db, $r, false, $horarioProg);
        continue;
    }

    if ($recvType === 'group' && $groupId) {
        $instName = $groupInst ?: $instance;
        $result = sendGroupWhatsAppWithRetry($instName, $groupId, $msg);
    } elseif ($phone) {
        $isGroup = str_contains($phone, '@g.us') || str_contains($phone, '-');
        if ($isGroup) {
            $result = sendGroupWhatsAppWithRetry($instance, $phone, $msg);
        } else {
            $result = ['ok' => false, 'error' => 'Não tentado'];
            for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
                $result = ReportController::sendWhatsAppStatic($instance, $phone, $msg);
                if ($result['ok']) break;
                if ($tentativa < 3) usleep(5000000);
            }
        }
    } else {
        $log[] = "ERRO #{$r['id']}: destinatário não configurado";
        $erros++;
        atualizarProximoEnvio($db, $r, false, $horarioProg);
        continue;
    }

    if ($result['ok']) {
        $log[] = "ENVIADO #{$r['id']} '{$r['title']}' → $phone ✓";
        $enviados++;
        // Salva o horário PROGRAMADO (não atual) para anti-duplicata funcionar corretamente
        $sentAtTime = $now->format('Y-m-d') . ' ' . $horarioProg . ':00';
        $db->query("UPDATE reports SET sent_whatsapp=1, sent_at=?, last_send_status='ok', last_send_error=NULL WHERE id=?", [$sentAtTime, $r['id']]);
        try { $db->query(
    "INSERT INTO report_logs (report_id,user_id,status,destinatario,tipo_envio,report_title,client_name,company,periodo,canal) VALUES (?,?,'enviado',?,'automatico',?,?,?,?,?)",
    [$r['id'],$r['user_id'],$phone,$r['title']??'',$r['client_name']??'',$r['client_company']??'',$r['periodo']??'',($r['recv_type']==='group'?'grupo':'numero')]
); } catch (\Throwable $_rl) {}
        // Envia mensagem complementar se existir
        $followupText = trim($r['followup_message'] ?? '');
        if ($followupText !== '') {
            $followupFinal = ReportController::buildMessage($followupText, [
                'metrics'        => $metrics ?? [],
                'periodo'        => $periodo_str ?? '',
                'account_name'   => $r['account_name'] ?? '',
                'client_name'    => $r['client_name'] ?? '',
                'client_company' => $r['client_company'] ?? '',
                'observacoes'    => '',
                'campaign_name'  => $campNome ?? '',
                'link'           => '',
            ]);
            sleep(1);
            if ($recvType === 'group' && !empty($groupId)) {
                $instName = !empty($r['group_instance']) ? $r['group_instance'] : $instance;
                ReportController::sendWhatsAppGroup($instName, $groupId, $followupFinal);
            } else {
                ReportController::sendWhatsAppStatic($instance, $phone, $followupFinal);
            }
        }

        atualizarProximoEnvio($db, $r, true, $horarioProg);
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'success', ?, ?)",
                [$r['user_id'], "Relatório enviado: {$r['title']}", "Enviado para $phone com sucesso."]
            );
        } catch (\Throwable $_e) {}
        usleep(2000000);
    } else {
        $log[] = "ERRO #{$r['id']}: {$result['error']}";
        $erros++;
        $db->query("UPDATE reports SET last_send_status='error', last_send_error=? WHERE id=?",
            [mb_substr($result['error'], 0, 255), $r['id']]);
        try { $db->query(
    "INSERT INTO report_logs (report_id,user_id,status,destinatario,tipo_envio,erro_msg,report_title,client_name,company,periodo,canal) VALUES (?,?,'erro',?,'automatico',?,?,?,?,?,?)",
    [$r['id'],$r['user_id'],$phone,mb_substr($result['error'],0,255),$r['title']??'',$r['client_name']??'',$r['client_company']??'',$r['periodo']??'',($r['recv_type']==='group'?'grupo':'numero')]
); } catch (\Throwable $_rl) {}
        atualizarProximoEnvio($db, $r, false, $horarioProg);
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                [$r['user_id'], "Falha no envio: {$r['title']}", mb_substr($result['error'], 0, 200)]
            );
        } catch (\Throwable $_e) {}
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

// ============================================================
// Funções auxiliares
// ============================================================

/**
 * Atualiza next_send_at após processamento.
 * $sucesso=true: agenda próximo dia | false: reagenda 1h para nova tentativa (once) ou próximo dia
 */
function atualizarProximoEnvio(object $db, array $r, bool $sucesso, string $horarioProg): void {
    $freq = $r['frequency'] ?? 'once';
    if ($freq === 'once') {
        if ($sucesso) {
            $db->query("UPDATE reports SET status='sent', next_send_at=NULL WHERE id=?", [$r['id']]);
        } else {
            // Em erro: reagenda para 1h depois (nova tentativa no mesmo dia)
            $db->query("UPDATE reports SET next_send_at=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?", [$r['id']]);
        }
    } else {
        $next = ReportController::calcNextSend($r['send_time'], $r['send_days'], $freq);
        $db->query("UPDATE reports SET next_send_at=? WHERE id=?", [$next, $r['id']]);
    }
}

function sendGroupWhatsAppWithRetry(string $instanceName, string $groupId, string $message): array {
    $lastError = 'Erro desconhecido';
    for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
        $result = ReportController::sendWhatsAppGroup($instanceName, $groupId, $message);
        if ($result['ok']) return ['ok' => true];
        $lastError = "Tentativa $tentativa: " . ($result['error'] ?? 'erro');
        if ($tentativa < 3) usleep(5000000);
    }
    return ['ok' => false, 'error' => $lastError];
}
