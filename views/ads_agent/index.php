<?php /* views/ads_agent/index.php */ ?>
<style>
#ag-wrap{display:flex;height:calc(100vh - 64px);overflow:hidden;background:var(--bg)}
#ag-side{width:270px;flex-shrink:0;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;transition:width .2s}
#ag-side.collapsed{width:0;overflow:hidden}
.ag-side-head{padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.ag-side-head h3{font-size:12px;font-weight:700;color:var(--txt);margin:0 0 10px;text-transform:uppercase;letter-spacing:.6px}
.prov-row{display:flex;gap:5px;margin-bottom:8px}
.prov-btn{flex:1;padding:6px 4px;border-radius:7px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt3);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s}
.prov-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.ag-sel{width:100%;background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:7px 10px;border-radius:8px;font-size:12px;outline:none;cursor:pointer;margin-bottom:6px}
.key-row{display:flex;gap:5px;margin-top:4px}
.key-inp{flex:1;background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:6px 8px;border-radius:7px;font-size:11px;outline:none}
.key-inp:focus{border-color:var(--accent)}
.key-save{background:var(--accent);color:#fff;border:none;border-radius:7px;padding:6px 10px;font-size:11px;cursor:pointer;white-space:nowrap}
.key-ok{font-size:11px;color:var(--success);padding:3px 0;display:none}
.no-key-warn{font-size:11px;color:var(--warn);padding:7px 8px;background:rgba(243,156,18,.1);border-radius:7px;line-height:1.5;margin-top:4px}
.no-key-warn a{color:var(--accent)}
.ag-hist-section{flex:1;overflow-y:auto;padding:10px 0}
.ag-hist-section .hist-head{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.7px;padding:6px 16px 4px}
.conv-item{padding:8px 16px;cursor:pointer;border-left:2px solid transparent;transition:all .12s}
.conv-item:hover{background:var(--bg3);border-left-color:var(--accent)}
.conv-item.active{background:var(--accent3);border-left-color:var(--accent)}
.conv-item .ci-title{font-size:12px;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:210px}
.conv-item .ci-meta{font-size:10px;color:var(--txt3);margin-top:2px}
.conv-item .ci-prov{display:inline-block;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:700;background:var(--accent3);color:var(--accent);margin-right:3px}
.conv-item{display:flex;align-items:center;justify-content:space-between;gap:6px}
.conv-item .ci-body{flex:1;min-width:0}
.conv-item .ci-del{flex-shrink:0;opacity:0;transition:opacity .12s;background:none;border:none;color:var(--txt3);cursor:pointer;font-size:14px;padding:4px;border-radius:4px;line-height:1}
.conv-item:hover .ci-del{opacity:1}
.conv-item .ci-del:hover{color:var(--danger);background:var(--bg3)}
.ag-sugs{padding:10px 12px;border-top:1px solid var(--border);flex-shrink:0;max-height:240px;overflow-y:auto}
.ag-sugs h4{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.7px;margin:0 0 6px}
.sug-btn{display:block;width:100%;text-align:left;padding:6px 9px;border-radius:7px;background:var(--bg3);border:1px solid var(--border);font-size:11px;color:var(--txt2);cursor:pointer;margin-bottom:5px;line-height:1.4;transition:all .12s}
.sug-btn:hover{background:var(--accent3);color:var(--accent);border-color:var(--accent)}
.sug-btn strong{display:block;font-size:10px;color:var(--txt3);margin-bottom:1px}
#ag-main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
#ag-topbar{padding:10px 16px;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap}
.ag-toggle{background:var(--bg3);border:1px solid var(--border2);color:var(--txt3);width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;flex-shrink:0}
.ag-toggle:hover{background:var(--accent3);color:var(--accent)}
#acc-sel{background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;outline:none;cursor:pointer;min-width:180px;max-width:280px}
.model-badge{background:var(--accent3);color:var(--accent);font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap}
.topbar-actions{margin-left:auto;display:flex;gap:6px}
.tb-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt3);padding:6px 12px;border-radius:8px;font-size:11px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .12s}
.tb-btn:hover{background:var(--accent3);color:var(--accent);border-color:var(--accent)}
.tb-btn.danger:hover{background:rgba(231,76,60,.12);color:var(--danger);border-color:var(--danger)}
#ag-msgs{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;scroll-behavior:smooth}
.ag-msg{display:flex;gap:10px;max-width:88%;animation:msgIn .2s ease}
@keyframes msgIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.ag-msg.user{align-self:flex-end;flex-direction:row-reverse}
.ag-msg .av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;background:var(--bg3)}
.ag-msg.user .av{background:var(--accent3)}
.ag-msg .bbl{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:11px 14px;font-size:13px;line-height:1.65;color:var(--txt);word-break:break-word;min-width:40px}
.ag-msg.user .bbl{background:var(--accent3);border-color:var(--accent)}
.bbl strong{color:var(--accent)}
.bbl em{color:var(--txt2)}
.bbl code{background:var(--bg3);padding:1px 5px;border-radius:4px;font-size:11px;font-family:monospace}
.bbl table{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px}
.bbl table th{background:var(--bg3);color:var(--txt3);padding:6px 8px;text-align:left;font-size:11px;font-weight:700;border-bottom:1px solid var(--border2)}
.bbl table td{padding:5px 8px;border-bottom:1px solid var(--border);color:var(--txt2)}
.tools-row{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.tb{background:var(--bg3);color:var(--txt3);font-size:10px;padding:2px 7px;border-radius:20px;border:1px solid var(--border2)}
.confirm-box{background:rgba(243,156,18,.07);border:1px solid rgba(243,156,18,.4);border-radius:10px;padding:12px 14px;margin-top:10px}
.confirm-box .ct{font-size:12px;font-weight:700;color:var(--warn);margin-bottom:8px}
.confirm-item{font-size:12px;color:var(--txt2);padding:5px 0;border-bottom:1px solid rgba(243,156,18,.12);display:flex;align-items:center;gap:7px}
.confirm-item:last-of-type{border:none}
.confirm-btns{display:flex;gap:8px;margin-top:10px}
.btn-yes{background:var(--success);color:#fff;border:none;border-radius:7px;padding:8px 18px;font-size:12px;font-weight:700;cursor:pointer}
.btn-yes:hover{opacity:.88}
.btn-no{background:var(--bg3);color:var(--txt3);border:1px solid var(--border2);border-radius:7px;padding:8px 18px;font-size:12px;cursor:pointer}
.btn-no:hover{background:rgba(231,76,60,.1);color:var(--danger);border-color:var(--danger)}
.res-item{font-size:12px;padding:4px 0;display:flex;align-items:center;gap:6px;color:var(--txt2)}
#ag-input-area{padding:14px 16px;background:var(--bg2);border-top:1px solid var(--border);flex-shrink:0}
#ag-form{display:flex;gap:8px;align-items:flex-end}
#ag-input{flex:1;background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:10px 14px;border-radius:10px;font-size:13px;outline:none;resize:none;min-height:42px;max-height:130px;line-height:1.5;font-family:inherit;transition:border .15s}
#ag-input:focus{border-color:var(--accent)}
#ag-send{background:var(--accent);color:#fff;border:none;border-radius:10px;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:17px;transition:all .15s}
#ag-send:hover{opacity:.88}
#ag-send:disabled{opacity:.4;cursor:not-allowed}
#ag-img-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt3);border-radius:10px;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:18px;transition:all .15s}
#ag-img-btn:hover{background:var(--accent3);color:var(--accent);border-color:var(--accent)}
#ag-img-btn.has-img{background:var(--accent3);color:var(--accent);border-color:var(--accent)}
#ag-img-preview{display:none;align-items:center;gap:8px;padding:8px 12px;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;margin-bottom:8px}
#ag-img-preview img{width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border2)}
#ag-img-preview .img-name{font-size:11px;color:var(--txt2);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#ag-img-preview .img-remove{background:none;border:none;color:var(--txt3);cursor:pointer;font-size:16px;padding:0 4px;line-height:1}
#ag-img-preview .img-remove:hover{color:var(--danger)}
#ag-img-uploading{display:none;font-size:11px;color:var(--txt3);padding:4px 0;align-items:center;gap:6px}
.input-hint{font-size:10px;color:var(--txt3);margin-top:5px;text-align:center}
.typing-dots{display:flex;gap:4px;padding:2px 0;align-items:center}
.typing-dots span{width:6px;height:6px;border-radius:50%;background:var(--txt3);animation:dp 1.2s infinite}
.typing-dots span:nth-child(2){animation-delay:.2s}.typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes dp{0%,80%,100%{transform:scale(.8);opacity:.4}40%{transform:scale(1.2);opacity:1}}
#ag-welcome{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:40px 20px;text-align:center}
.wlc-ico{font-size:52px;margin-bottom:14px}
.wlc-title{font-size:22px;font-weight:800;color:var(--txt);margin:0 0 8px}
.wlc-sub{font-size:13px;color:var(--txt2);max-width:420px;margin:0 0 20px;line-height:1.7}
.wlc-caps{display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:480px;margin-bottom:20px;text-align:left}
.wlc-cap{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--txt2)}
.wlc-cap strong{display:block;font-size:11px;color:var(--txt);margin-bottom:2px}
.wlc-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;max-width:500px}
.wlc-chip{background:var(--bg3);border:1px solid var(--border2);color:var(--txt2);padding:7px 14px;border-radius:20px;font-size:12px;cursor:pointer;transition:all .15s}
.wlc-chip:hover{background:var(--accent3);color:var(--accent);border-color:var(--accent)}
@media(max-width:768px){#ag-side{display:none}#ag-wrap{height:calc(100vh - 112px)}.wlc-caps{grid-template-columns:1fr}}
</style>

<div id="ag-wrap">
  <!-- Sidebar -->
  <div id="ag-side">
    <div class="ag-side-head">
      <h3>🤖 Agente ADS IA</h3>
      <div class="prov-row">
        <button class="prov-btn <?= $hasOpenAI?'active':'' ?>" id="btn-openai" onclick="setProvider('openai')">◆ GPT</button>
        <button class="prov-btn <?= (!$hasOpenAI&&$hasAnthropic)?'active':'' ?>" id="btn-anthropic" onclick="setProvider('anthropic')">◎ Claude</button>
      </div>
      <select class="ag-sel" id="sel-model" onchange="setModel(this.value)">
        <optgroup label="◆ GPT (OpenAI)">
          <?php foreach ($models['openai'] as $m): ?>
          <option value="openai::<?= $m['id'] ?>"><?= $m['name'] ?> — <?= $m['price'] ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="◎ Claude (Anthropic)">
          <?php foreach ($models['anthropic'] as $m): ?>
          <option value="anthropic::<?= $m['id'] ?>"><?= $m['name'] ?> — <?= $m['price'] ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
      <?php if (!$hasAnthropic): ?>
      <div id="ant-key-row" class="key-row">
        <input type="password" class="key-inp" id="ant-key-inp" placeholder="Chave Anthropic (sk-ant-...)">
        <button class="key-save" onclick="saveAnthropicKey()">Salvar</button>
      </div>
      <div class="key-ok" id="key-ok-msg">✓ Chave salva!</div>
      <?php endif; ?>
      <?php if (!$hasOpenAI && !$hasAnthropic): ?>
      <div class="no-key-warn">⚠️ Configure uma chave em <a href="<?= APP_URL ?>/ai">Análise IA</a> ou adicione a chave Anthropic acima.</div>
      <?php endif; ?>
    </div>

    <div class="ag-hist-section">
      <?php if (!empty($recentConvs)): ?>
      <div class="hist-head">💬 Conversas Recentes</div>
      <?php foreach ($recentConvs as $conv): ?>
      <div class="conv-item" id="conv-<?= $conv['id'] ?>" onclick="loadConversation(<?= $conv['id'] ?>)">
        <div class="ci-body">
        <div class="ci-title"><?= e($conv['title']) ?></div>
        <div class="ci-meta"><span class="ci-prov"><?= strtoupper($conv['provider']) ?></span><?= e($conv['account_name']??'') ?> · <?= date('d/m',strtotime($conv['created_at'])) ?></div>
        </div>
        <button class="ci-del" title="Excluir conversa" onclick="event.stopPropagation();deleteConversation(<?= $conv['id'] ?>)">🗑️</button>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="ag-sugs">
      <h4>💡 Sugestões</h4>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Liste todas as campanhas ativas com gasto, CTR e CPM dos últimos 30 dias"><strong>📋 Relatório de Campanhas</strong>Campanhas ativas com métricas</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Encontre todos os conjuntos com CPC acima de R$ 3,00 e CTR abaixo de 1% nos últimos 7 dias. Liste-os e diga quais pausar."><strong>🔍 Encontrar o que está ruim</strong>CPC alto ou CTR baixo</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Analise todas as campanhas ativas, identifique as 3 piores e proponha ações para otimizar ou pausar cada uma."><strong>🎯 Otimizar tudo</strong>IA analisa e propõe ações</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Me mostre os dados demográficos (idade e gênero) das campanhas ativas com mais gasto nos últimos 30 dias"><strong>👥 Dados demográficos</strong>Idade e gênero do público</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Relatório completo dos últimos 30 dias: gasto total, impressões, alcance, CTR médio, CPC médio, frequência e principais conversões"><strong>📊 Relatório Completo</strong>Resumo executivo 30 dias</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Pause todos os conjuntos com CPM acima de R$ 60 nos últimos 7 dias. Mostre a lista antes."><strong>⏸ Pausar CPM alto</strong>Pausa conjuntos caros em lote</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Quero criar uma nova campanha de tráfego para o Brasil. Me ajude passo a passo com nome, objetivo, orçamento e segmentação."><strong>🆕 Criar Campanha</strong>Assistência passo a passo</button>
      <button class="sug-btn" onclick="useSug(this.dataset.m)" data-m="Duplique a campanha com melhor ROAS dos últimos 30 dias"><strong>📋 Duplicar melhor campanha</strong>Copia a de maior ROAS</button>
    </div>
  </div>

  <!-- Main -->
  <div id="ag-main">
    <div id="ag-topbar">
      <button class="ag-toggle" onclick="toggleSidebar()">☰</button>
      <select id="acc-sel" onchange="setAccount(this.value)">
        <option value="">— Selecione uma conta —</option>
        <?php foreach ($accounts as $acc): ?>
        <option value="<?= $acc['id'] ?>"><?php if($acc['client_name']): ?><?= e($acc['client_name']) ?> — <?php endif; ?><?= e($acc['account_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="model-badge" id="model-badge">GPT-4.1 Mini</span>
      <div class="topbar-actions">
        <a href="<?= APP_URL ?>/ads-agent/history" class="tb-btn">📋 Ações Executadas</a>
        <button class="tb-btn danger" onclick="clearChat()">🗑 Nova Conversa</button>
      </div>
    </div>

    <div id="ag-msgs">
      <div id="ag-welcome">
        <div class="wlc-ico">🤖</div>
        <h2 class="wlc-title">Agente ADS com IA</h2>
        <p class="wlc-sub">Selecione uma conta e converse em linguagem natural. Posso analisar, criar, otimizar e gerenciar suas campanhas do Meta Ads — sempre com sua confirmação antes de executar qualquer ação.</p>
        <div class="wlc-caps">
          <div class="wlc-cap"><strong>📊 Análise completa</strong>Métricas, demográfico e comparações entre campanhas</div>
          <div class="wlc-cap"><strong>🔍 Identificar problemas</strong>Encontra o que está ruim e propõe o que fazer</div>
          <div class="wlc-cap"><strong>⚡ Ações em lote</strong>Pausa ou ativa múltiplos itens de uma só vez</div>
          <div class="wlc-cap"><strong>🆕 Criar com segmentação</strong>Campanhas e conjuntos com público detalhado</div>
        </div>
        <div class="wlc-chips">
          <span class="wlc-chip" onclick="useSug('Liste as campanhas ativas com métricas dos últimos 7 dias')">📋 Ver campanhas</span>
          <span class="wlc-chip" onclick="useSug('Qual é o saldo da conta?')">💰 Ver saldo</span>
          <span class="wlc-chip" onclick="useSug('Encontre conjuntos com CPC alto e CTR baixo nos últimos 7 dias')">📉 O que está ruim</span>
          <span class="wlc-chip" onclick="useSug('Relatório completo dos últimos 30 dias')">📊 Relatório</span>
          <span class="wlc-chip" onclick="useSug('Me ajude a criar uma campanha de tráfego nova para o Brasil')">🆕 Nova campanha</span>
        </div>
      </div>
    </div>

    <div id="ag-input-area">
      <input type="file" id="ag-img-file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/mpeg" style="display:none" onchange="handleImageSelect(this)">
      <div id="ag-img-preview">
        <img id="ag-img-thumb" src="" alt="preview">
        <span class="img-name" id="ag-img-name"></span>
        <button class="img-remove" onclick="removeImage()" title="Remover imagem">✕</button>
      </div>
      <div id="ag-img-uploading"><div class="typing-dots"><span></span><span></span><span></span></div> Enviando imagem para o Meta...</div>
      <div id="ag-form">
        <button id="ag-img-btn" onclick="document.getElementById('ag-img-file').click()" title="Anexar imagem (JPG, PNG) ou vídeo (MP4, MOV)">📎</button>
        <textarea id="ag-input" placeholder="Ex: Quais campanhas estão com CPC alto? / Otimize o que está ruim / Crie uma campanha de leads..." rows="1" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
        <button id="ag-send" onclick="sendMsg()">➤</button>
      </div>
      <div class="input-hint">📎 Imagem ou vídeo · Ações de escrita sempre pedem confirmação · Enter para enviar · Shift+Enter nova linha</div>
    </div>
  </div>
</div>

<script>
const CSRF='<?= $_SESSION['csrf_token']??'' ?>';const APP_URL='<?= APP_URL ?>';
let curProvider='<?= $hasOpenAI?'openai':($hasAnthropic?'anthropic':'openai') ?>',curModel='<?= $hasOpenAI?'gpt-4.1-mini':'claude-haiku-4-5-20251001' ?>',curAccount=0,curConvId=0,chatHistory=[],pendingActs=[],pendingAccId=0,loading=false;

document.addEventListener('DOMContentLoaded',()=>{
  const sel=document.getElementById('sel-model');
  for(let o of sel.options){if(o.value.startsWith(curProvider+'::')){sel.value=o.value;curModel=o.value.split('::')[1];break;}}
  updateBadge();
});

function setProvider(p){curProvider=p;document.getElementById('btn-openai').classList.toggle('active',p==='openai');document.getElementById('btn-anthropic').classList.toggle('active',p==='anthropic');const sel=document.getElementById('sel-model');for(let o of sel.options){if(o.value.startsWith(p+'::')){sel.value=o.value;curModel=o.value.split('::')[1];break;}}updateBadge();}
function setModel(v){const[p,m]=v.split('::');curProvider=p;curModel=m;document.getElementById('btn-openai').classList.toggle('active',p==='openai');document.getElementById('btn-anthropic').classList.toggle('active',p==='anthropic');updateBadge();}
function updateBadge(){const L={'gpt-4.1':'GPT-4.1','gpt-4o':'GPT-4o','gpt-4.1-mini':'GPT-4.1 Mini','gpt-4o-mini':'GPT-4o Mini','claude-sonnet-4-20250514':'Sonnet 4','claude-haiku-4-5-20251001':'Haiku 4.5'};document.getElementById('model-badge').textContent=(curProvider==='anthropic'?'◎':'◆')+' '+(L[curModel]||curModel);}
function setAccount(v){curAccount=parseInt(v)||0;}
function toggleSidebar(){document.getElementById('ag-side').classList.toggle('collapsed');}
function useSug(msg){const inp=document.getElementById('ag-input');inp.value=msg;autoResize(inp);inp.focus();}
function handleKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}}
function autoResize(el){el.style.height='auto';el.style.height=Math.min(el.scrollHeight,130)+'px';}

function clearChat(){chatHistory=[];pendingActs=[];curConvId=0;document.querySelectorAll('.conv-item').forEach(el=>el.classList.remove('active'));document.getElementById('ag-msgs').innerHTML='<div id="ag-welcome"><div class="wlc-ico">🤖</div><h2 class="wlc-title">Agente ADS com IA</h2><p class="wlc-sub">Selecione uma conta e comece a conversar.</p><div class="wlc-chips"><span class="wlc-chip" onclick="useSug(\'Liste as campanhas ativas com métricas dos últimos 7 dias\')">📋 Ver campanhas</span><span class="wlc-chip" onclick="useSug(\'Qual é o saldo da conta?\')">💰 Ver saldo</span><span class="wlc-chip" onclick="useSug(\'Encontre conjuntos com CPC alto e CTR baixo nos últimos 7 dias\')">📉 O que está ruim</span><span class="wlc-chip" onclick="useSug(\'Relatório completo dos últimos 30 dias\')">📊 Relatório</span></div></div>';}

// ── Image upload state ────────────────────────────────────────────────────
let pendingImageFile = null;
let pendingImageUrl  = null;  // URL pública após upload para o Meta

function handleImageSelect(input) {
  const file = input.files[0];
  if (!file) return;
  const isVid = file.type.startsWith('video/');
  const maxMB  = isVid ? 200 : 10;
  if (file.size > maxMB * 1024 * 1024) { showToast((isVid ? 'Vídeo' : 'Imagem') + ' muito grande (máx ' + maxMB + 'MB)', 'warn'); return; }
  pendingImageFile = file;
  pendingImageUrl  = null;
  // Show preview
  const reader = new FileReader();
  reader.onload = e => {
    const thumb = document.getElementById('ag-img-thumb');
    if (isVid) {
      thumb.src = ''; // no video preview in img tag
      thumb.style.display = 'none';
    } else {
      thumb.src = e.target.result;
      thumb.style.display = 'block';
    }
    const icon = isVid ? '🎬 ' : '';
    document.getElementById('ag-img-name').textContent = icon + file.name;
    document.getElementById('ag-img-preview').style.display = 'flex';
    document.getElementById('ag-img-btn').classList.add('has-img');
  };
  reader.readAsDataURL(file);
  // Clear input so same file can be re-selected
  input.value = '';
}

function removeImage() {
  pendingImageFile = null;
  pendingImageUrl  = null;
  document.getElementById('ag-img-preview').style.display = 'none';
  document.getElementById('ag-img-btn').classList.remove('has-img');
  document.getElementById('ag-img-thumb').src = '';
}

async function uploadImageToMeta(file) {
  // Convert file to base64, send to backend which will upload to Meta via upload_image tool
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = async e => {
      try {
        const base64 = e.target.result; // data:image/jpeg;base64,....
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('account_id', curAccount);
        fd.append('image_data', base64);
        fd.append('image_name', file.name);
        const res  = await fetch(APP_URL + '/ads-agent/upload-image', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok && data.type === 'video' && data.video_id) resolve({ type: 'video', video_id: data.video_id });
        else if (data.ok && data.hash) resolve({ type: 'image', hash: data.hash, url: data.url || '' });
        else reject(data.error || 'Falha no upload');
      } catch(err) { reject(err.message); }
    };
    reader.onerror = () => reject('Falha ao ler arquivo');
    reader.readAsDataURL(file);
  });
}

