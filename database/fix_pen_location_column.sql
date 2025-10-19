-- =============================================
-- Fix Missing pen_location Column
-- Created: 2024
-- Description: Adds missing pen_location column to resolve import errors
-- =============================================

-- Check if the column already exists (safe execution)
SET @db_name = DATABASE();
SET @table_name = 'animals'; -- Replace with your actual table name
SET @column_name = 'pen_location';

-- Check if column exists
SELECT COUNT(*) INTO @column_exists
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
AND TABLE_NAME = @table_name 
AND COLUMN_NAME = @column_name;

-- Add the column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    CONCAT('ALTER TABLE ', @table_name, ' ADD COLUMN pen_location VARCHAR(255) NULL AFTER species;'),
    'SELECT "Column pen_location already exists" as Status;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the column was added
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
AND TABLE_NAME = @table_name 
AND COLUMN_NAME = @column_name;

-- Show updated table structure
DESCRIBE animals; -- Replace with your actual table name

-- =============================================
-- Alternative versions for different table names:
-- =============================================

-- If your table name is different, use one of these:

-- For table named 'livestock':
-- ALTER TABLE livestock ADD COLUMN pen_location VARCHAR(255) NULL AFTER species;

-- For table named 'cattle':
-- ALTER TABLE cattle ADD COLUMN pen_location VARCHAR(255) NULL AFTER breed;

-- For table named 'records':
-- ALTER TABLE records ADD COLUMN pen_location VARCHAR(255) NULL;

-- =============================================
-- Different data type options:
-- =============================================

-- If you need TEXT instead of VARCHAR:
-- ALTER TABLE animals ADD COLUMN pen_location TEXT NULL;

-- If you need integer values:
-- ALTER TABLE animals ADD COLUMN pen_location INT NULL;

-- If you need with default value:
-- ALTER TABLE animals ADD COLUMN pen_location VARCHAR(255) DEFAULT 'Main Pen' NULL;

-- =============================================
-- Update existing records (optional):
-- =============================================

-- If you want to set default values for existing records:
-- UPDATE animals SET pen_location = 'Default Pen' WHERE pen_location IS NULL;

SELECT 'Column pen_location has been successfully added to the table.' as Result;