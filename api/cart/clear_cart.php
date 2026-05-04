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

$sql = "DELETE FROM shopping_cart WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear cart'
    ]);
}

closeDBConnection($conn);
?>
