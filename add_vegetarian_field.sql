-- Add vegetarian field to ingredients table
USE smart_meal_planner;

-- Add is_vegetarian field to ingredients table
ALTER TABLE ingredients 
ADD COLUMN IF NOT EXISTS is_vegetarian BOOLEAN DEFAULT TRUE AFTER category;

-- Update existing ingredients based on category
UPDATE ingredients SET is_vegetarian = FALSE 
WHERE category IN ('Protein') 
AND (
    ingredient_name LIKE '%chicken%' OR
    ingredient_name LIKE '%beef%' OR
    ingredient_name LIKE '%pork%' OR
    ingredient_name LIKE '%fish%' OR
    ingredient_name LIKE '%mutton%' OR
    ingredient_name LIKE '%lamb%' OR
    ingredient_name LIKE '%turkey%' OR
    ingredient_name LIKE '%duck%' OR
    ingredient_name LIKE '%meat%' OR
    ingredient_name LIKE '%bacon%' OR
    ingredient_name LIKE '%ham%' OR
    ingredient_name LIKE '%sausage%' OR
    ingredient_name LIKE '%prawn%' OR
    ingredient_name LIKE '%shrimp%' OR
    ingredient_name LIKE '%crab%' OR
    ingredient_name LIKE '%lobster%'
);

-- Update eggs as non-vegetarian (some consider them non-veg)
UPDATE ingredients SET is_vegetarian = FALSE 
WHERE ingredient_name LIKE '%egg%';

SELECT 'Vegetarian field added and updated successfully!' as Status;