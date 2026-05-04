<?php
/**
 * Test endpoint to check meal plan generation prerequisites
 */
session_start();
header('Content-Type: application/json');

try {
    require_once '../config/database.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();

    $checks = [];

    // Check 1: User exists
    $user_sql = "SELECT id, first_name, dietary_preference FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $checks['user_exists'] = $user ? true : false;
    $checks['user_data'] = $user;

    // Check 2: User profile exists
    $profile_sql = "SELECT * FROM user_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($profile_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $checks['profile_exists'] = $profile ? true : false;
    $checks['profile_data'] = $profile;

    // Check 3: Recipes exist
    $recipe_sql = "SELECT COUNT(*) as count FROM recipes WHERE approval_status = 'approved'";
    $result = $conn->query($recipe_sql);
    $recipe_count = $result->fetch_assoc()['count'];
    $checks['recipes_count'] = $recipe_count;
    $checks['recipes_exist'] = $recipe_count > 0;

    // Check 4: Shopping cart table exists
    $table_sql = "SHOW TABLES LIKE 'shopping_cart'";
    $result = $conn->query($table_sql);
    $checks['shopping_cart_table_exists'] = $result->num_rows > 0;

    // Check 5: Meal plans table exists
    $table_sql = "SHOW TABLES LIKE 'meal_plans'";
    $result = $conn->query($table_sql);
    $checks['meal_plans_table_exists'] = $result->num_rows > 0;

    // Check 6: Ingredients have prices
    $ing_sql = "SELECT COUNT(*) as count FROM ingredients WHERE price_per_unit > 0";
    $result = $conn->query($ing_sql);
    $ing_count = $result->fetch_assoc()['count'];
    $checks['ingredients_with_prices'] = $ing_count;

    // Overall status
    $all_good = $checks['user_exists'] && 
                $checks['profile_exists'] && 
                $checks['recipes_exist'] && 
                $checks['shopping_cart_table_exists'] &&
                $checks['meal_plans_table_exists'] &&
                $checks['ingredients_with_prices'] > 0;

    echo json_encode([
        'success' => true,
        'ready_for_meal_plan' => $all_good,
        'checks' => $checks,
        'message' => $all_good ? 'All systems ready!' : 'Some prerequisites missing'
    ]);

    closeDBConnection($conn);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
