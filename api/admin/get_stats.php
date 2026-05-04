<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDBConnection();

// Get total users (excluding admin users)
$usersStmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin' OR role IS NULL");
$totalUsers = $usersStmt->fetch_assoc()['total'];

// Get total recipes
$recipesStmt = $conn->query("SELECT COUNT(*) as total FROM recipes");
$totalRecipes = $recipesStmt->fetch_assoc()['total'];

// Get total meal plans
$mealPlansStmt = $conn->query("SELECT COUNT(*) as total FROM meal_plans");
$totalMealPlans = $mealPlansStmt->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'stats' => [
        'total_users' => $totalUsers,
        'total_recipes' => $totalRecipes,
        'total_meal_plans' => $totalMealPlans
    ]
]);

closeDBConnection($conn);
?>
