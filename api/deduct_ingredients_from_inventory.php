<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'utils/unit_converter.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$recipe_id = $input['recipe_id'] ?? 0;

if (!$recipe_id) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID required']);
    exit;
}

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
    
    error_log("Deducting for Recipe $recipe_id. Found " . count($ingredients) . " ingredients.");
    
    $deducted_items = [];
    $insufficient_items = [];
    
    foreach ($ingredients as $ingredient) {
        $ing_id = $ingredient['ingredient_id'];
        $required_qty = $ingredient['quantity'];
        $ing_name = $ingredient['ingredient_name'];
        
        error_log("Checking ingredient: $ing_name (ID: $ing_id) Qty: $required_qty " . $ingredient['unit']);

        // Check if user has this ingredient in inventory
        $check_sql = "SELECT id, quantity, unit FROM user_inventory 
                      WHERE user_id = ? AND ingredient_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $ing_id);
        $check_stmt->execute();
        $inventory_item = $check_stmt->get_result()->fetch_assoc();
        
        if ($inventory_item) {
            error_log("Found in inventory: " . $inventory_item['quantity'] . " " . $inventory_item['unit']);
            $available_qty = $inventory_item['quantity'];
            $available_unit = $inventory_item['unit'] ?? 'g'; // Fallback if unit missing
            
            // Check if available quantity (with units) is enough
            if (hasEnoughInventory($available_qty, $available_unit, $required_qty, $ingredient['unit'])) {
                // Calculate remaining quantity and unit
                $remaining = calculateRemaining($available_qty, $available_unit, $required_qty, $ingredient['unit']);
                $new_qty = $remaining['quantity'];
                $new_unit = $remaining['unit'];
                
                if ($new_qty > 0) {
                    // Update quantity and unit (in case it changed during normalization)
                    $update_sql = "UPDATE user_inventory 
                                   SET quantity = ?, unit = ?
                                   WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("dsi", $new_qty, $new_unit, $inventory_item['id']);
                    if (!$update_stmt->execute()) {
                        error_log("Failed to update inventory item " . $inventory_item['id'] . ": " . $conn->error);
                        continue;
                    }
                } else {
                    // Remove from inventory if quantity reaches 0
                    $delete_sql = "DELETE FROM user_inventory WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $inventory_item['id']);
                    if (!$delete_stmt->execute()) {
                        error_log("Failed to delete inventory item " . $inventory_item['id'] . ": " . $conn->error);
                        continue;
                    }
                }
                
                error_log("Deducted $required_qty " . $ingredient['unit'] . " of $ing_name. New qty: $new_qty $new_unit");
                
                $deducted_items[] = [
                    'ingredient_name' => $ingredient['ingredient_name'],
                    'quantity_deducted' => $required_qty,
                    'remaining' => $new_qty
                ];
            } else {
                // Not enough quantity
                $insufficient_items[] = [
                    'ingredient_name' => $ingredient['ingredient_name'],
                    'required' => $required_qty,
                    'available' => $available_qty
                ];
            }
        } else {
            // Ingredient not in inventory
            $insufficient_items[] = [
                'ingredient_name' => $ingredient['ingredient_name'],
                'required' => $required_qty,
                'available' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ingredients processed',
        'deducted_items' => $deducted_items,
        'insufficient_items' => $insufficient_items
    ]);
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
