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

if (!isset($input['recipe_id']) || !isset($input['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID and rating required']);
    exit;
}

$recipe_id = intval($input['recipe_id']);
$rating = intval($input['rating']);
$review = isset($input['review']) ? $conn->real_escape_string($input['review']) : null;

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

// Insert or update rating
$sql = "INSERT INTO recipe_ratings (recipe_id, user_id, rating, review) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = ?, review = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiisis", $recipe_id, $user_id, $rating, $review, $rating, $review);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error submitting rating']);
}

closeDBConnection($conn);
?>
