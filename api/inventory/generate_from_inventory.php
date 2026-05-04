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

// Get user profile for dietary preferences
$profile_sql = "SELECT dietary_preference, target_calories FROM users WHERE id = ?";
$stmt = $conn->prepare($profile_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get user's inventory with quantities (exclude expired ingredients)
$inventory_sql = "SELECT ui.*, i.ingredient_name, i.category 
                  FROM user_inventory ui
                  JOIN ingredients i ON ui.ingredient_id = i.id
                  WHERE ui.user_id = ? 
                  AND (ui.expiry_date IS NULL OR ui.expiry_date >= CURDATE())";
$stmt = $conn->prepare($inventory_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inventory_result = $stmt->get_result();

$user_inventory = [];
while ($row = $inventory_result->fetch_assoc()) {
    $user_inventory[$row['ingredient_id']] = $row;
}

// Find recipes that match available ingredients
// Strategy: Find recipes where user has at least 60% of required ingredients
$recipes = [];

// Get all approved recipes with their ingredients
$recipe_sql = "SELECT DISTINCT r.*, 
               COUNT(DISTINCT ri.ingredient_id) as total_ingredients,
               GROUP_CONCAT(DISTINCT i.ingredient_name SEPARATOR ', ') as ingredient_list
               FROM recipes r
               JOIN recipe_ingredients ri ON r.id = ri.recipe_id
               JOIN ingredients i ON ri.ingredient_id = i.id
               WHERE r.approval_status = 'approved'
               GROUP BY r.id";

$recipe_result = $conn->query($recipe_sql);

while ($recipe = $recipe_result->fetch_assoc()) {
    $recipe_id = $recipe['id'];
    
    // Get detailed ingredients for this recipe
    $ing_sql = "SELECT ri.*, i.ingredient_name, i.category 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.id
                WHERE ri.recipe_id = ?";
    $ing_stmt = $conn->prepare($ing_sql);
    $ing_stmt->bind_param("i", $recipe_id);
    $ing_stmt->execute();
    $recipe_ingredients = $ing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Check ingredient availability
    $available_count = 0;
    $missing_ingredients = [];
    $available_ingredients = [];
    
    foreach ($recipe_ingredients as $ing) {
        $ing_id = $ing['ingredient_id'];
        
        if (isset($user_inventory[$ing_id])) {
            $available_qty = $user_inventory[$ing_id]['quantity'];
            $required_qty = $ing['quantity'];
            
            if ($available_qty >= $required_qty) {
                $available_count++;
                $available_ingredients[] = $ing['ingredient_name'];
            } else {
                $missing_ingredients[] = $ing['ingredient_name'];
            }
        } else {
            $missing_ingredients[] = $ing['ingredient_name'];
        }
    }
    
    $total_ingredients = count($recipe_ingredients);
    $match_percentage = $total_ingredients > 0 ? ($available_count / $total_ingredients) * 100 : 0;
    
    // Include recipes with at least 60% ingredient match
    if ($match_percentage >= 60) {
        $recipe['match_percentage'] = round($match_percentage, 1);
        $recipe['available_ingredients'] = $available_ingredients;
        $recipe['missing_ingredients'] = $missing_ingredients;
        $recipe['ingredients'] = $recipe_ingredients;
        $recipes[] = $recipe;
    }
}

// Sort by match percentage (highest first)
usort($recipes, function($a, $b) {
    return $b['match_percentage'] <=> $a['match_percentage'];
});

// Limit to top 10 matches
$recipes = array_slice($recipes, 0, 10);

echo json_encode([
    'success' => true,
    'recipes' => $recipes,
    'total_found' => count($recipes),
    'message' => count($recipes) > 0 
        ? "Found " . count($recipes) . " recipes you can make!" 
        : "No recipes found. Try adding more ingredients to your inventory."
]);

closeDBConnection($conn);
?>
