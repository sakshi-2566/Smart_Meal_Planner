-- Sample Recipes for Testing
-- Run this after setting up the recipe management system

USE smart_meal_planner;

-- Sample Recipe 1: Grilled Chicken Salad
INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
                     calories, protein, carbs, fats, dietary_tags, is_public, approval_status, 
                     image_url)
VALUES (1, 'Grilled Chicken Salad', 
        'A healthy and protein-rich salad with grilled chicken breast and fresh vegetables',
        15, 20, 2, 450, 45, 25, 15, 'high-protein,gluten-free', 1, 'approved',
        'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400');

SET @recipe1_id = LAST_INSERT_ID();

-- Ingredients for Recipe 1
INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES
(@recipe1_id, 1, 200, 'g'),  -- Chicken Breast
(@recipe1_id, 3, 100, 'g'),  -- Spinach
(@recipe1_id, 14, 50, 'g'),  -- Tomato
(@recipe1_id, 15, 10, 'ml'); -- Olive Oil

-- Steps for Recipe 1
INSERT INTO recipe_steps (recipe_id, step_number, step_description) VALUES
(@recipe1_id, 1, 'Season the chicken breast with salt, pepper, and your favorite herbs'),
(@recipe1_id, 2, 'Preheat grill to medium-high heat and grill chicken for 6-7 minutes per side'),
(@recipe1_id, 3, 'While chicken is cooking, wash and prepare the spinach and tomatoes'),
(@recipe1_id, 4, 'Slice the grilled chicken and arrange over the salad greens'),
(@recipe1_id, 5, 'Drizzle with olive oil and serve immediately');

-- Sample Recipe 2: Salmon Quinoa Bowl
INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
                     calories, protein, carbs, fats, dietary_tags, is_public, approval_status,
                     image_url)
VALUES (1, 'Salmon Quinoa Bowl', 
        'Nutritious bowl with baked salmon, quinoa, and roasted vegetables',
        10, 25, 2, 580, 38, 52, 22, 'high-protein,omega-3', 1, 'approved',
        'https://images.unsplash.com/photo-1546069901-d5bfd2cbfb1f?w=400');

SET @recipe2_id = LAST_INSERT_ID();

-- Ingredients for Recipe 2
INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES
(@recipe2_id, 5, 150, 'g'),  -- Salmon
(@recipe2_id, 6, 80, 'g'),   -- Quinoa
(@recipe2_id, 7, 100, 'g'),  -- Broccoli
(@recipe2_id, 10, 100, 'g'), -- Sweet Potato
(@recipe2_id, 15, 15, 'ml'); -- Olive Oil

-- Steps for Recipe 2
INSERT INTO recipe_steps (recipe_id, step_number, step_description) VALUES
(@recipe2_id, 1, 'Cook quinoa according to package instructions'),
(@recipe2_id, 2, 'Preheat oven to 400°F (200°C)'),
(@recipe2_id, 3, 'Season salmon with salt, pepper, and lemon juice'),
(@recipe2_id, 4, 'Roast salmon and vegetables for 20 minutes'),
(@recipe2_id, 5, 'Assemble bowl with quinoa, salmon, and roasted vegetables');

-- Sample Recipe 3: Greek Yogurt Parfait (Pending Approval)
INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
                     calories, protein, carbs, fats, dietary_tags, is_public, approval_status,
                     image_url)
VALUES (1, 'Greek Yogurt Parfait', 
        'Quick and easy breakfast parfait with Greek yogurt, oats, and fresh fruit',
        10, 0, 1, 320, 18, 45, 8, 'vegetarian,high-protein', 1, 'pending',
        'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=400');

SET @recipe3_id = LAST_INSERT_ID();

-- Ingredients for Recipe 3
INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES
(@recipe3_id, 4, 200, 'g'),  -- Greek Yogurt
(@recipe3_id, 12, 40, 'g'),  -- Oats
(@recipe3_id, 13, 100, 'g'), -- Banana
(@recipe3_id, 11, 20, 'g');  -- Almonds

