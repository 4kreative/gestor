<?php
// views/ai/index.php

$hasGroq   = !empty($aiSettings['ai_groq_key']);
$hasOpenAI = !empty($aiSettings['ai_openai_key']);
$hasGemini = !empty($aiSettings['ai_gemini_key']);
$hasAnyKey = $hasGroq || $hasOpenAI || $hasGemini;
$defProv   = $aiSettings['ai_default_provider'] ?? 'groq';
$defModel  = $aiSettings['ai_default_model']    ?? 'llama-3.1-8b-instant';

function aiSendForm(string $pfx, array $instances, array $clients, array $groups): string {
    $wpOpts = '<option value="">— Instância —</option>';
    foreach ($instances as $i) $wpOpts .= '<option value="'.htmlspecialchars($i['id']).'">'.htmlspecialchars($i['instance_name']).' · '.htmlspecialchars($i['phone_number']??'').'</option>';
    $cliOpts = '<option value="">— Cliente —</option>';
    foreach ($clients as $c) $cliOpts .= '<option value="'.htmlspecialchars($c['id']).'" data-phone="'.htmlspecialchars($c['phone']??'').'">'.htmlspecialchars($c['name']).' · '.htmlspecialchars($c['phone']??'sem telefone').'</option>';
    return '
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
      <div><label class="form-label" style="font-size:11px">Instância WhatsApp</label>
        <select id="'.$pfx.'WpId" class="form-control form-control-sm" onchange="aiLoadGroups(\''.$pfx.'\')">'.$wpOpts.'</select></div>
      <div><label class="form-label" style="font-size:11px">Enviar para</label>
        <select id="'.$pfx.'Tipo" class="form-control form-control-sm" onchange="sndTipo(\''.$pfx.'\',this.value)">
          <option value="phone">📱 Número</option><option value="client">👤 Cliente</option><option value="group">👥 Grupo WA</option>
        </select></div>
    </div>
    <div id="'.$pfx.'Ph" style="margin-bottom:8px"><input type="tel" id="'.$pfx.'Phone" class="form-control form-control-sm" placeholder="5581999999999"></div>
    <div id="'.$pfx.'Cl" style="display:none;margin-bottom:8px"><select id="'.$pfx.'CliSel" class="form-control form-control-sm" onchange="var o=this.options[this.selectedIndex];document.getElementById(\''.$pfx.'Phone\').value=o.getAttribute(\'data-phone\')||\'\';\document.getElementById(\''.$pfx.'ClientId\').value=this.value;">'.$cliOpts.'</select></div>
    <input type="hidden" id="'.$pfx.'ClientId">
    <div id="'.$pfx.'Gr" style="display:none;margin-bottom:8px">
      <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px">
        <select id="'.$pfx.'GrpSel" class="form-control form-control-sm" style="flex:1"><option value="">— Selecione o grupo —</option></select>
        <button type="button" class="btn btn-secondary btn-sm" onclick="aiLoadGroups(\''.$pfx.'\')" style="white-space:nowrap;flex-shrink:0">🔄 Buscar</button>
      </div>
      <div id="'.$pfx.'GrpStatus" style="font-size:11px;color:var(--txt3)"></div>
    </div>
    <label class="form-label" style="font-size:11px;margin-bottom:4px;display:block">Mensagem <span style="color:var(--txt3)">(editável antes do envio)</span></label>
    <textarea id="'.$pfx.'Msg" style="width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius);padding:9px 11px;color:var(--txt);font-family:var(--font);font-size:12px;line-height:1.6;resize:vertical;min-height:130px;box-sizing:border-box"></textarea>
    <div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap">
      <button class="btn btn-primary btn-sm" onclick="sndWp(\''.$pfx.'\')"><span class="material-icons-outlined" style="font-size:14px">send</span> Enviar Agora</button>
      <button class="btn btn-secondary btn-sm" onclick="sndReset(\''.$pfx.'\')"><span class="material-icons-outlined" style="font-size:14px">refresh</span> Restaurar</button>
      <span id="'.$pfx.'St" style="font-size:12px"></span>
    </div>';
}
?>
<style>
.ai-grid{display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start}
.ai-panel{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:20px;position:sticky;top:80px}
.ai-panel h3{font-size:10px;font-weight:700;color:var(--txt3);margin:14px 0 8px;display:flex;align-items:center;gap:5px;text-transform:uppercase;letter-spacing:.5px}
.ai-panel h3:first-child{margin-top:0}
.prov-tabs{display:flex;gap:5px;margin-bottom:10px}
.prov-tab{flex:1;padding:7px 4px;border-radius:var(--radius);border:1.5px solid var(--border2);background:var(--bg3);color:var(--txt2);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;font-family:var(--font);line-height:1.5}
.prov-tab.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.prov-tab:hover:not(.active){background:var(--bg4);color:var(--txt)}
.model-list{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;max-height:240px;overflow-y:auto}
.model-opt{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:var(--radius);border:1.5px solid var(--border2);background:var(--bg3);cursor:pointer;transition:all .15s}
.model-opt:hover,.model-opt.selected{border-color:var(--accent);background:var(--accent3)}
.model-opt input{accent-color:var(--accent);flex-shrink:0}
.model-name{font-size:11px;font-weight:600;color:var(--txt)}.model-price{font-size:10px;color:var(--txt3)}
.tier-badge{font-size:9px;font-weight:700;padding:2px 6px;border-radius:6px;white-space:nowrap;margin-left:auto}
.tier-free{background:rgba(39,174,96,.15);color:var(--success)}.tier-cheap{background:rgba(0,120,255,.12);color:var(--accent)}
.tier-mid{background:rgba(243,156,18,.12);color:var(--warn)}.tier-premium{background:rgba(231,76,60,.12);color:var(--danger)}
.periodo-wrap{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.periodo-custom{display:none;grid-template-columns:1fr 1fr;gap:6px}
.camp-opt-active{color:var(--success)}.camp-opt-paused{color:var(--warn)}.camp-opt-other{color:var(--txt3)}
.key-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:16px;margin-bottom:16px}
.key-box details summary{cursor:pointer;font-size:13px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px;list-style:none;user-select:none}
.key-box details summary::after{content:'▼';font-size:10px;color:var(--txt3);margin-left:auto;transition:transform .2s}
.key-box details[open] summary::after{transform:rotate(180deg)}
.key-form{margin-top:14px;display:flex;flex-direction:column;gap:10px}
.key-row{display:grid;grid-template-columns:85px 1fr;align-items:center;gap:8px}
.key-row label{font-size:12px;font-weight:600;color:var(--txt2);display:flex;align-items:center;gap:5px}
.key-in{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:7px 10px;color:var(--txt);font-size:12px;font-family:var(--font);width:100%;box-sizing:border-box}
.key-in:focus{outline:none;border-color:var(--accent)}
.no-key{background:rgba(243,156,18,.08);border:1px solid rgba(243,156,18,.3);border-radius:var(--radius);padding:11px 14px;margin-bottom:16px;font-size:12px;color:var(--warn);display:flex;align-items:center;gap:8px}
.dot-on{color:var(--success)}.dot-off{color:var(--txt3)}
.chat-area{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);display:flex;flex-direction:column;min-height:520px;overflow:hidden}
.chat-messages{flex:1;padding:20px;display:flex;flex-direction:column;gap:14px;overflow-y:auto;max-height:500px}
.chat-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:300px;color:var(--txt3);text-align:center;gap:12px}
.chat-empty .material-icons-outlined{font-size:52px;opacity:.12}
.bubble-user{align-self:flex-end;max-width:75%;background:var(--accent3);border:1px solid var(--accent);border-radius:14px 14px 4px 14px;padding:10px 14px;font-size:13px;line-height:1.7;color:var(--txt);word-break:break-word}
.bubble-ai{align-self:flex-start;max-width:90%;background:var(--bg3);border:1px solid var(--border2);border-radius:14px 14px 14px 4px;padding:10px 14px;font-size:13px;line-height:1.7;color:var(--txt);word-break:break-word}
.bubble-ai strong{font-weight:700}
.bubble-ai-wrap{display:flex;flex-direction:column;align-items:flex-start;gap:4px;max-width:90%}
.bubble-ai-actions{display:flex;gap:5px;align-self:flex-start;padding-left:2px}
.bbl-btn{border:1px solid var(--border2);border-radius:var(--radius);padding:3px 9px;font-size:11px;cursor:pointer;background:var(--bg2);color:var(--txt2);font-family:var(--font);display:inline-flex;align-items:center;gap:4px;transition:background .12s}
.bbl-btn:hover{background:var(--bg4);color:var(--txt)}
.bbl-btn-wa{background:rgba(37,211,102,.08);border-color:rgba(37,211,102,.3);color:#15a352}
.bbl-btn-wa:hover{background:rgba(37,211,102,.18);color:#0e8040}
/* modal de envio WA das bolhas */
.wa-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10000;align-items:center;justify-content:center;padding:16px}
.wa-modal-overlay.open{display:flex}
.wa-modal{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:500px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.wa-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.wa-modal-hdr span{font-size:14px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px}
.wa-modal-body{padding:16px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:12px}
.wa-modal-footer{padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;flex-shrink:0}
.wa-lbl{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px}
.wa-tabs{display:flex;gap:5px}
.wa-tab{flex:1;padding:7px 5px;border:1px solid var(--border2);border-radius:var(--radius);font-size:12px;font-weight:600;cursor:pointer;background:var(--bg3);color:var(--txt2);font-family:var(--font);text-align:center;transition:all .12s}
.wa-tab:hover{background:var(--bg4);color:var(--txt)}
.wa-tab.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.wa-ta{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:9px 11px;color:var(--txt);font-family:var(--font);font-size:12px;line-height:1.6;resize:vertical;min-height:120px;box-sizing:border-box}
.wa-ta:focus{outline:none;border-color:var(--accent)}
.wa-row-lbl{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px}
.wa-regen{border:1px solid var(--border2);border-radius:var(--radius);padding:3px 9px;font-size:11px;cursor:pointer;background:var(--bg2);color:var(--txt3);font-family:var(--font);display:inline-flex;align-items:center;gap:4px}
.wa-regen:hover{background:var(--bg3);color:var(--txt2)}
.wa-regen:disabled{opacity:.4;cursor:not-allowed}
.wa-send-btn{flex:2;padding:10px;border:none;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;background:#25D366;color:#fff;font-family:var(--font);display:flex;align-items:center;justify-content:center;gap:6px}
.wa-send-btn:hover{background:#1db954}
.wa-send-btn:disabled{opacity:.45;cursor:not-allowed}
.wa-cancel-btn{flex:1;padding:10px;border:1px solid var(--border2);border-radius:var(--radius);font-size:13px;cursor:pointer;background:var(--bg2);color:var(--txt2);font-family:var(--font)}
.wa-cancel-btn:hover{background:var(--bg3)}
.wa-status{font-size:12px;text-align:center;min-height:16px;color:var(--txt3)}
.bubble-loading{align-self:flex-start;background:var(--bg3);border:1px solid var(--border2);border-radius:14px;padding:10px 16px;font-size:12px;color:var(--txt3);display:flex;align-items:center;gap:8px}
.ai-spin{width:14px;height:14px;border:2px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.ms-grid-chat{display:grid;gap:8px;margin-bottom:8px}
.ms-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:10px;text-align:center}
.ms-val{font-size:14px;font-weight:700;color:var(--txt)}.ms-lbl{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.chat-input-wrap{border-top:1px solid var(--border);padding:14px 16px;display:flex;flex-direction:column;gap:10px;flex-shrink:0}
.tab-row{display:flex;gap:5px}
.tab-btn{flex:1;padding:7px 6px;border-radius:var(--radius);border:1.5px solid var(--border2);background:var(--bg3);font-size:11px;font-weight:700;color:var(--txt2);text-align:center;cursor:pointer;font-family:var(--font);transition:all .15s}
.tab-btn.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.tab-btn:hover:not(.active){background:var(--bg4);color:var(--txt)}
.cascade-wrap{position:relative}
.cascade-trigger{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:8px 12px;color:var(--txt2);font-family:var(--font);font-size:12px;cursor:pointer;text-align:left;display:flex;align-items:center;gap:7px;transition:border-color .15s;box-sizing:border-box}
.cascade-trigger:hover,.cascade-trigger.open{border-color:var(--accent);color:var(--txt)}
.cascade-trigger .ct-label{flex:1}.cascade-trigger .ct-arrow{font-size:10px;color:var(--txt3);transition:transform .2s;flex-shrink:0}
.cascade-panel{position:absolute;bottom:calc(100% + 5px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius2);z-index:200;box-shadow:0 -4px 20px rgba(0,0,0,.18);display:none;flex-direction:column;max-height:360px;overflow:hidden}
.cascade-panel.open{display:flex}
.cascade-search{padding:10px;border-bottom:1px solid var(--border);flex-shrink:0}
.cascade-search input{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:7px 10px;color:var(--txt);font-family:var(--font);font-size:12px;outline:none;box-sizing:border-box}
.cascade-search input:focus{border-color:var(--accent)}
.cat-tabs{display:flex;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border);flex-wrap:wrap;flex-shrink:0}
.cat-tab{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;background:var(--bg3);border:1px solid var(--border2);color:var(--txt2);font-family:var(--font);white-space:nowrap}
.cat-tab.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.cascade-list{overflow-y:auto;flex:1;padding:8px 10px;display:flex;flex-wrap:wrap;gap:4px;align-content:flex-start}
.cascade-list.list-mode{flex-direction:column;flex-wrap:nowrap;gap:2px;padding:6px}
.vsec-title{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;width:100%;padding:5px 2px 3px}
.vtag{display:inline-flex;align-items:center;padding:4px 10px;border-radius:20px;font-size:11px;font-family:monospace;cursor:pointer;border:1px solid;white-space:nowrap;transition:opacity .1s}
.vtag.blue{background:rgba(0,120,255,.1);color:var(--accent);border-color:rgba(0,120,255,.25)}.vtag.blue:hover{background:var(--accent);color:#fff}
.vtag.green{background:rgba(39,174,96,.1);color:var(--success);border-color:rgba(39,174,96,.25)}.vtag.green:hover{background:var(--success);color:#fff}
.vtag.orange{background:rgba(243,156,18,.1);color:var(--warn);border-color:rgba(243,156,18,.25)}.vtag.orange:hover{background:var(--warn);color:#fff}
.vtag.purple{background:rgba(155,89,182,.1);color:#bb88ff;border-color:rgba(155,89,182,.25)}.vtag.purple:hover{background:#9b59b6;color:#fff}
.vtag.added{opacity:.35;cursor:default;text-decoration:line-through;pointer-events:none}
.cascade-empty{padding:20px;text-align:center;font-size:12px;color:var(--txt3);width:100%}
.tpl-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--radius);font-size:12px;cursor:pointer;color:var(--txt);transition:background .1s}
.tpl-item:hover{background:var(--bg3)}.tpl-item.selected{background:var(--accent3);color:var(--accent)}
.tpl-item-name{flex:1}.tpl-item-meta{font-size:10px;color:var(--txt3);font-family:monospace}
.tpl-item.selected .tpl-item-meta{color:var(--accent);opacity:.7}
.chat-input-field{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius2);padding:10px 13px;color:var(--txt);font-family:var(--font);font-size:13px;min-height:44px;display:flex;align-items:center;flex-wrap:wrap;gap:5px;cursor:text;line-height:1.9;word-break:break-word;flex:1}
.chat-token{display:inline-flex;align-items:center;gap:3px;background:var(--accent3);color:var(--accent);border:1px solid var(--accent);border-radius:6px;padding:1px 7px;font-size:11px;font-family:monospace;white-space:nowrap}
.chat-token-x{opacity:.55;cursor:pointer;font-size:10px;margin-left:1px;line-height:1}.chat-token-x:hover{opacity:1}
.chat-placeholder{color:var(--txt3);font-size:13px;pointer-events:none}
.chat-text-node{outline:none;min-width:2px;white-space:pre-wrap;color:var(--txt)}
.chat-send-row{display:flex;gap:8px;align-items:flex-start}
.send-btn-main{padding:10px 16px;background:var(--accent);color:#fff;border-radius:var(--radius);border:none;font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:opacity .15s;flex-shrink:0;height:44px}
.send-btn-main:hover{opacity:.88}.send-btn-main:disabled{opacity:.4;cursor:not-allowed}
.send-tpl-btn{width:100%;padding:9px;background:var(--accent);color:#fff;border-radius:var(--radius);border:none;font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s}
.send-tpl-btn:disabled{opacity:.35;cursor:not-allowed}.send-tpl-btn:hover:not(:disabled){opacity:.88}
.snd-box{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius2);padding:16px;margin-top:14px}
.snd-box h4{font-size:12px;font-weight:700;color:var(--txt);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.pop-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;align-items:center;justify-content:center;padding:16px}
.pop-overlay.open{display:flex}
.pop-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:720px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.pop-hdr{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid var(--border);flex-shrink:0}
.pop-title{font-size:14px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px}
.pop-close{background:none;border:none;color:var(--txt3);cursor:pointer;font-size:22px;padding:0 5px;line-height:1}
.pop-body{padding:18px;overflow-y:auto;flex:1}
.pop-footer{padding:11px 18px;border-top:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0}
.pop-send{padding:14px 18px;border-top:1px solid var(--border)}
@media(max-width:860px){.ai-grid{grid-template-columns:1fr}.ai-panel{position:relative;top:0}}
@keyframes waSpin{to{transform:rotate(360deg)}}
</style>

<div style="margin-bottom:16px;display:flex;align-items:center;gap:8px">
  <span class="material-icons-outlined" style="color:var(--accent);font-size:22px">auto_awesome</span>
  <div>
    <div style="font-size:19px;font-weight:700;color:var(--txt)">Análise de Campanhas com IA</div>
    <div style="font-size:12px;color:var(--txt3);margin-top:2px">Selecione a campanha, faça sua pergunta ou escolha um template — a IA analisa exatamente o que você pediu</div>
  </div>
</div>

<?php if (!$hasAnyKey): ?>
<div class="no-key"><span class="material-icons-outlined" style="font-size:16px">warning</span> Configure ao menos uma chave de IA abaixo para começar.</div>
<?php endif; ?>

<div class="key-box">
  <details <?= !$hasAnyKey ? 'open' : '' ?>>
    <summary>
      <span class="material-icons-outlined" style="font-size:16px">key</span>
      Chaves de API — Provedores de IA
      <?php if($hasAnyKey): ?><span style="font-size:10px;background:rgba(39,174,96,.12);color:var(--success);padding:2px 8px;border-radius:10px;margin-left:6px;font-weight:700">● Configurado</span><?php endif; ?>
    </summary>
    <form method="POST" action="<?= APP_URL ?>/ai/save-keys" class="key-form">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="key-row"><label><span class="<?= $hasGroq?'dot-on':'dot-off' ?>">●</span> ⚡ Groq</label><input type="password" name="ai_groq_key" class="key-in" placeholder="gsk_..." autocomplete="off" value="<?= $hasGroq?str_repeat('•',24):'' ?>"></div>
      <div class="key-row"><label><span class="<?= $hasGemini?'dot-on':'dot-off' ?>">●</span> ✦ Gemini</label><input type="password" name="ai_gemini_key" class="key-in" placeholder="AIza..." autocomplete="off" value="<?= $hasGemini?str_repeat('•',24):'' ?>"></div>
      <div class="key-row"><label><span class="<?= $hasOpenAI?'dot-on':'dot-off' ?>">●</span> ◆ OpenAI</label><input type="password" name="ai_openai_key" class="key-in" placeholder="sk-..." autocomplete="off" value="<?= $hasOpenAI?str_repeat('•',24):'' ?>"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span style="font-size:10px;color:var(--txt3)">Grátis: <a href="https://console.groq.com" target="_blank">Groq</a> · <a href="https://aistudio.google.com/app/apikey" target="_blank">Gemini</a> · <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a></span>
        <button type="submit" class="btn btn-primary btn-sm"><span class="material-icons-outlined" style="font-size:14px">save</span> Salvar</button>
      </div>
    </form>
  </details>
</div>

<div class="ai-grid">
  <!-- Painel lateral -->
  <div class="ai-panel">
    <h3><span class="material-icons-outlined" style="font-size:13px;color:var(--accent)">campaign</span> Campanha</h3>
    <div class="form-group" style="margin-bottom:8px">
      <label class="form-label" style="font-size:11px">Conta de Anúncios</label>
      <select id="aiAcc" class="form-control form-control-sm" onchange="onAccChange()">
        <option value="">— Selecione —</option>
        <?php foreach($accounts as $a): ?>
        <option value="<?=$a['id']?>" data-platform="<?=e($a['platform'])?>"><?=htmlspecialchars($a['account_name'])?> (<?=strtoupper($a['platform'])?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:12px">
      <label class="form-label" style="font-size:11px">Campanha</label>
      <select id="aiCamp" class="form-control form-control-sm"><option value="">— Selecione uma conta primeiro —</option></select>
      <div id="campStatus" style="font-size:10px;color:var(--txt3);margin-top:3px;display:none"></div>
    </div>
    <h3><span class="material-icons-outlined" style="font-size:13px;color:var(--accent)">date_range</span> Período</h3>
    <div class="periodo-wrap">
      <select id="aiPeriodo" class="form-control form-control-sm" onchange="onPeriodoChange()">
        <option value="today">Hoje</option><option value="yesterday">Ontem</option>
        <option value="last_7_days" selected>Últimos 7 dias</option><option value="last_15_days">Últimos 15 dias</option>
        <option value="last_30_days">Últimos 30 dias</option><option value="last_90_days">Últimos 90 dias</option>
        <option value="this_month">Este mês</option><option value="last_month">Mês passado</option>
        <option value="maximum">Máximo</option><option value="custom">📅 Personalizado</option>
      </select>
      <div class="periodo-custom" id="periodoCustom">
        <input type="date" id="aiCustomStart" class="form-control form-control-sm">
        <input type="date" id="aiCustomEnd" class="form-control form-control-sm" value="<?=date('Y-m-d')?>">
      </div>
    </div>
    <h3><span class="material-icons-outlined" style="font-size:13px;color:var(--accent)">psychology</span> Modelo de IA</h3>
    <select id="aiModelSelect" name="aiModel" class="form-control form-control-sm" onchange="selModelFromSelect(this)" style="margin-bottom:4px">
      <?php foreach($allModels as $pk => $pd):
        $on = ($pk==='groq'&&$hasGroq)||($pk==='openai'&&$hasOpenAI)||($pk==='gemini'&&$hasGemini);
      ?>
      <optgroup label="<?=htmlspecialchars($pd['icon'].' '.$pd['label'].(!$on?' (sem chave)':''))?>">
        <?php foreach($pd['models'] as $mdl):
          $sel = ($mdl['id']===$defModel&&$pk===$defProv)?'selected':'';
        ?>
        <option value="<?=htmlspecialchars($mdl['id'])?>" data-prov="<?=$pk?>" <?=$sel?> <?=!$on?'disabled':''?>>
          <?=htmlspecialchars($mdl['name'])?> <?=htmlspecialchars($mdl['price'])?>
        </option>
        <?php endforeach; ?>
      </optgroup>
      <?php endforeach; ?>
    </select>
    <!-- Radios hidden para compatibilidade -->
    <div id="modelLists" style="display:none">
      <?php foreach($allModels as $pk => $pd): ?>
      <div id="ml_<?=$pk?>">
        <?php foreach($pd['models'] as $mdl):
          $chk = ($mdl['id']===$defModel&&$pk===$defProv)?'checked':'';
        ?>
        <label class="model-opt">
          <input type="radio" name="aiModelHidden" value="<?=htmlspecialchars($mdl['id'])?>" data-prov="<?=$pk?>" <?=$chk?>>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Chat -->
  <div class="chat-area">
    <div class="chat-messages" id="chatMessages">
      <div class="chat-empty" id="chatEmpty">
        <span class="material-icons-outlined">auto_awesome</span>
        <div style="font-size:14px;font-weight:600;color:var(--txt2)">Pronto para analisar</div>
        <div style="font-size:12px;max-width:300px;line-height:1.6">Selecione conta e campanha ao lado, depois faça sua pergunta ou escolha um template</div>
      </div>
    </div>
    <div class="chat-input-wrap">
      <div class="tab-row">
        <button type="button" class="tab-btn active" id="tabChat" onclick="switchTab('chat')">💬 Chat livre</button>
        <button type="button" class="tab-btn" id="tabTpl" onclick="switchTab('tpl')">📋 Templates</button>
      </div>
      <!-- Chat livre -->
      <div id="panelChat">
        <div class="cascade-wrap" style="margin-bottom:8px">
          <button type="button" class="cascade-trigger" id="varTrigger" onclick="toggleVars()">
            <span class="material-icons-outlined" style="font-size:14px">add_circle_outline</span>
            <span class="ct-label">Inserir variável por categoria...</span>
            <span class="ct-arrow">▾</span>
          </button>
          <div class="cascade-panel" id="varPanel">
            <div class="cascade-search"><input type="text" id="varSearch" placeholder="🔍 Buscar variável..." oninput="renderVars()"></div>
            <div class="cat-tabs" id="varCatTabs"></div>
            <div class="cascade-list" id="varList"></div>
          </div>
        </div>
        <div class="chat-send-row">
          <div class="chat-input-field" id="chatInputField" onclick="focusChatText()">
            <span class="chat-placeholder" id="chatPlaceholder">Faça sua pergunta... variáveis inseridas aparecem aqui</span>
          </div>
          <button type="button" class="send-btn-main" id="btnSendChat" onclick="sendChat()" <?=!$hasAnyKey?'disabled':''?>>
            <span class="material-icons-outlined" style="font-size:16px">send</span> Enviar
          </button>
        </div>
      </div>
      <!-- Templates -->
      <div id="panelTpl" style="display:none;flex-direction:column;gap:8px">
        <div class="cascade-wrap">
          <button type="button" class="cascade-trigger" id="tplTrigger" onclick="toggleTpl()">
            <span class="material-icons-outlined" style="font-size:14px">description</span>
            <span class="ct-label" id="tplTriggerLabel">Selecione um template...</span>
            <span class="ct-arrow" id="tplArrow">▾</span>
          </button>
          <div class="cascade-panel" id="tplPanel">
            <div class="cascade-search"><input type="text" id="tplSearch" placeholder="🔍 Buscar template..." oninput="renderTpls()"></div>
            <div class="cascade-list list-mode" id="tplList"></div>
          </div>
        </div>
        <button type="button" class="send-tpl-btn" id="btnSendTpl" disabled onclick="sendTpl()" <?=!$hasAnyKey?'disabled':''?>>
          <span class="material-icons-outlined" style="font-size:16px">auto_awesome</span> Analisar com este template
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal envio WA das bolhas -->
<div class="wa-modal-overlay" id="waSendModal" onclick="if(event.target===this)waModalClose()">
  <div class="wa-modal">
    <div class="wa-modal-hdr">
      <span><span style="color:#25D366">&#x2709;</span> Enviar por WhatsApp</span>
      <button class="pop-close" onclick="waModalClose()">✕</button>
    </div>
    <div class="wa-modal-body">
      <div>
        <label class="wa-lbl">Instância WhatsApp</label>
        <select id="waMdWpId" class="form-control form-control-sm">
          <option value="">— Instância —</option>
          <?php foreach($instances as $i): ?>
          <option value="<?=htmlspecialchars($i['id'])?>"><?=htmlspecialchars($i['instance_name'])?> · <?=htmlspecialchars($i['phone_number']??'')?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="wa-lbl">Enviar para</label>
        <div class="wa-tabs">
          <button class="wa-tab active" id="waMdTabPhone" onclick="waMdSetTipo('phone')">📱 Número</button>
          <button class="wa-tab" id="waMdTabClient" onclick="waMdSetTipo('client')">👤 Cliente</button>
          <button class="wa-tab" id="waMdTabGroup" onclick="waMdSetTipo('group')">👥 Grupo WA</button>
        </div>
      </div>
      <div id="waMdPhoneWrap">
        <label class="wa-lbl">Número</label>
        <input type="tel" id="waMdPhone" class="form-control form-control-sm" placeholder="5581999999999">
      </div>
      <div id="waMdClientWrap" style="display:none">
        <label class="wa-lbl">Cliente</label>
        <select id="waMdCliSel" class="form-control form-control-sm" onchange="var o=this.options[this.selectedIndex];document.getElementById('waMdPhone').value=o.getAttribute('data-phone')||'';document.getElementById('waMdClientId').value=this.value;">
          <option value="">— Cliente —</option>
          <?php foreach($clients as $c): ?>
          <option value="<?=htmlspecialchars($c['id'])?>" data-phone="<?=htmlspecialchars($c['phone']??'')?>"><?=htmlspecialchars($c['name'])?> · <?=htmlspecialchars($c['phone']??'sem telefone')?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" id="waMdClientId">
      </div>
      <div id="waMdGroupWrap" style="display:none">
        <label class="wa-lbl">Grupo WhatsApp</label>
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px">
          <select id="waMdGrpSel" class="form-control form-control-sm" style="flex:1">
            <option value="">— Selecione o grupo —</option>
          </select>
          <button type="button" class="btn btn-secondary btn-sm" onclick="waMdLoadGroups()" style="white-space:nowrap;flex-shrink:0">🔄 Buscar</button>
        </div>
        <div id="waMdGrpStatus" style="font-size:11px;color:var(--txt3)"></div>
      </div>
      <div>
        <div class="wa-row-lbl">
          <label class="wa-lbl" style="margin-bottom:0">Mensagem (editável)</label>
          <button class="wa-regen" id="waMdRegenBtn" onclick="waMdRegen()">
            <span class="material-icons-outlined" style="font-size:12px">refresh</span> Gerar outro resumo
          </button>
        </div>
        <textarea class="wa-ta" id="waMdMsg"></textarea>
      </div>
      <div class="wa-status" id="waMdStatus"></div>
    </div>
    <div class="wa-modal-footer">
      <button class="wa-cancel-btn" onclick="waModalClose()">Cancelar</button>
      <button class="wa-send-btn" id="waMdSendBtn" onclick="waMdSend()">
        <svg id="waMdBtnIcon" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        <span id="waMdBtnTxt">Enviar agora</span>
      </button>
    </div>
  </div>
</div>

<!-- Popup rápido -->
<div class="pop-overlay" id="aiPop" onclick="if(event.target===this)popClose()">
  <div class="pop-box">
    <div class="pop-hdr">
      <div class="pop-title"><span style="color:var(--accent)">✨</span><span id="popTitle">Análise IA</span></div>
      <button class="pop-close" onclick="popClose()">✕</button>
    </div>
    <div class="pop-body" id="popBody"><div style="display:flex;align-items:center;justify-content:center;min-height:200px;gap:12px"><div class="ai-spin" style="width:28px;height:28px;border-width:3px"></div><p style="font-size:13px;color:var(--txt2)">Analisando com IA...</p></div></div>
    <div class="pop-footer" id="popFooter" style="display:none">
      <button class="btn btn-secondary btn-sm" onclick="popCopy()"><span class="material-icons-outlined" style="font-size:14px">content_copy</span> Copiar</button>
      <button class="btn btn-secondary btn-sm" onclick="togEl('popSnd')"><span class="material-icons-outlined" style="font-size:14px">send</span> Enviar WhatsApp</button>
      <a href="<?= APP_URL ?>/ai" class="btn btn-ghost btn-sm" style="font-size:11px">Abrir IA →</a>
    </div>
    <div class="pop-send" id="popSnd" style="display:none"><?= aiSendForm('pop', $instances, $clients, $wpGroups) ?></div>
  </div>
</div>

<script>
var APP_URL = '<?= APP_URL ?>';
var CSRF    = '<?= e($_SESSION['csrf_token'] ?? '') ?>';
var _rawAnalysis = '', _popRaw = '';
var _sndTipo = {main:'phone', pop:'phone'};

var DB_TEMPLATES = <?= json_encode(array_values($templates ?? []), JSON_UNESCAPED_UNICODE) ?>;

var VAR_CATS = {
  'Custos':[
    {l:'Investimento',t:'{investimento}',c:'orange'},{l:'CPL',t:'{cpl}',c:'orange'},
    {l:'CPC',t:'{cpc}',c:'orange'},{l:'CPM',t:'{cpm}',c:'orange'},
    {l:'CPV',t:'{cpv}',c:'orange'},{l:'Custo/Resultado',t:'{custo_result}',c:'orange'},
    {l:'Custo/Mensagem',t:'{cmsg}',c:'orange'},{l:'Ticket Médio',t:'{tm}',c:'orange'},
    {l:'Custo/Engajamento',t:'{engajamento_cost}',c:'orange'},
    {l:'Custo/Visita ao perfil',t:'{custo_por_visita}',c:'orange'},
  ],
  'Conversões':[
    {l:'Leads',t:'{leads}',c:'green'},{l:'Conversões',t:'{conversoes}',c:'green'},
    {l:'ROAS',t:'{roas}',c:'green'},{l:'Receita',t:'{receita}',c:'green'},
    {l:'Vendas',t:'{vendas}',c:'green'},{l:'Mensagens',t:'{msg}',c:'green'},
    {l:'Resultados',t:'{results}',c:'green'},{l:'Todos os leads',t:'{all_leads}',c:'green'},
    {l:'Engajamento',t:'{engajamento}',c:'green'},
  ],
  'Cliques e Alcance':[
    {l:'Cliques no link',t:'{cliques}',c:'blue'},{l:'Todos os cliques',t:'{clicks_all}',c:'blue'},
    {l:'CTR',t:'{ctr}',c:'blue'},{l:'Alcance',t:'{alcance}',c:'blue'},
    {l:'Impressões',t:'{impressoes}',c:'blue'},{l:'Frequência',t:'{frequencia}',c:'blue'},
    {l:'Visitas ao perfil',t:'{profile_visit}',c:'blue'},
  ],
  'Engajamento':[
    {l:'Comentários',t:'{comment}',c:'purple'},{l:'Likes/Reações',t:'{post_reaction}',c:'purple'},
    {l:'Salvamentos',t:'{post_save}',c:'purple'},{l:'Eng. página',t:'{page_engagement}',c:'purple'},
  ],
  'Vídeo':[
    {l:'Assistiu 25%',t:'{view_25}',c:'purple'},{l:'Assistiu 50%',t:'{view_50}',c:'purple'},
    {l:'Assistiu 75%',t:'{view_75}',c:'purple'},{l:'Assistiu 95%',t:'{view_95}',c:'purple'},
    {l:'Assistiu 100%',t:'{view_100}',c:'purple'},{l:'Thruplay',t:'{thruplay}',c:'purple'},
    {l:'Tempo médio',t:'{v_avg}',c:'purple'},
  ],
  'Criativos':[
    {l:'TOP 1',t:'{top_1_creatives_ranking}',c:'purple'},{l:'TOP 3',t:'{top_3_creatives_ranking}',c:'purple'},
    {l:'TOP 5',t:'{top_5_creatives_ranking}',c:'purple'},{l:'Lista criativos',t:'{all_creatives_simple}',c:'purple'},
  ],
  'Gerais':[
    {l:'Nome do cliente',t:'{nome_cliente}',c:'blue'},{l:'Primeiro nome',t:'{primeiro_nome}',c:'blue'},
    {l:'Empresa',t:'{empresa}',c:'blue'},{l:'Período',t:'{periodo}',c:'blue'},
    {l:'Hoje',t:'{hoje}',c:'blue'},{l:'Conta',t:'{conta_anuncio}',c:'blue'},
    {l:'Campanha',t:'{campanha}',c:'blue'},{l:'Observações',t:'{observacoes}',c:'blue'},
  ],
};

function $$(id){return document.getElementById(id);}
function esc(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML;}
function fmt(n){return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtN(n){return parseInt(n||0).toLocaleString('pt-BR');}
function fmtTxt(t){return (t||'').replace(/\*(.*?)\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');}
function provLbl(p){return {groq:'⚡ Groq',openai:'◆ OpenAI',gemini:'✦ Gemini'}[p]||p;}
function togEl(id){
  var e=$$(id);
  if(!e) return;
  var opening = e.style.display==='none';
  e.style.display = opening ? 'block' : 'none';
  if(opening && id==='popSnd'){
    // Preencher mensagem com análise atual
    var ta=document.getElementById('popMsg');
    if(ta && _rawAnalysis){
      ta.value=_rawAnalysis;
    }
    // Auto-load grupos ao abrir
    setTimeout(function(){
      var wp=document.getElementById('popWpId');
      var gr=document.getElementById('popGrpSel');
      if(wp && !wp.value){
        for(var i=0;i<wp.options.length;i++){
          if(wp.options[i].value){wp.selectedIndex=i;break;}
        }
      }
      if(wp && wp.value){ aiLoadGroups('pop'); }
    }, 500);
  }
}

/* tabs */
function switchTab(tab){
  $$('tabChat').className='tab-btn'+(tab==='chat'?' active':'');
  $$('tabTpl').className='tab-btn'+(tab==='tpl'?' active':'');
  $$('panelChat').style.display=tab==='chat'?'block':'none';
  $$('panelTpl').style.display=tab==='tpl'?'flex':'none';
  if(tab==='tpl'){_tplOpen=true;$$('tplPanel').className='cascade-panel open';$$('tplArrow').textContent='▲';$$('tplTrigger').className='cascade-trigger open';setTimeout(function(){$$('tplSearch').focus();},50);}
}

/* variáveis */
var _varCat='Todas', _varOpen=false, _addedVars=[];
function buildVarCatTabs(){
  var el=$$('varCatTabs');if(!el)return;
  var cats=['Todas'].concat(Object.keys(VAR_CATS));
  el.innerHTML=cats.map(function(c){return '<div class="cat-tab'+(c===_varCat?' active':'')+'" onclick="setVarCat(\''+c.replace(/'/g,"\\'")+'\')">' +c+'</div>';}).join('');
}
function setVarCat(c){_varCat=c;buildVarCatTabs();renderVars();}
function renderVars(){
  var q=($$('varSearch').value||'').toLowerCase();
  var list=$$('varList');if(!list)return;list.innerHTML='';
  var cats=_varCat==='Todas'?Object.keys(VAR_CATS):[_varCat];
  var found=0;
  cats.forEach(function(cat){
    var items=(VAR_CATS[cat]||[]).filter(function(v){return !q||v.l.toLowerCase().includes(q)||v.t.toLowerCase().includes(q);});
    if(!items.length)return;
    if(_varCat==='Todas'){var sec=document.createElement('div');sec.className='vsec-title';sec.textContent=cat;list.appendChild(sec);}
    items.forEach(function(v){
      var added=_addedVars.indexOf(v.t)!==-1;
      var el=document.createElement('span');el.className='vtag '+(v.c||'blue')+(added?' added':'');
      el.textContent=v.t;el.title=v.l;
      if(!added)el.onclick=function(){insertVar(v.t);};
      list.appendChild(el);found++;
    });
  });
  if(!found){var em=document.createElement('div');em.className='cascade-empty';em.textContent='Nenhuma variável encontrada';list.appendChild(em);}
}
function insertVar(tag){
  if(_addedVars.indexOf(tag)!==-1)return;
  _addedVars.push(tag);
  var field=$$('chatInputField');
  var ph=$$('chatPlaceholder');if(ph)ph.remove();
  var tok=document.createElement('span');tok.className='chat-token';tok.dataset.tag=tag;
  tok.innerHTML=tag+'<span class="chat-token-x" onclick="removeVar(\''+tag.replace(/\{/g,'\\{').replace(/\}/g,'\\}').replace(/'/g,"\\'")+'\')">✕</span>';
  var tn=field.querySelector('.chat-text-node');
  if(tn){field.insertBefore(tok,tn);}else{field.appendChild(tok);}
  renderVars();
}
function removeVar(tag){
  _addedVars=_addedVars.filter(function(v){return v!==tag;});
  var field=$$('chatInputField');
  field.querySelectorAll('.chat-token').forEach(function(t){if(t.dataset.tag===tag)t.remove();});
  if(!_addedVars.length&&!getChatText().trim()){var ph=document.createElement('span');ph.className='chat-placeholder';ph.id='chatPlaceholder';ph.textContent='Faça sua pergunta... variáveis inseridas aparecem aqui';field.appendChild(ph);}
  renderVars();
}
function focusChatText(){
  var field=$$('chatInputField');var ph=$$('chatPlaceholder');if(ph)ph.remove();
  var tn=field.querySelector('.chat-text-node');
  if(!tn){tn=document.createElement('span');tn.className='chat-text-node';tn.contentEditable='true';tn.spellcheck=true;field.appendChild(tn);
    tn.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChat();}});
    tn.addEventListener('input',function(){if(!tn.textContent.trim()&&!_addedVars.length){tn.remove();var ph2=document.createElement('span');ph2.className='chat-placeholder';ph2.id='chatPlaceholder';ph2.textContent='Faça sua pergunta... variáveis inseridas aparecem aqui';$$('chatInputField').appendChild(ph2);}});
  }
  tn.focus();
}
function getChatText(){var tn=$$('chatInputField').querySelector('.chat-text-node');return tn?tn.textContent:'';}
function toggleVars(){
  _varOpen=!_varOpen;
  $$('varPanel').className='cascade-panel'+(_varOpen?' open':'');
  var tr=$$('varTrigger');tr.className='cascade-trigger'+(_varOpen?' open':'');
  tr.querySelector('.ct-arrow').textContent=_varOpen?'▲':'▾';
  if(_varOpen)setTimeout(function(){$$('varSearch').focus();},50);
}

/* templates */
var _tplOpen=false, _selTplId=null;
function renderTpls(){
  var q=($$('tplSearch').value||'').toLowerCase();
  var list=$$('tplList');if(!list)return;list.innerHTML='';
  var filtered=DB_TEMPLATES.filter(function(t){return !q||t.name.toLowerCase().includes(q);});
  if(!filtered.length){list.innerHTML='<div class="cascade-empty">Nenhum template encontrado</div>';return;}
  filtered.forEach(function(t){
    var el=document.createElement('div');el.className='tpl-item'+(t.id==_selTplId?' selected':'');
    var vars=(t.content.match(/\{[a-z_]+\}/gi)||[]);
    var uv=vars.filter(function(v,i){return vars.indexOf(v)===i;});
    el.innerHTML='<span class="tpl-item-name">'+esc(t.name)+'</span><span class="tpl-item-meta">'+uv.length+' var'+(uv.length!==1?'s':'')+'</span>';
    el.onclick=function(){selectTpl(t.id,t.name,t.content);};
    list.appendChild(el);
  });
}
function selectTpl(id,name,content){
  _selTplId=id;
  var lbl=$$('tplTriggerLabel');lbl.textContent=name;lbl.dataset.content=content;
  $$('btnSendTpl').disabled=false;
  _tplOpen=false;$$('tplPanel').className='cascade-panel';$$('tplArrow').textContent='▾';$$('tplTrigger').className='cascade-trigger';
  renderTpls();
}
function toggleTpl(){
  _tplOpen=!_tplOpen;$$('tplPanel').className='cascade-panel'+(_tplOpen?' open':'');$$('tplArrow').textContent=_tplOpen?'▲':'▾';
  $$('tplTrigger').className='cascade-trigger'+(_tplOpen?' open':'');
  if(_tplOpen){renderTpls();setTimeout(function(){$$('tplSearch').focus();},50);}
}

/* período / conta */
function onPeriodoChange(){var v=$$('aiPeriodo').value;$$('periodoCustom').style.display=(v==='custom')?'grid':'none';if(v==='custom'){var d=new Date();d.setDate(d.getDate()-30);$$('aiCustomStart').value=d.toISOString().split('T')[0];$$('aiCustomEnd').value=new Date().toISOString().split('T')[0];}}
function onAccChange(){
  var accId=$$('aiAcc').value,sel=$$('aiCamp'),status=$$('campStatus');
  sel.innerHTML='<option value="">Carregando...</option>';status.style.display='none';
  if(!accId){sel.innerHTML='<option value="">— Selecione uma conta primeiro —</option>';return;}
  fetch(APP_URL+'/api/accounts/campaigns?account_id='+encodeURIComponent(accId))
    .then(function(r){return r.json();}).then(function(d){
      var camps=d.campaigns||[];if(!camps.length){sel.innerHTML='<option value="">Nenhuma campanha</option>';return;}
      sel.innerHTML='<option value="">— Selecione a campanha —</option>';
      var ativas=camps.filter(function(c){return c.effective_status==='ACTIVE';});
      var pausadas=camps.filter(function(c){return c.effective_status==='PAUSED';});
      var outras=camps.filter(function(c){return c.effective_status!=='ACTIVE'&&c.effective_status!=='PAUSED';});
      function addGroup(label,list,cls){if(!list.length)return;var og=document.createElement('optgroup');og.label=label;list.forEach(function(c){var o=document.createElement('option');o.value=c.id;o.dataset.name=c.name;o.className=cls;o.textContent=c.name+' ['+c.effective_status+']';og.appendChild(o);});sel.appendChild(og);}
      addGroup('✅ Ativas ('+ativas.length+')',ativas,'camp-opt-active');addGroup('⏸ Pausadas ('+pausadas.length+')',pausadas,'camp-opt-paused');addGroup('📦 Outras ('+outras.length+')',outras,'camp-opt-other');
      status.style.display='block';status.textContent=camps.length+' campanha(s): '+ativas.length+' ativa(s)';
    }).catch(function(){$$('aiCamp').innerHTML='<option value="">Erro ao carregar</option>';});
}
function selProv(prov){try{localStorage.setItem('ai_prov',prov);}catch(e){}}
function selModelFromSelect(sel){
  var opt = sel.options[sel.selectedIndex];
  if(!opt) return;
  var modelId = opt.value;
  var prov = opt.dataset.prov || '';
  // Sincronizar radio hidden
  var radio = document.querySelector('#modelLists input[value="'+modelId+'"]');
  if(radio) radio.checked = true;
  // Salvar localStorage
  try{localStorage.setItem('ai_model',modelId);localStorage.setItem('ai_prov',prov);}catch(e){}
  // Salvar no banco como padrão via AJAX
  var fd = new FormData();
  fd.append('_ajax','1');
  fd.append('_csrf', CSRF);
  fd.append('provider', prov);
  fd.append('model', modelId);
  fetch(APP_URL+'/ai/save-model', {method:'POST', body:fd, credentials:'include'})
    .catch(function(){});
}
function selModel(el){el.closest('.model-list').querySelectorAll('.model-opt').forEach(function(m){m.classList.remove('selected');});el.classList.add('selected');var inp=el.querySelector('input');if(inp)inp.checked=true;try{localStorage.setItem('ai_model',inp?inp.value:'');localStorage.setItem('ai_prov',el.closest('.model-list').id.replace('ml_',''));}catch(e){}}

/* métricas → detectar quais enviar */
function extractMetrics(prompt){
  var map={'{investimento}':'invest','{receita}':'receita','{roas}':'roas','{cpl}':'cpl','{cpv}':'cpv','{custo_result}':'cpa','{cmsg}':'cpa','{custo_mensagem}':'cpa','{custo_por_conversa}':'cpa','{tm}':'cpa','{engajamento_cost}':'engajamento','{custo_por_visita}':'perfil','{alcance}':'alcance','{impressoes}':'imp','{cliques}':'clicks','{clicks_all}':'clicks','{ctr}':'ctr','{cpc}':'cpc','{cpm}':'cpm','{frequencia}':'freq','{profile_visit}':'perfil','{conversoes}':'conv','{leads}':'leads','{results}':'conv','{vendas}':'compras','{msg}':'msg','{mensagens}':'msg','{conversas}':'msg','{engajamento}':'engajamento','{all_leads}':'leads','{comment}':'conv','{post_reaction}':'conv','{post_save}':'conv','{page_engagement}':'conv','{view_25}':'view_25','{view_50}':'view_50','{view_75}':'view_75','{view_95}':'tendencia','{view_100}':'tendencia','{thruplay}':'thruplay','{v_avg}':'video_views','{video_views}':'video_views','{top_1_creatives_ranking}':'criativos','{top_3_creatives_ranking}':'criativos','{top_5_creatives_ranking}':'criativos','{all_creatives_simple}':'criativos'};
  var found={};Object.keys(map).forEach(function(tag){if(prompt.indexOf(tag)!==-1)found[map[tag]]=true;});
  return Object.keys(found).join(',');
}

/* cards de métricas */
function buildCards(m, prompt){
  // helpers para não mostrar card se valor for zero/nulo
  var _r=function(v){return parseFloat(v||0);};
  var _msgs=_r(m.messages)||_r(m.messaging_conversations)||_r(m.messages_sent);
  var _cmsg=_r(m.cost_per_message)||_r(m.cost_per_messaging_conv)||(_msgs>0?_r(m.spend)/_msgs:0);
  var _profv=_r(m.profile_visit)||_r(m.profile_visits)||0;
  var _cprofv=_r(m.cost_per_profile_visit)||(_profv>0?_r(m.spend)/_profv:0);
  var _eng=_r(m.engagement)||_r(m.post_engagement)||0;
  var _add=_r(m.add_to_cart)||0;
  var vm={
    '{investimento}':{l:'Investimento',v:'R$ '+fmt(m.spend)},
    '{receita}':{l:'Receita',v:'R$ '+fmt(m.revenue)},
    '{roas}':{l:'ROAS',v:_r(m.roas).toFixed(2)+'x'},
    '{alcance}':{l:'Alcance',v:fmtN(m.reach)},
    '{impressoes}':{l:'Impressões',v:fmtN(m.impressions)},
    '{cliques}':{l:'Cliques no link',v:fmtN(m.clicks)},
    '{clicks_all}':{l:'Todos cliques',v:fmtN(m.clicks_all||m.clicks)},
    '{ctr}':{l:'CTR',v:_r(m.ctr).toFixed(2)+'%'},
    '{cpm}':{l:'CPM',v:'R$ '+fmt(m.cpm)},
    '{cpc}':{l:'CPC',v:'R$ '+fmt(m.cpc)},
    '{frequencia}':{l:'Frequência',v:_r(m.frequency).toFixed(2)+'x'},
    '{profile_visit}':{l:'Visitas perfil',v:_profv>0?fmtN(_profv):'N/A'},
    '{custo_por_visita}':{l:'Custo/Visita',v:_cprofv>0?'R$ '+fmt(_cprofv):'N/A'},
    '{conversoes}':{l:'Conversões',v:fmtN(m.conversions)},
    '{leads}':{l:'Leads',v:fmtN(m.leads)},
    '{all_leads}':{l:'Todos leads',v:fmtN(m.all_leads||m.leads)},
    '{results}':{l:'Resultados',v:fmtN(m.results||m.conversions)},
    '{vendas}':{l:'Vendas',v:fmtN(m.purchases||0)},
    '{msg}':{l:'Conversas iniciadas',v:fmtN(_msgs)},
    '{mensagens}':{l:'Conversas iniciadas',v:fmtN(_msgs)},
    '{conversas}':{l:'Conversas iniciadas',v:fmtN(_msgs)},
    '{engajamento}':{l:'Engajamento',v:fmtN(m.engagement||0)},
    '{cpl}':{l:'CPL',v:'R$ '+fmt(m.cpl||0)},
    '{cpv}':{l:'CPV',v:'R$ '+fmt(m.cpv||0)},
    '{custo_result}':{l:'Custo/Resultado',v:'R$ '+fmt(m.cost_per_result||0)},
    '{cmsg}':{l:'Custo/Conversa',v:_cmsg>0?'R$ '+fmt(_cmsg):'N/A'},
    '{custo_por_conversa}':{l:'Custo/Conversa',v:_cmsg>0?'R$ '+fmt(_cmsg):'N/A'},
    '{custo_mensagem}':{l:'Custo/Conversa',v:_cmsg>0?'R$ '+fmt(_cmsg):'N/A'},
    '{tm}':{l:'Ticket Médio',v:'R$ '+fmt(m.ticket_medio||0)},
    '{video_views}':{l:'Views vídeo',v:fmtN(m.video_views||0)},
    '{view_25}':{l:'Assistiu 25%',v:_r(m.video_view_25)>0?fmtN(m.video_view_25):'N/A'},
    '{view_50}':{l:'Assistiu 50%',v:_r(m.video_view_50)>0?fmtN(m.video_view_50):'N/A'},
    '{view_75}':{l:'Assistiu 75%',v:_r(m.video_view_75)>0?fmtN(m.video_view_75):'N/A'},
    '{view_95}':{l:'Assistiu 95%',v:_r(m.video_view_95)>0?fmtN(m.video_view_95):'N/A'},
    '{view_100}':{l:'Assistiu 100%',v:_r(m.video_view_100)>0?fmtN(m.video_view_100):'N/A'},
    '{thruplay}':{l:'Thruplay',v:m.thruplay>0?fmtN(m.thruplay):'N/A'},
    '{v_avg}':{l:'Tempo médio',v:m.video_avg_time>0?_r(m.video_avg_time).toFixed(1)+'s':'N/A'},
    '{engajamento_cost}':{l:'Custo/Engajamento',v:_r(m.engagement)>0?'R$ '+fmt(_r(m.spend)/_r(m.engagement)):'N/A'},
    '{comment}':{l:'Comentários',v:fmtN(m.conversions||0)},
    '{post_reaction}':{l:'Reações',v:fmtN(m.engagement||0)},
    '{post_save}':{l:'Salvamentos',v:fmtN(m.add_to_cart||0)},
    '{page_engagement}':{l:'Engajamento página',v:fmtN(m.engagement||0)},
    '{all_creatives_simple}':{l:'Criativos',v:'Ver análise'},
    '{tm}':{l:'Ticket Médio',v:_r(m.ticket_medio)>0?'R$ '+fmt(m.ticket_medio):'N/A'}
  };
  var vars=(prompt.match(/\{[a-z_]+\}/gi)||[]),seen={},cards=[];
  // Variáveis de texto puro — não geram card numérico
  var textVars={'{empresa}':1,'{periodo}':1,'{nome_cliente}':1,'{primeiro_nome}':1,'{campanha}':1,'{conta_anuncio}':1,'{hoje}':1,'{observacoes}':1};
  vars.forEach(function(v){
    if(seen[v]||textVars[v]||!vm[v])return;
    var val=vm[v].v;
    if(val==='—'||val===''||val===null)return; // ignora texto vazio
    seen[v]=true;
    cards.push('<div class="ms-card"><div class="ms-val">'+val+'</div><div class="ms-lbl">'+vm[v].l+'</div></div>');
  });
  if(!cards.length)return null;
  var cols=Math.min(cards.length,4);
  var g=document.createElement('div');g.className='ms-grid-chat';g.style.gridTemplateColumns='repeat('+cols+',1fr)';g.innerHTML=cards.join('');return g;
}

/* histórico de conversa para o chat livre */
var _chatHistory = [];
var _lastAnalysisContext = ''; // contexto das últimas métricas analisadas

/* adicionar mensagem ao chat */
var _msgCounter=0;
function addMsg(html,type){
  var msgs=$$('chatMessages');var empty=$$('chatEmpty');if(empty)empty.remove();
  if(type==='ai'){
    var msgId='aibbl_'+(++_msgCounter);
    var wrap=document.createElement('div');wrap.className='bubble-ai-wrap';
    var bbl=document.createElement('div');bbl.className='bubble-ai';bbl.id=msgId;bbl.innerHTML=html;
    var acts=document.createElement('div');acts.className='bubble-ai-actions';
    acts.innerHTML='<button class="bbl-btn bbl-btn-wa" onclick="waModalOpen(\''+msgId+'\')"><span class="material-icons-outlined" style="font-size:12px">send</span> Enviar WA</button>'
      +'<button class="bbl-btn" onclick="bblCopy(this,\''+msgId+'\')"><span class="material-icons-outlined" style="font-size:12px">content_copy</span> Copiar</button>';
    wrap.appendChild(bbl);wrap.appendChild(acts);msgs.appendChild(wrap);msgs.scrollTop=msgs.scrollHeight;return bbl;
  }
  var el=document.createElement('div');el.className='bubble-user';el.innerHTML=html;
  msgs.appendChild(el);msgs.scrollTop=msgs.scrollHeight;return el;
}
function bblCopy(btn,id){var el=$$(id);if(!el)return;navigator.clipboard.writeText(el.innerText);var orig=btn.innerHTML;btn.innerHTML='<span class="material-icons-outlined" style="font-size:12px">check</span> Copiado';setTimeout(function(){btn.innerHTML=orig;},2000);}
function addLoading(txt){var msgs=$$('chatMessages');var el=document.createElement('div');el.className='bubble-loading';el.id='loadBubble';el.innerHTML='<div class="ai-spin"></div><span>'+(txt||'Pensando...')+'</span>';msgs.appendChild(el);msgs.scrollTop=msgs.scrollHeight;}
function rmLoading(){var el=$$('loadBubble');if(el)el.remove();}

/* enviar chat */
async function sendChat(){
  var aiSel=document.getElementById('aiModelSelect');
  var prov=aiSel&&aiSel.selectedIndex>=0?aiSel.options[aiSel.selectedIndex].dataset.prov:'';
  var txt=getChatText().trim(),vars=_addedVars.slice();
  if(!aiSel||!aiSel.value){alert('Selecione um modelo de IA.');return;}
  if(!vars.length&&!txt){alert('Escreva uma pergunta ou insira ao menos uma variável.');return;}

  var userHtml=vars.map(function(v){return '<span style="display:inline-block;background:var(--accent3);color:var(--accent);border:1px solid var(--accent);border-radius:5px;padding:1px 7px;font-size:11px;font-family:monospace;margin:1px">'+esc(v)+'</span>';}).join(' ')+(txt?(vars.length?'<br>':'')+esc(txt):'');
  addMsg(userHtml,'user');

  // Limpa input
  _addedVars=[];var field=$$('chatInputField');field.innerHTML='';
  var ph=document.createElement('span');ph.className='chat-placeholder';ph.id='chatPlaceholder';ph.textContent='Faça sua pergunta... variáveis inseridas aparecem aqui';field.appendChild(ph);
  renderVars();

  // Se tem variáveis → modo análise de campanha
  if(vars.length){
    var accId=$$('aiAcc').value,campSel=$$('aiCamp'),campId=campSel.value;
    var campOpt=campSel.options[campSel.selectedIndex];
    var campName=campOpt?(campOpt.dataset.name||campOpt.textContent.split('[')[0].trim()):'';
    var periodo=$$('aiPeriodo').value,cs=$$('aiCustomStart').value,ce=$$('aiCustomEnd').value;
    if(!accId){addMsg('<span style="color:var(--warn)">⚠️ Selecione a conta de anúncios.</span>','ai');return;}
    if(!campId){addMsg('<span style="color:var(--warn)">⚠️ Selecione a campanha.</span>','ai');return;}
    var prompt=(vars.join(' ')+'\n'+(txt||'')).trim();
    addLoading('Buscando métricas...');
    try{
      var fd=new FormData();fd.append('_token',CSRF);fd.append('provider',prov);fd.append('model',aiSel?aiSel.value:'');fd.append('account_id',accId);fd.append('campaign_id',campId);fd.append('campaign_name',campName);fd.append('periodo',periodo);fd.append('custom_start',cs);fd.append('custom_end',ce);fd.append('custom_prompt',prompt);fd.append('metrics',extractMetrics(prompt));
      var r=await fetch(APP_URL+'/ai/analyze',{method:'POST',body:fd});var d=await r.json();rmLoading();
      if(!d.success){addMsg('<span style="color:var(--danger)">⚠️ '+esc(d.error||'Erro')+'</span>','ai');return;}
      _rawAnalysis=d.analysis;
      // Salva contexto para o chat livre poder referenciar
      _lastAnalysisContext='Campanha: '+d.campaign_name+'\nConta: '+d.account_name+'\nPeriodo: '+d.period+(d.campaign_context?'\n'+d.campaign_context:'')+'\n\nAnalise gerada pelo gestor:\n'+d.analysis;
      _chatHistory=[];// reset histórico ao fazer nova análise
      _chatHistory.push({role:'user',content:'Analise estas metricas: '+prompt});
      _chatHistory.push({role:'assistant',content:d.analysis});
      var cards=buildCards(d.metrics,prompt);if(cards)$$('chatMessages').appendChild(cards);
      addMsg('<div style="font-size:10px;color:var(--txt3);margin-bottom:6px">'+esc(d.account_name)+' · '+esc(d.period)+' · '+provLbl(prov)+'</div>'+fmtTxt(d.analysis),'ai');
    }catch(err){rmLoading();addMsg('<span style="color:var(--danger)">❌ '+esc(err.message)+'</span>','ai');}

  } else {
    // Só texto → modo chat livre com histórico + contexto das métricas
    _chatHistory.push({role:'user',content:txt});
    addLoading('Pensando...');
    try{
      var fd2=new FormData();
      fd2.append('_token',CSRF);fd2.append('provider',prov);fd2.append('model',aiSel?aiSel.value:'');
      fd2.append('history',JSON.stringify(_chatHistory));
      // Context now stored server-side in session - no need to send via POST
      var r2=await fetch(APP_URL+'/ai/chat',{method:'POST',body:fd2});var d2=await r2.json();rmLoading();
      if(!d2.success){addMsg('<span style="color:var(--danger)">⚠️ '+esc(d2.error||'Erro')+'</span>','ai');return;}
      _chatHistory.push({role:'assistant',content:d2.text});
      addMsg(fmtTxt(d2.text),'ai');
    }catch(err){rmLoading();addMsg('<span style="color:var(--danger)">❌ '+esc(err.message)+'</span>','ai');}
  }
}

/* enviar template */
async function sendTpl(){
  if(!_selTplId)return;
  var accId=$$('aiAcc').value,campSel=$$('aiCamp'),campId=campSel.value;
  var campOpt=campSel.options[campSel.selectedIndex];
  var campName=campOpt?(campOpt.dataset.name||campOpt.textContent.split('[')[0].trim()):'';
  var aiSel=document.getElementById('aiModelSelect');
  var prov=aiSel&&aiSel.selectedIndex>=0?aiSel.options[aiSel.selectedIndex].dataset.prov:'';
  var periodo=$$('aiPeriodo').value,cs=$$('aiCustomStart').value,ce=$$('aiCustomEnd').value;
  if(!accId){alert('Selecione a conta de anúncios.');return;}
  if(!campId){alert('Selecione a campanha.');return;}
  if(!aiSel||!aiSel.value){alert('Selecione um modelo de IA.');return;}
  var lbl=$$('tplTriggerLabel');var tplName=lbl.textContent;var tplContent=lbl.dataset.content||'';
  addMsg(esc(tplName),'user');addLoading();switchTab('chat');
  try{
    var fd=new FormData();fd.append('_token',CSRF);fd.append('provider',prov);fd.append('model',aiSel?aiSel.value:'');fd.append('account_id',accId);fd.append('campaign_id',campId);fd.append('campaign_name',campName);fd.append('periodo',periodo);fd.append('custom_start',cs);fd.append('custom_end',ce);fd.append('custom_prompt',tplContent);fd.append('metrics',extractMetrics(tplContent));
    var r=await fetch(APP_URL+'/ai/analyze',{method:'POST',body:fd});var d=await r.json();rmLoading();
    if(!d.success){addMsg('<span style="color:var(--danger)">⚠️ '+esc(d.error||'Erro')+'</span>','ai');return;}
    _rawAnalysis=d.analysis;
    // Salva contexto para perguntas de acompanhamento no chat livre
    _lastAnalysisContext='Campanha: '+d.campaign_name+'\nConta: '+d.account_name+'\nPeriodo: '+d.period+(d.campaign_context?'\n'+d.campaign_context:'')+'\n\nAnalise gerada pelo gestor:\n'+d.analysis;
    _chatHistory=[];
    _chatHistory.push({role:'user',content:'Analise este template: '+tplName});
    _chatHistory.push({role:'assistant',content:d.analysis});
    var cards=buildCards(d.metrics,tplContent);if(cards)$$('chatMessages').appendChild(cards);
    addMsg('<div style="font-size:10px;color:var(--txt3);margin-bottom:6px">'+esc(d.account_name)+' · '+esc(d.period)+' · '+provLbl(prov)+'</div>'+fmtTxt(d.analysis),'ai');
    _selTplId=null;$$('btnSendTpl').disabled=true;lbl.textContent='Selecione um template...';delete lbl.dataset.content;$$('tplSearch').value='';renderTpls();
  }catch(err){rmLoading();addMsg('<span style="color:var(--danger)">❌ '+esc(err.message)+'</span>','ai');}
}

/* ── Buscar grupos dinamicamente ─────────────────────────────── */
async function aiLoadGroups(pfx) {
  var wpId = document.getElementById(pfx+'WpId');
  if (!wpId) return;
  var wid = wpId.value;
  var sel = document.getElementById(pfx+'GrpSel');
  var status = document.getElementById(pfx+'GrpStatus');
  if (!wid) { if(status) status.textContent = '⚠ Selecione uma instância primeiro.'; return; }
  if (sel) { sel.disabled = true; sel.innerHTML = '<option value="">⏳ Buscando grupos...</option>'; }
  if (status) status.textContent = '';
  try {
    var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wid));
    var d = await r.json();
    if (sel) sel.disabled = false;
    if (!d.success) {
      if (sel) sel.innerHTML = '<option value="">Nenhum grupo encontrado</option>';
      if (status) status.textContent = '⚠ '+(d.error||'Erro ao buscar grupos');
      return;
    }
    if (sel) {
      sel.innerHTML = '<option value="">— Selecione o grupo —</option>';
      (d.grupos||[]).forEach(function(g){
        var o = document.createElement('option');
        o.value = g.id; o.textContent = g.nome||g.id;
        sel.appendChild(o);
      });
    }
    if (status) status.textContent = '✅ '+(d.grupos||[]).length+' grupo(s) encontrado(s)';
  } catch(e) {
    if (sel) { sel.disabled = false; sel.innerHTML = '<option value="">Erro de conexão</option>'; }
    if (status) status.textContent = '❌ Erro: '+e.message;
  }
}
async function waMdLoadGroups() {
  var wpId = document.getElementById('waMdWpId');
  var wid = wpId ? wpId.value : '';
  var sel = document.getElementById('waMdGrpSel');
  var status = document.getElementById('waMdGrpStatus');
  if (!wid) { if(status) status.textContent = '⚠ Selecione uma instância primeiro.'; return; }
  if (sel) { sel.disabled = true; sel.innerHTML = '<option value="">⏳ Buscando grupos...</option>'; }
  if (status) status.textContent = '';
  try {
    var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wid));
    var d = await r.json();
    if (sel) sel.disabled = false;
    if (!d.success) {
      if (sel) sel.innerHTML = '<option value="">Nenhum grupo encontrado</option>';
      if (status) status.textContent = '⚠ '+(d.error||'Erro ao buscar grupos');
      return;
    }
    if (sel) {
      sel.innerHTML = '<option value="">— Selecione o grupo —</option>';
      (d.grupos||[]).forEach(function(g){
        var o = document.createElement('option');
        o.value = g.id; o.textContent = g.nome||g.id;
        sel.appendChild(o);
      });
    }
    if (status) status.textContent = '✅ '+(d.grupos||[]).length+' grupo(s) encontrado(s)';
  } catch(e) {
    if (sel) { sel.disabled = false; sel.innerHTML = '<option value="">Erro de conexão</option>'; }
    if (status) status.textContent = '❌ Erro: '+e.message;
  }
}

/* WA */
function sndTipo(pfx,tipo){_sndTipo[pfx]=tipo;$$(pfx+'Ph').style.display=tipo==='phone'?'block':'none';$$(pfx+'Cl').style.display=tipo==='client'?'block':'none';$$(pfx+'Gr').style.display=tipo==='group'?'block':'none';}
function sndReset(pfx){var base=_rawAnalysis||_popRaw;$$(pfx+'Msg').value=base||'';}
async function sndWp(pfx){var wpId=$$(pfx+'WpId').value,msg=$$(pfx+'Msg').value.trim(),st=$$(pfx+'St');var tipo=_sndTipo[pfx]||'phone',phone='',groupId='';if(tipo==='phone')phone=$$(pfx+'Phone').value.trim();if(tipo==='client')phone=$$(pfx+'CliSel').value;if(tipo==='group')groupId=$$(pfx+'GrpSel').value;if(!msg){alert('Mensagem vazia.');return;}if(tipo!=='group'&&!phone){alert('Informe o número.');return;}if(tipo==='group'&&!groupId){alert('Selecione o grupo.');return;}st.innerHTML='⏳ Enviando...';var cid=$$(pfx+'ClientId')?$$(pfx+'ClientId').value:'';var fd=new FormData();fd.append('_token',CSRF);fd.append('whatsapp_id',wpId);fd.append('recv_type',tipo);fd.append('phone',phone);fd.append('group_id',groupId);fd.append('client_id',cid);fd.append('message',msg);try{var r=await fetch(APP_URL+'/ai/send',{method:'POST',body:fd});var d=await r.json();st.innerHTML=d.success?'<span style="color:var(--success)">✅ Enviado!</span>':'<span style="color:var(--danger)">❌ '+(d.error||'Erro')+'</span>';}catch(e){st.innerHTML='<span style="color:var(--danger)">❌ Erro de rede</span>';}}

/* popup rápido */
/* ── Monta card de métricas por objetivo — idêntico ao dashboard ── */
function buildCardsFromObjective(m, objective) {
  var _r=function(v){return parseFloat(v||0);};
  var obj=(objective||'').toLowerCase();

  var conv   =parseInt(m.messages||m.messaging_conversations||0);
  var leads  =parseInt(m.leads||0);
  var roas   =_r(m.roas);
  var reach  =parseInt(m.reach||0);
  var impr   =parseInt(m.impressions||0);
  var spend  =_r(m.spend);
  var ctr    =_r(m.ctr);
  var cpm    =_r(m.cpm);
  var cpc    =_r(m.cpc);
  var freq   =_r(m.frequency);
  var cpl    =_r(m.cost_per_lead||m.cpl||0);
  var cmsg   =_r(m.cost_per_messaging_conv||m.cost_per_message||0);
  var prof   =parseInt(m.profile_visit||m.profile_visits||0);
  var clicks =parseInt(m.clicks||m.link_clicks||0);
  var eng    =parseInt(m.engagement||0);
  var vviews =parseInt(m.video_views||0);
  var thrpl  =parseInt(m.thruplay||0);
  var purch  =parseInt(m.purchases||0);

  var pool={};
  if(spend>0) pool['Investimento']  ={val:'R$'+fmt(spend),                        pct:50,                           color:'#9B59B6'};
  if(reach>0) pool['Alcance']       ={val:fmtN(reach),                            pct:65,                           color:'#1ABC9C'};
  if(impr>0)  pool['Impressões']    ={val:fmtN(impr),                             pct:80,                           color:'#1ABC9C'};
  if(ctr>0)   pool['CTR']           ={val:ctr.toFixed(2).replace('.',',')+'%',    pct:Math.min(100,ctr/3*100),      color:'#F39C12'};
  if(cpm>0)   pool['CPM']           ={val:'R$'+fmt(cpm),                          pct:Math.min(100,cpm/30*100),     color:'#5B8DEF'};
  if(cpc>0)   pool['CPC']           ={val:'R$'+fmt(cpc),                          pct:Math.min(100,cpc/5*100),      color:'#5B8DEF'};
  if(clicks>0)pool['Cliques']       ={val:fmtN(clicks),                           pct:Math.min(100,clicks/500*100), color:'#F39C12'};
  if(conv>0)  pool['Conversas']     ={val:fmtN(conv),                             pct:Math.min(100,conv/20*100),    color:'#1ABC9C'};
  if(cmsg>0)  pool['Custo/conv']    ={val:'R$'+fmt(cmsg),                         pct:Math.min(100,(20/Math.max(cmsg,0.01))*100), color:'#E74C3C'};
  if(leads>0) pool['Leads']         ={val:fmtN(leads),                            pct:Math.min(100,leads/50*100),   color:'#9B59B6'};
  if(cpl>0)   pool['Custo/lead']    ={val:'R$'+fmt(cpl),                          pct:Math.min(100,(50/Math.max(cpl,0.01))*100), color:'#E74C3C'};
  if(prof>0)  pool['Visitas perfil']={val:fmtN(prof),                             pct:Math.min(100,prof/500*100),   color:'#5B8DEF'};
  if(eng>0)   pool['Engajamento']   ={val:fmtN(eng),                              pct:Math.min(100,eng/1000*100),   color:'#F39C12'};
  if(vviews>0)pool['Views vídeo']   ={val:fmtN(vviews),                           pct:Math.min(100,vviews/1000*100),color:'#1ABC9C'};
  if(thrpl>0) pool['ThruPlay']      ={val:fmtN(thrpl),                            pct:Math.min(100,thrpl/500*100),  color:'#F39C12'};
  if(purch>0) pool['Compras']       ={val:fmtN(purch),                            pct:Math.min(100,purch/100*100),  color:'#2ECC71'};
  if(roas>0)  pool['ROAS']          ={val:roas.toFixed(2).replace('.',','),        pct:Math.min(100,roas/5*100),     color:'#2ECC71'};

  var prioridade;
  if(obj.indexOf('trafego')>=0||obj.indexOf('tráfego')>=0||obj.indexOf('outcome_traffic')>=0||obj.indexOf('link_click')>=0)
    prioridade=['Visitas perfil','Cliques','CTR','Alcance','Impressões','Investimento'];
  else if(obj.indexOf('mensagem')>=0||obj.indexOf('conversa')>=0||obj.indexOf('messages')>=0)
    prioridade=['Conversas','Custo/conv','CTR','Cliques','Alcance','Investimento'];
  else if(obj.indexOf('lead')>=0||obj.indexOf('outcome_leads')>=0)
    prioridade=['Leads','Custo/lead','CTR','Cliques','Alcance','Investimento'];
  else if(obj.indexOf('venda')>=0||obj.indexOf('compra')>=0||obj.indexOf('catalogo')>=0||obj.indexOf('catálogo')>=0||obj.indexOf('sales')>=0||obj.indexOf('outcome_sales')>=0||obj.indexOf('conversions')>=0||obj.indexOf('outcome_conversions')>=0)
    prioridade=['Compras','ROAS','CTR','Cliques','Investimento','Alcance'];
  else if(obj.indexOf('video')>=0||obj.indexOf('vídeo')>=0||obj.indexOf('video_view')>=0)
    prioridade=['Views vídeo','ThruPlay','Alcance','Impressões','CTR','Investimento'];
  else if(obj.indexOf('alcance')>=0||obj.indexOf('reconhecimento')>=0||obj.indexOf('awareness')>=0||obj.indexOf('reach')>=0||obj.indexOf('brand_awareness')>=0)
    prioridade=['Alcance','Impressões','CPM','Investimento','CTR','Cliques'];
  else if(obj.indexOf('engajamento')>=0||obj.indexOf('engagement')>=0||obj.indexOf('post_engagement')>=0)
    prioridade=['Engajamento','Alcance','CTR','Cliques','Impressões','Investimento'];
  else
    prioridade=['Investimento','Alcance','Impressões','CTR','Cliques','Conversas'];

  var toShow={},order=[];
  prioridade.forEach(function(lbl){if(pool[lbl]&&order.length<6){toShow[lbl]=pool[lbl];order.push(lbl);}});
  Object.keys(pool).forEach(function(lbl){if(!toShow[lbl]&&order.length<6){toShow[lbl]=pool[lbl];order.push(lbl);}});

  var rows=order.map(function(lbl){
    var mm=toShow[lbl];
    return '<div style="display:flex;align-items:center;padding:3px 0;border-bottom:1px solid var(--border2,rgba(255,255,255,.06))">'
      +'<span style="font-size:10px;color:var(--txt2);min-width:80px">'+lbl+'</span>'
      +'<div style="flex:1;height:3px;background:var(--bg3);border-radius:4px;margin:0 8px;overflow:hidden">'
        +'<div style="width:'+Math.round(mm.pct)+'%;height:100%;background:'+mm.color+';border-radius:4px"></div>'
      +'</div>'
      +'<span style="font-size:10px;font-weight:600;color:var(--txt);min-width:56px;text-align:right">'+mm.val+'</span>'
    +'</div>';
  }).join('');

  var extras=[];
  if(cpc>0&&!toShow['CPC'])         extras.push('CPC: <strong style="color:var(--txt)">R$'+fmt(cpc)+'</strong>');
  if(cmsg>0&&!toShow['Custo/conv'])  extras.push('Custo/conv: <strong style="color:var(--txt)">R$'+fmt(cmsg)+'</strong>');
  else if(cpl>0&&!toShow['Custo/lead']) extras.push('Custo/lead: <strong style="color:var(--txt)">R$'+fmt(cpl)+'</strong>');
  if(freq>0) extras.push('Freq: <strong style="color:var(--txt)">'+freq.toFixed(1).replace('.',',')+'x</strong>');

  var footer='<div style="margin-top:7px;padding-top:6px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">'
    +'<div style="font-size:10px;color:var(--txt3);display:flex;gap:10px;flex-wrap:wrap">'+extras.join(' &middot; ')+'</div>'
    +'</div>';

  return rows+footer;
}

async function openAiPopup(campId,accId,campName){
  _popRaw='';
  $$('popTitle').textContent=campName||'Análise IA';
  $$('popBody').innerHTML='<div style="display:flex;align-items:center;justify-content:center;min-height:200px;gap:12px"><div class="ai-spin" style="width:28px;height:28px;border-width:3px"></div><p style="font-size:13px;color:var(--txt2)">Analisando com IA...</p></div>';
  $$('popFooter').style.display='none';$$('popSnd').style.display='none';
  $$('aiPop').classList.add('open');
  var ed=new Date().toISOString().split('T')[0];
  var sd=new Date();sd.setDate(sd.getDate()-30);var st=sd.toISOString().split('T')[0];
  try {
    var fd=new FormData();
    fd.append('_token',CSRF);fd.append('campaign_id',campId);fd.append('account_id',accId);
    fd.append('date_start',st);fd.append('date_end',ed);
    var r=await fetch(APP_URL+'/ai/analyze-card',{method:'POST',body:fd});
    var d=await r.json();
    if(!d.success){$$('popBody').innerHTML='<div style="color:var(--danger);text-align:center;padding:30px">⚠️ '+esc(d.error)+'</div>';return;}
    _popRaw=d.analysis;
    var m=d.metrics;
    var objective=d.objective||'';
    var metricRows=buildCardsFromObjective(m,objective);
    $$('popBody').innerHTML=
      '<div style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:12px">'
        +metricRows
      +'</div>'
      +'<div style="font-size:11px;color:var(--txt3);margin-bottom:12px">'+esc(d.account_name)+' &middot; '+esc(d.period)+'</div>'
      +'<div style="font-size:13px;line-height:1.7;color:var(--txt)">'+fmtTxt(d.analysis)+'</div>';
    if($$('popMsg'))$$('popMsg').value=d.analysis||'';
    $$('popFooter').style.display='flex';
  } catch(err){$$('popBody').innerHTML='<div style="color:var(--danger);text-align:center;padding:30px">Erro: '+esc(err.message)+'</div>';}
}
function popClose(){$$('aiPop').classList.remove('open');}
function popCopy(){if(_popRaw)navigator.clipboard.writeText(_popRaw);}

/* modal WA bolhas */
var _waMdTipo='phone', _waMdSummaries=[], _waMdSumIdx=0, _waMdRawText='';
var _waMdSummaryTemplates=[
  function(txt){var lines=txt.split('\n').filter(function(l){return l.trim();}).slice(0,4);return '📊 *Resumo da Análise*\n\n'+lines.join('\n');},
  function(txt){var lines=txt.split('\n').filter(function(l){return l.trim();}).slice(0,3);return '📈 *Diagnóstico IA*\n\n'+lines.join('\n')+'\n\n✅ Análise gerada automaticamente.';},
  function(txt){var first=txt.split('\n').filter(function(l){return l.trim();})[0]||txt.substring(0,200);return '💡 *Insight Rápido*\n\n'+first;}
];
function waModalOpen(bubbleId){
  var el=$$(bubbleId);if(!el)return;
  _waMdRawText=el.innerText||'';
  _waMdSumIdx=0;
  $$('waMdMsg').value='⏳ Gerando resumo...';
  $$('waMdMsg').disabled=true;
  $$('waMdRegenBtn').disabled=true;
  $$('waMdStatus').innerHTML='';
  $$('waSendModal').classList.add('open');
  setTimeout(function(){
    $$('waMdMsg').value=_waMdSummaryTemplates[0](_waMdRawText);
    $$('waMdMsg').disabled=false;
    $$('waMdRegenBtn').disabled=false;
  },900);
}
function waModalClose(){$$('waSendModal').classList.remove('open');$$('waMdStatus').innerHTML='';}
function waMdSetTipo(tipo){
  _waMdTipo=tipo;
  ['phone','client','group'].forEach(function(t){
    var tab=$$('waMdTab'+t.charAt(0).toUpperCase()+t.slice(1));
    var wrap=$$('waMd'+t.charAt(0).toUpperCase()+t.slice(1)+'Wrap');
    if(tab)tab.classList.toggle('active',t===tipo);
    if(wrap)wrap.style.display=t===tipo?'block':'none';
  });
}
function waMdRegen(){
  _waMdSumIdx=(_waMdSumIdx+1)%_waMdSummaryTemplates.length;
  $$('waMdMsg').disabled=true;$$('waMdRegenBtn').disabled=true;
  $$('waMdMsg').value='⏳ Gerando...';
  setTimeout(function(){
    $$('waMdMsg').value=_waMdSummaryTemplates[_waMdSumIdx](_waMdRawText);
    $$('waMdMsg').disabled=false;$$('waMdRegenBtn').disabled=false;
  },700);
}
async function waMdSend(){
  var wpId=$$('waMdWpId').value,msg=$$('waMdMsg').value.trim(),st=$$('waMdStatus');
  var tipo=_waMdTipo,phone='',groupId='',clientId='';
  if(tipo==='phone')phone=$$('waMdPhone').value.trim();
  if(tipo==='client'){phone=$$('waMdPhone').value.trim();clientId=$$('waMdClientId')&&$$('waMdClientId').value||'';}
  if(tipo==='group')groupId=$$('waMdGrpSel').value;
  if(!msg){alert('Mensagem vazia.');return;}
  if(!wpId){alert('Selecione a instância WhatsApp.');return;}
  if(tipo!=='group'&&!phone){alert('Informe o número.');return;}
  if(tipo==='group'&&!groupId){alert('Selecione o grupo.');return;}

  var btn=$$('waMdSendBtn');
  var icon=$$('waMdBtnIcon');
  var txt=$$('waMdBtnTxt');
  btn.disabled=true;
  btn.style.background='#1a9e4e';
  txt.textContent='Enviando...';
  icon.outerHTML='<svg id="waMdBtnIcon" style="width:15px;height:15px;flex-shrink:0;animation:waSpin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';
  st.innerHTML='';

  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('whatsapp_id',wpId);
  fd.append('recv_type',tipo);fd.append('phone',phone);
  fd.append('group_id',groupId);fd.append('client_id',clientId);fd.append('message',msg);

  function resetBtn(){
    btn.disabled=false;btn.style.background='';
    $$('waMdBtnIcon')&&($$('waMdBtnIcon').outerHTML='<svg id="waMdBtnIcon" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>');
    $$('waMdBtnTxt').textContent='Enviar agora';
  }

  try{
    var r=await fetch(APP_URL+'/ai/send',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){
      $$('waMdBtnIcon')&&($$('waMdBtnIcon').outerHTML='<svg id="waMdBtnIcon" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>');
      $$('waMdBtnTxt').textContent='Enviado!';
      setTimeout(function(){waModalClose();resetBtn();},2000);
    } else {
      btn.style.background='var(--danger)';
      $$('waMdBtnIcon')&&($$('waMdBtnIcon').outerHTML='<svg id="waMdBtnIcon" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>');
      $$('waMdBtnTxt').textContent='Erro ao enviar';
      st.innerHTML='<span style="color:var(--danger);font-size:11px">'+(d.error||'Falha')+'</span>';
      setTimeout(resetBtn,3000);
    }
  }catch(e){
    btn.style.background='var(--danger)';
    $$('waMdBtnTxt')&&($$('waMdBtnTxt').textContent='Erro de rede');
    setTimeout(resetBtn,3000);
  }
}

/* init */
(function(){
  buildVarCatTabs();renderVars();renderTpls();
  // Restaura provider e modelo salvos
  try {
    var savedProv = localStorage.getItem('ai_prov');
    var savedModel = localStorage.getItem('ai_model');
    if(savedModel){
      var sel = document.getElementById('aiModelSelect');
      if(sel){
        for(var i=0;i<sel.options.length;i++){
          if(sel.options[i].value===savedModel && !sel.options[i].disabled){
            sel.selectedIndex=i;
            break;
          }
        }
      }
    }
  } catch(e) {}
  document.addEventListener('click',function(e){
    if(_varOpen&&$$('varPanel')&&!$$('varPanel').contains(e.target)&&!$$('varTrigger').contains(e.target)){_varOpen=false;$$('varPanel').className='cascade-panel';$$('varTrigger').className='cascade-trigger';$$('varTrigger').querySelector('.ct-arrow').textContent='▾';}
    if(_tplOpen&&$$('tplPanel')&&!$$('tplPanel').contains(e.target)&&$$('tplTrigger')&&!$$('tplTrigger').contains(e.target)){_tplOpen=false;$$('tplPanel').className='cascade-panel';if($$('tplArrow'))$$('tplArrow').textContent='▾';if($$('tplTrigger'))$$('tplTrigger').className='cascade-trigger';}
  });
})();
</script>
