# Inventory & Recipe Generation Setup Guide

## What Was Created

### New API Endpoints

1. **`api/inventory/generate_from_inventory.php`**
   - Finds matching recipes from database based on user's inventory
   - Returns recipes with ≥60% ingredient match
   - Shows available vs missing ingredients
   - Sorts by match percentage

2. **`api/inventory/generate_ai_recipe.php`**
   - Generates creative AI recipes using OpenAI
   - Uses user's inventory ingredients
   - Considers dietary preferences and calorie targets
   - Returns unique recipe with full instructions

3. **`api/get_ingredients.php`**
   - Public endpoint for authenticated users
   - Returns master ingredients list
   - Used to populate ingredient dropdown in inventory

### Updated Files

1. **`inventory.js`**
   - Added `generateRecipesFromInventory()` - finds matching recipes
   - Added `generateAIRecipe()` - generates AI recipe
   - Added `displayGeneratedRecipes()` - displays database recipes
   - Added `displayAIRecipe()` - displays AI-generated recipe
   - Updated `loadIngredients()` to use public endpoint

2. **`inventory.html`**
   - Added dropdown button for recipe generation options
   - Users can choose between "Find Matching Recipes" or "Generate AI Recipe"

### Documentation

1. **`INVENTORY_RECIPE_FLOW.md`**
   - Complete documentation of the inventory-to-recipe flow
   - User journey and technical details
   - API endpoint reference

2. **`inventory_flow_diagram.txt`**
   - Visual ASCII diagram showing the complete flow
   - Both database and AI recipe generation paths

## How It Works

### User Journey

1. **Add Ingredients to Inventory**
   ```
   User → Inventory Page → Add Ingredient → Select/Enter Details → Save
   ```

2. **Generate Recipes (Option A: Database)**
   ```
   User → Click "Find Recipes" → System matches inventory with recipes
   → Display recipes with match % → User selects recipe to cook
   ```

3. **Generate Recipes (Option B: AI)**
   ```
   User → Click "Generate AI Recipe" → System calls OpenAI
   → AI creates unique recipe → Display with full details
   ```

### Technical Flow

#### Database Recipe Matching
```php
// api/inventory/generate_from_inventory.php
1. Get user's inventory ingredients
2. Query all approved recipes
3. For each recipe:
   - Check which ingredients user has
   - Calculate match percentage
   - Track missing ingredients
4. Filter recipes with ≥60% match
5. Sort by match percentage
6. Return top 10 matches
```

#### AI Recipe Generation
```php
// api/inventory/generate_ai_recipe.php
1. Get user's inventory ingredients
2. Get user dietary preferences
3. Call Python script with OpenAI
4. AI generates custom recipe
5. Return recipe with instructions
```

## Prerequisites

### For Database Recipe Matching
- ✅ MySQL database with tables:
  - `user_inventory`
  - `ingredients`
  - `recipes`
  - `recipe_ingredients`
- ✅ User authentication (session-based)
- ✅ Approved recipes in database

### For AI Recipe Generation
- ⚠️ Python 3.x installed
- ⚠️ OpenAI API key configured
- ⚠️ Python packages: `openai`
- ⚠️ Environment variable: `OPENAI_API_KEY`

## Setup Instructions

### 1. Database Setup
Already configured if you have the recipe management system set up.

### 2. Python Setup (for AI features)

```bash
# Install Python dependencies
pip install openai

# Set OpenAI API key (Windows)
set OPENAI_API_KEY=your_api_key_here

# Or add to environment variables permanently
```

### 3. Test the System

#### Test Database Recipe Matching
1. Login as a user
2. Go to Inventory page
3. Add some ingredients (e.g., chicken, rice, tomatoes)
4. Click "Find Recipes" button
5. Should see matching recipes with percentages

#### Test AI Recipe Generation
1. Ensure OpenAI API key is set
2. Go to Inventory page
3. Add ingredients
4. Click dropdown → "Generate AI Recipe"
5. Wait for AI to generate recipe
6. Should see unique recipe with instructions

## Troubleshooting

### No Recipes Found
- **Cause**: Not enough ingredients or no matching recipes in database
- **Solution**: Add more ingredients or ensure recipes exist in database

### AI Generation Fails
- **Cause**: OpenAI API key not set or Python not configured
- **Solution**: 
  - Check `OPENAI_API_KEY` environment variable
  - Verify Python is installed: `python --version`
  - Check Python script path in PHP

### Ingredients Dropdown Empty
- **Cause**: No ingredients in master ingredients table
- **Solution**: Run database setup scripts to populate ingredients

### Permission Denied
- **Cause**: User not authenticated
- **Solution**: Ensure user is logged in with valid session

## API Testing

### Test Get Ingredients
```bash
curl -X GET http://localhost/api/get_ingredients.php \
  -H "Cookie: PHPSESSID=your_session_id"
```

### Test Generate from Inventory
```bash
curl -X POST http://localhost/api/inventory/generate_from_inventory.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"ingredients":["chicken","rice","tomatoes"]}'
```

### Test AI Recipe Generation
```bash
curl -X POST http://localhost/api/inventory/generate_ai_recipe.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"ingredients":["chicken","rice","tomatoes"]}'
```

## Features Summary

✅ **Inventory Management**
- Add/edit/delete ingredients
- Track quantities and units
- Monitor expiry dates
- Color-coded status indicators

✅ **Recipe Matching**
- Smart algorithm finds recipes with 60%+ match
- Shows available vs missing ingredients
- Sorted by best match
- Top 10 results

✅ **AI Recipe Generation**
- OpenAI-powered creative recipes
- Uses available ingredients
- Considers dietary preferences
- Full instructions and nutrition info

✅ **Recipe Actions**
- View recipe details
- Cook recipe (add to meal plan)
- Save AI recipes (coming soon)
- Add missing items to shopping list

## Next Steps

1. ✅ Test with real user accounts
2. ✅ Add sample recipes to database
3. ⚠️ Configure OpenAI API key for AI features
4. 🔄 Implement "Save AI Recipe" functionality
5. 🔄 Add recipe suggestions for expiring ingredients
6. 🔄 Auto-deduct inventory when cooking
