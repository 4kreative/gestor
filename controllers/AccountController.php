<?php
// Garante que TokenCrypto esteja disponível mesmo quando este arquivo
// é carregado diretamente por scripts cron (sem passar pelo App.php/autoload)
if (!class_exists('TokenCrypto')) {
    require_once __DIR__.'/../core/TokenCrypto.php';
}

class AccountController {
    public function index(): void {
        requireAuth();
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $accounts = $db->query(
            "SELECT aa.*, c.name AS client_name, c.company AS client_company FROM ad_accounts aa LEFT JOIN clients c ON aa.client_id=c.id WHERE aa.user_id=? ORDER BY aa.platform, aa.account_name",
            [$uid]
        )->fetchAll();
        $clients = $db->query(
            "SELECT id, name, company FROM clients WHERE user_id=? AND status='active' ORDER BY name",
            [$uid]
        )->fetchAll();
        require_once __DIR__.'/../views/accounts/index.php';
    }

    public function connectMeta(): void {
        requireAuth();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $redirectUri = APP_URL.'/api/oauth/meta-callback.php';
        $params = http_build_query([
            'client_id'     => META_APP_ID,
            'redirect_uri'  => $redirectUri,
            // Permissões ampliadas: inclui acesso a BM, contas client/owned e insights
            'scope'         => 'ads_read,ads_management,business_management,read_insights,pages_read_engagement,pages_show_list',
            'response_type' => 'code',
            'state'         => $_SESSION['oauth_state'],
            'auth_type'     => 'rerequest', // força re-aprovação de permissões se já autorizou antes
        ]);
        header('Location: https://www.facebook.com/'.META_API_VERSION.'/dialog/oauth?'.$params);
        exit;
    }

    public function connectMetaToken(): void {
        requireAuth(); csrfCheck();
        $uid   = currentUser()['id'];
        $db    = Database::getInstance();
        $token = trim($_POST['access_token'] ?? '');
        if (!$token) { flash('error','Token obrigatório.'); redirect('/accounts'); }

        $ch = curl_init("https://graph.facebook.com/".META_API_VERSION."/me/adaccounts?fields=id,name,account_status&limit=500&access_token=".urlencode($token));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>20]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($res, true);

        if ($code !== 200 || empty($data['data'])) {
            flash('error', 'Erro Meta API: '.($data['error']['message'] ?? 'Token inválido'));
            redirect('/accounts');
        }

        // Criptografa o token antes de salvar no banco
        $tokenEnc = TokenCrypto::encrypt($token);

