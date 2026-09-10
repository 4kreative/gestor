<?php
$pageTitle   = 'Alertas';
$currentPage = 'alerts';
ob_start();
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:12px">
  <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <div class="search-box">
      <span class="material-icons-outlined">search</span>
      <input type="text" name="q" placeholder="Buscar alerta..." value="<?= e($q??'') ?>">
    </div>
    <select name="status" class="form-control" style="width:140px">
      <option value="">Todos os status</option>
      <option value="ativo"   <?= ($status??'')==='ativo'?'selected':'' ?>>Ativos</option>
      <option value="inativo" <?= ($status??'')==='inativo'?'selected':'' ?>>Inativos</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  </form>
  <button class="btn btn-primary" onclick="openAlertModal()">
    <span class="material-icons-outlined">add_alert</span> Criar alerta
  </button>
</div>

<?php if (empty($alerts)): ?>
<div class="card">
  <div class="empty-state">
    <span class="material-icons-outlined" style="font-size:52px;color:var(--warn)">notifications_active</span>
    <h3>Nenhum alerta cadastrado</h3>
    <p>Receba notificações no WhatsApp quando o saldo estiver baixo</p>
    <button class="btn btn-primary" onclick="openAlertModal()">
      <span class="material-icons-outlined">add_alert</span> Criar primeiro alerta
    </button>
  </div>
