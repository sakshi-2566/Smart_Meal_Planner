<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "Step: Adding is_eaten column to meal_plan_items table\n";

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM meal_plan_items LIKE 'is_eaten'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE meal_plan_items ADD COLUMN is_eaten TINYINT(1) DEFAULT 0 AFTER servings";
    if ($conn->query($sql)) {
        echo "✓ Added is_eaten column successfully\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ is_eaten column already exists\n";
}

$conn->close();
?>
