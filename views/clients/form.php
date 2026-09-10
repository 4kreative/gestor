<?php
$editing     = !empty($client);
$pageTitle   = $editing ? 'Editar Cliente' : 'Novo Cliente';
$currentPage = 'clients';
ob_start();
?>
<div style="max-width:700px">
<div class="form-card">
  <div class="form-section"><?= $editing ? 'Editar' : 'Cadastrar' ?> Cliente</div>
  <form method="POST" action="<?= APP_URL ?>/clients/<?= $editing ? 'update' : 'store' ?>">
    <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $client['id'] ?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Nome Completo <span class="req">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="Nome do cliente"
               value="<?= e($client['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Empresa</label>
        <input type="text" name="company" class="form-control" placeholder="Nome da empresa"
               value="<?= e($client['company'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" placeholder="cliente@empresa.com"
               value="<?= e($client['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">WhatsApp</label>
        <input type="text" name="phone" class="form-control" placeholder="5511999999999"
               value="<?= e($client['phone'] ?? '') ?>">
        <div class="form-hint">Com DDI e DDD, só números. Ex: 5511999999999</div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Observações</label>
      <textarea name="notes" class="form-control" placeholder="Anotações sobre o cliente..."><?= e($client['notes'] ?? '') ?></textarea>
    </div>

    <?php if ($editing): ?>
    <div class="form-group">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="active"   <?= ($client['status']??'')==='active'?'selected':'' ?>>Ativo</option>
        <option value="inactive" <?= ($client['status']??'')==='inactive'?'selected':'' ?>>Inativo</option>
      </select>
    </div>
    <?php endif; ?>

    <!-- Tipo de pagamento -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">💳 Tipo de Pagamento</label>
        <select name="payment_type" id="payment_type_sel" class="form-control" onchange="toggleSaldoAlerta()">
          <option value="cartao"  <?= (($client['payment_type']??'cartao')==='cartao' ?'selected':'') ?>>Cartão de Crédito</option>
          <option value="prepago" <?= (($client['payment_type']??'')==='prepago'?'selected':'') ?>>Pré-pago (Pix/Transferência)</option>
        </select>
        <div class="form-hint">Pré-pago: controla saldo e dias restantes no dashboard</div>
      </div>
      <div class="form-group" id="saldo_alerta_group" style="display:<?= (($client['payment_type']??'cartao')==='prepago'?'block':'none') ?>">
        <label class="form-label">⚠️ Alertar quando saldo abaixo de</label>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="color:var(--txt2);font-size:14px">R$</span>
          <input type="number" name="saldo_alerta" class="form-control" step="1" min="0"
            placeholder="Ex: 100" value="<?= e($client['saldo_alerta'] ?? '50') ?>"
            style="max-width:140px">
        </div>
        <div class="form-hint">Aparecerá em vermelho no card de saldo quando atingir esse valor</div>
      </div>
    </div>
    <script>
    function toggleSaldoAlerta() {
      var sel = document.getElementById('payment_type_sel');
      var grp = document.getElementById('saldo_alerta_group');
      grp.style.display = sel.value === 'prepago' ? 'block' : 'none';
    }
    </script>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <span class="material-icons-outlined">save</span> <?= $editing ? 'Salvar Alterações' : 'Cadastrar Cliente' ?>
      </button>
      <a href="<?= APP_URL ?>/clients" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
