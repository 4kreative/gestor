<?php
// View de primeiro acesso — GestorADS
$erro = $_SESSION['pa_erro'] ?? '';
unset($_SESSION['pa_erro']);

$db  = Database::getInstance();
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
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<?php if ($favUrl): ?><link rel="icon" href="<?= htmlspecialchars($favUrl) ?>"><?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>Primeiro Acesso — <?= htmlspecialchars($nomeSistema) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;-webkit-text-size-adjust:100%}
body{min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px 16px;font-family:'DM Sans',sans-serif;background:transparent}
.card{width:100%;max-width:360px;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.18);border-radius:24px;padding:32px 24px 28px;box-shadow:0 25px 60px rgba(0,0,0,.4);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.logo-wrap{width:64px;height:64px;border-radius:50%;overflow:hidden;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.20);border:2px solid rgba(255,255,255,.35);font-size:24px}
.logo-wrap img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.titulo{font-size:16px;font-weight:700;color:#fff;text-align:center;margin-bottom:4px}
.sub{font-size:13px;color:rgba(255,255,255,.60);text-align:center;margin-bottom:6px}
.aviso{background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.4);border-radius:12px;padding:12px 14px;font-size:12px;color:rgba(255,255,255,.85);line-height:1.5;margin-bottom:20px;text-align:center}
.aviso strong{color:#fff}
.divider{height:1px;background:rgba(255,255,255,.10);margin:16px 0}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:5px}
.inp{width:100%;padding:12px 14px;background:rgba(255,255,255,.10);border:1.5px solid rgba(255,255,255,.18);border-radius:12px;color:#fff;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s}
.inp::placeholder{color:rgba(255,255,255,.30)}
.inp:focus{border-color:rgba(99,102,241,.8);background:rgba(255,255,255,.14)}
.pass-wrap{position:relative}
.pass-wrap .inp{padding-right:42px}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,.45);font-size:16px;padding:2px}
.erro-box{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.35);border-radius:10px;padding:10px 12px;font-size:12px;color:#fca5a5;margin-bottom:14px;text-align:center;display:<?= $erro ? 'block' : 'none' ?>}
.req{font-size:11px;color:rgba(255,255,255,.35);margin-top:4px}
.btn{width:100%;padding:14px;background:rgba(99,102,241,.85);border:1.5px solid rgba(99,102,241,.5);border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;margin-top:6px}
.btn:hover{background:rgba(99,102,241,1);transform:translateY(-1px)}
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt=""><?php else: ?>🔑<?php endif; ?>
  </div>
  <div class="titulo">Bem-vindo ao <?= htmlspecialchars($nomeSistema) ?>!</div>
  <div class="sub">Primeiro acesso — configure sua conta</div>
  <div class="divider"></div>
  <div class="aviso">
    ⚠️ Por segurança, <strong>defina seu e-mail e senha</strong> antes de continuar.<br>
    Isso garante que só você acessa sua conta.
  </div>
  <div class="erro-box" id="erro"><?= htmlspecialchars($erro) ?></div>
  <form method="POST" action="<?= rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') ?>/primeiro-acesso/post" autocomplete="off">
    <div class="field">
      <label>Seu nome</label>
      <input class="inp" name="nome" placeholder="Como quer ser chamado" autocomplete="off" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Novo e-mail *</label>
      <input class="inp" type="email" name="email" placeholder="seu@email.com" required autocomplete="off">
    </div>
    <div class="field">
      <label>Nova senha *</label>
      <div class="pass-wrap">
        <input class="inp" type="password" name="senha" id="s1" placeholder="Mínimo 8 caracteres" required autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')">
        <button type="button" class="eye" onclick="tp('s1',this)">👁</button>
      </div>
      <div class="req">Mínimo 8 caracteres</div>
    </div>
    <div class="field">
      <label>Confirmar senha *</label>
      <div class="pass-wrap">
        <input class="inp" type="password" name="senha_conf" id="s2" placeholder="Repita a senha" required autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')">
        <button type="button" class="eye" onclick="tp('s2',this)">👁</button>
      </div>
    </div>
    <button type="submit" class="btn">✅ Salvar e entrar no sistema</button>
  </form>
</div>
<script>
function tp(id, btn) {
  var f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
