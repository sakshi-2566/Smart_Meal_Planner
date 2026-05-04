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
$days = isset($input['days']) ? intval($input['days']) : 7;
$use_ai = isset($input['use_ai']) ? $input['use_ai'] : false;

$conn = getDBConnection();

// Get user profile
$sql = "SELECT u.*, p.* FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();

if ($use_ai) {
    // Use OpenAI to generate meal plan
    $result = generateAIMealPlan($user_profile, $days);
    echo json_encode($result);
} else {
    // Use database recipes with ML recommendations
    $result = generateDatabaseMealPlan($user_profile, $days, $conn);
    echo json_encode($result);
}

closeDBConnection($conn);

function generateAIMealPlan($user_profile, $days) {
    // Prepare user profile for Python script
    $profile_json = json_encode([
        'target_calories' => $user_profile['target_calories'] ?? 2000,
        'dietary_preference' => $user_profile['dietary_preference'] ?? 'none',
        'goal' => $user_profile['goal'] ?? 'maintenance',
        'age' => $user_profile['age'] ?? 30,
        'weight' => $user_profile['weight'] ?? 70
    ]);
    
    // Call Python OpenAI script
    $python_path = 'python';  // or 'python3' depending on system
    $script_path = '../ml_service/openai_recipe_generator.py';
    
    $command = sprintf(
        '%s %s generate_meal_plan %s %d 2>&1',
        escapeshellcmd($python_path),
        escapeshellarg($script_path),
        escapeshellarg($profile_json),
        $days
    );
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    if ($result && isset($result['success']) && $result['success']) {
        return [
            'success' => true,
            'meal_plan' => $result['meal_plan'],
            'source' => 'ai_generated',
            'days' => $days
        ];
    } else {
        return [
            'success' => false,
            'message' => 'AI generation failed',
            'error' => $result['error'] ?? 'Unknown error'
        ];
    }
}

function generateDatabaseMealPlan($user_profile, $days, $conn) {
    $daily_calories = $user_profile['target_calories'] ?? 2000;
    $dietary_pref = $user_profile['dietary_preference'] ?? 'none';
    
    // Calorie distribution
    $breakfast_cal = $daily_calories * 0.30;
    $lunch_cal = $daily_calories * 0.40;
    $dinner_cal = $daily_calories * 0.30;
    
    // Get approved recipes
    $where_clause = "r.approval_status = 'approved' AND r.is_public = 1";
    if ($dietary_pref !== 'none') {
        $where_clause .= " AND r.dietary_tags LIKE '%" . $conn->real_escape_string($dietary_pref) . "%'";
    }
    
    $sql = "SELECT r.*, 
            COALESCE(AVG(rt.rating), 0) as avg_rating,
            COUNT(DISTINCT rf.id) as favorite_count
            FROM recipes r
            LEFT JOIN recipe_ratings rt ON r.id = rt.recipe_id
            LEFT JOIN recipe_favorites rf ON r.id = rf.recipe_id
            WHERE $where_clause
            GROUP BY r.id
            ORDER BY avg_rating DESC, favorite_count DESC";
    
    $result = $conn->query($sql);
    $all_recipes = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($all_recipes)) {
        return [
            'success' => false,
            'message' => 'No recipes available for your preferences'
        ];
    }
    
    $meal_plan = [];
    $used_recipes = [];
    
    for ($day = 1; $day <= $days; $day++) {
        $day_meals = [
            'day' => $day,
            'date' => date('Y-m-d', strtotime("+" . $day . " days")),
            'meals' => []
        ];
        
        // Select breakfast
        $breakfast = selectMealByCalories($all_recipes, $used_recipes, $breakfast_cal, 100);
        if ($breakfast) {
            $day_meals['meals'][] = [
                'type' => 'breakfast',
                'recipe' => $breakfast
            ];
            $used_recipes[] = $breakfast['id'];
        }
        
        // Select lunch
        $lunch = selectMealByCalories($all_recipes, $used_recipes, $lunch_cal, 150);
        if ($lunch) {
            $day_meals['meals'][] = [
                'type' => 'lunch',
                'recipe' => $lunch
            ];
            $used_recipes[] = $lunch['id'];
        }
        
        // Select dinner
        $dinner = selectMealByCalories($all_recipes, $used_recipes, $dinner_cal, 150);
        if ($dinner) {
            $day_meals['meals'][] = [
                'type' => 'dinner',
                'recipe' => $dinner
            ];
            $used_recipes[] = $dinner['id'];
        }
        
        $meal_plan[] = $day_meals;
    }
    
    return [
        'success' => true,
        'meal_plan' => $meal_plan,
        'source' => 'database',
        'days' => $days
    ];
}

function selectMealByCalories($recipes, $used_recipes, $target_calories, $tolerance) {
    $best_match = null;
    $best_diff = PHP_INT_MAX;
    
    foreach ($recipes as $recipe) {
        // Skip used recipes
        if (in_array($recipe['id'], $used_recipes)) {
            continue;
        }
        
        $calorie_diff = abs($recipe['calories'] - $target_calories);
        
        if ($calorie_diff <= $tolerance && $calorie_diff < $best_diff) {
            $best_match = $recipe;
            $best_diff = $calorie_diff;
        }
    }
    
    // If no match within tolerance, get closest
    if (!$best_match) {
        foreach ($recipes as $recipe) {
            if (in_array($recipe['id'], $used_recipes)) {
                continue;
            }
            
            $calorie_diff = abs($recipe['calories'] - $target_calories);
            if ($calorie_diff < $best_diff) {
                $best_match = $recipe;
                $best_diff = $calorie_diff;
            }
        }
    }
    
    return $best_match;
}
?>
