<?php
/**
 * Environment Setup Helper
 * Helps users configure their .env file
 */

require_once 'config/env.php';

echo "<h2>Smart Meal Planner - Environment Setup</h2>";

// Check if .env file exists
$envPath = '.env';
$envExists = file_exists($envPath);

echo "<h3>Environment File Status</h3>";
if ($envExists) {
    echo "<p style='color: green;'>✓ .env file exists</p>";
} else {
    echo "<p style='color: orange;'>⚠ .env file not found - will be created automatically</p>";
}

// Load current configuration
EnvLoader::load();

echo "<h3>Current Configuration</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Setting</th><th>Current Value</th><th>Status</th></tr>";

// OpenAI API Key
$openaiKey = EnvLoader::get('OPENAI_API_KEY', 'Not set');
$openaiStatus = EnvLoader::isOpenAIConfigured() ? 
    "<span style='color: green;'>✓ Configured</span>" : 
    "<span style='color: red;'>✗ Not configured</span>";
echo "<tr><td>OpenAI API Key</td><td>" . 
     (strlen($openaiKey) > 20 ? substr($openaiKey, 0, 20) . "..." : $openaiKey) . 
     "</td><td>$openaiStatus</td></tr>";

// Database settings
$dbHost = EnvLoader::get('DB_HOST', 'localhost');
$dbName = EnvLoader::get('DB_NAME', 'smart_meal_planner');
$dbUser = EnvLoader::get('DB_USERNAME', 'root');
echo "<tr><td>Database Host</td><td>$dbHost</td><td>✓</td></tr>";
echo "<tr><td>Database Name</td><td>$dbName</td><td>✓</td></tr>";
echo "<tr><td>Database User</td><td>$dbUser</td><td>✓</td></tr>";

echo "</table>";

// Configuration form
echo "<h3>Update Configuration</h3>";
echo "<form method='POST'>";
echo "<table>";
echo "<tr><td><label>OpenAI API Key:</label></td>";
echo "<td><input type='text' name='openai_key' value='" . htmlspecialchars($openaiKey) . "' style='width: 400px;' placeholder='sk-proj-...'></td></tr>";
echo "<tr><td><label>Database Host:</label></td>";
echo "<td><input type='text' name='db_host' value='" . htmlspecialchars($dbHost) . "' placeholder='localhost'></td></tr>";
echo "<tr><td><label>Database Name:</label></td>";
echo "<td><input type='text' name='db_name' value='" . htmlspecialchars($dbName) . "' placeholder='smart_meal_planner'></td></tr>";
echo "<tr><td><label>Database Username:</label></td>";
echo "<td><input type='text' name='db_user' value='" . htmlspecialchars($dbUser) . "' placeholder='root'></td></tr>";
echo "<tr><td><label>Database Password:</label></td>";
echo "<td><input type='password' name='db_pass' value='" . htmlspecialchars(EnvLoader::get('DB_PASSWORD', '')) . "' placeholder='(leave empty if no password)'></td></tr>";
echo "</table>";
echo "<br><button type='submit' name='update_env' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px;'>Update Configuration</button>";
echo "</form>";

// Handle form submission
if (isset($_POST['update_env'])) {
    $newConfig = [
        'OPENAI_API_KEY' => $_POST['openai_key'] ?? '',
        'DB_HOST' => $_POST['db_host'] ?? 'localhost',
        'DB_NAME' => $_POST['db_name'] ?? 'smart_meal_planner',
        'DB_USERNAME' => $_POST['db_user'] ?? 'root',
        'DB_PASSWORD' => $_POST['db_pass'] ?? '',
    ];
    
    // Create new .env content
    $envContent = "# OpenAI Configuration\n";
    $envContent .= "OPENAI_API_KEY=" . $newConfig['OPENAI_API_KEY'] . "\n\n";
    $envContent .= "# Database Configuration\n";
    $envContent .= "DB_HOST=" . $newConfig['DB_HOST'] . "\n";
    $envContent .= "DB_USERNAME=" . $newConfig['DB_USERNAME'] . "\n";
    $envContent .= "DB_PASSWORD=" . $newConfig['DB_PASSWORD'] . "\n";
    $envContent .= "DB_NAME=" . $newConfig['DB_NAME'] . "\n\n";
    $envContent .= "# Application Settings\n";
    $envContent .= "APP_NAME=Smart Meal Planner\n";
    $envContent .= "APP_ENV=development\n";
    $envContent .= "APP_DEBUG=true\n";
    
    if (file_put_contents($envPath, $envContent)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✓ Configuration updated successfully! Please refresh the page to see changes.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✗ Failed to update configuration. Please check file permissions.";
        echo "</div>";
    }
}

// Test OpenAI connection
if (EnvLoader::isOpenAIConfigured()) {
    echo "<h3>Test OpenAI Connection</h3>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='test_openai' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px;'>Test OpenAI API</button>";
    echo "</form>";
    
    if (isset($_POST['test_openai'])) {
        echo "<div style='background: #e2e3e5; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Testing OpenAI API...</strong><br>";
        
        $testKey = EnvLoader::getOpenAIKey();
        $testData = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Say "Hello from Smart Meal Planner!"']
            ],
            'max_tokens' => 20
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $testKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                echo "<span style='color: green;'>✓ OpenAI API is working!</span><br>";
                echo "Response: " . htmlspecialchars($result['choices'][0]['message']['content']);
            } else {
                echo "<span style='color: orange;'>⚠ Unexpected response format</span>";
            }
        } else {
            echo "<span style='color: red;'>✗ OpenAI API test failed (HTTP $httpCode)</span><br>";
            if ($response) {
                $error = json_decode($response, true);
                echo "Error: " . htmlspecialchars($error['error']['message'] ?? 'Unknown error');
            }
        }
        echo "</div>";
    }
}

echo "<h3>Instructions</h3>";
echo "<ol>";
echo "<li><strong>OpenAI API Key:</strong> Get your API key from <a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI Platform</a></li>";
echo "<li><strong>Database:</strong> Make sure your database is running and accessible</li>";
echo "<li><strong>File Permissions:</strong> Ensure the web server can write to the .env file</li>";
echo "<li><strong>Security:</strong> Never commit your .env file to version control</li>";
echo "</ol>";

echo "<h3>Quick Links</h3>";
echo "<ul>";
echo "<li><a href='index.html'>Go to Application</a></li>";
echo "<li><a href='test_openai.php'>Test OpenAI API</a></li>";
echo "<li><a href='admin.html'>Admin Panel</a></li>";
echo "</ul>";
?>