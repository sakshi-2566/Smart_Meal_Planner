<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDBConnection();

// Fetch all orders with user names and item counts
$sql = "SELECT o.*, u.first_name, u.last_name, COUNT(oi.id) as items_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.order_date DESC";

$result = $conn->query($sql);

$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'orders' => $orders
]);

closeDBConnection($conn);
?>
