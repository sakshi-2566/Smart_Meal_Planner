<?php
/**
 * AI-Powered Meal Plan Generator with User Preferences
 * Uses OpenAI API to generate personalized meal plans
 */

session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120); // Allow 2 minutes for OpenAI API

try {
    require_once '../config/database.php';
    require_once 'utils/unit_converter.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get user preferences from form
    $preferences = $input['preferences'] ?? [];
    $days = $input['days'] ?? 7;
    
    $diet_type = $preferences['diet_type'] ?? 'vegetarian';
    $cuisine = $preferences['cuisine'] ?? 'Mixed';
    $spice_level = $preferences['spice_level'] ?? 'mild';

    $meal_types = $preferences['meal_types'] ?? ['main-course'];
    
    $conn = getDBConnection();
    
    // Get user profile for calorie targets
    $profile_sql = "SELECT u.first_name, p.target_calories, p.target_protein, p.target_carbs, p.target_fats, p.goal
                    FROM users u
                    LEFT JOIN user_profiles p ON u.id = p.user_id
                    WHERE u.id = ?";
    $stmt = $conn->prepare($profile_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    
    if (!$profile || !$profile['target_calories']) {
        echo json_encode([
            'success' => false,
            'message' => 'Please complete your health profile first'
        ]);
        exit;
    }
    
    // Get user's inventory (exclude expired ingredients)
    $inventory_sql = "SELECT i.ingredient_name, ui.quantity, ui.unit, ui.expiry_date
                      FROM user_inventory ui
                      JOIN ingredients i ON ui.ingredient_id = i.id
                      WHERE ui.user_id = ? 
                      AND (ui.expiry_date IS NULL OR ui.expiry_date >= CURDATE())";
    $stmt = $conn->prepare($inventory_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
    
    $inventory_items = [];
    while ($row = $inventory_result->fetch_assoc()) {
        $inventory_items[] = $row['ingredient_name'] . ' (' . $row['quantity'] . $row['unit'] . ')';
    }
    
    // Generate AI meal plan
    try {
        $ai_response = generateAIMealPlanWithOpenAI(
            $profile,
            $diet_type,
            $cuisine,
            $spice_level,
            $meal_types,
            $inventory_items,
            $days
        );
        
        if (!$ai_response['success']) {
            // Fallback to sample meals
            $ai_response = generateSampleMeals($profile, $diet_type, $cuisine, $days);
        }
        
        // Validate and adjust meals to stay within nutrition goals
        if ($ai_response['success'] && isset($ai_response['meals'])) {
            $ai_response['meals'] = validateAndAdjustMealPlan($ai_response['meals'], $profile);
        }
    } catch (Exception $e) {
        // If OpenAI fails, use sample meals
        $ai_response = generateSampleMeals($profile, $diet_type, $cuisine, $days);
        
        // Validate sample meals too
        if ($ai_response['success'] && isset($ai_response['meals'])) {
            $ai_response['meals'] = validateAndAdjustMealPlan($ai_response['meals'], $profile);
        }
    }
    
    // Save meal plan to database
    $meals_data = $ai_response['meals'] ?? [];
    $meal_plan = saveAIMealPlan($conn, $user_id, $meals_data, $profile['target_calories'], $days);
    
    // Reset daily stats (delete today's nutrition logs)
    $resetSql = "DELETE FROM nutrition_logs WHERE user_id = ? AND log_date = CURDATE()";
    $resetStmt = $conn->prepare($resetSql);
    $resetStmt->bind_param("i", $user_id);
    $resetStmt->execute();
    $resetStmt->close();
    
    // Check inventory and add missing ingredients to cart
    try {
        $cart_result = processIngredients($conn, $user_id, $meals_data);
    } catch (Exception $e) {
        // If cart processing fails, continue with empty cart result
        $cart_result = [
            'items_added' => 0,
            'missing_ingredients' => [],
            'summary' => ['total_items' => 0, 'total_price' => 0]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'AI meal plan generated successfully!',
        'meal_plan' => $meal_plan,
        'cart_items_added' => $cart_result['items_added'] ?? 0,
        'missing_ingredients' => $cart_result['missing_ingredients'] ?? [],
        'used_ingredients' => $cart_result['used_ingredients'] ?? [],
        'cart_summary' => $cart_result['summary'] ?? ['total_items' => 0, 'total_price' => 0]
    ]);
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function generateAIMealPlanWithOpenAI($profile, $diet_type, $cuisine, $spice_level, $meal_types, $inventory, $days) {
    // Load environment configuration
    require_once __DIR__ . '/../config/env.php';
    
    // Get OpenAI API Key from environment
    $api_key = getOpenAIKey();
    
    // If no API key, return sample meals
    if (empty($api_key) || $api_key === 'YOUR_OPENAI_API_KEY_HERE') {
        return generateSampleMeals($profile, $diet_type, $cuisine, $days);
    }
    
    $target_cal = $profile['target_calories'];
    $breakfast_cal = round($target_cal * 0.30);
    $lunch_cal = round($target_cal * 0.40);
    $dinner_cal = round($target_cal * 0.30);
    
    $inventory_text = empty($inventory) ? 'No inventory items' : implode(', ', $inventory);
    $inventory_text = empty($inventory) ? 'No inventory items' : implode(', ', $inventory);
    $meal_types_text = implode(', ', $meal_types);
    
    // Determine strict meal structure based on selected types
    $meal_structure = [];
    $num_types = count($meal_types);
    
    if ($num_types === 1) {
        // All meals same type
        $meal_structure = [$meal_types[0], $meal_types[0], $meal_types[0]];
    } elseif ($num_types === 3) {
        // Distribute strictly: Starter -> Main -> Dessert (or whatever order provided, usually sorted by UI)
        // We'll define a logical order: Starter, Main Course, Dessert
        $ordered_types = [];
        if (in_array('starter', $meal_types)) $ordered_types[] = 'starter';
        if (in_array('main-course', $meal_types)) $ordered_types[] = 'main-course';
        if (in_array('dessert', $meal_types)) $ordered_types[] = 'dessert';
        
        // Fill if any missing from standard logic (fallback)
        foreach ($meal_types as $mt) {
            if (!in_array($mt, $ordered_types)) $ordered_types[] = $mt;
        }
        $meal_structure = array_slice($ordered_types, 0, 3);
    } elseif ($num_types === 2) {
        // Distribute 2 types across 3 meals
        // e.g. Starter, Main, Main OR Main, Main, Dessert
         $ordered_types = [];
        if (in_array('starter', $meal_types)) $ordered_types[] = 'starter';
        if (in_array('main-course', $meal_types)) $ordered_types[] = 'main-course';
        if (in_array('dessert', $meal_types)) $ordered_types[] = 'dessert';
        
         // Fill remaining
         foreach ($meal_types as $mt) {
            if (!in_array($mt, $ordered_types)) $ordered_types[] = $mt;
        }

        // Logic: Type 1, Type 2, Type 1 (or Type 2 depending on logical flow)
        // If Starter & Main: Starter, Main, Main
        // If Main & Dessert: Main, Main, Dessert
        // If Starter & Dessert: Starter, Dessert, Starter? No, likely Starter, Main(missing), Dessert. 
        // But we MUST use selected types. So: Starter, Starter, Dessert.
        
        $t1 = $ordered_types[0] ?? $meal_types[0];
        $t2 = $ordered_types[1] ?? $meal_types[1];
        
        if ($t1 === 'starter' && $t2 === 'main-course') {
            $meal_structure = ['starter', 'main-course', 'main-course'];
        } elseif ($t1 === 'main-course' && $t2 === 'dessert') {
            $meal_structure = ['main-course', 'main-course', 'dessert'];
        } else {
             $meal_structure = [$t1, $t2, $t1];
        }
    } else {
        // Default fallback
        $meal_structure = ['breakfast', 'lunch', 'dinner'];
    }

    $meal_structure_text = implode(', ', $meal_structure);

    $prompt = "You are a professional chef specializing in {$cuisine} cuisine. Generate a {$days}-day meal plan with STRICT adherence to these requirements:
 
 **CRITICAL REQUIREMENTS:**
 1. **Cuisine Type:** ALL recipes MUST be authentic {$cuisine} cuisine dishes
 2. **Dietary Preference:** STRICTLY {$diet_type} only
 3. **Spice Level:** {$spice_level} spice level
 4. **Meal Structure (STRICT):** You must generate exactly 3 meals per day, corresponding EXACTLY to these types in order: {$meal_structure_text}.
    - Meal 1: {$meal_structure[0]}
    - Meal 2: {$meal_structure[1]}
    - Meal 3: {$meal_structure[2]}

**Nutritional Targets (DO NOT EXCEED):**
- Daily Total: {$target_cal} calories (MAXIMUM - do not go over)
- Target Protein: {$profile['target_protein']}g (MAXIMUM - do not exceed)
- Target Carbs: {$profile['target_carbs']}g (MAXIMUM - do not exceed)  
- Target Fats: {$profile['target_fats']}g (MAXIMUM - do not exceed)
- Distribute calories appropriately across the 3 meals.

**IMPORTANT NUTRITION RULES:**
- Each meal should be 5-10% UNDER the target to ensure we don't exceed daily goals
- If a meal would put the daily total over the limit, reduce portion sizes
- Prioritize staying within calorie and macro limits over large portions

**Available Inventory (prioritize these):** {$inventory_text}

**IMPORTANT:** 
- Use ONLY {$cuisine} recipes and ingredients
- Recipe names must be in {$cuisine} style
- If {$diet_type} is vegetarian, use NO meat, fish, or eggs
- Match the {$spice_level} spice level accurately
- NEVER exceed the daily nutrition targets

Provide {$days} days of meals in JSON format:
[
  {
    \"day\": 1,
    \"date\": \"YYYY-MM-DD\",
    \"meals\": [
      {
        \"meal_type\": \"{$meal_structure[0]}\",
        \"recipe_name\": \"Recipe Name\",
        \"description\": \"Brief description\",
        \"calories\": 500,
        \"protein\": 20,
        \"carbs\": 60,
        \"fats\": 15,
        \"prep_time\": 10,
        \"cook_time\": 20,
        \"servings\": 1,
        \"dietary_tags\": \"{$diet_type}, {$cuisine}, {$spice_level}\",
        \"ingredients\": [
          {\"name\": \"Ingredient\", \"quantity\": 100, \"unit\": \"g\"}
        ],
        \"instructions\": [
          \"Step 1\", \"Step 2\"
        ]
      },
      {
        \"meal_type\": \"{$meal_structure[1]}\",
        \"recipe_name\": \"Recipe Name\",
        ...
      },
      {
        \"meal_type\": \"{$meal_structure[2]}\",
        \"recipe_name\": \"Recipe Name\",
        ...
      }
    ]
  }
]

**IMPORTANT:** 
- Include detailed step-by-step instructions
- Include dietary_tags field with relevant tags
- Prioritize using ingredients from the available inventory
- Make sure recipes match the cuisine and spice level
- ENSURE TOTAL DAILY NUTRITION STAYS WITHIN LIMITS";

    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional nutritionist and chef who creates personalized meal plans.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        // Log the error for debugging
        error_log("OpenAI API Error - HTTP Code: $http_code, Response: $response, Curl Error: $curl_error");
        return ['success' => false, 'message' => 'AI API error. HTTP Code: ' . $http_code];
    }
    
    $result = json_decode($response, true);
    $content = $result['choices'][0]['message']['content'] ?? '';
    
    // Extract JSON from response
    preg_match('/\[.*\]/s', $content, $matches);
    if (empty($matches)) {
        return generateSampleMeals($profile, $diet_type, $cuisine, $days);
    }
    
    $meals_data = json_decode($matches[0], true);
    
    return [
        'success' => true,
        'meals' => $meals_data
    ];
}

