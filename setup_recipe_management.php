<?php
/**
 * Recipe Management System Setup Script
 * Run this file once to set up the recipe management tables
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Recipe Management Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        h1 { color: #10b981; }
    </style>
</head>
<body>
    <h1>Recipe Management System Setup</h1>";

$conn = getDBConnection();

// SQL statements to execute
$sql_statements = [
    // Add approval fields to recipes table
    "ALTER TABLE recipes 
     ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER is_public",
    
    "ALTER TABLE recipes 
     ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER approval_status",
    
    "ALTER TABLE recipes 
     ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by",
    
    "ALTER TABLE recipes 
     ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approved_at",
    
    // Create recipe_images table
    "CREATE TABLE IF NOT EXISTS recipe_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        INDEX idx_recipe_id (recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Create recipe_steps table
    "CREATE TABLE IF NOT EXISTS recipe_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        step_number INT NOT NULL,
        step_description TEXT NOT NULL,
        step_image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        INDEX idx_recipe_id (recipe_id),
        INDEX idx_step_number (step_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Create recipe_ratings table
    "CREATE TABLE IF NOT EXISTS recipe_ratings (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Create recipe_favorites table
    "CREATE TABLE IF NOT EXISTS recipe_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_recipe (user_id, recipe_id),
        INDEX idx_user_id (user_id),
        INDEX idx_recipe_id (recipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

$success_count = 0;
$error_count = 0;

foreach ($sql_statements as $index => $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Statement " . ($index + 1) . " executed successfully</div>";
            $success_count++;
        } else {
            // Check if error is about column already existing
            if (strpos($conn->error, 'Duplicate column name') !== false) {
                echo "<div class='info'>ℹ Statement " . ($index + 1) . " skipped (already exists)</div>";
                $success_count++;
            } else {
                echo "<div class='error'>✗ Error in statement " . ($index + 1) . ": " . $conn->error . "</div>";
                $error_count++;
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Exception in statement " . ($index + 1) . ": " . $e->getMessage() . "</div>";
        $error_count++;
    }
}

// Add indexes if they don't exist
$index_statements = [
    "ALTER TABLE recipes ADD INDEX IF NOT EXISTS idx_approval_status (approval_status)",
    "ALTER TABLE recipes ADD CONSTRAINT IF NOT EXISTS fk_approved_by 
     FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL"
];

foreach ($index_statements as $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Ignore errors for indexes (they might already exist)
    }
}

echo "<hr>";
echo "<h2>Setup Summary</h2>";
echo "<div class='success'>✓ Successful operations: $success_count</div>";
if ($error_count > 0) {
    echo "<div class='error'>✗ Failed operations: $error_count</div>";
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>
    <li>Navigate to <a href='recipes.html'>recipes.html</a> to start managing recipes</li>
    <li>Login as admin to approve recipes in the <a href='admin.html'>admin panel</a></li>
    <li>Check the <a href='RECIPE_MANAGEMENT_README.md'>documentation</a> for detailed usage</li>
</ol>";

echo "<div class='info'>
    <strong>Note:</strong> You can safely delete this setup file (setup_recipe_management.php) after successful setup.
</div>";

closeDBConnection($conn);

echo "</body></html>";
?>
