<?php
/**
 * Auto Generate Meal Plan on Profile Update
 * This endpoint:
 * 1. Generates AI meal plan based on user profile
 * 2. Checks inventory for required ingredients
 * 3. Auto-adds missing ingredients to shopping cart
 * 4. Returns meal plan and cart status
 */

session_start();
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

try {
    require_once '../config/database.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $days = $input['days'] ?? 7; // Default 7-day meal plan
    
    // Get user preferences from form
    $preferences = $input['preferences'] ?? [];
    $cuisine_pref = $preferences['cuisine'] ?? null;
    $diet_pref = $preferences['diet_type'] ?? null;

    $conn = getDBConnection();

    // Step 1: Get user profile
    $profile_sql = "SELECT u.dietary_preference, u.first_name, 
                    p.target_calories, p.target_protein, p.target_carbs, p.target_fats, p.goal
                    FROM users u
                    LEFT JOIN user_profiles p ON u.id = p.user_id
                    WHERE u.id = ?";
    $stmt = $conn->prepare($profile_sql);
    
    if (!$stmt) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    if (!$profile) {
        throw new Exception('User profile not found');
    }

    if (!$profile['target_calories']) {
        echo json_encode([
            'success' => false,
            'message' => 'Please complete your profile with health information first. Go to Profile → Health Information and fill in your details.'
        ]);
        closeDBConnection($conn);
        exit;
    }

    // Step 2: Generate AI meal plan with preferences
    $meal_plan = generateAIMealPlan($conn, $user_id, $profile, $days, $preferences);

    if (!$meal_plan['success']) {
        echo json_encode($meal_plan);
        closeDBConnection($conn);
        exit;
    }
    
    // Validate and adjust meals to stay within nutrition goals
    if (isset($meal_plan['meals'])) {
        $meal_plan['meals'] = validateAndAdjustMealPlan($meal_plan['meals'], $profile);
    }

    // Reset daily stats (delete today's nutrition logs)
    $resetSql = "DELETE FROM nutrition_logs WHERE user_id = ? AND log_date = CURDATE()";
    $resetStmt = $conn->prepare($resetSql);
    $resetStmt->bind_param("i", $user_id);
    $resetStmt->execute();
    $resetStmt->close();

// Step 3: Get all required ingredients from meal plan
$required_ingredients = [];
foreach ($meal_plan['meals'] as $meal) {
    if ($meal['recipe_id'] > 0) {
        $ing_sql = "SELECT ri.ingredient_id, ri.quantity, ri.unit, i.ingredient_name, i.price_per_unit
                    FROM recipe_ingredients ri
                    JOIN ingredients i ON ri.ingredient_id = i.id
                    WHERE ri.recipe_id = ?";
        $ing_stmt = $conn->prepare($ing_sql);
        $ing_stmt->bind_param("i", $meal['recipe_id']);
        $ing_stmt->execute();
        $ingredients = $ing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($ingredients as $ing) {
            $ing_id = $ing['ingredient_id'];
            
            // Filter out generic ingredients
            $generic_ingredients = [
                'main ingredient',
                'mix vegetable', 
                'mixed vegetable',
                'mixed vegetables',
                'vegetables',
                'spices',
                'seasoning',
                'generic'
            ];
            
            $ingredient_name_lower = strtolower(trim($ing['ingredient_name']));
            $is_generic = false;
            
            foreach ($generic_ingredients as $generic) {
                if ($ingredient_name_lower === $generic || strpos($ingredient_name_lower, $generic) !== false) {
                    $is_generic = true;
                    break;
                }
            }
            
            // Skip generic ingredients
            if ($is_generic) {
                continue;
            }
            
            if (!isset($required_ingredients[$ing_id])) {
                $required_ingredients[$ing_id] = [
                    'ingredient_id' => $ing_id,
                    'ingredient_name' => $ing['ingredient_name'],
                    'quantity' => 0,
                    'unit' => $ing['unit'],
                    'price_per_unit' => $ing['price_per_unit']
                ];
            }
            $required_ingredients[$ing_id]['quantity'] += $ing['quantity'];
        }
    }
}

// Step 4: Check user inventory (exclude expired ingredients)
$inventory_sql = "SELECT ingredient_id, quantity, unit, expiry_date 
                  FROM user_inventory 
                  WHERE user_id = ? 
                  AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
$stmt = $conn->prepare($inventory_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inventory_result = $stmt->get_result();

$user_inventory = [];
while ($row = $inventory_result->fetch_assoc()) {
    $user_inventory[$row['ingredient_id']] = $row;
}

// Step 5: Calculate missing ingredients and auto-add to cart
$missing_ingredients = [];
$cart_items_added = 0;

foreach ($required_ingredients as $ing_id => $required) {
    $needed_qty = $required['quantity'];
    
    // Check if user has this ingredient
    if (isset($user_inventory[$ing_id])) {
        $available_qty = $user_inventory[$ing_id]['quantity'];
        $needed_qty = max(0, $required['quantity'] - $available_qty);
    }
    
    // If still need more, add to cart
    if ($needed_qty > 0) {
        $missing_ingredients[] = [
            'ingredient_id' => $ing_id,
            'ingredient_name' => $required['ingredient_name'],
            'quantity' => $needed_qty,
            'unit' => $required['unit'],
            'price' => $required['price_per_unit'] * $needed_qty
        ];
        
        // Auto-add to shopping cart
        $cart_sql = "INSERT INTO shopping_cart (user_id, ingredient_id, quantity, unit, price)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     quantity = quantity + VALUES(quantity),
                     price = price + VALUES(price)";
        $cart_stmt = $conn->prepare($cart_sql);
        $price = $required['price_per_unit'] * $needed_qty;
        $cart_stmt->bind_param("iidsd", $user_id, $ing_id, $needed_qty, $required['unit'], $price);
        
        if ($cart_stmt->execute()) {
            $cart_items_added++;
        }
    }
}

// Step 6: Get cart summary (excluding generic ingredients)
$cart_sql = "SELECT COUNT(*) as item_count, SUM(price) as total_price 
             FROM shopping_cart sc
             JOIN ingredients i ON sc.ingredient_id = i.id
             WHERE sc.user_id = ? 
             AND i.ingredient_name NOT LIKE '%main ingredient%'
             AND i.ingredient_name NOT LIKE '%mix vegetable%'
             AND i.ingredient_name NOT LIKE '%mixed vegetable%'
             AND i.ingredient_name NOT LIKE '%generic%'
             AND i.ingredient_name NOT IN ('main ingredient', 'mix vegetable', 'mixed vegetables', 'vegetables', 'spices', 'seasoning')";
$stmt = $conn->prepare($cart_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_summary = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => 'Meal plan generated and shopping cart updated!',
        'meal_plan' => $meal_plan,
        'missing_ingredients' => $missing_ingredients,
        'cart_items_added' => $cart_items_added,
        'cart_summary' => [
            'total_items' => $cart_summary['item_count'],
            'total_price' => round($cart_summary['total_price'], 2)
        ]
    ]);

    closeDBConnection($conn);

} catch (Exception $e) {
    // Return JSON error instead of HTML with detailed error info
    error_log("Meal Plan Generation Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit;
}

// Helper function to generate AI meal plan
function generateAIMealPlan($conn, $user_id, $profile, $days, $preferences = []) {
    $target_calories = $profile['target_calories'];
    $dietary_pref = $preferences['diet_type'] ?? $profile['dietary_preference'];
    
    // Calculate meal distribution
    $breakfast_cal = $target_calories * 0.30;
    $lunch_cal = $target_calories * 0.40;
    $dinner_cal = $target_calories * 0.30;
    
    $meals = [];
    $plan_name = "AI Generated Plan - " . date('Y-m-d');
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+" . ($days-1) . " days")); // For 1 day, end_date = start_date
    
    // Delete existing meal plans for this date range
    $delete_items_sql = "DELETE mpi FROM meal_plan_items mpi
                        INNER JOIN meal_plans mp ON mpi.meal_plan_id = mp.id
                        WHERE mp.user_id = ? 
                        AND mpi.meal_date BETWEEN ? AND ?";
    $del_stmt = $conn->prepare($delete_items_sql);
    $del_stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $del_stmt->execute();
    
    $delete_plans_sql = "DELETE FROM meal_plans 
                        WHERE user_id = ? 
                        AND start_date <= ? 
                        AND end_date >= ?";
    $del_stmt2 = $conn->prepare($delete_plans_sql);
    $del_stmt2->bind_param("iss", $user_id, $end_date, $start_date);
    $del_stmt2->execute();
    
    // Create meal plan record
    $plan_sql = "INSERT INTO meal_plans (user_id, plan_name, start_date, end_date, total_calories)
                 VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($plan_sql);
    $stmt->bind_param("isssi", $user_id, $plan_name, $start_date, $end_date, $target_calories);
    $stmt->execute();
    $plan_id = $conn->insert_id;
    
    // Generate meals for each day
    for ($day = 0; $day < $days; $day++) {
        $meal_date = date('Y-m-d', strtotime("+" . $day . " days"));
        
        // Breakfast
        $breakfast = getRecipeForMeal($conn, $dietary_pref, $breakfast_cal, 'breakfast', $preferences);
        if ($breakfast) {
            $meals[] = array_merge($breakfast, [
                'meal_type' => 'breakfast',
                'meal_date' => $meal_date,
                'day' => $day + 1
            ]);
            
            // Save to meal_plan_items
            saveMealPlanItem($conn, $plan_id, $breakfast['recipe_id'], 'breakfast', $meal_date);
        }
        
        // Lunch
        $lunch = getRecipeForMeal($conn, $dietary_pref, $lunch_cal, 'lunch', $preferences);
        if ($lunch) {
            $meals[] = array_merge($lunch, [
                'meal_type' => 'lunch',
                'meal_date' => $meal_date,
                'day' => $day + 1
            ]);
            
            saveMealPlanItem($conn, $plan_id, $lunch['recipe_id'], 'lunch', $meal_date);
        }
        
        // Dinner
        $dinner = getRecipeForMeal($conn, $dietary_pref, $dinner_cal, 'dinner', $preferences);
        if ($dinner) {
            $meals[] = array_merge($dinner, [
                'meal_type' => 'dinner',
                'meal_date' => $meal_date,
                'day' => $day + 1
            ]);
            
            saveMealPlanItem($conn, $plan_id, $dinner['recipe_id'], 'dinner', $meal_date);
        }
    }
    
    // Check if we found any meals - if not, use OpenAI to generate
    if (empty($meals)) {
        // Use OpenAI to generate meals
        $ai_result = generateMealsWithOpenAI($conn, $user_id, $profile, $days, $preferences);
        return $ai_result;
    }
    
    return [
        'success' => true,
        'plan_id' => $plan_id,
        'meals' => $meals,
        'days' => $days
    ];
}

function getRecipeForMeal($conn, $dietary_pref, $target_calories, $meal_type, $preferences = []) {
    global $user_id;
    
    $calorie_range = 200;
    $min_cal = $target_calories - $calorie_range;
    $max_cal = $target_calories + $calorie_range;
    
    // Extract preferences
    $cuisine = $preferences['cuisine'] ?? null;
    $spice_level = $preferences['spice_level'] ?? null;
    
    // Check if cuisine_type and spice_level columns exist
    $columns_check = $conn->query("SHOW COLUMNS FROM recipes LIKE 'cuisine_type'");
    $has_cuisine_column = $columns_check->num_rows > 0;
    
    // Build SQL query with preferences
    $select_fields = "r.id as recipe_id, r.recipe_name, r.calories, r.protein, r.carbs, r.fats, 
                      r.prep_time, r.cook_time";
    
    if ($has_cuisine_column) {
        $select_fields .= ", r.cuisine_type, r.spice_level";
    }
    
    $sql = "SELECT $select_fields,
                   COUNT(DISTINCT ui.ingredient_id) as inventory_match_count,
                   COUNT(DISTINCT ri.ingredient_id) as total_ingredients
            FROM recipes r
            JOIN recipe_ingredients ri ON r.id = ri.recipe_id
            LEFT JOIN user_inventory ui ON ri.ingredient_id = ui.ingredient_id AND ui.user_id = ?
            WHERE r.calories BETWEEN ? AND ? 
            AND r.approval_status = 'approved'";
    
    $params = [$user_id, $min_cal, $max_cal];
    $types = "idd";
    
    // Add dietary preference filter
    if ($dietary_pref && $dietary_pref !== 'none') {
        if ($dietary_pref === 'non-vegetarian') {
            // For non-veg, exclude vegan and vegetarian
            $sql .= " AND (r.dietary_tags NOT LIKE '%vegan%' AND r.dietary_tags NOT LIKE '%vegetarian%')";
        } else {
            $sql .= " AND r.dietary_tags LIKE ?";
            $params[] = "%$dietary_pref%";
            $types .= "s";
        }
    }
    
    // Add cuisine filter (only if column exists)
    if ($has_cuisine_column && $cuisine && $cuisine !== 'Mixed') {
        $sql .= " AND r.cuisine_type = ?";
        $params[] = $cuisine;
        $types .= "s";
    }
    
    // Add spice level filter (only if column exists)
    if ($has_cuisine_column && $spice_level) {
        $sql .= " AND r.spice_level = ?";
        $params[] = $spice_level;
        $types .= "s";
    }
    
    $sql .= " GROUP BY r.id
              HAVING total_ingredients > 0
              ORDER BY (COUNT(DISTINCT ui.ingredient_id) / COUNT(DISTINCT ri.ingredient_id)) DESC, RAND()
              LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters dynamically
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        // Remove the extra fields we used for sorting
        unset($recipe['inventory_match_count']);
        unset($recipe['total_ingredients']);
        return $recipe;
    }
    
    return null;
}

