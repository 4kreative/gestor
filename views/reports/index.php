<?php
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/Sao_Paulo');
$pageTitle   = 'Relatórios';
$currentPage = 'reports';
ob_start();

$PERIODOS = [
    'today'        => 'Hoje',
    'yesterday'    => 'Ontem',
    'last_7_days'  => 'Últimos 7 dias',
    'last_14_days' => 'Últimos 15 dias',
    'last_30_days' => 'Últimos 30 dias',
    'last_90_days' => 'Últimos 90 dias',
    'max'          => 'Máximo',
    'this_month'   => '📆 Mês atual (dia 01 até hoje)',
    'custom'       => '📅 Personalizado',
];
$FREQS = ['once'=>'Uma vez','daily'=>'Diário','weekly'=>'Semanal','monthly'=>'Mensal'];
$OBJS  = ['todos'=>'Todos','reconhecimento'=>'Reconhecimento','trafego'=>'Tráfego',
          'mensagem'=>'Mensagem','engajamento'=>'Engajamento','leads'=>'Leads','vendas'=>'Vendas',
          'turbinar'=>'Turbinar','app'=>'Promoção de App'];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:12px">
  <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <div class="search-box">
      <span class="material-icons-outlined">search</span>
      <input type="text" name="q" placeholder="Buscar relatório..." value="<?= e($q??'') ?>">
    </div>
    <select name="platform" class="form-control" style="width:140px">
      <option value="">Todos os canais</option>
      <option value="meta"   <?= ($plat??'')==='meta'?'selected':'' ?>>Meta Ads</option>
      <option value="google" <?= ($plat??'')==='google'?'selected':'' ?>>Google Ads</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  </form>
  <a href="<?= APP_URL ?>/reports/pdf-editor" class="btn btn-secondary" style="border-color:rgba(231,76,60,.4);color:#e74c3c;background:rgba(231,76,60,.06)">
    <span class="material-icons-outlined" style="font-size:16px">design_services</span> Editor PDF
  </a>
  <button class="btn btn-primary" onclick="openWizard()">
    <span class="material-icons-outlined">add</span> Criar Relatório
  </button>
</div>

<?php if (empty($reports)): ?>
<div class="card"><div class="empty-state">
  <span class="material-icons-outlined">assessment</span>
  <h3>Nenhum relatório ainda</h3>
  <p>Crie seu primeiro relatório para seus clientes</p>
  <button class="btn btn-primary" onclick="openWizard()"><span class="material-icons-outlined">add</span> Criar Relatório</button>
</div></div>
<?php else: ?>
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);display:flex;flex-direction:column;min-height:0">
  <div class="table-toolbar" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <span style="font-size:13px;color:var(--txt2)">Total: <?= $total ?> relatório(s)</span>
    <!-- Barra de ordenação -->
    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap">
      <span style="font-size:11px;color:var(--txt3);margin-right:2px">Ordenar:</span>
      <button class="sort-pill active" data-sort="client_name" data-dir="asc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--accent);background:var(--accent3);color:var(--accent);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">business</span> Empresa <span class="srt-arrow">↑</span>
      </button>
      <button class="sort-pill" data-sort="next_send_at" data-dir="asc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">schedule</span> Próx. envio <span class="srt-arrow">↕</span>
      </button>
      <button class="sort-pill" data-sort="created_at" data-dir="desc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">calendar_today</span> Data <span class="srt-arrow">↕</span>
      </button>
      <button class="sort-pill" data-sort="send_time" data-dir="asc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">access_time</span> Hora <span class="srt-arrow">↕</span>
      </button>
      <button class="sort-pill" data-sort="platform" data-dir="asc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">hub</span> Canal <span class="srt-arrow">↕</span>
      </button>
      <button class="sort-pill" data-sort="objetivo" data-dir="asc" onclick="sortTable(this)" style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;height:24px;border-radius:20px;font-size:11px;cursor:pointer;border:1px solid var(--border);background:var(--bg3);color:var(--txt2);white-space:nowrap">
        <span class="material-icons-outlined" style="font-size:11px">flag</span> Tipo <span class="srt-arrow">↕</span>
      </button>
    </div>
  </div>

  <!-- Barra de seleção em massa -->
  <div id="bulkBar" style="display:none;align-items:center;gap:10px;padding:8px 14px;background:var(--accent3);border-bottom:1px solid rgba(0,120,255,.2)">
    <span id="bulkCount" style="font-size:13px;font-weight:600;color:var(--accent)">0 selecionado(s)</span>
    <button onclick="bulkEdit()" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:5px"><span class="material-icons-outlined" style="font-size:13px">edit</span> Editar</button>
    <button onclick="bulkDelete()" class="btn btn-danger btn-sm" style="display:flex;align-items:center;gap:5px"><span class="material-icons-outlined" style="font-size:13px">delete</span> Excluir</button>
    <button onclick="clearChk()" class="btn btn-secondary btn-sm">✕ Limpar</button>
  </div>

  <div class="tbl-scroll" style="overflow-x:auto;scrollbar-width:thin;scrollbar-color:var(--border2,#333) transparent">
  <table id="rptTable" style="min-width:900px;border-collapse:separate;border-spacing:0">
    <thead><tr>
      <th id="thChk" style="position:sticky;top:0;left:0;z-index:5;background:var(--bg2);white-space:nowrap"><input type="checkbox" id="chkAll" onclick="toggleAllChk(this)" style="cursor:pointer;width:14px;height:14px;accent-color:var(--accent)"></th>
      <th id="thSt" style="position:sticky;top:0;left:0;z-index:5;background:var(--bg2);white-space:nowrap">St.</th>
      <th id="thData" style="position:sticky;top:0;left:0;z-index:5;background:var(--bg2);white-space:nowrap">Data</th>
      <th id="thNome" style="position:sticky;top:0;left:0;z-index:5;background:var(--bg2);white-space:nowrap;box-shadow:2px 0 6px -2px rgba(0,0,0,.2)">Nome</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Recebedor</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Canal</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Tipo</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Freq.</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Período</th>
      <th style="position:sticky;top:0;z-index:4;background:var(--bg2)">Próx. envio</th>
      <th style="position:sticky;top:0;z-index:4;width:100px;background:var(--bg2)">Ações</th>
    </tr></thead>
    <tbody>
    <?php foreach ($reports as $r): ?>
    <?php
      // --- lógica de status de envio ---
      $sendStatus = $r['last_send_status'] ?? '';
      $sendError  = $r['last_send_error']  ?? '';
      $sentAt     = $r['sent_at']          ?? '';
      $nextAt     = $r['next_send_at']     ?? '';

      // Badge de enviado
      $sentBadge = '';
      $nextBadge = '';
      if ($sendStatus === 'ok') {
          $statusDot   = 'var(--success)';
          $sentFmt     = $sentAt ? date('d/m H:i', strtotime($sentAt)) : '';
          $statusTitle = 'Enviado em '.$sentFmt;
          $sentBadge   = '<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:var(--success);background:rgba(39,174,96,.12);border:1px solid rgba(39,174,96,.3);border-radius:10px;padding:1px 7px;white-space:nowrap">✓ Enviado '.$sentFmt.'</span>';
      } elseif ($sendStatus === 'error') {
          $statusDot   = 'var(--danger,#e74c3c)';
          $statusTitle = 'Erro: '.$sendError;
          $shortErr    = mb_strlen($sendError) > 40 ? mb_substr($sendError,0,40).'…' : $sendError;
          $sentBadge   = '<span title="'.e($sendError).'" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#e74c3c;background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);border-radius:10px;padding:1px 7px;white-space:nowrap;cursor:help">✕ Erro: '.e($shortErr).'</span>';
      } else {
          $statusDot   = 'var(--txt3)';
          $statusTitle = 'Sem envio anterior';
      }
      // Badge do próximo envio (sempre separado)
      if (!empty($nextAt)) {
          $nextFmt   = date('d/m H:i', strtotime($nextAt));
          $nextBadge = '<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:var(--accent);background:var(--accent3);border:1px solid rgba(0,120,255,.2);border-radius:10px;padding:1px 7px;white-space:nowrap;margin-top:3px">🕐 '.$nextFmt.'</span>';
          if (empty($sentBadge)) { $statusDot = 'var(--accent)'; $statusTitle = 'Agendado para '.$nextFmt; }
      }
      $sendBadge = '<div style="display:flex;flex-direction:column;gap:3px">'.($sentBadge ?: '').$nextBadge.'</div>';
      if (!$sentBadge && !$nextBadge) $sendBadge = '<span style="font-size:10px;color:var(--txt3)">—</span>';
    ?>
    <tr
      data-client_name="<?=e(strtolower($r['client_name']??''))?>"
      data-next_send_at="<?=e($r['next_send_at']??'9999-12-31 23:59')?>"
      data-created_at="<?=e($r['created_at']??'')?>"
      data-send_time="<?=e($r['send_time']??'00:00')?>"
      data-platform="<?=e($r['platform']??'')?>"
      data-objetivo="<?=e($r['objetivo']??'')?>"
    >
      <td data-sticky="0" style="position:sticky;left:0;z-index:2;background:var(--bg2);white-space:nowrap"><input type="checkbox" class="rowChk" value="<?=$r['id']?>" data-json="<?=htmlspecialchars(json_encode($r),ENT_QUOTES)?>" onclick="onRowChk()" style="cursor:pointer;width:14px;height:14px;accent-color:var(--accent)"></td>
      <td data-sticky="1" style="position:sticky;left:0;z-index:2;background:var(--bg2);white-space:nowrap">
        <?php $isActive = ($r['status']??'active') !== 'paused'; ?>
        <button onclick="toggleReportStatus(<?=$r['id']?>, <?=$isActive?'true':'false'?>)"
          title="<?= $isActive ? 'Clique para pausar' : 'Clique para ativar' ?>"
          style="background:none;border:none;cursor:pointer;padding:2px 4px">
          <!-- Toggle switch -->
          <span style="
            display:inline-block;position:relative;width:32px;height:18px;
            background:<?= $isActive ? 'var(--success)' : 'var(--border2,#444)' ?>;
            border-radius:9px;transition:background .2s;vertical-align:middle">
            <span style="
              display:inline-block;position:absolute;top:2px;
              left:<?= $isActive ? '16px' : '2px' ?>;
              width:14px;height:14px;background:#fff;border-radius:50%;
              transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.3)">
            </span>
          </span>
        </button>
      </td>
      <td data-sticky="2" style="font-size:12px;color:var(--txt2);position:sticky;left:0;z-index:2;background:var(--bg2);white-space:nowrap"><?= date('d/M',strtotime($r['created_at'])) ?></td>
      <td data-sticky="3" style="min-width:180px;position:sticky;left:0;z-index:2;background:var(--bg2);box-shadow:2px 0 6px -2px rgba(0,0,0,.2);padding-right:12px">
        <div style="font-weight:600;color:var(--txt)"><?= e($r['title']) ?></div>
        <?php if(!empty($r['client_name'])): ?><div style="font-size:11px;color:var(--txt3);margin-top:2px"><?= e($r['client_name']) ?></div><?php endif; ?>
        <?php if(!empty($r['camp_labels'])): ?><div style="font-size:10px;color:var(--txt3);opacity:.7;margin-top:1px;font-style:italic">🎯 <?= e($r['camp_labels']) ?></div><?php endif; ?>
      </td>
      <td style="font-size:12px;color:var(--txt2);padding-left:12px">
        <?php
          $gname = trim($r['group_name'] ?? '');
          $gid   = trim($r['group_id']   ?? '');
          $rph   = trim($r['recipient_phone'] ?? '');
          // Limpa placeholder do select
          $isPlaceholder = in_array($gname, ['— Selecione o grupo —', '— Selecione —', '']);
          $displayName = $isPlaceholder ? '' : $gname;
        ?>
        <?php if($r['recv_type']==='group'): ?>
          <i class="fa-brands fa-whatsapp" style="color:var(--success)"></i>
          <?php if($displayName): ?>
            <div style="font-weight:600;font-size:12px;white-space:nowrap"><?= e($displayName) ?></div>
            <?php if($gid): ?><div style="font-size:10px;color:var(--txt3);white-space:nowrap"><?= e($gid) ?></div><?php endif; ?>
          <?php elseif($gid): ?>
            <div style="font-size:11px;color:var(--txt2)">👥 Grupo WA</div>
            <div style="font-size:10px;color:var(--txt3)"><?= e($gid) ?></div>
          <?php else: ?>
            <span style="font-size:11px;color:var(--warn)">⚠ Sem grupo</span>
          <?php endif; ?>
        <?php elseif($rph): ?>
          <i class="fa-brands fa-whatsapp" style="color:var(--success)"></i>
          <?= e($rph) ?>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td><?= $r['platform']==='meta'?'<i class="fa-brands fa-facebook" style="color:#1877F2"></i>':'<i class="fa-brands fa-google" style="color:#EA4335"></i>' ?></td>
      <td><span class="badge badge-blue badge-sm"><?= e($OBJS[$r['objetivo']??'todos']??'Todos') ?></span></td>
      <td><span class="badge badge-gray badge-sm"><?= $FREQS[$r['frequency']??'once'] ?></span></td>
      <td style="font-size:11px;color:var(--txt2);white-space:nowrap"><?php
        $pt=$r['period_type']??'';
        if(strpos($pt,'custom|')===0){$pp=explode('|',$pt);echo isset($pp[1])&&isset($pp[2])?date('d/m/Y',strtotime($pp[1])).' → '.date('d/m/Y',strtotime($pp[2])):'Personalizado';}
        else echo $PERIODOS[$pt]??'—';
      ?></td>
      <td><?= $sendBadge ?></td>
      <td>
        <div style="display:flex;gap:3px">
          <button onclick="openAiReport(<?=htmlspecialchars(json_encode(['id'=>$r['id'],'title'=>$r['title'],'ad_account_id'=>$r['ad_account_id'],'camp_ids'=>$r['camp_ids']??'','period_type'=>$r['period_type']??'last_7_days','platform'=>$r['platform']??'meta']),ENT_QUOTES)?>)" class="btn btn-secondary btn-sm btn-icon" title="Analisar com IA" style="background:var(--accent3);border-color:var(--accent);color:var(--accent)"><span class="material-icons-outlined" style="font-size:13px">auto_awesome</span></button>
          <button onclick="openPdfSendModal(this)" data-id="<?=$r['id']?>" data-title="<?=htmlspecialchars($r['title'],ENT_QUOTES)?>" data-msg="<?=htmlspecialchars($r['message_text']??'',ENT_QUOTES)?>" class="btn btn-secondary btn-sm btn-icon" title="PDF / Enviar WhatsApp" style="background:rgba(231,76,60,.08);border-color:rgba(231,76,60,.3);color:#e74c3c"><span class="material-icons-outlined" style="font-size:13px">picture_as_pdf</span></button>
          <button onclick="sendNow(<?=$r['id']?>,'<?=e($r['recv_type']==='group'?($r['group_name']??$r['recipient_phone']??''):($r['recipient_phone']??''))?>','<?=e($r['recv_type']??'phone')?>')" class="btn btn-success btn-sm btn-icon" title="Enviar agora"><i class="fa-brands fa-whatsapp" style="font-size:13px"></i></button>
          <button onclick="editRel(<?=htmlspecialchars(json_encode($r),ENT_QUOTES)?>)" class="btn btn-secondary btn-sm btn-icon" title="Editar"><span class="material-icons-outlined" style="font-size:13px">edit</span></button>
          <form method="POST" action="<?=APP_URL?>/reports/delete" style="display:inline">
            <input type="hidden" name="_token" value="<?=e($_SESSION['csrf_token'])?>">
            <input type="hidden" name="id" value="<?=$r['id']?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon" data-confirm="Excluir «<?=e($r['title'])?>»?"><span class="material-icons-outlined" style="font-size:13px">delete</span></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div><!-- fim scroll inner -->
</div>
<?php if($pages>1): ?>
<div class="pagination">
  <span class="pg-info">Pág. <?=$page?>/<?=$pages?></span>
  <?php for($i=1;$i<=$pages;$i++): ?><a href="?page=<?=$i?>&q=<?=urlencode($q??'')?>&platform=<?=urlencode($plat??'')?>" class="pg-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- MODAL ENVIAR -->
<div id="mdSend" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;align-items:center;justify-content:center">
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:420px;max-width:95vw">
  <div style="font-size:15px;font-weight:600;margin-bottom:16px"><i class="fa-brands fa-whatsapp" style="color:var(--success)"></i> Enviar agora</div>

  <!-- Resultado do envio -->
  <div id="sndResult" style="display:none;margin-bottom:16px;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;display:none;align-items:center;gap:8px"></div>

  <form id="fSend">
    <input type="hidden" name="_token" value="<?=e($_SESSION['csrf_token'])?>">
    <input type="hidden" name="report_id" id="sndId">
    <div class="form-group" id="sndPhoneRow"><label class="form-label">Telefone</label>
      <input type="text" name="phone" id="sndPhone" class="form-control" placeholder="5511999999999">
      <div class="form-hint">DDI+DDD+número</div></div>
    <div class="form-group" id="sndGroupRow" style="display:none">
      <label class="form-label">Grupo WhatsApp</label>
      <div id="sndGroupName" style="padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--txt1)">—</div>
    </div>
    <div class="form-actions" style="border-top:none;padding-top:0">
      <button type="submit" class="btn btn-primary" id="btnSend">
        <i class="fa-brands fa-whatsapp"></i> Enviar
      </button>
      <button type="button" class="btn btn-secondary" id="btnCancelarSend" onclick="document.getElementById('mdSend').style.display='none'">Cancelar</button>
    </div>
  </form>
</div></div>

