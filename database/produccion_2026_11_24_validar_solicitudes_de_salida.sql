-- Producción (base mlgrupoc_camara): validar solicitudes de salida autorizadas.
-- Equivale a la migración 2026_11_24_162951_add_validation_to_exit_requests_table.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) exit_requests.validated_at (cuándo se validó la solicitud autorizada)
SET @existe := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'exit_requests' AND `COLUMN_NAME` = 'validated_at');
SET @sql := IF(@existe = 0, 'ALTER TABLE `exit_requests` ADD COLUMN `validated_at` DATETIME NULL AFTER `exit_id`', 'SELECT 1');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- 2) exit_requests.validated_by (quién la validó)
SET @existe := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'exit_requests' AND `COLUMN_NAME` = 'validated_by');
SET @sql := IF(@existe = 0, 'ALTER TABLE `exit_requests` ADD COLUMN `validated_by` BIGINT UNSIGNED NULL AFTER `validated_at`', 'SELECT 1');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- 3) Llave foránea hacia users (solo si todavía no existe)
SET @existe := (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'exit_requests' AND `CONSTRAINT_NAME` = 'exit_requests_validated_by_foreign');
SET @sql := IF(@existe = 0, 'ALTER TABLE `exit_requests` ADD CONSTRAINT `exit_requests_validated_by_foreign` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT', 'SELECT 1');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- 4) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_24_162951_add_validation_to_exit_requests_table',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_24_162951_add_validation_to_exit_requests_table');
