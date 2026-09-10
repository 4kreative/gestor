<?php
/**
 * GestorAds — Cron: Sincronização de Saldo Pré-pago
 *
 * Cacheia balance + daily_budget das contas Meta do tipo pré-pago em ad_accounts,
 * eliminando chamadas de API em tempo real no dashboard.
 *
 * Configure para rodar a cada 30 minutos no cron-job.org:
 *   GET https://seudominio.com.br/cron/sync_prepago.php?key={CRON_SECRET}
 *
 * Ou via servidor (CLI) no crontab:
 *   */30 * * * * php /caminho/do/projeto/cron/sync_prepago.php
 */

if (!defined('CLI_MODE')) define('CLI_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Carrega helpers apenas se existir (cron não precisa de sessão/auth)
if (file_exists(__DIR__ . '/../core/helpers.php')) {
    require_once __DIR__ . '/../core/helpers.php';
}

// ── Autenticação HTTP / CLI ────────────────────────────────────────────────────
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
    if ($key !== CRON_SECRET) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }
    header('Content-Type: application/json');
}

set_exception_handler(function (Throwable $e) use ($isCli) {
    $msg = $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
    error_log('[sync_prepago FATAL] ' . $msg);
    if ($isCli) echo "[ERRO FATAL] $msg\n";
    else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    }
    exit;
});

// ── Inicialização ──────────────────────────────────────────────────────────────
$db    = Database::getInstance();
$now   = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$log   = [];
$ok    = 0;
$err   = 0;

$log[] = "[{$now->format('Y-m-d H:i:s')}] Iniciando sync_prepago...";

// ── Busca contas Meta vinculadas a clientes pré-pago ativos ───────────────────
$contas = $db->query(
    "SELECT aa.id, aa.account_id, aa.access_token, aa.account_name
       FROM ad_accounts aa
       JOIN clients c ON c.id = aa.client_id AND c.user_id = aa.user_id
      WHERE aa.platform = 'meta'
        AND aa.status   = 'active'
        AND c.payment_type = 'prepago'
        AND c.status    = 'active'
      ORDER BY aa.id"
)->fetchAll();
$contas = decryptTokens($contas);

$log[] = "Contas pré-pago encontradas: " . count($contas);

if (empty($contas)) {
    _finalize($log, $ok, $err, $isCli);
}

// ── Processa cada conta ────────────────────────────────────────────────────────
foreach ($contas as $acc) {
    try {
        $balance  = _fetchBalance($acc['account_id'], $acc['access_token']);
        $ritmo    = _fetchRitmoDia($acc['account_id'], $acc['access_token'], (int)$acc['id'], $db);

        $db->query(
            "UPDATE ad_accounts
                SET prepago_balance   = ?,
                    prepago_ritmo_dia = ?,
                    prepago_synced_at = NOW()
              WHERE id = ?",
            [$balance, $ritmo, $acc['id']]
        );

        $log[] = "[OK] #{$acc['id']} {$acc['account_name']} | saldo=R\${$balance} ritmo=R\${$ritmo}/dia";
        $ok++;
    } catch (Throwable $e) {
        $log[] = "[ERRO] #{$acc['id']} {$acc['account_name']}: " . $e->getMessage();
        error_log('[sync_prepago] Conta #' . $acc['id'] . ' ' . $acc['account_name'] . ': ' . $e->getMessage());
        $err++;
    }
}

_finalize($log, $ok, $err, $isCli);

// ═════════════════════════════════════════════════════════════════════════════
// Funções auxiliares
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Busca o saldo atual da conta Meta (spend_cap - amount_spent OU balance).
 * Retorna valor em BRL (já dividido por 100).
 */