<!-- MODAL EDITAR -->
<div id="mdEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:700px;margin:auto;padding:24px">
  <div style="font-size:15px;font-weight:700;margin-bottom:18px;display:flex;justify-content:space-between">
    <span><span class="material-icons-outlined" style="color:var(--accent);vertical-align:middle">edit_calendar</span> Editar / Reagendar</span>
    <button onclick="document.getElementById('mdEdit').style.display='none'" style="background:none;border:none;color:var(--txt2);cursor:pointer;font-size:20px">✕</button>
  </div>
  <form method="POST" action="<?=APP_URL?>/reports/update" id="fEdit">
    <input type="hidden" name="_token" value="<?=e($_SESSION['csrf_token'])?>">
    <input type="hidden" name="id" id="eId">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Nome</label><input type="text" name="title" id="eTitle" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Objetivo</label>
        <select name="objetivo" id="eObj" class="form-control"><?php foreach($OBJS as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Período</label>
        <select name="period_type" id="ePeriod" class="form-control" onchange="toggleEditCustom(this.value)"><?php foreach($PERIODOS as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select>
      <div id="eCustomWrap" style="display:none;gap:8px;margin-top:8px;align-items:center">
        <div style="flex:1"><label style="font-size:11px;color:var(--txt3)">De</label>
          <input type="date" id="eCustomStart" name="custom_start" class="form-control" style="font-size:13px"></div>
        <div style="padding-top:14px;color:var(--txt3)">→</div>
        <div style="flex:1"><label style="font-size:11px;color:var(--txt3)">Até</label>
          <input type="date" id="eCustomEnd" name="custom_end" class="form-control" style="font-size:13px"></div>
      </div>
      </div>
      <div class="form-group"><label class="form-label">Frequência</label>
        <select name="frequency" id="eFreq" class="form-control"><?php foreach($FREQS as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Horário</label><input type="time" name="send_time" id="eTime" class="form-control"></div>
      <div class="form-group"><label class="form-label">Telefone</label><input type="text" name="recipient_phone" id="ePhone" class="form-control" placeholder="5511999999999"></div>
    </div>
    <div class="form-group"><label class="form-label">Dias da semana</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap" id="eDaysWrap">
        <?php foreach(['0'=>'Dom','1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb'] as $v=>$l): ?>
        <button type="button" class="day-btn" data-day="<?=$v?>" onclick="this.classList.toggle('active')"><?=$l?></button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="send_days" id="eDays">
    </div>
    <div class="form-group"><label class="form-label">Mensagem</label><textarea name="message_text" id="eMsg" class="form-control" rows="7" style="font-size:12px"></textarea></div>
    <div class="form-actions" style="border-top:none;padding-top:0;justify-content:space-between">
      <button type="submit" class="btn btn-primary" onclick="document.getElementById('eDays').value=Array.from(document.querySelectorAll('#eDaysWrap .day-btn.active')).map(b=>b.dataset.day).join(',')"><span class="material-icons-outlined">save</span> Salvar</button>
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('mdEdit').style.display='none'">Cancelar</button>
    </div>
  </form>
</div></div>

<!-- WIZARD -->
<div id="mdWiz" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:700;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto">
<div class="modal-inner" style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:820px;margin:auto">

  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid var(--border)">
    <div><div id="wTitle" style="font-size:15px;font-weight:700;color:var(--txt)">Criar relatório</div><div id="wSub" style="font-size:12px;color:var(--txt2)"></div></div>
    <button onclick="closeWizard()" style="background:none;border:none;color:var(--txt2);cursor:pointer;font-size:22px">✕</button>
  </div>

  <!-- Steps -->
  <div style="display:flex;align-items:center;justify-content:center;padding:12px 22px;border-bottom:1px solid var(--border);gap:0">
    <?php foreach([1=>'Início',2=>'Canal',3=>'Mensagem',4=>'Programação',5=>'Conclusão'] as $n=>$lbl): ?>
    <div style="display:flex;align-items:center">
      <div style="display:flex;flex-direction:column;align-items:center;gap:3px;min-width:64px;cursor:pointer" onclick="gotoStep(<?=$n?>)">
        <div id="wC<?=$n?>" style="width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;background:var(--bg4);color:var(--txt3);transition:all .2s"><?=$n?></div>
        <div id="wL<?=$n?>" style="font-size:10px;color:var(--txt3);text-align:center"><?=$lbl?></div>
      </div>
      <?php if($n<5): ?><div style="width:24px;height:1px;background:var(--border);margin-bottom:13px"></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div id="wBody" style="padding:22px;min-height:360px"></div>

  <div style="display:flex;justify-content:space-between;padding:14px 22px;border-top:1px solid var(--border)">
    <button id="wPrev" class="btn btn-secondary" onclick="prev()" style="display:none">← Anterior</button>
    <div></div>
    <button id="wNext" class="btn btn-primary" onclick="next()">Próximo →</button>
  </div>
</div></div>

<style>
:root { --bg-sel: color-mix(in srgb, var(--accent) 18%, var(--bg)); }
.tbl-scroll table{border-collapse:separate;border-spacing:0}
.tbl-scroll::-webkit-scrollbar{width:5px;height:6px}
.tbl-scroll::-webkit-scrollbar-track{background:var(--bg2)}
.tbl-scroll::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}
.tbl-scroll::-webkit-scrollbar-thumb:hover{background:var(--txt3)}
.btn-ai{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:4px;border:1px solid var(--accent);background:var(--accent3);color:var(--accent);font-size:10px;font-weight:700;cursor:pointer;font-family:var(--font);transition:all .15s;white-space:nowrap;line-height:1.5}
.btn-ai:hover{background:var(--accent);color:#fff}
.rp-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;align-items:center;justify-content:center;padding:16px}
.rp-overlay.open{display:flex}
.rp-box{background:var(--bg2);border:1px solid var(--border);border-radius:12px;width:100%;max-width:720px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.rp-hdr{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid var(--border);flex-shrink:0}
.rp-title{font-size:14px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px}
.rp-close{background:none;border:none;color:var(--txt3);cursor:pointer;font-size:22px;padding:0 6px;border-radius:4px;line-height:1}
.rp-close:hover{background:var(--bg3);color:var(--txt)}
.rp-body{padding:18px;overflow-y:auto;flex:1}
.rp-footer{padding:11px 18px;border-top:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0}
.rp-send{padding:14px 18px;border-top:1px solid var(--border)}
.rp-spin{width:36px;height:36px;border:3px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:rpspin .7s linear infinite;margin:40px auto;display:block}
@keyframes rpspin{to{transform:rotate(360deg)}}
.rp-ms{display:flex;gap:8px;margin-bottom:14px}
.rp-mc{flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:8px;text-align:center}
.rp-mv{font-size:14px;font-weight:700;color:var(--txt)}
.rp-ml{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.rp-txt{font-size:13px;line-height:1.8;color:var(--txt);word-break:break-word}
.rp-txt strong{font-weight:700}
.rp-ta{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:9px 11px;color:var(--txt);font-family:var(--font);font-size:12px;line-height:1.5;resize:vertical;min-height:110px;box-sizing:border-box}
.rp-ta:focus{outline:none;border-color:var(--accent)}

/* ── Animação botão Enviar ── */
.snd-dots-wrap{display:inline-flex;align-items:center;gap:3px}
.snd-dot{width:5px;height:5px;border-radius:50%;background:currentColor;animation:sndBounce 1.1s infinite ease-in-out both}
.snd-dot:nth-child(1){animation-delay:-.32s}
.snd-dot:nth-child(2){animation-delay:-.16s}
@keyframes sndBounce{0%,80%,100%{transform:scale(0);opacity:.4}40%{transform:scale(1);opacity:1}}
@keyframes sndPop{0%{transform:scale(0)}70%{transform:scale(1.3)}100%{transform:scale(1)}}
.day-btn{padding:6px 10px;border:1px solid var(--border2);border-radius:6px;cursor:pointer;font-size:12px;color:var(--txt2);background:var(--bg3);font-family:var(--font)}
.day-btn.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.ocard{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px;border:1px solid var(--border2);border-radius:var(--radius);cursor:pointer;font-size:11px;font-weight:600;color:var(--txt2);background:var(--bg3);min-width:74px;transition:all .15s}
.ocard.sel{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.recv-tab{padding:7px 14px;border:1px solid var(--border2);border-radius:var(--radius);cursor:pointer;font-size:12px;font-weight:600;color:var(--txt2);background:var(--bg3);display:inline-flex;align-items:center;gap:5px;font-family:var(--font)}
.recv-tab.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}

/* Var picker */
.varpicker-wrap{position:relative}
.varpicker-btn{width:100%;padding:9px 13px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt2);font-family:var(--font);font-size:13px;cursor:pointer;text-align:left;display:flex;align-items:center;justify-content:space-between}
.varpicker-btn:hover{border-color:var(--accent);color:var(--txt)}
.varpicker-panel{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius2);z-index:800;box-shadow:var(--shadow);display:none;flex-direction:column;max-height:400px}
.varpicker-panel.open{display:flex}
.varpicker-search{padding:10px;border-bottom:1px solid var(--border)}
.varpicker-search input{width:100%;padding:7px 10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt);font-family:var(--font);font-size:13px;outline:none}
.varpicker-cats{display:flex;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border);flex-wrap:wrap}
.vcat-btn{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;background:var(--bg3);border:1px solid var(--border2);color:var(--txt2);font-family:var(--font)}
.vcat-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.varpicker-list{overflow-y:auto;flex:1;padding:8px 10px}
.vtag{display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(0,120,255,.1);color:var(--accent);border:1px solid rgba(0,120,255,.2);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace}
.vtag:hover{background:var(--accent);color:#fff}
.vtag.green{background:rgba(39,174,96,.1);color:var(--success);border-color:rgba(39,174,96,.3)}
.vtag.green:hover{background:var(--success);color:#fff}
.vtag.orange{background:rgba(243,156,18,.1);color:var(--warn);border-color:rgba(243,156,18,.3)}
.vtag.orange:hover{background:var(--warn);color:#fff}
.vtag.pink{background:rgba(155,89,182,.1);color:#bb88ff;border-color:rgba(155,89,182,.3)}
.vtag.pink:hover{background:#9b59b6;color:#fff}
.vsec-title{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;padding:6px 2px 4px;width:100%;display:block}

/* Período dropdown */
.periodo-select-wrap{position:relative}
.periodo-select-btn{width:100%;padding:9px 13px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt);font-family:var(--font);font-size:13px;cursor:pointer;text-align:left;display:flex;align-items:center;justify-content:space-between}
.periodo-dropdown{position:absolute;top:calc(100%+4px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius2);z-index:800;box-shadow:var(--shadow);display:none;max-height:280px;overflow-y:auto}
.periodo-dropdown.open{display:block}
.periodo-opt{padding:9px 14px;font-size:13px;color:var(--txt2);cursor:pointer;transition:background .1s}
.periodo-opt:hover{background:var(--bg3);color:var(--txt)}
.periodo-opt.active{background:var(--accent3);color:var(--accent);font-weight:600}
</style>

<script>
var APP_URL   = '<?=APP_URL?>';
var CSRF      = '<?=e($_SESSION["csrf_token"] ?? "")?>';

// ══ STICKY COLUMNS: calcula left real após render ══════════════════════════
(function fixStickyLeft(){
  function apply(){
    var tbl = document.getElementById('rptTable');
    if(!tbl) return;
    var thChk  = document.getElementById('thChk');
    var thSt   = document.getElementById('thSt');
    var thData = document.getElementById('thData');
    var thNome = document.getElementById('thNome');
    if(!thChk||!thSt||!thData||!thNome) return;

    var w0 = thChk.offsetWidth;
    var w1 = thSt.offsetWidth;
    var w2 = thData.offsetWidth;

    var l0 = 0;
    var l1 = w0;
    var l2 = w0 + w1;
    var l3 = w0 + w1 + w2;

    // Aplica nos th
    thChk.style.left  = l0+'px';
    thSt.style.left   = l1+'px';
    thData.style.left = l2+'px';
    thNome.style.left = l3+'px';

    // Aplica nos td de todas as linhas
    tbl.querySelectorAll('td[data-sticky="0"]').forEach(function(td){ td.style.left = l0+'px'; });
    tbl.querySelectorAll('td[data-sticky="1"]').forEach(function(td){ td.style.left = l1+'px'; });
    tbl.querySelectorAll('td[data-sticky="2"]').forEach(function(td){ td.style.left = l2+'px'; });
    tbl.querySelectorAll('td[data-sticky="3"]').forEach(function(td){ td.style.left = l3+'px'; });
  }
  // Roda após render completo
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', apply);
  } else {
    apply();
  }
  window.addEventListener('resize', apply);
})();
// ══ FIM STICKY COLUMNS ════════════════════════════════════════════════════

// ══ SELEÇÃO EM MASSA ══════════════════════════════════════════════════════════
function onRowChk(){
  var chks=document.querySelectorAll('.rowChk:checked');
  var bar=document.getElementById('bulkBar');
  var cnt=document.getElementById('bulkCount');
  var all=document.getElementById('chkAll');
  var total=document.querySelectorAll('.rowChk').length;
  bar.style.display=chks.length>0?'flex':'none';
  if(cnt) cnt.textContent=chks.length+' selecionado'+(chks.length>1?'s':'');
  if(all) all.indeterminate=(chks.length>0&&chks.length<total);
  if(all) all.checked=(chks.length===total&&total>0);
  // Destaca linha — muda tr e força sticky tds para cor visível
  document.querySelectorAll('.rowChk').forEach(function(c){
    var tr=c.closest('tr');
    if(!tr) return;
    var sel=c.checked;
    tr.style.backgroundColor=sel?'rgba(24,95,165,0.15)':'';
    tr.querySelectorAll('td[style*="sticky"]').forEach(function(td){
      td.style.background=sel?'var(--bg3)':'var(--bg2)';
    });
  });
}
function toggleAllChk(cb){
  document.querySelectorAll('.rowChk').forEach(function(c){c.checked=cb.checked;});
  onRowChk();
}
function clearChk(){
  document.querySelectorAll('.rowChk').forEach(function(c){c.checked=false;});
  var all=document.getElementById('chkAll');
  if(all){all.checked=false;all.indeterminate=false;}
  onRowChk();
}
function bulkEdit(){
  var chks=document.querySelectorAll('.rowChk:checked');
  if(chks.length===0){alert('Selecione pelo menos um relatório.');return;}
  if(chks.length>1){alert('Selecione apenas 1 relatório para editar.');return;}
  var r=JSON.parse(chks[0].dataset.json);
  editRel(r);
  clearChk();
}
function bulkDelete(){
  var chks=document.querySelectorAll('.rowChk:checked');
  if(chks.length===0){alert('Selecione pelo menos um relatório.');return;}
  var nomes=Array.from(chks).map(function(c){return JSON.parse(c.dataset.json).title;});
  if(!confirm('Excluir '+chks.length+' relatório(s)?\n\n'+nomes.join('\n'))){return;}
  var ids=Array.from(chks).map(function(c){return c.value;});
  var token=document.querySelector('[name="_token"]')?document.querySelector('[name="_token"]').value:CSRF;
  Promise.all(ids.map(function(id){
    var fd=new FormData();
    fd.append('_token',token);fd.append('id',id);
    return fetch(APP_URL+'/reports/delete',{method:'POST',body:fd,credentials:'include'});
  })).then(function(){location.reload();});
}
// ══ FIM SELEÇÃO EM MASSA ══════════════════════════════════════════════════════

async function toggleReportStatus(id, isActive) {
  var action = isActive ? 'pausar' : 'ativar';
  if (!confirm('Deseja ' + action + ' este relatório?')) return;
  try {
    var fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('id', id);
    fd.append('status', isActive ? 'paused' : 'active');
    var r = await fetch(APP_URL + '/reports/toggle-status', {method:'POST', body:fd, credentials:'include'});
    var d = await r.json();
    if (d.success) {
      window.location.reload();
    } else {
      alert('Erro: ' + (d.error || 'Tente novamente'));
    }
  } catch(e) {
    alert('Erro de conexao: ' + e.message);
  }
}
var CONTAS     = <?=json_encode(array_values($accounts??[]))?>;
var INSTANCES  = <?=json_encode(array_values($instances??[]))?>;
var TEMPLATES  = <?=json_encode(array_values($templates??[]))?>;
var CLIENTS    = <?=json_encode(array_values($clients??[]))?>;
var WP_GROUPS  = <?=json_encode(array_values($wpGroups??[]))?>;
var PDF_TEMPLATES = <?=json_encode(array_values($pdfTemplates??[]))?>;
var _pdfSendTplId = 0;
var CSRF_TOKEN = '<?= e($_SESSION["csrf_token"] ?? "") ?>';

// ── Modal Enviar PDF via WhatsApp ─────────────────────────────────────────
var _pdfSendId = 0;

var _currentPdfUrl = '';

function openPdfSendModal(el) {
  var id          = el ? parseInt(el.dataset.id) : 0;
  var title       = el ? (el.dataset.title||'') : '';
  var msgTemplate = el ? (el.dataset.msg||'') : '';
  _pdfSendId     = id;
  _currentPdfUrl = '';

  var ex = document.getElementById('pdfSendOverlay');
  if (ex) ex.remove();

  var instances = INSTANCES||[], groups=WP_GROUPS||[], clients=CLIENTS||[];

  var instOpts = '';
  instances.forEach(function(i, idx){
    var sel = idx===0 ? ' selected' : '';
    instOpts += '<option value="'+i.id+'"'+sel+'>'+esc(i.instance_name)+(i.phone_number?' ('+i.phone_number+')':'')+'</option>';
  });

  // Clientes separados de grupos
  var clientOpts = '<option value="">— Selecione o cliente —</option>';
  clients.forEach(function(cl){
    if(cl.phone) clientOpts += '<option value="phone|'+esc(cl.phone)+'|">'+esc(cl.name)+(cl.company?' — '+esc(cl.company):'')+' ('+esc(cl.phone)+')</option>';
  });
  var groupOpts = '<option value="">— Selecione o grupo —</option>';
  groups.forEach(function(g){
    groupOpts += '<option value="group|'+esc(g.id)+'|'+esc(g.instance_name||g.instance||'')+'">'+esc(g.name)+'</option>';
  });

  // Mensagens pré-definidas
  var msgTemplates = [
    {l:'Padrão com link', v:'Olá! 👋\n\nSeu relatório *'+title+'* está pronto! Acesse o link e veja as métricas:\n\n👉 {link}\n\nQualquer dúvida estou à disposição! 🚀'},
    {l:'Completo com métricas', v:'Olá! 👋\n\n📊 *Relatório: '+title+'*\n📅 Período: {periodo}\n\n💰 Investimento: R$ {investimento}\n👁 Impressões: {impressoes}\n🖱 Cliques: {cliques}\n📊 CTR: {ctr}%\n👥 Alcance: {alcance}\n💬 Mensagens: {msg}\n\n👉 Acesse o relatório completo:\n{link}\n\nQualquer dúvida estou à disposição! 🚀'},
    {l:'Resumo executivo', v:'Olá! 👋\n\n📊 *Resumo — '+title+'*\nPeríodo: {periodo}\n\n✅ Investimento: R$ {investimento}\n✅ Alcance: {alcance} pessoas\n✅ Cliques: {cliques}\n✅ CTR: {ctr}%\n\n👉 Ver relatório completo: {link}'},
    {l:'Só o link', v:'Relatório disponível: {link}'},
  ];
  var defMsg = (msgTemplate||'').trim() || msgTemplates[0].v;

  var ov = document.createElement('div');
  ov.id = 'pdfSendOverlay';
  ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px';

  var inner = document.createElement('div');
  inner.style.cssText = 'background:var(--bg2);border:1px solid var(--border);border-radius:14px;width:100%;max-width:500px;max-height:92vh;overflow-y:auto;padding:22px';

  inner.innerHTML = ''
    + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">'
    +   '<span style="font-size:14px;font-weight:700;color:var(--txt)">📄 PDF / WhatsApp</span>'
    +   '<button id="pdfSendClose" style="background:none;border:none;cursor:pointer;color:var(--txt3);font-size:22px;padding:0 4px">✕</button>'
    + '</div>'

    // Link público
    + '<div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:14px">'
    +   '<div style="font-size:10px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">🔗 Link público (sem login)</div>'
    +   '<div id="pdfLinkPreview" style="font-size:12px;color:var(--txt3);min-height:18px;margin-bottom:8px">⏳ Gerando...</div>'
    +   '<div style="display:flex;gap:6px">'
    +     '<button id="pdfCopyBtn" class="btn btn-secondary btn-sm" style="flex:1">📋 Copiar link</button>'
    +     '<button id="pdfOpenBtn" class="btn btn-secondary btn-sm" style="flex:1">👁 Abrir</button>'
    +   '</div>'
    + '</div>'

    // Template PDF
    + (PDF_TEMPLATES.length > 0
        ? '<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border)">'
          + '<div style="font-size:10px;font-weight:700;color:var(--txt3);margin-bottom:6px;text-transform:uppercase">🎨 Template do PDF</div>'
          + '<select id="pdfTplSelect" class="form-control form-control-sm" onchange="_pdfSendTplId=parseInt(this.value)||0;generatePdfLink(_pdfSendId)">'
          + '<option value="0">— Selecione um template —</option>'
          + PDF_TEMPLATES.map(function(t){ return '<option value="'+t.id+'">'+esc(t.name)+'</option>'; }).join('')
          + '</select>'
          + '<div style="font-size:10px;color:var(--txt3);margin-top:4px">Escolha o template adequado para o objetivo do relatório</div>'
          + '</div>'
        : '')

    // WhatsApp
    + '<div style="font-size:11px;font-weight:700;color:var(--txt3);margin-bottom:10px;padding-top:12px;border-top:1px solid var(--border)">📱 Enviar via WhatsApp</div>'
    + (instances.length > 1
        ? '<div style="margin-bottom:10px"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">📱 Instância</label><select id="pdfSendInst" class="form-control form-control-sm"><option value="">— Padrão —</option>' + instOpts + '</select></div>'
        : '<input type="hidden" id="pdfSendInst" value="">'
      )

    // Tipo de destino
    + '<div style="margin-bottom:8px"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">Tipo de destino</label>'
    +   '<div style="display:flex;gap:8px">'
    +     '<label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px"><input type="radio" name="pdfDestType" value="client" checked onchange="pdfToggleDestType(this.value)" style="accent-color:var(--accent)"> 👤 Cliente</label>'
    +     '<label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px"><input type="radio" name="pdfDestType" value="group" onchange="pdfToggleDestType(this.value)" style="accent-color:var(--accent)"> 👥 Grupo</label>'
    +   '</div>'
    + '</div>'

    // Cliente select
    + '<div id="pdfClientDiv" style="margin-bottom:10px"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">👤 Cliente</label><select id="pdfSendClient" class="form-control form-control-sm">'+clientOpts+'</select></div>'
    // Grupo select
    + '<div id="pdfGroupDiv" style="margin-bottom:10px;display:none"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">👥 Grupo</label><div style="display:flex;gap:6px;align-items:center;margin-bottom:4px"><select id="pdfSendGroup" class="form-control form-control-sm" style="flex:1">'+groupOpts+'</select><button type="button" class="btn btn-secondary btn-sm" onclick="pdfLoadGroups()" style="white-space:nowrap;flex-shrink:0">🔄 Buscar</button></div><div id="pdfGrpStatus" style="font-size:11px;color:var(--txt3)"></div></div>'

    // Mensagem
    + '<div style="margin-bottom:8px"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">Mensagem pronta</label><select id="pdfMsgTpl" class="form-control form-control-sm" onchange="applyPdfMsgTpl(this.value)"><option value="">— Escolher modelo —</option></select></div>'
    + '<div style="margin-bottom:12px"><label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">Mensagem <span style="font-weight:400;font-size:10px">(use {link} para o link)</span></label>'
    +   '<textarea id="pdfSendMsg" style="width:100%;height:100px;padding:8px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--txt);font-size:12px;line-height:1.6;resize:vertical;box-sizing:border-box"></textarea>'
    + '</div>'

    + '<button id="pdfSendBtn" class="btn btn-primary" style="width:100%">📤 Enviar no WhatsApp</button>'
    + '<div id="pdfSendFeedback" style="margin-top:8px;font-size:12px;text-align:center"></div>';

  ov.appendChild(inner);
  document.body.appendChild(ov);

  // Set values safely
  document.getElementById('pdfSendMsg').value = defMsg;

  // Fill template selector
  var tplSel = document.getElementById('pdfMsgTpl');
  if (tplSel) {
    msgTemplates.forEach(function(t) {
      var opt = document.createElement('option');
      opt.value = t.v;
      opt.textContent = t.l;
      tplSel.appendChild(opt);
    });
  }

  // Buttons
  document.getElementById('pdfSendClose').onclick = function(){ ov.remove(); };
  document.getElementById('pdfCopyBtn').onclick   = function(){
    if (!_currentPdfUrl) { alert('Aguarde o link ser gerado'); return; }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(_currentPdfUrl).then(function(){
        var btn = document.getElementById('pdfCopyBtn');
        if(btn){ btn.textContent='✅ Copiado!'; setTimeout(function(){ btn.textContent='📋 Copiar link'; }, 2000); }
      });
    } else {
      prompt('Copie o link:', _currentPdfUrl);
    }
  };
  document.getElementById('pdfOpenBtn').onclick   = function(){
    if (!_currentPdfUrl) { alert('Aguarde o link ser gerado'); return; }
    window.open(_currentPdfUrl, '_blank');
  };
  document.getElementById('pdfSendBtn').onclick   = doSendPdfLink;
  ov.addEventListener('click', function(e){ if(e.target===ov) ov.remove(); });

  // Auto-load grupos se grupos vazios
  if (!groups || groups.length === 0) {
    setTimeout(function(){ pdfLoadGroups(); }, 200);
  } else {
    var st = document.getElementById('pdfGrpStatus');
    if(st) st.textContent = '✅ '+groups.length+' grupo(s)';
  }

  // Generate link
  generatePdfLink(id);
}

function applyPdfMsgTpl(val) {
  if (!val) return;
  var ta = document.getElementById('pdfSendMsg');
  if (ta) ta.value = val;
  // Reset select
  var sel = document.getElementById('pdfMsgTpl');
  if (sel) sel.value = '';
}

/* ── Buscar grupos dinamicamente ── */
async function pdfLoadGroups() {
  var instEl = document.getElementById('pdfSendInst');
  var wid = instEl ? instEl.value : '';
  if (!wid && INSTANCES && INSTANCES.length > 0) wid = INSTANCES[0].id;
  var sel = document.getElementById('pdfSendGroup');
  var status = document.getElementById('pdfGrpStatus');
  if (!wid) { if(status) status.textContent = '⚠ Nenhuma instância disponível.'; return; }
  if (sel) { sel.disabled = true; sel.innerHTML = '<option value="">⏳ Buscando...</option>'; }
  if (status) status.textContent = '';
  try {
    var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wid));
    var d = await r.json();
    if (sel) sel.disabled = false;
    if (!d.success) {
      if (sel) sel.innerHTML = '<option value="">Nenhum grupo encontrado</option>';
      if (status) status.textContent = '⚠ '+(d.error||'Erro ao buscar grupos');
      return;
    }
    if (sel) {
      sel.innerHTML = '<option value="">— Selecione o grupo —</option>';
      (d.grupos||[]).forEach(function(g){
        var o = document.createElement('option');
        o.value = 'group|'+g.id+'|'; o.textContent = g.nome||g.id;
        sel.appendChild(o);
      });
    }
    if (status) status.textContent = '✅ '+(d.grupos||[]).length+' grupo(s) encontrado(s)';
  } catch(e) {
    if (sel) { sel.disabled = false; sel.innerHTML = '<option value="">Erro de conexão</option>'; }
    if (status) status.textContent = '❌ '+e.message;
  }
}
async function s4LoadGroups() {
  var wpEl = document.getElementById('wWp');
  var wid = (wpEl ? wpEl.value : '') || W.wp_id || '';
  if (!wid && INSTANCES && INSTANCES.length > 0) wid = INSTANCES[0].id;
  var sel = document.getElementById('wGroup');
  var status = document.getElementById('s4GrpStatus');
  if (!wid) { if(status) status.textContent = '⚠ Selecione a instância WhatsApp primeiro.'; return; }
  if (sel) { sel.disabled = true; sel.innerHTML = '<option value="">⏳ Buscando...</option>'; }
  if (status) status.textContent = '⏳ Buscando grupos...';
  try {
    var r = await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wid));
    var d = await r.json();
    if (sel) sel.disabled = false;
    if (!d.success) {
      if (sel) sel.innerHTML = '<option value="">Nenhum grupo encontrado</option>';
      if (status) status.textContent = '⚠ '+(d.error||'Erro ao buscar grupos');
      return;
    }
    WP_GROUPS = (d.grupos||[]).map(function(g){ return {id:g.id, name:g.nome||g.name||g.id, instance:g.instance||g.instance_name||'', instance_name:g.instance_name||g.instance||''}; });
    if (sel) {
      sel.innerHTML = '<option value="">— Selecione o grupo —</option>';
      WP_GROUPS.forEach(function(g){
        var o = document.createElement('option');
        o.value = g.id; o.dataset.inst = g.instance_name||g.instance||''; o.textContent = g.name;
        // Auto-seleciona o grupo salvo no relatório
        if (W.group_id && g.id === W.group_id) {
          o.selected = true;
          W.group_inst = o.dataset.inst||'';
          W.group_name = g.name; // salva nome atualizado
        }
        sel.appendChild(o);
      });
      // Se não encontrou o grupo salvo na lista — adiciona como opção temporária
      if (W.group_id && sel.value !== W.group_id) {
        var o = document.createElement('option');
        o.value = W.group_id; o.selected = true;
        o.textContent = '✅ '+(W.group_name || W.group_id);
        sel.appendChild(o);
        sel.value = W.group_id;
      } else if(sel.value === W.group_id) {
        // grupo encontrado na lista - garante nome salvo
        var selOpt = sel.options[sel.selectedIndex];
        if(selOpt) W.group_name = selOpt.textContent.replace(/^✅ /,'');
      }
    }
    if (status) status.textContent = '✅ '+WP_GROUPS.length+' grupo(s) encontrado(s)';
    // Se estamos no step 4 editando um relatório com grupo, redesenha para selecionar o grupo
    if (W.step === 4 && W.recv_type === 'group') { draw(); }
  } catch(e) {
    if (sel) { sel.disabled = false; sel.innerHTML = '<option value="">Erro de conexão</option>'; }
    if (status) status.textContent = '❌ '+e.message;
  }
}
function pdfToggleDestType(v) {
  var cd = document.getElementById('pdfClientDiv');
  var gd = document.getElementById('pdfGroupDiv');
  if(cd) cd.style.display = v==='client' ? 'block' : 'none';
  if(gd) gd.style.display = v==='group'  ? 'block' : 'none';
}

