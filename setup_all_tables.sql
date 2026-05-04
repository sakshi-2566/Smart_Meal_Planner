-- Complete Database Setup for Smart Meal Planner
-- Run this file to set up all tables

-- Make sure we're using the correct database
USE smart_meal_planner;

-- ============================================
-- RECIPE MANAGEMENT TABLES
-- ============================================

-- Add approval fields to recipes table (if not exists)
ALTER TABLE recipes 
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER is_public;

ALTER TABLE recipes 
ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER approval_status;

ALTER TABLE recipes 
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by;

ALTER TABLE recipes 
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approved_at;

-- Create recipe_images table
CREATE TABLE IF NOT EXISTS recipe_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_id (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create recipe_steps table
CREATE TABLE IF NOT EXISTS recipe_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    step_number INT NOT NULL,
    step_description TEXT NOT NULL,
    step_image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_id (recipe_id),
    INDEX idx_step_number (step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create recipe_ratings table
CREATE TABLE IF NOT EXISTS recipe_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_recipe (user_id, recipe_id),
    INDEX idx_recipe_id (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create recipe_favorites table
CREATE TABLE IF NOT EXISTS recipe_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_recipe (user_id, recipe_id),
    INDEX idx_user_id (user_id),
    INDEX idx_recipe_id (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ML FEATURES TABLES
-- ============================================

-- User Recipe Interactions Table
CREATE TABLE IF NOT EXISTS user_recipe_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    interaction_type ENUM('view', 'favorite', 'cook', 'rate', 'share') NOT NULL,
    rating INT NULL,
    duration_seconds INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_recipe_id (recipe_id),
    INDEX idx_interaction_type (interaction_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Preference Scores Table
CREATE TABLE IF NOT EXISTS user_preference_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_type ENUM('ingredient', 'dietary_tag', 'cuisine', 'calorie_range') NOT NULL,
    preference_value VARCHAR(100) NOT NULL,
    score DECIMAL(5,2) DEFAULT 0.0,
    interaction_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_type, preference_value),
    INDEX idx_user_id (user_id),
    INDEX idx_preference_type (preference_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ML Model Metadata Table
CREATE TABLE IF NOT EXISTS ml_model_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    model_type ENUM('linear_regression', 'neural_network', 'random_forest', 'collaborative_filtering') NOT NULL,
    accuracy_score DECIMAL(5,4) NULL,
    training_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    model_path VARCHAR(255) NULL,
    hyperparameters JSON NULL,
    training_samples INT NULL,
    notes TEXT NULL,
    INDEX idx_model_name (model_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recommendation History Table
CREATE TABLE IF NOT EXISTS recommendation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    recommendation_score DECIMAL(5,2) NOT NULL,
    recommendation_reason TEXT NULL,
    was_accepted BOOLEAN NULL,
    was_cooked BOOLEAN DEFAULT FALSE,
    user_rating INT NULL,
    recommended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_recommended_at (recommended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Learning Progress Table
CREATE TABLE IF NOT EXISTS user_learning_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_interactions INT DEFAULT 0,
    recipes_viewed INT DEFAULT 0,
    recipes_cooked INT DEFAULT 0,
    recipes_rated INT DEFAULT 0,
    avg_rating_given DECIMAL(3,2) NULL,
    favorite_cuisine VARCHAR(100) NULL,
    favorite_dietary_tag VARCHAR(100) NULL,
    avg_preferred_calories INT NULL,
    learning_confidence DECIMAL(3,2) DEFAULT 0.0,
    last_interaction TIMESTAMP NULL,
    profile_completeness DECIMAL(3,2) DEFAULT 0.0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_learning_confidence (learning_confidence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial ML model metadata
INSERT IGNORE INTO ml_model_metadata (model_name, model_version, model_type, is_active, notes) VALUES
('nutrition_predictor', 'v1.0', 'linear_regression', TRUE, 'Initial linear regression model for nutrition prediction'),
('meal_recommender', 'v1.0', 'collaborative_filtering', TRUE, 'Collaborative filtering for meal recommendations'),
('adaptive_recommender', 'v1.0', 'neural_network', TRUE, 'Adaptive neural network that learns from user behavior');

-- Summary
SELECT 'All tables created successfully!' as Status;
SELECT 
    COUNT(*) as 'Total Tables',
    SUM(CASE WHEN TABLE_NAME LIKE 'recipe_%' THEN 1 ELSE 0 END) as 'Recipe Tables',
    SUM(CASE WHEN TABLE_NAME LIKE 'user_%' THEN 1 ELSE 0 END) as 'User Tables',
    SUM(CASE WHEN TABLE_NAME LIKE 'ml_%' OR TABLE_NAME LIKE '%_recommendation%' THEN 1 ELSE 0 END) as 'ML Tables'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'smart_meal_planner';
