<?php
require_once __DIR__.'/core/App.php';

// ============================================================
// Router simples
// ============================================================
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim(str_replace(parse_url(APP_URL, PHP_URL_PATH) ?? '', '', $uri), '/');
$uri    = $uri ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Rotas públicas
$publicRoutes = [
    '/primeiro-acesso'      => ['AuthController', 'primeiroAcessoPage'],
    '/primeiro-acesso/post'  => ['AuthController', 'primeiroAcessoPost'],
    '/ativacao'      => ['AuthController', 'ativacaoPage'],
    '/ativacao/post' => ['AuthController', 'ativacaoPost'],
    '/r'             => ['ReportController', 'publicPdf'],
    '/privacy'       => ['PagesController', 'privacy'],
    '/terms'         => ['PagesController', 'terms'],
    '/data-deletion' => ['PagesController', 'dataDeletion'],
    '/'              => ['AuthController', 'loginPage'],
    '/login'         => ['AuthController', 'loginPage'],
    '/login/post'    => ['AuthController', 'login'],
    '/register'      => ['AuthController', 'registerPage'],
    '/register/post' => ['AuthController', 'register'],
    '/forgot'        => ['AuthController', 'forgotPage'],
    '/forgot/post'   => ['AuthController', 'forgotPost'],
    '/reset'         => ['AuthController', 'resetPage'],
    '/reset/post'    => ['AuthController', 'resetPost'],
    '/logout'        => ['AuthController', 'logout'],
];