async function generatePdfLink(id) {
  var fd = new FormData();
  fd.append('_token', CSRF_TOKEN);
  fd.append('report_id', id);
  // Envia o template selecionado para gerar o link com o PDF correto
  var tplSel = document.getElementById('pdfTplSelect');
  var tplId = tplSel ? (parseInt(tplSel.value)||0) : (_pdfSendTplId||0);
  if (tplId) fd.append('pdf_tpl_id', tplId);
  var el = document.getElementById('pdfLinkPreview');
  try {
    var resp = await fetch(APP_URL + '/reports/share-token', {method:'POST', body:fd, credentials:'include'});
    var raw = await resp.text();
    console.log('share-token status:', resp.status, 'body:', raw);
    var d;
    try { d = JSON.parse(raw); } catch(je) {
      if(el) el.innerHTML = '<span style="color:red;font-size:11px">Resposta inválida ('+resp.status+'): '+raw.substring(0,120)+'</span>';
      return;
    }
    if (d.success) {
      _currentPdfUrl = d.url;
      if (el) el.innerHTML = '<a href="'+d.url+'" target="_blank" style="color:var(--accent);word-break:break-all;font-size:12px">'+d.url+'</a>';
      var cp = document.getElementById('pdfCopyBtn');
      if(cp) cp.style.borderColor = 'var(--accent)';
    } else {
      if(el) el.innerHTML = '<span style="color:red;font-size:11px">❌ '+(d.error||'Erro desconhecido')+'</span>';
    }
  } catch(e) {
    console.error('share-token fetch error:', e);
    if(el) el.innerHTML = '<span style="color:red;font-size:11px">❌ Erro: '+e.message+'</span>';
  }
}

async function copyPdfLink() {
  if (!_currentPdfUrl) { alert('Aguarde o link ser gerado'); return; }
  try {
    await navigator.clipboard.writeText(_currentPdfUrl);
    var btn = document.getElementById('pdfCopyBtn');
    if(btn){ btn.textContent='✅ Copiado!'; setTimeout(function(){btn.textContent='📋 Copiar link';},2000); }
  } catch(e) { prompt('Copie o link:', _currentPdfUrl); }
}


async function doSendPdfLink() {
  var msg = (document.getElementById('pdfSendMsg')||{}).value || '';
  if (!msg)  { alert('Digite uma mensagem'); return; }
  if (!_currentPdfUrl) { alert('Aguarde o link ser gerado'); return; }

  // Detectar tipo de destino selecionado
  var typeEl = document.querySelector('input[name="pdfDestType"]:checked');
  var destType = typeEl ? typeEl.value : 'client';
  var destVal='', destInst='';
  if (destType === 'group') {
    var gSel = (document.getElementById('pdfSendGroup')||{}).value || '';
    if (!gSel) { alert('Selecione o grupo'); return; }
    var gParts = gSel.split('|');
    // formato: "group|JID|instance" — gParts[0]='group', gParts[1]=JID, gParts[2]=instance
    destType = 'group'; destVal = gParts[1]||gParts[0]; destInst = gParts[2]||'';
  } else {
    var cSel = (document.getElementById('pdfSendClient')||{}).value || '';
    if (!cSel) { alert('Selecione o cliente'); return; }
    var cParts = cSel.split('|');
    destType = 'phone'; destVal = cParts[1]||cParts[0]; destInst = '';
  }

  var finalMsg = msg.replace(/\{link\}/g, _currentPdfUrl);
  var instId = (document.getElementById('pdfSendInst')||{}).value || '';

  var btn = document.getElementById('pdfSendBtn');
  if (btn) { btn.disabled=true; btn.textContent='Enviando...'; }

  var fd = new FormData();
  fd.append('_token',          CSRF_TOKEN);
  fd.append('report_id',       _pdfSendId);
  fd.append('message',         finalMsg);
  fd.append('dest',            destVal);
  fd.append('dest_type',       destType);
  fd.append('whatsapp_id',     instId);
  fd.append('group_instance',  destInst);
  // Envia o template PDF selecionado
  var tplSelSend = document.getElementById('pdfTplSelect');
  var tplIdSend = tplSelSend ? (parseInt(tplSelSend.value)||0) : 0;
  if (tplIdSend) fd.append('pdf_tpl_id', tplIdSend);

  try {
    var r = await fetch(APP_URL + '/reports/send-pdf-link', {method:'POST', body:fd, credentials:'include'});
    var d = await r.json();
    var fb = document.getElementById('pdfSendFeedback');
    if (d.success) {
      if (fb) fb.innerHTML = '<span style="color:var(--success)">✅ Enviado com sucesso!</span>';
      if (btn) { btn.textContent='✅ Enviado!'; }
      setTimeout(function(){ document.getElementById('pdfSendOverlay').remove(); }, 2000);
    } else {
      if (fb) fb.innerHTML = '<span style="color:var(--danger)">❌ '+esc(d.error||'Erro ao enviar')+'</span>';
      if (btn) { btn.disabled=false; btn.textContent='📤 Tentar novamente'; }
    }
  } catch(e) {
    var fb = document.getElementById('pdfSendFeedback');
    if (fb) fb.innerHTML = '<span style="color:var(--danger)">❌ Erro de conexão</span>';
    if (btn) { btn.disabled=false; btn.textContent='📤 Enviar no WhatsApp'; }
  }
}

var CAMPAIGNS = <?=json_encode(array_values($campaigns??[]))?>;
var PERIODOS  = <?=json_encode($PERIODOS)?>;
var OBJS_MAP  = <?=json_encode($OBJS)?>;

// ===== TODAS AS VARIÁVEIS EM CATEGORIAS =====
var VAR_CATS = {
  'Gerais': [
    {l:'Nome do cliente',t:'{nome_cliente}',c:'blue'},{l:'Primeiro nome',t:'{primeiro_nome}',c:'blue'},{l:'Empresa do cliente',t:'{empresa}',c:'blue'},{l:'Período',t:'{periodo}',c:'blue'},
    {l:'Data de hoje',t:'{hoje}',c:'blue'},{l:'Conta de anúncio',t:'{conta_anuncio}',c:'blue'},{l:'Nome da campanha',t:'{campanha}',c:'blue'},
    {l:'Observações',t:'{observacoes}',c:'blue'},{l:'Saudação',t:'{saudacao}',c:'blue'},{l:'Saudação (EN)',t:'{greeting}',c:'blue'},
  ],
  'Cliques e Impressões': [
    {l:'Alcance',t:'{alcance}',c:'orange'},{l:'Impressões',t:'{impressoes}',c:'orange'},
    {l:'Cliques no link',t:'{cliques}',c:'orange'},{l:'Clique Todos',t:'{clicks_all}',c:'orange'},
    {l:'CTR',t:'{ctr}',c:'orange'},{l:'CPM',t:'{cpm}',c:'orange'},
    {l:'CPC',t:'{cpc}',c:'orange'},{l:'Frequência',t:'{frequencia}',c:'orange'},
    {l:'Pesquisa',t:'{search}',c:'orange'},{l:'Visitas ao perfil',t:'{profile_visit}',c:'orange'},
  ],
  'Conversões': [
    {l:'Conversões',t:'{conversoes}',c:'green'},{l:'Leads',t:'{leads}',c:'green'},
    {l:'Resultados',t:'{results}',c:'green'},{l:'ROAS',t:'{roas}',c:'green'},
    {l:'Receita',t:'{receita}',c:'green'},{l:'Vendas (Compras)',t:'{vendas}',c:'green'},
    {l:'Download',t:'{download}',c:'green'},{l:'Mensagens',t:'{msg}',c:'green'},
    {l:'Engajamento',t:'{engajamento}',c:'green'},{l:'App Install',t:'{app_install}',c:'green'},
    {l:'Faturado',t:'{fat}',c:'green'},{l:'Todos os leads',t:'{all_leads}',c:'green'},
  ],
  'Custos': [
    {l:'Investimento',t:'{investimento}',c:'orange'},{l:'CPL (Custo/Lead)',t:'{cpl}',c:'orange'},
    {l:'CPV (Custo/Compra)',t:'{cpv}',c:'orange'},{l:'Custo/Resultado',t:'{custo_result}',c:'orange'},
    {l:'Custo/Todos Leads',t:'{all_leads_cost}',c:'orange'},{l:'Custo/Mensagem',t:'{cmsg}',c:'orange'},
    {l:'Custo/Engajamento',t:'{engajamento_cost}',c:'orange'},{l:'Custo/Download',t:'{download_cost}',c:'orange'},
    {l:'CPM',t:'{cpm}',c:'orange'},{l:'CPC',t:'{cpc}',c:'orange'},
    {l:'Ticket Médio',t:'{tm}',c:'orange'},{l:'Custo/Visita ao perfil',t:'{custo_por_visita}',c:'orange'},
  ],
  'Engajamento': [
    {l:'Comentários',t:'{comment}',c:'pink'},{l:'Likes (Reações)',t:'{post_reaction}',c:'pink'},
    {l:'Salvamentos',t:'{post_save}',c:'pink'},{l:'Engajamento com página',t:'{page_engagement}',c:'pink'},
  ],
  'Vídeo': [
    {l:'Assistiu 25%',t:'{view_25}',c:'pink'},{l:'Assistiu 50%',t:'{view_50}',c:'pink'},
    {l:'Assistiu 75%',t:'{view_75}',c:'pink'},{l:'Assistiu 95%',t:'{view_95}',c:'pink'},
    {l:'Assistiu 100%',t:'{view_100}',c:'pink'},{l:'Thruplay',t:'{thruplay}',c:'pink'},
    {l:'Tempo médio assistido',t:'{v_avg}',c:'pink'},
  ],
  'Criativos': [
    {l:'Ranking TOP 1',t:'{top_1_creatives_ranking}',c:'pink'},
    {l:'Ranking TOP 3',t:'{top_3_creatives_ranking}',c:'pink'},
    {l:'Ranking TOP 5',t:'{top_5_creatives_ranking}',c:'pink'},
    {l:'Lista nomes criativos',t:'{all_creatives_simple}',c:'pink'},
  ],
};

// Monta HTML de uma campanha com adsets expansíveis usando DOM
function buildCampItem(camp, campIds, adsetIds){
  if(!camp || !camp.id) return '';
  var cid    = String(camp.id);
  var chk    = campIds.includes(cid);
  var st     = campStatus(camp.effective_status, camp.status);
  var adsets = camp.adsets || [];
  var name   = camp.name || '(sem nome)';
  var H      = '';

  var bc = chk ? 'var(--accent)'  : 'var(--border2)';
  var bg = chk ? 'var(--accent3)' : 'var(--bg3)';

  H += '<div id="camp_wrap_'+cid+'" style="border:1px solid '+bc+';border-radius:var(--radius);background:'+bg+'">';
  H += '<div style="display:flex;align-items:center;gap:8px;padding:7px 10px;min-height:36px">';
  H += '<input type="checkbox" value="'+cid+'" '+(chk?'checked':'')+' data-camp="'+cid+'" onchange="toggleCampCB(this)" style="accent-color:var(--accent);flex-shrink:0;width:14px;height:14px">';
  H += '<span style="flex:1;font-size:12px;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(name)+'">'+esc(name)+'</span>';


  if(adsets.length > 0){
    H += '<button type="button" id="adsets_btn_'+cid+'" data-cid="'+cid+'" onclick="toggleAdsets(this.dataset.cid)" style="flex-shrink:0;background:none;border:1px solid var(--border2);border-radius:4px;padding:2px 7px;font-size:10px;color:var(--txt2);cursor:pointer">&#9658; '+adsets.length+' conj.</button>';
  }

  var stBg    = st.bg    || 'rgba(128,128,128,.15)';
  var stColor = st.color || '#888';
  var stDot   = st.dot   || '○';
  var stLabel = st.label || (camp.effective_status||'?');

  H += '<span style="flex-shrink:0;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:'+stBg+';color:'+stColor+'">'+stDot+' '+stLabel+'</span>';
  var _aid=document.getElementById('wAcc')?document.getElementById('wAcc').value:'0';
  H += '<button type="button" class="btn-ai" onclick="rpOpen(\''+cid+'\',\''+_aid+'\',\''+esc(name)+'\')">✨ IA</button>';
  H += '</div>';

  if(adsets.length > 0){
    var nAtivas = adsets.filter(function(a){return a.effective_status==='ACTIVE';}).length;
    H += '<div id="adsets_'+cid+'" style="display:none;border-top:1px solid var(--border);padding:4px 0">';
    H += '<div style="display:flex;align-items:center;justify-content:space-between;padding:3px 10px 5px">';
    H += '<label style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--txt3);cursor:pointer">';
    H += '<input type="checkbox" data-selall="'+cid+'" onchange="toggleAllAdsetsCB(this)" style="accent-color:var(--accent)"> Selecionar todos</label>';
    H += '<span style="font-size:10px;color:var(--txt3)">'+nAtivas+' ativo(s) / '+adsets.length+'</span>';
    H += '</div>';
    adsets.forEach(function(as){
      var ast  = campStatus(as.effective_status, as.status);
      var achk = adsetIds.includes(as.id);
      H += '<label style="display:flex;align-items:center;gap:8px;padding:5px 10px;cursor:pointer">';
      H += '<input type="checkbox" data-adset="'+as.id+'" '+(achk?'checked':'')+' onchange="toggleAdsetCB(this)" style="accent-color:var(--accent)">';
      H += '<span style="flex:1;font-size:11px;color:var(--txt2)">'+esc(as.name)+'</span>';
      H += '<span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:10px;background:'+ast.bg+';color:'+ast.color+'">'+ast.dot+' '+ast.label+'</span>';
      H += '</label>';
    });
    H += '</div>';
  }

  H += '</div>';
  return H;
}


// ===== BUSCA DE CAMPANHA =====
function searchCamps(q){
  q = q.toLowerCase().trim();
  if(!W._campaigns) return;

  var source = W._filteredCamps || W._campaigns;
  var filtered = q
    ? source.filter(function(c){ return (c.name||'').toLowerCase().includes(q); })
    : source;

  var list = document.getElementById('campList');
  if(!list) return;
  list.innerHTML = filtered.map(function(camp){
    return buildCampItem(camp, W.camp_ids, W.adset_ids||[]);
  }).join('');
}

// ===== FILTROS DE CAMPANHA =====
var _campFilter = 'all'; // estado atual do filtro

function filterCamps(mode, btn){
  _campFilter = mode;

  // Atualiza visual dos botões
  ['fcAll','fcActive','fcLast5'].forEach(function(id){
    var b = document.getElementById(id);
    if(!b) return;
    if(b === btn || b.dataset.mode === mode){
      b.style.background = 'var(--accent)'; b.style.color = '#fff'; b.style.borderColor = 'var(--accent)';
    } else {
      b.style.background = 'var(--bg3)'; b.style.color = 'var(--txt2)'; b.style.borderColor = 'var(--border2)';
    }
  });

  if(!W._campaigns) return;

  var camps = W._campaigns;
  if(mode === 'active'){
    camps = W._campaigns.filter(function(c){ return c.effective_status === 'ACTIVE'; });
    // AUTO-SELECIONA ativas no camp_ids apenas no modo CRIAR
    // No modo EDITAR o usuário controla manualmente quais estão marcadas
    if(W._editId == null){
      camps.forEach(function(camp){
        if(!W.camp_ids.includes(camp.id)) W.camp_ids.push(camp.id);
      });
    }
  } else if(mode === 'last5'){
    // últimas 5 = primeiras 5 da lista (já ordenadas: ativas primeiro, depois por nome)
    camps = W._campaigns.slice(0, 5);
  }

  // Guarda o filtro ativo e redesenha o step
  W._campFilter = mode;
  W._filteredCamps = camps;
  var list = document.getElementById('campList');
  if(list){
    list.innerHTML = camps.map(function(camp){
      return buildCampItem(camp, W.camp_ids, W.adset_ids||[]);
    }).join('');
  }
}

function selectAllCamps(){
  if(!W._campaigns) return;
  var camps = _campFilter === 'active'
    ? W._campaigns.filter(function(c){ return c.effective_status === 'ACTIVE'; })
    : _campFilter === 'last5'
        ? W._campaigns.slice(0,5)
        : W._campaigns;
  camps.forEach(function(camp){
    if(!W.camp_ids.includes(camp.id)) W.camp_ids.push(camp.id);
    var cb = document.querySelector('[data-camp="'+camp.id+'"]');
    if(cb){ cb.checked = true; }
    var wrap = document.getElementById('camp_wrap_'+camp.id);
    if(wrap){ wrap.style.borderColor='var(--accent)'; wrap.style.background='var(--accent3)'; }
  });
}

