<?php
// Simple test for analytics API
session_start();

// Set admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

echo "<h2>Testing Analytics API</h2>";
echo "<p>Making request to api/admin/get_analytics.php...</p>";

// Make a request to the analytics API
$url = 'http://localhost/Smart_Meal_Planner/api/admin/get_analytics.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "<p style='color: red;'>Failed to fetch analytics data</p>";
} else {
    echo "<h3>Response:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "<h3>Analytics Summary:</h3>";
        echo "<ul>";
        echo "<li>User Growth Data Points: " . count($data['analytics']['user_growth']) . "</li>";
        echo "<li>Dietary Preferences: " . count($data['analytics']['dietary_preferences']) . "</li>";
        echo "<li>Meal Plans Data: " . count($data['analytics']['meal_plans']) . "</li>";
        echo "<li>Active Users Data: " . count($data['analytics']['active_users']) . "</li>";
        if (isset($data['analytics']['summary'])) {
            echo "<li>Total Users: " . $data['analytics']['summary']['total_users'] . "</li>";
            echo "<li>Total Meal Plans: " . $data['analytics']['summary']['total_meal_plans'] . "</li>";
            echo "<li>Active Users Today: " . $data['analytics']['summary']['active_users_today'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>API returned error or invalid data</p>";
    }
}
?>