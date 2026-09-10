<?php
class AuthController {

    // ---- LOGIN ----
    public function loginPage(): void {
        if (isAuth()) redirect('/dashboard');
        require_once __DIR__.'/../views/auth/login.php';
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/login');
        csrfCheck();

        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            flash('error', 'Preencha todos os campos.');
            redirect('/login');
        }

        $db = Database::getInstance();

        // ── Brute force: bloqueia após 5 falhas em 15 min ──────
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $window   = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $attempts = (int)$db->query(
            "SELECT COUNT(*) FROM activity_log WHERE action='login_fail' AND ip=? AND created_at > ?",
            [$ip, $window]
        )->fetchColumn();

        if ($attempts >= 5) {
            flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            redirect('/login');
        }

        $user = $db->query(
            "SELECT * FROM users WHERE email=? AND status='active' LIMIT 1",
            [$email]
        )->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $db->query(
                "INSERT INTO activity_log (user_id, action, entity, details, ip) VALUES (NULL, 'login_fail', 'auth', ?, ?)",
                [$email, $ip]
            );
            flash('error', 'E-mail ou senha incorretos.');
            redirect('/login');
        }

        // Inicia sessão — delete_old_session=true para remover arquivo de sessão antigo
        // e evitar acúmulo de arquivos em hospedagem compartilhada.
        // Em shared hosting com session.save_handler=files isso é seguro e necessário.
        session_regenerate_id(true);
        $_SESSION['user_id']          = $user['id'];
        $_SESSION['user_name']        = $user['name'];
        $_SESSION['user_email']       = $user['email'];
        $_SESSION['user_role']        = $user['role'];
        $_SESSION['user_plan']        = $user['plan'];
        $_SESSION['user_avatar']      = $user['avatar'] ?? '';
        $_SESSION['plan_expires_at']  = $user['plan_expires_at'] ?? 'never';
        $_SESSION['_last_activity']   = time();
        $_SESSION['_plan_last_check'] = time();

        // Detectar primeiro acesso (email ou senha ainda padrão)
        // primeiro acesso removido — usuário vai direto ao dashboard

        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $db->query("UPDATE users SET remember_token=? WHERE id=?", [$token, $user['id']]);
            setcookie('remember_token', $token, time() + (REMEMBER_DAYS * 86400), '/', '', true, true);
        }

        // Atualiza last_login e registra log em paralelo (uma query cada, ambas rápidas)
        $db->query("UPDATE users SET last_login=NOW() WHERE id=?", [$user['id']]);


        $db->query(
            "INSERT INTO activity_log (user_id, action, entity, ip) VALUES (?, 'login', 'auth', ?)",
            [$user['id'], $ip]
        );
        redirect('/dashboard');
        // ÍNDICE RECOMENDADO no banco para evitar full table scan no brute-force check:
        // CREATE INDEX IF NOT EXISTS idx_activity_brute ON activity_log (action, ip, created_at);
    }

    // ---- REGISTER ----
    public function registerPage(): void {
        if (isAuth()) redirect('/dashboard');
        require_once __DIR__.'/../views/auth/register.php';
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/register');
        csrfCheck();

        $name     = sanitize($_POST['name']            ?? '');
        $email    = sanitize($_POST['email']           ?? '');
        $password = $_POST['password']                 ?? '';
        $confirm  = $_POST['password_confirm']         ?? '';

        if (!$name || !$email || !$password) {
            flash('error', 'Preencha todos os campos.'); redirect('/register');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'E-mail inválido.'); redirect('/register');
        }
        if (strlen($password) < 8) {
            flash('error', 'A senha deve ter no mínimo 8 caracteres.'); redirect('/register');
        }
        if ($password !== $confirm) {
            flash('error', 'As senhas não coincidem.'); redirect('/register');
        }

        $db = Database::getInstance();
        if ($db->query("SELECT id FROM users WHERE email=?", [$email])->fetch()) {
            flash('error', 'Este e-mail já está cadastrado.'); redirect('/register');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->query("INSERT INTO users (name,email,password,status) VALUES (?,?,?,'active')", [$name,$email,$hash]);

        // E-mail de boas-vindas
        $html = Mailer::template(
            "Bem-vindo ao " . APP_NAME . "! 🎉",
            "Olá, <strong>" . e($name) . "</strong>!<br><br>
             Sua conta foi criada com sucesso. Agora você pode conectar suas contas de anúncios e começar a enviar relatórios automatizados pelo WhatsApp.",
            'Acessar Painel',
            APP_URL . '/dashboard'
        );
        Mailer::send($email, $name, 'Bem-vindo ao ' . APP_NAME, $html);

        flash('success', 'Conta criada! Faça login.');
        redirect('/login');
    }

    // ---- FORGOT PASSWORD ----
    public function forgotPage(): void {
        require_once __DIR__.'/../views/auth/forgot.php';
    }

    public function forgotPost(): void {
        csrfCheck();
        $email = sanitize($_POST['email'] ?? '');
        if (!$email) { flash('error','Informe seu e-mail.'); redirect('/forgot'); }

        $db = Database::getInstance();

        // #6 — Rate limit: máx 5 tentativas por IP em 15 minutos
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $window  = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $tentativas = (int)$db->query(
            "SELECT COUNT(*) FROM activity_log WHERE action='forgot_attempt' AND ip=? AND created_at > ?",
            [$ip, $window]
        )->fetchColumn();
        if ($tentativas >= 5) {
            flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            redirect('/forgot');
        }
        $db->query(
            "INSERT INTO activity_log (user_id, action, entity, ip) VALUES (NULL, 'forgot_attempt', 'auth', ?)",
            [$ip]
        );

        $user = $db->query("SELECT id, name FROM users WHERE email=? AND status='active'", [$email])->fetch();

        // Sempre mostra sucesso (evita enumeração de e-mails)
        flash('success', 'Se o e-mail estiver cadastrado, você receberá o código em instantes.');

        if ($user) {
            $code     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = hash('sha256', $code); // #7 — armazena hash, não o código em texto
            $token    = bin2hex(random_bytes(32));
            $expires  = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $db->query(
                "INSERT INTO password_resets (email,token,code,expires_at) VALUES (?,?,?,?)",
                [$email, $token, $codeHash, $expires]
            );

            // Envia e-mail com o código
            $nome = $user['name'] ?? 'Usuário';
            $html = Mailer::template(
                'Recuperação de senha',
                "Olá, <strong>" . e($nome) . "</strong>!<br><br>
                 Seu código de recuperação é:<br><br>
                 <div style='text-align:center;margin:20px 0'>
                   <span style='font-size:36px;font-weight:800;color:#5B8DEF;letter-spacing:8px'>$code</span>
                 </div>
                 Este código expira em <strong>30 minutos</strong>.<br><br>
                 Se você não solicitou a recuperação, ignore este e-mail."
            );
            Mailer::send($email, $nome, APP_NAME . ' — Código de recuperação', $html);

            if (APP_ENV === 'development') flash('info', "DEV — Código: $code");
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_token'] = $token;
        }
        redirect('/reset');
    }

    // ---- RESET PASSWORD ----
    public function resetPage(): void {
        require_once __DIR__.'/../views/auth/reset.php';
    }

    public function resetPost(): void {
        csrfCheck();
        $code     = sanitize($_POST['code']            ?? '');
        $password = $_POST['new_password']             ?? '';
        $confirm  = $_POST['new_password_confirm']     ?? '';
        $email    = $_SESSION['reset_email']           ?? '';
        $token    = $_SESSION['reset_token']           ?? '';

        if (!$code || !$password || !$email) {
            flash('error','Dados inválidos.'); redirect('/reset');
        }
        if ($password !== $confirm || strlen($password) < 8) {
            flash('error','Senhas não coincidem ou muito curtas.'); redirect('/reset');
        }

        $db  = Database::getInstance();

        // ── Rate limit: bloqueia após 10 tentativas de código em 15 min por IP ──
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $window  = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $attempts = (int)$db->query(
            "SELECT COUNT(*) FROM activity_log WHERE action='reset_fail' AND ip=? AND created_at > ?",
            [$ip, $window]
        )->fetchColumn();
        if ($attempts >= 10) {
            flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            redirect('/reset');
        }
        // #7 — Compara hash do código (não texto puro)
        $codeHash = hash('sha256', $code);
        $row = $db->query(
            "SELECT * FROM password_resets WHERE email=? AND token=? AND code=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            [$email, $token, $codeHash]
        )->fetch();

        if (!$row) {
            $db->query(
                "INSERT INTO activity_log (user_id, action, entity, details, ip) VALUES (NULL, 'reset_fail', 'auth', ?, ?)",
                [$email, $ip]
            );
            flash('error','Código inválido ou expirado.');
            redirect('/reset');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->query("UPDATE users SET password=? WHERE email=?", [$hash, $email]);
        $db->query("UPDATE password_resets SET used=1 WHERE id=?", [$row['id']]);

        unset($_SESSION['reset_email'], $_SESSION['reset_token']);
        flash('success','Senha alterada com sucesso! Faça login.');
        redirect('/login');
    }

    // ---- LOGOUT ----
    public function logout(): void {
        $db = Database::getInstance();
        if (isAuth()) $db->query("UPDATE users SET remember_token=NULL WHERE id=?", [$_SESSION['user_id']]);
        session_destroy();
        setcookie('remember_token', '', time()-3600, '/', '', true, true);
        redirect('/login');
    }

    // ── Ativação de Licença ──────────────────────────────────────
    public function ativacaoPage(): void {
        require_once __DIR__.'/../views/auth/ativacao.php';
    }

    public function ativacaoPost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/ativacao');
        }

        $chave = strtoupper(trim($_POST['chave'] ?? ''));
        $chave = preg_replace('/[^A-Z0-9\-]/', '', $chave);

        if (!$chave) {
            $_SESSION['ativ_erro'] = 'Digite a chave da licença.';
            redirect('/ativacao');
        }

        if (!defined('LH_API_URL') || !defined('LH_SECRET_KEY')) {
            $_SESSION['ativ_erro'] = 'Configuração de licença ausente.';
            redirect('/ativacao');
        }

        $domain = lh_norm_domain($_SERVER['HTTP_HOST'] ?? '');
        $ts     = time();
        $sig    = hash_hmac('sha256', $chave . '|' . $domain . '|' . $ts, LH_SECRET_KEY);

        $post = http_build_query([
            'acao'    => 'validar',
            'chave'   => $chave,
            'dominio' => $domain,
            'ts'      => $ts,
            'sig'     => $sig,
            'sistema' => defined('LH_SISTEMA') ? LH_SISTEMA : 'gestorads',
        ]);

        $ch = curl_init(LH_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err) {
            $_SESSION['ativ_erro'] = 'Não foi possível conectar ao servidor de licenças.';
            redirect('/ativacao');
        }

        $ret = json_decode($resp, true);
        if (!is_array($ret)) {
            $_SESSION['ativ_erro'] = 'Resposta inválida do servidor de licenças.';
            redirect('/ativacao');
        }

        if (!empty($ret['ok'])) {
            $vencimento = $ret['data_vencimento'] ?? $ret['vencimento'] ?? date('Y-m-d', strtotime('+30 days'));
            if (strpos($vencimento, '/') !== false) {
                $p = explode('/', $vencimento);
                if (count($p) === 3) $vencimento = $p[2] . '-' . $p[1] . '-' . $p[0];
            }

            // secret_key NÃO é salvo — sempre usa o secret global

            // Salva no banco local
            $pdo = Database::getInstance()->getConnection();
            lh_save_local_license($pdo, $chave, $vencimento);
            redirect('/');
        }

        $erro = $ret['erro'] ?? 'Não foi possível ativar a licença.';
        if (stripos($erro, 'dom') !== false) {
            $erro .= ' (domínio detectado: ' . htmlspecialchars($domain) . ')';
        }
        $_SESSION['ativ_erro'] = $erro;
        redirect('/ativacao');
    }


    // ── Primeiro Acesso ──────────────────────────────────────────
    public function primeiroAcessoPage(): void {
        if (empty($_SESSION['user_id'])) { redirect('/login'); }
        if (empty($_SESSION['primeiro_acesso'])) { redirect('/'); }
        require_once __DIR__.'/../views/auth/primeiro_acesso.php';
    }

    public function primeiroAcessoPost(): void {
        if (empty($_SESSION['user_id']) || empty($_SESSION['primeiro_acesso'])) {
            redirect('/login');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/primeiro-acesso'); }

        $nome      = trim($_POST['nome']       ?? '');
        $email     = trim($_POST['email']      ?? '');
        $senha     = $_POST['senha']            ?? '';
        $senhaConf = $_POST['senha_conf']       ?? '';

        if (!$email) {
            $_SESSION['pa_erro'] = 'E-mail obrigatório.';
            redirect('/primeiro-acesso');
        }
        if (strlen($senha) < 8) {
            $_SESSION['pa_erro'] = 'A senha deve ter pelo menos 8 caracteres.';
            redirect('/primeiro-acesso');
        }
        if ($senha !== $senhaConf) {
            $_SESSION['pa_erro'] = 'As senhas não coincidem.';
            redirect('/primeiro-acesso');
        }

        $db  = Database::getInstance();
        $uid = $_SESSION['user_id'];

        // Verificar email duplicado
        $chk = $db->query("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1", [$email, $uid])->fetch();
        if ($chk) {
            $_SESSION['pa_erro'] = 'Este e-mail já está em uso.';
            redirect('/primeiro-acesso');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->query(
            "UPDATE users SET email=?, password=?, name=CASE WHEN ? != '' THEN ? ELSE name END, updated_at=NOW() WHERE id=?",
            [$email, $hash, $nome, $nome, $uid]
        );

        // Atualizar sessão
        unset($_SESSION['primeiro_acesso']);
        $_SESSION['user_email'] = $email;
        if ($nome) $_SESSION['user_name'] = $nome;

        redirect('/');
    }

}