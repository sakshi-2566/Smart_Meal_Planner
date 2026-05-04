-- Add payment_method column to orders table
-- Run this SQL in phpMyAdmin or your MySQL client

USE smart_meal_planner;

-- Check if column exists and add it if not
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'smart_meal_planner' 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'payment_method';

SET @query = IF(@col_exists = 0,
    'ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT ''pending'' AFTER payment_status',
    'SELECT ''Column payment_method already exists'' AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the result
DESCRIBE orders;