function clearAllCamps(){
  W.camp_ids   = [];
  W.adset_ids  = [];
  document.querySelectorAll('[data-camp]').forEach(function(cb){
    cb.checked = false;
    var wrap = document.getElementById('camp_wrap_'+cb.dataset.camp);
    if(wrap){ wrap.style.borderColor='var(--border2)'; wrap.style.background='var(--bg3)'; }
  });
}

// Mapeia effective_status do Meta para label + cor
function campStatus(es,s){
  es=es||''; s=s||'';
  var MAP={
    'ACTIVE':               {label:'Ativo',          dot:'●', bg:'rgba(39,174,96,.15)',   color:'#27ae60'},
    'PAUSED':               {label:'Pausado',         dot:'⏸', bg:'rgba(128,128,128,.15)', color:'#888'},
    'CAMPAIGN_PAUSED':      {label:'Camp. Pausada',   dot:'⏸', bg:'rgba(243,156,18,.12)', color:'#f39c12'},
    'ADSET_PAUSED':         {label:'Conj. Pausado',   dot:'⏸', bg:'rgba(243,156,18,.12)', color:'#f39c12'},
    'ARCHIVED':             {label:'Arquivado',       dot:'▪', bg:'rgba(100,100,100,.1)',  color:'#666'},
    'DELETED':              {label:'Excluído',        dot:'✕', bg:'rgba(193,39,45,.12)',   color:'#c1272d'},
    'ENDED':                {label:'Finalizado',      dot:'⏹', bg:'rgba(100,100,100,.15)', color:'#666'},
    'PENDING_REVIEW':       {label:'Em Análise',      dot:'🔍', bg:'rgba(52,152,219,.12)', color:'#3498db'},
    'DISAPPROVED':          {label:'Reprovado',       dot:'✕', bg:'rgba(193,39,45,.15)',   color:'#c1272d'},
    'PREAPPROVED':          {label:'Pré-aprovado',    dot:'✓', bg:'rgba(39,174,96,.1)',    color:'#27ae60'},
    'PENDING_BILLING_INFO': {label:'Sem saldo',       dot:'💳', bg:'rgba(243,156,18,.15)', color:'#f39c12'},
    'WITH_ISSUES':          {label:'Com problemas',   dot:'⚠', bg:'rgba(243,156,18,.15)', color:'#f39c12'},
    'IN_PROCESS':           {label:'Processando',     dot:'⏳', bg:'rgba(52,152,219,.1)',  color:'#3498db'},
    'ERROR':                {label:'Erro',            dot:'✕', bg:'rgba(193,39,45,.15)',   color:'#c1272d'},
  };
  // Usa effective_status primeiro; se não mapeado, tenta status do usuário
  return MAP[es] || MAP[s] || {label:es||s||'?', dot:'○', bg:'rgba(128,128,128,.1)', color:'#888'};
}

// Toggle adsets panel
function toggleAdsets(campId){
  var el=document.getElementById('adsets_'+campId);
  if(!el) return;
  var open=el.style.display!=='none';
  el.style.display=open?'none':'block';
  var btn=document.getElementById('adsets_btn_'+campId);
  if(btn) btn.textContent=open?'▸ Conjuntos':'▾ Conjuntos';
}

// Seleciona/deseleciona todos os adsets de uma campanha


// Chamado ao trocar conta
function onAccChange(sel){
  W.acc_id   = sel.value;
  W.camp_ids = [];
  var o      = sel.options[sel.selectedIndex];
  W.acc_name    = o ? (o.dataset.name || '') : '';
  W.acc_meta_id = o ? (o.dataset.meta || '') : '';
  if(W.acc_id){
    loadCampaigns(W.acc_id);
  } else {
    W._campaigns   = null;
    W._campLoading = false;
    draw();
  }
}

// Load campanhas via API Meta
function loadCampaigns(accId){
  W._campLoading = true;
  W._campaigns   = null;
  draw();
  fetch('<?=APP_URL?>/api/accounts/campaigns?account_id='+encodeURIComponent(accId))
    .then(function(r){ return r.json(); })
    .then(function(d){
      W._campaigns   = d.campaigns || [];
      W._campLoading = false;
      var ativas = W._campaigns.filter(function(c){ return c.effective_status === 'ACTIVE'; });
      if(ativas.length > 0){
        _campFilter = 'active';
        W._filteredCamps = ativas;
        // Modo CRIAR: auto-seleciona ativas no camp_ids
        // Modo EDITAR: preserva camp_ids que vieram do banco — não sobrescreve
        if(W._editId == null){
          ativas.forEach(function(camp){
            if(!W.camp_ids.includes(camp.id)) W.camp_ids.push(camp.id);
          });
        }
      }
      if(W.step===2) draw();
    })
    .catch(function(e){
      console.error('[GestorPro] Erro campanhas:', e);
      W._campaigns   = [];
      W._campLoading = false;
      if(W.step===2) draw();
    });
}

var W={step:1,nome:'',plat:'meta',acc_id:'',acc_name:'',acc_meta_id:'',client_name:'',recv_client_id:'',pdf_tpl_id:0,
  _campaigns:null,_campLoading:false,adset_ids:[],
  obj:'todos',camp_ids:[],periodo:'last_7_days',custom_start:'',custom_end:'',msg:'',
  freq:'daily',time:'08:00',days:[1,2,3,4,5],
  recv_type:'phone',phone:'',client_id:'',group_id:'',group_inst:'',group_name:'',wp_id:'',
  _editId:null,_grpLoading:false};  // null = criar, number = editar

function defMsg(){
  return "Olá {primeiro_nome}! 👋\nSegue o relatório de performance das suas campanhas no período de *{periodo}*:\n\n📊 *Resumo de Resultados:*\n• 💰 Investimento: R$ {investimento}\n• 👥 Alcance: {alcance} pessoas\n• 👁 Impressões: {impressoes}\n• 🖱 Cliques: {cliques}\n• 📈 CTR: {ctr}%\n• 💵 CPM: R$ {cpm}\n• 💵 CPC: R$ {cpc}\n• ✅ Conversões: {conversoes}\n• 🚀 ROAS: {roas}x\n\n{observacoes}\nQualquer dúvida, estou à disposição! 🚀";
}

function openWizard(){
  W={step:1,nome:'',plat:'meta',acc_id:'',acc_name:'',acc_meta_id:'',client_name:'',recv_client_id:'',pdf_tpl_id:0,
    obj:'todos',camp_ids:[],adset_ids:[],
    _campaigns:null,_campLoading:false,
    periodo:'last_7_days',custom_start:'',custom_end:'',msg:defMsg(),
    freq:'daily',time:'08:00',days:[1,2,3,4,5],
    recv_type:'phone',phone:'',client_id:'',group_id:'',group_inst:'',group_name:'',wp_id:'',
    _editId:null,_grpLoading:false};
  draw();document.getElementById('mdWiz').style.display='flex';
  document.body.style.overflow='hidden';
}
function closeWizard(){document.getElementById('mdWiz').style.display='none';document.body.style.overflow='';}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeWizard();document.getElementById('mdEdit').style.display='none';document.getElementById('mdSend').style.display='none';}});

function gotoStep(n){if(n<W.step){W.step=n;draw();}}
function next(){
  if(W.step===1){var n=document.getElementById('wNome');if(!n||!n.value.trim()){alert('Digite um nome');return;}W.nome=n.value.trim();var cs=document.getElementById('wClienteS1');if(cs){W.client_id=cs.value;var co=cs.options[cs.selectedIndex];W.client_name=co?co.dataset.name||'':'';}}
  if(W.step===2){if(!W.acc_id){alert('Selecione uma conta de anúncio');return;}}
  if(W.step===3){var m=document.getElementById('wMsg');if(m)W.msg=m.value;var fu=document.getElementById('wFollowup');if(fu)W.followup=fu.value;var pt=document.getElementById('wPdfTpl');if(pt)W.pdf_tpl_id=parseInt(pt.value)||0;}
  if(W.step===4){
    collectS4();
    // Valida grupo selecionado
    if(W.recv_type==='group' && !W.group_id) {
      alert('Selecione um grupo WhatsApp antes de continuar.');
      return;
    }
  }
  if(W.step===5){submit();return;}
  W.step++;draw();
}
function prev(){if(W.step>1){W.step--;draw();}}

function draw(){
  var s=W.step;
  for(var i=1;i<=5;i++){
    var c=document.getElementById('wC'+i),l=document.getElementById('wL'+i);
    c.style.background=i<=s?'var(--accent)':'var(--bg4)';c.style.color=i<=s?'#fff':'var(--txt3)';
    l.style.color=i===s?'var(--txt)':'var(--txt3)';l.style.fontWeight=i===s?'700':'400';
  }
  document.getElementById('wPrev').style.display=s>1?'':'none';
  document.getElementById('wNext').textContent=s===5?(W._editId?'✓ Salvar alterações':'✓ Criar relatório'):'Próximo →';
  document.getElementById('wTitle').textContent=W._editId?'Editar relatório':'Criar relatório';
  document.getElementById('wSub').textContent=W.nome;
  var b='';
  if(s===1)b=s1();else if(s===2)b=s2();else if(s===3)b=s3();else if(s===4)b=s4();else b=s5();
  document.getElementById('wBody').innerHTML=b;
  if(s===1)setTimeout(function(){var n=document.getElementById('wNome');if(n)n.focus();},50);
  if(s===3)initVarPicker();
  if(s===4){
    initPeriodoDrop();
    // Auto-carrega grupos APÓS HTML estar no DOM (wWp já existe)
    if(W.recv_type==='group' && !W._grpLoading) {
      W._grpLoading=true;
      setTimeout(function(){
        s4LoadGroups();
      }, 100); // pequeno delay para garantir DOM pronto
    }
  }
}

/* ===== S1 ===== */
function s1(){
  var cliOpts='<option value="">— Nenhum (sem nome de cliente) —</option>';
  CLIENTS.forEach(function(c){
    var label = esc(c.name) + (c.company ? ' — ' + esc(c.company) : '');
    cliOpts+='<option value="'+c.id+'" data-name="'+esc(c.name)+'" '+(W.client_id==c.id?'selected':'')+'>'+label+'</option>';
  });
  return '<div class="form-group"><label class="form-label">Nome do relatório <span class="req">*</span></label>'+
    '<input type="text" id="wNome" class="form-control" value="'+esc(W.nome)+'" placeholder="Ex: Relatório semanal – Cliente X" style="font-size:15px;padding:14px"></div>'+
    '<div class="form-group" style="margin-top:16px">'+
    '<label class="form-label"><span class="material-icons-outlined" style="font-size:15px;vertical-align:middle;margin-right:4px">person</span> Cliente <span style="color:var(--txt3);font-weight:400">(opcional — preenche {nome_cliente} na mensagem)</span></label>'+
    '<select id="wClienteS1" class="form-control" style="font-size:14px" onchange="var o=this.options[this.selectedIndex];W.client_id=this.value;W.client_name=o.dataset.name||\'\';">'+cliOpts+'</select>'+
    (CLIENTS.length===0?'<div class="form-hint" style="color:var(--warn)">⚠ Nenhum cliente cadastrado. <a href="'+APP_URL+'/clients">Cadastrar cliente</a></div>':'<div class="form-hint">O nome do cliente será usado automaticamente onde você colocar {nome_cliente} na mensagem.</div>')+
    '</div>';
}

/* ===== S2: Canal + Conta + Objetivo + Campanha ===== */
function s2(){
  var cf=CONTAS.filter(function(c){return c.platform===W.plat;});
  var sel='<option value="">— Selecione a conta —</option>';
  cf.forEach(function(c){sel+='<option value="'+c.id+'" data-meta="'+esc(c.account_id||'')+'" data-name="'+esc(c.account_name)+'" '+(W.acc_id==c.id?'selected':'')+'>'+esc(c.account_name)+'</option>';});

  var objs=[{k:'todos',i:'🌐',l:'Todos'},{k:'reconhecimento',i:'👁',l:'Reconhecimento'},
    {k:'trafego',i:'🚦',l:'Tráfego'},{k:'mensagem',i:'💬',l:'Mensagem'},
    {k:'engajamento',i:'👍',l:'Engajamento'},{k:'turbinar',i:'⚡',l:'Turbinar'},
    {k:'leads',i:'🎯',l:'Leads'},{k:'vendas',i:'💰',l:'Vendas'},{k:'app',i:'📱',l:'App'}];
  var oc=objs.map(function(o){return '<div class="ocard '+(W.obj===o.k?'sel':'')+'" onclick="W.obj=\''+o.k+'\';document.querySelectorAll(\'.ocard\').forEach(e=>e.classList.remove(\'sel\'));this.classList.add(\'sel\')"><span style="font-size:18px">'+o.i+'</span>'+o.l+'</div>';}).join('');


  // Campanhas carregadas via API Meta (W._campaigns)
  var campHtml='';
  if(W.acc_id){
    if(W._campLoading){
      campHtml='<div class="form-group"><label class="form-label">Campanhas</label>'+
        '<div style="padding:12px;background:var(--bg3);border-radius:var(--radius);font-size:12px;color:var(--txt2)">'+
        '<span style="display:inline-block;width:12px;height:12px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle;margin-right:6px"></span>'+
        'Carregando campanhas da Meta API...</div></div>';
    } else if(W._campaigns && W._campaigns.length>0){
      var ativas=W._campaigns.filter(function(c){return c.effective_status==='ACTIVE';}).length;
      // Renderiza apenas as campanhas do filtro ativo (_campFilter)
      var _renderCamps = W._campaigns;
      if(_campFilter === 'active'){
        _renderCamps = W._campaigns.filter(function(c){ return c.effective_status === 'ACTIVE'; });
      } else if(_campFilter === 'last5'){
        _renderCamps = W._campaigns.slice(0,5);
      }
      var items=_renderCamps.map(function(camp){
        return buildCampItem(camp, W.camp_ids, W.adset_ids||[]);
      }).join('');
      // Botões de filtro rápido
      var btnStyle = 'padding:4px 10px;font-size:11px;font-weight:600;border-radius:20px;cursor:pointer;font-family:var(--font)';
      var btnOff   = btnStyle+';border:1px solid var(--border2);background:var(--bg3);color:var(--txt2)';
      var btnOn    = btnStyle+';border:1px solid var(--accent);background:var(--accent);color:#fff';
      // Botão ativo no filterBar reflete _campFilter atual
      var isActive = (_campFilter === 'active');
      var isLast5  = (_campFilter === 'last5');
      var filterBar =
        '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:8px">' +
          '<button type="button" id="fcAll"    data-mode="all"    onclick="filterCamps(this.dataset.mode,this)" style="'+(!isActive&&!isLast5?btnOn:btnOff)+'">Todas ('+W._campaigns.length+')</button>' +
          '<button type="button" id="fcActive" data-mode="active" onclick="filterCamps(this.dataset.mode,this)" style="'+(isActive?btnOn:btnOff)+'">&#9679; Ativas ('+ativas+')</button>' +
          '<button type="button" id="fcLast5"  data-mode="last5"  onclick="filterCamps(this.dataset.mode,this)" style="'+(isLast5?btnOn:btnOff)+'">&#9733; Últimas 5</button>' +
          '<button type="button" onclick="selectAllCamps()" style="'+btnOff+'">&#9745; Sel. todas</button>' +
          '<button type="button" onclick="clearAllCamps()"  style="'+btnOff+'">&#9746; Limpar</button>' +
        '</div>';

      var searchBox = '<div style="position:relative;margin-bottom:6px">'+
        '<input type="text" id="campSearch" oninput="searchCamps(this.value)" placeholder="🔍 Buscar campanha..." '+
        'style="width:100%;padding:7px 10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt);font-family:var(--font);font-size:12px;outline:none"></div>';

      campHtml='<div class="form-group"><label class="form-label" style="display:flex;align-items:center;justify-content:space-between">'+
        '<span>Campanhas <span style="font-size:11px;font-weight:400;color:var(--txt3)">(vazio = todas)</span></span>'+
        '<span><span style="font-size:11px;color:var(--success);font-weight:600">'+ativas+' ativa(s)</span>'+
        ' <span style="font-size:11px;color:var(--txt3)">/ '+W._campaigns.length+' total</span></span>'+
        '</label>'+
        filterBar+
        searchBox+
        '<div id="campList" style="display:flex;flex-direction:column;gap:3px;max-height:240px;overflow-y:auto;padding:3px;border:1px solid var(--border);border-radius:var(--radius)">'+items+'</div></div>';
    } else if(W._campaigns !== null){
      campHtml='<div class="form-hint" style="color:var(--txt2);margin-bottom:12px">Nenhuma campanha encontrada nesta conta.</div>';
    } else {
      campHtml='';
    }
  }


  return '<div class="form-group"><label class="form-label">Canal</label>'+
    '<div style="display:flex;gap:12px;margin-bottom:14px">'+
    '<div onclick="W.plat=\'meta\';draw()" style="flex:1;padding:14px;border:2px solid '+(W.plat==='meta'?'var(--accent)':'var(--border)')+';border-radius:var(--radius2);cursor:pointer;text-align:center;background:'+(W.plat==='meta'?'var(--accent3)':'var(--bg3)')+'">'+
    '<i class="fa-brands fa-facebook" style="font-size:26px;color:#1877F2;display:block;margin-bottom:4px"></i>'+
    '<div style="font-size:13px;font-weight:600;color:var(--txt)">Meta Ads</div>'+
    '<div style="font-size:10px;color:'+(W.plat==='meta'?'var(--accent)':'var(--txt3)')+'">'+(W.plat==='meta'?'✓ Selecionado':'Selecionar')+'</div></div>'+
    '<div onclick="W.plat=\'google\';draw()" style="flex:1;padding:14px;border:2px solid '+(W.plat==='google'?'var(--accent)':'var(--border)')+';border-radius:var(--radius2);cursor:pointer;text-align:center;background:'+(W.plat==='google'?'var(--accent3)':'var(--bg3)')+'">'+
    '<i class="fa-brands fa-google" style="font-size:26px;color:#EA4335;display:block;margin-bottom:4px"></i>'+
    '<div style="font-size:13px;font-weight:600;color:var(--txt)">Google Ads</div>'+
    '<div style="font-size:10px;color:'+(W.plat==='google'?'var(--accent)':'var(--txt3)')+'">'+(W.plat==='google'?'✓ Selecionado':'Selecionar')+'</div></div></div></div>'+
    '<div class="form-group"><label class="form-label">Conta de anúncio <span class="req">*</span></label>'+
    '<select id="wAcc" class="form-control" onchange="onAccChange(this)">'+sel+'</select>'+
    (CONTAS.length===0?'<div class="form-hint" style="color:var(--warn)">⚠ <a href="<?=APP_URL?>/accounts">Conectar conta</a></div>':'')+
    '</div>'+campHtml+
    '<div class="form-group"><label class="form-label">Objetivo</label><div style="display:flex;gap:8px;flex-wrap:wrap">'+oc+'</div></div>';
}
function toggleCamp(cb, campId){
  var val=cb.value;
  var wrap=cb.closest('div[style]');
  if(cb.checked){
    if(!W.camp_ids.includes(val)) W.camp_ids.push(val);
    if(wrap){wrap.style.background='var(--accent3)';wrap.style.borderColor='var(--accent)';}
  } else {
    W.camp_ids=W.camp_ids.filter(function(v){return v!==val;});
    if(wrap){wrap.style.background='var(--bg3)';wrap.style.borderColor='var(--border2)';}
    // remove adsets desta campanha
    if(campId && W.adset_ids){
      var camp=W._campaigns&&W._campaigns.find(function(c){return c.id===campId;});
      if(camp&&camp.adsets){
        var asIds=camp.adsets.map(function(a){return a.id;});
        W.adset_ids=W.adset_ids.filter(function(id){return !asIds.includes(id);});
      }
    }
  }
}
// Alias usado pelo onchange do checkbox gerado em buildCampItem
function toggleCampCB(cb){
  toggleCamp(cb, cb.value);
}
function toggleAdset(cb){
  var val=cb.value;
  if(!W.adset_ids) W.adset_ids=[];
  if(cb.checked){if(!W.adset_ids.includes(val))W.adset_ids.push(val);}
  else{W.adset_ids=W.adset_ids.filter(function(v){return v!==val;});}
}

