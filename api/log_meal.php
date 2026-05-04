<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$recipe_id = isset($input['recipe_id']) ? intval($input['recipe_id']) : null;
$meal_type = $input['meal_type'] ?? 'snack';
$calories = intval($input['calories'] ?? 0);
$protein = floatval($input['protein'] ?? 0);
$carbs = floatval($input['carbs'] ?? 0);
$fats = floatval($input['fats'] ?? 0);
$log_date = $input['log_date'] ?? date('Y-m-d');
$notes = $input['notes'] ?? null;
$meal_plan_item_id = isset($input['meal_plan_item_id']) ? intval($input['meal_plan_item_id']) : null;

$conn = getDBConnection();

// If meal_plan_item_id is provided, check if already eaten and mark it as eaten
if ($meal_plan_item_id) {
    // Check if already eaten to prevent double logging
    $check_sql = "SELECT is_eaten FROM meal_plan_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $meal_plan_item_id);
    $check_stmt->execute();
    $is_eaten = $check_stmt->get_result()->fetch_assoc()['is_eaten'] ?? 0;

    if ($is_eaten == 1) {
        echo json_encode(['success' => false, 'message' => 'This meal instance has already been logged']);
        exit;
    }

    $update_sql = "UPDATE meal_plan_items SET is_eaten = 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $meal_plan_item_id);
    $update_stmt->execute();
}

// Insert nutrition log
$sql = "INSERT INTO nutrition_logs 
        (user_id, log_date, meal_type, recipe_id, calories, protein, carbs, fats, notes, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    error_log("Inserting nutrition log for user $user_id, recipe $recipe_id");
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        exit;
    }
    
    $stmt->bind_param("issidddds", $user_id, $log_date, $meal_type, $recipe_id, 
                      $calories, $protein, $carbs, $fats, $notes);

    if ($stmt->execute()) {
    // Record interaction if recipe_id is provided
    if ($recipe_id) {
        $int_sql = "INSERT INTO user_recipe_interactions 
                   (user_id, recipe_id, interaction_type, created_at) 
                   VALUES (?, ?, 'cook', NOW())";
        $int_stmt = $conn->prepare($int_sql);
        $int_stmt->bind_param("ii", $user_id, $recipe_id);
        $int_stmt->execute();
        
        // Update learning progress
        $prog_sql = "INSERT INTO user_learning_progress (user_id, total_interactions, recipes_cooked, updated_at)
                    VALUES (?, 1, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                    total_interactions = total_interactions + 1,
                    recipes_cooked = recipes_cooked + 1,
                    updated_at = NOW()";
        $prog_stmt = $conn->prepare($prog_sql);
        $prog_stmt->bind_param("i", $user_id);
        $prog_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Meal logged successfully',
        'log_id' => $conn->insert_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to log meal: ' . $conn->error
    ]);
}

closeDBConnection($conn);
?>
