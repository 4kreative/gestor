<?php
/**
 * cron/cron_master.php — Cron Master
 * 
 * Roda a cada 1 minuto no cron-job.org e decide internamente
 * quais jobs executar com base no horário programado.
 * 
 * URL: https://seudominio.com.br/cron/cron_master.php?key=SEU_CRON_SECRET
 * Frequência: a cada 1 minuto
 */

require_once __DIR__ . '/../config/config.php';

$key = $_GET['key'] ?? '';
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$now    = new \DateTime('now', new \DateTimeZone(APP_TIMEZONE));
$hora   = (int)$now->format('H');
$minuto = (int)$now->format('i');
$log    = [];
$log[]  = "cron_master — " . $now->format('Y-m-d H:i:s');

// ============================================================
// AGENDA DE JOBS
// ============================================================
//
// Formato de cada linha:
//   ['arquivo', HORA, MINUTO, ATIVO]
//
// ── HORA ────────────────────────────────────────────────────
//   -1  = toda hora (não fixa uma hora específica)
//    0  = meia-noite (00h)
//    3  = 3h da manhã
//   14  = 14h (2 da tarde)
//   23  = 23h
//
// ── MINUTO ──────────────────────────────────────────────────
//   -1  = todo minuto
//    0  = no minuto 00 (ex: 01:00, 02:00...)
//   15  = no minuto 15 (ex: 01:15, 02:15...)
//   30  = no minuto 30 (ex: 01:30, 02:30...)
//   45  = no minuto 45 (ex: 01:45, 02:45...)
//
// ── ATIVO ───────────────────────────────────────────────────
//   true  = ligado (vai executar)
//   false = desligado (fica na lista mas não executa)
//
// ── EXEMPLOS ────────────────────────────────────────────────
//
// Todo minuto:
//   ['arquivo.php', -1, -1, true]
//   → igual "A cada 1 minuto" no cron-job.org
//
// A cada hora:
//   ['arquivo.php', -1, 0, true]
//   → igual "A cada 1 hora" no cron-job.org
//
// A cada 30 minutos (duas linhas):
//   ['arquivo.php', -1,  0, true],
//   ['arquivo.php', -1, 30, true],
//   → igual "A cada 30 minutos" no cron-job.org
//
// Todo dia às 03:00:
//   ['arquivo.php', 3, 0, true]
//   → igual "Todos os dias às 3:00" no cron-job.org
//
// Todo dia às 14:30:
//   ['arquivo.php', 14, 30, true]
//   → igual "Todos os dias às 14:30" no cron-job.org
//
// ── PARA ATIVAR OU DESATIVAR ────────────────────────────────
// Mude o último campo:
//   Ligado:    ['monitor.php', -1, -1, true]
//   Desligado: ['monitor.php', -1, -1, false]
//
// ── PARA MUDAR O HORÁRIO ────────────────────────────────────
// Exemplo: mudar sync_groups de 03:00 para 02:30
//   Antes:  ['sync_groups.php',  3,  0, true]
//   Depois: ['sync_groups.php',  2, 30, true]
//
// Exemplo: rodar 2x por dia às 08:00 e 20:00 (duplique a linha):
//   ['arquivo.php',  8, 0, true],
//   ['arquivo.php', 20, 0, true],
//
// ============================================================

$jobs = [
    // ── Todo minuto ─────────────────────────────────────────
    ['send-reports.php',    -1,  -1,  true],   // Envio de relatórios agendados
    ['run-alerts.php',      -1,  -1,  true],   // Alertas de saldo (a cada minuto)

    // ── A cada hora (no minuto 0) ───────────────────────────
    ['sync_metrics.php',    -1,   0,  true],   // Sincronização de métricas dashboard
    ['api/sync-prepago',    -1,   0,  true],   // Sync saldo pré-pago (a cada 30min — xx:00)
    ['api/sync-prepago',    -1,  30,  true],   // Sync saldo pré-pago (a cada 30min — xx:30)

    // ── 1x por dia ──────────────────────────────────────────
    ['sync_groups.php',      3,   0,  true],   // Sincroniza grupos WhatsApp (03:00)
    ['refresh_tok.php',      3,  30,  true],   // Renova tokens Meta/Google (03:30)
    ['cleanup.php',          4,   0,  true],   // Limpa banco de dados (04:00)
    // ['run-metrics.php',   5,   0,  true],   // Sync histórico de métricas — não utilizado

    // ── Debug (desativado por padrão) ───────────────────────
    // Para ativar: mude false para true
    // Para desativar: mude true para false
    ['monitor.php',         -1,   0,  false],  // Monitor de debug a cada 15min — desativado por padrão
    ['monitor.php',         -1,  15,  false],  // (ative mudando false para true nos 4 itens)
    ['monitor.php',         -1,  30,  false],
    ['monitor.php',         -1,  45,  false],
];

// ============================================================
// EXECUÇÃO — não edite abaixo desta linha
// ============================================================

$executados = 0;
$erros      = 0;

foreach ($jobs as [$arquivo, $jobHora, $jobMinuto, $ativo]) {
    // Verifica se está ativo
    if (!$ativo) continue;

    // Verifica se deve rodar agora
    $deveRodar = true;
    if ($jobHora   !== -1 && $jobHora   !== $hora)   $deveRodar = false;
    if ($jobMinuto !== -1 && $jobMinuto !== $minuto)  $deveRodar = false;

    if (!$deveRodar) continue;

    $log[] = "→ Executando: $arquivo";

    if (strpos($arquivo, 'api/') === 0) {
        $rota = str_replace('api/', '', $arquivo);
        $url  = APP_URL . '/api/cron/' . $rota . '?key=' . urlencode(CRON_SECRET);
    } else {
        $url  = APP_URL . '/cron/' . $arquivo . '?key=' . urlencode(CRON_SECRET);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 55,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code !== 200) {
        $log[] = "  ❌ ERRO: " . ($err ?: "HTTP $code");
        $erros++;
    } else {
        $decoded = json_decode($res, true);
        $status  = ($decoded['success'] ?? true) ? '✅' : '⚠️';
        $log[]   = "  $status OK (HTTP $code)";
        $executados++;
    }
}

if ($executados === 0 && $erros === 0) {
    $log[] = "Nenhum job agendado para este minuto ({$hora}:{$minuto})";
}

echo json_encode([
    'success'    => true,
    'hora'       => $now->format('H:i'),
    'executados' => $executados,
    'erros'      => $erros,
    'log'        => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
