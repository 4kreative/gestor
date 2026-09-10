<?php
$pageTitle   = 'Central de Ajuda';
$currentPage = 'help';
ob_start();
?>
<style>
.help-grid{display:grid;grid-template-columns:240px 1fr;gap:24px;align-items:start}
.help-nav{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:8px 0;position:sticky;top:80px}
.help-nav-item{display:flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;color:var(--txt2);cursor:pointer;transition:all .15s;border-left:2px solid transparent;text-decoration:none}
.help-nav-item:hover{background:var(--bg3);color:var(--txt);text-decoration:none}
.help-nav-item.active{background:var(--accent3);color:var(--accent);border-left-color:var(--accent)}
.help-nav-item .material-icons-outlined{font-size:17px}
.help-nav-section{padding:10px 16px 4px;font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.8px}
.help-content{display:flex;flex-direction:column;gap:20px}
.help-section{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px 28px;scroll-margin-top:80px}
.help-section h2{font-size:17px;font-weight:700;color:var(--txt);margin-bottom:6px;display:flex;align-items:center;gap:10px}
.help-section .section-desc{font-size:13px;color:var(--txt2);margin-bottom:20px;line-height:1.7}
.help-step{display:flex;gap:14px;margin-bottom:18px;align-items:flex-start}
.help-step-num{width:28px;height:28px;border-radius:50%;background:var(--accent);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.help-step-body{flex:1}
.help-step-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:3px}
.help-step-text{font-size:13px;color:var(--txt2);line-height:1.7}
.help-tip{background:var(--accent3);border:1px solid rgba(0,120,255,.2);border-radius:var(--radius);padding:12px 14px;font-size:12px;color:var(--txt2);display:flex;gap:10px;align-items:flex-start;margin-top:14px}
.help-tip .material-icons-outlined{font-size:16px;color:var(--accent);margin-top:1px;flex-shrink:0}
.help-warn{background:rgba(243,156,18,.08);border:1px solid rgba(243,156,18,.25);border-radius:var(--radius);padding:12px 14px;font-size:12px;color:var(--txt2);display:flex;gap:10px;align-items:flex-start;margin-top:14px}
.help-warn .material-icons-outlined{font-size:16px;color:var(--warn);flex-shrink:0}
.help-faq{border-top:1px solid var(--border);margin-top:12px;padding-top:12px}
.help-faq-q{font-size:13px;font-weight:600;color:var(--txt);cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:8px 0;user-select:none}
.help-faq-q:hover{color:var(--accent)}
.help-faq-a{font-size:13px;color:var(--txt2);line-height:1.7;padding:6px 0 12px;display:none}
.help-faq-a.open{display:block}
.help-tag{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:var(--accent3);color:var(--accent);margin-left:6px}
.help-video-placeholder{background:var(--bg3);border:2px dashed var(--border2);border-radius:var(--radius2);padding:32px;text-align:center;color:var(--txt3);font-size:13px;margin-top:12px}
@media(max-width:768px){.help-grid{grid-template-columns:1fr}.help-nav{display:none}}
</style>

