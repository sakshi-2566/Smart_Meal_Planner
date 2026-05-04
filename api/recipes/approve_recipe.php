<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$conn = getDBConnection();
$admin_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['recipe_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID and action required']);
    exit;
}

$recipe_id = intval($input['recipe_id']);
$action = $input['action']; // 'approve' or 'reject'
$rejection_reason = isset($input['rejection_reason']) ? $conn->real_escape_string($input['rejection_reason']) : null;

if ($action === 'approve') {
    $sql = "UPDATE recipes SET approval_status = 'approved', approved_by = ?, approved_at = NOW() 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $recipe_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Recipe approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error approving recipe']);
    }
    
} elseif ($action === 'reject') {
    $sql = "UPDATE recipes SET approval_status = 'rejected', approved_by = ?, 
            approved_at = NOW(), rejection_reason = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $admin_id, $rejection_reason, $recipe_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Recipe rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error rejecting recipe']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

closeDBConnection($conn);
?>