-- Steps for Recipe 3
INSERT INTO recipe_steps (recipe_id, step_number, step_description) VALUES
(@recipe3_id, 1, 'Layer Greek yogurt in a glass or bowl'),
(@recipe3_id, 2, 'Add a layer of oats'),
(@recipe3_id, 3, 'Top with sliced banana and crushed almonds'),
(@recipe3_id, 4, 'Repeat layers if desired and serve immediately');

-- Sample Recipe 4: Protein Smoothie Bowl
INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
                     calories, protein, carbs, fats, dietary_tags, is_public, approval_status,
                     image_url)
VALUES (1, 'Protein Smoothie Bowl', 
        'Energizing smoothie bowl packed with protein and nutrients',
        5, 0, 1, 380, 25, 48, 12, 'vegetarian,high-protein', 1, 'approved',
        'https://images.unsplash.com/photo-1590301157890-4810ed352733?w=400');

SET @recipe4_id = LAST_INSERT_ID();

-- Ingredients for Recipe 4
INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES
(@recipe4_id, 4, 150, 'g'),  -- Greek Yogurt
(@recipe4_id, 13, 150, 'g'), -- Banana
(@recipe4_id, 3, 30, 'g'),   -- Spinach
(@recipe4_id, 11, 15, 'g');  -- Almonds

-- Steps for Recipe 4
INSERT INTO recipe_steps (recipe_id, step_number, step_description) VALUES
(@recipe4_id, 1, 'Blend Greek yogurt, banana, and spinach until smooth'),
(@recipe4_id, 2, 'Pour into a bowl'),
(@recipe4_id, 3, 'Top with sliced almonds and additional fruit if desired'),
(@recipe4_id, 4, 'Enjoy immediately');

-- Sample Recipe 5: Egg and Avocado Toast (Pending)
INSERT INTO recipes (user_id, recipe_name, description, prep_time, cook_time, servings, 
                     calories, protein, carbs, fats, dietary_tags, is_public, approval_status,
                     image_url)
VALUES (1, 'Egg and Avocado Toast', 
        'Classic breakfast with poached eggs and creamy avocado on whole grain toast',
        5, 10, 1, 420, 18, 35, 24, 'vegetarian,high-protein', 1, 'pending',
        'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=400');

SET @recipe5_id = LAST_INSERT_ID();

-- Ingredients for Recipe 5
INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES
(@recipe5_id, 8, 100, 'g'),  -- Eggs (2 eggs)
(@recipe5_id, 9, 100, 'g'),  -- Avocado
(@recipe5_id, 14, 30, 'g');  -- Tomato

-- Steps for Recipe 5
INSERT INTO recipe_steps (recipe_id, step_number, step_description) VALUES
(@recipe5_id, 1, 'Toast your bread to desired crispness'),
(@recipe5_id, 2, 'Poach or fry eggs to your preference'),
(@recipe5_id, 3, 'Mash avocado and spread on toast'),
(@recipe5_id, 4, 'Top with eggs and sliced tomatoes'),
(@recipe5_id, 5, 'Season with salt, pepper, and optional chili flakes');

-- Add some sample ratings
INSERT INTO recipe_ratings (recipe_id, user_id, rating, review) VALUES
(@recipe1_id, 1, 5, 'Absolutely delicious! Perfect for meal prep.'),
(@recipe2_id, 1, 5, 'Love this bowl! So nutritious and filling.'),
(@recipe4_id, 1, 4, 'Great breakfast option, very refreshing.');

-- Add some favorites
INSERT INTO recipe_favorites (recipe_id, user_id) VALUES
(@recipe1_id, 1),
(@recipe2_id, 1);

-- Summary
SELECT 'Sample recipes created successfully!' as Status;
SELECT COUNT(*) as 'Total Recipes' FROM recipes;
SELECT COUNT(*) as 'Pending Recipes' FROM recipes WHERE approval_status = 'pending';
SELECT COUNT(*) as 'Approved Recipes' FROM recipes WHERE approval_status = 'approved';
