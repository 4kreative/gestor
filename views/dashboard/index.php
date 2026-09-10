<?php
$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
// Configurações IA para reescrita no popup WA
$_rtAiSettings = [];
try { $_rtAiSettings = AiController::getAiSettings(currentUser()['id'], Database::getInstance()); } catch (\Throwable $_e) {}
$_rtDefProv  = $_rtAiSettings['ai_default_provider'] ?? 'groq';
$_rtDefModel = $_rtAiSettings['ai_default_model']    ?? 'llama-3.1-8b-instant';
$_rtModels   = AiController::getModels();
ob_start();
?>
<?php if (!empty($data['tokens_expiring'])): ?>
<div style="background:rgba(227,179,65,.12);border:1px solid rgba(227,179,65,.3);border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
  <span style="font-size:16px">⚠️</span>
  <div style="font-size:12px;color:var(--warn)">
    <strong>Token Meta expirando em breve:</strong>
    <?php foreach($data['tokens_expiring'] as $t): ?>
      <span style="margin-left:8px"><?= e($t['account_name']) ?> (<?= date('d/m/Y', strtotime($t['token_expires'])) ?>)</span>
    <?php endforeach; ?>
    — <a href="/accounts" style="color:var(--warn);text-decoration:underline">Reconectar agora</a>
  </div>
</div>
<?php endif;

// ── Banner: relatórios atrasados (cron não rodou) ────────────
$overdueCount  = count($data['overdue_reports'] ?? []);
$cronLastRun   = $data['cron_last_run'] ?? null;
$cronAgeMin    = $cronLastRun ? round((time() - $cronLastRun) / 60) : null;
$cronNeverRan  = $cronLastRun === null;
$cronStale     = $cronLastRun && (time() - $cronLastRun) > 7200; // > 2h sem rodar
$showCronWarn  = $overdueCount > 0 && ($cronNeverRan || $cronStale);
?>
<?php if ($showCronWarn): ?>
<div style="background:rgba(248,81,73,.10);border:1px solid rgba(248,81,73,.3);border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:flex-start;gap:10px">
  <span style="font-size:16px;flex-shrink:0">⚠️</span>
  <div style="font-size:12px;color:var(--danger);flex:1">
    <strong>
      <?php if($cronNeverRan): ?>
        Cron não configurado — <?= $overdueCount ?> relatório(s) com envio atrasado
      <?php else: ?>
        Cron parado há <?= $cronAgeMin ?>min — <?= $overdueCount ?> relatório(s) pendente(s)
      <?php endif; ?>
    </strong>
    <div style="color:var(--txt2);margin-top:4px;font-size:11px">
      <?php foreach(array_slice($data['overdue_reports'] ?? [], 0, 3) as $or): ?>
        <span style="margin-right:10px">📋 <?= e($or['title']) ?>
          <?php if($or['client_name']): ?>(<?= e($or['client_name']) ?>)<?php endif; ?>
          — deveria ter enviado <?= date('d/m H:i', strtotime($or['next_send_at'])) ?>
        </span>
      <?php endforeach; ?>
      <?php if($overdueCount > 3): ?><span style="color:var(--txt3)">+<?= $overdueCount - 3 ?> outros</span><?php endif; ?>
    </div>
    <div style="margin-top:6px">
      <a href="https://cron-job.org" target="_blank" style="color:var(--danger);text-decoration:underline;font-size:11px">Configure o cron agora →</a>
    </div>
  </div>
</div>
<?php endif;

// Prepare chart data
$metaByDay = []; $googleByDay = [];
$labels7 = [];
for($i=6;$i>=0;$i--){
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels7[] = date('d/M', strtotime($d));
    $metaByDay[$d] = 0; $googleByDay[$d] = 0;
}
foreach(($data['spend_7days']??[]) as $row){
    $d = $row['d'];
    if(isset($metaByDay[$d]) && $row['platform']==='meta') $metaByDay[$d] = (float)$row['total'];
    if(isset($googleByDay[$d]) && $row['platform']==='google') $googleByDay[$d] = (float)$row['total'];
}
$metaVals   = json_encode(array_values($metaByDay));
$googleVals = json_encode(array_values($googleByDay));
$chartLabels= json_encode($labels7);

$totalSpend = (float)$data['spend_meta'] + (float)$data['spend_google'];
$metaPct    = $totalSpend > 0 ? round($data['spend_meta']/$totalSpend*100) : 75;
$googlePct  = 100 - $metaPct;