</div>
<?php else: ?>
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th style="width:44px">Status</th>
        <th>Nome</th>
        <th>Canal</th>
        <th>Conta</th>
        <th>Tipo / Valor</th>
        <th>Próximo envio</th>
        <th>Último resultado</th>
        <th style="width:110px">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alerts as $a): ?>
      <tr>
        <td>
          <form method="POST" action="<?= APP_URL ?>/alerts/toggle">
            <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button type="submit" class="toggle-btn <?= $a['ativo']?'on':'off' ?>">
              <span class="toggle-knob"></span>
            </button>
          </form>
        </td>
        <td>
          <div style="font-weight:600;color:var(--txt)"><?= e($a['name']) ?></div>
          <?php if ($a['recipient_phone']): ?>
          <div style="font-size:11px;color:var(--txt3);margin-top:2px">
            <?php if (($a['recipient_type']??'phone')==='group'): ?>
              <i class="fa-solid fa-users" style="color:var(--accent)"></i> Grupo
            <?php else: ?>
              <i class="fa-brands fa-whatsapp" style="color:var(--success)"></i>
            <?php endif; ?>
            <?= e($a['recipient_phone']) ?>
          </div>
          <?php endif; ?>
        </td>
        <td>
          <?= $a['platform']==='meta'
            ? '<span style="color:#1877F2;font-size:13px;font-weight:600"><i class="fa-brands fa-facebook"></i> Meta</span>'
            : '<span style="color:#EA4335;font-size:13px;font-weight:600"><i class="fa-brands fa-google"></i> Google</span>' ?>
        </td>
        <td style="font-size:12px;color:var(--txt2)"><?= e($a['account_name'] ?? '—') ?></td>
        <td>
          <?php
            $typeLabels = ['saldo_minimo'=>['💰 Saldo','badge-warn'],'erro_conta'=>['❌ Erro','badge-danger'],'ctr_baixo'=>['📉 CTR','badge-info'],'cpc_alto'=>['💸 CPC','badge-danger'],'custo_conv_alto'=>['💵 Custo','badge-danger'],'roas_baixo'=>['📈 ROAS','badge-info']];
            $tInfo = $typeLabels[$a['type']??'saldo_minimo'] ?? ['💰 Saldo','badge-warn'];
          ?>
          <span class="badge <?= $tInfo[1] ?> badge-sm"><?= $tInfo[0] ?></span>
          <?php if(($a['type']??'saldo_minimo')==='saldo_minimo'): ?>
          <span style="font-size:11px;color:var(--txt3)"><?= brl((float)$a['saldo_minimo']) ?></span>
          <?php elseif(!empty($a['valor_threshold'])): ?>
          <span style="font-size:11px;color:var(--txt3)"><?= number_format((float)$a['valor_threshold'],2,',','.') ?></span>
          <?php endif; ?>
        </td>
        <td style="font-size:11px">
          <?php
            // Calcula próximo horário de envio
            $horariosArr = array_filter(array_map('trim', explode(',', $a['horarios']??'12:00')));
            $diasArr     = array_map('trim', explode(',', $a['dias_semana']??'1,2,3,4,5'));
            $now2        = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
            $proximoEnvio = null;
            // Tenta nos próximos 7 dias
            for ($d=0; $d<=6 && !$proximoEnvio; $d++) {
                $dia = clone $now2;
                if ($d > 0) $dia->modify("+{$d} day");
                $diaSemana = $dia->format('w');
                if (!in_array($diaSemana, $diasArr)) continue;
                foreach ($horariosArr as $h) {
                    [$hh,$mm] = explode(':', trim($h));
                    $candidate = clone $dia;
                    $candidate->setTime((int)$hh,(int)$mm,0);
                    if ($candidate > $now2) { $proximoEnvio = $candidate; break; }
                }
            }
          ?>
          <?php if (!$a['ativo']): ?>
            <span style="color:var(--txt3)">Inativo</span>
          <?php elseif (!empty($a['disparo_imediato'])): ?>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="color:var(--accent);font-size:10px;font-weight:600">⚡ Imediato (saldo)</span>
              <?php if ($proximoEnvio): ?>
              <span style="color:var(--txt3);font-size:10px">+ <?= $proximoEnvio->format('d/m H:i') ?></span>
              <?php endif; ?>
            </div>
          <?php elseif ($proximoEnvio): ?>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="color:var(--txt2);font-weight:500"><?= $proximoEnvio->format('d/m H:i') ?></span>
              <span style="color:var(--txt3);font-size:10px"><?= e(str_replace(',',', ',$a['horarios']??'')) ?></span>
            </div>
          <?php else: ?>
            <span style="color:var(--txt3)">Nenhum</span>
          <?php endif; ?>
        </td>
        <td>
          <?php
            $ultimoEnvioStr = $a['ultimo_envio'] ? (new DateTime($a['ultimo_envio'], new DateTimeZone(APP_TIMEZONE)))->format('d/m H:i') : null;
            $saldoLog       = $a['ultimo_saldo'] ?? null;
            $saldoStr       = ($saldoLog !== null && $saldoLog !== '') ? 'R$ '.number_format((float)$saldoLog,2,',','.') : null;
          ?>
          <?php if ($a['ultimo_status'] === 'enviado'): ?>
            <div style="display:flex;flex-direction:column;gap:2px;cursor:pointer" onclick="verLogs(<?= $a['id'] ?>, '<?= e(addslashes($a['name'])) ?>')">
              <span class="badge badge-sm" style="background:#e7f5ec;color:#128C7E;width:fit-content">
                <i class="fa-solid fa-check"></i>
                Enviado <?= $a['ultimo_tipo']==='manual'?'(manual)':'(auto)' ?>
              </span>
              <?php if ($ultimoEnvioStr): ?>
              <span style="font-size:10px;color:var(--txt3)"><?= $ultimoEnvioStr ?></span>
              <?php endif; ?>
              <?php if ($saldoStr): ?>
              <span style="font-size:10px;color:var(--txt2)">💰 Saldo: <?= $saldoStr ?></span>
              <?php endif; ?>
            </div>
          <?php elseif ($a['ultimo_status'] === 'erro'): ?>
            <div style="display:flex;flex-direction:column;gap:2px;cursor:pointer" onclick="verLogs(<?= $a['id'] ?>, '<?= e(addslashes($a['name'])) ?>')">
              <span class="badge badge-red badge-sm" style="width:fit-content" title="<?= e($a['ultimo_erro']??'') ?>">
                <i class="fa-solid fa-triangle-exclamation"></i> Erro — clique p/ ver
              </span>
              <?php if ($ultimoEnvioStr): ?>
              <span style="font-size:10px;color:var(--txt3)"><?= $ultimoEnvioStr ?></span>
              <?php endif; ?>
              <?php if (!empty($a['ultimo_erro'])): ?>
              <span style="font-size:10px;color:#e74c3c;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= e($a['ultimo_erro']) ?>"><?= e(mb_substr($a['ultimo_erro'],0,50)) ?></span>
              <?php endif; ?>
            </div>
          <?php elseif (!$a['ativo']): ?>
            <span style="font-size:11px;color:var(--txt3)">⏸ Inativo</span>
          <?php elseif (!empty($a['disparo_imediato'])): ?>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:11px;color:var(--accent);font-weight:600">⚡ Monitorando saldo</span>
              <span style="font-size:10px;color:var(--txt3)">Dispara ao cair abaixo de <?= brl((float)$a['saldo_minimo']) ?></span>
              <?php if ($proximoEnvio): ?>
              <span style="font-size:10px;color:var(--txt3)">+ Agendado <?= $proximoEnvio->format('d/m H:i') ?></span>
              <?php endif; ?>
            </div>
          <?php elseif ($proximoEnvio): ?>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:11px;color:var(--txt2);font-weight:600">🕐 Próximo envio</span>
              <span style="font-size:11px;color:var(--accent);font-weight:700"><?= $proximoEnvio->format('d/m H:i') ?></span>
              <span style="font-size:10px;color:var(--txt3)">Se saldo ≤ <?= brl((float)$a['saldo_minimo']) ?></span>
            </div>
          <?php else: ?>
            <span style="font-size:11px;color:var(--txt3)">Nenhum horário ativo</span>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:4px;align-items:center">
            <button type="button" class="btn btn-sm btn-icon" style="background:var(--success);color:#fff;border:none" title="Enviar agora"
              onclick="enviarManual(<?= $a['id'] ?>, '<?= e(addslashes($a['name'])) ?>')">
              <span class="material-icons-outlined" style="font-size:14px">send</span>
            </button>
            <a href="<?= APP_URL ?>/alerts/edit?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">
              <span class="material-icons-outlined" style="font-size:14px">edit</span>
            </a>
            <form method="POST" action="<?= APP_URL ?>/alerts/delete" style="display:inline">
              <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon" data-confirm="Excluir alerta «<?= e($a['name']) ?>»?">
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