/* ===== S3: Mensagem + Var Picker ===== */
function s3(){
  var tplOpts='<option value="">— Sem template —</option>';
  TEMPLATES.forEach(function(t){tplOpts+='<option value="'+esc(t.content)+'">'+esc(t.name)+'</option>';});
  var prev=renderPrev(W.msg);
  return '<div style="display:grid;grid-template-columns:1fr 280px;gap:16px;height:460px">'+
    '<div style="display:flex;flex-direction:column;gap:8px">'+
    '<div><label class="form-label">Template de mensagem</label><select class="form-control" onchange="if(this.value){document.getElementById(\'wMsg\').value=this.value;updPrev();}">'+tplOpts+'</select></div>'+
    '<div>'+
    '<label class="form-label" style="margin-top:8px">Template do PDF</label>'+
    (PDF_TEMPLATES && PDF_TEMPLATES.length > 0
      ? '<select id="wPdfTpl" class="form-control" onchange="W.pdf_tpl_id=parseInt(this.value)||0">'+
        '<option value="0"'+(W.pdf_tpl_id?'':' selected')+'>— Selecione um template —</option>'+
        PDF_TEMPLATES.map(function(t){return '<option value="'+t.id+'"'+(W.pdf_tpl_id&&W.pdf_tpl_id==t.id?' selected':'')+'>'+esc(t.name)+'</option>';}).join('')+
        '</select>'
      : '<div style="font-size:11px;color:var(--txt3);padding:6px 0">Nenhum template criado. <a href="'+APP_URL+'/reports/pdf-editor" target="_blank" style="color:var(--accent)">Criar agora →</a></div>'
    )+'</div>'+
    '<div class="form-group" style="margin-bottom:0"><label class="form-label">Inserir variável</label>'+
    '<div class="varpicker-wrap" id="vpWrap">'+
    '<button type="button" class="varpicker-btn" id="vpBtn"><span style="display:flex;align-items:center;gap:6px"><span class="material-icons-outlined" style="font-size:14px">add_circle_outline</span>Clique para inserir uma variável</span><span class="material-icons-outlined" style="font-size:14px;color:var(--txt3)">expand_more</span></button>'+
    '<div class="varpicker-panel" id="vpPanel">'+
    '<div class="varpicker-search"><input type="text" id="vpSearch" placeholder="🔍 Buscar variável..." oninput="vpRender()"></div>'+
    '<div class="varpicker-cats" id="vpCats"></div>'+
    '<div class="varpicker-list" id="vpList"></div>'+
    '</div></div></div>'+
    '<div style="flex:1;display:flex;flex-direction:column;gap:8px">'+
    '<div style="display:flex;flex-direction:column;flex:1"><label class="form-label">Mensagem</label>'+
    '<textarea id="wMsg" class="form-control" style="flex:1;font-size:12px;line-height:1.7;resize:none" oninput="updPrev()">'+esc(W.msg)+'</textarea></div>'+
    '<div style="display:flex;flex-direction:column">'+
    '<label class="form-label" style="display:flex;align-items:center;gap:6px">Mensagem complementar <span style="font-size:10px;font-weight:400;color:var(--txt3)">(opcional — enviada logo após a principal)</span></label>'+
    '<textarea id="wFollowup" class="form-control" style="font-size:12px;line-height:1.7;resize:vertical;min-height:70px" placeholder="Deixe vazio para não enviar mensagem adicional...">'+esc(W.followup||'')+'</textarea>'+
    '<div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap">'+
    '<span style="font-size:10px;color:var(--txt3)">Use Enter para pular linha · Variáveis disponíveis:</span>'+
    '<button type="button" onclick="insVarFollowup(\'{saudacao}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{saudacao}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{primeiro_nome}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{primeiro_nome}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{nome_completo}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{nome_completo}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{empresa}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{empresa}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{periodo}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{periodo}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{conta_anuncio}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{conta_anuncio}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{investimento}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{investimento}</button>'+
    '<button type="button" onclick="insVarFollowup(\'{msg}\')" style="font-size:10px;padding:1px 6px;border-radius:4px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);cursor:pointer">{msg}</button>'+
    '</div>'+
    '</div>'+
    '</div>'+
    '</div>'+
    '<div style="display:flex;flex-direction:column;gap:6px">'+
    '<div style="font-size:12px;font-weight:600;color:var(--txt)">Preview WhatsApp</div>'+
    '<div id="wPrev" style="background:#128C7E;border-radius:12px 12px 12px 0;padding:14px;color:#fff;font-size:12px;line-height:1.7;flex:1;overflow-y:auto">'+prev+'</div>'+
    '</div></div>';
}

var vpActiveCat='Todas';
function initVarPicker(){
  var btn=document.getElementById('vpBtn');
  var panel=document.getElementById('vpPanel');
  if(!btn||!panel)return;
  btn.addEventListener('click',function(e){e.stopPropagation();panel.classList.toggle('open');if(panel.classList.contains('open'))vpBuild();});
  document.addEventListener('click',function(e){if(panel&&!panel.contains(e.target)&&e.target!==btn)panel.classList.remove('open');});
  vpBuild();
}
function vpBuild(){
  vpActiveCat='Todas';
  var catsEl=document.getElementById('vpCats');if(!catsEl)return;
  catsEl.innerHTML='';
  var cats=['Todas'].concat(Object.keys(VAR_CATS));
  cats.forEach(function(c){
    var b=document.createElement('button');b.type='button';b.className='vcat-btn'+(c==='Todas'?' active':'');b.textContent=c;
    b.onclick=function(){vpActiveCat=c;document.querySelectorAll('.vcat-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');vpRender();};
    catsEl.appendChild(b);
  });
  vpRender();
}
function vpRender(){
  var q=(document.getElementById('vpSearch')||{}).value||'';
  var list=document.getElementById('vpList');if(!list)return;
  list.innerHTML='';
  var cats=vpActiveCat==='Todas'?Object.keys(VAR_CATS):[vpActiveCat];
  var found=0;
  cats.forEach(function(cat){
    var items=(VAR_CATS[cat]||[]).filter(function(v){return !q||v.l.toLowerCase().includes(q.toLowerCase())||v.t.toLowerCase().includes(q.toLowerCase());});
    if(!items.length)return;
    var sec=document.createElement('span');sec.className='vsec-title';sec.textContent=cat;list.appendChild(sec);
    var wrap=document.createElement('div');
    items.forEach(function(v){
      var tag=document.createElement('span');tag.className='vtag '+(v.c||'');tag.title=v.l;tag.textContent=v.t;
      tag.onclick=function(){insVar(v.t);document.getElementById('vpPanel').classList.remove('open');};
      wrap.appendChild(tag);found++;
    });
    list.appendChild(wrap);
  });
  if(!found){var em=document.createElement('div');em.style.cssText='padding:20px;text-align:center;font-size:12px;color:var(--txt3)';em.textContent='Nenhuma variável encontrada';list.appendChild(em);}
}

function renderPrev(t){
  return (t||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\n/g,'<br>')
    .replace(/(\{[\w_]+\}|&lt;\w+&gt;)/g,'<strong style="background:rgba(255,255,255,.2);border-radius:3px;padding:0 2px">$1</strong>')
    .replace(/\*([^*]+)\*/g,'<strong>$1</strong>');
}
function updPrev(){var ta=document.getElementById('wMsg');var pr=document.getElementById('wPrev');if(ta&&pr)pr.innerHTML=renderPrev(ta.value);}
function insVar(tag){var ta=document.getElementById('wMsg');if(!ta)return;var s=ta.selectionStart,e=ta.selectionEnd;ta.value=ta.value.substring(0,s)+tag+ta.value.substring(e);ta.focus();ta.selectionStart=ta.selectionEnd=s+tag.length;updPrev();}
function insVarFollowup(tag){var ta=document.getElementById('wFollowup');if(!ta)return;var s=ta.selectionStart,e=ta.selectionEnd;ta.value=ta.value.substring(0,s)+tag+ta.value.substring(e);ta.focus();ta.selectionStart=ta.selectionEnd=s+tag.length;}

/* ===== S4: Programação ===== */
function s4(){
  var wpOpts='<option value="">— Selecione WhatsApp —</option>';
  var defaultWpId = INSTANCES.length ? INSTANCES[0].id : null;
  INSTANCES.forEach(function(w){
    var isSel = W.wp_id==w.id || (!W.wp_id && w.id==defaultWpId);
    wpOpts+='<option value="'+w.id+'" '+(isSel?'selected':'')+'>'+esc(w.instance_name)+(w.phone_number?' ('+w.phone_number+')':'')+'</option>';
  });
  var cliOpts='<option value="">— Selecione o cliente —</option>';
  CLIENTS.forEach(function(c){var lbl=esc(c.name)+(c.company?' — '+esc(c.company):'')+(c.phone?' ('+c.phone+')':'');cliOpts+='<option value="'+c.id+'" data-phone="'+esc(c.phone||'')+'" '+(W.client_id==c.id?'selected':'')+'>'+lbl+'</option>';});
  // Monta options do grupo — se já tem grupo salvo E lista vazia, pré-seleciona o salvo
  var grpOpts='<option value="">— Selecione o grupo —</option>';
  if(WP_GROUPS.length > 0) {
    WP_GROUPS.forEach(function(g){
      var inst=g.instance||g.instance_name||'';
      grpOpts+='<option value="'+esc(g.id)+'" data-inst="'+esc(inst)+'" '+(W.group_id===g.id?'selected':'')+'>'+esc(g.name)+(inst?' ['+esc(inst)+']':'')+'</option>';
    });
  } else if(W.group_id && W.group_name) {
    // Grupo salvo mas lista ainda não carregada — mostra o salvo como opção selecionada
    grpOpts = '<option value="'+esc(W.group_id)+'" selected>✅ '+esc(W.group_name)+'</option>';
  }
  var dias=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
  var dBtns=dias.map(function(d,i){return '<button type="button" class="day-btn '+(W.days.includes(i)?'active':'')+'" data-day="'+i+'" onclick="toggleDay('+i+',this)">'+d+'</button>';}).join('');
  var recvHtml='';
  if(W.recv_type==='phone') {
    recvHtml='<input type="text" id="wPhone" class="form-control" value="'+esc(W.phone)+'" placeholder="5511999999999"><div class="form-hint">DDI+DDD+número. Ex: 5511999999999</div>';
  } else if(W.recv_type==='client') {
    recvHtml='<select id="wClient" class="form-control" onchange="W.client_id=this.value;var o=this.options[this.selectedIndex];W.phone=o.dataset.phone||\'\'">'+cliOpts+'</select><div class="form-hint">Usa o telefone do cliente cadastrado</div>';
  } else if(W.recv_type==='group') {
    // grpOpts já foi construído acima com o grupo salvo pré-selecionado (se houver)
    recvHtml='<div style="display:flex;gap:6px;align-items:center;margin-bottom:4px"><select id="wGroup" class="form-control" style="flex:1" onchange="W.group_id=this.value;var o=this.options[this.selectedIndex];W.group_inst=o.dataset.inst||\'\'\';W.group_name=o.textContent.replace(/^✅ /,\'\'\');">'+grpOpts+'</select><button type="button" class="btn btn-secondary btn-sm" onclick="s4LoadGroups()" style="white-space:nowrap;flex-shrink:0">🔄 Buscar grupos</button></div><div id="s4GrpStatus" style="font-size:11px;color:var(--txt3)">'+(WP_GROUPS.length?'✅ '+WP_GROUPS.length+' grupo(s) carregado(s)':(W.group_id&&W.group_name?'✅ Grupo salvo: '+esc(W.group_name)+' — clique 🔄 para atualizar lista':'⚠ Clique em 🔄 Buscar grupos para carregar'))+'</div>';
  }

  return '<div style="display:flex;flex-direction:column;gap:16px">'+
    '<div class="form-group"><label class="form-label">WhatsApp para enviar</label>'+
    '<select id="wWp" class="form-control" onchange="W.wp_id=this.value">'+wpOpts+'</select>'+
    (INSTANCES.length===0?'<div class="form-hint" style="color:var(--warn)">⚠ <a href="<?=APP_URL?>/whatsapp">Conectar WhatsApp</a></div>':'')+'</div>'+
    '<div class="form-group"><label class="form-label">Recebedor do relatório</label>'+
    '<div style="display:flex;gap:8px;margin-bottom:10px">'+
    '<button type="button" class="recv-tab '+(W.recv_type==='phone'?'active':'')+'" onclick="W.recv_type=\'phone\';draw()"><span class="material-icons-outlined" style="font-size:14px">smartphone</span> Número</button>'+
    '<button type="button" class="recv-tab '+(W.recv_type==='client'?'active':'')+'" onclick="W.recv_type=\'client\';draw()"><span class="material-icons-outlined" style="font-size:14px">person</span> Cliente</button>'+
    '<button type="button" class="recv-tab '+(W.recv_type==='group'?'active':'')+'" onclick="W.recv_type=\'group\';W._grpLoading=false;draw();"><i class="fa-brands fa-whatsapp"></i> Grupo WA</button>'+
    '</div>'+recvHtml+'</div>'+
    '<div class="form-group"><label class="form-label">Período de envio</label>'+
    '<div class="periodo-select-wrap">'+
    '<button type="button" class="periodo-select-btn" id="periodoBtn" onclick="togglePeriodoDrop()">'+
    '<span id="periodoLabel">'+getPeriodoLabel()+'</span>'+
    '<span class="material-icons-outlined" style="font-size:16px;color:var(--txt3)">expand_more</span></button>'+
    '<div class="periodo-dropdown" id="periodoDrop">'+
    Object.entries(PERIODOS).map(function(kv){var k=kv[0],v=kv[1];var isAct=W.periodo===k||(k==='custom'&&W.periodo==='custom');return '<div class="periodo-opt '+(isAct?'active':'')+'" onclick="selectPeriodo(this)" data-key="'+esc(k)+'">'+esc(v)+'</div>';}).join('')+
    '</div></div>'+
    (W.periodo==='custom'?
      '<div id="customDateWrap" style="display:flex;gap:8px;margin-top:8px;align-items:center">'+
        '<div style="flex:1">'+
          '<label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">De</label>'+
          '<input type="date" id="wCustomStart" class="form-control" style="font-size:13px" value="'+esc(W.custom_start)+'" onchange="W.custom_start=this.value;updCustomLabel()">'+
        '</div>'+
        '<div style="padding-top:20px;color:var(--txt3)">→</div>'+
        '<div style="flex:1">'+
          '<label style="font-size:11px;color:var(--txt3);display:block;margin-bottom:3px">Até</label>'+
          '<input type="date" id="wCustomEnd" class="form-control" style="font-size:13px" value="'+esc(W.custom_end)+'" onchange="W.custom_end=this.value;updCustomLabel()">'+
        '</div>'+
      '</div>'
    :'')+
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'+
    '<div class="form-group"><label class="form-label">Frequência</label>'+
    '<select id="wFreq" class="form-control" onchange="W.freq=this.value">'+
    '<option value="once" '+(W.freq==='once'?'selected':'')+'>Uma vez</option>'+
    '<option value="daily" '+(W.freq==='daily'?'selected':'')+'>Diário</option>'+
    '<option value="weekly" '+(W.freq==='weekly'?'selected':'')+'>Semanal</option>'+
    '<option value="monthly" '+(W.freq==='monthly'?'selected':'')+'>Mensal</option>'+
    '</select></div>'+
    '<div class="form-group"><label class="form-label">Horário</label>'+
    '<input type="time" id="wTime" class="form-control" value="'+W.time+'" onchange="W.time=this.value"></div>'+
    '</div>'+
    '<div class="form-group"><label class="form-label">Dias da semana</label>'+
    '<div style="display:flex;gap:6px;flex-wrap:wrap">'+dBtns+'</div></div>'+
    
    '</div>';
}
function initPeriodoDrop(){
  document.addEventListener('click',function(e){
    var d=document.getElementById('periodoDrop');var b=document.getElementById('periodoBtn');
    if(d&&b&&!d.contains(e.target)&&e.target!==b&&!b.contains(e.target))d.classList.remove('open');
  },{once:false});
}
function togglePeriodoDrop(){var d=document.getElementById('periodoDrop');if(d)d.classList.toggle('open');}
function selectPeriodo(el){
  var key=el.dataset.key;
  W.periodo=key;
  if(key!=='custom'){W.custom_start='';W.custom_end='';}
  document.querySelectorAll('.periodo-opt').forEach(function(x){x.classList.remove('active');});
  el.classList.add('active');
  var drop=document.getElementById('periodoDrop');
  if(drop)drop.classList.remove('open');
  var lbl=document.getElementById('periodoLabel');
  if(lbl)lbl.textContent=getPeriodoLabel();
  // Re-render to show/hide custom date inputs
  draw();
}
function getPeriodoLabel(){
  if(W.periodo==='custom'&&W.custom_start&&W.custom_end){
    var f=function(d){var p=d.split('-');return p[2]+'/'+p[1]+'/'+p[0];};
    return f(W.custom_start)+' → '+f(W.custom_end);
  }
  return PERIODOS[W.periodo]||W.periodo||'Selecione...';
}
function updCustomLabel(){
  var lbl=document.getElementById('periodoLabel');
  if(lbl)lbl.textContent=getPeriodoLabel();
}
function toggleDay(i,el){var idx=W.days.indexOf(i);if(idx>-1)W.days.splice(idx,1);else W.days.push(i);el.classList.toggle('active');}
function toggleEditCustom(val,s,e){
  var wrap=document.getElementById('eCustomWrap');
  if(!wrap)return;
  wrap.style.display=val==='custom'?'flex':'none';
  if(val==='custom'){
    var si=document.getElementById('eCustomStart');var ei=document.getElementById('eCustomEnd');
    if(si&&s!==undefined)si.value=s||''; if(ei&&e!==undefined)ei.value=e||'';
  }
}
function collectS4(){
  var p=document.getElementById('wPhone');if(p)W.phone=p.value;
  var cl=document.getElementById('wClient');if(cl){W.recv_client_id=cl.value;var op=cl.options[cl.selectedIndex];if(op)W.phone=op.dataset.phone||'';W.client_id=W.recv_client_id||W.client_id;}
  var gr=document.getElementById('wGroup');if(gr){var og=gr.options[gr.selectedIndex];if(gr.value){W.group_id=gr.value;if(og){W.group_inst=og.dataset.inst||'';W.group_name=og.textContent.replace(/^✅ /,'');}}}
  var w=document.getElementById('wWp');if(w)W.wp_id=w.value;
  var f=document.getElementById('wFreq');if(f)W.freq=f.value;
  var t=document.getElementById('wTime');if(t)W.time=t.value;
  var m=document.getElementById('wMsg');if(m)W.msg=m.value;
  var fu=document.getElementById('wFollowup');if(fu)W.followup=fu.value;
  var pt=document.getElementById('wPdfTpl');if(pt)W.pdf_tpl_id=parseInt(pt.value)||0;
}

/* ===== S5 ===== */
function s5(){
  var dias=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
  var recv=W.recv_type==='group'?'Grupo WA':W.phone||'—';
  var rows=[['Nome',W.nome],['Cliente',W.client_name||'—'],['Canal',W.plat==='meta'?'Meta Ads':'Google Ads'],
    ['Conta',W.acc_name],['Objetivo',OBJS_MAP[W.obj]||W.obj],
    ['Campanhas',(function(){
      if(!W.camp_ids.length) return 'Todas';
      if(W._campaigns && W._campaigns.length){
        var names=W.camp_ids.map(function(cid){
          var c=W._campaigns.find(function(x){return x.id===cid;});
          return c?c.name:cid;
        });
        return names.join(' | ');
      }
      // Fallback: usar camp_labels já salvo
      if(W.camp_labels) return W.camp_labels;
      return W.camp_ids.length+' selecionada(s)';
    })()],
    ['Período',getPeriodoLabel()],
    ['Msg. complementar',W.followup ? W.followup.substring(0,60)+(W.followup.length>60?'...':'') : '(não enviada)'],
    ['Frequência',{once:'Uma vez',daily:'Diário',weekly:'Semanal',monthly:'Mensal'}[W.freq]],
    ['Horário',W.time],['Dias',W.days.map(function(d){return dias[d];}).join(', ')],
    ['Recebedor',recv]];
  // Template PDF já selecionado no step 3 — só mostra o nome escolhido no resumo
  var tplNomeSelecionado = '';
  if(W.pdf_tpl_id && PDF_TEMPLATES){
    var tplObj = W.pdf_tpl_id ? PDF_TEMPLATES.find(function(t){return t.id==W.pdf_tpl_id;}) : null;
    if(tplObj) tplNomeSelecionado = tplObj.name;
  }
  var tplSelectHtml = tplNomeSelecionado
    ? '<div style="margin-bottom:12px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);border:1px solid var(--border);font-size:13px">'+
      '<span style="color:var(--txt2)">🎨 Template PDF:</span> <strong style="color:var(--txt)">'+esc(tplNomeSelecionado)+'</strong></div>'
    : '<div style="margin-bottom:12px;padding:10px 12px;background:var(--bg3);border-radius:var(--radius);border:1px solid var(--border);font-size:12px;color:var(--txt3)">🎨 Template PDF: <em>Nenhum selecionado</em></div>';

  return '<div style="text-align:center;padding:10px 0 16px"><div style="font-size:32px;margin-bottom:6px">✅</div>'+
    '<div style="font-size:17px;font-weight:700;color:var(--txt)">'+esc(W.nome)+'</div>'+
    '<div style="font-size:12px;color:var(--txt2);margin-top:3px">Revise antes de criar</div></div>'+
    tplSelectHtml+
    '<div style="background:var(--bg3);border-radius:var(--radius);padding:14px">'+
    rows.map(function(r){return '<div style="display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px solid var(--border)"><span style="color:var(--txt2)">'+r[0]+'</span><span style="color:var(--txt);font-weight:600;max-width:60%;text-align:right">'+esc(String(r[1]))+'</span></div>';}).join('')+'</div>';
}

function submit(){
  var isEdit = W._editId != null;

  // Feedback visual no botão
  var btn = document.getElementById('wNext');
  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px">' +
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:wSpin .7s linear infinite">' +
    '<path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>' +
    (isEdit ? 'Salvando...' : 'Criando...') + '</span>';

  // Adiciona keyframe de rotação se ainda não existir
  if (!document.getElementById('wSpinStyle')) {
    var st = document.createElement('style');
    st.id = 'wSpinStyle';
    st.textContent = '@keyframes wSpin{to{transform:rotate(360deg)}}';
    document.head.appendChild(st);
  }

  var form=document.createElement('form');
  form.method='POST';
  form.action=isEdit?'<?=APP_URL?>/reports/update':'<?=APP_URL?>/reports/store';
  // Coleta nomes das campanhas selecionadas
  var _campLabels=[];
  if(W._campaigns && W.camp_ids.length){
    W.camp_ids.forEach(function(cid){
      var c=W._campaigns.find(function(x){return x.id===cid;});
      if(c && c.name) _campLabels.push(c.name);
    });
  }
  var f={'_token':'<?=e($_SESSION['csrf_token'])?>','title':W.nome,'platform':W.plat,
    'ad_account_id':W.acc_id,'objetivo':W.obj,'camp_ids':W.camp_ids.join(','),'camp_labels':_campLabels.join(' | '),
    'adset_ids':(W.adset_ids||[]).join(','),
    'period_type':(W.periodo==='custom'?'custom|'+(W.custom_start||'')+'|'+(W.custom_end||''):W.periodo),'message_text':W.msg,'followup_message':W.followup||'','frequency':W.freq,
    'send_time':W.time,'send_days':W.days.join(','),'recipient_phone':W.phone,
    'whatsapp_id':W.wp_id,'client_id':W.client_id,'group_id':W.group_id,
    'group_instance':W.group_inst,'group_name':W.group_name||'','recv_type':W.recv_type,
    'pdf_tpl_id':W.pdf_tpl_id||0};
  if(isEdit) f['id'] = W._editId;
  Object.entries(f).forEach(function(e){var i=document.createElement('input');i.type='hidden';i.name=e[0];i.value=e[1];form.appendChild(i);});
  document.body.appendChild(form);form.submit();
}

