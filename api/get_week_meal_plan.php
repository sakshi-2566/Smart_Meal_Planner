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

// Get active meal plan
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

if (!$plan) {
    echo json_encode([
        'success' => true,
        'days' => [],
        'message' => 'No active meal plan found'
    ]);
    closeDBConnection($conn);
    exit;
}

// Get all meals for the plan, grouped by date
$meals_sql = "SELECT mpi.*, mpi.id as meal_plan_item_id, r.recipe_name, r.calories, r.protein, r.carbs, r.fats, 
              r.prep_time, r.cook_time, r.description,
              mpi.meal_date, mpi.is_eaten
              FROM meal_plan_items mpi
              JOIN recipes r ON mpi.recipe_id = r.id
              WHERE mpi.meal_plan_id = ?
              ORDER BY mpi.meal_date, 
                  CASE mpi.meal_type
                      WHEN 'breakfast' THEN 1
                      WHEN 'lunch' THEN 2
                      WHEN 'dinner' THEN 3
                      WHEN 'snack' THEN 4
                  END";

$stmt = $conn->prepare($meals_sql);
$stmt->bind_param("i", $plan['id']);
$stmt->execute();
$result = $stmt->get_result();

// Group meals by date
$days_data = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['meal_date'];
    if (!isset($days_data[$date])) {
        $days_data[$date] = [
            'date' => $date,
            'day_name' => date('l', strtotime($date)), // Monday, Tuesday, etc.
            'is_today' => ($date === $today),
            'meals' => [],
            'total_calories' => 0,
            'total_protein' => 0,
            'total_carbs' => 0,
            'total_fats' => 0
        ];
    }
    
    $days_data[$date]['meals'][] = $row;
    $days_data[$date]['total_calories'] += $row['calories'];
    $days_data[$date]['total_protein'] += $row['protein'];
    $days_data[$date]['total_carbs'] += $row['carbs'];
    $days_data[$date]['total_fats'] += $row['fats'];
}

// Convert to indexed array and sort by date
$days = array_values($days_data);
usort($days, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

echo json_encode([
    'success' => true,
    'days' => $days,
    'plan_info' => [
        'name' => $plan['plan_name'],
        'start_date' => $plan['start_date'],
        'end_date' => $plan['end_date'],
        'total_days' => count($days)
    ]
]);

closeDBConnection($conn);
?>
