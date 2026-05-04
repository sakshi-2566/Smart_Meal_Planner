# Inventory to Recipe Generation Flow

## Overview
Users can manage their ingredient inventory and generate recipes based on what they have available.

## User Flow

### 1. Access Inventory
- Navigate to **Inventory** from the main menu
- URL: `inventory.html`

### 2. Manage Inventory

#### Add Ingredients
1. Click "Add Ingredient" button
2. Select ingredient from dropdown (master ingredients list)
3. Enter quantity and unit (g, kg, ml, l, pieces, cups, tbsp, tsp)
4. Optionally set expiry date
5. Click "Save"

#### Edit Ingredients
- Click edit icon on any inventory item
- Modify quantity, unit, or expiry date
- Save changes

#### Delete Ingredients
- Click trash icon on any item
- Confirm deletion

#### Track Status
Dashboard shows:
- **Total Items**: All ingredients in inventory
- **Expiring Soon**: Items expiring within 3 days (yellow)
- **Expired**: Past expiry date (red)
- **Fresh**: Good condition (green)

### 3. Generate Recipes from Inventory

Users have two options:

#### Option A: Find Matching Recipes (Database)
**Button**: "Find Recipes" or "Find Matching Recipes"

**How it works**:
1. System retrieves all user's inventory ingredients
2. Queries database for approved recipes
3. Calculates match percentage (available ingredients / total required)
4. Returns recipes with ≥60% ingredient match
5. Shows which ingredients are available vs missing
6. Sorts by match percentage (highest first)
7. Limits to top 10 matches

**API**: `api/inventory/generate_from_inventory.php`

**Response includes**:
- Recipe details (name, description, nutrition)
- Match percentage
- Available ingredients list
- Missing ingredients list
- Recipe ID for viewing/cooking

#### Option B: Generate AI Recipe (OpenAI)
**Button**: "Generate AI Recipe" or "Generate Creative AI Recipe"

**How it works**:
1. System retrieves user's inventory ingredients
2. Gets user dietary preferences and calorie targets
3. Calls OpenAI API via Python script
4. AI creates unique recipe using available ingredients
5. Returns custom recipe with instructions

**API**: `api/inventory/generate_ai_recipe.php`

**Python Script**: `ml_service/openai_recipe_generator.py`

**Response includes**:
- AI-generated recipe name
- Ingredient list with quantities
- Step-by-step instructions
- Prep/cook time
- Nutritional information
- Servings

### 4. Recipe Actions

From generated recipes, users can:
- **View**: Opens recipe details in new tab
- **Cook This**: Redirects to dashboard with recipe selected
- **Save** (AI recipes): Save AI-generated recipe to database (coming soon)

### 5. Check Recipe Availability

Users can check if they have ingredients for any specific recipe:
1. Select recipe from dropdown
2. System compares recipe requirements vs inventory
3. Shows available and missing ingredients
4. Option to add missing items to shopping list

**API**: `api/inventory/check_recipe_availability.php`

## Technical Details

### Database Tables
- `user_inventory`: User's ingredient inventory
- `ingredients`: Master ingredients list
- `recipes`: Recipe database
- `recipe_ingredients`: Recipe ingredient requirements

### Key Features
- **Smart Matching**: Finds recipes with 60%+ ingredient availability
- **Expiry Tracking**: Color-coded status for ingredient freshness
- **Flexible Units**: Supports multiple measurement units
- **AI Integration**: OpenAI-powered creative recipe generation
- **Shopping List**: Auto-add missing ingredients

### API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `api/inventory/get_inventory.php` | GET | Get user's inventory |
| `api/inventory/add_inventory.php` | POST | Add ingredient to inventory |
| `api/inventory/update_inventory.php` | POST | Update inventory item |
| `api/inventory/delete_inventory.php` | POST | Delete inventory item |
| `api/inventory/generate_from_inventory.php` | POST | Find matching recipes |
| `api/inventory/generate_ai_recipe.php` | POST | Generate AI recipe |
| `api/inventory/check_recipe_availability.php` | GET | Check recipe availability |

## Future Enhancements
- Save AI-generated recipes to database
- Recipe suggestions based on expiring ingredients
- Automatic inventory deduction when cooking
- Barcode scanning for quick ingredient entry
- Integration with shopping list for auto-replenishment