function saveMealPlanItem($conn, $plan_id, $recipe_id, $meal_type, $meal_date) {
    $sql = "INSERT INTO meal_plan_items (meal_plan_id, recipe_id, meal_type, meal_date)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $plan_id, $recipe_id, $meal_type, $meal_date);
    $stmt->execute();
}
?>


// Generate meals using OpenAI when no database recipes match
function generateMealsWithOpenAI($conn, $user_id, $profile, $days, $preferences) {
    // Load environment configuration
    require_once __DIR__ . '/../config/env.php';
    
    // Get OpenAI API Key from environment
    $api_key = getOpenAIKey();
    
    $diet_type = $preferences['diet_type'] ?? 'vegetarian';
    $cuisine = $preferences['cuisine'] ?? 'Mixed';
    $spice_level = $preferences['spice_level'] ?? 'mild';

    
    $target_cal = $profile['target_calories'];
    $breakfast_cal = round($target_cal * 0.30);
    $lunch_cal = round($target_cal * 0.40);
    $dinner_cal = round($target_cal * 0.30);
    

    
    $prompt = "Generate a {$days}-day meal plan with these requirements:

**REQUIREMENTS:**
- Cuisine: {$cuisine}
- Diet: {$diet_type}
- Spice Level: {$spice_level}
- Daily Calories: {$target_cal} (Breakfast: {$breakfast_cal}, Lunch: {$lunch_cal}, Dinner: {$dinner_cal})

Provide {$days} days in JSON format:
[
  {
    \"day\": 1,
    \"date\": \"" . date('Y-m-d') . "\",
    \"meals\": [
      {
        \"meal_type\": \"breakfast\",
        \"recipe_name\": \"Recipe Name\",
        \"description\": \"Brief description\",
        \"calories\": {$breakfast_cal},
        \"protein\": 15,
        \"carbs\": 45,
        \"fats\": 10,
        \"prep_time\": 10,
        \"cook_time\": 15,
        \"ingredients\": [
          {\"name\": \"Ingredient\", \"quantity\": 50, \"unit\": \"g\"}
        ],
        \"instructions\": [\"Step 1\", \"Step 2\"]
      }
    ]
  }
]";

    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional chef creating meal plans.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 3000
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        // Fallback to sample meals
        $sample_result = generateSampleMealsForPreferences($profile, $preferences, $days);
        if ($sample_result['success']) {
            return saveAIMealsToDatabase($conn, $user_id, $sample_result['meals'], $profile, $days);
        }
        return $sample_result;
    }
    
    $result = json_decode($response, true);
    $content = $result['choices'][0]['message']['content'] ?? '';
    
    // Extract JSON
    preg_match('/\[.*\]/s', $content, $matches);
    if (empty($matches)) {
        $sample_result = generateSampleMealsForPreferences($profile, $preferences, $days);
        if ($sample_result['success']) {
            return saveAIMealsToDatabase($conn, $user_id, $sample_result['meals'], $profile, $days);
        }
        return $sample_result;
    }
    
    $meals_data = json_decode($matches[0], true);
    
    // Save meals to database
    return saveAIMealsToDatabase($conn, $user_id, $meals_data, $profile, $days);
}

