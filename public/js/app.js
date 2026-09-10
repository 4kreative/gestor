/* GestorPro – JavaScript Principal */

// ---- Tema: aplica ANTES do DOMContentLoaded para evitar flash ----
(function(){
  function getCookie(n){var m=document.cookie.match('(^|;)\s*'+n+'\s*=\s*([^;]+)');return m?m.pop():null;}
  var theme = getCookie('gestorpro_theme') || localStorage.getItem('gestorpro_theme') || 'dark';
  if (theme === 'light') document.documentElement.classList.add('pre-light');
})();

document.addEventListener('DOMContentLoaded', function () {

  // ---- Tema Claro/Escuro ----
  var THEME_KEY = 'gestorpro_theme';
  var themeBtn  = document.getElementById('themeToggleBtn');

  function getCookie(n){var m=document.cookie.match('(^|;)\s*'+n+'\s*=\s*([^;]+)');return m?m.pop():null;}
  function setCookie(n,v){document.cookie=n+'='+v+';path=/;max-age=31536000;SameSite=Lax';}

  function applyTheme(t) {
    if (t === 'light') {
      document.documentElement.classList.add('light-mode');
      document.documentElement.classList.remove('pre-light');
      document.body.classList.add('light-mode');
      if (themeBtn) themeBtn.textContent = '🌙';
      if (themeBtn) themeBtn.title = 'Mudar para modo escuro';
    } else {
      document.documentElement.classList.remove('light-mode');
      document.documentElement.classList.remove('pre-light');
      document.body.classList.remove('light-mode');
      if (themeBtn) themeBtn.textContent = '☀️';
      if (themeBtn) themeBtn.title = 'Mudar para modo claro';
    }
  }

  var saved = getCookie(THEME_KEY) || localStorage.getItem(THEME_KEY) || 'dark';
  applyTheme(saved);

  if (themeBtn) {
    themeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var curr = document.body.classList.contains('light-mode') ? 'light' : 'dark';
      var next = curr === 'dark' ? 'light' : 'dark';
      setCookie(THEME_KEY, next);
      localStorage.setItem(THEME_KEY, next);
      applyTheme(next);
    });
  }

  // ---- Sidebar mobile toggle + overlay ----
  var toggle  = document.getElementById('sidebarToggle');
  var sidebar = document.querySelector('.sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  function openSidebar(){if(sidebar)sidebar.classList.add('open');if(overlay)overlay.classList.add('open');document.body.style.overflow='hidden';}
  function closeSidebar(){if(sidebar)sidebar.classList.remove('open');if(overlay)overlay.classList.remove('open');document.body.style.overflow='';}
  function toggleSidebar(){if(sidebar&&sidebar.classList.contains('open'))closeSidebar();else openSidebar();}
  window.toggleSidebar=toggleSidebar;window.closeSidebar=closeSidebar;
  if(toggle){toggle.addEventListener('click',function(e){e.stopPropagation();toggleSidebar();});}
  if(overlay){overlay.addEventListener('click',closeSidebar);}
  if(sidebar){sidebar.querySelectorAll('.nav-item').forEach(function(a){a.addEventListener('click',function(){if(window.innerWidth<=768)closeSidebar();});});}

  // ---- Auto-dismiss alerts ----
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function(el) {
    setTimeout(function() {
      el.style.opacity = '0'; el.style.transition = 'opacity .5s';
      setTimeout(function(){ el.remove(); }, 500);
    }, parseInt(el.dataset.autoDismiss) || 4000);
  });

  // ---- Confirm delete ----
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm(el.dataset.confirm || 'Confirmar?')) e.preventDefault();
    });
  });

  // ---- OTP input navigation ----
  var otpInputs = document.querySelectorAll('.otp-input');
  otpInputs.forEach(function(input, i) {
    input.addEventListener('input', function() { if (input.value && otpInputs[i+1]) otpInputs[i+1].focus(); });
    input.addEventListener('keydown', function(e) { if (e.key === 'Backspace' && !input.value && otpInputs[i-1]) otpInputs[i-1].focus(); });
  });

  // ---- Progress bars ----
  document.querySelectorAll('.metric-bar[data-width]').forEach(function(bar) {
    setTimeout(function(){ bar.style.width = bar.dataset.width + '%'; }, 200);
  });

  // ---- WP polling ----
  document.querySelectorAll('[data-wp-poll]').forEach(function(el) {
    var id = el.dataset.wpPoll; if (!id) return;
    var iv = setInterval(function() {
      fetch('/whatsapp/status?id='+id).then(function(r){return r.json();}).then(function(d){
        var dot = el.querySelector('.wp-status-dot');
        var txt = el.querySelector('.wp-status-text');
        if (dot) dot.className = 'wp-status-dot '+d.status;
        if (txt) txt.textContent = d.status==='connected' ? '✓ '+(d.phone||'Conectado') : d.status;
        if (d.status==='connected') clearInterval(iv);
      }).catch(function(){});
    }, 5000);
  });

  // ---- Var Picker ----
  initVarPickers();

  // ---- Chart ----
  initMetricsChart();

  // ---- Topbar dropdown ----
  (function(){
    var btn  = document.getElementById('topUserBtn');
    var menu = document.getElementById('topUserMenu');
    if (!btn||!menu) return;
    btn.addEventListener('click', function(e){ e.stopPropagation(); menu.style.display = menu.style.display==='none'?'block':'none'; });
    document.addEventListener('click', function(){ menu.style.display='none'; });
    menu.addEventListener('click', function(e){ e.stopPropagation(); });
  })();

  // ---- Sidebar user dropdown ----
  (function(){
    var btn  = document.getElementById('sidebarUserBtn');
    var menu = document.getElementById('userMenu');
    var arr  = document.getElementById('sidebarUserArrow');
    if (!btn||!menu) return;
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      var open = menu.style.display==='none'||!menu.style.display;
      menu.style.display = open ? 'block' : 'none';
      if (arr) arr.textContent = open ? 'expand_less' : 'expand_more';
    });
    document.addEventListener('click', function(){ menu.style.display='none'; if(arr) arr.textContent='expand_more'; });
    menu.addEventListener('click', function(e){ e.stopPropagation(); });
  })();

});

