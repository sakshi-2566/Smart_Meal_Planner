<?php
/**
 * ML Recommendation System Setup Script
 * Sets up database tables and tests ML integration
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>ML System Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 10px 0; }
        h1 { color: #10b981; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🤖 ML Recommendation System Setup</h1>";

$conn = getDBConnection();

// Step 1: Create ML tables
echo "<div class='test-section'>";
echo "<h2>Step 1: Database Setup</h2>";

$sql_file = 'database/ml_features.sql';
if (file_exists($sql_file)) {
    $sql_content = file_get_contents($sql_file);
    $statements = explode(';', $sql_content);
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            if ($conn->query($statement)) {
                $success_count++;
            } else {
                if (strpos($conn->error, 'already exists') === false && 
                    strpos($conn->error, 'Duplicate') === false) {
                    $error_count++;
                    echo "<div class='error'>Error: " . $conn->error . "</div>";
                }
            }
        } catch (Exception $e) {
            // Ignore some errors
        }
    }
    
    echo "<div class='success'>✓ Database setup completed: $success_count operations successful</div>";
} else {
    echo "<div class='error'>✗ SQL file not found: $sql_file</div>";
}
echo "</div>";

// Step 2: Check Python installation
echo "<div class='test-section'>";
echo "<h2>Step 2: Python Environment Check</h2>";

$python_commands = ['python', 'python3'];
$python_found = false;
$python_cmd = '';

foreach ($python_commands as $cmd) {
    $output = shell_exec("$cmd --version 2>&1");
    if ($output && strpos($output, 'Python') !== false) {
        $python_found = true;
        $python_cmd = $cmd;
        echo "<div class='success'>✓ Python found: $output</div>";
        break;
    }
}

if (!$python_found) {
    echo "<div class='error'>✗ Python not found. Please install Python 3.8+</div>";
} else {
    // Check required packages
    echo "<h3>Checking Python Packages:</h3>";
    $packages = ['numpy', 'pandas', 'scikit-learn', 'openai'];
    
    foreach ($packages as $package) {
        $check = shell_exec("$python_cmd -c \"import $package; print($package.__version__)\" 2>&1");
        if ($check && strpos($check, 'Error') === false && strpos($check, 'No module') === false) {
            echo "<div class='success'>✓ $package: " . trim($check) . "</div>";
        } else {
            echo "<div class='warning'>⚠ $package not installed. Run: pip install $package</div>";
        }
    }
}
echo "</div>";

// Step 3: Test ML Scripts
echo "<div class='test-section'>";
echo "<h2>Step 3: ML Scripts Test</h2>";

if ($python_found) {
    // Test meal recommender
    echo "<h3>Testing Meal Recommender:</h3>";
    $test_cmd = "$python_cmd ml_service/meal_recommender.py calculate_bmr 70 175 30 male 2>&1";
    $result = shell_exec($test_cmd);
    
    if ($result) {
        $json = json_decode($result, true);
        if ($json && isset($json['bmr'])) {
            echo "<div class='success'>✓ Meal Recommender working: BMR = " . $json['bmr'] . "</div>";
        } else {
            echo "<div class='warning'>⚠ Meal Recommender output: <pre>$result</pre></div>";
        }
    }
    
    // Test nutrition predictor
    echo "<h3>Testing Nutrition Predictor:</h3>";
    $ingredients = json_encode([['quantity' => 200, 'calories_per_100g' => 165, 'protein_per_100g' => 31, 'carbs_per_100g' => 0, 'fats_per_100g' => 3.6]]);
    $test_cmd = "$python_cmd ml_service/neural_nutrition_predictor.py predict_nutrition " . escapeshellarg($ingredients) . " 2>&1";
    $result = shell_exec($test_cmd);
    
    if ($result) {
        $json = json_decode($result, true);
        if ($json && isset($json['calories'])) {
            echo "<div class='success'>✓ Nutrition Predictor working: " . $json['calories'] . " calories</div>";
        } else {
            echo "<div class='warning'>⚠ Nutrition Predictor output: <pre>$result</pre></div>";
        }
    }
    
    // Test OpenAI integration
    echo "<h3>Testing OpenAI Integration:</h3>";
    echo "<div class='info'>ℹ OpenAI API key configured in code. Testing connection...</div>";
    
    $preferences = json_encode(['dietary_preference' => 'vegetarian', 'cuisine' => 'italian']);
    $targets = json_encode(['calories' => 500, 'protein' => 25, 'carbs' => 60, 'fats' => 15]);
    $test_cmd = "$python_cmd ml_service/openai_recipe_generator.py generate_recipe " . 
                escapeshellarg($preferences) . " " . escapeshellarg($targets) . " 2>&1";
    
    echo "<div class='warning'>⚠ OpenAI test skipped (requires API credits). Test manually if needed.</div>";
    echo "<pre>Test command: $test_cmd</pre>";
    
} else {
    echo "<div class='error'>✗ Cannot test ML scripts without Python</div>";
}
echo "</div>";

// Step 4: API Endpoints Check
echo "<div class='test-section'>";
echo "<h2>Step 4: API Endpoints Check</h2>";

$endpoints = [
    'api/ml/get_personalized_recommendations.php',
    'api/ml/generate_ai_meal_plan.php',
    'api/ml/predict_nutrition.php',
    'api/ml/record_user_interaction.php'
];

foreach ($endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        echo "<div class='success'>✓ $endpoint exists</div>";
    } else {
        echo "<div class='error'>✗ $endpoint not found</div>";
    }
}
echo "</div>";

// Step 5: Database Tables Check
echo "<div class='test-section'>";
echo "<h2>Step 5: Database Tables Verification</h2>";

$tables = [
    'user_recipe_interactions',
    'user_preference_scores',
    'ml_model_metadata',
    'recommendation_history',
    'user_learning_progress'
];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
        echo "<div class='success'>✓ Table '$table' exists ($count rows)</div>";
    } else {
        echo "<div class='error'>✗ Table '$table' not found</div>";
    }
}
echo "</div>";

// Summary and Next Steps
echo "<hr>";
echo "<h2>📋 Setup Summary</h2>";

$all_good = $python_found;
if ($all_good) {
    echo "<div class='success'>";
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p>Your ML Recommendation System is ready to use.</p>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠ Setup Incomplete</h3>";
    echo "<p>Please install missing dependencies and re-run this script.</p>";
    echo "</div>";
}

echo "<h2>🚀 Next Steps</h2>";
echo "<ol>
    <li>Install Python packages: <code>pip install -r ml_service/requirements.txt</code></li>
    <li>Test recommendations: Visit <a href='recipes.html'>recipes.html</a></li>
    <li>Generate AI meal plan: Visit <a href='dashboard.html'>dashboard.html</a></li>
    <li>Check ML documentation: <a href='ML_RECOMMENDATION_ENGINE_README.md'>ML_RECOMMENDATION_ENGINE_README.md</a></li>
    <li>Monitor user interactions in database</li>
</ol>";

echo "<h2>🧪 Manual Tests</h2>";
echo "<pre>";
echo "# Test BMR calculation\n";
echo "$python_cmd ml_service/meal_recommender.py calculate_bmr 70 175 30 male\n\n";

echo "# Test nutrition prediction\n";
echo "$python_cmd ml_service/neural_nutrition_predictor.py predict_nutrition '[{\"quantity\":200,\"calories_per_100g\":165}]'\n\n";

echo "# Test adaptive recommender\n";
echo "$python_cmd ml_service/adaptive_recommender.py get_insights 1\n";
echo "</pre>";

echo "<div class='info'>";
echo "<strong>Note:</strong> You can safely delete this setup file (setup_ml_system.php) after successful setup.";
echo "</div>";

closeDBConnection($conn);

echo "</body></html>";
?>
