-- Create Database
CREATE DATABASE IF NOT EXISTS smart_meal_planner;
USE smart_meal_planner;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    dietary_preference VARCHAR(50) DEFAULT 'none',
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Profiles Table
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT,
    gender ENUM('male', 'female', 'other'),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    activity_level ENUM('sedentary', 'light', 'moderate', 'active', 'very_active') DEFAULT 'moderate',
    goal ENUM('weight_loss', 'maintenance', 'muscle_gain', 'athletic') DEFAULT 'maintenance',
    bmr DECIMAL(7,2),
    tdee DECIMAL(7,2),
    target_calories INT,
    target_protein INT,
    target_carbs INT,
    target_fats INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Meal Plans Table
CREATE TABLE IF NOT EXISTS meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_calories INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recipes Table
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    recipe_name VARCHAR(150) NOT NULL,
    description TEXT,
    instructions TEXT,
    prep_time INT,
    cook_time INT,
    servings INT DEFAULT 1,
    calories INT,
    protein DECIMAL(6,2),
    carbs DECIMAL(6,2),
    fats DECIMAL(6,2),
    dietary_tags VARCHAR(255),
    image_url VARCHAR(255),
    is_ai_generated BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_dietary_tags (dietary_tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ingredients Table
CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    calories_per_100g DECIMAL(6,2),
    protein_per_100g DECIMAL(6,2),
    carbs_per_100g DECIMAL(6,2),
    fats_per_100g DECIMAL(6,2),
    unit VARCHAR(20) DEFAULT 'g',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (ingredient_name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recipe Ingredients Table
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(8,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    INDEX idx_recipe_id (recipe_id),
    INDEX idx_ingredient_id (ingredient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shopping Lists Table
CREATE TABLE IF NOT EXISTS shopping_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    list_name VARCHAR(100) NOT NULL,
    meal_plan_id INT,
    total_estimated_price DECIMAL(10,2),
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shopping List Items Table
CREATE TABLE IF NOT EXISTS shopping_list_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shopping_list_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(8,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    estimated_price DECIMAL(8,2),
    is_purchased BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (shopping_list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    INDEX idx_shopping_list_id (shopping_list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nutrition Logs Table
CREATE TABLE IF NOT EXISTS nutrition_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    log_date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    recipe_id INT,
    calories INT,
    protein DECIMAL(6,2),
    carbs DECIMAL(6,2),
    fats DECIMAL(6,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, log_date),
    INDEX idx_log_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Inventory Table
CREATE TABLE IF NOT EXISTS user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(8,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    expiry_date DATE,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Admin User
-- Password: Admin@123 (hashed with bcrypt)
INSERT INTO users (first_name, last_name, email, password, dietary_preference, role, is_active) VALUES
('Admin', 'User', 'Admin@smartmealplanner.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'none', 'admin', TRUE);

-- Create Admin Profile
INSERT INTO user_profiles (user_id, age, gender, height, weight, activity_level, goal, bmr, tdee, target_calories, target_protein, target_carbs, target_fats) VALUES
(1, 30, 'male', 175, 75, 'moderate', 'maintenance', 1750, 2712, 2712, 203, 271, 90);

-- Insert Sample Ingredients
INSERT INTO ingredients (ingredient_name, category, calories_per_100g, protein_per_100g, carbs_per_100g, fats_per_100g, unit) VALUES
('Chicken Breast', 'Protein', 165, 31, 0, 3.6, 'g'),
('Brown Rice', 'Grains', 111, 2.6, 23, 0.9, 'g'),
('Spinach', 'Vegetables', 23, 2.9, 3.6, 0.4, 'g'),
('Greek Yogurt', 'Dairy', 59, 10, 3.6, 0.4, 'g'),
('Salmon', 'Protein', 208, 20, 0, 13, 'g'),
('Quinoa', 'Grains', 120, 4.4, 21, 1.9, 'g'),
('Broccoli', 'Vegetables', 34, 2.8, 7, 0.4, 'g'),
('Eggs', 'Protein', 155, 13, 1.1, 11, 'g'),
('Avocado', 'Fats', 160, 2, 8.5, 15, 'g'),
('Sweet Potato', 'Vegetables', 86, 1.6, 20, 0.1, 'g'),
('Almonds', 'Nuts', 579, 21, 22, 50, 'g'),
('Oats', 'Grains', 389, 17, 66, 7, 'g'),
('Banana', 'Fruits', 89, 1.1, 23, 0.3, 'g'),
('Tomato', 'Vegetables', 18, 0.9, 3.9, 0.2, 'g'),
('Olive Oil', 'Fats', 884, 0, 0, 100, 'ml');
