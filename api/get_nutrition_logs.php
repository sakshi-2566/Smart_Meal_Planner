<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT nl.*, r.recipe_name
    FROM nutrition_logs nl
    LEFT JOIN recipes r ON nl.recipe_id = r.id
    WHERE nl.user_id = ? AND nl.log_date BETWEEN ? AND ?
    ORDER BY nl.log_date DESC, nl.meal_type
");
$stmt->bind_param("iss", $userId, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get daily totals
$totalStmt = $conn->prepare("
    SELECT log_date,
           SUM(calories) as total_calories,
           SUM(protein) as total_protein,
           SUM(carbs) as total_carbs,
           SUM(fats) as total_fats
    FROM nutrition_logs
    WHERE user_id = ? AND log_date BETWEEN ? AND ?
    GROUP BY log_date
    ORDER BY log_date DESC
");
$totalStmt->bind_param("iss", $userId, $startDate, $endDate);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();

$dailyTotals = [];
while ($row = $totalResult->fetch_assoc()) {
    $dailyTotals[] = $row;
}

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'dailyTotals' => $dailyTotals
]);

$stmt->close();
$totalStmt->close();
closeDBConnection($conn);
?>
