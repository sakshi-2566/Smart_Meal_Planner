<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID required']);
    exit;
}

$recipe_id = intval($input['recipe_id']);

// Check ownership or admin
$sql = "SELECT user_id FROM recipes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Recipe not found']);
    exit;
}

$recipe = $result->fetch_assoc();
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($recipe['user_id'] != $user_id && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Delete recipe (cascade will handle related tables)
$sql = "DELETE FROM recipes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Recipe deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting recipe']);
}

closeDBConnection($conn);
?>
