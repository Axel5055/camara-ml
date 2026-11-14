-- Producción (base mlgrupoc_camara): permitir cajas agregadas por el super administrador sin entrada.
-- Equivale a la migración 2026_11_14_100000_make_boxes_entry_item_nullable.
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) boxes.entry_item_id pasa a aceptar NULL (la llave foránea se conserva)
ALTER TABLE `boxes` MODIFY `entry_item_id` BIGINT UNSIGNED NULL;

-- 2) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_100000_make_boxes_entry_item_nullable',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_14_100000_make_boxes_entry_item_nullable');
