<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$date = $input['date'] ?? date('Y-m-d');

$conn = getDBConnection();

// Get user profile and preferences
$stmt = $conn->prepare("
    SELECT u.dietary_preference, p.target_calories, p.target_protein, p.target_carbs, p.target_fats, p.goal
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userProfile = $result->fetch_assoc();
$stmt->close();

if (!$userProfile || !$userProfile['target_calories']) {
    echo json_encode([
        'success' => false,
        'message' => 'Please complete your profile first to generate meal plans'
    ]);
    closeDBConnection($conn);
    exit;
}

// Calculate meal distribution (30% breakfast, 40% lunch, 30% dinner)
$targetCalories = $userProfile['target_calories'];
$breakfastCal = $targetCalories * 0.30;
$lunchCal = $targetCalories * 0.40;
$dinnerCal = $targetCalories * 0.30;

// Get suitable recipes based on dietary preference
$dietaryPreference = $userProfile['dietary_preference'];
$dietaryTags = getDietaryTags($dietaryPreference);

// Generate meal plan using AI logic
$meals = [];

// Breakfast
$breakfast = getAIRecommendedMeal($conn, $dietaryTags, $breakfastCal, 'breakfast');
if ($breakfast) {
    $meals[] = array_merge($breakfast, ['meal_type' => 'Breakfast']);
}

// Lunch
$lunch = getAIRecommendedMeal($conn, $dietaryTags, $lunchCal, 'lunch');
if ($lunch) {
    $meals[] = array_merge($lunch, ['meal_type' => 'Lunch']);
}

// Dinner
$dinner = getAIRecommendedMeal($conn, $dietaryTags, $dinnerCal, 'dinner');
if ($dinner) {
    $meals[] = array_merge($dinner, ['meal_type' => 'Dinner']);
}

// If no meals found, create sample meals
if (empty($meals)) {
    $meals = generateSampleMeals($targetCalories, $dietaryPreference);
}

// Validate and adjust meals to stay within nutrition goals
$meals = validateAndAdjustMeals($meals, $userProfile);

// Save meal plan to database
$planStmt = $conn->prepare("
    INSERT INTO meal_plans (user_id, plan_name, start_date, end_date, total_calories)
    VALUES (?, ?, ?, ?, ?)
");
$planName = "AI Generated Plan - " . date('Y-m-d');
$planStmt->bind_param("isssi", $userId, $planName, $date, $date, $targetCalories);
$planStmt->execute();
$planId = $conn->insert_id;
$planStmt->close();

// Reset stats if the plan starts today
if ($date === date('Y-m-d')) {
    $resetSql = "DELETE FROM nutrition_logs WHERE user_id = ? AND log_date = CURDATE()";
    $resetStmt = $conn->prepare($resetSql);
    $resetStmt->bind_param("i", $userId);
    $resetStmt->execute();
    $resetStmt->close();
}

echo json_encode([
    'success' => true,
    'message' => 'Meal plan generated successfully',
    'meals' => $meals,
    'planId' => $planId,
    'totalCalories' => array_sum(array_column($meals, 'calories'))
]);

closeDBConnection($conn);

// Helper function to get dietary tags
function getDietaryTags($preference) {
    $tags = [];
    switch ($preference) {
        case 'vegetarian':
            $tags = ['vegetarian', 'vegan'];
            break;
        case 'non-vegetarian':
            // For non-vegetarian, we want recipes that are NOT vegetarian or vegan
            $tags = ['non-vegetarian', 'meat', 'chicken', 'fish', 'seafood'];
            break;
        case 'vegan':
            $tags = ['vegan'];
            break;
        case 'keto':
            $tags = ['keto', 'low-carb'];
            break;
        case 'paleo':
            $tags = ['paleo'];
            break;
        case 'gluten-free':
            $tags = ['gluten-free'];
            break;
        default:
            $tags = [];
    }
    return $tags;
}

// AI-based meal recommendation
function getAIRecommendedMeal($conn, $dietaryTags, $targetCalories, $mealType) {
    $calorieRange = 200; // +/- range
    $minCal = $targetCalories - $calorieRange;
    $maxCal = $targetCalories + $calorieRange;
    
    $query = "SELECT id, recipe_name, calories, protein, carbs, fats, dietary_tags 
              FROM recipes 
              WHERE calories BETWEEN ? AND ?";
    
    if (!empty($dietaryTags)) {
        $tagConditions = [];
        foreach ($dietaryTags as $tag) {
            $tagConditions[] = "dietary_tags LIKE '%$tag%'";
        }
        $query .= " AND (" . implode(" OR ", $tagConditions) . ")";
    }
    
    $query .= " ORDER BY RAND() LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dd", $minCal, $maxCal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    $stmt->close();
    return null;
}

// Generate sample meals if no recipes found
function generateSampleMeals($targetCalories, $dietaryPreference) {
    $breakfastCal = round($targetCalories * 0.30);
    $lunchCal = round($targetCalories * 0.40);
    $dinnerCal = round($targetCalories * 0.30);
    
    $isVeg = in_array($dietaryPreference, ['vegetarian', 'vegan']);
    
    return [
        [
            'id' => 0,
            'recipe_name' => $isVeg ? 'Oatmeal with Fruits & Nuts' : 'Scrambled Eggs with Toast',
            'meal_type' => 'Breakfast',
            'calories' => $breakfastCal,
            'protein' => round($breakfastCal * 0.25 / 4),
            'carbs' => round($breakfastCal * 0.50 / 4),
            'fats' => round($breakfastCal * 0.25 / 9)
        ],
        [
            'id' => 0,
            'recipe_name' => $isVeg ? 'Quinoa Buddha Bowl' : 'Grilled Chicken Salad',
            'meal_type' => 'Lunch',
            'calories' => $lunchCal,
            'protein' => round($lunchCal * 0.30 / 4),
            'carbs' => round($lunchCal * 0.40 / 4),
            'fats' => round($lunchCal * 0.30 / 9)
        ],
        [
            'id' => 0,
            'recipe_name' => $isVeg ? 'Lentil Curry with Brown Rice' : 'Salmon with Roasted Vegetables',
            'meal_type' => 'Dinner',
            'calories' => $dinnerCal,
            'protein' => round($dinnerCal * 0.30 / 4),
            'carbs' => round($dinnerCal * 0.40 / 4),
            'fats' => round($dinnerCal * 0.30 / 9)
        ]
    ];
}

/**
 * Validate and adjust meals to stay within user's nutrition goals
 */
function validateAndAdjustMeals($meals, $userProfile) {
    $target_calories = $userProfile['target_calories'];
    $target_protein = $userProfile['target_protein'];
    $target_carbs = $userProfile['target_carbs'];
    $target_fats = $userProfile['target_fats'];
    
    // Calculate total nutrition from all meals
    $total_calories = 0;
    $total_protein = 0;
    $total_carbs = 0;
    $total_fats = 0;
    
    foreach ($meals as $meal) {
        $total_calories += $meal['calories'];
        $total_protein += $meal['protein'];
        $total_carbs += $meal['carbs'];
        $total_fats += $meal['fats'];
    }
    
    // Allow 5% buffer for goals (so we don't go over)
    $max_calories = $target_calories * 0.95;
    $max_protein = $target_protein * 0.95;
    $max_carbs = $target_carbs * 0.95;
    $max_fats = $target_fats * 0.95;
    
    // Check if we exceed any goals
    $needs_adjustment = false;
    $adjustment_factor = 1.0;
    
    if ($total_calories > $max_calories) {
        $adjustment_factor = min($adjustment_factor, $max_calories / $total_calories);
        $needs_adjustment = true;
    }
    
    if ($target_protein > 0 && $total_protein > $max_protein) {
        $adjustment_factor = min($adjustment_factor, $max_protein / $total_protein);
        $needs_adjustment = true;
    }
    
    if ($target_carbs > 0 && $total_carbs > $max_carbs) {
        $adjustment_factor = min($adjustment_factor, $max_carbs / $total_carbs);
        $needs_adjustment = true;
    }
    
    if ($target_fats > 0 && $total_fats > $max_fats) {
        $adjustment_factor = min($adjustment_factor, $max_fats / $total_fats);
        $needs_adjustment = true;
    }
    
    // Apply adjustment if needed
    if ($needs_adjustment && $adjustment_factor < 1.0) {
        foreach ($meals as &$meal) {
            $meal['calories'] = round($meal['calories'] * $adjustment_factor);
            $meal['protein'] = round($meal['protein'] * $adjustment_factor, 1);
            $meal['carbs'] = round($meal['carbs'] * $adjustment_factor, 1);
            $meal['fats'] = round($meal['fats'] * $adjustment_factor, 1);
        }
    }
    
    return $meals;
}
?>
