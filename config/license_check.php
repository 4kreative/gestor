<?php
// ============================================================
// LICENSE CHECK — GestorADS
// Verifica licença APENAS na ativação e no vencimento.
// Status fica salvo no banco local — sem chamadas externas
// durante o uso normal do sistema.
// ============================================================

if (!function_exists('date_default_timezone_get') || date_default_timezone_get() !== 'America/Sao_Paulo') {
    date_default_timezone_set('America/Sao_Paulo');
}

if (!defined('LH_INTERVALO_CHECAGEM_MINUTOS')) {
    define('LH_INTERVALO_CHECAGEM_MINUTOS', 0.5); // 30 segundos
}

if (!defined('LH_API_URL') || !defined('LH_SECRET_KEY')) { return; }

// ── Helpers de domínio ────────────────────────────────────────

function lh_norm_domain($d) {
    $d = trim((string)$d);
    $d = preg_replace('#^https?://#i', '', $d);
    $d = preg_replace('#^www\.#i',     '', $d);
    $d = preg_replace('#/.*$#',        '', $d);
    $d = preg_replace('#:\d+$#',       '', $d);
    return strtolower(trim($d));
}

function lh_current_domain() {
    return lh_norm_domain($_SERVER['HTTP_HOST'] ?? '');
}

function lh_is_api_request() {
    $s = $_SERVER['PHP_SELF'] ?? '';
    return strpos($s, '/api/') !== false
        || str_ends_with($s, '/api')
        || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}

function lh_current_page() {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $base = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
    if ($base && strpos($p, $base) === 0) $p = substr($p, strlen($base));
    return strtolower(trim($p, '/'));
}

function lh_redirect_activation() {
    $base = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
    $url = $base . '/ativacao';
    if (lh_is_api_request()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'licenca' => false,
            'erro'    => 'Licença não ativada.',
            'redirect'=> $url,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!headers_sent()) header('Location: ' . $url);
    exit;
}

// ── Tabela de licença no banco local ─────────────────────────

function lh_ensure_table($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sys_licenca` (
            `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `chave`          VARCHAR(64)  NOT NULL,
            `dominio`        VARCHAR(255) NOT NULL,
            `ativo`          TINYINT(1)   NOT NULL DEFAULT 0,
            `data_vencimento` DATE         NOT NULL,
            `ativado_em`     DATETIME     DEFAULT NULL,
            `ultima_checagem` DATETIME    DEFAULT NULL,
            `atualizado_em`  DATETIME     DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Migração leve: garante a coluna em tabelas já existentes
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM sys_licenca LIKE 'ultima_checagem'")->fetchAll();
        if (!$cols) {
            $pdo->exec("ALTER TABLE sys_licenca ADD COLUMN ultima_checagem DATETIME DEFAULT NULL");
        } else {
            // Migrar DATE → DATETIME se necessário
            $col0 = (array)$cols[0];
            $tipo = $col0['Type'] ?? '';
            if (strtolower($tipo) === 'date') {
                $pdo->exec("ALTER TABLE sys_licenca MODIFY ultima_checagem DATETIME DEFAULT NULL");
            }
        }
    } catch (Exception $e) {}
}

