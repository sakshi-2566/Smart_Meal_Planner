<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$sql = "SELECT sc.*, i.ingredient_name, i.category, i.price_per_unit
        FROM shopping_cart sc
        JOIN ingredients i ON sc.ingredient_id = i.id
        WHERE sc.user_id = ? 
        AND i.ingredient_name NOT LIKE '%main ingredient%'
        AND i.ingredient_name NOT LIKE '%mix vegetable%'
        AND i.ingredient_name NOT LIKE '%mixed vegetable%'
        AND i.ingredient_name NOT LIKE '%generic%'
        AND i.ingredient_name NOT IN ('main ingredient', 'mix vegetable', 'mixed vegetables', 'vegetables', 'spices', 'seasoning')
        ORDER BY sc.added_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_price = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_price += $row['price'];
}

echo json_encode([
    'success' => true,
    'cart_items' => $cart_items,
    'total_items' => count($cart_items),
    'total_price' => round($total_price, 2)
]);

closeDBConnection($conn);
?>
