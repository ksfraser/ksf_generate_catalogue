-- SQL Updates for Enhanced Square Catalog Generator
-- Date: 2025-08-31

-- =====================================================
-- 1. Social Media Fields Table (Generic for e-commerce)
-- =====================================================
CREATE TABLE IF NOT EXISTS `0_social_media_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` varchar(20) NOT NULL,
  `social_media_title` varchar(255) DEFAULT NULL,
  `social_media_description` text DEFAULT NULL,
  `last_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_id` (`stock_id`),
  KEY `idx_stock_id` (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. Contains Alcohol Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `0_contains_alcohol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` varchar(20) NOT NULL,
  `contains_alcohol` tinyint(1) NOT NULL DEFAULT 0,
  `last_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_id` (`stock_id`),
  KEY `idx_stock_id` (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. Square Token Import Log Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `0_square_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `import_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `records_processed` int(11) DEFAULT 0,
  `records_updated` int(11) DEFAULT 0,
  `records_created` int(11) DEFAULT 0,
  `import_status` enum('success','partial','failed') DEFAULT 'success',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_date` (`import_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. Product Variations Table (for future use)
-- =====================================================
-- CREATE TABLE IF NOT EXISTS `0_product_variations` (
--   `id` int(11) NOT NULL AUTO_INCREMENT,
--   `stock_id` varchar(20) NOT NULL,
--   `option_name_1` varchar(100) DEFAULT NULL,
--   `option_value_1` varchar(100) DEFAULT NULL,
--   `option_name_2` varchar(100) DEFAULT NULL,
--   `option_value_2` varchar(100) DEFAULT NULL,
--   `option_name_3` varchar(100) DEFAULT NULL,
--   `option_value_3` varchar(100) DEFAULT NULL,
--   `is_active` tinyint(1) NOT NULL DEFAULT 1,
--   `last_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`),
--   KEY `idx_stock_id` (`stock_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. Add new configuration fields to existing prefs
-- Note: This assumes your preferences table structure
-- Adjust table name as needed for your specific setup
-- =====================================================
-- ALTER TABLE `0_ksf_generate_catalogue_prefs` ADD COLUMN `online_sale_pricebook_id` int(11) DEFAULT 2 COMMENT 'Sales type ID for online sale prices';
-- ALTER TABLE `0_ksf_generate_catalogue_prefs` ADD COLUMN `default_reporting_category` varchar(100) DEFAULT 'General' COMMENT 'Default reporting category for Square';
-- ALTER TABLE `0_ksf_generate_catalogue_prefs` ADD COLUMN `enable_alcohol_tracking` tinyint(1) DEFAULT 1 COMMENT 'Enable alcohol content tracking';

-- =====================================================
-- 6. Foreign Key Constraints (if desired)
-- =====================================================
-- Note: Uncomment these if you want strict referential integrity
-- ALTER TABLE `0_social_media_fields` ADD CONSTRAINT `fk_social_media_stock` 
--   FOREIGN KEY (`stock_id`) REFERENCES `0_stock_master` (`stock_id`) ON DELETE CASCADE;

-- ALTER TABLE `0_contains_alcohol` ADD CONSTRAINT `fk_alcohol_stock` 
--   FOREIGN KEY (`stock_id`) REFERENCES `0_stock_master` (`stock_id`) ON DELETE CASCADE;

-- ALTER TABLE `0_product_variations` ADD CONSTRAINT `fk_variations_stock` 
--   FOREIGN KEY (`stock_id`) REFERENCES `0_stock_master` (`stock_id`) ON DELETE CASCADE;

-- =====================================================
-- 7. Sample Data Insertion (optional)
-- =====================================================
-- INSERT INTO `0_social_media_fields` (`stock_id`, `social_media_title`, `social_media_description`) 
-- VALUES 
--   ('SAMPLE001', 'Check out our amazing product!', 'Perfect for your daily needs. High quality and affordable.'),
--   ('SAMPLE002', 'Limited time offer!', 'Don\'t miss this incredible deal on our bestselling item.');

-- INSERT INTO `0_contains_alcohol` (`stock_id`, `contains_alcohol`) 
-- VALUES 
--   ('WINE001', 1),
--   ('BEER001', 1),
--   ('JUICE001', 0);

COMMIT;