async function sendMsg(){
  const inp=document.getElementById('ag-input');
  let msg=inp.value.trim();
  if((!msg && !pendingImageFile)||loading)return;
  if(!curAccount){showToast('Selecione uma conta de anúncio primeiro','warn');return;}
  document.getElementById('ag-welcome')?.remove();

  // If there's an image, upload it to Meta first, then inject the URL into the message
  if (pendingImageFile) {
    document.getElementById('ag-img-uploading').style.display = 'flex';
      document.getElementById('ag-img-uploading').querySelector('.typing-dots')?.parentNode && (document.getElementById('ag-img-uploading').lastChild.textContent = pendingImageFile.type.startsWith('video/') ? ' Enviando vídeo para o Meta (pode demorar)...' : ' Enviando imagem para o Meta...');
    document.getElementById('ag-send').disabled = true;
    try {
      const mediaResult = await uploadImageToMeta(pendingImageFile);
      let mediaNote;
      if (mediaResult.type === 'video') {
        mediaNote = '[vídeo carregado no Meta — video_id: ' + mediaResult.video_id + ']';
      } else {
        mediaNote = '[imagem carregada no Meta — image_hash: ' + mediaResult.hash + (mediaResult.url ? ' | url: ' + mediaResult.url : '') + ']';
      }
      msg = msg ? msg + '\n' + mediaNote : mediaNote;
      showToast('✅ Imagem enviada ao Meta!', 'success');
    } catch(err) {
      showToast('❌ Erro no upload da imagem: ' + err, 'warn');
      document.getElementById('ag-img-uploading').style.display = 'none';
      document.getElementById('ag-send').disabled = false;
      return;
    }
    document.getElementById('ag-img-uploading').style.display = 'none';
    removeImage();
  }

  // Show user message (without the raw URL clutter — show friendly version)
  const displayMsg = inp.value.trim() + (pendingImageFile ? ' 📎 [imagem]' : '');
  addMsg('user', msg.replace(/\[imagem anexada: [^\]]+\]/g, '📎 <em>imagem anexada</em>'));
  chatHistory.push({role:'user',content:msg});
  inp.value='';inp.style.height='auto';
  const tid=addTyping();loading=true;document.getElementById('ag-send').disabled=true;
  try{
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('provider',curProvider);fd.append('model',curModel);fd.append('account_id',curAccount);fd.append('conv_id',curConvId);fd.append('message',msg);fd.append('history',JSON.stringify(chatHistory.slice(-14)));
    const res=await fetch(APP_URL+'/ads-agent/chat',{method:'POST',body:fd}),data=await res.json();
    removeTyping(tid);
    if(!data.ok){addMsg('assistant','❌ **Erro:** '+(data.error||'Falha na comunicação.'));}
    else{if(data.conv_id){curConvId=data.conv_id;refreshConvList(data.conv_id,msg);}pendingActs=data.pending_actions||[];pendingAccId=curAccount;addMsg('assistant',data.reply||'',data.tools_executed||[],pendingActs);chatHistory.push({role:'assistant',content:data.reply||''});}
  }catch(e){removeTyping(tid);addMsg('assistant','❌ Erro de conexão: '+e.message);}
  loading=false;document.getElementById('ag-send').disabled=false;
}

