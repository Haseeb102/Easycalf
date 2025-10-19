-- EasyCalf Database Schema - Complete with Treatment System & Audit Trail
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime NULL,
  `last_login` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Batches table
CREATE TABLE IF NOT EXISTS `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('normal','sick_pen','weaning_group','isolation') DEFAULT 'normal',
  `capacity` int(11) DEFAULT 10,
  `location` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calves table
CREATE TABLE IF NOT EXISTS `calves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `calf_id` varchar(50) NOT NULL UNIQUE,
  `birth_date` date NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `dam_id` varchar(50) DEFAULT NULL,
  `birth_weight` decimal(5,2) DEFAULT NULL,
  `health_status` enum('healthy','needs_attention','sick') DEFAULT 'healthy',
  `status` enum('active','sold','deceased') DEFAULT 'active',
  `batch_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_calf_id` (`calf_id`),
  INDEX `idx_birth_date` (`birth_date`),
  INDEX `idx_health_status` (`health_status`),
  INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events template table
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('vaccination','treatment','management','health') NOT NULL,
  `age_start` int(11) NOT NULL,
  `age_end` int(11) NOT NULL,
  `preferred_day` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') DEFAULT NULL,
  `reminder_days` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calf events table (ENHANCED with user tracking)
CREATE TABLE IF NOT EXISTS `calf_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `calf_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `completed_date` datetime NULL,
  `completed_by` int(11) NULL,
  `completed_notes` text NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_due_date` (`due_date`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NEW: Treatment Plans table
CREATE TABLE IF NOT EXISTS `treatment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `calf_id` int(11) NOT NULL,
  `treatment_type` enum('electrolyte','antibiotic','medication','other') NOT NULL,
  `treatment_name` varchar(100) NOT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `start_date` date NOT NULL,
  `duration_days` int(11) DEFAULT 3,
  `current_day` int(11) DEFAULT 1,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`calf_id`) REFERENCES `calves`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  INDEX `idx_treatment_status` (`status`),
  INDEX `idx_treatment_type` (`treatment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NEW: Treatment Completions audit table
CREATE TABLE IF NOT EXISTS `treatment_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `treatment_plan_id` int(11) NOT NULL,
  `calf_id` int(11) NOT NULL,
  `completed_day` int(11) NOT NULL,
  `completed_by` int(11) NOT NULL,
  `completed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`treatment_plan_id`) REFERENCES `treatment_plans`(`id`),
  FOREIGN KEY (`calf_id`) REFERENCES `calves`(`id`),
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`),
  INDEX `idx_completion_date` (`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Milk allowances table
CREATE TABLE IF NOT EXISTS `milk_allowances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `age_start` int(11) NOT NULL,
  `age_end` int(11) NOT NULL,
  `milk_amount` decimal(4,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  UNIQUE KEY `unique_age_range` (`age_start`, `age_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Milk powder ratio settings table
CREATE TABLE IF NOT EXISTS `milk_powder_ratio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `powder_amount` decimal(4,2) NOT NULL COMMENT 'Amount of powder in grams',
  `water_amount` decimal(4,2) NOT NULL COMMENT 'Amount of water in liters',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ENHANCED: Activity logs table with treatment tracking
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `calf_id` int(11) NULL,
  `treatment_plan_id` int(11) NULL,
  `task_id` int(11) NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`calf_id`) REFERENCES `calves`(`id`),
  FOREIGN KEY (`treatment_plan_id`) REFERENCES `treatment_plans`(`id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions table
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `approved_at`) VALUES
('Administrator', 'admin@easycalf.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW());

-- Insert default events
INSERT INTO `events` (`name`, `type`, `age_start`, `age_end`, `reminder_days`, `created_by`) VALUES
('Colostrum Check', 'health', 0, 0, 0, 1),
('Navel Dip', 'treatment', 0, 0, 0, 1),
('Tagging', 'management', 1, 1, 0, 1),
('First Vaccination', 'vaccination', 14, 14, 2, 1),
('Disbudding', 'management', 21, 28, 3, 1),
('Deworming', 'treatment', 30, 30, 2, 1),
('Booster Vaccine', 'vaccination', 45, 45, 2, 1),
('Weaning', 'management', 60, 80, 7, 1);

-- Insert default milk allowances (PER FEED amounts - 2 feeds daily)
INSERT INTO `milk_allowances` (`age_start`, `age_end`, `milk_amount`, `created_by`) VALUES
(0, 10, 1.0, 1),    -- 1.0L per feed = 2.0L daily
(11, 15, 1.25, 1),  -- 1.25L per feed = 2.5L daily
(16, 30, 1.5, 1),   -- 1.5L per feed = 3.0L daily
(31, 60, 1.25, 1);  -- 1.25L per feed = 2.5L daily

-- Insert default milk powder ratio (150g powder per 1L water)
INSERT INTO `milk_powder_ratio` (`powder_amount`, `water_amount`, `created_by`) VALUES
(150, 1.0, 1);

SET FOREIGN_KEY_CHECKS = 1;