<?php
/**
 * CronController — Endpoints de cron acessíveis via HTTP com autenticação por key.
 * Rotas:
 *   GET /api/cron/sync-groups?key={CRON_SECRET}
 *   GET /api/cron/sync-prepago?key={CRON_SECRET}
 */
class CronController
{
    // =========================================================================
    // SYNC GROUPS — Sincroniza cache de grupos WhatsApp
    // =========================================================================
    public function syncGroups(): void
    {
        // Autenticacao
        $key = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
        if ($key !== CRON_SECRET) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // Responde imediatamente e continua em background
        ignore_user_abort(true);
        set_time_limit(0);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Processando em background...'], JSON_UNESCAPED_UNICODE);
        if (ob_get_level() > 0) ob_end_flush();
        flush();

        // Processamento em background
        require_once __DIR__.'/../core/EvolutionApi.php';

        $db        = Database::getInstance();
        $instances = $db->query(
            "SELECT id, user_id, instance_name FROM whatsapp_instances WHERE status = 'connected'"
        )->fetchAll();

        if (empty($instances)) {
            error_log('[GestorAds][syncGroups] Nenhuma instancia conectada.');
            return;
        }

        foreach ($instances as $inst) {
            $wpId  = (int)$inst['id'];
            $uid   = (int)$inst['user_id'];
            $iname = $inst['instance_name'];

            try {
                $raw = EvolutionApi::fetchGroups($iname);

                if (!is_array($raw) || empty($raw)) {
                    error_log("[GestorAds][syncGroups][{$iname}] Sem grupos retornados.");
                    continue;
                }

                $apiGroups = [];
                foreach ($raw as $g) {
                    if (!is_array($g)) continue;
                    $gid   = $g['id'] ?? $g['remoteJid'] ?? '';
                    $gname = $g['subject'] ?? $g['name'] ?? $gid;
                    if ($gid) $apiGroups[$gid] = $gname;
                }

                if (empty($apiGroups)) {
                    error_log("[GestorAds][syncGroups][{$iname}] Nenhum grupo valido.");
                    continue;
                }

                $cached    = $db->query(
                    "SELECT group_id, group_name FROM whatsapp_groups_cache WHERE whatsapp_id = ?",
                    [$wpId]
                )->fetchAll(PDO::FETCH_KEY_PAIR);

                $cachedIds = array_keys($cached);
                $apiIds    = array_keys($apiGroups);
                $inseridos = 0; $renomeados = 0; $removidos = 0;

                foreach (array_diff($apiIds, $cachedIds) as $gid) {
                    $db->query(
                        "INSERT INTO whatsapp_groups_cache (user_id, whatsapp_id, group_id, group_name)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE group_name = VALUES(group_name), synced_at = NOW()",
                        [$uid, $wpId, $gid, $apiGroups[$gid]]
                    );
                    $inseridos++;
                }

                foreach ($cached as $gid => $oldName) {
                    if (isset($apiGroups[$gid]) && $apiGroups[$gid] !== $oldName) {
                        $db->query(
                            "UPDATE whatsapp_groups_cache SET group_name = ?, synced_at = NOW()
                             WHERE whatsapp_id = ? AND group_id = ?",
                            [$apiGroups[$gid], $wpId, $gid]
                        );
                        $renomeados++;
                    }
                }

                foreach (array_diff($cachedIds, $apiIds) as $gid) {
                    $db->query(
                        "DELETE FROM whatsapp_groups_cache WHERE whatsapp_id = ? AND group_id = ?",
                        [$wpId, $gid]
                    );
                    $removidos++;
                }

                error_log("[GestorAds][syncGroups][{$iname}] OK: +{$inseridos} novos, ~{$renomeados} renomeados, -{$removidos} removidos (total: " . count($apiIds) . ")");

            } catch (Throwable $e) {
                error_log("[GestorAds][syncGroups][{$iname}] ERRO: " . $e->getMessage());
            }
        }

        error_log('[GestorAds][syncGroups] FIM.');
    }

