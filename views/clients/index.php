<?php
$pageTitle   = 'Clientes';
$currentPage = 'clients';
ob_start();
?>
<div class="table-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
  <div class="table-toolbar">
    <form method="GET" style="display:flex;align-items:center;gap:10px">
      <div class="search-box">
        <span class="material-icons-outlined">search</span>
        <input type="text" name="q" placeholder="Nome, e-mail ou telefone..." value="<?= e($q ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
    </form>
    <a href="<?= APP_URL ?>/clients/create" class="btn btn-primary btn-sm">
      <span class="material-icons-outlined">person_add</span> Novo Cliente
    </a>
  </div>

  <?php if (empty($clients)): ?>
  <div class="empty-state">
    <span class="material-icons-outlined">people_outline</span>
    <h3>Nenhum cliente cadastrado</h3>
    <p>Cadastre seus clientes para vincular aos relatórios</p>
    <a href="<?= APP_URL ?>/clients/create" class="btn btn-primary">Cadastrar Primeiro Cliente</a>
  </div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th style='width:50px'>St.</th><th>Cliente</th><th>Empresa</th><th>WhatsApp</th><th>Pagamento</th><th>Cadastrado</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td>
          <?php $cActive = ($c['status']??'active') === 'active'; ?>
          <button onclick="toggleClientStatus(<?=$c['id']?>, <?=$cActive?'true':'false'?>)"
            title="<?= $cActive ? 'Clique para desativar' : 'Clique para ativar' ?>"
            style="background:none;border:none;cursor:pointer;padding:2px 4px">
            <span style="display:inline-block;position:relative;width:32px;height:18px;
              background:<?= $cActive ? 'var(--success)' : 'var(--border2,#444)' ?>;
              border-radius:9px;vertical-align:middle">
              <span style="display:inline-block;position:absolute;top:2px;
                left:<?= $cActive ? '16px' : '2px' ?>;
                width:14px;height:14px;background:#fff;border-radius:50%;
                box-shadow:0 1px 3px rgba(0,0,0,.3)">
              </span>
            </span>
          </button>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar avatar-sm"><?= initials($c['name']) ?></div>
            <div>
              <div style="font-weight:600;color:var(--txt)"><?= e($c['name']) ?></div>
              <?php if ($c['email']): ?><div style="font-size:11px;color:var(--txt2)"><?= e($c['email']) ?></div><?php endif; ?>
            </div>
          </div>
        </td>
        <td><?= e($c['company'] ?: '—') ?></td>
        <td>
          <?php if ($c['phone']): ?>
          <a href="https://wa.me/<?= preg_replace('/\D/','', $c['phone']) ?>" target="_blank"
             style="color:var(--success);font-size:13px;text-decoration:none">
            <i class="fa-brands fa-whatsapp"></i> <?= e($c['phone']) ?>
          </a>
          <?php else: ?><span style="color:var(--txt3)">—</span><?php endif; ?>
        </td>
        <td>
          <?php if(($c['payment_type']??'cartao')==='prepago'): ?>
            <span style="font-size:10px;font-weight:700;border-radius:20px;padding:2px 10px;background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid rgba(46,204,113,.3)">
              💰 Pré-pago
            </span>
          <?php else: ?>
            <span style="font-size:10px;border-radius:20px;padding:2px 10px;background:var(--bg3);color:var(--txt3);border:1px solid var(--border)">
              💳 Cartão
            </span>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--txt2)"><?= date('d/m/Y',strtotime($c['created_at'])) ?></td>
        <td>
          <div style="display:flex;gap:4px">
            <a href="<?= APP_URL ?>/clients/edit?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Editar">
              <span class="material-icons-outlined" style="font-size:14px">edit</span>
            </a>
            <button class="btn btn-sm" style="background:rgba(91,141,239,.15);color:var(--accent);border:1px solid rgba(91,141,239,.3)"
              onclick="openClientDash(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')" title="Ver Dashboard">
              <span class="material-icons-outlined" style="font-size:14px">dashboard</span>
            </button>
            <form method="POST" action="<?= APP_URL ?>/clients/delete" style="display:inline">
              <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon"
                data-confirm="Excluir cliente <?= e($c['name']) ?>?">
                <span class="material-icons-outlined" style="font-size:14px">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div style="padding:14px 18px;border-top:1px solid var(--border)">
    <div class="pagination">
      <span class="pg-info">Total: <?= $total ?></span>
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($q??'') ?>" class="pg-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
var APP_URL = '<?= APP_URL ?>';
var CSRF    = '<?= e($_SESSION["csrf_token"] ?? "") ?>';

