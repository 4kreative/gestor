<?php
$pageTitle   = 'Editar Alerta';
$currentPage = 'alerts';
ob_start();

$horariosList = explode(',', $alert['horarios'] ?? '12:00');
$diasAtivos   = explode(',', $alert['dias_semana'] ?? '1,2,3,4,5');
?>

<div style="margin-bottom:18px;display:flex;align-items:center;gap:8px">
  <a href="<?= APP_URL ?>/alerts" class="btn btn-secondary btn-sm">
    <span class="material-icons-outlined" style="font-size:14px">arrow_back</span> Voltar
  </a>
  <span style="font-size:16px;font-weight:700;color:var(--txt)">Editar Alerta</span>
</div>

<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:900px;display:grid;grid-template-columns:1fr 300px;overflow:hidden">

  <!-- Formulário -->
  <div style="padding:24px;overflow-y:auto;max-height:calc(100vh - 120px)">
    <form method="POST" action="<?= APP_URL ?>/alerts/update" id="alertForm" onsubmit="collectHorarios()">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="id" value="<?= $alert['id'] ?>">

      <div class="form-group">
        <label class="form-label">Nome <span class="req">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= e($alert['name']) ?>" required>
      </div>

      <!-- Tipo -->
      <div class="form-group">
        <label class="form-label">Tipo de alerta</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label class="type-card <?= $alert['type']==='saldo_minimo'?'active':'' ?>" onclick="setType(this,'saldo_minimo')">
            <input type="radio" name="type" value="saldo_minimo" <?= $alert['type']==='saldo_minimo'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:var(--warn)">account_balance_wallet</span> Saldo mínimo
          </label>
          <label class="type-card <?= $alert['type']==='erro_conta'?'active':'' ?>" onclick="setType(this,'erro_conta')">
            <input type="radio" name="type" value="erro_conta" <?= $alert['type']==='erro_conta'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:var(--danger)">error_outline</span> Erro na conta
          </label>
          <label class="type-card <?= $alert['type']==='ctr_baixo'?'active':'' ?>" onclick="setType(this,'ctr_baixo')">
            <input type="radio" name="type" value="ctr_baixo" <?= $alert['type']==='ctr_baixo'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#3498DB">trending_down</span> CTR baixo
          </label>
          <label class="type-card <?= $alert['type']==='cpc_alto'?'active':'' ?>" onclick="setType(this,'cpc_alto')">
            <input type="radio" name="type" value="cpc_alto" <?= $alert['type']==='cpc_alto'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#E74C3C">price_change</span> CPC alto
          </label>
          <label class="type-card <?= $alert['type']==='custo_conv_alto'?'active':'' ?>" onclick="setType(this,'custo_conv_alto')">
            <input type="radio" name="type" value="custo_conv_alto" <?= $alert['type']==='custo_conv_alto'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#E67E22">money_off</span> Custo/conv alto
          </label>
          <label class="type-card <?= $alert['type']==='roas_baixo'?'active':'' ?>" onclick="setType(this,'roas_baixo')">
            <input type="radio" name="type" value="roas_baixo" <?= $alert['type']==='roas_baixo'?'checked':'' ?> style="display:none">
            <span class="material-icons-outlined" style="font-size:15px;color:#F39C12">show_chart</span> ROAS baixo
          </label>
        </div>
      </div>

      <!-- Canal -->
      <div class="form-group">
        <label class="form-label">Canal <span class="req">*</span></label>
        <div style="display:flex;gap:8px">
          <label class="platform-card <?= $alert['platform']==='meta'?'active':'' ?>" onclick="setPlatform(this,'meta')">
            <input type="radio" name="platform" value="meta" <?= $alert['platform']==='meta'?'checked':'' ?> style="display:none">
            <i class="fa-brands fa-facebook" style="font-size:18px;color:#1877F2"></i>
            <span style="font-size:12px">Meta Ads</span>
          </label>
          <label class="platform-card <?= $alert['platform']==='google'?'active':'' ?>" onclick="setPlatform(this,'google')">
            <input type="radio" name="platform" value="google" <?= $alert['platform']==='google'?'checked':'' ?> style="display:none">
            <i class="fa-brands fa-google" style="font-size:18px;color:#EA4335"></i>
            <span style="font-size:12px">Google Ads</span>
          </label>
        </div>
      </div>

      <!-- Conta -->
      <div class="form-group">
        <label class="form-label">Conta de anúncio</label>
        <select name="ad_account_id" class="form-control" id="accSel">
          <option value="">— Todas as contas —</option>
          <?php foreach ($accounts as $acc): ?>
          <option value="<?= $acc['id'] ?>" data-p="<?= e($acc['platform']) ?>" <?= $alert['ad_account_id']==$acc['id']?'selected':'' ?>>
            [<?= strtoupper($acc['platform']) ?>] <?= e($acc['account_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Saldo mínimo -->
      <?php $perfTypes=['erro_conta','ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo']; ?>
      <!-- Saldo mínimo (só para saldo_minimo) -->
      <div class="form-group" id="saldoGrp" style="<?= in_array($alert['type'],$perfTypes)?'display:none':'' ?>">
        <label class="form-label">Saldo mínimo (R$)</label>
        <input type="number" name="saldo_minimo" class="form-control" value="<?= $alert['saldo_minimo'] ?>" min="0" step="0.01">
        <div class="form-hint">Alerta disparado quando saldo cair abaixo deste valor</div>
      </div>

      <!-- Modo de disparo (só para erro_conta) -->
      <div class="form-group" id="modoDisparoGrp" style="<?= $alert['type']==='erro_conta' ? '' : 'display:none' ?>">
        <label class="form-label">Modo de disparo</label>
        <div style="display:flex;gap:0;border:1px solid var(--border2);border-radius:10px;overflow:hidden;width:fit-content">
          <label id="btnInteligente" style="
            display:flex;align-items:center;gap:8px;padding:10px 20px;cursor:pointer;font-size:13px;font-weight:600;
            background:<?= ($alert['modo_disparo']??'agendado')==='inteligente'?'var(--accent)':'var(--bg3)' ?>;
            color:<?= ($alert['modo_disparo']??'agendado')==='inteligente'?'#fff':'var(--txt2)' ?>;
            border-right:1px solid var(--border2);transition:all .2s">
            <input type="radio" name="modo_disparo" value="inteligente"
              <?= ($alert['modo_disparo']??'agendado')==='inteligente'?'checked':'' ?>
              style="display:none" onchange="onModoDisparo()">
            🔔 Inteligente
          </label>
          <label id="btnAgendado" style="
            display:flex;align-items:center;gap:8px;padding:10px 20px;cursor:pointer;font-size:13px;font-weight:600;
            background:<?= ($alert['modo_disparo']??'agendado')==='agendado'?'var(--accent)':'var(--bg3)' ?>;
            color:<?= ($alert['modo_disparo']??'agendado')==='agendado'?'#fff':'var(--txt2)' ?>;
            transition:all .2s">
            <input type="radio" name="modo_disparo" value="agendado"
              <?= ($alert['modo_disparo']??'agendado')==='agendado'?'checked':'' ?>
              style="display:none" onchange="onModoDisparo()">
            📅 Agendado
          </label>
        </div>
        <div id="modoInteligenteHint" style="<?= ($alert['modo_disparo']??'agendado')==='inteligente'?'':'display:none' ?>;margin-top:8px;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--txt2)">
          ⚡ Envia <strong>imediatamente</strong> ao detectar erro. Se não resolvido, reenvio em <strong>6h</strong>. Quando resolvido, para automaticamente.
        </div>
        <div id="modoAgendadoHint" style="<?= ($alert['modo_disparo']??'agendado')==='agendado'?'':'display:none' ?>;margin-top:8px;background:var(--bg3);border:1px solid var(--border2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--txt2)">
          📅 Envia apenas no <strong>horário programado</strong> abaixo, independente de quando o erro ocorreu.
        </div>
      </div>

      <!-- Threshold para tipos de performance -->
      <?php $perfLabels=['ctr_baixo'=>['CTR mínimo (%)','Dispara quando CTR cair abaixo deste valor'],'cpc_alto'=>['CPC máximo (R$)','Dispara quando CPC ultrapassar este valor'],'custo_conv_alto'=>['Custo/conv máximo (R$)','Dispara quando custo por conversa ultrapassar este valor'],'roas_baixo'=>['ROAS mínimo (x)','Dispara quando ROAS cair abaixo deste valor']]; ?>
      <div class="form-group" id="perfGrp" style="<?= in_array($alert['type'],array_keys($perfLabels))?'':'display:none' ?>">
        <label class="form-label" id="perfLbl"><?= ($perfLabels[$alert['type'] ?? ''] ?? ['Valor limite'])[0] ?></label>
        <input type="number" name="threshold" id="thresholdInp" class="form-control" value="<?= htmlspecialchars($alert['valor_threshold'] ?? $alert['threshold'] ?? '') ?>" min="0" step="0.01">
        <div class="form-hint" id="perfHint"><?= ($perfLabels[$alert['type'] ?? ''] ?? ['',''])[1] ?></div>
      </div>

      <!-- Período de análise (só para métricas, não para saldo) -->
      <div class="form-group" id="periodoGrp" style="<?= in_array($alert['type'],array_keys($perfLabels))?'':'display:none' ?>">
        <label class="form-label">Período de análise</label>
        <select name="period_type" id="periodTypeSelect" class="form-control">
          <?php
          $periodOpts = [
            'today'        => 'Hoje',
            'yesterday'    => 'Ontem',
            'last_3_days'  => 'Últimos 3 dias',
            'last_7_days'  => 'Últimos 7 dias',
            'last_14_days' => 'Últimos 14 dias',
            'last_30_days' => 'Últimos 30 dias',
            'this_week'    => 'Esta semana',
            'this_month'   => 'Este mês',
            'last_month'   => 'Mês passado',
            'max'          => 'Máximo (desde o início)',
          ];
          $selPeriod = $alert['period_type'] ?? 'last_7_days';
          foreach ($periodOpts as $val => $label):
          ?>
          <option value="<?= $val ?>" <?= $selPeriod===$val?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <!-- Período personalizado -->
        <div id="customPeriodWrap" style="<?= str_starts_with($alert['period_type']??'','custom')?'':'display:none' ?>;margin-top:8px;display:flex;gap:8px">
          <?php
            $customParts = explode('|', $alert['period_type'] ?? '');
            $customStart = $customParts[1] ?? date('Y-m-d', strtotime('-7 days'));
            $customEnd   = $customParts[2] ?? date('Y-m-d');
          ?>
          <input type="date" id="customStart" class="form-control" value="<?= $customStart ?>" style="flex:1">
          <input type="date" id="customEnd"  class="form-control" value="<?= $customEnd ?>"  style="flex:1">
        </div>
        <div class="form-hint">Período usado para calcular a métrica e comparar com o limite</div>
      </div>

      <!-- WhatsApp -->
      <div class="form-group">
        <label class="form-label">Instância WhatsApp</label>
        <?php $defaultInstId = $instances[0]['id'] ?? null; ?>
        <select name="whatsapp_id" class="form-control">
          <option value="">— Selecione —</option>
          <?php foreach ($instances as $inst): ?>
          <?php $selInst = ($alert['whatsapp_id'] ?? $defaultInstId) == $inst['id']; ?>
          <option value="<?= $inst['id'] ?>" <?= $selInst?'selected':'' ?>>
            <?= e($inst['instance_name']) ?><?= $inst['phone_number']?' ('.$inst['phone_number'].')':'' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Cliente -->
      <div class="form-group">
        <label class="form-label">Cliente <span style="font-size:11px;color:var(--txt3)">(opcional — preenche telefone automaticamente)</span></label>
        <!-- Select oculto para envio do formulário -->
        <select name="client_id" id="clientSel" style="display:none">
          <option value="">— Selecione um cliente —</option>
          <?php foreach ($clients as $cli): ?>
          <option value="<?= $cli['id'] ?>"
            data-phone="<?= e($cli['phone'] ?? '') ?>"
            data-company="<?= e($cli['company'] ?? '') ?>"
            data-name="<?= e($cli['name'] ?? '') ?>"
            <?= ($alert['client_id'] ?? 0) == $cli['id'] ? 'selected' : '' ?>>
            <?= e($cli['name']) ?><?= $cli['company'] ? ' — '.$cli['company'] : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <!-- Dropdown customizado -->
        <div class="custom-select-wrap" id="clientDropWrap">
          <div class="custom-select-trigger" id="clientDropTrigger" onclick="toggleClientDrop()">
            <span id="clientDropLabel">— Selecione um cliente —</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" viewBox="0 0 12 8"><path fill="#888" d="M1 1l5 5 5-5"/></svg>
          </div>
          <div class="custom-select-options" id="clientDropOptions">
            <div class="custom-select-option" data-value="" onclick="selectClient('','','','','— Selecione um cliente —')">— Selecione um cliente —</div>
            <?php foreach ($clients as $cli): ?>
            <div class="custom-select-option"
              data-value="<?= $cli['id'] ?>"
              data-phone="<?= e($cli['phone'] ?? '') ?>"
              data-company="<?= e($cli['company'] ?? '') ?>"
              data-name="<?= e($cli['name'] ?? '') ?>"
              onclick="selectClient('<?= $cli['id'] ?>','<?= e($cli['phone'] ?? '') ?>','<?= e($cli['company'] ?? '') ?>','<?= e($cli['name'] ?? '') ?>','<?= e($cli['name']) ?><?= $cli['company'] ? ' — '.e($cli['company']) : '' ?>')">
              <?= e($cli['name']) ?><?= $cli['company'] ? ' — '.e($cli['company']) : '' ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Tipo destino -->
      <div class="form-group">
        <label class="form-label">Tipo de destino</label>
        <div style="display:flex;gap:8px">
          <label class="dest-card <?= ($alert['recipient_type']??'phone')==='phone'?'active':'' ?>" onclick="setDestino(this,'phone')">
            <input type="radio" name="recipient_type" value="phone" <?= ($alert['recipient_type']??'phone')==='phone'?'checked':'' ?> style="display:none">
            <i class="fa-brands fa-whatsapp" style="color:#128C7E"></i>
            <span style="font-size:12px">Privado</span>
          </label>
          <label class="dest-card <?= ($alert['recipient_type']??'')==='group'?'active':'' ?>" onclick="setDestino(this,'group')">
            <input type="radio" name="recipient_type" value="group" <?= ($alert['recipient_type']??'')==='group'?'checked':'' ?> style="display:none">
            <i class="fa-solid fa-users" style="color:var(--accent)"></i>
            <span style="font-size:12px">Grupo</span>
          </label>
        </div>
      </div>

      <!-- Telefone / Grupo -->
      <div class="form-group">
        <label class="form-label" id="phoneLabel"><?= ($alert['recipient_type']??'phone')==='group'?'Grupo WhatsApp':'Telefone para receber' ?></label>

        <!-- Dropdown de grupos (visível apenas quando tipo=grupo) -->
        <div id="gruposContainer" style="<?= ($alert['recipient_type']??'phone')==='group'?'':'display:none' ?>;margin-bottom:8px">
          <select id="grupoSelect" class="form-control" onchange="document.getElementById('phoneInput').value=this.value">
            <option value="">— Selecione ou cole o ID abaixo —</option>
            <?php if (($alert['recipient_type']??'phone')==='group' && !empty($alert['recipient_phone'])): ?>
            <option value="<?= e($alert['recipient_phone']) ?>" selected><?= e($alert['recipient_phone']) ?></option>
            <?php endif; ?>
          </select>
          <button type="button" class="btn btn-secondary btn-sm" style="margin-top:6px" onclick="carregarGrupos()">
            <span class="material-icons-outlined" style="font-size:14px;vertical-align:middle">refresh</span>
            Buscar grupos
          </button>
          <span id="gruposLoading" style="display:none;font-size:12px;color:var(--txt2);margin-left:8px">Buscando...</span>
          <div id="gruposErro" style="display:none;color:red;font-size:12px;margin-top:4px"></div>
        </div>

        <input type="text" name="recipient_phone" class="form-control" id="phoneInput"
          value="<?= e($alert['recipient_phone']) ?>"
          placeholder="<?= ($alert['recipient_type']??'phone')==='group'?'120363XXXXXXXXXX@g.us':'5511999999999' ?>">
        <div class="form-hint" id="phoneHint">
          <?= ($alert['recipient_type']??'phone')==='group'?'Selecione um grupo acima ou cole o ID manualmente':'DDI+DDD+número. Ex: 5511944445555' ?>
        </div>
      </div>

      <!-- Horários -->
      <div class="form-group" id="horariosGrp" style="<?= ($alert['type']==='erro_conta' && ($alert['modo_disparo']??'agendado')==='inteligente') ? 'display:none' : '' ?>">
        <label class="form-label">Horários de envio</label>
        <div id="horariosWrap" style="display:flex;flex-direction:column;gap:6px">
          <?php foreach ($horariosList as $i => $h): ?>
          <div class="horario-row" style="display:flex;align-items:center;gap:8px">
            <input type="time" name="horarios_list[]" class="form-control" value="<?= e(trim($h)) ?>" style="width:130px">
            <?php if ($i === 0): ?>
              <button type="button" onclick="addHorario()" class="btn btn-secondary btn-sm">+ Horário</button>
            <?php else: ?>
              <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm btn-icon">
                <span class="material-icons-outlined" style="font-size:14px">close</span>
              </button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="horarios" id="horariosHidden" value="<?= e($alert['horarios'] ?? '12:00') ?>">
        <div class="form-hint">Adicione quantos horários quiser. O cron verificará a cada minuto.</div>
      </div>

      <!-- Dias da semana -->
      <div class="form-group">
        <label class="form-label">Dias da semana</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach (['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','0'=>'Dom'] as $v=>$l): ?>
          <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--txt2);cursor:pointer;background:var(--bg3);border:1px solid var(--border2);border-radius:6px;padding:5px 10px">
            <input type="checkbox" name="dias_semana[]" value="<?= $v ?>"
              <?= in_array($v, $diasAtivos)?'checked':'' ?>
              style="accent-color:var(--accent)"> <?= $l ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--txt2);cursor:pointer">
          <input type="checkbox" name="inativar_apos" value="1" <?= $alert['inativar_apos']?'checked':'' ?> style="accent-color:var(--accent)">
          Inativar após o envio
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--txt2);cursor:pointer">
          <input type="checkbox" name="receber_email" value="1" <?= $alert['receber_email']?'checked':'' ?> style="accent-color:var(--accent)">
          Receber no e-mail
        </label>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" onclick="collectHorarios()">
          <span class="material-icons-outlined">save</span> Salvar alterações
        </button>
        <a href="<?= APP_URL ?>/alerts" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>

  <!-- Preview mensagem -->
  <?php
  $defaultMsg = "🚨*Aviso – Saldo Meta ADS*\n\nOlá, *{primeiro_nome}*! 👋\n🏢 Empresa: *{empresa}*\n💰 Saldo atual: *{saldo}*\n\n⚠️ É necessário realizar uma recarga para evitar a pausa dos anúncios.\n\nQual valor para gerar o Pix?\n\n— Equipe EMPRESA 🚀\n(Mensagem Automatica)";
  $msgAtual = ($alert['message'] ?? '') !== '' ? $alert['message'] : $defaultMsg;
  ?>
  <div style="background:var(--bg3);border-left:1px solid var(--border);padding:20px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;max-height:calc(100vh - 120px)">
    <div style="font-size:13px;font-weight:600;color:var(--txt)">Mensagem do alerta</div>
    <?php if (!empty($alertTemplates)): ?>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--txt3);text-transform:uppercase;display:block;margin-bottom:4px">Usar template</label>
      <select id="tplSelect" class="form-control" style="font-size:12px" onchange="aplicarTemplate(this.value)">
        <option value="">— Selecione um template —</option>
        <?php foreach ($alertTemplates as $tpl): ?>
        <option value="<?= htmlspecialchars($tpl['content']) ?>"><?= htmlspecialchars($tpl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div style="background:#128C7E;border-radius:12px;padding:14px;color:#fff;font-size:12px;line-height:1.7" id="alertPreview"></div>
    <textarea name="message" form="alertForm" id="alertMsg" class="form-control" rows="5"
      oninput="updatePreview(this.value)"
      placeholder="Personalize a mensagem..."><?= e($msgAtual) ?></textarea>
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
        '{erros_conta}' => 'Erros detectados na conta',
      ] as $tag => $lbl): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--txt2)"><?= $lbl ?></span>
        <button type="button" onclick="insertVar('<?= $tag ?>')"
          style="background:none;border:none;color:var(--accent);font-family:monospace;font-size:11px;cursor:pointer;padding:0"><?= $tag ?></button>
      </div>
      <?php endforeach; ?>
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
</style>

