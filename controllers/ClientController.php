<?php
class ClientController {
    public function index(): void {
        requireAuth();
        $uid    = currentUser()['id'];
        $db     = Database::getInstance();
        $page   = max(1,(int)($_GET['page']??1));
        $q      = sanitize($_GET['q']??'');

        $where  = "WHERE user_id=?";
        $params = [$uid];
        if ($q) { $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $p="%$q%"; $params[]=$p;$params[]=$p;$params[]=$p; }

        $total   = $db->query("SELECT COUNT(*) FROM clients $where",$params)->fetchColumn();
        $pages   = max(1,ceil($total/ITEMS_PER_PAGE));
        $offset  = ($page-1)*ITEMS_PER_PAGE;
        $clients = $db->query("SELECT * FROM clients $where ORDER BY name LIMIT ".ITEMS_PER_PAGE." OFFSET $offset",$params)->fetchAll();

        require_once __DIR__.'/../views/clients/index.php';
    }

    public function create(): void {
        requireAuth();
        require_once __DIR__.'/../views/clients/form.php';
    }

    public function store(): void {
        requireAuth(); csrfCheck();
        $uid  = currentUser()['id'];
        $db   = Database::getInstance();
        $name = sanitize($_POST['name'] ?? '');
        if (!$name) { flash('error','Nome obrigatório.'); redirect('/clients/create'); }

        // Verifica limite do plano
        $count = (int)$db->query("SELECT COUNT(*) FROM clients WHERE user_id=?",[$uid])->fetchColumn();
        if (!planAllows('clients',$count)) { flash('error','Limite de clientes do seu plano atingido. Faça upgrade!'); redirect('/clients'); }

        $db->query("INSERT INTO clients (user_id,name,email,phone,company,notes,payment_type,saldo_alerta) VALUES (?,?,?,?,?,?,?,?)",[
            $uid,
            $name,
            sanitize($_POST['email']  ??''),
            sanitize($_POST['phone']  ??''),
            sanitize($_POST['company']??''),
            sanitize($_POST['notes']  ??''),
            in_array(($_POST['payment_type']??'cartao'),['prepago','cartao']) ? $_POST['payment_type'] : 'cartao',
            (float)($_POST['saldo_alerta'] ?? 50),
        ]);
        flash('success','Cliente cadastrado!');
        redirect('/clients');
    }

    public function edit(): void {
        requireAuth();
        $uid    = currentUser()['id'];
        $client = Database::getInstance()->query("SELECT * FROM clients WHERE id=? AND user_id=?",[(int)($_GET['id']??0),$uid])->fetch();
        if (!$client) { flash('error','Cliente não encontrado.'); redirect('/clients'); }
        require_once __DIR__.'/../views/clients/form.php';
    }

    public function update(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        $id  = (int)($_POST['id']??0);
        Database::getInstance()->query(
            "UPDATE clients SET name=?,email=?,phone=?,company=?,notes=?,status=?,payment_type=?,saldo_alerta=? WHERE id=? AND user_id=?",[
            sanitize($_POST['name']   ??''),
            sanitize($_POST['email']  ??''),
            sanitize($_POST['phone']  ??''),
            sanitize($_POST['company']??''),
            sanitize($_POST['notes']  ??''),
            (sanitize($_POST['status']??'active')==='active'?'active':'inactive'),
            in_array(($_POST['payment_type']??'cartao'),['prepago','cartao']) ? $_POST['payment_type'] : 'cartao',
            (float)($_POST['saldo_alerta'] ?? 50),
            $id, $uid
        ]);
        flash('success','Cliente atualizado!');
        redirect('/clients');
    }

    public function delete(): void {
        requireAuth(); csrfCheck();
        $uid = currentUser()['id'];
        Database::getInstance()->query("DELETE FROM clients WHERE id=? AND user_id=?",[(int)($_POST['id']??0),$uid]);
        flash('success','Cliente excluído.');
        redirect('/clients');
    }

    public function toggleStatus(): void {
        requireAuth(); csrfCheck();
        header('Content-Type: application/json');
        $uid    = currentUser()['id'];
        $id     = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';
        $db     = Database::getInstance();
        $client = $db->query("SELECT id FROM clients WHERE id=? AND user_id=?", [$id,$uid])->fetch();
        if (!$client) { echo json_encode(['success'=>false,'error'=>'Cliente não encontrado']); return; }
        $db->query("UPDATE clients SET status=? WHERE id=? AND user_id=?", [$status, $id, $uid]);
        echo json_encode(['success'=>true]);
    }
}