function generateSampleMealsForPreferences($profile, $preferences, $days) {
    $cuisine = $preferences['cuisine'] ?? 'Mixed';
    $diet_type = $preferences['diet_type'] ?? 'vegetarian';
    $target_cal = $profile['target_calories'];
    
    $cuisineRecipes = [
        'Indian' => [
            'breakfast' => ['Masala Dosa', 'Poha', 'Upma', 'Idli Sambar'],
            'lunch' => ['Dal Tadka with Rice', 'Paneer Butter Masala', 'Chole Bhature'],
            'dinner' => ['Palak Paneer', 'Aloo Gobi', 'Vegetable Korma']
        ],
        'Chinese' => [
            'breakfast' => ['Congee', 'Steamed Buns', 'Fried Rice'],
            'lunch' => ['Kung Pao Tofu', 'Vegetable Chow Mein', 'Fried Rice'],
            'dinner' => ['Szechuan Vegetables', 'Hot Pot', 'Stir Fry Noodles']
        ]
    ];
    
    $recipes = $cuisineRecipes[$cuisine] ?? [
        'breakfast' => ['Healthy Breakfast Bowl', 'Oatmeal', 'Smoothie Bowl'],
        'lunch' => ['Grain Bowl', 'Salad Bowl', 'Soup and Bread'],
        'dinner' => ['Stir Fry', 'Curry', 'Pasta']
    ];
    
    $meals = [];
    for ($day = 1; $day <= $days; $day++) {
        $date = date('Y-m-d', strtotime("+" . ($day-1) . " days"));
        $idx = ($day - 1) % 3;
        
        $meals[] = [
            'day' => $day,
            'date' => $date,
            'meals' => [
                [
                    'meal_type' => 'breakfast',
                    'recipe_name' => $recipes['breakfast'][$idx],
                    'description' => $cuisine . ' ' . $diet_type . ' breakfast',
                    'calories' => round($target_cal * 0.30),
                    'protein' => 15,
                    'carbs' => 45,
                    'fats' => 10,
                    'prep_time' => 10,
                    'cook_time' => 15,
                    'ingredients' => [
                        ['name' => 'Main ingredient', 'quantity' => 100, 'unit' => 'g'],
                        ['name' => 'Vegetables', 'quantity' => 50, 'unit' => 'g']
                    ],
                    'instructions' => ['Prepare ingredients', 'Cook', 'Serve hot']
                ],
                [
                    'meal_type' => 'lunch',
                    'recipe_name' => $recipes['lunch'][$idx],
                    'description' => $cuisine . ' ' . $diet_type . ' lunch',
                    'calories' => round($target_cal * 0.40),
                    'protein' => 25,
                    'carbs' => 50,
                    'fats' => 15,
                    'prep_time' => 15,
                    'cook_time' => 25,
                    'ingredients' => [
                        ['name' => 'Rice/Bread', 'quantity' => 150, 'unit' => 'g'],
                        ['name' => 'Protein', 'quantity' => 100, 'unit' => 'g']
                    ],
                    'instructions' => ['Prepare ingredients', 'Cook', 'Serve hot']
                ],
                [
                    'meal_type' => 'dinner',
                    'recipe_name' => $recipes['dinner'][$idx],
                    'description' => $cuisine . ' ' . $diet_type . ' dinner',
                    'calories' => round($target_cal * 0.30),
                    'protein' => 20,
                    'carbs' => 40,
                    'fats' => 12,
                    'prep_time' => 15,
                    'cook_time' => 30,
                    'ingredients' => [
                        ['name' => 'Main ingredient', 'quantity' => 150, 'unit' => 'g'],
                        ['name' => 'Vegetables', 'quantity' => 100, 'unit' => 'g']
                    ],
                    'instructions' => ['Prepare ingredients', 'Cook', 'Serve hot']
                ]
            ]
        ];
    }
    
    // Return the meals data - will be saved by the calling function
    return [
        'success' => true,
        'meals' => $meals,
        'days' => $days
    ];
}

