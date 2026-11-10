-- Producción (base mlgrupoc_camara): aviso al almacenista para imprimir el ticket de una entrada autorizada.
-- Equivale a la migración 2026_11_10_130000_add_ticket_prompted_at_to_entry_requests_table.
-- Requiere que ya exista la tabla entry_requests (archivo produccion_2026_11_10_solicitudes_de_entrada.sql).
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) Columna que recuerda si ya se le ofreció imprimir el ticket (solo si todavía no existe)
SET @existe := (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'entry_requests' AND column_name = 'ticket_prompted_at');
SET @sql := IF(@existe = 0,
               'ALTER TABLE `entry_requests` ADD COLUMN `ticket_prompted_at` DATETIME NULL AFTER `entry_id`',
               'SELECT ''ticket_prompted_at ya existe'' AS aviso');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- 2) Las solicitudes que ya estaban autorizadas no vuelven a mostrar el aviso
UPDATE `entry_requests` SET `ticket_prompted_at` = NOW()
WHERE `status` = 'approved' AND `ticket_prompted_at` IS NULL;

-- 3) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_10_130000_add_ticket_prompted_at_to_entry_requests_table',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_130000_add_ticket_prompted_at_to_entry_requests_table');
