# Affordable Pricing Structure

## Updated Prices (Much Lower!)

All prices are now **per gram (g) or milliliter (ml)** to keep totals affordable.

---

## Ingredient Prices

| Category | Price per g/ml | Price per kg/L | Example |
|----------|----------------|----------------|---------|
| **Protein** | ₹0.20/g | ₹200/kg | 500g chicken = ₹100 |
| **Grains** | ₹0.05/g | ₹50/kg | 1kg rice = ₹50 |
| **Vegetables** | ₹0.03/g | ₹30/kg | 500g tomatoes = ₹15 |
| **Fruits** | ₹0.06/g | ₹60/kg | 500g apples = ₹30 |
| **Dairy** | ₹0.08/g | ₹80/kg | 500g yogurt = ₹40 |
| **Fats** | ₹0.15/ml | ₹150/L | 200ml oil = ₹30 |
| **Nuts** | ₹0.50/g | ₹500/kg | 100g almonds = ₹50 |

---

## Delivery Charges

| Type | Amount |
|------|--------|
| Standard Delivery | ₹20 |
| Free Delivery Over | ₹200 |

---

## Example Shopping Carts

### Small Order (₹180)
```
Chicken: 500g × ₹0.20 = ₹100
Rice: 1kg × ₹0.05 = ₹50
Tomatoes: 500g × ₹0.03 = ₹15
Onions: 500g × ₹0.03 = ₹15
                    ─────────
Subtotal:           ₹180
Delivery:           ₹20 (under ₹200)
                    ─────────
TOTAL:              ₹200
```

### Medium Order (₹285) - FREE DELIVERY!
```
Chicken: 1kg × ₹0.20 = ₹200
Rice: 1kg × ₹0.05 = ₹50
Vegetables: 500g × ₹0.03 = ₹15
Oil: 100ml × ₹0.15 = ₹15
Yogurt: 250g × ₹0.08 = ₹20
                    ─────────
Subtotal:           ₹300
Delivery:           FREE (over ₹200)
                    ─────────
TOTAL:              ₹300
```

### 7-Day Meal Plan (Typical)
```
Protein (3.5kg):    ₹700
Grains (5kg):       ₹250
Vegetables (3kg):   ₹90
Fruits (2kg):       ₹120
Dairy (1.5kg):      ₹120
Oil (500ml):        ₹75
Spices/Others:      ₹45
                    ─────────
Subtotal:           ₹1,400
Delivery:           FREE
                    ─────────
TOTAL:              ₹1,400
Per Day:            ₹200
```

---

## Price Breakdown Examples

### Breakfast (₹40-60)
- Oats: 100g = ₹5
- Milk: 200ml = ₹16
- Banana: 100g = ₹6
- Almonds: 20g = ₹10
- **Total: ₹37**

### Lunch (₹60-80)
- Chicken: 150g = ₹30
- Rice: 200g = ₹10
- Vegetables: 200g = ₹6
- Oil: 20ml = ₹3
- **Total: ₹49**

### Dinner (₹50-70)
- Fish: 150g = ₹30
- Chapati (wheat): 150g = ₹7.50
- Dal: 100g = ₹5
- Vegetables: 150g = ₹4.50
- **Total: ₹47**

**Daily Total: ₹133** (very affordable!)

---

## Comparison: Old vs New

| Item | Old Price | New Price | Savings |
|------|-----------|-----------|---------|
| 500g Chicken | ₹300 | ₹100 | ₹200 (67%) |
| 1kg Rice | ₹80 | ₹50 | ₹30 (38%) |
| 500g Vegetables | ₹20 | ₹15 | ₹5 (25%) |
| 500g Fruits | ₹50 | ₹30 | ₹20 (40%) |
| Delivery | ₹40 | ₹20 | ₹20 (50%) |
| Free Delivery | ₹500 | ₹200 | ₹300 (60%) |

---

## Benefits of New Pricing

✅ **More Affordable**: 40-67% cheaper
✅ **Lower Delivery Fee**: ₹20 instead of ₹40
✅ **Easier Free Delivery**: Only ₹200 needed
✅ **Realistic for Students**: Budget-friendly
✅ **Daily Meals**: ~₹130-200 per day
✅ **Weekly Budget**: ~₹1,000-1,500

---

## Target Audience

Perfect for:
- 🎓 Students
- 👨‍💼 Young professionals
- 👨‍👩‍👧 Small families
- 💪 Fitness enthusiasts on budget
- 🏠 Home cooks

---

## How Prices Work

### Per Gram/ML System
- Ingredients stored in grams (g) or milliliters (ml)
- Price calculated: `quantity × price_per_unit`
- Example: 500g chicken × ₹0.20/g = ₹100

### Benefits
- ✅ Precise pricing
- ✅ No rounding errors
- ✅ Flexible quantities
- ✅ Easy to understand

---

## Adjusting Prices

### To Make Even Cheaper
```sql
UPDATE ingredients SET price_per_unit = 0.15 WHERE category = 'Protein';  -- ₹150/kg
UPDATE ingredients SET price_per_unit = 0.04 WHERE category = 'Grains';   -- ₹40/kg
```

### To Increase Slightly
```sql
UPDATE ingredients SET price_per_unit = 0.25 WHERE category = 'Protein';  -- ₹250/kg
UPDATE ingredients SET price_per_unit = 0.06 WHERE category = 'Grains';   -- ₹60/kg
```

---

## Real-World Examples

### Student Budget (₹150/day)
- Breakfast: ₹40
- Lunch: ₹60
- Dinner: ₹50
- **Total: ₹150/day = ₹1,050/week**

### Professional Budget (₹250/day)
- Breakfast: ₹70
- Lunch: ₹100
- Dinner: ₹80
- **Total: ₹250/day = ₹1,750/week**

### Family Budget (₹400/day for 2 people)
- Breakfast: ₹120
- Lunch: ₹150
- Dinner: ₹130
- **Total: ₹400/day = ₹2,800/week**

---

## Summary

**New pricing is 40-67% cheaper!**

- Delivery: ₹40 → **₹20**
- Free delivery: ₹500 → **₹200**
- Chicken: ₹600/kg → **₹200/kg**
- Rice: ₹80/kg → **₹50/kg**
- Vegetables: ₹40/kg → **₹30/kg**

**Perfect for budget-conscious users!** 💰✨

---

**Last Updated**: December 4, 2024
**Status**: ✅ Active
