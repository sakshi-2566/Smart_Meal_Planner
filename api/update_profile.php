<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

// Log input for debugging
error_log("Update Profile Input for User $userId: " . print_r($input, true));

$age = $input['age'] ?? null;
$gender = $input['gender'] ?? null;
$height = $input['height'] ?? null;
$weight = $input['weight'] ?? null;
$activityLevel = $input['activityLevel'] ?? 'moderate';
$goal = $input['goal'] ?? 'maintenance';

$conn = getDBConnection();

// Initialize all calculated values to 0 explicitly as requested
$bmr = 0;
$tdee = 0;
$targetCalories = 0;
$targetProtein = 0;
$targetCarbs = 0;
$targetFats = 0;

// Calculate BMR using Mifflin-St Jeor Equation
// Ensure we have valid numeric values for calculation
if ($age && $gender && $height && $weight) {
    if ($gender === 'male') {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
    } else {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
    }
}

// Calculate TDEE and Macros if BMR is available
if ($bmr > 0) {
    $activityMultipliers = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'very_active' => 1.9
    ];
    $tdee = $bmr * ($activityMultipliers[$activityLevel] ?? 1.55);

    // Adjust calories based on goal
    $targetCalories = $tdee;
    if ($goal === 'weight_loss') {
        $targetCalories = $tdee - 500; // 500 calorie deficit
    } elseif ($goal === 'muscle_gain') {
        $targetCalories = $tdee + 300; // 300 calorie surplus
    }
    
    // Ensure target calories isn't negative, but allow it to be 0 if calculation failed/reset
    if ($targetCalories < 0) $targetCalories = 0;

    // Calculate macros (40% carbs, 30% protein, 30% fats)
    // Round them to integers for storage and consistent display
    $targetProtein = round(($targetCalories * 0.30) / 4);
    $targetCarbs = round(($targetCalories * 0.40) / 4);
    $targetFats = round(($targetCalories * 0.30) / 9);
    $targetCalories = round($targetCalories);
}

// Update profile
// Note: We use 'd' (double) for BMR/TDEE and 'i' (integer) for targets.
// Since we initialized to 0, they will be saved as 0.

$stmt = $conn->prepare("
    UPDATE user_profiles 
    SET age = ?, gender = ?, height = ?, weight = ?, activity_level = ?, goal = ?,
        bmr = ?, tdee = ?, target_calories = ?, target_protein = ?, target_carbs = ?, target_fats = ?
    WHERE user_id = ?
");

$stmt->bind_param(
    "isddssdddiiii",
    $age, $gender, $height, $weight, $activityLevel, $goal,
    $bmr, $tdee, $targetCalories, $targetProtein, $targetCarbs, $targetFats, $userId
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'calculations' => [
            'bmr' => round($bmr, 2),
            'tdee' => round($tdee, 2),
            'targetCalories' => $targetCalories,
            'targetProtein' => $targetProtein,
            'targetCarbs' => $targetCarbs,
            'targetFats' => $targetFats
        ]
    ]);

    // Reset daily stats (delete today's nutrition logs)
    $resetSql = "DELETE FROM nutrition_logs WHERE user_id = ? AND log_date = CURDATE()";
    $resetStmt = $conn->prepare($resetSql);
    $resetStmt->bind_param("i", $userId);
    $resetStmt->execute();
    $resetStmt->close();
} else {
    error_log("Profile Update Error: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}

$stmt->close();
closeDBConnection($conn);
?>