async function toggleClientStatus(id, isActive) {
  var action = isActive ? 'desativar' : 'ativar';
  if (!confirm('Deseja ' + action + ' este cliente?')) return;
  try {
    var fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('id', id);
    fd.append('status', isActive ? 'inactive' : 'active');
    var r = await fetch(APP_URL + '/clients/toggle-status', {method:'POST', body:fd});
    var d = await r.json();
    if (d.success) { window.location.reload(); }
    else { alert('Erro: ' + (d.error || 'Tente novamente')); }
  } catch(e) { alert('Erro de conexão'); }
}

// ── Dashboard por cliente ─────────────────────────────────────────────────────
var cdAllMetrics = [
  {k:'spend',         l:'💰 Investimento',    fn:function(m,x){return 'R$ '+nf(m.spend||0);},          sub:null},
  {k:'impressions',   l:'👁 Impressões',       fn:function(m,x){return ni(m.impressions||0);},           sub:null},
  {k:'clicks',        l:'🖱 Cliques',          fn:function(m,x){return ni(m.clicks||0);},                sub:null},
  {k:'reach',         l:'👥 Alcance',          fn:function(m,x){return ni(m.reach||0);},                 sub:null},
  {k:'ctr',           l:'📊 CTR',              fn:function(m,x){return nf(m.ctr||0)+'%';},               sub:null},
  {k:'cpc',           l:'💵 CPC',              fn:function(m,x){return 'R$ '+nf(m.cpc||0);},             sub:null},
  {k:'cpm',           l:'📈 CPM',              fn:function(m,x){return 'R$ '+nf(m.cpm||0);},             sub:null},
  {k:'frequency',     l:'🔁 Frequência',       fn:function(m,x){return nf(m.frequency||0)+'x';},         sub:null},
  {k:'messages',      l:'💬 Mensagens',        fn:function(m,x){return ni(m.messages||0);},              sub:function(m,x){return x.cmsg>0?'Custo: R$ '+nf(x.cmsg):'';}},
  {k:'leads',         l:'🎯 Leads',            fn:function(m,x){return ni(m.leads||0);},                 sub:function(m,x){return x.cpl>0?'Custo: R$ '+nf(x.cpl):'';}},
  {k:'conversions',   l:'✅ Conversões',       fn:function(m,x){return ni(m.conversions||0);},           sub:null},
  {k:'roas',          l:'📈 ROAS',             fn:function(m,x){return nf(m.roas||0)+'x';},              sub:null},
  {k:'purchases',     l:'🛒 Compras',          fn:function(m,x){return ni(m.purchases||0);},             sub:null},
  {k:'profile_visits',l:'👤 Visitas Perfil',   fn:function(m,x){return ni(m.profile_visits||0);},        sub:null},
];

var cdPeriodos = [
  {v:'last_7_days',   l:'Últimos 7 dias'},
  {v:'last_15_days',  l:'Últimos 15 dias'},
  {v:'last_30_days',  l:'Últimos 30 dias'},
  {v:'last_90_days',  l:'Últimos 90 dias'},
  {v:'this_month',    l:'Este mês'},
  {v:'last_month',    l:'Mês passado'},
  {v:'maximum',       l:'Máximo (desde o início)'},
  {v:'custom',        l:'📅 Personalizado'},
];

var cdCurrentId=0, cdCurrentData=null, cdCurrentPeriod='last_30_days';

function getCdMetrics(cid) {
  try { var s=localStorage.getItem('cd_metrics_'+cid); if(s) return JSON.parse(s); } catch(e){}
  return {spend:1,impressions:1,clicks:1,reach:1,ctr:1,cpc:1,cpm:1,frequency:1};
}
function setCdMetrics(cid, obj) {
  try { localStorage.setItem('cd_metrics_'+cid, JSON.stringify(obj)); } catch(e){}
}