async function confirmActions(accept){
  if(!accept){pendingActs=[];addMsg('assistant','✋ Ação cancelada.');chatHistory.push({role:'assistant',content:'Ação cancelada.'});return;}
  const acts=[...pendingActs];pendingActs=[];
  addMsg('user','✅ Confirmar e executar as ações acima');chatHistory.push({role:'user',content:'Confirmar e executar.'});
  const tid=addTyping();loading=true;document.getElementById('ag-send').disabled=true;
  const results=[];
  for(const act of acts){
    try{const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('account_id',pendingAccId||curAccount);fd.append('action',act.tool);fd.append('params',JSON.stringify(act.params));fd.append('conv_id',curConvId);
      const res=await fetch(APP_URL+'/ads-agent/execute',{method:'POST',body:fd}),data=await res.json();
      results.push({label:act.label,ok:data.ok||data.success,error:data.error||null,newId:data.id||null});
    }catch(e){results.push({label:act.label,ok:false,error:e.message});}
  }
  removeTyping(tid);
  let html='<strong>Resultado das ações:</strong><br>';
  results.forEach(r=>{const icon=r.ok?'✅':'❌';html+=`<div class="res-item">${icon} ${esc(r.label)}${r.newId?` (ID: ${esc(r.newId)})`:''} ${r.error?`<span style="color:var(--danger)">— ${esc(r.error)}</span>`:''}</div>`;});
  const ok=results.filter(r=>r.ok).length,fail=results.length-ok;
  html+=`<br><small style="color:var(--txt3)">${ok} executada(s)${fail>0?` · ${fail} com erro`:''}</small>`;
  addMsgRaw('assistant',html,[],[]);chatHistory.push({role:'assistant',content:`${ok} ação(ões) executada(s) com sucesso.`});
  loading=false;document.getElementById('ag-send').disabled=false;
}

