# Automated Meal Plan & Shopping Flow

## Complete User Journey

### 1. User Updates Profile
**Location**: `profile.html` → Health Information Tab

**User Actions**:
- Enter age, gender, height, weight
- Select activity level (sedentary, light, moderate, active, very active)
- Choose health goal (weight loss, maintenance, muscle gain)
- Click "Save Changes"

**System Actions**:
- Calculate BMR (Basal Metabolic Rate)
- Calculate TDEE (Total Daily Energy Expenditure)
- Calculate target calories based on goal
- Calculate macro targets (protein, carbs, fats)
- Update user profile in database
- **Trigger automated flow prompt**

---

### 2. AI Generates Personalized Meal Plan
**Triggered**: Automatically after profile update

**User Prompt**:
Modal appears asking: "Generate Personalized Meal Plan?"
- Shows what will happen:
  - Generate 7-day AI meal plan
  - Check inventory for ingredients
  - Add missing ingredients to cart
  - Ready for checkout

**System Actions** (when user clicks "Generate Now"):

#### Step 1: Generate Meal Plan
- Get user's dietary preferences (vegetarian, vegan, keto, etc.)
- Get calculated calorie targets
- Distribute calories across meals:
  - Breakfast: 30% of daily calories
  - Lunch: 40% of daily calories
  - Dinner: 30% of daily calories

#### Step 2: Select Recipes
For each day (7 days) and each meal (breakfast, lunch, dinner):
- Query database for recipes matching:
  - Calorie range (±200 calories from target)
  - Dietary preferences
  - Approved status
- Randomly select suitable recipes
- Create meal plan record in database

#### Step 3: Extract Required Ingredients
- For each recipe in meal plan:
  - Get all required ingredients
  - Get quantities needed
  - Aggregate total quantities for the week

---

### 3. Check Inventory for Ingredients
**Automatic**: System checks user's inventory

**Process**:
```
For each required ingredient:
  1. Check if user has it in inventory
  2. Compare available quantity vs required quantity
  3. Calculate shortage (if any)
  4. Mark as "missing" if not enough
```

**Example**:
```
Required: Chicken 2000g
Available in inventory: 500g
Missing: 1500g → Add to cart
```

---

### 4. Auto-Add Missing Ingredients to Cart
**Automatic**: No user action needed

**Process**:
- For each missing/insufficient ingredient:
  - Calculate quantity needed
  - Get ingredient price from database
  - Calculate total price (quantity × price_per_unit)
  - Add to shopping cart
  - If ingredient already in cart, update quantity

**Database**: `shopping_cart` table
```sql
INSERT INTO shopping_cart (user_id, ingredient_id, quantity, unit, price)
VALUES (?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE 
  quantity = quantity + VALUES(quantity),
  price = price + VALUES(price)
```

---

### 5. Show Results to User
**Modal Display**:

**Summary Cards**:
- 📅 **21 Meals Planned** (7 days × 3 meals)
- 🛒 **15 Items Added to Cart**
- 💰 **$87.50 Cart Total**

**Missing Ingredients List**:
```
✓ Chicken Breast - 1500g - $12.00
✓ Brown Rice - 800g - $4.00
✓ Broccoli - 600g - $3.60
✓ Olive Oil - 200ml - $5.00
... (and more)
```

**Action Buttons**:
- "View Meal Plan" → Go to dashboard
- "Go to Cart" → Go to shopping cart
- "Close" → Stay on profile page

---

### 6. User Reviews Cart
**Location**: `cart.html`

**Cart Display**:
- List of all ingredients
- Quantities and units
- Individual prices
- Category badges

**Order Summary**:
- Items count
- Subtotal
- Delivery fee ($5.00, FREE over $50)
- Total amount

**User Actions**:
- Review items
- Remove unwanted items (optional)
- Enter delivery address
- Click "Proceed to Checkout"

---

### 7. Process Order
**Triggered**: User clicks "Proceed to Checkout"

**System Actions**:

#### Validation:
- Check cart is not empty
- Verify delivery address provided

#### Create Order:
```sql
1. Generate unique order number (ORD-20241204-ABC123)
2. Calculate total amount
3. Create order record with status 'pending'
4. Create order items for each cart item
5. Clear shopping cart
```

#### Order Record:
```
Order Number: ORD-20241204-ABC123
Total Amount: $92.50
Status: Pending
Payment Status: Pending
Delivery Address: [User's address]
Items: 15 ingredients
```

---

### 8. Order Confirmation
**Display**: Success modal

**Information Shown**:
- ✅ Order placed successfully
- Order number
- Total amount
- Items count
- Estimated delivery: 2-3 business days

**Next Actions**:
- "Go to Dashboard" → View meal plan
- "View Orders" → See order history

---

## Technical Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    USER UPDATES PROFILE                     │
│                      (profile.html)                         │
│  Age, Gender, Height, Weight, Activity, Goal                │
└─────────────────────────────────────────────────────────────┘
                            ↓
                  api/update_profile.php
                            ↓
              Calculate BMR, TDEE, Targets
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              SHOW AUTO-GENERATE PROMPT                      │
│  "Generate personalized meal plan?"                         │
│  [Maybe Later]  [Generate Now]                              │
└─────────────────────────────────────────────────────────────┘
                            ↓ (User clicks Generate)
                            ↓
