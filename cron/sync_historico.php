<?php
/**
 * GestorPro — Sync Histórico Completo
 * Busca TODOS os dados desde a data mais antiga até hoje.
 *
 * Acesse UMA VEZ via URL autenticada com CRON_SECRET:
 * GET https://seudominio.com.br/cron/sync_historico.php?key={CRON_SECRET}
 *
 * APAGUE após rodar!
 */

// ── Carrega config ANTES de checar a chave ────────────────────────────────────
define('CLI_MODE', true);
require_once __DIR__.'/../config/config.php';

$key = $_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? '');
if ($key !== CRON_SECRET) {
    http_response_code(403);
    die('403 Forbidden');
}

// Aumenta limites para não cortar no meio
set_time_limit(600);
ini_set('memory_limit', '256M');

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/TokenCrypto.php';
require_once __DIR__.'/../controllers/AccountController.php';
require_once __DIR__.'/../controllers/ReportController.php';

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;background:#111;color:#eee;padding:20px}
.ok{color:#2ecc71}.err{color:#e74c3c}.info{color:#3498db}.warn{color:#f39c12}
pre{background:#222;padding:8px;border-radius:4px;margin:4px 0}
</style>';
echo '<h2>🔄 Sync Histórico Completo — GestorPro</h2>';
echo '<p class="warn">⏳ Isso pode levar alguns minutos. Não feche a aba!</p>';
flush();

$db   = Database::getInstance();
$accs = decryptTokens($db->query("SELECT * FROM ad_accounts WHERE status='active' ORDER BY id")->fetchAll());

// Data de início: 2025-11-01 (início das campanhas mais antigas)
// Ajuste se tiver campanhas ainda mais antigas
$HIST_START = '2025-11-01';
$end        = date('Y-m-d');

echo "<p class='info'>📅 Período: <b>$HIST_START → $end</b></p>";
echo "<p class='info'>📊 Contas ativas: <b>" . count($accs) . "</b></p><hr>";
flush();

$totalRows = 0;
$erros     = 0;

foreach ($accs as $acc) {
    echo "<h3>📊 {$acc['account_name']} ({$acc['platform']})</h3>";
    flush();

    if ($acc['platform'] !== 'meta') {
        echo "<p class='warn'>⚠️ Plataforma {$acc['platform']} — pulando (somente Meta suportado)</p>";
        continue;
    }
    if (!$acc['access_token']) {
        echo "<p class='err'>❌ Sem token — pulando</p>";
        $erros++;
        continue;
    }

    // Divide em blocos de 30 dias para não sobrecarregar a API
    $cursor    = new DateTime($HIST_START);
    $endDate   = new DateTime($end);
    $accRows   = 0;
    $accErros  = 0;

    while ($cursor <= $endDate) {
        $blockStart = $cursor->format('Y-m-d');
        $cursor->modify('+29 days');
        $blockEnd = min($cursor->format('Y-m-d'), $end);

        echo "<p class='info'>  📅 Bloco: $blockStart → $blockEnd ...</p>";
        flush();

        $result = syncMetaBlock($acc, $blockStart, $blockEnd, $db);
        $accRows  += $result['rows'];
        $accErros += $result['errors'];

        if ($result['errors'] > 0) {
            echo "<p class='err'>    ❌ Erros neste bloco: {$result['errors']} — {$result['last_error']}</p>";
        } else {
            echo "<p class='ok'>    ✅ {$result['rows']} linhas salvas</p>";
        }
        flush();

        $cursor->modify('+1 day');

        // Pequena pausa entre blocos para não bater no rate limit
        usleep(300000); // 300ms
    }

    $totalRows += $accRows;
    echo "<p class='ok'><b>✅ Total conta: $accRows linhas | Erros: $accErros</b></p><hr>";
    flush();
}

echo "<h2 class='ok'>🏁 Concluído! Total de linhas salvas: $totalRows | Erros: $erros</h2>";
echo "<p class='err'><b>⚠️ APAGUE este arquivo (sync_historico.php) do servidor agora!</b></p>";


// ─── Função de sync por bloco ────────────────────────────────────────────────
function syncMetaBlock(array $acc, string $start, string $end, $db): array {
    $timeRange = urlencode(json_encode(['since' => $start, 'until' => $end]));
    $fields    = 'campaign_id,campaign_name,impressions,clicks,spend,reach,date_start,cpm,cpc,ctr,actions,action_values,instagram_profile_visits,cost_per_action_type';
    $url = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$acc['account_id']}/insights"
         . "?fields={$fields}&level=campaign&time_range={$timeRange}&time_increment=1&limit=500"
         . "&access_token=" . urlencode($acc['access_token']);

    $rows      = 0;
    $errors    = 0;
    $lastError = '';
    $nextUrl   = $url;

    for ($p = 0; $p < 20 && $nextUrl; $p++) {
        $ch = curl_init($nextUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($raw, true);

        if ($code !== 200 || !empty($res['error'])) {
            $lastError = $res['error']['message'] ?? "HTTP $code";
            $errors++;
            break;
        }

        if (empty($res['data'])) break;

        foreach ($res['data'] as $row) {
            $conversions = 0; $revenue = 0; $msgs = 0; $leads = 0; $profVisits = 0;
            foreach ($row['actions'] ?? [] as $a) {
                $t = $a['action_type']; $v = (int)$a['value'];
                // Mensagens
                if ($t === 'onsite_conversion.messaging_conversation_started_7d') $msgs = max($msgs, $v);
                elseif ($t === 'click_to_whatsapp_all' && $msgs === 0) $msgs = $v;
                elseif (strpos($t,'messaging') !== false && $msgs === 0) $msgs = max($msgs, $v);
                // Visitas ao perfil
                if (in_array($t,['ig_profile_visit','profile_visit','instagram_profile_visit'])) $profVisits += $v;
                // WhatsApp cliques diretos em actions[]
                if ($t === 'click_to_whatsapp_all' && $msgs === 0) $msgs = $v;
                // Leads e conversões
                // Prioridade leadgen/lead_grouped — evita dupla contagem com 'lead'
                if (in_array($t,['leadgen_grouped','onsite_conversion.lead_grouped'])) { $leads += $v; }
                elseif ($t === 'lead' && $leads === 0) { $leads += $v; }
                elseif (in_array($t,['offsite_conversion.fb_pixel_lead','onsite_web_lead','contact']) && $leads === 0) { $leads += $v; }
                if (in_array($t,['purchase','complete_registration','offsite_conversion.fb_pixel_purchase'])) $conversions += $v;
            }
            foreach ($row['action_values'] ?? [] as $a) {
                if ($a['action_type'] === 'purchase') $revenue += (float)$a['value'];
            }
            // profile_visits como campo direto
            if (!$profVisits) $profVisits = (int)($row['instagram_profile_visits'] ?? 0);
            // click_to_whatsapp via cost_per_action_type (campanha OUTCOME_TRAFFIC)
            if (!$msgs && !empty($row['cost_per_action_type'])) {
                $rowSpend = (float)($row['spend'] ?? 0);
                foreach ($row['cost_per_action_type'] as $cpa) {
                    if (($cpa['action_type'] ?? '') === 'click_to_whatsapp_all' && (float)($cpa['value'] ?? 0) > 0 && $rowSpend > 0) {
                        $msgs = (int)round($rowSpend / (float)$cpa['value']);
                        break;
                    }
                }
            }

            try {
                $db->query(
                    "INSERT INTO campaign_metrics
                        (ad_account_id,campaign_id,campaign_name,platform,date,
                         impressions,clicks,spend,reach,cpm,cpc,ctr,
                         conversions,leads,messages,profile_visits,revenue,synced_at)
                     VALUES (?,?,?,'meta',?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE
                        impressions=VALUES(impressions), clicks=VALUES(clicks),
                        spend=VALUES(spend), reach=VALUES(reach),
                        cpm=VALUES(cpm), cpc=VALUES(cpc), ctr=VALUES(ctr),
                        conversions=VALUES(conversions), leads=VALUES(leads),
                        messages=VALUES(messages), profile_visits=VALUES(profile_visits),
                        revenue=VALUES(revenue), synced_at=NOW()",
                    [
                        $acc['id'], $row['campaign_id'], $row['campaign_name'], $row['date_start'],
                        (int)($row['impressions'] ?? 0),
                        (int)($row['clicks']      ?? 0),
                        (float)($row['spend']     ?? 0),
                        (int)($row['reach']       ?? 0),
                        (float)($row['cpm']       ?? 0),
                        (float)($row['cpc']       ?? 0),
                        (float)($row['ctr']       ?? 0),
                        $conversions, $leads, $msgs, $profVisits, $revenue,
                    ]
                );
                $rows++;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $errors++;
            }
        }

        $nextUrl = $res['paging']['next'] ?? null;
    }

    return ['rows' => $rows, 'errors' => $errors, 'last_error' => $lastError];
}
