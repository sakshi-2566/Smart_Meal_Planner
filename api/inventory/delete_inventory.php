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
$inventory_id = intval($input['inventory_id']);

$conn = getDBConnection();

$sql = "DELETE FROM user_inventory WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $inventory_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Item deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
}

closeDBConnection($conn);
?>