async function loadConversation(id){
  try{
    const res=await fetch(APP_URL+'/ads-agent/load-conv?id='+id),data=await res.json();
    if(!data.ok)return;
    curConvId=id;chatHistory=data.messages||[];
    document.querySelectorAll('.conv-item').forEach(el=>el.classList.remove('active'));
    document.getElementById('conv-'+id)?.classList.add('active');
    if(data.conv?.account_id){document.getElementById('acc-sel').value=data.conv.account_id;curAccount=parseInt(data.conv.account_id);}
    if(data.conv?.provider)curProvider=data.conv.provider;
    if(data.conv?.model)curModel=data.conv.model;
    updateBadge();
    const msgsEl=document.getElementById('ag-msgs');msgsEl.innerHTML='';
    chatHistory.forEach(m=>{if(m.role==='user'||m.role==='assistant')addMsg(m.role,m.content||'');});
  }catch(e){}
}

async function deleteConversation(id){
  if(!confirm('Excluir esta conversa? Essa ação não pode ser desfeita.'))return;
  try{
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',id);
    const res=await fetch(APP_URL+'/ads-agent/delete-conv',{method:'POST',body:fd}),data=await res.json();
    if(data.ok){
      document.getElementById('conv-'+id)?.remove();
      if(curConvId===id){curConvId=null;chatHistory=[];document.getElementById('ag-msgs').innerHTML='';}
      showToast('Conversa excluída','success');
    } else {
      showToast(data.error||'Erro ao excluir','warn');
    }
  }catch(e){showToast('Erro ao excluir','warn');}
}

