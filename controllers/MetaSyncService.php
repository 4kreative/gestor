<?php
/**
 * ARQUIVO 2/3 — MetaSyncService.php
 * ============================================================
 * Sincroniza TODAS as métricas da API Meta Ads para o banco,
 * cobrindo as 38 variáveis do painel de variáveis.
 *
 * Substitua (ou mescle) o seu serviço de sync atual por este.
 *
 * Uso:
 *   $svc = new MetaSyncService($db);
 *   $svc->syncAccount($adAccount, $dateFrom, $dateTo);
 * ============================================================
 */

class MetaSyncService
{
    private \PDO $db;

    // Versão da API Meta (atualize conforme necessário)
    // API version agora usa a constante global META_API_VERSION de config.php
    private const API_BASE    = 'https://graph.facebook.com/';

    public function __construct($db)
    {
        $this->db = $db;
    }

    // =========================================================
    // SYNC PRINCIPAL — chama para uma conta e intervalo
    // =========================================================
    public function syncAccount(array $account, string $dateFrom, string $dateTo): array
    {
        // Descriptografa o token antes de usar na API Meta
        $token     = TokenCrypto::decrypt($account['access_token']);
        $accountId = $account['account_id'];  // ex: "act_1234567890"
        $dbAccId   = (int)$account['id'];

        // Prefixo "act_" obrigatório na API Meta
        if (!str_starts_with($accountId, 'act_')) {
            $accountId = 'act_' . $accountId;
        }

        $log = ['account' => $accountId, 'from' => $dateFrom, 'to' => $dateTo, 'rows' => 0, 'errors' => []];

        // ── Campos que vamos pedir à API ─────────────────────
        $fields = implode(',', [
            // Básicos
            'campaign_id',
            'campaign_name',
            'date_start',
            'impressions',
            'reach',
            'spend',
            'frequency',

            // Cliques
            'clicks',                         // {cliques} — link clicks
            'inline_link_clicks',             // cliques no link (mais preciso)
            'unique_clicks',                  // clicks_all base

            // CTR / CPM / CPC calculados pela API (validação)
            'ctr',
            'cpm',
            'cpc',

            // Conversões / actions
            'actions',                        // array: leads, purchase, etc.
            'action_values',                  // receita por tipo de ação
            'cost_per_action_type',           // custo por ação

            // Engajamento com post
            'post_engagement',
            'post_reactions',
            'post_saves',
            'comments',

            // Visitas ao perfil do Instagram (campo correto — Marketing API v19+)
            'instagram_profile_visits',
            'page_engagement',

            // Vídeo
            'video_p25_watched_actions',
            'video_p50_watched_actions',
            'video_p75_watched_actions',
            'video_p95_watched_actions',
            'video_p100_watched_actions',
            'video_thruplay_watched_actions',
            'video_avg_time_watched_actions',

            // Criativos (por ad dentro de cada campanha)
            // Puxados em chamada separada — ver fetchCreatives()
        ]);

        // ── Parâmetros da requisição ──────────────────────────
        $params = [
            'fields'        => $fields,
            'level'         => 'campaign',          // agrupa por campanha
            'time_range'    => json_encode(['since' => $dateFrom, 'until' => $dateTo]),
            'time_increment'=> 1,                   // 1 linha por dia
            'limit'         => 500,
            'access_token'  => $token,
        ];

        // ── Paginação ─────────────────────────────────────────
        $url  = self::API_BASE . META_API_VERSION . '/' . $accountId . '/insights?' . http_build_query($params);
        $rows = [];

        do {
            $response = $this->apiGet($url);

            if (!empty($response['error'])) {
                $log['errors'][] = $response['error']['message'] ?? 'API error';
                break;
            }

            $data = $response['data'] ?? [];
            foreach ($data as $row) {
                $rows[] = $row;
            }

            // Próxima página
            $url = $response['paging']['next'] ?? null;

        } while ($url);

        // ── Salva no banco ────────────────────────────────────
        foreach ($rows as $row) {
            $this->upsertRow($dbAccId, $row);
            $log['rows']++;
        }

        return $log;
    }

