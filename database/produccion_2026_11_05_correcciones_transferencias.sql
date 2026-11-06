-- Producción: corrección de transferencias por el super administrador
-- Equivale a la migración 2026_11_05_100000_allow_super_admin_corrections_of_transfers
-- Haz respaldo antes de ejecutarlo. Ejecútalo una sola vez.
--
-- Lo demás de esta entrega (botón "Revertir" en el detalle de salida y "Cancelar entrada"
-- en el detalle de entrada) no requiere cambios en la base de datos.

-- 1) Columna para marcar las cajas quitadas de una transferencia (nunca se borran)
ALTER TABLE transfer_items ADD COLUMN removed_at DATETIME NULL AFTER status;

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

-- 3) Registrar la migración para que `php artisan migrate` no la vuelva a correr
INSERT INTO migrations (migration, batch)
VALUES ('2026_11_05_100000_allow_super_admin_corrections_of_transfers',
        (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) m));