function refreshConvList(id,title){
  const sec=document.querySelector('.ag-hist-section');let head=sec.querySelector('.hist-head');
  if(!head){head=document.createElement('div');head.className='hist-head';head.textContent='💬 Conversas Recentes';sec.prepend(head);}
  const ex=document.getElementById('conv-'+id);if(ex){document.querySelectorAll('.conv-item').forEach(el=>el.classList.remove('active'));ex.classList.add('active');return;}
  const el=document.createElement('div');el.className='conv-item active';el.id='conv-'+id;
  el.innerHTML=`<div class="ci-title">${esc(title.substring(0,60))}</div><div class="ci-meta"><span class="ci-prov">${curProvider.toUpperCase()}</span> Agora</div>`;
  el.onclick=()=>loadConversation(id);head.after(el);
  document.querySelectorAll('.conv-item').forEach(e=>{if(e!==el)e.classList.remove('active');});
}

function addMsg(role,text,toolsExec,pending){addMsgRaw(role,fmt(text),toolsExec||[],pending||[]);}
function addMsgRaw(role,html,toolsExec,pending){
  const msgs=document.getElementById('ag-msgs'),div=document.createElement('div');div.className='ag-msg '+role;
  let extra='';
  if(toolsExec&&toolsExec.length){extra+='<div class="tools-row">';toolsExec.forEach(t=>{const dbg=t._debug_params?` <small style="opacity:.6;font-size:10px;">[${JSON.stringify(t._debug_params)}]</small>`:'';extra+=`<span class="tb">🔧 ${esc(tl(t.tool))}${t.summary?' · '+esc(t.summary):''}${dbg}</span>`;});extra+='</div>';}
  if(pending&&pending.length){extra+=`<div class="confirm-box"><div class="ct">⚠️ Confirmar ${pending.length} ação(ões) antes de executar:</div>`;pending.forEach(a=>{extra+=`<div class="confirm-item">🔸 ${esc(a.label)}</div>`;});extra+=`<div class="confirm-btns"><button class="btn-yes" onclick="confirmActions(true)">✅ Confirmar e executar</button><button class="btn-no" onclick="confirmActions(false)">✗ Cancelar</button></div></div>`;}
  div.innerHTML=`<div class="av">${role==='user'?'👤':'🤖'}</div><div class="bbl">${html}${extra}</div>`;
  msgs.appendChild(div);msgs.scrollTop=msgs.scrollHeight;
}

