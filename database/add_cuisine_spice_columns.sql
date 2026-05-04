-- Add cuisine_type and spice_level columns to recipes table
-- Run this to enable cuisine and spice level filtering

ALTER TABLE recipes 
ADD COLUMN cuisine_type VARCHAR(50) DEFAULT NULL AFTER dietary_tags,
ADD COLUMN spice_level ENUM('mild', 'medium', 'spicy') DEFAULT 'mild' AFTER cuisine_type;

-- Add indexes for better query performance
ALTER TABLE recipes
ADD INDEX idx_cuisine_type (cuisine_type),
ADD INDEX idx_spice_level (spice_level);

-- Update existing recipes with default values
UPDATE recipes 
SET cuisine_type = 'Continental', 
    spice_level = 'mild' 
WHERE cuisine_type IS NULL;
