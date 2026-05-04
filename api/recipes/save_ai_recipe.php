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

// Validate required fields
$recipe_name = $input['recipe_name'] ?? '';
$description = $input['description'] ?? '';
$calories = floatval($input['calories'] ?? 0);
$protein = floatval($input['protein'] ?? 0);
$carbs = floatval($input['carbs'] ?? 0);
$fats = floatval($input['fats'] ?? 0);
$prep_time = intval($input['prep_time'] ?? 15);
$cook_time = intval($input['cook_time'] ?? 30);
$servings = intval($input['servings'] ?? 2);
$dietary_tags = $input['dietary_tags'] ?? 'AI-Generated';
$is_public = $input['is_public'] ?? true;
$ingredients = $input['ingredients'] ?? [];
$instructions = $input['instructions'] ?? '';

if (empty($recipe_name)) {
    echo json_encode(['success' => false, 'message' => 'Recipe name is required']);
    exit;
}

$conn = getDBConnection();

try {
    // Insert recipe
    $sql = "INSERT INTO recipes (user_id, recipe_name, description, calories, protein, carbs, fats, 
            prep_time, cook_time, servings, dietary_tags, is_public, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
    
    $stmt = $conn->prepare($sql);
    $is_public_int = $is_public ? 1 : 0;
    $stmt->bind_param("issdddiiissi", 
        $user_id, $recipe_name, $description, $calories, $protein, $carbs, $fats,
        $prep_time, $cook_time, $servings, $dietary_tags, $is_public_int
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save recipe');
    }
    
    $recipe_id = $conn->insert_id;
    
    // Save ingredients if provided
    if (!empty($ingredients) && is_array($ingredients)) {
        $ing_sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit)
                    VALUES (?, ?, ?, ?)";
        $ing_stmt = $conn->prepare($ing_sql);
        
        foreach ($ingredients as $ing) {
            // Try to find ingredient by name
            $ing_name = is_array($ing) ? ($ing['name'] ?? $ing['ingredient_name'] ?? '') : $ing;
            $quantity = is_array($ing) ? floatval($ing['quantity'] ?? 1) : 1;
            $unit = is_array($ing) ? ($ing['unit'] ?? 'g') : 'g';
            
            if (!empty($ing_name)) {
                // Find or create ingredient
                $find_sql = "SELECT id FROM ingredients WHERE ingredient_name LIKE ? LIMIT 1";
                $find_stmt = $conn->prepare($find_sql);
                $search_name = "%$ing_name%";
                $find_stmt->bind_param("s", $search_name);
                $find_stmt->execute();
                $result = $find_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $ingredient_id = $result->fetch_assoc()['id'];
                } else {
                    // Create new ingredient if not found
                    $create_sql = "INSERT INTO ingredients (ingredient_name, category) VALUES (?, 'Other')";
                    $create_stmt = $conn->prepare($create_sql);
                    $create_stmt->bind_param("s", $ing_name);
                    $create_stmt->execute();
                    $ingredient_id = $conn->insert_id;
                }
                
                if ($ingredient_id) {
                    $ing_stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
                    $ing_stmt->execute();
                }
            }
        }
    }
    
    // Save instructions as steps if provided
    if (!empty($instructions)) {
        $step_sql = "INSERT INTO recipe_steps (recipe_id, step_number, instruction)
                     VALUES (?, ?, ?)";
        $step_stmt = $conn->prepare($step_sql);
        
        // If instructions is a string, split by newlines or periods
        if (is_string($instructions)) {
            $steps = preg_split('/\n+|\.\s+/', $instructions);
            $step_number = 1;
            foreach ($steps as $step) {
                $step = trim($step);
                if (!empty($step)) {
                    $step_stmt->bind_param("iis", $recipe_id, $step_number, $step);
                    $step_stmt->execute();
                    $step_number++;
                }
            }
        } elseif (is_array($instructions)) {
            $step_number = 1;
            foreach ($instructions as $step) {
                $step_text = is_array($step) ? ($step['instruction'] ?? $step['step'] ?? '') : $step;
                if (!empty($step_text)) {
                    $step_stmt->bind_param("iis", $recipe_id, $step_number, $step_text);
                    $step_stmt->execute();
                    $step_number++;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'AI recipe saved successfully!',
        'recipe_id' => $recipe_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error saving recipe: ' . $e->getMessage()
    ]);
}

closeDBConnection($conn);
?>
