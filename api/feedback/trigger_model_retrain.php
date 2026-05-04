<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

// Only admins can trigger retraining
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$conn = getDBConnection();

// Get training data
$sql = "SELECT 
            uri.user_id,
            uri.recipe_id,
            uri.rating,
            r.calories,
            r.protein,
            r.carbs,
            r.fats,
            r.dietary_tags,
            up.age,
            up.weight,
            up.height,
            up.activity_level,
            up.goal
        FROM user_recipe_interactions uri
        JOIN recipes r ON uri.recipe_id = r.id
        LEFT JOIN user_profiles up ON uri.user_id = up.user_id
        WHERE uri.rating IS NOT NULL
        ORDER BY uri.created_at DESC
        LIMIT 1000";

$result = $conn->query($sql);
$training_data = $result->fetch_all(MYSQLI_ASSOC);

if (count($training_data) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'Not enough training data. Need at least 10 rated meals.',
        'current_data_points' => count($training_data)
    ]);
    closeDBConnection($conn);
    exit;
}

// Save training data to CSV for Python script
$csv_file = '../../ml_service/training_data.csv';
$fp = fopen($csv_file, 'w');

// Write header
fputcsv($fp, ['user_id', 'recipe_id', 'rating', 'calories', 'protein', 'carbs', 'fats', 
              'age', 'weight', 'height', 'activity_level', 'goal', 'dietary_tags']);

// Write data
foreach ($training_data as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

// Trigger Python retraining script (async)
$python_path = 'python';
$script_path = '../../ml_service/retrain_model.py';
$command = "$python_path $script_path > ../../ml_service/retrain.log 2>&1 &";

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen("start /B " . $command, "r"));
} else {
    exec($command);
}

// Update model metadata
$model_sql = "INSERT INTO ml_model_metadata 
              (model_name, model_version, model_type, training_samples, training_date, is_active, notes)
              VALUES ('adaptive_recommender', ?, 'neural_network', ?, NOW(), TRUE, 'Retrained with user feedback')";
$version = 'v' . date('Ymd_His');
$stmt = $conn->prepare($model_sql);
$sample_count = count($training_data);
$stmt->bind_param("si", $version, $sample_count);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Model retraining initiated',
    'training_samples' => count($training_data),
    'model_version' => $version,
    'status' => 'Training in progress...'
]);

closeDBConnection($conn);
?>