// ============================================================
// VARIÁVEIS PICKER — todas as métricas do sistema
// ============================================================
var VAR_CATEGORIES = {
  'Gerais': [
    {label:'Nome do cliente', tag:'{nome_cliente}', color:'blue'},
    {label:'Período', tag:'{periodo}', color:'blue'},
    {label:'Mês atual', tag:'{mes_atual}', color:'blue'},
    {label:'Mês anterior', tag:'{mes_anterior}', color:'blue'},
    {label:'Ano atual', tag:'{ano}', color:'blue'},
    {label:'Hoje', tag:'{hoje}', color:'blue'},
    {label:'Ontem', tag:'{yesterday}', color:'blue'},
    {label:'Saudação', tag:'{greeting}', color:'blue'},
    {label:'Campanha', tag:'{campanha}', color:'blue'},
    {label:'Conta de anúncio', tag:'{conta_anuncio}', color:'blue'},
    {label:'Observações', tag:'{observacoes}', color:'blue'},
    {label:'Total campanhas ativas', tag:'{total_camp_ativa}', color:'blue'},
    {label:'Limite de gasto', tag:'{limite_ca}', color:'blue'},
    {label:'Objetivo da campanha', tag:'{objective}', color:'blue'},
    {label:'Orçamento diário', tag:'{daily_budget}', color:'blue'},
    {label:'Saldo da conta', tag:'{saldo}', color:'blue'},
  ],
  'Cliques e Impressões': [
    {label:'Investimento', tag:'{investimento}', color:'orange'},
    {label:'Alcance', tag:'{alcance}', color:'orange'},
    {label:'Impressões', tag:'{impressoes}', color:'orange'},
    {label:'Cliques no link', tag:'{cliques}', color:'orange'},
    {label:'Cliques de saída', tag:'{click_saida}', color:'orange'},
    {label:'Clique Todos', tag:'{clicks_all}', color:'orange'},
    {label:'CTR', tag:'{ctr}', color:'orange'},
    {label:'CTR Todos', tag:'{ctr_all}', color:'orange'},
    {label:'CPM', tag:'{cpm}', color:'orange'},
    {label:'CPC', tag:'{cpc}', color:'orange'},
    {label:'Frequência', tag:'{frequencia}', color:'orange'},
    {label:'Pesquisa', tag:'{search}', color:'orange'},
    {label:'Taxa de entrada na página', tag:'{cr}', color:'orange'},
    {label:'Visitas ao perfil', tag:'{profile_visit}', color:'orange'},
    {label:'Visualizações de página', tag:'{pageview}', color:'orange'},
    {label:'Incrementabilidade', tag:'{increm}', color:'orange'},
  ],
  'Conversões': [
    {label:'Conversões', tag:'{conversoes}', color:'green'},
    {label:'Leads', tag:'{leads}', color:'green'},
    {label:'Lead da Meta', tag:'{leads_meta}', color:'green'},
    {label:'Lead no Facebook', tag:'{lead_facebook}', color:'green'},
    {label:'ROAS', tag:'{roas}', color:'green'},
    {label:'Resultados', tag:'{results}', color:'green'},
    {label:'Vendas (Compras)', tag:'{vend}', color:'green'},
    {label:'Receita', tag:'{receita}', color:'green'},
    {label:'Todos os leads', tag:'{all_leads}', color:'green'},
    {label:'Compra no Aplicativo', tag:'{app_purchase}', color:'green'},
    {label:'Valor de Vendas App', tag:'{app_purchase_value}', color:'green'},
    {label:'Conversão de Lead Pixel', tag:'{conversion_lead_pixel}', color:'green'},
    {label:'Conversão Pixel Custom', tag:'{conv_pixel_custom}', color:'green'},
    {label:'Conversão Personalizada', tag:'{conversion_custom}', color:'green'},
    {label:'Conversas Iniciadas', tag:'{msg}', color:'green'},
    {label:'Novas mensagens', tag:'{msg_new}', color:'green'},
    {label:'Engajamento com post', tag:'{engajamento}', color:'green'},
    {label:'Faturado', tag:'{fat}', color:'green'},
    {label:'Início de Checkout', tag:'{inicheckout}', color:'green'},
    {label:'Instalação App Mobile', tag:'{app_install}', color:'green'},
    {label:'Download', tag:'{download}', color:'green'},
    {label:'Registros Concluídos', tag:'{complete_registration}', color:'green'},
    {label:'Total de mensagens', tag:'{msg_all}', color:'green'},
    {label:'Ligações 20seg', tag:'{call_20_seconds}', color:'green'},
    {label:'Ligações 60seg', tag:'{call_60_seconds}', color:'green'},
    {label:'Ligações feitas', tag:'{click_to_call}', color:'green'},
  ],
  'Custos': [
    {label:'Custo por Lead (CPL)', tag:'{cpl}', color:'orange'},
    {label:'Custo por Compra (CPV)', tag:'{cpv}', color:'orange'},
    {label:'Custo por Resultado', tag:'{results_cost}', color:'orange'},
    {label:'Custo por Conversão', tag:'{conversion_cost}', color:'orange'},
    {label:'Custo por Lead Meta', tag:'{leads_meta_cost}', color:'orange'},
    {label:'Custo por Clique (CPC)', tag:'{cpc}', color:'orange'},
    {label:'Custo por Mensagem', tag:'{cmsg}', color:'orange'},
    {label:'Custo por Engajamento', tag:'{engajamento_cost}', color:'orange'},
    {label:'Custo por Registro', tag:'{complete_registration_cost}', color:'orange'},
    {label:'Custo por Download', tag:'{download_cost}', color:'orange'},
    {label:'Custo por Checkout', tag:'{cpcheck}', color:'orange'},
    {label:'Custo por Lead Pixel', tag:'{conversion_lead_pixel_cost}', color:'orange'},
    {label:'Custo por Lead Facebook', tag:'{lead_facebook_cost}', color:'orange'},
    {label:'Custo por Todos Leads', tag:'{all_leads_cost}', color:'orange'},
    {label:'Custo por Novas Msg', tag:'{msg_new_cost}', color:'orange'},
    {label:'Custo por Ligação 20s', tag:'{call_20_seconds_cost}', color:'orange'},
    {label:'Custo por Ligação 60s', tag:'{call_60_seconds_cost}', color:'orange'},
    {label:'Custo por Ligação feita', tag:'{click_to_call_cost}', color:'orange'},
    {label:'Custo por App Install', tag:'{app_install_cost}', color:'orange'},
    {label:'Custo por Compra App', tag:'{app_purchase_cost}', color:'orange'},
    {label:'Custo por Visita Perfil', tag:'{profile_visit_cost}', color:'orange'},
    {label:'Custo por Pageview', tag:'{cpvp}', color:'orange'},
    {label:'Custo/Mil alcançados', tag:'{cpma}', color:'orange'},
    {label:'Investimento total', tag:'{inv}', color:'orange'},
    {label:'Valor usado total', tag:'{total_spend}', color:'orange'},
    {label:'Ticket Médio', tag:'{tm}', color:'orange'},
    {label:'Taxa Conv. Página Venda', tag:'{conv_pg_vendas}', color:'orange'},
    {label:'Taxa Conv. Funil Venda', tag:'{conv_funnel}', color:'orange'},
    {label:'Taxa Conv. Checkout', tag:'{conv_checkout}', color:'orange'},
    {label:'Taxa Conv. Lead Click', tag:'{conv_lead_click}', color:'orange'},
    {label:'Taxa Conv. Msg Click', tag:'{conv_msg_click}', color:'orange'},
    {label:'Checkout Rate', tag:'{checkout_rate}', color:'orange'},
  ],
  'Engajamento': [
    {label:'Comentários', tag:'{comment}', color:'pink'},
    {label:'Likes no anúncio', tag:'{post_reaction}', color:'pink'},
    {label:'Salvamentos', tag:'{post_save}', color:'pink'},
    {label:'Engajamento c/ página', tag:'{page_engagement}', color:'pink'},
  ],
  'Vídeo': [
    {label:'Assistiu 25%', tag:'{view_25}', color:'pink'},
    {label:'Assistiu 50%', tag:'{view_50}', color:'pink'},
    {label:'Assistiu 75%', tag:'{view_75}', color:'pink'},
    {label:'Assistiu 95%', tag:'{view_95}', color:'pink'},
    {label:'Assistiu 100%', tag:'{view_100}', color:'pink'},
    {label:'Taxa visualização 100%', tag:'{vview_p}', color:'pink'},
    {label:'Tempo assistido Médio', tag:'{v_avg}', color:'pink'},
    {label:'Thruplay', tag:'{thruplay}', color:'pink'},
    {label:'Video Play', tag:'{vplay}', color:'pink'},
    {label:'Video View', tag:'{vview}', color:'pink'},
  ],
  'Criativos': [
    {label:'Ranking TOP 1 Criativo', tag:'{top_1_creatives_ranking}', color:'pink'},
    {label:'Ranking TOP 3 Criativos', tag:'{top_3_creatives_ranking}', color:'pink'},
    {label:'Ranking TOP 5 Criativos', tag:'{top_5_creatives_ranking}', color:'pink'},
    {label:'Ranking Todos Criativos', tag:'{all_creatives_ranking}', color:'pink'},
    {label:'Lista nomes criativos', tag:'{all_creatives_simple}', color:'pink'},
  ],
};

