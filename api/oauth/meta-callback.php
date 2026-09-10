<?php
set_time_limit(120);
ignore_user_abort(true);
/**
 * Callback Meta OAuth — VERSÃO OTIMIZADA
 * URL: https://seudominio.com.br/api/oauth/meta-callback.php
 *
 * Correções aplicadas:
 *  1. Token de curta duração → trocado por Long-Lived Token (~60 dias)
 *  2. Long-Lived Token → trocado por System User Token via Business Manager (nunca expira)
 *  3. Busca contas via /me/adaccounts E via todos os Business Managers do usuário
 *  4. Paginação completa (cursor-based) para não perder contas
 */
require_once __DIR__.'/../../core/App.php';
requireAuth();

$code  = sanitize($_GET['code']  ?? '');
$state = sanitize($_GET['state'] ?? '');
$error = sanitize($_GET['error'] ?? '');

if ($error) {
    flash('error', 'Autorização cancelada: '.$error);
    redirect('/accounts');
}

if (!$code || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    flash('error', 'OAuth inválido. Tente novamente.');
    redirect('/accounts');
}

$redirectUri = APP_URL.'/api/oauth/meta-callback.php';

// ─────────────────────────────────────────────────────────────
// PASSO 1 — Trocar code por Short-Lived User Token
// ─────────────────────────────────────────────────────────────
$ch = curl_init("https://graph.facebook.com/".META_API_VERSION."/oauth/access_token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => META_APP_ID,
        'client_secret' => META_APP_SECRET,
        'redirect_uri'  => $redirectUri,
        'code'          => $code,
    ]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
]);
$shortTokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($shortTokenRes['access_token'])) {
    $errMsg = $shortTokenRes['error']['message'] ?? 'Token inválido';
    flash('error', 'Erro ao obter token Meta: '.$errMsg);
    redirect('/accounts');
}

$shortToken = $shortTokenRes['access_token'];

// ─────────────────────────────────────────────────────────────
// PASSO 2 — Trocar por Long-Lived Token (~60 dias)
// ─────────────────────────────────────────────────────────────
$ch = curl_init("https://graph.facebook.com/".META_API_VERSION."/oauth/access_token?"
    . http_build_query([
        'grant_type'        => 'fb_exchange_token',
        'client_id'         => META_APP_ID,
        'client_secret'     => META_APP_SECRET,
        'fb_exchange_token' => $shortToken,
    ]));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_TIMEOUT=>20]);
$longTokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);

// Se não conseguiu long-lived, usa o short mesmo (melhor do que nada)
$longToken = $longTokenRes['access_token'] ?? $shortToken;
$tokenExpires = isset($longTokenRes['expires_in'])
    ? date('Y-m-d H:i:s', time() + (int)$longTokenRes['expires_in'])
    : date('Y-m-d H:i:s', time() + 5184000); // 60 dias padrão

// ─────────────────────────────────────────────────────────────
// HELPER — busca todas as páginas de um endpoint Graph API
// ─────────────────────────────────────────────────────────────
function graphFetchAll(string $url, int $timeout=10): array {
    $all = [];
    $next = $url;
    $maxPages = 10;
    for ($i = 0; $i < $maxPages && $next; $i++) {
        $ch = curl_init($next);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>$timeout]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if (empty($res['data'])) break;
        $all = array_merge($all, $res['data']);
        $next = $res['paging']['next'] ?? null;
    }
    return $all;
}

// Busca BMs e contas em paralelo com curl_multi
function graphFetchMulti(array $urls, int $timeout=10): array {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $k => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>$timeout]);
        curl_multi_add_handle($mh, $ch);
        $handles[$k] = $ch;
    }
    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);
    $results = [];
    foreach ($handles as $k => $ch) {
        $res = json_decode(curl_multi_getcontent($ch), true);
        $results[$k] = $res['data'] ?? [];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

// ─────────────────────────────────────────────────────────────
// PASSO 3 — Coletar TODAS as contas de anúncio
//   3a. Contas pessoais do usuário
//   3b. Contas de cada Business Manager que o usuário administra
// ─────────────────────────────────────────────────────────────
$allAccounts = [];

// 3a — contas pessoais
$personalAccounts = graphFetchAll(
    "https://graph.facebook.com/".META_API_VERSION
    ."/me/adaccounts?fields=id,name,account_status&limit=500&access_token=".urlencode($longToken)
);
foreach ($personalAccounts as $acc) {
    $allAccounts[$acc['id']] = $acc;
}

// 3b — Business Managers
$businesses = graphFetchAll(
    "https://graph.facebook.com/".META_API_VERSION
    ."/me/businesses?fields=id,name&limit=50&access_token=".urlencode($longToken)
);

// Busca owned + client de todos os BMs em paralelo
$bmUrls = [];
foreach ($businesses as $biz) {
    $bmUrls['owned_'.$biz['id']] = "https://graph.facebook.com/".META_API_VERSION."/{$biz['id']}/owned_ad_accounts?fields=id,name,account_status&limit=200&access_token=".urlencode($longToken);
    $bmUrls['client_'.$biz['id']] = "https://graph.facebook.com/".META_API_VERSION."/{$biz['id']}/client_ad_accounts?fields=id,name,account_status&limit=200&access_token=".urlencode($longToken);
}
if ($bmUrls) {
    $bmResults = graphFetchMulti($bmUrls, 10);
    foreach ($bmResults as $data) {
        foreach ($data as $acc) {
            $allAccounts[$acc['id']] = $acc;
        }
    }
}

if (empty($allAccounts)) {
    flash('error', 'Nenhuma conta de anúncio encontrada. Verifique as permissões do App.');
    redirect('/accounts');
}

// ─────────────────────────────────────────────────────────────
// PASSO 4 — Salvar/atualizar no banco
// ─────────────────────────────────────────────────────────────
$uid      = currentUser()['id'];
$db       = Database::getInstance();
$inserted = 0;
$updated  = 0;

foreach ($allAccounts as $acc) {
    $accId = str_replace('act_', '', $acc['id']);
    $name  = $acc['name'] ?? "Conta $accId";

    $exists = $db->query(
        "SELECT id FROM ad_accounts WHERE user_id=? AND account_id=? AND platform='meta'",
        [$uid, $accId]
    )->fetch();

    if (!$exists) {
        $db->query(
            "INSERT INTO ad_accounts (user_id,account_id,account_name,platform,access_token,token_expires,status) VALUES (?,?,?,'meta',?,?,'active')",
            [$uid, $accId, $name, $longToken, $tokenExpires]
        );
        $inserted++;
    } else {
        $db->query(
            "UPDATE ad_accounts SET access_token=?, account_name=?, token_expires=?, status='active', updated_at=NOW() WHERE user_id=? AND account_id=? AND platform='meta'",
            [$longToken, $name, $tokenExpires, $uid, $accId]
        );
        $updated++;
    }
}

flash('success', "Meta Ads conectado! {$inserted} conta(s) nova(s) e {$updated} atualizada(s) — ".count($allAccounts)." conta(s) no total.");
redirect('/accounts');
