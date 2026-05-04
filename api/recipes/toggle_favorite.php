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

// Check if already favorited
$sql = "SELECT id FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $recipe_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Remove from favorites
    $sql = "DELETE FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $recipe_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Removed from favorites', 'is_favorited' => false]);
} else {
    // Add to favorites
    $sql = "INSERT INTO recipe_favorites (recipe_id, user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $recipe_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Added to favorites', 'is_favorited' => true]);
}

closeDBConnection($conn);
?>
