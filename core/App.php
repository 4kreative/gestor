<?php
// ============================================================
// Bootstrap do GestorPro
// ============================================================

// Aumenta tempo de execução para rotas de cron e API pesadas
$_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (str_contains($_uri, '/api/cron') || str_contains($_uri, '/cron/')) {
    ini_set('max_execution_time', 180);
    set_time_limit(180);
}

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';

// eram carregados em TODA requisição (101KB + 104KB + 241KB = ~450KB de PHP compilado
// desnecessariamente). O spl_autoload_register abaixo já os carrega sob demanda.

// Session segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (strpos(APP_URL,'https') !== false) ini_set('session.cookie_secure', 1);
    // GC agressivo para evitar acúmulo de arquivos de sessão em hospedagem compartilhada
    // (acúmulo causava lentidão no I/O de sessão ao fazer login)
    ini_set('session.gc_maxlifetime',  SESSION_LIFETIME);
    ini_set('session.gc_probability',  1);
    ini_set('session.gc_divisor',      50); // 2% de chance de rodar GC a cada request
    session_name('GESTORPRO_SESS');
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Licença — após Database e sessão prontos
if (file_exists(__DIR__.'/../config/license.php')) {
    require_once __DIR__.'/../config/license.php';
}
if (file_exists(__DIR__.'/../config/license_check.php')) {
    require_once __DIR__.'/../config/license_check.php';
}

require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/EvolutionApi.php';
require_once __DIR__.'/../core/Mailer.php';

// ── Session Lifetime ──────────────────────────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    $lastActivity = $_SESSION['_last_activity'] ?? time();
    if (time() - $lastActivity > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
    } else {
        $_SESSION['_last_activity'] = time();
    }
}

// ── Validação de plano: verifica do banco a cada 5 minutos ───────────────────
// CORREÇÃO: não depender apenas da sessão — planAllows() usa $_SESSION['user_plan']
// que pode ficar desatualizado. Revalidamos do banco a cada 300s.
if (!empty($_SESSION['user_id'])) {
    $planLastCheck = $_SESSION['_plan_last_check'] ?? 0;
    if (time() - $planLastCheck > 300) {
        $db = Database::getInstance();
        $dbUser = $db->query(
            "SELECT plan, plan_expires_at FROM users WHERE id=? AND status='active' LIMIT 1",
            [$_SESSION['user_id']]
        )->fetch();
        if ($dbUser) {
            // Verifica expiração
            if (!empty($dbUser['plan_expires_at']) && $dbUser['plan_expires_at'] !== 'never'
                && strtotime($dbUser['plan_expires_at']) < time()) {
                $db->query("UPDATE users SET plan='trial', plan_expires_at=NULL WHERE id=?", [$_SESSION['user_id']]);
                $_SESSION['user_plan'] = 'trial';
                unset($_SESSION['plan_expires_at']);
                flash('warning', 'Seu plano expirou. Você foi movido para o plano Trial.');
            } else {
                // Sincroniza plano real do banco com sessão
                $_SESSION['user_plan']      = $dbUser['plan'];
                $_SESSION['plan_expires_at']= $dbUser['plan_expires_at'] ?? 'never';
            }
        }
        $_SESSION['_plan_last_check'] = time();
    }
}

// ── Remember Me (com verificação de expiração) ───────────────────────────────
// CORREÇÃO: verifica remember_expires_at se existir; sempre invalida token após uso
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
    $db   = Database::getInstance();
    $user = $db->query(
        "SELECT * FROM users WHERE remember_token=? AND status='active' LIMIT 1",
        [trim($_COOKIE['remember_token'])]
    )->fetch();
    if ($user) {
        // Se a coluna remember_expires_at existir, valida expiração
        $tokenValido = true;
        if (isset($user['remember_expires_at']) && !empty($user['remember_expires_at'])) {
            $tokenValido = strtotime($user['remember_expires_at']) > time();
        }
        if ($tokenValido) {
            // Rotaciona o token a cada reconexão (token rotation)
            $novoToken = bin2hex(random_bytes(32));
            $novaExpiracao = date('Y-m-d H:i:s', time() + (REMEMBER_DAYS * 86400));
            if (isset($user['remember_expires_at'])) {
                $db->query("UPDATE users SET remember_token=?, remember_expires_at=? WHERE id=?",
                    [$novoToken, $novaExpiracao, $user['id']]);
            } else {
                $db->query("UPDATE users SET remember_token=? WHERE id=?", [$novoToken, $user['id']]);
            }
            setcookie('remember_token', $novoToken, time() + (REMEMBER_DAYS * 86400), '/', '', true, true);

            session_regenerate_id(true);
            $_SESSION['user_id']          = $user['id'];
            $_SESSION['user_name']        = $user['name'];
            $_SESSION['user_email']       = $user['email'];
            $_SESSION['user_role']        = $user['role'];
            $_SESSION['user_plan']        = $user['plan'];
            $_SESSION['user_avatar']      = $user['avatar'] ?? '';
            $_SESSION['_last_activity']   = time();
            $_SESSION['_plan_last_check'] = time();
        } else {
            // Token expirado: limpa
            $db->query("UPDATE users SET remember_token=NULL WHERE id=?", [$user['id']]);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
}

// Controllers.php contém múltiplas classes num único arquivo (WebhookController,
// ProfileController, NotificationController, etc.) — precisa de require explícito
// pois o autoload só encontra arquivos com nome igual à classe.
require_once __DIR__.'/../controllers/Controllers.php';
if (file_exists(__DIR__.'/../controllers/AdsAgentController.php')) {
    require_once __DIR__.'/../controllers/AdsAgentController.php';
}


// Os demais controllers são carregados sob demanda pelo autoload abaixo.

// Autoload para todos os controllers, models e core
spl_autoload_register(function($class) {
    $dirs = [
        __DIR__.'/../controllers/',
        __DIR__.'/../models/',
        __DIR__.'/../core/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir.$class.'.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});
