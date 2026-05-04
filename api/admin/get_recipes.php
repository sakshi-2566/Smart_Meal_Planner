<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getDBConnection();
    
    // Simple query - just get recipes with user info
    $sql = "SELECT r.*, u.first_name, u.last_name, u.email
            FROM recipes r
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Query failed: ' . $conn->error,
            'recipes' => [],
            'stats' => [
                'total_recipes' => 0,
                'pending_recipes' => 0,
                'approved_recipes' => 0,
                'rejected_recipes' => 0,
                'ai_generated_recipes' => 0
            ]
        ]);
        closeDBConnection($conn);
        exit;
    }
    
    $recipes = [];
    while ($row = $result->fetch_assoc()) {
        // Add default values for missing columns
        if (!isset($row['approval_status'])) {
            $row['approval_status'] = 'approved';
        }
        if (!isset($row['is_ai_generated'])) {
            $row['is_ai_generated'] = 0;
        }
        
        // Add default stats
        $row['avg_rating'] = 0;
        $row['rating_count'] = 0;
        $row['favorite_count'] = 0;
        $row['usage_count'] = 0;
        
        $recipes[] = $row;
    }
    
    // Get simple stats
    $stats = [
        'total_recipes' => count($recipes),
        'pending_recipes' => 0,
        'approved_recipes' => count($recipes),
        'rejected_recipes' => 0,
        'ai_generated_recipes' => 0
    ];
    
    echo json_encode([
        'success' => true,
        'recipes' => $recipes,
        'stats' => $stats
    ]);
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'recipes' => [],
        'stats' => [
            'total_recipes' => 0,
            'pending_recipes' => 0,
            'approved_recipes' => 0,
            'rejected_recipes' => 0,
            'ai_generated_recipes' => 0
        ]
    ]);
}
?>
