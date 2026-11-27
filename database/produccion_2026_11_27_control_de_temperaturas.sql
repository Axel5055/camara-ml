-- Producción (base mlgrupoc_camara): control de temperatura de las cámaras.
-- Equivale a la migración 2026_11_27_103923_create_temperature_readings_tables.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).
-- Ejecútalo ANTES de subir los archivos nuevos.

-- 1) Lecturas: una por cámara, día y turno
CREATE TABLE IF NOT EXISTS `temperature_readings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chamber_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reading_date` date NOT NULL,
  `shift` varchar(10) NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `unmeasured_reason` varchar(500) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `temperature_readings_chamber_id_reading_date_shift_unique` (`chamber_id`, `reading_date`, `shift`),
  KEY `temperature_readings_user_id_reading_date_index` (`user_id`, `reading_date`),
  CONSTRAINT `temperature_readings_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `temperature_readings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Correcciones del super administrador (el valor capturado no se toca)
CREATE TABLE IF NOT EXISTS `temperature_corrections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `temperature_reading_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `temperature` decimal(4,1) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `temperature_corrections_temperature_reading_id_foreign` (`temperature_reading_id`),
  KEY `temperature_corrections_user_id_foreign` (`user_id`),
  CONSTRAINT `temperature_corrections_temperature_reading_id_foreign` FOREIGN KEY (`temperature_reading_id`) REFERENCES `temperature_readings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `temperature_corrections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Triggers: las lecturas y sus correcciones no se pueden modificar ni borrar
DROP TRIGGER IF EXISTS `temperature_readings_no_delete`;
CREATE TRIGGER `temperature_readings_no_delete` BEFORE DELETE ON `temperature_readings` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in temperature_readings cannot be deleted';

DROP TRIGGER IF EXISTS `temperature_readings_no_update`;
CREATE TRIGGER `temperature_readings_no_update` BEFORE UPDATE ON `temperature_readings` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in temperature_readings cannot be updated';

DROP TRIGGER IF EXISTS `temperature_corrections_no_delete`;
CREATE TRIGGER `temperature_corrections_no_delete` BEFORE DELETE ON `temperature_corrections` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in temperature_corrections cannot be deleted';

DROP TRIGGER IF EXISTS `temperature_corrections_no_update`;
CREATE TRIGGER `temperature_corrections_no_update` BEFORE UPDATE ON `temperature_corrections` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in temperature_corrections cannot be updated';

-- 4) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_27_103923_create_temperature_readings_tables',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_27_103923_create_temperature_readings_tables');
