<?php
$db       = Database::getInstance();
try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Exception $e) { $settings = []; }
$siteName     = !empty($settings['site_name'])       ? $settings['site_name']     : APP_NAME;
$loginLogoUrl = !empty($settings['login_logo_path']) ? APP_URL.'/public/img/uploads/'.$settings['login_logo_path'] : '';
$loginBgUrl   = !empty($settings['login_bg_path'])   ? APP_URL.'/public/img/uploads/'.$settings['login_bg_path']  : '';
$faviconUrl   = !empty($settings['favicon_path'])    ? APP_URL.'/public/img/uploads/'.$settings['favicon_path']   : APP_URL.'/public/img/favicon.ico';
$pageTitle='Criar Conta'; ?><!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="<?= e($faviconUrl) ?>" type="image/x-icon">
<title>Criar Conta — <?= e($siteName) ?></title>
<script>(function(){var t=localStorage.getItem("gestorpro_theme")||"dark";if(t==="light")document.documentElement.className="pre-light";})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined">
</head><body>
<?php if ($loginBgUrl): ?>
<div class="auth-bg" style="background-image:url('<?= e($loginBgUrl) ?>');"></div>
<?php endif; ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <?php if ($loginLogoUrl): ?><img src="<?= e($loginLogoUrl) ?>" class="auth-logo-img" alt="<?= e($siteName) ?>"><?php else: ?><div class="logo-big"><span><?= e($siteName) ?></span></div><?php endif; ?>
      <div class="logo-sub">Crie sua conta gratuitamente</div>
    </div>
    <?php foreach (getFlash() as $type => $msgs): foreach ((array)$msgs as $msg): ?>
    <div class="alert alert-<?= e($type) ?>" data-auto-dismiss><span class="material-icons-outlined"><?= $type==='success'?'check_circle':'error' ?></span><span><?= e($msg) ?></span></div>
    <?php endforeach; endforeach; ?>
    <form action="<?= APP_URL ?>/register/post" method="POST">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nome <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="João" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sobrenome</label>
          <input type="text" name="lastname" class="form-control" placeholder="Silva">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">E-mail <span class="req">*</span></label>
        <input type="email" name="email" class="form-control" placeholder="joao@email.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Senha <span class="req">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label class="form-label">Confirmar Senha <span class="req">*</span></label>
        <input type="password" name="password_confirm" class="form-control" placeholder="Repita a senha" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">Criar Conta Grátis</button>
    </form>
    <div class="auth-footer">Já tem conta? <a href="<?= APP_URL ?>/login">Fazer login</a></div>
  </div>
</div>
<script src="<?= APP_URL ?>/public/js/app.js"></script>
</body></html>
