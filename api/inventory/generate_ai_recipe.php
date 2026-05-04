<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$ingredients = $input['ingredients'] ?? [];

if (empty($ingredients)) {
    echo json_encode(['success' => false, 'message' => 'No ingredients provided']);
    exit;
}

$conn = getDBConnection();

// Get user profile for preferences
$profile_sql = "SELECT dietary_preference, target_calories, health_goal FROM users WHERE id = ?";
$stmt = $conn->prepare($profile_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$dietary_pref = $profile['dietary_preference'] ?? 'none';
$target_calories = $profile['target_calories'] ?? 500;

// Prepare preferences for Python script
$preferences = json_encode([
    'dietary_preference' => $dietary_pref,
    'cuisine' => 'any',
    'ingredients' => $ingredients
]);

$targets = json_encode([
    'calories' => intval($target_calories / 3), // Assuming one meal
    'protein' => 30,
    'carbs' => 50,
    'fats' => 15
]);

// Call Python OpenAI recipe generator
$python_path = 'python'; // or 'python3' depending on system
$script_path = '../../ml_service/openai_recipe_generator.py';

$command = escapeshellcmd("$python_path $script_path generate_recipe " . 
                          escapeshellarg($preferences) . " " . 
                          escapeshellarg($targets));

$output = shell_exec($command . " 2>&1");

if ($output) {
    $result = json_decode($output, true);
    
    if ($result && isset($result['success']) && $result['success']) {
        echo json_encode([
            'success' => true,
            'recipe' => $result['recipe'],
            'message' => 'AI recipe generated successfully!'
        ]);
    } else {
        // Fallback: Return a simple recipe structure
        echo json_encode([
            'success' => true,
            'recipe' => [
                'name' => 'Custom Recipe from Your Ingredients',
                'description' => 'A delicious meal made with: ' . implode(', ', $ingredients),
                'ingredients' => array_map(function($ing) {
                    return ['name' => $ing, 'quantity' => '1', 'unit' => 'portion'];
                }, $ingredients),
                'instructions' => 'Combine ingredients and cook according to your preference.',
                'prep_time' => 15,
                'cook_time' => 30,
                'servings' => 2,
                'calories' => 500,
                'protein' => 30,
                'carbs' => 50,
                'fats' => 15
            ],
            'message' => 'Basic recipe template created. AI generation unavailable.',
            'fallback' => true
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate AI recipe. Please try again.'
    ]);
}

closeDBConnection($conn);
?>
