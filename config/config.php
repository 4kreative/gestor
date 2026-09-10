<?php
// ============================================================
// GestorPro – Configurações do Sistema
// ============================================================
// SEGURANÇA: credenciais sensíveis devem estar no .env da raiz.
// Se o .env não existir, usa os valores fallback abaixo.
// NUNCA suba o .env para o repositório (adicione ao .gitignore).
// ============================================================

// Carrega .env se existir
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (strpos($_line, '=') !== false) {
            [$_k, $_v] = explode('=', $_line, 2);
            $_k = trim($_k);
            $_v = trim($_v, " \t\n\r\0\x0B\"'");
            if (!empty($_k)) putenv("$_k=$_v");
        }
    }
}
unset($_envFile, $_line, $_k, $_v);

// Helper: lê variável de ambiente ou retorna fallback
function _env(string $key, string $fallback = ''): string {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $fallback;
}

// BANCO DE DADOS
define('DB_HOST',    _env('DB_HOST',    'localhost'));
define('DB_PORT',    _env('DB_PORT',    '3306'));
define('DB_NAME',    _env('DB_NAME',    ''));
define('DB_USER',    _env('DB_USER',    ''));
define('DB_PASS',    _env('DB_PASS',    ''));
define('DB_CHARSET', 'utf8mb4');

// APLICAÇÃO
define('APP_NAME',     _env('APP_NAME',     'GestorPro'));
define('APP_URL',      _env('APP_URL',      ''));
define('APP_VERSION',  '1.0.0');
define('APP_TIMEZONE', 'America/Sao_Paulo');
define('APP_ENV',      _env('APP_ENV',      'production'));

// SEGURANÇA
define('APP_SECRET',       _env('APP_SECRET',       ''));
define('CRON_SECRET', 'GST_'.hash('sha256', APP_SECRET.'_cron_2025'));
define('SESSION_LIFETIME', 7200);
// BCRYPT_COST 10 é o equilíbrio certo para hospedagem compartilhada:
// custo 12 → ~2-4s de lentidão no login; custo 10 → ~200-300ms (aceitável)
define('BCRYPT_COST',      10);
define('REMEMBER_DAYS',    30);

// EVOLUTION API (WhatsApp na VPS)
// Evolution API — prioridade: .env → system_settings → vazio
if (!defined('EVOLUTION_API_URL')) {
    $_evoUrl = _env('EVOLUTION_API_URL', '');
    $_evoKey = _env('EVOLUTION_API_KEY', '');
    if (($_evoUrl === '' || $_evoKey === '') && defined('DB_HOST')) {
        try {
            $_pdoEvo = new PDO(
                'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $_rowEvo = $_pdoEvo->query("SELECT evolution_api_url, evolution_api_key FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($_rowEvo) {
                if ($_evoUrl === '' && !empty($_rowEvo['evolution_api_url'])) $_evoUrl = $_rowEvo['evolution_api_url'];
                if ($_evoKey === '' && !empty($_rowEvo['evolution_api_key'])) $_evoKey = $_rowEvo['evolution_api_key'];
            }
            unset($_pdoEvo, $_rowEvo);
        } catch (Exception $_e) {}
    }
    define('EVOLUTION_API_URL', $_evoUrl);
    define('EVOLUTION_API_KEY', $_evoKey);
    unset($_evoUrl, $_evoKey);
}
define('EVOLUTION_WEBHOOK_URL', APP_URL . '/api/webhook/whatsapp');

// META ADS (Facebook / Instagram)
// Prioridade: .env → system_settings (banco) → vazio
// As credenciais podem ser configuradas pelo painel em Configurações → Meta API
if (!defined('META_APP_ID')) {
    $_metaId  = _env('META_APP_ID', '');
    $_metaSec = _env('META_APP_SECRET', '');
    // Se não estiver no .env, tenta ler do banco (system_settings)
    if (($_metaId === '' || $_metaSec === '') && defined('DB_HOST')) {
        try {
            $_pdo = new PDO(
                'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $_row = $_pdo->query("SELECT meta_app_id, meta_app_secret FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($_row) {
                if ($_metaId  === '' && !empty($_row['meta_app_id']))     $_metaId  = $_row['meta_app_id'];
                if ($_metaSec === '' && !empty($_row['meta_app_secret'])) $_metaSec = $_row['meta_app_secret'];
            }
            unset($_pdo, $_row);
        } catch (Exception $_e) { /* banco indisponível — ignora */ }
    }
    define('META_APP_ID',     $_metaId);
    define('META_APP_SECRET', $_metaSec);
    unset($_metaId, $_metaSec);
}
define('META_REDIRECT',   APP_URL . '/api/oauth/meta/callback');
define('META_API_VERSION','v25.0');

