<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

$dietaryPreference = $input['dietaryPreference'] ?? 'none';

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE users SET dietary_preference = ? WHERE id = ?");
$stmt->bind_param("si", $dietaryPreference, $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Preferences updated successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}

$stmt->close();
closeDBConnection($conn);
?>
