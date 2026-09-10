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
.pe-wrap{display:grid;grid-template-columns:200px 1fr 210px;height:calc(100vh - 64px);overflow:hidden}
.pe-sidebar{background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.pe-canvas-wrap{overflow-y:auto;overflow-x:hidden;background:#ccc;padding:16px;display:flex;justify-content:center;align-items:flex-start}
.pe-props-panel{background:var(--bg2);border-left:1px solid var(--border);overflow-y:auto;padding:12px;display:none}
.pe-props-panel.open{display:block}
.pe-main{display:grid;grid-template-rows:46px 1fr;overflow:hidden}
.pe-toolbar{background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;padding:0 14px}
.pe-canvas-outer{transform-origin:top center;transition:transform .15s;display:inline-block}
.pe-canvas{background:#fff;width:720px;min-height:860px;box-shadow:0 4px 24px rgba(0,0,0,.25);padding:36px;font-family:Arial,sans-serif;overflow:visible}
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
.kg{display:grid;gap:7px}
.kc{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:6px;padding:10px 12px}
.kl{font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.kv{font-size:16px;font-weight:700;color:#1a1a2e}
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
    <?php $allB=['header'=>['🏷️','Cabeçalho'],'kpis'=>['📊','KPIs'],'kpis2'=>['📈','KPIs Secundários'],'chart'=>['📉','Gráfico Linha'],'chart_bar'=>['📊','Gráfico Barras'],'comparative'=>['⚖️','Comparativo'],'message'=>['💬','Mensagem'],'footer'=>['🔖','Rodapé'],'signature'=>['✍️','Assinatura'],'divider'=>['➖','Divisor'],'spacer'=>['⬜','Espaço']];
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
    <button class="btn btn-primary btn-sm" onclick="saveTemplate()">💾 Salvar Template</button>
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
    <div id="pCOuter" class="pe-canvas-outer">
      <div class="pe-canvas" id="pC"><div id="bC"></div></div>
    </div>
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
  footerL:'',footerR:''
};

var allKpis=[
  {k:'spend',l:'💰 Investimento'},{k:'impr',l:'👁 Impressões'},{k:'clicks',l:'🖱 Cliques'},
  {k:'ctr',l:'📊 CTR'},{k:'cpc',l:'💵 CPC'},{k:'cpm',l:'📈 CPM'},
  {k:'reach',l:'👥 Alcance'},{k:'freq',l:'🔁 Frequência'},{k:'msgs',l:'💬 Mensagens'},
  {k:'leads',l:'🎯 Leads'},{k:'roas',l:'📈 ROAS'},{k:'conv',l:'✅ Conversões'},
  {k:'cpl',l:'💵 Custo/Lead'},{k:'cmsg',l:'💵 Custo/Msg'}
];
var allKpis2=[
  {k:'msgs',l:'💬 Mensagens'},{k:'leads',l:'🎯 Leads'},{k:'roas',l:'📈 ROAS'},
  {k:'conv',l:'✅ Conversões'},{k:'cpl',l:'💵 Custo/Lead'},{k:'cmsg',l:'💵 Custo/Msg'}
];
var allComp=[
  {k:'spend',l:'Investimento',c:'spend',p:'pSpend',hi:false},
  {k:'ctr',l:'CTR',c:'ctr',p:'pCtr',hi:true},{k:'cpc',l:'CPC',c:'cpc',p:'pCpc',hi:false},
  {k:'cpm',l:'CPM',c:'cpm',p:'pCpm',hi:false},{k:'impr',l:'Impressões',c:'impr',p:'pImpr',hi:true},
  {k:'clicks',l:'Cliques',c:'clicks',p:'pClicks',hi:true},{k:'reach',l:'Alcance',c:'reach',p:'pReach',hi:true},
  {k:'freq',l:'Frequência',c:'freq',p:'pFreq',hi:false},{k:'msgs',l:'Mensagens',c:'msgs',p:'pMsgs',hi:true},
  {k:'leads',l:'Leads',c:'leads',p:'pLeads',hi:true},{k:'roas',l:'ROAS',c:'roas',p:'pRoas',hi:true},
  {k:'conv',l:'Conversões',c:'conv',p:'pConv',hi:true},{k:'cpl',l:'Custo/Lead',c:'cpl',p:'pCpl',hi:false},
  {k:'cmsg',l:'Custo/Msg',c:'cmsg',p:'pCmsg',hi:false}
];

function kc(l,v){return '<div class="kc"><div class="kl">'+l+'</div><div class="kv">'+v+'</div></div>';}
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
    return '<div><div class="ps">Métricas Principais</div><div class="kg" style="grid-template-columns:repeat('+cols+',1fr)">'+opts.map(function(o){return kc(o.l,S[o.k]||'—');}).join('')+'</div></div>';
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
    return '<div><div class="ps">Resultados</div><div class="kg" style="grid-template-columns:repeat('+cols2+',1fr)">'+opts2.map(function(o){return kc(o.l,S[o.k]);}).join('')+'</div></div>';
  }
  if(key==='chart'){
    var id='ch'+Date.now()+idx;
    setTimeout(function(){var el=document.getElementById(id);if(!el)return;new Chart(el,{type:'line',data:{labels:['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'],datasets:[{label:'Investimento',data:[12,18,15,22,19,14,17],borderColor:ap,backgroundColor:ap+'22',tension:.4,fill:true,pointRadius:4}]},options:{responsive:true,plugins:{legend:{display:true}},scales:{y:{beginAtZero:true}}}});},150);
    return '<div class="cb"><div class="ps">Evolução do Investimento</div><canvas id="'+id+'" height="100"></canvas></div>';
  }
  if(key==='chart_bar'){
    var id2='cb'+Date.now()+idx;
    setTimeout(function(){var el=document.getElementById(id2);if(!el)return;new Chart(el,{type:'bar',data:{labels:['Impressões','Cliques','Alcance','Msgs'],datasets:[{label:'Atual',data:[21933,312,7795,12],backgroundColor:ap+'BB'},{label:'Anterior',data:[18500,265,6900,9],backgroundColor:ap+'33'}]},options:{responsive:true}});},150);
    return '<div class="cb"><div class="ps">Comparativo Visual</div><canvas id="'+id2+'" height="100"></canvas></div>';
  }
  if(key==='comparative'){
    var period=c.compPeriod||'Período Anterior';
    var opts3=allComp.filter(function(o){return c[o.k]!==false;});
    if(!opts3.length)opts3=allComp.slice(0,6);
    return '<div><div class="ps">Comparativo — '+period+'</div><table class="pt"><thead><tr><th>Métrica</th><th>Atual</th><th>Anterior</th><th>Variação</th></tr></thead><tbody>'+opts3.map(function(o){return tr(o.l,S[o.c],S[o.p],o.hi);}).join('')+'</tbody></table></div>';
  }
  if(key==='message'){
    var msg=c.msg!==undefined?c.msg:S.msg;
    return '<div><div class="ps">Mensagem do Relatório</div><div class="pm">'+msg.replace(/\n/g,'<br>')+'</div></div>';
  }
  if(key==='footer'){
    var fl=c.footerL||(new Date().toLocaleDateString('pt-BR')+' — '+sn);
    var fr=c.footerR||'seudominio.com.br';
    return '<div class="pf"><span>'+fl+'</span><span>'+fr+'</span></div>';
  }
  if(key==='signature')return '<div class="psi"><div style="font-size:11px;color:#888">Aprovado por</div><div class="psl"></div><div class="psn">'+cl+'</div><div style="font-size:10px;color:#aaa;margin-top:2px">'+pr+'</div></div>';
  if(key==='divider')return '<hr class="pdv">';
  if(key==='spacer')return '<div class="psp"></div>';
  return '';
}

function addBlock(key){blocks.push(key);bCfg[blocks.length-1]={};render();}
function moveBlock(i,d){if(i+d<0||i+d>=blocks.length)return;var t=blocks[i];blocks[i]=blocks[i+d];blocks[i+d]=t;var tc=bCfg[i];bCfg[i]=bCfg[i+d]||{};bCfg[i+d]=tc||{};render();}
function removeBlock(i){blocks.splice(i,1);delete bCfg[i];// reindex
var nb={};blocks.forEach(function(_,j){nb[j]=bCfg[j+1]||{};});bCfg=nb;render();}

function render(){
  var ap=pal.accent||'#5B8DEF';
  document.getElementById('pC').style.setProperty('--ap',ap);
  document.getElementById('pC').style.background=pal.bg||'#fff';
  document.getElementById('pC').style.color=pal.txt||'#1a1a2e';
  var h='';
  blocks.forEach(function(key,i){
    var hasCfg=['header','kpis','kpis2','comparative','message','footer'].indexOf(key)>=0;
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
  setTimeout(scaleCanvas, 50);
  Sortable.create(document.getElementById('bC'),{animation:150,handle:'.dg',ghostClass:'sg',onEnd:function(e){var m=blocks.splice(e.oldIndex,1)[0];blocks.splice(e.newIndex,0,m);var mc=bCfg[e.oldIndex]||{};// shift configs
render();}});
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

  var labels={'header':'🏷️ Cabeçalho','kpis':'📊 KPIs','kpis2':'📈 KPIs Secundários','comparative':'⚖️ Comparativo','message':'💬 Mensagem','footer':'🔖 Rodapé'};
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
  } else if(key==='comparative'){
    b.innerHTML='<div class="pp-lbl">Rótulo do período anterior</div>'
      +'<input class="pp-inp" id="f_compPeriod" value="'+esc(c.compPeriod||'Período Anterior')+'" oninput="saveCfgField(\'compPeriod\')" placeholder="Ex: Semana passada, Mês anterior...">'
      +'<div class="pp-sec">Métricas a exibir</div>'
      +allComp.map(function(o){var ck=c[o.k]!==false;return '<div class="pp-row"><span>'+o.l+'</span><input type="checkbox" '+(ck?'checked':'')+' onchange="toggleMetric(\''+o.k+'\',this.checked)"></div>';}).join('');
  } else if(key==='message'){
    b.innerHTML='<div class="pp-lbl">Texto da mensagem</div>'
      +'<textarea class="pp-ta" id="f_msg" oninput="saveCfgField(\'msg\')" placeholder="Digite a mensagem...">'+(c.msg!==undefined?c.msg:S.msg)+'</textarea>'
      +'<div style="font-size:10px;color:var(--txt3)">Use Enter para quebrar linha</div>';
  } else if(key==='footer'){
    b.innerHTML='<div class="pp-lbl">Texto esquerda</div>'
      +'<input class="pp-inp" id="f_footerL" value="'+esc(c.footerL||'')+'" oninput="saveCfgField(\'footerL\')" placeholder="Ex: Gerado por EMPRESA">'
      +'<div class="pp-lbl">Texto direita</div>'
      +'<input class="pp-inp" id="f_footerR" value="'+esc(c.footerR||'')+'" oninput="saveCfgField(\'footerR\')" placeholder="Ex: seudominio.com.br">';
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
}

function toggleMetric(key,checked){
  if(panelIdx<0)return;
  if(!bCfg[panelIdx])bCfg[panelIdx]={};
  bCfg[panelIdx][key]=checked?true:false;
  render();
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

async function saveTemplate(){
  if(blocks.length===0){alert('Adicione pelo menos um bloco antes de salvar.');return;}
  var nm=document.getElementById('tN').value||'Meu Template';
  var config=JSON.stringify({blocks:blocks,palette:pal,blockConfigs:bCfg});
  console.log('Salvando config:', config.substring(0,200));
  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('id',TPL_ID);fd.append('name',nm);fd.append('config',config);
  try{
    var r=await fetch(APP_URL+'/reports/pdf-template/save',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){
      TPL_ID=d.id;
      // Atualizar URL para preservar o template na próxima recarga
      var newUrl=APP_URL+'/reports/pdf-editor?tpl='+d.id+(REPORT_ID?'&report_id='+REPORT_ID:'');
      window.history.replaceState(null,'',newUrl);
      var m=document.getElementById('sMsg');m.style.display='inline';
      setTimeout(function(){m.style.display='none';},2500);
    }
    else alert('Erro: '+(d.error||''));
  }catch(e){alert('Erro de conexão');}
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
    var r=await fetch(APP_URL+'/reports/pdf-template/delete',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){window.location.href=APP_URL+'/reports/pdf-editor';}
    else alert('Erro: '+(d.error||''));
  }catch(e){alert('Erro de conexão');}
}

// Scale canvas to fit available width
function scaleCanvas() {
  var wrap = document.querySelector('.pe-canvas-wrap');
  var outer = document.getElementById('pCOuter');
  var canvas = document.getElementById('pC');
  if(!wrap || !canvas || !outer) return;
  var availW = wrap.clientWidth - 32;
  var canvasW = 720;
  var scale = Math.min(1, availW / canvasW);
  outer.style.transform = 'scale('+scale+')';
  outer.style.transformOrigin = 'top center';
  // Adjust outer height so it doesn't leave gap
  outer.style.height = Math.round(canvas.offsetHeight * scale) + 'px';
  outer.style.width = Math.round(canvasW * scale) + 'px';
}
window.addEventListener('resize', scaleCanvas);
setTimeout(scaleCanvas, 200);

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
