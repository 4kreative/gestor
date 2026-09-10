<?php
class SettingsController {

    public function index(): void {
        requireAdmin();
        $db       = Database::getInstance();
        try {
            $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch();
        } catch (\Exception $e) { $settings = []; }
        if (!$settings) {
            $db->query("INSERT INTO system_settings (site_name) VALUES ('GestorPro')");
            $settings = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch();
        }
        require_once __DIR__.'/../views/settings/index.php';
    }

    public function update(): void {
        requireAdmin();
        csrfCheck();
        $db       = Database::getInstance();
        $siteName = sanitize($_POST['site_name'] ?? 'GestorPro');

        $uploadDir = dirname(__DIR__).'/public/img/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileMap = [
            'logo'       => 'logo_path',
            'favicon'    => 'favicon_path',
            'login_logo' => 'login_logo_path',
            'login_bg'   => 'login_bg_path',
        ];

        $updates = ['site_name' => $siteName];

        // Credenciais Meta API — só atualiza se veio preenchido
        if (isset($_POST['meta_app_id']) && trim($_POST['meta_app_id']) !== '') {
            $updates['meta_app_id'] = trim($_POST['meta_app_id']);
        }
        if (isset($_POST['meta_app_secret']) && trim($_POST['meta_app_secret']) !== '') {
            $updates['meta_app_secret'] = trim($_POST['meta_app_secret']);
        }

        // Evolution API — só atualiza se veio preenchido
        if (isset($_POST['evolution_api_url']) && trim($_POST['evolution_api_url']) !== '') {
            $updates['evolution_api_url'] = trim($_POST['evolution_api_url']);
        }
        if (isset($_POST['evolution_api_key']) && trim($_POST['evolution_api_key']) !== '') {
            $updates['evolution_api_key'] = trim($_POST['evolution_api_key']);
        }

        // Email SMTP — só atualiza se veio preenchido
        if (isset($_POST['mail_host'])      && trim($_POST['mail_host'])      !== '') $updates['mail_host']      = trim($_POST['mail_host']);
        if (isset($_POST['mail_port'])      && (int)$_POST['mail_port']       > 0)   $updates['mail_port']      = (int)$_POST['mail_port'];
        if (isset($_POST['mail_user'])      && trim($_POST['mail_user'])      !== '') $updates['mail_user']      = trim($_POST['mail_user']);
        if (isset($_POST['mail_from_name']) && trim($_POST['mail_from_name']) !== '') $updates['mail_from_name'] = trim($_POST['mail_from_name']);
        if (isset($_POST['mail_pass'])      && trim($_POST['mail_pass'])      !== '') $updates['mail_pass']      = trim($_POST['mail_pass']);

        // Limpar campos explicitamente (via botão de reset)
        foreach (['meta_app_id','meta_app_secret','evolution_api_url','evolution_api_key',
                  'mail_host','mail_user','mail_pass','mail_from_name'] as $_clearField) {
            if (!empty($_POST['clear_' . $_clearField])) {
                $updates[$_clearField] = '';
            }
        }

