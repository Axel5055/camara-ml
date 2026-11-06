-- Base de datos completa de ML Grupo Comercial (estructura + triggers + datos base)
-- Para una base de datos NUEVA o VACIA de produccion (phpMyAdmin > Importar o pestaña SQL).
-- No incluye usuarios ni datos de negocio: crea el primer super admin aparte.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_event_index` (`event`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `audit_logs_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in audit_logs cannot be updated' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `audit_logs_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in audit_logs cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `box_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `box_id` bigint unsigned NOT NULL,
  `type` enum('entry','exit','transfer_out','transfer_in','partial_withdrawal','adjustment','reversal','transfer_cancel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_chamber_id` bigint unsigned DEFAULT NULL,
  `to_chamber_id` bigint unsigned DEFAULT NULL,
  `to_branch_id` bigint unsigned DEFAULT NULL,
  `weight_before_kg` decimal(10,3) NOT NULL,
  `weight_after_kg` decimal(10,3) NOT NULL,
  `status_before` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_after` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `entry_id` bigint unsigned DEFAULT NULL,
  `exit_id` bigint unsigned DEFAULT NULL,
  `transfer_id` bigint unsigned DEFAULT NULL,
  `adjustment_id` bigint unsigned DEFAULT NULL,
  `reverses_movement_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_movements_from_chamber_id_foreign` (`from_chamber_id`),
  KEY `box_movements_to_chamber_id_foreign` (`to_chamber_id`),
  KEY `box_movements_to_branch_id_foreign` (`to_branch_id`),
  KEY `box_movements_entry_id_foreign` (`entry_id`),
  KEY `box_movements_exit_id_foreign` (`exit_id`),
  KEY `box_movements_transfer_id_foreign` (`transfer_id`),
  KEY `box_movements_adjustment_id_foreign` (`adjustment_id`),
  KEY `box_movements_reverses_movement_id_foreign` (`reverses_movement_id`),
  KEY `box_movements_box_id_occurred_at_index` (`box_id`,`occurred_at`),
  KEY `box_movements_type_occurred_at_index` (`type`,`occurred_at`),
  KEY `box_movements_user_id_occurred_at_index` (`user_id`,`occurred_at`),
  CONSTRAINT `box_movements_adjustment_id_foreign` FOREIGN KEY (`adjustment_id`) REFERENCES `inventory_adjustments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_entry_id_foreign` FOREIGN KEY (`entry_id`) REFERENCES `entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_exit_id_foreign` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_from_chamber_id_foreign` FOREIGN KEY (`from_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_reverses_movement_id_foreign` FOREIGN KEY (`reverses_movement_id`) REFERENCES `box_movements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_to_branch_id_foreign` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_to_chamber_id_foreign` FOREIGN KEY (`to_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `box_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `box_movements_no_update` BEFORE UPDATE ON `box_movements` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in box_movements cannot be updated' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `box_movements_no_delete` BEFORE DELETE ON `box_movements` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in box_movements cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `boxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode_source` enum('generated','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated',
  `manual_barcode_origin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `entry_item_id` bigint unsigned NOT NULL,
  `origin_chamber_id` bigint unsigned NOT NULL,
  `initial_weight_kg` decimal(10,3) NOT NULL,
  `current_weight_kg` decimal(10,3) NOT NULL,
  `unit_cost_mxn` decimal(14,2) NOT NULL,
  `entered_at` datetime NOT NULL,
  `voided_at` datetime DEFAULT NULL,
  `status` enum('available','in_transit','dispatched','depleted','written_off') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `current_chamber_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boxes_barcode_unique` (`barcode`),
  KEY `boxes_category_id_foreign` (`category_id`),
  KEY `boxes_entry_item_id_foreign` (`entry_item_id`),
  KEY `boxes_origin_chamber_id_foreign` (`origin_chamber_id`),
  KEY `boxes_current_chamber_id_status_product_id_index` (`current_chamber_id`,`status`,`product_id`),
  KEY `boxes_product_id_entered_at_index` (`product_id`,`entered_at`),
  CONSTRAINT `boxes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `boxes_current_chamber_id_foreign` FOREIGN KEY (`current_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `boxes_entry_item_id_foreign` FOREIGN KEY (`entry_item_id`) REFERENCES `entry_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `boxes_origin_chamber_id_foreign` FOREIGN KEY (`origin_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `boxes_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `boxes_depleted_is_empty` CHECK (((`status` <> _utf8mb4'depleted') or (`current_weight_kg` = 0))),
  CONSTRAINT `boxes_location_matches_status` CHECK (((`status` = _utf8mb4'available') = (`current_chamber_id` is not null))),
  CONSTRAINT `boxes_manual_origin_required` CHECK (((`barcode_source` <> _utf8mb4'manual') or (`manual_barcode_origin` is not null))),
  CONSTRAINT `boxes_weights_valid` CHECK (((`initial_weight_kg` > 0) and (`current_weight_kg` >= 0) and (`current_weight_kg` <= `initial_weight_kg`) and (`unit_cost_mxn` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `boxes_no_delete` BEFORE DELETE ON `boxes` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in boxes cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `chamber_user` (
  `user_id` bigint unsigned NOT NULL,
  `chamber_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`chamber_id`),
  KEY `chamber_user_chamber_id_foreign` (`chamber_id`),
  CONSTRAINT `chamber_user_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chamber_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `chambers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chambers_branch_id_name_unique` (`branch_id`,`name`),
  UNIQUE KEY `chambers_code_unique` (`code`),
  CONSTRAINT `chambers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chamber_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `total_boxes` int unsigned NOT NULL,
  `total_weight_kg` decimal(14,3) NOT NULL,
  `total_cost_mxn` decimal(16,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('confirmed','reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `client_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entries_reference_unique` (`reference`),
  UNIQUE KEY `entries_client_token_unique` (`client_token`),
  KEY `entries_user_id_foreign` (`user_id`),
  KEY `entries_chamber_id_occurred_at_index` (`chamber_id`,`occurred_at`),
  CONSTRAINT `entries_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `entries_immutable_columns` BEFORE UPDATE ON `entries` FOR EACH ROW BEGIN IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`reference` <=> NEW.`reference`) OR NOT (OLD.`chamber_id` <=> NEW.`chamber_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`client_token` <=> NEW.`client_token`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of entries cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `entries_no_delete` BEFORE DELETE ON `entries` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in entries cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `entry_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `boxes_count` int unsigned NOT NULL,
  `weight_per_box_kg` decimal(10,3) NOT NULL,
  `unit_cost_mxn` decimal(14,2) NOT NULL,
  `total_weight_kg` decimal(14,3) NOT NULL,
  `total_cost_mxn` decimal(16,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_items_entry_id_foreign` (`entry_id`),
  KEY `entry_items_product_id_index` (`product_id`),
  CONSTRAINT `entry_items_entry_id_foreign` FOREIGN KEY (`entry_id`) REFERENCES `entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entry_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `entry_items_positive` CHECK (((`boxes_count` > 0) and (`weight_per_box_kg` > 0) and (`unit_cost_mxn` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `entry_items_immutable_columns` BEFORE UPDATE ON `entry_items` FOR EACH ROW BEGIN IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`entry_id` <=> NEW.`entry_id`) OR NOT (OLD.`product_id` <=> NEW.`product_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of entry_items cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `entry_items_no_delete` BEFORE DELETE ON `entry_items` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in entry_items cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `exit_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exit_id` bigint unsigned NOT NULL,
  `box_id` bigint unsigned NOT NULL,
  `kind` enum('full','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `weight_before_kg` decimal(10,3) NOT NULL,
  `weight_out_kg` decimal(10,3) NOT NULL,
  `weight_after_kg` decimal(10,3) NOT NULL,
  `unit_cost_snapshot_mxn` decimal(14,2) NOT NULL,
  `cost_out_mxn` decimal(16,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `holds_box` tinyint(1) NOT NULL DEFAULT '1',
  `active_full_box_id` bigint unsigned GENERATED ALWAYS AS (if(((`kind` = _utf8mb4'full') and (`is_active` = 1) and (`holds_box` = 1)),`box_id`,NULL)) VIRTUAL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exit_items_exit_id_box_id_unique` (`exit_id`,`box_id`),
  UNIQUE KEY `exit_items_active_full_box_id_unique` (`active_full_box_id`),
  KEY `exit_items_box_id_index` (`box_id`),
  CONSTRAINT `exit_items_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_items_exit_id_foreign` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exit_items_full_empties_box` CHECK (((`kind` <> _utf8mb4'full') or (`weight_after_kg` = 0))),
  CONSTRAINT `exit_items_weights_valid` CHECK (((`weight_out_kg` > 0) and (`weight_after_kg` >= 0) and (`weight_after_kg` = (`weight_before_kg` - `weight_out_kg`))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `exit_items_immutable_columns` BEFORE UPDATE ON `exit_items` FOR EACH ROW BEGIN IF NOT (OLD.`box_id` <=> NEW.`box_id`) OR NOT (OLD.`cost_out_mxn` <=> NEW.`cost_out_mxn`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) OR NOT (OLD.`exit_id` <=> NEW.`exit_id`) OR NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`kind` <=> NEW.`kind`) OR NOT (OLD.`unit_cost_snapshot_mxn` <=> NEW.`unit_cost_snapshot_mxn`) OR NOT (OLD.`weight_after_kg` <=> NEW.`weight_after_kg`) OR NOT (OLD.`weight_before_kg` <=> NEW.`weight_before_kg`) OR NOT (OLD.`weight_out_kg` <=> NEW.`weight_out_kg`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of exit_items cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `exit_items_no_delete` BEFORE DELETE ON `exit_items` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in exit_items cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `exits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_chamber_id` bigint unsigned NOT NULL,
  `dest_branch_id` bigint unsigned NOT NULL,
  `dest_chamber_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `total_boxes` int unsigned NOT NULL,
  `total_weight_kg` decimal(14,3) NOT NULL,
  `total_cost_mxn` decimal(16,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('confirmed','reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint unsigned DEFAULT NULL,
  `reversed_reason` text COLLATE utf8mb4_unicode_ci,
  `client_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exits_reference_unique` (`reference`),
  UNIQUE KEY `exits_client_token_unique` (`client_token`),
  KEY `exits_dest_chamber_id_foreign` (`dest_chamber_id`),
  KEY `exits_user_id_foreign` (`user_id`),
  KEY `exits_reversed_by_foreign` (`reversed_by`),
  KEY `exits_origin_chamber_id_occurred_at_index` (`origin_chamber_id`,`occurred_at`),
  KEY `exits_dest_branch_id_occurred_at_index` (`dest_branch_id`,`occurred_at`),
  KEY `exits_requester_name_index` (`requester_name`),
  CONSTRAINT `exits_dest_branch_id_foreign` FOREIGN KEY (`dest_branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exits_dest_chamber_id_foreign` FOREIGN KEY (`dest_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exits_origin_chamber_id_foreign` FOREIGN KEY (`origin_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exits_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `exits_immutable_columns` BEFORE UPDATE ON `exits` FOR EACH ROW BEGIN IF NOT (OLD.`client_token` <=> NEW.`client_token`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) OR NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`origin_chamber_id` <=> NEW.`origin_chamber_id`) OR NOT (OLD.`reference` <=> NEW.`reference`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of exits cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `exits_no_delete` BEFORE DELETE ON `exits` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in exits cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint unsigned NOT NULL,
  `box_id` bigint unsigned DEFAULT NULL,
  `type` enum('missing','unexpected','weight_difference','damaged','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_weight_kg` decimal(10,3) DEFAULT NULL,
  `received_weight_kg` decimal(10,3) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `reported_by` bigint unsigned NOT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incidents_box_id_foreign` (`box_id`),
  KEY `incidents_reported_by_foreign` (`reported_by`),
  KEY `incidents_resolved_by_foreign` (`resolved_by`),
  KEY `incidents_status_type_index` (`status`,`type`),
  KEY `incidents_transfer_id_index` (`transfer_id`),
  CONSTRAINT `incidents_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `incidents_reported_by_foreign` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `incidents_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `incidents_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `incidents_immutable_columns` BEFORE UPDATE ON `incidents` FOR EACH ROW BEGIN IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`transfer_id` <=> NEW.`transfer_id`) OR NOT (OLD.`box_id` <=> NEW.`box_id`) OR NOT (OLD.`type` <=> NEW.`type`) OR NOT (OLD.`expected_weight_kg` <=> NEW.`expected_weight_kg`) OR NOT (OLD.`received_weight_kg` <=> NEW.`received_weight_kg`) OR NOT (OLD.`description` <=> NEW.`description`) OR NOT (OLD.`reported_by` <=> NEW.`reported_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of incidents cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `incidents_no_delete` BEFORE DELETE ON `incidents` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in incidents cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chamber_id` bigint unsigned DEFAULT NULL,
  `box_id` bigint unsigned NOT NULL,
  `type` enum('weight_correction','found','lost','damaged','status_fix') COLLATE utf8mb4_unicode_ci NOT NULL,
  `weight_before_kg` decimal(10,3) NOT NULL,
  `weight_after_kg` decimal(10,3) NOT NULL,
  `status_before` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_after` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `authorized_by` bigint unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_adjustments_reference_unique` (`reference`),
  KEY `inventory_adjustments_requested_by_foreign` (`requested_by`),
  KEY `inventory_adjustments_authorized_by_foreign` (`authorized_by`),
  KEY `inventory_adjustments_box_id_occurred_at_index` (`box_id`,`occurred_at`),
  KEY `inventory_adjustments_chamber_id_occurred_at_index` (`chamber_id`,`occurred_at`),
  CONSTRAINT `inventory_adjustments_authorized_by_foreign` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_adjustments_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_adjustments_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_adjustments_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `inventory_adjustments_no_update` BEFORE UPDATE ON `inventory_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in inventory_adjustments cannot be updated' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `inventory_adjustments_no_delete` BEFORE DELETE ON `inventory_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in inventory_adjustments cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `passkeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credential_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credential` json NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkeys_credential_id_unique` (`credential_id`),
  KEY `passkeys_user_id_index` (`user_id`),
  CONSTRAINT `passkeys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_code_unique` (`code`),
  KEY `products_category_id_is_active_index` (`category_id`,`is_active`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_code_format` CHECK (regexp_like(`code`,_utf8mb4'^[A-Z0-9]{2,30}$'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sequences` (
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_value` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `stock_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chamber_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `min_boxes` int unsigned DEFAULT NULL,
  `min_weight_kg` decimal(10,3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_thresholds_chamber_id_product_id_unique` (`chamber_id`,`product_id`),
  KEY `stock_thresholds_product_id_foreign` (`product_id`),
  CONSTRAINT `stock_thresholds_chamber_id_foreign` FOREIGN KEY (`chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_thresholds_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint unsigned NOT NULL,
  `box_id` bigint unsigned NOT NULL,
  `expected_weight_kg` decimal(10,3) NOT NULL,
  `received_weight_kg` decimal(10,3) DEFAULT NULL,
  `status` enum('expected','received','missing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expected',
  `removed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_items_transfer_id_box_id_unique` (`transfer_id`,`box_id`),
  KEY `transfer_items_box_id_index` (`box_id`),
  CONSTRAINT `transfer_items_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfer_items_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `transfer_items_immutable_columns` BEFORE UPDATE ON `transfer_items` FOR EACH ROW BEGIN IF NOT (OLD.`box_id` <=> NEW.`box_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) OR NOT (OLD.`expected_weight_kg` <=> NEW.`expected_weight_kg`) OR NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`transfer_id` <=> NEW.`transfer_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of transfer_items cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `transfer_items_no_delete` BEFORE DELETE ON `transfer_items` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in transfer_items cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exit_id` bigint unsigned NOT NULL,
  `origin_chamber_id` bigint unsigned NOT NULL,
  `dest_chamber_id` bigint unsigned NOT NULL,
  `status` enum('pending','in_transit','received','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_transit',
  `dispatched_at` datetime NOT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `receive_notes` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfers_reference_unique` (`reference`),
  UNIQUE KEY `transfers_exit_id_unique` (`exit_id`),
  KEY `transfers_received_by_foreign` (`received_by`),
  KEY `transfers_cancelled_by_foreign` (`cancelled_by`),
  KEY `transfers_dest_chamber_id_status_index` (`dest_chamber_id`,`status`),
  KEY `transfers_origin_chamber_id_status_index` (`origin_chamber_id`,`status`),
  CONSTRAINT `transfers_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfers_dest_chamber_id_foreign` FOREIGN KEY (`dest_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfers_exit_id_foreign` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfers_origin_chamber_id_foreign` FOREIGN KEY (`origin_chamber_id`) REFERENCES `chambers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfers_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `transfers_immutable_columns` BEFORE UPDATE ON `transfers` FOR EACH ROW BEGIN IF NOT (OLD.`created_at` <=> NEW.`created_at`) OR NOT (OLD.`exit_id` <=> NEW.`exit_id`) OR NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`origin_chamber_id` <=> NEW.`origin_chamber_id`) OR NOT (OLD.`reference` <=> NEW.`reference`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of transfers cannot be changed'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `transfers_no_delete` BEFORE DELETE ON `transfers` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in transfers cannot be deleted' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','super_admin','warehouse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warehouse',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `branch_id` bigint unsigned DEFAULT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_branch_id_foreign` (`branch_id`),
  KEY `users_role_index` (`role`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2024_01_01_000000_create_passkeys_table',1),(5,'2025_08_14_170933_add_two_factor_columns_to_users_table',1),(6,'2026_10_28_000001_create_branches_table',1),(7,'2026_10_28_000002_add_inventory_columns_to_users_table',1),(8,'2026_10_28_000003_create_chambers_table',1),(9,'2026_10_28_000004_create_chamber_user_table',1),(10,'2026_10_28_000005_create_categories_table',1),(11,'2026_10_28_000006_create_products_table',1),(12,'2026_10_28_000007_create_stock_thresholds_table',1),(13,'2026_10_28_000008_create_sequences_table',1),(14,'2026_10_28_000009_create_entries_table',1),(15,'2026_10_28_000010_create_entry_items_table',1),(16,'2026_10_28_000011_create_boxes_table',1),(17,'2026_10_28_000012_create_exits_table',1),(18,'2026_10_28_000013_create_transfers_table',1),(19,'2026_10_28_000014_create_exit_items_table',1),(20,'2026_10_28_000015_create_transfer_items_table',1),(21,'2026_10_28_000016_create_incidents_table',1),(22,'2026_10_28_000017_create_inventory_adjustments_table',1),(23,'2026_10_28_000018_create_box_movements_table',1),(24,'2026_10_28_000019_create_audit_logs_table',1),(25,'2026_10_28_000020_add_immutability_triggers',1),(26,'2026_10_28_000021_rename_usd_columns_to_mxn',1),(27,'2026_10_28_000022_widen_product_code_and_barcode_lengths',1),(28,'2026_10_30_000023_add_super_admin_role_to_users',1),(29,'2026_11_03_140848_add_receive_notes_to_transfers_table',1),(30,'2026_11_03_144607_allow_received_boxes_to_leave_again',2),(31,'2026_11_03_152626_allow_super_admin_corrections_of_entries_and_exits',3),(32,'2026_11_05_100000_allow_super_admin_corrections_of_transfers',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sequences` WRITE;
/*!40000 ALTER TABLE `sequences` DISABLE KEYS */;
INSERT INTO `sequences` VALUES ('adjustment',0,NOW(),NOW()),('barcode',0,NOW(),NOW()),('entry',0,NOW(),NOW()),('exit',0,NOW(),NOW()),('transfer',0,NOW(),NOW());
/*!40000 ALTER TABLE `sequences` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