function initVarPickers() {
  document.querySelectorAll('[data-var-picker]').forEach(function(btn) {
    var targetId = btn.dataset.varPicker;
    var panelId  = btn.dataset.varPanel;
    var panel    = document.getElementById(panelId);
    if (!panel) return;

    var searchInput = panel.querySelector('.var-picker-search input');
    var catsWrap    = panel.querySelector('.var-picker-cats');
    var listWrap    = panel.querySelector('.var-picker-list');
    var activeCat   = 'Todas';

    // Build categories
    catsWrap.innerHTML = '';
    var allBtn = document.createElement('button');
    allBtn.className = 'var-cat-btn active'; allBtn.type='button'; allBtn.textContent='Todas';
    allBtn.onclick = function(){ activeCat='Todas'; renderVars(''); allBtn.parentNode.querySelectorAll('.var-cat-btn').forEach(function(b){b.classList.remove('active');}); allBtn.classList.add('active'); };
    catsWrap.appendChild(allBtn);
    Object.keys(VAR_CATEGORIES).forEach(function(cat) {
      var cb = document.createElement('button');
      cb.className='var-cat-btn'; cb.type='button'; cb.textContent=cat;
      cb.onclick = function(){
        activeCat=cat; renderVars(searchInput?searchInput.value:'');
        panel.querySelectorAll('.var-cat-btn').forEach(function(b){b.classList.remove('active');}); cb.classList.add('active');
      };
      catsWrap.appendChild(cb);
    });

    function renderVars(q) {
      listWrap.innerHTML = '';
      var cats = activeCat==='Todas' ? Object.keys(VAR_CATEGORIES) : [activeCat];
      var found = 0;
      cats.forEach(function(cat) {
        var items = VAR_CATEGORIES[cat].filter(function(v){
          return !q || v.label.toLowerCase().includes(q.toLowerCase()) || v.tag.toLowerCase().includes(q.toLowerCase());
        });
        if (!items.length) return;
        var title = document.createElement('span');
        title.className = 'var-section-title'; title.textContent = cat;
        listWrap.appendChild(title);
        var wrap = document.createElement('div');
        items.forEach(function(v) {
          var tag = document.createElement('span');
          tag.className = 'var-tag'+(v.color&&v.color!='blue' ? ' '+v.color : '');
          tag.title = v.label;
          tag.textContent = v.tag;
          tag.onclick = function(){
            var ta = document.getElementById(targetId);
            if (!ta) return;
            var start = ta.selectionStart, end = ta.selectionEnd;
            ta.value = ta.value.substring(0,start) + v.tag + ta.value.substring(end);
            ta.focus(); ta.selectionStart = ta.selectionEnd = start + v.tag.length;
            panel.classList.remove('open'); panel.style.display='none';
          };
          wrap.appendChild(tag); found++;
        });
        listWrap.appendChild(wrap);
      });
      if (!found) { var em=document.createElement('div'); em.className='var-empty'; em.textContent='Nenhuma variável encontrada'; listWrap.appendChild(em); }
    }

    if (searchInput) searchInput.addEventListener('input', function(){ renderVars(this.value); });
    renderVars('');

    // Toggle panel
    btn.addEventListener('click', function(e) {
      e.stopPropagation(); e.preventDefault();
      var isOpen = panel.style.display==='flex';
      document.querySelectorAll('.var-picker-panel').forEach(function(p){ p.style.display='none'; });
      if (!isOpen) { panel.style.display='flex'; if(searchInput){searchInput.value='';searchInput.focus();} renderVars(''); }
    });
    document.addEventListener('click', function(e){
      if (!panel.contains(e.target) && e.target!==btn) panel.style.display='none';
    });
    panel.addEventListener('click', function(e){ e.stopPropagation(); });
  });
}

