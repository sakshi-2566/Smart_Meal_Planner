<?php
session_start();
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once '../../config/database.php';

    $recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;

    if (!$recipe_id) {
        echo json_encode(['success' => false, 'message' => 'Recipe ID required']);
        exit;
    }

    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get recipe details
    $sql = "SELECT r.*, u.first_name, u.last_name
            FROM recipes r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $recipe = $stmt->get_result()->fetch_assoc();

    if (!$recipe) {
        echo json_encode(['success' => false, 'message' => 'Recipe not found']);
        closeDBConnection($conn);
        exit;
    }

    // Get ingredients
    $recipe['ingredients'] = [];
    try {
        $ing_sql = "SELECT ri.*, i.ingredient_name, i.category
                    FROM recipe_ingredients ri
                    JOIN ingredients i ON ri.ingredient_id = i.id
                    WHERE ri.recipe_id = ?
                    ORDER BY ri.id";
        $stmt = $conn->prepare($ing_sql);
        if ($stmt) {
            $stmt->bind_param("i", $recipe_id);
            $stmt->execute();
            $recipe['ingredients'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        // Table might not exist, continue with empty array
    }

    // Get steps
    $recipe['steps'] = [];
    try {
        $step_sql = "SELECT * FROM recipe_steps 
                     WHERE recipe_id = ? 
                     ORDER BY step_number";
        $stmt = $conn->prepare($step_sql);
        if ($stmt) {
            $stmt->bind_param("i", $recipe_id);
            $stmt->execute();
            $recipe['steps'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        // Table might not exist, continue with empty array
    }

    // Get images
    $recipe['images'] = [];
    try {
        $img_sql = "SELECT * FROM recipe_images 
                    WHERE recipe_id = ? 
                    ORDER BY display_order";
        $stmt = $conn->prepare($img_sql);
        if ($stmt) {
            $stmt->bind_param("i", $recipe_id);
            $stmt->execute();
            $recipe['images'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        // Table might not exist, continue with empty array
    }

    echo json_encode([
        'success' => true,
        'recipe' => $recipe
    ]);

    closeDBConnection($conn);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
