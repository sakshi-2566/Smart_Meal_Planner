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

$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');

if (empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
$stmt->bind_param("ssi", $firstName, $lastName, $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Personal information updated successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}

$stmt->close();
closeDBConnection($conn);
?>
