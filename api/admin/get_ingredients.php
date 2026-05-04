<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

$conn = getDBConnection();

// Get all ingredients (no admin check needed for reading)
$sql = "SELECT id, ingredient_name, category, calories_per_100g, protein_per_100g, 
        carbs_per_100g, fats_per_100g, unit, price_per_unit
        FROM ingredients
        ORDER BY category, ingredient_name ASC";

$result = $conn->query($sql);

$ingredients = [];
while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

echo json_encode([
    'success' => true,
    'ingredients' => $ingredients
]);

closeDBConnection($conn);
?>