<!-- ========== MODAL CRIAR ALERTA ========== -->
<div id="alertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:900px;margin:auto;display:grid;grid-template-columns:1fr 300px;overflow:hidden">
  <div style="padding:24px;overflow-y:auto;max-height:90vh">
    <div style="font-size:16px;font-weight:700;color:var(--txt);margin-bottom:20px;display:flex;align-items:center;gap:8px">
      <span class="material-icons-outlined" style="color:var(--warn)">add_alert</span> Criar alerta
    </div>
    <form method="POST" action="<?= APP_URL ?>/alerts/store" id="alertForm">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label class="form-label">Nome <span class="req">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="Ex: Alerta saldo baixo cliente X" required>
      </div>

      <div class="form-group">
        <label class="form-label">Tipo de alerta</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label class="type-card active" onclick="setType(this,'saldo_minimo')">
            <input type="radio" name="type" value="saldo_minimo" checked style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:var(--warn)">account_balance_wallet</span> Saldo mínimo
          </label>
          <label class="type-card" onclick="setType(this,'erro_conta')">
            <input type="radio" name="type" value="erro_conta" style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:var(--danger)">error_outline</span> Erro na conta
          </label>
          <label class="type-card" onclick="setType(this,'ctr_baixo')">
            <input type="radio" name="type" value="ctr_baixo" style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#3498DB">trending_down</span> CTR baixo
          </label>
          <label class="type-card" onclick="setType(this,'cpc_alto')">
            <input type="radio" name="type" value="cpc_alto" style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#E74C3C">price_change</span> CPC alto
          </label>
          <label class="type-card" onclick="setType(this,'custo_conv_alto')">
            <input type="radio" name="type" value="custo_conv_alto" style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#E67E22">money_off</span> Custo/conv alto
          </label>
          <label class="type-card" onclick="setType(this,'roas_baixo')">
            <input type="radio" name="type" value="roas_baixo" style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#F39C12">show_chart</span> ROAS baixo
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Canal <span class="req">*</span></label>
        <div style="display:flex;gap:8px">
          <label class="platform-card active" onclick="setPlatform(this,'meta')">
            <input type="radio" name="platform" value="meta" checked style="display:none">
            <i class="fa-brands fa-facebook" style="font-size:18px;color:#1877F2"></i>
            <span style="font-size:12px">Meta Ads</span>
          </label>
          <label class="platform-card" onclick="setPlatform(this,'google')">
            <input type="radio" name="platform" value="google" style="display:none">
            <i class="fa-brands fa-google" style="font-size:18px;color:#EA4335"></i>
            <span style="font-size:12px">Google Ads</span>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Conta de anúncio</label>
        <select name="ad_account_id" class="form-control" id="accSel">
          <option value="">— Todas as contas —</option>
          <?php foreach ($accounts as $acc): ?>
          <option value="<?= $acc['id'] ?>" data-p="<?= e($acc['platform']) ?>">[<?= strtoupper($acc['platform']) ?>] <?= e($acc['account_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" id="saldoGrp">
        <label class="form-label" id="limiarLabel">Saldo mínimo (R$)</label>
        <input type="number" name="saldo_minimo" class="form-control" value="60" min="0" step="0.01">
        <div class="form-hint" id="limiarHint">Alerta disparado quando saldo cair abaixo deste valor</div>
      </div>

      <!-- WhatsApp Instance -->
      <div class="form-group">
        <label class="form-label">Instância WhatsApp</label>
        <select name="whatsapp_id" class="form-control" id="wpInstSel" onchange="onInstanciaChange(this.value)">
          <option value="">— Selecione —</option>
          <?php foreach ($instances as $inst): ?>
          <option value="<?= $inst['id'] ?>"><?= e($inst['instance_name']) ?><?= $inst['phone_number']?' ('.$inst['phone_number'].')':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Cliente -->
      <div class="form-group">
        <label class="form-label">Cliente <span style="font-size:11px;color:var(--txt3)">(opcional — preenche telefone automaticamente)</span></label>
        <select name="client_id" class="form-control" id="clientSelModal" onchange="onClientChangeModal(this.value)">
          <option value="">— Selecione um cliente —</option>
          <?php foreach ($clients as $cli): ?>
          <option value="<?= $cli['id'] ?>"
            data-phone="<?= e($cli['phone'] ?? '') ?>"
            data-company="<?= e($cli['company'] ?? '') ?>"
            data-name="<?= e($cli['name'] ?? '') ?>">
            <?= e($cli['name']) ?><?= $cli['company'] ? ' — '.$cli['company'] : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tipo destino -->
      <div class="form-group">
        <label class="form-label">Tipo de destino</label>
        <div style="display:flex;gap:8px">
          <label class="dest-card active" onclick="setDestino(this,'phone')">
            <input type="radio" name="recipient_type" value="phone" checked style="display:none">
            <i class="fa-brands fa-whatsapp" style="color:#128C7E"></i>
            <span style="font-size:12px">Privado</span>
          </label>
          <label class="dest-card" onclick="setDestino(this,'group')">
            <input type="radio" name="recipient_type" value="group" style="display:none">
            <i class="fa-solid fa-users" style="color:var(--accent)"></i>
            <span style="font-size:12px">Grupo</span>
          </label>
        </div>
      </div>

      <!-- Campo hidden unificado para recipient_phone -->
      <input type="hidden" name="recipient_phone" id="recipientPhoneHidden">

      <!-- Telefone privado -->
      <div class="form-group" id="phoneGrp">
        <label class="form-label">Telefone para receber</label>
        <input type="text" class="form-control" id="phoneInput" placeholder="5511999999999" oninput="document.getElementById('recipientPhoneHidden').value=this.value">
        <div class="form-hint">DDI+DDD+número. Ex: 5511944445555</div>
      </div>

      <!-- Grupo -->
      <div class="form-group" id="grupoGrp" style="display:none">
        <label class="form-label">Grupo WhatsApp</label>
        <div style="display:flex;gap:8px">
          <select class="form-control" id="grupoSel" onchange="document.getElementById('recipientPhoneHidden').value=this.value">
            <option value="">— Selecione uma instância primeiro —</option>
          </select>
          <button type="button" class="btn btn-secondary btn-sm" onclick="carregarGrupos()" id="btnCarregarGrupos" style="white-space:nowrap">
            <span class="material-icons-outlined" style="font-size:14px">refresh</span> Buscar
          </button>
        </div>
        <div class="form-hint" id="grupoHint">Selecione a instância e clique em Buscar para listar os grupos.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Horários de envio</label>
        <div id="horariosWrap" style="display:flex;flex-direction:column;gap:6px">
          <div class="horario-row" style="display:flex;align-items:center;gap:8px">
            <input type="time" name="horarios_list[]" class="form-control" value="12:00" style="width:130px">
            <button type="button" onclick="addHorario()" class="btn btn-secondary btn-sm">+ Horário</button>
          </div>
        </div>
        <input type="hidden" name="horarios" id="horariosHidden">
        <div class="form-hint">Adicione quantos horários quiser. O cron verificará a cada minuto.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Dias da semana</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach (['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','0'=>'Dom'] as $v=>$l): ?>
          <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--txt2);cursor:pointer;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:5px 10px">
            <input type="checkbox" name="dias_semana[]" value="<?= $v ?>" <?= in_array($v,['1','2','3','4','5'])?'checked':'' ?> style="accent-color:var(--accent)"> <?= $l ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--txt2);cursor:pointer">
          <input type="checkbox" name="inativar_apos" value="1" style="accent-color:var(--accent)"> Inativar após o envio
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--txt2);cursor:pointer">
          <input type="checkbox" name="receber_email" value="1" style="accent-color:var(--accent)"> Receber no e-mail
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--txt2);cursor:pointer">
          <input type="checkbox" name="disparo_imediato" value="1" checked style="accent-color:var(--accent)"> Disparar imediatamente quando saldo cair
        </label>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" onclick="collectHorarios()">
          <span class="material-icons-outlined">add_alert</span> Criar alerta
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeAlertModal()">Cancelar</button>
      </div>
    </form>
  </div>

  <!-- Preview -->
  <div style="background:var(--bg3);border-left:1px solid var(--border);padding:20px;display:flex;flex-direction:column;gap:12px">
    <div style="font-size:13px;font-weight:600;color:var(--txt)">Mensagem do alerta</div>
    <?php if (!empty($templates)): ?>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--txt3);text-transform:uppercase;display:block;margin-bottom:4px">Usar template</label>
      <select id="tplSelectCreate" class="form-control" style="font-size:12px" onchange="aplicarTemplateCreate(this.value)">
        <option value="">— Selecione um template —</option>
        <?php foreach ($templates as $tpl): ?>
        <option value="<?= htmlspecialchars($tpl['content']) ?>"><?= htmlspecialchars($tpl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div style="background:#128C7E;border-radius:12px;padding:14px;color:#fff;font-size:12px;line-height:1.7" id="alertPreview">
      🚨<strong>*Aviso – Saldo Meta ADS*</strong><br><br>Olá, <strong>*{primeiro_nome}*</strong>! 👋<br>🏢 Empresa: <strong>*{empresa}*</strong><br>💰 Saldo atual: <strong>*{saldo}*</strong><br><br>⚠️ É necessário realizar uma recarga para evitar a pausa dos anúncios.<br><br>Qual valor para gerar o Pix?<br><br>— Equipe EMPRESA 🚀<br>(Mensagem Automatica)
    </div>
    <textarea name="message" form="alertForm" id="alertMsg" class="form-control" rows="5"
      oninput="updatePreview(this.value)"
      placeholder="Personalize a mensagem...">🚨*Aviso – Saldo Meta ADS*

Olá, *{primeiro_nome}*! 👋
🏢 Empresa: *{empresa}*
💰 Saldo atual: *{saldo}*

⚠️ É necessário realizar uma recarga para evitar a pausa dos anúncios.

Qual valor para gerar o Pix?

— Equipe EMPRESA 🚀
(Mensagem Automatica)</textarea>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--txt3);text-transform:uppercase;margin-bottom:8px">Variáveis</div>
      <?php foreach ([
        '{primeiro_nome}' => 'Primeiro nome',
        '{nome_completo}' => 'Nome completo',
        '{empresa}' => 'Empresa',
        '{conta_anuncio}' => 'Conta de anúncio',
        '{periodo}' => 'Período',
        '{hoje}' => 'Data de hoje',
        '{hora}' => 'Hora atual',
        '{data_hora}' => 'Data e hora',
        '{investimento}' => 'Investimento (R$)',
        '{saldo}' => 'Saldo atual (R$)',
        '{saldo_minimo}' => 'Saldo mínimo',
        '{alcance}' => 'Alcance',
        '{impressoes}' => 'Impressões',
        '{frequencia}' => 'Frequência',
        '{cliques}' => 'Cliques',
        '{ctr}' => 'CTR (%)',
        '{cpc}' => 'CPC (R$)',
        '{cpm}' => 'CPM (R$)',
        '{msg}' => 'Conversas iniciadas',
        '{cmsg}' => 'Custo por mensagem',
        '{custo_mensagem}' => 'Custo por mensagem (alt)',
        '{custo_conversa}' => 'Custo por conversa',
        '{conversoes}' => 'Conversões',
        '{leads}' => 'Leads',
        '{cpl}' => 'Custo por lead',
        '{roas}' => 'ROAS',
        '{thruplay}' => 'ThruPlay',
        '{profile_visit}' => 'Visitas ao perfil',
        '{metrica_atual}' => 'Valor atual da métrica',
        '{limiar}' => 'Valor limite configurado',
        '{tipo_alerta}' => 'Tipo do alerta',
        '{plataforma}' => 'Plataforma',
        '{nome_alerta}' => 'Nome do alerta',
      ] as $tag => $lbl): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--txt2)"><?= $lbl ?></span>
        <button type="button" onclick="insertAlertVar('<?= $tag ?>')" style="background:none;border:none;color:var(--accent);font-family:monospace;font-size:11px;cursor:pointer;padding:0"><?= $tag ?></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<!-- ========== MODAL ENVIO MANUAL ========== -->
