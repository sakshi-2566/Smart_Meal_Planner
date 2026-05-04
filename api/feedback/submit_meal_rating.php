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

$recipe_id = intval($input['recipe_id']);
$rating = intval($input['rating']);
$feedback_text = isset($input['feedback']) ? $input['feedback'] : null;
$meal_type = isset($input['meal_type']) ? $input['meal_type'] : null;

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

$conn = getDBConnection();

// Insert or update rating
$sql = "INSERT INTO recipe_ratings (recipe_id, user_id, rating, review, created_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE rating = ?, review = ?, updated_at = NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiisis", $recipe_id, $user_id, $rating, $feedback_text, $rating, $feedback_text);

if ($stmt->execute()) {
    // Record interaction for ML learning
    $interaction_sql = "INSERT INTO user_recipe_interactions 
                       (user_id, recipe_id, interaction_type, rating, created_at) 
                       VALUES (?, ?, 'rate', ?, NOW())";
    $int_stmt = $conn->prepare($interaction_sql);
    $int_stmt->bind_param("iii", $user_id, $recipe_id, $rating);
    $int_stmt->execute();
    
    // Update user learning progress
    $progress_sql = "INSERT INTO user_learning_progress (user_id, total_interactions, recipes_rated, updated_at)
                    VALUES (?, 1, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                    total_interactions = total_interactions + 1,
                    recipes_rated = recipes_rated + 1,
                    updated_at = NOW()";
    $prog_stmt = $conn->prepare($progress_sql);
    $prog_stmt->bind_param("i", $user_id);
    $prog_stmt->execute();
    
    // Update user preference scores based on rating
    if ($rating >= 4) {
        // Positive feedback - increase preference for this recipe's tags
        $tag_sql = "SELECT dietary_tags FROM recipes WHERE id = ?";
        $tag_stmt = $conn->prepare($tag_sql);
        $tag_stmt->bind_param("i", $recipe_id);
        $tag_stmt->execute();
        $tag_result = $tag_stmt->get_result();
        
        if ($tag_row = $tag_result->fetch_assoc()) {
            $tags = explode(',', $tag_row['dietary_tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $pref_sql = "INSERT INTO user_preference_scores 
                                (user_id, preference_type, preference_value, score, interaction_count)
                                VALUES (?, 'dietary_tag', ?, 1.0, 1)
                                ON DUPLICATE KEY UPDATE 
                                score = score + 0.5,
                                interaction_count = interaction_count + 1";
                    $pref_stmt = $conn->prepare($pref_sql);
                    $pref_stmt->bind_param("is", $user_id, $tag);
                    $pref_stmt->execute();
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully',
        'learning_updated' => true
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
}

closeDBConnection($conn);
?>