function generateSampleMeals($profile, $diet_type, $cuisine, $days) {
    // Fallback: Generate sample meals based on preferences
    $meals = [];
    $target_cal = $profile['target_calories'];
    
    // Cuisine-specific meal names
    $cuisineRecipes = [
        'Indian' => [
            'breakfast' => ['Masala Dosa', 'Poha', 'Upma', 'Idli Sambar', 'Paratha with Curd'],
            'lunch' => ['Dal Tadka with Rice', 'Paneer Butter Masala', 'Chole Bhature', 'Biryani', 'Rajma Chawal'],
            'dinner' => ['Palak Paneer', 'Aloo Gobi', 'Vegetable Korma', 'Kadhi Pakora', 'Mixed Vegetable Curry']
        ],
        'Chinese' => [
            'breakfast' => ['Congee', 'Steamed Buns', 'Fried Rice', 'Noodle Soup', 'Spring Rolls'],
            'lunch' => ['Kung Pao Tofu', 'Vegetable Chow Mein', 'Fried Rice', 'Mapo Tofu', 'Sweet and Sour Vegetables'],
            'dinner' => ['Szechuan Vegetables', 'Hot Pot', 'Stir Fry Noodles', 'Vegetable Dumplings', 'Buddha Bowl']
        ],
        'Italian' => [
            'breakfast' => ['Bruschetta', 'Frittata', 'Focaccia', 'Caprese Salad', 'Italian Omelette'],
            'lunch' => ['Pasta Primavera', 'Margherita Pizza', 'Risotto', 'Minestrone Soup', 'Penne Arrabbiata'],
            'dinner' => ['Eggplant Parmesan', 'Mushroom Risotto', 'Vegetable Lasagna', 'Gnocchi', 'Caprese Pasta']
        ],
        'Mexican' => [
            'breakfast' => ['Breakfast Burrito', 'Huevos Rancheros', 'Chilaquiles', 'Breakfast Tacos', 'Mexican Omelette'],
            'lunch' => ['Bean Burrito', 'Vegetable Fajitas', 'Quesadilla', 'Taco Salad', 'Enchiladas'],
            'dinner' => ['Black Bean Tacos', 'Vegetable Enchiladas', 'Mexican Rice Bowl', 'Stuffed Peppers', 'Burrito Bowl']
        ]
    ];
    
    // Default to Mixed if cuisine not found
    $recipes = $cuisineRecipes[$cuisine] ?? [
        'breakfast' => ['Healthy Breakfast Bowl', 'Oatmeal', 'Smoothie Bowl', 'Toast with Avocado', 'Granola Bowl'],
        'lunch' => ['Grain Bowl', 'Salad Bowl', 'Soup and Bread', 'Wrap', 'Buddha Bowl'],
        'dinner' => ['Stir Fry', 'Curry', 'Pasta', 'Rice Bowl', 'Vegetable Medley']
    ];
    
    for ($day = 1; $day <= $days; $day++) {
        $date = date('Y-m-d', strtotime("+" . $day . " days"));
        
        // Randomly select recipes for variety
        $breakfast_idx = ($day - 1) % count($recipes['breakfast']);
        $lunch_idx = ($day - 1) % count($recipes['lunch']);
        $dinner_idx = ($day - 1) % count($recipes['dinner']);
        
        $meals[] = [
            'day' => $day,
            'date' => $date,
            'meals' => [
                [
                    'meal_type' => 'breakfast',
                    'recipe_name' => $recipes['breakfast'][$breakfast_idx],
                    'description' => 'Authentic ' . $cuisine . ' ' . $diet_type . ' breakfast',
                    'calories' => round($target_cal * 0.30),
                    'protein' => 15,
                    'carbs' => 45,
                    'fats' => 10,
                    'prep_time' => 10,
                    'cook_time' => 15,
                    'ingredients' => [
                        ['name' => 'Main ingredient', 'quantity' => 100, 'unit' => 'g'],
                        ['name' => 'Vegetables', 'quantity' => 50, 'unit' => 'g'],
                        ['name' => 'Spices', 'quantity' => 5, 'unit' => 'g'],
                        ['name' => 'Oil', 'quantity' => 10, 'unit' => 'ml']
                    ],
                    'instructions' => [
                        'Gather all ingredients for ' . $recipes['breakfast'][$breakfast_idx],
                        'Prepare and chop vegetables',
                        'Heat oil in a pan',
                        'Add spices and cook',
                        'Add main ingredients',
                        'Cook until done',
                        'Serve hot with accompaniments'
                    ]
                ],
                [
                    'meal_type' => 'lunch',
                    'recipe_name' => $recipes['lunch'][$lunch_idx],
                    'description' => 'Traditional ' . $cuisine . ' ' . $diet_type . ' lunch',
                    'calories' => round($target_cal * 0.40),
                    'protein' => 25,
                    'carbs' => 50,
                    'fats' => 15,
                    'prep_time' => 15,
                    'cook_time' => 25,
                    'ingredients' => [
                        ['name' => 'Rice/Bread', 'quantity' => 150, 'unit' => 'g'],
                        ['name' => 'Protein source', 'quantity' => 100, 'unit' => 'g'],
                        ['name' => 'Mixed vegetables', 'quantity' => 100, 'unit' => 'g'],
                        ['name' => 'Spices and herbs', 'quantity' => 10, 'unit' => 'g'],
                        ['name' => 'Cooking oil', 'quantity' => 15, 'unit' => 'ml']
                    ],
                    'instructions' => [
                        'Prepare all ingredients for ' . $recipes['lunch'][$lunch_idx],
                        'Wash and chop vegetables',
                        'Prepare protein source',
                        'Heat oil in a large pan or wok',
                        'Add spices and aromatics',
                        'Add vegetables and protein',
                        'Cook with ' . $cuisine . ' style seasoning',
                        'Simmer until fully cooked',
                        'Garnish with fresh herbs',
                        'Serve hot with rice or bread'
                    ]
                ],
                [
                    'meal_type' => 'dinner',
                    'recipe_name' => $recipes['dinner'][$dinner_idx],
                    'description' => 'Delicious ' . $cuisine . ' ' . $diet_type . ' dinner',
                    'calories' => round($target_cal * 0.30),
                    'protein' => 20,
                    'carbs' => 40,
                    'fats' => 12,
                    'prep_time' => 15,
                    'cook_time' => 30,
                    'ingredients' => [
                        ['name' => 'Main ingredient', 'quantity' => 150, 'unit' => 'g'],
                        ['name' => 'Vegetables', 'quantity' => 100, 'unit' => 'g'],
                        ['name' => 'Sauce/Gravy base', 'quantity' => 50, 'unit' => 'ml'],
                        ['name' => 'Spices', 'quantity' => 10, 'unit' => 'g'],
                        ['name' => 'Cooking oil', 'quantity' => 15, 'unit' => 'ml'],
                        ['name' => 'Garnish', 'quantity' => 10, 'unit' => 'g']
                    ],
                    'instructions' => [
                        'Gather ingredients for ' . $recipes['dinner'][$dinner_idx],
                        'Prep and marinate main ingredient if needed',
                        'Chop all vegetables',
                        'Heat oil in a cooking pot',
                        'Add whole spices and let them crackle',
                        'Add chopped onions and cook until golden',
                        'Add ginger-garlic paste',
                        'Add main ingredient and vegetables',
                        'Add sauce/gravy and spices',
                        'Cook covered on medium heat',
                        'Simmer until everything is cooked through',
                        'Garnish with fresh herbs',
                        'Serve hot with rice, bread, or noodles'
                    ]
                ]
            ]
        ];
    }
    
    return ['success' => true, 'meals' => $meals];
}

