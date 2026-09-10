<?php
// View de ativação de licença — GestorADS
$erro = $_SESSION['ativ_erro'] ?? '';
unset($_SESSION['ativ_erro']);

// Pegar config visual do sistema
$db = Database::getInstance();
$cfg = null;
try {
    $cfg = $db->query("SELECT site_name, login_bg_path, favicon_path, login_logo_path FROM system_settings WHERE id=1 LIMIT 1")->fetch();
} catch (Exception $e) {}

$nomeSistema = $cfg['site_name'] ?? APP_NAME ?? 'GestorADS';
$logoUrl     = !empty($cfg['login_logo_path']) ? '/uploads/' . $cfg['login_logo_path'] : '';
$bgUrl       = !empty($cfg['login_bg_path'])   ? '/uploads/' . $cfg['login_bg_path']   : '';
$favUrl      = !empty($cfg['favicon_path'])     ? '/uploads/' . $cfg['favicon_path']    : '';

$bgInline = $bgUrl
    ? "background:#0f172a url('" . htmlspecialchars($bgUrl) . "') center/cover no-repeat fixed;min-height:100%"
    : "background:#0f172a;background-image:linear-gradient(135deg,#1e1b4b,#312e81,#1e3a5f);min-height:100%";
?>
<!DOCTYPE html>
<html lang="pt-BR" style="<?= $bgInline ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<?php if ($favUrl): ?><link rel="icon" href="<?= htmlspecialchars($favUrl) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<title><?= htmlspecialchars($nomeSistema) ?> — Ativação</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;-webkit-text-size-adjust:100%}
body{min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px 16px;font-family:'DM Sans',sans-serif;background:transparent}
.card{width:100%;max-width:328px;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.20);border-radius:28px;padding:34px 24px 24px;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.40);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.logo-wrap{width:72px;height:72px;border-radius:50%;overflow:hidden;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.20);border:2px solid rgba(255,255,255,.40);box-shadow:0 0 0 4px rgba(255,255,255,.08);font-size:26px}
.logo-wrap img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.nome{font-size:15px;font-weight:700;color:#fff;margin-bottom:4px}
.sub{font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);margin-bottom:24px}
.alert{display:<?= $erro ? 'block' : 'none' ?>;margin:0 0 14px;padding:12px 14px;border-radius:12px;text-align:left;font-size:13px;line-height:1.5;color:#fff;background:rgba(180,20,50,.25);border:1px solid rgba(255,80,80,.35)}
.input-wrap{position:relative;margin-bottom:14px}
.key-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:15px;opacity:.7}
input[name=chave]{width:100%;padding:13px 14px 13px 38px;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);border-radius:14px;color:#fff;font-size:14px;font-weight:600;letter-spacing:.5px;outline:none;transition:.2s}
input[name=chave]::placeholder{color:rgba(255,255,255,.45);font-weight:400;letter-spacing:0}
input[name=chave]:focus{border-color:rgba(99,102,241,.8);background:rgba(255,255,255,.16)}
.btn{width:100%;padding:14px;background:rgba(99,102,241,0.85);border:1.5px solid rgba(99,102,241,.6);border-radius:14px;color:#fff;font-size:14px;font-weight:700;letter-spacing:.3px;cursor:pointer;transition:.2s;margin-bottom:16px}
.btn:hover{background:rgba(99,102,241,1);transform:translateY(-1px)}
.hint{font-size:11px;color:rgba(255,255,255,.50);line-height:1.5}
.hint b{color:rgba(255,255,255,.75)}
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <?php if ($logoUrl): ?>
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo">
    <?php else: ?>
      🔑
    <?php endif; ?>
  </div>
  <div class="nome"><?= htmlspecialchars($nomeSistema) ?></div>
  <div class="sub">Ativação da Licença</div>
  <div class="alert" id="alerta"><?= htmlspecialchars($erro) ?></div>
  <form method="POST" action="<?= rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') ?>/ativacao/post">
    <div class="input-wrap">
      <span class="key-icon">🔑</span>
      <input type="text" name="chave" placeholder="XXXX-XXXX-XXXX-XXXX-XX"
             value="" autocomplete="off" autocapitalize="characters"
             maxlength="22" spellcheck="false">
    </div>
    <button type="submit" class="btn">ATIVAR LICENÇA</button>
  </form>
  <div class="hint">Formato: <b>XXXX-XXXX-XXXX-XXXX-XX</b><br>Recebida no ato da contratação.</div>
</div>
</body>
</html>
