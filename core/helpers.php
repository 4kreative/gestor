<?php
// ============================================================
// Helpers Globais – GestorPro
// ============================================================

/** Escapa HTML para prevenir XSS */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Redireciona para URL */
function redirect(string $url): never {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: '.APP_URL.$url);
    exit;
}

/** Verifica se usuário está autenticado */
function isAuth(): bool {
    return !empty($_SESSION['user_id']);
}

/** Exige autenticação – redireciona se não logado */
function requireAuth(): void {
    if (!isAuth()) {
        redirect('/login');
    }
}

/** Exige role admin */
function requireAdmin(): void {
    requireAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        redirect('/dashboard');
    }
}

/** Retorna dados do usuário logado da sessão */
function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']   ?? 0,
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email']?? '',
        'role'  => $_SESSION['user_role'] ?? 'user',
        'plan'  => $_SESSION['user_plan'] ?? 'trial',
        'avatar'=> $_SESSION['user_avatar']??'',
    ];
}

/** Flashbag – define mensagem de sucesso/erro para próxima página */
function flash(string $type, string $msg): void {
    $_SESSION['flash'][$type][] = $msg;
}

/** Retorna e limpa mensagens flash */
function getFlash(string $type=''): array|string {
    if ($type) {
        $msgs = $_SESSION['flash'][$type] ?? [];
        unset($_SESSION['flash'][$type]);
        return $msgs;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/** Valida CSRF token */
function csrfCheck(): void {
    $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        // Limpa qualquer output buffering pendente para não corromper o JSON
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.']);
        exit;
    }
}

/** Sanitiza string */
function sanitize(string $str): string {
    return trim(strip_tags($str));
}

/** Formata número como moeda BRL */
function brl(float $value): string {
    return 'R$ '.number_format($value, 2, ',', '.');
}

/** Verifica limite do plano do usuário atual */
function planAllows(string $feature, int $current): bool {
    $plan   = $_SESSION['user_plan'] ?? 'trial';
    $limits = unserialize(PLAN_LIMITS);
    $planLimits = $limits[$plan] ?? $limits['trial'];
    $max = $planLimits[$feature] ?? 999;
    return $current < $max;
}

/** Iniciais para avatar */
function initials(string $name): string {
    $words = explode(' ', trim($name));
    $init  = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) $init .= strtoupper(substr(end($words), 0, 1));
    return $init;
}

/** Formata número grande (1200 → 1.2K) */
function formatNum(float $n): string {
    if ($n >= 1000000) return number_format($n/1000000,1).'M';
    if ($n >= 1000)    return number_format($n/1000,1).'K';
    return number_format($n,0,'.',',');
}

/** JSON response */
function jsonResponse(array $data, int $code=200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Imagem padrão se não houver avatar */
function avatarUrl(string $avatar=''): string {
    if ($avatar) {
        // Usa __DIR__ para verificar no filesystem, não via HTTP
        $localPath = __DIR__.'/../public/img/avatars/'.$avatar;
        if (file_exists($localPath)) {
            return APP_URL.'/public/img/avatars/'.ltrim($avatar, '/');
        }
    }
    return APP_URL.'/public/img/default-avatar.png';
}

/**
 * Descriptografa access_token, refresh_token e meta_token de um array de linha do banco.
 * Suporta fetch() (array simples) e fetchAll() (array de arrays).
 * Tokens legados em texto puro são retornados sem modificação.
 */
function decryptTokens(array $row): array {
    if (!class_exists('TokenCrypto')) {
        require_once __DIR__.'/TokenCrypto.php';
    }
    if (empty($row)) return $row;
    if (isset($row[0]) && is_array($row[0])) {
        return array_map('decryptTokens', $row);
    }
    foreach (['access_token', 'refresh_token', 'meta_token'] as $field) {
        if (!empty($row[$field])) {
            $row[$field] = TokenCrypto::decrypt($row[$field]);
        }
    }
    return $row;
}

