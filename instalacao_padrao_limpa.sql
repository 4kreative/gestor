-- phpMyAdmin SQL Dump (versão limpa para instalação em qualquer hospedagem)
-- Login padrão gerado:
--   E-mail: admin@meusistema.com
--   Senha:  trocar123

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity` varchar(100) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `ad_accounts`;
CREATE TABLE `ad_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `platform` enum('meta','google') NOT NULL,
  `account_id` varchar(100) NOT NULL,
  `account_name` varchar(200) NOT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `status` enum('active','inactive','error') NOT NULL DEFAULT 'active',
  `prepago_balance` decimal(12,2) DEFAULT NULL COMMENT 'Saldo cacheado (balance ou spend_cap - amount_spent) em BRL',
  `prepago_ritmo_dia` decimal(12,2) DEFAULT NULL COMMENT 'Ritmo diário cacheado (daily_budget de campanhas/adsets ativos) em BRL',
  `prepago_synced_at` datetime DEFAULT NULL COMMENT 'Última vez que o cron sync_prepago atualizou este registro',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `ai_logs`;
CREATE TABLE `ai_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ad_account_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `account_name` varchar(255) NOT NULL DEFAULT '',
  `campaign_id` varchar(100) NOT NULL DEFAULT '',
  `campaign_name` varchar(255) NOT NULL DEFAULT '',
  `provider` varchar(50) NOT NULL DEFAULT '',
  `model` varchar(100) NOT NULL DEFAULT '',
  `metrics_json` longtext DEFAULT NULL,
  `analysis_text` longtext DEFAULT NULL,
  `period` varchar(50) NOT NULL DEFAULT '',
  `objective` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('saldo_minimo','erro_conta','ctr_baixo','cpc_alto','custo_conv_alto','roas_baixo') NOT NULL DEFAULT 'saldo_minimo',
  `platform` enum('meta','google') NOT NULL DEFAULT 'meta',
  `ad_account_id` int(10) UNSIGNED DEFAULT NULL,
  `saldo_minimo` decimal(10,2) DEFAULT 0.00,
  `whatsapp_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_type` enum('phone','group','client') NOT NULL DEFAULT 'phone',
  `recipient_phone` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `horarios` varchar(200) DEFAULT '12:00',
  `dias_semana` varchar(50) DEFAULT '1,2,3,4,5',
  `inativar_apos` tinyint(1) NOT NULL DEFAULT 0,
  `receber_email` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_envio` datetime DEFAULT NULL,
  `disparo_imediato` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `valor_threshold` decimal(10,2) DEFAULT NULL,
  `period_type` varchar(50) NOT NULL DEFAULT 'last_7_days',
  `ultimo_erros_hash` varchar(64) DEFAULT NULL,
  `modo_disparo` enum('agendado','inteligente') NOT NULL DEFAULT 'agendado',
  `proximo_reenvio_erro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `alert_logs`;
CREATE TABLE `alert_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `alert_id` int(10) UNSIGNED NOT NULL,
  `alert_name` varchar(200) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('enviado','erro') NOT NULL,
  `saldo` decimal(10,2) DEFAULT NULL,
  `destinatario` varchar(150) DEFAULT NULL,
  `tipo_envio` enum('manual','automatico') NOT NULL DEFAULT 'automatico',
  `erro_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `campaign_metrics`;
CREATE TABLE `campaign_metrics` (
  `id` int(10) UNSIGNED NOT NULL,
  `ad_account_id` int(10) UNSIGNED NOT NULL,
  `campaign_id` varchar(100) NOT NULL,
  `campaign_name` varchar(255) NOT NULL,
  `platform` enum('meta','google') NOT NULL,
  `date` date NOT NULL,
  `impressions` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `clicks_all` int(11) DEFAULT 0,
  `inline_clicks` int(11) DEFAULT 0,
  `spend` decimal(10,2) DEFAULT 0.00,
  `conversions` int(11) DEFAULT 0,
  `leads` int(11) DEFAULT 0,
  `all_leads` int(11) DEFAULT 0,
  `results` int(11) DEFAULT 0,
  `purchases` int(11) DEFAULT 0,
  `downloads` int(11) DEFAULT 0,
  `messages` int(11) DEFAULT 0,
  `app_installs` int(11) DEFAULT 0,
  `engagement` int(11) DEFAULT 0,
  `post_comments` int(11) DEFAULT 0,
  `post_reactions` int(11) DEFAULT 0,
  `post_saves` int(11) DEFAULT 0,
  `page_engagement` int(11) DEFAULT 0,
  `profile_visits` int(11) DEFAULT 0,
  `reach` int(11) DEFAULT 0,
  `frequency` decimal(6,2) DEFAULT 0.00,
  `cpm` decimal(10,2) DEFAULT 0.00,
  `cpc` decimal(10,2) DEFAULT 0.00,
  `ctr` decimal(5,2) DEFAULT 0.00,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `roas` decimal(10,4) DEFAULT 0.0000,
  `billed_amount` decimal(10,2) DEFAULT 0.00,
  `video_p25` int(11) DEFAULT 0,
  `video_p50` int(11) DEFAULT 0,
  `video_p75` int(11) DEFAULT 0,
  `video_p95` int(11) DEFAULT 0,
  `video_p100` int(11) DEFAULT 0,
  `thruplay` int(11) DEFAULT 0,
  `video_avg_time` decimal(8,2) DEFAULT 0.00,
  `creatives_json` longtext DEFAULT NULL,
  `synced_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `payment_type` enum('prepago','cartao') NOT NULL DEFAULT 'cartao' COMMENT 'prepago = controla saldo; cartao = sem controle de budget',
  `budget_mensal` decimal(10,2) DEFAULT NULL COMMENT 'Verba mensal combinada (referência, não obrigatório para pré-pago)',
  `saldo_alerta` decimal(10,2) DEFAULT 50.00 COMMENT 'Avisar quando saldo restante cair abaixo desse valor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `client_recharges`;
