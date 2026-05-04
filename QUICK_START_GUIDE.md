# 🚀 Quick Start Guide - Automated Meal Planning System

## Setup (5 minutes)

### Step 1: Run Database Setup
Open in browser:
```
http://localhost/setup_shopping_system.php
```

Wait for all green checkmarks ✓

### Step 2: Verify Setup
Check that you see:
- ✓ Shopping cart table created
- ✓ Orders table created
- ✓ Order items table created
- ✓ Meal plans table created
- ✓ Meal plan items table created
- ✓ Price columns added
- ✓ Sample prices set

---

## Testing the Flow (2 minutes)

### 1. Login
```
http://localhost/login.html
```
Use your test account credentials

### 2. Go to Profile
Click "Profile" in navigation menu

### 3. Update Health Info
Go to "Health Information" tab

Fill in:
- Age: 30
- Gender: Female (or Male)
- Height: 165 cm
- Weight: 70 kg
- Activity Level: Moderate
- Goal: Weight Loss

Click **"Save Changes"**

### 4. Generate Meal Plan
Modal appears: "Generate Personalized Meal Plan?"

Click **"Generate Now"**

Wait 3-5 seconds (shows loading steps)

### 5. View Results
Results modal shows:
- ✅ 21 Meals Planned
- ✅ X Items Added to Cart
- ✅ $XX.XX Cart Total

Click **"Go to Cart"**

### 6. Review Cart
Shopping cart page opens

Shows:
- All missing ingredients
- Quantities and prices
- Order summary

Enter delivery address in text box

Click **"Proceed to Checkout"**

### 7. Order Confirmation
Success modal appears:
- ✅ Order Placed Successfully!
- Order Number: ORD-XXXXXXXX
- Total Amount: $XX.XX

Click **"Go to Dashboard"** to see your meal plan

---

## What Just Happened?

1. ✅ System calculated your BMR, TDEE, and calorie targets
2. ✅ AI generated a 7-day personalized meal plan (21 meals)
3. ✅ System checked your inventory for required ingredients
4. ✅ Missing ingredients were automatically added to cart
5. ✅ You reviewed and placed the order
6. ✅ Order was processed and saved

**Total time**: ~2 minutes
**Manual work saved**: ~2 hours!

---

## File Structure

```
your-project/
├── api/
│   ├── auto_generate_meal_plan.php    ← Main automation
│   ├── cart/
│   │   ├── get_cart.php
│   │   ├── add_to_cart.php
│   │   ├── remove_from_cart.php
│   │   ├── clear_cart.php
│   │   └── checkout.php
│   └── ...
├── database/
│   └── shopping_cart_system.sql       ← Database schema
├── cart.html                          ← Shopping cart page
├── cart.js                            ← Cart functionality
├── profile.js                         ← Updated with automation
├── setup_shopping_system.php          ← Setup script
└── Documentation/
    ├── AUTOMATED_MEAL_PLAN_FLOW.md
    ├── COMPLETE_SYSTEM_SUMMARY.md
    ├── automated_flow_diagram.txt
    └── QUICK_START_GUIDE.md (this file)
```

---

## Key URLs

| Page | URL | Purpose |
|------|-----|---------|
| Setup | `/setup_shopping_system.php` | Initialize database |
| Profile | `/profile.html` | Update health info |
| Cart | `/cart.html` | Review and checkout |
| Dashboard | `/dashboard.html` | View meal plan |

---

## Troubleshooting

### Issue: "Please complete your profile first"
**Fix**: Go to Profile → Health Information and fill in all fields

### Issue: "No recipes found"
**Fix**: Ensure you have recipes in database with `approval_status = 'approved'`

### Issue: Cart is empty after generation
**Fix**: Check that `ingredients` table has `price_per_unit` column

### Issue: Modal doesn't appear
**Fix**: Check browser console for JavaScript errors

### Issue: Order fails
**Fix**: Ensure delivery address is filled in

---

## Testing Checklist

- [ ] Database setup completed
- [ ] Can login successfully
- [ ] Profile page loads
- [ ] Can update health information
- [ ] Auto-generate prompt appears
- [ ] Meal plan generates (shows 21 meals)
- [ ] Items added to cart (shows count)
- [ ] Cart page displays items
- [ ] Can enter delivery address
- [ ] Checkout processes successfully
- [ ] Order confirmation appears
- [ ] Order number displayed

---

## Next Steps

### For Users:
1. View your meal plan on dashboard
2. Start cooking your meals
3. Track your nutrition progress
4. Update inventory as you use ingredients

### For Developers:
1. Customize meal distribution percentages
2. Adjust delivery fees
3. Add payment gateway integration
4. Implement order tracking
5. Add email notifications

---

## Support

### Documentation Files:
- `AUTOMATED_MEAL_PLAN_FLOW.md` - Complete technical flow
- `COMPLETE_SYSTEM_SUMMARY.md` - Full system overview
- `automated_flow_diagram.txt` - Visual flow diagram

### Common Commands:

**Reset cart:**
```sql
DELETE FROM shopping_cart WHERE user_id = YOUR_USER_ID;
```

**View orders:**
```sql
SELECT * FROM orders WHERE user_id = YOUR_USER_ID ORDER BY order_date DESC;
```

**Check meal plan:**
```sql
SELECT * FROM meal_plans WHERE user_id = YOUR_USER_ID AND status = 'active';
```

---

## Success Indicators

You'll know it's working when:

✅ Profile update triggers auto-generate prompt
✅ Loading modal shows progress steps
✅ Results modal shows meals and cart items
✅ Cart displays all missing ingredients
✅ Checkout creates order successfully
✅ Order confirmation shows order number

---

## Performance

**Expected Processing Times:**
- Profile calculation: < 1 second
- Meal plan generation: 2-3 seconds
- Inventory check: 1-2 seconds
- Cart update: < 1 second
- Checkout: < 1 second

**Total automation time**: ~5 seconds

---

## Tips

💡 **Tip 1**: Update your inventory regularly for accurate cart items

💡 **Tip 2**: Review cart before checkout - you can remove items you already have

💡 **Tip 3**: Orders over $50 get free delivery

💡 **Tip 4**: Change dietary preferences in profile to get different recipes

💡 **Tip 5**: Regenerate meal plan anytime by updating profile again

---

## Congratulations! 🎉

You now have a fully automated meal planning and shopping system!

**What you achieved:**
- ✅ Automated meal planning
- ✅ Intelligent inventory checking
- ✅ Auto-populated shopping cart
- ✅ Seamless order processing

**Time saved per week**: ~2 hours
**Convenience**: Priceless! 😊

---

**Need help?** Check the documentation files or review the code comments.

**Ready to customize?** See `COMPLETE_SYSTEM_SUMMARY.md` for customization options.

**Happy meal planning!** 🍽️
