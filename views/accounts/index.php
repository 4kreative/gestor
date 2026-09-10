<?php
$pageTitle   = 'Contas de Anúncio';
$currentPage = 'accounts';
ob_start();
?>
<div style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
  <div>
    <p style="font-size:13px;color:var(--txt2)">Conecte suas contas de Meta Ads e Google Ads para sincronizar métricas</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-primary" onclick="openMetaModal()">
      <i class="fa-brands fa-facebook"></i> Conectar Meta Ads
    </button>
    <span class="btn btn-secondary" style="border-color:#ccc;color:#999;cursor:not-allowed;opacity:.6;pointer-events:none" title="Em breve">
      <i class="fa-brands fa-google"></i> Google Ads — Em breve
    </span>
  </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card">
  <div class="empty-state">
    <span class="material-icons-outlined">account_balance</span>
    <h3>Nenhuma conta conectada</h3>
    <p>Conecte suas contas de Meta Ads e Google Ads para sincronizar métricas automaticamente</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <button class="btn btn-primary" onclick="openMetaModal()">
        <i class="fa-brands fa-facebook"></i> Conectar Meta Ads
      </button>
    </div>
  </div>
</div>
<?php else: ?>
<!-- Campo de busca -->
<div style="margin-bottom:10px">
  <div style="position:relative">
    <span class="material-icons-outlined" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--txt3);font-size:18px;pointer-events:none">search</span>
    <input type="text" id="accountSearch" placeholder="Buscar conta por nome ou ID..."
      oninput="filterAccounts(this.value)"
      style="width:100%;padding:9px 12px 9px 36px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);color:var(--txt);font-size:13px;font-family:var(--font);box-sizing:border-box;outline:none"
      onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
  </div>
  <div id="searchInfo" style="font-size:11px;color:var(--txt3);margin-top:4px;display:none"></div>
</div>

<!-- Barra de seleção múltipla -->
<div id="bulkBar" style="display:none;align-items:center;gap:10px;background:var(--bg2);border:1px solid var(--danger);border-radius:var(--radius);padding:10px 14px;margin-bottom:10px">
  <span class="material-icons-outlined" style="color:var(--danger);font-size:18px">warning</span>
  <span id="bulkCount" style="font-size:13px;font-weight:600;color:var(--txt)">0 contas selecionadas</span>
  <button onclick="bulkDelete()" class="btn btn-danger btn-sm" style="margin-left:auto">
    <span class="material-icons-outlined" style="font-size:14px">delete</span> Deletar Selecionadas
  </button>
  <button onclick="clearSelection()" class="btn btn-secondary btn-sm">Cancelar</button>
</div>

<div class="table-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
  <table>
    <thead>
      <tr>
        <th style="width:36px">
          <input type="checkbox" id="checkAll" title="Selecionar todas"
            style="width:15px;height:15px;cursor:pointer;accent-color:var(--accent)"
            onchange="toggleAll(this)">
        </th>
        <th>Conta</th><th>Plataforma</th><th>Cliente</th><th>Status</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td style="width:36px">
          <input type="checkbox" name="del_ids[]" value="<?= $a['id'] ?>" class="row-check"
            style="width:15px;height:15px;cursor:pointer;accent-color:var(--accent)">
        </td>
        <td>
          <div style="font-weight:600;color:var(--txt)"><?= e($a['account_name']) ?></div>
          <div style="font-size:11px;color:var(--txt3)">ID: <?= e($a['account_id']) ?></div>
        </td>
        <td>
          <?php if ($a['platform']==='meta'): ?>
            <span class="badge badge-blue"><i class="fa-brands fa-facebook" style="font-size:10px"></i> Meta Ads</span>
          <?php else: ?>
            <span class="badge" style="background:rgba(234,67,53,.1);color:#EA4335;font-size:11px;padding:3px 9px"><i class="fa-brands fa-google" style="font-size:10px"></i> Google Ads</span>
          <?php endif; ?>
        </td>
        <td>
          <select class="form-control form-control-sm client-link-sel"
            data-acc="<?= $a['id'] ?>"
            style="font-size:11px;padding:3px 28px 3px 6px;width:100%;min-width:180px;max-width:320px"
            onchange="linkClient(this)">
            <option value="">— Sem cliente —</option>
            <?php foreach($clients as $cl): 
                $label = e($cl['name']);
                if (!empty($cl['company'])) $label .= ' — ' . e($cl['company']);
            ?>
              <option value="<?= $cl['id'] ?>" <?= ((int)$a['client_id'] === (int)$cl['id']) ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <?php $sc=['active'=>'badge-green','inactive'=>'badge-gray','error'=>'badge-red'];
                $sl=['active'=>'Ativa','inactive'=>'Inativa','error'=>'Erro']; ?>
          <span class="badge <?= $sc[$a['status']]??'badge-gray' ?> badge-sm"><?= $sl[$a['status']]??$a['status'] ?></span>
        </td>
        <td>
          <div style="display:flex;gap:4px">
            <form method="POST" action="<?= APP_URL ?>/accounts/sync">
              <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="account_id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" title="Sincronizar métricas">
                <span class="material-icons-outlined" style="font-size:14px">sync</span> Sincronizar
              </button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/accounts/delete">
              <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon"
                      data-confirm="Remover conta <?= e($a['account_name']) ?>?">
                <span class="material-icons-outlined" style="font-size:14px">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ========== MODAL META ADS TOKEN ========== -->