┌─────────────────────────────────────────────────────────────┐
│           api/auto_generate_meal_plan.php                   │
│                                                             │
│  STEP 1: Get User Profile                                  │
│  - Dietary preferences                                      │
│  - Calorie targets                                          │
│  - Health goals                                             │
│                                                             │
│  STEP 2: Generate 7-Day Meal Plan                          │
│  For each day (7 days):                                     │
│    - Breakfast (30% calories)                               │
│    - Lunch (40% calories)                                   │
│    - Dinner (30% calories)                                  │
│  Select recipes from database matching:                     │
│    - Calorie range                                          │
│    - Dietary preferences                                    │
│  Save to meal_plans & meal_plan_items tables                │
│                                                             │
│  STEP 3: Extract Required Ingredients                      │
│  For each recipe:                                           │
│    - Get recipe_ingredients                                 │
│    - Aggregate quantities                                   │
│  Result: List of all ingredients needed for 7 days         │
│                                                             │
│  STEP 4: Check User Inventory                              │
│  Query user_inventory table                                 │
│  For each required ingredient:                              │
│    IF in inventory:                                         │
│      Calculate: needed = required - available               │
│    ELSE:                                                    │
│      needed = required                                      │
│                                                             │
│  STEP 5: Auto-Add Missing to Cart                          │
│  For each missing/insufficient ingredient:                  │
│    - Calculate price                                        │
│    - INSERT/UPDATE shopping_cart                            │
│                                                             │
│  STEP 6: Return Results                                    │
│  - Meal plan details                                        │
│  - Missing ingredients list                                 │
│  - Cart summary                                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              SHOW RESULTS MODAL                             │
│  ✓ 21 Meals Planned                                         │
│  ✓ 15 Items Added to Cart                                   │
│  ✓ $87.50 Total                                             │
│  [View Meal Plan] [Go to Cart]                              │
└─────────────────────────────────────────────────────────────┘
                            ↓ (User clicks Go to Cart)
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   SHOPPING CART PAGE                        │
│                      (cart.html)                            │
│                                                             │
│  Cart Items:                                                │
│  - Chicken Breast: 1500g - $12.00                           │
│  - Brown Rice: 800g - $4.00                                 │
│  - Broccoli: 600g - $3.60                                   │
│  ... (15 items total)                                       │
│                                                             │
│  Order Summary:                                             │
│  - Subtotal: $87.50                                         │
│  - Delivery: $5.00                                          │
│  - Total: $92.50                                            │
│                                                             │
│  Delivery Address: [User enters address]                    │
│  [Proceed to Checkout]                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
                  api/cart/checkout.php
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   PROCESS ORDER                             │
│  1. Validate cart & address                                 │
│  2. Generate order number                                   │
│  3. Create order record                                     │
│  4. Create order items                                      │
│  5. Clear shopping cart                                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              ORDER SUCCESS MODAL                            │
│  ✓ Order Placed Successfully!                               │
│  Order Number: ORD-20241204-ABC123                          │
│  Total: $92.50                                              │
│  Delivery: 2-3 business days                                │
│  [Go to Dashboard] [View Orders]                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Tables Used

### 1. `users` & `user_profiles`
- Store user information
- Health metrics (age, gender, height, weight)
- Calculated values (BMR, TDEE, targets)

### 2. `meal_plans`
- Stores meal plan metadata
- Links to user
- Date range (start_date, end_date)
- Status (active, completed, cancelled)

### 3. `meal_plan_items`
- Individual meals in the plan
- Links to recipes
- Meal type (breakfast, lunch, dinner)
- Meal date

### 4. `recipes` & `recipe_ingredients`
- Recipe database
- Ingredient requirements per recipe

### 5. `user_inventory`
- User's current ingredient stock
- Quantities and expiry dates

### 6. `shopping_cart`
- Temporary cart items
- Cleared after checkout

### 7. `orders` & `order_items`
- Permanent order records
- Order history
- Delivery tracking

### 8. `ingredients`
- Master ingredients list
- Prices per unit
- Categories

---

## API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `api/update_profile.php` | POST | Update user health info |
| `api/auto_generate_meal_plan.php` | POST | Generate meal plan + auto-cart |
| `api/cart/get_cart.php` | GET | Get cart items |
| `api/cart/add_to_cart.php` | POST | Add item to cart |
| `api/cart/remove_from_cart.php` | POST | Remove cart item |
| `api/cart/clear_cart.php` | POST | Clear entire cart |
| `api/cart/checkout.php` | POST | Process order |

---

## Key Features

✅ **Fully Automated**
- No manual ingredient selection needed
- System handles everything from profile to cart

✅ **Intelligent Inventory Check**
- Compares required vs available
- Only adds what's missing

✅ **Smart Aggregation**
- Combines ingredients across multiple recipes
- Calculates total quantities for the week

✅ **Dietary Preferences**
- Respects user's dietary choices
- Filters recipes accordingly

✅ **Price Calculation**
- Automatic price calculation
- Shows delivery fees
- Free delivery over $50

✅ **Order Tracking**
- Unique order numbers
- Order history
- Status tracking

---

## Setup Instructions

### 1. Run Database Setup
```bash
# Execute SQL file to create tables
mysql -u username -p database_name < database/shopping_cart_system.sql
```

### 2. Configure Ingredient Prices
Update prices in `ingredients` table as needed.

### 3. Test the Flow
1. Login as user
2. Go to Profile → Health Information
3. Fill in all fields
4. Click "Save Changes"
5. Click "Generate Now" in prompt
6. Wait for processing
7. Review results
8. Go to cart
9. Enter delivery address
10. Checkout

---

## Future Enhancements

🔄 **Payment Integration**
- Stripe/PayPal integration
- Real payment processing

🔄 **Delivery Tracking**
- Real-time order tracking
- SMS/Email notifications

🔄 **Inventory Auto-Update**
- Deduct from inventory when cooking
- Auto-reorder low stock items

🔄 **Recipe Substitutions**
- Suggest alternatives for missing ingredients
- Dietary restriction alternatives

🔄 **Meal Plan Customization**
- Allow users to swap meals
- Regenerate specific days
- Save favorite meal plans