    // =========================================================
    // UPSERT — insere ou atualiza uma linha no banco
    // =========================================================
    private function upsertRow(int $dbAccId, array $r): void
    {
        $campaignId   = $r['campaign_id']   ?? '';
        $campaignName = $r['campaign_name'] ?? '';
        $date         = $r['date_start']    ?? date('Y-m-d');

        // ── Extrações simples ─────────────────────────────────
        $impressions  = (int)($r['impressions'] ?? 0);
        $reach        = (int)($r['reach']        ?? 0);
        $spend        = (float)($r['spend']      ?? 0);
        $frequency    = (float)($r['frequency']  ?? ($impressions > 0 && $reach > 0 ? $impressions / $reach : 0));
        $clicks       = (int)($r['inline_link_clicks'] ?? $r['clicks'] ?? 0);
        $clicks_all   = (int)($r['clicks']       ?? 0);
        $ctr          = (float)($r['ctr']         ?? 0);
        $cpm          = (float)($r['cpm']         ?? 0);
        $cpc          = (float)($r['cpc']         ?? 0);

        // ── Engajamento com post ──────────────────────────────
        $post_engagement = (int)($r['post_engagement'] ?? 0);
        $post_reactions  = $this->extractAction($r, 'post_reaction');
        $post_saves      = $this->extractAction($r, 'post_save');
        $post_comments   = $this->extractAction($r, 'comment');
        $page_engagement = (int)($r['page_engagement'] ?? 0);

        // ── Visitas ao perfil do Instagram ───────────────────
        // Campo correto para Marketing API v19+: instagram_profile_visits
        $profile_visits = (int)($r['instagram_profile_visits'] ?? 0);
        // Fallback: tenta via actions[] caso o campo direto venha vazio
        if ($profile_visits === 0) {
            $profile_visits = $this->extractAction($r, 'ig_profile_visit')
                            + $this->extractAction($r, 'profile_visit');
        }

        // ── Conversões via actions[] ──────────────────────────
        // Prioridade: leadgen_grouped = formulário nativo (valor exibido no painel Meta)
        // Fallback: lead genérico. NÃO somar os dois — causa dupla contagem.
        $leads_fb     = $this->extractAction($r, 'leadgen_grouped')
                      + $this->extractAction($r, 'onsite_conversion.lead_grouped');
        $leads_gn     = $this->extractAction($r, 'lead');
        $leads        = $leads_fb ?: $leads_gn;
        $all_leads    = $leads_fb + $leads_gn; // total completo para referência
        $purchases    = $this->extractAction($r, 'purchase')
                      + $this->extractAction($r, 'omni_purchase');
        $downloads    = $this->extractAction($r, 'app_custom_event.fb_mobile_complete_registration')
                      + $this->extractAction($r, 'mobile_app_install');
        // messaging_conversation_started_7d = "Resultado" do painel da Meta para campanhas de mensagem
        // NÃO somar com first_reply — causa dupla contagem
        $messages = $this->extractAction($r, 'onsite_conversion.messaging_conversation_started_7d');
        if ($messages === 0) {
            $messages = $this->extractAction($r, 'onsite_conversion.messaging_first_reply');
        }
        if ($messages === 0) {
            $messages = $this->extractAction($r, 'onsite_conversion.total_messaging_connection');
        }
        $app_installs = $this->extractAction($r, 'app_install')
                      + $this->extractAction($r, 'mobile_app_install');
        $conversions  = (int)($r['conversions'] ?? $purchases ?: $leads);

        // ── Receita (action_values) ───────────────────────────
        $revenue      = $this->extractActionValue($r, 'purchase')
                      + $this->extractActionValue($r, 'omni_purchase');

        // ── ROAS ──────────────────────────────────────────────
        $roas = $spend > 0 && $revenue > 0 ? round($revenue / $spend, 2) : 0;

        // ── Resultados (depende do objetivo — usa maior conversão)
        $results = max($leads, $purchases, $conversions);

        // ── Faturado (billed_amount) ──────────────────────────
        $billed_amount = $spend; // Meta cobra igual ao spend; substitua se tiver campo separado

        // ── Vídeo ─────────────────────────────────────────────
        $video_p25   = $this->extractVideoAction($r, 'video_p25_watched_actions');
        $video_p50   = $this->extractVideoAction($r, 'video_p50_watched_actions');
        $video_p75   = $this->extractVideoAction($r, 'video_p75_watched_actions');
        $video_p95   = $this->extractVideoAction($r, 'video_p95_watched_actions');
        $video_p100  = $this->extractVideoAction($r, 'video_p100_watched_actions');
        $thruplay    = $this->extractVideoAction($r, 'video_thruplay_watched_actions');
        $video_avg   = $this->extractVideoAvg($r);

        // ── Criativos (JSON) ──────────────────────────────────
        // Já puxados e salvos separadamente se necessário;
        // aqui setamos null e atualizamos depois via fetchCreatives()
        $creatives_json = null;

        // ── UPSERT no banco ───────────────────────────────────
        $sql = "
            INSERT INTO campaign_metrics (
                ad_account_id, campaign_id, campaign_name, platform, date,
                impressions, reach, spend, frequency,
                clicks, clicks_all, inline_clicks, ctr, cpm, cpc,
                conversions, leads, all_leads, results,
                purchases, downloads, messages, app_installs,
                engagement, post_comments, post_reactions, post_saves, page_engagement,
                profile_visits,
                revenue, roas, billed_amount,
                video_p25, video_p50, video_p75, video_p95, video_p100,
                thruplay, video_avg_time,
                creatives_json,
                synced_at
            ) VALUES (
                ?, ?, ?, 'meta', ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                campaign_name   = VALUES(campaign_name),
                impressions     = VALUES(impressions),
                reach           = VALUES(reach),
                spend           = VALUES(spend),
                frequency       = VALUES(frequency),
                clicks          = VALUES(clicks),
                clicks_all      = VALUES(clicks_all),
                inline_clicks   = VALUES(inline_clicks),
                ctr             = VALUES(ctr),
                cpm             = VALUES(cpm),
                cpc             = VALUES(cpc),
                conversions     = VALUES(conversions),
                leads           = VALUES(leads),
                all_leads       = VALUES(all_leads),
                results         = VALUES(results),
                purchases       = VALUES(purchases),
                downloads       = VALUES(downloads),
                messages        = VALUES(messages),
                app_installs    = VALUES(app_installs),
                engagement      = VALUES(engagement),
                post_comments   = VALUES(post_comments),
                post_reactions  = VALUES(post_reactions),
                post_saves      = VALUES(post_saves),
                page_engagement = VALUES(page_engagement),
                profile_visits  = VALUES(profile_visits),
                revenue         = VALUES(revenue),
                roas            = VALUES(roas),
                billed_amount   = VALUES(billed_amount),
                video_p25       = VALUES(video_p25),
                video_p50       = VALUES(video_p50),
                video_p75       = VALUES(video_p75),
                video_p95       = VALUES(video_p95),
                video_p100      = VALUES(video_p100),
                thruplay        = VALUES(thruplay),
                video_avg_time  = VALUES(video_avg_time),
                synced_at       = NOW()
        ";

        $params = [
            $dbAccId, $campaignId, $campaignName, $date,
            $impressions, $reach, $spend, $frequency,
            $clicks, $clicks_all, $clicks, $ctr, $cpm, $cpc,
            $conversions, $leads, $all_leads, $results,
            $purchases, $downloads, $messages, $app_installs,
            $post_engagement, $post_comments, $post_reactions, $post_saves, $page_engagement,
            $profile_visits,
            $revenue, $roas, $billed_amount,
            $video_p25, $video_p50, $video_p75, $video_p95, $video_p100,
            $thruplay, $video_avg,
            $creatives_json,
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    // =========================================================
    // CRIATIVOS — busca top criativos por campanha e salva JSON
    // =========================================================
    public function syncCreatives(array $account, string $campaignId, string $dateFrom, string $dateTo): void
    {
        // Descriptografa o token antes de usar na API Meta
        $token     = TokenCrypto::decrypt($account['access_token']);
        $dbAccId   = (int)$account['id'];

        $fields = 'ad_id,ad_name,impressions,clicks,spend,actions';
        $url    = self::API_BASE . META_API_VERSION . '/' . $campaignId . '/insights?'
                . http_build_query([
                    'fields'        => $fields,
                    'level'         => 'ad',
                    'time_range'    => json_encode(['since' => $dateFrom, 'until' => $dateTo]),
                    'limit'         => 100,
                    'access_token'  => $token,
                ]);

        $response = $this->apiGet($url);
        $ads      = $response['data'] ?? [];

        if (empty($ads)) return;

        // Ordena por spend desc
        usort($ads, fn($a, $b) => (float)($b['spend'] ?? 0) <=> (float)($a['spend'] ?? 0));

        $creatives = array_map(fn($ad) => [
            'ad_id'       => $ad['ad_id']   ?? '',
            'name'        => $ad['ad_name'] ?? '',
            'spend'       => (float)($ad['spend']       ?? 0),
            'clicks'      => (int)($ad['clicks']        ?? 0),
            'impressions' => (int)($ad['impressions']   ?? 0),
        ], $ads);

        $json = json_encode($creatives, JSON_UNESCAPED_UNICODE);

        // Atualiza TODOS os dias do intervalo para esta campanha
        $this->db->prepare("
            UPDATE campaign_metrics
            SET creatives_json = ?, synced_at = NOW()
            WHERE ad_account_id = ?
              AND campaign_id   = ?
              AND `date` BETWEEN ? AND ?
        ")->execute([$json, $dbAccId, $campaignId, $dateFrom, $dateTo]);
    }

    // =========================================================
    // HELPERS — extração de valores dos arrays da API Meta
    // =========================================================

    /** Extrai valor inteiro de actions[] por tipo */
    private function extractAction(array $row, string $actionType): int
    {
        foreach ($row['actions'] ?? [] as $a) {
            if (($a['action_type'] ?? '') === $actionType) {
                return (int)($a['value'] ?? 0);
            }
        }
        return 0;
    }

    /** Extrai valor monetário de action_values[] por tipo */
    private function extractActionValue(array $row, string $actionType): float
    {
        foreach ($row['action_values'] ?? [] as $a) {
            if (($a['action_type'] ?? '') === $actionType) {
                return (float)($a['value'] ?? 0);
            }
        }
        return 0.0;
    }

    /** Extrai total de visualizações de vídeo (o campo é um array de objetos) */
    private function extractVideoAction(array $row, string $fieldName): int
    {
        $arr = $row[$fieldName] ?? [];
        $total = 0;
        foreach ($arr as $item) {
            $total += (int)($item['value'] ?? 0);
        }
        return $total;
    }

    /** Extrai tempo médio assistido (segundos) */
    private function extractVideoAvg(array $row): float
    {
        $arr = $row['video_avg_time_watched_actions'] ?? [];
        foreach ($arr as $item) {
            return (float)($item['value'] ?? 0);
        }
        return 0.0;
    }

    // =========================================================
    // HTTP GET com curl
    // =========================================================
    private function apiGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'GestorPro/1.0',
        ]);
        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || !$body) {
            return ['error' => ['message' => 'cURL error ' . $errno]];
        }

        return json_decode($body, true) ?? [];
    }
}
