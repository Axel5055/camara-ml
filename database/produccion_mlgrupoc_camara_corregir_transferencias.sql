-- Base de datos de producción del sistema de cámaras (mlgrupoc_camara)
-- Corrección de transferencias por el super administrador (migración 2026_11_05_100000).
-- Se puede ejecutar más de una vez sin problema. Haz respaldo antes (phpMyAdmin > SQL).

-- 1) Columna removed_at en transfer_items (solo si todavía no existe)
SET @existe := (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'transfer_items' AND column_name = 'removed_at');
SET @sql := IF(@existe = 0,
               'ALTER TABLE `transfer_items` ADD COLUMN `removed_at` DATETIME NULL AFTER `status`',
               'SELECT ''removed_at ya existe'' AS aviso');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- 2) Triggers: solo cambian las columnas que una corrección necesita
DELIMITER $$

DROP TRIGGER IF EXISTS transfers_immutable_columns$$
CREATE TRIGGER transfers_immutable_columns BEFORE UPDATE ON transfers FOR EACH ROW
BEGIN
  IF NOT (OLD.`created_at` <=> NEW.`created_at`)
     OR NOT (OLD.`exit_id` <=> NEW.`exit_id`)
     OR NOT (OLD.`id` <=> NEW.`id`)
     OR NOT (OLD.`origin_chamber_id` <=> NEW.`origin_chamber_id`)
     OR NOT (OLD.`reference` <=> NEW.`reference`)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of transfers cannot be changed';
  END IF;
END$$

DROP TRIGGER IF EXISTS transfer_items_immutable_columns$$
CREATE TRIGGER transfer_items_immutable_columns BEFORE UPDATE ON transfer_items FOR EACH ROW
BEGIN
  IF NOT (OLD.`box_id` <=> NEW.`box_id`)
     OR NOT (OLD.`created_at` <=> NEW.`created_at`)
     OR NOT (OLD.`expected_weight_kg` <=> NEW.`expected_weight_kg`)
     OR NOT (OLD.`id` <=> NEW.`id`)
     OR NOT (OLD.`transfer_id` <=> NEW.`transfer_id`)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of transfer_items cannot be changed';
  END IF;
END$$

DROP TRIGGER IF EXISTS exits_immutable_columns$$
CREATE TRIGGER exits_immutable_columns BEFORE UPDATE ON exits FOR EACH ROW
BEGIN
  IF NOT (OLD.`client_token` <=> NEW.`client_token`)
     OR NOT (OLD.`created_at` <=> NEW.`created_at`)
     OR NOT (OLD.`id` <=> NEW.`id`)
     OR NOT (OLD.`origin_chamber_id` <=> NEW.`origin_chamber_id`)
     OR NOT (OLD.`reference` <=> NEW.`reference`)
     OR NOT (OLD.`user_id` <=> NEW.`user_id`)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of exits cannot be changed';
  END IF;
END$$

DELIMITER ;

-- 3) Registrar la migración (solo si todavía no está)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_05_100000_allow_super_admin_corrections_of_transfers',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) b)
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_05_100000_allow_super_admin_corrections_of_transfers');