function editRel(r){
  // Abre o wizard populado com os dados do relatório existente
  var pt = r.period_type||'last_7_days';
  var cs='', ce='';
  if(pt.startsWith('custom|')){var pp=pt.split('|');cs=pp[1]||'';ce=pp[2]||'';pt='custom';}

  // Descobre acc_id e acc_meta_id a partir de CONTAS
  var accObj = CONTAS.find(function(c){ return c.id == r.ad_account_id; }) || {};

  // camp_ids: string salva no banco → array
  var cids = r.camp_ids ? r.camp_ids.split(',').filter(Boolean) : [];

  // Dias da semana: string → array de números
  var dias = (r.send_days||'1,2,3,4,5').split(',').map(Number);

  W = {
    step: 2,
    _editId: r.id,           // modo edição — abre direto no step 2 (Canal)
    nome: r.title||'',
    plat: r.platform||'meta',
    acc_id:      String(accObj.id||r.ad_account_id||''),
    acc_name:    accObj.account_name||'',
    acc_meta_id: accObj.account_id||'',
    obj:         r.objetivo||'todos',
    camp_ids:    cids,
    camp_labels: r.camp_labels||'',
    followup:    r.followup_message||'',
    adset_ids:   [],
    _campaigns:  null,
    _campLoading: false,
    periodo:     pt,
    custom_start: cs,
    custom_end:   ce,
    msg:         r.message_text||defMsg(),
    freq:        r.frequency||'daily',
    time:        r.send_time||'08:00',
    days:        dias,
    recv_type:   r.recv_type||'phone',
    // Se recv_type é grupo, o recipient_phone armazena o JID — não usar como telefone
    phone:       (r.recv_type==='group'?'':(r.recv_type==='client'?'':r.recipient_phone||'')),
    client_id:   r.client_id||'',
    client_name: (function(){var cl=CLIENTS.find(function(c){return c.id==r.client_id;});return cl?cl.name:'';})(),
    group_id:    r.group_id||(r.recv_type==='group'?r.recipient_phone||'':''),
    group_inst:  r.group_instance||'',
    // Limpa placeholder caso tenha sido salvo por engano
    group_name:  (function(){var gn=r.group_name||''; return (gn==='— Selecione o grupo —'||gn==='— Selecione —')?'':gn;})(),
    _had_group:  (r.recv_type==='group'), // flag para auto-carregar grupos no edit
    wp_id:       r.whatsapp_id||'',
    pdf_tpl_id:  parseInt(r.pdf_tpl_id)||0
  };

  // Carrega campanhas da conta (para mostrar seleção correta no step 2)
  if(W.acc_id) loadCampaigns(W.acc_id);

  // Se é relatório com grupo, vai direto pro step 4 e carrega grupos automaticamente
  if(W.recv_type === 'group') {
    W.step = 4;
    W._grpLoading = false; // permite auto-load no draw()
    // Sempre carrega a lista de grupos para o usuário poder selecionar/confirmar
    // (mesmo que já tenha nome salvo, carrega para mostrar no select)
    setTimeout(function(){ s4LoadGroups(); }, 200);
    W._grpLoading = true; // marca como já iniciado para não duplicar
  }

  draw();
  document.getElementById('mdWiz').style.display='flex';
  document.body.style.overflow='hidden';
}

function sendNow(id,phone,recvType){
  document.getElementById('sndId').value=id;
  var isGroup = (recvType==='group');
  var phoneRow = document.getElementById('sndPhoneRow');
  var phoneInput = document.getElementById('sndPhone');
  var groupRow = document.getElementById('sndGroupRow');
  var groupName = document.getElementById('sndGroupName');
  if(isGroup){
    phoneInput.value='__grupo__';
    if(phoneRow) phoneRow.style.display='none';
    if(groupRow) groupRow.style.display='';
    // Busca nome do grupo pelo id do relatório
    if(groupName) {
      var rData = window._REPORTS_DATA ? window._REPORTS_DATA[id] : null;
      groupName.textContent = rData ? (rData.group_name || rData.recipient_phone || '—') : phone || '—';
    }
  } else {
    phoneInput.value=phone||'';
    if(phoneRow) phoneRow.style.display='';
    if(groupRow) groupRow.style.display='none';
  }
  // reseta estado do modal
  var res=document.getElementById('sndResult');
  res.style.display='none'; res.innerHTML='';
  var btn=document.getElementById('btnSend');
  btn.disabled=false;
  btn.style.background='';
  btn.innerHTML='<i class="fa-brands fa-whatsapp"></i> Enviar';
  document.getElementById('btnCancelarSend').textContent='Cancelar';
  document.getElementById('mdSend').style.display='flex';
}

// Submit AJAX com animação
document.getElementById('fSend').addEventListener('submit', function(e){
  e.preventDefault();
  var btn  = document.getElementById('btnSend');
  var res  = document.getElementById('sndResult');
  var phone= document.getElementById('sndPhone').value.trim();
  var id   = document.getElementById('sndId').value;
  var token= document.querySelector('#fSend [name="_token"]').value;

  if(!phone && phone!=='__grupo__'){ alert('Informe o telefone.'); return; }

  // ── Animação "Enviando..." ──
  btn.disabled = true;
  btn.innerHTML =
    '<span style="display:inline-flex;align-items:center;gap:6px">'
    +'<span class="snd-dots-wrap"><span class="snd-dot"></span><span class="snd-dot"></span><span class="snd-dot"></span></span>'
    +' Enviando</span>';
  res.style.display='none';

  fetch(APP_URL+'/reports/send', {
    method:'POST',
    headers:{
      'Content-Type':'application/x-www-form-urlencoded',
      'X-Requested-With':'XMLHttpRequest'
    },
    body: '_token='+encodeURIComponent(token)
         +'&report_id='+encodeURIComponent(id)
         +'&phone='+encodeURIComponent(phone)
         +'&_ajax=1'
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    if(data.success){
      // ── Animação "Enviado!" ──
      btn.innerHTML =
        '<span style="display:inline-flex;align-items:center;gap:6px">'
        +'<i class="fa-solid fa-check" style="animation:sndPop .3s ease"></i>'
        +' Enviado!</span>';
      btn.style.background='var(--success)';
      res.style.cssText='display:flex;background:#e8faf0;border:1px solid #a3d9b1;color:#1a6b3a;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
      res.innerHTML='<i class="fa-solid fa-circle-check" style="font-size:16px;color:#28a745"></i> Relatório enviado com sucesso!';
      document.getElementById('btnCancelarSend').textContent='Fechar';
      setTimeout(function(){
        document.getElementById('mdSend').style.display='none';
        btn.style.background='';
        location.reload();
      }, 2200);
    } else {
      btn.disabled=false;
      btn.innerHTML='<i class="fa-brands fa-whatsapp"></i> Tentar novamente';
      res.style.cssText='display:flex;background:#fdecea;border:1px solid #f5c2c7;color:#842029;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
      res.innerHTML='<i class="fa-solid fa-triangle-exclamation" style="font-size:16px"></i> '+(data&&data.error?data.error:'Erro ao enviar. Tente novamente.');
    }
  })
  .catch(function(){
    btn.disabled=false;
    btn.innerHTML='<i class="fa-brands fa-whatsapp"></i> Tentar novamente';
    res.style.cssText='display:flex;background:#fdecea;border:1px solid #f5c2c7;color:#842029;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
    res.innerHTML='<i class="fa-solid fa-triangle-exclamation" style="font-size:16px"></i> Erro de comunicação com o servidor.';
  });
});
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

// ── Auto-fix sticky column left positions ──────────────────────────────────
function fixStickyColumns(){
  var ids=['thChk','thSt','thData','thNome'];
  var acc=0;
  ids.forEach(function(id,i){
    var th=document.getElementById(id);
    if(!th) return;
    th.style.left=acc+'px';
    var w=th.getBoundingClientRect().width;
    // Apply same left to all tbody tds with data-sticky=i
    document.querySelectorAll('td[data-sticky="'+i+'"]').forEach(function(td){
      td.style.left=acc+'px';
    });
    acc+=Math.ceil(w);
  });
}
document.addEventListener('DOMContentLoaded', fixStickyColumns);
window.addEventListener('resize', fixStickyColumns);
setTimeout(fixStickyColumns, 100);

</script>
<!-- POPUP IA campanha (reports) -->
<div class="rp-overlay" id="rpPop" onclick="if(event.target===this)rpClose()">
  <div class="rp-box">
    <div class="rp-hdr">
      <div class="rp-title"><span style="color:var(--accent)">✨</span><span id="rpTitle">Análise IA</span></div>
      <button class="rp-close" onclick="rpClose()">✕</button>
    </div>
    <div class="rp-body" id="rpBody"><div style="text-align:center"><div class="rp-spin"></div></div></div>
    <div class="rp-footer" id="rpFoot" style="display:none">
      <button class="btn btn-secondary btn-sm" onclick="rpCopy()"><span class="material-icons-outlined" style="font-size:14px">content_copy</span> Copiar</button>
      <button class="btn btn-secondary btn-sm" onclick="togRpSnd()"><span class="material-icons-outlined" style="font-size:14px">send</span> Enviar WhatsApp</button>
      <a href="<?=APP_URL?>/ai" class="btn btn-ghost btn-sm" style="font-size:11px" target="_blank">Abrir em IA →</a>
    </div>
    <div class="rp-send" id="rpSndBox" style="display:none">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div><label style="font-size:11px;color:var(--txt2);display:block;margin-bottom:3px">WhatsApp</label>
        <select id="rpWp" class="form-control form-control-sm">
          <option value="">— Instância —</option>
          <?php foreach(($instances??[]) as $i): ?><option value="<?=htmlspecialchars($i['id'])?>"><?=htmlspecialchars($i['instance_name'])?></option><?php endforeach; ?>
        </select></div>
        <div><label style="font-size:11px;color:var(--txt2);display:block;margin-bottom:3px">Enviar para</label>
        <select id="rpTipo" class="form-control form-control-sm" onchange="rpToggle()">
          <option value="phone">📱 Número</option><option value="client">👤 Cliente</option><option value="group">👥 Grupo</option>
        </select></div>
      </div>
      <div id="rpPh"><input type="tel" id="rpPhone" class="form-control form-control-sm" placeholder="5581999999999" style="margin-bottom:8px"></div>
      <div id="rpCl" style="display:none"><select id="rpCliSel" class="form-control form-control-sm" style="margin-bottom:8px" onchange="var o=this.options[this.selectedIndex];document.getElementById('rpPhone').value=o.dataset.phone||'';document.getElementById('rpClientId').value=this.value;">
        <option value="">— Cliente —</option>
        <?php foreach(($clients??[]) as $c): ?><option value="<?=htmlspecialchars($c['id'])?>" data-phone="<?=htmlspecialchars($c['phone']??'')?>"><?=htmlspecialchars($c['name'])?><?php if($c['phone']??''): ?> · <?=htmlspecialchars($c['phone'])?><?php endif; ?></option><?php endforeach; ?>
      </select><input type="hidden" id="rpClientId"></div>
      <div id="rpGr" style="display:none"><select id="rpGrp" class="form-control form-control-sm" style="margin-bottom:8px">
        <option value="">— Grupo —</option>
        <?php foreach(($wpGroups??[]) as $g): ?><option value="<?=htmlspecialchars($g['id'])?>" data-inst="<?=htmlspecialchars($g['instance_name']??'')?>"><?=htmlspecialchars($g['name']??$g['id'])?></option><?php endforeach; ?>
      </select></div>
      <label style="font-size:11px;color:var(--txt2);display:block;margin-bottom:3px">Mensagem (editável)</label>
      <textarea id="rpMsg" class="rp-ta"></textarea>
      <div style="display:flex;align-items:center;gap:8px;margin-top:10px">
        <button class="btn btn-primary btn-sm" onclick="rpSend()"><span class="material-icons-outlined" style="font-size:14px">send</span> Enviar</button>
        <button class="btn btn-secondary btn-sm" onclick="rpReset()"><span class="material-icons-outlined" style="font-size:14px">refresh</span> Restaurar</button>
        <span id="rpSt" style="font-size:12px"></span>
      </div>
    </div>
  </div>
