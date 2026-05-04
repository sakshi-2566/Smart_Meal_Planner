<?php
// Prevent any output before headers
ob_start();

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user profile
$stmt = $conn->prepare("
    SELECT u.dietary_preference, p.age, p.weight, p.height, p.goal, p.target_calories, p.bmr, p.tdee
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

// Initialize profile with defaults if null
if (!$profile) {
    $profile = [
        'dietary_preference' => null,
        'age' => null,
        'weight' => null,
        'height' => null,
        'goal' => null,
        'target_calories' => 2000,
        'bmr' => null,
        'tdee' => null
    ];
}

// Get recent nutrition logs
$logsStmt = $conn->prepare("
    SELECT log_date, SUM(calories) as total_calories, SUM(protein) as total_protein
    FROM nutrition_logs
    WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY log_date
    ORDER BY log_date DESC
");
$logsStmt->bind_param("i", $userId);
$logsStmt->execute();
$logsResult = $logsStmt->get_result();
$recentLogs = [];
while ($row = $logsResult->fetch_assoc()) {
    $recentLogs[] = $row;
}
$logsStmt->close();

// Generate AI recommendations
$recommendations = [];

// Check if profile is complete
if (empty($profile['age']) || empty($profile['weight']) || empty($profile['height'])) {
    $recommendations[] = [
        'icon' => 'fa-user-edit',
        'color' => 'warning',
        'message' => 'Complete your profile to get personalized meal recommendations and accurate calorie targets.'
    ];
}

// Check recent activity
if (empty($recentLogs)) {
    $recommendations[] = [
        'icon' => 'fa-clipboard-list',
        'color' => 'info',
        'message' => 'Start logging your meals to track your nutrition and get better AI recommendations.'
    ];
} else {
    // Analyze recent logs
    $avgCalories = array_sum(array_column($recentLogs, 'total_calories')) / count($recentLogs);
    $targetCalories = $profile['target_calories'] ?? 2000;
    
    if ($avgCalories < $targetCalories * 0.8) {
        $recommendations[] = [
            'icon' => 'fa-arrow-up',
            'color' => 'warning',
            'message' => 'You\'re eating below your target. Consider adding healthy snacks to meet your calorie goals.'
        ];
    } elseif ($avgCalories > $targetCalories * 1.2) {
        $recommendations[] = [
            'icon' => 'fa-arrow-down',
            'color' => 'danger',
            'message' => 'You\'re exceeding your calorie target. Try smaller portions or lower-calorie alternatives.'
        ];
    } else {
        $recommendations[] = [
            'icon' => 'fa-check-circle',
            'color' => 'success',
            'message' => 'Great job! You\'re staying within your calorie target. Keep it up!'
        ];
    }
    
    // Protein recommendation
    $avgProtein = array_sum(array_column($recentLogs, 'total_protein')) / count($recentLogs);
    $targetProtein = ($targetCalories * 0.30) / 4; // 30% of calories from protein
    
    if ($avgProtein < $targetProtein * 0.8) {
        $recommendations[] = [
            'icon' => 'fa-drumstick-bite',
            'color' => 'info',
            'message' => 'Increase your protein intake. Try adding lean meats, fish, eggs, or plant-based proteins.'
        ];
    }
}

// Goal-specific recommendations
if (!empty($profile['goal'])) {
    switch ($profile['goal']) {
        case 'weight_loss':
            $recommendations[] = [
                'icon' => 'fa-weight',
                'color' => 'primary',
                'message' => 'For weight loss, focus on high-protein, high-fiber foods to stay full longer.'
            ];
            break;
        case 'muscle_gain':
            $recommendations[] = [
                'icon' => 'fa-dumbbell',
                'color' => 'success',
                'message' => 'For muscle gain, ensure you\'re eating enough protein (1.6-2.2g per kg body weight).'
            ];
            break;
        case 'athletic':
            $recommendations[] = [
                'icon' => 'fa-running',
                'color' => 'info',
                'message' => 'Athletes need adequate carbs for energy. Time your meals around your training sessions.'
            ];
            break;
    }
}

// Dietary preference tips
if (!empty($profile['dietary_preference'])) {
    switch ($profile['dietary_preference']) {
        case 'vegan':
            $recommendations[] = [
                'icon' => 'fa-leaf',
                'color' => 'success',
                'message' => 'Ensure you\'re getting B12, iron, and omega-3s from fortified foods or supplements.'
            ];
            break;
        case 'vegetarian':
            $recommendations[] = [
                'icon' => 'fa-seedling',
                'color' => 'success',
                'message' => 'Include variety: legumes, nuts, seeds, and whole grains for complete nutrition.'
            ];
            break;
        case 'keto':
            $recommendations[] = [
                'icon' => 'fa-bacon',
                'color' => 'warning',
                'message' => 'Stay hydrated and ensure adequate electrolytes (sodium, potassium, magnesium).'
            ];
            break;
    }
}

// Hydration reminder
$recommendations[] = [
    'icon' => 'fa-glass-water',
    'color' => 'info',
    'message' => 'Don\'t forget to drink water! Aim for 8-10 glasses per day.'
];

// Limit to top 5 recommendations
$recommendations = array_slice($recommendations, 0, 5);

// Clear any output buffer and send JSON
ob_clean();
echo json_encode([
    'success' => true,
    'recommendations' => $recommendations
]);

closeDBConnection($conn);
?>