<div id="metaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:540px;padding:28px">

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <i class="fa-brands fa-facebook" style="font-size:24px;color:#1877F2"></i>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--txt)">Conectar Meta Ads</div>
        <div style="font-size:12px;color:var(--txt2)">Cole o token de acesso para conectar suas contas</div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:20px;background:var(--bg3);padding:4px;border-radius:var(--radius)">
      <button type="button" onclick="switchTab('token')" id="tabToken"
        style="flex:1;padding:7px;border:none;border-radius:6px;font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;background:var(--accent);color:#fff;transition:all .15s">
        Token Manual
      </button>
      <button type="button" onclick="switchTab('oauth')" id="tabOAuth"
        style="flex:1;padding:7px;border:none;border-radius:6px;font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;background:transparent;color:var(--txt2);transition:all .15s">
        OAuth (Login FB)
      </button>
    </div>

    <!-- Tab Token -->
    <div id="panelToken">
      <div class="alert alert-info" style="margin-bottom:16px">
        <span class="material-icons-outlined">info</span>
        <div>
          <strong>Como obter o token:</strong><br>
          1. Acesse <a href="https://developers.facebook.com/tools/explorer" target="_blank">developers.facebook.com/tools/explorer</a><br>
          2. Selecione o app <strong>EMPRESA - ADS</strong><br>
          3. Marque: <code>ads_read</code>, <code>ads_management</code>, <code>business_management</code><br>
          4. Clique <strong>"Gerar token de acesso"</strong> e copie
        </div>
      </div>

      <form method="POST" action="<?= APP_URL ?>/accounts/connect/meta-token">
        <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <div class="form-group">
          <label class="form-label">Token de Acesso Meta <span class="req">*</span></label>
          <textarea name="access_token" class="form-control" rows="4"
            placeholder="Cole aqui o token gerado no Graph API Explorer..." required
            style="font-family:monospace;font-size:12px"></textarea>
          <div class="form-hint">O token será usado para buscar automaticamente todas as suas contas de anúncio</div>
        </div>
        <div class="form-actions" style="border-top:none;padding-top:0;gap:8px">
          <button type="submit" class="btn btn-primary">
            <i class="fa-brands fa-facebook"></i> Conectar Contas
          </button>
          <button type="button" class="btn btn-secondary" onclick="closeMetaModal()">Cancelar</button>
        </div>
      </form>
    </div>

    <!-- Tab OAuth -->
    <div id="panelOAuth" style="display:none">
      <div class="alert alert-warn" style="margin-bottom:16px">
        <span class="material-icons-outlined">warning</span>
        <div>Requer configuração do Facebook Login no App. Se estiver com erro de domínio, use a aba <strong>Token Manual</strong>.</div>
      </div>
      <div style="text-align:center;padding:16px 0">
        <a href="<?= APP_URL ?>/accounts/connect/meta" class="btn btn-primary btn-lg">
          <i class="fa-brands fa-facebook"></i> Entrar com Facebook
        </a>
      </div>
    </div>

  </div>
</div>

<!-- Instruções -->
<details style="margin-top:16px">
  <summary style="cursor:pointer;font-size:12px;color:var(--txt3);padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);list-style:none;display:flex;align-items:center;gap:6px;user-select:none">
    <span style="font-size:14px">ℹ️</span> Como conectar suas contas <span style="margin-left:auto;font-size:10px;opacity:.6">clique para expandir</span>
  </summary>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:16px;background:var(--bg3);border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius) var(--radius)">
    <div>
      <div style="font-size:13px;font-weight:600;color:var(--txt);margin-bottom:8px;display:flex;align-items:center;gap:6px">
        <i class="fa-brands fa-facebook" style="color:#1877F2"></i> Meta Ads — Token Manual (recomendado)
      </div>
      <ol style="font-size:12px;color:var(--txt2);line-height:2;padding-left:16px">
        <li>Acesse <a href="https://developers.facebook.com/tools/explorer" target="_blank">Graph API Explorer</a></li>
        <li>Selecione o app <strong>EMPRESA - ADS</strong></li>
        <li>Adicione as permissões: <code>ads_read</code>, <code>ads_management</code></li>
        <li>Clique <strong>"Gerar token"</strong></li>
        <li>Clique <strong>"Conectar Meta Ads"</strong> acima e cole o token</li>
      </ol>
      <a href="https://developers.facebook.com/tools/explorer" target="_blank" class="btn btn-primary btn-sm" style="margin-top:6px">
        Abrir Graph API Explorer →
      </a>
    </div>
    <div>
      <div style="font-size:13px;font-weight:600;color:var(--txt);margin-bottom:8px;display:flex;align-items:center;gap:6px">
        <i class="fa-brands fa-google" style="color:#EA4335"></i> Google Ads
      </div>
      <ol style="font-size:12px;color:var(--txt2);line-height:2;padding-left:16px">
        <li>Acesse <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a></li>
        <li>Crie credenciais OAuth 2.0</li>
        <li>Configure no <code>config/config.php</code></li>
        <li>Clique em <strong>"Conectar Google Ads"</strong></li>
      </ol>
    </div>
  </div>
