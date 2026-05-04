<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['ingredients']) || !is_array($input['ingredients'])) {
    echo json_encode(['success' => false, 'message' => 'Ingredients array required']);
    exit;
}

$conn = getDBConnection();

// Get ingredient nutrition data
$ingredients_data = [];
foreach ($input['ingredients'] as $ingredient) {
    $ingredient_id = intval($ingredient['ingredient_id']);
    $quantity = floatval($ingredient['quantity']);
    
    $sql = "SELECT * FROM ingredients WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ingredient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ing_data = $result->fetch_assoc();
    
    if ($ing_data) {
        $ingredients_data[] = [
            'ingredient_id' => $ingredient_id,
            'ingredient_name' => $ing_data['ingredient_name'],
            'quantity' => $quantity,
            'calories_per_100g' => $ing_data['calories_per_100g'],
            'protein_per_100g' => $ing_data['protein_per_100g'],
            'carbs_per_100g' => $ing_data['carbs_per_100g'],
            'fats_per_100g' => $ing_data['fats_per_100g']
        ];
    }
}

// Calculate nutrition using ML model or simple calculation
$use_ml = isset($input['use_ml']) && $input['use_ml'];

if ($use_ml) {
    $nutrition = predictNutritionML($ingredients_data);
} else {
    $nutrition = calculateNutritionSimple($ingredients_data);
}

echo json_encode([
    'success' => true,
    'nutrition' => $nutrition,
    'method' => $use_ml ? 'ml_prediction' : 'simple_calculation',
    'ingredients_count' => count($ingredients_data)
]);

closeDBConnection($conn);

function predictNutritionML($ingredients_data) {
    // Call Python ML model for prediction
    $ingredients_json = json_encode($ingredients_data);
    
    $python_path = 'python';
    $script_path = '../ml_service/neural_nutrition_predictor.py';
    
    $command = sprintf(
        '%s %s predict_nutrition %s 2>&1',
        escapeshellcmd($python_path),
        escapeshellarg($script_path),
        escapeshellarg($ingredients_json)
    );
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    if ($result && !isset($result['error'])) {
        return $result;
    } else {
        // Fallback to simple calculation
        return calculateNutritionSimple($ingredients_data);
    }
}

function calculateNutritionSimple($ingredients_data) {
    $total_calories = 0;
    $total_protein = 0;
    $total_carbs = 0;
    $total_fats = 0;
    
    foreach ($ingredients_data as $ingredient) {
        $multiplier = $ingredient['quantity'] / 100;
        
        $total_calories += $ingredient['calories_per_100g'] * $multiplier;
        $total_protein += $ingredient['protein_per_100g'] * $multiplier;
        $total_carbs += $ingredient['carbs_per_100g'] * $multiplier;
        $total_fats += $ingredient['fats_per_100g'] * $multiplier;
    }
    
    return [
        'calories' => round($total_calories, 2),
        'protein' => round($total_protein, 2),
        'carbs' => round($total_carbs, 2),
        'fats' => round($total_fats, 2)
    ];
}
?>