function saveAIMealPlan($conn, $user_id, $meals_data, $target_calories, $days) {
    // Create meal plan record
    $plan_name = "AI Generated Plan - " . date('Y-m-d');
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+" . ($days-1) . " days"));
    
    $plan_sql = "INSERT INTO meal_plans (user_id, plan_name, start_date, end_date, total_calories)
                 VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($plan_sql);
    $stmt->bind_param("isssi", $user_id, $plan_name, $start_date, $end_date, $target_calories);
    $stmt->execute();
    $plan_id = $conn->insert_id;
    
    $all_meals = [];
    
    foreach ($meals_data as $day_data) {
        foreach ($day_data['meals'] as $meal) {
            // Save recipe if it doesn't exist
            $recipe_id = saveRecipeIfNotExists($conn, $meal);
            
            // Save to meal plan items
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

function saveRecipeIfNotExists($conn, $meal) {
    // Get current user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Check if recipe exists for this user (to avoid duplicates per user)
    $check_sql = "SELECT id FROM recipes WHERE recipe_name = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("si", $meal['recipe_name'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $recipe_id = $result->fetch_assoc()['id'];
        // Even if recipe exists, we should ensure it has ingredients if provided in the AI response
        saveIngredients($conn, $recipe_id, $meal['ingredients'] ?? []);
        return $recipe_id;
    }
    
    // Create new recipe with user ownership
    $instructions = is_array($meal['instructions']) ? implode("\n", $meal['instructions']) : $meal['instructions'];
    $servings = $meal['servings'] ?? 1;
    
    // Get dietary tags from meal or use default
    $dietary_tags = $meal['dietary_tags'] ?? 'ai-generated';
    
    $recipe_sql = "INSERT INTO recipes (user_id, recipe_name, description, instructions, prep_time, cook_time, 
                   servings, calories, protein, carbs, fats, approval_status, dietary_tags, is_ai_generated, is_public)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, 1, 1)";
    $stmt = $conn->prepare($recipe_sql);
    $stmt->bind_param("isssiiidddds", 
        $user_id,
        $meal['recipe_name'],
        $meal['description'],
        $instructions,
        $meal['prep_time'],
        $meal['cook_time'],
        $servings,
        $meal['calories'],
        $meal['protein'],
        $meal['carbs'],
        $meal['fats'],
        $dietary_tags
    );
    $stmt->execute();
    $recipe_id = $conn->insert_id;
    
    // Save step-by-step instructions to recipe_steps table if it exists
    if (isset($meal['instructions']) && is_array($meal['instructions'])) {
        $step_number = 1;
        foreach ($meal['instructions'] as $instruction) {
            $step_sql = "INSERT INTO recipe_steps (recipe_id, step_number, step_description) 
                        VALUES (?, ?, ?)";
            $step_stmt = $conn->prepare($step_sql);
            if ($step_stmt) {
                $step_stmt->bind_param("iis", $recipe_id, $step_number, $instruction);
                $step_stmt->execute();
                $step_number++;
            }
        }
    }
    
    // Save ingredients if provided
    saveIngredients($conn, $recipe_id, $meal['ingredients'] ?? []);
    
    return $recipe_id;
}