// Rotas protegidas
$privateRoutes = [
    '/dashboard'              => ['DashboardController', 'index'],

    '/reports'                => ['ReportController', 'index'],
    '/reports/create'         => ['ReportController', 'create'],
    '/reports/store'          => ['ReportController', 'store'],
    '/reports/edit'           => ['ReportController', 'edit'],
    '/reports/update'         => ['ReportController', 'update'],
    '/reports/delete'         => ['ReportController', 'delete'],
    '/reports/send'           => ['ReportController', 'send'],
    '/reports/preview'        => ['ReportController', 'preview'],
    '/reports/toggle-status'  => ['ReportController', 'toggleStatus'],
    '/reports/rt-metrics'     => ['ReportController', 'realtimeMetrics'],
    '/reports/rt-chat'        => ['ReportController', 'realtimeChat'],
    '/reports/rt-send'        => ['ReportController', 'realtimeSend'],
    '/reports/rt-message'     => ['ReportController', 'realtimeMessage'],
    '/reports/rt-names'       => ['ReportController', 'realtimeNames'],
    '/reports/rt-save-sel'    => ['ReportController', 'realtimeSaveSelection'],
    '/reports/pdf'            => ['ReportController', 'generatePdf'],
    '/reports/pdf-editor'     => ['ReportController', 'pdfEditor'],
    '/reports/pdf-template/save'   => ['ReportController', 'savePdfTemplate'],
    '/reports/pdf-template/delete' => ['ReportController', 'deletePdfTemplate'],
    '/reports/share-token'         => ['ReportController', 'generateShareToken'],
    '/reports/send-pdf-link'        => ['ReportController', 'sendPdfLink'],
    '/dashboard/save-note'    => ['DashboardController', 'saveNote'],
    '/dashboard/delete-note'  => ['DashboardController', 'deleteNote'],
    '/dashboard/client'       => ['DashboardController', 'clientDashboard'],

    '/clients'                => ['ClientController', 'index'],
    '/clients/create'         => ['ClientController', 'create'],
    '/clients/store'          => ['ClientController', 'store'],
    '/clients/edit'           => ['ClientController', 'edit'],
    '/clients/update'         => ['ClientController', 'update'],
    '/clients/delete'         => ['ClientController', 'delete'],
    '/clients/toggle-status'  => ['ClientController', 'toggleStatus'],

    '/budget/list'            => ['BudgetController', 'list'],
    '/budget/recharge'        => ['BudgetController', 'addRecharge'],
    '/budget/history'         => ['BudgetController', 'history'],
    '/budget/set-type'        => ['BudgetController', 'setPaymentType'],

    '/accounts'               => ['AccountController', 'index'],
    '/accounts/connect/meta'       => ['AccountController', 'connectMeta'],
    '/accounts/connect/meta-token' => ['AccountController', 'connectMetaToken'],
    '/accounts/connect/google'=> ['AccountController', 'connectGoogle'],
    '/accounts/delete'        => ['AccountController', 'delete'],
    '/accounts/delete-bulk'   => ['AccountController', 'deleteBulk'],
    '/accounts/sync'           => ['AccountController', 'sync'],
    '/accounts/link-client'    => ['AccountController', 'linkClient'],
    '/api/accounts/campaigns'   => ['AccountController', 'apiCampaigns'],

    '/whatsapp'               => ['WhatsAppController', 'index'],
    '/whatsapp/create'        => ['WhatsAppController', 'create'],
    '/whatsapp/qr'            => ['WhatsAppController', 'qr'],
    '/whatsapp/status'        => ['WhatsAppController', 'status'],
    '/whatsapp/delete'        => ['WhatsAppController', 'delete'],
    '/whatsapp/disconnect'     => ['WhatsAppController', 'disconnect'],
    '/whatsapp/test'          => ['WhatsAppController', 'test'],

    '/alerts'                 => ['AlertController', 'index'],
    '/alerts/store'           => ['AlertController', 'store'],
    '/alerts/edit'            => ['AlertController', 'edit'],
    '/alerts/update'          => ['AlertController', 'update'],
    '/alerts/delete'          => ['AlertController', 'delete'],
    '/alerts/toggle'          => ['AlertController', 'toggle'],
    '/alerts/send-manual'     => ['AlertController', 'sendManual'],
    '/alerts/fetchGroups'     => ['AlertController', 'fetchGroups'],
    '/alerts/getClientPhone'  => ['AlertController', 'getClientPhone'],
    '/alerts/logs'            => ['AlertController', 'logs'],

    '/notifications'          => ['NotificationController', 'index'],
    '/notifications/read'     => ['NotificationController', 'markRead'],

    '/profile'                => ['ProfileController', 'index'],
    '/profile/update'         => ['ProfileController', 'update'],
    '/profile/password'       => ['ProfileController', 'password'],
    '/profile/plan'           => ['ProfileController', 'plan'],

    '/templates'              => ['TemplateController', 'index'],
    '/variables'              => ['VariablesController', 'index'],
    '/templates/store'        => ['TemplateController', 'store'],
    '/templates/update'       => ['TemplateController', 'update'],
    '/templates/delete'       => ['TemplateController', 'delete'],

    // Admin
    '/settings'               => ['SettingsController', 'index'],
    '/settings/update'        => ['SettingsController', 'update'],
    '/settings/reset'         => ['SettingsController', 'reset'],
    '/ai'                     => ['AiController', 'index'],
    '/ai/chat'                => ['AiController', 'chat'],
    '/ai/save-keys'           => ['AiController', 'saveKeys'],
    '/ai/analyze'             => ['AiController', 'analyze'],
    '/ai/analyze-card'        => ['AiController', 'analyzeCard'],
    '/ai/send-analysis'       => ['AiController', 'sendAnalysis'],
    '/ai/send'                => ['AiController', 'sendAnalysis'],
    '/ai/sync-account'        => ['AiController', 'syncAccount'],
    '/ai/chat-report'         => ['AiController', 'chatReport'],
    '/ai/analyze-report'      => ['AiController', 'analyzeReport'],
    '/ai/history'             => ['AiController', 'history'],
    '/ai/save-model'          => ['AiController', 'saveModel'],

    '/admin'                  => ['AdminController', 'index'],
    '/admin/users'            => ['AdminController', 'users'],
    '/admin/user/edit'        => ['AdminController', 'editUser'],
    '/admin/user/update'      => ['AdminController', 'updateUser'],
    '/admin/debug'             => ['AdminController', 'debugPanel'],
    '/admin/user/create'      => ['AdminController', 'createUser'],

    '/integrations'               => ['IntegrationController', 'index'],
    '/integrations/create'        => ['IntegrationController', 'create'],
    '/integrations/update'        => ['IntegrationController', 'update'],
    '/integrations/delete'        => ['IntegrationController', 'delete'],
    '/integrations/toggle'        => ['IntegrationController', 'toggle'],
    '/api/integrations/clients'    => ['IntegrationController', 'apiClients'],
    '/api/integrations/templates'  => ['IntegrationController', 'apiTemplates'],
    '/api/integrations/groups'    => ['IntegrationController', 'apiGroups'],
    '/webhook/integration'        => ['IntegrationController', 'webhookReceive'],
    '/webhook/elementor'          => ['IntegrationController', 'elementorReceive'],
    '/webhook/tintim'             => ['IntegrationController', 'tintimReceive'],
    '/webhook/facebook-lead'      => ['IntegrationController', 'facebookLeadReceive'],

    '/help'                       => ['HelpController', 'index'],

];

