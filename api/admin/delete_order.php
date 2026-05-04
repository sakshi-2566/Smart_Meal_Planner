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

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$conn = getDBConnection();

// Start transaction to ensure both order and items are deleted
$conn->begin_transaction();

try {
    // Delete order items first (due to foreign key constraints if not ON DELETE CASCADE)
    // Note: setup_shopping_system.php shows order_items has ON DELETE CASCADE for order_id
    // But it's safer to be explicit or trust the schema. 
    // Specifically: FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    
    $sql = "DELETE FROM orders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to delete order: ' . $e->getMessage()]);
}

closeDBConnection($conn);
?>