CREATE TABLE `client_recharges` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor adicionado ao saldo',
  `tipo` enum('pix','transferencia','dinheiro','outro') NOT NULL DEFAULT 'pix',
  `descricao` varchar(255) DEFAULT NULL COMMENT 'Observação livre (ex: comprovante)',
  `saldo_antes` decimal(10,2) DEFAULT NULL COMMENT 'Saldo antes da recarga',
  `saldo_apos` decimal(10,2) DEFAULT NULL COMMENT 'Saldo após a recarga',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de recargas de saldo dos clientes pré-pago';

-- --------------------------------------------------------
DROP TABLE IF EXISTS `dashboard_cache`;
CREATE TABLE `dashboard_cache` (
  `id` int(10) UNSIGNED NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `payload` mediumtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `dashboard_notes`;
CREATE TABLE `dashboard_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#F7DC6F',
  `text_color` varchar(20) DEFAULT '#2c2a00',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `integrations`;
CREATE TABLE `integrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `uuid` varchar(64) NOT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `whatsapp_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_phone` varchar(100) DEFAULT NULL,
  `recipient_type` enum('phone','group','client') NOT NULL DEFAULT 'phone',
  `message` text DEFAULT NULL,
  `secret_key` varchar(255) DEFAULT NULL COMMENT 'HMAC secret para validar assinatura do webhook (opcional)',
  `verify_token` varchar(128) DEFAULT NULL COMMENT 'Token de verificação para Facebook Lead Ads',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `integration_logs`;
CREATE TABLE `integration_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `integration_id` int(10) UNSIGNED NOT NULL,
  `payload` text DEFAULT NULL,
  `message_sent` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'sent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_templates`;
CREATE TABLE `message_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `code` varchar(6) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `pdf_templates`;
CREATE TABLE `pdf_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Meu Template',
  `config` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ad_account_id` int(10) UNSIGNED DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `period_type` varchar(30) DEFAULT 'custom',
  `platform` enum('meta','google','both') NOT NULL DEFAULT 'meta',
  `objetivo` varchar(100) DEFAULT NULL,
  `camp_ids` text DEFAULT NULL,
  `camp_labels` text DEFAULT NULL,
  `message_text` text DEFAULT NULL,
  `followup_message` text DEFAULT NULL,
  `frequency` varchar(20) DEFAULT 'once',
  `send_time` varchar(10) DEFAULT '08:00',
  `send_days` varchar(20) DEFAULT '1,2,3,4,5',
  `recipient_phone` varchar(100) DEFAULT NULL,
  `whatsapp_id` int(10) UNSIGNED DEFAULT NULL,
  `recv_type` enum('phone','group','client') NOT NULL DEFAULT 'phone',
  `group_id` varchar(100) DEFAULT NULL,
  `group_instance` varchar(100) DEFAULT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `next_send_at` datetime DEFAULT NULL,
  `sent_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `last_send_status` varchar(20) DEFAULT NULL,
  `last_send_error` text DEFAULT NULL,
  `status` enum('draft','active','scheduled','sent','paused') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rt_camp_selection` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rt_camp_selection`)),
  `share_token` varchar(64) DEFAULT NULL,
  `pdf_tpl_id` int(10) UNSIGNED DEFAULT NULL,
  `pdf_accent` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_logs`;
CREATE TABLE `report_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('enviado','erro') NOT NULL DEFAULT 'enviado',
  `destinatario` varchar(150) DEFAULT NULL,
  `tipo_envio` enum('manual','automatico') NOT NULL DEFAULT 'automatico',
  `report_title` varchar(200) DEFAULT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `periodo` varchar(100) DEFAULT NULL,
  `canal` varchar(20) DEFAULT NULL,
  `erro_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_name` varchar(100) DEFAULT 'GestorPro',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `login_bg_path` varchar(255) DEFAULT NULL,
  `login_logo_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `meta_app_id` varchar(100) DEFAULT NULL,
  `meta_app_secret` varchar(255) DEFAULT NULL,
  `evolution_api_url` varchar(255) DEFAULT NULL,
  `evolution_api_key` varchar(255) DEFAULT NULL,
  `mail_host` varchar(100) DEFAULT 'smtp.hostinger.com',
  `mail_port` int(11) DEFAULT 465,
  `mail_user` varchar(150) DEFAULT NULL,
  `mail_pass` varchar(255) DEFAULT NULL,
  `mail_from_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `sys_licenca`;