// GOOGLE ADS
define('GOOGLE_CLIENT_ID',      _env('GOOGLE_CLIENT_ID',      ''));
define('GOOGLE_CLIENT_SECRET',  _env('GOOGLE_CLIENT_SECRET',  ''));
define('GOOGLE_REDIRECT',       APP_URL . '/api/oauth/google/callback');
define('GOOGLE_DEVELOPER_TOKEN',_env('GOOGLE_DEVELOPER_TOKEN',''));

// E-MAIL (SMTP Hostinger)
// Email (SMTP) — prioridade: .env → system_settings → vazio
if (!defined('MAIL_HOST')) {
    $_mailHost = _env('MAIL_HOST', 'smtp.hostinger.com');
    $_mailPort = _env('MAIL_PORT', '465');
    $_mailUser = _env('MAIL_USER', '');
    $_mailPass = _env('MAIL_PASS', '');
    $_mailFrom = _env('MAIL_FROM_NAME', '');
    if (($_mailUser === '' || $_mailPass === '') && defined('DB_HOST')) {
        try {
            $_pdoMail = new PDO(
                'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $_rowMail = $_pdoMail->query("SELECT mail_host, mail_port, mail_user, mail_pass, mail_from_name FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($_rowMail) {
                if ($_mailHost === 'smtp.hostinger.com' && !empty($_rowMail['mail_host'])) $_mailHost = $_rowMail['mail_host'];
                if ($_mailPort === '465'               && !empty($_rowMail['mail_port']))  $_mailPort = (string)$_rowMail['mail_port'];
                if ($_mailUser === ''                  && !empty($_rowMail['mail_user']))  $_mailUser = $_rowMail['mail_user'];
                if ($_mailPass === ''                  && !empty($_rowMail['mail_pass']))  $_mailPass = $_rowMail['mail_pass'];
                if ($_mailFrom === ''                  && !empty($_rowMail['mail_from_name'])) $_mailFrom = $_rowMail['mail_from_name'];
            }
            unset($_pdoMail, $_rowMail);
        } catch (Exception $_e) {}
    }
    define('MAIL_HOST',      $_mailHost);
    define('MAIL_PORT',      (int)$_mailPort);
    define('MAIL_USER',      $_mailUser);
    define('MAIL_PASS',      $_mailPass);
    define('MAIL_FROM_NAME', $_mailFrom ?: APP_NAME);
    unset($_mailHost, $_mailPort, $_mailUser, $_mailPass, $_mailFrom);
}

// PAGINAÇÃO
define('ITEMS_PER_PAGE', 15);

// LIMITES POR PLANO
define('PLAN_LIMITS', serialize([
    //                clients  reports  whatsapp  accounts  alerts
    'trial'    => ['clients'=>5,   'reports'=>5,   'whatsapp'=>1,  'accounts'=>2,   'alerts'=>3  ],
    'essencial'=> ['clients'=>999, 'reports'=>999, 'whatsapp'=>1,  'accounts'=>10,  'alerts'=>10 ],
    'advanced' => ['clients'=>999, 'reports'=>999, 'whatsapp'=>2,  'accounts'=>50,  'alerts'=>30 ],
    'pro'      => ['clients'=>999, 'reports'=>999, 'whatsapp'=>5,  'accounts'=>100, 'alerts'=>999],
    'premium'  => ['clients'=>999, 'reports'=>999, 'whatsapp'=>10, 'accounts'=>200, 'alerts'=>999],
]));

// ============================================================
// Init
// ============================================================
date_default_timezone_set(APP_TIMEZONE);

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    // Detecta o caminho do log automaticamente se ERROR_LOG_PATH não estiver definido
    $_logPath = _env('ERROR_LOG_PATH', '');
    if (!$_logPath) {
        $_logPath = ini_get('error_log') ?: sys_get_temp_dir() . '/gestorads_error.log';
    }
    ini_set('error_log', $_logPath);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    // Detecta o caminho do log automaticamente se ERROR_LOG_PATH não estiver definido
    $_logPath = _env('ERROR_LOG_PATH', '');
    if (!$_logPath) {
        $_logPath = ini_get('error_log') ?: sys_get_temp_dir() . '/gestorads_error.log';
    }
    ini_set('error_log', $_logPath);
}
