<?php
/**
 * BudgetController
 *
 * Saldo e ritmo diário lidos do CACHE em ad_accounts (prepago_balance / prepago_ritmo_dia).
 * O cron cron/sync_prepago.php atualiza esse cache a cada 30 minutos, eliminando
 * chamadas de API em tempo real durante o carregamento do dashboard.
 *
 * Execute a migration sql/migration_prepago_cache.sql uma única vez para adicionar
 * as colunas de cache à tabela ad_accounts.
 */
class BudgetController {

    /**
     * Retorna o widget Saldo Pré-pago lendo exclusivamente do banco local.
     * Zero chamadas de API — dados vêm do cache atualizado pelo cron.
     */
    public static function getPrepagoWidget(int $userId): array {
        $db    = Database::getInstance();
        $today = date('Y-m-d');

        $clientes = $db->query(
            "SELECT c.id, c.name, c.saldo_alerta, c.budget_mensal,
                    aa.id             AS aa_id,
                    aa.account_name,
                    aa.prepago_balance    AS saldo_atual,
                    aa.prepago_ritmo_dia  AS ritmo_dia,
                    aa.prepago_synced_at  AS synced_at
               FROM clients c
               JOIN ad_accounts aa ON aa.client_id = c.id AND aa.user_id = c.user_id
              WHERE c.user_id = ? AND c.payment_type = 'prepago'
                AND c.status  = 'active' AND aa.status = 'active'
                AND aa.platform = 'meta'
              ORDER BY c.name",
            [$userId]
        )->fetchAll();

        if (empty($clientes)) return [];

        $resultado = [];

        foreach ($clientes as $cl) {
            $saldoAtual = (float)($cl['saldo_atual'] ?? 0);
            $ritmodia   = (float)($cl['ritmo_dia']   ?? 0);

            // Detecta pausa pelo último dia com gasto no banco
            $diasPausada    = 0;
            $estaPausada    = false;
            $ultimoDiaGasto = null;
            try {
                $ultimoDiaGasto = $db->query(
                    "SELECT MAX(date) FROM campaign_metrics WHERE ad_account_id=? AND spend > 0",
                    [$cl['aa_id']]
                )->fetchColumn();
                if ($ultimoDiaGasto) {
                    $diasPausada = max(0, (int)((strtotime($today) - strtotime($ultimoDiaGasto)) / 86400));
                    $estaPausada = $diasPausada >= 1;
                }
            } catch (\Throwable $e) {}

            // Fallback de ritmo: só usa se não há dado no cache E não está pausada
            if ($ritmodia <= 0 && !$estaPausada) {
                try {
                    $mesStart      = date('Y-m-01');
                    $diasCorridos  = max(1, (int)date('j'));
                    $gastoMes      = (float)$db->query(
                        "SELECT COALESCE(SUM(spend),0) FROM campaign_metrics WHERE ad_account_id=? AND date>=?",
                        [$cl['aa_id'], $mesStart]
                    )->fetchColumn();
                    $ritmodia = $gastoMes > 0 ? round($gastoMes / $diasCorridos, 2) : 0;
                } catch (\Throwable $e) {}
            }

            // Dias restantes e data de encerramento
            $diasRestantes    = null;
            $dataEncerramento = null;
            if ($ritmodia > 0 && $saldoAtual > 0 && !$estaPausada) {
                $diasRestantes    = (int)floor($saldoAtual / $ritmodia);
                $dataEncerramento = date('d/m/Y', strtotime("+{$diasRestantes} days"));
            }

            // Última recarga manual registrada
            $ultimaRecarga = $db->query(
                "SELECT valor, tipo, created_at FROM client_recharges
                  WHERE client_id=? AND user_id=? ORDER BY created_at DESC LIMIT 1",
                [$cl['id'], $userId]
            )->fetch();

            $emAlerta  = $saldoAtual > 0 && $saldoAtual <= (float)($cl['saldo_alerta'] ?? 50);
            $semSaldo  = $saldoAtual <= 0;

            // Informa ao frontend se o cache está desatualizado (>1h)
            $cacheAntigo = false;
            if ($cl['synced_at']) {
                $cacheAntigo = (time() - strtotime($cl['synced_at'])) > 3600;
            }

            $resultado[] = [
                'client_id'         => $cl['id'],
                'client_name'       => $cl['name'],
                'account_name'      => $cl['account_name'],
                'saldo_atual'       => round($saldoAtual, 2),
                'saldo_fonte_meta'  => true,   // cache veio da API Meta via cron
                'saldo_synced_at'   => $cl['synced_at'],
                'cache_antigo'      => $cacheAntigo,
                'gasto_mes_atual'   => 0,
                'ritmo_dia'         => $estaPausada ? 0.0 : round($ritmodia, 2),
                'dias_ativos'       => 0,
                'esta_pausada'      => $estaPausada,
                'dias_pausada'      => $diasPausada,
                'dias_restantes'    => $diasRestantes,
                'data_encerramento' => $dataEncerramento,
                'ultima_recarga'    => $ultimaRecarga,
                'em_alerta'         => $emAlerta,
                'sem_saldo'         => $semSaldo,
                'saldo_alerta'      => (float)($cl['saldo_alerta'] ?? 50),
            ];
        }
        // Ordenar por prioridade:
        // 1. Sem saldo (crítico) primeiro
        // 2. Em alerta (saldo baixo) antes dos normais
        // 3. Dentro de cada grupo: dias restantes crescente (quem acaba antes aparece primeiro)
        usort($resultado, function($a, $b) {
            $prioA = $a['sem_saldo'] ? 0 : ($a['em_alerta'] ? 1 : 2);
            $prioB = $b['sem_saldo'] ? 0 : ($b['em_alerta'] ? 1 : 2);
            if ($prioA !== $prioB) return $prioA - $prioB;
            // Mesma prioridade: dias restantes crescente (null = vai ao final)
            $dA = $a['dias_restantes'] ?? 9999;
            $dB = $b['dias_restantes'] ?? 9999;
            return $dA - $dB;
        });

        return $resultado;
    }

