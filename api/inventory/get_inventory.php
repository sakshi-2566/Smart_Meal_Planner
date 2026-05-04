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

$sql = "SELECT ui.*, i.ingredient_name, i.category, i.is_vegetarian, i.unit as default_unit
        FROM user_inventory ui
        JOIN ingredients i ON ui.ingredient_id = i.id
        WHERE ui.user_id = ?
        ORDER BY ui.added_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$inventory = [];
while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
}

echo json_encode([
    'success' => true,
    'inventory' => $inventory
]);

closeDBConnection($conn);
?>
