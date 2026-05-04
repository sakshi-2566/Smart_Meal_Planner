<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user profile and preferences
$sql = "SELECT u.*, p.* FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();

// Get user's interaction history
$sql = "SELECT recipe_id, 'favorite' as type FROM recipe_favorites WHERE user_id = ?
        UNION ALL
        SELECT recipe_id, 'rate' as type FROM recipe_ratings WHERE user_id = ?
        UNION ALL
        SELECT recipe_id, 'cook' as type FROM nutrition_logs WHERE user_id = ? AND recipe_id IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$interactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available recipes (approved and public)
$sql = "SELECT r.*, 
        COALESCE(AVG(rt.rating), 0) as avg_rating,
        COUNT(DISTINCT rt.id) as rating_count,
        COUNT(DISTINCT rf.id) as favorite_count
        FROM recipes r
        LEFT JOIN recipe_ratings rt ON r.id = rt.recipe_id
        LEFT JOIN recipe_favorites rf ON r.id = rf.recipe_id
        WHERE r.approval_status = 'approved' AND r.is_public = 1
        GROUP BY r.id";
$result = $conn->query($sql);
$available_recipes = $result->fetch_all(MYSQLI_ASSOC);

// Calculate recommendation scores
$recommendations = [];
foreach ($available_recipes as $recipe) {
    $score = calculateRecommendationScore($user_profile, $recipe, $interactions);
    
    if ($score > 50) {  // Only include recipes with decent scores
        $recommendations[] = [
            'recipe' => $recipe,
            'score' => $score,
            'reason' => generateRecommendationReason($user_profile, $recipe)
        ];
    }
}

// Sort by score
usort($recommendations, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// Return top 10
$recommendations = array_slice($recommendations, 0, 10);

echo json_encode([
    'success' => true,
    'recommendations' => $recommendations,
    'user_profile' => [
        'dietary_preference' => $user_profile['dietary_preference'],
        'goal' => $user_profile['goal'],
        'target_calories' => $user_profile['target_calories']
    ]
]);

closeDBConnection($conn);

function calculateRecommendationScore($user_profile, $recipe, $interactions) {
    $score = 50.0;  // Base score
    
    // Dietary preference matching
    $dietary_pref = strtolower($user_profile['dietary_preference'] ?? 'none');
    $recipe_tags = strtolower($recipe['dietary_tags'] ?? '');
    
    if ($dietary_pref !== 'none' && strpos($recipe_tags, $dietary_pref) !== false) {
        $score += 20;
    }
    
    // Calorie matching
    $target_calories = ($user_profile['target_calories'] ?? 2000) / 3;  // Per meal
    $calorie_diff = abs($recipe['calories'] - $target_calories);
    $calorie_score = max(0, 15 - ($calorie_diff / 100));
    $score += $calorie_score;
    
    // Protein matching (for high-protein goals)
    if (($user_profile['goal'] ?? '') === 'muscle_gain') {
        $protein_ratio = $recipe['protein'] / max($recipe['calories'], 1) * 100;
        if ($protein_ratio > 20) {  // High protein
            $score += 15;
        }
    }
    
    // Recipe popularity
    $score += min($recipe['avg_rating'] * 2, 10);
    $score += min($recipe['favorite_count'] * 0.5, 10);
    
    // User interaction history
    $recipe_id = $recipe['id'];
    $has_favorited = false;
    foreach ($interactions as $interaction) {
        if ($interaction['recipe_id'] == $recipe_id) {
            if ($interaction['type'] === 'favorite') {
                $has_favorited = true;
            }
        }
    }
    
    // Penalize already favorited recipes
    if ($has_favorited) {
        $score -= 30;
    }
    
    return round($score, 2);
}

function generateRecommendationReason($user_profile, $recipe) {
    $reasons = [];
    
    $dietary_pref = strtolower($user_profile['dietary_preference'] ?? 'none');
    $recipe_tags = strtolower($recipe['dietary_tags'] ?? '');
    
    if ($dietary_pref !== 'none' && strpos($recipe_tags, $dietary_pref) !== false) {
        $reasons[] = "Matches your " . ucfirst($dietary_pref) . " preference";
    }
    
    if ($recipe['avg_rating'] >= 4.5) {
        $reasons[] = "Highly rated (" . round($recipe['avg_rating'], 1) . "★)";
    }
    
    if ($recipe['favorite_count'] > 10) {
        $reasons[] = "Popular choice";
    }
    
    $goal = $user_profile['goal'] ?? '';
    if ($goal === 'muscle_gain' && $recipe['protein'] > 30) {
        $reasons[] = "High protein for muscle gain";
    } elseif ($goal === 'weight_loss' && $recipe['calories'] < 400) {
        $reasons[] = "Low calorie for weight loss";
    }
    
    return implode(' • ', $reasons) ?: 'Recommended for you';
}
?>
