<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$dietaryPreference = trim($input['dietaryPreference'] ?? 'none');

// Validation
if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

$conn = getDBConnection();

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $stmt->close();
    closeDBConnection($conn);
    exit;
}
$stmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, dietary_preference) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $dietaryPreference);

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    
    // Create default user profile
    $profileStmt = $conn->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
    $profileStmt->bind_param("i", $userId);
    $profileStmt->execute();
    $profileStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'userId' => $userId
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again']);
}

$stmt->close();
closeDBConnection($conn);
?>