// OAuth & Webhooks
$apiRoutes = [
    '/api/oauth/meta/callback'    => ['OAuthController', 'metaCallback'],
    '/api/oauth/google/callback'  => ['OAuthController', 'googleCallback'],
    '/api/webhook/whatsapp'       => ['WebhookController', 'whatsapp'],
    '/api/metrics/sync'           => ['MetricsController', 'sync'],
    '/api/reports/data'           => ['ReportController', 'apiData'],
    '/api/cron/sync-prepago'      => ['CronController', 'syncPrepago'],

    '/ads-agent'              => ['AdsAgentController', 'index'],
    '/ads-agent/chat'         => ['AdsAgentController', 'chat'],
    '/ads-agent/execute'      => ['AdsAgentController', 'execute'],
    '/ads-agent/save-key'     => ['AdsAgentController', 'saveAnthropicKey'],
    '/ads-agent/load-conv'    => ['AdsAgentController', 'loadConv'],
    '/ads-agent/delete-conv'  => ['AdsAgentController', 'deleteConv'],
    '/ads-agent/history'      => ['AdsAgentController', 'history'],
    '/ads-agent/upload-image' => ['AdsAgentController', 'uploadImage'],

    '/api/cron/sync-groups'       => ['CronController', 'syncGroups'],
    '/api/cron/sync-groups'       => ['CronController', 'syncGroups'],

    // Rotas de integração via /api/integrations/* (compatibilidade com sistemas externos)
    '/api/integrations/webhook'       => ['IntegrationController', 'webhookReceive'],
    '/api/integrations/elementor'     => ['IntegrationController', 'elementorReceive'],
    '/api/integrations/tintim'        => ['IntegrationController', 'tintimReceive'],
    '/api/integrations/facebook_lead' => ['IntegrationController', 'facebookLeadReceive'],
    '/api/integrations/autentique'    => ['IntegrationController', 'webhookReceive'],
];

// Resolve rota
function resolveRoute(string $uri, array $routes, bool $requireAuth=false): bool {
    foreach ($routes as $route => $handler) {
        if ($uri === $route) {
            if ($requireAuth) requireAuth();
            [$class, $action] = $handler;
            $ctrl = new $class();
            $ctrl->$action();
            return true;
        }
    }
    return false;
}

if (resolveRoute($uri, $publicRoutes)) exit;
if (resolveRoute($uri, $apiRoutes))    exit;
if (resolveRoute($uri, $privateRoutes, true)) exit;

// 404
http_response_code(404);
require_once __DIR__.'/views/layouts/404.php';
