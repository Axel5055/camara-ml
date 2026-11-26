-- Producción (base mlgrupoc_camara): cámaras a las que un almacenista puede pedir producto.
-- Equivale a la migración 2026_11_26_104932_create_requestable_chamber_user_table.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) Asignación de cámaras para pedir (aparte de las que el almacenista opera)
CREATE TABLE IF NOT EXISTS `requestable_chamber_user` (
  `user_id` bigint unsigned NOT NULL,
  `chamber_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`, `chamber_id`),
  KEY `requestable_chamber_user_chamber_id_foreign` (`chamber_id`),
  CONSTRAINT `requestable_chamber_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `requestable_chamber_user_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_26_104932_create_requestable_chamber_user_table',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_26_104932_create_requestable_chamber_user_table');