<div id="modalEnvioManual" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:420px;padding:28px;margin:20px">
    <div style="font-size:15px;font-weight:700;color:var(--txt);margin-bottom:6px;display:flex;align-items:center;gap:8px">
      <span class="material-icons-outlined" style="color:var(--success)">send</span> Enviar alerta agora
    </div>
    <p style="font-size:13px;color:var(--txt2);margin-bottom:20px">
      Envia o alerta <strong id="alertNomeManual"></strong> imediatamente, verificando o saldo atual e disparando a mensagem configurada.
    </p>
    <div id="resultadoEnvio" style="display:none;margin-bottom:16px;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;align-items:center;gap:8px"></div>
    <form id="formEnvioManual">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="id" id="alertIdManual">
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-success" id="btnEnviarManual">
          <span class="material-icons-outlined" style="font-size:15px">send</span> Confirmar envio
        </button>
        <button type="button" class="btn btn-secondary" id="btnFecharManual" onclick="fecharModalManual()">Fechar</button>
      </div>
    </form>
  </div>
</div>

<!-- ========== MODAL LOGS ========== -->
<div id="modalLogs" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:600px;padding:28px;margin:20px;max-height:80vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-size:15px;font-weight:700;color:var(--txt)">
        <span class="material-icons-outlined" style="color:var(--accent);vertical-align:middle">history</span>
        Histórico — <span id="logNomeAlerta"></span>
      </div>
      <button onclick="document.getElementById('modalLogs').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--txt3)">
        <span class="material-icons-outlined">close</span>
      </button>
    </div>
    <div id="logsConteudo">
      <div style="text-align:center;padding:30px;color:var(--txt3)">Carregando...</div>
    </div>
  </div>