</div>
<script>
var _rpRaw='', _rpTipo='phone';
function rpOpen(campId,accId,campName){
  _rpRaw=''; document.getElementById('rpTitle').textContent=campName||'Análise IA';
  document.getElementById('rpBody').innerHTML='<div style="text-align:center"><div class="rp-spin"></div><p style="font-size:13px;color:var(--txt2);margin-top:8px">Analisando com IA...</p></div>';
  document.getElementById('rpFoot').style.display='none'; document.getElementById('rpSndBox').style.display='none';
  document.getElementById('rpPop').classList.add('open');
  var ed=new Date().toISOString().split('T')[0], sd=new Date();sd.setDate(sd.getDate()-30);var st=sd.toISOString().split('T')[0];
  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('campaign_id',campId);fd.append('account_id',accId);fd.append('date_start',st);fd.append('date_end',ed);
  fetch(APP_URL+'/ai/analyze-card',{method:'POST',body:fd,credentials:'include'}).then(function(r){return r.json();}).then(function(d){
    if(!d.success){document.getElementById('rpBody').innerHTML='<div style="color:var(--danger);text-align:center;padding:30px">⚠️ '+esc(d.error)+'</div>';return;}
    _rpRaw=d.analysis;
    var m=d.metrics;
    function rpms(v,l){return '<div class="rp-mc"><div class="rp-mv">'+v+'</div><div class="rp-ml">'+l+'</div></div>';}
    function rpfmt(n){return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
    document.getElementById('rpBody').innerHTML=
      '<div class="rp-ms">'+rpms('R$ '+rpfmt(m.spend),'Investido')+rpms(rpfmt(m.clicks),'Cliques')+rpms(parseFloat(m.ctr).toFixed(2)+'%','CTR')+rpms(parseFloat(m.roas).toFixed(2)+'x','ROAS')+'</div>'+
      '<div style="font-size:11px;color:var(--txt3);margin-bottom:10px">'+esc(d.account_name)+' · '+esc(d.period)+'</div>'+
      '<div class="rp-txt">'+(d.analysis||'').replace(/\*(.*?)\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>')+'</div>';
    document.getElementById('rpMsg').value='📊 *Análise de Campanha*\n*'+d.campaign_name+'*\n*Conta:* '+d.account_name+'\n*Período:* '+d.period+'\n\n'+d.analysis;
    document.getElementById('rpFoot').style.display='flex';
  }).catch(function(e){document.getElementById('rpBody').innerHTML='<div style="color:var(--danger);text-align:center;padding:30px">Erro: '+esc(e.message)+'</div>';});
}
function rpClose(){document.getElementById('rpPop').classList.remove('open');}
function rpCopy(){if(_rpRaw)navigator.clipboard.writeText(_rpRaw);}
function togRpSnd(){var b=document.getElementById('rpSndBox');b.style.display=b.style.display==='none'?'block':'none';}
function rpToggle(){
  _rpTipo=document.getElementById('rpTipo').value;
  document.getElementById('rpPh').style.display=_rpTipo==='phone'?'block':'none';
  document.getElementById('rpCl').style.display=_rpTipo==='client'?'block':'none';
  document.getElementById('rpGr').style.display=_rpTipo==='group'?'block':'none';
}
function rpReset(){document.getElementById('rpMsg').value=_rpRaw?'📊 *Análise de Campanha*\n\n'+_rpRaw:'';}
function rpSend(){
  var wpId=document.getElementById('rpWp').value, msg=document.getElementById('rpMsg').value.trim(), st=document.getElementById('rpSt');
  var phone='', groupId='', clientId='';
  if(_rpTipo==='phone') phone=document.getElementById('rpPhone').value.trim();
  if(_rpTipo==='client'){ clientId=document.getElementById('rpClientId').value; phone=document.getElementById('rpPhone').value.trim(); }
  if(_rpTipo==='group') groupId=document.getElementById('rpGrp').value;
  if(!msg){alert('Mensagem vazia.');return;}
  if(_rpTipo!=='group'&&!phone&&!clientId){alert('Selecione um cliente ou informe o número.');return;}
  if(_rpTipo==='group'&&!groupId){alert('Selecione o grupo.');return;}
  st.innerHTML='⏳ Enviando...';
  var fd=new FormData();
  fd.append('_token',CSRF);fd.append('whatsapp_id',wpId);fd.append('recv_type',_rpTipo);fd.append('phone',phone);fd.append('client_id',clientId);fd.append('group_id',groupId);fd.append('message',msg);
  fetch(APP_URL+'/ai/send',{method:'POST',body:fd,credentials:'include'}).then(function(r){return r.json();}).then(function(d){
    st.innerHTML=d.success?'<span style="color:var(--success)">✅ Enviado!</span>':'<span style="color:var(--danger)">❌ '+(d.error||'Erro')+'</span>';
  }).catch(function(){st.innerHTML='<span style="color:var(--danger)">❌ Erro de rede</span>';});
}

// ══ VARPICKER DO POPUP DE RELATÓRIO ══════════════════════════════════════
var _rpVpCat = 'Todas';
var RP_VAR_CATS = {
  'Gerais': [
    {l:'Nome do cliente',t:'{nome_cliente}',c:''},{l:'Primeiro nome',t:'{primeiro_nome}',c:''},
    {l:'Empresa',t:'{empresa}',c:''},{l:'Período',t:'{periodo}',c:''},
    {l:'Conta de anúncio',t:'{conta_anuncio}',c:''},{l:'Nome da campanha',t:'{campanha}',c:''},
    {l:'Observações',t:'{observacoes}',c:''},
  ],
  'Cliques e Impressões': [
    {l:'Alcance',t:'{alcance}',c:'orange'},{l:'Impressões',t:'{impressoes}',c:'orange'},
    {l:'Cliques no link',t:'{cliques}',c:'orange'},{l:'Todos os cliques',t:'{clicks_all}',c:'orange'},
    {l:'CTR',t:'{ctr}',c:'orange'},{l:'CPM',t:'{cpm}',c:'orange'},
    {l:'CPC',t:'{cpc}',c:'orange'},{l:'Frequência',t:'{frequencia}',c:'orange'},
    {l:'Visitas ao perfil',t:'{profile_visit}',c:'orange'},
  ],
  'Conversões': [
    {l:'Conversões',t:'{conversoes}',c:'green'},{l:'Leads',t:'{leads}',c:'green'},
    {l:'ROAS',t:'{roas}',c:'green'},{l:'Receita',t:'{receita}',c:'green'},
    {l:'Vendas',t:'{vendas}',c:'green'},{l:'Mensagens',t:'{msg}',c:'green'},
    {l:'Todos os leads',t:'{all_leads}',c:'green'},
  ],
  'Custos': [
    {l:'Investimento',t:'{investimento}',c:'orange'},{l:'CPL',t:'{cpl}',c:'orange'},
    {l:'CPV',t:'{cpv}',c:'orange'},{l:'Custo/Resultado',t:'{custo_result}',c:'orange'},
    {l:'Custo/Mensagem',t:'{cmsg}',c:'orange'},{l:'Ticket Médio',t:'{tm}',c:'orange'},
    {l:'Custo/Visita ao perfil',t:'{custo_por_visita}',c:'orange'},
  ],
  'Engajamento': [
    {l:'Comentários',t:'{comment}',c:'pink'},{l:'Likes',t:'{post_reaction}',c:'pink'},
    {l:'Salvamentos',t:'{post_save}',c:'pink'},{l:'Engajamento página',t:'{page_engagement}',c:'pink'},
  ],
  'Vídeo': [
    {l:'25% assistido',t:'{view_25}',c:'pink'},{l:'50% assistido',t:'{view_50}',c:'pink'},
    {l:'75% assistido',t:'{view_75}',c:'pink'},{l:'95% assistido',t:'{view_95}',c:'pink'},
    {l:'100% assistido',t:'{view_100}',c:'pink'},{l:'Thruplay',t:'{thruplay}',c:'pink'},
  ],
  'Criativos': [
    {l:'Ranking TOP 1',t:'{top_1_creatives_ranking}',c:'pink'},
    {l:'Ranking TOP 3',t:'{top_3_creatives_ranking}',c:'pink'},
    {l:'Lista criativos',t:'{all_creatives_simple}',c:'pink'},
  ],
};

function initRpVarpicker(){
  var btn=document.getElementById('rpVpBtn'), panel=document.getElementById('rpVpPanel');
  if(!btn||!panel) return;
  btn.addEventListener('click',function(e){
    e.stopPropagation();
    var isOpen = panel.style.display==='flex';
    panel.style.display = isOpen?'none':'flex';
    if(!isOpen) rpVpBuild();
  });
  document.addEventListener('click',function(e){
    if(panel&&!panel.contains(e.target)&&e.target!==btn) panel.style.display='none';
  });
}

function rpVpBuild(){
  _rpVpCat='Todas';
  var catsEl=document.getElementById('rpVpCats'); if(!catsEl) return;
  catsEl.innerHTML='';
  ['Todas'].concat(Object.keys(RP_VAR_CATS)).forEach(function(c){
    var b=document.createElement('button'); b.type='button';
    b.style.cssText='padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:var(--font);border:1px solid '+(c==='Todas'?'var(--accent)':'var(--border2)')+';background:'+(c==='Todas'?'var(--accent)':'var(--bg3)')+';color:'+(c==='Todas'?'#fff':'var(--txt2)');
    b.textContent=c;
    b.onclick=function(){
      _rpVpCat=c;
      document.querySelectorAll('#rpVpCats button').forEach(function(x){x.style.background='var(--bg3)';x.style.color='var(--txt2)';x.style.borderColor='var(--border2)';});
      b.style.background='var(--accent)'; b.style.color='#fff'; b.style.borderColor='var(--accent)';
      rpVpRender();
    };
    catsEl.appendChild(b);
  });
  rpVpRender();
}

function rpVpRender(){
  var q=(document.getElementById('rpVpSearch')||{}).value||'';
  var list=document.getElementById('rpVpList'); if(!list) return;
  list.innerHTML='';
  var cats=_rpVpCat==='Todas'?Object.keys(RP_VAR_CATS):[_rpVpCat];
  var found=0;
  cats.forEach(function(cat){
    var items=(RP_VAR_CATS[cat]||[]).filter(function(v){return !q||v.l.toLowerCase().includes(q.toLowerCase())||v.t.toLowerCase().includes(q.toLowerCase());});
    if(!items.length) return;
    var sec=document.createElement('span');
    sec.style.cssText='font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;padding:6px 2px 4px;width:100%;display:block';
    sec.textContent=cat; list.appendChild(sec);
    var wrap=document.createElement('div');
    items.forEach(function(v){
      var tag=document.createElement('span');
      var c=v.c;
      if(c==='orange') tag.style.cssText='display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(243,156,18,.1);color:var(--warn);border:1px solid rgba(243,156,18,.3);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace';
      else if(c==='green') tag.style.cssText='display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(39,174,96,.1);color:var(--success);border:1px solid rgba(39,174,96,.3);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace';
      else if(c==='pink') tag.style.cssText='display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(155,89,182,.1);color:#bb88ff;border:1px solid rgba(155,89,182,.3);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace';
      else tag.style.cssText='display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(0,120,255,.1);color:var(--accent);border:1px solid rgba(0,120,255,.2);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace';
      tag.title=v.l; tag.textContent=v.t;
      tag.onclick=function(){
        rpInsVar(v.t);
        document.getElementById('rpVpPanel').style.display='none';
      };
      wrap.appendChild(tag); found++;
    });
    list.appendChild(wrap);
  });
  if(!found){ var em=document.createElement('div'); em.style.cssText='padding:16px;text-align:center;font-size:12px;color:var(--txt3)'; em.textContent='Nenhuma variável encontrada'; list.appendChild(em); }
}

function rpInsVar(tag){
  var ta=document.getElementById('aiRepCustom'); if(!ta) return;
  var s=ta.selectionStart, e=ta.selectionEnd;
  ta.value=ta.value.substring(0,s)+tag+ta.value.substring(e);
  ta.focus(); ta.selectionStart=ta.selectionEnd=s+tag.length;
}
function rpAddSugg(s){
  var ta=document.getElementById('aiRepCustom'); if(!ta) return;
  var v=ta.value; ta.value=(v?v+(v.slice(-1)==='.'||v.slice(-1)===' '?'':'. '):'')+ s; ta.focus();
}

// ══ ANÁLISE IA DO RELATÓRIO ══════════════════════════════════════════════
var _aiReport = null;
var _aiRawText = '';

function openAiReport(data){
  _aiReport = data;
  _aiRawText = '';
  _aiRepChatHistory = [];
  document.getElementById('aiRepTitle').textContent = data.title || 'Relatório';
  document.getElementById('aiRepBody').innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:200px;gap:14px"><div style="width:36px;height:36px;border:3px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite"></div><p style="color:var(--txt2);font-size:13px">Aguardando análise...</p></div>';
  document.getElementById('aiRepFooter').style.display='none';
  document.getElementById('aiRepChatSection').style.display='none';
  document.getElementById('aiRepChatHistory').innerHTML='';
  document.getElementById('aiRepChatInput').value='';
  document.getElementById('aiRepCustom').value='';
  document.getElementById('aiRepPeriodo').value = data.period_type || 'last_7_days';
  aiRepPeriodoChange();
  var tplSel = document.getElementById('aiRepTplSelect');
  if(tplSel){
    tplSel.innerHTML='<option value="">— Sem template (análise geral) —</option>';
    (TEMPLATES||[]).forEach(function(t){
      var o=document.createElement('option');
      o.value=t.content||'';
      o.textContent=t.name||'';
      tplSel.appendChild(o);
    });
    tplSel.value=''; onAiRepTplChange(tplSel);
  }
  // Init varpicker
  setTimeout(initRpVarpicker, 50);
  document.getElementById('mdAiReport').style.display='flex';
}
function aiRepSaveModel(sel){
  var opt=sel.options[sel.selectedIndex];
  var prov=opt?opt.dataset.prov:'';
  var model=sel.value;
  var fd=new FormData();
  fd.append('_ajax','1');fd.append('_csrf',CSRF);
  fd.append('provider',prov);fd.append('model',model);
  fetch(APP_URL+'/ai/save-model',{method:'POST',body:fd,credentials:'include'}).catch(function(){});
}

function closeAiReport(){ document.getElementById('mdAiReport').style.display='none'; }

// ════ HISTÓRICO DE CHAT ════
var _aiRepChatHistory = [];

// ════ TEMPLATES ════
function onAiRepTplChange(sel) {
  var val     = sel.value;
  var preview = document.getElementById('aiRepTplPreview');
  var varsEl  = document.getElementById('aiRepTplVars');
  var badge   = document.getElementById('aiRepTplBadge');
  var note    = document.getElementById('aiRepTplNote');
  var badgeTxt= document.getElementById('aiRepTplBadgeTxt');
  if (!val) {
    preview.style.display='none'; varsEl.innerHTML='';
    badge.style.display='none'; note.style.display='none';
    return;
  }
  preview.innerHTML = esc(val).replace(/\n/g,'<br>');
  preview.style.display='block';
  var vars = [...new Set((val.match(/\{[a-z_]+\}/g)||[]))];
  varsEl.innerHTML = vars.map(function(v){
    return '<span style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:12px;background:rgba(39,174,96,.1);color:var(--success);border:1px solid rgba(39,174,96,.25);font-size:10px;font-weight:600;font-family:monospace">'+esc(v)+'</span>';
  }).join('');
  badge.style.display='inline-flex';
  badgeTxt.textContent = vars.length+' variáveis detectadas';
  note.style.display='block';
  var ta = document.getElementById('aiRepCustom');
  // Não auto-preenche instrução quando template está selecionado - template já define o formato
}

// ════ CHAT ════
async function sendAiRepChat() {
  var ta  = document.getElementById('aiRepChatInput');
  var msg = ta.value.trim();
  if(!msg) return;
  ta.value=''; ta.style.height='40px';
  var hist = document.getElementById('aiRepChatHistory');
  var btn  = document.getElementById('aiRepChatBtn');
  hist.innerHTML += '<div style="display:flex;flex-direction:row-reverse;gap:8px">'
    +'<div style="width:26px;height:26px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0">👤</div>'
    +'<div style="background:var(--accent);border-radius:10px 10px 2px 10px;padding:9px 13px;font-size:12px;line-height:1.7;color:#fff;max-width:85%">'+esc(msg)+'</div></div>';
  hist.scrollTop=hist.scrollHeight;
  btn.disabled=true;
  btn.innerHTML='<div style="width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></div>';
  var tid='aichat_'+Date.now();
  hist.innerHTML+='<div id="'+tid+'" style="display:flex;gap:8px;align-items:center">'
    +'<div style="width:26px;height:26px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:12px">✨</div>'
    +'<div style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 14px;display:flex;gap:4px">'
    +'<span style="width:6px;height:6px;border-radius:50%;background:var(--txt3);display:inline-block;animation:sndBounce 1.1s -.32s infinite ease-in-out both"></span>'
    +'<span style="width:6px;height:6px;border-radius:50%;background:var(--txt3);display:inline-block;animation:sndBounce 1.1s -.16s infinite ease-in-out both"></span>'
    +'<span style="width:6px;height:6px;border-radius:50%;background:var(--txt3);display:inline-block;animation:sndBounce 1.1s 0s infinite ease-in-out both"></span>'
    +'</div></div>';
  hist.scrollTop=hist.scrollHeight;
  _aiRepChatHistory.push({role:'user',content:msg});
  try {
    var aiRepSel=document.getElementById('aiRepModelSel');
    var fd=new FormData();
    fd.append('_token',CSRF);
    fd.append('provider',aiRepSel&&aiRepSel.selectedIndex>=0?aiRepSel.options[aiRepSel.selectedIndex].dataset.prov:'');
    fd.append('model',aiRepSel?aiRepSel.value:'');
    fd.append('account_id',_aiReport.ad_account_id||'');
    fd.append('camp_ids',_aiReport.camp_ids||'');
    fd.append('message',msg);
    fd.append('history',JSON.stringify(_aiRepChatHistory.slice(0,-1)));
    var r=await fetch(APP_URL+'/ai/chat-report',{method:'POST',body:fd,credentials:'include'});
    var d=await r.json();
    document.getElementById(tid)?.remove();
    if(!d.success){
      hist.innerHTML+='<div style="color:var(--danger);font-size:12px;padding:4px 0">⚠️ '+esc(d.error||'Erro')+'</div>';
      return;
    }
    var reply=d.reply||'';
    _aiRepChatHistory.push({role:'assistant',content:reply});
    var escaped=JSON.stringify(reply);
    hist.innerHTML+='<div style="display:flex;gap:8px;align-items:flex-start">'
      +'<div style="width:26px;height:26px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:2px">✨</div>'
      +'<div style="flex:1">'
      +'<div style="background:var(--bg3);border:1px solid var(--border);border-radius:10px 10px 10px 2px;padding:10px 13px;font-size:12px;line-height:1.8;color:var(--txt)">'+fmtAiTxt(reply)+'</div>'
      +'<div style="display:flex;gap:6px;margin-top:5px">'
      +'<button onclick=\'navigator.clipboard.writeText('+escaped+');this.textContent="✅ Copiado!";setTimeout(()=>{this.textContent="📋 Copiar"},2000)\' style="font-size:11px;padding:3px 10px;border-radius:6px;background:var(--bg3);color:var(--txt2);border:1px solid var(--border2);cursor:pointer;font-family:var(--font)">📋 Copiar</button>'
      +'<button onclick=\'document.getElementById(\"aiRepMsg\").value='+escaped+';document.getElementById(\"aiRepSendPanel\").style.display=\"block\";document.getElementById(\"aiRepChatSection\").scrollIntoView({behavior:\"smooth\"})\' style="font-size:11px;padding:3px 10px;border-radius:6px;background:#25D366;color:#fff;border:none;cursor:pointer;font-family:var(--font);display:flex;align-items:center;gap:4px"><i class=\"fa-brands fa-whatsapp\"></i> Enviar WA</button>'
      +'</div></div></div>';
    hist.scrollTop=hist.scrollHeight;
  } catch(e){
    document.getElementById(tid)?.remove();
    hist.innerHTML+='<div style="color:var(--danger);font-size:12px">❌ Erro: '+esc(e.message)+'</div>';
  } finally {
    btn.disabled=false;
    btn.innerHTML='<svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round"/><path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }
}
document.addEventListener('DOMContentLoaded',function(){
  var ci=document.getElementById('aiRepChatInput');
  if(ci) ci.addEventListener('input',function(){ this.style.height='40px'; this.style.height=Math.min(this.scrollHeight,100)+'px'; });
});

function extractMetricsFromPrompt(prompt) {
  var map = {
    '{investimento}':'invest', '{receita}':'receita', '{roas}':'roas',
    '{cpl}':'cpa', '{cpv}':'cpa', '{custo_result}':'cpa',
    '{alcance}':'alcance', '{impressoes}':'imp', '{impressoes}':'imp',
    '{cliques}':'clicks', '{clicks_all}':'clicks', '{ctr}':'ctr',
    '{cpc}':'cpc', '{cpm}':'cpm', '{frequencia}':'freq', '{frequencia}':'freq',
    '{conversoes}':'conv', '{conversoes}':'conv', '{leads}':'conv',
    '{results}':'conv', '{vendas}':'conv', '{msg}':'conv',
    '{engajamento}':'conv', '{all_leads}':'conv',
    '{profile_visit}':'alcance', '{custo_por_visita}':'perfil', '{cmsg}':'cpa', '{engajamento_cost}':'cpa', '{tm}':'cpa',
    '{view_25}':'tendencia', '{view_50}':'tendencia', '{view_75}':'tendencia',
    '{view_95}':'tendencia', '{view_100}':'tendencia', '{thruplay}':'tendencia', '{v_avg}':'tendencia',
  };
  var found = {};
  Object.keys(map).forEach(function(tag){
    if(prompt.indexOf(tag) !== -1) found[map[tag]] = true;
  });
  var keys = Object.keys(found);
  return keys.length ? keys.join(',') : '';
}

function aiRepPeriodoChange(){
  var v = document.getElementById('aiRepPeriodo').value;
  document.getElementById('aiRepCustomDates').style.display = v==='custom'?'grid':'none';
}

async function runAiReport(){
  if(!_aiReport) return;
  var aiRepSel=document.getElementById('aiRepModelSel');
  var periodo= document.getElementById('aiRepPeriodo').value;
  var cStart = document.getElementById('aiRepCStart').value;
  var cEnd   = document.getElementById('aiRepCEnd').value;
  var prompt = document.getElementById('aiRepCustom').value.trim();
  if(!aiRepSel||!aiRepSel.value){ alert('Selecione um modelo.'); return; }
  if(periodo==='custom'&&(!cStart||!cEnd)){ alert('Informe as datas.'); return; }

  _aiRawText = '';
  document.getElementById('aiRepBody').innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:200px;gap:14px"><div style="width:36px;height:36px;border:3px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite"></div><p id="aiRepLoadMsg" style="color:var(--txt2);font-size:13px;animation:pulse 1.5s ease-in-out infinite">Carregando métricas...</p></div>';
  document.getElementById('aiRepFooter').style.display='none';

  var msgs=['Carregando métricas...','Processando com IA...','Gerando diagnóstico...','Quase pronto...'];
  var mi=0, iv=setInterval(function(){ var el=document.getElementById('aiRepLoadMsg'); if(el) el.textContent=msgs[mi=(mi+1)%msgs.length]; },1800);

  try {
    var fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('provider', aiRepSel.options[aiRepSel.selectedIndex].dataset.prov);
    fd.append('model', aiRepSel.value);
    fd.append('account_id', _aiReport.ad_account_id||'');
    fd.append('camp_ids', _aiReport.camp_ids||'');
    fd.append('periodo', periodo);
    fd.append('custom_start', cStart);
    fd.append('custom_end', cEnd);
    fd.append('custom_prompt', prompt);
    fd.append('metrics', extractMetricsFromPrompt(prompt));
    fd.append('template', (document.getElementById('aiRepTplSelect')||{}).value||'');
    fd.append('use_template_direct', (document.getElementById('aiRepTplSelect')||{}).value ? '1' : '0');

    var r = await fetch(APP_URL+'/ai/analyze-report', {method:'POST', body:fd, credentials:'include'});
    var d = await r.json();
    clearInterval(iv);

    if(!d.success){
      document.getElementById('aiRepBody').innerHTML = '<div style="color:var(--danger);text-align:center;padding:30px;font-size:13px">⚠️ '+esc(d.error)+'</div>';
      return;
    }
    _aiRawText = d.analysis;

    // Monta HTML do resultado — cards dinâmicos baseados nas variáveis do prompt
    var html = '';
    if(d.metrics){
      var m = d.metrics;
      // Pega variáveis do template selecionado OU do campo de instrução
      var tplSelect = document.getElementById('aiRepTplSelect');
      var tplContent = tplSelect ? (tplSelect.value||'') : '';
      var promptUsado = document.getElementById('aiRepCustom') ? (document.getElementById('aiRepCustom').value||'').trim() : '';
      var sourceTxt = tplContent || promptUsado;
      var varsNoPrompt = sourceTxt.match(/\{([^}]+)\}/g) || [];
      // Adiciona variáveis extras que podem estar no template mas não listadas
      if (varsNoPrompt.length === 0) {
        varsNoPrompt = ['{investimento}','{alcance}','{impressoes}','{cliques}','{cpm}'];
      }
      var cpa = parseFloat(m.conversions||0)>0 ? 'R$ '+fmt((m.spend||0)/(m.conversions||1)) : 'N/A';
      var varMapRep = {
        '{investimento}':  {l:'Investimento',    v:'R$ '+fmt(m.spend||0)},
        '{receita}':       {l:'Receita',          v:'R$ '+fmt(m.revenue||0)},
        '{roas}':          {l:'ROAS',             v:parseFloat(m.roas||0).toFixed(2)+'x'},
        '{alcance}':       {l:'Alcance',          v:fmtN(m.reach||0)},
        '{impressoes}':    {l:'Impressões',       v:fmtN(m.impressions||0)},
        '{cliques}':       {l:'Cliques no link',  v:fmtN(m.clicks||0)},
        '{clicks_all}':    {l:'Todos os cliques', v:fmtN(m.clicks_all||m.clicks||0)},
        '{ctr}':           {l:'CTR',              v:parseFloat(m.ctr||0).toFixed(2)+'%'},
        '{cpc}':           {l:'CPC',              v:'R$ '+fmt(m.cpc||0)},
        '{cpm}':           {l:'CPM',              v:'R$ '+fmt(m.cpm||0)},
        '{frequencia}':    {l:'Frequência',       v:parseFloat(m.frequency||0).toFixed(2)},
        '{profile_visit}': {l:'Visitas ao perfil',v:fmtN(m.profile_visit||0)},
        '{custo_por_visita}':{l:'Custo/Visita',   v:(m.profile_visit||0)>0?'R$ '+fmt((m.spend||0)/(m.profile_visit||1)):'N/A'},
        '{conversoes}':    {l:'Conversões',       v:fmtN(m.conversions||0)},
        '{leads}':         {l:'Leads',            v:fmtN(m.leads||0)},
        '{vendas}':        {l:'Vendas',           v:fmtN(m.purchases||0)},
        '{msg}':           {l:'Mensagens',        v:fmtN(m.messages||0)},
        '{engajamento}':   {l:'Engajamento',      v:fmtN(m.engagement||0)},
        '{cpl}':           {l:'CPL',              v:'R$ '+fmt(m.cpl||0)},
        '{cpv}':           {l:'CPV',              v:'R$ '+fmt(m.cpv||0)},
        '{cmsg}':          {l:'Custo/Mensagem',   v:'R$ '+fmt(m.cost_per_message||0)},
        '{visitas_perfil}': {l:'Visitas ao perfil', v:fmtN(m.profile_visit||m.profile_visits||0)},
        '{custo_visita_perfil}':{l:'Custo/Visita',  v:(m.profile_visit||0)>0?'R$ '+fmt((m.spend||0)/(m.profile_visit||1)):'R$ 0,00'},
      };
      var repCards = [];
      var repAdded = {};
      varsNoPrompt.forEach(function(v){ if(varMapRep[v] && !repAdded[v]){ repAdded[v]=true; repCards.push(msCard(varMapRep[v].v,varMapRep[v].l)); } });
      if(repCards.length>0){
        var cols=Math.min(repCards.length,4), mid=Math.ceil(repCards.length/2);
        html += '<div style="display:grid;grid-template-columns:repeat('+cols+',1fr);gap:8px;margin-bottom:8px">';
        html += repCards.slice(0,mid).join(''); html += '</div>';
        if(repCards.length>mid){ html += '<div style="display:grid;grid-template-columns:repeat('+Math.min(repCards.length-mid,4)+',1fr);gap:8px;margin-bottom:16px">'; html += repCards.slice(mid).join(''); html += '</div>'; }
      }
    }
    html += '<div style="font-size:11px;color:var(--txt3);margin-bottom:12px">'+esc(d.period||'')+(d.provider?' · '+provLblAi(d.provider):'')+'</div>';
    html += '<div style="font-size:13px;line-height:1.85;color:var(--txt);word-break:break-word">'+fmtAiTxt(d.analysis)+'</div>';
    document.getElementById('aiRepBody').innerHTML = html;

    // Preenche textarea de envio
    document.getElementById('aiRepMsg').value = '📊 *Diagnóstico IA — '+(_aiReport.title||'')+' *\n*Período:* '+(d.period||'')+'\n\n'+d.analysis;
    document.getElementById('aiRepFooter').style.display='flex';
    // Ativa chat
    _aiRepChatHistory = [{role:'assistant', content: d.analysis}];
    document.getElementById('aiRepChatHistory').innerHTML='';
    document.getElementById('aiRepChatSection').style.display='block';
    document.getElementById('aiRepChatInput').value='';
  } catch(err){
    clearInterval(iv);
    document.getElementById('aiRepBody').innerHTML = '<div style="color:var(--danger);text-align:center;padding:30px">Erro: '+esc(err.message)+'</div>';
  }
}

function msCard(v,l){ return '<div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:9px;text-align:center"><div style="font-size:14px;font-weight:700;color:var(--txt)">'+v+'</div><div style="font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-top:2px">'+l+'</div></div>'; }
function fmt(n){ return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fmtN(n){ return parseInt(n||0).toLocaleString('pt-BR'); }
function fmtAiTxt(t){ return (t||'').replace(/\*(.*?)\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>'); }
function provLblAi(p){ return {groq:'⚡ Groq',openai:'◆ OpenAI',gemini:'✦ Gemini'}[p]||p; }
function copyAiReport(){ if(_aiRawText) navigator.clipboard.writeText(_aiRawText); }
function aiRepCopyMain(){ if(_aiRawText){ navigator.clipboard.writeText(_aiRawText); var st=document.getElementById('aiRepSt'); st.textContent='✅ Copiado!'; setTimeout(function(){st.textContent='';},2000); } }
function aiRepToggleSend(){ var p=document.getElementById('aiRepSendPanel'); p.style.display=p.style.display==='none'?'block':'none'; }
function aiRepRegenMsg(){
  if(!_aiRawText) return;
  var fd=new FormData();
  fd.append('_token',CSRF);
  var aiRepSel=document.getElementById('aiRepModelSel');
  fd.append('provider',aiRepSel&&aiRepSel.selectedIndex>=0?aiRepSel.options[aiRepSel.selectedIndex].dataset.prov:'');
  fd.append('model',aiRepSel?aiRepSel.value:'');
  fd.append('message','Gere um resumo curto e direto desta análise para envio por WhatsApp, com emojis, máximo 5 linhas.');
  fd.append('history',JSON.stringify([{role:'assistant',content:_aiRawText}]));
  var btn=event.target; btn.textContent='⏳ Gerando...'; btn.disabled=true;
  fetch(APP_URL+'/ai/chat-report',{method:'POST',body:fd,credentials:'include'}).then(function(r){return r.json();}).then(function(d){
    if(d.success) document.getElementById('aiRepMsg').value=d.reply||'';
    btn.textContent='🔄 Gerar novo resumo'; btn.disabled=false;
  }).catch(function(){ btn.textContent='🔄 Gerar novo resumo'; btn.disabled=false; });
}

var _aiRepTipo = 'phone';
function aiRepSndTipo(v){
  _aiRepTipo=v;
  document.getElementById('aiRepSndPh').style.display=v==='phone'?'block':'none';
  document.getElementById('aiRepSndCl').style.display=v==='client'?'block':'none';
  document.getElementById('aiRepSndGr').style.display=v==='group'?'block':'none';
  // Atualizar botões
  ['Phone','Client','Group'].forEach(function(t){
    var b=document.getElementById('aiRepBtn'+t);
    if(b) b.className='btn btn-sm'+(v===t.toLowerCase()?' btn-primary':' btn-secondary');
  });
  if(v==='group') aiRepLoadGroups();
}

async function aiRepLoadGroups(){
  var wpId=document.getElementById('aiRepWpId').value;
  var grpSel=document.getElementById('aiRepGrpSel');
  var status=document.getElementById('aiRepGrpStatus');
  if(!wpId||!grpSel) return;
  if(status) status.textContent='⏳ Carregando...';
  try{
    var r=await fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wpId),{credentials:'include'});
    var d=await r.json();
    if(d.success && d.grupos && d.grupos.length>0){
      grpSel.innerHTML='<option value="">— Selecione o grupo —</option>';
      d.grupos.forEach(function(g){
        var o=document.createElement('option');
        o.value=g.id||'';
        o.textContent=g.nome||g.name||'Grupo';
        grpSel.appendChild(o);
      });
      if(status) status.textContent='✅ '+d.grupos.length+' grupo(s)';
    } else {
      if(status) status.textContent='⚠️ Nenhum grupo encontrado';
    }
  }catch(e){ if(status) status.textContent='❌ Erro ao buscar grupos'; }
}
async function sendAiReport(){
  var wpId    = document.getElementById('aiRepWpId').value;
  var msg     = document.getElementById('aiRepMsg').value.trim();
  var st      = document.getElementById('aiRepSt');
  var phone='', groupId='', clientId='';
  if(_aiRepTipo==='phone')  phone    = document.getElementById('aiRepPhone').value.trim();
  if(_aiRepTipo==='client'){ clientId = document.getElementById('aiRepCliSel').value; phone = document.getElementById('aiRepPhone').value.trim(); }
  if(_aiRepTipo==='group')  groupId  = document.getElementById('aiRepGrpSel').value;
  if(!msg){ alert('Mensagem vazia.'); return; }
  if(_aiRepTipo!=='group'&&!phone&&!clientId){ alert('Informe o número ou selecione um cliente.'); return; }
  if(_aiRepTipo==='group'&&!groupId){ alert('Selecione o grupo.'); return; }
  st.innerHTML='⏳ Enviando...';
  var fd=new FormData();
  fd.append('_token',CSRF); fd.append('whatsapp_id',wpId);
  fd.append('recv_type',_aiRepTipo); fd.append('phone',phone);
  fd.append('client_id',clientId); fd.append('group_id',groupId); fd.append('message',msg);
  try {
    var r=await fetch(APP_URL+'/ai/send',{method:'POST',body:fd,credentials:'include'});
    var d=await r.json();
    st.innerHTML=d.success?'<span style="color:var(--success)">✅ Enviado!</span>':'<span style="color:var(--danger)">❌ '+(d.error||'Erro')+'</span>';
  } catch(e){ st.innerHTML='<span style="color:var(--danger)">❌ Erro</span>'; }
}
</script>

<!-- MODAL IA DO RELATÓRIO -->
<div id="mdAiReport" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:900;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto">
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:780px;margin:auto;display:flex;flex-direction:column;overflow:hidden">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);flex-shrink:0">
    <div style="font-size:14px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px">
      <span style="color:var(--accent)">✨</span> Análise IA — <span id="aiRepTitle"></span>
    </div>
    <button onclick="closeAiReport()" style="background:none;border:none;color:var(--txt3);cursor:pointer;font-size:22px;line-height:1">✕</button>
  </div>

  <!-- Config -->
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:12px;flex-shrink:0">
    <!-- Período -->
    <div>
      <label style="font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Período</label>
      <select id="aiRepPeriodo" class="form-control form-control-sm" onchange="aiRepPeriodoChange()">
        <option value="today">Hoje</option>
        <option value="yesterday">Ontem</option>
        <option value="last_7_days">Últimos 7 dias</option>
        <option value="last_15_days">Últimos 15 dias</option>
        <option value="last_30_days">Últimos 30 dias</option>
        <option value="last_90_days">Últimos 90 dias</option>
        <option value="this_month">Este mês</option>
        <option value="last_month">Mês passado</option>
        <option value="max">Máximo</option>
        <option value="custom">📅 Personalizado</option>
      </select>
      <div id="aiRepCustomDates" style="display:none;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px">
        <input type="date" id="aiRepCStart" class="form-control form-control-sm">
        <input type="date" id="aiRepCEnd" class="form-control form-control-sm" value="<?=date('Y-m-d')?>">
      </div>
    </div>
    <!-- Modelo IA -->
    <div>
      <label style="font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:5px">Modelo de IA</label>
      <?php
      $aiSettingsRep = AiController::getAiSettings(currentUser()['id'], Database::getInstance());
      $hasGroqR  = !empty($aiSettingsRep['ai_groq_key']);
      $hasGemR   = !empty($aiSettingsRep['ai_gemini_key']);
      $hasOaiR   = !empty($aiSettingsRep['ai_openai_key']);
      $defProvR  = $aiSettingsRep['ai_default_provider'] ?? 'groq';
      $defMdlR   = $aiSettingsRep['ai_default_model']    ?? 'llama-3.1-8b-instant';
      $allMdlsR  = AiController::getModels();
      ?>
      <?php if(!$hasGroqR && !$hasGemR && !$hasOaiR): ?>
      <div style="font-size:11px;color:var(--txt3);padding:8px;background:var(--bg3);border-radius:8px;text-align:center">
        ⚠️ Nenhuma chave de IA configurada.<br>
        <a href="/ai" style="color:var(--accent)">Configurar agora →</a>
      </div>
      <?php else: ?>
      <select id="aiRepModelSel" class="form-control form-control-sm" onchange="aiRepSaveModel(this)" style="font-size:12px">
        <?php foreach($allMdlsR as $pk => $pd):
          $on = ($pk==='groq'&&$hasGroqR)||($pk==='openai'&&$hasOaiR)||($pk==='gemini'&&$hasGemR);
        ?>
        <optgroup label="<?=e($pd['icon'].' '.$pd['label'].(!$on?' (sem chave)':''))?>">
          <?php foreach($pd['models'] as $mdl):
            $sel = ($mdl['id']===$defMdlR && $pk===$defProvR) ? 'selected' : '';
          ?>
          <option value="<?=e($mdl['id'])?>" data-prov="<?=$pk?>" <?=$sel?> <?=!$on?'disabled':''?>><?=e($mdl['name'])?> <?=e($mdl['price'])?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════ SEÇÃO DE TEMPLATES ════ -->
  <div style="padding:10px 20px;border-bottom:1px solid var(--border);flex-shrink:0" id="aiRepTplSection">
    <label style="font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:6px;margin-bottom:6px">
      Template de Análise
      <span id="aiRepTplBadge" style="display:none;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(79,140,255,.12);color:var(--accent);border:1px solid rgba(79,140,255,.25)">
        ✓ <span id="aiRepTplBadgeTxt"></span>
      </span>
    </label>
    <select id="aiRepTplSelect" class="form-control form-control-sm" onchange="onAiRepTplChange(this)">
      <option value="">— Sem template (análise geral) —</option>
    </select>
    <div id="aiRepTplPreview" style="display:none;margin-top:8px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:11px;color:var(--txt2);line-height:1.6;max-height:80px;overflow-y:auto"></div>
    <div id="aiRepTplVars" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px"></div>
    <div id="aiRepTplNote" style="display:none;margin-top:6px;background:rgba(79,140,255,.07);border:1px solid rgba(79,140,255,.2);border-radius:8px;padding:9px 12px;font-size:11px;color:var(--txt2)">
      <strong style="color:var(--accent)">✨ Template carregado!</strong> As variáveis serão analisadas automaticamente. Deixe o campo de instrução vazio ou adicione instrução extra abaixo.
    </div>
  </div>

  <!-- Varpicker + Prompt -->
  <div style="padding:10px 20px;border-bottom:1px solid var(--border);flex-shrink:0">
    <label style="font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:6px">Instrução para IA</label>

    <!-- Varpicker suspenso -->
    <div style="position:relative;margin-bottom:8px" id="rpVpWrap">
      <button type="button" id="rpVpBtn" style="width:100%;padding:8px 12px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt2);font-family:var(--font);font-size:12px;cursor:pointer;text-align:left;display:flex;align-items:center;justify-content:space-between;transition:border-color .15s">
        <span style="display:flex;align-items:center;gap:6px">
          <span class="material-icons-outlined" style="font-size:14px">add_circle_outline</span>
          Inserir variável no prompt
        </span>
        <span class="material-icons-outlined" style="font-size:14px;color:var(--txt3)">expand_more</span>
      </button>
      <div id="rpVpPanel" style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius2);z-index:9999;box-shadow:var(--shadow);display:none;flex-direction:column;max-height:340px">
        <div style="padding:10px;border-bottom:1px solid var(--border)">
          <input type="text" id="rpVpSearch" placeholder="🔍 Buscar variável..." oninput="rpVpRender()" style="width:100%;padding:7px 10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt);font-family:var(--font);font-size:12px;outline:none;box-sizing:border-box">
        </div>
        <div id="rpVpCats" style="display:flex;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border);flex-wrap:wrap"></div>
        <div id="rpVpList" style="overflow-y:auto;flex:1;padding:8px 10px"></div>
      </div>
    </div>

    <!-- Textarea de prompt livre -->
    <div style="background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius2);overflow:hidden;margin-bottom:5px">
      <textarea id="aiRepCustom" placeholder="Escreva aqui o que a IA deve analisar. Use o menu acima para inserir variáveis. Ex: Analise o {investimento} e o {roas}. Verifique o {ctr}. Seja direto e use emojis." style="width:100%;background:transparent;border:none;padding:10px 12px;color:var(--txt);font-family:var(--font);font-size:11px;line-height:1.6;resize:none;height:60px;box-sizing:border-box;outline:none"></textarea>
      <div style="padding:4px 10px 7px;display:flex;flex-wrap:wrap;gap:3px;border-top:1px dashed var(--border2)">
        <span style="font-size:9px;color:var(--txt3);margin-right:2px">Adicionar:</span>
        <?php foreach(['Simplifique','Seja direto','Foque em leads','Foque em ROAS','Tom formal','Mais emojis','Compare benchmarks'] as $sg): ?>
        <button onclick="rpAddSugg(<?=json_encode($sg)?>)" style="font-size:9px;padding:2px 7px;border-radius:8px;border:1px solid var(--border2);background:var(--bg2);color:var(--txt3);cursor:pointer;font-family:var(--font)"><?=e($sg)?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Botão analisar -->
  <div style="padding:10px 20px;border-bottom:1px solid var(--border);flex-shrink:0">
    <button onclick="runAiReport()" class="btn btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:7px;padding:9px">
      <span class="material-icons-outlined" style="font-size:16px">auto_awesome</span> Gerar Análise com IA
    </button>
  </div>

  <!-- Corpo resultado -->
  <div id="aiRepBody" style="padding:18px 20px;overflow-y:auto;flex:1;min-height:180px">
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:160px;color:var(--txt3);gap:8px">
      <span class="material-icons-outlined" style="font-size:40px;opacity:.2">auto_awesome</span>
      <div style="font-size:12px">Configure e clique em Gerar Análise</div>
    </div>
  </div>

  <!-- ════ CHAT DE ACOMPANHAMENTO ════ -->
  <div id="aiRepChatSection" style="display:none;border-top:1px solid var(--border);flex-shrink:0">

    <!-- Barra de ações do resultado principal -->
    <div style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-bottom:1px solid var(--border);background:var(--bg2)">
      <button onclick="runAiReport()" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:5px;font-size:12px">
        <span class="material-icons-outlined" style="font-size:13px">refresh</span> Nova Análise
      </button>
      <button onclick="aiRepCopyMain()" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:5px;font-size:12px">
        <span class="material-icons-outlined" style="font-size:13px">content_copy</span> Copiar
      </button>
      <button onclick="aiRepToggleSend()" class="btn btn-sm" style="display:flex;align-items:center;gap:5px;font-size:12px;background:#25D366;color:#fff;border-color:#25D366">
        <i class="fa-brands fa-whatsapp" style="font-size:13px"></i> Enviar WhatsApp
      </button>
      <span id="aiRepSt" style="font-size:11px;color:var(--txt3)"></span>
    </div>

    <!-- Painel de envio -->
    <div id="aiRepSendPanel" style="display:none;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--bg2)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--txt2);display:block;margin-bottom:3px">INSTÂNCIA WHATSAPP</label>
          <select id="aiRepWpId" class="form-control form-control-sm">
            <option value="">— Selecione —</option>
            <?php foreach($instances as $wi): ?>
            <option value="<?=e($wi['id'])?>"><?=e($wi['instance_name'])?><?php if($wi['phone_number']??''): ?> (<?=e($wi['phone_number'])?>)<?php endif; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--txt2);display:block;margin-bottom:3px">ENVIAR PARA</label>
          <div style="display:flex;gap:4px">
            <button type="button" id="aiRepBtnPhone"  onclick="aiRepSndTipo('phone')"  class="btn btn-secondary btn-sm" style="flex:1;font-size:10px">📱 Número</button>
            <button type="button" id="aiRepBtnClient" onclick="aiRepSndTipo('client')" class="btn btn-primary btn-sm"    style="flex:1;font-size:10px">👤 Cliente</button>
            <button type="button" id="aiRepBtnGroup"  onclick="aiRepSndTipo('group')"  class="btn btn-secondary btn-sm" style="flex:1;font-size:10px">👥 Grupo</button>
          </div>
        </div>
      </div>
      <div style="margin-bottom:10px">
        <div id="aiRepSndPh">
          <label style="font-size:10px;color:var(--txt3);display:block;margin-bottom:3px">Número (DDI+DDD+número)</label>
          <input type="tel" id="aiRepPhone" class="form-control form-control-sm" placeholder="Ex: 5511999999999">
        </div>
        <div id="aiRepSndCl" style="display:none">
          <label style="font-size:10px;color:var(--txt3);display:block;margin-bottom:3px">Cliente</label>
          <select id="aiRepCliSel" class="form-control form-control-sm" onchange="var o=this.options[this.selectedIndex];document.getElementById('aiRepPhone').value=o.dataset.phone||''">
            <option value="">— Selecione o cliente —</option>
            <?php foreach($clients as $cl): ?>
            <option value="<?=e($cl['id'])?>" data-phone="<?=e($cl['phone']??'')?>"><?=e($cl['name'])?><?php if($cl['phone']??''): ?> · <?=e($cl['phone'])?><?php endif; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="aiRepSndGr" style="display:none">
          <label style="font-size:10px;color:var(--txt3);display:block;margin-bottom:3px">Grupo WhatsApp</label>
          <div style="display:flex;gap:6px;margin-bottom:4px">
            <select id="aiRepGrpSel" class="form-control form-control-sm" style="flex:1">
              <option value="">— Selecione o grupo —</option>
            </select>
            <button type="button" onclick="aiRepLoadGroups()" class="btn btn-secondary btn-sm">🔄</button>
          </div>
          <div id="aiRepGrpStatus" style="font-size:10px;color:var(--txt3)"></div>
        </div>
      </div>
      <div style="margin-bottom:10px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3px">
          <label style="font-size:10px;font-weight:600;color:var(--txt2)">MENSAGEM <span style="font-weight:400;color:var(--txt3)">(editável)</span></label>
          <button onclick="aiRepRegenMsg()" style="font-size:10px;padding:2px 8px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--txt3);cursor:pointer;font-family:var(--font);display:flex;align-items:center;gap:4px">🔄 Gerar novo resumo</button>
        </div>
        <textarea id="aiRepMsg" style="width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:8px 10px;color:var(--txt);font-family:var(--font);font-size:11px;line-height:1.6;resize:vertical;min-height:100px;box-sizing:border-box"></textarea>
      </div>
      <button onclick="sendAiReport()" class="btn btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px;font-size:13px">
        <i class="fa-brands fa-whatsapp" style="font-size:15px"></i> Enviar Agora
      </button>
    </div>

    <!-- Histórico de chat -->
    <div id="aiRepChatHistory" style="max-height:280px;overflow-y:auto;padding:12px 20px;display:flex;flex-direction:column;gap:14px"></div>

    <!-- Input chat -->
    <div style="padding:10px 20px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:flex-end">
      <textarea id="aiRepChatInput"
        placeholder="Faça uma pergunta... Ex: Qual campanha pausar? Como melhorar o ROAS?"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAiRepChat();}"
        style="flex:1;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:8px 11px;color:var(--txt);font-family:var(--font);font-size:12px;resize:none;height:40px;outline:none;line-height:1.5"></textarea>
      <button id="aiRepChatBtn" onclick="sendAiRepChat()"
        style="background:var(--accent);border:none;border-radius:8px;width:38px;height:38px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round"/><path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
    <div style="padding:0 20px 8px;font-size:10px;color:var(--txt3)">Enter para enviar · Shift+Enter para nova linha</div>
  </div>

  <!-- Footer (mantido vazio para compatibilidade JS) -->
  <div id="aiRepFooter" style="display:none"></div>

