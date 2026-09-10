<?php
// ============================================================
// ProfileController
// ============================================================
class ProfileController {
    public function index(): void {
        requireAuth();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $user = $db->query("SELECT * FROM users WHERE id=?", [$uid])->fetch();
        if (!$user) { redirect('/login'); }
        require_once __DIR__.'/../views/profile/index.php';
    }
    public function update(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $name = sanitize($_POST['name']  ?? '');
        $email= sanitize($_POST['email'] ?? '');
        $phone= sanitize($_POST['phone'] ?? '');
        if (!$name || !$email) { flash('error','Nome e e-mail são obrigatórios.'); redirect('/profile'); }
        $db = Database::getInstance();
        $exists = $db->query("SELECT id FROM users WHERE email=? AND id!=?",[$email,$uid])->fetch();
        if ($exists) { flash('error','E-mail já utilizado por outra conta.'); redirect('/profile'); }
        $db->query("UPDATE users SET name=?,email=?,phone=? WHERE id=?",[$name,$email,$phone,$uid]);
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        flash('success','Perfil atualizado!');
        redirect('/profile');
    }
    public function password(): void {
        requireAuth(); csrfCheck();
        $uid     = currentUser()['id'];
        $db      = Database::getInstance();
        $current = $_POST['current_password']      ?? '';
        $new     = $_POST['new_password']           ?? '';
        $confirm = $_POST['new_password_confirm']   ?? '';
        $user    = $db->query("SELECT password FROM users WHERE id=?",[$uid])->fetch();
        if (!password_verify($current, $user['password'])) { flash('error','Senha atual incorreta.'); redirect('/profile'); }
        if ($new !== $confirm || strlen($new) < 8)         { flash('error','Senhas não coincidem ou muito curtas.'); redirect('/profile'); }
        $db->query("UPDATE users SET password=? WHERE id=?",[password_hash($new,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]),$uid]);
        flash('success','Senha alterada!');
        redirect('/profile');
    }
    public function plan(): void {
        requireAuth();
        require_once __DIR__.'/../views/profile/plan.php';
    }
}

// ============================================================
// NotificationController
// ============================================================
class NotificationController {
    public function index(): void {
        requireAuth();
        $uid = currentUser()['id'];
        $db  = Database::getInstance();

        // Busca logs de alertas
        $alertLogs = $db->query(
            "SELECT al.*, a.name AS alert_name, a.recipient_type, a.type AS alert_type
             FROM alert_logs al
             LEFT JOIN alerts a ON al.alert_id = a.id
             WHERE al.user_id=?
             ORDER BY al.created_at DESC LIMIT 100",
            [$uid]
        )->fetchAll();

        // Busca logs de relatórios (reports com sent_at)
        $reportLogs = $db->query(
            "SELECT r.id, r.title, r.sent_at, r.last_send_status, r.last_send_error, r.recipient_phone,
                    c.name AS client_name, c.company AS client_company,
                    aa.account_name
             FROM reports r
             LEFT JOIN clients c ON r.client_id = c.id
             LEFT JOIN ad_accounts aa ON r.ad_account_id = aa.id
             WHERE r.user_id=? AND r.sent_at IS NOT NULL
             ORDER BY r.sent_at DESC LIMIT 100",
            [$uid]
        )->fetchAll();

        // Mescla e ordena por data desc
        $allLogs = [];
        foreach ($alertLogs as $l) {
            $alertTypeLabels = [
                'saldo_minimo'   => ['Alerta Saldo',  '⚡', 'rgba(243,156,18,.15)',  '#F39C12'],
                'ctr_baixo'      => ['CTR Baixo',     '📉', 'rgba(155,89,182,.15)', '#9B59B6'],
                'cpc_alto'       => ['CPC Alto',      '💸', 'rgba(231,76,60,.15)',  '#E74C3C'],
                'custo_conv_alto'=> ['Custo/Conv',    '💰', 'rgba(230,126,34,.15)', '#E67E22'],
                'roas_baixo'     => ['ROAS Baixo',    '📊', 'rgba(192,57,43,.15)',  '#C0392B'],
                'erro_conta'     => ['Erro Conta',    '🚨', 'rgba(231,76,60,.15)',  '#E74C3C'],
            ];
            $aType = $l['alert_type'] ?? 'saldo_minimo';
            $aInfo = $alertTypeLabels[$aType] ?? ['Alerta', '⚡', 'rgba(0,120,255,.1)', 'var(--accent)'];
            $allLogs[] = [
                'tipo'        => 'alerta',
                'alert_type'  => $aType,
                'alert_label' => $aInfo[0],
                'alert_icon'  => $aInfo[1],
                'alert_bg'    => $aInfo[2],
                'alert_color' => $aInfo[3],
                'nome'        => $l['alert_name'] ?? 'Alerta #'.$l['alert_id'],
                'status'      => $l['status'],
                'dest'        => $l['destinatario'] ?? '-',
                'detalhe'     => $l['saldo'] ? 'Saldo: R$ '.number_format($l['saldo'],2,',','.') : '',
                'erro'        => $l['erro_msg'] ?? '',
                'envio'       => $l['tipo_envio'] ?? 'auto',
                'data'        => $l['created_at'],
            ];
        }
        foreach ($reportLogs as $r) {
            $detalheR = '';
            if (!empty($r['client_company'])) $detalheR = $r['client_company'];
            elseif (!empty($r['client_name'])) $detalheR = $r['client_name'];
            elseif (!empty($r['account_name'])) $detalheR = $r['account_name'];
            $allLogs[] = [
                'tipo'    => 'relatorio',
                'nome'    => $r['title'],
                'status'  => $r['last_send_status'] === 'ok' ? 'enviado' : 'erro',
                'dest'    => $r['recipient_phone'] ?? '-',
                'detalhe' => $detalheR,
                'erro'    => $r['last_send_error'] ?? '',
                'envio'   => 'auto',
                'data'    => $r['sent_at'],
            ];
        }
        usort($allLogs, fn($a,$b) => strtotime($b['data']) - strtotime($a['data']));

        $pageTitle='Logs de Envio'; $currentPage='notifications';
        ob_start(); ?>
<div style="max-width:900px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <div style="font-size:14px;color:var(--txt2)"><?= count($allLogs) ?> registro(s) - alertas e relatórios</div>
    <div style="display:flex;gap:8px">
      <button onclick="filtrar('todos',this)" class="log-filter active" data-f="todos">Todos</button>
      <button onclick="filtrar('enviado',this)" class="log-filter" data-f="enviado">OK Enviados</button>
      <button onclick="filtrar('erro',this)" class="log-filter" data-f="erro">✕ Erros</button>
      <button onclick="filtrar('alerta',this)" class="log-filter" data-f="alerta">⚡ Alertas</button>
      <button onclick="filtrar('relatorio',this)" class="log-filter" data-f="relatorio">📊 Relatórios</button>
    </div>
  </div>

  <?php if (empty($allLogs)): ?>
  <div class="card">
    <div class="empty-state">
      <span class="material-icons-outlined">send</span>
      <h3>Nenhum envio registrado ainda</h3>
      <p>Os logs de alertas e relatórios enviados aparecerão aqui.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <style>
      #logsTable thead th { padding:8px 10px !important; }
      #logsTable tbody td { padding:8px 10px !important; white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important; }
      #logsTable tbody td:nth-child(5) { overflow:visible !important; text-overflow:clip !important; }
    </style>
    <table id="logsTable" style="table-layout:fixed;min-width:780px">
      <colgroup>
        <col style="width:120px">
        <col style="width:120px">
        <col style="width:135px">
        <col style="width:140px">
        <col style="width:90px">
        <col style="width:auto">
        <col style="width:125px">
      </colgroup>
      <thead>
        <tr>
          <th style="width:120px;white-space:nowrap">Tipo</th>
          <th style="width:120px;white-space:nowrap">Nome</th>
          <th style="width:135px;white-space:nowrap">Destinatário</th>
          <th style="width:140px;white-space:nowrap">Detalhe</th>
          <th style="width:90px;white-space:nowrap">Status</th>
          <th style="white-space:nowrap">Erro</th>
          <th style="width:125px;white-space:nowrap">Data/Hora</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($allLogs as $l): ?>
      <tr data-status="<?= $l['status'] ?>" data-tipo="<?= $l['tipo'] ?>">
        <td>
          <?php if ($l['tipo'] === 'alerta'): ?>
            <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:<?= $l['alert_bg'] ?? 'rgba(0,120,255,.1)' ?>;color:<?= $l['alert_color'] ?? 'var(--accent)' ?>"><?= ($l['alert_icon']??'⚡').' '.($l['alert_label']??'Alerta') ?></span>
          <?php else: ?>
            <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:rgba(39,174,96,.1);color:var(--success)">📊 Relatório</span>
          <?php endif; ?>
        </td>
        <td style="font-weight:600;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($l['nome']) ?>"><?= e($l['nome']) ?></td>
        <td style="font-size:12px;color:var(--txt2)"><?= e($l['dest']) ?></td>
        <td style="font-size:12px;color:var(--txt2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($l['detalhe']) ?></td>
        <td>
          <?php if ($l['status'] === 'enviado'): ?>
            <span style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:var(--success);background:rgba(39,174,96,.12);border:1px solid rgba(39,174,96,.3);border-radius:10px;padding:2px 8px">OK Enviado</span>
          <?php else: ?>
            <span style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:#e74c3c;background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);border-radius:10px;padding:2px 8px">✕ Erro</span>
          <?php endif; ?>
        </td>
        <td style="font-size:11px;color:#e74c3c;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($l['erro']) ?>">
          <?= $l['erro'] ? e(mb_substr($l['erro'],0,60)).($l['erro']!==''&&strlen($l['erro'])>60?'…':'') : '-' ?>
        </td>
        <td style="font-size:12px;color:var(--txt3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= date('d/m/Y H:i', strtotime($l['data'])) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<style>
.log-filter{padding:5px 12px;border:1px solid var(--border2);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;background:var(--bg3);color:var(--txt2);font-family:var(--font)}
.log-filter.active{background:var(--accent);color:#fff;border-color:var(--accent)}
</style>
<script>
function filtrar(f, btn) {
  document.querySelectorAll('.log-filter').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#logsTable tbody tr').forEach(function(tr) {
    if (f === 'todos') { tr.style.display = ''; return; }
    var ok = tr.dataset.status === f || tr.dataset.tipo === f;
    tr.style.display = ok ? '' : 'none';
  });
}
</script>
<?php
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }
    public function markRead(): void {
        requireAuth();
        $uid = currentUser()['id'];
        Database::getInstance()->query("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL",[$uid]);
        redirect('/notifications');
    }
}