</details>

<script>
function openMetaModal(){ document.getElementById('metaModal').style.display='flex'; }
function closeMetaModal(){ document.getElementById('metaModal').style.display='none'; }
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeMetaModal(); });

var APP_URL = '<?= APP_URL ?>';
var CSRF = '<?= e($_SESSION["csrf_token"]) ?>';

function filterAccounts(q) {
  var rows = document.querySelectorAll('tbody tr');
  var val = q.trim().toLowerCase();
  var shown = 0;
  rows.forEach(function(row) {
    var text = row.textContent.toLowerCase();
    // Fuzzy: check if all words in query appear somewhere in the row
    var words = val.split(/\s+/).filter(Boolean);
    var match = words.every(function(w) { return text.includes(w); });
    row.style.display = (!val || match) ? '' : 'none';
    if (!val || match) shown++;
  });
  var info = document.getElementById('searchInfo');
  if (val) {
    info.style.display = 'block';
    info.textContent = shown + ' conta(s) encontrada(s) para "' + q + '"';
  } else {
    info.style.display = 'none';
  }
  // Uncheck hidden rows
  document.querySelectorAll('tbody tr').forEach(function(row) {
    if (row.style.display === 'none') {
      var cb = row.querySelector('.row-check');
      if (cb) cb.checked = false;
    }
  });
  updateBulkBar();
}

function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
  updateBulkBar();
}
function updateBulkBar() {
  var checked = document.querySelectorAll('.row-check:checked');
  var bar = document.getElementById('bulkBar');
  var count = document.getElementById('bulkCount');
  if (checked.length > 0) {
    bar.style.display = 'flex';
    count.textContent = checked.length + ' conta(s) selecionada(s)';
  } else {
    bar.style.display = 'none';
    document.getElementById('checkAll').checked = false;
  }
}
function clearSelection() {
  document.querySelectorAll('.row-check').forEach(c => c.checked = false);
  document.getElementById('checkAll').checked = false;
  updateBulkBar();
}
function bulkDelete() {
  var checked = Array.from(document.querySelectorAll('.row-check:checked'));
  if (!checked.length) return;
  var names = checked.map(c => c.closest('tr').querySelector('td:nth-child(2) div:first-child').textContent.trim());
  if (!confirm('Tem certeza que deseja remover ' + checked.length + ' conta(s)?\n\n' + names.join('\n') + '\n\nEsta ação não pode ser desfeita.')) return;
  var ids = checked.map(c => c.value);
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = APP_URL + '/accounts/delete-bulk';
  var token = document.createElement('input');
  token.type = 'hidden'; token.name = '_token'; token.value = '<?= e($_SESSION['csrf_token']) ?>';
  form.appendChild(token);
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    form.appendChild(inp);
  });
  document.body.appendChild(form);
  form.submit();
}
document.addEventListener('change', function(e) {
  if (e.target.classList.contains('row-check')) updateBulkBar();
});

function switchTab(tab) {
  var isToken = tab==='token';
  document.getElementById('panelToken').style.display = isToken?'block':'none';
  document.getElementById('panelOAuth').style.display = isToken?'none':'block';
  document.getElementById('tabToken').style.background  = isToken?'var(--accent)':'transparent';
  document.getElementById('tabToken').style.color       = isToken?'#fff':'var(--txt2)';
  document.getElementById('tabOAuth').style.background  = isToken?'transparent':'var(--accent)';
  document.getElementById('tabOAuth').style.color       = isToken?'var(--txt2)':'#fff';
}

async function linkClient(sel) {
  var accId    = sel.dataset.acc;
  var clientId = sel.value;
  var fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('account_id', accId);
  fd.append('client_id', clientId);
  sel.style.opacity = '0.5';
  try {
    var r = await fetch(APP_URL + '/accounts/link-client', {method:'POST', body:fd});
    var d = await r.json();
    if (d.success) {
      sel.style.opacity = '1';
      sel.style.borderColor = 'var(--success)';
      setTimeout(function(){ sel.style.borderColor = ''; }, 1500);
    } else {
      sel.style.opacity = '1';
      alert('Erro ao vincular: ' + (d.error||'Tente novamente'));
    }
  } catch(e) {
    sel.style.opacity = '1';
    alert('Erro de conexão');
  }
}
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
