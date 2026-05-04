<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$ingredient_id = intval($input['ingredient_id']);
$quantity = floatval($input['quantity']);
$unit = $input['unit'];

$conn = getDBConnection();

// Get ingredient details and check if it's a generic ingredient
$price_sql = "SELECT ingredient_name, price_per_unit FROM ingredients WHERE id = ?";
$stmt = $conn->prepare($price_sql);
$stmt->bind_param("i", $ingredient_id);
$stmt->execute();
$result = $stmt->get_result();
$ingredient = $result->fetch_assoc();

if (!$ingredient) {
    echo json_encode(['success' => false, 'message' => 'Ingredient not found']);
    closeDBConnection($conn);
    exit;
}

// Filter out generic ingredients
$generic_ingredients = [
    'main ingredient',
    'mix vegetable', 
    'mixed vegetable',
    'mixed vegetables',
    'vegetables',
    'spices',
    'seasoning',
    'generic'
];

$ingredient_name_lower = strtolower(trim($ingredient['ingredient_name']));

// Check if ingredient name contains generic terms or is in the generic list
$is_generic = false;
foreach ($generic_ingredients as $generic) {
    if ($ingredient_name_lower === $generic || strpos($ingredient_name_lower, $generic) !== false) {
        $is_generic = true;
        break;
    }
}

if ($is_generic) {
    echo json_encode([
        'success' => false, 
        'message' => 'Generic ingredients cannot be added to cart. Please select specific ingredients.'
    ]);
    closeDBConnection($conn);
    exit;
}

$price = $ingredient['price_per_unit'] * $quantity;

// Add to cart (or update if exists)
$sql = "INSERT INTO shopping_cart (user_id, ingredient_id, quantity, unit, price)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        quantity = quantity + VALUES(quantity),
        price = price + VALUES(price)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iidsd", $user_id, $ingredient_id, $quantity, $unit, $price);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add item to cart'
    ]);
}

closeDBConnection($conn);
?>
