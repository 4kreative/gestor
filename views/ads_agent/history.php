<?php /* views/ads_agent/history.php */ ?>

<style>
.hist-wrap{padding:24px}
.hist-header{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.hist-header h2{font-size:18px;font-weight:700;color:var(--txt);margin:0}
.hist-back{background:var(--bg3);border:1px solid var(--border2);color:var(--txt2);padding:7px 14px;border-radius:8px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.hist-back:hover{background:var(--accent3);color:var(--accent)}
.hist-table{width:100%;border-collapse:collapse;font-size:12px}
.hist-table th{background:var(--bg3);color:var(--txt3);padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border)}
.hist-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--txt2);vertical-align:middle}
.hist-table tr:hover td{background:var(--bg3)}
.st-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:700}
.st-success{background:rgba(46,213,115,.15);color:var(--success)}
.st-error{background:rgba(231,76,60,.15);color:var(--danger)}
.st-pending{background:rgba(243,156,18,.15);color:var(--warn)}
.st-cancelled{background:rgba(149,165,166,.15);color:var(--txt3)}
.act-type{background:var(--accent3);color:var(--accent);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:600}
.empty-state{text-align:center;padding:60px 20px;color:var(--txt3)}
.empty-state .ei{font-size:40px;margin-bottom:12px}
</style>

<div class="hist-wrap">
  <div class="hist-header">
    <a href="<?= APP_URL ?>/ads-agent" class="hist-back">← Voltar ao Agente</a>
    <h2>📋 Histórico de Ações Executadas</h2>
  </div>

  <?php if (empty($actions)): ?>
  <div class="empty-state">
    <div class="ei">📭</div>
    <div>Nenhuma ação executada ainda.</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="hist-table">
    <thead>
      <tr>
        <th>Data / Hora</th>
        <th>Conta</th>
        <th>Ação</th>
        <th>Entidade</th>
        <th>ID / Nome</th>
        <th>Status</th>
        <th>Detalhe</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($actions as $act): ?>
      <?php
        $params = json_decode($act['params_json'] ?? '{}', true) ?: [];
        $result = json_decode($act['result_json'] ?? '{}', true) ?: [];
        $stClass = match($act['status']) { 'success'=>'st-success','error'=>'st-error','cancelled'=>'st-cancelled', default=>'st-pending' };
        $stLabel = match($act['status']) { 'success'=>'✅ Sucesso','error'=>'❌ Erro','cancelled'=>'✗ Cancelado', default=>'⏳ Pendente' };
      ?>
      <tr>
        <td style="white-space:nowrap;color:var(--txt3);font-size:11px">
          <?= date('d/m/Y', strtotime($act['executed_at'])) ?><br>
          <?= date('H:i:s', strtotime($act['executed_at'])) ?>
        </td>
        <td style="font-size:11px"><?= e($act['account_name'] ?? '—') ?></td>
        <td><span class="act-type"><?= e(str_replace('_',' ',$act['action_type'])) ?></span></td>
        <td style="color:var(--txt3)"><?= e($act['entity_type'] ?: '—') ?></td>
        <td style="font-size:11px">
          <?php if ($act['entity_id']): ?>
          <div style="color:var(--txt3)"><?= e($act['entity_id']) ?></div>
          <?php endif; ?>
          <?php if ($act['entity_name']): ?>
          <div><?= e($act['entity_name']) ?></div>
          <?php endif; ?>
          <?php if (!empty($params)): ?>
          <div style="color:var(--txt3);margin-top:2px">
            <?php foreach (['amount','status','budget_type'] as $pk): ?>
              <?php if (isset($params[$pk])): ?>
                <span style="background:var(--bg4);padding:1px 5px;border-radius:3px"><?= $pk ?>: <?= e($params[$pk]) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </td>
        <td><span class="st-badge <?= $stClass ?>"><?= $stLabel ?></span></td>
        <td style="font-size:11px;max-width:200px">
          <?php if ($act['error_msg']): ?>
            <span style="color:var(--danger)"><?= e(mb_substr($act['error_msg'],0,80)) ?></span>
          <?php elseif (isset($result['id'])): ?>
            <span style="color:var(--success)">ID: <?= e($result['id']) ?></span>
          <?php elseif ($result['success'] ?? false): ?>
            <span style="color:var(--success)">Executado com sucesso</span>
          <?php elseif (!empty($result['bulk_results'])): ?>
            <span style="color:var(--success)"><?= count($result['bulk_results']) ?> item(s) processados</span>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
