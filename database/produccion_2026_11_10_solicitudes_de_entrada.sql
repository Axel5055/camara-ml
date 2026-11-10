-- Producción (base mlgrupoc_camara): solicitudes de entrada que autoriza un administrador.
-- Equivale a la migración 2026_11_10_100000_create_entry_requests_table.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) Tabla de solicitudes (las entradas del almacenista esperan aquí hasta que se autorizan)
CREATE TABLE IF NOT EXISTS `entry_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chamber_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `total_boxes` int unsigned NOT NULL,
  `total_weight_kg` decimal(14,3) NOT NULL,
  `total_cost_mxn` decimal(16,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `groups` json NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_notes` text COLLATE utf8mb4_unicode_ci,
  `entry_id` bigint unsigned DEFAULT NULL,
  `client_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_requests_client_token_unique` (`client_token`),
  KEY `entry_requests_user_id_foreign` (`user_id`),
  KEY `entry_requests_decided_by_foreign` (`decided_by`),
  KEY `entry_requests_entry_id_foreign` (`entry_id`),
  KEY `entry_requests_status_created_at_index` (`status`,`created_at`),
  KEY `entry_requests_chamber_id_status_index` (`chamber_id`,`status`),
  CONSTRAINT `entry_requests_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entry_requests_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entry_requests_entry_id_foreign` FOREIGN KEY (`entry_id`) REFERENCES `entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entry_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_10_100000_create_entry_requests_table',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_100000_create_entry_requests_table');
