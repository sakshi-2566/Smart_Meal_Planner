<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

$conn = getDBConnection();

// Get today's meal plan items
$sql = "SELECT mpi.*, mpi.id as meal_plan_item_id, r.recipe_name, r.calories, r.protein, r.carbs, r.fats, 
        r.prep_time, r.cook_time, r.description, mpi.is_eaten
        FROM meal_plan_items mpi
        JOIN meal_plans mp ON mpi.meal_plan_id = mp.id
        JOIN recipes r ON mpi.recipe_id = r.id
        WHERE mp.user_id = ? 
        AND mpi.meal_date = ?
        AND mp.is_active = 1
        ORDER BY 
            CASE mpi.meal_type
                WHEN 'breakfast' THEN 1
                WHEN 'lunch' THEN 2
                WHEN 'dinner' THEN 3
                WHEN 'snack' THEN 4
            END";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();

$meals = [];
while ($row = $result->fetch_assoc()) {
    $meals[] = $row;
}

// If no meals for today, check if there's an active meal plan
if (empty($meals)) {
    $plan_sql = "SELECT id, plan_name, start_date, end_date 
                 FROM meal_plans 
                 WHERE user_id = ? 
                 AND is_active = 1 
                 AND start_date <= ? 
                 AND end_date >= ?
                 ORDER BY created_at DESC 
                 LIMIT 1";
    $stmt = $conn->prepare($plan_sql);
    $stmt->bind_param("iss", $user_id, $today, $today);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'meals' => [],
        'has_active_plan' => $plan ? true : false,
        'plan_info' => $plan,
        'message' => $plan ? 'No meals scheduled for today' : 'No active meal plan'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'meals' => $meals,
        'total_calories' => array_sum(array_column($meals, 'calories')),
        'total_protein' => array_sum(array_column($meals, 'protein')),
        'total_carbs' => array_sum(array_column($meals, 'carbs')),
        'total_fats' => array_sum(array_column($meals, 'fats'))
    ]);
}

closeDBConnection($conn);
?>
