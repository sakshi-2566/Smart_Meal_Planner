-- Migration Script: Add Admin Role Support
-- Run this if you already have an existing database

USE smart_meal_planner;

-- Step 1: Add role column to users table if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role ENUM('user', 'admin') DEFAULT 'user' AFTER dietary_preference;

-- Step 2: Add index for role column
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_role (role);

-- Step 3: Check if admin user already exists
SET @admin_exists = (SELECT COUNT(*) FROM users WHERE email = 'Admin@smartmealplanner.com');

-- Step 4: Insert admin user only if doesn't exist
INSERT INTO users (first_name, last_name, email, password, dietary_preference, role, is_active)
SELECT 'Admin', 'User', 'Admin@smartmealplanner.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'none', 'admin', TRUE
WHERE @admin_exists = 0;

-- Step 5: Get the admin user ID
SET @admin_id = (SELECT id FROM users WHERE email = 'Admin@smartmealplanner.com');

-- Step 6: Check if admin profile exists
SET @profile_exists = (SELECT COUNT(*) FROM user_profiles WHERE user_id = @admin_id);

-- Step 7: Create admin profile if doesn't exist
INSERT INTO user_profiles (user_id, age, gender, height, weight, activity_level, goal, bmr, tdee, target_calories, target_protein, target_carbs, target_fats)
SELECT @admin_id, 30, 'male', 175, 75, 'moderate', 'maintenance', 1750, 2712, 2712, 203, 271, 90
WHERE @profile_exists = 0;

-- Verify admin user was created
SELECT 
    id, 
    first_name, 
    last_name, 
    email, 
    role, 
    is_active,
    created_at
FROM users 
WHERE email = 'Admin@smartmealplanner.com';
