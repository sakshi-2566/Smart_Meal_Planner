<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID required']);
    exit;
}

$recipe_id = intval($input['recipe_id']);

// Check ownership or admin
$sql = "SELECT user_id FROM recipes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Recipe not found']);
    exit;
}

$recipe = $result->fetch_assoc();
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($recipe['user_id'] != $user_id && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$conn->begin_transaction();

try {
    // Update recipe basic info
    if (isset($input['recipe_name'])) {
        $updates = [];
        $params = [];
        $types = '';
        
        if (isset($input['recipe_name'])) {
            $updates[] = "recipe_name = ?";
            $params[] = $input['recipe_name'];
            $types .= 's';
        }
        if (isset($input['description'])) {
            $updates[] = "description = ?";
            $params[] = $input['description'];
            $types .= 's';
        }
        if (isset($input['prep_time'])) {
            $updates[] = "prep_time = ?";
            $params[] = intval($input['prep_time']);
            $types .= 'i';
        }
        if (isset($input['cook_time'])) {
            $updates[] = "cook_time = ?";
            $params[] = intval($input['cook_time']);
            $types .= 'i';
        }
        if (isset($input['servings'])) {
            $updates[] = "servings = ?";
            $params[] = intval($input['servings']);
            $types .= 'i';
        }
        if (isset($input['dietary_tags'])) {
            $updates[] = "dietary_tags = ?";
            $params[] = $input['dietary_tags'];
            $types .= 's';
        }
        if (isset($input['image_url'])) {
            $updates[] = "image_url = ?";
            $params[] = $input['image_url'];
            $types .= 's';
        }
        if (isset($input['is_public'])) {
            $updates[] = "is_public = ?";
            $params[] = $input['is_public'] ? 1 : 0;
            $types .= 'i';
        }
        
        // Reset approval status when edited
        $updates[] = "approval_status = 'pending'";
        
        if (count($updates) > 0) {
            $sql = "UPDATE recipes SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $recipe_id;
            $types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
    }
    
    // Update ingredients if provided
    if (isset($input['ingredients'])) {
        // Delete existing ingredients
        $sql = "DELETE FROM recipe_ingredients WHERE recipe_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $recipe_id);
        $stmt->execute();
        
        // Recalculate nutrition
        $total_calories = 0;
        $total_protein = 0;
        $total_carbs = 0;
        $total_fats = 0;
        
        foreach ($input['ingredients'] as $ingredient) {
            $ingredient_id = intval($ingredient['ingredient_id']);
            $quantity = floatval($ingredient['quantity']);
            $unit = $ingredient['unit'];
            
            $sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
            $stmt->execute();
            
            // Get nutrition
            $sql = "SELECT calories_per_100g, protein_per_100g, carbs_per_100g, fats_per_100g 
                    FROM ingredients WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $ingredient_id);
            $stmt->execute();
            $ing_data = $stmt->get_result()->fetch_assoc();
            
            $multiplier = $quantity / 100;
            $total_calories += $ing_data['calories_per_100g'] * $multiplier;
            $total_protein += $ing_data['protein_per_100g'] * $multiplier;
            $total_carbs += $ing_data['carbs_per_100g'] * $multiplier;
            $total_fats += $ing_data['fats_per_100g'] * $multiplier;
        }
        
        // Update nutrition
        $sql = "UPDATE recipes SET calories = ?, protein = ?, carbs = ?, fats = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddddi", $total_calories, $total_protein, $total_carbs, $total_fats, $recipe_id);
        $stmt->execute();
    }
    
    // Update steps if provided
    if (isset($input['steps'])) {
        // Delete existing steps
        $sql = "DELETE FROM recipe_steps WHERE recipe_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $recipe_id);
        $stmt->execute();
        
        // Insert new steps
        foreach ($input['steps'] as $index => $step) {
            $step_number = $index + 1;
            $step_description = $step['description'];
            $step_image = isset($step['image_url']) ? $step['image_url'] : null;
            
            $sql = "INSERT INTO recipe_steps (recipe_id, step_number, step_description, step_image_url) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $recipe_id, $step_number, $step_description, $step_image);
            $stmt->execute();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Recipe updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating recipe: ' . $e->getMessage()]);
}

closeDBConnection($conn);
?>
