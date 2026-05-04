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
$cart_id = intval($input['cart_id']);

$conn = getDBConnection();

$sql = "DELETE FROM shopping_cart WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $cart_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to remove item'
    ]);
}

closeDBConnection($conn);
?>
