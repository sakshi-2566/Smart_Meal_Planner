<?php
/**
 * Setup Script: Add Cuisine Type and Spice Level to Recipes
 * This adds cuisine_type and spice_level columns to the recipes table
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Cuisine & Spice Level Columns</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🌶️ Add Cuisine Type & Spice Level Columns</h1>";

try {
    $conn = getDBConnection();
    
    echo "<div class='info'>Connected to database successfully!</div>";
    
    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM recipes LIKE 'cuisine_type'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>✓ Columns already exist. Skipping migration.</div>";
    } else {
        echo "<div class='info'>Adding cuisine_type and spice_level columns...</div>";
        
        // Add cuisine_type column
        $sql1 = "ALTER TABLE recipes 
                 ADD COLUMN cuisine_type VARCHAR(50) DEFAULT NULL AFTER dietary_tags";
        
        if ($conn->query($sql1)) {
            echo "<div class='success'>✓ Added cuisine_type column</div>";
        } else {
            throw new Exception("Error adding cuisine_type: " . $conn->error);
        }
        
        // Add spice_level column
        $sql2 = "ALTER TABLE recipes 
                 ADD COLUMN spice_level ENUM('mild', 'medium', 'spicy') DEFAULT 'mild' AFTER cuisine_type";
        
        if ($conn->query($sql2)) {
            echo "<div class='success'>✓ Added spice_level column</div>";
        } else {
            throw new Exception("Error adding spice_level: " . $conn->error);
        }
        
        // Add indexes
        $sql3 = "ALTER TABLE recipes ADD INDEX idx_cuisine_type (cuisine_type)";
        $conn->query($sql3); // Ignore if index already exists
        
        $sql4 = "ALTER TABLE recipes ADD INDEX idx_spice_level (spice_level)";
        $conn->query($sql4); // Ignore if index already exists
        
        echo "<div class='success'>✓ Added indexes for better performance</div>";
        
        // Update existing recipes with default values
        $sql5 = "UPDATE recipes 
                 SET cuisine_type = 'Continental', 
                     spice_level = 'mild' 
                 WHERE cuisine_type IS NULL";
        
        if ($conn->query($sql5)) {
            $updated = $conn->affected_rows;
            echo "<div class='success'>✓ Updated $updated existing recipes with default values</div>";
        }
    }
    
    echo "<div class='success'><strong>✓ Setup Complete!</strong></div>";
    echo "<div class='info'>
            <strong>Next Steps:</strong><br>
            1. You can now edit recipes to set their cuisine type and spice level<br>
            2. Go to Admin Panel → Recipes to update existing recipes<br>
            3. New recipes can specify cuisine (Indian, Chinese, Italian, etc.) and spice level (mild, medium, spicy)<br>
            4. The meal plan generator will now filter by these preferences!
          </div>";
    
    echo "<p><a href='dashboard.html' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "<div class='error'><strong>Error:</strong> " . $e->getMessage() . "</div>";
    echo "<div class='info'>Please check your database connection and try again.</div>";
}

echo "</body></html>";
?>
