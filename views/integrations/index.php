<?php
$pageTitle   = 'Integrações';
$currentPage = 'integrations';
ob_start();
$csrf = $_SESSION['csrf_token'];
?>
<style>
.integr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;margin-bottom:28px}
.integr-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:20px;display:flex;flex-direction:column;gap:10px}
.integr-card-header{display:flex;align-items:center;gap:12px}
.integr-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.integr-icon.webhook{background:rgba(99,102,241,.15)}
.integr-icon.elementor{background:rgba(144,0,255,.12)}
.integr-icon.tintim{background:rgba(239,68,68,.12)}
.integr-icon.facebook_lead{background:rgba(24,119,242,.12)}
.integr-title{font-size:14px;font-weight:700;color:var(--txt)}
.integr-desc{font-size:12px;color:var(--txt2);line-height:1.6}
.integr-count{font-size:11px;color:var(--txt3)}
.integr-footer{display:flex;justify-content:space-between;align-items:center;margin-top:4px}
.integr-list{display:flex;flex-direction:column;gap:8px}
.integr-item{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;align-items:center;gap:10px}
.integr-item-url{font-size:11px;color:var(--txt3);font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;cursor:pointer}
.integr-item-url:hover{color:var(--accent)}
.dot-active{width:8px;height:8px;border-radius:50%;background:var(--success);flex-shrink:0}
.dot-inactive{width:8px;height:8px;border-radius:50%;background:var(--txt3);flex-shrink:0}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-header{padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.modal-body{padding:20px 24px}
.modal-footer{padding:14px 24px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end}
.step-num{width:22px;height:22px;border-radius:50%;background:var(--accent);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.step-row{display:flex;gap:12px;align-items:flex-start;margin-bottom:16px}
.step-body{flex:1}
.step-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:6px}
.vars-wrap{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.var-tag{background:var(--accent3);color:var(--accent);font-size:11px;padding:3px 8px;border-radius:20px;cursor:pointer;font-family:monospace;border:1px solid rgba(0,120,255,.2)}
.var-tag:hover{background:var(--accent);color:#fff}
.endpoint-box{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:10px 12px;font-size:12px;font-family:monospace;color:var(--txt2);word-break:break-all;display:flex;align-items:center;gap:8px;justify-content:space-between}
.copy-btn{background:none;border:none;cursor:pointer;color:var(--accent);font-size:13px;flex-shrink:0;padding:2px 6px}
.copy-btn:hover{color:var(--txt)}
.msg-preview{background:#075e54;border-radius:10px;padding:12px 14px;font-size:13px;color:#fff;line-height:1.6;white-space:pre-wrap;min-height:80px}
.fb-steps{background:var(--bg3);border-radius:var(--radius);padding:14px;font-size:12px;color:var(--txt2);line-height:1.8}
.fb-steps ol{padding-left:16px}
.integr-icon.autentique{background:rgba(16,185,129,.12)}
.integr-card.em-breve{opacity:.55;pointer-events:none;user-select:none;position:relative}
.em-breve-badge{position:absolute;top:12px;right:12px;background:var(--accent);color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;letter-spacing:.5px}
.integr-card{position:relative}
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <p style="font-size:13px;color:var(--txt2)">Receba leads de outros sistemas e notifique automaticamente no WhatsApp</p>
  </div>
</div>

<!-- Cards de tipos de integração -->
<div class="integr-grid">

  <!-- WEBHOOK -->
  <?php $wh = array_filter($integrations, fn($i)=>$i['type']==='webhook'); ?>
  <div class="integr-card em-breve"><span class="em-breve-badge">Em breve</span>
    <div class="integr-card-header">
      <div class="integr-icon webhook">🔗</div>
      <div>
        <div class="integr-title">Webhook</div>
        <div class="integr-count"><?= count($wh) ?>/∞ conexões</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="openSettings('webhook')">⚙️</button>
    </div>
    <div class="integr-desc">Use webhooks para enviar informações de outros aplicativos ao sistema, que serão processadas e enviadas como notificação no WhatsApp.</div>
    <div class="integr-list" id="list-webhook">
      <?php foreach($wh as $i): ?>
      <div class="integr-item" id="item-<?= $i['id'] ?>">
        <div class="<?= $i['status']==='active'?'dot-active':'dot-inactive' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--txt)"><?= e($i['name']) ?></div>
          <div class="integr-item-url" onclick="copyEndpoint('<?= e($i['endpoint']) ?>')" title="Clique para copiar"><?= e($i['endpoint']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditById(<?= $i['id'] ?>)" data-integration="<?= htmlspecialchars(json_encode($i), ENT_QUOTES) ?>" id="btn-edit-<?= $i['id'] ?>" title="Configurar">⚙️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(<?= $i['id'] ?>)">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm" style="margin-top:4px" onclick="openCreate('webhook')">+ Nova integração</button>
  </div>

  <!-- ELEMENTOR -->
  <?php $el = array_filter($integrations, fn($i)=>$i['type']==='elementor'); ?>
  <div class="integr-card em-breve"><span class="em-breve-badge">Em breve</span>
    <div class="integr-card-header">
      <div class="integr-icon elementor">🅴</div>
      <div>
        <div class="integr-title">Elementor</div>
        <div class="integr-count"><?= count($el) ?>/∞ conexões</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="openSettings('elementor')">⚙️</button>
    </div>
    <div class="integr-desc">Envie notificações no WhatsApp do seu cliente quando um lead se cadastra em sua landing page feita com Elementor.</div>
    <div class="integr-list" id="list-elementor">
      <?php foreach($el as $i): ?>
      <div class="integr-item" id="item-<?= $i['id'] ?>">
        <div class="<?= $i['status']==='active'?'dot-active':'dot-inactive' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--txt)"><?= e($i['name']) ?></div>
          <div class="integr-item-url" onclick="copyEndpoint('<?= e($i['endpoint']) ?>')" title="Clique para copiar"><?= e($i['endpoint']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditById(<?= $i['id'] ?>)" data-integration="<?= htmlspecialchars(json_encode($i), ENT_QUOTES) ?>" id="btn-edit-<?= $i['id'] ?>" title="Configurar">⚙️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(<?= $i['id'] ?>)">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm" style="margin-top:4px" onclick="openCreate('elementor')">+ Nova integração</button>
  </div>

  <!-- TINTIM -->
  <?php $tt = array_filter($integrations, fn($i)=>$i['type']==='tintim'); ?>
  <div class="integr-card em-breve"><span class="em-breve-badge">Em breve</span>
    <div class="integr-card-header">
      <div class="integr-icon tintim">🤖</div>
      <div>
        <div class="integr-title">Tintim</div>
        <div class="integr-count"><?= count($tt) ?>/∞ conexões</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="openSettings('tintim')">⚙️</button>
    </div>
    <div class="integr-desc">Conecte o Tintim ao sistema e tenha o rastreamento preciso de leads gerados, diretamente nos relatórios.</div>
    <div class="integr-list" id="list-tintim">
      <?php foreach($tt as $i): ?>
      <div class="integr-item" id="item-<?= $i['id'] ?>">
        <div class="<?= $i['status']==='active'?'dot-active':'dot-inactive' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--txt)"><?= e($i['name']) ?></div>
          <div class="integr-item-url" onclick="copyEndpoint('<?= e($i['endpoint']) ?>')" title="Clique para copiar"><?= e($i['endpoint']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditById(<?= $i['id'] ?>)" data-integration="<?= htmlspecialchars(json_encode($i), ENT_QUOTES) ?>" id="btn-edit-<?= $i['id'] ?>" title="Configurar">⚙️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(<?= $i['id'] ?>)">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm" style="margin-top:4px" onclick="openCreate('tintim')">+ Nova integração</button>
  </div>

  <!-- FACEBOOK LEAD ADS -->
  <?php $fb = array_filter($integrations, fn($i)=>$i['type']==='facebook_lead'); ?>
  <div class="integr-card em-breve"><span class="em-breve-badge">Em breve</span>
    <div class="integr-card-header">
      <div class="integr-icon facebook_lead" style="font-size:20px;color:#1877F2">f</div>
      <div>
        <div class="integr-title">Formulário instantâneo</div>
        <div class="integr-count"><?= count($fb) ?>/∞ conexões</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="openSettings('facebook_lead')">⚙️</button>
    </div>
    <div class="integr-desc">Conecte os formulários nativos do Facebook Ads para notificar no WhatsApp quando um novo lead se cadastrar.</div>
    <div class="integr-list" id="list-facebook_lead">
      <?php foreach($fb as $i): ?>
      <div class="integr-item" id="item-<?= $i['id'] ?>">
        <div class="<?= $i['status']==='active'?'dot-active':'dot-inactive' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--txt)"><?= e($i['name']) ?></div>
          <div class="integr-item-url" onclick="copyEndpoint('<?= e($i['endpoint']) ?>')" title="Clique para copiar"><?= e($i['endpoint']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditById(<?= $i['id'] ?>)" data-integration="<?= htmlspecialchars(json_encode($i), ENT_QUOTES) ?>" id="btn-edit-<?= $i['id'] ?>" title="Configurar">⚙️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(<?= $i['id'] ?>)">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm" style="margin-top:4px" onclick="openCreate('facebook_lead')">+ Nova integração</button>
  </div>

  <!-- AUTENTIQUE -->
  <?php $au = array_filter($integrations, fn($i)=>$i['type']==='autentique'); ?>
  <div class="integr-card em-breve"><span class="em-breve-badge">Em breve</span>
    <div class="integr-card-header">
      <div class="integr-icon autentique">✍️</div>
      <div>
        <div class="integr-title">Autentique</div>
        <div class="integr-count"><?= count($au) ?>/∞ conexões</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="openSettings('autentique')">⚙️</button>
    </div>
    <div class="integr-desc">Receba notificações no WhatsApp quando um documento for assinado, recusado ou visualizado na plataforma Autentique.</div>
    <div class="integr-list" id="list-autentique">
      <?php foreach($au as $i): ?>
      <div class="integr-item" id="item-<?= $i['id'] ?>">
        <div class="<?= $i['status']==='active'?'dot-active':'dot-inactive' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:var(--txt)"><?= e($i['name']) ?></div>
          <div class="integr-item-url" onclick="copyEndpoint('<?= e($i['endpoint']) ?>')" title="Clique para copiar"><?= e($i['endpoint']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditById(<?= $i['id'] ?>)" data-integration="<?= htmlspecialchars(json_encode($i), ENT_QUOTES) ?>" id="btn-edit-<?= $i['id'] ?>" title="Configurar">⚙️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(<?= $i['id'] ?>)">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm" style="margin-top:4px" onclick="openCreate('autentique')">+ Nova integração</button>
  </div>

</div>

<!-- ===== MODAL CRIAR ===== -->
<div id="modalCreate" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <span id="modalCreateIcon" style="font-size:24px">🔗</span>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--txt)" id="modalCreateTitle">Nova integração</div>
        <div style="font-size:12px;color:var(--txt2)">Adicione uma integração</div>
      </div>
    </div>
    <div class="modal-body" id="modalCreateBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeCreate()">Cancelar</button>
      <button class="btn btn-primary" id="btnCreateConfirm" onclick="submitCreate()">🔗 Criar conexão</button>
    </div>
  </div>
</div>

<!-- ===== MODAL CONFIGURAR (pós-criação) ===== -->
<div id="modalConfig" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <span id="modalConfigIcon" style="font-size:24px">⚙️</span>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--txt)" id="modalConfigTitle">Configurar integração</div>
        <div style="font-size:12px;color:var(--txt2)">Siga os passos para completar</div>
      </div>
    </div>
    <div class="modal-body" id="modalConfigBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeConfig()">Fechar</button>
      <button class="btn btn-primary" id="btnConfigSave" onclick="submitConfig()">Salvar configurações</button>
    </div>
  </div>
</div>

<script>
const CSRF  = '<?= $csrf ?>';
const APP   = '<?= APP_URL ?>';
const WPS   = <?= json_encode(array_values($whatsapps)) ?>;
let currentType = '';
let currentEditId = null;
let pendingIntegration = null;

const typeLabels = {webhook:'Webhook',elementor:'Elementor',tintim:'Tintim',facebook_lead:'Formulário Facebook',autentique:'Autentique'};
const typeIcons  = {webhook:'🔗',elementor:'🅴',tintim:'🤖',facebook_lead:'📋',autentique:'✍️'};

const typeVars = {
  webhook:       ['{{lead_name}}','{{lead_email}}','{{lead_phone}}','{{utm_source}}','{{utm_campaign}}'],
  elementor:     ['{{lead_name}}','{{lead_email}}','{{lead_phone}}','{{lead_formName}}','{{utm_source}}','{{utm_campaign}}','{{utm_content}}'],
  tintim:        ['{{lead_name}}','{{lead_phone}}','{{lead_email}}','{{tintim_id}}','{{event}}'],
  facebook_lead: ['{{lead_name}}','{{lead_email}}','{{lead_phone}}'],
  autentique:    ['{{event_label}}','{{signer_name}}','{{signer_email}}','{{signer_cpf}}','{{signer_phone}}','{{doc_signed_at}}','{{doc_id}}'],
};

const defaultMsgs = {
  webhook:       '🔔 *Novo lead recebido!*\n\nNome: {{lead_name}}\nEmail: {{lead_email}}\nTelefone: {{lead_phone}}',
  elementor:     '🔔 *Novo lead via Elementor!*\n\nNome: {{lead_name}}\nEmail: {{lead_email}}\nTelefone: {{lead_phone}}\n\nFonte: {{utm_source}} | Campanha: {{utm_campaign}}',
  tintim:        '💬 *Nova conversa no Tintim!*\n\nContato: {{lead_name}}\nTelefone: {{lead_phone}}',
  facebook_lead: '📋 *Novo lead do Facebook Ads!*\n\nNome: {{lead_name}}\nEmail: {{lead_email}}\nTelefone: {{lead_phone}}',
};

function wpOptions(selected) {
  let h = '<option value="">Selecione uma instância</option>';
  WPS.forEach(w => {
    h += `<option value="${w.id}" ${w.id==selected?'selected':''}>${w.instance_name}${w.phone_number?' ('+w.phone_number+')':''}</option>`;
  });
  return h;
}

function openCreate(type) {
  currentType = type;
  document.getElementById('modalCreateIcon').textContent  = typeIcons[type];
  document.getElementById('modalCreateTitle').textContent = 'Nova integração — '+typeLabels[type];
  document.getElementById('modalCreateBody').innerHTML = `
    <div class="form-group">
      <label class="form-label">Nome da integração</label>
      <input type="text" class="form-control" id="createName" placeholder="Ex: Leads Site Principal">
    </div>`;
  document.getElementById('modalCreate').classList.add('open');
}
function closeCreate() { document.getElementById('modalCreate').classList.remove('open'); }

async function submitCreate() {
  const name = document.getElementById('createName').value.trim();
  if (!name) { alert('Digite um nome.'); return; }
  const fd = new FormData();
  fd.append('_token', CSRF); fd.append('type', currentType); fd.append('name', name);
  const r = await fetch(APP+'/integrations/create', {method:'POST',body:fd});
  const d = await r.json();
  if (d.success) {
    pendingIntegration = d.integration;
    closeCreate();
    openConfigNew(d.integration);
    addItemToList(d.integration);
  } else { alert(d.error || 'Erro ao criar'); }
}

function addItemToList(i) {
  const list = document.getElementById('list-'+i.type);
  const div = document.createElement('div');
  div.className = 'integr-item'; div.id = 'item-'+i.id;
  div.innerHTML = `
    <div class="dot-active"></div>
    <div style="flex:1;min-width:0">
      <div style="font-size:12px;font-weight:600;color:var(--txt)">${i.name}</div>
      <div class="integr-item-url" onclick="copyEndpoint('${i.endpoint}')" title="Clique para copiar">${i.endpoint}</div>
    </div>
    <button class="btn btn-ghost btn-sm btn-icon" id="btn-edit-${i.id}" data-integration='${JSON.stringify(i).replace(/'/g,"&#39;")}' onclick="openEditById(${i.id})">⚙️</button>
    <button class="btn btn-danger btn-sm btn-icon" onclick="deleteIntegration(${i.id})">🗑</button>`;
  list.appendChild(div);
}
function escJ(s){return s.replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');}

function openEditById(id) {
  const btn = document.getElementById('btn-edit-'+id);
  const json = btn ? btn.getAttribute('data-integration') : null;
  if (json) openEdit(id, JSON.parse(json));
  else openEdit(id, {id:id,type:'webhook',recipient_type:'privado',recipient_phone:'',message:'',endpoint:'',whatsapp_id:null,uuid:''});
}

function openConfigNew(i) { openEdit(i.id, i, true); }

function openEdit(id, jsonStrOrObj, isNew=false) {
  currentEditId = id;
  const i = (typeof jsonStrOrObj === 'string') ? JSON.parse(jsonStrOrObj) : jsonStrOrObj;
  const type = i.type;
  document.getElementById('modalConfigIcon').textContent  = typeIcons[type];
  document.getElementById('modalConfigTitle').textContent = typeLabels[type]+' — '+(isNew?'Configurar':'Editar');

  const vars = typeVars[type]||[];
  const varHtml = vars.map(v=>`<span class="var-tag" onclick="insertVar('${v}')">${v}</span>`).join('');

  const wpSel = WPS.length
    ? `<select class="form-control" id="cfgWp" onchange="onWpChange()">${wpOptions(i.whatsapp_id)}</select>`
    : `<select class="form-control" id="cfgWp" onchange="onWpChange()"><option value="">⚠️ Nenhum WhatsApp conectado</option></select>
       <div style="font-size:11px;color:var(--warn);margin-top:4px"><a href="${APP}/whatsapp" style="color:var(--warn)">Conectar WhatsApp →</a></div>`;

  let extra = '';
  if (type === 'facebook_lead') {
    extra = `
    <div class="fb-steps" style="margin-bottom:16px">
      <strong>Como conectar o Facebook Lead Ads:</strong>
      <ol>
        <li>Copie a URL do webhook abaixo</li>
        <li>Acesse o <a href="https://developers.facebook.com" target="_blank" style="color:var(--accent)">Facebook Developers</a></li>
        <li>No seu App → Webhooks → Adicionar subscription para <strong>leadgen</strong></li>
        <li>Cole a URL e o token de verificação: <code style="color:var(--accent)">${i.uuid}</code></li>
        <li>Salve e teste enviando um lead de teste no Gerenciador de Anúncios</li>
      </ol>
    </div>`;
  } else if (type === 'tintim') {
    extra = `
    <div class="fb-steps" style="margin-bottom:16px">
      <strong>Como conectar ao Tintim:</strong>
      <ol>
        <li>Copie a URL do webhook abaixo</li>
        <li>No Tintim → Integrações → Nova integração → Cole a URL nos campos:<br>
          <em>URL que receberá dados toda vez que uma conversa for criada</em><br>
          <em>URL que receberá dados toda vez que uma conversa for alterada</em>
        </li>
        <li>Clique em Salvar</li>
      </ol>
    </div>`;
  } else if (type === 'autentique') {
    extra = `
    <div class="fb-steps" style="margin-bottom:16px">
      <strong>Como conectar ao Autentique:</strong>
      <ol>
        <li>Copie a URL do webhook abaixo</li>
        <li>No Autentique → Perfil → <strong>Webhooks</strong> → Adicionar endpoint</li>
        <li>Cole a URL e salve</li>
        <li>A cada documento assinado, você receberá a notificação no WhatsApp</li>
      </ol>
    </div>`;
  } else if (type === 'elementor') {
    extra = `
    <div class="fb-steps" style="margin-bottom:16px">
      <strong>Como conectar ao Elementor:</strong>
      <ol>
        <li>Copie a URL do webhook abaixo</li>
        <li>No Elementor → edite o formulário → aba Ações após envio</li>
        <li>Adicione a ação <strong>Webhook</strong> e cole a URL</li>
        <li>Publique o formulário</li>
      </ol>
    </div>`;
  }

  document.getElementById('modalConfigBody').innerHTML = `
    ${extra}
    <div class="step-row">
      <div class="step-num">✏️</div>
      <div class="step-body">
        <div class="step-title">Nome da integração</div>
        <input type="text" class="form-control" id="cfgName" value="${i.name||''}" placeholder="Ex: Assinatura - Contrato" style="margin-top:4px">
      </div>
    </div>
    <div class="step-row">
      <div class="step-num">1</div>
      <div class="step-body">
        <div class="step-title">Crie a mensagem da notificação</div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <div class="vars-wrap" style="margin-bottom:0;flex:1">${varHtml}</div>
          <button onclick="openTemplatePicker()" class="btn btn-ghost btn-sm" style="flex-shrink:0;margin-left:8px;font-size:11px">📋 Usar template</button>
        </div>
        <div class="msg-preview" id="msgPreview" contenteditable="true" oninput="updatePreview()" style="outline:none">${i.message || defaultMsgs[type]}</div>
      </div>
    </div>
    <div class="step-row">
      <div class="step-num">2</div>
      <div class="step-body">
        <div class="step-title">Selecione quem vai receber</div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;margin-bottom:10px">
          <div>
            <label class="form-label">Conta de WhatsApp</label>
            <select class="form-control" id="cfgWp" onchange="onWpChange()">${wpOptions(i.whatsapp_id)}</select>
          </div>
          <div>
            <label class="form-label">Tipo</label>
            <select class="form-control" id="cfgType" onchange="toggleRecipient()">
              <option value="privado"  ${i.recipient_type==='privado' ?'selected':''}>Privado</option>
              <option value="grupo"    ${i.recipient_type==='grupo'   ?'selected':''}>Grupo</option>
              <option value="cliente"  ${i.recipient_type==='cliente' ?'selected':''}>Cliente</option>
            </select>
          </div>
        </div>
        <div id="cfgRecipientWrap">
          <label class="form-label" id="cfgRecipientLabel">Número (com DDD, ex: 5581999999999)</label>
          <input type="text" class="form-control" id="cfgRecipient" value="${i.recipient_phone||''}" placeholder="5581999999999">
          <div id="cfgClienteInfo" style="display:none;margin-top:6px;font-size:12px;color:var(--txt2)">O número será puxado do cadastro do cliente vinculado à conta de anúncio que disparou o lead.</div>
        </div>
      </div>
    </div>
    <div class="step-row">
      <div class="step-num">3</div>
      <div class="step-body">
        <div class="step-title">Copie o webhook gerado</div>
        <div class="endpoint-box">
          <span id="endpointText">${i.endpoint}</span>
          <button class="copy-btn" onclick="copyEndpoint('${i.endpoint}')">📋 Copiar</button>
        </div>
      </div>
    </div>`;

  document.getElementById('modalConfig').classList.add('open');
  // Força o tipo correto via JS (não depende do 'selected' no HTML)
  const cfgTypeEl = document.getElementById('cfgType');
  if (cfgTypeEl) cfgTypeEl.value = i.recipient_type || 'privado';
  setTimeout(toggleRecipient, 80);
}

// Quando muda só o WhatsApp — só recarrega grupos se tipo já for grupo
function onWpChange() {
  const t = document.getElementById('cfgType')?.value;
  if (t === 'grupo') loadGroups();
}

function toggleRecipient() {
  const t   = document.getElementById('cfgType')?.value;
  const lbl = document.getElementById('cfgRecipientLabel');
  const inp = document.getElementById('cfgRecipient');
  const info= document.getElementById('cfgClienteInfo');
  const wrap= document.getElementById('cfgRecipientWrap');
  if (!lbl) return;

  // Remove dynamic select if exists
  document.getElementById('cfgDynSelect')?.remove();

  if (t === 'grupo') {
    lbl.textContent = 'Grupo do WhatsApp';
    inp.style.display = 'none';
    if(info) info.style.display='none';
    loadGroups();
  } else if (t === 'cliente') {
    lbl.textContent = 'Cliente';
    inp.style.display = 'none';
    if(info) info.style.display='block';
    loadClients();
  } else {
    lbl.textContent = 'Número (com DDD, ex: 5581999999999)';
    inp.placeholder = '5581999999999';
    inp.style.display = 'block';
    if(info) info.style.display='none';
  }
}

async function loadGroups() {
  const wpId = document.getElementById('cfgWp')?.value;
  const wrap = document.getElementById('cfgRecipientWrap');
  const inp  = document.getElementById('cfgRecipient');

  document.getElementById('cfgDynSelect')?.remove();
  const sel = document.createElement('select');
  sel.className='form-control'; sel.id='cfgDynSelect';
  sel.innerHTML='<option value="">Carregando grupos...</option>';
  inp.style.display='none';
  wrap.appendChild(sel);

  if (!wpId) { sel.innerHTML='<option value="">Selecione um WhatsApp primeiro</option>'; return; }

  try {
    const r = await fetch(APP+'/integrations/groups?whatsapp_id='+wpId);
    const d = await r.json();
    if (d.success && d.grupos && d.grupos.length) {
      const cur = inp.value;
      sel.innerHTML = '<option value="">Selecione um grupo</option>'
        + d.grupos.map(g=>`<option value="${g.id}" ${cur===g.id?'selected':''}>${g.nome}</option>`).join('');
      if (cur) sel.value = cur;
    } else {
      sel.innerHTML=`<option value="">${d.error||'Nenhum grupo encontrado — verifique se o WhatsApp está conectado'}</option>`;
    }
  } catch(e) {
    sel.innerHTML='<option value="">Erro ao carregar grupos</option>';
  }
}

async function loadClients() {
  const wrap = document.getElementById('cfgRecipientWrap');
  const inp  = document.getElementById('cfgRecipient');

  document.getElementById('cfgDynSelect')?.remove();
  const sel = document.createElement('select');
  sel.className='form-control'; sel.id='cfgDynSelect';
  sel.innerHTML='<option value="">Carregando clientes...</option>';
  inp.style.display='none';
  wrap.appendChild(sel);

  try {
    const r = await fetch(APP+'/integrations/clients');
    const d = await r.json();
    if (d.success && d.clients && d.clients.length) {
      const cur = inp.value;
      sel.innerHTML = '<option value="">Selecione um cliente</option>'
        + d.clients.map(c=>{
            const phone = (c.phone||'').replace(/\D/g,'');
            return `<option value="${phone}" ${cur===phone?'selected':''}>${c.name}${c.phone?' — '+c.phone:' (sem telefone)'}</option>`;
          }).join('');
      if (cur) sel.value = cur;
    } else {
      sel.innerHTML='<option value="">Nenhum cliente cadastrado</option>';
    }
  } catch(e) {
    sel.innerHTML='<option value="">Erro ao carregar clientes</option>';
  }
}

function preg_digits(s){ return s.replace(/\D/g,''); }

async function submitConfig() {
  const wpId  = document.getElementById('cfgWp')?.value || '';
  const dtype = document.getElementById('cfgType')?.value || 'privado';
  const dynSel = document.getElementById('cfgDynSelect');
  const dest  = dynSel ? dynSel.value : (document.getElementById('cfgRecipient')?.value.trim() || '');
  const msg   = document.getElementById('msgPreview')?.innerText.trim() || '';

  if (!wpId) { alert('Selecione uma instância de WhatsApp.'); return; }
  if (!dest) { alert('Selecione ou informe o destinatário.'); return; }

  const cfgNameVal = (document.getElementById('cfgName')?.value || '').trim();

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id', currentEditId);
  fd.append('whatsapp_id', wpId);
  fd.append('recipient_phone', dest);
  fd.append('recipient_type', dtype);
  fd.append('message', msg);
  fd.append('status', 'active');
  if (cfgNameVal) fd.append('name', cfgNameVal);

  const r = await fetch(APP+'/integrations/update', {method:'POST', body:fd});
  let d;
  try { d = await r.json(); } catch(e) { alert('Erro ao salvar. Verifique o servidor.'); return; }

  if (d.success) {
    // Atualiza o dot do item na lista para verde (ativo)
    const item = document.getElementById('item-'+currentEditId);
    if (item) {
      const dot = item.querySelector('.dot-active, .dot-inactive');
      if (dot) { dot.className = 'dot-active'; }
      // Atualiza o nome exibido na lista se foi alterado
      if (cfgNameVal) {
        const nameEl = item.querySelector('[style*="font-weight:600"]');
        if (nameEl) nameEl.textContent = cfgNameVal;
      }
    }
    // Atualiza o botão de edição com os novos dados
    const btn = document.getElementById('btn-edit-'+currentEditId);
    if (btn) {
      try {
        const integData = JSON.parse(btn.getAttribute('data-integration') || '{}');
        integData.whatsapp_id = wpId;
        integData.recipient_phone = dest;
        integData.recipient_type = dtype;
        integData.message = msg;
        if (cfgNameVal) integData.name = cfgNameVal;
        btn.setAttribute('data-integration', JSON.stringify(integData));
      } catch(e) {}
    }
    closeConfig();
    showToast('✅ Configuração salva!');
  } else {
    alert(d.error||'Erro ao salvar');
  }
}

function insertVar(v) {
  const el = document.getElementById('msgPreview');
  el.focus();
  document.execCommand('insertText', false, v);
}
function updatePreview() {}

function closeConfig() { document.getElementById('modalConfig').classList.remove('open'); pendingIntegration=null; }

async function deleteIntegration(id) {
  if (!confirm('Remover esta integração?')) return;
  const fd = new FormData(); fd.append('_token',CSRF); fd.append('id',id);
  const r = await fetch(APP+'/integrations/delete',{method:'POST',body:fd});
  const d = await r.json();
  if (d.success) { document.getElementById('item-'+id)?.remove(); showToast('Removida!'); }
}

function openSettings(type) {
  const items = document.querySelectorAll('#list-'+type+' .integr-item');
  if (items.length === 0) { openCreate(type); return; }
  // Abre o edit do primeiro item
  const first = items[0];
  const id = first.id.replace('item-','');
  const btn = first.querySelector('.btn-ghost');
  if (btn) btn.click();
}

function copyEndpoint(url) {
  navigator.clipboard.writeText(url).then(()=>showToast('URL copiada!')).catch(()=>{
    const el=document.createElement('textarea');el.value=url;document.body.appendChild(el);el.select();document.execCommand('copy');document.body.removeChild(el);showToast('URL copiada!');
  });
}

function showToast(msg) {
  const t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:24px;right:24px;background:#27ae60;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.3)';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(()=>t.remove(),2500);
}

// Fecha modal ao clicar fora
['modalCreate','modalConfig','modalTpl'].forEach(id=>{
  const el = document.getElementById(id);
  if (el) el.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
});

// ── Template Picker ──────────────────────────────────────────
async function openTemplatePicker() {
  const modal = document.getElementById('modalTpl');
  const body  = document.getElementById('modalTplBody');
  body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--txt2)">Carregando templates...</div>';
  modal.classList.add('open');

  try {
    const r = await fetch(APP+'/api/integrations/templates');
    const d = await r.json();
    if (!d.success || !d.templates || d.templates.length === 0) {
      body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--txt2)">Nenhum template encontrado.<br><a href="'+APP+'/templates" style="color:var(--accent)" target="_blank">Criar templates →</a></div>';
      return;
    }
    body.innerHTML = d.templates.map(t => `
      <div onclick="applyTemplate(${JSON.stringify(t.content).replace(/"/g,'&quot;')})" style="cursor:pointer;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;margin-bottom:8px;transition:.15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:13px;font-weight:600;color:var(--txt);margin-bottom:4px">${t.name}${t.is_default?'  <span style=\'font-size:10px;color:var(--accent)\'>padrão</span>':''}</div>
        <div style="font-size:11px;color:var(--txt2);white-space:pre-wrap;max-height:60px;overflow:hidden">${t.content}</div>
      </div>`).join('');
  } catch(e) {
    body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger)">Erro ao carregar templates.</div>';
  }
}

function applyTemplate(content) {
  const el = document.getElementById('msgPreview');
  if (el) el.innerText = content;
  document.getElementById('modalTpl').classList.remove('open');
  showToast('📋 Template aplicado!');
}
</script>

<!-- Modal Template Picker -->
<div id="modalTpl" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <span style="font-size:22px">📋</span>
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--txt)">Escolher Template</div>
        <div style="font-size:12px;color:var(--txt2)">Clique para aplicar na mensagem</div>
      </div>
      <button onclick="document.getElementById('modalTpl').classList.remove('open')" class="btn btn-ghost btn-sm btn-icon" style="margin-left:auto">✕</button>
    </div>
    <div class="modal-body" id="modalTplBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('modalTpl').classList.remove('open')">Cancelar</button>
      <a href="<?= APP_URL ?>/templates" target="_blank" class="btn btn-ghost btn-sm">+ Criar novo template</a>
    </div>
  </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
?>