function saveIngredients($conn, $recipe_id, $ingredients_data) {
    if (!is_array($ingredients_data) || count($ingredients_data) === 0) {
        return;
    }
    
    // First, clear existing ingredients for this recipe to avoid duplicates 
    // (Only if it's an AI generated/user owned recipe we are updating)
    $delete_sql = "DELETE FROM recipe_ingredients WHERE recipe_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $recipe_id);
    $delete_stmt->execute();

    foreach ($ingredients_data as $ingredient) {
        // Find or create ingredient
        $ing_name = $ingredient['name'];
        $ing_check = "SELECT id FROM ingredients WHERE ingredient_name = ? LIMIT 1";
        $ing_stmt = $conn->prepare($ing_check);
        $ing_stmt->bind_param("s", $ing_name);
        $ing_stmt->execute();
        $ing_result = $ing_stmt->get_result();
        
        if ($ing_result->num_rows > 0) {
            $ingredient_id = $ing_result->fetch_assoc()['id'];
        } else {
            // Create new ingredient
            $ing_insert = "INSERT INTO ingredients (ingredient_name, category) VALUES (?, 'Other')";
            $ing_stmt = $conn->prepare($ing_insert);
            $ing_stmt->bind_param("s", $ing_name);
            $ing_stmt->execute();
            $ingredient_id = $conn->insert_id;
        }
        
        // Link ingredient to recipe
        $link_sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) 
                    VALUES (?, ?, ?, ?)";
        $link_stmt = $conn->prepare($link_sql);
        $link_stmt->bind_param("iids", $recipe_id, $ingredient_id, $ingredient['quantity'], $ingredient['unit']);
        $link_stmt->execute();
    }
}

