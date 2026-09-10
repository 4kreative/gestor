<?php
// views/layouts/main.php
$user   = currentUser();
$db     = Database::getInstance();
$unread = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL",[$user['id']])->fetchColumn();

// Carregar notificações para o painel global do sino
try {
  $globalNotifs = $db->query(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30",
    [$user['id']]
  )->fetchAll();
} catch(\Exception $e) { $globalNotifs = []; }

// ── Badges de status para o menu ─────────────────────────────
try {
  // WhatsApp: instâncias desconectadas ou com erro
  $menuWaProblemas = (int)$db->query(
    "SELECT COUNT(*) FROM whatsapp_instances WHERE user_id=? AND status != 'connected'",
    [$user['id']]
  )->fetchColumn();
} catch(\Exception $e) { $menuWaProblemas = 0; }

try {
  // Contas Meta com token inválido
  $menuMetaErros = (int)$db->query(
    "SELECT COUNT(*) FROM ad_accounts WHERE user_id=? AND status = 'error'",
    [$user['id']]
  )->fetchColumn();
} catch(\Exception $e) { $menuMetaErros = 0; }

try {
  // Alertas com erro nas últimas 24h
  $menuAlertaErros = (int)$db->query(
    "SELECT COUNT(*) FROM alert_logs al
     JOIN alerts a ON a.id = al.alert_id
     WHERE a.user_id=? AND al.status='erro'
       AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    [$user['id']]
  )->fetchColumn();
} catch(\Exception $e) { $menuAlertaErros = 0; }

try {
  // Relatórios com erro nas últimas 24h
  $menuRelErros = (int)$db->query(
    "SELECT COUNT(*) FROM report_logs rl
     JOIN reports r ON r.id = rl.report_id
     WHERE r.user_id=? AND rl.status='erro'
       AND rl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    [$user['id']]
  )->fetchColumn();
} catch(\Exception $e) { $menuRelErros = 0; }

// Configurações visuais do sistema
try { $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(); } catch(\Exception $e) { $settings = []; }
$siteLogoUrl  = !empty($settings['logo_path'])    ? APP_URL.'/public/img/uploads/'.$settings['logo_path']    : '';
$siteName     = !empty($settings['site_name'])     ? $settings['site_name']     : APP_NAME;
$faviconUrl   = !empty($settings['favicon_path'])  ? APP_URL.'/public/img/uploads/'.$settings['favicon_path'] : APP_URL.'/public/img/favicon.ico';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
<title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($siteName) ?></title>
<link rel="icon" href="<?= e($faviconUrl) ?>" type="image/x-icon">

