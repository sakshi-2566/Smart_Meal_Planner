-- Step-by-Step Admin Setup
-- Execute these queries one by one in phpMyAdmin

-- STEP 1: Select your database
USE smart_meal_planner;

-- STEP 2: Add role column to users table
ALTER TABLE users 
ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER dietary_preference;

-- STEP 3: Add index for better performance
ALTER TABLE users 
ADD INDEX idx_role (role);

-- STEP 4: Insert admin user
-- Password: Admin@123
INSERT INTO users (first_name, last_name, email, password, dietary_preference, role, is_active) 
VALUES (
    'Admin', 
    'User', 
    'Admin@smartmealplanner.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'none', 
    'admin', 
    TRUE
);

-- STEP 5: Get the admin user ID (note the ID from the result)
SELECT id FROM users WHERE email = 'Admin@smartmealplanner.com';

-- STEP 6: Create admin profile (replace 1 with the actual ID from step 5 if different)
INSERT INTO user_profiles (user_id, age, gender, height, weight, activity_level, goal, bmr, tdee, target_calories, target_protein, target_carbs, target_fats) 
VALUES (
    1,  -- Replace with actual admin user ID if different
    30, 
    'male', 
    175, 
    75, 
    'moderate', 
    'maintenance', 
    1750, 
    2712, 
    2712, 
    203, 
    271, 
    90
);

-- STEP 7: Verify admin user was created successfully
SELECT 
    u.id, 
    u.first_name, 
    u.last_name, 
    u.email, 
    u.role, 
    u.is_active,
    u.created_at,
    p.bmr,
    p.tdee
FROM users u
LEFT JOIN user_profiles p ON u.id = p.user_id
WHERE u.email = 'Admin@smartmealplanner.com';

-- You should see the admin user with role='admin'
-- Now you can login with:
-- Email: Admin@smartmealplanner.com
-- Password: Admin@123
