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

// Get user learning progress
$sql = "SELECT * FROM user_learning_progress WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();

// Get user preferences
$pref_sql = "SELECT preference_type, preference_value, score, interaction_count 
             FROM user_preference_scores 
             WHERE user_id = ? 
             ORDER BY score DESC 
             LIMIT 10";
$pref_stmt = $conn->prepare($pref_sql);
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$preferences = $pref_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent interactions
$int_sql = "SELECT uri.*, r.recipe_name 
            FROM user_recipe_interactions uri
            JOIN recipes r ON uri.recipe_id = r.id
            WHERE uri.user_id = ?
            ORDER BY uri.created_at DESC
            LIMIT 20";
$int_stmt = $conn->prepare($int_sql);
$int_stmt->bind_param("i", $user_id);
$int_stmt->execute();
$interactions = $int_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate learning confidence
$confidence = 0;
if ($progress) {
    $total_interactions = $progress['total_interactions'];
    $confidence = min(1.0, $total_interactions / 50); // Max confidence at 50 interactions
}

echo json_encode([
    'success' => true,
    'progress' => $progress ?: [
        'total_interactions' => 0,
        'recipes_viewed' => 0,
        'recipes_cooked' => 0,
        'recipes_rated' => 0,
        'learning_confidence' => 0
    ],
    'preferences' => $preferences,
    'recent_interactions' => $interactions,
    'learning_confidence' => round($confidence, 2),
    'recommendation_quality' => $confidence > 0.5 ? 'High' : ($confidence > 0.2 ? 'Medium' : 'Low')
]);

closeDBConnection($conn);
?>