    // =========================================================================
    // SYNC PREPAGO — Sincroniza saldo/ritmo de contas pre-pago Meta Ads
    // =========================================================================
    public function syncPrepago(): void
    {
        ini_set('max_execution_time', 120);
        header('Content-Type: application/json; charset=utf-8');

        $key = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
        if ($key !== CRON_SECRET) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $db  = Database::getInstance();
        $log = [];
        $ok  = 0;
        $err = 0;

        $log[] = '[' . date('Y-m-d H:i:s') . '] Iniciando sync_prepago...';

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

        $log[] = 'Contas encontradas: ' . count($contas);

        foreach ($contas as $acc) {
            try {
                $balance = $this->fetchBalance($acc['account_id'], $acc['access_token']);
                $ritmo   = $this->fetchRitmoDia($acc['account_id'], $acc['access_token'], (int)$acc['id'], $db);

                $db->query(
                    "UPDATE ad_accounts SET prepago_balance=?, prepago_ritmo_dia=?, prepago_synced_at=NOW() WHERE id=?",
                    [$balance, $ritmo, $acc['id']]
                );

                $log[] = "[OK] #{$acc['id']} {$acc['account_name']} | saldo=R\${$balance} ritmo=R\${$ritmo}/dia";
                $ok++;
            } catch (Throwable $e) {
                $log[] = "[ERRO] #{$acc['id']} {$acc['account_name']}: " . $e->getMessage();
                error_log('[CronController::syncPrepago] ' . $e->getMessage());
                $err++;
            }
        }

        $log[] = '[' . date('Y-m-d H:i:s') . "] Concluido. OK: $ok | Erros: $err";

        // Invalida cache do dashboard de todos os usuários que tiveram contas atualizadas
        if ($ok > 0) {
            try {
                $db->query("DELETE FROM dashboard_cache WHERE cache_key LIKE 'widget_metrics_%'");
            } catch (Throwable $e) {}
        }

        echo json_encode([
            'success' => true,
            'ok'      => $ok,
            'erros'   => $err,
            'log'     => $log,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================
    private function fetchBalance(string $accountId, string $token): float
    {
        $url  = "https://graph.facebook.com/" . META_API_VERSION . "/act_{$accountId}"
              . "?fields=balance,spend_cap,amount_spent&access_token=" . urlencode($token);
        $data = $this->curlGet($url);

        if (!empty($data['error'])) {
            throw new RuntimeException("Meta API: " . ($data['error']['message'] ?? 'erro'));
        }

        if (!empty($data['spend_cap']) && (int)$data['spend_cap'] > 0 && isset($data['amount_spent'])) {
            return max(0, round(((float)$data['spend_cap'] - (float)$data['amount_spent']) / 100, 2));
        }

        return isset($data['balance']) ? round((float)$data['balance'] / 100, 2) : 0.0;
    }

    private function fetchRitmoDia(string $accountId, string $token, int $dbId, Database $db): float
    {
        $v = META_API_VERSION;

        $camps = $this->curlGet(
            "https://graph.facebook.com/{$v}/act_{$accountId}/campaigns"
            . "?fields=daily_budget,effective_status&effective_status=[\"ACTIVE\"]&limit=100"
            . "&access_token=" . urlencode($token)
        );
        $total = 0;
        foreach (($camps['data'] ?? []) as $c) {
            if (!empty($c['daily_budget'])) $total += (float)$c['daily_budget'];
        }
        if ($total > 0) return round($total / 100, 2);

        $adsets = $this->curlGet(
            "https://graph.facebook.com/{$v}/act_{$accountId}/adsets"
            . "?fields=daily_budget,effective_status&effective_status=[\"ACTIVE\"]&limit=100"
            . "&access_token=" . urlencode($token)
        );
        $total = 0;
        foreach (($adsets['data'] ?? []) as $a) {
            if (!empty($a['daily_budget'])) $total += (float)$a['daily_budget'];
        }
        if ($total > 0) return round($total / 100, 2);

        try {
            $ultimoDia = (string)$db->query(
                "SELECT MAX(date) FROM campaign_metrics WHERE ad_account_id=? AND spend > 0",
                [$dbId]
            )->fetchColumn();
            if ($ultimoDia && (int)floor((time() - strtotime($ultimoDia)) / 86400) >= 1) {
                return 0.0;
            }
        } catch (Throwable $e) {}

        $gasto = (float)$db->query(
            "SELECT COALESCE(SUM(spend),0) FROM campaign_metrics WHERE ad_account_id=? AND date>=?",
            [$dbId, date('Y-m-01')]
        )->fetchColumn();

        return $gasto > 0 ? round($gasto / max(1, (int)date('j')), 2) : 0.0;
    }

    private function curlGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'GestorAds/1.0',
        ]);
        $raw  = curl_exec($ch);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException("cURL: $cerr");
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new RuntimeException("Resposta invalida da API Meta");
        return $data;
    }
}
