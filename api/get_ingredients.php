<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Allow authenticated users to get ingredients list
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT id, ingredient_name, category, is_vegetarian,
        calories_per_100g, protein_per_100g, carbs_per_100g, fats_per_100g, unit
        FROM ingredients
        ORDER BY category, ingredient_name ASC";

$result = $conn->query($sql);

$ingredients = [];
while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

echo json_encode([
    'success' => true,
    'ingredients' => $ingredients,
    'total' => count($ingredients)
]);

closeDBConnection($conn);
?>
