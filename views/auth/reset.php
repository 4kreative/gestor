<?php
$db       = Database::getInstance();
try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Exception $e) { $settings = []; }
$siteName     = !empty($settings['site_name'])       ? $settings['site_name']     : APP_NAME;
$loginLogoUrl = !empty($settings['login_logo_path']) ? APP_URL.'/public/img/uploads/'.$settings['login_logo_path'] : '';
$loginBgUrl   = !empty($settings['login_bg_path'])   ? APP_URL.'/public/img/uploads/'.$settings['login_bg_path']  : '';
$faviconUrl   = !empty($settings['favicon_path'])    ? APP_URL.'/public/img/uploads/'.$settings['favicon_path']   : APP_URL.'/public/img/favicon.ico';
$pageTitle='Nova Senha'; ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="<?= e($faviconUrl) ?>" type="image/x-icon">
<title>Nova Senha — <?= e($siteName) ?></title>
<script>(function(){var t=localStorage.getItem("gestorpro_theme")||"dark";if(t==="light")document.documentElement.className="pre-light";})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head><body>
<?php if ($loginBgUrl): ?>
<div class="auth-bg" style="background-image:url('<?= e($loginBgUrl) ?>');"></div>
<?php endif; ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="font-size:36px;margin-bottom:8px">🔐</div>
      <?php if ($loginLogoUrl): ?><img src="<?= e($loginLogoUrl) ?>" class="auth-logo-img" alt="<?= e($siteName) ?>"><?php else: ?><div class="logo-big"><span><?= e($siteName) ?></span></div><?php endif; ?>
      <div class="logo-sub">Insira o código enviado ao e-mail</div>
    </div>
    <?php foreach (getFlash() as $t => $msgs): foreach ((array)$msgs as $msg): ?>
    <div class="alert alert-<?= e($t) ?>"><?= e($msg) ?></div>
    <?php endforeach; endforeach; ?>
    <form action="<?= APP_URL ?>/reset/post" method="POST">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-group">
        <label class="form-label" style="text-align:center;display:block">Código de 6 dígitos</label>
        <div class="otp-group">
          <?php for ($i=0;$i<6;$i++): ?>
          <input type="text" class="otp-input" maxlength="1" id="otp_<?= $i ?>">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="code" id="codeInput">
      </div>
      <div class="form-group">
        <label class="form-label">Nova Senha</label>
        <input type="password" name="new_password" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label class="form-label">Confirmar Nova Senha</label>
        <input type="password" name="new_password_confirm" class="form-control" placeholder="Repita a senha" required>
      </div>
      <button type="submit" class="btn btn-success btn-full" onclick="joinOtp()">Salvar Nova Senha</button>
    </form>
    <div class="auth-footer"><a href="<?= APP_URL ?>/forgot">← Reenviar código</a></div>
  </div>
</div>
<script>
function joinOtp() {
  const code = Array.from({length:6},(_,i)=>document.getElementById('otp_'+i).value).join('');
  document.getElementById('codeInput').value = code;
}
</script>
<script src="<?= APP_URL ?>/public/js/app.js"></script>
</body></html>