</div>

<style>
.type-card{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--border2);border-radius:var(--radius);cursor:pointer;font-size:13px;font-weight:500;color:var(--txt2);background:var(--bg3)}
.type-card.active{border-color:var(--accent);background:var(--accent3);color:var(--accent)}
.platform-card{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 18px;border:1px solid var(--border2);border-radius:var(--radius2);cursor:pointer;font-size:12px;font-weight:600;color:var(--txt2);background:var(--bg3);min-width:90px}
.platform-card.active{border-color:var(--accent);background:var(--accent3)}
.dest-card{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 18px;border:1px solid var(--border2);border-radius:var(--radius2);cursor:pointer;font-size:12px;font-weight:600;color:var(--txt2);background:var(--bg3);min-width:100px}
.dest-card.active{border-color:var(--accent);background:var(--accent3)}
.toggle-btn{width:40px;height:22px;border-radius:11px;border:none;cursor:pointer;position:relative;transition:background .2s;background:var(--bg5)}
.toggle-btn.on{background:var(--success)}
.toggle-knob{position:absolute;top:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:left .2s;left:3px}
.toggle-btn.on .toggle-knob{left:21px}
.btn-success{background:var(--success)!important;color:#fff!important;border:none!important}
/* ── Animação botão Enviar ── */
.snd-dots-wrap{display:inline-flex;align-items:center;gap:3px;vertical-align:middle}
.snd-dot{width:5px;height:5px;border-radius:50%;background:currentColor;animation:sndBounce 1.1s infinite ease-in-out both}
.snd-dot:nth-child(1){animation-delay:-.32s}
.snd-dot:nth-child(2){animation-delay:-.16s}
@keyframes sndBounce{0%,80%,100%{transform:scale(0);opacity:.4}40%{transform:scale(1);opacity:1}}
@keyframes sndPop{0%{transform:scale(0)}70%{transform:scale(1.3)}100%{transform:scale(1)}}
</style>

<script>
var CSRF = '<?= e($_SESSION['csrf_token']) ?>';
var APP_URL = '<?= APP_URL ?>';

// ---------- Modal criar ----------
function aplicarTemplateCreate(txt) {
  if (!txt) return;
  document.getElementById('alertMsg').value = txt;
  updatePreview(txt);
  document.getElementById('tplSelectCreate').value = '';
}
function openAlertModal(){ document.getElementById('alertModal').style.display='flex'; }
function closeAlertModal(){ document.getElementById('alertModal').style.display='none'; }
document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ closeAlertModal(); fecharModalManual(); document.getElementById('modalLogs').style.display='none'; }});

