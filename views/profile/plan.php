<?php
$pageTitle   = 'Planos';
$currentPage = 'profile';
ob_start();
$currentPlan = currentUser()['plan'];
$plans = [
    'essencial' => ['label'=>'Essencial', 'price_m'=>97,  'price_y'=>77,  'clients'=>'ilimitados', 'reports'=>'ilimitados', 'whatsapp'=>1,  'accounts'=>10,  'badge'=>''],
    'advanced'  => ['label'=>'Advanced',  'price_m'=>167, 'price_y'=>139, 'clients'=>'ilimitados', 'reports'=>'ilimitados', 'whatsapp'=>2,  'accounts'=>50,  'badge'=>''],
    'pro'       => ['label'=>'Pro',       'price_m'=>297, 'price_y'=>289, 'clients'=>'ilimitados', 'reports'=>'ilimitados', 'whatsapp'=>5,  'accounts'=>100, 'badge'=>'Recomendado'],
    'premium'   => ['label'=>'Premium',   'price_m'=>497, 'price_y'=>493, 'clients'=>'ilimitados', 'reports'=>'ilimitados', 'whatsapp'=>10, 'accounts'=>200, 'badge'=>''],
];
?>
<div style="text-align:center;margin-bottom:24px">
  <h2 style="font-size:22px;font-weight:700;color:var(--txt)">Escolha seu plano</h2>
  <p style="font-size:14px;color:var(--txt2);margin-top:6px">Todos os planos incluem relatórios ilimitados, suporte WhatsApp e clientes ilimitados</p>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;max-width:1100px;margin:0 auto">
<?php foreach ($plans as $key => $p): $isCurrent = ($currentPlan === $key); $isRec = $p['badge']==='Recomendado'; ?>
<div style="background:var(--bg2);border:1px solid <?= $isRec?'var(--accent)':($isCurrent?'var(--success)':'var(--border)') ?>;border-radius:var(--radius2);padding:20px;display:flex;flex-direction:column;gap:10px;position:relative">
  <?php if ($p['badge']): ?>
  <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;font-size:10px;font-weight:700;padding:3px 12px;border-radius:20px;white-space:nowrap"><?= $p['badge'] ?></div>
  <?php endif; ?>
  <?php if ($isCurrent): ?>
  <div style="position:absolute;top:10px;right:10px"><span class="badge badge-green badge-sm">Atual</span></div>
  <?php endif; ?>

  <div style="font-size:16px;font-weight:700;color:var(--txt)"><?= $p['label'] ?></div>
  <div>
    <span style="font-size:28px;font-weight:700;color:var(--txt)">R$<?= $p['price_m'] ?></span>
    <span style="font-size:12px;color:var(--txt2)">/mês</span>
  </div>

  <div style="font-size:11px;color:var(--txt3);border-top:1px solid var(--border);padding-top:10px">
    <div style="margin-bottom:5px">✓ Clientes ilimitados</div>
    <div style="margin-bottom:5px">✓ Relatórios ilimitados</div>
    <div style="margin-bottom:5px">✓ <?= $p['whatsapp'] ?> WhatsApp conectado<?= $p['whatsapp']>1?'s':'' ?></div>
    <div style="margin-bottom:5px">✓ Até <?= $p['accounts'] ?> contas de anúncio</div>
    <div style="margin-bottom:5px">✓ Suporte no WhatsApp</div>
    <div>✓ Mensagens 100% personalizáveis</div>
  </div>

  <?php if (!$isCurrent): ?>
  <a href="#" class="btn btn-primary btn-full btn-sm" style="margin-top:auto"
     onclick="alert('Integração de pagamento (Stripe/Hotmart) a configurar no config.php'); return false;">
    Assinar <?= $p['label'] ?>
  </a>
  <?php else: ?>
  <div class="btn btn-ghost btn-full btn-sm" style="margin-top:auto;cursor:default;opacity:.6">Plano Atual</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<div style="text-align:center;margin-top:24px;font-size:12px;color:var(--txt3)">
  Cancele quando quiser · Cobrança em Reais · Sem taxa de adesão
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