function initMetricsChart() {
  var canvas = document.getElementById('metricsChart');
  if (!canvas || typeof Chart==='undefined') return;
  var accountId = canvas.dataset.accountId;
  if (!accountId) return;
  var start = canvas.dataset.start || new Date(Date.now()-29*86400000).toISOString().slice(0,10);
  var end   = canvas.dataset.end   || new Date().toISOString().slice(0,10);
  fetch('/api/reports/data?account_id='+accountId+'&start='+start+'&end='+end)
    .then(function(r){return r.json();})
    .then(function(d){
      if (!d.data||!d.data.length) return;
      new Chart(canvas,{
        type:'bar',
        data:{labels:d.data.map(function(r){return r.date;}),datasets:[
          {label:'Investimento (R$)',data:d.data.map(function(r){return parseFloat(r.spend);}),backgroundColor:'rgba(0,120,255,.6)',borderColor:'#0078FF',borderWidth:1,yAxisID:'y'},
          {label:'Cliques',data:d.data.map(function(r){return parseInt(r.clicks);}),type:'line',borderColor:'#27ae60',backgroundColor:'transparent',borderWidth:2,pointRadius:3,yAxisID:'y1'},
        ]},
        options:{responsive:true,interaction:{mode:'index',intersect:false},scales:{
          x:{ticks:{color:'#888',font:{size:11}},grid:{color:'rgba(128,128,128,.15)'}},
          y:{ticks:{color:'#aaa',callback:function(v){return'R$'+v.toFixed(0);}},grid:{color:'rgba(128,128,128,.15)'},position:'left'},
          y1:{ticks:{color:'#27ae60'},grid:{drawOnChartArea:false},position:'right'},
        },plugins:{legend:{labels:{color:'#aaa',font:{size:12}}}}}
      });
    }).catch(function(){});
}