// ============================================================
// TemplateController
// ============================================================
class TemplateController {
    private static function allVars(): string {
        return '{nome_cliente} {periodo} {hoje} {conta_anuncio} {observacoes} {saudacao} {greeting}
{alcance} {impressoes} {cliques} {clicks_all} {ctr} {cpm} {cpc} {frequencia} {search} {profile_visit}
{conversoes} {leads} {results} {roas} {receita} {vendas} {download} {msg} {engajamento} {app_install} {fat} {all_leads}
{investimento} {cpl} {cpv} {custo_result} {all_leads_cost} {cmsg} {engajamento_cost} {download_cost} {tm} {custo_por_visita}
{comment} {post_reaction} {post_save} {page_engagement}
{view_25} {view_50} {view_75} {view_95} {view_100} {thruplay} {v_avg}
{top_1_creatives_ranking} {top_3_creatives_ranking} {top_5_creatives_ranking} {all_creatives_simple}';
    }

    public function index(): void {
        requireAuth();
        $uid       = currentUser()['id'];
        $templates = Database::getInstance()->query("SELECT * FROM message_templates WHERE user_id=? ORDER BY is_default DESC,name",[$uid])->fetchAll();
        $pageTitle = 'Templates de Mensagem'; $currentPage = 'templates';
        ob_start(); ?>
<div style="max-width:900px">
  <div style="margin-bottom:16px;display:flex;justify-content:flex-end">
    <button class="btn btn-primary btn-sm" onclick="abrirModal()">
      <span class="material-icons-outlined">add</span> Novo Template
    </button>
  </div>
  <?php if (empty($templates)): ?>
  <div class="card"><div class="empty-state"><span class="material-icons-outlined">file_copy</span><h3>Nenhum template</h3><p>Crie seu primeiro template de mensagem</p></div></div>
  <?php else: ?>
  <?php foreach ($templates as $t): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-weight:600;color:var(--txt)"><?= e($t['name']) ?></span>
        <?php if ($t['is_default']): ?><span class="badge badge-blue badge-sm">Padrão</span><?php endif; ?>
      </div>
      <div style="display:flex;gap:6px">
        <button onclick="editarTemplate(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)" class="btn btn-secondary btn-sm btn-icon" title="Editar">
          <span class="material-icons-outlined" style="font-size:14px">edit</span>
        </button>
        <form method="POST" action="<?= APP_URL ?>/templates/delete" style="display:inline">
          <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="id" value="<?= $t['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm btn-icon" data-confirm="Excluir template?">
            <span class="material-icons-outlined" style="font-size:14px">delete</span>
          </button>
        </form>
      </div>
    </div>
    <pre style="font-size:12px;color:var(--txt2);white-space:pre-wrap;background:var(--bg3);padding:12px;border-radius:var(--radius);max-height:160px;overflow:hidden"><?= e(substr($t['content'],0,400)) ?><?= strlen($t['content'])>400?'...':'' ?></pre>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Modal criar/editar template -->
<div id="tplModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:820px;margin:auto">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid var(--border)">
      <div style="font-size:15px;font-weight:700;color:var(--txt)" id="tplModalTitle">Novo Template</div>
      <button onclick="fecharModal()" style="background:none;border:none;color:var(--txt2);cursor:pointer;font-size:22px">✕</button>
    </div>

    <!-- Body: 2 colunas -->
    <div style="display:grid;grid-template-columns:1fr 280px;gap:0">

      <!-- Coluna esquerda: form -->
      <div style="padding:22px;border-right:1px solid var(--border)">
        <form method="POST" id="tplForm" action="<?= APP_URL ?>/templates/store">
          <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="id" id="tplId" value="">

          <div class="form-group">
            <label class="form-label">Nome do Template <span class="req">*</span></label>
            <input type="text" name="name" id="tplName" class="form-control" required placeholder="Ex: Relatório Semanal">
          </div>

          <!-- Var picker -->
          <div class="form-group">
            <label class="form-label">Inserir variável</label>
            <div style="position:relative" id="vpTplWrap">
              <button type="button" id="vpTplBtn" onclick="toggleVpTpl()" style="width:100%;padding:9px 13px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt2);font-family:var(--font);font-size:13px;cursor:pointer;text-align:left;display:flex;align-items:center;justify-content:space-between">
                <span style="display:flex;align-items:center;gap:6px"><span class="material-icons-outlined" style="font-size:14px">add_circle_outline</span>Clique para inserir uma variável</span>
                <span class="material-icons-outlined" style="font-size:14px;color:var(--txt3)">expand_more</span>
              </button>
              <div id="vpTplPanel" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius2);z-index:800;box-shadow:0 4px 20px rgba(0,0,0,.3);flex-direction:column;max-height:380px">
                <div style="padding:10px;border-bottom:1px solid var(--border)">
                  <input type="text" id="vpTplSearch" oninput="renderVpTpl()" placeholder="🔍 Buscar variável..." style="width:100%;padding:7px 10px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--txt);font-family:var(--font);font-size:13px;outline:none">
                </div>
                <div id="vpTplCats" style="display:flex;gap:4px;padding:8px 10px;border-bottom:1px solid var(--border);flex-wrap:wrap"></div>
                <div id="vpTplList" style="overflow-y:auto;flex:1;padding:8px 10px"></div>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Mensagem <span class="req">*</span></label>
            <textarea name="content" id="tplContent" class="form-control" rows="12" required oninput="updTplPrev()" placeholder="Olá {nome_cliente}!&#10;..."></textarea>
          </div>

          <div style="display:flex;gap:8px;margin-top:16px">
            <button type="submit" class="btn btn-primary" id="tplSaveBtn"><span class="material-icons-outlined" style="font-size:15px">save</span> Salvar</button>
            <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
          </div>
        </form>
      </div>

