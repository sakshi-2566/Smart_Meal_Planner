<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? 0;
$status = $input['status'] ?? '';

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
    exit;
}

$conn = getDBConnection();

// Debug logging
error_log("Updating order $order_id to status: $status");

// Get current status to prevent reversion
$checkSql = "SELECT status FROM orders WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $order_id);
$checkStmt->execute();
$currentStatus = $checkStmt->get_result()->fetch_assoc()['status'];

$hierarchy = [
    'pending' => 1,
    'processing' => 2,
    'shipped' => 3,
    'delivered' => 4,
    'cancelled' => 5
];

if (isset($hierarchy[$currentStatus]) && isset($hierarchy[$status])) {
    if ($hierarchy[$status] < $hierarchy[$currentStatus] && $status !== 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cannot revert order status to a previous state']);
        exit;
    }
}

// Prepare the update SQL
$sql = "UPDATE orders SET status = ? ";
if ($status === 'delivered') {
    $sql .= ", delivery_date = CURRENT_TIMESTAMP ";
}
$sql .= "WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $order_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
} else {
    error_log("Update failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update order status: ' . $conn->error]);
}

closeDBConnection($conn);
?>
