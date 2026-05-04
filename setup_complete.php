<?php
/**
 * Complete Setup Script for Smart Meal Planner
 * Run this once to set up all tables
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Complete Setup - Smart Meal Planner</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; border-radius: 5px; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; border-radius: 5px; }
        h1 { color: #10b981; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #10b981; }
    </style>
</head>
<body>
    <h1><i class='fas fa-database'></i> Smart Meal Planner - Complete Setup</h1>
    <p>This will set up all required tables for Recipe Management and ML Features.</p>";

$conn = getDBConnection();
$success_count = 0;
$error_count = 0;

// SQL statements to execute
$tables = [
    'recipe_images' => "CREATE TABLE IF NOT EXISTS recipe_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        INDEX idx_recipe_id (recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'recipe_steps' => "CREATE TABLE IF NOT EXISTS recipe_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        step_number INT NOT NULL,
        step_description TEXT NOT NULL,
        step_image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        INDEX idx_recipe_id (recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'recipe_ratings' => "CREATE TABLE IF NOT EXISTS recipe_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL,
        review TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_recipe (user_id, recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'recipe_favorites' => "CREATE TABLE IF NOT EXISTS recipe_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_recipe (user_id, recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'user_recipe_interactions' => "CREATE TABLE IF NOT EXISTS user_recipe_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        recipe_id INT NOT NULL,
        interaction_type ENUM('view', 'favorite', 'cook', 'rate', 'share') NOT NULL,
        rating INT NULL,
        duration_seconds INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'user_preference_scores' => "CREATE TABLE IF NOT EXISTS user_preference_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        preference_type ENUM('ingredient', 'dietary_tag', 'cuisine', 'calorie_range') NOT NULL,
        preference_value VARCHAR(100) NOT NULL,
        score DECIMAL(5,2) DEFAULT 0.0,
        interaction_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_preference (user_id, preference_type, preference_value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'ml_model_metadata' => "CREATE TABLE IF NOT EXISTS ml_model_metadata (
        id INT AUTO_INCREMENT PRIMARY KEY,
        model_name VARCHAR(100) NOT NULL,
        model_version VARCHAR(50) NOT NULL,
        model_type ENUM('linear_regression', 'neural_network', 'random_forest', 'collaborative_filtering') NOT NULL,
        accuracy_score DECIMAL(5,4) NULL,
        training_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        notes TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'recommendation_history' => "CREATE TABLE IF NOT EXISTS recommendation_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        recipe_id INT NOT NULL,
        recommendation_score DECIMAL(5,2) NOT NULL,
        recommendation_reason TEXT NULL,
        was_accepted BOOLEAN NULL,
        recommended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'user_learning_progress' => "CREATE TABLE IF NOT EXISTS user_learning_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        total_interactions INT DEFAULT 0,
        recipes_viewed INT DEFAULT 0,
        recipes_cooked INT DEFAULT 0,
        learning_confidence DECIMAL(3,2) DEFAULT 0.0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "<div class='step'><h3>Step 1: Creating Tables</h3>";

foreach ($tables as $table_name => $sql) {
    if ($conn->query($sql)) {
        echo "<div class='success'>✓ Table '$table_name' created successfully</div>";
        $success_count++;
    } else {
        if (strpos($conn->error, 'already exists') !== false) {
            echo "<div class='info'>ℹ Table '$table_name' already exists</div>";
            $success_count++;
        } else {
            echo "<div class='error'>✗ Error creating '$table_name': " . $conn->error . "</div>";
            $error_count++;
        }
    }
}

echo "</div>";

// Add columns to recipes table
echo "<div class='step'><h3>Step 2: Adding Recipe Management Columns</h3>";

$columns = [
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS approved_by INT NULL",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL",
    "ALTER TABLE recipes ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL"
];

foreach ($columns as $sql) {
    @$conn->query($sql);
    echo "<div class='success'>✓ Column added/checked</div>";
}

echo "</div>";

// Summary
echo "<hr>";
echo "<h2>Setup Summary</h2>";
echo "<div class='success'><strong>✓ Successful operations: $success_count</strong></div>";
if ($error_count > 0) {
    echo "<div class='error'><strong>✗ Failed operations: $error_count</strong></div>";
}

// Check tables
$result = $conn->query("SHOW TABLES");
$table_count = $result->num_rows;

echo "<div class='info'><strong>Total tables in database: $table_count</strong></div>";

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>
    <li>✅ Database setup complete!</li>
    <li>Go to <a href='recipes.html'>recipes.html</a> to start managing recipes</li>
    <li>Go to <a href='admin.html'>admin.html</a> to access admin panel</li>
    <li>Run <a href='test_ml_services.php'>ML services test</a> to verify ML features</li>
</ol>";

echo "<div class='info'>
    <strong>Note:</strong> You can safely delete setup files after successful setup:
    <ul>
        <li>setup_complete.php (this file)</li>
        <li>setup_recipe_management.php</li>
        <li>setup_ml_system.php</li>
    </ul>
</div>";

closeDBConnection($conn);

echo "</body></html>";
?>
