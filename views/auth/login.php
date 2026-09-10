<?php
$db = Database::getInstance();
try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Exception $e) { $settings = []; }
$siteName = $settings['site_name'] ?? '';
$loginBgUrl   = !empty($settings['login_bg_path'])   ? APP_URL.'/public/img/uploads/'.$settings['login_bg_path']   : '';
$faviconUrl   = !empty($settings['favicon_path'])    ? APP_URL.'/public/img/uploads/'.$settings['favicon_path']    : APP_URL.'/public/img/favicon.ico';
$siteLogoUrl  = !empty($settings['logo_path'])       ? APP_URL.'/public/img/uploads/'.$settings['logo_path']      : '';
$loginLogoUrl = !empty($settings['login_logo_path']) ? APP_URL.'/public/img/uploads/'.$settings['login_logo_path'] : '';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login<?= !empty($siteName) ? ' — '.e($siteName) : '' ?></title>
<link rel="icon" href="<?= e($faviconUrl) ?>" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;}

/* Fundo */
.bg{position:fixed;inset:0;z-index:0;
  <?php if($loginBgUrl):?>background:url('<?=e($loginBgUrl)?>') center/cover no-repeat;
  <?php else:?>background:linear-gradient(160deg,#1a2030 0%,#2a3548 50%,#1e2a3a 100%);<?php endif;?>
}
.bg::after{content:'';position:absolute;inset:0;background:rgba(0,0,0,0.25);}

/* Card */
.card{
  position:relative;z-index:1;
  width:340px;
  background:rgba(40,50,65,0.72);
  backdrop-filter:blur(32px);-webkit-backdrop-filter:blur(32px);
  border:1px solid rgba(255,255,255,0.13);
  border-radius:22px;
  padding:40px 32px 30px;
  box-shadow:0 8px 40px rgba(0,0,0,0.35);
}

/* Logo */
.logo-circle{
  width:84px;height:84px;border-radius:50%;
  background:#fff;
  border:2px solid rgba(255,255,255,0.5);
  box-shadow:0 2px 12px rgba(0,0,0,0.18);
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;margin:0 auto 16px;
}
.logo-circle img{width:72px;height:72px;object-fit:contain;}
.logo-name{font-size:20px;font-weight:700;color:#fff;text-align:center;letter-spacing:-.3px;margin-bottom:4px;}
.logo-sub{font-size:12px;color:rgba(255,255,255,0.6);text-align:center;margin-bottom:26px;}

/* Alertas */
.alert{border-radius:8px;padding:9px 12px;font-size:13px;margin-bottom:14px;display:flex;align-items:center;gap:7px;}
.alert-err{background:rgba(220,50,50,0.22);border:1px solid rgba(220,80,80,0.4);color:#fca5a5;}
.alert-ok{background:rgba(40,180,100,0.2);border:1px solid rgba(40,180,100,0.4);color:#86efac;}

/* Campos */
.field{position:relative;margin-bottom:13px;}
.field-icon{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);
  display:flex;align-items:center;
  color:rgba(255,255,255,0.55);
  pointer-events:none;
}
.field-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.field input{
  width:100%;
  background:rgba(255,255,255,0.1);
  border:1px solid rgba(255,255,255,0.22);
  border-radius:9px;
  padding:11px 38px;
  font-size:14px;color:#fff;outline:none;
  transition:border-color .18s,background .18s;
}
.field input::placeholder{color:rgba(255,255,255,0.38);}
.field input:focus{border-color:rgba(255,255,255,0.6);background:rgba(255,255,255,0.15);}
.eye-btn{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;padding:0;
  color:rgba(255,255,255,0.45);display:flex;align-items:center;
}
.eye-btn:hover{color:rgba(255,255,255,0.85);}
.eye-btn svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* Remember / Forgot */
.row-mid{display:flex;align-items:center;justify-content:space-between;margin:14px 0 20px;}
.row-mid label{display:flex;align-items:center;gap:7px;font-size:13px;color:rgba(255,255,255,0.65);cursor:pointer;}
.row-mid input[type=checkbox]{width:14px;height:14px;accent-color:rgba(255,255,255,0.8);cursor:pointer;}
.row-mid a{font-size:12px;color:rgba(255,255,255,0.55);text-decoration:none;}
.row-mid a:hover{color:#fff;}

/* Botão */
.btn-login{
  width:100%;padding:13px;
  background:rgba(255,255,255,0.1);
  border:1.5px solid rgba(255,255,255,0.6);
  border-radius:10px;
  font-size:12px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;
  color:#fff;cursor:pointer;
  transition:background .18s,border-color .18s;
}
.btn-login:hover{background:rgba(255,255,255,0.22);border-color:rgba(255,255,255,0.9);}
.btn-login:active{transform:scale(.99);}

/* Footer */
.footer{
  position:fixed;bottom:14px;left:0;right:0;
  text-align:center;font-size:11px;
  color:rgba(255,255,255,0.35);
  z-index:2;
}
</style>
</head>
<body>
<div class="bg"></div>
<div class="card">

  <!-- Logo: usa login_logo_path se definido, senão logo_path, senão inicial do nome -->
  <div class="logo-circle">
    <?php if($loginLogoUrl): ?>
    <img src="<?=e($loginLogoUrl)?>" alt="<?=e($siteName)?>">
    <?php elseif($siteLogoUrl): ?>
    <img src="<?=e($siteLogoUrl)?>" alt="<?=e($siteName)?>">
    <?php else: ?>
    <span style="font-size:26px;font-weight:700;color:#334;letter-spacing:-1px"><?=e(mb_substr($siteName,0,1))?></span>
    <?php endif; ?>
  </div>
  <div class="logo-name"><?=e($siteName)?></div>
  <div class="logo-sub">Painel de Relatórios de Tráfego</div>

  <!-- Flash -->
  <?php foreach(getFlash() as $type=>$msgs): foreach((array)$msgs as $msg): ?>
  <div class="alert alert-<?=$type==='success'?'ok':'err'?>"><?=$type==='success'?'✓':'⚠'?> <?=e($msg)?></div>
  <?php endforeach;endforeach; ?>

  <!-- Form -->
  <form action="<?=APP_URL?>/login/post" method="POST">
    <input type="hidden" name="_token" value="<?=e($_SESSION['csrf_token'])?>">

    <!-- Email -->
    <div class="field">
      <span class="field-icon">
        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
      </span>
      <input type="email" name="email" placeholder="E-mail" value="<?=e($_POST['email']??'')?>" required autofocus>
    </div>

    <!-- Senha -->
    <div class="field">
      <span class="field-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </span>
      <input type="password" name="password" id="pwdField" placeholder="Senha" required>
      <button type="button" class="eye-btn" id="eyeBtn">
        <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>

    <!-- Lembrar / Esqueci -->
    <div class="row-mid">
      <label><input type="checkbox" name="remember"> Lembrar de mim</label>
      <a href="<?=APP_URL?>/forgot">Esqueci a senha</a>
    </div>

    <button type="submit" class="btn-login">Entrar</button>
  </form>


</div>

<div class="footer">©<?= !empty($siteName) ? ' '.e($siteName) : '' ?> — Todos os direitos reservados</div>

<script>
var p=document.getElementById('pwdField'),b=document.getElementById('eyeBtn');
var eyeOpen='<path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>';
var eyeOff='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
b.addEventListener('click',function(){
  var show=p.type==='password';
  p.type=show?'text':'password';
  document.getElementById('eyeIcon').innerHTML=show?eyeOff:eyeOpen;
});
</script>
</body>
</html>