function fmt(t){if(!t)return'';return esc(t).replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\*(.*?)\*/g,'<em>$1</em>').replace(/`([^`\n]+)`/g,'<code>$1</code>').replace(/\n/g,'<br>');}
function tl(t){const m={'get_account_info':'Info Conta','get_account_balance':'Saldo','list_campaigns':'Campanhas','list_adsets':'Conjuntos','list_ads':'Anúncios','get_insights':'Métricas','get_insights_by_date':'Métricas/Data','get_insights_breakdown':'Demográfico','compare_campaigns':'Comparação','find_underperforming':'Análise Desempenho','list_audiences':'Públicos','list_pixels':'Pixels','list_creatives':'Criativos','search_interests':'Interesses'};return m[t]||t.replace(/_/g,' ');}
function addTyping(){const msgs=document.getElementById('ag-msgs'),d=document.createElement('div');d.className='ag-msg assistant';d.id='typing-'+Date.now();d.innerHTML='<div class="av">🤖</div><div class="bbl"><div class="typing-dots"><span></span><span></span><span></span></div></div>';msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;return d.id;}
function removeTyping(id){document.getElementById(id)?.remove();}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function showToast(msg,type){const t=document.createElement('div');t.style.cssText=`position:fixed;bottom:20px;right:20px;background:var(--${type==='warn'?'warn':'accent'});color:#fff;padding:10px 18px;border-radius:9px;font-size:13px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.25)`;t.textContent=msg;document.body.appendChild(t);setTimeout(()=>t.remove(),3500);}
async function saveAnthropicKey(){const key=document.getElementById('ant-key-inp').value.trim();if(!key)return;const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('anthropic_key',key);const res=await fetch(APP_URL+'/ads-agent/save-key',{method:'POST',body:fd}),data=await res.json();if(data.ok){document.getElementById('key-ok-msg').style.display='block';document.getElementById('ant-key-row').style.display='none';showToast('Chave Anthropic salva!','success');}else showToast(data.error||'Erro ao salvar','warn');}
</script>
