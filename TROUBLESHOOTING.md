# Troubleshooting Guide

## Error: "Unexpected token '<', "<br /> <b>"... is not valid JSON"

This error means the PHP script is returning HTML (an error page) instead of JSON.

### Solution Steps:

### 1. Check Prerequisites

Visit this URL to check system status:
```
http://localhost/api/test_meal_plan.php
```

This will show you what's missing:
- ✓ User exists
- ✓ Profile exists
- ✓ Recipes exist
- ✓ Shopping cart table exists
- ✓ Meal plans table exists
- ✓ Ingredients have prices

### 2. Run Database Setup

If tables are missing:
```
http://localhost/setup_shopping_system.php
```

### 3. Complete Your Profile

Go to: Profile → Health Information

Fill in ALL fields:
- Age
- Gender
- Height
- Weight
- Activity Level
- Goal

Click "Save Changes"

### 4. Ensure Recipes Exist

Check if you have approved recipes:
```sql
SELECT COUNT(*) FROM recipes WHERE approval_status = 'approved';
```

If count is 0, you need to:
- Add recipes via admin panel
- Or run sample recipes SQL:
```
http://localhost/setup_recipe_management.php
```

### 5. Check Ingredient Prices

```sql
SELECT COUNT(*) FROM ingredients WHERE price_per_unit > 0;
```

If count is 0, run:
```
http://localhost/setup_shopping_system.php
```

---

## Common Issues

### Issue 1: "Please complete your profile first"

**Cause**: User profile doesn't have health information

**Fix**:
1. Go to Profile page
2. Click "Health Information" tab
3. Fill in: age, gender, height, weight, activity level, goal
4. Click "Save Changes"
5. Try generating meal plan again

---

### Issue 2: "No suitable recipes found"

**Cause**: No approved recipes in database

**Fix**:
1. Login as admin
2. Go to admin panel
3. Add and approve recipes
4. Or run: `http://localhost/setup_recipe_management.php`

---

### Issue 3: Cart items not adding

**Cause**: `shopping_cart` table doesn't exist or `ingredients` missing prices

**Fix**:
```
http://localhost/setup_shopping_system.php
```

---

### Issue 4: PHP Errors Showing

**Cause**: PHP errors being displayed instead of JSON

**Fix**: Check `api/auto_generate_meal_plan.php` has:
```php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors
```

---

### Issue 5: Database Connection Failed

**Cause**: Database credentials incorrect

**Fix**: Check `config/database.php`:
```php
$host = 'localhost';
$username = 'your_username';
$password = 'your_password';
$database = 'smart_meal_planner';
```

---

## Debug Checklist

Run through this checklist:

- [ ] Database exists: `smart_meal_planner`
- [ ] Tables exist: `users`, `user_profiles`, `recipes`, `ingredients`, `shopping_cart`, `meal_plans`, `meal_plan_items`
- [ ] User is logged in (check session)
- [ ] User profile has `target_calories` set
- [ ] At least 1 approved recipe exists
- [ ] Ingredients have `price_per_unit` > 0
- [ ] PHP errors are not being displayed
- [ ] API returns JSON (not HTML)

---

## Testing Commands

### Check User Profile
```sql
SELECT u.id, u.first_name, p.target_calories 
FROM users u 
LEFT JOIN user_profiles p ON u.id = p.user_id 
WHERE u.email = 'your@email.com';
```

### Check Recipes
```sql
SELECT COUNT(*) as total, 
       SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved
FROM recipes;
```

### Check Ingredients
```sql
SELECT COUNT(*) as total,
       SUM(CASE WHEN price_per_unit > 0 THEN 1 ELSE 0 END) as with_prices
FROM ingredients;
```

### Check Tables
```sql
SHOW TABLES LIKE '%cart%';
SHOW TABLES LIKE '%meal%';
```

---

## Quick Fixes

### Reset Everything
```sql
-- Clear cart
DELETE FROM shopping_cart;

-- Clear meal plans
DELETE FROM meal_plan_items;
DELETE FROM meal_plans;

-- Start fresh
```

### Add Sample Data

**Add a test recipe:**
```sql
INSERT INTO recipes (user_id, recipe_name, description, calories, protein, carbs, fats, approval_status)
VALUES (1, 'Test Breakfast', 'Test meal', 500, 30, 50, 15, 'approved');
```

**Set ingredient prices:**
```sql
UPDATE ingredients SET price_per_unit = 5.00 WHERE price_per_unit IS NULL OR price_per_unit = 0;
```

---

## Still Having Issues?

### Enable PHP Error Logging

In `api/auto_generate_meal_plan.php`, temporarily change:
```php
ini_set('display_errors', 1); // Show errors for debugging
```

Then check browser console for the actual PHP error.

### Check PHP Error Log

Location varies by system:
- Windows XAMPP: `C:\xampp\apache\logs\error.log`
- Linux: `/var/log/apache2/error.log`
- Mac MAMP: `/Applications/MAMP/logs/php_error.log`

### Test API Directly

Use browser or Postman:
```
POST http://localhost/api/auto_generate_meal_plan.php
Headers: Content-Type: application/json
Body: {"days": 7}
```

Check the response - should be JSON, not HTML.

---

## Contact Support

If still stuck, provide:
1. Output from `api/test_meal_plan.php`
2. PHP error log
3. Browser console errors
4. Database table list: `SHOW TABLES;`
5. User profile data (without sensitive info)

---

## Prevention

To avoid these issues in future:

1. ✅ Always run setup scripts after fresh install
2. ✅ Complete user profile before generating meal plans
3. ✅ Ensure recipes are approved before use
4. ✅ Keep ingredient prices updated
5. ✅ Regular database backups