function processIngredients($conn, $user_id, $meals_data) {
    // Step 1: Get all required ingredients from all meals
    $required_ingredients = [];
    
    foreach ($meals_data as $day_data) {
        foreach ($day_data['meals'] as $meal) {
            if (isset($meal['ingredients']) && is_array($meal['ingredients'])) {
                foreach ($meal['ingredients'] as $ingredient) {
                    $ing_name = $ingredient['name'];
                    
                    // Find ingredient ID
                    $ing_sql = "SELECT id, price_per_unit FROM ingredients WHERE ingredient_name = ? LIMIT 1";
                    $stmt = $conn->prepare($ing_sql);
                    $stmt->bind_param("s", $ing_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $ing_data = $result->fetch_assoc();
                        $ing_id = $ing_data['id'];
                        
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
                        
                        $ingredient_name_lower = strtolower(trim($ing_name));
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
                        
                        $price_per_unit = rand(50, 300) / 100; // Random price between 0.50 and 3.00
                        
                        if (!isset($required_ingredients[$ing_id])) {
                            $required_ingredients[$ing_id] = [
                                'ingredient_id' => $ing_id,
                                'ingredient_name' => $ing_name,
                                'quantity' => 0,
                                'unit' => $ingredient['unit'],
                                'price_per_unit' => $price_per_unit
                            ];
                        }
                        $required_ingredients[$ing_id]['quantity'] += $ingredient['quantity'];
                    }
                }
            }
        }
    }
    
    // Step 2: Check user's inventory (exclude expired ingredients)
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
    
    // Step 3: Calculate missing ingredients and add to cart
    $missing_ingredients = [];
    $items_added = 0;
    
    foreach ($required_ingredients as $ing_id => $required) {
        $needed_qty = $required['quantity'];
        
        // Check if user has this ingredient in inventory
        $used_qty = 0;
        if (isset($user_inventory[$ing_id])) {
            $available_qty = $user_inventory[$ing_id]['quantity'];
            $available_unit = $user_inventory[$ing_id]['unit'];
            
            // Check if available quantity (with units) is enough
            $normalizedAvailable = normalizeQuantity($available_qty, $available_unit);
            $normalizedRequired = normalizeQuantity($required['quantity'], $required['unit']);
            
            if ($normalizedAvailable['quantity'] > 0) {
                $used_qty_base = min($normalizedRequired['quantity'], $normalizedAvailable['quantity']);
                
                // Track usage (display in the requested unit, which is typically what's in inventory or required)
                // For results display, we'll return what was actually "taken" from inventory
                $used_ingredients[] = [
                    'ingredient_id' => $ing_id,
                    'ingredient_name' => $required['ingredient_name'],
                    'quantity' => $required['unit'] === $available_unit ? $used_qty_base : $used_qty_base, // Keep it simple for now
                    'unit' => $normalizedRequired['unit'] // Standardized unit for display
                ];
                
                $needed_qty_base = max(0, $normalizedRequired['quantity'] - $normalizedAvailable['quantity']);
                
                // If we need more, calculate needed_qty in the required unit
                if ($needed_qty_base > 0) {
                    $needed_qty = $required['unit'] === 'kg' ? $needed_qty_base / 1000 : $needed_qty_base;
                } else {
                    $needed_qty = 0;
                }
            }
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
            
            // Add to shopping cart
            $price = $required['price_per_unit'] * $needed_qty;
            $cart_sql = "INSERT INTO shopping_cart (user_id, ingredient_id, quantity, unit, price)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE 
                         quantity = quantity + VALUES(quantity),
                         price = price + VALUES(price)";
            $cart_stmt = $conn->prepare($cart_sql);
            $cart_stmt->bind_param("iidsd", $user_id, $ing_id, $needed_qty, $required['unit'], $price);
            
            if ($cart_stmt->execute()) {
                $items_added++;
            }
        }
    }
    
    // Step 4: Get cart summary (excluding generic ingredients)
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
    
    return [
        'items_added' => $items_added,
        'missing_ingredients' => $missing_ingredients,
        'used_ingredients' => $used_ingredients,
        'summary' => [
            'total_items' => $cart_summary['item_count'],
            'total_price' => round($cart_summary['total_price'], 2)
        ]
    ];
}