function saveAIMealsToDatabase($conn, $user_id, $meals_data, $profile, $days) {
    $plan_name = "AI Generated Plan - " . date('Y-m-d');
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+" . ($days-1) . " days")); // For 1 day, end_date = start_date
    
    $plan_sql = "INSERT INTO meal_plans (user_id, plan_name, start_date, end_date, total_calories)
                 VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($plan_sql);
    $stmt->bind_param("isssi", $user_id, $plan_name, $start_date, $end_date, $profile['target_calories']);
    $stmt->execute();
    $plan_id = $conn->insert_id;
    
    $all_meals = [];
    
    foreach ($meals_data as $day_data) {
        foreach ($day_data['meals'] as $meal) {
            // Save recipe
            $recipe_id = saveAIRecipe($conn, $meal);
            
            // Save to meal plan
            $meal_sql = "INSERT INTO meal_plan_items (meal_plan_id, recipe_id, meal_type, meal_date)
                        VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($meal_sql);
            $stmt->bind_param("iiss", $plan_id, $recipe_id, $meal['meal_type'], $day_data['date']);
            $stmt->execute();
            
            $all_meals[] = array_merge($meal, [
                'recipe_id' => $recipe_id,
                'meal_date' => $day_data['date'],
                'day' => $day_data['day']
            ]);
        }
    }
    
    return [
        'success' => true,
        'plan_id' => $plan_id,
        'meals' => $all_meals,
        'days' => $days
    ];
}

