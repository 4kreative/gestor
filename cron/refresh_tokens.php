<?php
/**
 * GestorPro — Cron de Renovação de Tokens
 * Renova tokens Meta (long-lived) e Google (refresh_token) antes de expirarem.
 *
 * Configure no cron-job.org para rodar 1x por dia às 03:00:
 * GET https://seudominio.com.br/cron/refresh_tokens.php?key={CRON_SECRET}
 *
 * Ou via servidor (CLI):
 * 0 3 * * * php /caminho/do/projeto/cron/refresh_tokens.php
 */

// ── Carrega config ANTES de checar a chave ────────────────────────────────────
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

// Suporte HTTP (cron-job.org) e CLI
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
require_once __DIR__.'/../core/TokenCrypto.php';

$db      = Database::getInstance();
$renewed = 0;
$failed  = 0;
$log     = [];

// ============================================================
// META — renova Long-Lived Tokens
// ============================================================
$log[] = "[META] Buscando tokens próximos de expirar...";
// Só renova tokens OAuth (que têm token_expires definido)
// Tokens manuais (token_expires IS NULL) não expiram e não precisam de renovação
$metaAccounts = $db->query(
    "SELECT id, access_token, account_name, user_id FROM ad_accounts
     WHERE platform='meta' AND status='active'
     AND token_expires IS NOT NULL
     AND token_expires < DATE_ADD(NOW(), INTERVAL 10 DAY)"
)->fetchAll();

$log[] = "Contas Meta para renovar: " . count($metaAccounts);

foreach ($metaAccounts as $acc) {
    $ch = curl_init("https://graph.facebook.com/" . META_API_VERSION . "/oauth/access_token?"
        . http_build_query([
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => META_APP_ID,
            'client_secret'     => META_APP_SECRET,
            'fb_exchange_token' => TokenCrypto::decrypt($acc['access_token']),
        ]));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 20]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($res['access_token'])) {
        $expires = isset($res['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int)$res['expires_in'])
            : date('Y-m-d H:i:s', time() + 5184000); // 60 dias padrão
        $db->query(
            "UPDATE ad_accounts SET access_token=?, token_expires=?, status='active', updated_at=NOW() WHERE id=?",
            [TokenCrypto::encrypt($res['access_token']), $expires, $acc['id']]
        );
        $renewed++;
        $log[] = "[OK] Meta — {$acc['account_name']} — expira em $expires";
        // Notificação no painel
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'success', ?, ?)",
                [$acc['user_id'], "🔑 Token renovado: {$acc['account_name']}", "Token Meta renovado. Expira em: $expires"]
            );
        } catch (\Throwable $_e) {}
    } else {
        $failed++;
        $err = $res['error']['message'] ?? 'erro desconhecido';
        // Só marca como erro se for token OAuth (tem token_expires)
        // Token manual não deve ser marcado como erro aqui
        if (!empty($acc['token_expires'])) {
            $db->query("UPDATE ad_accounts SET status='error' WHERE id=?", [$acc['id']]);
        }
        $log[] = "[ERRO] Meta — {$acc['account_name']}: $err";
        // Notificação de erro
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                [$acc['user_id'], "❌ Falha ao renovar token: {$acc['account_name']}", mb_substr($err, 0, 200)]
            );
        } catch (\Throwable $_e) {}
    }
}

// ============================================================
// GOOGLE — renova via refresh_token
// ============================================================
$log[] = "[GOOGLE] Buscando tokens próximos de expirar...";
$googleAccounts = $db->query(
    "SELECT id, refresh_token, account_name, user_id FROM ad_accounts
     WHERE platform='google' AND status='active'
     AND refresh_token IS NOT NULL AND refresh_token != ''
     AND (token_expires IS NULL OR token_expires < DATE_ADD(NOW(), INTERVAL 2 DAY))"
)->fetchAll();

$log[] = "Contas Google para renovar: " . count($googleAccounts);

foreach ($googleAccounts as $acc) {
    if (empty($acc['refresh_token'])) continue;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => TokenCrypto::decrypt($acc['refresh_token']),
            'grant_type'    => 'refresh_token',
        ]),
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($res['access_token'])) {
        $expires = date('Y-m-d H:i:s', time() + (int)($res['expires_in'] ?? 3600));
        $db->query(
            "UPDATE ad_accounts SET access_token=?, token_expires=?, status='active', updated_at=NOW() WHERE id=?",
            [TokenCrypto::encrypt($res['access_token']), $expires, $acc['id']]
        );
        $renewed++;
        $log[] = "[OK] Google — {$acc['account_name']} — expira em $expires";
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'success', ?, ?)",
                [$acc['user_id'], "🔑 Token Google renovado: {$acc['account_name']}", "Token Google renovado. Expira em: $expires"]
            );
        } catch (\Throwable $_e) {}
    } else {
        $failed++;
        $err = $res['error_description'] ?? $res['error'] ?? 'erro desconhecido';
        $db->query("UPDATE ad_accounts SET status='error' WHERE id=?", [$acc['id']]);
        $log[] = "[ERRO] Google — {$acc['account_name']}: $err";
        try {
            $db->query(
                "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'error', ?, ?)",
                [$acc['user_id'], "❌ Falha token Google: {$acc['account_name']}", mb_substr($err, 0, 200)]
            );
        } catch (\Throwable $_e) {}
    }
}

$log[] = "FIM — Renovados: $renewed | Erros: $failed";

if ($isCli) {
    foreach ($log as $linha) echo $linha . "\n";
} else {
    echo json_encode([
        'success'  => true,
        'renovados'=> $renewed,
        'erros'    => $failed,
        'log'      => $log,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
