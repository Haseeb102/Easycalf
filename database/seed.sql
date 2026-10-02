-- Default events, milk allowances, and powder ratio.
-- Import this only after an administrator user with id 1 exists.
-- The installer does that. For a manual install, create that user first
-- with a password hash you generate yourself. Do not commit the hash.

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
