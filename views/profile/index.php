<?php
$pageTitle   = 'Perfil';
$currentPage = 'profile';
ob_start();
// $user já foi carregado do banco pelo ProfileController
if (!isset($user)) $user = currentUser();
?>
<div style="max-width:640px">
  <div class="form-card">
    <div class="form-section">Dados Pessoais</div>
    <form method="POST" action="<?= APP_URL ?>/profile/update">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nome <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($user['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">E-mail <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Telefone / WhatsApp</label>
        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="5511999999999">
        <div class="form-hint">DDI+DDD+número sem espaços</div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><span class="material-icons-outlined" style="font-size:16px">save</span> Salvar dados</button>
      </div>
    </form>
  </div>
  <div class="form-card" style="margin-top:18px">
    <div class="form-section">Alterar Senha</div>
    <form method="POST" action="<?= APP_URL ?>/profile/password">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-group">
        <label class="form-label">Senha atual</label>
        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nova senha</label>
          <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
          <div class="form-hint">Mínimo 8 caracteres</div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" name="new_password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><span class="material-icons-outlined" style="font-size:16px">lock</span> Alterar senha</button>
      </div>
    </form>
  </div>
  <div class="form-card" style="margin-top:18px">
    <div class="form-section">Informações da Conta</div>
    <div style="display:flex;flex-direction:column;gap:0;font-size:13px">
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--txt2)">Plano atual</span>
        <span class="plan-chip plan-<?= e($user['plan'] ?? 'trial') ?>"><?= ucfirst($user['plan'] ?? 'trial') ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--txt2)">Status</span>
        <?= ($user['status']??'')==='active' ? '<span class="badge badge-green badge-sm">Ativo</span>' : '<span class="badge badge-red badge-sm">'.ucfirst($user['status']??'inativo').'</span>' ?>
      </div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--txt2)">Membro desde</span>
        <span style="color:var(--txt);font-weight:600"><?= date('d/m/Y',strtotime($user['created_at']??'now')) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:10px 0">
        <span style="color:var(--txt2)">Último acesso</span>
        <span style="color:var(--txt);font-weight:600"><?= $user['last_login']?date('d/m/Y H:i',strtotime($user['last_login'])):'—' ?></span>
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
?>