</div>
</div>

<script>
/* ===== ORDENAÇÃO DA TABELA DE RELATÓRIOS ===== */
(function(){
  var currentSort = 'client_name';
  var currentDir  = 'asc';

  window.sortTable = function(btn) {
    var key = btn.dataset.sort;
    if (currentSort === key) {
      currentDir = currentDir === 'asc' ? 'desc' : 'asc';
    } else {
      currentSort = key;
      currentDir  = btn.dataset.dir || 'asc';
    }
    btn.dataset.dir = currentDir;

    // Atualiza visuais dos botões
    document.querySelectorAll('.sort-pill').forEach(function(b) {
      var active = b.dataset.sort === currentSort;
      b.style.border        = active ? '1px solid var(--accent)' : '1px solid var(--border)';
      b.style.background    = active ? 'var(--accent3)' : 'var(--bg3)';
      b.style.color         = active ? 'var(--accent)' : 'var(--txt2)';
      b.classList.toggle('active', active);
      var arrow = b.querySelector('.srt-arrow');
      if (arrow) arrow.textContent = active ? (currentDir === 'asc' ? '↑' : '↓') : '↕';
    });

    var tbody = document.querySelector('#rptTable tbody');
    if (!tbody) return;
    var rows = Array.from(tbody.querySelectorAll('tr[data-client_name]'));
    var sep  = Array.from(tbody.querySelectorAll('tr[data-group-sep]'));
    // Remove separadores antigos
    sep.forEach(function(s){ s.parentNode.removeChild(s); });

    rows.sort(function(a, b) {
      var va = (a.dataset[currentSort] || '').toLowerCase();
      var vb = (b.dataset[currentSort] || '').toLowerCase();
      if (va < vb) return currentDir === 'asc' ? -1 : 1;
      if (va > vb) return currentDir === 'asc' ? 1 : -1;
      // Secundário: próx. envio
      var na = (a.dataset.next_send_at || '9999').toLowerCase();
      var nb = (b.dataset.next_send_at || '9999').toLowerCase();
      return na < nb ? -1 : na > nb ? 1 : 0;
    });

    // Reinsere linhas ordenadas + separadores de empresa (quando sort=empresa)
    var lastGroup = null;
    rows.forEach(function(row) {
      tbody.appendChild(row);
    });
  };

  // Aplicar ordem padrão ao carregar
  document.addEventListener('DOMContentLoaded', function(){
    var defaultBtn = document.querySelector('.sort-pill[data-sort="client_name"]');
    if (defaultBtn) sortTable(defaultBtn);
  });
})();
</script>

<?php
$pageContent=ob_get_clean();
require_once __DIR__.'/../layouts/main.php';