function saveAIRecipe($conn, $meal) {
    // Check if recipe exists
    $check_sql = "SELECT id FROM recipes WHERE recipe_name = ? LIMIT 1";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $meal['recipe_name']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    
    // Create new recipe
    $instructions = is_array($meal['instructions']) ? implode("\n", $meal['instructions']) : $meal['instructions'];
    
    $recipe_sql = "INSERT INTO recipes (user_id, recipe_name, description, instructions, prep_time, cook_time, 
                   servings, calories, protein, carbs, fats, approval_status, dietary_tags, is_ai_generated, is_public)
                   VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'approved', 'ai-generated', 1, 1)";
    $stmt = $conn->prepare($recipe_sql);
    $stmt->bind_param("isssiiidddd", 
        $user_id,
        $meal['recipe_name'],
        $meal['description'],
        $instructions,
        $meal['prep_time'],
        $meal['cook_time'],
        $meal['calories'],
        $meal['protein'],
        $meal['carbs'],
        $meal['fats']
    );
    $stmt->execute();
    $recipe_id = $conn->insert_id;
    
    // Save ingredients
    if (isset($meal['ingredients']) && is_array($meal['ingredients'])) {
        foreach ($meal['ingredients'] as $ingredient) {
            $ing_name = $ingredient['name'];
            
            // Find or create ingredient
            $ing_check = "SELECT id FROM ingredients WHERE ingredient_name = ? LIMIT 1";
            $ing_stmt = $conn->prepare($ing_check);
            $ing_stmt->bind_param("s", $ing_name);
            $ing_stmt->execute();
            $ing_result = $ing_stmt->get_result();
            
            if ($ing_result->num_rows > 0) {
                $ingredient_id = $ing_result->fetch_assoc()['id'];
            } else {
                $ing_insert = "INSERT INTO ingredients (ingredient_name, category) VALUES (?, 'Other')";
                $ing_stmt = $conn->prepare($ing_insert);
                $ing_stmt->bind_param("s", $ing_name);
                $ing_stmt->execute();
                $ingredient_id = $conn->insert_id;
            }
            
            // Link to recipe
            $link_sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) 
                        VALUES (?, ?, ?, ?)";
            $link_stmt = $conn->prepare($link_sql);
            $link_stmt->bind_param("iids", $recipe_id, $ingredient_id, $ingredient['quantity'], $ingredient['unit']);
            $link_stmt->execute();
        }
    }
    
    return $recipe_id;
}

