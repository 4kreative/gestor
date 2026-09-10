<?php
$pageTitle   = 'WhatsApp — Conexões';
$currentPage = 'whatsapp';
ob_start();
?>
<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <p style="font-size:13px;color:var(--txt2)">Gerencie suas instâncias WhatsApp via Evolution API na VPS</p>
  </div>
  <button class="btn btn-primary" onclick="document.getElementById('newInstanceModal').style.display='flex'">
    <i class="fa-brands fa-whatsapp"></i> Nova Instância
  </button>
</div>

<?php if (empty($instances)): ?>
<div class="card">
  <div class="empty-state">
    <i class="fa-brands fa-whatsapp" style="font-size:48px;color:var(--txt3);margin-bottom:12px"></i>
    <h3>Nenhuma instância configurada</h3>
    <p>Crie uma instância e conecte seu WhatsApp para enviar relatórios</p>
    <button class="btn btn-success" onclick="document.getElementById('newInstanceModal').style.display='flex'">
      <i class="fa-brands fa-whatsapp"></i> Criar Primeira Instância
    </button>
  </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach ($instances as $inst): ?>
  <div class="wp-instance-card" data-wp-poll="<?= $inst['id'] ?>">
    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
      <div class="wp-status-dot <?= e($inst['status']) ?>"></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= e($inst['instance_name']) ?>
          <?php if ($inst['is_default']): ?><span class="badge badge-blue badge-sm" style="margin-left:6px">Padrão</span><?php endif; ?>
        </div>
        <div class="wp-status-text" style="font-size:12px;color:var(--txt2)">
          <?php
          $sl = ['connected'=>'✓ Conectado','disconnected'=>'Desconectado','qr_pending'=>'Aguardando QR...','error'=>'Erro de conexão'];
          echo e($inst['phone_number'] ? '✓ '.$inst['phone_number'] : ($sl[$inst['status']] ?? $inst['status']));
          ?>
        </div>
        <div style="font-size:11px;color:var(--txt3)">Criado <?= date('d/m/Y',strtotime($inst['created_at'])) ?></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
      <?php if ($inst['status'] !== 'connected'): ?>
      <a href="<?= APP_URL ?>/whatsapp/qr?id=<?= $inst['id'] ?>" class="btn btn-warn btn-sm">QR Code</a>
      <?php else: ?>
      <span class="badge badge-green" style="justify-content:center">Online</span>
      <?php endif; ?>

      <!-- Botão Testar API -->
      <button class="btn btn-sm" 
              style="background:var(--bg3);border:1px solid var(--border);color:var(--txt2)"
              onclick="abrirModalTeste('<?= e($inst['instance_name']) ?>', <?= $inst['id'] ?>)">
        <i class="fa-solid fa-paper-plane"></i> Testar
      </button>

      <!-- Botão Desconectar (só aparece quando conectado) -->
      <?php if ($inst['status'] === 'connected'): ?>
      <form method="POST" action="<?= APP_URL ?>/whatsapp/disconnect">
        <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="id" value="<?= $inst['id'] ?>">
        <button type="submit" class="btn btn-sm btn-full"
                style="background:var(--bg3);border:1px solid var(--accent);color:var(--accent)"
                data-confirm="Desconectar instância <?= e($inst['instance_name']) ?>? Você poderá reconectar depois via QR Code.">
          <i class="fa-solid fa-plug-circle-xmark"></i> Desconectar
        </button>
      </form>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/whatsapp/delete">
        <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="id" value="<?= $inst['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm btn-full" data-confirm="Remover instância <?= e($inst['instance_name']) ?>?">
          Remover
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:20px">
  <div class="card-header"><div class="card-title">Configuração da Evolution API</div></div>
  <div style="font-size:13px;color:var(--txt2);line-height:1.8">
    <p>A Evolution API deve estar rodando na sua VPS. Configure o arquivo <code>config/config.php</code>:</p>
    <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;margin-top:10px;font-family:monospace;font-size:12px;color:var(--txt2)">
      EVOLUTION_API_URL = "http://SEU-IP-VPS:8080"<br>
      EVOLUTION_API_KEY = "sua-chave-da-evolution"
    </div>
    <p style="margin-top:10px">Para instalar a Evolution API na VPS, acesse: <a href="https://github.com/EvolutionAPI/evolution-api" target="_blank">github.com/EvolutionAPI/evolution-api</a></p>
  </div>
</div>

<!-- Modal nova instância -->
<div id="newInstanceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:500;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:380px;margin:20px">
    <div style="font-size:16px;font-weight:600;margin-bottom:16px">Nova Instância WhatsApp</div>
    <form method="POST" action="<?= APP_URL ?>/whatsapp/create" id="createInstanceForm">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-group">
        <label class="form-label">Nome da Instância <span class="req">*</span></label>
        <input type="text" name="instance_name" class="form-control" placeholder="meu-whatsapp" required
               pattern="[a-zA-Z0-9_-]+" title="Apenas letras, números, - e _">
        <div class="form-hint">Apenas letras, números, hífen e underline</div>
      </div>
      <div class="form-actions" style="gap:8px">
        <button type="submit" id="btnCriarInstancia" class="btn btn-success">
          <i class="fa-brands fa-whatsapp" id="btnCriarIcon"></i>
          <span id="btnCriarText"> Criar</span>
        </button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('newInstanceModal').style.display='none'">Cancelar</button>
      </div>
    </form>
    <script>
    document.getElementById('createInstanceForm').addEventListener('submit', function() {
      var btn  = document.getElementById('btnCriarInstancia');
      var icon = document.getElementById('btnCriarIcon');
      var txt  = document.getElementById('btnCriarText');
      btn.disabled = true;
      btn.style.opacity = '0.85';
      btn.style.cursor  = 'not-allowed';
      icon.className = 'fa-solid fa-circle-notch fa-spin';
      txt.textContent  = ' Criando...';
    });
    </script>
  </div>