async function openClientDash(id, name) {
  cdCurrentId = id;
  cdCurrentPeriod = 'last_30_days';
  document.getElementById('cdTitle').textContent = name;
  document.getElementById('cdBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--txt3)">Carregando...</div>';
  document.getElementById('cdModal').style.display = 'flex';
  await loadClientDash(id, cdCurrentPeriod);
}

async function loadClientDash(id, period, customStart, customEnd) {
  document.getElementById('cdBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--txt3)">Carregando...</div>';
  try {
    var url = APP_URL + '/dashboard/client?id=' + id + '&period=' + period;
    if (period === 'custom' && customStart && customEnd) {
      url += '&custom_start=' + customStart + '&custom_end=' + customEnd;
    }
    var r = await fetch(url);
    var d = await r.json();
    if (!d.success) { document.getElementById('cdBody').innerHTML = '<p style="color:var(--danger)">Erro ao carregar</p>'; return; }
    cdCurrentData = d;
    renderCdDash(d, period, customStart, customEnd);
  } catch(e) { document.getElementById('cdBody').innerHTML='<p style="color:var(--danger)">Erro de conexão</p>'; }
}

function renderCdDash(d, period, customStart, customEnd) {
  var m = d.metricas || {};
  var spend = parseFloat(m.spend||0), clicks = parseInt(m.clicks||0);
  var msgs = parseInt(m.messages||0), leads = parseInt(m.leads||0);
  var x = {cpc:parseFloat(m.cpc||0)||(clicks>0?spend/clicks:0), cmsg:msgs>0?spend/msgs:0, cpl:leads>0?spend/leads:0};
  var active = getCdMetrics(cdCurrentId);

  function kpiCard(l,v,s) {
    return '<div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 12px">'
      +'<div style="font-size:10px;color:var(--txt3);margin-bottom:3px">'+l+'</div>'
      +'<div style="font-size:15px;font-weight:700;color:var(--txt)">'+v+'</div>'
      +(s?'<div style="font-size:10px;color:var(--txt3);margin-top:2px">'+s+'</div>':'')
      +'</div>';
  }

  var html = '';

  // ── Seletor de Período ──
  html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap">';
  html += '<select id="cdPeriodSel" onchange="cdOnPeriodChange()" style="flex:1;min-width:160px;padding:5px 8px;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--txt);font-size:12px">';
  cdPeriodos.forEach(function(p) {
    var sel = (period||'last_30_days') === p.v ? ' selected' : '';
    html += '<option value="'+p.v+'"'+sel+'>'+p.l+'</option>';
  });
  html += '</select>';
  html += '<button onclick="openCdConfig()" style="font-size:11px;padding:5px 10px;border:1px solid var(--border);border-radius:6px;background:var(--bg3);color:var(--txt2);cursor:pointer;white-space:nowrap">⚙️ Métricas</button>';
  html += '</div>';

  // Datas personalizadas
  html += '<div id="cdCustomDates" style="display:'+(period==='custom'?'flex':'none')+';gap:8px;margin-bottom:10px;align-items:center">';
  html += '<input type="date" id="cdCustomStart" value="'+(customStart||'')+'" style="flex:1;padding:5px 8px;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--txt);font-size:12px">';
  html += '<span style="color:var(--txt3);font-size:12px">até</span>';
  html += '<input type="date" id="cdCustomEnd" value="'+(customEnd||'')+'" style="flex:1;padding:5px 8px;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--txt);font-size:12px">';
  html += '<button onclick="cdApplyCustom()" style="padding:5px 12px;background:var(--accent);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px">Aplicar</button>';
  html += '</div>';

  // Label do período
  if (d.periodo_label) {
    html += '<div style="font-size:10px;color:var(--txt3);margin-bottom:10px;padding:3px 8px;background:var(--bg3);border-radius:4px;display:inline-block">📅 '+esc(d.periodo_label)+'</div>';
  }

  // ── Grid de métricas ──
  var active_list = cdAllMetrics.filter(function(mt){return active[mt.k];});
  if (!active_list.length) active_list = cdAllMetrics.slice(0,8);
  for (var i=0; i<active_list.length; i+=4) {
    var row = active_list.slice(i,i+4);
    html += '<div style="display:grid;grid-template-columns:repeat('+row.length+',1fr);gap:8px;margin-bottom:8px">';
    row.forEach(function(mt){
      html += kpiCard(mt.l, mt.fn(m,x), mt.sub?mt.sub(m,x):'');
    });
    html += '</div>';
  }
  html += '<div style="margin-bottom:10px"></div>';

  // Contas
  if (d.accounts && d.accounts.length) {
    html += '<div style="font-size:11px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">Contas de Anúncio</div>';
    html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">';
    d.accounts.forEach(function(a){
      var cor = a.status==='active' ? 'var(--success)' : 'var(--txt3)';
      html += '<span style="font-size:11px;padding:3px 10px;border-radius:20px;background:var(--bg3);border:1px solid var(--border);color:'+cor+'">'+esc(a.account_name)+'</span>';
    });
    html += '</div>';
  }

  // Relatórios
  if (d.reports && d.reports.length) {
    html += '<div style="font-size:11px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">Relatórios</div>';
    html += '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px"><thead><tr style="border-bottom:1px solid var(--border)">'
      +'<th style="text-align:left;padding:4px 6px;color:var(--txt3)">Título</th>'
      +'<th style="text-align:left;padding:4px 6px;color:var(--txt3)">Frequência</th>'
      +'<th style="text-align:left;padding:4px 6px;color:var(--txt3)">Último Envio</th>'
      +'<th style="text-align:left;padding:4px 6px;color:var(--txt3)">Status</th>'
      +'</tr></thead><tbody>';
    d.reports.forEach(function(rp){
      var st = rp.last_send_status==='ok'
        ? '<span style="color:var(--success)">✓ OK</span>'
        : rp.last_send_status ? '<span style="color:var(--danger)">✗ Erro</span>' : '—';
      html += '<tr style="border-bottom:1px solid var(--border2)">'
        +'<td style="padding:5px 6px;color:var(--txt)">'+esc(rp.title)+'</td>'
        +'<td style="padding:5px 6px;color:var(--txt2)">'+esc(rp.frequency)+'</td>'
        +'<td style="padding:5px 6px;color:var(--txt3)">'+(rp.sent_at?rp.sent_at.substring(0,16):'—')+'</td>'
        +'<td style="padding:5px 6px">'+st+'</td></tr>';
    });
    html += '</tbody></table>';
  }

  // Alertas
  if (d.alerts && d.alerts.length) {
    html += '<div style="font-size:11px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">Alertas</div>';
    html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">';
    d.alerts.forEach(function(al){
      var cor = al.ativo ? 'var(--success)' : 'var(--txt3)';
      html += '<span style="font-size:11px;padding:3px 10px;border-radius:20px;background:var(--bg3);border:1px solid var(--border);color:'+cor+'">⚡ '+esc(al.name)+'</span>';
    });
    html += '</div>';
  }

  // IA logs
  if (d.ai_logs && d.ai_logs.length) {
    html += '<div style="font-size:11px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">Últimas Análises IA</div>';
    d.ai_logs.forEach(function(ai){
      html += '<div style="font-size:11px;padding:6px 10px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);margin-bottom:4px;color:var(--txt2)">'
        +'✨ '+esc(ai.campaign_name)+' · '+esc(ai.period)+' <span style="color:var(--txt3)">'+ai.created_at.substring(0,16)+'</span></div>';
    });
  }

  document.getElementById('cdBody').innerHTML = html;
}

function cdOnPeriodChange() {
  var sel = document.getElementById('cdPeriodSel');
  if (!sel) return;
  var v = sel.value;
  cdCurrentPeriod = v;
  var customDiv = document.getElementById('cdCustomDates');
  if (customDiv) customDiv.style.display = v==='custom' ? 'flex' : 'none';
  if (v !== 'custom') {
    loadClientDash(cdCurrentId, v);
  }
}

function cdApplyCustom() {
  var s = document.getElementById('cdCustomStart').value;
  var e = document.getElementById('cdCustomEnd').value;
  if (!s || !e) { alert('Selecione as datas'); return; }
  loadClientDash(cdCurrentId, 'custom', s, e);
}

function openCdConfig() {
  var active = getCdMetrics(cdCurrentId);
  var html = '<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;display:flex;align-items:center;justify-content:center" id="cdCfgOv">'
    +'<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;width:300px;max-height:80vh;overflow-y:auto">'
    +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">'
    +'<span style="font-size:13px;font-weight:700;color:var(--txt)">⚙️ Métricas do Dashboard</span>'
    +'<button onclick="closeCdCfg()" style="background:none;border:none;cursor:pointer;color:var(--txt3);font-size:18px">✕</button>'
    +'</div>'
    +'<div style="font-size:11px;color:var(--txt3);margin-bottom:10px">Marque as métricas que quer ver:</div>';
  cdAllMetrics.forEach(function(mt){
    var ck = active[mt.k] ? 'checked' : '';
    html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border2)">'
      +'<span style="font-size:12px;color:var(--txt)">'+mt.l+'</span>'
      +'<input type="checkbox" '+ck+' data-k="'+mt.k+'" style="width:15px;height:15px;cursor:pointer;accent-color:var(--accent)">'
      +'</div>';
  });
  html += '<button onclick="saveCdConfig()" class="btn btn-primary" style="width:100%;margin-top:14px;font-size:12px">✅ Aplicar</button>';
  html += '</div></div>';
  document.body.insertAdjacentHTML('beforeend', html);
}

function closeCdCfg(){var el=document.getElementById('cdCfgOv');if(el)el.remove();}
function saveCdConfig() {
  var obj = {};
  document.querySelectorAll('#cdCfgOv input[type=checkbox]').forEach(function(cb){
    obj[cb.dataset.k] = cb.checked ? 1 : 0;
  });
  setCdMetrics(cdCurrentId, obj);
  document.getElementById('cdCfgOv').remove();
  if (cdCurrentData) renderCdDash(cdCurrentData, cdCurrentPeriod);
}

function nf(v){return parseFloat(v||0).toFixed(2).replace('.',',');}
function ni(v){return parseInt(v||0).toLocaleString('pt-BR');}
function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
</script>

<!-- Modal Dashboard Cliente -->
<div id="cdModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:16px">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:14px;width:100%;max-width:720px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border)">
      <span style="font-size:14px;font-weight:700;color:var(--txt)" id="cdTitle">Dashboard Cliente</span>
      <button onclick="document.getElementById('cdModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--txt3);font-size:20px">✕</button>
    </div>
    <div id="cdBody" style="padding:16px;overflow-y:auto;flex:1"></div>
  </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