        $inserted = 0; $updated = 0;
        foreach ($data['data'] as $acc) {
            $accId = str_replace('act_','', $acc['id']);
            $name  = $acc['name'];
            $exists = $db->query("SELECT id FROM ad_accounts WHERE user_id=? AND account_id=? AND platform='meta'",[$uid,$accId])->fetch();
            if (!$exists) {
                $db->query("INSERT INTO ad_accounts (user_id,account_id,account_name,platform,access_token,status) VALUES (?,?,?,'meta',?,'active')",[$uid,$accId,$name,$tokenEnc]);
                $inserted++;
            } else {
                $db->query("UPDATE ad_accounts SET access_token=?, account_name=?, status='active' WHERE user_id=? AND account_id=? AND platform='meta'",[$tokenEnc,$name,$uid,$accId]);
                $updated++;
            }
        }
        flash('success', "Meta Ads conectado! $inserted conta(s) nova(s), $updated atualizada(s).");
        redirect('/accounts');
    }

    public function connectGoogle(): void {
        requireAuth();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $params = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT,
            'scope'         => 'https://www.googleapis.com/auth/adwords',
            'response_type' => 'code',
            'access_type'   => 'offline',
            'state'         => $_SESSION['oauth_state'],
            'prompt'        => 'consent',
        ]);
        header('Location: https://accounts.google.com/o/oauth2/auth?'.$params);
        exit;
    }

    /**
     * Busca campanhas diretamente da Meta API com status real
     * Retorna todas as campanhas (ativas E pausadas) de uma conta
     */
    public static function fetchCampaignsMeta(string $accountId, string $token): array {
        if (!$accountId || !$token) return [];

        // effective_status retorna o status REAL (considera conta pai, verba, etc)
        // status = status configurado pelo usuário
        // Busca também spend_cap e budget_remaining para contas sem saldo
        // Busca campanhas — sem status_filter para pegar todas (ACTIVE + PAUSED + ARCHIVED)
        // effective_status é o status REAL considerando conta, budget, etc
        $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}/campaigns"
             . "?fields=id,name,status,effective_status,objective,stop_time,created_time,updated_time"
             . "&limit=500"
             . "&access_token=".urlencode($token);

        $campaigns = [];
        $nextUrl   = $url;

        // Percorre paginação
        for ($page = 0; $page < 10 && $nextUrl; $page++) {
            $ch = curl_init($nextUrl);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>15]);
            $res  = json_decode(curl_exec($ch), true);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || empty($res['data'])) break;

            foreach ($res['data'] as $c) {
                // Determina o status real com base em ambos os campos
                // status       = o que o usuário configurou (ACTIVE/PAUSED/ARCHIVED/DELETED)
                // effective_status = o que o Meta avalia (pode ser ACTIVE, PAUSED, WITH_ISSUES, etc.)
                $userStatus      = $c['status']           ?? 'UNKNOWN';
                $effectiveStatus = $c['effective_status'] ?? $userStatus;

                // Se o usuário pausou/arquivou/deletou, prevalece sobre o effective_status
                $finalStatus = $effectiveStatus;
                if (in_array($userStatus, ['PAUSED', 'ARCHIVED', 'DELETED']) && $effectiveStatus === 'ACTIVE') {
                    $finalStatus = $userStatus; // correção: usuário desativou mas effective ainda dizia ACTIVE
                }

                // Verifica se a campanha terminou (stop_time no passado)
                $isEnded = false;
                if (!empty($c['stop_time'])) {
                    $isEnded = strtotime($c['stop_time']) < time();
                    if ($isEnded && $finalStatus === 'ACTIVE') {
                        $finalStatus = 'ENDED';
                    }
                }

                $campaigns[] = [
                    'id'               => $c['id'],
                    'name'             => $c['name'],
                    'status'           => $userStatus,        // status configurado pelo usuário
                    'effective_status' => $finalStatus,       // status final corrigido
                    'objective'        => $c['objective'] ?? '',
                    'stop_time'        => $c['stop_time'] ?? null,
                    'active'           => ($finalStatus === 'ACTIVE'),
                ];
            }
            $nextUrl = $res['paging']['next'] ?? null;
        }

        // Ordena: ACTIVE primeiro, depois PAUSED, depois o resto; alfabético dentro de cada grupo
        $order = ['ACTIVE'=>0,'PAUSED'=>1,'PENDING_BILLING_INFO'=>2,'WITH_ISSUES'=>2,
                  'CAMPAIGN_PAUSED'=>3,'ADSET_PAUSED'=>3,'PENDING_REVIEW'=>4,
                  'ENDED'=>5,'ARCHIVED'=>6,'DELETED'=>7];
        // Remove APENAS campanhas de impulsionamento simples (publicações orgânicas)
        // ATENÇÃO: OUTCOME_ENGAGEMENT e POST_ENGAGEMENT foram removidos daqui —
        // campanhas de Mensagem/Conversa usam OUTCOME_ENGAGEMENT e devem aparecer normalmente
        $boostedObjectives = [
            'PAGE_LIKES', 'VIDEO_VIEWS',
            'BRAND_AWARENESS', 'EVENT_RESPONSES',
            'LOCAL_AWARENESS',
        ];
        $campaigns = array_values(array_filter($campaigns, function($c) use ($boostedObjectives) {
            return !in_array($c['objective'], $boostedObjectives);
        }));

        usort($campaigns, function($a, $b) use ($order) {
            $oa = $order[$a['effective_status']] ?? 9;
            $ob = $order[$b['effective_status']] ?? 9;
            if ($oa !== $ob) return $oa - $ob;
            return strcmp($a['name'], $b['name']);
        });

        return $campaigns;
    }

    /**
     * API endpoint: retorna campanhas + adsets de uma conta em JSON
     */
    public function apiCampaigns(): void {
        requireAuth();
        header('Content-Type: application/json');
        $uid   = currentUser()['id'];
        $accId = (int)($_GET['account_id'] ?? 0);
        $db    = Database::getInstance();

        $acc = $db->query("SELECT * FROM ad_accounts WHERE id=? AND user_id=?",[$accId,$uid])->fetch();
        if (!$acc) { echo json_encode(['campaigns'=>[]]); exit; }

        if ($acc['platform'] === 'meta') {
            $plainToken = TokenCrypto::decrypt($acc['access_token']);
            $campaigns = self::fetchCampaignsMeta($acc['account_id'], $plainToken);
            // Busca adsets agrupados por campanha
            $adsets    = self::fetchAdsetsMeta($acc['account_id'], $plainToken);
            // Agrupa adsets por campaign_id (string keys para garantir match)
            $adsetMap  = [];
            foreach ($adsets as $as) {
                $key = (string)$as['campaign_id'];
                $adsetMap[$key][] = $as;
            }
            foreach ($campaigns as &$camp) {
                $camp['adsets'] = $adsetMap[(string)$camp['id']] ?? [];
            }
            unset($camp);
        } else {
            $campaigns = [];
        }

        echo json_encode(['campaigns' => $campaigns]);
        exit;
    }

    /**
     * Busca todos os Conjuntos de Anúncios de uma conta
     */
    public static function fetchAdsetsMeta(string $accountId, string $token): array {
        if (!$accountId || !$token) return [];

        $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accountId}/adsets"
             . "?fields=id,name,status,effective_status,campaign_id,daily_budget,lifetime_budget"
             . "&limit=500&status_filter=all"
             . "&access_token=".urlencode($token);

        $adsets  = [];
        $nextUrl = $url;

        for ($page = 0; $page < 10 && $nextUrl; $page++) {
            $ch = curl_init($nextUrl);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>15]);
            $res  = json_decode(curl_exec($ch), true);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || empty($res['data'])) break;

            foreach ($res['data'] as $as) {
                $adsets[] = [
                    'id'               => $as['id'],
                    'name'             => $as['name'],
                    'campaign_id'      => $as['campaign_id'],
                    'status'           => $as['status'],
                    'effective_status' => $as['effective_status'],
                    'active'           => ($as['effective_status'] === 'ACTIVE'),
                ];
            }
            $nextUrl = $res['paging']['next'] ?? null;
        }
        return $adsets;
    }

    public function sync(): void {
        requireAuth(); csrfCheck();
        $uid   = currentUser()['id'];
        $id    = (int)($_POST['account_id'] ?? 0);
        $db    = Database::getInstance();
        $acc   = $db->query("SELECT * FROM ad_accounts WHERE id=? AND user_id=?",[$id,$uid])->fetch();
        if (!$acc) { flash('error','Conta não encontrada.'); redirect('/accounts'); }

        $start = date('Y-m-d', strtotime('-90 days'));
        $end   = date('Y-m-d');

        if ($acc['platform'] === 'meta') {
            $this->syncMeta($acc, $start, $end, $db);
        } else {
            $this->syncGoogle($acc, $start, $end, $db);
        }

        flash('success','Métricas sincronizadas!');
        redirect('/accounts');
    }

    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        Database::getInstance()->query("DELETE FROM ad_accounts WHERE id=? AND user_id=?",[(int)($_POST['id']??0),$uid]);
        flash('success','Conta removida.');
        redirect('/accounts');
    }

    public function deleteBulk(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $ids = array_map('intval', (array)($_POST['ids']??[]));
        if (empty($ids)) { redirect('/accounts'); return; }
        $db = Database::getInstance();
        $count = 0;
        foreach ($ids as $id) {
            if ($id <= 0) continue;
            $db->query("DELETE FROM ad_accounts WHERE id=? AND user_id=?", [$id, $uid]);
            $count++;
        }
        flash('success', "{$count} conta(s) removida(s) com sucesso.");
        redirect('/accounts');
    }

    // Alias público para o cron (evita uso de Reflection)
    public static function syncMetaPublic(array $acc, string $start, string $end, Database $db): void {
        (new self())->syncMeta($acc, $start, $end, $db);
    }
    public static function syncGooglePublic(array $acc, string $start, string $end, Database $db): void {
        (new self())->syncGoogle($acc, $start, $end, $db);
    }

    private function syncMeta(array $acc, string $start, string $end, Database $db): void {
        if (!$acc['access_token']) return;
        // Descriptografa o token antes de usar na API
        $acc['access_token'] = TokenCrypto::decrypt($acc['access_token']);
        try {
            require_once __DIR__.'/MetaSyncService.php';
            $svc    = new MetaSyncService($db);
            $result = $svc->syncAccount($acc, $start, $end);
            if (!empty($result['error'])) {
                flash('error', 'Erro ao sincronizar: '.$result['error']);
            }
        } catch (\Throwable $e) {
            // Fallback simples se MetaSyncService falhar
            $this->syncMetaSimple($acc, $start, $end, $db);
        }
    }

    private function syncMetaSimple(array $acc, string $start, string $end, Database $db): void {
        $token = $acc['access_token'];
        $accId = $acc['account_id'];
        $fields = 'campaign_id,campaign_name,impressions,clicks,spend,reach,date_start,cpm,cpc,ctr,frequency,actions,action_values,video_p25_watched_actions,video_p50_watched_actions,video_p75_watched_actions,video_p100_watched_actions,video_thruplay_watched_actions,video_avg_time_watched_actions';
        $timeRange = urlencode(json_encode(['since'=>$start,'until'=>$end]));
        $url = "https://graph.facebook.com/".META_API_VERSION."/act_{$accId}/insights"
             . "?fields={$fields}&level=campaign&time_range={$timeRange}&time_increment=1&limit=500"
             . "&access_token=".urlencode($token);

        $nextUrl = $url;
        $saved = 0;
        for ($p=0; $p<20 && $nextUrl; $p++) {
            $ch = curl_init($nextUrl);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>false]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);
            if (empty($res['data'])) break;
            foreach ($res['data'] as $row) {
                // Reset all metrics
                $msgs = 0; $leads = 0; $conv = 0; $revenue = 0;
                $purchases = 0; $engagement = 0; $profileVisits = 0;
                $postComments = 0; $postReactions = 0; $postSaves = 0;
                $pageEngagement = 0; $appInstalls = 0; $downloads = 0;
                $videoP25 = 0; $videoP50 = 0; $videoP75 = 0; $videoP100 = 0;
                $thruplay = 0; $videoAvgTime = 0;
                $clicksAll = 0; $inlineClicks = 0; $allLeads = 0;

                foreach ($row['actions'] ?? [] as $a) {
                    $t = $a['action_type']; $v = (int)$a['value'];
                    // Mensagens
                    if ($t === 'onsite_conversion.messaging_conversation_started_7d') $msgs = max($msgs, $v);
                    elseif ($t === 'click_to_whatsapp_all' && $msgs === 0)             $msgs = $v;
                    elseif ($t === 'onsite_conversion.messaging_first_reply' && $msgs === 0) $msgs = $v;
                    elseif ($t === 'onsite_conversion.total_messaging_connection' && $msgs === 0) $msgs = $v;
                    // Leads
                    // Prioridade leadgen — evita dupla contagem
                    if (in_array($t,['leadgen_grouped','onsite_conversion.lead_grouped'])) { $leads += $v; }
                    elseif ($t === 'lead' && $leads === 0) { $leads += $v; }
                    elseif ($t === 'offsite_conversion.fb_pixel_lead' && $leads === 0) { $leads += $v; }
                    // Conversões/Compras
                    if (in_array($t,['purchase','offsite_conversion.fb_pixel_purchase'])) { $conv += $v; $purchases += $v; }
                    if ($t === 'complete_registration') $conv += $v;
                    // Visitas ao perfil IG
                    if (in_array($t,['ig_profile_visit','profile_visit','instagram_profile_visit'])) $profileVisits += $v;
                    // Engajamento
                    if (in_array($t,['post_engagement','page_engagement'])) $engagement = max($engagement, $v);
                    if ($t === 'comment')        $postComments  += $v;
                    if (in_array($t,['post_reaction','like'])) $postReactions += $v;
                    if (in_array($t,['post_save','onsite_conversion.post_save'])) $postSaves += $v;
                    if ($t === 'page_engagement') $pageEngagement = max($pageEngagement, $v);
                    // App installs / downloads
                    if (in_array($t,['app_install','mobile_app_install'])) $appInstalls += $v;
                    if ($t === 'app_custom_event.fb_mobile_content_view') $downloads += $v;
                    // Clicks
                    if ($t === 'click')        $clicksAll    += $v;
                    if ($t === 'inline_link_click') $inlineClicks += $v;
                    // All leads
                    if (in_array($t,['lead','offsite_conversion.fb_pixel_lead','onsite_conversion.lead_grouped',
                                      'contact','schedule','find_location','submit_application'])) $allLeads += $v;
                }
                foreach ($row['action_values'] ?? [] as $a) {
                    if (in_array($a['action_type'],['purchase','offsite_conversion.fb_pixel_purchase'])) $revenue += (float)$a['value'];
                }
                // Vídeo
                foreach ($row['video_p25_watched_actions']  ?? [] as $a) $videoP25   += (int)$a['value'];
                foreach ($row['video_p50_watched_actions']  ?? [] as $a) $videoP50   += (int)$a['value'];
                foreach ($row['video_p75_watched_actions']  ?? [] as $a) $videoP75   += (int)$a['value'];
                foreach ($row['video_p100_watched_actions'] ?? [] as $a) $videoP100  += (int)$a['value'];
                foreach ($row['video_thruplay_watched_actions'] ?? [] as $a) $thruplay += (int)$a['value'];
                foreach ($row['video_avg_time_watched_actions'] ?? [] as $a) $videoAvgTime = max($videoAvgTime, (float)$a['value']);

                $reach = (int)($row['reach'] ?? 0);
                $impr  = (int)($row['impressions'] ?? 0);
                $freq  = ($reach > 0 && $impr > 0) ? round($impr/$reach, 2) : (float)($row['frequency'] ?? 0);
                $roas  = ($spend > 0 && $revenue > 0) ? round($revenue / (float)$row['spend'], 4) : 0;
                $db->query(
                    "INSERT INTO campaign_metrics
                        (ad_account_id,campaign_id,campaign_name,platform,date,
                         impressions,reach,spend,frequency,clicks,clicks_all,inline_clicks,
                         ctr,cpm,cpc,conversions,leads,all_leads,messages,purchases,
                         revenue,roas,engagement,post_comments,post_reactions,post_saves,
                         page_engagement,profile_visits,app_installs,downloads,
                         video_p25,video_p50,video_p75,video_p100,thruplay,video_avg_time,
                         synced_at)
                     VALUES (?,?,?,'meta',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE
                        impressions=VALUES(impressions),reach=VALUES(reach),spend=VALUES(spend),
                        frequency=VALUES(frequency),clicks=VALUES(clicks),clicks_all=VALUES(clicks_all),
                        inline_clicks=VALUES(inline_clicks),ctr=VALUES(ctr),cpm=VALUES(cpm),cpc=VALUES(cpc),
                        conversions=VALUES(conversions),leads=VALUES(leads),all_leads=VALUES(all_leads),
                        messages=VALUES(messages),purchases=VALUES(purchases),revenue=VALUES(revenue),roas=VALUES(roas),
                        engagement=VALUES(engagement),post_comments=VALUES(post_comments),
                        post_reactions=VALUES(post_reactions),post_saves=VALUES(post_saves),
                        page_engagement=VALUES(page_engagement),profile_visits=VALUES(profile_visits),
                        app_installs=VALUES(app_installs),downloads=VALUES(downloads),
                        video_p25=VALUES(video_p25),video_p50=VALUES(video_p50),
                        video_p75=VALUES(video_p75),video_p100=VALUES(video_p100),
                        thruplay=VALUES(thruplay),video_avg_time=VALUES(video_avg_time),
                        synced_at=NOW()",
                    [
                        $acc['id'],$row['campaign_id']??'',$row['campaign_name']??'',$row['date_start'],
                        $impr,$reach,(float)$row['spend'],$freq,
                        (int)$row['clicks'],$clicksAll,$inlineClicks,
                        (float)($row['ctr']??0),(float)($row['cpm']??0),(float)($row['cpc']??0),
                        $conv,$leads,$allLeads,$msgs,$purchases,
                        $revenue,$roas,$engagement,$postComments,$postReactions,$postSaves,
                        $pageEngagement,$profileVisits,$appInstalls,$downloads,
                        $videoP25,$videoP50,$videoP75,$videoP100,$thruplay,$videoAvgTime,
                    ]
                );
                $saved++;
            }
            $nextUrl = $res['paging']['next'] ?? null;
        }
    }

    private function syncGoogle(array $acc, string $start, string $end, Database $db): void {
        if (!$acc['access_token']) return;
        // Descriptografa o token antes de usar na API
        $acc['access_token'] = TokenCrypto::decrypt($acc['access_token']);
        $query = "SELECT campaign.id, campaign.name, metrics.impressions, metrics.clicks,
                         metrics.cost_micros, metrics.conversions, metrics.average_cpm,
                         metrics.average_cpc, metrics.ctr, segments.date
                  FROM campaign WHERE segments.date BETWEEN '$start' AND '$end'
                  AND campaign.status IN ('ENABLED','PAUSED')";
        $url = "https://googleads.googleapis.com/v18/customers/{$acc['account_id']}/googleAds:searchStream";
        $ch  = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['query'=>$query]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$acc['access_token'],'developer-token: '.GOOGLE_DEVELOPER_TOKEN,'Content-Type: application/json'],
            CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        foreach ($res ?? [] as $batch) {
            foreach ($batch['results'] ?? [] as $row) {
                $spend = ($row['metrics']['costMicros'] ?? 0) / 1_000_000;
                $db->query(
                    "INSERT INTO campaign_metrics (ad_account_id,campaign_id,campaign_name,platform,date,impressions,clicks,spend,cpm,cpc,ctr,conversions)
                     VALUES (?,?,?,'google',?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE impressions=VALUES(impressions),clicks=VALUES(clicks),spend=VALUES(spend),cpm=VALUES(cpm),ctr=VALUES(ctr)",
                    [$acc['id'],$row['campaign']['id'],$row['campaign']['name'],$row['segments']['date'],
                     (int)($row['metrics']['impressions']??0),(int)($row['metrics']['clicks']??0),
                     $spend,(float)($row['metrics']['averageCpm']??0)/1_000_000,
                     (float)($row['metrics']['averageCpc']??0)/1_000_000,
                     (float)($row['metrics']['ctr']??0),(float)($row['metrics']['conversions']??0)]
                );
            }
        }
    }

    // AJAX — vincula cliente a uma conta de anúncio
    public function linkClient(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid      = currentUser()['id'];
        $db       = Database::getInstance();
        $accId    = (int)($_POST['account_id'] ?? 0);
        $clientId = (int)($_POST['client_id']  ?? 0) ?: null;
        if (!$accId) { echo json_encode(['success'=>false,'error'=>'Conta inválida']); return; }
        $db->query(
            "UPDATE ad_accounts SET client_id=? WHERE id=? AND user_id=?",
            [$clientId, $accId, $uid]
        );
        $clientName = '';
        if ($clientId) {
            $c = $db->query("SELECT name, company FROM clients WHERE id=? AND user_id=?", [$clientId,$uid])->fetch();
            $clientName = $c['name'] ?? '';
            if (!empty($c['company'])) $clientName .= ' — ' . $c['company'];
        }
        echo json_encode(['success'=>true,'client_name'=>$clientName]);
    }
}