</div>

<!-- Modal Testar API -->
<div id="testeApiModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:400px;margin:20px">
    
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="background:#25D366;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center">
          <i class="fa-brands fa-whatsapp" style="color:#fff;font-size:18px"></i>
        </div>
        <div>
          <div style="font-size:15px;font-weight:600;color:var(--txt)">Testar Instância</div>
          <div id="testeInstanciaNome" style="font-size:12px;color:var(--txt3)"></div>
        </div>
      </div>
      <button onclick="fecharModalTeste()" style="background:none;border:none;color:var(--txt3);cursor:pointer;font-size:18px;padding:4px">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="form-group">
      <label class="form-label">Número de destino <span class="req">*</span></label>
      <input type="text" id="testeNumero" class="form-control" placeholder="5511999999999" 
             style="font-size:15px;letter-spacing:.5px">
      <div class="form-hint">DDD + número, sem espaços ou símbolos</div>
    </div>

    <div class="form-group">
      <label class="form-label">Mensagem</label>
      <textarea id="testeMensagem" class="form-control" rows="3" 
                style="resize:none;font-size:13px">🚀 Teste de API — GestorPro conectado com sucesso!</textarea>
    </div>

    <!-- Resultado -->
    <div id="testeResultado" style="display:none;border-radius:var(--radius);padding:10px 14px;font-size:13px;margin-bottom:14px"></div>

    <div style="display:flex;gap:8px">
      <button id="btnEnviarTeste" class="btn btn-success" style="flex:1" onclick="enviarTeste()">
        <i class="fa-solid fa-paper-plane" id="btnTesteIcon"></i>
        <span id="btnTesteText"> Enviar</span>
      </button>
      <button class="btn btn-secondary" onclick="fecharModalTeste()">Cancelar</button>
    </div>
  </div>
</div>

<script>
var _testeInstanceId = null;
var _testeInstanceName = null;

function abrirModalTeste(nome, id) {
  _testeInstanceId   = id;
  _testeInstanceName = nome;
  document.getElementById('testeInstanciaNome').textContent = nome;
  document.getElementById('testeNumero').value  = '';
  document.getElementById('testeMensagem').value = '🚀 Teste de API — GestorPro conectado com sucesso!';
  document.getElementById('testeResultado').style.display = 'none';
  resetBtnTeste();
  document.getElementById('testeApiModal').style.display = 'flex';
  setTimeout(function(){ document.getElementById('testeNumero').focus(); }, 100);
}

function fecharModalTeste() {
  document.getElementById('testeApiModal').style.display = 'none';
}

function resetBtnTeste() {
  var btn  = document.getElementById('btnEnviarTeste');
  var icon = document.getElementById('btnTesteIcon');
  var txt  = document.getElementById('btnTesteText');
  btn.disabled     = false;
  btn.style.opacity = '1';
  icon.className   = 'fa-solid fa-paper-plane';
  txt.textContent  = ' Enviar';
}

function enviarTeste() {
  var numero   = document.getElementById('testeNumero').value.trim().replace(/\D/g,'');
  var mensagem = document.getElementById('testeMensagem').value.trim();
  var resultado = document.getElementById('testeResultado');

  if (!numero || numero.length < 10) {
    mostrarResultado('error', '<i class="fa-solid fa-circle-exclamation"></i> Digite um número válido com DDD.');
    return;
  }
  if (!mensagem) {
    mostrarResultado('error', '<i class="fa-solid fa-circle-exclamation"></i> Digite uma mensagem.');
    return;
  }

  // Loading
  var btn  = document.getElementById('btnEnviarTeste');
  var icon = document.getElementById('btnTesteIcon');
  var txt  = document.getElementById('btnTesteText');
  btn.disabled     = true;
  btn.style.opacity = '0.8';
  icon.className   = 'fa-solid fa-circle-notch fa-spin';
  txt.textContent  = ' Enviando...';
  resultado.style.display = 'none';

  fetch('<?= APP_URL ?>/whatsapp/test', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      instance_id: _testeInstanceId,
      number:      numero,
      message:     mensagem,
      _token:      '<?= e($_SESSION['csrf_token']) ?>'
    })
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    if (data.success) {
      mostrarResultado('success', '<i class="fa-solid fa-circle-check"></i> Mensagem enviada com sucesso!');
    } else {
      mostrarResultado('error', '<i class="fa-solid fa-circle-exclamation"></i> ' + (data.error || 'Erro ao enviar.'));
    }
    resetBtnTeste();
  })
  .catch(function() {
    mostrarResultado('error', '<i class="fa-solid fa-circle-exclamation"></i> Erro de conexão.');
    resetBtnTeste();
  });
}

function mostrarResultado(tipo, html) {
  var el = document.getElementById('testeResultado');
  el.style.display    = 'block';
  el.style.background = tipo === 'success' ? 'rgba(37,211,102,.12)' : 'rgba(220,53,69,.12)';
  el.style.color      = tipo === 'success' ? '#25D366' : '#dc3545';
  el.style.border     = '1px solid ' + (tipo === 'success' ? 'rgba(37,211,102,.3)' : 'rgba(220,53,69,.3)');
  el.innerHTML        = html;
}

// Fechar ao clicar fora
document.getElementById('testeApiModal').addEventListener('click', function(e) {
  if (e.target === this) fecharModalTeste();
});
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
