<?php
// views/ai/history.php
$objColors = [
    'trafego'    => '#5B8DEF','tráfego'    => '#5B8DEF',
    'mensagem'   => '#1ABC9C','conversa'   => '#1ABC9C',
    'lead'       => '#9B59B6',
    'venda'      => '#2ECC71','compra'     => '#2ECC71',
    'video'      => '#3498DB','vídeo'      => '#3498DB',
    'alcance'    => '#F39C12','engajamento'=> '#E67E22',
];
?>
<style>
.hist-filters{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center}
.hist-table{width:100%;border-collapse:collapse;font-size:13px}
.hist-table th{text-align:left;padding:8px 12px;color:var(--txt3);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.hist-table td{padding:10px 12px;border-bottom:1px solid var(--border2);vertical-align:top}
.hist-table tr:hover td{background:var(--bg3)}
.obj-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;display:inline-block}
.analysis-preview{font-size:11px;color:var(--txt3);max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer}
.analysis-preview:hover{color:var(--txt)}
/* Modal */
.hist-modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:16px}
.hist-modal-ov.open{display:flex}
.hist-modal{background:var(--bg2);border:1px solid var(--border);border-radius:14px;width:100%;max-width:640px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden}
.hist-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-shrink:0}
.hist-modal-body{padding:18px;overflow-y:auto;font-size:13px;line-height:1.7;color:var(--txt);white-space:pre-wrap}
.pag{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:20px}
.pag a,.pag span{padding:5px 12px;border-radius:6px;font-size:12px;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);text-decoration:none}
.pag a:hover{background:var(--bg4);color:var(--txt)}
.pag .active{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:700}
</style>

