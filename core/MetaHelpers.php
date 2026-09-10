<?php
/**
 * MetaHelpers.php — Funções compartilhadas de verificação da API Meta
 * Usado pelo cron/run-alerts.php e controllers/AlertController.php
 */

if (!function_exists('verificarErroMeta')) {

/**
 * Verifica erros na conta Meta e em anuncios ativos com problemas.
 * Retorna array de strings descrevendo cada erro encontrado (vazio = sem erros).
 */
function verificarErroMeta(string $accountId, string $token): array {
    if (!$accountId || !$token) return [];
    $erros = [];

    // ── 1. Erros da conta ────────────────────────────────────────────────────
    $url = "https://graph.facebook.com/" . META_API_VERSION
         . "/act_{$accountId}?fields=account_status,disable_reason,spend_cap,amount_spent&access_token=" . urlencode($token);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if ($res) {
        $statusMap = [
            2   => 'Conta desativada',
            3   => 'Pagamento recusado ou sem metodo de pagamento valido',
            7   => 'Conta suspensa por violacao de politica',
            9   => 'Conta encerrada',
            100 => 'Conta pendente de revisao',
            101 => 'Conta desativada por inatividade',
            201 => 'Conta comprometida',
        ];
        $disableMap = [
            1 => 'Pagamento recusado — verifique o cartao de credito cadastrado',
            2 => 'Conta encerrada pelo usuario',
            3 => 'Conta desativada por violacao de politica',
            4 => 'Conta desativada por fraude',
            5 => 'Conta desativada por acesso nao autorizado',
            6 => 'Conta desativada por baixa qualidade',
            7 => 'Conta desativada por falta de pagamento',
        ];

        $status = isset($res['account_status']) ? (int)$res['account_status'] : 1;
        if ($status !== 1) {
            $erros[] = $statusMap[$status] ?? "Conta com status anormal (codigo $status)";
        }

        $disReason = isset($res['disable_reason']) ? (int)$res['disable_reason'] : 0;
        if ($disReason !== 0) {
            $txt = $disableMap[$disReason] ?? "Conta desativada (motivo $disReason)";
            if (!in_array($txt, $erros)) $erros[] = $txt;
        }

        if (!empty($res['spend_cap']) && (int)$res['spend_cap'] > 0 && isset($res['amount_spent'])) {
            if ((float)$res['amount_spent'] >= (float)$res['spend_cap']) {
                $erros[] = 'Limite de gastos da conta atingido';
            }
        }
    }

    // ── 2. Anuncios ATIVOS com erros de veiculacao ───────────────────────────
    // Busca apenas anuncios com WITH_ISSUES cuja campanha pai esteja ATIVA
    $adsUrl = "https://graph.facebook.com/" . META_API_VERSION
            . "/act_{$accountId}/ads"
            . '?fields=name,effective_status,issues_info,campaign{name,effective_status},adset{effective_status}'
            . '&effective_status=["WITH_ISSUES"]'
            . '&limit=50'
            . "&access_token=" . urlencode($token);
    $ch2 = curl_init($adsUrl);
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
    $adsRes = json_decode(curl_exec($ch2), true);
    curl_close($ch2);

    if (!empty($adsRes['data'])) {
        $errosVistos = []; // evitar duplicatas
        foreach ($adsRes['data'] as $ad) {
            if (empty($ad['issues_info'])) continue;

            // Ignorar se campanha ou adset pai estiver pausado
            $campStatus  = $ad['campaign']['effective_status'] ?? '';
            $adsetStatus = $ad['adset']['effective_status'] ?? '';
            if (in_array($campStatus,  ['PAUSED','ARCHIVED','DELETED'])) continue;
            if (in_array($adsetStatus, ['PAUSED','ARCHIVED','DELETED'])) continue;

            $adName   = $ad['name'] ?? 'Anuncio sem nome';
            $campName = $ad['campaign']['name'] ?? '';

            foreach ($ad['issues_info'] as $issue) {
                $msg   = $issue['error_message'] ?? ($issue['title'] ?? 'Erro desconhecido');
                // Chave única para evitar duplicatas
                $chave = md5($adName . '|' . $campName . '|' . $msg);
                if (isset($errosVistos[$chave])) continue;
                $errosVistos[$chave] = true;

                $linha = 'Anuncio "' . $adName . '"';
                if ($campName) $linha .= ' (campanha: ' . $campName . ')';
                $linha .= ': ' . $msg;
                $erros[] = $linha;
            }
        }
    }

    return $erros;
}

} // end if (!function_exists)