function setType(el,val){
  document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active'); el.querySelector('input').checked=true;
  var perf=['ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo'];
  document.getElementById('saldoGrp').style.display=(val==='saldo_minimo'||perf.includes(val))?'':'none';
  var lbl=document.getElementById('limiarLabel');
  var hint=document.getElementById('limiarHint');
  if(val==='saldo_minimo'){lbl.textContent='Saldo mínimo (R$)';hint.textContent='Dispara quando saldo cair abaixo deste valor';}
  else if(val==='ctr_baixo'){lbl.textContent='CTR mínimo (%)';hint.textContent='Dispara quando CTR dos últimos 7 dias ficar abaixo deste valor';}
  else if(val==='cpc_alto'){lbl.textContent='CPC máximo (R$)';hint.textContent='Dispara quando CPC dos últimos 7 dias ultrapassar este valor';}
  else if(val==='custo_conv_alto'){lbl.textContent='Custo/conv máximo (R$)';hint.textContent='Dispara quando custo por conversa/resultado ultrapassar este valor';}
  else if(val==='roas_baixo'){lbl.textContent='ROAS mínimo (x)';hint.textContent='Dispara quando ROAS dos últimos 7 dias ficar abaixo deste valor';}
  else{lbl.textContent='Limiar';hint.textContent='';}
}
function setPlatform(el,val){
  document.querySelectorAll('.platform-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active'); el.querySelector('input').checked=true;
  document.querySelectorAll('#accSel option').forEach(o=>{
    o.style.display=(o.value===''||o.dataset.p===val)?'':'none';
  });
  document.getElementById('accSel').value='';
}
function setDestino(el,val){
  document.querySelectorAll('.dest-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active'); el.querySelector('input').checked=true;
  document.getElementById('phoneGrp').style.display = val==='phone' ? '' : 'none';
  document.getElementById('grupoGrp').style.display  = val==='group'   ? '' : 'none';
  // limpa e sincroniza o campo hidden
  if(val==='group'){
    document.getElementById('phoneInput').value='';
    document.getElementById('recipientPhoneHidden').value=document.getElementById('grupoSel').value||'';
    // Auto-carrega grupos se já tiver instância selecionada
    var wpId=document.getElementById('wpInstSel').value;
    if(wpId) carregarGrupos();
  } else {
    document.getElementById('recipientPhoneHidden').value=document.getElementById('phoneInput').value||'';
  }
}
function onInstanciaChange(val){
  document.getElementById('grupoSel').innerHTML='<option value="">— Clique em Buscar para carregar —</option>';
  document.getElementById('recipientPhoneHidden').value='';
}
function carregarGrupos(){
  var wpId = document.getElementById('wpInstSel').value;
  if(!wpId){ alert('Selecione uma instância primeiro.'); return; }
  var btn = document.getElementById('btnCarregarGrupos');
  btn.disabled=true; btn.innerHTML='<span class="material-icons-outlined" style="font-size:14px;animation:spin 1s linear infinite">refresh</span>';
  fetch(APP_URL+'/alerts/fetchGroups?whatsapp_id='+wpId)
    .then(r=>r.json()).then(function(data){
      btn.disabled=false; btn.innerHTML='<span class="material-icons-outlined" style="font-size:14px">refresh</span> Buscar';
      if(!data.success){ document.getElementById('grupoHint').textContent='Erro: '+(data.error||'Falha ao buscar'); return; }
      var sel = document.getElementById('grupoSel');
      sel.innerHTML='<option value="">— Selecione o grupo —</option>';
      data.grupos.forEach(function(g){
        var o=document.createElement('option'); o.value=g.id; o.textContent=g.nome+' ('+g.id+')';
        sel.appendChild(o);
      });
      document.getElementById('grupoHint').textContent=data.grupos.length+' grupo(s) encontrado(s)';
    }).catch(function(){ btn.disabled=false; btn.innerHTML='<span class="material-icons-outlined" style="font-size:14px">refresh</span> Buscar'; });
}

