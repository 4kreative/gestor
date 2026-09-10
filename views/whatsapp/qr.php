<?php
$pageTitle   = 'QR Code WhatsApp';
$currentPage = 'whatsapp';
ob_start();
?>
<div style="max-width:400px;margin:0 auto">
  <div class="qr-wrapper">
    <div style="font-size:16px;font-weight:600;margin-bottom:16px;color:var(--txt)">
      Escanear QR Code
    </div>
    <div style="margin-bottom:8px;font-size:12px;color:var(--txt2)"><?= e($inst['instance_name']) ?></div>

    <?php if (!empty($inst['qr_code'])): ?>
      <?php
      $src = $inst['qr_code'];
      if (strpos($src, 'base64,') === false) $src = 'data:image/png;base64,'.$src;
      ?>
      <img src="<?= $src ?>" alt="QR Code" style="width:220px;height:220px;display:block;margin:0 auto;border-radius:8px;background:#fff;padding:8px">
    <?php else: ?>
      <div style="width:220px;height:220px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;justify-content:center;margin:0 auto;color:var(--txt2);font-size:12px">
        QR Code não disponível.<br>Atualize a página.
      </div>
    <?php endif; ?>

    <div class="qr-steps" style="margin-top:20px">
      <p style="font-weight:600;color:var(--txt);margin-bottom:8px">Como conectar:</p>
      <ol>
        <li>Abra o WhatsApp no celular</li>
        <li>Toque em <strong>⋮ Menu</strong> → Dispositivos Conectados</li>
        <li>Toque em <strong>Conectar um dispositivo</strong></li>
        <li>Aponte a câmera para o QR code acima</li>
      </ol>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;justify-content:center">
      <a href="<?= APP_URL ?>/whatsapp/qr?id=<?= $inst['id'] ?>" class="btn btn-secondary btn-sm">
        <span class="material-icons-outlined">refresh</span> Atualizar QR
      </a>
      <a href="<?= APP_URL ?>/whatsapp" class="btn btn-ghost btn-sm">← Voltar</a>
    </div>

    <div id="statusInfo" style="margin-top:14px;font-size:12px;color:var(--txt2);text-align:center" data-wp-poll="<?= $inst['id'] ?>">
      Status: <span class="wp-status-text"><?= $inst['status'] === 'connected' ? '✓ Conectado' : 'Aguardando...' ?></span>
    </div>
  </div>
</div>

<script>
// Auto-refresh QR se ainda não conectado
<?php if ($inst['status'] !== 'connected'): ?>
let pollCount = 0;
const interval = setInterval(async () => {
  pollCount++;
  if (pollCount > 60) { clearInterval(interval); return; } // 5 min max
  try {
    const r = await fetch('<?= APP_URL ?>/whatsapp/status?id=<?= $inst['id'] ?>');
    const d = await r.json();
    if (d.status === 'connected') {
      clearInterval(interval);
      document.querySelector('.wp-status-text').textContent = '✓ Conectado! Redirecionando...';
      setTimeout(() => window.location = '<?= APP_URL ?>/whatsapp', 1500);
    }
  } catch(e) {}
}, 5000);
<?php endif; ?>
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
