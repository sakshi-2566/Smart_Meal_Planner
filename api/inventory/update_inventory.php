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
$quantity = floatval($input['quantity']);
$unit = $input['unit'];
$expiry_date = $input['expiry_date'] ?? null;

$conn = getDBConnection();

// Verify ownership
$check_sql = "SELECT id FROM user_inventory WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $inventory_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Inventory item not found']);
    closeDBConnection($conn);
    exit;
}

// Update
$sql = "UPDATE user_inventory SET quantity = ?, unit = ?, expiry_date = ? WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("dssii", $quantity, $unit, $expiry_date, $inventory_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Inventory updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update inventory']);
}

closeDBConnection($conn);
?>