<script>
var APP_URL = '<?= APP_URL ?>';
// Inicia preview
updatePreview(document.getElementById('alertMsg').value);

function onClientChange(clientId) {
  var sel = document.getElementById('clientSel');
  var opt = sel.options[sel.selectedIndex];
  if (!clientId) return;
  var phone   = opt.dataset.phone   || '';
  var company = opt.dataset.company || '';
  // Preenche telefone se estiver vazio
  var phoneInput = document.getElementById('phoneInput');
  if (phone && phoneInput) {
    phoneInput.value = phone.replace(/\D/g, '');
  }
}

function aplicarTemplate(txt) {
  if (!txt) return;
  document.getElementById('alertMsg').value = txt;
  updatePreview(txt);
  document.getElementById('tplSelect').value = '';
}
function updatePreview(val){
  document.getElementById('alertPreview').innerHTML = val
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\n/g,'<br>')
    .replace(/\{([^}]+)\}/g,'<strong>{$1}</strong>');
}
function insertVar(tag){
  var ta=document.getElementById('alertMsg');
  var s=ta.selectionStart, e=ta.selectionEnd;
  ta.value=ta.value.substring(0,s)+tag+ta.value.substring(e);
  ta.selectionStart=ta.selectionEnd=s+tag.length;
  ta.focus();
  updatePreview(ta.value);
}
function onModoDisparo(){
  var inteligente=document.querySelector('input[name="modo_disparo"][value="inteligente"]').checked;
  var btnI=document.getElementById('btnInteligente');
  var btnA=document.getElementById('btnAgendado');
  var hintI=document.getElementById('modoInteligenteHint');
  var hintA=document.getElementById('modoAgendadoHint');
  var horariosGrp=document.getElementById('horariosGrp');
  if(inteligente){
    btnI.style.background='var(--accent)';btnI.style.color='#fff';
    btnA.style.background='var(--bg3)';btnA.style.color='var(--txt2)';
    hintI.style.display='';hintA.style.display='none';
    if(horariosGrp)horariosGrp.style.display='none';
  }else{
    btnA.style.background='var(--accent)';btnA.style.color='#fff';
    btnI.style.background='var(--bg3)';btnI.style.color='var(--txt2)';
    hintA.style.display='';hintI.style.display='none';
    if(horariosGrp)horariosGrp.style.display='';
  }
}
function setType(el,val){
  document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  el.querySelector('input').checked=true;
  var perf=['ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo'];
  // Mostra/esconde bloco de modo de disparo (só erro_conta)
  var modoGrp=document.getElementById('modoDisparoGrp');
  if(modoGrp) modoGrp.style.display=(val==='erro_conta')?'':'none';
  // Mostra horários só se não for erro_conta inteligente
  var horariosGrp=document.getElementById('horariosGrp');
  if(horariosGrp){
    if(val==='erro_conta'){
      var inteligente=document.querySelector('input[name="modo_disparo"][value="inteligente"]');
      horariosGrp.style.display=(inteligente&&inteligente.checked)?'none':'';
    } else {
      horariosGrp.style.display='';
    }
  }
  document.getElementById('saldoGrp').style.display=(val==='saldo_minimo')?'':'none';
  document.getElementById('perfGrp').style.display=perf.indexOf(val)>=0?'':'none';
  document.getElementById('periodoGrp').style.display=perf.indexOf(val)>=0?'':'none';
  var lbl=document.getElementById('perfLbl'), hint=document.getElementById('perfHint');
  if(lbl&&hint){
    if(val==='ctr_baixo'){lbl.textContent='CTR mínimo (%)';hint.textContent='Dispara quando CTR dos últimos 7 dias ficar abaixo deste valor';}
    else if(val==='cpc_alto'){lbl.textContent='CPC máximo (R$)';hint.textContent='Dispara quando CPC dos últimos 7 dias ultrapassar este valor';}
    else if(val==='custo_conv_alto'){lbl.textContent='Custo/conv máximo (R$)';hint.textContent='Dispara quando custo por conversa ultrapassar este valor';}
    else if(val==='roas_baixo'){lbl.textContent='ROAS mínimo (x)';hint.textContent='Dispara quando ROAS dos últimos 7 dias ficar abaixo deste valor';}
  }
}
// Período personalizado
document.getElementById('periodTypeSelect').addEventListener('change',function(){
  var wrap = document.getElementById('customPeriodWrap');
  wrap.style.display = this.value === 'custom' ? 'flex' : 'none';
});
// Ao submeter, monta o valor custom|start|end no select
document.querySelector('form').addEventListener('submit', function(){
  var sel = document.getElementById('periodTypeSelect');
  if(sel.value === 'custom'){
    var s = document.getElementById('customStart').value;
    var e = document.getElementById('customEnd').value;
    sel.value = 'custom|'+s+'|'+e;
  }
});

