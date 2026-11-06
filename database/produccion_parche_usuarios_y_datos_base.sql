-- Parche para la base de producción (mlgrupoc_demo) que ya tiene las tablas del sistema de cámaras.
-- Lo que falta: columnas del sistema de cámaras en `users`, contadores base y registro de migraciones.
-- Haz respaldo antes. Ejecútalo UNA sola vez (phpMyAdmin > SQL).
--
-- Importante: `users` es compartida con el otro sistema. Solo se AGREGAN columnas; `activo` y `sucursal_id`
-- del otro sistema no se tocan.

-- 1) Columnas del sistema de cámaras en la tabla users
ALTER TABLE `users`
  ADD COLUMN `role` ENUM('admin','super_admin','warehouse') NOT NULL DEFAULT 'warehouse' AFTER `password`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
  ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `is_active`,
  ADD KEY `users_role_index` (`role`),
  ADD KEY `users_branch_id_foreign` (`branch_id`),
  ADD CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT;

-- Quien estaba inactivo en el otro sistema queda inactivo aquí
UPDATE `users` SET `is_active` = `activo`;

-- 2) Contadores de referencias y códigos de barras (empiezan en cero)
INSERT IGNORE INTO `sequences` (`name`, `last_value`, `created_at`, `updated_at`) VALUES
  ('adjustment', 0, NOW(), NOW()),
  ('barcode', 0, NOW(), NOW()),
  ('entry', 0, NOW(), NOW()),
  ('exit', 0, NOW(), NOW()),
  ('transfer', 0, NOW(), NOW());

-- 3) Registrar las migraciones del sistema de cámaras para que Laravel no las repita
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`migration`, 3 FROM (
  SELECT '2026_10_28_000001_create_branches_table' AS `migration`
  UNION ALL SELECT '2026_10_28_000002_add_inventory_columns_to_users_table'
  UNION ALL SELECT '2026_10_28_000003_create_chambers_table'
  UNION ALL SELECT '2026_10_28_000004_create_chamber_user_table'
  UNION ALL SELECT '2026_10_28_000005_create_categories_table'
  UNION ALL SELECT '2026_10_28_000006_create_products_table'
  UNION ALL SELECT '2026_10_28_000007_create_stock_thresholds_table'
  UNION ALL SELECT '2026_10_28_000008_create_sequences_table'
  UNION ALL SELECT '2026_10_28_000009_create_entries_table'
  UNION ALL SELECT '2026_10_28_000010_create_entry_items_table'
  UNION ALL SELECT '2026_10_28_000011_create_boxes_table'
  UNION ALL SELECT '2026_10_28_000012_create_exits_table'
  UNION ALL SELECT '2026_10_28_000013_create_transfers_table'
  UNION ALL SELECT '2026_10_28_000014_create_exit_items_table'
  UNION ALL SELECT '2026_10_28_000015_create_transfer_items_table'
  UNION ALL SELECT '2026_10_28_000016_create_incidents_table'
  UNION ALL SELECT '2026_10_28_000017_create_inventory_adjustments_table'
  UNION ALL SELECT '2026_10_28_000018_create_box_movements_table'
  UNION ALL SELECT '2026_10_28_000019_create_audit_logs_table'
  UNION ALL SELECT '2026_10_28_000020_add_immutability_triggers'
  UNION ALL SELECT '2026_10_28_000021_rename_usd_columns_to_mxn'
  UNION ALL SELECT '2026_10_28_000022_widen_product_code_and_barcode_lengths'
  UNION ALL SELECT '2026_10_30_000023_add_super_admin_role_to_users'
  UNION ALL SELECT '2026_11_03_140848_add_receive_notes_to_transfers_table'
  UNION ALL SELECT '2026_11_03_144607_allow_received_boxes_to_leave_again'
  UNION ALL SELECT '2026_11_03_152626_allow_super_admin_corrections_of_entries_and_exits'
  UNION ALL SELECT '2026_11_05_100000_allow_super_admin_corrections_of_transfers'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.`migration`);

-- 4) Primer super administrador del sistema de cámaras.
--    Cambia el correo por el de la persona que será super admin y quita los dos guiones de adelante:
-- UPDATE `users` SET `role` = 'super_admin' WHERE `email` = 'axel@gmail.com';
