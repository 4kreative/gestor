<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/license.php';
$pdo = Database::getInstance()->getConnection();

function ativ_norm_domain($domain) {
    $domain = trim((string)$domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i',     '', $domain);
    $domain = preg_replace('#/.*$#',        '', $domain);
    $domain = preg_replace('#:\d+$#',       '', $domain);
    return strtolower(trim($domain));
}

// Salva a licença no banco de dados local
function ativ_save_license($pdo, string $chave, string $vencimento): bool {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `sys_licenca` (
                `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `chave`           VARCHAR(64)  NOT NULL,
                `dominio`         VARCHAR(255) NOT NULL,
                `ativo`           TINYINT(1)   NOT NULL DEFAULT 0,
                `data_vencimento` DATE         NOT NULL,
                `ativado_em`      DATETIME     DEFAULT NULL,
                `atualizado_em`   DATETIME     DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("DELETE FROM sys_licenca");
        $st = $pdo->prepare("
            INSERT INTO sys_licenca (chave, dominio, ativo, data_vencimento, ativado_em)
            VALUES (:chave, :dominio, 1, :vencimento, NOW())
        ");
        $st->execute([
            ':chave'      => strtoupper(trim($chave)),
            ':dominio'    => ativ_norm_domain($_SERVER['HTTP_HOST'] ?? ''),
            ':vencimento' => $vencimento,
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function ativ_validate_remote($key): array {
    $key    = strtoupper(trim((string)$key));
    $domain = ativ_norm_domain($_SERVER['HTTP_HOST'] ?? '');
    $ts     = time();
    $sig    = hash_hmac('sha256', $key . '|' . $domain . '|' . $ts, LH_SECRET_KEY);
    $post   = http_build_query([
        'acao'    => 'validar',
        'chave'   => $key,
        'dominio' => $domain,
        'ts'      => $ts,
        'sig'     => $sig,
        'sistema' => defined('LH_SISTEMA') ? LH_SISTEMA : 'financeiro',
    ]);

    // Usa cURL para suportar HTTPS corretamente e seguir redirects automaticamente
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
        return ['ok' => false, 'erro' => 'Não foi possível conectar ao servidor de licenças.'];
    }
    $json = json_decode($resp, true);
    return is_array($json) ? $json : ['ok' => false, 'erro' => 'Resposta inválida do servidor de licenças. (' . substr($resp, 0, 80) . ')'];
}

function telaAssetUrl($path){
    $path = trim((string)$path);
    if ($path === '') return '';
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return $path;
    if ($path[0] === '/') return $path;
    return BASE_PATH . '/' . ltrim($path, '/');
}

function telaAssetLocalPath($path){
    $path = trim((string)$path);
    if ($path === '') return '';
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return '';
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($docRoot !== '') {
        $base = ltrim((string)BASE_PATH, '/');
        if ($path[0] === '/') return $docRoot . $path;
        return $docRoot . ($base ? '/' . $base : '') . '/' . ltrim($path, '/');
    }
    return __DIR__ . '/' . ltrim($path, '/');
}

function telaAssetExists($path){
    $path = trim((string)$path);
    if ($path === '') return false;
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return true;
    $local = telaAssetLocalPath($path);
    return $local !== '' && is_file($local);
}

$cfg = null;
try {
    $st  = $pdo->query("SELECT site_name,login_bg_path,favicon_path,login_logo_path FROM system_settings WHERE id=1 LIMIT 1");
    $cfg = $st->fetch();
} catch (Exception $e) {}

$basePath = defined('APP_URL') ? rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') : '';
$loginBg      = trim((string)($cfg->login_bg_path ?? ''));
$loginFav     = trim((string)($cfg->favicon_path ?? ''));
$loginEmpresa = html_entity_decode($cfg->site_name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'GestorADS';
$logoParaUsar = trim((string)($cfg->login_logo_path ?? ''));

$bgUrl   = telaAssetExists($loginBg) ? telaAssetUrl($loginBg) : '';
$favUrl  = telaAssetExists($loginFav) ? telaAssetUrl($loginFav) : '';
$logoUrl = telaAssetExists($logoParaUsar) ? telaAssetUrl($logoParaUsar) : '';

if ($bgUrl) {
    $bgInline = "background:#1a0836 url('" . htmlspecialchars($bgUrl, ENT_QUOTES) . "') center/cover no-repeat fixed;min-height:100%";
} else {
    $bgInline = "background:#1a0836;background-image:linear-gradient(135deg,#2b0c4d,#7a2c5a,#1c2c87);min-height:100%";
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chave = strtoupper(trim($_POST['chave'] ?? ''));
    $chave = preg_replace('/[^A-Z0-9\-]/', '', $chave);

    if ($chave === '') {
        $erro = 'Digite a chave da licença.';
    } else {
        $dominioDetectado = ativ_norm_domain($_SERVER['HTTP_HOST'] ?? '');
        $ret = ativ_validate_remote($chave);

        if (!empty($ret['ok'])) {
            // Pega a data de vencimento retornada pela API
            $vencimento = $ret['data_vencimento']
                ?? $ret['vencimento']
                ?? date('Y-m-d', strtotime('+30 days'));

            // Normaliza formato da data para Y-m-d
            if (strpos($vencimento, '/') !== false) {
                $parts = explode('/', $vencimento);
                if (count($parts) === 3) {
                    $vencimento = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }

            // Se a API retornou um secret_key único para esta licença,
            // salva automaticamente no config_licenca.php — sem precisar
            // de intervenção manual.
            if (!empty($ret['secret_key'])) {
                $configPath = __DIR__ . '/includes/config_licenca.php';
                if (is_writable($configPath)) {
                    $conteudo = file_get_contents($configPath);
                    $novoConteudo = preg_replace(
                        "/define\('LH_SECRET_KEY',\s*'[^']*'\);/",
                        "define('LH_SECRET_KEY', '" . addslashes($ret['secret_key']) . "');",
                        $conteudo
                    );
                    if ($novoConteudo && $novoConteudo !== $conteudo) {
                        file_put_contents($configPath, $novoConteudo);
                    }
                }
            }

            // Salva no banco local
            lh_save_local_license($pdo, $chave, $vencimento);

            header('Location: ' . $basePath . '/login');
            exit;
        }

        $erroServidor = $ret['erro'] ?? 'Não foi possível ativar a licença.';
        if (stripos($erroServidor, 'dom') !== false) {
            $erro = $erroServidor . ' (domínio detectado: ' . htmlspecialchars($dominioDetectado) . ')';
        } else {
            $erro = htmlspecialchars($erroServidor);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" style="<?= $bgInline ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
<meta name="theme-color" content="#1a0836">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?php if ($favUrl): ?><link rel="icon" href="<?= htmlspecialchars($favUrl, ENT_QUOTES) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<title><?= htmlspecialchars($loginEmpresa) ?> — Ativação</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;-webkit-text-size-adjust:100%}
body{min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px 16px;font-family:'DM Sans',sans-serif;background:transparent}
.card{width:100%;max-width:328px;background:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.28);border-radius:28px;padding:34px 24px 24px;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.28);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.logo-wrap{width:72px;height:72px;border-radius:50%;overflow:hidden;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.30);border:2px solid rgba(255,255,255,.55);box-shadow:0 0 0 4px rgba(255,255,255,.10);font-size:26px}
.logo-wrap img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.nome{font-size:15px;font-weight:700;color:#fff;margin-bottom:4px}
.sub{font-size:13px;font-weight:500;color:rgba(255,255,255,0.88);margin-bottom:24px}
.alert{display:<?= $erro ? 'block' : 'none' ?>;margin:0 0 14px;padding:12px 14px;border-radius:12px;text-align:left;font-size:13px;line-height:1.5;color:#fff;background:rgba(180,20,50,.22);border:1px solid rgba(255,80,80,.30)}
.field{display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,0.45);padding:0 0 11px 0;margin-bottom:22px;text-align:left}
.field input{flex:1;border:none!important;outline:none!important;box-shadow:none!important;background:transparent!important;color:#fff!important;font-family:'DM Sans',sans-serif;font-size:15px;padding:0;-webkit-appearance:none;appearance:none;text-transform:uppercase;letter-spacing:1.5px}
.field input::placeholder{color:rgba(255,255,255,0.65);letter-spacing:0;text-transform:none}
.btn{width:100%;height:48px;border-radius:14px;border:1px solid rgba(255,255,255,.45);background:rgba(255,255,255,.07);color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;letter-spacing:2px;text-transform:uppercase;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{background:rgba(255,255,255,.15)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.help{margin-top:14px;font-size:12px;color:rgba(255,255,255,.75);line-height:1.6}
.help strong{color:rgba(255,255,255,.95)}
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>" alt="logo" onerror="this.style.display='none';this.parentNode.innerHTML='🔑'"><?php else: ?>🔑<?php endif; ?>
  </div>
  <div class="nome"><?= htmlspecialchars($loginEmpresa) ?></div>
  <div class="sub">Ativação da Licença</div>

  <div class="alert" id="alerta"><?= $erro ?></div>

  <form method="POST" autocomplete="off" id="frm">
    <div class="field">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
      <input type="text" name="chave" id="chave"
             placeholder="XXXX-XXXX-XXXX-XXXX-XX"
             maxlength="22" required
             value="<?= htmlspecialchars(strtoupper($_POST['chave'] ?? '')) ?>">
    </div>
    <button type="submit" class="btn" id="btn">
      <span class="spinner" id="spin"></span>
      <span id="btxt">Ativar Licença</span>
    </button>
  </form>

  <div class="help">
    Formato: <strong>XXXX-XXXX-XXXX-XXXX-XX</strong><br>
    Recebida no ato da contratação.
  </div>
</div>
<script>
document.getElementById('chave').addEventListener('input', function(){
    let v = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,18);
    let o = '';
    for(let i=0;i<v.length;i++){
        if(i===4||i===8||i===12||i===16) o+='-';
        o+=v[i];
    }
    this.value = o;
});
document.getElementById('frm').addEventListener('submit',function(){
    const b=document.getElementById('btn');
    b.disabled=true;
    document.getElementById('spin').style.display='block';
    document.getElementById('btxt').textContent='Ativando...';
});
</script>
</body>
</html>