<div style="max-width:1100px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <div>
      <h2 style="font-size:18px;font-weight:700;color:var(--txt)">Histórico de Análises IA</h2>
      <div style="font-size:12px;color:var(--txt3)"><?= $total ?> análise(s) encontrada(s)</div>
    </div>
    <a href="<?= APP_URL ?>/ai" class="btn btn-primary btn-sm">+ Nova Análise</a>
  </div>

  <!-- Filtros -->
  <form method="GET" action="<?= APP_URL ?>/ai/history" class="hist-filters">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar campanha ou conta..."
      class="form-control" style="max-width:240px">
    <select name="acc" class="form-control" style="max-width:200px">
      <option value="">Todas as contas</option>
      <?php foreach($accounts as $a): ?>
        <option value="<?= $a['ad_account_id'] ?>" <?= $accId==$a['ad_account_id']?'selected':'' ?>>
          <?= e($a['account_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="obj" class="form-control" style="max-width:160px">
      <option value="">Todos os objetivos</option>
      <?php foreach(['trafego','mensagem','lead','venda','video','alcance','engajamento'] as $o): ?>
        <option value="<?= $o ?>" <?= $obj===$o?'selected':'' ?>><?= ucfirst($o) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
    <?php if($search||$accId||$obj): ?>
      <a href="<?= APP_URL ?>/ai/history" class="btn btn-ghost btn-sm">Limpar</a>
    <?php endif; ?>
  </form>

  <?php if(empty($logs)): ?>
  <div class="card">
    <div class="empty-state">
      <span class="material-icons-outlined">auto_awesome</span>
      <h3>Nenhuma análise encontrada</h3>
      <p>As análises feitas pela IA aparecerão aqui.</p>
      <a href="<?= APP_URL ?>/ai" class="btn btn-primary">Fazer primeira análise</a>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="hist-table" style="min-width:820px">
      <thead>
        <tr>
          <th style="min-width:160px">Conta / Campanha</th>
          <th style="min-width:160px">Período</th>
          <th style="min-width:100px">Objetivo</th>
          <th style="min-width:110px">Modelo</th>
          <th style="min-width:240px">Análise</th>
          <th style="min-width:110px">Data</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($logs as $l):
        $objKey  = strtolower(trim($l['objective'] ?? ''));
        $objColor= $objColors[$objKey] ?? 'var(--txt3)';
        $preview = strip_tags($l['analysis_text'] ?? '');
        $preview = preg_replace('/\*+/', '', $preview);
        $preview = substr(trim($preview), 0, 120);
      ?>
      <tr>
        <td style="vertical-align:middle">
          <div style="font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px"><?= e($l['account_name'] ?? $l['acc_name'] ?? '—') ?></div>
          <div style="font-size:11px;color:var(--txt3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px"><?= e($l['campaign_name'] ?? '') ?></div>
        </td>
        <td style="font-size:12px;color:var(--txt2);white-space:nowrap;vertical-align:middle"><?= e($l['period'] ?? '—') ?></td>
        <td style="vertical-align:middle">
          <?php if($objKey): ?>
            <span class="obj-badge" style="background:<?= $objColor ?>22;color:<?= $objColor ?>;border:1px solid <?= $objColor ?>44">
              <?= e($l['objective']) ?>
            </span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td style="font-size:11px;color:var(--txt3);white-space:nowrap;vertical-align:middle"><?= e($l['model'] ?? '—') ?></td>
        <td style="vertical-align:middle">
          <span class="analysis-preview" onclick="openHistModal(<?= $l['id'] ?>, this.dataset.full)"
            data-full="<?= e(htmlspecialchars($l['analysis_text'] ?? '', ENT_QUOTES)) ?>">
            <?= e($preview) ?>…
          </span>
        </td>
        <td style="font-size:11px;color:var(--txt3);white-space:nowrap;vertical-align:middle">
          <?= date('d/m/Y H:i', strtotime($l['created_at'])) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Paginação -->
  <?php if($totalPages > 1): ?>
  <div class="pag">
    <?php if($page > 1): ?>
      <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&acc=<?= $accId ?>&obj=<?= urlencode($obj) ?>">‹ Anterior</a>
    <?php endif; ?>
    <?php for($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
      <?php if($p == $page): ?>
        <span class="active"><?= $p ?></span>
      <?php else: ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&acc=<?= $accId ?>&obj=<?= urlencode($obj) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if($page < $totalPages): ?>
      <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&acc=<?= $accId ?>&obj=<?= urlencode($obj) ?>">Próxima ›</a>
    <?php endif; ?>
    <span style="border:none;background:none;color:var(--txt3)">Pág. <?= $page ?>/<?= $totalPages ?></span>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Modal análise completa -->
<div class="hist-modal-ov" id="histModal">
  <div class="hist-modal">
    <div class="hist-modal-hdr">
      <span style="font-size:14px;font-weight:700;color:var(--txt)">✨ Análise Completa</span>
      <button onclick="document.getElementById('histModal').classList.remove('open')"
        style="background:none;border:none;cursor:pointer;color:var(--txt3);font-size:20px">✕</button>
    </div>
    <div class="hist-modal-body" id="histModalBody"></div>
    <div class="hist-modal-ftr" style="padding:12px 18px;border-top:1px solid var(--border);flex-shrink:0">
      <div style="display:flex;gap:6px;margin-bottom:8px">
        <button type="button" onclick="histSndTipo('phone')" id="histBtnPhone" class="btn btn-secondary btn-sm" style="flex:1;font-size:11px">📱 Número</button>
        <button type="button" onclick="histSndTipo('client')" id="histBtnClient" class="btn btn-primary btn-sm" style="flex:1;font-size:11px">👤 Cliente</button>
        <button type="button" onclick="histSndTipo('group')" id="histBtnGroup" class="btn btn-secondary btn-sm" style="flex:1;font-size:11px">👥 Grupo WA</button>
      </div>
      <div id="histPhoneDiv" style="display:none;margin-bottom:8px">
        <input type="tel" id="histPhoneInput" class="form-control form-control-sm" placeholder="5581999999999">
      </div>
      <div id="histClientDiv" style="margin-bottom:8px">
        <select id="histClientSel" class="form-control form-control-sm">
          <option value="">— Selecione o cliente —</option>
          <?php foreach(($clients??[]) as $cl): ?>
            <?php if(!empty($cl['phone'])): ?>
            <option value="<?=htmlspecialchars($cl['phone'])?>"><?=htmlspecialchars($cl['name'])?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="histGroupDiv" style="display:none;margin-bottom:8px">
        <div style="display:flex;gap:6px">
          <select id="histGrpSel" class="form-control form-control-sm" style="flex:1"><option value="">— Selecione o grupo —</option></select>
          <button type="button" onclick="histLoadGroups()" class="btn btn-secondary btn-sm">🔄 Buscar</button>
        </div>
        <div id="histGrpStatus" style="font-size:11px;color:var(--txt3);margin-top:3px"></div>
      </div>
      <button id="histWaBtn" onclick="sendAiAnalysisWa()"
        style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:#25D366;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
        <i class="fa-brands fa-whatsapp" style="font-size:15px"></i> Enviar Agora
      </button>
    </div>
  </div>
</div>

<script>
var APP_URL   = '<?=APP_URL?>';
var CSRF      = '<?=e($_SESSION["csrf_token"] ?? "")?>';
var INSTANCES = <?=json_encode(array_values($instances??[]))?>;
var _histAnalysisText = '';

async function histLoadGroups() {
  var grpEl = document.getElementById('histGrpOptgroup');
  if (!grpEl || !INSTANCES || !INSTANCES.length) return;
  for (var i = 0; i < INSTANCES.length; i++) {
    var wpId = INSTANCES[i].id || '';
    if (!wpId) continue;
    try {
      var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wpId), {credentials:'include'});
      var d = await r.json();
      if (d.success && d.groups && d.groups.length > 0) {
        grpEl.innerHTML = '';
        d.groups.forEach(function(g){
          var opt = document.createElement('option');
          opt.value = 'group|'+(g.id||g.remoteJid||'')+'|'+(g.instance||INSTANCES[i].instance_name||'');
          opt.textContent = g.name||g.subject||'Grupo';
          grpEl.appendChild(opt);
        });
        break;
      }
    } catch(e){}
  }
}


var _histSndTipo = 'client';

function histSndTipo(tipo) {
  _histSndTipo = tipo;
  document.getElementById('histPhoneDiv').style.display  = tipo==='phone'  ? 'block' : 'none';
  document.getElementById('histClientDiv').style.display = tipo==='client' ? 'block' : 'none';
  document.getElementById('histGroupDiv').style.display  = tipo==='group'  ? 'block' : 'none';
  ['histBtnPhone','histBtnClient','histBtnGroup'].forEach(function(id){
    var b = document.getElementById(id);
    if(b) b.className = 'btn btn-sm' + (id === 'histBtn'+tipo.charAt(0).toUpperCase()+tipo.slice(1) ? ' btn-primary' : ' btn-secondary');
  });
  if (tipo === 'group') histLoadGroups();
}

async function histLoadGroups() {
  var grpSel = document.getElementById('histGrpSel');
  var status = document.getElementById('histGrpStatus');
  if (!grpSel || !INSTANCES || !INSTANCES.length) return;
  if(status) status.textContent = '⏳ Carregando...';
  for (var i = 0; i < INSTANCES.length; i++) {
    var wpId = INSTANCES[i].id || '';
    if (!wpId) continue;
    try {
      var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wpId), {credentials:'include'});
      var d = await r.json();
      if (d.success && d.grupos && d.grupos.length > 0) {
        grpSel.innerHTML = '<option value="">— Selecione o grupo —</option>';
        d.grupos.forEach(function(g){
          var opt = document.createElement('option');
          opt.value = (g.id||'')+'|'+(INSTANCES[i].instance_name||'');
          opt.textContent = g.nome||g.name||g.subject||'Grupo';
          grpSel.appendChild(opt);
        });
        if(status) status.textContent = '✅ '+d.grupos.length+' grupo(s)';
        return;
      }
    } catch(e){}
  }
  if(status) status.textContent = '⚠️ Nenhum grupo encontrado';
}