function addHorario(){
  var wrap=document.getElementById('horariosWrap');
  var row=document.createElement('div');
  row.className='horario-row'; row.style.cssText='display:flex;align-items:center;gap:8px';
  row.innerHTML='<input type="time" name="horarios_list[]" class="form-control" value="08:00" style="width:130px"><button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm btn-icon"><span class="material-icons-outlined" style="font-size:14px">close</span></button>';
  wrap.appendChild(row);
}
function collectHorarios(){
  var vals=Array.from(document.querySelectorAll('input[name="horarios_list[]"]')).map(i=>i.value).filter(Boolean);
  document.getElementById('horariosHidden').value=vals.join(',');
}
function updatePreview(val){
  document.getElementById('alertPreview').innerHTML=val
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\n/g,'<br>').replace(/\{([^}]+)\}/g,'<strong>{$1}</strong>');
}
function insertAlertVar(tag){
  var ta=document.getElementById('alertMsg');
  var s=ta.selectionStart,e=ta.selectionEnd;
  ta.value=ta.value.substring(0,s)+tag+ta.value.substring(e);
  ta.selectionStart=ta.selectionEnd=s+tag.length;
  ta.focus(); updatePreview(ta.value);
}
function onClientChangeModal(clientId){
  var sel=document.getElementById('clientSelModal');
  var opt=sel.options[sel.selectedIndex];
  if(!clientId) return;
  var phone=opt.dataset.phone||'';
  if(phone){
    var phoneInput=document.getElementById('phoneInput');
    if(phoneInput) phoneInput.value=phone.replace(/\D/g,'');
    var hidden=document.getElementById('recipientPhoneHidden');
    if(hidden) hidden.value=phone.replace(/\D/g,'');
  }
}

// ---------- Envio manual ----------
function enviarManual(id,nome){
  document.getElementById('alertIdManual').value=id;
  document.getElementById('alertNomeManual').textContent=nome;
  var res=document.getElementById('resultadoEnvio');
  res.style.display='none'; res.innerHTML='';
  var btn=document.getElementById('btnEnviarManual');
  btn.disabled=false;
  btn.style.background='';
  btn.innerHTML='<span class="material-icons-outlined" style="font-size:15px">send</span> Confirmar envio';
  document.getElementById('btnFecharManual').textContent='Fechar';
  document.getElementById('modalEnvioManual').style.display='flex';
}
function fecharModalManual(){ document.getElementById('modalEnvioManual').style.display='none'; }

