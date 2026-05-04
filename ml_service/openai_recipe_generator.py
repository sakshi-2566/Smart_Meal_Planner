"""
OpenAI-powered Recipe Generator
Generates personalized recipes using GPT models
"""

import os
import json
import sys
from openai import OpenAI

class RecipeGenerator:
    def __init__(self, api_key=None):
        """Initialize OpenAI client"""
        # Use provided API key or environment variable
        self.api_key = api_key or os.getenv('OPENAI_API_KEY')
        if not self.api_key:
            raise ValueError("OpenAI API key not provided")
        
        self.client = OpenAI(api_key=self.api_key)
    
    def generate_recipe(self, user_preferences, nutritional_targets):
        """
        Generate a recipe based on user preferences and nutritional targets
        
        Args:
            user_preferences: dict with dietary_preference, cuisine, ingredients
            nutritional_targets: dict with target calories, protein, carbs, fats
        
        Returns:
            dict with recipe details
        """
        prompt = self._create_prompt(user_preferences, nutritional_targets)
        
        try:
            response = self.client.chat.completions.create(
                model="gpt-3.5-turbo",
                messages=[
                    {"role": "system", "content": "You are a professional nutritionist and chef. Create healthy, delicious recipes with accurate nutritional information."},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                max_tokens=1000
            )
            
            recipe_text = response.choices[0].message.content
            recipe = self._parse_recipe(recipe_text)
            
            return {
                'success': True,
                'recipe': recipe
            }
        
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _create_prompt(self, preferences, targets):
        """Create a detailed prompt for recipe generation"""
        dietary = preferences.get('dietary_preference', 'none')
        cuisine = preferences.get('cuisine', 'any')
        ingredients = preferences.get('ingredients', [])
        
        prompt = f"""Create a {dietary} recipe with the following requirements:

Nutritional Targets:
- Calories: {targets.get('calories', 500)} kcal (±50)
- Protein: {targets.get('protein', 30)}g
- Carbs: {targets.get('carbs', 50)}g
- Fats: {targets.get('fats', 15)}g

Preferences:
- Dietary: {dietary}
- Cuisine: {cuisine}
"""
        
        if ingredients:
            prompt += f"- Must include: {', '.join(ingredients)}\n"
        
        prompt += """
Please provide:
1. Recipe Name
2. Ingredients (with quantities)
3. Step-by-step Instructions
4. Prep Time and Cook Time
5. Servings
6. Nutritional Information (calories, protein, carbs, fats per serving)

Format the response as JSON with these keys: name, ingredients, instructions, prep_time, cook_time, servings, nutrition
"""
        
        return prompt
    
    def _parse_recipe(self, recipe_text):
        """Parse the AI-generated recipe text"""
        try:
            # Try to parse as JSON first
            recipe = json.loads(recipe_text)
            return recipe
        except:
            # If not JSON, parse manually
            return {
                'name': 'AI Generated Recipe',
                'description': recipe_text,
                'ingredients': [],
                'instructions': recipe_text,
                'prep_time': 15,
                'cook_time': 30,
                'servings': 2,
                'nutrition': {
                    'calories': 500,
                    'protein': 30,
                    'carbs': 50,
                    'fats': 15
                }
            }
    
    def generate_meal_plan(self, user_profile, days=7):
        """
        Generate a complete meal plan for multiple days
        
        Args:
            user_profile: dict with user preferences and targets
            days: number of days to plan
        
        Returns:
            dict with meal plan
        """
        daily_calories = user_profile.get('target_calories', 2000)
        dietary_pref = user_profile.get('dietary_preference', 'none')
        
        # Distribute calories across meals
        breakfast_cal = int(daily_calories * 0.30)
        lunch_cal = int(daily_calories * 0.40)
        dinner_cal = int(daily_calories * 0.30)
        
        meal_plan = []
        
        for day in range(1, days + 1):
            day_meals = {
                'day': day,
                'meals': []
            }
            
            # Generate breakfast
            breakfast = self.generate_recipe(
                {'dietary_preference': dietary_pref, 'cuisine': 'breakfast'},
                {'calories': breakfast_cal, 'protein': 20, 'carbs': 40, 'fats': 10}
            )
            if breakfast['success']:
                day_meals['meals'].append({
                    'type': 'breakfast',
                    'recipe': breakfast['recipe']
                })
            
            # Generate lunch
            lunch = self.generate_recipe(
                {'dietary_preference': dietary_pref, 'cuisine': 'any'},
                {'calories': lunch_cal, 'protein': 35, 'carbs': 50, 'fats': 20}
            )
            if lunch['success']:
                day_meals['meals'].append({
                    'type': 'lunch',
                    'recipe': lunch['recipe']
                })
            
            # Generate dinner
            dinner = self.generate_recipe(
                {'dietary_preference': dietary_pref, 'cuisine': 'any'},
                {'calories': dinner_cal, 'protein': 30, 'carbs': 40, 'fats': 15}
            )
            if dinner['success']:
                day_meals['meals'].append({
                    'type': 'dinner',
                    'recipe': dinner['recipe']
                })
            
            meal_plan.append(day_meals)
        
        return {
            'success': True,
            'meal_plan': meal_plan,
            'total_days': days
        }
    
    def suggest_substitutions(self, ingredient, dietary_restriction):
        """
        Suggest ingredient substitutions based on dietary restrictions
        """
        prompt = f"""Suggest 3 healthy substitutions for "{ingredient}" that are suitable for a {dietary_restriction} diet.
        
Provide the response as a JSON array of objects with 'name' and 'reason' keys."""
        
        try:
            response = self.client.chat.completions.create(
                model="gpt-3.5-turbo",
                messages=[
                    {"role": "system", "content": "You are a nutritionist helping with ingredient substitutions."},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                max_tokens=300
            )
            
            substitutions_text = response.choices[0].message.content
            substitutions = json.loads(substitutions_text)
            
            return {
                'success': True,
                'substitutions': substitutions
            }
        
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }

# CLI Interface
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        sys.exit(1)
    
    command = sys.argv[1]
    
    # Get API key from environment or argument
    api_key = os.getenv('OPENAI_API_KEY')
    if not api_key:
        print(json.dumps({'error': 'OPENAI_API_KEY not set'}))
        sys.exit(1)
    
    generator = RecipeGenerator(api_key)
    
    if command == 'generate_recipe':
        # python openai_recipe_generator.py generate_recipe '{"dietary_preference":"vegan"}' '{"calories":500}'
        preferences = json.loads(sys.argv[2])
        targets = json.loads(sys.argv[3])
        
        result = generator.generate_recipe(preferences, targets)
        print(json.dumps(result))
    
    elif command == 'generate_meal_plan':
        # python openai_recipe_generator.py generate_meal_plan '{"target_calories":2000,"dietary_preference":"vegetarian"}' 7
        profile = json.loads(sys.argv[2])
        days = int(sys.argv[3]) if len(sys.argv) > 3 else 7
        
        result = generator.generate_meal_plan(profile, days)
        print(json.dumps(result))
    
    elif command == 'suggest_substitutions':
        # python openai_recipe_generator.py suggest_substitutions "chicken" "vegetarian"
        ingredient = sys.argv[2]
        dietary = sys.argv[3]
        
        result = generator.suggest_substitutions(ingredient, dietary)
        print(json.dumps(result))
    
    else:
        print(json.dumps({'error': 'Unknown command'}))
