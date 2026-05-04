<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->query("
    SELECT id, first_name, last_name, email, dietary_preference, role, is_active, created_at, last_login
    FROM users
    WHERE role != 'admin' OR role IS NULL
    ORDER BY created_at DESC
");

$users = [];
while ($row = $stmt->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode([
    'success' => true,
    'users' => $users
]);

closeDBConnection($conn);
?>
