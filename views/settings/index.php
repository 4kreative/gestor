<?php
$pageTitle   = 'Configurações';
$currentPage = 'settings';
ob_start();
$APP_URL = APP_URL;
?>
<div style="max-width:800px">

  <div class="form-card">
    <div class="form-section">Identidade Visual</div>
    <form method="POST" action="<?= $APP_URL ?>/settings/update" enctype="multipart/form-data" id="formSettings">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <!-- Nome do sistema -->
      <div class="form-group">
        <label class="form-label">Nome do Sistema</label>
        <input type="text" name="site_name" class="form-control"
               value="<?= e($settings['site_name'] ?? 'GestorPro') ?>"
               placeholder="GestorPro" required>
        <div class="form-hint">Aparece no título das páginas e no sidebar</div>
      </div>

      <!-- LOGO DO PAINEL -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Logo do Painel (Sidebar)</label>
          <?php if (!empty($settings['logo_path'])): ?>
          <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:var(--radius);display:inline-flex;align-items:center;gap:10px">
            <img src="<?= $APP_URL ?>/public/img/uploads/<?= e($settings['logo_path']) ?>" style="max-height:40px;max-width:120px;object-fit:contain">
            <button type="button" class="btn btn-danger btn-sm" onclick="resetField('logo_path','Remover logo?')">✕ Remover</button>
          </div>
          <?php endif; ?>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <div class="form-hint">PNG/SVG recomendado, fundo transparente. Máx 2MB.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Favicon</label>
          <?php if (!empty($settings['favicon_path'])): ?>
          <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:var(--radius);display:inline-flex;align-items:center;gap:10px">
            <img src="<?= $APP_URL ?>/public/img/uploads/<?= e($settings['favicon_path']) ?>" style="width:32px;height:32px;object-fit:contain">
            <button type="button" class="btn btn-danger btn-sm" onclick="resetField('favicon_path','Remover favicon?')">✕ Remover</button>
          </div>
          <?php endif; ?>
          <input type="file" name="favicon" class="form-control" accept="image/*,.ico">
          <div class="form-hint">.ico ou PNG 32x32. Máx 2MB.</div>
        </div>
      </div>

      <div class="form-section" style="margin-top:8px">Tela de Login</div>

      <!-- LOGO DO LOGIN -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Logo da Tela de Login</label>
          <?php if (!empty($settings['login_logo_path'])): ?>
          <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:var(--radius);display:inline-flex;align-items:center;gap:10px">
            <img src="<?= $APP_URL ?>/public/img/uploads/<?= e($settings['login_logo_path']) ?>" style="max-height:50px;max-width:140px;object-fit:contain">
            <button type="button" class="btn btn-danger btn-sm" onclick="resetField('login_logo_path','Remover?')">✕ Remover</button>
          </div>
          <?php endif; ?>
          <input type="file" name="login_logo" class="form-control" accept="image/*">
          <div class="form-hint">Exibida no card de login. PNG/SVG recomendado.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Imagem de Fundo do Login</label>
          <?php if (!empty($settings['login_bg_path'])): ?>
          <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:var(--radius);display:inline-flex;align-items:center;gap:10px">
            <img src="<?= $APP_URL ?>/public/img/uploads/<?= e($settings['login_bg_path']) ?>" style="width:80px;height:50px;object-fit:cover;border-radius:4px">
            <button type="button" class="btn btn-danger btn-sm" onclick="resetField('login_bg_path','Remover?')">✕ Remover</button>
          </div>
          <?php endif; ?>
          <input type="file" name="login_bg" class="form-control" accept="image/*">
          <div class="form-hint">JPG/PNG. Recomendado 1920×1080. Máx 2MB.</div>
        </div>
      </div>

      <!-- Preview do login -->
      <?php
      $hasLoginBg   = !empty($settings['login_bg_path']);
      $hasLoginLogo = !empty($settings['login_logo_path']);
      ?>
      <div style="border:1px solid var(--border);border-radius:var(--radius2);overflow:hidden;margin-bottom:16px">
        <div style="font-size:11px;color:var(--txt3);padding:8px 14px;background:var(--bg3);border-bottom:1px solid var(--border)">Preview da tela de login</div>
        <div style="background:<?= $hasLoginBg ? 'url('.$APP_URL.'/public/img/uploads/'.$settings['login_bg_path'].') center/cover' : 'var(--bg)' ?>;padding:30px 20px;display:flex;align-items:center;justify-content:center;min-height:180px">
          <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px 24px;width:200px;text-align:center">
            <?php if ($hasLoginLogo): ?>
              <img src="<?= $APP_URL ?>/public/img/uploads/<?= e($settings['login_logo_path']) ?>" style="max-height:36px;max-width:140px;object-fit:contain;margin-bottom:8px">
            <?php else: ?>
              <div style="font-size:14px;font-weight:700;color:var(--txt);margin-bottom:4px"><?= e($settings['site_name'] ?? 'GestorPro') ?></div>
            <?php endif; ?>
            <div style="font-size:10px;color:var(--txt2);margin-bottom:10px">Painel de Relatórios</div>
            <div style="height:6px;background:var(--bg3);border-radius:4px;margin-bottom:6px"></div>
            <div style="height:6px;background:var(--bg3);border-radius:4px;margin-bottom:10px"></div>
            <div style="height:8px;background:var(--accent);border-radius:4px;opacity:.8"></div>
          </div>
        </div>
      </div>

      <div style="border-top:2px solid var(--border);margin:24px 0 16px"></div>
      <div class="form-section" style="margin-top:20px;display:flex;align-items:center;gap:8px"><span style="font-size:18px">📘</span> Meta API (Facebook / Instagram)</div>
      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px">
        Credenciais do seu App no <a href="https://developers.facebook.com" target="_blank" style="color:var(--accent)">Facebook Developers</a>.
        Necessário para conectar contas de anúncio e renovar tokens.
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">App ID</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" name="meta_app_id" class="form-control"
              value="<?= e($settings['meta_app_id'] ?? '') ?>"
              placeholder="Ex: 1234567890123456">
            <?php if (!empty($settings['meta_app_id'])): ?>
            <button type="button" onclick="clearField('meta_app_id')" title="Limpar" style="background:none;border:1px solid var(--border);border-radius:6px;padding:6px 10px;cursor:pointer;color:var(--txt2);white-space:nowrap">✕</button>
            <input type="hidden" name="clear_meta_app_id" id="clear_meta_app_id" value="">
            <?php endif; ?>
          </div>
          <div class="form-hint">ID do App no Facebook Developers</div>
        </div>
        <div class="form-group">
          <label class="form-label">App Secret</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="password" name="meta_app_secret" class="form-control"
              value="<?= e($settings['meta_app_secret'] ?? '') ?>"
              placeholder="Cole o App Secret aqui"
              autocomplete="new-password">
            <?php if (!empty($settings['meta_app_secret'])): ?>
            <button type="button" onclick="clearField('meta_app_secret')" title="Limpar" style="background:none;border:1px solid var(--border);border-radius:6px;padding:6px 10px;cursor:pointer;color:var(--txt2);white-space:nowrap">✕</button>
            <input type="hidden" name="clear_meta_app_secret" id="clear_meta_app_secret" value="">
            <?php endif; ?>
          </div>
          <div class="form-hint">Chave secreta do App (não compartilhe)</div>
        </div>
      </div>

      <div style="border-top:2px solid var(--border);margin:24px 0 16px"></div>
      <div class="form-section" style="margin-top:20px;display:flex;align-items:center;gap:8px"><span style="font-size:18px">💬</span> Evolution API (WhatsApp)</div>
      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px">
        URL e chave de acesso da sua instância Evolution API para envio de mensagens WhatsApp.
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">URL da Evolution API</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" name="evolution_api_url" class="form-control"
              value="<?= e($settings['evolution_api_url'] ?? '') ?>"
              placeholder="Ex: https://api.seudominio.com.br">
            <?php if (!empty($settings['evolution_api_url'])): ?>
            <button type="button" onclick="clearField('evolution_api_url')" title="Limpar" style="background:none;border:1px solid var(--border);border-radius:6px;padding:6px 10px;cursor:pointer;color:var(--txt2)">✕</button>
            <input type="hidden" name="clear_evolution_api_url" id="clear_evolution_api_url" value="">
            <?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">API Key</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="password" name="evolution_api_key" class="form-control"
              value="<?= e($settings['evolution_api_key'] ?? '') ?>"
              placeholder="Chave de acesso da Evolution API"
              autocomplete="new-password">
            <?php if (!empty($settings['evolution_api_key'])): ?>
            <button type="button" onclick="clearField('evolution_api_key')" title="Limpar" style="background:none;border:1px solid var(--border);border-radius:6px;padding:6px 10px;cursor:pointer;color:var(--txt2)">✕</button>
            <input type="hidden" name="clear_evolution_api_key" id="clear_evolution_api_key" value="">
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="border-top:2px solid var(--border);margin:24px 0 16px"></div>
      <div class="form-section" style="margin-top:20px;display:flex;align-items:center;gap:8px"><span style="font-size:18px">📧</span> Email (SMTP)</div>
      <div style="font-size:12px;color:var(--txt2);margin-bottom:12px">
        Configurações de envio de e-mail para notificações e recuperação de senha.
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Servidor SMTP</label>
          <input type="text" name="mail_host" class="form-control"
            value="<?= e($settings['mail_host'] ?? 'smtp.hostinger.com') ?>"
            placeholder="smtp.hostinger.com">
        </div>
        <div class="form-group" style="max-width:120px">
          <label class="form-label">Porta</label>
          <input type="number" name="mail_port" class="form-control"
            value="<?= e($settings['mail_port'] ?? '465') ?>"
            placeholder="465">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Usuário (e-mail)</label>
          <input type="text" name="mail_user" class="form-control"
            value="<?= e($settings['mail_user'] ?? '') ?>"
            placeholder="noreply@seudominio.com.br">
        </div>
        <div class="form-group">
          <label class="form-label">Senha</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="password" name="mail_pass" class="form-control"
              value="<?= e($settings['mail_pass'] ?? '') ?>"
              placeholder="Senha do e-mail SMTP"
              autocomplete="new-password">
            <?php if (!empty($settings['mail_pass'])): ?>
            <button type="button" onclick="clearField('mail_pass')" title="Limpar" style="background:none;border:1px solid var(--border);border-radius:6px;padding:6px 10px;cursor:pointer;color:var(--txt2)">✕</button>
            <input type="hidden" name="clear_mail_pass" id="clear_mail_pass" value="">
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Nome do Remetente</label>
        <input type="text" name="mail_from_name" class="form-control"
          value="<?= e($settings['mail_from_name'] ?? '') ?>"
          placeholder="Ex: GestorADS">
        <div class="form-hint">Nome que aparece no campo "De:" dos e-mails enviados</div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <span class="material-icons-outlined">save</span> Salvar Configurações
        </button>
      </div>
    </form>
  </div>

</div>

<script>
function clearField(field) {
  if (!confirm('Tem certeza que deseja remover este valor? O campo ficará vazio.')) return;
  document.getElementById('clear_' + field).value = '1';
  document.querySelector('[name="' + field + '"]').value = '';
  document.querySelector('[name="' + field + '"]').placeholder = 'Vazio — digite para definir novo valor';
}
function resetField(field, msg) {
  if (!confirm(msg)) return;
  var fd = new FormData();
  fd.append('_token', '<?= e($_SESSION['csrf_token']) ?>');
  fd.append('field', field);
  fetch('<?= $APP_URL ?>/settings/reset', { method: 'POST', body: fd })
    .then(function(r){ window.location.reload(); })
    .catch(function(){ alert('Erro ao remover. Tente novamente.'); });
}
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
