<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

$conn = getDBConnection();

// Verify order belongs to user
$check_sql = "SELECT id FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    closeDBConnection($conn);
    exit;
}

// Get order items
$sql = "SELECT oi.*, i.ingredient_name, i.category
        FROM order_items oi
        JOIN ingredients i ON oi.ingredient_id = i.id
        WHERE oi.order_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    'success' => true,
    'items' => $items
]);

closeDBConnection($conn);
?>
