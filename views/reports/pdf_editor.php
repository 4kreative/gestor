<?php
// views/reports/pdf_editor.php
$tplData = json_decode($template['config'] ?? '{}', true) ?: [];
// Se config vazia ou sem blocos, usar padrão
$blocks  = !empty($tplData['blocks']) ? $tplData['blocks'] : ['header','kpis','message','footer'];
$palette = !empty($tplData['palette']) ? $tplData['palette'] : ['accent'=>'#5B8DEF','bg'=>'#ffffff','txt'=>'#1a1a2e'];
$tplName = $template['name']   ?? 'Meu Template';
$bCfgs   = $tplData['blockConfigs'] ?? [];
$allTemplates = $allTemplates ?? [];
?>
<style>
.pe-wrap{display:grid;grid-template-columns:190px 1fr;height:calc(100vh - 64px);overflow:hidden}
.pe-sidebar{background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.pe-canvas-wrap{overflow-y:auto;overflow-x:hidden;background:#ccc;padding:16px;display:flex;justify-content:center;align-items:flex-start}
.pe-props-panel{position:fixed;top:64px;right:0;width:240px;height:calc(100vh - 64px);background:var(--bg2);border-left:1px solid var(--border);overflow-y:auto;padding:12px;display:none;z-index:100;box-shadow:-4px 0 16px rgba(0,0,0,.2)}
.pe-props-panel.open{display:block}
.pe-main{display:grid;grid-template-rows:46px 1fr;overflow:hidden}
.pe-toolbar{background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;padding:0 14px}
.pe-canvas{background:#fff;width:100%;max-width:720px;min-height:860px;box-shadow:0 4px 24px rgba(0,0,0,.25);padding:36px;font-family:Arial,sans-serif;overflow:visible;box-sizing:border-box}
.pe-block-list{padding:8px;flex:1;overflow-y:auto}
.pbb{display:flex;align-items:center;gap:7px;padding:7px 9px;border-radius:7px;border:1px dashed var(--border2);margin-bottom:5px;cursor:pointer;font-size:12px;color:var(--txt2);background:var(--bg3);width:100%;text-align:left;transition:all .12s}
.pbb:hover{background:var(--accent3);border-color:var(--accent);color:var(--txt)}
.pe-app{padding:10px;border-top:1px solid var(--border)}
.pr{display:flex;align-items:center;gap:6px;margin-bottom:7px}
.pr label{font-size:11px;color:var(--txt2);width:58px;flex-shrink:0}
.pr input[type=color]{width:30px;height:24px;border:1px solid var(--border);border-radius:4px;cursor:pointer;padding:1px;background:var(--bg3)}
.pr input[type=text],.pr select,.pr input[type=number]{flex:1;font-size:11px;padding:4px 6px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt)}
/* blocos canvas */
.pb{position:relative;margin-bottom:14px;border:2px dashed transparent;border-radius:5px;padding:3px;cursor:pointer;transition:border-color .12s}
.pb:hover{border-color:#5B8DEF66}
.pb.sel{border-color:#5B8DEF;background:#5B8DEF08}
.pb-bar{display:none;justify-content:flex-end;gap:3px;margin-bottom:4px;z-index:20}
.pb:hover .pb-bar,.pb.sel .pb-bar{display:flex}
.pb-bar button{font-size:10px;padding:2px 8px;border-radius:3px;border:1px solid var(--border);background:var(--bg2);color:var(--txt2);cursor:pointer}
.pb-bar button:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.pb-bar .pb-del:hover{background:#e74c3c;border-color:#e74c3c}
.dg{position:absolute;left:-20px;top:50%;transform:translateY(-50%);cursor:grab;color:#bbb;font-size:15px;display:none}
.pb:hover .dg{display:block}
.sg{opacity:.25;background:#EEF4FF!important;border:2px dashed #5B8DEF!important}
/* PDF elements */
.ph{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid var(--ap);padding-bottom:12px}
.ph h1{font-size:18px;font-weight:700;color:var(--ap);margin:0}
.ph .sub{font-size:11px;color:#666;margin-top:2px}
.ph-logo{font-size:16px;font-weight:900;color:var(--ap)}
.pp{display:inline-block;background:#EEF4FF;color:var(--ap);font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;margin-top:4px}
.ps{font-size:10px;font-weight:700;color:var(--ap);border-bottom:1px solid #e8eaf0;padding-bottom:4px;margin:0 0 8px;text-transform:uppercase;letter-spacing:.4px}
.kg{display:grid;gap:7px;width:100%}
.kc{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:6px;padding:10px 12px}
.kl{font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.kv{font-size:15px;font-weight:700;color:#1a1a2e}
.cb{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:7px;padding:13px}
.pm{background:#f8f9fc;border-left:4px solid var(--ap);border-radius:0 5px 5px 0;padding:12px;font-size:12px;line-height:1.8;white-space:pre-wrap;color:#2c2c3e}
.pt{width:100%;border-collapse:collapse;font-size:11px}
.pt th{background:#f0f2f8;text-align:left;padding:6px 8px;font-weight:700;color:#555}
.pt td{padding:6px 8px;border-bottom:1px solid #f0f2f8}
.pf{padding-top:10px;border-top:1px solid #e8eaf0;font-size:10px;color:#aaa;display:flex;justify-content:space-between;margin-top:12px}
.psi{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:7px;padding:13px;text-align:center}
.psl{width:150px;border-top:1px solid #aaa;margin:12px auto 0}
.psn{font-size:13px;font-weight:700;margin-top:5px;color:#333}
hr.pdv{border:none;border-top:1px solid #e8eaf0;margin:3px 0}
.psp{height:18px}
/* props panel */
.pp-sec{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;margin:10px 0 6px;padding-bottom:4px;border-bottom:1px solid var(--border)}
.pp-row{display:flex;align-items:center;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border2);font-size:12px;color:var(--txt2)}
.pp-row input[type=checkbox]{width:14px;height:14px;cursor:pointer;accent-color:var(--accent)}
.pp-inp{width:100%;font-size:11px;padding:5px 7px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt);margin-bottom:7px}
.pp-ta{width:100%;height:130px;font-size:11px;padding:6px 8px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt);resize:vertical;line-height:1.6;margin-bottom:4px}
.pp-lbl{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px}
</style>

<div class="pe-wrap">
<!-- SIDEBAR ESQUERDA -->
<div class="pe-sidebar">
  <div style="padding:10px 12px;border-bottom:1px solid var(--border);font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase">📦 Adicionar Bloco</div>
  <div class="pe-block-list">
    <?php $allB=['header'=>['🏷️','Cabeçalho'],'kpis'=>['📊','KPIs'],'kpis2'=>['📈','KPIs Secundários'],'chart'=>['📉','Gráfico Linha'],'chart_bar'=>['📊','Gráfico Barras'],'comparative'=>['⚖️','Comparativo'],'audience'=>['👥','Audiência (Idade/Sexo)'],'geo'=>['🗺️','Localização (Cidades/Estados)'],'demographic'=>['📊','Demográfico (Idade+Gênero)'],'message'=>['💬','Mensagem'],'footer'=>['🔖','Rodapé'],'signature'=>['✍️','Assinatura'],'divider'=>['➖','Divisor'],'spacer'=>['⬜','Espaço']];
    foreach($allB as $k=>[$ic,$lb]):?>
    <button class="pbb" onclick="addBlock('<?=$k?>')"><span><?=$ic?></span><span><?=$lb?></span></button>
    <?php endforeach;?>
  </div>
  <div style="padding:8px 12px;border-top:1px solid var(--border);font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase">🎨 Aparência</div>
  <div class="pe-app">
    <div class="pr"><label>Destaque</label><input type="color" id="cA" oninput="ap()"></div>
    <div class="pr"><label>Fundo</label><input type="color" id="cB" oninput="ap()"></div>
    <div class="pr"><label>Texto</label><input type="color" id="cT" oninput="ap()"></div>
    <div class="pr"><label>Nome</label><input type="text" id="tN" value="<?=e($tplName)?>"></div>
  </div>
</div>

<!-- CANVAS PRINCIPAL -->
<div class="pe-main">
  <div class="pe-toolbar">
    <button class="btn btn-secondary btn-sm" onclick="previewPdf()">👁 Preview</button>
    <button class="btn btn-primary btn-sm btn-save-tpl" id="saveTplBtn" onclick="saveTemplate()">💾 Salvar Template</button>
    <button class="btn btn-secondary btn-sm" onclick="novoTemplate()" title="Criar novo template em branco" style="margin-left:4px">✚ Novo</button>
    <?php if(!empty($_GET['report_id'])): ?>
    <span id="realMetricsBar" style="display:none;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--success);margin-left:8px;background:rgba(39,174,96,.1);padding:4px 10px;border-radius:20px;border:1px solid rgba(39,174,96,.3)"></span>
    <span style="font-size:11px;color:var(--txt3);margin-left:4px">⏳ Carregando métricas reais...</span>
    <?php else: ?>
    <span style="font-size:11px;color:var(--txt3);margin-left:8px;font-style:italic">Prévia com dados de exemplo — abra via relatório para ver dados reais</span>
    <?php endif; ?>
    <select id="previewPeriod" style="font-size:11px;padding:4px 8px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt);margin-left:4px" onchange="onPreviewPeriodChange(this.value)">
      <option value="last_7_days">Últimos 7 dias</option>
      <option value="last_14_days">Últimos 14 dias</option>
      <option value="last_30_days" selected>Últimos 30 dias</option>
      <option value="last_90_days">Últimos 90 dias</option>
      <option value="this_month">Este mês</option>
      <option value="last_month">Mês passado</option>
      <option value="max">Máximo</option>
      <option value="custom">📅 Personalizado</option>
    </select>
    <div id="customPreviewDates" style="display:none;align-items:center;gap:4px">
      <input type="date" id="previewStart" onchange="onCustomDateChange()" style="font-size:11px;padding:3px 6px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt)">
      <span style="font-size:11px;color:var(--txt3)">até</span>
      <input type="date" id="previewEnd" onchange="onCustomDateChange()" style="font-size:11px;padding:3px 6px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt)">
    </div>
    <span id="sMsg" style="font-size:12px;color:var(--success);display:none;margin-left:6px">✅ Salvo!</span>
    <?php if(!empty($allTemplates)): ?>
    <select onchange="loadTemplate(this.value)" style="font-size:11px;padding:4px 8px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:var(--txt);margin-left:8px">
      <option value="">📂 Carregar template salvo...</option>
      <?php foreach($allTemplates as $t): ?>
      <option value="<?=$t['id']?>" <?= $t['id']==$template['id']?'selected':'' ?>><?=e($t['name'])?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-danger btn-sm" onclick="deleteTemplate()" title="Excluir template atual" style="padding:4px 8px">🗑</button>
    <?php endif; ?>
    <div style="flex:1"></div>
    <a href="<?=APP_URL?>/reports" class="btn btn-ghost btn-sm">← Voltar</a>
  </div>
  <div class="pe-canvas-wrap">
    <div class="pe-canvas" id="pC"><div id="bC"></div></div>
  </div>
</div>

<!-- PAINEL DIREITO DE PROPRIEDADES -->
<div class="pe-props-panel" id="pP">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <span style="font-size:12px;font-weight:700;color:var(--txt)" id="pPTitle">Configurar</span>
    <button onclick="closePanel()" style="background:none;border:none;cursor:pointer;color:var(--txt3);font-size:18px;line-height:1">✕</button>
  </div>
  <div id="pPBody"></div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var CSRF=<?=json_encode($_SESSION['csrf_token'])?>;
var APP_URL=<?=json_encode(APP_URL)?>;
var TPL_ID=<?=(int)($template['id']??0)?>;
var REPORT_ID=<?=(int)($_GET['report_id']??0)?>;

// Carrega métricas reais do relatório vinculado (se houver)
if(REPORT_ID){
  fetch(APP_URL+'/reports/rt-metrics',{credentials:'include'})
    .then(function(r){return r.json();})
    .then(function(d){
      if(!d.success||!d.cards)return;
      var card=d.cards.find(function(c){return c.id==REPORT_ID;});
      if(!card||!card.metrics)return;
      var m=card.metrics;
      var n2=function(v){return (Math.round(v*100)/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
      var ni=function(v){return Math.round(v).toLocaleString('pt-BR');};
      // Popula S com dados reais
      S.spend      = 'R$ '+n2(m.spend||0);
      S.impr       = ni(m.impressions||0);
      S.clicks     = ni(m.clicks||0);
      S.ctr        = n2(m.ctr||0)+'%';
      S.cpc        = 'R$ '+n2(m.cpc||0);
      S.cpm        = 'R$ '+n2(m.cpm||0);
      S.reach      = ni(m.reach||0);
      S.freq       = n2(m.frequency||0)+'x';
      S.msgs       = ni(m.messages||0);
      S.leads      = ni(m.leads||m.conversions||0);
      S.roas       = n2(m.roas||0)+'x';
      S.conv       = ni(m.conversions||0);
      S.cpl        = 'R$ '+n2(m.cpl||(m.leads>0?m.spend/m.leads:0)||0);
      S.cmsg       = 'R$ '+n2(m.cmsg||0);
      S.profile_visit = ni(m.profile_visits||0);
      S.cpv        = 'R$ '+n2(m.custo_por_visita||0);
      // Período anterior (bloco comparativo)
      var pm = card.prev_metrics || {};
      if(pm && pm.spend !== undefined){
        S.pSpend  = 'R$ '+n2(pm.spend||0);
        S.pImpr   = ni(pm.impressions||0);
        S.pClicks = ni(pm.clicks||0);
        S.pCtr    = n2(pm.ctr||0)+'%';
        S.pCpc    = 'R$ '+n2(pm.cpc||0);
        S.pCpm    = 'R$ '+n2(pm.cpm||0);
        S.pReach  = ni(pm.reach||0);
        S.pFreq   = n2(pm.frequency||0)+'x';
        S.pMsgs   = ni(pm.messages||0);
        S.pLeads  = ni(pm.leads||pm.conversions||0);
        S.pRoas   = n2(pm.roas||0)+'x';
        S.pConv   = ni(pm.conversions||0);
        S.pCpl    = 'R$ '+n2(pm.cpl||0);
        S.pCmsg   = 'R$ '+n2(pm.cmsg||0);
        S.pProfileVisit = ni(pm.profile_visits||pm.profile_visit||0);
        S.pCpv    = 'R$ '+n2(pm.custo_por_visita||pm.cpv||0);
      }
      // Período e identificação
      if(card.period_range) S.period = card.period_range;
      if(card.client_name)  S.client = card.client_name;
      if(card.title)        S.title  = card.title;
      // Remove o "carregando..." da toolbar
      var loadSpan = document.querySelector('[style*="Carregando métricas"]');
      if(loadSpan) loadSpan.style.display = 'none';
      // Re-renderiza o canvas com dados reais
      render();
      // Notifica
      var bar=document.getElementById('realMetricsBar');
      if(bar){bar.style.display='flex';bar.textContent='✅ Métricas reais carregadas ('+card.period_label+')';}
    })
    .catch(function(){});
}
var blocks=<?=json_encode($blocks)?>;
var pal=<?=json_encode($palette)?>;
var bCfg=<?=json_encode($bCfgs)?>;
var selIdx=-1;

// Dados amostra
var S={
  title:'Relatório de Tráfego',client:'Empresa Exemplo',company:'Marketing Digital',
  period:'02/04/2026 a 09/04/2026',siteName:<?=json_encode($siteName??'GestorPro')?>,logo:'',
  spend:'R$ 95,08',impr:'21.933',clicks:'312',ctr:'1,42%',cpc:'R$ 0,30',cpm:'R$ 4,34',
  reach:'7.795',freq:'2,81x',msgs:'12',leads:'5',roas:'3,20x',conv:'5',cpl:'R$ 19,01',cmsg:'R$ 7,92',
  pSpend:'R$ 82,40',pCtr:'1,18%',pCpc:'R$ 0,35',pCpm:'R$ 4,80',pImpr:'18.500',pClicks:'265',
  pReach:'6.900',pFreq:'2,50x',pMsgs:'9',pLeads:'3',pRoas:'2,80x',pConv:'3',pCpl:'R$ 27,46',pCmsg:'R$ 9,15',
  msg:'Campanha de Mensagens\n02/04/2026 a 09/04/2026\n\nInvestimento: R$ 95,08\nAlcance: 7.795\nConversas: 12\nCusto/conversa: R$ 7,92',
  footerL:'',footerR:'',
  profile_visit:'247',cpv:'R$ 0,39',pProfileVisit:'185',pCpv:'R$ 0,45'
};

var allKpis=[
  {k:'spend',l:'💰 Investimento'},{k:'impr',l:'👁 Impressões'},{k:'clicks',l:'🖱 Cliques'},
  {k:'ctr',l:'📊 CTR'},{k:'cpc',l:'💵 CPC'},{k:'cpm',l:'📈 CPM'},
  {k:'reach',l:'👥 Alcance'},{k:'freq',l:'🔁 Frequência'},{k:'msgs',l:'💬 Mensagens'},
  {k:'leads',l:'🎯 Leads'},{k:'roas',l:'📈 ROAS'},{k:'conv',l:'✅ Conversões'},
  {k:'cpl',l:'💵 Custo/Lead'},{k:'cmsg',l:'💵 Custo/Msg'},
  {k:'profile_visit',l:'👤 Visitas ao Perfil'},{k:'cpv',l:'💵 Custo/Visita'}
];
var allKpis2=[
  {k:'msgs',l:'💬 Mensagens'},{k:'leads',l:'🎯 Leads'},{k:'roas',l:'📈 ROAS'},
  {k:'conv',l:'✅ Conversões'},{k:'cpl',l:'💵 Custo/Lead'},{k:'cmsg',l:'💵 Custo/Msg'},
  {k:'profile_visit',l:'👤 Visitas ao Perfil'},{k:'cpv',l:'💵 Custo/Visita'}
];
var allComp=[
  {k:'spend',l:'Investimento',c:'spend',p:'pSpend',hi:false},
  {k:'ctr',l:'CTR',c:'ctr',p:'pCtr',hi:true},{k:'cpc',l:'CPC',c:'cpc',p:'pCpc',hi:false},
  {k:'cpm',l:'CPM',c:'cpm',p:'pCpm',hi:false},{k:'impr',l:'Impressões',c:'impr',p:'pImpr',hi:true},
  {k:'clicks',l:'Cliques',c:'clicks',p:'pClicks',hi:true},{k:'reach',l:'Alcance',c:'reach',p:'pReach',hi:true},
  {k:'freq',l:'Frequência',c:'freq',p:'pFreq',hi:false},{k:'msgs',l:'Mensagens',c:'msgs',p:'pMsgs',hi:true},
  {k:'leads',l:'Leads',c:'leads',p:'pLeads',hi:true},{k:'roas',l:'ROAS',c:'roas',p:'pRoas',hi:true},
  {k:'conv',l:'Conversões',c:'conv',p:'pConv',hi:true},{k:'cpl',l:'Custo/Lead',c:'cpl',p:'pCpl',hi:false},
  {k:'cmsg',l:'Custo/Msg',c:'cmsg',p:'pCmsg',hi:false},
  {k:'profile_visit',l:'Visitas Perfil',c:'profile_visit',p:'pProfileVisit',hi:true},
  {k:'cpv',l:'Custo/Visita',c:'cpv',p:'pCpv',hi:false}
];

var kpiColors={spend:'#e74c3c',impr:'#3498db',clicks:'#9b59b6',ctr:'#27ae60',cpc:'#e67e22',cpm:'#e67e22',reach:'#f39c12',freq:'#1abc9c',msgs:'#2ecc71',leads:'#3498db',roas:'#27ae60',conv:'#27ae60',cpl:'#e74c3c',cmsg:'#e74c3c',profile_visit:'#9b59b6',cpv:'#e74c3c'};
var metricColors={'spend':'#e74c3c','impressions':'#3498db','clicks':'#9b59b6','reach':'#f39c12','messages':'#2ecc71','ctr':'#27ae60','cpc':'#e67e22','cpm':'#e67e22','profile_visits':'#8e44ad','leads':'#3498db','roas':'#27ae60','conversions':'#27ae60','frequency':'#1abc9c'};
function kc(l,v,k){
  var col=kpiColors[k||'']||'#5B8DEF';
  return '<div class="kc" style="border-left:3px solid '+col+';background:linear-gradient(135deg,'+col+'08,#f8f9fc)">'
    +'<div class="kl" style="color:'+col+'88">'+l+'</div>'
    +'<div class="kv" style="color:'+col+'">'+v+'</div></div>';
}
function tr(l,c,p,hi){
  var arr='',cv=parseFloat((c||'').replace(/[^0-9,]/g,'').replace(',','.')),pv=parseFloat((p||'').replace(/[^0-9,]/g,'').replace(',','.'));
  if(pv>0){var v=((cv-pv)/pv*100).toFixed(1),g=hi?(v>0):(v<0);arr='<span style="color:'+(g?'#27ae60':'#e74c3c')+'">'+(v>0?'▲':'▼')+Math.abs(v)+'%</span>';}
  return '<tr><td>'+l+'</td><td>'+c+'</td><td>'+p+'</td><td>'+(arr||'—')+'</td></tr>';
}

function getCfg(idx){return bCfg[idx]||{};}

function renderBlock(key,idx){
  var c=getCfg(idx), ap=pal.accent||'#5B8DEF';
  var t=c.title||S.title, cl=c.client||S.client, co=c.company||S.company;
  var pr=c.period||S.period, sn=c.siteName||S.siteName, logo=c.logo||'';
  if(key==='header'){
    var logoH=logo?'<img src="'+logo+'" style="max-height:44px;max-width:110px;object-fit:contain;border-radius:3px">'
      :'<span class="ph-logo">'+sn+'</span>';
    return '<div class="ph"><div><h1>'+t+'</h1><div class="sub">'+cl+' — '+co+'</div><span class="pp2">📅 '+pr+'</span></div><div>'+logoH+'</div></div>';
  }
  if(key==='kpis'){
    var cfgKeys=Object.keys(c).filter(function(k){return allKpis.some(function(o){return o.k===k;});});
    var opts;
    if(cfgKeys.length===0){
      // Nunca configurado: padrão 8
      opts=allKpis.slice(0,8);
    } else {
      // Configurado: só os marcados como true/1
      opts=allKpis.filter(function(o){return c[o.k]===true||c[o.k]===1;});
      if(!opts.length) opts=allKpis.slice(0,8);
    }
    var cols=Math.min(opts.length,4);
    return '<div><div class="ps">Métricas Principais</div><div class="kg" style="grid-template-columns:repeat('+cols+',1fr)">'+opts.map(function(o){return kc(o.l,S[o.k]||'—',o.k);}).join('')+'</div></div>';
  }
  if(key==='kpis2'){
    var cfgKeys2=Object.keys(c).filter(function(k){return allKpis2.some(function(o){return o.k===k;});});
    var opts2;
    if(cfgKeys2.length===0){
      opts2=allKpis2.slice(0,3);
    } else {
      opts2=allKpis2.filter(function(o){return c[o.k]===true||c[o.k]===1;});
      if(!opts2.length) opts2=allKpis2.slice(0,3);
    }
    var cols2=Math.min(opts2.length,4);
    return '<div><div class="ps" style="color:var(--accent)">Resultados</div><div class="kg" style="grid-template-columns:repeat('+cols2+',1fr)">'+opts2.map(function(o){return kc(o.l,S[o.k]);}).join('')+'</div></div>';
  }
  if(key==='chart'){
    var chartMetricMap={'spend':'Investimento (R$)','impressions':'Impressões','clicks':'Cliques','reach':'Alcance','messages':'Mensagens','ctr':'CTR (%)','cpc':'CPC (R$)','cpm':'CPM (R$)','frequency':'Frequência','leads':'Leads','conversions':'Conversões','roas':'ROAS','profile_visits':'Visitas ao Perfil'};
    var cm=c.metric||'spend';
    var cmLbl=chartMetricMap[cm]||cm;
    var sampleData={'spend':[10.5,11.8,11.0,14.8,14.6,12.0,10.0],'impressions':[3100,3500,3200,4200,4100,3400,2800],'clicks':[46,52,47,62,60,50,42],'reach':[1100,1250,1150,1550,1500,1200,1000],'messages':[1,2,1,3,2,1,2],'ctr':[1.5,1.5,1.5,1.5,1.5,1.5,1.5],'cpc':[0.23,0.23,0.23,0.24,0.24,0.24,0.24],'cpm':[4.3,4.4,4.2,4.5,4.4,4.3,4.2],'frequency':[1.2,1.3,1.2,1.4,1.4,1.3,1.2],'leads':[0,1,0,1,0,0,1],'conversions':[0,1,0,1,0,0,1],'roas':[0,2.5,0,2.8,0,0,2.2],'profile_visits':[35,42,38,55,50,40,38]};
    var sd=sampleData[cm]||sampleData['spend'];
    var col=c.chart_color||(metricColors[cm]||ap);
    var id='ch'+Date.now()+idx;
    setTimeout(function(){var el=document.getElementById(id);if(!el)return;new Chart(el,{type:'line',data:{labels:['03/04','04/04','05/04','06/04','07/04','08/04','09/04'],datasets:[{label:cmLbl,data:sd,borderColor:col,backgroundColor:col+'33',tension:.4,fill:true,pointRadius:4,pointBackgroundColor:col}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{font:{size:9}}},x:{ticks:{font:{size:9}}}}}});},150);
    return '<div class="cb"><div class="ps" style="color:'+col+'">📈 '+cmLbl+' <span style="font-size:9px;font-weight:400;color:#888">(dados reais no PDF)</span></div><canvas id="'+id+'" height="90"></canvas></div>';
  }
  if(key==='chart_bar'){
    var barMetricMap={'spend':'Investimento (R$)','impressions':'Impressões','clicks':'Cliques','reach':'Alcance','messages':'Mensagens','ctr':'CTR (%)','cpc':'CPC (R$)','cpm':'CPM (R$)','frequency':'Frequência','leads':'Leads','profile_visits':'Visitas Perfil'};
    var bm=c.bar_metric||'spend';
    var bmLbl=barMetricMap[bm]||bm;
    var bm2=c.bar_metric2||'';
    var bm2Lbl=barMetricMap[bm2]||'';
    var barSample={'spend':[10.5,11.8,11.0,14.8,14.6,12.0,10.0],'impressions':[3100,3500,3200,4200,4100,3400,2800],'clicks':[46,52,47,62,60,50,42],'reach':[1100,1250,1150,1550,1500,1200,1000],'messages':[1,2,1,3,2,1,2],'ctr':[1.5,1.5,1.5,1.5,1.5,1.5,1.5],'cpc':[0.23,0.23,0.23,0.24,0.24,0.24,0.24],'cpm':[4.3,4.4,4.2,4.5,4.4,4.3,4.2],'frequency':[1.2,1.3,1.2,1.4,1.4,1.3,1.2],'leads':[0,1,0,1,0,0,1],'conversions':[0,1,0,1,0,0,1],'roas':[0,2.5,0,2.8,0,0,2.2],'profile_visits':[35,42,38,55,50,40,38]};
    var bd=barSample[bm]||barSample['spend'];
    var bd2=bm2?barSample[bm2]:null;
    var col1=c.bar_color1||(metricColors[bm]||ap);
    var col2=c.bar_color2||(metricColors[bm2]||ap);
    var id2='cb'+Date.now()+idx;
    var ds=[{label:bmLbl,data:bd,backgroundColor:col1+'CC',borderRadius:4,borderColor:col1,borderWidth:1,yAxisID:'y'}];
    var scalesCfg={y:{beginAtZero:true,position:'left',ticks:{font:{size:9},color:col1},grid:{color:'#f0f0f0'}},x:{ticks:{font:{size:9}}}};
    if(bd2){
      // Segunda métrica como linha no eixo direito (escala independente)
      ds.push({label:bm2Lbl,data:bd2,type:'line',borderColor:col2,backgroundColor:col2+'22',borderWidth:2,pointRadius:4,pointBackgroundColor:col2,tension:.3,fill:false,yAxisID:'y2'});
      scalesCfg['y2']={beginAtZero:true,position:'right',ticks:{font:{size:9},color:col2},grid:{drawOnChartArea:false}};
    }
    setTimeout(function(){var el=document.getElementById(id2);if(!el)return;new Chart(el,{type:'bar',data:{labels:['03/04','04/04','05/04','06/04','07/04','08/04','09/04'],datasets:ds},options:{responsive:true,plugins:{legend:{display:!!bd2,labels:{font:{size:9},boxWidth:10}}},scales:scalesCfg}});},150);
    var barTitle2=bmLbl+(bm2?' vs '+bm2Lbl:' Diário');
    return '<div class="cb"><div class="ps" style="color:'+col1+'">📊 '+barTitle2+' <span style="font-size:9px;font-weight:400;color:#888">(dados reais no PDF)</span></div><canvas id="'+id2+'" height="90"></canvas></div>';
  }
  if(key==='comparative'){
    var period=c.compPeriod||'Período Anterior';
    var cfgHasComp=Object.keys(c).some(function(k){return allComp.some(function(o){return o.k===k;});});
    var opts3=cfgHasComp?allComp.filter(function(o){return c[o.k]===true||c[o.k]===1;}):allComp.slice(0,8);
    if(!opts3.length)opts3=allComp.slice(0,8);
    return '<div><div class="ps">Comparativo — '+period+'</div><table class="pt"><thead><tr><th>Métrica</th><th>Atual</th><th>Anterior</th><th>Variação</th></tr></thead><tbody>'+opts3.map(function(o){return tr(o.l,S[o.c],S[o.p],o.hi);}).join('')+'</tbody></table></div>';
  }
  if(key==='message'){
    var msg=c.msg!==undefined?c.msg:S.msg;
    return '<div><div class="ps">Mensagem do Relatório</div><div class="pm">'+msg.replace(/\n/g,'<br>')+'</div></div>';
  }
  if(key==='footer'){
    var fl=c.footerL||(new Date().toLocaleDateString('pt-BR')+' — '+sn);
    var fr=c.footerR||'gestorads.j6digital.com.br';
    return '<div class="pf"><span>'+fl+'</span><span>'+fr+'</span></div>';
  }
  if(key==='signature')return '<div class="psi"><div style="font-size:11px;color:#888">Aprovado por</div><div class="psl"></div><div class="psn">'+cl+'</div><div style="font-size:10px;color:#aaa;margin-top:2px">'+pr+'</div></div>';
  if(key==='demographic'){
    return '<div style="background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:12px">'
      +'<div class="ps" style="margin-bottom:8px">Dados Demográficos</div>'
      +'<table style="width:100%;border-collapse:collapse;font-size:10px">'
      +'<thead><tr style="background:#fff">'
        +'<th style="padding:5px 6px;text-align:left;border-bottom:2px solid #e8eaf0;color:#555">Segmento</th>'
        +'<th style="padding:5px 6px;text-align:right;border-bottom:2px solid #e8eaf0;color:#555">Alcance</th>'
        +'<th style="padding:5px 6px;text-align:right;border-bottom:2px solid #2ecc71;color:#2ecc71">Conversas</th>'
        +'<th style="padding:5px 6px;text-align:right;border-bottom:2px solid #e74c3c;color:#e74c3c">Custo/Conv</th>'
        +'<th style="padding:5px 6px;text-align:right;border-bottom:2px solid #3498db;color:#3498db">CTR</th>'
      +'</tr></thead>'
      +'<tbody>'
      +'<tr style="background:#fff"><td style="padding:5px 6px;color:#e91e8c">● 25-34, Mulheres</td><td style="text-align:right;padding:5px 6px">2.087</td><td style="text-align:right;padding:5px 6px;color:#2ecc71">4</td><td style="text-align:right;padding:5px 6px;color:#e74c3c">R$ 5,35</td><td style="text-align:right;padding:5px 6px;color:#3498db">0,06%</td></tr>'
      +'<tr style="background:#fafafa"><td style="padding:5px 6px;color:#e91e8c">● 45-54, Mulheres</td><td style="text-align:right;padding:5px 6px">1.410</td><td style="text-align:right;padding:5px 6px;color:#2ecc71">3</td><td style="text-align:right;padding:5px 6px;color:#e74c3c">R$ 6,76</td><td style="text-align:right;padding:5px 6px;color:#3498db">0,07%</td></tr>'
      +'<tr style="background:#fff"><td style="padding:5px 6px;color:#3498db">● 35-44, Homens</td><td style="text-align:right;padding:5px 6px">1.908</td><td style="text-align:right;padding:5px 6px;color:#2ecc71">2</td><td style="text-align:right;padding:5px 6px;color:#e74c3c">R$ 12,72</td><td style="text-align:right;padding:5px 6px;color:#3498db">0,03%</td></tr>'
      +'</tbody></table>'
      +'<div style="font-size:9px;color:#aaa;margin-top:6px">Dados reais no PDF — top 10 segmentos por alcance</div>'
      +'</div>';
  }
  if(key==='geo'){
    return '<div style="background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px">'
      +'<div class="ps" style="margin-bottom:10px">Localização da Audiência</div>'
      +'<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">'
      +'<div style="background:#fff;border:1px solid #e8eaf0;border-radius:6px;padding:10px">'
        +'<div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:6px">Cidades</div>'
        +'<div style="font-size:11px;color:#5B8DEF">📍 Dados reais no PDF</div></div>'
      +'<div style="background:#fff;border:1px solid #e8eaf0;border-radius:6px;padding:10px">'
        +'<div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:6px">Estados</div>'
        +'<div style="font-size:11px;color:#9b59b6">📍 Dados reais no PDF</div></div>'
      +'<div style="background:#fff;border:1px solid #e8eaf0;border-radius:6px;padding:10px">'
        +'<div style="font-size:9px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:6px">Países</div>'
        +'<div style="font-size:11px;color:#27ae60">📍 Dados reais no PDF</div></div>'
      +'</div></div>';
  }
  if(key==='audience'){
    return '<div style="background:#f8f9fc;border:1px solid #e8eaf0;border-radius:8px;padding:14px">'
      +'<div class="ps" style="margin-bottom:8px">Audiência</div>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
      +'<div style="background:#fff;border:1px solid #e8eaf0;border-radius:6px;padding:10px;text-align:center;font-size:11px;color:#888">📊 Faixa Etária<br><small>(dados reais no PDF)</small></div>'
      +'<div style="background:#fff;border:1px solid #e8eaf0;border-radius:6px;padding:10px;text-align:center;font-size:11px;color:#888">🧑‍🤝‍🧑 Por Sexo<br><small>(dados reais no PDF)</small></div>'
      +'</div></div>';
  }
  if(key==='divider')return '<hr class="pdv">';
  if(key==='spacer')return '<div class="psp"></div>';
  return '';
}

function addBlock(key){blocks.push(key);bCfg[blocks.length-1]={};render();}
function moveBlock(i,d){if(i+d<0||i+d>=blocks.length)return;var t=blocks[i];blocks[i]=blocks[i+d];blocks[i+d]=t;var tc=bCfg[i];bCfg[i]=bCfg[i+d]||{};bCfg[i+d]=tc||{};render();}
function removeBlock(i){var oldCfgs={};blocks.forEach(function(_,j){oldCfgs[j]=bCfg[j]||{};});blocks.splice(i,1);var nb={};blocks.forEach(function(_,j){nb[j]=oldCfgs[j<i?j:j+1]||{};});bCfg=nb;render();}

function render(){
  var ap=pal.accent||'#5B8DEF';
  document.getElementById('pC').style.setProperty('--ap',ap);
  document.getElementById('pC').style.background=pal.bg||'#fff';
  document.getElementById('pC').style.color=pal.txt||'#1a1a2e';
  var h='';
  blocks.forEach(function(key,i){
    var hasCfg=['header','kpis','kpis2','comparative','message','footer','chart','chart_bar'].indexOf(key)>=0;
    h+='<div class="pb" id="pb'+i+'" onclick="selBlock('+i+',\''+key+'\')">'
      +'<span class="dg">⠿</span>'
      +'<div class="pb-bar">'
      +(hasCfg?'<button onclick="event.stopPropagation();openPanel('+i+',\''+key+'\')">⚙️ Config</button>':'')
      +'<button onclick="event.stopPropagation();moveBlock('+i+',-1)" title="Mover para cima">↑</button>'
      +'<button onclick="event.stopPropagation();moveBlock('+i+',1)" title="Mover para baixo">↓</button>'
      +'<button class="pb-del" onclick="event.stopPropagation();removeBlock('+i+')" title="Remover">✕</button>'
      +'</div>'
      +'<div>'+renderBlock(key,i)+'</div></div>';
  });
  document.getElementById('bC').innerHTML=h||'<div style="text-align:center;padding:40px;color:#aaa;font-size:13px">← Clique em um bloco para adicionar</div>';

  Sortable.create(document.getElementById('bC'),{animation:150,handle:'.dg',ghostClass:'sg',onEnd:function(e){var oldI=e.oldIndex,newI=e.newIndex;var m=blocks.splice(oldI,1)[0];blocks.splice(newI,0,m);var oldCfgs={};blocks.forEach(function(_,j){oldCfgs[j]=bCfg[j]||{};});var mc=oldCfgs[oldI]||{};var nb={};var tmpArr=Object.keys(oldCfgs).map(function(k){return oldCfgs[k];});tmpArr.splice(oldI,1);tmpArr.splice(newI,0,mc);tmpArr.forEach(function(v,j){nb[j]=v||{};});bCfg=nb;render();}});
  if(selIdx>=0){var el=document.getElementById('pb'+selIdx);if(el)el.classList.add('sel');}
}

function selBlock(i,key){selIdx=i;document.querySelectorAll('.pb').forEach(function(b){b.classList.remove('sel');});var el=document.getElementById('pb'+i);if(el)el.classList.add('sel');}

function ap(){pal.accent=document.getElementById('cA').value;pal.bg=document.getElementById('cB').value;pal.txt=document.getElementById('cT').value;render();}

// ── Painel de propriedades ──────────────────────────────────────────────────
var panelIdx=-1,panelKey='';

function openPanel(idx,key){
  panelIdx=idx;panelKey=key;
  var p=document.getElementById('pP');
  var t=document.getElementById('pPTitle');
  var b=document.getElementById('pPBody');
  var c=getCfg(idx);
  p.classList.add('open');

  var labels={'header':'🏷️ Cabeçalho','kpis':'📊 KPIs','kpis2':'📈 KPIs Secundários','comparative':'⚖️ Comparativo','message':'💬 Mensagem','footer':'🔖 Rodapé','chart':'📉 Gráfico Linha','chart_bar':'📊 Gráfico Barras'};
  t.textContent=labels[key]||'Configurar';

  if(key==='header'){
    b.innerHTML=''
      +'<div class="pp-lbl">Título</div><input class="pp-inp" id="f_title" value="'+esc(c.title||S.title)+'" oninput="saveCfgField(\'title\')">'
      +'<div class="pp-lbl">Nome do Cliente</div><input class="pp-inp" id="f_client" value="'+esc(c.client||S.client)+'" oninput="saveCfgField(\'client\')">'
      +'<div class="pp-lbl">Empresa</div><input class="pp-inp" id="f_company" value="'+esc(c.company||S.company)+'" oninput="saveCfgField(\'company\')">'
      +'<div class="pp-lbl">Período</div><input class="pp-inp" id="f_period" value="'+esc(c.period||S.period)+'" oninput="saveCfgField(\'period\')">'
      +'<div class="pp-lbl">Texto canto direito</div><input class="pp-inp" id="f_siteName" value="'+esc(c.siteName||S.siteName)+'" oninput="saveCfgField(\'siteName\')">'
      +'<div class="pp-lbl">Logo (imagem)</div>'
      +'<input type="file" accept="image/*" onchange="loadLogo('+idx+',this)" style="font-size:11px;width:100%;margin-bottom:6px">'
      +(c.logo?'<img src="'+c.logo+'" style="max-width:100%;max-height:48px;border-radius:4px;margin-bottom:4px"><br>':'')
      +(c.logo?'<button onclick="removeLogo('+idx+')" style="font-size:10px;color:#e74c3c;background:none;border:none;cursor:pointer">✕ Remover logo</button>':'');
  } else if(key==='kpis'){
    // Se nunca configurado, defaults = primeiros 8
    var defaultKpis=['spend','impr','clicks','ctr','cpc','cpm','reach','freq'];
    var cfgHasKeys=Object.keys(c).some(function(k){return allKpis.some(function(o){return o.k===k;});});
    b.innerHTML='<div class="pp-sec">Métricas a exibir</div>'+allKpis.map(function(o){
      var ck=cfgHasKeys?(c[o.k]===true||c[o.k]===1):(defaultKpis.indexOf(o.k)>=0);
      return '<div class="pp-row"><span>'+o.l+'</span><input type="checkbox" '+(ck?'checked':'')+' onchange="toggleMetric(\''+o.k+'\',this.checked)"></div>';
    }).join('');
  } else if(key==='kpis2'){
    var defaultKpis2=['msgs','leads','conv'];
    var cfgHasKeys2=Object.keys(c).some(function(k){return allKpis2.some(function(o){return o.k===k;});});
    b.innerHTML='<div class="pp-sec">Métricas a exibir</div>'+allKpis2.map(function(o){
      var ck=cfgHasKeys2?(c[o.k]===true||c[o.k]===1):(defaultKpis2.indexOf(o.k)>=0);
      return '<div class="pp-row"><span>'+o.l+'</span><input type="checkbox" '+(ck?'checked':'')+' onchange="toggleMetric(\''+o.k+'\',this.checked)"></div>';
    }).join('');
  } else if(key==='chart'){
    var chartOpts=['spend','impressions','clicks','reach','messages','ctr','cpc','cpm','frequency','leads','conversions','roas','profile_visits'];
    var chartLbls={'spend':'💰 Investimento (R$)','impressions':'👁 Impressões','clicks':'🖱 Cliques','reach':'👥 Alcance','messages':'💬 Mensagens','ctr':'📊 CTR (%)','cpc':'💵 CPC (R$)','cpm':'📈 CPM (R$)','frequency':'🔁 Frequência','leads':'🎯 Leads','conversions':'✅ Conversões','roas':'📈 ROAS','profile_visits':'👤 Visitas Perfil'};
    var cur=c.metric||'spend';
    b.innerHTML='<div class="pp-lbl">Métrica a exibir</div>'
      +'<select id="f_metric" class="pp-inp" onchange="saveCfgField(\'metric\')">'
      +chartOpts.map(function(o){return '<option value="'+o+'"'+(o===cur?' selected':'')+'>'+chartLbls[o]+'</option>';}).join('')
      +'</select>'
      +'<div class="pp-lbl" style="margin-top:8px">Cor do gráfico</div>'
      +'<input type="color" id="f_chart_color" class="pp-inp" value="'+(c.chart_color||pal.accent||'#5B8DEF')+'" oninput="saveCfgField(\'chart_color\')" style="height:32px;padding:2px;cursor:pointer">';
  } else if(key==='chart_bar'){
    var barOpts2=['spend','impressions','clicks','reach','messages','ctr','cpc','cpm','frequency','leads','conversions','roas','profile_visits'];
    var barLbls2={'spend':'💰 Investimento (R$)','impressions':'👁 Impressões','clicks':'🖱 Cliques','reach':'👥 Alcance','messages':'💬 Mensagens','ctr':'📊 CTR (%)','cpc':'💵 CPC (R$)','cpm':'📈 CPM (R$)','frequency':'🔁 Frequência','leads':'🎯 Leads','conversions':'✅ Conversões','roas':'📈 ROAS','profile_visits':'👤 Visitas Perfil'};
    var curB=c.bar_metric||'spend';
    var curB2=c.bar_metric2||'';
    b.innerHTML='<div class="pp-lbl">Métrica principal</div>'
      +'<select id="f_bar_metric" class="pp-inp" onchange="saveCfgField(\'bar_metric\')">'
      +barOpts2.map(function(o){return '<option value="'+o+'"'+(o===curB?' selected':'')+'>'+barLbls2[o]+'</option>';}).join('')
      +'</select>'
      +'<div class="pp-lbl" style="margin-top:8px">Comparar com (opcional)</div>'
      +'<select id="f_bar_metric2" class="pp-inp" onchange="saveCfgField(\'bar_metric2\')">'
      +'<option value="">— Nenhuma —</option>'
      +barOpts2.map(function(o){return '<option value="'+o+'"'+(o===curB2?' selected':'')+'>'+barLbls2[o]+'</option>';}).join('')
      +'</select>'
      +'<div class="pp-lbl" style="margin-top:8px">Cor da barra</div>'
      +'<input type="color" id="f_bar_color1" class="pp-inp" value="'+(c.bar_color1||pal.accent||'#5B8DEF')+'" oninput="saveCfgField(\'bar_color1\')" style="height:32px;padding:2px;cursor:pointer">'
      +'<div class="pp-lbl" style="margin-top:8px">Cor da linha comparativa</div>'
      +'<input type="color" id="f_bar_color2" class="pp-inp" value="'+(c.bar_color2||'#2ecc71')+'" oninput="saveCfgField(\'bar_color2\')" style="height:32px;padding:2px;cursor:pointer">';
  } else if(key==='comparative'){
    // Se nunca configurado, todos marcados por padrão
    var cfgHasCompKeys=Object.keys(c).some(function(k){return allComp.some(function(o){return o.k===k;});});
    if(!cfgHasCompKeys){
      allComp.forEach(function(o){bCfg[panelIdx][o.k]=true;});
    }
    var c2=getCfg(panelIdx);
    b.innerHTML='<div class="pp-lbl">Rótulo do período anterior</div>'
      +'<input class="pp-inp" id="f_compPeriod" value="'+esc(c2.compPeriod||'Período Anterior')+'" oninput="saveCfgField(\'compPeriod\')" placeholder="Ex: Semana passada, Mês anterior...">'
      +'<div class="pp-sec">Métricas a exibir</div>'
      +allComp.map(function(o){var ck=c2[o.k]===true||c2[o.k]===1;return '<div class="pp-row"><span>'+o.l+'</span><input type="checkbox" '+(ck?'checked':'')+' onchange="toggleMetric(\''+o.k+'\',this.checked)"></div>';}).join('');
  } else if(key==='message'){
    b.innerHTML='<div class="pp-lbl">Texto da mensagem</div>'
      +'<textarea class="pp-ta" id="f_msg" oninput="saveCfgField(\'msg\')" placeholder="Digite a mensagem...">'+(c.msg!==undefined?c.msg:S.msg)+'</textarea>'
      +'<div style="font-size:10px;color:var(--txt3)">Use Enter para quebrar linha</div>';
  } else if(key==='footer'){
    b.innerHTML='<div class="pp-lbl">Texto esquerda</div>'
      +'<input class="pp-inp" id="f_footerL" value="'+esc(c.footerL||'')+'" oninput="saveCfgField(\'footerL\')" placeholder="Ex: Gerado por J6 Digital">'
      +'<div class="pp-lbl">Texto direita</div>'
      +'<input class="pp-inp" id="f_footerR" value="'+esc(c.footerR||'')+'" oninput="saveCfgField(\'footerR\')" placeholder="Ex: gestorads.j6digital.com.br">';
  }
}

function closePanel(){document.getElementById('pP').classList.remove('open');panelIdx=-1;}

function saveCfgField(field){
  if(panelIdx<0)return;
  if(!bCfg[panelIdx])bCfg[panelIdx]={};
  var el=document.getElementById('f_'+field);
  if(!el)return;
  bCfg[panelIdx][field]=el.tagName==='TEXTAREA'?el.value:el.value;
  render();
  // Auto-save em qualquer mudança de config
  clearTimeout(window._autoSaveTmr);
  var sBtn = document.getElementById('saveTplBtn');
  if(sBtn){sBtn.textContent='⏳...';}
  window._autoSaveTmr=setTimeout(function(){
    saveTemplate(true);
  },800);
}

function toggleMetric(key,checked){
  if(panelIdx<0)return;
  if(!bCfg[panelIdx])bCfg[panelIdx]={};
  bCfg[panelIdx][key]=checked?true:false;
  render();
  // Auto-save após mudança
  clearTimeout(window._autoSaveTmr);
  window._autoSaveTmr=setTimeout(function(){saveTemplate(true);},800);
}

function loadLogo(idx,input){
  if(!input.files||!input.files[0])return;
  var r=new FileReader();
  r.onload=function(e){
    if(!bCfg[idx])bCfg[idx]={};
    bCfg[idx].logo=e.target.result;
    render();
    setTimeout(function(){openPanel(idx,'header');},100);
  };
  r.readAsDataURL(input.files[0]);
}

function removeLogo(idx){
  if(!bCfg[idx])bCfg[idx]={};
  delete bCfg[idx].logo;
  render();
  setTimeout(function(){openPanel(idx,'header');},100);
}

function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

function novoTemplate(){
  if(!confirm('Criar novo template em branco? O template atual será mantido salvo.')){return;}
  // ?new=1 força o editor a abrir em branco (sem carregar o último template)
  var sep = REPORT_ID ? '?report_id=' + REPORT_ID + '&new=1' : '?new=1';
  window.location.href = APP_URL + '/reports/pdf-editor' + sep;
}

async function saveTemplate(silent){
  if(blocks.length===0){alert('Adicione pelo menos um bloco antes de salvar.');return;}
  var nm=document.getElementById('tN').value||'Meu Template';
  // Injeta cor do accent em blocos de gráfico que não têm cor customizada
  var bCfgFinal={};
  blocks.forEach(function(key,i){
    bCfgFinal[i]=Object.assign({},bCfg[i]||{});
    if(key==='chart_bar' && !bCfgFinal[i].bar_color1) bCfgFinal[i].bar_color1=pal.accent||'#5B8DEF';
    if(key==='chart' && !bCfgFinal[i].chart_color) bCfgFinal[i].chart_color=pal.accent||'#5B8DEF';
  });
  var config=JSON.stringify({blocks:blocks,palette:pal,blockConfigs:bCfgFinal});
  console.log('Salvando config:', config.substring(0,200));
  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('id',TPL_ID);fd.append('name',nm);fd.append('config',config);
  try{
    var r=await fetch(APP_URL+'/reports/pdf-template/save',{method:'POST',body:fd,credentials:'include'});
    var d=await r.json();
    if(d.success){
      TPL_ID=d.id;
      var newUrl=APP_URL+'/reports/pdf-editor?tpl='+d.id+(REPORT_ID?'&report_id='+REPORT_ID:'');
      window.history.replaceState(null,'',newUrl);
      var sBtn=document.getElementById('saveTplBtn');
      if(!silent){
        var m=document.getElementById('sMsg');if(m){m.style.display='inline';setTimeout(function(){m.style.display='none';},2500);}
        if(sBtn){sBtn.textContent='✅ Salvo!';setTimeout(function(){sBtn.textContent='💾 Salvar Template';},2000);}
      } else {
        if(sBtn){sBtn.style.opacity='1';sBtn.textContent='✅ Auto-salvo';setTimeout(function(){sBtn.textContent='💾 Salvar Template';},1500);}
      }
    }
    else { console.error('Erro ao salvar:', d.error); if(!silent) alert('Erro: '+(d.error||'')); else { var sBtn=document.getElementById('saveTplBtn'); if(sBtn){sBtn.textContent='❌ Erro ao salvar';setTimeout(function(){sBtn.textContent='💾 Salvar Template';},3000);} } }
  }catch(e){console.error('Erro conexão:',e); var sBtn=document.getElementById('saveTplBtn'); if(sBtn){sBtn.textContent='❌ Sem conexão';setTimeout(function(){sBtn.textContent='💾 Salvar Template';},3000);}}
}

// Mapa de períodos para gerar label de data dinâmico
function pad(n){return n<10?'0'+n:String(n);}
function fmtDate(d){return pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear();}
function fmtDateRange(daysFrom){
  var s=new Date(); s.setDate(s.getDate()+daysFrom);
  return fmtDate(s)+' a '+fmtDate(new Date());
}
function getPeriodLabel(v, cs, ce) {
  if(v==='custom' && cs && ce){
    function fmt(s){var p=s.split('-');return p[2]+'/'+p[1]+'/'+p[0];}
    return fmt(cs)+' a '+fmt(ce);
  }
  var map = {
    'last_7_days':  function(){return fmtDateRange(-7);},
    'last_14_days': function(){return fmtDateRange(-14);},
    'last_30_days': function(){return fmtDateRange(-30);},
    'last_90_days': function(){return fmtDateRange(-90);},
    'this_month':   function(){var d=new Date();return '01/'+pad(d.getMonth()+1)+'/'+d.getFullYear()+' a '+fmtDate(new Date());},
    'last_month':   function(){var d=new Date();d.setDate(1);d.setMonth(d.getMonth()-1);var e=new Date(d.getFullYear(),d.getMonth()+1,0);return fmtDate(d)+' a '+fmtDate(e);},
    'max':          function(){return '(desde o início) a '+fmtDate(new Date());},
  };
  return map[v] ? map[v]() : '';
}
function applyPeriodToHeader(v, cs, ce) {
  var label = getPeriodLabel(v, cs, ce);
  if(!label) return;
  S.period = label;
  // Atualizar TODOS os blocos header (pode ter mais de um)
  blocks.forEach(function(bk, bi) {
    if(bk === 'header') {
      if(!bCfg[bi]) bCfg[bi] = {};
      bCfg[bi].period = label;
    }
  });
  var fp = document.getElementById('f_period');
  if(fp) fp.value = label;
  render();
}
function onPreviewPeriodChange(v) {
  var d = document.getElementById('customPreviewDates');
  if(d) d.style.display = v==='custom' ? 'flex' : 'none';
  if(v !== 'custom') applyPeriodToHeader(v, '', '');
}
function onCustomDateChange() {
  var s=(document.getElementById('previewStart')||{}).value||'';
  var e=(document.getElementById('previewEnd')||{}).value||'';
  if(s && e) applyPeriodToHeader('custom', s, e);
}
function previewPdf(){
  if(!REPORT_ID){alert('Acesse: Relatórios → ícone PDF vermelho → Editor PDF');return;}
  var period = (document.getElementById('previewPeriod')||{}).value || 'last_30_days';
  var url = APP_URL+'/reports/pdf?id='+REPORT_ID+'&tpl='+TPL_ID+'&period='+encodeURIComponent(period);
  if(period==='custom'){
    var s=(document.getElementById('previewStart')||{}).value||'';
    var e=(document.getElementById('previewEnd')||{}).value||'';
    if(!s||!e){alert('Selecione as datas personalizadas');return;}
    url+='&custom_start='+s+'&custom_end='+e;
  }
  window.open(url,'_blank');
}

// Carregar template salvo
function loadTemplate(id){
  if(!id) return;
  window.location.href=APP_URL+'/reports/pdf-editor?tpl='+id+(REPORT_ID?'&report_id='+REPORT_ID:'');
}

// Excluir template atual
async function deleteTemplate(){
  if(!TPL_ID){alert('Nenhum template salvo para excluir.');return;}
  if(!confirm('Excluir este template?'))return;
  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('id',TPL_ID);
  try{
    var r=await fetch(APP_URL+'/reports/pdf-template/delete',{method:'POST',body:fd,credentials:'include'});
    var d=await r.json();
    if(d.success){window.location.href=APP_URL+'/reports/pdf-editor';}
    else alert('Erro: '+(d.error||''));
  }catch(e){alert('Erro de conexão');}
}

// Scale canvas to fit available width
// Canvas is responsive via CSS

// Init
document.getElementById('cA').value=pal.accent||'#5B8DEF';
document.getElementById('cB').value=pal.bg||'#ffffff';
document.getElementById('cT').value=pal.txt||'#1a1a2e';
// Fix .pp2 -> .pp conflict with css
document.querySelectorAll('.pp2').forEach(function(el){el.className='pp';});
// Aplicar período inicial no cabeçalho
var initPeriodVal = (document.getElementById('previewPeriod')||{}).value || 'last_30_days';
applyPeriodToHeader(initPeriodVal, '', '');
render();
</script>
