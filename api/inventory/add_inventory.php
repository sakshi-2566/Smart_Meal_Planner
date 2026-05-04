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

$quantity = floatval($input['quantity']);
$unit = $input['unit'];
$expiry_date = $input['expiry_date'] ?? null;

$conn = getDBConnection();

// Handle custom ingredient
if (isset($input['custom_ingredient_name']) && !empty($input['custom_ingredient_name'])) {
    $custom_name = trim($input['custom_ingredient_name']);
    $category = $input['category'] ?? 'Other';
    
    // Check if custom ingredient already exists in ingredients table
    $check_sql = "SELECT id FROM ingredients WHERE LOWER(ingredient_name) = LOWER(?)";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $custom_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Use existing ingredient
        $row = $result->fetch_assoc();
        $ingredient_id = $row['id'];
    } else {
        // Create new ingredient with correct column names
        $insert_ing = "INSERT INTO ingredients (ingredient_name, category, calories_per_100g, protein_per_100g, carbs_per_100g, fats_per_100g, unit) 
                       VALUES (?, ?, 0, 0, 0, 0, 'g')";
        $stmt = $conn->prepare($insert_ing);
        $stmt->bind_param("ss", $custom_name, $category);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to create custom ingredient: ' . $stmt->error]);
            closeDBConnection($conn);
            exit;
        }
        $ingredient_id = $conn->insert_id;
    }
} else {
    $ingredient_id = intval($input['ingredient_id']);
}

// Check if ingredient already exists in inventory
$check_sql = "SELECT id, quantity FROM user_inventory WHERE user_id = ? AND ingredient_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $user_id, $ingredient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing quantity
    $existing = $result->fetch_assoc();
    $new_quantity = $existing['quantity'] + $quantity;
    
    $sql = "UPDATE user_inventory SET quantity = ?, expiry_date = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsi", $new_quantity, $expiry_date, $existing['id']);
    $message = 'Ingredient quantity updated';
} else {
    // Insert new
    $sql = "INSERT INTO user_inventory (user_id, ingredient_id, quantity, unit, expiry_date) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iidss", $user_id, $ingredient_id, $quantity, $unit, $expiry_date);
    $message = 'Ingredient added to inventory';
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add ingredient: ' . $stmt->error]);
}

closeDBConnection($conn);
?>