function openHistModal(id, text) {
  var clean = (text||'').replace(/\*/g,'').replace(/\\n/g,'\n');
  _histAnalysisText = clean;
  document.getElementById('histModalBody').textContent = clean;
  resetHistWaBtn();
  histSndTipo('client');
  document.getElementById('histModal').classList.add('open');
}
document.getElementById('histModal').addEventListener('click', function(e){
  if(e.target===this) this.classList.remove('open');
});

function resetHistWaBtn() {
  var btn = document.getElementById('histWaBtn');
  if (!btn) return;
  btn.disabled = false;
  btn.style.background = '#25D366';
  btn.innerHTML = '<i class="fa-brands fa-whatsapp" style="font-size:14px"></i> Enviar';
}

async function sendAiAnalysisWa() {
  if (!_histAnalysisText) { alert('Nenhum conteúdo para enviar'); return; }
  var phone = '', groupId = '', wpInstName = '';
  if (_histSndTipo === 'phone') {
    phone = (document.getElementById('histPhoneInput')||{}).value||'';
    if(!phone){alert('Informe o número.');return;}
  } else if (_histSndTipo === 'client') {
    phone = (document.getElementById('histClientSel')||{}).value||'';
    if(!phone){alert('Selecione o cliente.');return;}
  } else if (_histSndTipo === 'group') {
    var grpVal = ((document.getElementById('histGrpSel')||{}).value||'').split('|');
    groupId = grpVal[0]||'';
    wpInstName = grpVal[1]||'';
    if(!groupId){alert('Selecione o grupo.');return;}
  }

  var btn = document.getElementById('histWaBtn');
  // Efeito de enviando
  btn.disabled = true;
  btn.style.background = '#128C7E';
  btn.innerHTML = '<i class="fa-brands fa-whatsapp" style="font-size:14px"></i> <span id="histWaDotsAnim">Enviando</span>';
  var dots = 0;
  var anim = setInterval(function(){
    dots = (dots+1)%4;
    var el = document.getElementById('histWaDotsAnim');
    if(el) el.textContent = 'Enviando' + '.'.repeat(dots);
  }, 400);



  // Pegar instância padrão
  var wpId = INSTANCES.length > 0 ? INSTANCES[0].id : 0;

  var fd = new FormData();
  fd.append('_token',      CSRF);
  fd.append('message',     _histAnalysisText);
  fd.append('whatsapp_id', wpId);
  if (_histSndTipo === 'group') {
    fd.append('recv_type',     'group');
    fd.append('group_id',      groupId);
    fd.append('instance_name', wpInstName);
  } else {
    fd.append('recv_type', 'phone');
    fd.append('phone',     phone);
  }

  try {
    var r = await fetch(APP_URL + '/ai/send', {method:'POST', body:fd});
    var d = await r.json();
    clearInterval(anim);
    if (d.success) {
      btn.style.background = '#27ae60';
      btn.innerHTML = '✅ Enviado!';
      setTimeout(resetHistWaBtn, 2500);
    } else {
      btn.style.background = '#e74c3c';
      btn.innerHTML = '❌ ' + (d.error||'Erro');
      setTimeout(resetHistWaBtn, 3000);
    }
  } catch(e) {
    clearInterval(anim);
    btn.style.background = '#e74c3c';
    btn.innerHTML = '❌ Erro de conexão';
    setTimeout(resetHistWaBtn, 3000);
  }
}
</script>