document.getElementById('formEnvioManual').addEventListener('submit',function(e){
  e.preventDefault();
  var btn=document.getElementById('btnEnviarManual');
  var res=document.getElementById('resultadoEnvio');

  // ── Animação "Enviando..." ──
  btn.disabled=true;
  btn.innerHTML=
    '<span style="display:inline-flex;align-items:center;gap:6px">'
    +'<span class="snd-dots-wrap"><span class="snd-dot"></span><span class="snd-dot"></span><span class="snd-dot"></span></span>'
    +' Enviando</span>';
  res.style.display='none';

  var fd=new FormData(this);
  fetch(APP_URL+'/alerts/send-manual',{method:'POST',body:fd})
    .then(r=>r.json()).then(function(data){
      if(data.success){
        // ── Animação "Enviado!" ──
        btn.innerHTML='<span style="display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-check" style="animation:sndPop .3s ease"></i> Enviado!</span>';
        btn.style.background='var(--success)';
        res.style.cssText='display:flex;background:#e8faf0;border:1px solid #a3d9b1;color:#1a6b3a;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
        res.innerHTML='<i class="fa-solid fa-circle-check" style="font-size:16px;color:#28a745"></i> <span><strong>Enviado com sucesso!</strong>'+(data.saldo?' — Saldo atual: '+data.saldo:'')+'</span>';
        document.getElementById('btnFecharManual').textContent='Fechar';
        setTimeout(function(){ document.getElementById('modalEnvioManual').style.display='none'; location.reload(); },2200);
      } else {
        btn.disabled=false;
        btn.innerHTML='<span class="material-icons-outlined" style="font-size:15px">send</span> Tentar novamente';
        res.style.cssText='display:flex;background:#fdecea;border:1px solid #f5c2c7;color:#842029;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
        res.innerHTML='<i class="fa-solid fa-triangle-exclamation" style="font-size:16px"></i> <strong>Erro:</strong> '+(data.error||'Falha desconhecida');
      }
    }).catch(function(){
      btn.disabled=false;
      btn.innerHTML='<span class="material-icons-outlined" style="font-size:15px">send</span> Tentar novamente';
      res.style.cssText='display:flex;background:#fdecea;border:1px solid #f5c2c7;color:#842029;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;align-items:center;gap:8px;margin-bottom:16px';
      res.innerHTML='<i class="fa-solid fa-triangle-exclamation" style="font-size:16px"></i> Erro de comunicação com o servidor.';
    });
});

// ---------- Logs ----------
function verLogs(id,nome){
  document.getElementById('logNomeAlerta').textContent=nome;
  document.getElementById('logsConteudo').innerHTML='<div style="text-align:center;padding:30px;color:var(--txt3)">Carregando...</div>';
  document.getElementById('modalLogs').style.display='flex';
  fetch(APP_URL+'/alerts/logs?id='+id)
    .then(r=>r.json()).then(function(data){
      if(!data.success||!data.logs.length){
        document.getElementById('logsConteudo').innerHTML='<div style="text-align:center;padding:30px;color:var(--txt3)">Nenhum registro encontrado.</div>';
        return;
      }
      var html='<table style="width:100%;font-size:12px;border-collapse:collapse">'
        +'<thead><tr style="border-bottom:1px solid var(--border)">'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Data</th>'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Status</th>'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Tipo</th>'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Saldo</th>'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Destino</th>'
        +'<th style="padding:8px 6px;text-align:left;color:var(--txt3)">Erro</th>'
        +'</tr></thead><tbody>';
      data.logs.forEach(function(l){
        var statusBadge = l.status==='enviado'
          ? '<span style="background:#e7f5ec;color:#128C7E;padding:2px 8px;border-radius:20px"><i class="fa-solid fa-check"></i> Enviado</span>'
          : '<span style="background:#fdecea;color:#c62828;padding:2px 8px;border-radius:20px"><i class="fa-solid fa-xmark"></i> Erro</span>';
        var tipoBadge = l.tipo_envio==='manual'
          ? '<span style="background:var(--bg3);color:var(--txt2);padding:2px 8px;border-radius:20px">Manual</span>'
          : '<span style="background:var(--bg3);color:var(--txt2);padding:2px 8px;border-radius:20px">Auto</span>';
        html+='<tr style="border-bottom:1px solid var(--border)">'
          +'<td style="padding:8px 6px;color:var(--txt2)">'+l.created_at+'</td>'
          +'<td style="padding:8px 6px">'+statusBadge+'</td>'
          +'<td style="padding:8px 6px">'+tipoBadge+'</td>'
          +'<td style="padding:8px 6px;color:var(--txt2)">'+(l.saldo ? 'R$ '+parseFloat(l.saldo).toFixed(2).replace('.',',') : '—')+'</td>'
          +'<td style="padding:8px 6px;color:var(--txt2);max-width:120px;overflow:hidden;text-overflow:ellipsis">'+( l.destinatario||'—')+'</td>'
          +'<td style="padding:8px 6px;color:#c62828;max-width:160px">'+(l.erro_msg||'—')+'</td>'
          +'</tr>';
      });
      html+='</tbody></table>';
      document.getElementById('logsConteudo').innerHTML=html;
    });
}

// Animação spin
var style=document.createElement('style');
style.textContent='@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(style);
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
?>