function lh_get_local_license($pdo) {
    try {
        lh_ensure_table($pdo);
        $st = $pdo->query("SELECT * FROM sys_licenca ORDER BY id DESC LIMIT 1");
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function lh_save_local_license($pdo, string $chave, string $vencimento) {
    try {
        lh_ensure_table($pdo);
        // Remove registros antigos e insere o novo
        $pdo->exec("DELETE FROM sys_licenca");
        $st = $pdo->prepare("
            INSERT INTO sys_licenca (chave, dominio, ativo, data_vencimento, ativado_em, ultima_checagem)
            VALUES (:chave, :dominio, 1, :vencimento, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $st->execute([
            ':chave'      => strtoupper(trim($chave)),
            ':dominio'    => lh_current_domain(),
            ':vencimento' => $vencimento,
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function lh_touch_checagem($pdo) {
    try {
        $pdo->exec("UPDATE sys_licenca SET ultima_checagem = UTC_TIMESTAMP()");
    } catch (Exception $e) {}
}

function lh_clear_local_license($pdo) {
    try {
        lh_ensure_table($pdo);
        $pdo->exec("DELETE FROM sys_licenca");
    } catch (Exception $e) {}
}

// ── Validação remota (só chamada na ativação ou no vencimento) ─

function lh_remote_validate($key) {
    $key    = strtoupper(trim((string)$key));
    $domain = lh_current_domain();
    $ts     = time();
    $sig    = hash_hmac('sha256', $key . '|' . $domain . '|' . $ts, LH_SECRET_KEY);
    $post   = http_build_query([
        'acao'    => 'validar',
        'chave'   => $key,
        'dominio' => $domain,
        'ts'      => $ts,
        'sig'     => $sig,
        'sistema' => defined('LH_SISTEMA') ? LH_SISTEMA : 'desconhecido',
    ]);

    // Usa cURL para suportar HTTPS e seguir redirects automaticamente
    $ch = curl_init(LH_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,   // segue redirects (ex: .php → sem .php)
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        return ['ok' => false, 'erro' => 'Servidor indisponível.', 'offline' => true];
    }
    $j = json_decode($resp, true);
    return is_array($j) ? $j : ['ok' => false, 'erro' => 'Resposta inválida.'];
}

// Quantos dias de tolerância liberar o acesso caso o servidor
// de licenças (key.chat6.com.br) esteja fora do ar no momento
// de uma revalidação obrigatória (licença vencida). Evita bloquear
// cliente legítimo por instabilidade momentânea do servidor central.
if (!defined('LH_TOLERANCIA_OFFLINE_DIAS')) {
    define('LH_TOLERANCIA_OFFLINE_DIAS', 3);
}

// Intervalo (em dias) para revalidar com a API mesmo quando a
// licença ainda está dentro da validade local. Permite que uma
// pausa manual (ativo=0) no LicenseHub reflita no cliente em até
// esse número de dias, sem esperar o vencimento.
if (!defined('LH_INTERVALO_CHECAGEM_DIAS')) {
    define('LH_INTERVALO_CHECAGEM_DIAS', 1);
}

// ── Lógica principal ──────────────────────────────────────────

function lh_check_license($pdo): bool {
    $lic = lh_get_local_license($pdo);

    // Sem licença salva → precisa ativar
    if (!$lic || empty($lic['chave'])) {
        return false;
    }

    // Se pausado localmente → checar KEY para ver se foi reativado
    if (!$lic['ativo']) {
        $resultado = lh_remote_validate($lic['chave']);
        if (!empty($resultado['ok'])) {
            // Foi reativado no KEY → reativar local automaticamente
            $novaData = $resultado['data_vencimento'] ?? $resultado['vencimento'] ?? $lic['data_vencimento'];
            lh_save_local_license($pdo, $lic['chave'], $novaData);
            try { $pdo->exec("UPDATE sys_licenca SET ativo=1, ultima_checagem = UTC_TIMESTAMP()"); } catch(Exception $e){}
            return true;
        }
        return false; // Ainda pausado
    }

    $hoje       = date('Y-m-d');
    $vencimento = $lic['data_vencimento'];
    $venceu     = ($vencimento < $hoje);

    // Decide se precisa checar a API hoje:
    // - sempre, se já venceu
    // - ou se passou LH_INTERVALO_CHECAGEM_DIAS dias desde a última checagem
    //   (detecta pausa manual feita no LicenseHub, mesmo sem vencer)
    $ultimaChecagem = $lic['ultima_checagem'] ?? null;
    $precisaChecar  = $venceu;
    if (!$precisaChecar) {
        if (!$ultimaChecagem) {
            $precisaChecar = true; // nunca checou ainda
        } else {
            // Usar SQL para comparar datas (evita problema de fuso PHP vs banco)
            $segundos = (int)(LH_INTERVALO_CHECAGEM_MINUTOS * 60);
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ultima_checagem, UTC_TIMESTAMP()) >= ? FROM sys_licenca LIMIT 1");
            $st->execute([$segundos]);
            $precisaChecar = (bool)$st->fetchColumn();
        }
    }

    // Licença dentro da validade e ainda não é hora de checar → libera direto
    if (!$venceu && !$precisaChecar) {
        return true;
    }

    // Chama a API para revalidar
    $resultado = lh_remote_validate($lic['chave']);

    if (!empty($resultado['ok'])) {
        // Licença confirmada como ativa pelo painel.
        $novaData = $resultado['data_vencimento']
            ?? $resultado['vencimento']
            ?? $vencimento;
        lh_save_local_license($pdo, $lic['chave'], $novaData);
        // Garantir ativo=1 caso estivesse pausado
        try { $pdo->exec("UPDATE sys_licenca SET ativo=1, ultima_checagem = UTC_TIMESTAMP()"); } catch(Exception $e){}

        // secret_key NÃO é salvo — sempre usa o secret global para evitar problemas ao recriar licença
        return true;
    }

    // Servidor de licenças fora do ar (não respondeu)
    if (!empty($resultado['offline'])) {
        // Se ainda dentro da validade local, libera normalmente —
        // só não conseguiu confirmar a checagem periódica.
        if (!$venceu) {
            return true;
        }
        // Já venceu E está offline → tolerância de alguns dias
        $limiteTolerancia = date('Y-m-d', strtotime($vencimento . ' +' . LH_TOLERANCIA_OFFLINE_DIAS . ' days'));
        if ($hoje <= $limiteTolerancia) {
            return true;
        }
        return false;
    }

    // Servidor respondeu que a licença está inativa/inválida
    // Se o erro indica "não encontrada" → excluída → apaga local
    // Se pausada → só marca ativo=0 (reativa automaticamente quando KEY reativar)
    $erro = $resultado['erro'] ?? '';
    $httpCode = $resultado['http_code'] ?? 0;
    // "Chave não encontrada" = excluída → apaga local
    // "Licença pausada" ou qualquer outro erro = pausada → mantém chave
    if (stripos($erro, 'não encontrada') !== false && stripos($erro, 'inativa') === false) {
        // Licença excluída do KEY → apaga local, cliente precisa de nova chave
        lh_clear_local_license($pdo);
    } else {
        // Licença pausada → só desativa local, mantém a chave
        try { $pdo->exec("UPDATE sys_licenca SET ativo=0"); } catch(Exception $e){}
    }
    return false;
}

// ── Execução ──────────────────────────────────────────────────

// O $pdo pode ainda não existir quando config.php carrega este arquivo.
// Se não existir, tenta conectar com as constantes definidas em config.php.
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
        );
    } catch (Exception $e) {
        // Se não conectar, libera para não bloquear tudo por erro de banco
        $GLOBALS['LH_LICENSE_VALID']   = true;
        $lhBase = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
$GLOBALS['LH_ACTIVATION_PATH'] = $lhBase . '/ativacao';
        return;
    }
}

$GLOBALS['LH_LICENSE_VALID']   = lh_check_license($pdo);
$lhBase = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
$GLOBALS['LH_ACTIVATION_PATH'] = $lhBase . '/ativacao';

$page             = lh_current_page();
$isActivationPage = ($page === 'ativacao' || $page === 'ativacao/' || $page === 'ativacao/post');

if (!$GLOBALS['LH_LICENSE_VALID'] && !$isActivationPage) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['user_id'], $_SESSION['user_data']);
    }
    lh_redirect_activation();
}

if ($GLOBALS['LH_LICENSE_VALID'] && $isActivationPage) {
    $base2 = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
    $dest = !empty($_SESSION['user_id']) ? $base2 . '/' : $base2 . '/login';
    header('Location: ' . $dest);
    exit;
}

// Salvar dados de ping numa variável global — o head.php injeta no <head>
if ($GLOBALS['LH_LICENSE_VALID'] && !$isActivationPage && !lh_is_api_request()) {
    $base3 = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
    $GLOBALS['LH_PING_URL'] = $base3 . '/api/licenca_ping.php';
    $GLOBALS['LH_PING_RED'] = $base3 . '/ativacao';
    $GLOBALS['LH_PING_MS']  = LH_INTERVALO_CHECAGEM_MINUTOS * 60000;
}