    public function list(): void {
        requireAuth();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'data'=>self::getPrepagoWidget(currentUser()['id'])]);
    }

    public function addRecharge(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid=$uid=currentUser()['id']; $db=Database::getInstance();
        $clientId=(int)($_POST['client_id']??0);
        $valor=(float)($_POST['valor']??0);
        $tipo=sanitize($_POST['tipo']??'pix');
        $descricao=sanitize($_POST['descricao']??'');
        if (!$clientId||$valor<=0){echo json_encode(['success'=>false,'error'=>'Dados inválidos']);return;}
        $client=$db->query("SELECT id,name FROM clients WHERE id=? AND user_id=? AND payment_type='prepago'",[$clientId,$uid])->fetch();
        if (!$client){echo json_encode(['success'=>false,'error'=>'Cliente não encontrado']);return;}
        $db->query("INSERT INTO client_recharges (user_id,client_id,valor,tipo,descricao,saldo_antes,saldo_apos) VALUES (?,?,?,?,?,0,0)",
            [$uid,$clientId,$valor,$tipo,$descricao]);
        try {
            $db->query("INSERT INTO notifications (user_id,type,title,body) VALUES (?,'success',?,?)",
                [$uid,'💰 Recarga — '.$client['name'],'R$ '.number_format($valor,2,',','.').' via '.strtoupper($tipo).($descricao?' — '.$descricao:'')]);
        } catch(\Throwable $e){}
        echo json_encode(['success'=>true,'message'=>'Recarga de R$ '.number_format($valor,2,',','.').' registrada!']);
    }

    public function history(): void {
        requireAuth(); header('Content-Type: application/json');
        $uid=currentUser()['id']; $db=Database::getInstance();
        $clientId=(int)($_GET['client_id']??0);
        $rows=$db->query("SELECT r.*,c.name AS client_name FROM client_recharges r JOIN clients c ON c.id=r.client_id WHERE r.client_id=? AND r.user_id=? ORDER BY r.created_at DESC LIMIT 50",[$clientId,$uid])->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows]);
    }

    public function setPaymentType(): void {
        requireAuth(); csrfCheck(); header('Content-Type: application/json');
        $uid=currentUser()['id']; $db=Database::getInstance();
        $clientId=(int)($_POST['client_id']??0);
        $type=sanitize($_POST['payment_type']??'cartao');
        $alerta=(float)($_POST['saldo_alerta']??50);
        if (!in_array($type,['prepago','cartao'])){echo json_encode(['success'=>false,'error'=>'Tipo inválido']);return;}
        $db->query("UPDATE clients SET payment_type=?,saldo_alerta=? WHERE id=? AND user_id=?",[$type,$alerta,$clientId,$uid]);
        echo json_encode(['success'=>true]);
    }
}