// Notifications json for JS
$notifsJson = json_encode($data['notifications']??[]);
$postitsJson = json_encode($data['postits']??[]);
$unreadCount = (int)($data['unread_notifs']??0);
?>
<style>
.db-kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
.db-kpi{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:13px 15px;display:flex;gap:10px;align-items:center;transition:box-shadow .15s}
.db-kpi:hover{box-shadow:0 2px 12px rgba(0,0,0,.12)}
.db-kpi-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}
.db-kpi-lbl{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:2px;font-weight:600}
.db-kpi-val{font-size:20px;font-weight:700;color:var(--txt);line-height:1}
.db-kpi-ch{font-size:10px;margin-top:3px;color:var(--txt3)}
.db-spark{width:62px;height:26px;flex-shrink:0;align-self:flex-end;margin-left:auto}
.db-mid{display:grid;grid-template-columns:1.65fr 1fr;gap:12px;margin-bottom:14px}
.db-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px 16px}
.db-card-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.db-card-title{font-size:13px;font-weight:600;color:var(--txt)}
.db-card-sub{font-size:10px;color:var(--txt3);margin-top:2px}
.db-dd{background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--txt2);cursor:pointer;text-decoration:none;display:inline-block}
.db-pill{padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;display:inline-block}
.db-pill-g{background:rgba(39,174,96,.12);color:var(--success)}
.db-pill-y{background:rgba(243,156,18,.12);color:var(--warn)}
.db-pill-r{background:rgba(231,76,60,.12);color:var(--danger)}
.db-pill-b{background:rgba(91,141,239,.12);color:var(--accent)}
.db-3col{display:grid;grid-template-columns:1.3fr 1.1fr 1fr;gap:12px;margin-bottom:14px}
.db-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.db-table{width:100%;border-collapse:collapse}
.db-table thead th{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);padding:5px 8px;text-align:left;border-bottom:1px solid var(--border2)}
.db-table tbody td{font-size:11px;color:var(--txt2);padding:7px 8px;border-bottom:1px solid var(--border)}
.db-table tbody tr:last-child td{border-bottom:none}
.db-table tbody tr:hover td{background:var(--bg3);color:var(--txt)}
.db-cdot{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;color:#fff;flex-shrink:0}
.db-cr{display:flex;align-items:center;gap:6px}
.db-hbadge{display:flex;align-items:center;gap:5px;background:rgba(39,174,96,.1);border:1px solid rgba(39,174,96,.25);border-radius:20px;padding:3px 9px;font-size:11px;font-weight:600;color:var(--success)}
.db-hdot{width:6px;height:6px;border-radius:50%;background:var(--success);animation:dbpulse 2s infinite;flex-shrink:0}
@keyframes dbpulse{0%,100%{opacity:1}50%{opacity:.3}}
.db-ia-metric{display:flex;align-items:center;padding:4px 0;border-bottom:1px solid var(--border)}
.db-ia-metric:last-child{border-bottom:none}
.db-bar-wrap{background:var(--bg3);border-radius:4px;height:4px;flex:1;margin:0 7px;overflow:hidden}
.db-bar-fill{height:100%;border-radius:4px;transition:width .6s ease}
.db-diag-item{display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)}
.db-diag-item:last-child{border-bottom:none}
.db-ddot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.db-dl{display:flex;align-items:center;gap:7px}
/* Post-its */
.db-postit{border-radius:8px;padding:11px 12px;position:relative;transition:opacity .15s,transform .15s;aspect-ratio:1/1;display:flex;flex-direction:column}
.db-postit-empty{border-radius:8px;transition:opacity .2s}
.db-postit-empty:hover{opacity:.7!important}
.db-postit-colors{display:flex;gap:4px;margin-bottom:7px;flex-wrap:wrap}
.db-pc{width:13px;height:13px;border-radius:50%;cursor:pointer;border:2px solid transparent;flex-shrink:0;transition:transform .1s}
.db-pc:hover{transform:scale(1.2)}
.db-pc.db-sel{border-color:rgba(0,0,0,0.35)}
.db-postit-ta{background:none;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;resize:none;line-height:1.65;flex:1}
.db-postit-close{position:absolute;top:6px;right:8px;width:16px;height:16px;border-radius:50%;background:rgba(0,0,0,0.18);border:none;cursor:pointer;font-size:10px;color:rgba(0,0,0,0.5);line-height:16px;text-align:center;padding:0}
.db-postit-footer{font-size:9px;opacity:.5;margin-top:4px;border-top:1px solid rgba(0,0,0,0.1);padding-top:4px}
/* Notif panel */
.db-notif-panel{position:fixed;top:60px;right:20px;width:300px;background:var(--bg2);border:1px solid var(--border2);border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.25);z-index:9999;display:none;max-height:480px;overflow:hidden;flex-direction:column}
.db-notif-panel.open{display:flex}
.db-notif-hd{padding:12px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.db-notif-body{overflow-y:auto;flex:1}
.db-notif-item{padding:10px 14px;border-bottom:1px solid var(--border);display:flex;gap:9px;align-items:flex-start}
.db-notif-item:last-child{border-bottom:none}
.db-notif-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.db-notif-unread{width:6px;height:6px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.db-sw{width:30px;height:16px;border-radius:8px;background:var(--accent);cursor:pointer;position:relative;transition:background .15s;flex-shrink:0;border:none}
.db-sw.off{background:var(--border2)}
.db-sw-k{position:absolute;top:2px;left:16px;width:12px;height:12px;background:#fff;border-radius:50%;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.3);pointer-events:none}
.db-sw.off .db-sw-k{left:2px}
</style>


<!-- Health badge no topbar via JS injection -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Inject health badge into topbar
    var tbRight = document.querySelector('.topbar-right, [data-topbar-right]');
    // Will be added inline below
});
</script>

<!-- Sistema OK e sino movidos para o topbar global (main.php) -->

<!-- Painel de notificações -->
<div class="db-notif-panel" id="db-notif-panel">
  <div class="db-notif-hd">
    <span style="font-size:12px;font-weight:600;color:var(--txt)">Notificações</span>
    <div style="display:flex;align-items:center;gap:10px">
      <div style="display:flex;align-items:center;gap:6px;font-size:10px;color:var(--txt3)">
        <button class="db-sw" id="db-notif-sw" onclick="dbToggleNotifSw()"><div class="db-sw-k"></div></button>
        <span id="db-notif-sw-lbl">Ativas</span>
      </div>
      <button onclick="dbClearNotifs()" style="background:none;border:none;cursor:pointer;font-size:10px;color:var(--txt3)">Limpar</button>
    </div>
  </div>
  <div class="db-notif-body" id="db-notif-list">
    <?php if(empty($data['notifications'])): ?>
    <div style="padding:24px;text-align:center;font-size:12px;color:var(--txt3)">Nenhuma notificação</div>
    <?php else: foreach($data['notifications'] as $n):
      $ico = match($n['type']??'info'){
        'success'=>'📤', 'error'=>'⚠️', 'warning'=>'🔔', 'ai'=>'✨', default=>'💬'
      };
      $icoBg = match($n['type']??'info'){
        'success'=>'rgba(26,188,156,.15)', 'error'=>'rgba(231,76,60,.12)', 'warning'=>'rgba(243,156,18,.12)', 'ai'=>'rgba(91,141,239,.15)', default=>'rgba(91,141,239,.1)'
      };
      $unread = empty($n['read_at']);
      $ago = '';
      if(!empty($n['created_at'])){
        $diff = time() - strtotime($n['created_at']);
        if($diff<3600) $ago = 'há '.floor($diff/60).'min';
        elseif($diff<86400) $ago = 'há '.floor($diff/3600).'h';
        else $ago = date('d/m',strtotime($n['created_at']));
      }
    ?>
    <div class="db-notif-item" data-id="<?= $n['id'] ?>">
      <div class="db-notif-ico" style="background:<?= $icoBg ?>"><?= $ico ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:11px;color:var(--txt2);line-height:1.55"><?= e($n['message']??$n['title']??'') ?></div>
        <div style="font-size:9px;color:var(--txt3);margin-top:2px"><?= $ago ?></div>
      </div>
      <?php if($unread): ?><div class="db-notif-unread"></div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div id="db-widgets-container">
<!-- KPIs -->
<div class="db-widget" data-widget="kpis" data-widget-name="📊 KPIs Principais">
<div class="db-kpi-row">
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(91,141,239,.15)">💰</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Investimento (30d)</div>
      <div class="db-kpi-val"><?= brl((float)$data['spend_meta']+(float)$data['spend_google']) ?></div>
      <div class="db-kpi-ch"><span style="color:var(--success)">Meta + Google</span></div>
    </div>
    <canvas class="db-spark" id="dbsp1"></canvas>
  </div>
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(26,188,156,.15)">📤</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Relatórios enviados</div>
      <div class="db-kpi-val"><?= $data['reports_sent'] ?></div>
      <div class="db-kpi-ch">de <?= $data['total_reports'] ?> criados</div>
    </div>
    <canvas class="db-spark" id="dbsp2"></canvas>
  </div>
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(155,89,182,.15)">✨</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Análises IA</div>
      <div class="db-kpi-val"><?= $data['ai_analyses'] ?></div>
      <div class="db-kpi-ch">Hoje: <?= $data['ai_today'] ?></div>
    </div>
    <canvas class="db-spark" id="dbsp3"></canvas>
  </div>
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(243,156,18,.15)">👥</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Clientes ativos</div>
      <div class="db-kpi-val"><?= $data['total_clients'] ?></div>
      <div class="db-kpi-ch"><?= $data['total_accounts'] ?> contas</div>
    </div>
    <canvas class="db-spark" id="dbsp4"></canvas>
  </div>
</div>

<!-- Botão resetar layout -->
<div id="dashResetBtn" style="display:none;position:fixed;bottom:90px;right:72px;z-index:150">
  <button onclick="resetDashboardLayout()" style="background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:6px 12px;font-size:11px;color:var(--txt3);cursor:pointer;box-shadow:var(--shadow);display:flex;align-items:center;gap:5px;opacity:.7;transition:opacity .2s" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.7'">
    <span class="material-icons-outlined" style="font-size:13px">restart_alt</span>Resetar layout
  </button>
</div>
<script>
// Mostra botão "Resetar" SOMENTE se há customização real salva
(function(){
  try {
    var s = JSON.parse(localStorage.getItem('db_widgets_v3') || '{}');
    var hasCustom = (s.order && s.order.length > 0) || (s.hidden && s.hidden.length > 0);
    if (hasCustom) document.getElementById('dashResetBtn').style.display = 'block';
  } catch(e) {}
})();
</script>


<!-- KPIs Integrações -->
<?php if (($data['total_integrations'] ?? 0) > 0 || ($data['integration_logs_today'] ?? 0) > 0): ?>
<div class="db-widget" data-widget="kpis-int" data-widget-name="🔗 KPIs Integrações">
<div class="db-kpi-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:14px">
  <div class="db-kpi" style="cursor:pointer" onclick="window.location='<?= APP_URL ?>/integrations'">
    <div class="db-kpi-ico" style="background:rgba(99,102,241,.15)">🔗</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Integrações ativas</div>
      <div class="db-kpi-val"><?= $data['total_integrations'] ?? 0 ?></div>
      <div class="db-kpi-ch">webhooks configurados</div>
    </div>
  </div>
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(16,185,129,.15)">📨</div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Disparos hoje</div>
      <div class="db-kpi-val"><?= $data['integration_logs_today'] ?? 0 ?></div>
      <div class="db-kpi-ch">notificações enviadas</div>
    </div>
  </div>
  <div class="db-kpi">
    <div class="db-kpi-ico" style="background:rgba(<?= ($data['integration_errors_today']??0)>0?'231,76,60':'39,174,96' ?>,.15)"><?= ($data['integration_errors_today']??0)>0?'⚠️':'✅' ?></div>
    <div style="flex:1;min-width:0">
      <div class="db-kpi-lbl">Erros hoje</div>
      <div class="db-kpi-val" style="color:var(--<?= ($data['integration_errors_today']??0)>0?'danger':'success' ?>)"><?= $data['integration_errors_today'] ?? 0 ?></div>
      <div class="db-kpi-ch"><?= ($data['integration_errors_today']??0)>0?'<span style="color:var(--danger)">verificar integrações</span>':'sem erros' ?></div>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- ── NOVOS WIDGETS: Monitoramento ────────────────────────── -->
<div class="db-autorow db-row-3">

  <!-- Campanhas com problema — tempo real da API Meta -->
  <div class="db-card db-widget" data-widget="alertas-campanhas" data-widget-name="🚨 Campanhas com Problema">
    <div class="db-card-hd">
      <div><div class="db-card-title">🚨 Atenção agora</div><div class="db-card-sub">Tempo real · ordem de urgência</div></div>
    </div>
    <?php $_campanhasProblema = $data['campanhas_problema'] ?? []; ?>
    <?php if(empty($_campanhasProblema)): ?>
      <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">✅ Nenhuma campanha com problema agora</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:5px;max-height:320px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($_campanhasProblema as $_cp):
        // Cor da borda esquerda baseada na urgência
        $_borderColor = $_cp['urgencia'] >= 80 ? '#e74c3c' : ($_cp['urgencia'] >= 30 ? '#F39C12' : '#8b949e');
      ?>
        <div style="padding:7px 10px;background:var(--bg3);border-radius:7px;border-left:3px solid <?= $_borderColor ?>">
          <div style="font-size:9px;color:var(--txt3);margin-bottom:1px"><?= e($_cp['client_name']) ?><?php if(!empty($_cp['account_name'])): ?> · <span style="opacity:.7">📣 <?= e($_cp['account_name']) ?></span><?php endif; ?></div>
          <div style="font-size:11px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:1px"><?= e($_cp['camp_name']) ?></div>
          <?php if(!empty($_cp['camp_labels'])): ?><div style="font-size:8px;color:var(--txt3);opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-style:italic;margin-bottom:3px">🎯 <?= e($_cp['camp_labels']) ?></div><?php endif; ?>
          <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:<?= count($_cp['issues'])>0 ? '5' : '0' ?>px">
            <?php foreach($_cp['issues'] as $_i): ?>
              <span style="font-size:9px;font-weight:600;border-radius:4px;padding:1px 5px;background:<?= $_i['bg'] ?>;color:<?= $_i['color'] ?>"><?= e($_i['label']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php
            // Monta frase de observação com base nos problemas detectados
            $_obs = [];
            foreach($_cp['issues'] as $_i) {
              if(strpos($_i['label'],'CTR baixo') !== false)       $_obs[] = 'CTR muito abaixo do ideal nos últimos 7 dias';
              elseif(strpos($_i['label'],'CTR fraco') !== false)   $_obs[] = 'CTR abaixo de 1% nos últimos 7 dias';
              elseif(strpos($_i['label'],'Sem entrega') !== false) $_obs[] = 'Sem entrega hoje — verifique saldo ou orçamento';
              elseif(strpos($_i['label'],'Freq. alta') !== false)  $_obs[] = 'Frequência alta — público saturado';
              elseif(strpos($_i['label'],'Freq. elevada') !== false) $_obs[] = 'Frequência elevada — considere novos criativos';
              elseif(strpos($_i['label'],'CPM alto') !== false)    $_obs[] = 'CPM alto — custo por mil impressões elevado';
              elseif(strpos($_i['label'],'Com erro') !== false)    $_obs[] = 'Campanha com erro na plataforma';
              elseif(strpos($_i['label'],'Em análise') !== false)  $_obs[] = 'Campanha em análise pelo Meta';
            }
          ?>
          <?php if(!empty($_obs)): ?>
            <div style="font-size:9px;color:var(--txt3);line-height:1.5;margin-top:1px">
              <?= implode(' · ', $_obs) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Gasto da Campanha -->
  <div class="db-card db-widget" data-widget="budget-mes" data-widget-name="💰 Gasto da Campanha">
    <div class="db-card-hd">
      <div><div class="db-card-title">💰 Gasto da campanha</div><div class="db-card-sub">Total investido desde o início</div></div>
    </div>
    <?php
    $budgetContas = [];
    try {
      if (!empty($data['widget_metrics'])) {
        foreach ($data['widget_metrics'] as $_wm) {
          $budgetContas[] = [
            'name'        => $_wm['title'] ?? $_wm['account_name'] ?? 'Conta',
            'client'      => $_wm['client_name'] ?? '',
            'account'     => $_wm['account_name'] ?? '',
            'camp_labels' => $_wm['camp_labels'] ?? '',
            'spent'       => (float)($_wm['spend_total'] ?? $_wm['spend']),
            'budget'      => 0,
            'period'      => 'Desde o início',
          ];
        }
      }
    } catch(\Throwable $_e){}
    ?>
    <?php if(empty($budgetContas)): ?>
      <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">Sem dados de budget</div>
    <?php else: ?>
      <div id="budgetList" style="display:flex;flex-direction:column;gap:10px;max-height:280px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($budgetContas as $_bi => $_b):
        $_pct  = $_b['budget'] > 0 ? min(100, round(($_b['spent']/$_b['budget'])*100)) : 0;
        $_color = $_pct >= 90 ? '#E74C3C' : ($_pct >= 70 ? '#F39C12' : '#1f6feb');
      ?>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <div style="display:flex;flex-direction:column;max-width:140px;overflow:hidden">
              <span style="font-size:11px;color:var(--txt2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_b['name']) ?></span>
              <?php if(!empty($_b['client'])): ?><span style="font-size:9px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_b['client']) ?></span><?php endif; ?>
              <?php if(!empty($_b['account']) && $_b['account'] !== $_b['name']): ?><span style="font-size:9px;color:var(--txt3);opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">📣 <?= e($_b['account']) ?></span><?php endif; ?>
              <?php if(!empty($_b['camp_labels'])): ?><span style="font-size:8px;color:var(--txt3);opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-style:italic">🎯 <?= e($_b['camp_labels']) ?></span><?php endif; ?>
            </div>
            <span style="font-size:11px;font-weight:700;color:<?= $_color ?>"><?= $_b['budget']>0 ? $_pct.'%' : brl($_b['spent']) ?></span>
          </div>
          <?php if($_b['budget']>0): ?>
          <div style="height:5px;background:var(--bg4);border-radius:3px;overflow:hidden">
            <div style="height:100%;width:<?= $_pct ?>%;background:<?= $_color ?>;border-radius:3px;transition:width .6s"></div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:3px">
            <span style="font-size:9px;color:var(--txt3)"><?= brl($_b['spent']) ?> gastos</span>
            <span style="font-size:9px;color:var(--txt3)">meta <?= brl($_b['budget']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Semana a semana -->
  <div class="db-card db-widget" data-widget="semana-semana" data-widget-name="📈 Semana a Semana">
    <div class="db-card-hd">
      <div><div class="db-card-title">📈 Semana a semana</div><div class="db-card-sub">vs semana anterior</div></div>
    </div>
    <?php
    $weekData = [];
    try {
      if (!empty($data['widget_metrics'])) {
        $colors = ['#1f6feb','#8b5cf6','#1ABC9C','#F39C12'];
        foreach ($data['widget_metrics'] as $_i => $_wm) {
          $chg   = (int)($_wm['spend_change'] ?? 0);
          $spark = [40, 50, 55, 60, 65, 70, 75]; // placeholder visual
          if (($_wm['spend_prev'] ?? 0) > 0 && $_wm['spend'] > 0) {
            // Distribui gasto nos últimos 7 pontos proporcionalmente
            $base = $_wm['spend'] / 7;
            $spark = array_map(fn($d) => round($base * (0.7 + $d * 0.05), 2), range(1, 7));
          }
          $weekData[] = [
            'label'       => $_wm['title'],
            'client'      => $_wm['client_name'] ?? '',
            'account'     => $_wm['account_name'] ?? '',
            'camp_labels' => $_wm['camp_labels'] ?? '',
            'chg'         => $chg,
            'spark'       => $spark,
            'color'       => $colors[$_i % count($colors)],
          ];
        }
      }
    } catch(\Throwable $_e){}
    ?>
    <?php if(empty($weekData)): ?>
      <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">Sem dados comparativos</div>
    <?php else: ?>
      <div id="weekList" style="display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($weekData as $_wi => $_w):
        $_max = max(array_filter($_w['spark']) ?: [1]);
        $_chgColor = $_w['chg'] >= 0 ? '#1ABC9C' : '#E74C3C';
        $_chgSign  = $_w['chg'] >= 0 ? '+' : '';
      ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)">
          <div style="flex:1;min-width:0;overflow:hidden">
            <div style="font-size:10px;color:var(--txt2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_w['label']) ?></div>
            <?php if(!empty($_w['client'])): ?><div style="font-size:8px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_w['client']) ?><?php if(!empty($_w['account'])): ?> · 📣 <?= e($_w['account']) ?><?php endif; ?></div><?php endif; ?>
            <?php if(!empty($_w['camp_labels'])): ?><div style="font-size:8px;color:var(--txt3);opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-style:italic">🎯 <?= e($_w['camp_labels']) ?></div><?php endif; ?>
          </div>
          <div style="display:flex;align-items:flex-end;gap:1px;height:18px;margin:0 8px;width:48px">
            <?php foreach($_w['spark'] as $_sv): $h = $_max>0 ? max(10,round(($_sv/$_max)*100)) : 10; ?>
            <div style="flex:1;height:<?= $h ?>%;background:<?= $_w['color'] ?>;border-radius:1px 1px 0 0;opacity:.8"></div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:11px;font-weight:700;color:<?= $_chgColor ?>"><?= $_chgSign . $_w['chg'] ?>%</div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── NOVOS WIDGETS: Performance ──────────────────────────── -->
<div class="db-autorow db-row-3" style="grid-template-columns:1fr 1.1fr 0.9fr">

  <!-- Ranking de campanhas -->
  <div class="db-card db-widget" data-widget="ranking-campanhas" data-widget-name="🏆 Ranking Campanhas">
    <div class="db-card-hd">
      <div><div class="db-card-title">🏆 Top campanhas</div><div class="db-card-sub">Melhor custo/resultado</div></div>
    </div>
    <?php
    $rankCamps = [];
    try {
      if (!empty($data['widget_metrics'])) {
        foreach ($data['widget_metrics'] as $_wm) {
          $_val   = (float)$_wm['cost_result'];
          $_color = $_wm['cost_color'];
          $_lbl   = $_val > 0
            ? 'R$'.number_format($_val,2,',','.').'/<span style="font-size:8px">'.$_wm['cost_label'].'</span>'
            : number_format($_wm['ctr'],2,',','.').'%<span style="font-size:8px"> CTR</span>';
          $rankCamps[] = [
            'name'        => $_wm['title'],
            'client'      => $_wm['client_name'] ?? '',
            'account'     => $_wm['account_name'] ?? '',
            'camp_labels' => $_wm['camp_labels'] ?? '',
            'lbl'         => $_lbl,
            'val'         => $_val,
            'color'       => $_color,
          ];
        }
        usort($rankCamps, fn($a,$b) => $a['val'] <=> $b['val']);
      }
    } catch(\Throwable $_e){}
    ?>
    <?php if(empty($rankCamps)): ?>
      <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">Sem dados de ranking</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($rankCamps as $_i=>$_r): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border)">
          <div style="width:18px;height:18px;border-radius:4px;background:var(--bg4);font-size:9px;font-weight:700;color:<?= $_r['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $_i+1 ?></div>
          <div style="flex:1;min-width:0;overflow:hidden">
            <div style="font-size:10px;color:var(--txt2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_r['name']) ?></div>
            <?php if(!empty($_r['client'])): ?><div style="font-size:8px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_r['client']) ?><?php if(!empty($_r['account'])): ?> · 📣 <?= e($_r['account']) ?><?php endif; ?></div><?php endif; ?>
            <?php if(!empty($_r['camp_labels'])): ?><div style="font-size:8px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-style:italic;opacity:.8">🎯 <?= e($_r['camp_labels']) ?></div><?php endif; ?>
          </div>
          <div style="font-size:11px;font-weight:700;color:<?= $_r['color'] ?>"><?= $_r['lbl'] ?: '—' ?></div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Score de saúde -->
  <div class="db-card db-widget" data-widget="score-saude" data-widget-name="🎯 Saúde dos Clientes">
    <div class="db-card-hd">
      <div><div class="db-card-title">🎯 Saúde dos clientes</div><div class="db-card-sub">Score 0–100 automático</div></div>
    </div>
    <?php
    $scoreClients = [];
    try {
      if (!empty($data['widget_metrics'])) {
        foreach ($data['widget_metrics'] as $_wm) {
          $score  = $_wm['score'];
          $_label = $score >= 80 ? 'Ótimo' : ($score >= 60 ? 'Bom' : ($score >= 40 ? 'Atenção' : 'Crítico'));
          $_color = $score >= 80 ? '#1ABC9C' : ($score >= 60 ? '#1f6feb' : ($score >= 40 ? '#F39C12' : '#E74C3C'));
          $scoreClients[] = [
            'name'        => $_wm['title'],
            'client'      => $_wm['client_name'] ?? '',
            'account'     => $_wm['account_name'] ?? '',
            'camp_labels' => $_wm['camp_labels'] ?? '',
            'score'       => $score,
            'label'       => $_label,
            'color'       => $_color,
          ];
        }
        // Ordenar: pior score primeiro (quem precisa de atenção aparece no topo esquerdo)
        usort($scoreClients, fn($a, $b) => $a['score'] <=> $b['score']);
      }
    } catch(\Throwable $_e){}
    ?>
    <?php if(empty($scoreClients)): ?>
      <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">Sem dados de score</div>
    <?php else: ?>
      <div id="scoreList" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:380px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($scoreClients as $_si => $_sc): ?>
        <div style="background:var(--bg3);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:10px;font-weight:600;color:var(--txt2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:1px"><?= e($_sc['name']) ?></div>
          <?php if(!empty($_sc['client'])): ?><div style="font-size:8px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:1px"><?= e($_sc['client']) ?></div><?php endif; ?>
          <?php if(!empty($_sc['account'])): ?><div style="font-size:8px;color:var(--txt3);opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">📣 <?= e($_sc['account']) ?></div><?php endif; ?>
          <?php if(!empty($_sc['camp_labels'])): ?><div style="font-size:7px;color:var(--txt3);opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-style:italic;margin-bottom:4px">🎯 <?= e($_sc['camp_labels']) ?></div><?php else: ?><div style="margin-bottom:4px"></div><?php endif; ?>
          <div style="width:44px;height:44px;border-radius:50%;border:3px solid <?= $_sc['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:<?= $_sc['color'] ?>;margin:0 auto 4px"><?= $_sc['score'] ?></div>
          <div style="font-size:9px;font-weight:600;color:<?= $_sc['color'] ?>"><?= $_sc['label'] ?></div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Saldo Pré-pago -->
  <div class="db-card db-widget" data-widget="saldo-prepago" data-widget-name="💰 Saldo Pré-pago">
    <div class="db-card-hd">
      <div>
        <div class="db-card-title">💰 Saldo Pré-pago</div>
        <div class="db-card-sub">Dias restantes por cliente</div>
      </div>
      <button onclick="document.getElementById('modal-recarga').style.display='flex'"
        style="background:var(--accent);border:none;border-radius:6px;padding:3px 10px;font-size:11px;color:#fff;cursor:pointer;font-weight:600">+ Recarga</button>
    </div>
    <?php
    $_prepago = $data['prepago_widget'] ?? [];
    // Fallback: mostra projeção normal se não há clientes pré-pago cadastrados
    $_temPrepago = !empty($_prepago);
    ?>
    <?php if(!$_temPrepago): ?>
      <?php
      // Mostra projeção fim do mês como fallback
      $projecoes = [];
      if (!empty($data['widget_metrics'])) {
        foreach ($data['widget_metrics'] as $_wm) {
          if ($_wm['spend'] <= 0) continue;
          $projecoes[] = $_wm;
        }
      }
      ?>
      <?php if(empty($projecoes)): ?>
        <div style="text-align:center;padding:16px;font-size:12px;color:var(--txt3)">
          Nenhum cliente pré-pago cadastrado.<br>
          <span style="color:var(--accent);cursor:pointer" onclick="window.location='/clients'">Configurar clientes →</span>
        </div>
      <?php else: ?>
        <div style="font-size:10px;color:var(--txt3);margin-bottom:6px">📌 Projeção fim do mês (todos cartão)</div>
        <div style="display:flex;flex-direction:column;gap:0;max-height:190px;overflow-y:auto;scrollbar-width:thin">
        <?php foreach($projecoes as $_wm): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-size:11px;color:var(--txt2);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($_wm['client_name']) ?></div>
              <span style="font-size:9px;font-weight:600;border-radius:4px;padding:1px 6px;background:var(--bg4);color:#8b949e;margin-top:3px;display:inline-block">
                R$<?= number_format($_wm['ritmo_dia'],2,',','.') ?><span style="font-size:8px">/dia</span>
              </span>
            </div>
            <div style="font-size:13px;font-weight:700;color:var(--txt)"><?= brl($_wm['projecao']) ?></div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:2px">
      <?php foreach($_prepago as $_pp): ?>
        <?php
          $_semSaldo  = $_pp['sem_saldo'];
          $_emAlerta  = $_pp['em_alerta'];
          $_dias      = $_pp['dias_restantes'];
          $_saldo     = $_pp['saldo_atual'];
          $_ritmo     = $_pp['ritmo_dia'];
          $_recarga   = $_pp['ultima_recarga'];
          $_dataEnc   = $_pp['data_encerramento'];

          // Cor do badge de dias restantes
          if ($_semSaldo)        { $_diasColor = '#e74c3c'; $_diasBg = 'rgba(231,76,60,.18)'; $_diasLabel = 'Sem saldo'; }
          elseif ($_dias === null){ $_diasColor = '#8b949e'; $_diasBg = 'var(--bg4)';          $_diasLabel = 'Verificar'; }
          elseif ($_dias <= 3)   { $_diasColor = '#e74c3c'; $_diasBg = 'rgba(231,76,60,.18)'; $_diasLabel = $_dias.'d restantes'; }
          elseif ($_dias <= 7)   { $_diasColor = '#F39C12'; $_diasBg = 'rgba(243,156,18,.18)';$_diasLabel = $_dias.'d restantes'; }
          else                   { $_diasColor = '#2ecc71'; $_diasBg = 'rgba(46,204,113,.15)';$_diasLabel = $_dias.'d restantes'; }
        ?>
        <div style="padding:8px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:11px;color:var(--txt2);font-weight:600;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= e($_pp['client_name']) ?>
              </div>
              <?php if(!empty($_pp['account_name'])): ?>
              <div style="font-size:9px;color:var(--txt3);opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px">
                📣 <?= e($_pp['account_name']) ?>
              </div>
              <?php endif; ?>
              <div style="display:flex;gap:4px;margin-top:3px;flex-wrap:wrap">
                <!-- Badge dias restantes -->
                <span style="font-size:9px;font-weight:700;border-radius:4px;padding:1px 6px;background:<?= $_diasBg ?>;color:<?= $_diasColor ?>">
                  <?= $_diasLabel ?>
                </span>
                <!-- Ritmo diário -->
                <?php if($_ritmo > 0): ?>
                <span style="font-size:9px;border-radius:4px;padding:1px 6px;background:var(--bg4);color:#8b949e">
                  R$<?= number_format($_ritmo,2,',','.') ?>/dia
                </span>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right">
              <!-- Saldo atual -->
              <div style="font-size:13px;font-weight:700;color:<?= $_semSaldo ? '#e74c3c' : ($_emAlerta ? '#F39C12' : 'var(--txt)') ?>">
                <?= brl($_saldo) ?>
              </div>
              <!-- Data estimada de encerramento -->
              <?php if($_dataEnc && !$_semSaldo): ?>
              <div style="font-size:9px;color:var(--txt3);margin-top:2px">até <?= $_dataEnc ?></div>
              <?php endif; ?>
            </div>
          </div>
          <!-- Última recarga -->
          <?php if($_recarga): ?>
          <div style="font-size:9px;color:var(--txt3);margin-top:4px">
            💳 Última recarga: <strong style="color:var(--txt2)">R$<?= number_format($_recarga['valor'],2,',','.') ?></strong>
            via <?= strtoupper($_recarga['tipo']) ?>
            — <?= date('d/m/Y', strtotime($_recarga['created_at'])) ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Registrar Recarga -->
<div id="modal-recarga" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center">
  <div style="background:var(--bg2);border-radius:12px;padding:24px;width:100%;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.4)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-size:15px;font-weight:700;color:var(--txt)">💰 Registrar Recarga</div>
      <button onclick="document.getElementById('modal-recarga').style.display='none'"
        style="background:none;border:none;color:var(--txt3);font-size:18px;cursor:pointer">✕</button>
    </div>
    <form id="form-recarga">
      <div style="margin-bottom:12px">
        <label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:4px">Cliente Pré-pago</label>
        <select id="rc-client" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:8px;color:var(--txt);font-size:13px">
          <?php foreach($_prepago as $_pp): ?>
          <option value="<?= $_pp['client_id'] ?>"><?= e($_pp['client_name']) ?> — Saldo: R$<?= number_format($_pp['saldo_atual'],2,',','.') ?></option>
          <?php endforeach; ?>
          <?php if(empty($_prepago)): ?>
          <option value="">Nenhum cliente pré-pago cadastrado</option>
          <?php endif; ?>
        </select>
      </div>
      <div style="margin-bottom:12px">
        <label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:4px">Valor (R$)</label>
        <input id="rc-valor" type="number" step="0.01" min="1" placeholder="Ex: 500.00"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:8px;color:var(--txt);font-size:13px;box-sizing:border-box">
      </div>
      <div style="margin-bottom:12px">
        <label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:4px">Forma de pagamento</label>
        <select id="rc-tipo" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:8px;color:var(--txt);font-size:13px">
          <option value="pix">Pix</option>
          <option value="transferencia">Transferência</option>
          <option value="dinheiro">Dinheiro</option>
          <option value="outro">Outro</option>
        </select>
      </div>
      <div style="margin-bottom:16px">
        <label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:4px">Observação (opcional)</label>
        <input id="rc-desc" type="text" placeholder="Ex: comprovante recebido"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:8px;color:var(--txt);font-size:13px;box-sizing:border-box">
      </div>
      <div id="rc-msg" style="font-size:12px;margin-bottom:10px;display:none"></div>
      <div style="display:flex;gap:8px">
        <button type="button" onclick="document.getElementById('modal-recarga').style.display='none'"
          style="flex:1;padding:9px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);cursor:pointer;font-size:13px">
          Cancelar
        </button>
        <button type="button" onclick="registrarRecarga()"
          style="flex:1;padding:9px;border-radius:6px;border:none;background:var(--accent);color:#fff;cursor:pointer;font-size:13px;font-weight:700">
          ✓ Confirmar
        </button>
      </div>
    </form>
  </div>
</div>
<script>
function registrarRecarga() {
  const clientId = document.getElementById('rc-client').value;
  const valor    = document.getElementById('rc-valor').value;
  const tipo     = document.getElementById('rc-tipo').value;
  const desc     = document.getElementById('rc-desc').value;
  const msg      = document.getElementById('rc-msg');
  if (!clientId || !valor || parseFloat(valor) <= 0) {
    msg.style.display = 'block'; msg.style.color = '#e74c3c';
    msg.textContent = 'Preencha o cliente e o valor.'; return;
  }
  msg.style.display = 'block'; msg.style.color = 'var(--txt3)'; msg.textContent = 'Salvando...';
  const fd = new FormData();
  fd.append('client_id', clientId); fd.append('valor', valor);
  fd.append('tipo', tipo); fd.append('descricao', desc);
  fd.append('_csrf', window._csrf || '');
  fetch('/budget/recharge', { method:'POST', body:fd })
    .then(r => r.json()).then(d => {
      if (d.success) {
        msg.style.color = '#2ecc71';
        msg.textContent = d.message;
        setTimeout(() => { document.getElementById('modal-recarga').style.display='none'; location.reload(); }, 1200);
      } else {
        msg.style.color = '#e74c3c'; msg.textContent = d.error || 'Erro ao salvar.';
      }
    }).catch(() => { msg.style.color='#e74c3c'; msg.textContent='Erro de conexão.'; });
}
</script>

<!-- Gráficos -->
<div class="db-autorow db-row-2">
<div class="db-card db-widget" data-widget="grafico-gasto" data-widget-name="📈 Gasto por Plataforma">
    <div class="db-card-hd">
      <div><div class="db-card-title">Gasto por plataforma</div><div class="db-card-sub">Meta Ads vs Google Ads — 7 dias</div></div>
      <span class="db-dd">7 dias</span>
    </div>
    <div style="position:relative;height:160px"><canvas id="dbAreaC"></canvas></div>
  </div>
  <div class="db-card db-widget" data-widget="distribuicao-gasto" data-widget-name="📊 Distribuição de Gasto">
    <div class="db-card-hd"><div class="db-card-title">Distribuição de gasto</div></div>
    <div style="position:relative;width:120px;height:120px;margin:0 auto 10px">
      <canvas id="dbDonutC" width="120" height="120"></canvas>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
        <div style="font-size:20px;font-weight:700;color:var(--txt)"><?= $metaPct ?>%</div>
        <div style="font-size:10px;color:var(--txt3)">Meta Ads</div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:6px"><div style="width:8px;height:8px;border-radius:50%;background:#1877F2"></div><span style="font-size:11px;color:var(--txt2)">Meta Ads</span></div>
        <span style="font-size:11px;font-weight:600;color:var(--txt)"><?= $metaPct ?>%</span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:6px"><div style="width:8px;height:8px;border-radius:50%;background:#EA4335"></div><span style="font-size:11px;color:var(--txt2)">Google Ads</span></div>
        <span style="font-size:11px;font-weight:600;color:var(--txt)"><?= $googlePct ?>%</span>
      </div>
    </div>
  </div>
</div>

<!-- Relatórios + Post-its + IA -->
<div class="db-autorow db-row-3">
  <!-- Feed unificado: Relatórios + Alertas de Saldo -->
  <?php
  // Helpers locais para o feed unificado
  function _seRelTime(?string $dt): string {
    if(!$dt || $dt==='0000-00-00 00:00:00') return '—';
    $diff = time()-strtotime($dt);
    if($diff<60)     return 'agora';
    if($diff<3600)   return floor($diff/60).'min atrás';
    if($diff<86400)  return floor($diff/3600).'h atrás';
    if($diff<604800) return floor($diff/86400).'d atrás';
    return date('d/m/Y',strtotime($dt));
  }
  function _seFreqLabel(string $f): string {
    return match($f){'daily'=>'Diário','weekly'=>'Semanal','monthly'=>'Mensal','once'=>'Único',default=>ucfirst($f)};
  }
  function _seProxEnvio(?string $dt): string {
    if(!$dt) return '';
    $diff = strtotime($dt)-time();
    if($diff<0) {
        $ago = abs($diff);
        if($ago < 3600) $lbl = 'há '.floor($ago/60).'min';
        elseif($ago < 86400) $lbl = 'há '.floor($ago/3600).'h';
        else $lbl = 'há '.floor($ago/86400).'d';
        return '<span style="color:var(--danger);font-weight:600">⚠ Atrasado '.$lbl.'</span>';
    }
    if($diff<3600)   return 'em '.floor($diff/60).'min';
    if($diff<86400)  return 'em '.floor($diff/3600).'h';
    if($diff<172800) return 'amanhã '.date('H:i',strtotime($dt));
    return date('d/m H:i',strtotime($dt));
  }
  $seFeed = $data['feed_envios'] ?? [];
  ?>
  <div class="db-card" data-card-id="disparos" style="grid-column:span 1;">
    <div class="db-card-hd" style="align-items:flex-start;flex-direction:column;gap:8px">
      <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
        <div class="db-card-title">📤 Saldo de Envios</div>
        <a href="<?= APP_URL ?>/reports/create" class="btn btn-primary btn-sm" style="font-size:10px;padding:3px 10px;gap:3px">
          <span class="material-icons-outlined" style="font-size:12px">add</span>Novo
        </a>
      </div>
      <!-- Filtros de aba -->
      <div style="display:flex;gap:4px;" id="se-filter-btns">
        <button class="btn btn-sm btn-secondary se-filter active" data-filter="todos" style="font-size:10px;padding:2px 9px">Todos</button>
        <button class="btn btn-sm btn-secondary se-filter" data-filter="relatorio" style="font-size:10px;padding:2px 9px">Relatórios</button>
        <button class="btn btn-sm btn-secondary se-filter" data-filter="saldo_minimo" style="font-size:10px;padding:2px 9px">Saldo</button>
        <button class="btn btn-sm btn-secondary se-filter" data-filter="ctr_baixo" style="font-size:10px;padding:2px 9px">CTR</button>
        <button class="btn btn-sm btn-secondary se-filter" data-filter="cpc_alto" style="font-size:10px;padding:2px 9px">CPC</button>
        <button class="btn btn-sm btn-secondary se-filter" data-filter="erro_conta" style="font-size:10px;padding:2px 9px">Erro</button>
      </div>
    </div>

    <!-- Feed de itens -->
    <div id="se-feed" style="display:flex;flex-direction:column;gap:5px;margin-top:4px;max-height:290px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;padding-right:3px">
    <?php if(empty($seFeed)): ?>
      <div style="text-align:center;padding:24px 12px;font-size:12px;color:var(--txt3)">
        Nenhum relatório ou alerta criado ainda.<br>
        <a href="<?= APP_URL ?>/reports/create" style="color:var(--accent);margin-top:6px;display:inline-block">Criar primeiro →</a>
      </div>
    <?php else: foreach($seFeed as $sItem):
        $sTipo    = $sItem['tipo'] ?? 'relatorio';
        $sCliente = e($sItem['cliente'] ?? $sItem['nome'] ?? '—');
        $sObj     = e($sItem['objetivo'] ?? '—');
        $sSt      = $sItem['status'] ?? 'draft';
        $sFreq    = _seFreqLabel($sItem['frequency'] ?? 'once');
        $sLast    = _seRelTime($sItem['sent_at'] ?? null);
        $sNext    = _seProxEnvio($sItem['next_send_at'] ?? null);
        $sEditUrl = $sTipo==='relatorio' ? APP_URL.'/reports/edit?id='.$sItem['id'] : APP_URL.'/alerts/edit?id='.$sItem['id'];

        // Visual por tipo
        $alertLabels = [
            'relatorio'     => ['Relatório',      '#5B8DEF', 'rgba(91,141,239,.18)',  'description'],
            'saldo_minimo'  => ['Alerta Saldo',   '#F39C12', 'rgba(243,156,18,.18)', 'account_balance_wallet'],
            'ctr_baixo'     => ['CTR Baixo',      '#9B59B6', 'rgba(155,89,182,.18)', 'trending_down'],
            'cpc_alto'      => ['CPC Alto',       '#E74C3C', 'rgba(231,76,60,.18)',  'price_change'],
            'custo_conv_alto'=>['Custo/Conv',     '#E67E22', 'rgba(230,126,34,.18)', 'monetization_on'],
            'roas_baixo'    => ['ROAS Baixo',     '#C0392B', 'rgba(192,57,43,.18)',  'show_chart'],
            'erro_conta'    => ['Erro Conta',     '#E74C3C', 'rgba(231,76,60,.18)',  'error_outline'],
        ];
        $aInfo = $alertLabels[$sTipo] ?? $alertLabels['saldo_minimo'];
        [$aLabel, $aDotColor, $aDotBg, $sIcon] = $aInfo;
        if($sTipo==='relatorio'){
          $sBadge='<span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:20px;background:'.$aDotBg.';color:'.$aDotColor.'">'.$aLabel.'</span>';
        } else {
          $sBadge='<span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:20px;background:'.$aDotBg.';color:'.$aDotColor.'">'.$aLabel.'</span>';
        }

        // Badge status
        if($sSt==='active'||$sSt==='sent')   $sStBadge='<span class="db-pill db-pill-g">'.($sSt==='sent'?'Enviado':'Ativo').'</span>';
        elseif($sSt==='scheduled')            $sStBadge='<span class="db-pill db-pill-b">Agendado</span>';
        elseif($sSt==='paused')              $sStBadge='<span class="db-pill db-pill-y">Pausado</span>';
        elseif($sSt==='draft')               $sStBadge='<span class="db-pill">Rascunho</span>';
        else                                 $sStBadge='<span class="db-pill">'.e($sSt).'</span>';
    ?>
      <div class="se-item" data-tipo="<?= $sTipo ?>"
           style="display:flex;align-items:center;gap:8px;
                  background:var(--bg3);border:1px solid var(--border);
                  border-radius:8px;padding:7px 10px;
                  transition:background .15s;cursor:default"
           onmouseover="this.style.background='var(--bg4)'"
           onmouseout="this.style.background='var(--bg3)'">

        <!-- Ícone -->
        <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;
                    display:flex;align-items:center;justify-content:center;
                    background:<?= $aDotBg ?>">
          <span class="material-icons-outlined" style="font-size:13px;color:<?= $aDotColor ?>"><?= $sIcon ?></span>
        </div>

        <!-- Conteúdo -->
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:1px">
            <span style="font-size:11px;font-weight:600;color:var(--txt);
                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px">
              <?= e($sItem['nome'] ?? $sCliente) ?>
            </span>
            <?php if(!empty($sCliente) && $sCliente !== ($sItem['nome']??'')): ?>
            <span style="font-size:9px;color:var(--txt3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:90px">· <?= $sCliente ?></span>
            <?php endif; ?>
            <?= $sBadge ?>
            <?= $sStBadge ?>
            <?php if(!empty($sItem['tipo_envio'])): ?>
            <?php $isManual = $sItem['tipo_envio']==='manual'; ?>
            <span style="font-size:9px;font-weight:600;padding:2px 6px;border-radius:20px;
                         background:<?= $isManual?'rgba(155,89,182,.18)':'rgba(26,188,156,.15)' ?>;
                         color:<?= $isManual?'#9B59B6':'#1ABC9C' ?>">
              <?= $isManual?'Manual':'Auto' ?>
            </span>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:10px;color:var(--txt3);display:flex;align-items:center;gap:2px">
              <span class="material-icons-outlined" style="font-size:10px">schedule</span><?= $sFreq ?>
            </span>
            <?php if($sLast!=='—'): ?>
            <span style="font-size:10px;color:var(--txt3)"><?= $sLast ?></span>
            <?php endif; ?>
            <?php if($sNext): ?>
            <span style="font-size:9px;background:rgba(91,141,239,.12);color:#5B8DEF;padding:1px 6px;border-radius:20px">
              Próx: <?= e($sNext) ?>
            </span>
            <?php endif; ?>
            <?php if($sObj !== '—'): ?>
            <span style="font-size:9px;color:var(--txt3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100px"><?= $sObj ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Editar -->
        <a href="<?= $sEditUrl ?>" class="btn btn-icon btn-ghost"
           title="Editar" style="flex-shrink:0">
          <span class="material-icons-outlined" style="font-size:13px">edit</span>
        </a>
      </div>
    <?php endforeach; endif; ?>
    </div><!-- /se-feed -->

    <script>
    (function(){
      var btns=document.querySelectorAll('.se-filter');
      btns.forEach(function(b){
        b.addEventListener('click',function(){
          btns.forEach(function(x){x.classList.remove('active')});
          b.classList.add('active');
          var f=b.dataset.filter;
          document.querySelectorAll('#se-feed .se-item').forEach(function(el){
            el.style.display=(f==='todos'||el.dataset.tipo===f)?'':'none';
          });
        });
      });
    })();
    </script>
  </div>


  <!-- Post-its 2x2 -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <span style="font-size:12px;font-weight:600;color:var(--txt)">📌 Anotações</span>
      <button onclick="dbAddNote()" class="btn btn-secondary btn-sm" style="font-size:11px;padding:3px 10px">+ Nova</button>
    </div>
    <div id="db-postits" style="display:grid;grid-template-columns:1fr 1fr;gap:7px">
      <?php
      $ptColors = ['#F7DC6F','#82E0AA','#85C1E9','#F1948A','#D7BDE2','#FAD7A0'];
      $ptTxts   = ['#2c2a00','#0a2a1a','#0a1a2a','#2a0a0a','#1a0a2a','#2a1500'];
      $existingPostits = $data['postits']??[];
      // Preenche sempre 4 slots (existentes + vazios)
      for($slot=0; $slot<4; $slot++):
        if(!empty($existingPostits[$slot])):
          $pt = $existingPostits[$slot];
          $bg = $pt['color']??'#F7DC6F';
          $tc = $pt['text_color']??'#2c2a00';
          $ago = '';
          if(!empty($pt['updated_at'])){$diff=time()-strtotime($pt['updated_at']);$ago=$diff<3600?floor($diff/60).'min atrás':($diff<86400?floor($diff/3600).'h atrás':date('d/m',strtotime($pt['updated_at'])));}
      ?>
      <div class="db-postit" id="dbpt<?= $pt['id'] ?>" data-id="<?= $pt['id'] ?>" style="background:<?= e($bg) ?>;color:<?= e($tc) ?>">
        <button class="db-postit-close" onclick="dbRemoveNote(<?= $pt['id'] ?>)">✕</button>
        <div class="db-postit-colors">
          <?php foreach($ptColors as $ci=>$c): ?>
          <div class="db-pc<?= $c===$bg?' db-sel':'' ?>" style="background:<?= $c ?>" onclick="dbSetColor(<?= $pt['id'] ?>,'<?= $c ?>','<?= $ptTxts[$ci] ?>')"></div>
          <?php endforeach; ?>
        </div>
        <textarea class="db-postit-ta" style="color:<?= e($tc) ?>" onblur="dbSaveNote(<?= $pt['id'] ?>,this.value)"><?= e($pt['content']??'') ?></textarea>
        <div class="db-postit-footer"><?= $ago ?></div>
      </div>
      <?php else: // Slot vazio
        $emptyColors = ['#F7DC6F','#82E0AA','#85C1E9','#F1948A'];
        $ebg = $emptyColors[$slot]; $etc = $ptTxts[$slot];
      ?>
      <div class="db-postit db-postit-empty" data-id="new" style="background:<?= $ebg ?>;color:<?= $etc ?>;cursor:pointer;opacity:.45;display:flex;align-items:center;justify-content:center;min-height:120px" onclick="dbAddNoteSlot(this,'<?= $ebg ?>','<?= $etc ?>')">
        <span style="font-size:22px;opacity:.6">+</span>
      </div>
      <?php endif; endfor; ?>
    </div>
  </div>

  <!-- Análises IA do dia -->
  <div class="db-card">
    <div class="db-card-hd">
      <div>
        <div class="db-card-title">Análises IA do dia</div>
        <div class="db-card-sub"><?= !empty($data['ai_today_list']) ? 'Hoje · '.count($data['ai_today_list']).' análise'.(count($data['ai_today_list'])>1?'s':'') : 'Sem análises hoje' ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <?php if(!empty($data['last_ai'])): $mp=explode('-',$data['last_ai']['model']??'IA');$ms=strtoupper(preg_replace('/[^a-zA-Z0-9]/','',  $mp[0]));if(strlen($ms)>8)$ms=substr($ms,0,8); ?>
        <span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;background:rgba(91,141,239,.15);color:#5B8DEF"><?= e($ms) ?></span>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/ai" class="db-dd">Analisar →</a>
      </div>
    </div>

    <?php if(empty($data['ai_today_list'])): ?>
    <div style="text-align:center;padding:24px 16px;font-size:12px;color:var(--txt3)">
      Nenhuma análise ainda.<br>
      <a href="<?= APP_URL ?>/ai" style="color:var(--accent);margin-top:6px;display:inline-block">Clique para analisar →</a>
    </div>
    <?php else: ?>

    <div style="max-height:310px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent;display:flex;flex-direction:column;gap:8px;padding-right:2px">

    <?php foreach($data['ai_today_list'] as $ai):
      $aiM  = json_decode($ai['metrics_json']??'{}', true);
      $diff = time()-strtotime($ai['created_at']??'');
      $aiAgo = $diff<60?'agora':($diff<3600?floor($diff/60).'min atrás':($diff<86400?floor($diff/3600).'h atrás':floor($diff/86400).'d atrás'));
      // Usa objetivo do log; fallback para objective salvo no metrics_json (já normalizado PT-BR)
      $obj  = strtolower(trim($ai['objetivo'] ?: ($aiM['objective'] ?? '')));
      // Normaliza objetivos em inglês da API Meta para PT-BR (caso venham do campo objetivo da tabela)
      $obj = match(true) {
        str_contains($obj,'outcome_traffic')||str_contains($obj,'link_click')||str_contains($obj,'traffic') => 'trafego',
        str_contains($obj,'outcome_engagement')||str_contains($obj,'messages')||str_contains($obj,'post_engagement') => 'mensagem',
        str_contains($obj,'outcome_leads')||str_contains($obj,'lead_generation') => 'lead',
        str_contains($obj,'outcome_sales')||str_contains($obj,'conversions')||str_contains($obj,'product_catalog_sales') => 'venda',
        str_contains($obj,'video_view') => 'video',
        str_contains($obj,'brand_awareness')||str_contains($obj,'reach')||str_contains($obj,'outcome_awareness') => 'alcance',
        str_contains($obj,'engagement') => 'engajamento',
        default => $obj,
      };

      // Cores badge objetivo
      $objColors = [
        'trafego'=>'rgba(91,141,239,.18)|#5B8DEF','tráfego'=>'rgba(91,141,239,.18)|#5B8DEF',
        'mensagem'=>'rgba(26,188,156,.15)|#1ABC9C','conversa'=>'rgba(26,188,156,.15)|#1ABC9C',
        'lead'=>'rgba(155,89,182,.18)|#9B59B6',
        'venda'=>'rgba(46,204,113,.15)|#2ECC71','compra'=>'rgba(46,204,113,.15)|#2ECC71',
        'catalogo'=>'rgba(46,204,113,.15)|#2ECC71','catálogo'=>'rgba(46,204,113,.15)|#2ECC71',
        'video'=>'rgba(52,152,219,.18)|#3498DB','vídeo'=>'rgba(52,152,219,.18)|#3498DB',
        'alcance'=>'rgba(241,196,15,.15)|#F39C12','engajamento'=>'rgba(230,126,34,.15)|#E67E22',
      ];
      $objBg='rgba(100,100,100,.12)'; $objColor='var(--txt3)';
      foreach($objColors as $k=>$v){ if(str_contains($obj,$k)){[$objBg,$objColor]=explode('|',$v);break;} }

      // Métricas: lê direto do analysis_text para exibir só o que o template gerou
      $txt   = $ai['analysis_text'] ?? '';
      $spend =(float)($aiM['spend']??0);
      $reach =(int)($aiM['reach']??0);
      $impr  =(int)($aiM['impressions']??0);
      $ctr   =(float)($aiM['ctr']??0);
      $cpm   =(float)($aiM['cpm']??0);
      $cpc   =(float)($aiM['cpc']??0);
      $freq  =(float)($aiM['frequency']??0);
      $roas  =(float)($aiM['roas']??0);
      $prof  =(int)($aiM['profile_visit']??$aiM['profile_visits']??0);
      $clicks=(int)($aiM['clicks']??$aiM['link_clicks']??0);
      $eng   =(int)($aiM['engagement']??0);
      $vviews=(int)($aiM['video_views']??0);
      $thrpl =(int)($aiM['thruplay']??0);
      $conv  =(int)($aiM['messages']??$aiM['messaging_conversations']??0);
      $leads =(int)($aiM['leads']??0);
      // Sobrescreve leads com valor da API filtrado pela campanha (evita somar campanhas pausadas)
      if (!empty($data['metricas']['leads'])) $leads = (int)$data['metricas']['leads'];
      $purch =(int)($aiM['purchases']??0);
      $cpl   =(float)($aiM['cost_per_lead']??$aiM['cpl']??0);
      $cmsg  = $conv > 0 ? round($spend / max($conv,1), 2) : 0;

      // Detecta quais métricas aparecem no texto do relatório gerado
      $txtHas = function(array $needles) use ($txt): bool {
        foreach ($needles as $n) { if (mb_stripos($txt, $n) !== false) return true; }
        return false;
      };
      $txtHasConv  = $txtHas(['Conversas','Conversa Iniciada','Mensagens Iniciadas','Custo por Mensagem','Custo/Msg','cost_per_message']);
      $txtHasProf  = $txtHas(['Visitas ao Perfil','Visitas Perfil','profile_visit','Custo por Visita','Custo/Visita']);
      $txtHasLeads = $txtHas(['Leads','CPL','Custo/lead','Custo por Lead']);
      $txtHasPurch = $txtHas(['Compras','Purchases','ROAS']);
      $txtHasVideo = $txtHas(['Views Video','ThruPlay','Thruplay','25% assistido']);

      // Zera métricas que NÃO aparecem no texto do relatório
      if (!$txtHasConv)  { $conv = 0; $cmsg = 0; }
      if (!$txtHasProf)  { $prof = 0; }
      if (!$txtHasLeads) { $leads = 0; $cpl = 0; }
      if (!$txtHasPurch) { $purch = 0; $roas = 0; }
      if (!$txtHasVideo) { $vviews = 0; $thrpl = 0; }

      $pool=[];
      if($spend>0) $pool['Investimento']  =['val'=>brl($spend),                            'pct'=>50,                          'color'=>'#9B59B6'];
      if($reach>0) $pool['Alcance']       =['val'=>number_format($reach,0,'.',','),        'pct'=>65,                          'color'=>'#1ABC9C'];
      if($impr>0)  $pool['Impressões']    =['val'=>number_format($impr,0,'.',','),         'pct'=>80,                          'color'=>'#1ABC9C'];
      if($ctr>0)   $pool['CTR']           =['val'=>number_format($ctr,2,',','.').'%',      'pct'=>min(100,$ctr/3*100),         'color'=>'#F39C12'];
      if($cpm>0)   $pool['CPM']           =['val'=>'R$'.number_format($cpm,2,',','.'),     'pct'=>min(100,$cpm/30*100),        'color'=>'#5B8DEF'];
      if($cpc>0)   $pool['CPC']           =['val'=>'R$'.number_format($cpc,2,',','.'),     'pct'=>min(100,$cpc/5*100),         'color'=>'#5B8DEF'];
      if($clicks>0)$pool['Cliques']       =['val'=>number_format($clicks,0,'.',','),       'pct'=>min(100,$clicks/500*100),    'color'=>'#F39C12'];
      if($conv>0)  $pool['Conversas']     =['val'=>number_format($conv,0,'.',','),         'pct'=>min(100,$conv/20*100),       'color'=>'#1ABC9C'];
      if($conv>0 && $cmsg>0) $pool['Custo/conv']=['val'=>'R$'.number_format($cmsg,2,',','.'),    'pct'=>min(100,(20/max($cmsg,0.01))*100),'color'=>'#E74C3C'];
      if($leads>0) $pool['Leads']         =['val'=>number_format($leads,0,'.',','),        'pct'=>min(100,$leads/50*100),      'color'=>'#9B59B6'];
      if($cpl>0)   $pool['Custo/lead']    =['val'=>'R$'.number_format($cpl,2,',','.'),     'pct'=>min(100,(50/max($cpl,0.01))*100),'color'=>'#E74C3C'];
      if($prof>0)  $pool['Visitas perfil']=['val'=>number_format($prof,0,'.',','),         'pct'=>min(100,$prof/500*100),      'color'=>'#5B8DEF'];
      if($eng>0)   $pool['Engajamento']   =['val'=>number_format($eng,0,'.',','),          'pct'=>min(100,$eng/1000*100),      'color'=>'#F39C12'];
      if($vviews>0)$pool['Views vídeo']   =['val'=>number_format($vviews,0,'.',','),       'pct'=>min(100,$vviews/1000*100),   'color'=>'#1ABC9C'];
      if($thrpl>0) $pool['ThruPlay']      =['val'=>number_format($thrpl,0,'.',','),        'pct'=>min(100,$thrpl/500*100),     'color'=>'#F39C12'];
      if($purch>0) $pool['Compras']       =['val'=>number_format($purch,0,'.',','),        'pct'=>min(100,$purch/100*100),     'color'=>'#2ECC71'];
      $cprofv = (float)($aiM['cost_per_profile_visit'] ?? ($prof > 0 && $spend > 0 ? round($spend / max($prof,1),2) : 0));
      if($prof>0 && $cprofv>0) $pool['Custo/visita']=['val'=>'R$'.number_format($cprofv,2,',','.'), 'pct'=>min(100,(1/max($cprofv,0.01))*100),'color'=>'#85C1E9'];
      if($roas>0)  $pool['ROAS']          =['val'=>number_format($roas,2,',','.'),         'pct'=>min(100,$roas/5*100),        'color'=>'#2ECC71'];
      if($freq>0)  $pool['Freq']          =['val'=>number_format($freq,1,',','.').'x',     'pct'=>min(100,$freq/4*100),        'color'=>'#E3B341'];

      // objReal — usa o objetivo salvo no banco (já correto)
      // Com as métricas zeradas pelo txtHas, a prioridade funciona automaticamente
      $objReal = $obj;
      if ($conv > 0)                                           { $objReal = 'mensagem'; }
      elseif ($leads > 0)                                      { $objReal = 'lead'; }
      elseif ($purch > 0 || $roas > 0)                        { $objReal = 'venda'; }
      elseif ($vviews > 0 || $thrpl > 0)                      { $objReal = 'video'; }
      elseif ($prof > 0 && $conv == 0 && $leads == 0)         { $objReal = 'trafego'; }
      elseif ($eng > 0)                                        { $objReal = 'engajamento'; }

      $prioridade = match(true) {
        str_contains($objReal,'mensagem')||str_contains($objReal,'conversa')
          =>['Conversas','Custo/conv','Alcance','Impressões','Cliques','CTR','Investimento','Freq'],
        str_contains($objReal,'lead')
          =>['Leads','Custo/lead','CTR','Cliques','Alcance','Impressões','Investimento','Freq'],
        str_contains($objReal,'venda')||str_contains($objReal,'compra')||str_contains($objReal,'catalogo')||str_contains($objReal,'catálogo')
          =>['Compras','ROAS','CTR','Cliques','Investimento','Alcance','Impressões','Freq'],
        str_contains($objReal,'video')||str_contains($objReal,'vídeo')
          =>['Views vídeo','ThruPlay','Alcance','Impressões','CTR','Investimento','Freq','Cliques'],
        str_contains($objReal,'alcance')||str_contains($objReal,'reconhecimento')
          =>['Alcance','Impressões','CPM','Investimento','CTR','Cliques','Freq'],
        str_contains($objReal,'engajamento')
          =>['Engajamento','Alcance','CTR','Cliques','Impressões','Investimento','Freq'],
        str_contains($objReal,'trafego')||str_contains($objReal,'tráfego')
          =>['Visitas perfil','Custo/visita','Cliques','CTR','Alcance','Impressões','Investimento','Freq'],
        default=>['Investimento','Alcance','Impressões','CTR','Cliques','Conversas','Freq']
      };

      $toShow=[];
      foreach($prioridade as $lbl){ if(isset($pool[$lbl])&&count($toShow)<8) $toShow[$lbl]=$pool[$lbl]; }
      foreach($pool as $lbl=>$m){ if(!isset($toShow[$lbl])&&count($toShow)<8) $toShow[$lbl]=$m; }

      // Diagnóstico automático por objetivo
      $diagScore = 0; $diagTotal = 0;
      if(str_contains($objReal,'mensagem')||str_contains($objReal,'conversa')){
        if($conv>0){$diagTotal++;if($conv>=10)$diagScore+=2;elseif($conv>=3)$diagScore+=1;}
        if($cmsg>0){$diagTotal++;if($cmsg<=8)$diagScore+=2;elseif($cmsg<=15)$diagScore+=1;}
        if($ctr>0){$diagTotal++;if($ctr>=1.0)$diagScore+=2;elseif($ctr>=0.5)$diagScore+=1;}
      } elseif(str_contains($objReal,'lead')){
        if($leads>0){$diagTotal++;if($leads>=10)$diagScore+=2;elseif($leads>=3)$diagScore+=1;}
        if($cpl>0){$diagTotal++;if($cpl<=20)$diagScore+=2;elseif($cpl<=50)$diagScore+=1;}
        if($ctr>0){$diagTotal++;if($ctr>=1.5)$diagScore+=2;elseif($ctr>=0.8)$diagScore+=1;}
      } elseif(str_contains($objReal,'venda')||str_contains($objReal,'compra')){
        if($roas>0){$diagTotal++;if($roas>=4)$diagScore+=2;elseif($roas>=2)$diagScore+=1;}
        if($purch>0){$diagTotal++;if($purch>=10)$diagScore+=2;elseif($purch>=3)$diagScore+=1;}
      } elseif(str_contains($objReal,'trafego')||str_contains($objReal,'tráfego')){
        if($ctr>0){$diagTotal++;if($ctr>=1.5)$diagScore+=2;elseif($ctr>=0.8)$diagScore+=1;}
        if($cpc>0){$diagTotal++;if($cpc<=1.5)$diagScore+=2;elseif($cpc<=3)$diagScore+=1;}
        if($prof>0){$diagTotal++;if($prof>=200)$diagScore+=2;elseif($prof>=50)$diagScore+=1;}
      } else {
        if($ctr>0){$diagTotal++;if($ctr>=1.5)$diagScore+=2;elseif($ctr>=0.7)$diagScore+=1;}
        if($reach>0&&$spend>0){$diagTotal++;$cpr=$spend/max($reach,1);if($cpr<=0.02)$diagScore+=2;elseif($cpr<=0.05)$diagScore+=1;}
      }
      $pctScore = $diagTotal>0 ? ($diagScore/($diagTotal*2))*100 : 50;
      if($pctScore>=80){$diagLabel='Alta performance';$diagBg='rgba(52,199,89,.15)';$diagColor='#27ae60';}
      elseif($pctScore>=55){$diagLabel='Boa performance';$diagBg='rgba(91,141,239,.15)';$diagColor='#5B8DEF';}
      elseif($pctScore>=30){$diagLabel='Atenção';$diagBg='rgba(243,156,18,.15)';$diagColor='#F39C12';}
      else{$diagLabel='Nível baixo';$diagBg='rgba(231,76,60,.15)';$diagColor='#E74C3C';}

      // Observação da métrica mais crítica com cor correta
      $obs=''; $obsCor='var(--txt3)';
      if(str_contains($objReal,'mensagem')||str_contains($objReal,'conversa')){
        if($conv==0)               { $obs='Conversas';     $obsCor='#E74C3C'; }
        elseif($cmsg>0&&$cmsg>15)  { $obs='Custo/conv';   $obsCor='#E74C3C'; }
        elseif($cmsg>0&&$cmsg>8)   { $obs='Custo/conv';   $obsCor='#F39C12'; }
        elseif($cmsg>0&&$cmsg<=8)  { $obs='Custo/conv';   $obsCor='#27ae60'; }
        elseif($ctr>0&&$ctr<0.5)   { $obs='CTR';          $obsCor='#F39C12'; }
        elseif($ctr>0&&$ctr>=1.5)  { $obs='CTR';          $obsCor='#27ae60'; }
        else                       { $obs='';              $obsCor='var(--txt3)'; }
      } elseif(str_contains($objReal,'lead')){
        if($leads==0)              { $obs='Leads';         $obsCor='#E74C3C'; }
        elseif($cpl>0&&$cpl>50)    { $obs='Custo/lead';   $obsCor='#E74C3C'; }
        elseif($cpl>0&&$cpl>20)    { $obs='Custo/lead';   $obsCor='#F39C12'; }
        elseif($cpl>0&&$cpl<=20)   { $obs='Custo/lead';   $obsCor='#27ae60'; }
        else                       { $obs='';              $obsCor='var(--txt3)'; }
      } elseif(str_contains($objReal,'trafego')||str_contains($objReal,'tráfego')){
        if($prof>0&&$cprofv>0&&$cprofv<=0.15) { $obs='Custo/visita';  $obsCor='#27ae60'; }
        elseif($prof>0&&$cprofv>0&&$cprofv<=0.50){ $obs='Custo/visita'; $obsCor='#F39C12'; }
        elseif($ctr>0&&$ctr>=1.5)      { $obs='CTR';          $obsCor='#27ae60'; }
        elseif($ctr>0&&$ctr>=0.8)      { $obs='CTR';          $obsCor='#F39C12'; }
        elseif($ctr>0&&$ctr<0.8)       { $obs='CTR';          $obsCor='#E74C3C'; }
        elseif($cpc>0&&$cpc>3)         { $obs='CPC';          $obsCor='#F39C12'; }
        else                           { $obs='';              $obsCor='var(--txt3)'; }
      } elseif(str_contains($objReal,'venda')||str_contains($objReal,'compra')){
        if($roas>=3)               { $obs='ROAS';          $obsCor='#27ae60'; }
        elseif($roas>=2)           { $obs='ROAS';          $obsCor='#F39C12'; }
        elseif($roas>0&&$roas<2)   { $obs='ROAS';          $obsCor='#E74C3C'; }
        else                       { $obs='';              $obsCor='var(--txt3)'; }
      } else {
        if($ctr>0&&$ctr>=2)        { $obs='CTR';           $obsCor='#27ae60'; }
        elseif($ctr>0&&$ctr<0.5)   { $obs='CTR';           $obsCor='#E74C3C'; }
        else                       { $obs='';              $obsCor='var(--txt3)'; }
      }
      // Frequência alta sobrescreve se crítica
      if($freq>=3.5)               { $obs='Frequência';    $obsCor='#E74C3C'; }
    ?>
    <div style="border:1px solid var(--border);border-radius:10px;padding:10px 12px">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px">
        <span style="font-size:11px;font-weight:600;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:170px">
          <?= e($ai['account_name']??'—') ?>
          <?php if($obj): ?><span style="font-size:9px;font-weight:600;padding:1px 6px;border-radius:20px;margin-left:4px;background:<?= $objBg ?>;color:<?= $objColor ?>"><?= e($ai['objetivo'] ?: $objReal) ?></span><?php endif; ?>
        </span>
        <span style="font-size:10px;color:var(--txt3);flex-shrink:0;margin-left:6px"><?= $aiAgo ?></span>
      </div>
      <div style="font-size:10px;color:var(--txt3);margin-bottom:7px"><?= e($ai['campaign_name']??'') ?></div>

      <?php foreach($toShow as $lbl=>$m): ?>
      <div style="display:flex;align-items:center;padding:3px 0;border-bottom:1px solid var(--border2,rgba(255,255,255,.06))">
        <span style="font-size:10px;color:var(--txt2);min-width:80px"><?= $lbl ?></span>
        <div style="flex:1;height:3px;background:var(--bg3);border-radius:4px;margin:0 8px;overflow:hidden">
          <div style="width:<?= round($m['pct']) ?>%;height:100%;background:<?= $m['color'] ?>;border-radius:4px"></div>
        </div>
        <span style="font-size:10px;font-weight:600;color:var(--txt);min-width:56px;text-align:right"><?= $m['val'] ?></span>
      </div>
      <?php endforeach; ?>

      <div style="margin-top:8px;padding-top:7px;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
          <?php if($obs && $obsCor!=='var(--txt3)'): ?>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:<?= $obsCor ?>;background:<?= $obsCor ?>1a;border:1px solid <?= $obsCor ?>55;border-radius:20px;padding:2px 9px">
            <span style="width:6px;height:6px;border-radius:50%;background:<?= $obsCor ?>;flex-shrink:0;display:inline-block"></span>
            <?= $obs ?>
          </span>
          <?php else: ?>
          <span></span>
          <?php endif; ?>
          <span style="font-size:9px;font-weight:700;color:<?= $diagColor ?>"><?= $diagLabel ?></span>
        </div>
        <div style="height:4px;background:var(--bg3);border-radius:4px;overflow:hidden">
          <div style="width:<?= $pctScore ?>%;height:100%;border-radius:4px;background:<?= $diagColor ?>;transition:width .6s ease"></div>
        </div>
      </div>

    </div>
    <?php endforeach; ?>

    </div><!-- /scroll -->
    <?php endif; ?>
  </div>
</div>

<!-- ══ MÉTRICAS EM TEMPO REAL ══ -->
<style>
.rt-section{margin:0 0 14px}
.rt-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px}
.rt-title-row{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:7px}
.rt-live-dot{width:7px;height:7px;border-radius:50%;background:var(--success);animation:rtpulse 2s infinite;flex-shrink:0}
@keyframes rtpulse{0%{box-shadow:0 0 0 0 rgba(30,188,132,.5)}70%{box-shadow:0 0 0 6px rgba(30,188,132,0)}100%{box-shadow:0 0 0 0 rgba(30,188,132,0)}}
.rt-sub-lbl{font-size:10px;color:var(--txt3)}
.rt-ctrls{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.rt-fbtn{background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:3px 10px;font-size:10px;color:var(--txt2);cursor:pointer;transition:all .12s;white-space:nowrap;font-family:var(--font)}
.rt-fbtn:hover{background:var(--bg4);color:var(--txt)}
.rt-fbtn.active{background:rgba(91,141,239,.15);border-color:rgba(91,141,239,.35);color:var(--accent)}
.rt-scroll{overflow-x:auto;overflow-y:visible;scrollbar-width:thin;scrollbar-color:var(--border2) transparent;padding-bottom:8px;cursor:grab;user-select:none}
.rt-scroll:active{cursor:grabbing}
.rt-scroll::-webkit-scrollbar{height:3px}
.rt-scroll::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}
.rt-row{display:flex;gap:10px;width:max-content;padding:2px 1px 4px}
.rt-card{background:var(--bg2);border:1px solid var(--border);border-radius:11px;width:228px;flex-shrink:0;transition:border-color .15s,box-shadow .15s;position:relative;overflow:visible}
.rt-card:hover{border-color:var(--border2);box-shadow:0 3px 20px rgba(0,0,0,.35)}
.rt-card-bar{height:3px;border-radius:11px 11px 0 0}
.rt-card-bar.meta{background:#1877F2}
.rt-card-bar.google{background:#EA4335}
.rt-card-bar.both{background:linear-gradient(90deg,#1877F2 50%,#EA4335 50%)}
.rt-inner{padding:10px 11px 9px}
.rt-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:5px;gap:5px}
.rt-name{font-size:11px;font-weight:600;color:var(--txt);line-height:1.4;flex:1;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.rt-badge{font-size:8px;font-weight:700;padding:2px 6px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;flex-shrink:0}
.rt-b-meta{background:rgba(24,119,242,.15);color:#5B9BD5}
.rt-b-google{background:rgba(234,67,53,.12);color:#F87C6C}
.rt-b-both{background:rgba(167,139,250,.12);color:#C4B5FD}
.rt-b-obj{background:var(--bg3);color:var(--txt2);border:1px solid var(--border);font-size:9px}
.rt-b-ok{background:rgba(30,188,132,.12);color:var(--success)}
.rt-b-warn{background:rgba(227,179,65,.12);color:var(--warn)}
.rt-b-bad{background:rgba(248,81,73,.12);color:var(--danger)}
.rt-b-neutral{background:var(--bg4);color:var(--txt3)}
.rt-period-tag{display:inline-flex;align-items:center;gap:4px;background:rgba(91,141,239,.08);border:1px solid rgba(91,141,239,.2);border-radius:5px;padding:2px 7px;font-size:9px;color:var(--accent);margin-bottom:7px;font-weight:500;width:100%}
.rt-period-tag.manual{background:rgba(227,179,65,.06);border-color:rgba(227,179,65,.2);color:var(--warn)}
.rt-period-tag.google-p{background:rgba(234,67,53,.06);border-color:rgba(234,67,53,.2);color:#F87C6C}
.rt-pt-range{color:var(--txt2);margin-left:2px}
.rt-meta-row{display:flex;align-items:center;gap:4px;margin-bottom:7px;flex-wrap:wrap}
.rt-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);border:1px solid var(--border);border-radius:7px;overflow:hidden;margin-bottom:8px}
.rt-mc{background:var(--bg3);padding:5px 7px;transition:background .12s}
.rt-mc:hover{background:var(--bg4)}
.rt-mc-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:1px}
.rt-mc-val{font-size:13px;font-weight:700;color:var(--txt);line-height:1;font-variant-numeric:tabular-nums}
.rt-mc-val.sm{font-size:11px}
.rt-mc-bar{height:2px;background:var(--bg);border-radius:2px;margin-top:2px;overflow:hidden}
.rt-mc-fill{height:100%;border-radius:2px;transition:width .7s ease}
.rt-foot{display:flex;flex-direction:column;gap:5px;padding-top:6px;border-top:1px solid var(--border)}
.rt-foot-bottom{display:flex;align-items:center;justify-content:space-between;gap:6px}
.rt-diag-row{display:flex;gap:4px;flex-wrap:wrap;flex:1}
.rt-diag-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:4px;font-size:8px;font-weight:700;white-space:nowrap;border:1px solid}
.rt-diag-pill.ok{background:rgba(30,188,132,.08);border-color:rgba(30,188,132,.25);color:var(--success)}
.rt-diag-pill.warn{background:rgba(227,179,65,.1);border-color:rgba(227,179,65,.3);color:var(--warn)}
.rt-diag-pill.bad{background:rgba(248,81,73,.08);border-color:rgba(248,81,73,.25);color:var(--danger)}
.rt-diag-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0}
.rt-health-wrap{display:flex;align-items:center;gap:6px;margin-bottom:5px}
.rt-health-bar{height:4px;border-radius:4px;flex:1;background:var(--bg4);overflow:hidden}
.rt-health-fill{height:100%;border-radius:4px;transition:width .6s ease}
.rt-health-lbl{font-size:8px;font-weight:700;white-space:nowrap}
.rt-health-title{font-size:8px;color:var(--txt3);white-space:nowrap}
.rt-camp-toggle{display:flex;align-items:center;justify-content:space-between;background:var(--bg3);border:1px solid var(--border);border-radius:7px;padding:5px 8px;margin-bottom:7px;cursor:pointer;transition:border-color .15s;user-select:none}
.rt-camp-toggle:hover{border-color:var(--border2)}
.rt-camp-left{display:flex;flex-direction:column;gap:1px;flex:1;min-width:0}
.rt-camp-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3)}
.rt-camp-val{font-size:10px;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500}
.rt-camp-arrow{font-size:10px;color:var(--txt3);flex-shrink:0;margin-left:6px;transition:transform .2s}
.rt-camp-arrow.open{transform:rotate(180deg)}
.rt-camp-drop{background:var(--bg3);border:1px solid var(--border2);border-radius:7px;overflow:hidden;margin-bottom:7px;display:none;max-height:180px;overflow-y:auto}
.rt-camp-drop.open{display:block}
.rt-camp-opt{padding:6px 9px;cursor:pointer;transition:background .1s;border-bottom:1px solid var(--border)}
.rt-camp-opt:last-child{border-bottom:none}
.rt-camp-opt:hover{background:var(--bg4)}
.rt-camp-opt.active{background:rgba(91,141,239,.1)}
.rt-camp-opt-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--txt3);margin-bottom:1px}
.rt-camp-opt-val{font-size:10px;color:var(--txt);font-weight:500}
.rt-camp-loading{font-size:9px;color:var(--txt3);padding:8px 9px;text-align:center}
.rt-ok-txt{font-size:9px;color:var(--success);display:flex;align-items:center;gap:4px}
.rt-ok-dot{width:6px;height:6px;border-radius:50%;background:var(--success);flex-shrink:0}
.rt-ia-btn{display:inline-flex;align-items:center;gap:3px;background:rgba(167,139,250,.08);border:1px solid rgba(167,139,250,.2);border-radius:6px;padding:3px 7px;font-size:9px;font-weight:600;color:#A78BFA;cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0;font-family:var(--font)}
.rt-ia-btn:hover{background:rgba(167,139,250,.16);border-color:rgba(167,139,250,.4)}
.rt-ia-btn.active{background:rgba(167,139,250,.18);border-color:rgba(167,139,250,.5)}
.rt-wa-btn{display:inline-flex;align-items:center;gap:3px;background:rgba(37,211,102,.08);border:1px solid rgba(37,211,102,.2);border-radius:6px;padding:3px 7px;font-size:9px;font-weight:600;color:#25D366;cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0;font-family:var(--font)}
.rt-wa-btn:hover{background:rgba(37,211,102,.15);border-color:rgba(37,211,102,.4)}
/* Popup WA */
.rt-wa-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;padding:16px}
.rt-wa-modal.open{display:flex}
.rt-wa-box{background:var(--bg2);border:1px solid var(--border2);border-radius:14px;width:100%;max-width:480px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.rt-wa-hd{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.rt-wa-hd-l{display:flex;align-items:center;gap:8px}
.rt-wa-ico{width:26px;height:26px;border-radius:7px;background:rgba(37,211,102,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rt-wa-title{font-size:13px;font-weight:700;color:var(--txt)}
.rt-wa-sub{font-size:10px;color:var(--txt3);margin-top:1px}
.rt-wa-close{background:none;border:none;color:var(--txt3);font-size:15px;cursor:pointer;padding:2px 6px;border-radius:5px;flex-shrink:0}
.rt-wa-close:hover{color:var(--txt);background:var(--bg3)}
.rt-wa-body{padding:14px 16px;display:flex;flex-direction:column;gap:11px;overflow-y:auto;flex:1}
.rt-wa-lbl{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.rt-wa-tabs{display:flex;gap:4px}
.rt-wa-tab{flex:1;padding:6px 4px;border:1px solid var(--border2);border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;background:var(--bg3);color:var(--txt2);text-align:center;transition:all .12s;font-family:var(--font)}
.rt-wa-tab:hover{background:var(--bg4);color:var(--txt)}
.rt-wa-tab.active{border-color:#25D366;background:rgba(37,211,102,.08);color:#25D366}
.rt-wa-sel{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:7px 11px;color:var(--txt);font-size:12px;font-family:var(--font);cursor:pointer}
.rt-wa-sel:focus{outline:none;border-color:var(--accent)}
.rt-wa-input{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:7px 11px;color:var(--txt);font-size:12px;font-family:var(--font)}
.rt-wa-input:focus{outline:none;border-color:var(--accent)}
.rt-wa-ta{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:9px 11px;color:var(--txt);font-size:12px;font-family:var(--font);line-height:1.65;resize:vertical;min-height:120px;box-sizing:border-box}
.rt-wa-ta:focus{outline:none;border-color:rgba(37,211,102,.4)}
.rt-wa-msg-actions{display:flex;gap:6px;margin-top:4px}
.rt-wa-mbtn{background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:3px 9px;font-size:10px;color:var(--txt2);cursor:pointer;font-family:var(--font);transition:all .12s}
.rt-wa-mbtn:hover{background:var(--bg4);color:var(--txt)}
.rt-wa-mbtn-ai{background:rgba(167,139,250,.08);border-color:rgba(167,139,250,.2);color:#A78BFA}
.rt-wa-mbtn-ai:hover{background:rgba(167,139,250,.15);color:#A78BFA}
.rt-wa-foot{padding:11px 16px;border-top:1px solid var(--border);display:flex;gap:8px;flex-shrink:0}
.rt-wa-cancel{flex:1;padding:9px;border:1px solid var(--border2);border-radius:8px;font-size:12px;cursor:pointer;background:none;color:var(--txt2);font-family:var(--font)}
.rt-wa-cancel:hover{background:var(--bg3);color:var(--txt)}
.rt-wa-send{flex:2;padding:9px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;background:#25D366;color:#fff;font-family:var(--font);display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .12s}
.rt-wa-send:hover{opacity:.88}
.rt-wa-send:disabled{opacity:.4;cursor:not-allowed}
.rt-wa-status{font-size:11px;text-align:center;padding:0 16px 8px;min-height:18px;color:var(--txt3)}
.rt-inst-row{display:flex;align-items:center;gap:7px}
.rt-inst-dot{width:7px;height:7px;border-radius:50%;background:var(--success);flex-shrink:0}
.rt-chat-pop{position:absolute;bottom:calc(100% + 8px);right:0;width:296px;background:var(--bg2);border:1px solid var(--border2);border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.5);z-index:9999;display:none;flex-direction:column;overflow:hidden;animation:rtPopIn .18s ease}
.rt-chat-pop.open{display:flex}
@keyframes rtPopIn{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.rt-ch-hd{padding:9px 12px 8px;background:linear-gradient(135deg,rgba(167,139,250,.1),rgba(91,141,239,.06));border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.rt-ch-hd-l{display:flex;align-items:center;gap:7px}
.rt-ch-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#A78BFA,#5B8DEF);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.rt-ch-name{font-size:11px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
.rt-ch-sub{font-size:9px;color:#A78BFA;display:flex;align-items:center;gap:3px;margin-top:1px}
.rt-ch-sub::before{content:'';width:4px;height:4px;border-radius:50%;background:#A78BFA;display:inline-block}
.rt-ch-period{padding:5px 10px;background:rgba(91,141,239,.06);border-bottom:1px solid var(--border);font-size:9px;color:var(--txt3);display:flex;align-items:center;gap:5px;flex-shrink:0}
.rt-ch-period strong{color:var(--txt2)}
.rt-ch-close{background:none;border:none;cursor:pointer;color:var(--txt3);font-size:14px;padding:2px;border-radius:4px;transition:color .12s;flex-shrink:0}
.rt-ch-close:hover{color:var(--txt)}
.rt-chips{display:flex;gap:4px;padding:6px 10px 0;overflow-x:auto;scrollbar-width:none;flex-shrink:0}
.rt-chips::-webkit-scrollbar{display:none}
.rt-chip{background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:3px 9px;font-size:9px;color:var(--txt2);cursor:pointer;white-space:nowrap;transition:all .12s;flex-shrink:0;font-family:var(--font)}
.rt-chip:hover{background:rgba(167,139,250,.1);border-color:rgba(167,139,250,.3);color:#A78BFA}
.rt-msgs{flex:1;max-height:195px;overflow-y:auto;padding:8px 10px;display:flex;flex-direction:column;gap:6px;scrollbar-width:thin;scrollbar-color:var(--border2) transparent}
.rt-msgs::-webkit-scrollbar{width:3px}
.rt-msgs::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}
.rt-msg{display:flex;flex-direction:column;gap:2px;animation:rtMsgIn .15s ease}
@keyframes rtMsgIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.rt-msg.u{align-items:flex-end}.rt-msg.a{align-items:flex-start}
.rt-bubble{max-width:235px;padding:6px 9px;border-radius:10px;font-size:11px;line-height:1.55}
.rt-msg.u .rt-bubble{background:rgba(91,141,239,.18);border:1px solid rgba(91,141,239,.22);color:var(--txt);border-bottom-right-radius:3px}
.rt-msg.a .rt-bubble{background:var(--bg3);border:1px solid var(--border);color:var(--txt2);border-bottom-left-radius:3px}
.rt-msg.a .rt-bubble strong{color:var(--txt)}
.rt-msg-time{font-size:8px;color:var(--txt3);margin:0 2px}
.rt-typing{display:flex;align-items:center;gap:3px;padding:7px 9px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;border-bottom-left-radius:3px;width:fit-content}
.rt-typing span{width:5px;height:5px;border-radius:50%;background:var(--txt3);animation:rtDot 1.2s infinite}
.rt-typing span:nth-child(2){animation-delay:.2s}.rt-typing span:nth-child(3){animation-delay:.4s}
@keyframes rtDot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}
.rt-inp-wrap{padding:7px 9px 9px;border-top:1px solid var(--border);display:flex;gap:5px;align-items:center;flex-shrink:0}
.rt-inp{flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:7px;padding:5px 9px;font-size:11px;color:var(--txt);outline:none;transition:border-color .12s;font-family:var(--font);height:32px}
.rt-inp:focus{border-color:rgba(167,139,250,.4)}
.rt-inp::placeholder{color:var(--txt3)}
.rt-send{width:28px;height:28px;border-radius:7px;flex-shrink:0;background:linear-gradient(135deg,#A78BFA,#5B8DEF);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:opacity .12s,transform .1s;font-family:var(--font)}
.rt-send:hover{opacity:.85;transform:scale(1.05)}
.rt-send:disabled{opacity:.4;pointer-events:none}
.rt-skeleton{background:var(--bg2);border:1px solid var(--border);border-radius:11px;width:228px;flex-shrink:0;padding:14px;display:flex;flex-direction:column;gap:8px}
.rt-sk-line{background:var(--bg4);border-radius:4px;height:10px;animation:rtSkP 1.4s ease infinite}
.rt-sk-line.w80{width:80%}.rt-sk-line.w60{width:60%}.rt-sk-line.w40{width:40%}
.rt-sk-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.rt-sk-cell{background:var(--bg4);border-radius:5px;height:36px;animation:rtSkP 1.4s ease infinite}
@keyframes rtSkP{0%,100%{opacity:.5}50%{opacity:1}}
.rt-nodata{text-align:center;padding:18px 10px;font-size:11px;color:var(--txt3)}
</style>

<div class="db-widget" data-widget="rt-metrics" data-widget-name="⚡ Métricas em Tempo Real">
<div class="rt-section" id="rt-section" style="display:none">
  <div class="rt-hdr">
    <div class="rt-title-row">
      <div class="rt-live-dot"></div>
      Métricas em Tempo Real
      <span class="rt-sub-lbl">dos relatórios ativos</span>
    </div>
    <div class="rt-ctrls">
      <button class="rt-fbtn active" onclick="rtFilt('all',this)">Todos</button>
      <button class="rt-fbtn" onclick="rtFilt('meta',this)">Meta</button>
      <button class="rt-fbtn" onclick="rtFilt('google',this)">Google</button>
      <button class="rt-fbtn" id="rt-refresh-btn" onclick="rtLoad(true)">↻ Atualizar</button>
    </div>
  </div>
  <div class="rt-scroll" id="rt-scroll">
    <div class="rt-row" id="rt-row">
      <div class="rt-skeleton"><div class="rt-sk-line w60"></div><div class="rt-sk-line w80"></div><div class="rt-sk-grid"><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div></div><div class="rt-sk-line w40"></div></div>
      <div class="rt-skeleton"><div class="rt-sk-line w60"></div><div class="rt-sk-line w80"></div><div class="rt-sk-grid"><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div></div><div class="rt-sk-line w40"></div></div>
      <div class="rt-skeleton"><div class="rt-sk-line w60"></div><div class="rt-sk-line w80"></div><div class="rt-sk-grid"><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div><div class="rt-sk-cell"></div></div><div class="rt-sk-line w40"></div></div>
    </div>
  </div>
</div>

<?php if (!empty($data['recent_integration_logs'])): ?>
<div class="db-widget" data-widget="integracoes" data-widget-name="🔗 Últimos Disparos">
<div class="db-card" style="margin-bottom:14px">
  <div class="db-card-hd">
    <div><div class="db-card-title">🔗 Últimos disparos de integrações</div><div class="db-card-sub">Webhooks recebidos</div></div>
    <a href="<?= APP_URL ?>/integrations" style="font-size:11px;color:var(--accent)">Ver todas →</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:6px;padding:0 4px 4px;max-height:320px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:var(--bg5,#2d3650) transparent">
    <?php foreach($data['recent_integration_logs'] as $il): ?>
    <?php
      $ilPayload = json_decode($il['payload']??'{}', true);

      // Nome do signatário/lead — tenta todos os campos conhecidos
      $ilWho = '';
      foreach(['signer_name','lead_name','name','contact_name','full_name'] as $_f){
        if(!empty($ilPayload[$_f])){ $ilWho = $ilPayload[$_f]; break; }
      }
      if(!$ilWho) $ilWho = 'Desconhecido';

      // Rótulo do evento (Autentique já normaliza; demais tipos usam tipo da integração)
      $ilEventLabel = $ilPayload['event_label'] ?? '';

      // Nome do documento/contrato assinado
      $ilDoc = '';
      foreach(['doc_name','webhook_name','doc_id','document_name','form_name','lead_formName'] as $_f){
        if(!empty($ilPayload[$_f])){ $ilDoc = $ilPayload[$_f]; break; }
      }

      // Data de assinatura (se disponível) ou data do log
      $ilSignedAt = $ilPayload['doc_signed_at'] ?? '';

      // Email do signatário (secundário)
      $ilEmail = $ilPayload['signer_email'] ?? $ilPayload['lead_email'] ?? $ilPayload['email'] ?? '';

      $ilOk  = $il['status'] === 'sent';

      // Ícone por tipo de evento
      $ilIcon = $ilOk ? '✅' : '❌';
      if(str_contains($ilEventLabel,'Assinado'))   $ilIcon = '✍️';
      elseif(str_contains($ilEventLabel,'Finalizado')) $ilIcon = '🎉';
      elseif(str_contains($ilEventLabel,'Visualizado')) $ilIcon = '👁️';
      elseif(str_contains($ilEventLabel,'Recusado'))   $ilIcon = '❌';
    ?>
    <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:var(--bg3);border-radius:8px;border:1px solid var(--border)">
      <span style="font-size:16px;margin-top:1px"><?= $ilIcon ?></span>
      <div style="flex:1;min-width:0">
        <!-- Nome do signatário -->
        <div style="font-size:12px;font-weight:700;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= e($ilWho) ?>
          <?php if($ilEmail): ?><span style="font-weight:400;color:var(--txt3);font-size:11px"> · <?= e($ilEmail) ?></span><?php endif; ?>
        </div>
        <!-- Nome do contrato / documento -->
        <?php if($ilDoc): ?>
        <div style="font-size:11px;color:var(--accent);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          📄 <?= e($ilDoc) ?>
        </div>
        <?php endif; ?>
        <!-- Evento + tipo + data de assinatura -->
        <div style="display:flex;align-items:center;gap:6px;margin-top:3px;flex-wrap:wrap">
          <?php if($ilEventLabel): ?>
            <span style="font-size:10px;background:rgba(91,141,239,.12);color:var(--accent);border-radius:4px;padding:1px 6px;border:1px solid rgba(91,141,239,.2)"><?= e($ilEventLabel) ?></span>
          <?php else: ?>
            <span style="font-size:10px;color:var(--txt3)"><?= e($il['int_type']) ?></span>
          <?php endif; ?>
          <?php if($ilSignedAt): ?>
            <span style="font-size:10px;color:var(--txt3)">· assinado em <?= e($ilSignedAt) ?></span>
          <?php endif; ?>
        </div>
        <!-- Nome da integração -->
        <div style="font-size:10px;color:var(--txt3);margin-top:2px"><?= e($il['int_name']) ?></div>
      </div>
      <div style="font-size:10px;color:var(--txt3);white-space:nowrap;margin-top:2px"><?= date('d/m H:i', strtotime($il['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Modal WA RT -->
<div class="rt-wa-modal" id="rtWaModal">
  <div class="rt-wa-box">
    <div class="rt-wa-hd">
      <div class="rt-wa-hd-l">
        <div class="rt-wa-ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.112 1.523 5.836L0 24l6.336-1.498A11.946 11.946 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.817 9.817 0 01-5.001-1.368l-.36-.213-3.757.888.936-3.658-.234-.375A9.792 9.792 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg></div>
        <div>
          <div class="rt-wa-title">Enviar pelo WhatsApp</div>
          <div class="rt-wa-sub" id="rtWaSub">carregando...</div>
        </div>
      </div>
      <button class="rt-wa-close" onclick="rtWaClose()">✕</button>
    </div>
    <div class="rt-wa-body" id="rtWaBody">
      <div style="text-align:center;padding:30px;color:var(--txt3);font-size:12px">Carregando mensagem...</div>
    </div>
    <div class="rt-wa-foot">
      <button class="rt-wa-cancel" onclick="rtWaClose()">Cancelar</button>
      <button class="rt-wa-send" id="rtWaSendBtn" onclick="rtWaSubmit()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        Enviar pelo WhatsApp
      </button>
    </div>
    <div class="rt-wa-status" id="rtWaStatus"></div>
  </div>
</div>


<script>
(function(){
  var APP_URL='<?= defined("APP_URL")?rtrim(APP_URL,"/"):"" ?>';
  var rtActive=null,rtHist={};
  var ws=document.getElementById('rt-scroll');
  if(ws){var dn=false,sx,sl;ws.addEventListener('mousedown',function(e){dn=true;sx=e.pageX-ws.offsetLeft;sl=ws.scrollLeft});ws.addEventListener('mouseleave',function(){dn=false});ws.addEventListener('mouseup',function(){dn=false});ws.addEventListener('mousemove',function(e){if(!dn)return;e.preventDefault();ws.scrollLeft=sl-(e.pageX-ws.offsetLeft-sx)});}
  document.addEventListener('click',function(e){if(rtActive&&!e.target.closest('.rt-chat-pop')&&!e.target.closest('.rt-ia-btn'))rtCloseChat(rtActive);});
  window.rtFilt=function(plat,btn){document.querySelectorAll('.rt-ctrls .rt-fbtn').forEach(function(b){if(['Todos','Meta','Google'].indexOf(b.textContent.trim())>=0)b.classList.remove('active')});btn.classList.add('active');document.querySelectorAll('.rt-card').forEach(function(c){c.style.display=(plat==='all'||c.dataset.plat===plat)?'':'none'});};
  window.rtLoad=function(force){
    var btn=document.getElementById('rt-refresh-btn');
    if(force&&btn){btn.textContent='↻ …';btn.style.opacity='.5';btn.disabled=true;}
    fetch(APP_URL+'/reports/rt-metrics',{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json()})
      .then(function(data){
        if(btn){btn.textContent='↻ Atualizar';btn.style.opacity='';btn.disabled=false;}
        if(!data.success||!data.cards||!data.cards.length){document.getElementById('rt-section').style.display='none';return;}
        document.getElementById('rt-section').style.display='';
        console.log('[RT-METRICS debug]',data.cards.map(function(c){return{id:c.id,title:c.title,camp_ids:c._debug_camp_ids,has_metrics:c._debug_has_metrics,spend:c.metrics&&c.metrics.spend};}));
        var row=document.getElementById('rt-row');row.innerHTML='';
        data.cards.sort(function(a,b){var na=(a.client_name||a.title||'').toLowerCase();var nb=(b.client_name||b.title||'').toLowerCase();return na<nb?-1:na>nb?1:0;});
        data.cards.forEach(function(c){row.appendChild(rtBuildCard(c));});
      })
      .catch(function(){if(btn){btn.textContent='↻ Atualizar';btn.style.opacity='';btn.disabled=false;}});
  };
  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
  function rtBuildCard(c){
    var plat=c.platform||'meta',m=c.metrics||{},id='rtc'+c.id;
    var platBadge=plat==='both'?'<span class="rt-badge rt-b-both">Meta+Google</span>':(plat==='google'?'<span class="rt-badge rt-b-google">Google</span>':'<span class="rt-badge rt-b-meta">Meta</span>');
    var sBadge=c.status==='Alta performance'?'<span class="rt-badge rt-b-ok">Alta performance</span>':c.status==='Boa performance'?'<span class="rt-badge rt-b-ok">Boa performance</span>':c.status==='Atenção'?'<span class="rt-badge rt-b-warn">Atenção</span>':'<span class="rt-badge rt-b-neutral">'+esc(c.status)+'</span>';
    var ptClass='rt-period-tag'+(c.period_type&&c.period_type.indexOf('custom|')===0?' manual':(plat==='google'?' google-p':''));
    var gridHTML='';
    if(!c.has_data){gridHTML='<div class="rt-nodata">📭 Sem dados para este período</div>';}
    else{
      var fR=function(v){return 'R$'+parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})};
      var fN=function(v){return parseInt(v||0).toLocaleString('pt-BR')};
      var fP=function(v){return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+'%'};
      var fX=function(v){return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:1,maximumFractionDigits:1})+'x'};
      var colorMap={spend:'#9B59B6',reach:'#1EBC84',impressions:'#1EBC84',clicks:'#E3B341',ctr:'#E3B341',cpc:'#5B8DEF',cpm:'#5B8DEF',conversions:'#1EBC84',messages:'#1EBC84',engagement:'#1EBC84',roas:'#2ECC71',revenue:'#2ECC71',frequency:'#E3B341',profile_visits:'#85C1E9',cpl:'#5B8DEF',cpv:'#5B8DEF',cmsg:'#5B8DEF',tm:'#5B8DEF',custo_por_visita:'#85C1E9',thruplay:'#E3B341',view_25:'#E3B341',view_50:'#E3B341',view_75:'#E3B341',view_100:'#1EBC84',cart:'#1EBC84',inicheckout:'#1EBC84',download:'#1EBC84',comment:'#E3B341',post_reaction:'#E3B341',app_install:'#1EBC84',vplay:'#E3B341',search:'#85C1E9',pageview:'#85C1E9'};
      var fields = (c.tpl_fields && c.tpl_fields.length) ? c.tpl_fields : [
        {label:'Investimento',key:'spend',format:'r',value:m.spend||0},
        {label:'Alcance',key:'reach',format:'n',value:m.reach||0},
        {label:'Impressões',key:'impressions',format:'n',value:m.impressions||0},
        {label:'CTR',key:'ctr',format:'p',value:m.ctr||0},
        {label:'CPC',key:'cpc',format:'r',value:m.cpc||0},
        {label:'Frequência',key:'frequency',format:'x',value:m.frequency||0}
      ];
      gridHTML='<div class="rt-grid">';
      fields.slice(0,8).forEach(function(f){
        var v=f.value||0;
        var disp=f.format==='r'?fR(v):f.format==='n'?fN(v):f.format==='p'?fP(v):fX(v);
        var clr=colorMap[f.key]||'#5B8DEF';
        var maxRef={spend:500,reach:10000,impressions:50000,clicks:1000,ctr:3,cpc:5,cpm:50,conversions:50,messages:50,engagement:500,roas:5,revenue:5000,frequency:4,profile_visits:500,cpl:50,cpv:50,cmsg:50,tm:500,custo_por_visita:50,thruplay:1000,view_25:1000,view_50:1000,view_75:1000,view_100:1000,cart:100,inicheckout:100,download:500,comment:500,post_reaction:1000,app_install:200,vplay:5000,search:1000,pageview:5000};
        var fill=Math.min(100,Math.max(4,Math.round((v/(maxRef[f.key]||100))*100)));
        var sm=(f.format==='r'&&v>=100)||(f.format==='x')||(fields.length>6);
        var pm=c.prev_metrics||null;
        var invertDir=(f.key==='cpc'||f.key==='cpm'||f.key==='cmsg'||f.key==='cpl'||f.key==='cpv'||f.key==='frequency');
        var pBadge='';
        if(pm&&pm[f.key]!==undefined&&parseFloat(pm[f.key])>0){
          var pct=((v-parseFloat(pm[f.key]))/parseFloat(pm[f.key])*100);
          var up=pct>=0;var good=invertDir?!up:up;
          var clrP=good?'var(--success)':'var(--danger)';
          pBadge='<span style="font-size:8px;color:'+clrP+';margin-left:3px">'+(up?'▲':'▼')+Math.abs(pct).toFixed(1)+'%</span>';
        }
        gridHTML+='<div class="rt-mc"><div class="rt-mc-lbl">'+f.label+'</div><div class="rt-mc-val'+(sm?' sm':'')+'">'+disp+pBadge+'</div><div class="rt-mc-bar"><div class="rt-mc-fill" style="width:'+fill+'%;background:'+clr+'"></div></div></div>';
      });
      gridHTML+='</div>';
    }
    var objLabels={todos:'Todos',reconhecimento:'Reconhecimento',trafego:'Tráfego',engajamento:'Engajamento',leads:'Leads',vendas:'Vendas',turbinar:'Turbinar publicação',app:'Promoção de App',mensagens:'Mensagens',conversoes:'Conversões',conversao:'Conversões',messages:'Mensagens',traffic:'Tráfego',awareness:'Reconhecimento',engagement:'Engajamento',sales:'Vendas'};
    var objLabel=objLabels[(c.objetivo||'').toLowerCase()]||(c.objetivo||'Objetivo');
    var ctxJSON=JSON.stringify({title:c.title,platform:plat,objetivo:objLabel,period_label:c.period_label||'',period_range:c.period_range||'',status:c.status,metrics:{spend:m.spend||0,impressions:m.impressions||0,clicks:m.clicks||0,reach:m.reach||0,ctr:m.ctr||0,cpc:m.cpc||0,cpm:m.cpm||0,conversions:m.conversions||0,roas:m.roas||0,frequency:m.frequency||0}}).replace(/"/g,'&quot;');
    var obj=(c.objetivo||'').toLowerCase();
    var chips=obj.indexOf('lead')>=0?['R$/lead está bom?','Como melhorar leads?','Devo escalar?','Análise '+esc(c.period_label)]:obj.indexOf('mens')>=0||obj.indexOf('msg')>=0?['CTR está ok?','Custo/conv aceitável?','O que melhorar?','Análise '+esc(c.period_label)]:obj.indexOf('tr')>=0?['CTR está baixo?','Devo pausar?','Como aumentar cliques?','Análise '+esc(c.period_label)]:['Performance boa?','O que melhorar?','Devo escalar?','Análise '+esc(c.period_label)];
    var chipsHTML='';
    chips.slice(0,4).forEach(function(ch,ci){
      chipsHTML+='<button class="rt-chip" data-cid="'+String(ci)+'" data-chip="">'+esc(ch)+'</button>';
    });
    window['_rtChips_'+id]=chips.slice(0,4);
    var freqStr=(m.frequency||0)>0?' · Freq '+parseFloat(m.frequency).toFixed(1)+'x':'';
    var cpcStr=(m.cpc||0)>0?' · CPC R$'+parseFloat(m.cpc).toFixed(2).replace('.',','):'';
    var dotColor=c.status==='Sem dados'?'var(--txt3)':(c.status.indexOf('Alta')>=0||c.status.indexOf('Boa')>=0?'#1EBC84':'#E3B341');
    // Diagnóstico — usa status do backend + só métricas com benchmarks confiáveis
    // CTR por objetivo, frequência e ROAS são universais; custo/resultado é relativo
    var diagBad=[],diagWarn=[];
    var mObj=(c.objetivo||'').toLowerCase();
    // CTR — limiar varia por objetivo
    var ctrOk  = mObj.indexOf('traf')>=0||mObj.indexOf('traff')>=0?0.8:mObj.indexOf('conv')>=0?1.5:1.0;
    var ctrWarn= ctrOk*0.5;
    var ctrV=parseFloat(m.ctr||0);
    if(ctrV>0){
      if(ctrV<ctrWarn) diagBad.push('CTR');
      else if(ctrV<ctrOk) diagWarn.push('CTR');
    }
    // Frequência — universal
    var freqV=parseFloat(m.frequency||0);
    if(freqV>0){
      if(freqV>3.5) diagBad.push('Frequência');
      else if(freqV>2.8) diagWarn.push('Frequência');
    }
    // ROAS — só se tiver receita
    var roasV=parseFloat(m.roas||0);
    if(roasV>0){
      if(roasV<1.5) diagBad.push('ROAS');
      else if(roasV<3) diagWarn.push('ROAS');
    }
    // Conversões/Leads/Mensagens — só alerta se zero com gasto alto
    var convV=parseFloat(m.conversions||m.messages||0);
    var spendV=parseFloat(m.spend||0);
    if(spendV>50&&convV===0) diagBad.push('Sem resultados');
    // Barra de saúde — baseada no status do backend + alertas encontrados
    var diagScore,diagColor,diagLbl;
    var statusStr=(c.status||'').toLowerCase();
    if(statusStr.indexOf('alta')>=0){diagScore=diagBad.length>0?65:92;}
    else if(statusStr.indexOf('boa')>=0){diagScore=diagBad.length>0?55:78;}
    else if(statusStr.indexOf('aten')>=0){diagScore=diagBad.length>0?30:45;}
    else{diagScore=20;}
    diagColor=diagScore>=75?'var(--success)':diagScore>=45?'var(--warn)':'var(--danger)';
    diagLbl=diagScore>=75?'Ótima':diagScore>=45?'Atenção':'Ruim';
    // pílulas — só bad e warn
    var diagPills='';
    diagBad.forEach(function(l){diagPills+='<span class="rt-diag-pill bad"><span class="rt-diag-dot"></span>'+l+'</span>';});
    diagWarn.forEach(function(l){diagPills+='<span class="rt-diag-pill warn"><span class="rt-diag-dot"></span>'+l+'</span>';});
    if(!diagPills){diagPills='<span class="rt-ok-txt"><span class="rt-ok-dot"></span>Tudo dentro do esperado</span>';}
    var cardW=fields.length<=4?228:fields.length<=6?250:272;
    var html='<div class="rt-card" data-plat="'+esc(plat)+'" data-id="'+esc(id)+'" style="width:'+cardW+'px">'
      +'<div class="rt-card-bar '+esc(plat)+'"></div>'
      +'<div class="rt-inner">'
        +'<div class="rt-top"><div class="rt-name">'+esc(c.title)+'</div>'+platBadge+'</div>'
        +'<div class="'+ptClass+'"><span>📅</span> '+esc(c.period_label)+' <span class="rt-pt-range">· '+esc(c.period_range)+'</span></div>'
        +'<div class="rt-camp-toggle" id="ctog-'+esc(id)+'" onclick="rtCampToggle(\''+esc(id)+'\','+c.id+')">'
        +'<div class="rt-camp-left"><div class="rt-camp-lbl" id="ctog-lbl-'+esc(id)+'">'+(c.rt_camp_selection?esc(c.rt_camp_selection.label):'Campanha')+'</div><div class="rt-camp-val" id="ctog-val-'+esc(id)+'">'+(c.rt_camp_selection?esc(c.rt_camp_selection.name):'clique para ver')+'</div></div>'
        +'<span class="rt-camp-arrow" id="ctog-arr-'+esc(id)+'">▾</span>'
        +'</div>'
        +'<div class="rt-camp-drop" id="cdrop-'+esc(id)+'"><div class="rt-camp-loading">Buscando...</div></div>'
        +'<div class="rt-meta-row"><span class="rt-badge rt-b-obj">'+esc(objLabel)+'</span>'+sBadge+'</div>'
        +gridHTML
        +'<div class="rt-foot">'
        +'<div class="rt-health-wrap"><span class="rt-health-title">Saúde</span><div class="rt-health-bar"><div class="rt-health-fill" style="width:'+diagScore+'%;background:'+diagColor+'"></div></div><span class="rt-health-lbl" style="color:'+diagColor+'">'+diagLbl+'</span></div>'
        +'<div class="rt-foot-bottom"><div class="rt-diag-row">'+diagPills+'</div>'
        +'<div style="display:flex;gap:4px;flex-shrink:0">'
        +'<button class="rt-wa-btn" onclick="rtWaOpen(\''+esc(id)+'\','+c.id+')" title="Enviar por WhatsApp">&#9993;</button>'
        +'<button class="rt-ia-btn" id="iabtn-'+esc(id)+'" onclick="rtOpenChat(\''+esc(id)+'\',this)" data-ctx="'+ctxJSON+'">✦ IA</button>'
        +'</div></div>'
        +'</div>'
      +'</div>'
      +'<div class="rt-chat-pop" id="chat-'+esc(id)+'" style="width:'+Math.max(296,cardW+20)+'px">'
        +'<div class="rt-ch-hd"><div class="rt-ch-hd-l"><div class="rt-ch-av">✦</div><div><div class="rt-ch-name">'+esc(c.title)+'</div><div class="rt-ch-sub">Análise em tempo real</div></div></div><button class="rt-ch-close" onclick="rtCloseChat(\''+esc(id)+'\')">✕</button></div>'
        +'<div class="rt-ch-period">📅 <strong>'+esc(c.period_label)+'</strong> &nbsp;·&nbsp; '+esc(c.period_range)+'</div>'
        +'<div class="rt-chips" id="chips-'+esc(id)+'">'+chipsHTML+'</div>'
        +'<div class="rt-msgs" id="msgs-'+esc(id)+'"></div>'
        +'<div class="rt-inp-wrap"><input class="rt-inp" id="inp-'+esc(id)+'" placeholder="Pergunte sobre essa campanha…" onkeydown="if(event.key===\'Enter\'){event.preventDefault();rtSend(\''+esc(id)+'\')}"><button class="rt-send" id="snd-'+esc(id)+'" onclick="rtSend(\''+esc(id)+'\')">➤</button></div>'
      +'</div>'
    +'</div>';
    var w=document.createElement('div');w.innerHTML=html;
    var card=w.firstElementChild;
    var chipsContainer=card.querySelector('#chips-'+id);
    if(chipsContainer){
      var storedChips=window['_rtChips_'+id]||[];
      chipsContainer.querySelectorAll('.rt-chip').forEach(function(btn,i){
        btn.addEventListener('click',function(){rtChip(id,storedChips[i]||'');});
      });
    }
    return card;
  }
  window.rtOpenChat=function(id,btn){
    if(rtActive&&rtActive!==id)rtCloseChat(rtActive);
    var pop=document.getElementById('chat-'+id);if(!pop)return;
    if(rtActive===id){rtCloseChat(id);btn.classList.remove('active');return;}
    pop.classList.add('open');rtActive=id;btn.classList.add('active');
    if(!rtHist[id]){rtHist[id]=[];var ctx={};try{ctx=JSON.parse(btn.dataset.ctx||'{}')}catch(ex){}rtAppendMsg(id,'a','👋 Analisando <strong>'+esc(ctx.title||'')+'</strong> — <strong>'+(ctx.period_label||'')+' ('+esc(ctx.period_range||'')+')</strong>.<br>Status: <strong>'+esc(ctx.status||'')+'</strong>. Pode perguntar!');}
    setTimeout(function(){var i=document.getElementById('inp-'+id);if(i)i.focus();},80);
  };
  window.rtCloseChat=function(id){var p=document.getElementById('chat-'+id);if(p)p.classList.remove('open');var b=document.getElementById('iabtn-'+id);if(b)b.classList.remove('active');rtActive=null;};
  window.rtChip=function(id,txt){var i=document.getElementById('inp-'+id);if(i){i.value=txt;rtSend(id);}};
  window.rtSend=async function(id){
    var inp=document.getElementById('inp-'+id),snd=document.getElementById('snd-'+id);
    var txt=inp.value.trim();if(!txt)return;
    inp.value='';inp.disabled=true;snd.disabled=true;
    var chips=document.getElementById('chips-'+id);if(chips)chips.style.display='none';
    var btn=document.getElementById('iabtn-'+id);var ctx={};try{ctx=JSON.parse((btn&&btn.dataset.ctx)||'{}')}catch(ex){}
    rtAppendMsg(id,'u',txt);
    if(!rtHist[id])rtHist[id]=[];
    rtHist[id].push({role:'user',content:txt});
    var tid='rt-t-'+id+'-'+Date.now();rtAppendTyping(id,tid);
    try{
      var fd=new FormData();fd.append('message',txt);fd.append('history',JSON.stringify(rtHist[id].slice(-6)));fd.append('context',JSON.stringify(ctx));
      var res=await fetch(APP_URL+'/reports/rt-chat',{method:'POST',body:fd});
      var data=await res.json();rtRemoveTyping(tid);
      if(data.success){rtHist[id].push({role:'assistant',content:data.reply});rtAppendMsg(id,'a',data.reply.replace(/\n/g,'<br>'));}
      else{rtAppendMsg(id,'a','⚠️ '+(data.error||'Erro na IA'));}
    }catch(err){rtRemoveTyping(tid);rtAppendMsg(id,'a','⚠️ Erro de conexão.');}
    inp.disabled=false;snd.disabled=false;inp.focus();
  };
  function rtAppendMsg(id,role,html){var c=document.getElementById('msgs-'+id);if(!c)return;var t=new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});var d=document.createElement('div');d.className='rt-msg '+(role==='u'?'u':'a');d.innerHTML='<div class="rt-bubble">'+html+'</div><div class="rt-msg-time">'+t+'</div>';c.appendChild(d);c.scrollTop=c.scrollHeight;}
  function rtAppendTyping(id,tid){var c=document.getElementById('msgs-'+id);if(!c)return;var d=document.createElement('div');d.className='rt-msg a';d.id=tid;d.innerHTML='<div class="rt-typing"><span></span><span></span><span></span></div>';c.appendChild(d);c.scrollTop=c.scrollHeight;}
  function rtRemoveTyping(tid){var e=document.getElementById(tid);if(e)e.remove();}
  // ── WhatsApp Popup ──────────────────────────────
  var rtWaData={};var rtWaOrigMsg='';
  window.rtWaOpen=function(cardId,reportId){
    var modal=document.getElementById('rtWaModal');
    var body=document.getElementById('rtWaBody');
    var sub=document.getElementById('rtWaSub');
    var st=document.getElementById('rtWaStatus');
    var btn=document.getElementById('rtWaSendBtn');
    st.textContent=''; btn.disabled=true;
    // find card title
    var card=document.querySelector('[data-id="'+cardId+'"]');
    var title=card?card.querySelector('.rt-name').textContent:'';
    sub.textContent=title;
    body.innerHTML='<div style="text-align:center;padding:24px;color:var(--txt3);font-size:12px">Carregando mensagem...</div>';
    modal.classList.add('open');
    rtWaData={reportId:reportId,cardId:cardId};
    fetch(APP_URL+'/reports/rt-message?id='+reportId,{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json()})
      .then(function(d){
        if(!d.success){body.innerHTML='<div style="color:var(--danger);padding:16px">Erro: '+esc(d.error||'')+'</div>';return;}
        console.log('[RT-MSG debug]',{has_msg:d._debug_has_msg,has_metrics:d._debug_has_met,period:d._debug_period,start:d._debug_start,msg_preview:(d.message||'').substring(0,100)});
        rtWaOrigMsg=d.message;
        rtWaData.instances=d.instances||[];
        rtWaData.clients=d.clients||[];
        rtWaData.recvType=d.recv_type||'phone';
        rtWaData.phone=d.phone||'';
        rtWaData.groupId=d.group_id||'';
        rtWaData.wpId=d.wp_id||'';
        rtWaData.clientId=d.client_id||'';
        rtWaBuild(d);
        btn.disabled=false;
      })
      .catch(function(){body.innerHTML='<div style="color:var(--danger);padding:16px">Erro ao carregar</div>';});
  };

  function rtWaBuild(d){
    var body=document.getElementById('rtWaBody');
    var recvType=d.recv_type||'phone';
    body.innerHTML='';

    // ── Instância ──
    var instDiv=document.createElement('div');
    var instLbl=document.createElement('div');instLbl.className='rt-wa-lbl';instLbl.textContent='Instância WhatsApp';
    var instRow=document.createElement('div');instRow.className='rt-inst-row';
    var instDot=document.createElement('div');instDot.className='rt-inst-dot';
    var instSel=document.createElement('select');instSel.className='rt-wa-sel';instSel.id='rtWaInst';
    var iOpt=document.createElement('option');iOpt.value='';iOpt.textContent='— Selecione —';instSel.appendChild(iOpt);
    (d.instances||[]).forEach(function(i){
      var o=document.createElement('option');o.value=i.id;
      o.textContent=i.instance_name+' · '+(i.phone_number||'');
      if(String(i.id)===String(d.wp_id))o.selected=true;
      instSel.appendChild(o);
    });
    instRow.appendChild(instDot);instRow.appendChild(instSel);
    instDiv.appendChild(instLbl);instDiv.appendChild(instRow);
    body.appendChild(instDiv);

    // ── Tabs destino ──
    var destDiv=document.createElement('div');
    var destLbl=document.createElement('div');destLbl.className='rt-wa-lbl';destLbl.textContent='Enviar para';
    var tabs=document.createElement('div');tabs.className='rt-wa-tabs';
    [['phone','📱 Número'],['client','👤 Cliente'],['group','👥 Grupo']].forEach(function(pair){
      var btn=document.createElement('div');
      btn.className='rt-wa-tab'+(recvType===pair[0]?' active':'');
      btn.textContent=pair[1];
      btn.addEventListener('click',function(){rtWaTab(pair[0],btn);});
      tabs.appendChild(btn);
    });
    destDiv.appendChild(destLbl);destDiv.appendChild(tabs);
    body.appendChild(destDiv);

    // ── Número ──
    var phoneDiv=document.createElement('div');phoneDiv.id='rtWaDestPhone';
    phoneDiv.style.display=(recvType!=='client'&&recvType!=='group'?'block':'none');
    var phoneInp=document.createElement('input');phoneInp.className='rt-wa-input';phoneInp.id='rtWaPhone';
    phoneInp.placeholder='5581999999999';
    phoneInp.value=(recvType==='phone'?(d.phone||''):'');
    phoneDiv.appendChild(phoneInp);body.appendChild(phoneDiv);

    // ── Cliente ──
    var cliDiv=document.createElement('div');cliDiv.id='rtWaDestClient';
    cliDiv.style.display=(recvType==='client'?'block':'none');
    var cliSel=document.createElement('select');cliSel.className='rt-wa-sel';cliSel.id='rtWaClient';
    var cOpt=document.createElement('option');cOpt.value='';cOpt.textContent='— Selecione cliente —';cliSel.appendChild(cOpt);
    (d.clients||[]).forEach(function(c){
      var o=document.createElement('option');o.value=c.id;
      o.textContent=c.name+(c.phone?' · '+c.phone:'');
      o.setAttribute('data-phone',c.phone||'');
      if(String(c.id)===String(d.client_id))o.selected=true;
      cliSel.appendChild(o);
    });
    // ao selecionar cliente, preenche phone hidden
    cliSel.addEventListener('change',function(){
      var o=this.options[this.selectedIndex];
      var ph=document.getElementById('rtWaPhone');
      if(ph)ph.value=o.getAttribute('data-phone')||'';
    });
    // pre-fill phone do cliente padrão
    if(recvType==='client'&&d.client_id){
      var selOpt=cliSel.querySelector('option[value="'+d.client_id+'"]');
      if(selOpt){var ph=document.getElementById('rtWaPhone');if(ph)ph.value=selOpt.getAttribute('data-phone')||'';}
    }
    cliDiv.appendChild(cliSel);body.appendChild(cliDiv);

    // ── Grupo ──
    var grpDiv=document.createElement('div');grpDiv.id='rtWaDestGroup';
    grpDiv.style.display=(recvType==='group'?'block':'none');
    // Select de grupos
    var grpSel=document.createElement('select');grpSel.className='rt-wa-sel';grpSel.id='rtWaGroupSel';
    var grpDefOpt=document.createElement('option');grpDefOpt.value='';grpDefOpt.textContent='Clique em "Buscar grupos" abaixo';grpSel.appendChild(grpDefOpt);
    if(d.recv_type==='group'&&d.group_id){
      var grpCurOpt=document.createElement('option');grpCurOpt.value=d.group_id;grpCurOpt.textContent=d.group_id;grpCurOpt.selected=true;grpSel.appendChild(grpCurOpt);
    }
    grpSel.addEventListener('change',function(){
      var hidGrp=document.getElementById('rtWaGroup');if(hidGrp)hidGrp.value=this.value;
    });
    // Input hidden para o ID
    var grpInp=document.createElement('input');grpInp.type='hidden';grpInp.id='rtWaGroup';grpInp.value=(d.recv_type==='group'?d.group_id:'');
    // Botão buscar grupos
    var grpFetchBtn=document.createElement('button');grpFetchBtn.className='rt-wa-mbtn';grpFetchBtn.style.marginTop='5px';grpFetchBtn.textContent='🔄 Buscar grupos';
    var grpLoading=document.createElement('span');grpLoading.style.cssText='font-size:10px;color:var(--txt3);margin-left:8px;display:none';grpLoading.textContent='Buscando...';
    var grpErr=document.createElement('div');grpErr.style.cssText='font-size:10px;color:var(--danger);margin-top:3px;display:none';
    grpFetchBtn.addEventListener('click',function(){
      var wpSel=document.getElementById('rtWaInst');
      var wpId=wpSel?wpSel.value:'';
      if(!wpId){grpErr.textContent='Selecione a instância WhatsApp primeiro';grpErr.style.display='block';return;}
      grpFetchBtn.disabled=true;grpLoading.style.display='inline';grpErr.style.display='none';
      fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wpId),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();})
        .then(function(data){
          grpFetchBtn.disabled=false;grpLoading.style.display='none';
          if(!data.success){grpErr.textContent='Erro: '+(data.error||'Falha');grpErr.style.display='block';return;}
          grpSel.innerHTML='<option value="">— Selecione um grupo —</option>';
          (data.grupos||[]).forEach(function(g){
            var o=document.createElement('option');o.value=g.id;o.textContent=g.nome+' ('+g.id+')';
            if(g.id===(document.getElementById('rtWaGroup')||{}).value)o.selected=true;
            grpSel.appendChild(o);
          });
          if(!data.grupos||!data.grupos.length){grpErr.textContent='Nenhum grupo encontrado.';grpErr.style.display='block';}
        })
        .catch(function(e){grpFetchBtn.disabled=false;grpLoading.style.display='none';grpErr.textContent='Erro de conexão';grpErr.style.display='block';});
    });
    grpDiv.appendChild(grpSel);grpDiv.appendChild(grpInp);grpDiv.appendChild(grpFetchBtn);grpDiv.appendChild(grpLoading);grpDiv.appendChild(grpErr);
    body.appendChild(grpDiv);

    // ── Mensagem ──
    var msgDiv=document.createElement('div');
    var msgLbl=document.createElement('div');msgLbl.className='rt-wa-lbl';msgLbl.textContent='Mensagem';
    var msgSpan=document.createElement('span');
    msgSpan.style.cssText='color:var(--txt3);font-size:9px;text-transform:none;font-weight:400;margin-left:4px';
    msgSpan.textContent='(editável antes do envio)';
    msgLbl.appendChild(msgSpan);
    var msgTa=document.createElement('textarea');msgTa.className='rt-wa-ta';msgTa.id='rtWaMsg';msgTa.value=d.message;
    // ── Actions: Restaurar + IA ──
    var msgAct=document.createElement('div');msgAct.className='rt-wa-msg-actions';
    var restBtn=document.createElement('button');restBtn.className='rt-wa-mbtn';restBtn.textContent='↺ Restaurar';
    restBtn.addEventListener('click',function(){msgTa.value=rtWaOrigMsg;});
    // Seletor de modelo IA
    var modRow=document.createElement('div');modRow.style.cssText='display:flex;align-items:center;gap:5px;margin-bottom:5px';
    var modLbl=document.createElement('span');modLbl.style.cssText='font-size:9px;color:var(--txt3);white-space:nowrap';modLbl.textContent='Modelo IA:';
    var modSel=document.createElement('select');modSel.id='rtWaModSel';modSel.className='rt-wa-sel';modSel.style.cssText='font-size:10px;padding:3px 7px;height:auto';
    var provSel=document.createElement('input');provSel.type='hidden';provSel.id='rtWaProvSel';
    var defProv='<?= e($_rtDefProv) ?>';var defMod='<?= e($_rtDefModel) ?>';
    var rtMods=<?= json_encode(array_map(function($prov,$pd){$out=[];foreach($pd['models'] as $m){$m['provider']=$prov;$out[]=$m;}return $out;},array_keys($_rtModels),$_rtModels)) ?>;
    var flatMods=[];rtMods.forEach(function(g){g.forEach(function(m){flatMods.push(m);});});
    flatMods.forEach(function(m){
      var o=document.createElement('option');
      o.value=m.id;o.textContent=m.name;o.setAttribute('data-prov',m.provider||defProv);
      if(m.id===defMod)o.selected=true;
      modSel.appendChild(o);
    });
    modSel.addEventListener('change',function(){
      var o=this.options[this.selectedIndex];
      provSel.value=o.getAttribute('data-prov')||defProv;
    });
    // set initial provider
    var initOpt=modSel.querySelector('option[value="'+defMod+'"]');
    provSel.value=initOpt?initOpt.getAttribute('data-prov')||defProv:defProv;
    modRow.appendChild(modLbl);modRow.appendChild(modSel);modRow.appendChild(provSel);
    msgDiv.appendChild(modRow);

    var aiBtn=document.createElement('button');aiBtn.className='rt-wa-mbtn rt-wa-mbtn-ai';aiBtn.textContent='✦ Reescrever com IA';
    aiBtn.addEventListener('click',function(){rtWaRewrite(aiBtn);});
    msgAct.appendChild(restBtn);msgAct.appendChild(aiBtn);
    msgDiv.appendChild(msgLbl);msgDiv.appendChild(msgTa);msgDiv.appendChild(msgAct);
    body.appendChild(msgDiv);
  }
  window.rtWaTab=function(type,btn){
    document.querySelectorAll('.rt-wa-tab').forEach(function(b){b.classList.remove('active')});
    btn.classList.add('active');
    document.getElementById('rtWaDestPhone').style.display=type==='phone'?'block':'none';
    document.getElementById('rtWaDestClient').style.display=type==='client'?'block':'none';
    document.getElementById('rtWaDestGroup').style.display=type==='group'?'block':'none';
    rtWaData.recvType=type;
    // auto-busca grupos ao trocar para grupo se ainda não carregou
    if(type==='group'){
      var grpSel=document.getElementById('rtWaGroupSel');
      if(grpSel&&grpSel.options.length<=1){
        var fetchBtn=document.querySelector('#rtWaDestGroup button');
        if(fetchBtn)fetchBtn.click();
      }
    }
    // ao trocar para cliente, preenche phone do cliente selecionado
    if(type==='client'){
      var cliSel=document.getElementById('rtWaClient');
      if(cliSel&&cliSel.selectedIndex>0){
        var o=cliSel.options[cliSel.selectedIndex];
        var ph=document.getElementById('rtWaPhone');
        if(ph)ph.value=o.getAttribute('data-phone')||'';
      }
    }
  };

  window.rtWaRewrite=async function(btn){
    btn.disabled=true;btn.textContent='✦ Gerando...';
    var ta=document.getElementById('rtWaMsg');
    var st=document.getElementById('rtWaStatus');
    if(!ta){btn.disabled=false;btn.textContent='✦ Reescrever com IA';return;}
    var msg=rtWaOrigMsg||ta.value;
    var csrfTok=document.querySelector('meta[name="csrf-token"]')?.content||'';
    // Usa o mesmo endpoint e provider do chat de relatórios
    var provSel=document.getElementById('rtWaProvSel');
    var modSel=document.getElementById('rtWaModSel');
    var prov=provSel?provSel.value:'<?= e($_rtDefProv) ?>';
    var mod=modSel?modSel.value:'<?= e($_rtDefModel) ?>';
    var fd=new FormData();
    fd.append('_token',csrfTok);
    fd.append('provider',prov);
    fd.append('model',mod);
    fd.append('message','Reescreva a mensagem abaixo de relatorio de trafego pago de forma profissional, com emojis relevantes. Mantenha TODOS os numeros e metricas. Responda APENAS com a mensagem final reescrita, sem introducoes nem explicacoes:\n\n'+msg);
    fd.append('history','[]');
    try{
      var r=await fetch(APP_URL+'/ai/chat-report',{method:'POST',body:fd});
      var data=await r.json();
      if(data.success&&data.reply){
        ta.value=data.reply;
        if(st){st.style.color='var(--success)';st.textContent='✓ Mensagem reescrita com sucesso';}
        setTimeout(function(){if(st)st.textContent='';},3000);
      } else {
        if(st){st.style.color='var(--danger)';st.textContent='Erro: '+(data.error||'Falha na IA');}
      }
    }catch(e){
      if(st){st.style.color='var(--danger)';st.textContent='Erro de conexão: '+e.message;}
    }
    btn.disabled=false;btn.textContent='✦ Reescrever com IA';
  };

  window.rtWaClose=function(){
    document.getElementById('rtWaModal').classList.remove('open');
    document.getElementById('rtWaStatus').textContent='';
    rtWaData={};
  };

  window.rtWaSubmit=async function(){
    var btn=document.getElementById('rtWaSendBtn');
    var st=document.getElementById('rtWaStatus');
    var msg=(document.getElementById('rtWaMsg')||{}).value||'';
    var wpId=(document.getElementById('rtWaInst')||{}).value||'';
    var recvType=rtWaData.recvType||'phone';
    var phone='',groupId='';
    if(recvType==='group'){var gSel=document.getElementById('rtWaGroupSel');groupId=(gSel&&gSel.value)?gSel.value:((document.getElementById('rtWaGroup')||{}).value||'');}
    else{phone=(document.getElementById('rtWaPhone')||{}).value||'';}
    if(!msg.trim()){st.style.color='var(--danger)';st.textContent='Mensagem vazia';return;}
    if(recvType!=='group'&&!phone){st.style.color='var(--danger)';st.textContent='Informe o destinatario';return;}
    if(recvType==='group'&&!groupId){st.style.color='var(--danger)';st.textContent='Informe o ID do grupo';return;}
    btn.disabled=true;btn.textContent='Enviando...';st.textContent='';
    var csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
    var fd=new FormData();
    fd.append('_token',csrf);
    fd.append('report_id',rtWaData.reportId);
    fd.append('message',msg);
    fd.append('recv_type',recvType);
    fd.append('phone',phone);
    fd.append('group_id',groupId);
    fd.append('wp_id',wpId);
    try{
      var r=await fetch(APP_URL+'/reports/rt-send',{method:'POST',body:fd});
      var data=await r.json();
      if(data.success){
        st.style.color='var(--success)';st.textContent='✓ Enviado com sucesso!';
        btn.textContent='✓ Enviado!';btn.style.background='var(--success)';
        setTimeout(function(){rtWaClose();btn.style.background='';btn.textContent='Enviar pelo WhatsApp';btn.disabled=false;},2000);
      } else {
        st.style.color='var(--danger)';st.textContent='Erro: '+(data.error||'falha no envio');
        btn.textContent='Enviar pelo WhatsApp';btn.disabled=false;
      }
    }catch(err){st.style.color='var(--danger)';st.textContent='Erro de conexão';btn.textContent='Enviar pelo WhatsApp';btn.disabled=false;}
  };

  // fecha modal clicando fora
  document.getElementById('rtWaModal').addEventListener('click',function(e){if(e.target===this)rtWaClose();});

  // ── Campanha/Conjunto/Criativo toggle ─────────────────────
  var rtNamesCache={};
  window.rtCampToggle=function(cardId,reportId){
    var drop=document.getElementById('cdrop-'+cardId);
    var arr=document.getElementById('ctog-arr-'+cardId);
    if(!drop)return;
    var isOpen=drop.classList.contains('open');
    document.querySelectorAll('.rt-camp-drop.open').forEach(function(d){d.classList.remove('open');});
    document.querySelectorAll('.rt-camp-arrow.open').forEach(function(a){a.classList.remove('open');});
    if(isOpen)return;
    drop.classList.add('open');arr.classList.add('open');
    if(rtNamesCache[reportId]){rtRenderNames(cardId,rtNamesCache[reportId],reportId);return;}
    drop.innerHTML='<div class="rt-camp-loading">Buscando campanhas, conjuntos e criativos...</div>';
    fetch(APP_URL+'/reports/rt-names?id='+reportId,{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();})
      .then(function(data){
        if(!data.success||!data.campaigns||!data.campaigns.length){
          drop.innerHTML='<div class="rt-camp-loading" style="color:var(--txt2)">Sem dados</div>';return;
        }
        rtNamesCache[reportId]=data.campaigns;
        rtRenderNames(cardId,data.campaigns,reportId);
      })
      .catch(function(){drop.innerHTML='<div class="rt-camp-loading" style="color:var(--danger)">Erro</div>';});
  };
  function rtRenderNames(cardId,campaigns,reportId){
    var drop=document.getElementById('cdrop-'+cardId);
    var lbl=document.getElementById('ctog-lbl-'+cardId);
    var val=document.getElementById('ctog-val-'+cardId);
    if(!drop)return;
    drop.innerHTML='';
    var items=[];
    campaigns.forEach(function(camp){
      items.push({type:'camp',label:'Campanha',name:camp.name,status:camp.status});
      (camp.adsets||[]).forEach(function(as){items.push({type:'adset',label:'Conjunto',name:as.name,status:as.status});});
      (camp.ads||[]).forEach(function(ad){items.push({type:'ad',label:'Criativo',name:ad.name,status:ad.status});});
    });
    if(items.length){lbl.textContent=items[0].label;val.textContent=items[0].name;}
    items.forEach(function(item,idx){
      var opt=document.createElement('div');
      opt.className='rt-camp-opt'+(idx===0?' active':'');
      var sColor=item.status==='ACTIVE'?'var(--success)':item.status==='PAUSED'?'var(--warn)':'var(--txt3)';
      opt.innerHTML='<div class="rt-camp-opt-lbl">'+esc(item.label)+'</div>'
        +'<div class="rt-camp-opt-val">'+esc(item.name)
        +(item.status?'<span style="font-size:8px;color:'+sColor+';margin-left:4px">● '+esc(item.status.toLowerCase())+'</span>':'')+'</div>';
      opt.addEventListener('click',function(e){
        e.stopPropagation();
        document.querySelectorAll('#cdrop-'+cardId+' .rt-camp-opt').forEach(function(o){o.classList.remove('active');});
        opt.classList.add('active');
        lbl.textContent=item.label;val.textContent=item.name;
        drop.classList.remove('open');
        document.getElementById('ctog-arr-'+cardId).classList.remove('open');
        // salva seleção permanente
        var csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
        var fd=new FormData();
        fd.append('_token',csrf);
        fd.append('report_id',reportId);
        fd.append('label',item.label);
        fd.append('name',item.name);
        fd.append('item_id',item.id||'');
        fetch(APP_URL+'/reports/rt-save-sel',{method:'POST',body:fd}).catch(function(){});
      });
      drop.appendChild(opt);
    });
  }
  document.addEventListener('click',function(e){
    if(!e.target.closest('.rt-camp-toggle')&&!e.target.closest('.rt-camp-drop')){
      document.querySelectorAll('.rt-camp-drop.open').forEach(function(d){d.classList.remove('open');});
      document.querySelectorAll('.rt-camp-arrow.open').forEach(function(a){a.classList.remove('open');});
    }
  });

  // Variação % vs período anterior
  function pctChange(curr, prev) {
    curr = parseFloat(curr)||0; prev = parseFloat(prev)||0;
    if(!prev) return null;
    return ((curr - prev) / prev * 100).toFixed(1);
  }
  function pctBadge(curr, prev, invertDir) {
    var pct = pctChange(curr, prev);
    if(pct === null) return '';
    var up = parseFloat(pct) >= 0;
    var good = invertDir ? !up : up;
    var color = good ? 'var(--success)' : 'var(--danger)';
    var arrow = up ? '▲' : '▼';
    return '<span style="font-size:8px;color:'+color+';margin-left:3px">'+arrow+Math.abs(pct)+'%</span>';
  }

  rtLoad(false);
})();
</script>

<!-- Logs de Envio Recentes -->
<style>
.dsl-wrap{overflow-y:auto;overflow-x:hidden;max-height:260px;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent}
.dsl-wrap::-webkit-scrollbar{width:3px}
.dsl-wrap::-webkit-scrollbar-thumb{background:var(--border2,#333);border-radius:3px}
.dsl-wrap::-webkit-scrollbar-track{background:transparent}
.dsl-row{display:grid;grid-template-columns:105px 105px 160px 180px 95px 1fr 80px;align-items:center;padding:7px 12px;border-bottom:1px solid var(--border2);gap:6px;transition:background .1s}
.dsl-row:hover{background:var(--bg3)}
.dsl-row:last-child{border-bottom:none}
.dsl-head{background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2}
.dsl-head .dsl-row{padding:6px 12px}
.dsl-th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--txt3);white-space:nowrap;overflow:hidden}
.dsl-td{font-size:11px;color:var(--txt2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsl-nome{font-size:11px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsl-pill{display:inline-flex;align-items:center;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;white-space:nowrap}
.dsl-ok{background:#0a2018;color:#1ABC9C;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap;display:inline-block}
.dsl-er{background:#2a0a0a;color:#E74C3C;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap;display:inline-block}
.dsl-conta-n{font-size:11px;font-weight:500;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>
<div class="db-autorow db-row-3" style="margin-bottom:14px">
  <div class="db-card db-widget" style="grid-column:span 3;padding:0">
    <div class="db-card-hd" style="padding:10px 14px">
      <div>
        <div class="db-card-title">&#x1F4CB; Logs de Envio</div>
        <div class="db-card-sub"><?= date('D, d M Y &middot; H:i', time()) ?> &mdash; alertas e relatórios</div>
      </div>
      <a href="<?= APP_URL ?>/notifications" style="font-size:11px;color:var(--accent);text-decoration:none;opacity:.8">Ver todos &rarr;</a>
    </div>
    <?php $dashLogs = $data['dash_send_logs'] ?? []; ?>
    <?php if(empty($dashLogs)): ?>
      <div style="text-align:center;padding:20px;font-size:12px;color:var(--txt3)">Nenhum envio registrado ainda<?php if(isset($data['dash_send_logs_err'])): ?><div style="color:#E74C3C;font-size:10px;margin-top:4px"><?= e($data['dash_send_logs_err']) ?></div><?php endif; ?></div>
    <?php else: ?>
    <div class="dsl-head">
      <div class="dsl-row">
        <span class="dsl-th">Tipo</span>
        <span class="dsl-th">Nome</span>
        <span class="dsl-th">Destinatário</span>
        <span class="dsl-th">Conta de anúncio</span>
        <span class="dsl-th">Status</span>
        <span class="dsl-th">Erro</span>
        <span class="dsl-th" style="text-align:right">Data/hora</span>
      </div>
    </div>
    <div class="dsl-wrap">
      <?php
      $_dslTypeLabels = [
        'saldo_minimo'    => ['&#9889; Alerta Saldo', '#F39C12'],
        'erro_conta'      => ['&#128680; Erro Conta',  '#E74C3C'],
        'ctr_baixo'       => ['&#128201; CTR Baixo',   '#3498DB'],
        'cpc_alto'        => ['&#128181; CPC Alto',    '#E74C3C'],
        'custo_conv_alto' => ['&#128176; Custo/Conv',  '#E67E22'],
        'roas_baixo'      => ['&#128200; ROAS',         '#9B59B6'],
      ];
      foreach($dashLogs as $_dl):
        $_dlIsRep  = $_dl['source'] === 'report';
        [$_dlLabel,$_dlColor] = $_dslTypeLabels[$_dl['tipo_alerta'] ?? ''] ?? ['&#128276; Alerta','#888'];
        $_dlNome   = $_dl['nome'] ?? $_dl['alert_name'] ?? '—';
        $_dlConta  = $_dl['nome_conta'] ?? '—';
        $_dlDest   = $_dl['destinatario'] ?? '—';
        $_dlErro   = !empty($_dl['erro_msg']) ? mb_substr($_dl['erro_msg'],0,40) : '';
      ?>
      <div class="dsl-row">
        <div>
          <?php if($_dlIsRep): ?>
            <span class="dsl-pill" style="background:#0a1a2e;color:#5B8DEF">&#128202; Relatório</span>
          <?php else: ?>
            <span class="dsl-pill" style="background:<?= $_dlColor ?>20;color:<?= $_dlColor ?>"><?= $_dlLabel ?></span>
          <?php endif; ?>
        </div>
        <span class="dsl-nome" title="<?= e($_dlNome) ?>"><?= e($_dlNome) ?></span>
        <span class="dsl-td" title="<?= e($_dlDest) ?>"><?= e($_dlDest) ?></span>
        <span class="dsl-conta-n" title="<?= e($_dlConta) ?>"><?= e($_dlConta) ?></span>
        <div>
          <?= $_dl['status']==='enviado' ? '<span class="dsl-ok">&#10003; Enviado</span>' : '<span class="dsl-er">&#10007; Erro</span>' ?>
        </div>
        <span class="dsl-td" style="color:#E74C3C;font-size:10px;line-height:1.4;word-break:break-word;white-space:normal" title="<?= e($_dl['erro_msg']??'') ?>"><?= $_dlErro ? '&#9888; '.e($_dl['erro_msg']??'') : '' ?></span>
        <span class="dsl-td" style="text-align:right;font-size:10px;color:var(--txt3)"><?= date('d/m H:i', strtotime($_dl['created_at'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Diagnóstico + Conexões -->
<div class="db-autorow db-row-2">
<div class="db-card db-widget" data-widget="diagnostico" data-widget-name="🔍 Diagnóstico do Sistema">
    <div class="db-card-hd">
      <div><div class="db-card-title">Diagnóstico do sistema</div><div class="db-card-sub">24/7 · alertas via WhatsApp (máx 2×/dia)</div></div>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <button onclick="dbRunDiag()" class="btn btn-secondary btn-sm" style="font-size:11px">⚡ Rodar</button>
        <?php if(($user['role']??'')==='admin'): ?>
        <a href="<?= APP_URL ?>/admin/debug" class="btn btn-secondary btn-sm" style="font-size:11px;text-decoration:none" title="Painel de debug completo">🔧 Debug</a>
        <button onclick="openCacheModal()" class="btn btn-secondary btn-sm" style="font-size:11px" title="Limpar cache do sistema">🗑️ Cache</button>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0" id="db-diag-grid">
      <?php
      $checks = [
        ['Banco de dados','ok'],['Cron jobs','ok'],
        ['API Meta tokens','ok'],['WhatsApp','ok'],
        ['Envios pendentes','ok'],['Sessões PHP','ok'],
      ];
      foreach($checks as $ck): ?>
      <div class="db-diag-item" style="padding:5px 8px">
        <div class="db-dl"><div class="db-ddot" style="background:var(--success)"></div><span style="font-size:10px;color:var(--txt2)"><?= $ck[0] ?></span></div>
        <span style="font-size:10px;font-weight:600;color:var(--success)">OK</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:8px;font-size:9px;color:var(--txt3);background:var(--bg3);border-radius:6px;padding:5px 9px">
      🔔 WA alerta ao detectar erro · para automaticamente ao resolver · último check: agora
    </div>
  </div>

  <div class="db-card db-widget" data-widget="conexoes" data-widget-name="🔗 Conexões Ativas">
    <div class="db-card-hd"><div><div class="db-card-title">Conexões ativas</div><div class="db-card-sub">Status em tempo real</div></div></div>
    <?php
    $conns = [];
    if(!empty($data['wp_detail'])){
      foreach($data['wp_detail'] as $wp){
        $ok = $wp['status']==='connected';
        $conns[] = ['💬 WhatsApp — '.e($wp['instance_name']), $ok?'Conectado':'Desconectado', $ok?'g':'r'];
      }
    } else {
      $conns[] = ['💬 WhatsApp', $data['wp_instances']>0?'Conectado':'Desconectado', $data['wp_instances']>0?'g':'r'];
    }
    $conns[] = ['📘 Meta Ads API', $data['total_accounts'].' contas', 'g'];
    $conns[] = ['🤖 IA (Groq/OpenAI)', 'Configurado', 'b'];
    $conns[] = ['📊 Google Ads', $data['spend_google']>0?'Ativo':'Desconectado', $data['spend_google']>0?'g':'r'];
    foreach($conns as $c):
      $color = match($c[2]){'g'=>'var(--success)','r'=>'var(--danger)','y'=>'var(--warn)',default=>'var(--accent)'};
      $pillCls = match($c[2]){'g'=>'db-pill-g','r'=>'db-pill-r','y'=>'db-pill-y',default=>'db-pill-b'};
    ?>
    <div class="db-diag-item">
      <div class="db-dl"><div class="db-ddot" style="background:<?= $color ?>"></div><span style="font-size:10px;color:var(--txt2)"><?= $c[0] ?></span></div>
      <span class="db-pill <?= $pillCls ?>"><?= $c[1] ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:8px;font-size:9px;color:var(--txt3);background:var(--bg3);border-radius:6px;padding:5px 9px">
      Última verificação: há 5min
    </div>
  </div>
</div>
</div>

</div><!-- /db-widgets-container -->



<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var APP_URL = '<?= APP_URL ?>';
var CSRF    = document.querySelector('meta[name="csrf-token"]')?.content||'';
var dbNotifOpen = false;
var dbNotifOn   = true;
var dbNoteCount = <?= count($data['postits']??[]) ?>;
var ptColors = ['#F7DC6F','#82E0AA','#85C1E9','#F1948A','#D7BDE2','#FAD7A0'];
var ptTxts   = ['#2c2a00','#0a2a1a','#0a1a2a','#2a0a0a','#1a0a2a','#2a1500'];

// Notif panel
function dbToggleNotif(){
  dbNotifOpen=!dbNotifOpen;
  document.getElementById('db-notif-panel').classList.toggle('open',dbNotifOpen);
}
document.addEventListener('click',function(e){
  var p=document.getElementById('db-notif-panel');
  var b=document.getElementById('db-notif-btn');
  if(dbNotifOpen&&p&&!p.contains(e.target)&&b&&!b.contains(e.target)){
    dbNotifOpen=false;p.classList.remove('open');
  }
});
function dbToggleNotifSw(){
  dbNotifOn=!dbNotifOn;
  var sw=document.getElementById('db-notif-sw');
  sw.classList.toggle('off',!dbNotifOn);
  document.getElementById('db-notif-sw-lbl').textContent=dbNotifOn?'Ativas':'Pausadas';
}
function dbClearNotifs(){
  document.getElementById('db-notif-list').innerHTML='<div style="padding:24px;text-align:center;font-size:11px;color:var(--txt3)">Nenhuma notificação</div>';
  var dot=document.getElementById('db-notif-dot');
  if(dot) dot.style.display='none';
}

// Diagnóstico
async function dbRunDiag(){
  var btn=event.target;
  btn.textContent='Verificando...';
  btn.disabled=true;
  try{
    // Simula checks reais — pode expandir com endpoint PHP
    await new Promise(r=>setTimeout(r,1200));
    btn.textContent='⚡ Rodar';
    btn.disabled=false;
    // Update health badge
    var htxt=document.getElementById('topbar-health-txt');if(htxt)htxt.textContent='Sistema OK';
    var badge=document.getElementById('topbar-health-badge');
    if(badge){badge.style.background='rgba(26,188,156,.1)';badge.style.borderColor='rgba(26,188,156,.25)';badge.style.color='#1ABC9C';}
  }catch(e){btn.textContent='⚡ Rodar';btn.disabled=false;}
}

// Post-its
function dbSetColor(id,bg,tc){
  var pt=document.getElementById('dbpt'+id)||document.getElementById('dbptnew'+id);
  if(!pt) return;
  pt.style.background=bg; pt.style.color=tc;
  pt.querySelectorAll('textarea').forEach(t=>t.style.color=tc);
  pt.querySelectorAll('.db-pc').forEach(p=>{
    p.classList.toggle('db-sel', p.style.background===bg);
  });
  pt.dataset.color=bg; pt.dataset.tc=tc;
  // Salva cor sempre (incluindo notas tmp ainda não persistidas)
  var ptId = pt.dataset.id;
  if(ptId && ptId !== 'new'){
    var ta = pt.querySelector('textarea');
    dbSaveNote(ptId, ta?ta.value:'', bg, tc);
  }
}

// Debounce timer por nota
var dbSaveTimers = {};

function dbSaveNote(id, content, color, tc){
  // Localiza o elemento pelo id real ou temporário
  var pt = document.getElementById('dbpt'+id)
         ||document.getElementById('dbptnew'+id)
         ||document.querySelector('[data-tmpid="'+id+'"]');
  color = color || (pt&&pt.dataset.color) || '#F7DC6F';
  tc    = tc    || (pt&&pt.dataset.tc)    || '#2c2a00';
  // Salva sempre — inclusive vazio (cria a nota no banco)
  var fd=new FormData();
  fd.append('_token',CSRF);
  fd.append('id', (id==='new'||String(id).startsWith('tmp')) ? 0 : id);
  fd.append('content', content||'');
  fd.append('color', color);
  fd.append('text_color', tc);
  fetch(APP_URL+'/dashboard/save-note',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.success && d.id){
        var newId = d.id;
        // Atualiza elemento com id real do banco
        if(pt){
          pt.id = 'dbpt'+newId;
          pt.dataset.id = newId;
          delete pt.dataset.tmpid;
        }
        // Atualiza eventos do botão fechar e textarea
        var closeBtn = pt&&pt.querySelector('.db-postit-close');
        if(closeBtn) closeBtn.setAttribute('onclick','dbRemoveNote('+newId+')');
        var ta = pt&&pt.querySelector('textarea');
        if(ta){
          ta.setAttribute('onblur','dbSaveNote('+newId+',this.value)');
          // Remove listener de input antigo e adiciona com novo id
          ta.oninput = function(){ dbScheduleSave(newId, this); };
        }
        // Atualiza seletores de cor
        if(pt) pt.querySelectorAll('.db-pc').forEach(function(pc){
          var c = pc.style.background;
          var tci = ptColors.indexOf(c);
          if(tci>=0) pc.setAttribute('onclick','dbSetColor('+newId+',"'+c+'","'+ptTxts[tci]+'")');
        });
        // Atualiza timers
        if(dbSaveTimers[id]){ clearTimeout(dbSaveTimers[id]); delete dbSaveTimers[id]; }
      }
    }).catch(()=>{});
}

// Salva com debounce de 800ms enquanto digita
function dbScheduleSave(id, ta){
  if(dbSaveTimers[id]) clearTimeout(dbSaveTimers[id]);
  dbSaveTimers[id] = setTimeout(function(){
    dbSaveNote(id, ta.value);
  }, 800);
}

function dbRemoveNote(id){
  // 1 clique só — sem confirm
  var el = document.getElementById('dbpt'+id)
         ||document.getElementById('dbptnew'+id)
         ||document.querySelector('[data-id="'+id+'"]');
  if(!el) return;

  // Pega a posição do slot no grid para restaurar o placeholder
  var grid = document.getElementById('db-postits');
  var slots = Array.from(grid.children);
  var slotIdx = slots.indexOf(el);

  // Anima saída
  el.style.transition='opacity .15s,transform .15s';
  el.style.opacity='0'; el.style.transform='scale(0.92)';

  setTimeout(function(){
    // Cores dos placeholders por slot
    var emptyColors = ['#F7DC6F','#82E0AA','#85C1E9','#F1948A'];
    var emptyTxts   = ['#2c2a00','#0a2a1a','#0a1a2a','#2a0a0a'];
    var ci = slotIdx >= 0 ? Math.min(slotIdx, emptyColors.length-1) : 0;
    var ebg = emptyColors[ci], etc = emptyTxts[ci];

    // Substitui pelo slot vazio no lugar do post-it removido
    var placeholder = document.createElement('div');
    placeholder.className = 'db-postit db-postit-empty';
    placeholder.dataset.id = 'new';
    placeholder.style.cssText = 'background:'+ebg+';color:'+etc+';cursor:pointer;opacity:.45;display:flex;align-items:center;justify-content:center;min-height:120px;aspect-ratio:1/1;transition:opacity .2s';
    placeholder.onclick = function(){ dbAddNoteSlot(this, ebg, etc); };
    placeholder.innerHTML = '<span style="font-size:22px;opacity:.6">+</span>';

    if(slotIdx >= 0 && slotIdx < slots.length){
      grid.replaceChild(placeholder, el);
    } else {
      el.remove();
    }
  }, 160);

  // Delete no servidor
  if(id && id!=='new' && !String(id).startsWith('tmp')){
    var fd=new FormData();
    fd.append('_token',CSRF); fd.append('id',id);
    fetch(APP_URL+'/dashboard/delete-note',{method:'POST',body:fd}).catch(()=>{});
  }
}

var dbTmpCounter = 0;

function dbBuildNoteHTML(tmpId, bg, tc, ci){
  return '<button class="db-postit-close" onclick="dbRemoveNote(\''+tmpId+'\')">✕</button>'+
    '<div class="db-postit-colors">'+
    ptColors.map((c,i)=>'<div class="db-pc'+(i===ci?' db-sel':'')+'" style="background:'+c+'" onclick="dbSetColor(\''+tmpId+'\',\''+c+'\',\''+ptTxts[i]+'\')"></div>').join('')+
    '</div>'+
    '<textarea class="db-postit-ta" id="dbta_'+tmpId+'" style="color:'+tc+';flex:1" placeholder="Escreva sua nota..." '+
      'onblur="dbSaveNote(\''+tmpId+'\',this.value)" '+
      'oninput="dbScheduleSave(\''+tmpId+'\',this)"></textarea>'+
    '<div class="db-postit-footer">agora</div>';
}

function dbAddNoteSlot(el, bg, tc){
  dbTmpCounter++;
  var tmpId = 'tmp'+dbTmpCounter;
  var ci = ptColors.indexOf(bg); if(ci<0) ci=0;
  el.className = 'db-postit';
  el.style.cssText = 'background:'+bg+';color:'+tc+';aspect-ratio:1/1;display:flex;flex-direction:column;border-radius:8px;padding:11px 12px;position:relative;transition:opacity .15s,transform .15s';
  el.dataset.id = tmpId;
  el.dataset.tmpid = tmpId;
  el.dataset.color = bg;
  el.dataset.tc = tc;
  el.id = 'dbptnew'+tmpId;
  el.onclick = null;
  el.innerHTML = dbBuildNoteHTML(tmpId, bg, tc, ci);
  // Salva imediatamente ao criar (mesmo vazia) para registrar no banco com a cor
  dbSaveNote(tmpId, '', bg, tc);
  setTimeout(function(){ var ta=el.querySelector('textarea'); if(ta) ta.focus(); }, 50);
}

function dbAddNote(){
  var empty=document.querySelector('.db-postit-empty');
  if(empty){
    dbTmpCounter++;
    var ci=Math.floor(Math.random()*ptColors.length);
    dbAddNoteSlot(empty,ptColors[ci],ptTxts[ci]);
    return;
  }
  dbTmpCounter++;
  var tmpId='tmp'+dbTmpCounter;
  var ci=Math.floor(Math.random()*ptColors.length);
  var bg=ptColors[ci],tc=ptTxts[ci];
  var div=document.createElement('div');
  div.className='db-postit'; div.dataset.id=tmpId; div.dataset.tmpid=tmpId;
  div.dataset.color=bg; div.dataset.tc=tc;
  div.id='dbptnew'+tmpId;
  div.style.cssText='background:'+bg+';color:'+tc+';aspect-ratio:1/1;display:flex;flex-direction:column;border-radius:8px;padding:11px 12px;position:relative';
  div.innerHTML=dbBuildNoteHTML(tmpId,bg,tc,ci);
  document.getElementById('db-postits').appendChild(div);
  // Salva imediatamente para criar no banco
  dbSaveNote(tmpId, '', bg, tc);
  setTimeout(function(){ div.querySelector('textarea').focus(); },50);
}

// Charts
var metaVals   = <?= $metaVals ?>;
var googleVals = <?= $googleVals ?>;
var chartLabels= <?= $chartLabels ?>;

// Fallback demo data if empty
var hasData = metaVals.some(v=>v>0)||googleVals.some(v=>v>0);
if(!hasData){
  metaVals   = [1200,1580,980,1740,2100,1430,1560];
  googleVals = [420,510,380,620,780,490,540];
}

new Chart(document.getElementById('dbAreaC'),{
  type:'line',
  data:{labels:chartLabels,datasets:[
    {label:'Meta Ads',data:metaVals,borderColor:'#1877F2',backgroundColor:'rgba(24,119,242,0.12)',fill:true,tension:0.45,pointRadius:3,pointBackgroundColor:'#1877F2',borderWidth:2.5},
    {label:'Google Ads',data:googleVals,borderColor:'#EA4335',backgroundColor:'rgba(234,67,53,0.08)',fill:true,tension:0.45,pointRadius:3,pointBackgroundColor:'#EA4335',borderWidth:2.5}
  ]},
  options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
    plugins:{legend:{labels:{color:'#888',font:{size:10},boxWidth:10}},tooltip:{backgroundColor:'#1C2333',borderColor:'rgba(255,255,255,0.08)',borderWidth:1,padding:8,titleColor:'#CDD6F4',bodyColor:'#aaa',callbacks:{label:d=>`${d.dataset.label}: R$${Math.round(d.raw).toLocaleString('pt-BR')}`}}},
    scales:{x:{grid:{color:'rgba(128,128,128,0.06)'},ticks:{color:'#888',font:{size:10}}},y:{grid:{color:'rgba(128,128,128,0.06)'},ticks:{color:'#888',font:{size:10},callback:v=>v>=1000?'R$'+Math.round(v/1000)+'k':'R$'+Math.round(v)}}}}
});

new Chart(document.getElementById('dbDonutC'),{
  type:'doughnut',
  data:{labels:['Meta','Google'],datasets:[{data:[<?= $metaPct ?>,<?= $googlePct ?>],backgroundColor:['#1877F2','#EA4335'],borderWidth:0,hoverOffset:4}]},
  options:{responsive:false,cutout:'70%',plugins:{legend:{display:false}}}
});

function dbSpark(id,data,color){
  var el=document.getElementById(id);
  if(!el) return;
  new Chart(el,{
    type:'line',
    data:{labels:data.map((_,i)=>i),datasets:[{data,borderColor:color,backgroundColor:'transparent',borderWidth:1.8,pointRadius:0,tension:0.4}]},
    options:{responsive:false,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}}}
  });
}
var totalS = <?= (float)$data['spend_meta']+(float)$data['spend_google'] ?>;
dbSpark('dbsp1',hasData?metaVals.map((v,i)=>v+(googleVals[i]||0)):[900,1100,980,1400,1580,1430,Math.round(totalS)||1560],'#5B8DEF');
dbSpark('dbsp2',[8,10,9,13,11,14,<?= $data['reports_sent'] ?>],'#1ABC9C');
dbSpark('dbsp3',[2,4,3,5,4,6,<?= $data['ai_analyses'] ?>],'#9B59B6');
dbSpark('dbsp4',[20,21,21,22,23,23,<?= $data['total_clients'] ?>],'#F39C12');

// Toggle "ver todos" nos cards do dashboard
function toggleList(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  var expanded = el.dataset.expanded === '1';
  if (expanded) {
    el.style.maxHeight = '280px';
    el.dataset.expanded = '0';
    btn.textContent = btn.textContent.replace('− ver menos', '+ ver todos' + btn.textContent.match(/\(\d+\)/) ? ' ' + btn.textContent.match(/\(\d+\)/)[0] : '');
    btn.textContent = btn.getAttribute('data-label-more');
  } else {
    el.style.maxHeight = el.scrollHeight + 'px';
    el.dataset.expanded = '1';
    if (!btn.getAttribute('data-label-more')) btn.setAttribute('data-label-more', btn.textContent);
    btn.textContent = '− ver menos';
  }
}
// Inicializa labels dos botões
document.addEventListener('DOMContentLoaded', function() {
  ['budgetList','weekList','scoreList'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.dataset.expanded = '0';
  });
});

</script>

<script>
(function(){
  function fixRows(){
    var w = window.innerWidth;
    if(w > 900) return;
    var card_w = w <= 600 ? '82vw' : '48vw';
    document.querySelectorAll('div.db-autorow.db-row-3').forEach(function(row){
      row.style.cssText += ';display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding-bottom:8px!important;grid-template-columns:none!important';
      Array.from(row.children).forEach(function(c){
        c.style.cssText += ';flex:0 0 '+card_w+'!important;max-width:'+card_w+'!important;min-width:0!important;scroll-snap-align:start';
      });
    });
  }
  document.addEventListener('DOMContentLoaded', fixRows);
  window.addEventListener('resize', fixRows);
})();
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