CREATE TABLE `sys_licenca` (
  `id` int(10) UNSIGNED NOT NULL,
  `chave` varchar(64) NOT NULL,
  `dominio` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 0,
  `data_vencimento` date NOT NULL,
  `ativado_em` datetime DEFAULT NULL,
  `ultima_checagem` datetime DEFAULT NULL,
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
DROP VIEW IF EXISTS `v_client_saldo`;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `plan` enum('trial','essencial','advanced','pro','premium') NOT NULL DEFAULT 'trial',
  `plan_expires_at` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Usuário administrador padrão
-- E-mail: admin@meusistema.com
-- Senha:  trocar123
--
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `plan`, `plan_expires_at`, `avatar`, `phone`, `status`, `remember_token`, `remember_expires_at`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@meusistema.com', '$2b$10$r3UCrPBQRCc/n6gMZZ/h/uhjsVrULiLCM8ZiIdW8kEJMsunwbWlGW', 'admin', 'pro', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NOW(), NOW());

-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_ai_settings`;
CREATE TABLE `user_ai_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_groups_cache`;
CREATE TABLE `whatsapp_groups_cache` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `whatsapp_id` int(10) UNSIGNED NOT NULL,
  `group_id` varchar(120) NOT NULL,
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `synced_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_instances`;
CREATE TABLE `whatsapp_instances` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `instance_name` varchar(100) NOT NULL,
  `instance_key` varchar(255) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `status` enum('connected','disconnected','qr_pending','error') NOT NULL DEFAULT 'disconnected',
  `qr_code` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ip_action_time` (`ip`,`action`,`created_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_action_ip_time` (`action`,`ip`,`created_at`),
  ADD KEY `idx_activity_log_action_ip_date` (`action`,`ip`,`created_at`),
  ADD KEY `idx_activity_brute` (`action`,`ip`,`created_at`),
  ADD KEY `idx_activity_user` (`user_id`,`created_at`);

ALTER TABLE `ad_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_prepago_sync` (`prepago_synced_at`);

ALTER TABLE `ai_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_user_account` (`user_id`,`ad_account_id`),
  ADD KEY `idx_ad_account_id` (`ad_account_id`),
  ADD KEY `idx_user_created_desc` (`user_id`,`created_at`);

ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_ad_account_id` (`ad_account_id`),
  ADD KEY `idx_ativo_disparo` (`ativo`,`disparo_imediato`),
  ADD KEY `idx_reenvio_erro` (`modo_disparo`,`proximo_reenvio_erro`);

ALTER TABLE `alert_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert` (`alert_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_alert_user` (`alert_id`,`user_id`);

ALTER TABLE `campaign_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_campaign_date` (`ad_account_id`,`campaign_id`,`date`),
  ADD KEY `idx_account` (`ad_account_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_acc_date_spend` (`ad_account_id`,`date`,`spend`),
  ADD KEY `idx_campaign_id` (`campaign_id`),
  ADD KEY `idx_account_date` (`ad_account_id`,`date`);

ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_name` (`name`(50));

ALTER TABLE `client_recharges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `dashboard_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cache_key` (`cache_key`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`),
  ADD KEY `idx_expires` (`expires_at`);

ALTER TABLE `dashboard_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `integrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_uuid` (`uuid`);

ALTER TABLE `integration_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_integration` (`integration_id`);

ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read_at` (`read_at`),
  ADD KEY `idx_user_read` (`user_id`,`read_at`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires_used` (`expires_at`,`used`);

ALTER TABLE `pdf_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_ad_account_id` (`ad_account_id`),
  ADD KEY `idx_next_send_status` (`next_send_at`,`status`);

ALTER TABLE `report_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report` (`report_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sys_licenca`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_remember_token` (`remember_token`),
  ADD KEY `idx_users_email_status` (`email`,`status`),
  ADD KEY `idx_users_remember_token` (`remember_token`);

ALTER TABLE `user_ai_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_setting` (`user_id`,`setting_key`);

ALTER TABLE `whatsapp_groups_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_group` (`whatsapp_id`,`group_id`),
  ADD KEY `idx_user_wp` (`user_id`,`whatsapp_id`);

ALTER TABLE `whatsapp_instances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- AUTO_INCREMENT
--
ALTER TABLE `activity_log` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `ad_accounts` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `ai_logs` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `alerts` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `alert_logs` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `campaign_metrics` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `clients` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `client_recharges` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `dashboard_cache` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `dashboard_notes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `integrations` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `integration_logs` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `message_templates` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `notifications` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `password_resets` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `pdf_templates` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `reports` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `report_logs` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `system_settings` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `sys_licenca` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `users` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `user_ai_settings` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `whatsapp_groups_cache` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `whatsapp_instances` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- --------------------------------------------------------
-- View sem DEFINER fixo (portátil para qualquer hospedagem)
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_client_saldo` AS
SELECT `c`.`id` AS `client_id`, `c`.`user_id` AS `user_id`, `c`.`name` AS `client_name`,
       `c`.`payment_type` AS `payment_type`, `c`.`saldo_alerta` AS `saldo_alerta`,
       coalesce(sum(`r`.`valor`),0) AS `total_recarregado`,
       max(`r`.`created_at`) AS `ultima_recarga_at`,
       max(`r`.`valor`) AS `ultima_recarga_valor`
FROM (`clients` `c` LEFT JOIN `client_recharges` `r` ON(`r`.`client_id` = `c`.`id` AND `r`.`user_id` = `c`.`user_id`))
WHERE `c`.`payment_type` = 'prepago'
GROUP BY `c`.`id`, `c`.`user_id`, `c`.`name`, `c`.`payment_type`, `c`.`saldo_alerta`;

--
-- Restrições (chaves estrangeiras)
--
ALTER TABLE `ad_accounts`
  ADD CONSTRAINT `fk_ad_accounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ad_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `ai_logs`
  ADD CONSTRAINT `fk_ai_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `alerts`
  ADD CONSTRAINT `fk_alerts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_alerts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `alert_logs`
  ADD CONSTRAINT `fk_alert_logs_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`) ON DELETE CASCADE;

ALTER TABLE `campaign_metrics`
  ADD CONSTRAINT `fk_campaign_metrics_account` FOREIGN KEY (`ad_account_id`) REFERENCES `ad_accounts` (`id`) ON DELETE CASCADE;

ALTER TABLE `client_recharges`
  ADD CONSTRAINT `fk_recharge_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recharge_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `integrations`
  ADD CONSTRAINT `fk_integrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `pdf_templates`
  ADD CONSTRAINT `fk_pdf_templates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `report_logs`
  ADD CONSTRAINT `fk_report_logs_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

ALTER TABLE `whatsapp_groups_cache`
  ADD CONSTRAINT `fk_wgc_instance` FOREIGN KEY (`whatsapp_id`) REFERENCES `whatsapp_instances` (`id`) ON DELETE CASCADE;

ALTER TABLE `whatsapp_instances`
  ADD CONSTRAINT `fk_whatsapp_instances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
