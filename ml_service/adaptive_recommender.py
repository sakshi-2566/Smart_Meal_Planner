"""
Adaptive Meal Recommendation System
Learns from user behavior and adapts recommendations over time
"""

import numpy as np
import json
import sys
from datetime import datetime, timedelta
from collections import defaultdict


class AdaptiveRecommender:
    """
    Adaptive recommendation system that learns from user preferences
    Uses collaborative filtering and content-based filtering
    """
    
    def __init__(self):
        self.user_preferences = defaultdict(dict)
        self.recipe_features = {}
        self.interaction_history = []
        self.learning_rate = 0.1
    
    def record_interaction(self, user_id, recipe_id, interaction_type, rating=None):
        """
        Record user interaction with a recipe
        
        Args:
            user_id: User identifier
            recipe_id: Recipe identifier
            interaction_type: 'view', 'favorite', 'cook', 'rate'
            rating: Optional rating (1-5)
        """
        interaction = {
            'user_id': user_id,
            'recipe_id': recipe_id,
            'type': interaction_type,
            'rating': rating,
            'timestamp': datetime.now().isoformat()
        }
        
        self.interaction_history.append(interaction)
        
        # Update user preferences
        if user_id not in self.user_preferences:
            self.user_preferences[user_id] = {
                'favorite_recipes': [],
                'disliked_recipes': [],
                'preferred_ingredients': defaultdict(int),
                'preferred_tags': defaultdict(int),
                'avg_calories': 0,
                'interaction_count': 0
            }
        
        prefs = self.user_preferences[user_id]
        prefs['interaction_count'] += 1
        
        # Update based on interaction type
        if interaction_type == 'favorite':
            if recipe_id not in prefs['favorite_recipes']:
                prefs['favorite_recipes'].append(recipe_id)
        
        elif interaction_type == 'rate' and rating:
            if rating >= 4:
                if recipe_id not in prefs['favorite_recipes']:
                    prefs['favorite_recipes'].append(recipe_id)
            elif rating <= 2:
                if recipe_id not in prefs['disliked_recipes']:
                    prefs['disliked_recipes'].append(recipe_id)
    
    def update_preferences_from_recipe(self, user_id, recipe_data, positive=True):
        """
        Update user preferences based on recipe features
        
        Args:
            user_id: User identifier
            recipe_data: Recipe information (ingredients, tags, nutrition)
            positive: True if user liked it, False if disliked
        """
        if user_id not in self.user_preferences:
            self.user_preferences[user_id] = {
                'preferred_ingredients': defaultdict(int),
                'preferred_tags': defaultdict(int),
                'avg_calories': 0,
                'interaction_count': 0
            }
        
        prefs = self.user_preferences[user_id]
        weight = 1 if positive else -1
        
        # Update ingredient preferences
        for ingredient in recipe_data.get('ingredients', []):
            ing_name = ingredient.get('ingredient_name', '')
            prefs['preferred_ingredients'][ing_name] += weight
        
        # Update tag preferences
        tags = recipe_data.get('dietary_tags', '').split(',')
        for tag in tags:
            tag = tag.strip()
            if tag:
                prefs['preferred_tags'][tag] += weight
        
        # Update calorie preference (running average)
        calories = recipe_data.get('calories', 0)
        if prefs['avg_calories'] == 0:
            prefs['avg_calories'] = calories
        else:
            prefs['avg_calories'] = (prefs['avg_calories'] * 0.9) + (calories * 0.1)
    
    def get_personalized_recommendations(self, user_id, available_recipes, num_recommendations=10):
        """
        Get personalized recipe recommendations for a user
        
        Args:
            user_id: User identifier
            available_recipes: List of available recipes
            num_recommendations: Number of recommendations to return
        
        Returns:
            List of recommended recipes with scores
        """
        if user_id not in self.user_preferences:
            # New user: return popular recipes
            return self._get_popular_recipes(available_recipes, num_recommendations)
        
        prefs = self.user_preferences[user_id]
        recommendations = []
        
        for recipe in available_recipes:
            # Skip already favorited recipes
            if recipe['id'] in prefs.get('favorite_recipes', []):
                continue
            
            # Skip disliked recipes
            if recipe['id'] in prefs.get('disliked_recipes', []):
                continue
            
            # Calculate recommendation score
            score = self._calculate_recommendation_score(user_id, recipe)
            
            recommendations.append({
                'recipe': recipe,
                'score': score,
                'reason': self._generate_recommendation_reason(user_id, recipe)
            })
        
        # Sort by score and return top N
        recommendations.sort(key=lambda x: x['score'], reverse=True)
        return recommendations[:num_recommendations]
    
    def _calculate_recommendation_score(self, user_id, recipe):
        """
        Calculate recommendation score for a recipe
        Combines multiple factors: ingredients, tags, nutrition, popularity
        """
        prefs = self.user_preferences[user_id]
        score = 50.0  # Base score
        
        # Ingredient matching
        ingredient_score = 0
        for ingredient in recipe.get('ingredients', []):
            ing_name = ingredient.get('ingredient_name', '')
            if ing_name in prefs['preferred_ingredients']:
                ingredient_score += prefs['preferred_ingredients'][ing_name]
        score += min(ingredient_score * 2, 20)  # Max 20 points
        
        # Tag matching
        tag_score = 0
        tags = recipe.get('dietary_tags', '').split(',')
        for tag in tags:
            tag = tag.strip()
            if tag in prefs['preferred_tags']:
                tag_score += prefs['preferred_tags'][tag]
        score += min(tag_score * 3, 15)  # Max 15 points
        
        # Calorie matching
        if prefs['avg_calories'] > 0:
            calorie_diff = abs(recipe.get('calories', 0) - prefs['avg_calories'])
            calorie_score = max(0, 15 - (calorie_diff / 100))
            score += calorie_score
        
        # Recipe popularity (ratings and favorites)
        avg_rating = recipe.get('avg_rating', 0)
        score += avg_rating * 2  # Max 10 points
        
        favorite_count = recipe.get('favorite_count', 0)
        score += min(favorite_count * 0.5, 10)  # Max 10 points
        
        return round(score, 2)
    
    def _generate_recommendation_reason(self, user_id, recipe):
        """Generate human-readable reason for recommendation"""
        prefs = self.user_preferences[user_id]
        reasons = []
        
        # Check for matching ingredients
        matching_ingredients = []
        for ingredient in recipe.get('ingredients', []):
            ing_name = ingredient.get('ingredient_name', '')
            if ing_name in prefs['preferred_ingredients'] and prefs['preferred_ingredients'][ing_name] > 0:
                matching_ingredients.append(ing_name)
        
        if matching_ingredients:
            reasons.append(f"Contains {', '.join(matching_ingredients[:2])}")
        
        # Check for matching tags
        matching_tags = []
        tags = recipe.get('dietary_tags', '').split(',')
        for tag in tags:
            tag = tag.strip()
            if tag in prefs['preferred_tags'] and prefs['preferred_tags'][tag] > 0:
                matching_tags.append(tag)
        
        if matching_tags:
            reasons.append(f"Matches your {', '.join(matching_tags[:2])} preference")
        
        # High rating
        if recipe.get('avg_rating', 0) >= 4.5:
            reasons.append("Highly rated by users")
        
        # Popular
        if recipe.get('favorite_count', 0) > 10:
            reasons.append("Popular choice")
        
        return ' • '.join(reasons) if reasons else "Recommended for you"
    
    def _get_popular_recipes(self, recipes, num_recommendations):
        """Get popular recipes for new users"""
        scored_recipes = []
        
        for recipe in recipes:
            popularity_score = (
                recipe.get('avg_rating', 0) * 10 +
                recipe.get('favorite_count', 0) * 2 +
                recipe.get('rating_count', 0)
            )
            
            scored_recipes.append({
                'recipe': recipe,
                'score': popularity_score,
                'reason': 'Popular recipe'
            })
        
        scored_recipes.sort(key=lambda x: x['score'], reverse=True)
        return scored_recipes[:num_recommendations]
    
    def get_weekly_meal_plan(self, user_id, available_recipes, dietary_restrictions=None):
        """
        Generate a weekly meal plan adapted to user preferences
        
        Args:
            user_id: User identifier
            available_recipes: List of available recipes
            dietary_restrictions: Optional dietary restrictions
        
        Returns:
            7-day meal plan with breakfast, lunch, dinner
        """
        # Filter recipes by dietary restrictions
        if dietary_restrictions:
            available_recipes = [
                r for r in available_recipes
                if dietary_restrictions.lower() in r.get('dietary_tags', '').lower()
            ]
        
        # Get personalized recommendations
        recommendations = self.get_personalized_recommendations(
            user_id, available_recipes, num_recommendations=30
        )
        
        meal_plan = []
        used_recipes = set()
        
        for day in range(1, 8):
            day_meals = {
                'day': day,
                'date': (datetime.now() + timedelta(days=day-1)).strftime('%Y-%m-%d'),
                'meals': []
            }
            
            # Select breakfast (lower calories)
            breakfast = self._select_meal_by_type(
                recommendations, used_recipes, 'breakfast', max_calories=500
            )
            if breakfast:
                day_meals['meals'].append({
                    'type': 'breakfast',
                    'recipe': breakfast['recipe'],
                    'reason': breakfast['reason']
                })
                used_recipes.add(breakfast['recipe']['id'])
            
            # Select lunch (moderate calories)
            lunch = self._select_meal_by_type(
                recommendations, used_recipes, 'lunch', max_calories=700
            )
            if lunch:
                day_meals['meals'].append({
                    'type': 'lunch',
                    'recipe': lunch['recipe'],
                    'reason': lunch['reason']
                })
                used_recipes.add(lunch['recipe']['id'])
            
            # Select dinner (moderate calories)
            dinner = self._select_meal_by_type(
                recommendations, used_recipes, 'dinner', max_calories=700
            )
            if dinner:
                day_meals['meals'].append({
                    'type': 'dinner',
                    'recipe': dinner['recipe'],
                    'reason': dinner['reason']
                })
                used_recipes.add(dinner['recipe']['id'])
            
            meal_plan.append(day_meals)
        
        return meal_plan
    
    def _select_meal_by_type(self, recommendations, used_recipes, meal_type, max_calories=600):
        """Select a meal from recommendations based on type and constraints"""
        for rec in recommendations:
            recipe = rec['recipe']
            
            # Skip if already used
            if recipe['id'] in used_recipes:
                continue
            
            # Check calorie constraint
            if recipe.get('calories', 0) > max_calories:
                continue
            
            return rec
        
        return None
    
    def get_user_insights(self, user_id):
        """
        Get insights about user preferences and behavior
        
        Returns:
            Dict with user insights
        """
        if user_id not in self.user_preferences:
            return {'message': 'No data available yet'}
        
        prefs = self.user_preferences[user_id]
        
        # Top ingredients
        top_ingredients = sorted(
            prefs['preferred_ingredients'].items(),
            key=lambda x: x[1],
            reverse=True
        )[:5]
        
        # Top tags
        top_tags = sorted(
            prefs['preferred_tags'].items(),
            key=lambda x: x[1],
            reverse=True
        )[:5]
        
        return {
            'total_interactions': prefs['interaction_count'],
            'favorite_count': len(prefs.get('favorite_recipes', [])),
            'top_ingredients': [{'name': k, 'score': v} for k, v in top_ingredients],
            'top_dietary_tags': [{'name': k, 'score': v} for k, v in top_tags],
            'avg_preferred_calories': round(prefs['avg_calories'], 0)
        }
    
    def save_preferences(self, filepath):
        """Save user preferences to file"""
        data = {
            'user_preferences': dict(self.user_preferences),
            'interaction_history': self.interaction_history
        }
        
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=2)
    
    def load_preferences(self, filepath):
        """Load user preferences from file"""
        try:
            with open(filepath, 'r') as f:
                data = json.load(f)
                self.user_preferences = defaultdict(dict, data['user_preferences'])
                self.interaction_history = data['interaction_history']
            return True
        except Exception as e:
            print(f"Error loading preferences: {e}", file=sys.stderr)
            return False


# CLI Interface
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        sys.exit(1)
    
    command = sys.argv[1]
    recommender = AdaptiveRecommender()
    
    if command == 'record_interaction':
        # python adaptive_recommender.py record_interaction 1 123 favorite
        user_id = int(sys.argv[2])
        recipe_id = int(sys.argv[3])
        interaction_type = sys.argv[4]
        rating = int(sys.argv[5]) if len(sys.argv) > 5 else None
        
        recommender.record_interaction(user_id, recipe_id, interaction_type, rating)
        print(json.dumps({'success': True}))
    
    elif command == 'get_recommendations':
        # python adaptive_recommender.py get_recommendations 1 '[{...}]' 10
        user_id = int(sys.argv[2])
        recipes = json.loads(sys.argv[3])
        num = int(sys.argv[4]) if len(sys.argv) > 4 else 10
        
        recommendations = recommender.get_personalized_recommendations(user_id, recipes, num)
        print(json.dumps(recommendations))
    
    elif command == 'get_insights':
        # python adaptive_recommender.py get_insights 1
        user_id = int(sys.argv[2])
        insights = recommender.get_user_insights(user_id)
        print(json.dumps(insights))
    
    else:
        print(json.dumps({'error': 'Unknown command'}))