function showToast(msg,type){
  type=type||'success';
  var colors={success:'#27ae60',error:'#c1272d',info:'#3498db',warn:'#f39c12'};
  var t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:20px;right:20px;background:'+colors[type]+';color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.4);max-width:320px;font-family:Poppins,sans-serif';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .4s';setTimeout(function(){t.remove();},400);},3500);
}

// ── Salva/restaura scroll da sidebar entre páginas ────────────────────
(function() {
  var nav = document.querySelector('.sidebar-nav');
  if (!nav) return;

  // Restaura posição salva
  var saved = sessionStorage.getItem('sidebarScroll');
  if (saved) nav.scrollTop = parseInt(saved, 10);

  // Salva ao clicar em link do nav
  nav.addEventListener('click', function(e) {
    var a = e.target.closest('a.nav-item');
    if (a) sessionStorage.setItem('sidebarScroll', nav.scrollTop);
  });

  // Item ativo visível sem pular ao topo
  var active = nav.querySelector('.nav-item.active');
  if (active && !saved) {
    active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
  }
})();

// ════════════════════════════════════════════════════════════
// SWIPE para abrir/fechar sidebar (mobile)
// ════════════════════════════════════════════════════════════
(function(){
  var startX=0,startY=0,moved=false;
  document.addEventListener('touchstart',function(e){startX=e.touches[0].clientX;startY=e.touches[0].clientY;moved=false;},{passive:true});
  document.addEventListener('touchmove',function(e){if(!moved&&Math.abs(e.touches[0].clientX-startX)>10&&Math.abs(e.touches[0].clientY-startY)<50)moved=true;},{passive:true});
  document.addEventListener('touchend',function(e){
    if(!moved)return;
    var dx=e.changedTouches[0].clientX-startX;
    var sb=document.querySelector('.sidebar');
    if(!sb)return;
    if(!sb.classList.contains('open')&&dx>60&&startX<30&&window.openSidebar)window.openSidebar();
    if(sb.classList.contains('open')&&dx<-60&&window.closeSidebar)window.closeSidebar();
    moved=false;
  },{passive:true});
})();

