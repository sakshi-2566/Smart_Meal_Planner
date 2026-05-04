"""
Model Retraining Script
Retrains the recommendation model based on user feedback
"""

import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error, r2_score
import pickle
import json
import sys
from datetime import datetime

def load_training_data():
    """Load training data from CSV"""
    try:
        df = pd.read_csv('training_data.csv')
        return df
    except Exception as e:
        print(f"Error loading data: {e}")
        return None

def preprocess_data(df):
    """Preprocess training data"""
    # Handle missing values
    df = df.fillna({
        'age': 30,
        'weight': 70,
        'height': 170,
        'activity_level': 'moderate',
        'goal': 'maintenance'
    })
    
    # Encode categorical variables
    le_activity = LabelEncoder()
    le_goal = LabelEncoder()
    
    df['activity_encoded'] = le_activity.fit_transform(df['activity_level'])
    df['goal_encoded'] = le_goal.fit_transform(df['goal'])
    
    # Select features
    features = ['calories', 'protein', 'carbs', 'fats', 'age', 'weight', 
                'height', 'activity_encoded', 'goal_encoded']
    
    X = df[features]
    y = df['rating']
    
    return X, y, le_activity, le_goal

def train_model(X, y):
    """Train the recommendation model"""
    # Split data
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )
    
    # Scale features
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)
    
    # Train Random Forest model
    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=10,
        min_samples_split=5,
        random_state=42,
        n_jobs=-1
    )
    
    model.fit(X_train_scaled, y_train)
    
    # Evaluate
    y_pred = model.predict(X_test_scaled)
    mse = mean_squared_error(y_test, y_pred)
    r2 = r2_score(y_test, y_pred)
    
    return model, scaler, mse, r2

def save_model(model, scaler, le_activity, le_goal, metrics):
    """Save trained model and metadata"""
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    # Save model
    model_data = {
        'model': model,
        'scaler': scaler,
        'le_activity': le_activity,
        'le_goal': le_goal,
        'metrics': metrics,
        'timestamp': timestamp
    }
    
    with open(f'models/recommender_{timestamp}.pkl', 'wb') as f:
        pickle.dump(model_data, f)
    
    # Save as current model
    with open('models/recommender_current.pkl', 'wb') as f:
        pickle.dump(model_data, f)
    
    # Save metadata
    metadata = {
        'timestamp': timestamp,
        'mse': float(metrics['mse']),
        'r2_score': float(metrics['r2']),
        'model_type': 'RandomForestRegressor',
        'n_estimators': 100
    }
    
    with open('models/model_metadata.json', 'w') as f:
        json.dump(metadata, f, indent=2)
    
    return timestamp

def main():
    print("Starting model retraining...")
    
    # Load data
    df = load_training_data()
    if df is None or len(df) < 10:
        print("Error: Insufficient training data")
        sys.exit(1)
    
    print(f"Loaded {len(df)} training samples")
    
    # Preprocess
    X, y, le_activity, le_goal = preprocess_data(df)
    print("Data preprocessed")
    
    # Train
    model, scaler, mse, r2 = train_model(X, y)
    print(f"Model trained - MSE: {mse:.4f}, R²: {r2:.4f}")
    
    # Save
    metrics = {'mse': mse, 'r2': r2}
    timestamp = save_model(model, scaler, le_activity, le_goal, metrics)
    print(f"Model saved: recommender_{timestamp}.pkl")
    
    # Output results
    results = {
        'success': True,
        'timestamp': timestamp,
        'samples': len(df),
        'mse': float(mse),
        'r2_score': float(r2),
        'accuracy': float(r2 * 100)
    }
    
    print(json.dumps(results))
    
    return 0

if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': str(e)
        }))
        sys.exit(1)