      <!-- Coluna direita: preview -->
      <div style="padding:20px;display:flex;flex-direction:column;gap:10px;background:var(--bg3)">
        <div style="font-size:12px;font-weight:600;color:var(--txt)">Preview WhatsApp</div>
        <div id="tplPrev" style="background:#128C7E;border-radius:12px 12px 12px 0;padding:14px;color:#fff;font-size:12px;line-height:1.7;flex:1;overflow-y:auto;min-height:200px;word-break:break-word"></div>
      </div>
    </div>
  </div>
</div>

<style>
.vtag{display:inline-flex;align-items:center;margin:3px;padding:4px 10px;background:rgba(0,120,255,.1);color:var(--accent);border:1px solid rgba(0,120,255,.2);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;font-family:monospace}
.vtag:hover{background:var(--accent);color:#fff}
.vtag.green{background:rgba(39,174,96,.1);color:var(--success);border-color:rgba(39,174,96,.3)}
.vtag.green:hover{background:var(--success);color:#fff}
.vtag.orange{background:rgba(243,156,18,.1);color:var(--warn);border-color:rgba(243,156,18,.3)}
.vtag.orange:hover{background:var(--warn);color:#fff}
.vtag.pink{background:rgba(155,89,182,.1);color:#bb88ff;border-color:rgba(155,89,182,.3)}
.vtag.pink:hover{background:#9b59b6;color:#fff}
.vsec-title{font-size:10px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;padding:6px 2px 4px;width:100%;display:block}
.vcat-btn{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;background:var(--bg3);border:1px solid var(--border2);color:var(--txt2);font-family:var(--font)}
.vcat-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
</style>

<script>
var VAR_CATS_TPL = {
  'Gerais': [
    {l:'Nome do cliente',t:'{nome_cliente}',c:'blue'},{l:'Período',t:'{periodo}',c:'blue'},
    {l:'Data de hoje',t:'{hoje}',c:'blue'},{l:'Conta de anúncio',t:'{conta_anuncio}',c:'blue'},
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
    {l:'Receita',t:'{receita}',c:'green'},{l:'Vendas',t:'{vendas}',c:'green'},
    {l:'Download',t:'{download}',c:'green'},{l:'Mensagens',t:'{msg}',c:'green'},
    {l:'Engajamento',t:'{engajamento}',c:'green'},{l:'App Install',t:'{app_install}',c:'green'},
    {l:'Faturado',t:'{fat}',c:'green'},{l:'Todos os leads',t:'{all_leads}',c:'green'},
  ],
  'Custos': [
    {l:'Investimento',t:'{investimento}',c:'orange'},{l:'CPL',t:'{cpl}',c:'orange'},
    {l:'CPV',t:'{cpv}',c:'orange'},{l:'Custo/Resultado',t:'{custo_result}',c:'orange'},
    {l:'Custo/Todos Leads',t:'{all_leads_cost}',c:'orange'},{l:'Custo/Mensagem',t:'{cmsg}',c:'orange'},
    {l:'Custo/Engajamento',t:'{engajamento_cost}',c:'orange'},{l:'Custo/Download',t:'{download_cost}',c:'orange'},
    {l:'Ticket Médio',t:'{tm}',c:'orange'},{l:'Custo/Visita ao perfil',t:'{custo_por_visita}',c:'orange'},
  ],
  'Engajamento': [
    {l:'Comentários',t:'{comment}',c:'pink'},{l:'Likes',t:'{post_reaction}',c:'pink'},
    {l:'Salvamentos',t:'{post_save}',c:'pink'},{l:'Eng. página',t:'{page_engagement}',c:'pink'},
  ],
  'Vídeo': [
    {l:'25%',t:'{view_25}',c:'pink'},{l:'50%',t:'{view_50}',c:'pink'},
    {l:'75%',t:'{view_75}',c:'pink'},{l:'95%',t:'{view_95}',c:'pink'},
    {l:'100%',t:'{view_100}',c:'pink'},{l:'Thruplay',t:'{thruplay}',c:'pink'},
    {l:'Tempo médio',t:'{v_avg}',c:'pink'},
  ],
  'Criativos': [
    {l:'TOP 1',t:'{top_1_creatives_ranking}',c:'pink'},
    {l:'TOP 3',t:'{top_3_creatives_ranking}',c:'pink'},
    {l:'TOP 5',t:'{top_5_creatives_ranking}',c:'pink'},
    {l:'Lista criativos',t:'{all_creatives_simple}',c:'pink'},
  ],
};
var vpTplActiveCat = 'Todas';

function abrirModal(){ resetModal(); document.getElementById('tplModal').style.display='flex'; document.body.style.overflow='hidden'; buildVpTpl(); }
function fecharModal(){ document.getElementById('tplModal').style.display='none'; document.body.style.overflow=''; }
function resetModal(){
  document.getElementById('tplModalTitle').textContent='Novo Template';
  document.getElementById('tplId').value='';
  document.getElementById('tplName').value='';
  document.getElementById('tplContent').value='';
  document.getElementById('tplPrev').innerHTML='';
  document.getElementById('tplForm').action='<?= APP_URL ?>/templates/store';
}
function editarTemplate(t){
  document.getElementById('tplModalTitle').textContent='Editar Template';
  document.getElementById('tplId').value=t.id;
  document.getElementById('tplName').value=t.name||'';
  document.getElementById('tplContent').value=t.content||'';
  updTplPrev();
  document.getElementById('tplForm').action='<?= APP_URL ?>/templates/update';
  document.getElementById('tplModal').style.display='flex';
  document.body.style.overflow='hidden';
  buildVpTpl();
}
function updTplPrev(){
  var v=document.getElementById('tplContent').value||'';
  document.getElementById('tplPrev').innerHTML=v
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\n/g,'<br>')
    .replace(/(\{[\w_]+\})/g,'<strong style="background:rgba(255,255,255,.2);border-radius:3px;padding:0 2px">$1</strong>')
    .replace(/\*([^*]+)\*/g,'<strong>$1</strong>');
}
function toggleVpTpl(){
  var p=document.getElementById('vpTplPanel');
  var show=p.style.display==='none'||p.style.display==='';
  p.style.display=show?'flex':'none';
}
function buildVpTpl(){
  vpTplActiveCat='Todas';
  var cats=['Todas'].concat(Object.keys(VAR_CATS_TPL));
  var catsEl=document.getElementById('vpTplCats'); catsEl.innerHTML='';
  cats.forEach(function(c){
    var b=document.createElement('button'); b.type='button'; b.className='vcat-btn'+(c==='Todas'?' active':''); b.textContent=c;
    b.onclick=function(){ vpTplActiveCat=c; document.querySelectorAll('.vcat-btn').forEach(x=>x.classList.remove('active')); b.classList.add('active'); renderVpTpl(); };
    catsEl.appendChild(b);
  });
  renderVpTpl();
}
function renderVpTpl(){
  var q=(document.getElementById('vpTplSearch')||{}).value||'';
  var list=document.getElementById('vpTplList'); if(!list)return; list.innerHTML='';
  var cats=vpTplActiveCat==='Todas'?Object.keys(VAR_CATS_TPL):[vpTplActiveCat];
  var found=0;
  cats.forEach(function(cat){
    var items=(VAR_CATS_TPL[cat]||[]).filter(function(v){return !q||v.l.toLowerCase().includes(q.toLowerCase())||v.t.toLowerCase().includes(q.toLowerCase());});
    if(!items.length)return;
    var sec=document.createElement('span'); sec.className='vsec-title'; sec.textContent=cat; list.appendChild(sec);
    var wrap=document.createElement('div');
    items.forEach(function(v){
      var tag=document.createElement('span'); tag.className='vtag '+(v.c||''); tag.title=v.l; tag.textContent=v.t;
      tag.onclick=function(){
        var ta=document.getElementById('tplContent');
        var s=ta.selectionStart, e=ta.selectionEnd;
        ta.value=ta.value.substring(0,s)+v.t+ta.value.substring(e);
        ta.focus(); ta.selectionStart=ta.selectionEnd=s+v.t.length;
        updTplPrev();
        document.getElementById('vpTplPanel').style.display='none';
      };
      wrap.appendChild(tag); found++;
    });
    list.appendChild(wrap);
  });
  if(!found){ var em=document.createElement('div'); em.style.cssText='padding:20px;text-align:center;font-size:12px;color:var(--txt3)'; em.textContent='Nenhuma variável encontrada'; list.appendChild(em); }
}
document.addEventListener('click',function(e){
  var p=document.getElementById('vpTplPanel'), b=document.getElementById('vpTplBtn');
  if(p&&b&&!p.contains(e.target)&&e.target!==b&&!b.contains(e.target)) p.style.display='none';
});
document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ fecharModal(); } });
</script>
<?php
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }
    public function store(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $name = sanitize($_POST['name']    ?? '');
        $cont = $_POST['content']          ?? '';
        if (!$name || !$cont) { flash('error','Preencha todos os campos.'); redirect('/templates'); }
        Database::getInstance()->query("INSERT INTO message_templates (user_id,name,content) VALUES (?,?,?)",[$uid,$name,$cont]);
        flash('success','Template salvo!');
        redirect('/templates');
    }
    public function update(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $id   = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name']    ?? '');
        $cont = $_POST['content']          ?? '';
        if (!$name || !$cont || !$id) { flash('error','Preencha todos os campos.'); redirect('/templates'); }
        Database::getInstance()->query("UPDATE message_templates SET name=?,content=? WHERE id=? AND user_id=?",[$name,$cont,$id,$uid]);
        flash('success','Template atualizado!');
        redirect('/templates');
    }
    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        Database::getInstance()->query("DELETE FROM message_templates WHERE id=? AND user_id=?",[(int)($_POST['id']??0),$uid]);
        flash('success','Template excluído.');
        redirect('/templates');
    }
}

