-- Producción (base mlgrupoc_camara): rol "Solicitante" y solicitudes de salida.
-- Equivale a la migración 2026_11_21_100000_create_exit_requests_tables.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) Nuevo rol de usuario: requester (Solicitante)
ALTER TABLE `users`
  MODIFY `role` ENUM('admin', 'super_admin', 'manager', 'warehouse', 'requester') NOT NULL DEFAULT 'warehouse';

-- 2) Solicitudes de salida (lo que pide una sucursal sin cámara a una cámara)
CREATE TABLE IF NOT EXISTS `exit_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `chamber_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `total_boxes_requested` int unsigned NOT NULL,
  `total_boxes_approved` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_notes` text COLLATE utf8mb4_unicode_ci,
  `exit_id` bigint unsigned DEFAULT NULL,
  `client_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exit_requests_client_token_unique` (`client_token`),
  KEY `exit_requests_chamber_id_foreign` (`chamber_id`),
  KEY `exit_requests_user_id_foreign` (`user_id`),
  KEY `exit_requests_decided_by_foreign` (`decided_by`),
  KEY `exit_requests_exit_id_foreign` (`exit_id`),
  KEY `exit_requests_status_created_at_index` (`status`,`created_at`),
  KEY `exit_requests_chamber_id_status_index` (`chamber_id`,`status`),
  KEY `exit_requests_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `exit_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_requests_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_requests_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_requests_exit_id_foreign` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Productos de cada solicitud (cuántas cajas se pidieron y cuántas se surtieron)
CREATE TABLE IF NOT EXISTS `exit_request_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exit_request_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `boxes_requested` int unsigned NOT NULL,
  `boxes_approved` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exit_request_items_exit_request_id_product_id_unique` (`exit_request_id`,`product_id`),
  KEY `exit_request_items_product_id_foreign` (`product_id`),
  CONSTRAINT `exit_request_items_exit_request_id_foreign` FOREIGN KEY (`exit_request_id`) REFERENCES `exit_requests` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_request_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_21_100000_create_exit_requests_tables',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_21_100000_create_exit_requests_tables');