/**
 * Validate and adjust meal plan to stay within user's nutrition goals
 */
function validateAndAdjustMealPlan($meals_data, $profile) {
    $target_calories = $profile['target_calories'];
    $target_protein = $profile['target_protein'];
    $target_carbs = $profile['target_carbs'];
    $target_fats = $profile['target_fats'];
    
    // Allow 5% buffer for goals (so we don't go over)
    $max_calories = $target_calories * 0.95;
    $max_protein = $target_protein * 0.95;
    $max_carbs = $target_carbs * 0.95;
    $max_fats = $target_fats * 0.95;
    
    // Group meals by day for validation
    $meals_by_day = [];
    foreach ($meals_data as $meal) {
        $day = $meal['day'] ?? 1;
        if (!isset($meals_by_day[$day])) {
            $meals_by_day[$day] = [];
        }
        $meals_by_day[$day][] = $meal;
    }
    
    $adjusted_meals = [];
    
    foreach ($meals_by_day as $day => $day_meals) {
        // Calculate daily totals
        $daily_calories = 0;
        $daily_protein = 0;
        $daily_carbs = 0;
        $daily_fats = 0;
        
        foreach ($day_meals as $meal) {
            $daily_calories += $meal['calories'];
            $daily_protein += $meal['protein'];
            $daily_carbs += $meal['carbs'];
            $daily_fats += $meal['fats'];
        }
        
        // Check if we exceed any goals
        $needs_adjustment = false;
        $adjustment_factor = 1.0;
        
        if ($daily_calories > $max_calories) {
            $adjustment_factor = min($adjustment_factor, $max_calories / $daily_calories);
            $needs_adjustment = true;
        }
        
        if ($target_protein > 0 && $daily_protein > $max_protein) {
            $adjustment_factor = min($adjustment_factor, $max_protein / $daily_protein);
            $needs_adjustment = true;
        }
        
        if ($target_carbs > 0 && $daily_carbs > $max_carbs) {
            $adjustment_factor = min($adjustment_factor, $max_carbs / $daily_carbs);
            $needs_adjustment = true;
        }
        
        if ($target_fats > 0 && $daily_fats > $max_fats) {
            $adjustment_factor = min($adjustment_factor, $max_fats / $daily_fats);
            $needs_adjustment = true;
        }
        
        // Apply adjustment if needed
        if ($needs_adjustment && $adjustment_factor < 1.0) {
            foreach ($day_meals as &$meal) {
                $meal['calories'] = round($meal['calories'] * $adjustment_factor);
                $meal['protein'] = round($meal['protein'] * $adjustment_factor, 1);
                $meal['carbs'] = round($meal['carbs'] * $adjustment_factor, 1);
                $meal['fats'] = round($meal['fats'] * $adjustment_factor, 1);
            }
        }
        
        // Add adjusted meals to result
        foreach ($day_meals as $meal) {
            $adjusted_meals[] = $meal;
        }
    }
    
    return $adjusted_meals;
}
