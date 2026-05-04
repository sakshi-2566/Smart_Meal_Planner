"""
Neural Network for Nutrition Prediction
Uses deep learning to predict nutritional values and user preferences
"""

import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.linear_model import LinearRegression
import pickle
import json
import sys

try:
    import torch
    import torch.nn as nn
    import torch.optim as optim
    TORCH_AVAILABLE = True
except ImportError:
    TORCH_AVAILABLE = False
    print("Warning: PyTorch not available. Using Linear Regression fallback.", file=sys.stderr)


class NutritionPredictor:
    """
    Predicts nutritional values using ML models
    Supports both Neural Networks (PyTorch) and Linear Regression
    """
    
    def __init__(self, use_neural_network=True):
        self.use_neural_network = use_neural_network and TORCH_AVAILABLE
        self.scaler = StandardScaler()
        self.label_encoders = {}
        self.model = None
        
        if self.use_neural_network:
            self.model = NutritionNN()
        else:
            self.models = {
                'calories': LinearRegression(),
                'protein': LinearRegression(),
                'carbs': LinearRegression(),
                'fats': LinearRegression()
            }
    
    def prepare_features(self, data):
        """
        Prepare features for training/prediction
        Features: ingredient quantities, categories, user preferences
        """
        features = []
        
        # Encode categorical features
        if 'ingredient_category' in data.columns:
            if 'ingredient_category' not in self.label_encoders:
                self.label_encoders['ingredient_category'] = LabelEncoder()
                data['category_encoded'] = self.label_encoders['ingredient_category'].fit_transform(data['ingredient_category'])
            else:
                data['category_encoded'] = self.label_encoders['ingredient_category'].transform(data['ingredient_category'])
        
        # Select numerical features
        feature_columns = ['quantity', 'category_encoded', 'user_age', 'user_weight', 'user_activity']
        X = data[feature_columns].values
        
        return X
    
    def train_linear_models(self, X_train, y_train):
        """Train linear regression models for each nutrient"""
        for nutrient in ['calories', 'protein', 'carbs', 'fats']:
            if nutrient in y_train.columns:
                self.models[nutrient].fit(X_train, y_train[nutrient])
        
        return True
    
    def train_neural_network(self, X_train, y_train, epochs=100):
        """Train neural network model"""
        if not self.use_neural_network:
            return self.train_linear_models(X_train, y_train)
        
        # Convert to PyTorch tensors
        X_tensor = torch.FloatTensor(X_train)
        y_tensor = torch.FloatTensor(y_train.values)
        
        # Training setup
        criterion = nn.MSELoss()
        optimizer = optim.Adam(self.model.parameters(), lr=0.001)
        
        # Training loop
        self.model.train()
        for epoch in range(epochs):
            optimizer.zero_grad()
            outputs = self.model(X_tensor)
            loss = criterion(outputs, y_tensor)
            loss.backward()
            optimizer.step()
            
            if (epoch + 1) % 20 == 0:
                print(f'Epoch [{epoch+1}/{epochs}], Loss: {loss.item():.4f}', file=sys.stderr)
        
        return True
    
    def predict_nutrition(self, ingredients_data):
        """
        Predict nutritional values for a recipe
        
        Args:
            ingredients_data: list of dicts with ingredient info
        
        Returns:
            dict with predicted calories, protein, carbs, fats
        """
        if not self.model and not self.models:
            # Fallback: simple calculation
            return self._calculate_nutrition_simple(ingredients_data)
        
        # Prepare features
        df = pd.DataFrame(ingredients_data)
        X = self.prepare_features(df)
        X_scaled = self.scaler.transform(X)
        
        if self.use_neural_network:
            self.model.eval()
            with torch.no_grad():
                X_tensor = torch.FloatTensor(X_scaled)
                predictions = self.model(X_tensor).numpy()
            
            return {
                'calories': float(predictions[0][0]),
                'protein': float(predictions[0][1]),
                'carbs': float(predictions[0][2]),
                'fats': float(predictions[0][3])
            }
        else:
            return {
                'calories': float(self.models['calories'].predict(X_scaled)[0]),
                'protein': float(self.models['protein'].predict(X_scaled)[0]),
                'carbs': float(self.models['carbs'].predict(X_scaled)[0]),
                'fats': float(self.models['fats'].predict(X_scaled)[0])
            }
    
    def _calculate_nutrition_simple(self, ingredients_data):
        """Simple nutrition calculation based on ingredient database"""
        total_calories = 0
        total_protein = 0
        total_carbs = 0
        total_fats = 0
        
        for ingredient in ingredients_data:
            quantity = ingredient.get('quantity', 0)
            multiplier = quantity / 100  # Assuming per 100g values
            
            total_calories += ingredient.get('calories_per_100g', 0) * multiplier
            total_protein += ingredient.get('protein_per_100g', 0) * multiplier
            total_carbs += ingredient.get('carbs_per_100g', 0) * multiplier
            total_fats += ingredient.get('fats_per_100g', 0) * multiplier
        
        return {
            'calories': round(total_calories, 2),
            'protein': round(total_protein, 2),
            'carbs': round(total_carbs, 2),
            'fats': round(total_fats, 2)
        }
    
    def predict_user_preference(self, user_history, recipe_features):
        """
        Predict if user will like a recipe based on historical data
        
        Args:
            user_history: list of past recipes and ratings
            recipe_features: features of the recipe to predict
        
        Returns:
            float: predicted rating (0-5)
        """
        if not user_history:
            return 3.0  # Default neutral rating
        
        # Simple collaborative filtering approach
        similar_recipes = []
        for past_recipe in user_history:
            similarity = self._calculate_similarity(past_recipe['features'], recipe_features)
            similar_recipes.append({
                'similarity': similarity,
                'rating': past_recipe['rating']
            })
        
        # Weight ratings by similarity
        if similar_recipes:
            total_weight = sum(r['similarity'] for r in similar_recipes)
            if total_weight > 0:
                weighted_rating = sum(r['similarity'] * r['rating'] for r in similar_recipes) / total_weight
                return round(weighted_rating, 2)
        
        return 3.0
    
    def _calculate_similarity(self, features1, features2):
        """Calculate cosine similarity between two feature vectors"""
        # Simple similarity based on nutritional values
        cal_diff = abs(features1.get('calories', 0) - features2.get('calories', 0))
        protein_diff = abs(features1.get('protein', 0) - features2.get('protein', 0))
        
        # Normalize differences
        similarity = 1.0 / (1.0 + (cal_diff / 1000) + (protein_diff / 50))
        return similarity
    
    def save_model(self, filepath):
        """Save trained model"""
        if self.use_neural_network:
            torch.save({
                'model_state': self.model.state_dict(),
                'scaler': self.scaler,
                'label_encoders': self.label_encoders
            }, filepath)
        else:
            with open(filepath, 'wb') as f:
                pickle.dump({
                    'models': self.models,
                    'scaler': self.scaler,
                    'label_encoders': self.label_encoders
                }, f)
    
    def load_model(self, filepath):
        """Load trained model"""
        try:
            if self.use_neural_network:
                checkpoint = torch.load(filepath)
                self.model.load_state_dict(checkpoint['model_state'])
                self.scaler = checkpoint['scaler']
                self.label_encoders = checkpoint['label_encoders']
            else:
                with open(filepath, 'rb') as f:
                    data = pickle.load(f)
                    self.models = data['models']
                    self.scaler = data['scaler']
                    self.label_encoders = data['label_encoders']
            return True
        except Exception as e:
            print(f"Error loading model: {e}", file=sys.stderr)
            return False


