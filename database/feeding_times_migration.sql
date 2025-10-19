-- Migration: Add Feeding Times Configuration Table
-- This allows admins to configure AM/PM feeding shift times

CREATE TABLE IF NOT EXISTS `feeding_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(20) NOT NULL,
  `shift_label` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift` (`shift_name`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default feeding times
INSERT INTO `feeding_times` (`shift_name`, `shift_label`, `start_time`, `end_time`, `created_by`) VALUES
('AM', 'Morning Feed', '06:30:00', '09:00:00', 1),
('PM', 'Evening Feed', '16:00:00', '18:00:00', 1);