function setPlatform(el,val){
  document.querySelectorAll('.platform-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  el.querySelector('input').checked=true;
  document.querySelectorAll('#accSel option').forEach(o=>{
    o.style.display=(o.value===''||o.dataset.p===val)?'':'none';
  });
}
function setDestino(el,val){
  document.querySelectorAll('.dest-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  el.querySelector('input').checked=true;
  if(val==='group'){
    document.getElementById('phoneLabel').textContent='Grupo WhatsApp';
    document.getElementById('phoneInput').placeholder='120363XXXXXXXXXX@g.us';
    document.getElementById('phoneHint').textContent='Selecione um grupo acima ou cole o ID manualmente';
    document.getElementById('gruposContainer').style.display='';
    // Auto-carrega grupos ao trocar para "grupo"
    var wpId=document.querySelector('[name="whatsapp_id"]').value;
    if(wpId) carregarGrupos();
  } else {
    document.getElementById('phoneLabel').textContent='Telefone para receber';
    document.getElementById('phoneInput').placeholder='5511999999999';
    document.getElementById('phoneHint').textContent='DDI+DDD+número. Ex: 5511944445555';
    document.getElementById('gruposContainer').style.display='none';
  }
}

// ── Carrega grupos da Evolution API ──────────────────────────
function carregarGrupos(){
  var wpId=document.querySelector('[name="whatsapp_id"]').value;
  if(!wpId){ alert('Selecione uma instância WhatsApp primeiro.'); return; }
  var loading=document.getElementById('gruposLoading');
  var erroEl=document.getElementById('gruposErro');
  var sel=document.getElementById('grupoSelect');
  loading.style.display='inline';
  erroEl.style.display='none';
  sel.disabled=true;
  fetch('<?= APP_URL ?>/alerts/fetchGroups?whatsapp_id='+encodeURIComponent(wpId))
    .then(r=>r.json())
    .then(data=>{
      loading.style.display='none';
      sel.disabled=false;
      if(!data.success){
        erroEl.textContent='Erro: '+(data.error||'Falha ao buscar grupos');
        erroEl.style.display='block';
        return;
      }
      var currentVal=document.getElementById('phoneInput').value;
      sel.innerHTML='<option value="">— Selecione um grupo —</option>';
      data.grupos.forEach(function(g){
        var opt=document.createElement('option');
        opt.value=g.id;
        opt.textContent=g.nome+' ('+g.id+')';
        if(g.id===currentVal) opt.selected=true;
        sel.appendChild(opt);
      });
      if(data.grupos.length===0){
        erroEl.textContent='Nenhum grupo encontrado nesta instância.';
        erroEl.style.display='block';
      }
    })
    .catch(function(err){
      loading.style.display='none';
      sel.disabled=false;
      erroEl.textContent='Erro de conexão: '+err.message;
      erroEl.style.display='block';
    });
}

// Auto-carrega grupos se já estiver em modo grupo ao carregar a página
document.addEventListener('DOMContentLoaded', function(){
  var wpSel=document.querySelector('[name="whatsapp_id"]');
  if(wpSel){
    wpSel.addEventListener('change', function(){
      if(document.querySelector('[name="recipient_type"]:checked').value==='group'){
        carregarGrupos();
      }
    });
  }
  var isGroup=(document.querySelector('[name="recipient_type"]:checked')||{}).value==='group';
  if(isGroup && document.querySelector('[name="whatsapp_id"]').value){
    carregarGrupos();
  }
});
function addHorario(){
  var wrap=document.getElementById('horariosWrap');
  var row=document.createElement('div');
  row.className='horario-row';
  row.style.cssText='display:flex;align-items:center;gap:8px';
  row.innerHTML='<input type="time" name="horarios_list[]" class="form-control" value="08:00" style="width:130px"><button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm btn-icon"><span class="material-icons-outlined" style="font-size:14px">close</span></button>';
  wrap.appendChild(row);
}
function collectHorarios(){
  var vals=Array.from(document.querySelectorAll('input[name="horarios_list[]"]')).map(i=>i.value).filter(Boolean);
  document.getElementById('horariosHidden').value=vals.join(',');
}

// Custom select cliente
function toggleClientDrop() {
  var opts = document.getElementById('clientDropOptions');
  opts.classList.toggle('open');
}
function selectClient(val, phone, company, name, label) {
  // Atualiza select oculto
  var sel = document.getElementById('clientSel');
  sel.value = val;
  // Atualiza label visível
  document.getElementById('clientDropLabel').textContent = label;
  // Marca opção selecionada
  document.querySelectorAll('#clientDropOptions .custom-select-option').forEach(function(o) {
    o.classList.toggle('selected', o.dataset.value == val);
  });
  // Fecha dropdown
  document.getElementById('clientDropOptions').classList.remove('open');
  // Dispara lógica original de cliente
  if (val) onClientChange(val);
}
// Fecha dropdown ao clicar fora
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('clientDropWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('clientDropOptions').classList.remove('open');
  }
});
// Inicializa label se já tiver cliente selecionado
(function() {
  var sel = document.getElementById('clientSel');
  if (sel && sel.value) {
    var opt = sel.options[sel.selectedIndex];
    if (opt) document.getElementById('clientDropLabel').textContent = opt.text.trim();
  }
})();

</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
?>
