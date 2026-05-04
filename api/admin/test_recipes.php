<?php
// Simple test to see what's happening
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

$response = [
    'session_check' => [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'user_role' => $_SESSION['user_role'] ?? 'not set',
        'all_session' => $_SESSION
    ],
    'file_path' => __FILE__,
    'dir_path' => __DIR__
];

// Try to load database
try {
    require_once __DIR__ . '/../config/database.php';
    $response['database_config'] = 'loaded';
    
    $conn = getDBConnection();
    $response['database_connection'] = 'connected';
    
    // Check if recipes table exists
    $result = $conn->query("SHOW TABLES LIKE 'recipes'");
    $response['recipes_table_exists'] = $result && $result->num_rows > 0;
    
    // Count recipes
    $result = $conn->query("SELECT COUNT(*) as count FROM recipes");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['recipe_count'] = $row['count'];
    }
    
    // Check columns
    $result = $conn->query("SHOW COLUMNS FROM recipes");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $response['recipe_columns'] = $columns;
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
