<?php
// ============================================================
// admin_licenca.php — Emergência GestorADS
// NÃO inclui nenhum arquivo do sistema (bypassa o license_check)
// Lê credenciais do .env automaticamente
// ============================================================

// ⚠️ SENHA ATUAL: @214059@  — TROQUE DEPOIS DE TESTAR! ⚠️
define('LH_ADMIN_TOKEN', '@214059@');

// ============================================================
// NÃO EDITE ABAIXO DESTA LINHA
// ============================================================

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/plain; charset=utf-8');

// Verifica token ANTES de qualquer outra coisa
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!hash_equals(LH_ADMIN_TOKEN, $token)) {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
}

// Lê .env da raiz (mesmo nível deste arquivo)
$env = [];
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';
$dbName = $env['DB_NAME'] ?? '';

if (!$dbUser || !$dbName) {
    http_response_code(500);
    echo "Erro: credenciais do banco não encontradas no .env.\nVerifique se o arquivo .env existe na raiz do projeto.";
    exit;
}

// Conecta diretamente ao banco
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo "Erro ao conectar ao banco: " . $e->getMessage();
    exit;
}

// Garante que a tabela existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `sys_licenca` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `chave`           VARCHAR(64)  NOT NULL,
        `dominio`         VARCHAR(255) NOT NULL,
        `ativo`           TINYINT(1)   NOT NULL DEFAULT 0,
        `data_vencimento` DATE         NOT NULL,
        `ativado_em`      DATETIME     DEFAULT NULL,
        `ultima_checagem` DATETIME     DEFAULT NULL,
        `atualizado_em`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$lic = $pdo->query("SELECT * FROM sys_licenca ORDER BY id DESC LIMIT 1")->fetch();

// Sem parâmetro 'dias': mostra status
if (empty($_GET['dias']) && empty($_POST['dias'])) {
    if (!$lic) {
        echo "Nenhuma licença ativada neste sistema ainda.\n";
        echo "Acesse ?token=...&dias=30 para ativar/estender por 30 dias.";
        exit;
    }
    echo "=== Status da Licença ===\n";
    echo "Chave:       " . $lic['chave'] . "\n";
    echo "Domínio:     " . $lic['dominio'] . "\n";
    echo "Ativo:       " . ($lic['ativo'] ? 'Sim' : 'Não') . "\n";
    echo "Vencimento:  " . $lic['data_vencimento'] . "\n";
    echo "Hoje:        " . date('Y-m-d') . "\n";
    exit;
}

// Estender/ativar
$dias = (int)($_GET['dias'] ?? $_POST['dias'] ?? 0);
if ($dias <= 0 || $dias > 3650) {
    http_response_code(400);
    echo "Parâmetro 'dias' inválido. Use um valor entre 1 e 3650.";
    exit;
}

$novaData = date('Y-m-d', strtotime("+{$dias} days"));
$dominio  = strtolower(preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? ''));
$chave    = $lic['chave'] ?? 'LIBERACAO-MANUAL';

$pdo->exec("DELETE FROM sys_licenca");
$st = $pdo->prepare("
    INSERT INTO sys_licenca (chave, dominio, ativo, data_vencimento, ativado_em, ultima_checagem)
    VALUES (:chave, :dominio, 1, :vencimento, NOW(), NOW())
");
$st->execute([
    ':chave'      => $chave,
    ':dominio'    => $dominio,
    ':vencimento' => $novaData,
]);

echo "OK. Licença liberada/estendida até: {$novaData}\n";
echo "Chave registrada: {$chave}\n";
echo "Domínio: {$dominio}\n";
echo "\nLembre-se de sincronizar essa data no painel LicenseHub (key.chat6.com.br).";
