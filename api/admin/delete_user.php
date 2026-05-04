<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$conn = getDBConnection();

// Verify admin role
$admin_check = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($admin_check);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin || $admin['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    exit;
}

try {
    // Check if user exists and is not admin
    $check_sql = "SELECT id, role, first_name, last_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Prevent deletion of other admin users
    if ($user['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete related data first (to maintain referential integrity)
    
    // Delete user's meal plan items
    $conn->query("DELETE mpi FROM meal_plan_items mpi 
                  INNER JOIN meal_plans mp ON mpi.meal_plan_id = mp.id 
                  WHERE mp.user_id = $user_id");
    
    // Delete user's meal plans
    $conn->query("DELETE FROM meal_plans WHERE user_id = $user_id");
    
    // Delete user's recipe ratings
    $conn->query("DELETE FROM recipe_ratings WHERE user_id = $user_id");
    
    // Delete user's recipe favorites
    $conn->query("DELETE FROM recipe_favorites WHERE user_id = $user_id");
    
    // Delete user's shopping cart items
    $conn->query("DELETE FROM shopping_cart WHERE user_id = $user_id");
    
    // Delete user's orders
    $conn->query("DELETE FROM orders WHERE user_id = $user_id");
    
    // Delete user's inventory
    $conn->query("DELETE FROM user_inventory WHERE user_id = $user_id");
    
    // Delete user's nutrition logs
    $conn->query("DELETE FROM nutrition_logs WHERE user_id = $user_id");
    
    // Delete user's feedback
    $conn->query("DELETE FROM meal_feedback WHERE user_id = $user_id");
    
    // Update recipes created by user (set user_id to NULL instead of deleting)
    $conn->query("UPDATE recipes SET user_id = NULL WHERE user_id = $user_id");
    
    // Finally, delete the user
    $delete_sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "User {$user['first_name']} {$user['last_name']} deleted successfully"
        ]);
    } else {
        throw new Exception('Failed to delete user');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Error deleting user: ' . $e->getMessage()
    ]);
}

closeDBConnection($conn);
?>