class NutritionNN(nn.Module):
    """
    Neural Network for nutrition prediction
    Architecture: Input -> Hidden(64) -> Hidden(32) -> Output(4)
    """
    
    def __init__(self, input_size=5, hidden_size1=64, hidden_size2=32, output_size=4):
        super(NutritionNN, self).__init__()
        
        self.fc1 = nn.Linear(input_size, hidden_size1)
        self.relu1 = nn.ReLU()
        self.dropout1 = nn.Dropout(0.2)
        
        self.fc2 = nn.Linear(hidden_size1, hidden_size2)
        self.relu2 = nn.ReLU()
        self.dropout2 = nn.Dropout(0.2)
        
        self.fc3 = nn.Linear(hidden_size2, output_size)
    
    def forward(self, x):
        x = self.fc1(x)
        x = self.relu1(x)
        x = self.dropout1(x)
        
        x = self.fc2(x)
        x = self.relu2(x)
        x = self.dropout2(x)
        
        x = self.fc3(x)
        return x


# CLI Interface
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        sys.exit(1)
    
    command = sys.argv[1]
    predictor = NutritionPredictor(use_neural_network=False)  # Use linear regression by default
    
    if command == 'predict_nutrition':
        # python neural_nutrition_predictor.py predict_nutrition '[{"quantity":200,"calories_per_100g":165}]'
        ingredients = json.loads(sys.argv[2])
        result = predictor.predict_nutrition(ingredients)
        print(json.dumps(result))
    
    elif command == 'predict_preference':
        # python neural_nutrition_predictor.py predict_preference '[]' '{"calories":500}'
        history = json.loads(sys.argv[2])
        features = json.loads(sys.argv[3])
        rating = predictor.predict_user_preference(history, features)
        print(json.dumps({'predicted_rating': rating}))
    
    else:
        print(json.dumps({'error': 'Unknown command'}))
