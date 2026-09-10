/**
 * GestorPro — Lista completa de variáveis Meta Ads + Google Ads
 * Espelha exatamente o Metrifiquei
 */
var GP_VARS = {

  meta: {
    'Gerais': [
      {l:'Ano atual',            t:'<ANO>'},
      {l:'Campanha',             t:'<CAMPANHA>'},
      {l:'Conta de anúncios',    t:'<CA>'},
      {l:'Data do período',      t:'<DATA>'},
      {l:'Dia anterior',         t:'<YESTERDAY>'},
      {l:'Dia de hoje',          t:'<HOJE>'},
      {l:'Hora atual',           t:'<HORA>'},
      {l:'Limite de gasto',      t:'<LIMITE_CA>'},
      {l:'Mês anterior',         t:'<MES_ANTERIOR>'},
      {l:'Mês atual',            t:'<MES_ATUAL>'},
      {l:'Nome do cliente',      t:'<CUSTOMER_NAME>'},
      {l:'Primeiro nome',         t:'{primeiro_nome}'},
      {l:'Empresa do cliente',     t:'{empresa}'},
      {l:'Primeiro nome',         t:'{primeiro_nome}'},
      {l:'Empresa do cliente',     t:'{empresa}'},
      {l:'Objetivo da campanha', t:'<OBJECTIVE>'},
      {l:'Saldo da conta',       t:'<SALDO>'},
      {l:'Saudação',             t:'<SAUDACAO>'},
      {l:'Total campanhas ativas',t:'<TOTAL_CAMP_ATIVA>'},
      {l:'Orçamento diário',     t:'<DAILY_BUDGET>'},
      {l:'Impostos estimados',   t:'<TAX_ACCOUNT_META>'},
      {l:'Saldo com impostos',   t:'<GROSS_BALANCE>'},
    ],
    'Cliques e Impressões': [
      {l:'Alcance',              t:'<ALCAN>'},
      {l:'Cliques de saída',     t:'<CLICK_SAIDA>'},
      {l:'Cliques no link',      t:'<CLIQ>'},
      {l:'Clique Todos',         t:'<CLICKS_ALL>'},
      {l:'CTR',                  t:'<CTR>'},
      {l:'CTR Todos',            t:'<CTR_ALL>'},
      {l:'Frequência',           t:'<FREQUENCIA>'},
      {l:'Impressões',           t:'<IMP>'},
      {l:'Incrementabilidade',   t:'<INCREM>'},
      {l:'Pesquisa',             t:'<SEARCH>'},
      {l:'Taxa de Entrada na página',t:'<CR>'},
      {l:'Visitas ao perfil',    t:'<PROFILE_VISIT>'},
      {l:'Visualizações de Página',t:'<PAGEVIEW>'},
    ],
    'Conversões': [
      {l:'Adição ao Carrinho',   t:'<CART>'},
      {l:'Ativação do Aplicativo',t:'<ACTIVATE_APP>'},
      {l:'Compra no Aplicativo', t:'<APP_PURCHASE>'},
      {l:'Contatos no site',     t:'<CONTACT>'},
      {l:'Conversão',            t:'<CONVERSION>'},
      {l:'Conversão de Leads pelo Pixel',t:'<CONVERSION_LEAD_PIXEL>'},
      {l:'Conversão do Pixel',   t:'<CONV_PIXEL_CUSTOM>'},
      {l:'Conversão Personalizada',t:'<CONVERSION_CUSTOM>'},
      {l:'Conversas Iniciadas',  t:'<MSG>'},
      {l:'Download',             t:'<DOWNLOAD>'},
      {l:'Engajamento com o post',t:'<ENGAJAMENTO>'},
      {l:'Faturado',             t:'<FAT>'},
      {l:'Hookhate',             t:'<HOOKHATE_OVERALL>'},
      {l:'Hookhate por anúncio', t:'<HOOKHATE_AD>'},
      {l:'Hookhate por campanha',t:'<HOOKHATE_CAMP>'},
      {l:'Hookhate por conjunto',t:'<HOOKHATE_ADSET>'},
      {l:'Inclusões Pagamento',  t:'<ADD_PAYMENT_INFO>'},
      {l:'Início de checkout',   t:'<INICHECKOUT>'},
      {l:'Instalação App Mobile',t:'<APP_INSTALL>'},
      {l:'Lead da Meta',         t:'<LEADS_META>'},
      {l:'Lead no Facebook',     t:'<LEAD_FACEBOOK>'},
      {l:'Leads',                t:'<LEADS>'},
      {l:'Ligações 20 segundos', t:'<CALL_20_SECONDS>'},
      {l:'Ligações 60 segundos', t:'<CALL_60_SECONDS>'},
      {l:'Ligações feitas',      t:'<CLICK_TO_CALL>'},
      {l:'Lista nomes criativos',t:'<ALL_CREATIVES_SIMPLE>'},
      {l:'Novas mensagens',      t:'<MSG_NEW>'},
      {l:'Nº cliques confirmação ligação',t:'<CLICK_TO_CALL_CONFIRM>'},
      {l:'Ranking Todos Criativos',t:'<ALL_CREATIVES_RANKING>'},
      {l:'Ranking TOP 1 Criativo',t:'<TOP_1_CREATIVES>'},
      {l:'Ranking TOP 3 Criativos',t:'<TOP_3_CREATIVES>'},
      {l:'Ranking TOP 5 Criativos',t:'<TOP_5_CREATIVES>'},
      {l:'Registros Concluídos', t:'<COMPLETE_REGISTRATION>'},
      {l:'Resultados',           t:'<RESULTS>'},
      {l:'ROAS',                 t:'<ROAS>'},
      {l:'Taxa Conv. Página Captura',t:'<CONV_PG>'},
      {l:'Taxa Conv. Página Vendas',t:'<CONV_PG_VENDAS>'},
      {l:'Taxa Conv. Conversas/Cliques',t:'<CONV_MSG_CLICK>'},
      {l:'Taxa Conv. Lead/Cliques',t:'<CONV_LEAD_CLICK>'},
      {l:'Taxa Conv. Checkout',  t:'<CONV_CHECKOUT>'},
      {l:'Taxa Conv. Funil Vendas',t:'<CONV_FUNNEL>'},
      {l:'Taxa entrada Checkout',t:'<CHECKOUT_RATE>'},
      {l:'Taxa Custo vs Ticket Médio',t:'<FCI_TM_RATE>'},
      {l:'Ticket Médio',         t:'<TM>'},
      {l:'Todos os leads',       t:'<ALL_LEADS>'},
      {l:'Total de mensagens',   t:'<MSG_ALL>'},
      {l:'Valor Vendas Aplicativo',t:'<APP_PURCHASE_VALUE>'},
      {l:'Vendas (Compras)',      t:'<VEND>'},
    ],
    'Custo': [
      {l:'Custo/Adição ao Carrinho',t:'<CPCART>'},
      {l:'Custo/Ativação App',   t:'<ACTIVATE_APP_COST>'},
      {l:'CPC',                  t:'<CPC>'},
      {l:'Custo/Clique Todos',   t:'<CLICKS_ALL_COST>'},
      {l:'Custo/Compra (CPV)',   t:'<CPV>'},
      {l:'Custo/Compra App',     t:'<APP_PURCHASE_COST>'},
      {l:'Custo/Contatos site',  t:'<CONTACT_COST>'},
      {l:'Custo/Conversão',      t:'<CONVERSION_COST>'},
      {l:'Custo/Conv. Pixel',    t:'<CONV_PIXEL_CUSTOM_COST>'},
      {l:'Custo/Conv. Personalizada',t:'<CONVERSION_CUSTOM_COST>'},
      {l:'Custo/Conversas',      t:'<CMSG>'},
      {l:'Custo/Conv. 30 dias',  t:'<CONVERSATION_STARTED_30D>'},
      {l:'Custo/Download',       t:'<DOWNLOAD_COST>'},
      {l:'Custo/Engajamento',    t:'<ENGAJAMENTO_COST>'},
      {l:'Custo/Inclusão Pagamento',t:'<ADD_PAYMENT_INFO_COST>'},
      {l:'Custo/Início Compra',  t:'<CPCHECK>'},
      {l:'Custo/App Install',    t:'<APP_INSTALL_COST>'},
      {l:'CPL (Custo/Lead)',      t:'<CPL>'},
      {l:'Custo/Lead 30 dias',   t:'<LEAD_COST_30D>'},
      {l:'Custo/Lead Meta',      t:'<LEADS_META_COST>'},
      {l:'Custo/Lead Pixel',     t:'<CONVERSION_LEAD_PIXEL_COST>'},
      {l:'Custo/Lead Facebook',  t:'<LEAD_FACEBOOK_COST>'},
      {l:'Custo/Ligação 20s',    t:'<CALL_20_SECONDS_COST>'},
      {l:'Custo/Ligação 60s',    t:'<CALL_60_SECONDS_COST>'},
      {l:'Custo/Ligações feitas',t:'<CLICK_TO_CALL_COST>'},
      {l:'CPM',                  t:'<CPM>'},
      {l:'Custo/Mil alcançados', t:'<CPMA>'},
      {l:'Custo/Novas Mensagens',t:'<MSG_NEW_COST>'},
      {l:'Custo/Cliques Ligação',t:'<CLICK_TO_CALL_CONFIRM_COST>'},
      {l:'Custo/Pesquisa',       t:'<C_SEARCH>'},
      {l:'Custo/Registro Concluído',t:'<COMPLETE_REGISTRATION_COST>'},
      {l:'Custo/Resultado',      t:'<RESULTS_COST>'},
      {l:'Custo/Thruplay',       t:'<THRUPLAY_COST>'},
      {l:'Custo/Todos Leads',    t:'<ALL_LEADS_COST>'},
      {l:'Custo/Total Mensagens',t:'<MSG_ALL_COST>'},
      {l:'Custo/Visitas Perfil', t:'<PROFILE_VISIT_COST>'},
      {l:'Custo/Visualiz. Página',t:'<CPVP>'},
      {l:'Investimento',         t:'<INV>'},
      {l:'Valor usado total',    t:'<TOTAL_SPEND>'},
    ],
    'Engajamento': [
      {l:'Comentários no anúncio',t:'<COMMENT>'},
      {l:'Engajamento com a página',t:'<PAGE_ENGAGEMENT>'},
      {l:'Likes no anúncio',     t:'<POST_REACTION>'},
      {l:'Salvamentos do post',  t:'<POST_SAVE>'},
    ],
    'Vídeo': [
      {l:'Assistiu 100%',        t:'<VIEW_100>'},
      {l:'Assistiu 25%',         t:'<VIEW_25>'},
      {l:'Assistiu 50%',         t:'<VIEW_50>'},
      {l:'Assistiu 75%',         t:'<VIEW_75>'},
      {l:'Assistiu 95%',         t:'<VIEW_95>'},
      {l:'Taxa visualização 100%',t:'<VVIEW_P>'},
      {l:'Tempo assistido Médio',t:'<V_AVG>'},
      {l:'Thruplay',             t:'<THRUPLAY>'},
      {l:'Video Play',           t:'<VPLAY>'},
      {l:'Vídeo View',           t:'<VVIEW>'},
    ],
  },

  google: {
    'Gerais': [
      {l:'Ano atual',            t:'<ANO>'},
      {l:'Campanha',             t:'<CAMPANHA>'},
      {l:'Conta de anúncios',    t:'<CA>'},
      {l:'Data do período',      t:'<DATA>'},
      {l:'Dia anterior',         t:'<YESTERDAY>'},
      {l:'Dia de hoje',          t:'<HOJE>'},
      {l:'Hora atual',           t:'<HORA>'},
      {l:'Limite de gasto',      t:'<LIMITE_CA>'},
      {l:'Mês anterior',         t:'<MES_ANTERIOR>'},
      {l:'Mês atual',            t:'<MES_ATUAL>'},
      {l:'Nome do cliente',      t:'<CUSTOMER_NAME>'},
      {l:'Primeiro nome',         t:'{primeiro_nome}'},
      {l:'Empresa do cliente',     t:'{empresa}'},
      {l:'Objetivo da campanha', t:'<OBJECTIVE>'},
      {l:'Saldo da conta',       t:'<SALDO>'},
      {l:'Saudação',             t:'<SAUDACAO>'},
      {l:'Total campanhas ativas',t:'<TOTAL_CAMP_ATIVA>'},
    ],
    'Cliques e Impressões': [
      {l:'Cliques no link',      t:'<CLICKS>'},
      {l:'% Impressões Posição Absoluta Superior',t:'<ABS_TOP_IMPRESS_P>'},
      {l:'% Impressões no Topo', t:'<TOP_IMPRESS_P>'},
      {l:'% Impressões Perdidas por Classificação',t:'<RANK_LOST_IMP_P>'},
      {l:'% Impressões Perdidas Orçamento Conteúdo',t:'<CONT_BUDGET_LOST_P>'},
      {l:'% Impressões Perdidas Orçamento Pesquisa',t:'<SEARCH_LOST_IMP_P>'},
      {l:'Impressões',           t:'<IMPRESS>'},
      {l:'Parcela de Impressões Pesquisa',t:'<SEARCH_IMPRESSION_SHARE>'},
      {l:'CTR',                  t:'<CTR>'},
    ],
    'Conversões': [
      {l:'Conversões',           t:'<CONVERSIONS>'},
      {l:'Todas as Conversões',  t:'<ALL_CONVERSIONS>'},
      {l:'Valor das Conversões', t:'<CONV_VALUE>'},
      {l:'ROAS',                 t:'<ROAS>'},
      {l:'Taxa Conv. Interações',t:'<CONV_INT_RATE>'},
      {l:'Taxa Todas Conv. Interações',t:'<ALL_CONV_INT_R>'},
      {l:'Video TrueView',       t:'<VIDEO_TRUEVIEW>'},
      {l:'Lista palavras-chave', t:'<ALL_KEYWORDS_SIMPLE>'},
      {l:'Ranking Todas Keywords',t:'<ALL_KEYWORDS_RANKING>'},
      {l:'Ranking TOP 1 Keyword',t:'<TOP_1_KEYWORDS>'},
      {l:'Ranking TOP 3 Keywords',t:'<TOP_3_KEYWORDS>'},
      {l:'Ranking TOP 5 Keywords',t:'<TOP_5_KEYWORDS>'},
    ],
    'Conversões Detalhadas': [
      {l:'Adicionar ao carrinho',t:'<ADD_TO_CART>'},
      {l:'Adições ao carrinho (listagem)',t:'<ADD_TO_CART_DETAILS>'},
      {l:'Agendamento (listagem)',t:'<BOOK_APPOINTMENT_DETAILS>'},
      {l:'Assinatura',           t:'<SUBSCRIBE_PAID>'},
      {l:'Assinatura paga (listagem)',t:'<SUBSCRIBE_PAID_DETAILS>'},
      {l:'Cadastro (listagem)',  t:'<SIGNUP_DETAILS>'},
      {l:'Clique Externo',       t:'<OUTBOUND_CLICK>'},
      {l:'Clique externo (listagem)',t:'<OUTBOUND_CLICK_DETAILS>'},
      {l:'Compra (listagem)',    t:'<PURCHASE_DETAILS>'},
      {l:'Compras',              t:'<PURCHASE>'},
      {l:'Contato',              t:'<CONTACT>'},
      {l:'Contato (listagem)',   t:'<CONTACT_DETAILS>'},
      {l:'Download (listagem)',  t:'<DOWNLOAD_DETAILS>'},
      {l:'Downloads',            t:'<DOWNLOAD>'},
      {l:'Engajamento',          t:'<ENGAGEMENT>'},
      {l:'Engajamento (listagem)',t:'<ENGAGEMENT_DETAILS>'},
      {l:'Enviar Formulário Lead',t:'<SUBMIT_LEAD_FORM>'},
      {l:'Início de checkout',   t:'<BEGIN_CHECKOUT>'},
      {l:'Inscrição',            t:'<SIGNUP>'},
      {l:'Lead Convertido',      t:'<CONVERTED_LEAD>'},
      {l:'Lead Importado',       t:'<IMPORTED_LEAD>'},
      {l:'Lead por Ligação',     t:'<PHONE_CALL_LEAD>'},
      {l:'Lead Qualificado',     t:'<QUALIFIED_LEAD>'},
      {l:'Ligações',             t:'<PHONE_CALL>'},
      {l:'Receita na Loja',      t:'<STORE_SALE_REVENUE>'},
      {l:'Receita por Compra',   t:'<PURCHASE_REVENUE>'},
      {l:'Reservar horário',     t:'<BOOK_APPOINTMENT>'},
      {l:'Solicitar Orçamento',  t:'<REQUEST_QUOTE>'},
      {l:'Venda na Loja',        t:'<STORE_SALE>'},
      {l:'Ver rota',             t:'<GET_DIRECTIONS>'},
      {l:'Visitas à Loja',       t:'<STORE_VISIT>'},
      {l:'Visualização de Página',t:'<PAGE_VIEW>'},
    ],
    'Custo': [
      {l:'CPC Médio',            t:'<AVG_CPC>'},
      {l:'CPC (Últimos 30 dias)',t:'<CPC_30D>'},
      {l:'CPM Médio',            t:'<AVG_CPM>'},
      {l:'Custo Médio',          t:'<AVG_COST>'},
      {l:'Custo/Conversão',      t:'<COST_PER_CONVERSION>'},
      {l:'Custo/Conv. 30 dias',  t:'<COST_PER_CONVERSION_30D>'},
      {l:'Custo/Engajamento',    t:'<AVG_CPE>'},
      {l:'Custo/Todas Conversões',t:'<COST_ALL_CONV>'},
      {l:'Custo/Video TrueView', t:'<VIDEO_TRUEVIEW_COST>'},
      {l:'Investimento',         t:'<INV>'},
      {l:'Ticket Médio',         t:'<TICKET_MEDIO>'},
      {l:'Custo/Adição ao carrinho',t:'<ADD_TO_CART_COST>'},
      {l:'Custo/Assinatura',     t:'<SUBSCRIBE_PAID_COST>'},
      {l:'Custo/Clique Externo', t:'<OUTBOUND_CLICK_COST>'},
      {l:'Custo/Compra',         t:'<PURCHASE_COST>'},
      {l:'Custo/Contato',        t:'<CONTACT_COST>'},
      {l:'Custo/Download',       t:'<DOWNLOAD_COST>'},
      {l:'Custo/Engajamento',    t:'<ENGAGEMENT_COST>'},
      {l:'Custo/Formulário Lead',t:'<SUBMIT_LEAD_FORM_COST>'},
      {l:'Custo/Início checkout',t:'<BEGIN_CHECKOUT_COST>'},
      {l:'Custo/Inscrição',      t:'<SIGNUP_COST>'},
      {l:'Custo/Lead Convertido',t:'<CONVERTED_LEAD_COST>'},
      {l:'Custo/Lead Importado', t:'<IMPORTED_LEAD_COST>'},
      {l:'Custo/Lead Ligação',   t:'<PHONE_CALL_LEAD_COST>'},
      {l:'Custo/Lead Qualificado',t:'<QUALIFIED_LEAD_COST>'},
      {l:'Custo/Ligação',        t:'<PHONE_CALL_COST>'},
      {l:'Custo/Reserva',        t:'<BOOK_APPOINTMENT_COST>'},
      {l:'Custo/Orçamento',      t:'<REQUEST_QUOTE_COST>'},
      {l:'Custo/Venda na Loja',  t:'<STORE_SALE_COST>'},
      {l:'Custo/Visitas à Loja', t:'<STORE_VISIT_COST>'},
      {l:'Custo/Visualiz. Página',t:'<PAGE_VIEW_COST>'},
      {l:'Custo/Ver rota',       t:'<GET_DIRECTIONS_COST>'},
    ],
  },
};

