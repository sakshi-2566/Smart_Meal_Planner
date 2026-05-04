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
$delivery_address = $input['delivery_address'] ?? '';
$payment_method = $input['payment_method'] ?? 'UPI';
$payment_status = $input['payment_status'] ?? 'pending';

$conn = getDBConnection();

// Get cart items
$cart_sql = "SELECT * FROM shopping_cart WHERE user_id = ?";
$stmt = $conn->prepare($cart_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    closeDBConnection($conn);
    exit;
}

// Calculate total
$total_amount = array_sum(array_column($cart_items, 'price'));

// Generate order number
$order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

// Create order with proper payment status
$order_status = ($payment_status === 'paid') ? 'confirmed' : 'pending';

// Check if payment_method column exists
$check_column = "SHOW COLUMNS FROM orders LIKE 'payment_method'";
$result = $conn->query($check_column);
$has_payment_method = $result->num_rows > 0;

if ($has_payment_method) {
    $order_sql = "INSERT INTO orders (user_id, order_number, total_amount, delivery_address, status, payment_status, payment_method)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("isdssss", $user_id, $order_number, $total_amount, $delivery_address, $order_status, $payment_status, $payment_method);
} else {
    $order_sql = "INSERT INTO orders (user_id, order_number, total_amount, delivery_address, status, payment_status)
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("isdsss", $user_id, $order_number, $total_amount, $delivery_address, $order_status, $payment_status);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create order']);
    closeDBConnection($conn);
    exit;
}

$order_id = $conn->insert_id;

// Add order items
$item_sql = "INSERT INTO order_items (order_id, ingredient_id, quantity, unit, price, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)";
$item_stmt = $conn->prepare($item_sql);

foreach ($cart_items as $item) {
    $subtotal = $item['price'];
    $item_stmt->bind_param(
        "iidsdd",
        $order_id,
        $item['ingredient_id'],
        $item['quantity'],
        $item['unit'],
        $item['price'],
        $subtotal
    );
    $item_stmt->execute();
}

// Clear cart
$clear_sql = "DELETE FROM shopping_cart WHERE user_id = ?";
$stmt = $conn->prepare($clear_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Order placed successfully!',
    'order' => [
        'order_id' => $order_id,
        'order_number' => $order_number,
        'total_amount' => round($total_amount, 2),
        'status' => $order_status,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method,
        'items_count' => count($cart_items)
    ]
]);

closeDBConnection($conn);
?>