<!-- PWA -->
<link rel="manifest" href="<?= APP_URL ?>/public/manifest.json">
<meta name="theme-color" content="#0d0d0d">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GestorAds">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/public/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="192x192" href="<?= APP_URL ?>/public/icons/icon-192x192.png">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('<?= APP_URL ?>/public/sw.js')
      .then(function(reg) { console.log('SW registrado:', reg.scope); })
      .catch(function(err) { console.log('SW erro:', err); });
  });
}
</script>
<script>
// Aplica tema antes de renderizar para evitar flash
(function(){function gc(n){var m=document.cookie.match('(^|;)\s*'+n+'\s*=\s*([^;]+)');return m?m.pop():null;}var t=gc('gestorpro_theme')||localStorage.getItem('gestorpro_theme')||'dark';if(t==='light')document.documentElement.classList.add('pre-light');})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css?v=<?= filemtime(dirname(__DIR__,2).'/public/css/app.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="app-layout">

  <!-- ===== SIDEBAR ===== -->
  <!-- Overlay mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <?php if ($siteLogoUrl): ?>
        <img src="<?= e($siteLogoUrl) ?>" class="logo-img" alt="<?= e($siteName) ?>">
        <?php if (!empty($siteName) && $siteName !== APP_NAME): ?>
          <span class="logo-text" style="font-size:13px;font-weight:700;color:var(--txt);letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px"><?= e($siteName) ?></span>
        <?php endif; ?>
      <?php else: ?>
        <div class="logo-text"><?= e($siteName) ?></div>
      <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/dashboard" class="nav-item <?= str_contains($currentPage??'','dashboard')?'active':'' ?>">
        <span class="ni-ico">⊞</span> Dashboard
      </a>
      <a href="<?= APP_URL ?>/clients" class="nav-item <?= str_contains($currentPage??'','clients')?'active':'' ?>">
        <span class="ni-ico">👥</span> Clientes
      </a>
      <a href="<?= APP_URL ?>/reports" class="nav-item <?= str_contains($currentPage??'','reports')?'active':'' ?>" style="position:relative">
        <span class="ni-ico">📊</span> Relatórios
        <?php if ($menuRelErros > 0): ?>
        <span style="margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;line-height:16px"><?= $menuRelErros ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/alerts" class="nav-item <?= str_contains($currentPage??'','alerts')?'active':'' ?>" style="position:relative">
        <span class="ni-ico">🔔</span> Alertas
        <?php if ($menuAlertaErros > 0): ?>
        <span style="margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;line-height:16px"><?= $menuAlertaErros ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/ai" class="nav-item <?= ($currentPage??'')==='ai'?'active':'' ?>">
        <span class="ni-ico">✨</span> Análise IA
      </a>
      <a href="<?= APP_URL ?>/ai/history" class="nav-item <?= str_contains($currentPage??'','ai') && str_contains($_SERVER['REQUEST_URI']??'','history')?'active':'' ?>" style="font-size:12px;padding-left:32px;color:var(--txt3)">
        <span class="ni-ico">🕓</span> Histórico IA
      </a>
      <a href="<?= APP_URL ?>/ads-agent" class="nav-item <?= str_contains($currentPage??'','ads-agent')?'active':'' ?>">
        <span class="ni-ico">🤖</span> Agente ADS IA
      </a>
      <a href="<?= APP_URL ?>/accounts" class="nav-item <?= str_contains($currentPage??'','accounts')?'active':'' ?>" style="position:relative">
        <span class="ni-ico">📣</span> Contas de Anúncio
        <?php if ($menuMetaErros > 0): ?>
        <span style="margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;line-height:16px"><?= $menuMetaErros ?></span>
        <?php endif; ?>
      </a>

      <div class="nav-section">Conexões</div>
      <a href="<?= APP_URL ?>/whatsapp" class="nav-item <?= str_contains($currentPage??'','whatsapp')?'active':'' ?>" style="position:relative">
        <span class="ni-ico">💬</span> WhatsApp
        <?php if ($menuWaProblemas > 0): ?>
        <span style="margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;line-height:16px"><?= $menuWaProblemas ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/integrations" class="nav-item <?= str_contains($currentPage??'','integrations')?'active':'' ?>">
        <span class="ni-ico">🔗</span> Integrações
      </a>

      <div class="nav-section">Conta</div>
      <a href="<?= APP_URL ?>/templates" class="nav-item <?= str_contains($currentPage??'','templates')?'active':'' ?>">
        <span class="ni-ico">📄</span> Templates
      </a>
      <a href="<?= APP_URL ?>/variables" class="nav-item <?= str_contains($currentPage??'','variables')?'active':'' ?>">
        <span class="ni-ico">#</span> Variáveis
      </a>
      <a href="<?= APP_URL ?>/notifications" class="nav-item <?= str_contains($currentPage??'','notifications')?'active':'' ?>">
        <span class="ni-ico">🔕</span> Notificações
      </a>
      <a href="<?= APP_URL ?>/profile" class="nav-item <?= str_contains($currentPage??'','profile')?'active':'' ?>">
        <span class="ni-ico">👤</span> Perfil
      </a>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= APP_URL ?>/settings" class="nav-item <?= str_contains($currentPage??'','settings')?'active':'' ?>">
        <span class="ni-ico">⚙️</span> Configurações
      </a>
      <a href="<?= APP_URL ?>/admin" class="nav-item <?= str_contains($currentPage??'','admin')?'active':'' ?>">
        <span class="ni-ico">🛡️</span> Admin
      </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/logout" class="nav-item" style="color:#E74C3C">
        <span class="ni-ico">🚪</span> Sair
      </a>
      <a href="<?= APP_URL ?>/help" class="nav-item <?= str_contains($currentPage??'','help')?'active':'' ?>">
        <span class="ni-ico">❓</span> Ajuda
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-user" id="sidebarUserBtn">
        <?php if ($user['avatar']): ?>
          <img src="<?= APP_URL ?>/public/img/avatars/<?= e($user['avatar']) ?>" class="avatar" alt="">
        <?php else: ?>
          <div class="avatar"><?= initials($user['name']) ?></div>
        <?php endif; ?>
        <div class="user-info">
          <div class="user-name"><?= e(explode(' ',$user['name'])[0]) ?></div>
          <div class="user-role" title="<?= e($user['email']) ?>"><?= e($user['email']) ?></div>
        </div>
        <span class="material-icons-outlined" style="margin-left:auto;font-size:14px;color:var(--txt3)" id="sidebarUserArrow">expand_more</span>
      </div>
      <div id="userMenu" style="position:absolute;bottom:70px;left:10px;right:10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius2);overflow:hidden;box-shadow:var(--shadow);display:none;z-index:200">
        <a href="<?= APP_URL ?>/profile" class="dropdown-item"><span class="material-icons-outlined">person</span> Perfil</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= APP_URL ?>/settings" class="dropdown-item"><span class="material-icons-outlined">tune</span> Configurações</a>
        <?php endif; ?>
        <div class="dropdown-divider"></div>
        <a href="<?= APP_URL ?>/logout" class="dropdown-item danger"><span class="material-icons-outlined">logout</span> Sair</a>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <div class="main-content">

    <!-- Topbar -->
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons-outlined">menu</span></button>
        <div>
          <div class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
          <?php
          try {
            $tz  = new DateTimeZone('America/Sao_Paulo');
            $now = new DateTime('now', $tz);
          } catch(Exception $e) {
            $now = new DateTime('now');
          }
          $dias   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
          $meses  = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
          $dtLabel = $dias[(int)$now->format('w')].', '.(int)$now->format('d').' '.$meses[(int)$now->format('n')].' '.$now->format('Y').' - '.$now->format('H:i');
          ?>
          <div id="realtimeClock" style="font-size:10px;color:var(--txt3);margin-top:1px"><?= $dtLabel ?></div>
          <script>
          (function(){
            var dias=["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"];
            var meses=["","Jan","Fev","Mar","Abr","Mai","Jun","Jul","Ago","Set","Out","Nov","Dez"];
            function pad(n){return n<10?"0"+n:n;}
            function tick(){
              var now=new Date();
              var label=dias[now.getDay()]+", "+now.getDate()+" "+meses[now.getMonth()+1]+" "+now.getFullYear()+" - "+pad(now.getHours())+":"+pad(now.getMinutes())+":"+pad(now.getSeconds());
              var el=document.getElementById("realtimeClock");
              if(el) el.textContent=label;
            }
            tick();
            setInterval(tick,1000);
          })();
          </script>
        </div>
      </div>
      <div class="topbar-right">
        <!-- Sistema OK Badge -->
        <div id="topbar-health-badge" style="display:flex;align-items:center;gap:6px;background:rgba(26,188,156,.1);border:1px solid rgba(26,188,156,.25);border-radius:20px;padding:3px 11px;font-size:11px;font-weight:600;color:#1ABC9C;cursor:default;white-space:nowrap">
          <span id="topbar-health-dot" style="width:6px;height:6px;border-radius:50%;background:#1ABC9C;flex-shrink:0;display:inline-block;animation:topbar-pulse 2s infinite"></span>
          <span id="topbar-health-txt">Sistema OK</span>
        </div>
        <style>@keyframes topbar-pulse{0%,100%{opacity:1}50%{opacity:.35}}</style>


        <!-- Botão tema Claro/Escuro -->
        <button class="theme-btn" id="themeToggleBtn" title="Alternar tema">☀️</button>

        <!-- Sino de Notificações -->
        <div style="position:relative">
          <button class="topbar-icon" id="topNotifBtn" style="font-size:16px">
            🔔
            <?php if ($unread > 0): ?>
            <span id="gnotif-badge-dot" style="position:absolute;top:3px;right:3px;min-width:16px;height:16px;border-radius:50%;background:var(--danger);font-size:8px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg2);line-height:1;padding:0 2px"><?= min($unread,9) ?></span>
            <?php endif; ?>
          </button>
        </div>


        <!-- Avatar / Dropdown topbar -->
        <div style="position:relative">
          <button class="topbar-icon" id="topUserBtn" style="width:auto;padding:0 4px;gap:6px">
            <?php if ($user['avatar']): ?>
              <img src="<?= APP_URL ?>/public/img/avatars/<?= e($user['avatar']) ?>" class="avatar avatar-sm" alt="">
            <?php else: ?>
              <div class="avatar avatar-sm"><?= initials($user['name']) ?></div>
            <?php endif; ?>
          </button>
          <div id="topUserMenu" style="position:absolute;right:0;top:calc(100% + 6px);background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius2);min-width:200px;box-shadow:var(--shadow);overflow:hidden;display:none;z-index:300">
            <div style="padding:12px 14px 10px;border-bottom:1px solid var(--border)">
              <div style="font-size:13px;font-weight:600;color:var(--txt)"><?= e($user['name']) ?></div>
              <div style="font-size:11px;color:var(--txt2)"><?= e($user['email']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/profile" class="dropdown-item"><span class="material-icons-outlined">person</span> Perfil</a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= APP_URL ?>/settings" class="dropdown-item"><span class="material-icons-outlined">tune</span> Configurações</a>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <a href="<?= APP_URL ?>/logout" class="dropdown-item danger"><span class="material-icons-outlined">logout</span> Sair</a>
          </div>
        </div>
      </div>
    </header>

    <!-- Flash Messages -->
    <?php $flashes = getFlash(); ?>
    <?php if (!empty($flashes)): ?>
    <div style="padding:12px 24px 0">
      <?php foreach ($flashes as $type => $msgs): foreach ((array)$msgs as $msg): ?>
      <div class="alert alert-<?= e($type) ?>" data-auto-dismiss="4000">
        <span class="material-icons-outlined"><?= $type==='success'?'check_circle':($type==='danger'?'error':'info') ?></span>
        <span><?= e($msg) ?></span>
      </div>
      <?php endforeach; endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="page-body">
      <?= $pageContent ?? '' ?>
    </main>

  </div>
</div>

<script src="<?= APP_URL ?>/public/js/variables.js?v=<?= filemtime(dirname(__DIR__,2).'/public/js/variables.js') ?>"></script>
<script src="<?= APP_URL ?>/public/js/app.js?v=<?= filemtime(dirname(__DIR__,2).'/public/js/app.js') ?>"></script>

<!-- ===== PAINEL GLOBAL DE NOTIFICAÇÕES ===== -->
<style>
.gnotif-panel{position:fixed;top:58px;right:16px;width:310px;background:var(--bg2);border:1px solid var(--border2);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.35);z-index:9999;display:none;flex-direction:column;max-height:500px;overflow:hidden}
.gnotif-panel.open{display:flex}
.gnotif-hd{padding:12px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.gnotif-hd-title{font-size:13px;font-weight:700;color:var(--txt)}
.gnotif-actions{display:flex;align-items:center;gap:10px}
.gnotif-sw{width:30px;height:16px;border-radius:8px;background:var(--accent);cursor:pointer;position:relative;transition:background .15s;flex-shrink:0;border:none}
.gnotif-sw.off{background:var(--border2)}
.gnotif-sw-k{position:absolute;top:2px;left:16px;width:12px;height:12px;background:#fff;border-radius:50%;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.3);pointer-events:none}
.gnotif-sw.off .gnotif-sw-k{left:2px}
.gnotif-clear{background:none;border:none;cursor:pointer;font-size:11px;color:var(--txt3);padding:0;font-family:var(--font)}
.gnotif-clear:hover{color:var(--txt2)}
.gnotif-body{overflow-y:auto;flex:1}
.gnotif-item{padding:11px 14px;border-bottom:1px solid var(--border);display:flex;gap:9px;align-items:flex-start;cursor:default;transition:background .12s}
.gnotif-item:last-child{border-bottom:none}
.gnotif-item:hover{background:var(--bg3)}
.gnotif-item.unread{background:rgba(91,141,239,.04)}
.gnotif-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.gnotif-msg{font-size:11px;color:var(--txt2);line-height:1.55;flex:1;min-width:0}
.gnotif-time{font-size:9px;color:var(--txt3);margin-top:2px}
.gnotif-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.gnotif-empty{padding:32px 16px;text-align:center;font-size:12px;color:var(--txt3)}
.gnotif-footer{padding:9px 14px;border-top:1px solid var(--border);text-align:center;flex-shrink:0}
.gnotif-footer a{font-size:11px;color:var(--accent);text-decoration:none}
.gnotif-footer a:hover{text-decoration:underline}
</style>

<div class="gnotif-panel" id="gnotif-panel">
  <div class="gnotif-hd">
    <span class="gnotif-hd-title">🔔 Notificações</span>
    <div class="gnotif-actions">
      <div style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--txt3)">
        <button class="gnotif-sw" id="gnotif-sw" onclick="gNotifToggleSw()"><div class="gnotif-sw-k"></div></button>
        <span id="gnotif-sw-lbl">Ativas</span>
      </div>
      <button class="gnotif-clear" onclick="gNotifClear()">Limpar</button>
    </div>
  </div>
  <div class="gnotif-body" id="gnotif-body">
    <?php if(empty($globalNotifs)): ?>
    <div class="gnotif-empty">Nenhuma notificação</div>
    <?php else: foreach($globalNotifs as $n):
      $ico = match($n['type']??'info'){
        'success'=>'📤','error'=>'⚠️','warning'=>'🔔','ai'=>'✨','alert'=>'🚨',default=>'💬'
      };
      $icoBg = match($n['type']??'info'){
        'success'=>'rgba(26,188,156,.15)','error'=>'rgba(231,76,60,.15)',
        'warning'=>'rgba(243,156,18,.15)','ai'=>'rgba(91,141,239,.15)',
        'alert'=>'rgba(231,76,60,.15)',default=>'rgba(91,141,239,.1)'
      };
      $isUnread = empty($n['read_at']);
      $diff = time() - strtotime($n['created_at']??'now');
      if($diff < 60) $ago = 'agora';
      elseif($diff < 3600) $ago = 'há '.floor($diff/60).'min';
      elseif($diff < 86400) $ago = 'há '.floor($diff/3600).'h';
      else $ago = date('d/m/Y', strtotime($n['created_at']));
    ?>
    <div class="gnotif-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= $n['id'] ?>">
      <div class="gnotif-ico" style="background:<?= $icoBg ?>"><?= $ico ?></div>
      <div style="flex:1;min-width:0">
        <?php if(!empty($n['title'])): ?>
        <div style="font-size:11px;font-weight:600;color:var(--txt);margin-bottom:2px"><?= e($n['title']) ?></div>
        <?php endif; ?>
        <div class="gnotif-msg"><?= e($n['body']??$n['message']??'') ?></div>
        <div class="gnotif-time"><?= $ago ?></div>
      </div>
      <?php if($isUnread): ?><div class="gnotif-dot"></div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="gnotif-footer">
    <a href="<?= APP_URL ?>/notifications">Ver todas as notificações →</a>
  </div>
</div>

<script>
var gNotifOpen  = false;
var gNotifOn    = true;

function gNotifToggle(){
  gNotifOpen = !gNotifOpen;
  document.getElementById('gnotif-panel').classList.toggle('open', gNotifOpen);
  // Marcar como lidas via AJAX ao abrir
  if(gNotifOpen){
    fetch('<?= APP_URL ?>/notifications/read', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':'<?= e($_SESSION['csrf_token']) ?>'},
      body:'_token=<?= urlencode($_SESSION['csrf_token']) ?>'
    }).catch(function(){});
    // Remove badge do sino
    var dot = document.getElementById('gnotif-badge-dot');
    if(dot) dot.style.display='none';
  }
}

function gNotifToggleSw(){
  gNotifOn = !gNotifOn;
  var sw = document.getElementById('gnotif-sw');
  sw.classList.toggle('off', !gNotifOn);
  document.getElementById('gnotif-sw-lbl').textContent = gNotifOn ? 'Ativas' : 'Pausadas';
}

function gNotifClear(){
  document.getElementById('gnotif-body').innerHTML = '<div class="gnotif-empty">Nenhuma notificação</div>';
  var dot = document.getElementById('gnotif-badge-dot');
  if(dot) dot.style.display='none';
}

// Fechar ao clicar fora
document.addEventListener('click', function(e){
  var panel = document.getElementById('gnotif-panel');
  var btn   = document.getElementById('topNotifBtn');
  if(gNotifOpen && panel && !panel.contains(e.target) && btn && !btn.contains(e.target)){
    gNotifOpen = false;
    panel.classList.remove('open');
  }
});

// Topbar sino aponta para gNotifToggle em todas as páginas
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('topNotifBtn');
  if(btn){
    btn.onclick = function(e){ e.stopPropagation(); gNotifToggle(); };
  }

});
</script>
<!-- dropdowns handled by app.js -->
  <!-- ═══ MODAL LIMPAR CACHE ═══ -->
  <?php if (($user['role'] ?? '') === 'admin'): ?>
  <div id="cacheModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:900;align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:480px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden">
      <div style="padding:24px;overflow-y:auto;flex:1">
      <!-- Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--txt)">🗑️ Limpar Cache</div>
          <div style="font-size:12px;color:var(--txt3);margin-top:2px">Selecione o que deseja limpar</div>
        </div>
        <button onclick="closeCacheModal()" style="background:none;border:none;color:var(--txt3);cursor:pointer;font-size:20px;padding:4px">✕</button>
      </div>

      <!-- Status atual -->
      <div id="cacheStatus" style="background:var(--bg3);border-radius:var(--radius);padding:12px;margin-bottom:16px;font-size:12px;color:var(--txt2)">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="animation:spin .8s linear infinite;display:inline-block">⏳</span>
          Carregando status...
        </div>
      </div>

      <!-- Opções -->
      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px">
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_opcache" checked style="accent-color:var(--accent)">
          <span>⚡ OPcache PHP</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto">Bytecode compilado</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_session" checked style="accent-color:var(--accent)">
          <span>🔐 Cache de sessão</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto">Plano, usuário, etc.</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_errorlog" checked style="accent-color:var(--accent)">
          <span>📋 Log de erros PHP</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto" id="logSizeLabel">—</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_logs" checked style="accent-color:var(--accent)">
          <span>🗑️ Logs e Erros do Sistema</span>
          <span id="cc_logs_count" style="font-size:11px;color:var(--txt3);margin-left:auto">Erros de envio/alertas</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_campaigns" style="accent-color:var(--accent)">
          <span>📊 Cache de campanhas</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto">Dados em sessão</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_db" style="accent-color:var(--accent)">
          <span>🗄️ Limpeza do banco</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto">Notif. e logs antigos</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--txt)">
          <input type="checkbox" id="cc_localstorage" style="accent-color:var(--accent)">
          <span>💾 Layout do dashboard</span>
          <span style="font-size:11px;color:var(--txt3);margin-left:auto">Posição dos cards</span>
        </label>
      </div>

      <!-- Resultado -->
      <div id="cacheResult" style="display:none;background:var(--bg3);border-radius:var(--radius);padding:12px;margin-bottom:16px;font-size:12px"></div>

      <!-- Botões -->
      <div style="display:flex;gap:10px">
        <button onclick="closeCacheModal()" style="flex:1;padding:10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt2);cursor:pointer;font-size:13px;font-family:var(--font)">Cancelar</button>
        <button onclick="executeClearCache()" id="ccBtn" style="flex:2;padding:10px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius);cursor:pointer;font-size:13px;font-weight:700;font-family:var(--font)">🗑️ Limpar selecionados</button>
      </div>
      </div>
    </div>
  </div>

  <script>
  var CSRF_TOKEN_CACHE = '<?= $_SESSION['csrf_token'] ?? '' ?>';
  var APP_URL_CACHE = '<?= APP_URL ?>';
  var cacheModalJustOpened = false;

  function openCacheModal(){
    document.getElementById('cacheModal').style.display='flex';
    document.getElementById('cacheResult').style.display='none';
    loadCacheStatus();
    cacheModalJustOpened = true;
    setTimeout(function(){ cacheModalJustOpened = false; }, 300);
  }
  function closeCacheModal(){
    document.getElementById('cacheModal').style.display='none';
  }
  document.getElementById('cacheModal').addEventListener('click', function(e){
    if(cacheModalJustOpened) return;
    if(e.target===this) closeCacheModal();
  });

  function loadCacheStatus(){
    fetch(APP_URL_CACHE+'/clear_cache_status.php', {credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        var html = '';
        if(d.opcache_stats && d.opcache_stats.enabled){
          html += '<div style="display:flex;gap:16px;flex-wrap:wrap">';
          html += '<span>⚡ OPcache: <strong style="color:var(--success)">Ativo</strong></span>';
          html += '<span>📦 Scripts: <strong>'+(d.opcache_stats.scripts_cached||0)+'</strong></span>';
          html += '<span>💾 Memória: <strong>'+(d.opcache_stats.memory_used||0)+' MB</strong></span>';
          html += '</div>';
        } else {
          html += '<span style="color:var(--txt3)">⚡ OPcache: não disponível</span><br>';
        }
        var logKb = d.error_log_kb || 0;
        var logColor = logKb > 10 ? 'var(--warn)' : 'var(--success)';
        html += '<div style="margin-top:6px">📋 Log de erros: <strong style="color:'+logColor+'">'+logKb+' KB</strong>';
        if(logKb > 10) html += ' <span style="color:var(--warn)">— recomendado limpar</span>';
        html += '</div>';
        var logsErros = d.logs_erros || 0;
        if(logsErros > 0) {
          html += '<div style="margin-top:4px">🗑️ Logs de erro: <strong style="color:var(--warn)">'+logsErros+' registros</strong></div>';
          var elCount = document.getElementById('cc_logs_count');
          if(elCount) elCount.textContent = logsErros + ' erros';
        }
        document.getElementById('cacheStatus').innerHTML = html;
        document.getElementById('logSizeLabel').textContent = logKb + ' KB';
        if(logKb > 0) document.getElementById('cc_errorlog').checked = true;
      })
      .catch(function(){
        document.getElementById('cacheStatus').innerHTML = '<span style="color:var(--txt3)">Não foi possível carregar status.</span>';
      });
  }

  function executeClearCache(){
    var btn = document.getElementById('ccBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Limpando...';

    // localStorage
    if(document.getElementById('cc_localstorage').checked){
      try{localStorage.removeItem('gestorads_dashboard_order');}catch(e){}
    }

    var fd = new FormData();
    fd.append('_token', CSRF_TOKEN_CACHE);
    fd.append('type', 'all');
    // Desabilita tipos não selecionados
    if(!document.getElementById('cc_opcache').checked)   fd.append('skip_opcache','1');
    if(!document.getElementById('cc_session').checked)   fd.append('skip_session','1');
    if(!document.getElementById('cc_errorlog').checked)  fd.append('skip_errorlog','1');
    if(!document.getElementById('cc_campaigns').checked) fd.append('skip_campaigns','1');
    if(!document.getElementById('cc_db').checked)        fd.append('skip_db','1');
    if(!document.getElementById('cc_logs').checked)      fd.append('skip_logs','1');

    fetch(APP_URL_CACHE+'/clear_cache.php', {method:'POST', body:fd, credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        var html = d.ok
          ? '<div style="color:var(--success);font-weight:700;margin-bottom:8px">✅ Cache limpo com sucesso!</div>'
          : '<div style="color:var(--warn);font-weight:700;margin-bottom:8px">⚠️ Concluído com avisos</div>';
        if(d.results){
          Object.entries(d.results).forEach(function(e){
            html += '<div style="padding:2px 0">• <strong>'+e[0]+':</strong> '+e[1]+'</div>';
          });
        }
        if(d.errors && d.errors.length){
          d.errors.forEach(function(e){
            html += '<div style="color:var(--danger);padding:2px 0">⚠ '+e+'</div>';
          });
        }
        if(document.getElementById('cc_localstorage').checked){
          html += '<div style="padding:2px 0">• <strong>localStorage:</strong> layout do dashboard resetado</div>';
        }
        html += '<div style="color:var(--txt3);margin-top:6px;font-size:11px">⏰ '+d.time+'</div>';
        var res = document.getElementById('cacheResult');
        res.style.display='block';
        res.innerHTML = html;
        btn.disabled = false;
        btn.textContent = '✅ Feito! Limpar novamente';
        // Atualiza status
        loadCacheStatus();
      })
      .catch(function(e){
        document.getElementById('cacheResult').style.display='block';
        document.getElementById('cacheResult').innerHTML='<span style="color:var(--danger)">❌ Erro: '+e.message+'</span>';
        btn.disabled = false;
        btn.textContent = '🗑️ Limpar selecionados';
      });
  }
  </script>
  <?php endif; ?>

  <!-- ═══ BOTÃO + WIDGETS E PAINEL LATERAL (só no dashboard) ═══ -->
  <?php if(($currentPage??'')==='dashboard'): ?>
  <button id="db-add-btn" onclick="toggleWidgetPanel()" title="Organizar widgets do dashboard">
    <span class="material-icons-outlined" style="font-size:22px">add</span>
  </button>
  <div id="db-panel-overlay" onclick="closeWidgetPanel()"></div>
  <div id="db-add-panel">
    <div class="panel-header">
      <div class="panel-title">🧩 Widgets</div>
      <button class="panel-close" onclick="closeWidgetPanel()">✕</button>
    </div>
    <div class="panel-body">
      <div class="panel-sub">Clique para mostrar/ocultar · Arraste no dashboard para mover</div>
      <div id="panel-widget-list"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Bottom Nav Mobile -->
  <nav class="bottom-nav" id="bottomNav">
    <a href="<?= APP_URL ?>/dashboard" class="bottom-nav-item <?= str_contains($currentPage??'','dashboard')?'active':'' ?>">
      <span class="material-icons-outlined">dashboard</span>
      <span>Dashboard</span>
    </a>
    <a href="<?= APP_URL ?>/reports" class="bottom-nav-item <?= str_contains($currentPage??'','reports')?'active':'' ?>">
      <span class="material-icons-outlined">assessment</span>
      <span>Relatórios</span>
    </a>
    <a href="<?= APP_URL ?>/clients" class="bottom-nav-item <?= str_contains($currentPage??'','clients')?'active':'' ?>">
      <span class="material-icons-outlined">people</span>
      <span>Clientes</span>
    </a>
    <a href="<?= APP_URL ?>/alerts" class="bottom-nav-item <?= str_contains($currentPage??'','alerts')?'active':'' ?>">
      <span class="material-icons-outlined">notifications</span>
      <span>Alertas</span>
    </a>
    <button class="bottom-nav-item" onclick="toggleSidebar()">
      <span class="material-icons-outlined">menu</span>
      <span>Menu</span>
    </button>
  </nav>

</body>
</html>