/**
 * Inicializa um var-picker num elemento
 * @param {string} btnId      — id do botão trigger
 * @param {string} panelId    — id do painel dropdown
 * @param {string} targetId   — id do textarea onde inserir
 * @param {string} platform   — 'meta' | 'google' | 'auto' (detecta pelo estado do wizard)
 */
function initGPVarPicker(btnId, panelId, targetId, platformFn) {
  var btn   = document.getElementById(btnId);
  var panel = document.getElementById(panelId);
  if (!btn || !panel) return;

  var activeCat = 'Todas';

  function getPlatform() {
    if (typeof platformFn === 'function') return platformFn();
    return platformFn || 'meta';
  }

  function buildCats() {
    var plat = getPlatform();
    var data = GP_VARS[plat] || GP_VARS.meta;
    var catsEl = panel.querySelector('.gp-vp-cats');
    if (!catsEl) return;
    catsEl.innerHTML = '';
    var allBtn = _cat('Todas', activeCat === 'Todas');
    allBtn.onclick = function() { activeCat = 'Todas'; catsEl.querySelectorAll('.gp-vcat').forEach(b=>b.classList.remove('active')); allBtn.classList.add('active'); render(); };
    catsEl.appendChild(allBtn);
    Object.keys(data).forEach(function(cat) {
      var b = _cat(cat, activeCat === cat);
      b.onclick = function() { activeCat = cat; catsEl.querySelectorAll('.gp-vcat').forEach(b=>b.classList.remove('active')); b.classList.add('active'); render(); };
      catsEl.appendChild(b);
    });
  }

  function _cat(label, active) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'gp-vcat vcat-btn' + (active ? ' active' : '');
    b.textContent = label; return b;
  }

  function render() {
    var plat  = getPlatform();
    var data  = GP_VARS[plat] || GP_VARS.meta;
    var q     = (panel.querySelector('.gp-vp-search') || {}).value || '';
    var list  = panel.querySelector('.gp-vp-list');
    if (!list) return;
    list.innerHTML = '';
    var cats  = activeCat === 'Todas' ? Object.keys(data) : [activeCat];
    var found = 0;
    cats.forEach(function(cat) {
      var items = (data[cat] || []).filter(function(v) {
        return !q || v.l.toLowerCase().includes(q.toLowerCase()) || v.t.toLowerCase().includes(q.toLowerCase());
      });
      if (!items.length) return;
      var sec = document.createElement('span');
      sec.className = 'vsec-title'; sec.textContent = cat;
      list.appendChild(sec);
      var wrap = document.createElement('div');
      items.forEach(function(v) {
        var tag = document.createElement('span');
        tag.className = 'vtag';
        tag.title = v.l; tag.textContent = v.t;
        tag.onclick = function() {
          var ta = document.getElementById(targetId);
          if (ta) {
            var s = ta.selectionStart, e = ta.selectionEnd;
            ta.value = ta.value.substring(0,s) + v.t + ta.value.substring(e);
            ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.t.length;
            if (typeof updPrev === 'function') updPrev();
          }
          panel.classList.remove('open');
        };
        wrap.appendChild(tag); found++;
      });
      list.appendChild(wrap);
    });
    if (!found) {
      var em = document.createElement('div');
      em.style.cssText = 'padding:20px;text-align:center;font-size:12px;color:var(--txt3)';
      em.textContent = 'Nenhuma variável encontrada'; list.appendChild(em);
    }
  }

  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    var isOpen = panel.classList.contains('open');
    document.querySelectorAll('.varpicker-panel').forEach(function(p){p.classList.remove('open');});
    if (!isOpen) {
      panel.classList.add('open');
      var si = panel.querySelector('.gp-vp-search');
      if (si) { si.value = ''; si.focus(); }
      buildCats(); render();
    }
  });
  document.addEventListener('click', function(e) {
    if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target))
      panel.classList.remove('open');
  });
  panel.addEventListener('click', function(e){ e.stopPropagation(); });

  // Search live
  panel.addEventListener('input', function(e) {
    if (e.target.classList.contains('gp-vp-search')) render();
  });
}
