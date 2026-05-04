"""
AI Meal Recommendation System
Uses Machine Learning to provide personalized meal suggestions
"""

import numpy as np
import pandas as pd
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import RandomForestRegressor
import pickle
import json
import sys

class MealRecommender:
    def __init__(self):
        self.scaler = StandardScaler()
        self.model = None
        
    def train_model(self, training_data):
        """
        Train the ML model on user feedback data
        Features: age, weight, height, activity_level, dietary_preference, meal_calories
        Target: user_rating (1-5)
        """
        X = training_data[['age', 'weight', 'height', 'activity_level', 
                          'meal_calories', 'protein', 'carbs', 'fats']]
        y = training_data['user_rating']
        
        # Scale features
        X_scaled = self.scaler.fit_transform(X)
        
        # Train Random Forest model
        self.model = RandomForestRegressor(n_estimators=100, random_state=42)
        self.model.fit(X_scaled, y)
        
        return self.model.score(X_scaled, y)
    
    def predict_meal_rating(self, user_profile, meal_nutrition):
        """
        Predict how much a user will like a meal
        """
        if self.model is None:
            return 3.0  # Default rating if model not trained
        
        features = np.array([[
            user_profile['age'],
            user_profile['weight'],
            user_profile['height'],
            user_profile['activity_level'],
            meal_nutrition['calories'],
            meal_nutrition['protein'],
            meal_nutrition['carbs'],
            meal_nutrition['fats']
        ]])
        
        features_scaled = self.scaler.transform(features)
        rating = self.model.predict(features_scaled)[0]
        
        return max(1.0, min(5.0, rating))  # Clamp between 1-5
    
    def calculate_bmr(self, weight, height, age, gender):
        """
        Calculate Basal Metabolic Rate using Mifflin-St Jeor Equation
        """
        if gender.lower() == 'male':
            bmr = (10 * weight) + (6.25 * height) - (5 * age) + 5
        else:
            bmr = (10 * weight) + (6.25 * height) - (5 * age) - 161
        
        return round(bmr, 2)
    
    def calculate_tdee(self, bmr, activity_level):
        """
        Calculate Total Daily Energy Expenditure
        """
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
    
    def calculate_macros(self, calories, goal):
        """
        Calculate macro distribution based on goal
        """
        if goal == 'weight_loss':
            # High protein, moderate carbs, low fat
            protein_ratio = 0.35
            carbs_ratio = 0.40
            fats_ratio = 0.25
        elif goal == 'muscle_gain':
            # High protein, high carbs, moderate fat
            protein_ratio = 0.30
            carbs_ratio = 0.45
            fats_ratio = 0.25
        elif goal == 'athletic':
            # Moderate protein, high carbs, low fat
            protein_ratio = 0.25
            carbs_ratio = 0.50
            fats_ratio = 0.25
        else:  # maintenance
            # Balanced
            protein_ratio = 0.30
            carbs_ratio = 0.40
            fats_ratio = 0.30
        
        protein_grams = round((calories * protein_ratio) / 4)  # 4 cal/g
        carbs_grams = round((calories * carbs_ratio) / 4)      # 4 cal/g
        fats_grams = round((calories * fats_ratio) / 9)        # 9 cal/g
        
        return {
            'protein': protein_grams,
            'carbs': carbs_grams,
            'fats': fats_grams
        }
    
    def recommend_meals(self, user_profile, available_meals, num_recommendations=5):
        """
        Recommend meals based on user profile and preferences
        """
        recommendations = []
        
        for meal in available_meals:
            # Calculate compatibility score
            score = self.calculate_meal_score(user_profile, meal)
            recommendations.append({
                'meal': meal,
                'score': score
            })
        
        # Sort by score and return top N
        recommendations.sort(key=lambda x: x['score'], reverse=True)
        return recommendations[:num_recommendations]
    
    def calculate_meal_score(self, user_profile, meal):
        """
        Calculate how well a meal matches user preferences
        """
        score = 100.0
        
        # Check dietary restrictions
        dietary_pref = user_profile.get('dietary_preference', 'none')
        meal_tags = meal.get('dietary_tags', '').lower()
        
        if dietary_pref == 'vegan' and 'vegan' not in meal_tags:
            return 0.0
        if dietary_pref == 'vegetarian' and 'vegetarian' not in meal_tags and 'vegan' not in meal_tags:
            return 0.0
        
        # Check calorie target
        target_calories = user_profile.get('target_calories', 2000) / 3  # Per meal
        meal_calories = meal.get('calories', 0)
        calorie_diff = abs(meal_calories - target_calories)
        score -= (calorie_diff / target_calories) * 30  # Max 30 point penalty
        
        # Check protein target
        target_protein = user_profile.get('target_protein', 50) / 3  # Per meal
        meal_protein = meal.get('protein', 0)
        protein_diff = abs(meal_protein - target_protein)
        score -= (protein_diff / target_protein) * 20  # Max 20 point penalty
        
        # Bonus for matching dietary tags
        if dietary_pref in meal_tags:
            score += 10
        
        return max(0, score)
    
    def save_model(self, filepath):
        """Save trained model to file"""
        with open(filepath, 'wb') as f:
            pickle.dump({
                'model': self.model,
                'scaler': self.scaler
            }, f)
    
    def load_model(self, filepath):
        """Load trained model from file"""
        try:
            with open(filepath, 'rb') as f:
                data = pickle.load(f)
                self.model = data['model']
                self.scaler = data['scaler']
            return True
        except:
            return False

# CLI Interface
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        sys.exit(1)
    
    command = sys.argv[1]
    recommender = MealRecommender()
    
    if command == 'calculate_bmr':
        # python meal_recommender.py calculate_bmr weight height age gender
        weight = float(sys.argv[2])
        height = float(sys.argv[3])
        age = int(sys.argv[4])
        gender = sys.argv[5]
        
        bmr = recommender.calculate_bmr(weight, height, age, gender)
        print(json.dumps({'bmr': bmr}))
    
    elif command == 'calculate_tdee':
        # python meal_recommender.py calculate_tdee bmr activity_level
        bmr = float(sys.argv[2])
        activity_level = sys.argv[3]
        
        tdee = recommender.calculate_tdee(bmr, activity_level)
        print(json.dumps({'tdee': tdee}))
    
    elif command == 'calculate_macros':
        # python meal_recommender.py calculate_macros calories goal
        calories = float(sys.argv[2])
        goal = sys.argv[3]
        
        macros = recommender.calculate_macros(calories, goal)
        print(json.dumps(macros))
    
    else:
        print(json.dumps({'error': 'Unknown command'}))
