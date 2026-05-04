<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['recipe_name']) || !isset($input['ingredients']) || !isset($input['steps'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$recipe_name = $conn->real_escape_string($input['recipe_name']);
$description = isset($input['description']) ? $conn->real_escape_string($input['description']) : '';
$prep_time = isset($input['prep_time']) ? intval($input['prep_time']) : 0;
$cook_time = isset($input['cook_time']) ? intval($input['cook_time']) : 0;
$servings = isset($input['servings']) ? intval($input['servings']) : 1;
$dietary_tags = isset($input['dietary_tags']) ? $conn->real_escape_string($input['dietary_tags']) : '';
$image_url = isset($input['image_url']) ? $conn->real_escape_string($input['image_url']) : '';
$is_public = isset($input['is_public']) ? ($input['is_public'] ? 1 : 0) : 0;

// Start transaction
$conn->begin_transaction();

try {
    // Insert recipe (nutrition will be calculated)
    $sql = "INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
            dietary_tags, image_url, is_public, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issiiissi", $user_id, $recipe_name, $description, $prep_time, 
                      $cook_time, $servings, $dietary_tags, $image_url, $is_public);
    $stmt->execute();
    $recipe_id = $conn->insert_id;
    
    // Insert ingredients and calculate nutrition
    $total_calories = 0;
    $total_protein = 0;
    $total_carbs = 0;
    $total_fats = 0;
    
    foreach ($input['ingredients'] as $ingredient) {
        $ingredient_id = intval($ingredient['ingredient_id']);
        $quantity = floatval($ingredient['quantity']);
        $unit = $conn->real_escape_string($ingredient['unit']);
        
        // Insert recipe ingredient
        $sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
        $stmt->execute();
        
        // Get ingredient nutrition info
        $sql = "SELECT calories_per_100g, protein_per_100g, carbs_per_100g, fats_per_100g 
                FROM ingredients WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ingredient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ing_data = $result->fetch_assoc();
        
        // Calculate nutrition (assuming quantity is in grams)
        $multiplier = $quantity / 100;
        $total_calories += $ing_data['calories_per_100g'] * $multiplier;
        $total_protein += $ing_data['protein_per_100g'] * $multiplier;
        $total_carbs += $ing_data['carbs_per_100g'] * $multiplier;
        $total_fats += $ing_data['fats_per_100g'] * $multiplier;
    }
    
    // Update recipe with calculated nutrition
    $sql = "UPDATE recipes SET calories = ?, protein = ?, carbs = ?, fats = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddddi", $total_calories, $total_protein, $total_carbs, $total_fats, $recipe_id);
    $stmt->execute();
    
    // Insert recipe steps
    foreach ($input['steps'] as $index => $step) {
        $step_number = $index + 1;
        $step_description = $conn->real_escape_string($step['description']);
        $step_image = isset($step['image_url']) ? $conn->real_escape_string($step['image_url']) : null;
        
        $sql = "INSERT INTO recipe_steps (recipe_id, step_number, step_description, step_image_url) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $recipe_id, $step_number, $step_description, $step_image);
        $stmt->execute();
    }
    
    // Insert additional images if provided
    if (isset($input['images']) && is_array($input['images'])) {
        foreach ($input['images'] as $index => $img_url) {
            $img_url_escaped = $conn->real_escape_string($img_url);
            $is_primary = ($index === 0) ? 1 : 0;
            
            $sql = "INSERT INTO recipe_images (recipe_id, image_url, is_primary, display_order) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isii", $recipe_id, $img_url_escaped, $is_primary, $index);
            $stmt->execute();
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Recipe added successfully and pending approval',
        'recipe_id' => $recipe_id,
        'nutrition' => [
            'calories' => round($total_calories, 2),
            'protein' => round($total_protein, 2),
            'carbs' => round($total_carbs, 2),
            'fats' => round($total_fats, 2)
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error adding recipe: ' . $e->getMessage()]);
}

closeDBConnection($conn);
?>
