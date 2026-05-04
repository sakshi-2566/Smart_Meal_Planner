<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['recipe_id']) || !isset($input['interaction_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$recipe_id = intval($input['recipe_id']);
$interaction_type = $input['interaction_type'];  // view, favorite, cook, rate
$rating = isset($input['rating']) ? intval($input['rating']) : null;

$conn = getDBConnection();

// Record interaction in database
$sql = "INSERT INTO user_recipe_interactions (user_id, recipe_id, interaction_type, rating, created_at) 
        VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisi", $user_id, $recipe_id, $interaction_type, $rating);

if ($stmt->execute()) {
    // Update ML model with new interaction (async)
    updateMLModel($user_id, $recipe_id, $interaction_type, $rating);
    
    echo json_encode([
        'success' => true,
        'message' => 'Interaction recorded',
        'interaction_id' => $conn->insert_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to record interaction'
    ]);
}

closeDBConnection($conn);

function updateMLModel($user_id, $recipe_id, $interaction_type, $rating) {
    // This would call the Python adaptive recommender to update preferences
    // For now, we'll just log it
    error_log("ML Update: User $user_id interacted with recipe $recipe_id ($interaction_type)");
    
    // In production, you would call:
    // $command = "python ../ml_service/adaptive_recommender.py record_interaction $user_id $recipe_id $interaction_type $rating &";
    // exec($command);
}
?>
