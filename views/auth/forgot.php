<?php
$db       = Database::getInstance();
try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Exception $e) { $settings = []; }
$siteName     = !empty($settings['site_name'])       ? $settings['site_name']     : APP_NAME;
$loginLogoUrl = !empty($settings['login_logo_path']) ? APP_URL.'/public/img/uploads/'.$settings['login_logo_path'] : '';
$loginBgUrl   = !empty($settings['login_bg_path'])   ? APP_URL.'/public/img/uploads/'.$settings['login_bg_path']  : '';
$faviconUrl   = !empty($settings['favicon_path'])    ? APP_URL.'/public/img/uploads/'.$settings['favicon_path']   : APP_URL.'/public/img/favicon.ico';
$pageTitle='Recuperar Senha'; ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="<?= e($faviconUrl) ?>" type="image/x-icon">
<title>Recuperar Senha — <?= e($siteName) ?></title>
<script>(function(){var t=localStorage.getItem("gestorpro_theme")||"dark";if(t==="light")document.documentElement.className="pre-light";})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head><body>
<?php if ($loginBgUrl): ?>
<div class="auth-bg" style="background-image:url('<?= e($loginBgUrl) ?>');"></div>
<?php endif; ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="font-size:36px;margin-bottom:8px">✉️</div>
      <?php if ($loginLogoUrl): ?><img src="<?= e($loginLogoUrl) ?>" class="auth-logo-img" alt="<?= e($siteName) ?>"><?php else: ?><div class="logo-big"><span><?= e($siteName) ?></span></div><?php endif; ?>
      <div class="logo-sub">Recuperação de Senha</div>
    </div>
    <?php foreach (getFlash() as $t => $msgs): foreach ((array)$msgs as $msg): ?>
    <div class="alert alert-<?= e($t) ?>"><?= e($msg) ?></div>
    <?php endforeach; endforeach; ?>
    <form action="<?= APP_URL ?>/forgot/post" method="POST">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-group">
        <label class="form-label">E-mail Cadastrado</label>
        <input type="email" name="email" class="form-control" placeholder="seu@email.com" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Enviar Código</button>
    </form>
    <div class="auth-footer"><a href="<?= APP_URL ?>/login">← Voltar ao login</a></div>
  </div>
</div></body></html>
