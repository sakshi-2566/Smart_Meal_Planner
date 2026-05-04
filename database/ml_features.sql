-- ML Features and User Interaction Tracking
-- Supports adaptive learning and personalized recommendations

USE smart_meal_planner;

-- User Recipe Interactions Table
-- Tracks all user interactions with recipes for ML learning
CREATE TABLE IF NOT EXISTS user_recipe_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    interaction_type ENUM('view', 'favorite', 'cook', 'rate', 'share') NOT NULL,
    rating INT NULL CHECK (rating >= 1 AND rating <= 5),
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
-- Stores learned preferences for ingredients, tags, etc.
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
-- Tracks ML model versions and performance
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
-- Tracks what was recommended to users and their response
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

-- Meal Plan Performance Table
-- Tracks how well meal plans are followed
CREATE TABLE IF NOT EXISTS meal_plan_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id INT NOT NULL,
    user_id INT NOT NULL,
    planned_date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    recipe_id INT NULL,
    was_followed BOOLEAN DEFAULT FALSE,
    actual_recipe_id INT NULL,
    adherence_score DECIMAL(3,2) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL,
    FOREIGN KEY (actual_recipe_id) REFERENCES recipes(id) ON DELETE SET NULL,
    INDEX idx_meal_plan_id (meal_plan_id),
    INDEX idx_user_id (user_id),
    INDEX idx_planned_date (planned_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nutrition Prediction Cache Table
-- Caches ML predictions for faster response
CREATE TABLE IF NOT EXISTS nutrition_prediction_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_combination_hash VARCHAR(64) NOT NULL UNIQUE,
    predicted_calories DECIMAL(8,2) NOT NULL,
    predicted_protein DECIMAL(6,2) NOT NULL,
    predicted_carbs DECIMAL(6,2) NOT NULL,
    predicted_fats DECIMAL(6,2) NOT NULL,
    prediction_confidence DECIMAL(3,2) NULL,
    model_version VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hit_count INT DEFAULT 0,
    INDEX idx_hash (ingredient_combination_hash),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Learning Progress Table
-- Tracks how the system learns about each user
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
INSERT INTO ml_model_metadata (model_name, model_version, model_type, is_active, notes) VALUES
('nutrition_predictor', 'v1.0', 'linear_regression', TRUE, 'Initial linear regression model for nutrition prediction'),
('meal_recommender', 'v1.0', 'collaborative_filtering', TRUE, 'Collaborative filtering for meal recommendations'),
('adaptive_recommender', 'v1.0', 'neural_network', TRUE, 'Adaptive neural network that learns from user behavior');

-- Create view for user recommendation insights
CREATE OR REPLACE VIEW user_recommendation_insights AS
SELECT 
    u.id as user_id,
    u.email,
    ulp.total_interactions,
    ulp.learning_confidence,
    COUNT(DISTINCT uri.recipe_id) as unique_recipes_interacted,
    COUNT(DISTINCT rf.recipe_id) as favorite_count,
    AVG(rr.rating) as avg_rating,
    ulp.favorite_cuisine,
    ulp.favorite_dietary_tag
FROM users u
LEFT JOIN user_learning_progress ulp ON u.id = ulp.user_id
LEFT JOIN user_recipe_interactions uri ON u.id = uri.user_id
LEFT JOIN recipe_favorites rf ON u.id = rf.user_id
LEFT JOIN recipe_ratings rr ON u.id = rr.user_id
GROUP BY u.id;

-- Create stored procedure to update user learning progress
DELIMITER //

CREATE PROCEDURE update_user_learning_progress(IN p_user_id INT)
BEGIN
    -- Calculate user statistics
    DECLARE v_total_interactions INT;
    DECLARE v_recipes_viewed INT;
    DECLARE v_recipes_cooked INT;
    DECLARE v_recipes_rated INT;
    DECLARE v_avg_rating DECIMAL(3,2);
    DECLARE v_confidence DECIMAL(3,2);
    
    -- Count interactions
    SELECT COUNT(*) INTO v_total_interactions
    FROM user_recipe_interactions
    WHERE user_id = p_user_id;
    
    SELECT COUNT(DISTINCT recipe_id) INTO v_recipes_viewed
    FROM user_recipe_interactions
    WHERE user_id = p_user_id AND interaction_type = 'view';
    
    SELECT COUNT(DISTINCT recipe_id) INTO v_recipes_cooked
    FROM user_recipe_interactions
    WHERE user_id = p_user_id AND interaction_type = 'cook';
    
    SELECT COUNT(*) INTO v_recipes_rated
    FROM recipe_ratings
    WHERE user_id = p_user_id;
    
    SELECT AVG(rating) INTO v_avg_rating
    FROM recipe_ratings
    WHERE user_id = p_user_id;
    
    -- Calculate learning confidence (0-1 scale)
    SET v_confidence = LEAST(1.0, (v_total_interactions / 50.0));
    
    -- Insert or update progress
    INSERT INTO user_learning_progress 
        (user_id, total_interactions, recipes_viewed, recipes_cooked, recipes_rated, 
         avg_rating_given, learning_confidence, last_interaction)
    VALUES 
        (p_user_id, v_total_interactions, v_recipes_viewed, v_recipes_cooked, v_recipes_rated,
         v_avg_rating, v_confidence, NOW())
    ON DUPLICATE KEY UPDATE
        total_interactions = v_total_interactions,
        recipes_viewed = v_recipes_viewed,
        recipes_cooked = v_recipes_cooked,
        recipes_rated = v_recipes_rated,
        avg_rating_given = v_avg_rating,
        learning_confidence = v_confidence,
        last_interaction = NOW();
END //

DELIMITER ;

-- Summary
SELECT 'ML features and tracking tables created successfully!' as Status;
SELECT COUNT(*) as 'ML Tables Created' FROM information_schema.tables 
WHERE table_schema = 'smart_meal_planner' 
AND table_name IN ('user_recipe_interactions', 'user_preference_scores', 'ml_model_metadata', 
                   'recommendation_history', 'meal_plan_performance', 'nutrition_prediction_cache',
                   'user_learning_progress');
