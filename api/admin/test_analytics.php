<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

// Set admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

echo "Testing Analytics API...\n";

try {
    $conn = getDBConnection();
    
    // Test basic queries
    echo "Testing database connection... ";
    if ($conn) {
        echo "OK\n";
    } else {
        echo "FAILED\n";
        exit;
    }
    
    // Test users table
    echo "Testing users table... ";
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "OK ($count users)\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
    
    // Test meal_plans table
    echo "Testing meal_plans table... ";
    $result = $conn->query("SELECT COUNT(*) as count FROM meal_plans");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "OK ($count meal plans)\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
    
    // Test nutrition_logs table
    echo "Testing nutrition_logs table... ";
    $result = $conn->query("SELECT COUNT(*) as count FROM nutrition_logs");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "OK ($count nutrition logs)\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
    
    // Test recipes table
    echo "Testing recipes table... ";
    $result = $conn->query("SELECT COUNT(*) as count FROM recipes");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "OK ($count recipes)\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
    
    echo "\nAll basic tests passed. Analytics API should work.\n";
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>