<div class="help-grid">
  <!-- Nav lateral -->
  <nav class="help-nav">
    <div class="help-nav-section">Início</div>
    <a href="#visao-geral" class="help-nav-item active"><span class="material-icons-outlined">home</span> Visão Geral</a>
    <div class="help-nav-section">Configuração</div>
    <a href="#contas" class="help-nav-item"><span class="material-icons-outlined">account_balance</span> Contas de Anúncio</a>
    <a href="#clientes" class="help-nav-item"><span class="material-icons-outlined">people_outline</span> Clientes</a>
    <a href="#whatsapp" class="help-nav-item"><i class="fa-brands fa-whatsapp" style="width:17px;font-size:15px"></i> WhatsApp</a>
    <a href="#templates" class="help-nav-item"><span class="material-icons-outlined">file_copy</span> Templates</a>
    <a href="#variaveis" class="help-nav-item"><span class="material-icons-outlined">tag</span> Variáveis</a>
    <div class="help-nav-section">Recursos</div>
    <a href="#relatorios" class="help-nav-item"><span class="material-icons-outlined">assignment</span> Relatórios</a>
    <a href="#alertas" class="help-nav-item"><span class="material-icons-outlined">add_alert</span> Alertas de Saldo</a>
    <a href="#ia" class="help-nav-item"><span class="material-icons-outlined">auto_awesome</span> Análise com IA</a>
    <a href="#ia-relatorio" class="help-nav-item"><span class="material-icons-outlined">auto_awesome</span> IA nos Relatórios</a>
    <div class="help-nav-section">Outros</div>
    <a href="#faq" class="help-nav-item"><span class="material-icons-outlined">help_outline</span> Perguntas Frequentes</a>
  </nav>

  <!-- Conteúdo -->
  <div class="help-content">

    <!-- VISÃO GERAL -->
    <div class="help-section" id="visao-geral">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">home</span> Bem-vindo ao <?= APP_NAME ?></h2>
      <p class="section-desc">O <?= APP_NAME ?> é uma plataforma completa para gestores de tráfego pago. Conecte contas de Meta Ads e Google Ads, gere relatórios automáticos em PDF e envie pelo WhatsApp diretamente para seus clientes.</p>

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:4px">
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">📊</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">Métricas em tempo real</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Sincronize campanhas do Meta e Google Ads</div>
        </div>
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">📄</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">Relatórios automáticos</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Gere e envie relatórios pelo WhatsApp</div>
        </div>
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">🔔</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">Alertas inteligentes</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Receba avisos de saldo baixo no WhatsApp</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px">
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">🤖</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">IA com contexto completo</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Demografia, posicionamento, criativos e mais</div>
        </div>
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">🎯</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">Templates personalizados</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Mensagens com variáveis automáticas</div>
        </div>
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">⏸️</div>
          <div style="font-size:12px;font-weight:600;color:var(--txt)">Gestão completa</div>
          <div style="font-size:11px;color:var(--txt2);margin-top:3px">Ative/pause relatórios e clientes com toggle</div>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>Para começar do zero: conecte uma conta Meta Ads → crie um cliente → gere um relatório → configure o WhatsApp para envio automático.</span>
      </div>
    </div>

    <!-- CONTAS DE ANÚNCIO -->
    <div class="help-section" id="contas">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">account_balance</span> Contas de Anúncio</h2>
      <p class="section-desc">Conecte suas contas do Meta Ads (Facebook/Instagram) e Google Ads para começar a sincronizar métricas automaticamente.</p>

      <div style="font-size:13px;font-weight:600;color:var(--txt);margin-bottom:12px">Conectar via Login do Facebook (recomendado)</div>
      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Acesse Contas de Anúncio no menu</div>
          <div class="help-step-text">Clique em "Contas de Anúncio" na barra lateral esquerda.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Clique em "Conectar Meta Ads"</div>
          <div class="help-step-text">Clique no botão azul no canto superior direito da tela.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Escolha "OAuth (Login FB)"</div>
          <div class="help-step-text">Na janela que abrir, clique na aba "OAuth (Login FB)" e depois em "Entrar com Facebook".</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Autorize o acesso a todos os Negócios</div>
          <div class="help-step-text">Na tela do Facebook, selecione "Aceitar todos os Negócios atuais e no futuro" para que o sistema importe todas as contas de anúncio automaticamente.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">5</div>
        <div class="help-step-body">
          <div class="help-step-title">Pronto — contas importadas!</div>
          <div class="help-step-text">O sistema vai buscar todas as contas de anúncio vinculadas ao seu Facebook e Business Managers e adicioná-las automaticamente.</div>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>Prefere usar Token Manual? Cole um token de acesso do Gerenciador de Negócios do Facebook. O sistema buscará todas as contas vinculadas a ele.</span>
      </div>

      <div style="font-size:13px;font-weight:600;color:var(--txt);margin:18px 0 12px">Busca e exclusão em massa</div>
      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Busca por nome ou ID</div>
          <div class="help-step-text">Use o campo de busca no topo da lista para filtrar contas por nome ou ID em tempo real. Suporta múltiplas palavras.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Selecionar e excluir em massa</div>
          <div class="help-step-text">Marque o checkbox de cada conta que deseja remover (ou use "Selecionar todas"). A barra de ação aparece automaticamente com o botão "Deletar Selecionadas".</div>
        </div>
      </div>

      <div style="font-size:13px;font-weight:600;color:var(--txt);margin:18px 0 12px">Sincronizar métricas</div>
      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Sincronização automática</div>
          <div class="help-step-text">O sistema sincroniza métricas automaticamente via cron job configurado. Verifique com o administrador se está ativo.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Sincronização manual</div>
          <div class="help-step-text">Na lista de contas, clique no botão "Sincronizar" ao lado de qualquer conta para atualizar as métricas agora.</div>
        </div>
      </div>
    </div>

    <!-- CLIENTES -->
    <div class="help-section" id="clientes">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">people_outline</span> Clientes</h2>
      <p class="section-desc">Organize suas contas de anúncio por cliente para gerar relatórios personalizados e segmentados.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Crie um cliente</div>
          <div class="help-step-text">Vá em "Clientes" no menu → clique em "Novo Cliente" → preencha o nome e informações de contato.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Vincule contas ao cliente</div>
          <div class="help-step-text">Em "Contas de Anúncio", edite cada conta e selecione o cliente correspondente no campo "Cliente".</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Gere relatórios por cliente</div>
          <div class="help-step-text">Ao criar um relatório, selecione o cliente e todas as contas vinculadas serão incluídas automaticamente.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Ativar / Pausar cliente</div>
          <div class="help-step-text">O toggle verde/cinza na primeira coluna da lista ativa ou desativa o cliente. Clientes inativos não aparecem nas seleções de relatórios.</div>
        </div>
      </div>
    </div>

    <!-- WHATSAPP -->
    <div class="help-section" id="whatsapp">
      <h2><i class="fa-brands fa-whatsapp" style="color:#25D366;font-size:20px"></i> WhatsApp</h2>
      <p class="section-desc">Conecte uma instância do WhatsApp para enviar relatórios e alertas automaticamente para seus clientes.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Acesse "Conexões → WhatsApp"</div>
          <div class="help-step-text">Clique em WhatsApp no menu lateral e depois em "Nova Instância".</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Escaneie o QR Code</div>
          <div class="help-step-text">Um QR Code vai aparecer. Abra o WhatsApp no celular → Dispositivos Vinculados → Vincular um dispositivo → escaneie o código.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Aguarde a conexão</div>
          <div class="help-step-text">Em alguns segundos o status vai mudar para "Conectado" com o indicador verde. Pronto para enviar!</div>
        </div>
      </div>

      <div class="help-warn">
        <span class="material-icons-outlined">warning</span>
        <span>Mantenha o celular com o WhatsApp conectado à internet. Se o celular ficar offline por muito tempo, será necessário reconectar escaneando um novo QR Code.</span>
      </div>
    </div>

    <!-- RELATÓRIOS -->
    <div class="help-section" id="relatorios">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">assignment</span> Relatórios</h2>
      <p class="section-desc">Crie relatórios de performance com métricas de campanhas e envie automaticamente pelo WhatsApp para seus clientes.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Crie um novo relatório</div>
          <div class="help-step-text">Vá em "Meus Relatórios" → clique em "Novo Relatório" → escolha o cliente e o período desejado.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Selecione as contas e métricas</div>
          <div class="help-step-text">Escolha quais contas de anúncio incluir e quais métricas exibir (impressões, cliques, CPM, CPC, investimento, etc).</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Pré-visualize antes de enviar</div>
          <div class="help-step-text">Clique em "Pré-visualizar" para ver como o relatório vai aparecer para o cliente antes de enviar.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Envie pelo WhatsApp</div>
          <div class="help-step-text">Clique em "Enviar" para disparar o relatório via WhatsApp. Você pode enviar para número direto ou grupo.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">5</div>
        <div class="help-step-body">
          <div class="help-step-title">Configure envio automático</div>
          <div class="help-step-text">Ative o agendamento no relatório para que ele seja enviado automaticamente em dias e horários definidos por você.</div>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>Use <strong>Variáveis</strong> como <code>{cliente}</code>, <code>{periodo}</code>, <code>{investimento}</code> para personalizar a mensagem enviada com WhatsApp automaticamente.</span>
      </div>
    </div>

    <!-- ALERTAS -->
    <div class="help-section" id="alertas">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">add_alert</span> Alertas de Saldo</h2>
      <p class="section-desc">Configure alertas automáticos para ser notificado pelo WhatsApp quando o saldo de uma conta de anúncio cair abaixo de um valor mínimo.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Acesse "Alertas" no menu</div>
          <div class="help-step-text">Clique em "Alertas" na barra lateral e depois em "Novo Alerta".</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Escolha a conta e o saldo mínimo</div>
          <div class="help-step-text">Selecione a conta de anúncio e defina o valor em reais que vai disparar o alerta (ex: R$ 50,00).</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Configure o destinatário</div>
          <div class="help-step-text">Informe o número do WhatsApp ou selecione um grupo para receber os alertas.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Defina dias e horários</div>
          <div class="help-step-text">Escolha em quais dias da semana e horários o sistema deve verificar e enviar alertas.</div>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>O alerta com "Disparo Imediato" ativado envia a notificação assim que o saldo cair abaixo do mínimo, sem esperar o horário agendado (com cooldown de 60 min).</span>
      </div>
    </div>

    <!-- IA -->
    <div class="help-section" id="ia">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">auto_awesome</span> Análise com IA — Campanha</h2>
      <p class="section-desc">Analise campanhas específicas com IA. Acessa dados completos da API Meta: métricas gerais, demografia, posicionamentos, criativos e desempenho diário.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Configure as chaves de API</div>
          <div class="help-step-text">Em "Análise IA" clique em "Chaves de API". Suporta Groq (grátis), OpenAI (GPT-4o, GPT-4.1, o4-mini) e Gemini. Cada provedor tem modelos com custos diferentes.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Selecione conta, campanha e período</div>
          <div class="help-step-text">Escolha a conta de anúncio Meta, selecione a campanha e defina o período. O sistema busca métricas diretamente da API Meta em tempo real.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Use um template ou escreva uma instrução</div>
          <div class="help-step-text">Selecione um template pré-definido ou escreva uma instrução livre. O sistema preenche as variáveis automaticamente com dados reais.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Use o Chat livre para aprofundar</div>
          <div class="help-step-text">Após gerar a análise, use o Chat livre para perguntas como: "Qual faixa etária converte mais?", "Qual posicionamento tem melhor CTR?", "Compare os criativos".</div>
        </div>
      </div>

      <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;margin-top:14px">
        <div style="font-size:12px;font-weight:600;color:var(--txt);margin-bottom:8px">📊 Dados disponíveis para análise:</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;color:var(--txt2)">
          <div>✅ Métricas gerais (CTR, CPM, CPC, alcance)</div>
          <div>✅ Segmentação (idade, gênero, local)</div>
          <div>✅ Posicionamentos (Feed, Reels, Stories)</div>
          <div>✅ Desempenho diário (tendências)</div>
          <div>✅ Frequência e saturação do público</div>
          <div>✅ Métricas por anúncio/criativo</div>
          <div>✅ Ranking automático de criativos</div>
          <div>✅ Comparação com outras campanhas</div>
          <div>✅ Eventos de pixel (compras, leads)</div>
          <div>❌ ROI/receita (requer pixel configurado)</div>
        </div>
      </div>
    </div>

    <!-- IA NOS RELATÓRIOS -->
    <div class="help-section" id="ia-relatorio">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">auto_awesome</span> IA nos Relatórios</h2>
      <p class="section-desc">Cada relatório tem um botão de IA que analisa os dados daquele relatório específico e permite conversar sobre os resultados.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Clique no botão ✨ do relatório</div>
          <div class="help-step-text">Na lista de relatórios, clique no ícone de estrela (✨) ao lado do relatório para abrir a análise de IA.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Selecione template e período</div>
          <div class="help-step-text">Escolha um template de análise (ex: "Campanha de Mensagens", "Visitas ao Perfil") e o período desejado. Com template selecionado, o sistema preenche as variáveis e retorna a mensagem pronta.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Envie pelo WhatsApp</div>
          <div class="help-step-text">A mensagem gerada fica pronta para enviar ao cliente. Clique em "Enviar WhatsApp" para disparar direto.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">4</div>
        <div class="help-step-body">
          <div class="help-step-title">Ativar / Pausar relatório</div>
          <div class="help-step-text">O toggle na coluna St. ativa ou pausa o envio automático do relatório. Verde = ativo (envia nos horários agendados), cinza = pausado.</div>
        </div>
      </div>
    </div>

    <!-- TEMPLATES -->
    <div class="help-section" id="templates">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">file_copy</span> Templates de Mensagem</h2>
      <p class="section-desc">Crie modelos de mensagem reutilizáveis com variáveis dinâmicas para relatórios e análises de IA.</p>

      <div class="help-step">
        <div class="help-step-num">1</div>
        <div class="help-step-body">
          <div class="help-step-title">Acesse "Templates" no menu</div>
          <div class="help-step-text">Clique em Templates na barra lateral para ver todos os seus modelos de mensagem.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">2</div>
        <div class="help-step-body">
          <div class="help-step-title">Crie um template com variáveis</div>
          <div class="help-step-text">Use variáveis como <code>{investimento}</code>, <code>{alcance}</code>, <code>{empresa}</code> no texto. O sistema preenche automaticamente com os dados reais ao gerar a análise.</div>
        </div>
      </div>
      <div class="help-step">
        <div class="help-step-num">3</div>
        <div class="help-step-body">
          <div class="help-step-title">Use na Análise IA e Relatórios</div>
          <div class="help-step-text">Ao gerar uma análise de IA ou relatório, selecione um template. O sistema preenche as variáveis com dados reais da API e retorna a mensagem pronta para enviar.</div>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>Templates pré-definidos disponíveis: Campanha de Mensagens, Visitas ao Perfil, Relatório de Leads, Relatório de Vendas, Alcance e Engajamento, Performance Completo e mais.</span>
      </div>
    </div>

    <!-- VARIÁVEIS -->
    <div class="help-section" id="variaveis">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">tag</span> Variáveis</h2>
      <p class="section-desc">Use variáveis dinâmicas nas mensagens de relatórios e alertas para personalizar automaticamente o conteúdo enviado.</p>

      <div style="background:var(--bg3);border-radius:var(--radius);padding:16px;margin-bottom:4px">
        <div style="font-size:12px;font-weight:600;color:var(--txt);margin-bottom:10px">Variáveis disponíveis</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <?php
          $vars = [
            '{cliente}' => 'Nome do cliente',
            '{primeiro_nome}' => 'Primeiro nome do cliente',
            '{empresa}' => 'Nome da empresa/cliente',
            '{conta_anuncio}' => 'Nome da conta de anúncio',
            '{periodo}' => 'Período do relatório',
            '{investimento}' => 'Total investido (R$)',
            '{impressoes}' => 'Total de impressões',
            '{alcance}' => 'Alcance total',
            '{cliques}' => 'Cliques no link',
            '{cpc}' => 'Custo por clique',
            '{cpm}' => 'Custo por mil impressões',
            '{ctr}' => 'Taxa de cliques (%)',
            '{conversoes}' => 'Total de conversões',
            '{leads}' => 'Total de leads',
            '{engajamento}' => 'Total de engajamentos',
            '{roas}' => 'Retorno sobre investimento',
            '{frequencia}' => 'Frequência média',
            '{visitas_perfil}' => 'Visitas ao perfil Instagram',
            '{custo_visita_perfil}' => 'Custo por visita ao perfil',
            '{msg}' => 'Mensagens iniciadas',
            '{cmsg}' => 'Custo por mensagem',
            '{vendas}' => 'Total de vendas/compras',
            '{saldo}' => 'Saldo atual da conta',
            '{saldo_minimo}' => 'Saldo mínimo configurado',
          ];
          foreach ($vars as $tag => $desc): ?>
          <div style="display:flex;align-items:center;gap:8px;background:var(--bg2);padding:8px 10px;border-radius:6px;border:1px solid var(--border)">
            <code style="font-size:11px;color:var(--accent);font-family:monospace;background:var(--accent3);padding:2px 6px;border-radius:4px"><?= $tag ?></code>
            <span style="font-size:11px;color:var(--txt2)"><?= $desc ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="help-tip">
        <span class="material-icons-outlined">lightbulb</span>
        <span>Exemplo de mensagem: <em>"Olá! O saldo da conta {conta_anuncio} está em R$ {saldo}. Invistam antes que as campanhas pausem!"</em></span>
      </div>
    </div>

    <!-- FAQ -->
    <div class="help-section" id="faq">
      <h2><span class="material-icons-outlined" style="color:var(--accent)">help_outline</span> Perguntas Frequentes</h2>
      <p class="section-desc">Respostas para as dúvidas mais comuns.</p>

      <?php
      $faqs = [
        'Como ativar/pausar um relatório ou cliente?' => 'Use o toggle (botão deslizante) na primeira coluna da lista. Verde = ativo, cinza = inativo. Clique para alternar e confirme na janela de confirmação.',
        'A IA consegue ver os criativos e textos dos anúncios?' => 'Depende das permissões da conta. Para contas onde você é admin do Business Manager, a IA acessa headline, copy e CTA. Para contas de clientes em BMs de terceiros, a API do Meta pode restringir o acesso ao criativo.',
        'Quais provedores de IA são suportados?' => 'Groq (grátis — Llama 3.1, 3.3, GPT OSS, Qwen3, Llama 4), OpenAI (GPT-4o Mini, GPT-4.1, GPT-4o, o4-mini) e Google Gemini. Cada um tem velocidade e custo diferentes.',
        'Por que o botão OAuth (Login FB) não funciona?' => 'O App do Facebook precisa estar publicado e com o domínio configurado. Se acabou de configurar, aguarde 10-15 minutos para o Facebook propagar as configurações.',
        'Por que as métricas não estão atualizando?' => 'Verifique se o token da conta está válido em "Contas de Anúncio". Tokens expiram em 60 dias. Reconecte a conta via OAuth para renovar automaticamente.',
        'Como adicionar contas de clientes que não são minhas?' => 'O cliente precisa te dar acesso ao Business Manager dele. Depois é só fazer o OAuth com um usuário que tenha acesso e todas as contas aparecem.',
        'Posso conectar mais de um WhatsApp?' => 'Sim. Cada instância é independente. Você pode ter um número para cada cliente ou usar um único número central.',
        'Os relatórios são enviados como imagem ou texto?' => 'Os relatórios são enviados como mensagem de texto formatada com as métricas principais. O sistema também suporta envio em formato visual.',
        'Como configurar o envio automático de relatórios?' => 'Ao criar ou editar um relatório, ative a opção de agendamento e configure os dias da semana e horário desejado.',
        'O que acontece se o saldo zerar e o alerta não disparar?' => 'Verifique se o cron job do servidor está ativo. Acesse as configurações do servidor (Hostinger → Tarefas Agendadas) e confirme que o cron está rodando.',
        'Como revogar o acesso do sistema ao meu Facebook?' => 'Acesse Facebook → Configurações → Apps e Sites → encontre o app e clique em Remover. Depois remova a conta dentro do sistema também.',
      ];
      foreach ($faqs as $q => $a): ?>
      <div class="help-faq">
        <div class="help-faq-q" onclick="this.nextElementSibling.classList.toggle('open');this.querySelector('.material-icons-outlined').textContent=this.nextElementSibling.classList.contains('open')?'expand_less':'expand_more'">
          <?= e($q) ?>
          <span class="material-icons-outlined" style="font-size:18px;color:var(--txt3);flex-shrink:0">expand_more</span>
        </div>
        <div class="help-faq-a"><?= e($a) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<script>
// Highlight active nav on scroll
const sections = document.querySelectorAll('.help-section');
const navItems = document.querySelectorAll('.help-nav-item');
window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(s => { if (window.scrollY >= s.offsetTop - 100) current = s.id; });
  navItems.forEach(n => {
    n.classList.toggle('active', n.getAttribute('href') === '#'+current);
  });
}, {passive:true});
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
?>
