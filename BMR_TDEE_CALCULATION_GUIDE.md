# BMR & TDEE Calculation Guide

## Smart Meal Planner - Nutrition Calculation Formulas

This document explains the mathematical formulas used in the Smart Meal Planner system to calculate Basal Metabolic Rate (BMR), Total Daily Energy Expenditure (TDEE), and macronutrient targets.

---

## 📊 BMR (Basal Metabolic Rate) Calculation

### Formula Used: **Mifflin-St Jeor Equation**

The system uses the **Mifflin-St Jeor Equation**, which is considered one of the most accurate formulas for calculating BMR.

#### For Males:
```
BMR = (10 × weight in kg) + (6.25 × height in cm) - (5 × age in years) + 5
```

#### For Females:
```
BMR = (10 × weight in kg) + (6.25 × height in cm) - (5 × age in years) - 161
```

### Example Calculation:
**Male, 30 years old, 175 cm tall, 70 kg**
```
BMR = (10 × 70) + (6.25 × 175) - (5 × 30) + 5
BMR = 700 + 1093.75 - 150 + 5
BMR = 1648.75 kcal/day
```

**Female, 25 years old, 165 cm tall, 60 kg**
```
BMR = (10 × 60) + (6.25 × 165) - (5 × 25) - 161
BMR = 600 + 1031.25 - 125 - 161
BMR = 1345.25 kcal/day
```

---

## 🏃‍♂️ TDEE (Total Daily Energy Expenditure) Calculation

### Formula:
```
TDEE = BMR × Activity Level Multiplier
```

### Activity Level Multipliers:

| Activity Level | Multiplier | Description |
|----------------|------------|-------------|
| **Sedentary** | 1.2 | Little or no exercise, desk job |
| **Light** | 1.375 | Light exercise 1-3 days/week |
| **Moderate** | 1.55 | Moderate exercise 3-5 days/week |
| **Active** | 1.725 | Hard exercise 6-7 days/week |
| **Very Active** | 1.9 | Very hard exercise, physical job |

### Example Calculation:
**Using the male example above with moderate activity:**
```
TDEE = 1648.75 × 1.55
TDEE = 2555.56 kcal/day
```

---

## 🥗 Macronutrient Distribution

The system calculates protein, carbohydrates, and fats based on fitness goals:

### Caloric Values:
- **Protein**: 4 calories per gram
- **Carbohydrates**: 4 calories per gram
- **Fats**: 9 calories per gram

### Goal-Based Ratios:

#### 1. Weight Loss
- **Protein**: 35% of total calories
- **Carbohydrates**: 40% of total calories
- **Fats**: 25% of total calories

#### 2. Muscle Gain
- **Protein**: 30% of total calories
- **Carbohydrates**: 45% of total calories
- **Fats**: 25% of total calories

#### 3. Athletic Performance
- **Protein**: 25% of total calories
- **Carbohydrates**: 50% of total calories
- **Fats**: 25% of total calories

#### 4. Maintenance (Default)
- **Protein**: 30% of total calories
- **Carbohydrates**: 40% of total calories
- **Fats**: 30% of total calories

### Macro Calculation Formula:
```
Protein (grams) = (Total Calories × Protein Ratio) ÷ 4
Carbs (grams) = (Total Calories × Carbs Ratio) ÷ 4
Fats (grams) = (Total Calories × Fats Ratio) ÷ 9
```

### Example Calculation:
**2000 calories for weight loss:**
```
Protein = (2000 × 0.35) ÷ 4 = 700 ÷ 4 = 175g
Carbs = (2000 × 0.40) ÷ 4 = 800 ÷ 4 = 200g
Fats = (2000 × 0.25) ÷ 9 = 500 ÷ 9 = 56g
```

---

## 🎯 Calorie Adjustment for Goals

The system adjusts TDEE based on fitness goals:

### Weight Loss:
```
Target Calories = TDEE - 500 (for 1 lb/week loss)
Target Calories = TDEE - 750 (for 1.5 lb/week loss)
```

### Muscle Gain:
```
Target Calories = TDEE + 300-500 (lean bulk)
```

### Maintenance:
```
Target Calories = TDEE (no adjustment)
```

### Athletic Performance:
```
Target Calories = TDEE + 200-400 (performance fuel)
```

---

## 🔧 Implementation Details

### File Locations:
- **Main Calculation Logic**: `ml_service/meal_recommender.py`
- **Profile Update API**: `api/update_profile.php`
- **Frontend Display**: `profile.html` and `profile.js`

### Python Implementation:
```python
def calculate_bmr(self, weight, height, age, gender):
    """Calculate BMR using Mifflin-St Jeor Equation"""
    if gender.lower() == 'male':
        bmr = (10 * weight) + (6.25 * height) - (5 * age) + 5
    else:
        bmr = (10 * weight) + (6.25 * height) - (5 * age) - 161
    return round(bmr, 2)

def calculate_tdee(self, bmr, activity_level):
    """Calculate TDEE"""
    activity_multipliers = {
        'sedentary': 1.2,
        'light': 1.375,
        'moderate': 1.55,
        'active': 1.725,
        'very_active': 1.9
    }
    multiplier = activity_multipliers.get(activity_level, 1.55)
    tdee = bmr * multiplier
    return round(tdee, 2)
```

---

## 📝 Usage in Meal Planning

### Meal Distribution:
The system distributes daily calories across meals:
- **Breakfast**: 30% of total calories
- **Lunch**: 40% of total calories
- **Dinner**: 30% of total calories

### Nutrition Goal Validation:
- Meal plans are validated to stay within **95% of nutrition targets**
- If any meal exceeds limits, portions are scaled down proportionally
- This prevents users from exceeding their daily nutrition goals

---

## 🧪 Testing

### Manual Testing:
```bash
# Test BMR calculation
python ml_service/meal_recommender.py calculate_bmr 70 175 30 male

# Expected output: {"bmr": 1648.75}
```

### Test Page:
- **File**: `test_nutrition_goals.html`
- **Purpose**: Visual validation of nutrition calculations
- **Features**: Shows BMR, TDEE, and macro targets with meal plan validation

---

## 📚 Scientific References

### Mifflin-St Jeor Equation:
- **Source**: Mifflin, M. D., et al. (1990). "A new predictive equation for resting energy expenditure in healthy individuals." *American Journal of Clinical Nutrition*, 51(2), 241-247.
- **Accuracy**: ±10% for most individuals
- **Advantages**: More accurate than Harris-Benedict equation, especially for overweight individuals

### Activity Level Multipliers:
- **Source**: Institute of Medicine (2005). "Dietary Reference Intakes for Energy, Carbohydrate, Fiber, Fat, Fatty Acids, Cholesterol, Protein, and Amino Acids."
- **Usage**: Widely accepted standard in nutrition science

---

## ⚠️ Important Notes

1. **Individual Variation**: BMR can vary ±10-15% between individuals due to genetics, muscle mass, and metabolic factors.

2. **Medical Conditions**: Certain conditions (thyroid disorders, medications) can significantly affect metabolic rate.

3. **Age Factor**: Metabolism typically decreases by 1-2% per decade after age 30.

4. **Body Composition**: Higher muscle mass increases BMR; the formula assumes average body composition.

5. **Validation**: The system includes a 5% buffer in meal planning to ensure users don't exceed their nutrition goals.

---

## 🔄 System Updates

**Last Updated**: December 2024
**Version**: 1.0
**Status**: Active and validated

For technical support or formula updates, refer to the development team or check the latest version in the repository.