/**
 * Validate and adjust meal plan to stay within user's nutrition goals
 */
function validateAndAdjustMealPlan($meals_data, $profile) {
    $target_calories = $profile['target_calories'];
    $target_protein = $profile['target_protein'];
    $target_carbs = $profile['target_carbs'];
    $target_fats = $profile['target_fats'];
    
    // Allow 10% buffer for goals (so we don't go over)
    $max_calories = $target_calories * 0.95;
    $max_protein = $target_protein * 0.95;
    $max_carbs = $target_carbs * 0.95;
    $max_fats = $target_fats * 0.95;
    
    foreach ($meals_data as &$day_data) {
        // Calculate daily totals
        $daily_calories = 0;
        $daily_protein = 0;
        $daily_carbs = 0;
        $daily_fats = 0;
        
        foreach ($day_data['meals'] as $meal) {
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
            foreach ($day_data['meals'] as &$meal) {
                $meal['calories'] = round($meal['calories'] * $adjustment_factor);
                $meal['protein'] = round($meal['protein'] * $adjustment_factor, 1);
                $meal['carbs'] = round($meal['carbs'] * $adjustment_factor, 1);
                $meal['fats'] = round($meal['fats'] * $adjustment_factor, 1);
                
                // Also adjust ingredient quantities proportionally
                if (isset($meal['ingredients']) && is_array($meal['ingredients'])) {
                    foreach ($meal['ingredients'] as &$ingredient) {
                        $ingredient['quantity'] = round($ingredient['quantity'] * $adjustment_factor, 1);
                    }
                }
            }
        }
    }
    
    return $meals_data;
}
?>
