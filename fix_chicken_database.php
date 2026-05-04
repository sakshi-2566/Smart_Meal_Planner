<?php
// Fix chicken and other non-vegetarian ingredients in database
require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Fixing Chicken and Non-Vegetarian Ingredients ===\n\n";

// First, let's see what we have for chicken
echo "1. Current Chicken Ingredients:\n";
$check_sql = "SELECT id, ingredient_name, category, is_vegetarian FROM ingredients WHERE ingredient_name LIKE '%chicken%'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vegStatus = $row['is_vegetarian'] ? 'VEG' : 'NON-VEG';
        echo "   ID: {$row['id']}, Name: {$row['ingredient_name']}, Category: {$row['category']}, Status: $vegStatus\n";
    }
} else {
    echo "   No chicken ingredients found.\n";
}

// Update all chicken-related ingredients to non-vegetarian
echo "\n2. Updating Chicken to Non-Vegetarian:\n";
$update_sql = "UPDATE ingredients SET is_vegetarian = 0 WHERE ingredient_name LIKE '%chicken%'";
$result = $conn->query($update_sql);

if ($result) {
    echo "   ✅ Updated " . $conn->affected_rows . " chicken ingredients to NON-VEG.\n";
} else {
    echo "   ❌ Error updating chicken: " . $conn->error . "\n";
}

// Update other common non-vegetarian ingredients
echo "\n3. Updating Other Non-Vegetarian Ingredients:\n";
$nonVegIngredients = [
    '%beef%' => 'beef',
    '%pork%' => 'pork', 
    '%fish%' => 'fish',
    '%mutton%' => 'mutton',
    '%lamb%' => 'lamb',
    '%turkey%' => 'turkey',
    '%duck%' => 'duck',
    '%meat%' => 'meat',
    '%bacon%' => 'bacon',
    '%ham%' => 'ham',
    '%sausage%' => 'sausage',
    '%prawn%' => 'prawn',
    '%shrimp%' => 'shrimp',
    '%crab%' => 'crab',
    '%lobster%' => 'lobster',
    '%egg%' => 'egg',
    '%tuna%' => 'tuna',
    '%salmon%' => 'salmon',
    '%cod%' => 'cod'
];

$totalUpdated = 0;
foreach ($nonVegIngredients as $pattern => $name) {
    $update_sql = "UPDATE ingredients SET is_vegetarian = 0 WHERE ingredient_name LIKE '$pattern'";
    $result = $conn->query($update_sql);
    if ($result) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "   ✅ Updated $affected ingredients matching '$name'\n";
            $totalUpdated += $affected;
        }
    }
}

echo "\n   Total non-vegetarian ingredients updated: $totalUpdated\n";

// Show final status of chicken
echo "\n4. Final Chicken Status:\n";
$check_sql = "SELECT id, ingredient_name, category, is_vegetarian FROM ingredients WHERE ingredient_name LIKE '%chicken%'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vegStatus = $row['is_vegetarian'] ? 'VEG' : 'NON-VEG';
        echo "   ID: {$row['id']}, Name: {$row['ingredient_name']}, Category: {$row['category']}, Status: $vegStatus\n";
    }
}

// Also check if the database field exists
echo "\n5. Checking Database Schema:\n";
$schema_sql = "DESCRIBE ingredients";
$result = $conn->query($schema_sql);
$hasVegField = false;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'is_vegetarian') {
            $hasVegField = true;
            echo "   ✅ is_vegetarian field exists: Type = {$row['Type']}, Default = {$row['Default']}\n";
            break;
        }
    }
}

if (!$hasVegField) {
    echo "   ❌ is_vegetarian field does NOT exist! Adding it now...\n";
    $add_field_sql = "ALTER TABLE ingredients ADD COLUMN is_vegetarian BOOLEAN DEFAULT TRUE";
    if ($conn->query($add_field_sql)) {
        echo "   ✅ Added is_vegetarian field successfully!\n";
        
        // Re-run the updates
        echo "   🔄 Re-running non-vegetarian updates...\n";
        $conn->query("UPDATE ingredients SET is_vegetarian = 0 WHERE ingredient_name LIKE '%chicken%'");
        foreach ($nonVegIngredients as $pattern => $name) {
            $conn->query("UPDATE ingredients SET is_vegetarian = 0 WHERE ingredient_name LIKE '$pattern'");
        }
        echo "   ✅ Updates completed!\n";
    } else {
        echo "   ❌ Failed to add field: " . $conn->error . "\n";
    }
}

closeDBConnection($conn);
echo "\n🎉 Database fix completed!\n";
echo "\n📝 Next Steps:\n";
echo "1. Clear browser cache (Ctrl+F5)\n";
echo "2. Refresh inventory page\n";
echo "3. Chicken should now show NON-VEG badge\n";
?>