function _fetchBalance(string $metaAccountId, string $token): float
{
    $apiVersion = defined('META_API_VERSION') ? META_API_VERSION : 'v21.0';
    $url = "https://graph.facebook.com/{$apiVersion}/act_{$metaAccountId}"
         . "?fields=balance,spend_cap,amount_spent,currency"
         . "&access_token=" . urlencode($token);

    $raw  = _curlGet($url);
    $acc  = json_decode($raw, true);

    if (!is_array($acc)) {
        throw new RuntimeException("Resposta inválida da API Meta (balance)");
    }

    if (!empty($acc['error'])) {
        throw new RuntimeException("Meta API: " . ($acc['error']['message'] ?? 'erro desconhecido'));
    }

    if (!empty($acc['spend_cap']) && (int)$acc['spend_cap'] > 0 && isset($acc['amount_spent'])) {
        return max(0, round(((float)$acc['spend_cap'] - (float)$acc['amount_spent']) / 100, 2));
    }

    if (isset($acc['balance'])) {
        return round((float)$acc['balance'] / 100, 2);
    }

    return 0.0;
}

/**
 * Busca o ritmo diário real a partir das campanhas/adsets ativos.
 * Fallback: média diária do mês atual calculada no banco local.
 * Retorna valor em BRL (já dividido por 100).
 */
function _fetchRitmoDia(string $metaAccountId, string $token, int $adAccountDbId, Database $db): float
{
    $apiVersion = defined('META_API_VERSION') ? META_API_VERSION : 'v21.0';

    // ── 1. Tenta daily_budget nas campanhas (CBO) ──────────────────────────────
    $urlCamp = "https://graph.facebook.com/{$apiVersion}/act_{$metaAccountId}/campaigns"
             . "?fields=daily_budget,effective_status"
             . "&effective_status=[\"ACTIVE\"]"
             . "&limit=100"
             . "&access_token=" . urlencode($token);

    $rawCamp = _curlGet($urlCamp);
    $camps   = json_decode($rawCamp, true);

    $totalCBO      = 0;
    $temBudgetCBO  = false;
    foreach (($camps['data'] ?? []) as $camp) {
        if (!empty($camp['daily_budget'])) {
            $totalCBO += (float)$camp['daily_budget'];
            $temBudgetCBO = true;
        }
    }

    if ($temBudgetCBO) {
        return round($totalCBO / 100, 2);
    }

    // ── 2. Tenta daily_budget nos adsets (ABO) ─────────────────────────────────
    $urlAdsets = "https://graph.facebook.com/{$apiVersion}/act_{$metaAccountId}/adsets"
               . "?fields=daily_budget,effective_status"
               . "&effective_status=[\"ACTIVE\"]"
               . "&limit=100"
               . "&access_token=" . urlencode($token);

    $rawAdsets = _curlGet($urlAdsets);
    $adsets    = json_decode($rawAdsets, true);

    $totalABO = 0;
    foreach (($adsets['data'] ?? []) as $adset) {
        if (!empty($adset['daily_budget'])) {
            $totalABO += (float)$adset['daily_budget'];
        }
    }

    if ($totalABO > 0) {
        return round($totalABO / 100, 2);
    }

    // ── 3. Fallback: média diária do banco local ───────────────────────────────
    $mesStart      = date('Y-m-01');
    $diasCorridos  = max(1, (int)date('j'));
    $gastoMes      = (float)$db->query(
        "SELECT COALESCE(SUM(spend),0) FROM campaign_metrics WHERE ad_account_id=? AND date>=?",
        [$adAccountDbId, $mesStart]
    )->fetchColumn();

    return $gastoMes > 0 ? round($gastoMes / $diasCorridos, 2) : 0.0;
}

/**
 * cURL GET simples com timeout de 20s.
 * Nunca lança exceção em erros de HTTP — retorna o body para análise.
 */
function _curlGet(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,  // Hostinger shared: evita falha de certificado
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'GestorAds/1.0',
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("cURL error: $err");
    }

    return $raw;
}

/** Finaliza e imprime o resultado. */
function _finalize(array $log, int $ok, int $err, bool $isCli): void
{
    $log[] = "[" . date('Y-m-d H:i:s') . "] Concluído. OK: $ok | Erros: $err";

    if ($isCli) {
        foreach ($log as $linha) echo $linha . "\n";
    } else {
        echo json_encode([
            'success' => true,
            'ok'      => $ok,
            'erros'   => $err,
            'log'     => $log,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}