        foreach ($fileMap as $inputName => $dbColumn) {
            if (empty($_FILES[$inputName]['name']) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            $file = $_FILES[$inputName];

            // CORREÇÃO: valida MIME real com finfo, não apenas extensão
            // SVG aceito apenas para logo/favicon (admin only), nunca para login_bg
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo->file($file['tmp_name']);

            $allowedMime = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/x-icon'       => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
            ];

            // SVG: permitido apenas para logo e favicon (nunca login_bg), validando conteúdo
            if (in_array($inputName, ['logo', 'favicon', 'login_logo'])) {
                $allowedMime['image/svg+xml'] = 'svg';
            }

            if (!isset($allowedMime[$mimeReal])) {
                flash('error', "Formato inválido para $inputName. Tipos aceitos: PNG, JPG, GIF, WEBP, ICO" .
                    (in_array($inputName, ['logo','favicon','login_logo']) ? ', SVG' : '') . '.');
                redirect('/settings');
            }

            // Se for SVG, sanitiza com bloqueio completo de vetores XSS
            if ($mimeReal === 'image/svg+xml') {
                $svgContent = file_get_contents($file['tmp_name']);
                // Remove blocos <script>
                $svgContent = preg_replace('/<script[\s\S]*?<\/script>/i', '', $svgContent);
                // Remove tags perigosas: use, animate, foreignObject, iframe, object, embed
                $svgContent = preg_replace('/<(use|animate|animateMotion|animateTransform|set|foreignObject|iframe|object|embed)[\s\S]*?\/?\s*>/i', '', $svgContent);
                // Remove atributos on* (event handlers)
                $svgContent = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $svgContent);
                // Remove javascript: em qualquer atributo (href, xlink:href, action, src, etc.)
                $svgContent = preg_replace('/\b(href|xlink:href|src|action|data|formaction)\s*=\s*["\']?\s*javascript\s*:[^"\'>\s]*/i', '', $svgContent);
                // Remove xlink:href e href com valores externos (permite apenas # para referências internas)
                $svgContent = preg_replace('/\b(xlink:href|href)\s*=\s*["\'](?!#)[^"\']*["\']/i', '', $svgContent);
                // Remove base64 em src/href (pode esconder código)
                $svgContent = preg_replace('/\b(href|src)\s*=\s*["\']data:[^"\']*["\']/i', '', $svgContent);
                file_put_contents($file['tmp_name'], $svgContent);
            }

            if ($file['size'] > 2 * 1024 * 1024) {
                flash('error', "Arquivo $inputName muito grande (máx 2MB).");
                redirect('/settings');
            }

            $ext      = $allowedMime[$mimeReal];
            $filename = $dbColumn.'_'.time().'.'.$ext;
            $dest     = $uploadDir.$filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                try {
                    $old = $db->query("SELECT $dbColumn FROM system_settings LIMIT 1")->fetchColumn();
                    if ($old && file_exists($uploadDir.$old)) @unlink($uploadDir.$old);
                } catch (\Exception $e) {}
                $updates[$dbColumn] = $filename;
            } else {
                flash('error', "Erro ao salvar $inputName. Verifique permissões da pasta public/img/uploads/");
                redirect('/settings');
            }
        }

        // #8 — Whitelist explícita de colunas permitidas no UPDATE dinâmico
        $allowedColumns = [
            'site_name', 'logo_path', 'favicon_path', 'login_logo_path', 'login_bg_path',
            'meta_app_id', 'meta_app_secret',
            'evolution_api_url', 'evolution_api_key',
            'mail_host', 'mail_port', 'mail_user', 'mail_pass', 'mail_from_name',
        ];
        $updates = array_filter($updates, fn($k) => in_array($k, $allowedColumns, true), ARRAY_FILTER_USE_KEY);
        $sets   = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($updates)));
        $values = array_values($updates);
        $settingsId = $db->query("SELECT id FROM system_settings LIMIT 1")->fetchColumn();
        if (!$settingsId) {
            $db->query("INSERT INTO system_settings (site_name) VALUES (?)", [$siteName]);
            $settingsId = $db->query("SELECT id FROM system_settings LIMIT 1")->fetchColumn();
        }
        $values[] = $settingsId;
        $db->query("UPDATE system_settings SET $sets WHERE id=?", $values);

        $imgCount = count($updates) - 1;
        flash('success', 'Configurações salvas!' . ($imgCount > 0 ? " ($imgCount imagem(ns) atualizada(s))" : ''));
        redirect('/settings');
    }

    public function reset(): void {
        requireAdmin();
        csrfCheck();
        $field   = sanitize($_POST['field'] ?? '');
        $allowed = ['logo_path','favicon_path','login_bg_path','login_logo_path'];
        if (!in_array($field, $allowed)) redirect('/settings');

        $db        = Database::getInstance();
        $uploadDir = dirname(__DIR__).'/public/img/uploads/';
        try {
            $old = $db->query("SELECT `$field` FROM system_settings LIMIT 1")->fetchColumn();
            if ($old && file_exists($uploadDir.$old)) @unlink($uploadDir.$old);
        } catch (\Exception $e) {}
        $db->query("UPDATE system_settings SET `$field`=NULL WHERE id=(SELECT mid FROM (SELECT MIN(id) as mid FROM system_settings) t)");
        flash('success', 'Imagem removida.');
        redirect('/settings');
    }
}
