<?php
$_SESSION['user_id'] = 2; // Force user ID 2 for testing
require_once 'config/database.php';
require_once 'api/utils/unit_converter.php';

$user_id = 2;
$recipe_id = 80; // Dal Chawal with Broccoli Sabzi

echo "Testing deduction for User $user_id, Recipe $recipe_id\n";

try {
    $conn = getDBConnection();
    
    // Get recipe ingredients
    $sql = "SELECT ri.ingredient_id, ri.quantity, ri.unit, i.ingredient_name
            FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.id
            WHERE ri.recipe_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Found " . count($ingredients) . " ingredients.\n";
    
    foreach ($ingredients as $ingredient) {
        $ing_id = $ingredient['ingredient_id'];
        $required_qty = $ingredient['quantity'];
        $ing_name = $ingredient['ingredient_name'];
        echo "Check: $ing_name ($ing_id) Needs: $required_qty " . $ingredient['unit'] . "\n";

        $check_sql = "SELECT id, quantity, unit FROM user_inventory 
                      WHERE user_id = ? AND ingredient_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $ing_id);
        $check_stmt->execute();
        $inventory_item = $check_stmt->get_result()->fetch_assoc();
        
        if ($inventory_item) {
            echo "  In Inventory: " . $inventory_item['quantity'] . " " . $inventory_item['unit'] . "\n";
            if (hasEnoughInventory($inventory_item['quantity'], $inventory_item['unit'], $required_qty, $ingredient['unit'])) {
                $remaining = calculateRemaining($inventory_item['quantity'], $inventory_item['unit'], $required_qty, $ingredient['unit']);
                echo "  Result: SUCCESS. Remaining: " . $remaining['quantity'] . " " . $remaining['unit'] . "\n";
            } else {
                echo "  Result: INSUFFICIENT\n";
            }
        } else {
            echo "  Result: NOT IN INVENTORY\n";
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
