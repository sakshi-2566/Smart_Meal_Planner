<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user data
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.dietary_preference, u.role, u.created_at,
           p.age, p.gender, p.height, p.weight, p.activity_level, p.goal,
           p.bmr, p.tdee, p.target_calories, p.target_protein, p.target_carbs, p.target_fats
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$stmt->close();
closeDBConnection($conn);
?>
