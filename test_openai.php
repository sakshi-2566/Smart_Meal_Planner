<?php
// Simple OpenAI API Test
require_once 'config/env.php';

// Get OpenAI API Key from environment
$api_key = getOpenAIKey();

$data = [
    'model' => 'gpt-3.5-turbo', // Using cheaper model for testing
    'messages' => [
        ['role' => 'user', 'content' => 'Say "Hello, I am working!" in JSON format: {"message": "your response"}']
    ],
    'max_tokens' => 50
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);

echo "<h2>Testing OpenAI API Connection</h2>";
echo "<p>Sending request...</p>";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h3>Results:</h3>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";

if ($curl_error) {
    echo "<p style='color: red;'><strong>Curl Error:</strong> $curl_error</p>";
}

echo "<h4>Response:</h4>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($http_code === 200) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo "<p style='color: green; font-size: 20px;'><strong>✓ OpenAI API is working!</strong></p>";
        echo "<p>Response: " . htmlspecialchars($result['choices'][0]['message']['content']) . "</p>";
    }
} else {
    echo "<p style='color: red; font-size: 20px;'><strong>✗ OpenAI API failed</strong></p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>API key is valid and not expired</li>";
    echo "<li>You have credits in your OpenAI account</li>";
    echo "<li>Your server can make outbound HTTPS requests</li>";
    echo "</ul>";
}
?>
