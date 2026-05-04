<?php
// Test meal plan generation SQL
require_once 'config/database.php';

$conn = getDBConnection();

$days = 1;
$user_id = 1; // Test user

echo "Testing date calculations:\n";
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime("+" . ($days-1) . " days"));
echo "Start: $start_date\n";
echo "End: $end_date\n";

echo "\nTesting meal_date in loop:\n";
for ($day = 0; $day < $days; $day++) {
    $meal_date = date('Y-m-d', strtotime("+" . $day . " days"));
    echo "Day $day: $meal_date\n";
}

echo "\nTesting SQL queries:\n";

// Test 1: Delete items
$delete_items_sql = "DELETE mpi FROM meal_plan_items mpi
                    INNER JOIN meal_plans mp ON mpi.meal_plan_id = mp.id
                    WHERE mp.user_id = ? 
                    AND mpi.meal_date BETWEEN ? AND ?";
echo "Query 1: " . $delete_items_sql . "\n";
$stmt = $conn->prepare($delete_items_sql);
if (!$stmt) {
    echo "ERROR in Query 1: " . $conn->error . "\n";
} else {
    echo "Query 1: OK\n";
}

// Test 2: Delete plans
$delete_plans_sql = "DELETE FROM meal_plans 
                    WHERE user_id = ? 
                    AND start_date <= ? 
                    AND end_date >= ?";
echo "Query 2: " . $delete_plans_sql . "\n";
$stmt2 = $conn->prepare($delete_plans_sql);
if (!$stmt2) {
    echo "ERROR in Query 2: " . $conn->error . "\n";
} else {
    echo "Query 2: OK\n";
}

// Test 3: Insert plan
$plan_name = "Test Plan";
$target_calories = 2000;
$plan_sql = "INSERT INTO meal_plans (user_id, plan_name, start_date, end_date, total_calories)
             VALUES (?, ?, ?, ?, ?)";
echo "Query 3: " . $plan_sql . "\n";
$stmt3 = $conn->prepare($plan_sql);
if (!$stmt3) {
    echo "ERROR in Query 3: " . $conn->error . "\n";
} else {
    echo "Query 3: OK\n";
}

echo "\nAll tests completed!\n";

closeDBConnection($conn);
?>
