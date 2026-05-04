<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$recipe_id = intval($_GET['recipe_id']);

$conn = getDBConnection();

// Get recipe ingredients
$sql = "SELECT ri.*, i.ingredient_name 
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe_ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user inventory (exclude expired ingredients)
$sql = "SELECT ingredient_id, quantity, unit, expiry_date 
        FROM user_inventory 
        WHERE user_id = ? 
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inventory_result = $stmt->get_result();

$inventory = [];
while ($row = $inventory_result->fetch_assoc()) {
    $inventory[$row['ingredient_id']] = $row;
}

$available = [];
$missing = [];

foreach ($recipe_ingredients as $ing) {
    $ing_id = $ing['ingredient_id'];
    $required_qty = $ing['quantity'];
    
    if (isset($inventory[$ing_id])) {
        $available_qty = $inventory[$ing_id]['quantity'];
        
        if ($available_qty >= $required_qty) {
            $available[] = [
                'ingredient_name' => $ing['ingredient_name'],
                'required_quantity' => $required_qty,
                'available_quantity' => $available_qty,
                'unit' => $ing['unit']
            ];
        } else {
            $missing[] = [
                'ingredient_id' => $ing_id,
                'ingredient_name' => $ing['ingredient_name'],
                'required_quantity' => $required_qty - $available_qty,
                'unit' => $ing['unit']
            ];
        }
    } else {
        $missing[] = [
            'ingredient_id' => $ing_id,
            'ingredient_name' => $ing['ingredient_name'],
            'required_quantity' => $required_qty,
            'unit' => $ing['unit']
        ];
    }
}

echo json_encode([
    'success' => true,
    'available_ingredients' => $available,
    'missing_ingredients' => $missing,
    'can_cook' => empty($missing)
]);

closeDBConnection($conn);
?>
