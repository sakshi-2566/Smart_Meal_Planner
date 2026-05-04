<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;
$payment_method = $input['payment_method'] ?? 'UPI';
$payment_status = $input['payment_status'] ?? 'paid';

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verify order belongs to user
    $stmt = $conn->prepare("SELECT id, payment_status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        closeDBConnection($conn);
        exit;
    }
    
    if ($order['payment_status'] === 'paid') {
        echo json_encode(['success' => false, 'message' => 'Order already paid']);
        closeDBConnection($conn);
        exit;
    }
    
    // Update payment status and mark as delivered
    $delivery_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = ?, 
            payment_method = ?,
            status = 'delivered',
            delivery_date = ?
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param("sssii", $payment_status, $payment_method, $delivery_date, $order_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment successful! Your order has been delivered.',
            'payment_method' => $payment_method,
            'status' => 'delivered'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update payment'
        ]);
    }
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating payment: ' . $e->getMessage()
    ]);
}
?>
