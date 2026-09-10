<?php
class OAuthController {
    public function metaCallback(): void {
        requireAuth();
        $code  = sanitize($_GET['code']  ?? '');
        $state = sanitize($_GET['state'] ?? '');

        if (!$code || !hash_equals($_SESSION['oauth_state']??'', $state)) {
            flash('error','OAuth inválido.'); redirect('/accounts');
        }

        // Troca code por token
        $tokenRes = $this->metaToken($code);
        if (!isset($tokenRes['access_token'])) { flash('error','Erro ao obter token Meta.'); redirect('/accounts'); }

        $token = $tokenRes['access_token'];

        // Busca contas de anúncio
        $accsRes = json_decode(file_get_contents(
            "https://graph.facebook.com/".META_API_VERSION."/me/adaccounts?fields=id,name&access_token=$token"
        ), true);

        if (empty($accsRes['data'])) { flash('error','Nenhuma conta de anúncio encontrada.'); redirect('/accounts'); }

        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $inserted = 0;

        // Criptografa o token antes de salvar
        $tokenEnc = TokenCrypto::encrypt($token);

        foreach ($accsRes['data'] as $acc) {
            $accId = str_replace('act_','',$acc['id']);
            $exists = $db->query("SELECT id FROM ad_accounts WHERE user_id=? AND account_id=? AND platform='meta'",[$uid,$accId])->fetch();
            if (!$exists) {
                $db->query("INSERT INTO ad_accounts (user_id,account_id,account_name,platform,access_token) VALUES (?,?,?,'meta',?)",
                    [$uid,$accId,$acc['name'],$tokenEnc]);
                $inserted++;
            } else {
                $db->query("UPDATE ad_accounts SET access_token=?, status='active' WHERE user_id=? AND account_id=? AND platform='meta'",
                    [$tokenEnc,$uid,$accId]);
            }
        }

        flash('success',"Meta Ads conectado! $inserted conta(s) adicionada(s).");
        redirect('/accounts');
    }

    public function googleCallback(): void {
        requireAuth();
        $code  = sanitize($_GET['code']  ?? '');
        $state = sanitize($_GET['state'] ?? '');

        if (!$code || !hash_equals($_SESSION['oauth_state']??'', $state)) {
            flash('error','OAuth inválido.'); redirect('/accounts');
        }

        // Troca code por token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>http_build_query([
                'code'=>$code,'client_id'=>GOOGLE_CLIENT_ID,'client_secret'=>GOOGLE_CLIENT_SECRET,
                'redirect_uri'=>GOOGLE_REDIRECT,'grant_type'=>'authorization_code',
            ]),
        ]);
        $tokenRes = json_decode(curl_exec($ch),true);
        curl_close($ch);

        if (!isset($tokenRes['access_token'])) { flash('error','Erro ao obter token Google.'); redirect('/accounts'); }

        $token   = $tokenRes['access_token'];
        $refresh = $tokenRes['refresh_token'] ?? null;
        $expires = $tokenRes['expires_in']    ? date('Y-m-d H:i:s',time()+$tokenRes['expires_in']) : null;

        // Criptografa antes de salvar
        $tokenEnc   = TokenCrypto::encrypt($token);
        $refreshEnc = $refresh ? TokenCrypto::encrypt($refresh) : null;

        // Busca Customer IDs acessíveis
        $ch = curl_init('https://googleads.googleapis.com/v15/customers:listAccessibleCustomers');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'developer-token: '.GOOGLE_DEVELOPER_TOKEN],
        ]);
        $custRes = json_decode(curl_exec($ch),true);
        curl_close($ch);

        $uid = currentUser()['id'];
        $db  = Database::getInstance();
        $inserted = 0;
        foreach ($custRes['resourceNames'] ?? [] as $rn) {
            $custId = basename($rn);
            $exists = $db->query("SELECT id FROM ad_accounts WHERE user_id=? AND account_id=? AND platform='google'",[$uid,$custId])->fetch();
            if (!$exists) {
                $db->query("INSERT INTO ad_accounts (user_id,account_id,account_name,platform,access_token,refresh_token,token_expires) VALUES (?,?,?,'google',?,?,?)",
                    [$uid,$custId,"Google Ads $custId",$tokenEnc,$refreshEnc,$expires]);
                $inserted++;
            }
        }

        flash('success',"Google Ads conectado! $inserted conta(s) adicionada(s).");
        redirect('/accounts');
    }

    private function metaToken(string $code): array {
        $url = "https://graph.facebook.com/".META_API_VERSION."/oauth/access_token";
        $ch  = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>http_build_query([
                'client_id'=>META_APP_ID,'client_secret'=>META_APP_SECRET,
                'redirect_uri'=>META_REDIRECT,'code'=>$code,
            ]),
        ]);
        $res = json_decode(curl_exec($ch),true);
        curl_close($ch);
        return $res ?? [];
    }
}