// ════════════════════════════════════════════════════════════
// DASHBOARD WIDGETS — cada db-card.db-widget é arrastável
// ════════════════════════════════════════════════════════════
(function(){
  var STORAGE_KEY = 'db_widgets_v3';
  var container   = null;
  var dragging    = null;

  var CATALOG = [
    {id:'kpis',               icon:'📊', name:'KPIs Principais',         desc:'Investimento, relatórios, IA, clientes'},
    {id:'kpis-int',           icon:'🔗', name:'KPIs Integrações',         desc:'Disparos, webhooks e erros'},
    {id:'alertas-campanhas',  icon:'🚨', name:'Campanhas com Problema',   desc:'CTR baixo, freq alta, CPC alto'},
    {id:'budget-mes',         icon:'💰', name:'Budget do Mês',            desc:'Gasto vs meta por conta'},
    {id:'semana-semana',      icon:'📈', name:'Semana a Semana',          desc:'Comparativo vs semana anterior'},
    {id:'grafico-gasto',      icon:'📈', name:'Gasto por Plataforma',     desc:'Meta vs Google — últimos 7 dias'},
    {id:'distribuicao-gasto', icon:'📊', name:'Distribuição de Gasto',    desc:'% por plataforma'},
    {id:'ranking-campanhas',  icon:'🏆', name:'Ranking de Campanhas',     desc:'Top campanhas por resultado'},
    {id:'score-saude',        icon:'🎯', name:'Saúde dos Clientes',       desc:'Score 0–100 automático'},
    {id:'projecao-mes',       icon:'💸', name:'Projeção Fim do Mês',      desc:'Ritmo atual de gasto'},
    {id:'disparos',           icon:'📤', name:'Saldo de Envios',          desc:'Feed de relatórios e alertas'},
    {id:'rt-metrics',         icon:'⚡', name:'Métricas em Tempo Real',   desc:'Dados ao vivo dos relatórios'},
    {id:'integracoes',        icon:'🔗', name:'Últimos Disparos',         desc:'Webhooks recentes'},
    {id:'agenda-relatorios',  icon:'📅', name:'Próximos Relatórios',      desc:'Agenda de envios programados'},
    {id:'diagnostico',        icon:'🔍', name:'Diagnóstico do Sistema',   desc:'Saúde do sistema'},
    {id:'conexoes',           icon:'🌐', name:'Conexões Ativas',          desc:'WhatsApp, Meta, IA, Google'},
  ];

  function loadState(){try{return JSON.parse(localStorage.getItem(STORAGE_KEY))||{};}catch(e){return{};}}
  function saveState(s){try{localStorage.setItem(STORAGE_KEY,JSON.stringify(s));}catch(e){}}

  function getAllWidgets(){
    return container ? Array.from(container.querySelectorAll('[data-widget]')) : [];
  }

  function init(){
    container = document.getElementById('db-widgets-container');
    if(!container) return;
    var state = loadState();

    // Aplicar visibilidade
    if(state.hidden){
      state.hidden.forEach(function(id){
        var el = container.querySelector('[data-widget="'+id+'"]');
        if(el) el.style.display='none';
      });
    }

    // Aplicar ordem salva (só widgets de nível direto do container)
    if(state.order && state.order.length){
      // Para widgets que são filhos diretos de db-mid/db-3col/db-2col,
      // a ordem é aplicada dentro do grupo pai
      // Para widgets no nível raiz (db-widget), aplicar no container
      var rootWidgets = Array.from(container.children).filter(function(el){
        return el.hasAttribute('data-widget');
      });
      state.order.forEach(function(id){
        var el = container.querySelector(':scope > [data-widget="'+id+'"]');
        if(el) container.appendChild(el);
      });
    }

    // Adicionar controles em TODOS os widgets (incluindo dentro de grids)
    getAllWidgets().forEach(function(widget){
      addControls(widget);
      makeDraggable(widget);
    });

    renderPanel();

    updateResetBtn();
  }

  function addControls(widget){
    if(widget.querySelector('.db-widget-handle')) return;

    // Handle de arrastar — aparece discretamente no canto superior esquerdo no hover
    var handle = document.createElement('button');
    handle.className = 'db-widget-handle';
    handle.title = 'Arrastar para mover';
    handle.innerHTML = '<span class="material-icons-outlined" style="font-size:13px;pointer-events:none">drag_indicator</span>';
    // Ativa draggable apenas quando mousedown no handle
    handle.addEventListener('mousedown', function(){
      widget.setAttribute('draggable','true');
    });
    widget.appendChild(handle);

    // Botão fechar — aparece discretamente no canto superior direito no hover
    var remove = document.createElement('button');
    remove.className = 'db-widget-remove';
    remove.title = 'Ocultar card';
    remove.innerHTML = '<span class="material-icons-outlined" style="font-size:12px;pointer-events:none">close</span>';
    remove.addEventListener('click', function(e){
      e.stopPropagation();
      hideWidget(widget.getAttribute('data-widget'));
    });
    widget.appendChild(remove);
  }

  function makeDraggable(widget){
    // draggable ativado só via mousedown no handle — evita arrastar ao clicar no conteúdo
    widget.setAttribute('draggable','false');

    widget.addEventListener('dragstart',function(e){
      dragging = widget;
      setTimeout(function(){widget.classList.add('is-dragging');},0);
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', widget.getAttribute('data-widget'));
    });

    widget.addEventListener('dragend',function(){
      widget.setAttribute('draggable','false');
      widget.classList.remove('is-dragging');
      container.querySelectorAll('.drag-target').forEach(function(el){el.classList.remove('drag-target');});
      dragging = null;
      saveOrder();
    });

    widget.addEventListener('dragover',function(e){
      e.preventDefault();
      if(!dragging || dragging===widget) return;
      widget.classList.add('drag-target');
      var rect = widget.getBoundingClientRect();
      var mid  = rect.top + rect.height/2;
      // Sempre move para o container raiz (não dentro de db-autorow)
      var target = widget;
      var targetParent = widget.parentNode;
      // Se o widget alvo está dentro de um db-autorow, usa o db-autorow como referência
      if(targetParent && targetParent.classList && targetParent.classList.contains('db-autorow')){
        target = targetParent;
        targetParent = container;
      }
      // Se o widget sendo arrastado está dentro de um db-autorow, extrai ele primeiro
      var draggingParent = dragging.parentNode;
      if(draggingParent && draggingParent.classList && draggingParent.classList.contains('db-autorow')){
        dragging.style.marginBottom = '14px';
      }
      if(e.clientY < mid) targetParent.insertBefore(dragging, target);
      else targetParent.insertBefore(dragging, target.nextSibling);
      // Limpar db-autorow vazio
      cleanEmptyAutorows();
    });

    widget.addEventListener('dragleave',function(){widget.classList.remove('drag-target');});
    widget.addEventListener('drop',function(e){e.preventDefault();widget.classList.remove('drag-target');});
  }

  function hideWidget(id){
    var el = container.querySelector('[data-widget="'+id+'"]');
    if(el){
      el.style.display='none';
      var s=loadState(); s.hidden=s.hidden||[];
      if(s.hidden.indexOf(id)===-1) s.hidden.push(id);
      saveState(s); renderPanel();
      cleanEmptyAutorows();
      updateResetBtn();
    }
  }

  function showWidget(id){
    var el = container.querySelector('[data-widget="'+id+'"]');
    if(el){
      el.style.display='';
      var s=loadState();
      s.hidden=(s.hidden||[]).filter(function(h){return h!==id;});
      saveState(s); renderPanel();
    }
  }

  function cleanEmptyAutorows(){
    container.querySelectorAll('.db-autorow').forEach(function(row){
      var visibleCards = Array.from(row.children).filter(function(c){
        return c.hasAttribute('data-widget');
      });
      if(visibleCards.length === 0){
        row.parentNode && row.parentNode.removeChild(row);
      } else if(visibleCards.length === 1){
        // Só 1 card — extrai para o container e remove o row
        var card = visibleCards[0];
        card.style.marginBottom = '14px';
        row.parentNode.insertBefore(card, row);
        row.parentNode.removeChild(row);
      }
    });
  }

  function updateResetBtn(){
    var rb = document.getElementById('dashResetBtn');
    if(!rb) return;
    var s = loadState();
    var hasCustom = (s.order && s.order.length > 0) || (s.hidden && s.hidden.length > 0);
    rb.style.display = hasCustom ? 'block' : 'none';
  }

  function saveOrder(){
    // Coleta todos os widgets na ordem visual atual (incluindo dentro de autorows)
    var allEls = [];
    Array.from(container.children).forEach(function(child){
      if(child.hasAttribute('data-widget')){
        allEls.push(child);
      } else if(child.classList && child.classList.contains('db-autorow')){
        Array.from(child.children).forEach(function(c){
          if(c.hasAttribute('data-widget')) allEls.push(c);
        });
      }
    });
    var order = allEls.map(function(w){return w.getAttribute('data-widget');});
    var s=loadState(); s.order=order; saveState(s);
    updateResetBtn();
  }

  function renderPanel(){
    var list=document.getElementById('panel-widget-list');
    if(!list) return;
    var s=loadState(); var hidden=s.hidden||[];
    list.innerHTML='';
    CATALOG.forEach(function(item){
      var isHidden=hidden.indexOf(item.id)!==-1;
      var div=document.createElement('div');
      div.className='panel-widget-item'+(isHidden?' hidden-widget':'');
      div.innerHTML=
        '<span class="pwi-icon">'+item.icon+'</span>'+
        '<div class="pwi-info"><div class="pwi-name">'+item.name+'</div><div class="pwi-desc">'+item.desc+'</div></div>'+
        '<span class="pwi-status '+(isHidden?'hidden':'visible')+'">'+(isHidden?'Oculto':'Visível')+'</span>';
      div.addEventListener('click',function(){
        if(isHidden) showWidget(item.id); else hideWidget(item.id);
      });
      list.appendChild(div);
    });
  }

  window.toggleWidgetPanel=function(){
    var panel=document.getElementById('db-add-panel');
    var overlay=document.getElementById('db-panel-overlay');
    var btn=document.getElementById('db-add-btn');
    if(!panel) return;
    var open=panel.classList.contains('open');
    if(open){
      panel.classList.remove('open');
      overlay&&overlay.classList.remove('open');
      btn&&btn.classList.remove('panel-open');
    } else {
      renderPanel();
      panel.classList.add('open');
      overlay&&overlay.classList.add('open');
      btn&&btn.classList.add('panel-open');
    }
  };
  window.closeWidgetPanel=function(){
    document.getElementById('db-add-panel')&&document.getElementById('db-add-panel').classList.remove('open');
    document.getElementById('db-panel-overlay')&&document.getElementById('db-panel-overlay').classList.remove('open');
    document.getElementById('db-add-btn')&&document.getElementById('db-add-btn').classList.remove('panel-open');
  };
  window.resetDashboardLayout=function(){
    try{localStorage.removeItem(STORAGE_KEY);localStorage.removeItem('gestorads_dashboard_order');}catch(e){}
    location.reload();
  };

  document.addEventListener('DOMContentLoaded',function(){
    if(document.getElementById('db-widgets-container')) init();
  });
})();

// ════════════════════════════════════════════════════════════
// INDICADOR DE SCROLL — mostra fade quando há mais items
// ════════════════════════════════════════════════════════════
(function(){
  function initScrollFade(){
    document.querySelectorAll('[style*="overflow-y:auto"], [style*="overflow-y: auto"]').forEach(function(el){
      var parent = el.parentElement;
      if(!parent) return;
      
      function checkScroll(){
        var hasMore = el.scrollHeight > el.clientHeight + 8;
        var atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 8;
        if(hasMore && !atBottom){
          parent.classList.add('has-more');
        } else {
          parent.classList.remove('has-more');
        }
      }
      
      if(el.scrollHeight > el.clientHeight){
        parent.classList.add('db-scroll-fade');
        checkScroll();
        el.addEventListener('scroll', checkScroll, {passive:true});
      }
    });
  }
  
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(initScrollFade, 400);
  });
})();
