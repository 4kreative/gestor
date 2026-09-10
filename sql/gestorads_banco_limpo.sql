-- ============================================================
-- GestorADS — Banco Limpo (estrutura + admin padrão)
-- Usuário: admin@gestorads.com / senha: Admin@123
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

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

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `payment_type` enum('prepago','cartao') NOT NULL DEFAULT 'cartao' COMMENT 'prepago = controla saldo;

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

CREATE TABLE `dashboard_cache` (
  `id` int(10) UNSIGNED NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `payload` mediumtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `dashboard_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#F7DC6F',
  `text_color` varchar(20) DEFAULT '#2c2a00',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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

CREATE TABLE `integration_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `integration_id` int(10) UNSIGNED NOT NULL,
  `payload` text DEFAULT NULL,
  `message_sent` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'sent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `code` varchar(6) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pdf_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Meu Template',
  `config` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  `message_text` text DEFAULT NULL,
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

CREATE TABLE `report_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('enviado','erro') NOT NULL DEFAULT 'enviado',
  `destinatario` varchar(150) DEFAULT NULL,
  `tipo_envio` enum('manual','automatico') NOT NULL DEFAULT 'automatico',
  `erro_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_name` varchar(100) DEFAULT 'GestorPro',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `login_bg_path` varchar(255) DEFAULT NULL,
  `login_logo_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `user_ai_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `v_client_saldo` (
`client_id` int(10) unsigned
,`user_id` int(10) unsigned
,`client_name` varchar(150)
,`payment_type` enum('prepago','cartao')
,`saldo_alerta` decimal(10,2)
,`total_recarregado` decimal(32,2)
,`ultima_recarga_at` datetime
,`ultima_recarga_valor` decimal(10,2)
);

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

ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ip_action_time` (`ip`,`action`,`created_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_action_ip_time` (`action`,`ip`,`created_at`);

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

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_remember_token` (`remember_token`);

ALTER TABLE `user_ai_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_setting` (`user_id`,`setting_key`);

ALTER TABLE `whatsapp_instances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

ALTER TABLE `activity_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

ALTER TABLE `ad_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

ALTER TABLE `ai_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

ALTER TABLE `alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

ALTER TABLE `alert_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

ALTER TABLE `campaign_metrics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21643;

ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

ALTER TABLE `client_recharges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `dashboard_cache`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=308;

ALTER TABLE `dashboard_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `integrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `integration_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `message_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `pdf_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

ALTER TABLE `report_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `user_ai_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5411;

ALTER TABLE `whatsapp_instances`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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

ALTER TABLE `whatsapp_instances`
  ADD CONSTRAINT `fk_whatsapp_instances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ── Usuário admin padrão ─────────────────────────────────
-- Email: admin@admin.com
-- Senha: admin123
-- IMPORTANTE: Execute no terminal para gerar novo hash:
-- php -r "echo password_hash('SuaSenha', PASSWORD_BCRYPT);"
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `plan`, `plan_expires_at`, `status`, `role`, `avatar`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@admin.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIXB5xqj5EQ3Wr.', NULL, 'premium', NULL, 'active', 'admin', NULL, NULL, NOW(), NOW());

-- ── Templates de mensagem padrão ───────────────────────
INSERT INTO `message_templates` (`id`, `user_id`, `name`, `content`, `variables`, `is_default`, `created_at`, `updated_at`) VALUES
(61, 1, '📸 Visitas ao Perfil — Instagram', '📸 *Relatório — Visitas ao Perfil do Instagram*\r\n🗓 Período: {periodo}\r\n📊 Conta: {conta_anuncio}\r\n\r\n👁 *Visitas ao Perfil:* {profile_visit}\r\n💰 *Custo por Visita:* R$ {profile_visit_cost}\r\n\r\n📣 *Alcance:* {alcance}\r\n👀 *Impressões:* {impressoes}\r\n🔁 *Frequência:* {frequencia}x\r\n💸 *Investimento:* R$ {investimento}\r\n\r\n👉 Acesse o relatório completo:\r\n{link}\r\n\r\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-13 18:19:29'),
(62, 1, '💬 Campanha de Mensagens', '💬 *Relatório — Campanha de Mensagens*\r\n🗓 Período: {periodo}\r\n📊 Conta: {conta_anuncio}\r\n\r\n📩 *Conversas Iniciadas:* {msg}\r\n💰 *Custo por Mensagem:* R$ {cmsg}\r\n\r\n📣 *Alcance:* {alcance}\r\n👀 *Impressões:* {impressoes}\r\n🔗 *Cliques:* {cliques}\r\n📊 *CTR:* {ctr}%\r\n💸 *Investimento:* R$ {investimento}\r\n📈 *CPM:* R$ {cpm}\r\n\r\n👉 Acesse o relatório completo:\r\n{link}\r\n\r\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-13 11:09:10'),
(63, 1, '📡 Alcance e Reconhecimento de Marca', '📡 *Relatório de Alcance*\n🗓 Período: {periodo}\n📊 Conta: {conta_anuncio}\n\n👥 *Alcance (pessoas únicas):* {alcance}\n👀 *Impressões:* {impressoes}\n🔁 *Frequência:* {frequencia}x por pessoa\n💸 *Investimento:* R$ {investimento}\n📈 *CPM:* R$ {cpm}\n\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(64, 1, '🌐 Tráfego para o Site', '🌐 *Relatório — Tráfego para o Site*\n🗓 Período: {periodo}\n📊 Conta: {conta_anuncio}\n\n🖱 *Cliques no Link:* {cliques}\n💰 *CPC:* R$ {cpc}\n📊 *CTR:* {ctr}%\n🔎 *Visualizações de Página:* {pageview}\n💵 *Custo por Pageview:* R$ {cpvp}\n📣 *Alcance:* {alcance}\n👀 *Impressões:* {impressoes}\n📈 *CPM:* R$ {cpm}\n💸 *Investimento:* R$ {investimento}\n\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(65, 1, '🎯 Geração de Leads', '🎯 *Relatório — Geração de Leads*\n🗓 Período: {periodo}\n📊 Conta: {conta_anuncio}\n\n✅ *Leads Gerados:* {leads}\n💰 *Custo por Lead (CPL):* R$ {cpl}\n📣 *Alcance:* {alcance}\n👀 *Impressões:* {impressoes}\n🔗 *Cliques:* {cliques}\n📊 *CTR:* {ctr}%\n💸 *Investimento:* R$ {investimento}\n📈 *CPM:* R$ {cpm}\n🔁 *Frequência:* {frequencia}x\n\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(66, 1, '📊 Relatório Completo de Campanha', '📊 *Relatório Completo — {conta_anuncio}*\n🗓 Período: {periodo}\n\n━━━━━━━━━━━━━━━━━\n👥 *AUDIÊNCIA*\n📣 Alcance: {alcance}\n👀 Impressões: {impressoes}\n🔁 Frequência: {frequencia}x\n\n━━━━━━━━━━━━━━━━━\n🖱 *ENGAJAMENTO*\n🔗 Cliques: {cliques}\n📊 CTR: {ctr}%\n📸 Visitas ao Perfil: {profile_visit}\n💬 Mensagens: {msg}\n\n━━━━━━━━━━━━━━━━━\n✅ *CONVERSÕES*\n🎯 Resultados: {results}\n💰 Custo por Resultado: R$ {cpl}\n\n━━━━━━━━━━━━━━━━━\n💸 *INVESTIMENTO*\nTotal: R$ {investimento}\nCPM: R$ {cpm}\nCPC: R$ {cpc}\n\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(67, 1, '🎬 Campanhas de Vídeo', '🎬 *Relatório — Campanhas de Vídeo*\n🗓 Período: {periodo}\n📊 Conta: {conta_anuncio}\n\n▶️ *ThruPlays (assistidos até o fim):* {thruplay}\n💰 *Custo por ThruPlay:* R$ {thruplay_cost}\n📣 *Alcance:* {alcance}\n👀 *Impressões:* {impressoes}\n🔁 *Frequência:* {frequencia}x\n💸 *Investimento:* R$ {investimento}\n📈 *CPM:* R$ {cpm}\n\n_Relatório gerado automaticamente pelo GestorPro_', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(68, 1, '👤 Relatório para Cliente', 'Olá {nome_cliente}! 👋\n\nSegue o relatório de performance das suas campanhas no período de {periodo}:\n\n📊 *Resumo de Resultados:*\n• 💸 Investimento: R$ {investimento}\n• 👥 Alcance: {alcance} pessoas\n• 👀 Impressões: {impressoes}\n• 🔗 Cliques: {cliques}\n• 📊 CTR: {ctr}%\n• 📈 CPM: R$ {cpm}\n• 🖱 CPC: R$ {cpc}\n• ✅ Resultados: {results}\n• 💰 Custo por Resultado: R$ {cpl}\n• 📸 Visitas ao Perfil: {profile_visit}\n\nQualquer dúvida, estou à disposição! 🚀', NULL, 0, '2026-04-09 02:16:20', '2026-04-09 02:16:20'),
(69, 1, '⚡ Alerta — Saldo Baixo Meta', '🚨 *Aviso — Saldo Meta ADS*\n\nOlá, *{primeiro_nome}*! 👋\n🏢 Empresa: *{empresa}*\n💰 Saldo atual: *{saldo}*\n⚠️ Saldo mínimo: *{saldo_minimo}*\n\n🔴 É necessário realizar uma recarga para evitar a pausa dos anúncios.\n\nQual valor para gerar o Pix?\n\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-11 20:09:16'),
(70, 1, '📉 Alerta — CTR Baixo', '📉 *Aviso — CTR Baixo*\r\n\r\nOlá, *{primeiro_nome}*! 👋\r\n🏢 Empresa: *{empresa}*\r\n📊 Conta: *{conta_anuncio}*\r\n\r\n⚠️ O CTR está abaixo do limite configurado.\r\n📌 CTR atual: *{ctr}%*\r\n\r\nRecomendamos revisar os criativos e o público-alvo para melhorar o desempenho.\r\n\r\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-12 02:44:54'),
(71, 1, '💸 Alerta — CPC Alto', '💸 *Aviso — CPC Alto*\n\nOlá, *{primeiro_nome}*! 👋\n🏢 Empresa: *{empresa}*\n📊 Conta: *{conta_anuncio}*\n\n⚠️ O custo por clique está acima do limite.\n📌 CPC atual: *R$ {metrica_atual}*\n\nSugerimos revisar lances, segmentação ou criativos.\n\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-11 20:09:16'),
(72, 1, '💰 Alerta — Custo por Conversa Alto', '💰 *Aviso — Custo por Conversa Alto*\r\n\r\nOlá, *{primeiro_nome}*! 👋\r\n🏢 Empresa: *{empresa}*\r\n📊 Conta: *{conta_anuncio}*\r\n\r\n⚠️ O custo por conversa está acima do limite.\r\n📌 Custo atual: *R$ {cmsg}*\r\n\r\nRecomendamos ajustar o público ou os criativos.\r\n\r\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-12 02:44:21'),
(73, 1, '📈 Alerta — ROAS Baixo', '📈 *Aviso — ROAS Baixo*\n\nOlá, *{primeiro_nome}*! 👋\n🏢 Empresa: *{empresa}*\n📊 Conta: *{conta_anuncio}*\n\n⚠️ O ROAS está abaixo do esperado.\n📌 ROAS atual: *{metrica_atual}x*\n\nRevise sua estratégia de conversão e orçamento.\n\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-11 20:09:16'),
(74, 1, '🚨 Alerta — Erro na Conta', '🚨 *Aviso — Erro na Conta Meta*\r\n\r\nOlá, *{primeiro_nome}*! 👋\r\n🏢 Empresa: *{empresa}*\r\n\r\n🚨 *Problema na conta {conta_anuncio}*\r\n\r\n{erros_conta}\r\n\r\nAcesse o Gerenciador para corrigir.\r\n— Equipe J6\' Digital 🚀', NULL, 0, '2026-04-11 20:09:16', '2026-04-20 19:56:50'),
(75, 1, '✍️ Assinatura de Contrato', '✍️ *{{event_label}}*\r\n\r\n👤 *Signatário:* {{signer_name}}\r\n📧 *Email:* {{signer_email}}\r\n🪪 *CPF:* {{signer_cpf}}\r\n📅 *Assinado em:* {{doc_signed_at}}', NULL, 0, '2026-04-13 21:03:07', '2026-04-13 21:03:07');

-- ── Configurações do sistema ────────────────────────────
INSERT INTO `system_settings` (`id`, `site_name`, `logo_path`, `favicon_path`, `login_bg_path`, `login_logo_path`, `created_at`, `updated_at`) VALUES
(1, 'J6\' Digital - ADS 🚀', 'logo_path_1776189685.png', 'favicon_path_1776189619.png', 'login_bg_path_1776190641.webp', 'login_logo_path_1776189604.png', '2026-04-08 23:34:31', '2026-04-25 17:23:01');

-- AUTO_INCREMENT resets
ALTER TABLE `users` AUTO_INCREMENT = 2;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
