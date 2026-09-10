<?php
$pageTitle   = 'Notificações';
$currentPage = 'notifications';
ob_start();
$uid = currentUser()['id'];
$db  = Database::getInstance();

$filter = $_GET['filter'] ?? 'all';

$alertLogs = $db->query(
    "SELECT al.*, a.name AS nome, a.type AS tipo_alerta, 'alert' AS source
     FROM alert_logs al
     LEFT JOIN alerts a ON al.alert_id = a.id
     WHERE al.user_id = ? ORDER BY al.id DESC LIMIT 150",
    [$uid]
)->fetchAll();

$reportLogs = [];
try {
    $reportLogs = $db->query(
        "SELECT rl.*, r.title AS nome, NULL AS tipo_alerta, 'report' AS source
         FROM report_logs rl
         LEFT JOIN reports r ON rl.report_id = r.id
         WHERE rl.user_id = ? ORDER BY rl.id DESC LIMIT 150",
        [$uid]
    )->fetchAll();
} catch (\Throwable $e) {}

$logs = array_merge($alertLogs, $reportLogs);
usort($logs, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$logs = array_slice($logs, 0, 200);

$total   = count($logs);
$env     = count(array_filter($logs, fn($l) => $l['status']==='enviado'));
$err     = count(array_filter($logs, fn($l) => $l['status']!=='enviado'));
$nAlerts = count(array_filter($logs, fn($l) => $l['source']==='alert'));
$nRep    = count(array_filter($logs, fn($l) => $l['source']==='report'));

if ($filter==='enviado') $logs = array_filter($logs, fn($l) => $l['status']==='enviado');
if ($filter==='erro')    $logs = array_filter($logs, fn($l) => $l['status']!=='enviado');
if ($filter==='alert')   $logs = array_filter($logs, fn($l) => $l['source']==='alert');
if ($filter==='report')  $logs = array_filter($logs, fn($l) => $l['source']==='report');

$db->query("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL", [$uid]);

$typeLabels = [
    'saldo_minimo'   => ['⚡ Saldo',      '#F39C12'],
    'erro_conta'     => ['❌ Erro',        '#E74C3C'],
    'ctr_baixo'      => ['📉 CTR Baixo',  '#3498DB'],
    'cpc_alto'       => ['💸 CPC Alto',   '#E74C3C'],
    'custo_conv_alto'=> ['💰 Custo/Conv', '#E67E22'],
    'roas_baixo'     => ['📈 ROAS',       '#9B59B6'],
];

// estilos inline para vencer o CSS global
$thS  = 'style="font-size:10px!important;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);padding:9px 10px!important;background:var(--bg2);border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;overflow:hidden"';
$tdS  = 'style="font-size:12px!important;color:var(--txt2);padding:8px 10px!important;border-bottom:1px solid var(--border2);vertical-align:middle;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"';
?>
<style>
.lf-wrap{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.lf-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);text-decoration:none;transition:all .15s}
.lf-btn:hover{background:var(--bg4);color:var(--txt)}
.lf-btn.on{background:var(--accent);border-color:var(--accent);color:#fff}
.lf-n{font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;background:rgba(255,255,255,.2)}
.lf-btn:not(.on) .lf-n{background:var(--bg5);color:var(--txt3)}
.pill{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap}
.sok{background:#e7f5ec;color:#128C7E;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;white-space:nowrap;display:inline-block}
.ser{background:#fdecea;color:#c62828;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;white-space:nowrap;display:inline-block}
</style>

<div style="max-width:1000px">
  <div style="font-size:13px;color:var(--txt3);margin-bottom:12px"><?= $total ?> registro(s) — alertas e relatórios</div>
  <div class="lf-wrap">
    <?php foreach([['all','🔔 Todos',$total],['enviado','✓ Enviados',$env],['erro','✗ Erros',$err],['alert','⚡ Alertas',$nAlerts],['report','📊 Relatórios',$nRep]] as [$v,$l,$c]): ?>
    <a href="?filter=<?= $v ?>" class="lf-btn <?= $filter===$v?'on':'' ?>"><?= $l ?><span class="lf-n"><?= $c ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($logs)): ?>
  <div class="card"><div class="empty-state">
    <span class="material-icons-outlined">notifications_none</span>
    <h3>Nenhum registro</h3><p>Nenhum envio para este filtro.</p>
  </div></div>
  <?php else: ?>
  <div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
      <table style="width:100%;border-collapse:collapse;table-layout:fixed;min-width:740px">
        <colgroup>
          <col style="width:130px">
          <col style="width:140px">
          <col style="width:130px">
          <col style="width:120px">
          <col style="width:95px">
          <col style="width:auto">
          <col style="width:115px">
        </colgroup>
        <thead>
          <tr>
            <th <?= $thS ?>>Tipo</th>
            <th <?= $thS ?>>Nome</th>
            <th <?= $thS ?>>Destinatário</th>
            <th <?= $thS ?>>Detalhe</th>
            <th <?= $thS ?>>Status</th>
            <th <?= $thS ?>>Erro</th>
            <th <?= $thS ?>>Data/Hora</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l):
          $isRep = $l['source']==='report';
          [$label,$color] = $typeLabels[$l['tipo_alerta']??''] ?? ['🔔 Alerta','#888'];
          $detalhe = (!$isRep && !empty($l['saldo'])) ? 'Saldo: R$ '.number_format((float)$l['saldo'],2,',','.') : '';
          $nome = $l['nome'] ?? '—';
        ?>
        <tr>
          <td <?= $tdS ?>>
            <?php if ($isRep): ?>
              <span class="pill" style="background:#e8f4fd;color:#2980b9;border:1px solid #aed6f1">📊 Relatório</span>
            <?php else: ?>
              <span class="pill" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44"><?= $label ?></span>
            <?php endif; ?>
          </td>
          <td <?= $tdS ?> style="font-size:12px!important;padding:8px 10px!important;border-bottom:1px solid var(--border2);vertical-align:middle;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:var(--txt)" title="<?= e($nome) ?>"><?= e($nome) ?></td>
          <td <?= $tdS ?> title="<?= e($l['destinatario']??'') ?>"><?= e($l['destinatario']??'—') ?></td>
          <td <?= $tdS ?>><?= e($detalhe?:'—') ?></td>
          <td <?= $tdS ?>><?= $l['status']==='enviado'?'<span class="sok">✓ Enviado</span>':'<span class="ser">✗ Erro</span>' ?></td>
          <td <?= $tdS ?> style="font-size:11px!important;padding:8px 10px!important;border-bottom:1px solid var(--border2);vertical-align:middle;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c62828" title="<?= e($l['erro_msg']??'') ?>"><?= e($l['erro_msg']?mb_substr($l['erro_msg'],0,55):'—') ?></td>
          <td <?= $tdS ?> style="font-size:11px!important;padding:8px 10px!important;border-bottom:1px solid var(--border2);vertical-align:middle;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--txt3)"><?= date('d/m/Y H:i',strtotime($l['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