// ============================================================
// AdminController
// ============================================================
class AdminController {
    public function index(): void { requireAdmin(); $this->users(); }
    public function users(): void {
        requireAdmin();
        $users   = Database::getInstance()->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
        $pageTitle='Admin - Usuários'; $currentPage='admin';
        ob_start(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <span style="font-size:14px;color:var(--txt2)"><?= count($users) ?> usuário(s)</span>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('mdNovoUser').style.display='flex'">
    <span class="material-icons-outlined" style="font-size:15px">person_add</span> Cadastrar usuário
  </button>
</div>
<div class="table-wrapper">
  <table>
    <thead><tr><th>Usuário</th><th>Plano</th><th>Status</th><th>Login</th><th>Cadastro</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><div style="font-weight:600;color:var(--txt)"><?= e($u['name']) ?></div><div style="font-size:11px;color:var(--txt2)"><?= e($u['email']) ?></div></td>
      <td><span class="plan-chip plan-<?= e($u['plan']) ?>"><?= e($u['plan']) ?></span></td>
      <td><?= $u['status']==='active'?'<span class="badge badge-green badge-sm">Ativo</span>':'<span class="badge badge-red badge-sm">Inativo</span>' ?></td>
      <td style="font-size:12px;color:var(--txt3)"><?= $u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'-' ?></td>
      <td style="font-size:12px;color:var(--txt3)"><?= date('d/m/Y',strtotime($u['created_at'])) ?></td>
      <td><a href="<?= APP_URL ?>/admin/user/edit?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Editar</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal cadastrar novo usuário -->
<div id="mdNovoUser" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);width:100%;max-width:500px;padding:28px;margin:20px">
    <div style="font-size:15px;font-weight:700;color:var(--txt);margin-bottom:20px;display:flex;align-items:center;gap:8px">
      <span class="material-icons-outlined" style="color:var(--accent)">person_add</span> Cadastrar novo usuário
    </div>
    <form method="POST" action="<?= APP_URL ?>/admin/user/create">
      <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nome <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" required placeholder="Nome completo">
        </div>
        <div class="form-group">
          <label class="form-label">E-mail <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" required placeholder="email@exemplo.com">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Senha <span class="req">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="8" placeholder="Mínimo 8 caracteres">
        </div>
        <div class="form-group">
          <label class="form-label">Plano</label>
          <select name="plan" class="form-control">
            <?php foreach(['trial','essencial','advanced','pro','premium'] as $p): ?>
            <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Telefone</label>
        <input type="text" name="phone" class="form-control" placeholder="5511999999999">
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><span class="material-icons-outlined" style="font-size:15px">save</span> Cadastrar</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('mdNovoUser').style.display='none'">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<script>document.addEventListener('keydown',function(e){if(e.key==='Escape')document.getElementById('mdNovoUser').style.display='none';});</script>
<?php      $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }
    public function createUser(): void {
        requireAdmin(); csrfCheck();
        $db    = Database::getInstance();
        $name  = sanitize($_POST['name']  ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $plan  = sanitize($_POST['plan']  ?? 'trial');
        $phone = sanitize($_POST['phone'] ?? '');
        if (!$name || !$email || strlen($pass) < 8) {
            flash('error', 'Preencha nome, e-mail e senha (mín. 8 caracteres).');
            redirect('/admin');
        }
        $exists = $db->query("SELECT id FROM users WHERE email=?", [$email])->fetch();
        if ($exists) { flash('error', 'E-mail já cadastrado.'); redirect('/admin'); }
        $db->query(
            "INSERT INTO users (name,email,password,plan,phone,status) VALUES (?,?,?,?,?,'active')",
            [$name, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $plan, $phone]
        );
        flash('success', "Usuário «$name» cadastrado com sucesso!");
        redirect('/admin');
    }
    public function editUser(): void {
        requireAdmin();
        $user = Database::getInstance()->query("SELECT * FROM users WHERE id=?",[(int)($_GET['id']??0)])->fetch();
        if (!$user) { flash('error','Usuário não encontrado.'); redirect('/admin'); }
        $pageTitle='Editar Usuário'; $currentPage='admin';
        ob_start(); ?>
<div style="max-width:600px">
<div class="form-card">
  <form method="POST" action="<?= APP_URL ?>/admin/user/update">
    <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="id" value="<?= $user['id'] ?>">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required></div>
      <div class="form-group"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Plano</label>
        <select name="plan" class="form-control">
          <?php foreach(['trial','essencial','advanced','pro','premium'] as $p): ?>
          <option value="<?= $p ?>" <?= $user['plan']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Status</label>
        <select name="status" class="form-control">
          <option value="active"   <?= $user['status']==='active'?'selected':'' ?>>Ativo</option>
          <option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inativo</option>
          <option value="pending"  <?= $user['status']==='pending'?'selected':'' ?>>Pendente</option>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Salvar</button>
      <a href="<?= APP_URL ?>/admin" class="btn btn-secondary">Voltar</a>
    </div>
  </form>
</div>
</div>
<?php      $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }
    public function updateUser(): void {
        requireAdmin(); csrfCheck();
        $db = Database::getInstance();
        $id = (int)($_POST['id'] ?? 0);
        $db->query("UPDATE users SET name=?,email=?,plan=?,status=? WHERE id=?",[
            sanitize($_POST['name']??''), sanitize($_POST['email']??''),
            sanitize($_POST['plan']??'trial'), sanitize($_POST['status']??'active'), $id
        ]);
        flash('success','Usuário atualizado!');
        redirect('/admin');
    }
    public function debugPanel(): void {
        requireAdmin();
        $db = Database::getInstance();
        $s  = sanitize($_GET['s'] ?? 'overview');

        $pageTitle   = 'Debug';
        $currentPage = 'debug';

        // Closures helpers
        $q  = function(string $sql, array $p=[]) use ($db): array {
            try { return $db->query($sql,$p)->fetchAll(); }
            catch(\Throwable $e){ return []; }
        };
        $q1 = function(string $sql, array $p=[]) use ($db): array {
            try { $r=$db->query($sql,$p)->fetch(); return $r?:[]; }
            catch(\Throwable $e){ return []; }
        };
        $badge = function(bool $ok, string $y='OK', string $n='ERRO'): string {
            return "<b style='color:".($ok?'#27ae60':'#e74c3c')."'>".($ok?$y:$n)."</b>";
        };
        $row = function(string $k, string $v): string {
            return "<tr><td style='padding:6px 12px;color:var(--txt3);width:200px'>$k</td><td style='padding:6px 12px;color:var(--txt);font-family:monospace;font-size:12px'>$v</td></tr>";
        };
        $tbl = function(array $heads, array $rows): string {
            $h='<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:12px">';
            $h.='<thead><tr>';
            foreach($heads as $hd) $h.="<th style='padding:7px 12px;text-align:left;color:var(--txt3);font-size:10px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);background:var(--bg3)'>{$hd}</th>";
            $h.='</tr></thead><tbody>';
            foreach($rows as $r){ $h.='<tr>'; foreach($r as $c) $h.="<td style='padding:6px 12px;border-bottom:1px solid var(--border2);font-size:12px;color:var(--txt2)'>{$c}</td>"; $h.='</tr>'; }
            return $h.'</tbody></table></div>';
        };

        $nav = ['overview'=>'📊 Overview','server'=>'🖥️ Servidor','db'=>'🗄️ Banco','cron'=>'⏰ Crons',
                'alerts'=>'🔔 Alertas','reports'=>'📋 Relatórios','whatsapp'=>'💬 WhatsApp',
                'meta'=>'📘 Meta API','config'=>'⚙️ Config','logs'=>'📜 Logs','errors'=>'🚨 Erros PHP','phpinfo'=>'ℹ️ PHP Info'];

        ob_start();
        echo '<style>
.dn{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px}
.dn a{padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);text-decoration:none}
.dn a:hover,.dn a.on{background:var(--accent);color:#fff;border-color:var(--accent)}
.dg{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:16px}
.dc{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius2);padding:14px;text-align:center}
.dv{font-size:24px;font-weight:700;color:var(--accent)}.dl{font-size:11px;color:var(--txt3);margin-top:4px}
.dok{color:#27ae60;font-weight:700}.derr{color:#e74c3c;font-weight:700}.dwn{color:#f39c12;font-weight:700}
</style>
<div style="max-width:1000px">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px">
<div class="dn" style="margin-bottom:0;flex:1">';
        foreach($nav as $k=>$l) {
            echo '<a href="'.APP_URL.'/admin/debug?s='.$k.'" '.($s===$k?'class="on"':'').'>'.$l.'</a>';
        }
        echo '</div>
<div style="display:flex;gap:8px;flex-shrink:0;padding-top:2px">
  <a href="'.APP_URL.'/admin/debug?s=export&fmt=txt" style="display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);text-decoration:none;white-space:nowrap">📄 Exportar TXT</a>
  <a href="'.APP_URL.'/admin/debug?s=export&fmt=pdf" style="display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #e74c3c;background:var(--bg3);color:#e74c3c;text-decoration:none;white-space:nowrap">📑 Exportar PDF</a>
  <a href="'.APP_URL.'/dashboard" title="Voltar ao Dashboard" style="display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--border2);background:var(--bg3);color:var(--txt2);text-decoration:none;white-space:nowrap">⬅️ Voltar</a>
</div>
</div>';

        if ($s==='overview') {
            $stats=[
                ['Usuários',        'SELECT COUNT(*) c FROM users',                                    '👤'],
                ['Relatórios',      'SELECT COUNT(*) c FROM reports',                                  '📋'],
                ['Alertas',         "SELECT COUNT(*) c FROM alerts WHERE ativo=1",                     '🔔'],
                ['Clientes',        'SELECT COUNT(*) c FROM clients',                                  '👥'],
                ['Contas Ads',      'SELECT COUNT(*) c FROM ad_accounts',                              '📘'],
                ['WhatsApp',        'SELECT COUNT(*) c FROM whatsapp_instances',                       '💬'],
                ['Logs alertas',    'SELECT COUNT(*) c FROM alert_logs',                               '📜'],
                ['Logs relatórios', 'SELECT COUNT(*) c FROM report_logs',                              '📋'],
                ['Métricas',        'SELECT COUNT(*) c FROM campaign_metrics',                         '📊'],
                ['Templates',       'SELECT COUNT(*) c FROM message_templates',                        '📝'],
                ['Integrações',     'SELECT COUNT(*) c FROM integrations',                             '🔗'],
                ['Notificações',    "SELECT COUNT(*) c FROM notifications WHERE read_at IS NULL",      '🔔'],
            ];

            // Checa tabelas individualmente (SHOW TABLES bloqueado pela Hostinger)
            $expected = ['users','reports','alerts','alert_logs','report_logs','clients','ad_accounts','whatsapp_instances','notifications','campaign_metrics','message_templates','activity_log'];
            $tables = [];
            foreach ($expected as $tbl) {
                try {
                    Database::getInstance()->query("SELECT 1 FROM `$tbl` LIMIT 1");
                    $tables[] = $tbl;
                } catch(\Throwable $e) {}
            }
            $missing  = array_diff($expected, $tables);
            $dbOk = false; try { Database::getInstance()->query("SELECT 1"); $dbOk = true; } catch(\Throwable $e) {}
            $wa       = $q1("SELECT COUNT(*) c FROM whatsapp_instances WHERE status='connected'")['c']??0;
            $metaAct  = $q1("SELECT COUNT(*) c FROM ad_accounts WHERE status='active'")['c']??0;
            $lc       = $q1("SELECT MAX(created_at) t FROM alert_logs WHERE tipo_envio='automatico'");
            $cronMin  = ($lc['t']??null) ? round((time()-strtotime($lc['t']))/60) : null;
            $alertsHoje = $q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE()")['c']??0;
            $errosHoje  = $q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE() AND status='erro'")['c']??0;
            echo '<div class="dg">';
            foreach($stats as [$lbl,$sql,$icon]) {
                $val=$q1($sql)['c']??0;
                echo '<div class="dc"><div style="font-size:18px">'.$icon.'</div><div class="dv">'.$val.'</div><div class="dl">'.$lbl.'</div></div>';
            }
            echo '</div>';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">🗄️ Banco de Dados</td></tr>';
            echo $row('Conexão', $badge($dbOk,'✓ Conectado','✗ Erro'));
            echo $row('Tabelas', count($tables).' encontradas');
            echo $row('Faltando', empty($missing)?$badge(true,'Nenhuma'):"<span class='derr'>".implode(', ',$missing)."</span>");
            echo '</table></div>';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">⚙️ Serviços</td></tr>';
            echo $row('WhatsApp',  $badge($wa>0,$wa.' conectada(s)','Nenhuma'));
            echo $row('Meta Ads',  $badge($metaAct>0,$metaAct.' ativa(s)','Nenhuma'));
            echo $row('PHP',       PHP_VERSION.' '.$badge(version_compare(PHP_VERSION,'8.0','>='),'OK 8+','ERRO'));
            echo $row('OPcache',   $badge(function_exists('opcache_reset'),'Ativo','Inativo'));
            echo '</table></div>';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">⏰ Crons Hoje</td></tr>';
            $cronAgo = $cronMin!==null ? ($cronMin<60?"{$cronMin}min atrás":round($cronMin/60).'h atrás') : '<span class="dwn">Nenhum</span>';
            echo $row('Último envio', $cronAgo);
            echo $row('Envios hoje', "<span class='dok'>$alertsHoje</span>");
            echo $row('Erros hoje',  $errosHoje>0?"<span class='derr'>$errosHoje</span>":"<span class='dok'>0</span>");
            echo $row('Mem limite',  ini_get('memory_limit'));
            echo '</table></div>';
            echo '</div>';

        } elseif ($s==='db') {
            $cols=[
                'alert_logs.alert_name'=>"SHOW COLUMNS FROM alert_logs LIKE 'alert_name'",
                'alerts.valor_threshold'=>"SHOW COLUMNS FROM alerts LIKE 'valor_threshold'",
                'alerts.period_type'=>"SHOW COLUMNS FROM alerts LIKE 'period_type'",
                'reports.rt_camp_selection'=>"SHOW COLUMNS FROM reports LIKE 'rt_camp_selection'",
                'reports.last_send_status'=>"SHOW COLUMNS FROM reports LIKE 'last_send_status'",
                'report_logs (tabela)'=>"SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='report_logs'",
            ];
            $rows=[];
            foreach($cols as $l=>$sql) $rows[]=[$l,$badge(!empty($q1($sql)),'existe','faltando')];
            echo '<div class="card" style="padding:0;overflow:hidden">';
            echo $tbl(['Coluna','Status'],$rows);
            echo '</div>';
            try {
                $sizes=$q("SELECT TABLE_NAME,TABLE_ROWS,ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,2) mb FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY (DATA_LENGTH+INDEX_LENGTH) DESC");
                $sr=[];
                foreach($sizes as $t) $sr[]=[$t['TABLE_NAME'],number_format($t['TABLE_ROWS']),$t['mb'].' MB'];
                echo '<div class="card" style="padding:0;overflow:hidden;margin-top:16px">';
                echo $tbl(['Tabela','Linhas','Tamanho'],$sr);
                echo '</div>';
            } catch(\Throwable $e){}

        } elseif ($s==='cron') {
            // CRON_SECRET card
            { $_cs = CRON_SECRET;
            $__btn1 = "onclick=\"var e=document.getElementById('cron-secret-val');if(e.dataset.v){e.innerHTML='&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;';e.dataset.v='';this.textContent='Mostrar';}else{e.textContent=e.dataset.k;e.dataset.v=1;this.textContent='Ocultar';}\"";
            $__btn2 = "onclick=\"navigator.clipboard.writeText(document.getElementById('cron-secret-val').dataset.k).then(function(){var b=document.getElementById('cron-copy-btn');b.textContent='Copiado!';setTimeout(function(){b.textContent='Copiar';},2000);})\"";
            echo "<div class=\"card\" style=\"margin-bottom:16px\">";
            echo "<div style=\"font-weight:700;font-size:13px;color:var(--accent);margin-bottom:10px\">&#128273; Chave dos Crons (CRON_SECRET)</div>";
            echo "<div style=\"background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap\">";
            echo "<code id=\"cron-secret-val\" style=\"flex:1;font-size:12px;word-break:break-all;font-family:monospace;color:var(--txt1)\">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</code>";
            echo "<button style=\"padding:5px 10px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--txt1);cursor:pointer\" {$__btn1}>Mostrar</button>";
            echo "<button id=\"cron-copy-btn\" style=\"padding:5px 10px;border-radius:6px;border:none;background:var(--accent);color:#fff;cursor:pointer\" {$__btn2}>Copiar</button>";
            echo "</div>";
            echo "<div style=\"margin-top:8px;font-size:11px;color:var(--txt3)\">Header cron-job.org: <code style=\"background:var(--bg2);padding:2px 5px;border-radius:4px\">X-Cron-Key: {$_cs}</code></div>";
            echo "</div>";
            echo "<script>document.getElementById('cron-secret-val').dataset.k=" . json_encode($_cs) . ";</script>"; }

            $la=$q1("SELECT MAX(created_at) t FROM alert_logs WHERE tipo_envio='automatico'");
            $lr=$q1("SELECT MAX(created_at) t FROM report_logs WHERE tipo_envio='automatico'");
            $ha=$q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE()")['c']??0;
            $ea=$q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE() AND status='erro'")['c']??0;
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo $row('run-alerts - último', ($la['t'] ?? null)?htmlspecialchars($la['t'] ?? ''):'<span class="dwn">Nenhum</span>');
            echo $row('run-alerts - hoje', "<span class='dok'>{$ha}</span> envios | <span class='derr'>{$ea}</span> erros");
            echo $row('send-reports - último', ($lr['t'] ?? null)?htmlspecialchars($lr['t'] ?? ''):'<span class="dwn">Nenhum - normal se não há relatórios agendados</span>');
            echo $row('Nota','<span style="color:var(--txt3);font-size:11px">Cron só grava log ao enviar. Sem alertas no horário = sem log = normal.</span>');
            echo '</table></div>';
            $al=$q("SELECT id,name,type,horarios,ultimo_envio FROM alerts WHERE ativo=1 ORDER BY id");
            $ar=[];
            foreach($al as $a) {
                $ago='nunca';
                if($a['ultimo_envio']){$diff=time()-strtotime($a['ultimo_envio']);$ago=$diff<3600?round($diff/60).'min':($diff<86400?round($diff/3600).'h':round($diff/86400).'d').' atrás';}
                $ar[]=['#'.$a['id'],htmlspecialchars($a['name']),$a['type'],$a['horarios'],"<span class='".($a['ultimo_envio']?'dok':'dwn')."'>{$ago}</span>"];
            }
            echo '<div class="card" style="padding:0;overflow:hidden;margin-top:16px"><div style="padding:10px 16px;font-weight:700;font-size:13px;color:var(--accent);border-bottom:1px solid var(--border)">🔔 Alertas Ativos</div>';
            echo $tbl(['ID','Nome','Tipo','Horários','Último Envio'],$ar);
            echo '</div>';

        } elseif ($s==='alerts') {
            $logs=$q("SELECT al.id,COALESCE(a.name,al.alert_name,CONCAT('Alerta #',al.alert_id)) n,al.status,al.destinatario,al.saldo,al.tipo_envio,al.created_at FROM alert_logs al LEFT JOIN alerts a ON al.alert_id=a.id ORDER BY al.id DESC LIMIT 100");
            $lr=[];
            foreach($logs as $l) $lr[]=['#'.$l['id'],htmlspecialchars($l['n']),$l['status']==='enviado'?"<span class='dok'>ENVIADO</span>":"<span class='derr'>ERRO</span>",htmlspecialchars($l['destinatario']??'-'),$l['saldo']?'R$ '.number_format($l['saldo'],2,',','.'):'-',$l['tipo_envio'],htmlspecialchars($l['created_at'])];
            echo '<div class="card" style="padding:0;overflow:hidden">';
            echo $tbl(['ID','Alerta','Status','Destinatário','Saldo','Tipo','Data'],$lr);
            echo '</div>';

        } elseif ($s==='reports') {
            $nx=$q("SELECT id,title,next_send_at,frequency,recipient_phone FROM reports WHERE next_send_at IS NOT NULL AND status='active' ORDER BY next_send_at ASC LIMIT 15");
            $nr=[];
            foreach($nx as $r) $nr[]=['#'.$r['id'],htmlspecialchars($r['title']),strtotime($r['next_send_at'])<time()?"<span class='derr'>".htmlspecialchars($r['next_send_at'])." ⚠️</span>":"<span class='dok'>".htmlspecialchars($r['next_send_at'])."</span>",$r['frequency']];
            echo '<div class="card" style="padding:0;overflow:hidden"><div style="padding:10px 16px;font-weight:700;font-size:13px;color:var(--accent);border-bottom:1px solid var(--border)">📅 Próximos Agendados</div>';
            echo $tbl(['ID','Título','Próximo Envio','Frequência'],$nr);
            echo '</div>';
            $rl=$q("SELECT rl.*,r.title FROM report_logs rl LEFT JOIN reports r ON rl.report_id=r.id ORDER BY rl.id DESC LIMIT 30");
            if($rl) {
                $rr=[];
                foreach($rl as $l) $rr[]=['#'.$l['id'],htmlspecialchars($l['title']??'-'),$l['status']==='enviado'?"<span class='dok'>ENVIADO</span>":"<span class='derr'>ERRO</span>",htmlspecialchars($l['destinatario']??'-'),htmlspecialchars($l['created_at'])];
                echo '<div class="card" style="padding:0;overflow:hidden;margin-top:16px"><div style="padding:10px 16px;font-weight:700;font-size:13px;color:var(--accent);border-bottom:1px solid var(--border)">📋 Logs de Envio</div>';
                echo $tbl(['ID','Relatório','Status','Destinatário','Data'],$rr);
                echo '</div>';
            }

        } elseif ($s==='whatsapp') {
            $wi=$q("SELECT * FROM whatsapp_instances ORDER BY user_id");
            $wr=[];
            foreach($wi as $i) $wr[]=['#'.$i['id'],htmlspecialchars($i['instance_name']),$i['status']==='connected'?"<span class='dok'>CONNECTED</span>":"<span class='derr'>".strtoupper($i['status'])."</span>",htmlspecialchars($i['phone_number']??'-'),$i['is_default']?"<span class='dok'>&#10003;</span>":'-'];
            echo '<div class="card" style="padding:0;overflow:hidden">';
            echo $tbl(['ID','Nome','Status','Telefone','Padrão'],$wr);
            echo '</div>';

        } elseif ($s==='meta') {
            $ac=$q("SELECT id,account_name,account_id,platform,status,token_expires,LEFT(access_token,20) tp FROM ad_accounts ORDER BY user_id");
            if(empty($ac)) { echo '<div class="card"><div class="empty-state"><span class="material-icons-outlined">cloud_off</span><h3>Nenhuma conta</h3></div></div>'; }
            foreach($ac as $a) {
                $exp=$a['token_expires'];
                $days=$exp?round((strtotime($exp)-time())/86400):null;
                echo '<div class="card" style="padding:0;overflow:hidden;margin-bottom:12px"><table style="width:100%;border-collapse:collapse">';
                echo $row('Conta', htmlspecialchars($a['account_name']).' <span style="color:var(--txt3);font-size:11px">('.htmlspecialchars($a['account_id']).')</span>');
                echo $row('Plataforma', strtoupper($a['platform']));
                echo $row('Status', $badge($a['status']==='active','Ativo','Inativo'));
                echo $row('Token', $a['tp']?"<span style='font-family:monospace;font-size:11px'>".htmlspecialchars($a['tp'])."…</span>":"<span class='derr'>Ausente</span>");
                echo $row('Expira', $exp?"<span class='".($days>7?'dok':($days>0?'dwn':'derr'))."'>".htmlspecialchars($exp)." ({$days}d)</span>":'<span class="dwn">Não definido</span>');
                echo '</table></div>';
            }

        } elseif ($s==='config') {
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo $row('APP_NAME', APP_NAME);
            echo $row('APP_URL', APP_URL);
            echo $row('APP_ENV', APP_ENV);
            echo $row('APP_VERSION', APP_VERSION);
            echo $row('APP_TIMEZONE', APP_TIMEZONE);
            echo $row('DB', DB_HOST.':'.DB_PORT.' / '.DB_NAME);
            echo $row('SESSION', SESSION_LIFETIME.'s / BCRYPT cost '.BCRYPT_COST);
            echo $row('META_APP_ID', META_APP_ID);
            echo $row('META_VERSION', META_API_VERSION);
            echo $row('EVOLUTION_URL', EVOLUTION_API_URL);
            echo $row('SMTP', MAIL_HOST.':'.MAIL_PORT.' / '.MAIL_USER);
            echo $row('SMTP status', (str_contains(MAIL_USER,'seudominio')||MAIL_PASS==='senha-email')?"<span class='derr'>⚠️ Placeholder - e-mail não funciona</span>":"<span class='dok'>OK OK</span>");
            echo '</table></div>';

        } elseif ($s==='logs') {
            $al=$q("SELECT al.*,u.name un FROM activity_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.id DESC LIMIT 50");
            $ar=[];
            foreach($al as $l) $ar[]=[htmlspecialchars($l['un']??'visitante'),(str_contains($l['action']??'','fail')||str_contains($l['action']??'','error'))?"<span class='derr'>".$l['action']."</span>":"<span class='dok'>".$l['action']."</span>",htmlspecialchars($l['ip']??'-'),htmlspecialchars($l['created_at'])];
            echo '<div class="card" style="padding:0;overflow:hidden">';
            echo $tbl(['Usuário','Ação','IP','Data'],$ar);
            echo '</div>';

        } elseif ($s==='server') {
            $dT=$dF=0; try{$dT=disk_total_space('/');$dF=disk_free_space('/');}catch(\Throwable $e){}
            $dU=$dT-$dF; $dP=$dT>0?round($dU/$dT*100):0;
            $fB=fn($b)=>$b>=1073741824?round($b/1073741824,1).'GB':round($b/1048576).'MB';
            $load=sys_getloadavg(); $loadStr=$load?implode(' / ',array_map(fn($l)=>round($l,2),$load)):'N/A';
            $up=@file_get_contents('/proc/uptime'); $upSec=$up?(int)explode(' ',$up)[0]:0;
            $upStr=$upSec>86400?round($upSec/86400).'d '.round(($upSec%86400)/3600).'h':($upSec>3600?round($upSec/3600).'h '.round(($upSec%3600)/60).'min':round($upSec/60).'min');
            $barC=$dP>85?'#e74c3c':($dP>70?'#f39c12':'#27ae60');
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">🖥️ Sistema</td></tr>';
            echo $row('OS',          php_uname('s').' '.php_uname('r'));
            echo $row('Hostname',    php_uname('n'));
            echo $row('Arquitetura', php_uname('m'));
            echo $row('Uptime',      $upStr);
            echo $row('Load avg',    $loadStr.' (1/5/15min)');
            echo '</table></div>';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">💾 Disco & Memória</td></tr>';
            echo $row('Disco total', $fB($dT));
            echo $row('Disco usado', $fB($dU)." ({$dP}%)");
            echo $row('Disco livre', $fB($dF));
            echo $row('', '<div style="height:6px;background:#222;border-radius:4px"><div style="height:100%;width:'.$dP.'%;background:'.$barC.';border-radius:4px"></div></div>');
            echo $row('Mem usada',  $fB(memory_get_usage(true)));
            echo $row('Mem pico',   $fB(memory_get_peak_usage(true)));
            echo $row('Mem limite', ini_get('memory_limit'));
            echo '</table></div>';
            echo '</div>';
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">🐘 PHP</td></tr>';
            echo $row('Versão',        PHP_VERSION);
            echo $row('SAPI',          php_sapi_name());
            echo $row('Max execution', ini_get('max_execution_time').'s');
            echo $row('Upload max',    ini_get('upload_max_filesize'));
            echo $row('Post max',      ini_get('post_max_size'));
            echo $row('Timezone',      date_default_timezone_get());
            echo $row('display_errors',ini_get('display_errors')?"<span class='dwn'>On</span>":"<span class='dok'>Off ✓</span>");
            echo $row('Error log',     ini_get('error_log')?:'N/A');
            echo '</table></div>';
            $imp=['curl','pdo','pdo_mysql','mbstring','json','openssl','gd','zip','opcache','intl'];
            $ex=get_loaded_extensions(); $er=[];
            foreach($imp as $e) $er[]=[$e,$badge(in_array($e,$ex),'✓ carregada','✗ ausente')];
            echo '<div class="card" style="padding:0;overflow:hidden;margin-top:16px">';
            echo $tbl(['Extensão','Status'],$er);
            echo '</div>';

        } elseif ($s==='errors') {
            $errLog=ini_get('error_log'); $errCt=''; $errSz=0;
            if($errLog&&file_exists($errLog)){
                $errSz=filesize($errLog);
                $fp=fopen($errLog,'r');
                if($fp){fseek($fp,-min($errSz,50000),SEEK_END);$raw=fread($fp,50000);fclose($fp);
                    $lines=array_slice(array_filter(explode("\n",$raw)),-200);
                    $errCt=implode("\n",$lines);}
            }
            $fLog=null;
            foreach([ini_get('error_log'),'/tmp/php_errors.log','/var/log/php_errors.log'] as $pl)
                if($pl&&file_exists($pl)&&is_readable($pl)){$fLog=$pl;break;}
            echo '<div class="card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse">';
            echo '<tr><td colspan="2" style="padding:8px 12px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)">🚨 Error Log PHP</td></tr>';
            echo $row('Caminho',     htmlspecialchars($errLog?:'não definido'));
            echo $row('Encontrado',  $fLog?htmlspecialchars($fLog):"<span class='dwn'>Não encontrado</span>");
            echo $row('Tamanho',     $errSz>0?round($errSz/1024).' KB':"<span class='dwn'>0 / não encontrado</span>");
            echo $row('display_errors', ini_get('display_errors')?"<span class='dwn'>On</span>":"<span class='dok'>Off ✓</span>");
            echo $row('log_errors',  ini_get('log_errors')?"<span class='dok'>On ✓</span>":"<span class='derr'>Off</span>");
            echo '</table></div>';
            if($errCt){
                $el=array_filter(explode("\n",$errCt),fn($l)=>str_contains($l,'PHP Fatal')||str_contains($l,'PHP Warning')||str_contains($l,'PHP Error')||str_contains($l,'PHP Notice'));
                $el=array_slice(array_values($el),-100);
                if($el){
                    $colored=array_map(fn($l)=>str_contains($l,'Fatal')?"<span style='color:#e74c3c'>".htmlspecialchars($l)."</span>":(str_contains($l,'Warning')?"<span style='color:#f39c12'>".htmlspecialchars($l)."</span>":"<span style='color:#888'>".htmlspecialchars($l)."</span>"),$el);
                    echo '<div class="card" style="margin-top:16px;padding:12px"><pre style="font-size:11px;white-space:pre-wrap;max-height:400px;overflow-y:auto;margin:0">'.implode("\n",$colored).'</pre></div>';
                } else echo '<div class="card" style="margin-top:16px;padding:20px;text-align:center"><span class="dok">✅ Nenhum erro PHP no log!</span></div>';
            } else echo '<div class="card" style="margin-top:16px;padding:20px;color:var(--txt3);text-align:center">Log não encontrado. Adicione <code>error_log=/tmp/php_errors.log</code> no php.ini</div>';

        } elseif ($s==='phpinfo') {
            ob_start(); phpinfo(); $pi=ob_get_clean();
            preg_match('/<body[^>]*>(.*?)<\/body>/si',$pi,$m);
            $body=preg_replace('/<style[^>]*>.*?<\/style>/si','',$m[1]??'');
            echo '<div class="card" style="font-size:11px;color:var(--txt)">'.$body.'</div>';

        } elseif ($s==='export') {
            // Export roda aqui dentro do ob — mas vamos limpar o buffer e enviar headers
            ob_end_clean();
            $fmt=$_GET['fmt']??'txt';

            $expected=['users','reports','alerts','alert_logs','report_logs','clients','ad_accounts','whatsapp_instances','notifications','campaign_metrics','message_templates','activity_log'];
            $tables=[];
            foreach($expected as $tbl){try{Database::getInstance()->query("SELECT 1 FROM `$tbl` LIMIT 1");$tables[]=$tbl;}catch(\Throwable $e){}}
            $missing=array_diff($expected,$tables);
            $dbOk=false;try{Database::getInstance()->query("SELECT 1");$dbOk=true;}catch(\Throwable $e){}
            $wa=$q1("SELECT COUNT(*) c FROM whatsapp_instances WHERE status='connected'")['c']??0;
            $metaAct=$q1("SELECT COUNT(*) c FROM ad_accounts WHERE status='active'")['c']??0;
            $lc=$q1("SELECT MAX(created_at) t FROM alert_logs WHERE tipo_envio='automatico'");
            $aH=$q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE()")['c']??0;
            $eH=$q1("SELECT COUNT(*) c FROM alert_logs WHERE DATE(created_at)=CURDATE() AND status='erro'")['c']??0;
            $dT=disk_total_space('/'); $dF=disk_free_space('/'); $dU=$dT-$dF; $dP=$dT>0?round($dU/$dT*100):0;
            $fB=fn($b)=>$b>=1073741824?round($b/1073741824,1).'GB':round($b/1048576).'MB';
            $load=sys_getloadavg(); $lStr=$load?implode(' / ',array_map(fn($l)=>round($l,2),$load)):'N/A';
            $up=@file_get_contents('/proc/uptime'); $uS=$up?(int)explode(' ',$up)[0]:0;
            $uStr=$uS>86400?round($uS/86400).'d '.round(($uS%86400)/3600).'h':($uS>3600?round($uS/3600).'h '.round(($uS%3600)/60).'min':round($uS/60).'min');
            $counts=['Usuários'=>$q1("SELECT COUNT(*) c FROM users")['c']??0,'Relatórios'=>$q1("SELECT COUNT(*) c FROM reports")['c']??0,'Alertas ativos'=>$q1("SELECT COUNT(*) c FROM alerts WHERE ativo=1")['c']??0,'Clientes'=>$q1("SELECT COUNT(*) c FROM clients")['c']??0,'Contas Ads'=>$q1("SELECT COUNT(*) c FROM ad_accounts")['c']??0,'WhatsApp inst.'=>$q1("SELECT COUNT(*) c FROM whatsapp_instances")['c']??0,'Logs alertas'=>$q1("SELECT COUNT(*) c FROM alert_logs")['c']??0,'Logs relatórios'=>$q1("SELECT COUNT(*) c FROM report_logs")['c']??0,'Métricas'=>$q1("SELECT COUNT(*) c FROM campaign_metrics")['c']??0,'Templates'=>$q1("SELECT COUNT(*) c FROM message_templates")['c']??0];
            $imp=['curl','pdo','pdo_mysql','mbstring','json','openssl','gd','zip','opcache','intl'];
            $ex=get_loaded_extensions();
            $waInst=$q("SELECT instance_name,status,phone_number FROM whatsapp_instances");
            $metaC=$q("SELECT account_name,account_id,platform,status,token_expires FROM ad_accounts");
            $ultA=$q("SELECT alert_name,status,destinatario,created_at FROM alert_logs ORDER BY id DESC LIMIT 20");
            $ultR=$q("SELECT rl.status,r.title,rl.destinatario,rl.created_at FROM report_logs rl LEFT JOIN reports r ON rl.report_id=r.id ORDER BY rl.id DESC LIMIT 10");
            $errLog=ini_get('error_log'); $errCt='';
            if($errLog&&file_exists($errLog)){$fp=fopen($errLog,'r');if($fp){$sz=filesize($errLog);fseek($fp,-min($sz,80000),SEEK_END);$raw=fread($fp,80000);fclose($fp);$el=array_filter(explode("\n",$raw),fn($l)=>str_contains($l,'PHP Fatal')||str_contains($l,'PHP Warning')||str_contains($l,'PHP Error'));$errCt=implode("\n",array_slice(array_values($el),-30));}}
            $now=date('d/m/Y H:i:s'); $fn='gestorads_debug_'.date('Y-m-d_H-i-s');
            if($fmt==='txt'){
                header('Content-Type: text/plain; charset=UTF-8');
                header('Content-Disposition: attachment; filename="'.$fn.'.txt"');
                $s60=str_repeat('=',60); $s2=str_repeat('-',60);
                echo "GESTORADS - RELATORIO DE DEBUG\nGerado em: $now\nSistema: ".APP_NAME." | ".APP_ENV." | PHP ".PHP_VERSION."\n$s60\n\n";
                echo "CONTAGENS\n$s2\n";
                foreach($counts as $k=>$v) echo str_pad($k,28).$v."\n";
                echo "\nBANCO DE DADOS\n$s2\n";
                echo str_pad('Conexao',28).($dbOk?'OK':'FALHOU')."\n";
                echo str_pad('Tabelas',28).count($tables)."\n";
                echo str_pad('Faltando',28).(empty($missing)?'Nenhuma':implode(', ',$missing))."\n";
                echo str_pad('Lista',28).implode(', ',$tables)."\n";
                echo "\nSERVICOS\n$s2\n";
                echo str_pad('WhatsApp conectado',28).$wa."\n";
                echo str_pad('Meta Ads ativo',28).$metaAct."\n";
                echo str_pad('PHP',28).PHP_VERSION."\n";
                echo "\nCRONS HOJE\n$s2\n";
                echo str_pad('Ultimo envio alerta',28).($lc['t']??'Nenhum')."\n";
                echo str_pad('Envios hoje',28).$aH."\n";
                echo str_pad('Erros hoje',28).$eH."\n";
                echo "\nSERVIDOR\n$s2\n";
                echo str_pad('OS',28).php_uname('s').' '.php_uname('r')."\n";
                echo str_pad('Hostname',28).php_uname('n')."\n";
                echo str_pad('Uptime',28).$uStr."\n";
                echo str_pad('Load avg 1/5/15min',28).$lStr."\n";
                echo str_pad('Disco total',28).$fB($dT)."\n";
                echo str_pad('Disco usado',28).$fB($dU)." ({$dP}%)\n";
                echo str_pad('Disco livre',28).$fB($dF)."\n";
                echo str_pad('Mem PHP usada',28).$fB(memory_get_usage(true))."\n";
                echo str_pad('Mem PHP pico',28).$fB(memory_get_peak_usage(true))."\n";
                echo str_pad('Mem limite',28).ini_get('memory_limit')."\n";
                echo "\nPHP\n$s2\n";
                echo str_pad('Versao',28).PHP_VERSION."\n";
                echo str_pad('SAPI',28).php_sapi_name()."\n";
                echo str_pad('Max execution',28).ini_get('max_execution_time')."s\n";
                echo str_pad('Upload max',28).ini_get('upload_max_filesize')."\n";
                echo str_pad('Post max',28).ini_get('post_max_size')."\n";
                echo str_pad('Timezone',28).date_default_timezone_get()."\n";
                echo str_pad('Error log',28).(ini_get('error_log')?:'N/A')."\n";
                echo "\nEXTENSOES PHP\n$s2\n";
                foreach($imp as $e) echo str_pad($e,28).(in_array($e,$ex)?'OK':'AUSENTE')."\n";
                echo "\nCONFIGURACOES APP\n$s2\n";
                echo str_pad('APP_NAME',28).APP_NAME."\n";
                echo str_pad('APP_URL',28).APP_URL."\n";
                echo str_pad('APP_ENV',28).APP_ENV."\n";
                echo str_pad('APP_VERSION',28).APP_VERSION."\n";
                echo str_pad('DB',28).DB_HOST.':'.DB_PORT.' / '.DB_NAME."\n";
                echo str_pad('SMTP',28).MAIL_HOST.':'.MAIL_PORT.' / '.MAIL_USER."\n";
                echo str_pad('META_API_VERSION',28).META_API_VERSION."\n";
                echo str_pad('EVOLUTION_API_URL',28).EVOLUTION_API_URL."\n";
                echo "\nINSTANCIAS WHATSAPP\n$s2\n";
                foreach($waInst as $w) echo str_pad($w['instance_name'],30).str_pad($w['status'],15).($w['phone_number']??'—')."\n";
                echo "\nCONTAS META ADS\n$s2\n";
                foreach($metaC as $m) echo str_pad($m['account_name'],30).str_pad($m['status'],12).str_pad($m['platform'],12).'exp: '.($m['token_expires']??'N/A')."\n";
                echo "\nULTIMOS 20 ALERTAS\n$s2\n";
                foreach($ultA as $a) echo str_pad($a['alert_name']??'—',35).str_pad($a['status'],10).str_pad($a['destinatario']??'—',20).$a['created_at']."\n";
                echo "\nULTIMOS 10 RELATORIOS\n$s2\n";
                foreach($ultR as $r) echo str_pad($r['title']??'—',35).str_pad($r['status'],10).str_pad($r['destinatario']??'—',20).$r['created_at']."\n";
                if($errCt){ echo "\nULTIMOS ERROS PHP\n$s2\n".$errCt."\n"; }
                echo "\n$s60\n".APP_NAME." Debug Export - $now\n";
            } else {
                header('Content-Type: text/html; charset=UTF-8');
                header('Content-Disposition: attachment; filename="'.$fn.'.html"');
                $r2=fn($k,$v)=>"<tr><td style='padding:5px 10px;color:#555;width:200px'>$k</td><td style='padding:5px 10px;font-family:monospace;font-size:11px'>$v</td></tr>";
                $th2=fn($t)=>"<h2 style='color:#2563eb;font-size:13px;margin:18px 0 6px;padding:4px 0 4px 10px;border-left:3px solid #2563eb'>$t</h2>";
                $tb2=fn($hs,$rs)=>'<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><thead><tr>'.implode('',array_map(fn($h)=>"<th style='background:#2563eb;color:#fff;padding:5px 10px;text-align:left'>$h</th>",$hs)).'</tr></thead><tbody>'.implode('',array_map(fn($r2i)=>'<tr>'.implode('',array_map(fn($c)=>"<td style='padding:5px 10px;border-bottom:1px solid #eee'>$c</td>",$r2i)).'</tr>',$rs)).'</tbody></table>';
                echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>GestorADS Debug Export</title><style>body{font-family:Arial,sans-serif;font-size:12px;color:#222;padding:24px;background:#fff;max-width:900px;margin:0 auto}h1{color:#1e3a5f;border-bottom:2px solid #2563eb;padding-bottom:8px;font-size:18px}.sub{color:#666;font-size:11px;margin-bottom:20px}.ok{color:#16a34a;font-weight:700}.err{color:#dc2626;font-weight:700}.warn{color:#d97706;font-weight:700}tr:nth-child(even)td{background:#f5f7fa}@media print{.noprint{display:none}}</style><script>window.onload=()=>window.print()</script></head><body>';
                echo '<div class="noprint" style="margin-bottom:16px"><button onclick="window.print()" style="padding:8px 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">🖨️ Imprimir / Salvar PDF</button></div>';
                echo '<h1>🔧 GestorADS — Relatório de Debug</h1><div class="sub">Gerado em: '.$now.' | '.APP_NAME.' | PHP '.PHP_VERSION.' | '.APP_ENV.'</div>';
                echo $th2('📊 Contagens');
                echo $tb2(['Item','Qtd'],array_map(fn($k,$v)=>[$k,"<strong>$v</strong>"],array_keys($counts),array_values($counts)));
                echo $th2('🗄️ Banco');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('Conexão',$dbOk?"<span class='ok'>✓ Conectado</span>":"<span class='err'>✗ FALHOU</span>");
                echo $r2('Tabelas',count($tables));
                echo $r2('Faltando',empty($missing)?"<span class='ok'>Nenhuma</span>":"<span class='err'>".implode(', ',$missing)."</span>");
                echo '</tbody></table>';
                echo $th2('⚙️ Serviços');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('WhatsApp',$wa>0?"<span class='ok'>$wa conectada(s)</span>":"<span class='err'>0</span>");
                echo $r2('Meta Ads',$metaAct>0?"<span class='ok'>$metaAct ativa(s)</span>":"<span class='err'>0</span>");
                echo $r2('PHP',PHP_VERSION); echo $r2('SAPI',php_sapi_name());
                echo '</tbody></table>';
                echo $th2('⏰ Crons');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('Último envio',$lc['t']??'<span class="warn">Nenhum</span>');
                echo $r2('Envios hoje',"<span class='ok'>$aH</span>");
                echo $r2('Erros hoje',$eH>0?"<span class='err'>$eH</span>":"<span class='ok'>0</span>");
                echo '</tbody></table>';
                echo $th2('🖥️ Servidor');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('OS',php_uname('s').' '.php_uname('r')); echo $r2('Hostname',php_uname('n'));
                echo $r2('Uptime',$uStr); echo $r2('Load avg',$lStr);
                echo $r2('Disco total',$fB($dT)); echo $r2('Disco usado',$fB($dU)." ({$dP}%)"); echo $r2('Disco livre',$fB($dF));
                echo $r2('Mem usada',$fB(memory_get_usage(true))); echo $r2('Mem pico',$fB(memory_get_peak_usage(true))); echo $r2('Mem limite',ini_get('memory_limit'));
                echo '</tbody></table>';
                echo $th2('🐘 PHP');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('Versão',PHP_VERSION); echo $r2('SAPI',php_sapi_name());
                echo $r2('Max exec',ini_get('max_execution_time').'s'); echo $r2('Upload max',ini_get('upload_max_filesize'));
                echo $r2('Post max',ini_get('post_max_size')); echo $r2('Timezone',date_default_timezone_get());
                echo $r2('Error log',ini_get('error_log')?:'N/A');
                echo '</tbody></table>';
                echo $th2('🔌 Extensões PHP');
                echo $tb2(['Extensão','Status'],array_map(fn($e)=>[$e,in_array($e,$ex)?"<span class='ok'>✓ Carregada</span>":"<span class='err'>✗ Ausente</span>"],$imp));
                echo $th2('⚙️ App');
                echo '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px"><tbody>';
                echo $r2('APP_NAME',APP_NAME); echo $r2('APP_URL',APP_URL); echo $r2('APP_ENV',APP_ENV); echo $r2('APP_VERSION',APP_VERSION);
                echo $r2('DB',DB_HOST.':'.DB_PORT.' / '.DB_NAME); echo $r2('SMTP',MAIL_HOST.':'.MAIL_PORT.' / '.MAIL_USER);
                echo $r2('Meta API',META_API_VERSION); echo $r2('Evolution URL',EVOLUTION_API_URL);
                echo '</tbody></table>';
                if($waInst){ echo $th2('💬 WhatsApp'); echo $tb2(['Nome','Status','Telefone'],array_map(fn($w)=>[$w['instance_name'],$w['status']==='connected'?"<span class='ok'>connected</span>":"<span class='err'>".$w['status']."</span>",$w['phone_number']??'—'],$waInst)); }
                if($metaC){ echo $th2('📘 Meta Ads'); echo $tb2(['Nome','ID','Plataforma','Status','Expira'],array_map(fn($m)=>[$m['account_name'],$m['account_id'],$m['platform'],$m['status']==='active'?"<span class='ok'>active</span>":"<span class='err'>".$m['status']."</span>",$m['token_expires']??'—'],$metaC)); }
                if($ultA){ echo $th2('📜 Últimos 20 Alertas'); echo $tb2(['Alerta','Status','Destinatário','Data'],array_map(fn($a)=>[$a['alert_name']??'—',$a['status']==='enviado'?"<span class='ok'>enviado</span>":"<span class='err'>".$a['status']."</span>",$a['destinatario']??'—',$a['created_at']],$ultA)); }
                if($ultR){ echo $th2('📋 Últimos 10 Relatórios'); echo $tb2(['Relatório','Status','Destinatário','Data'],array_map(fn($r2i)=>[$r2i['title']??'—',$r2i['status']==='enviado'?"<span class='ok'>enviado</span>":"<span class='err'>".$r2i['status']."</span>",$r2i['destinatario']??'—',$r2i['created_at']],$ultR)); }
                if($errCt){ echo $th2('🚨 Últimos Erros PHP'); echo '<pre style="background:#1e1e2e;color:#f8f8f2;padding:12px;border-radius:6px;font-size:10px;white-space:pre-wrap">'.htmlspecialchars($errCt).'</pre>'; }
                echo '<div style="margin-top:24px;border-top:1px solid #eee;padding-top:8px;color:#999;font-size:10px;text-align:center">'.APP_NAME.' Debug Export — '.$now.'</div></body></html>';
            }
            exit;
        }

        echo '</div>';
        $pageContent = ob_get_clean();
        require_once __DIR__.'/../views/layouts/main.php';
    }

}

// ============================================================
// WebhookController
// ============================================================
class WebhookController {
    public function whatsapp(): void {
        $rawBody = file_get_contents('php://input');
        if (!$rawBody) { http_response_code(400); exit; }

        // ── Validação HMAC — só aceita chamadas da Evolution API ─────────────
        // A Evolution API envia o header: apikey: SUA_CHAVE
        // Valida comparando com a chave configurada no .env
        $apiKeyHeader = $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (empty(EVOLUTION_API_KEY) || !hash_equals(EVOLUTION_API_KEY, $apiKeyHeader)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) { http_response_code(400); exit; }
        // Registra evento de conexão
        $event = $payload['event'] ?? '';
        if (in_array($event, ['connection.update','status.instance'])) {
            $instanceName = $payload['instance'] ?? '';
            $state        = $payload['data']['state'] ?? 'unknown';
            if ($instanceName) {
                $db     = Database::getInstance();
                $status = $state === 'open' ? 'connected' : ($state === 'close' ? 'disconnected' : 'qr_pending');
                $db->query("UPDATE whatsapp_instances SET status=? WHERE instance_name=?",[$status,$instanceName]);
            }
        }
        http_response_code(200);
        echo json_encode(['ok'=>true]);
        exit;
    }
}

// ============================================================
// MetricsController (cron trigger)
// ============================================================
class MetricsController {
    public function sync(): void {
        requireAuth();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $accs = $db->query("SELECT * FROM ad_accounts WHERE user_id=? AND status='active'",[$uid])->fetchAll();
        $ctrl = new AccountController();
        foreach ($accs as $acc) {
            $ctrl->sync(); // reutiliza lógica
        }
        jsonResponse(['ok'=>true,'count'=>count($accs)]);
    }
}
