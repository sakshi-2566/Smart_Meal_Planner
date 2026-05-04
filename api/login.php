<?php
session_start();
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
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$remember = $input['remember'] ?? false;

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

$conn = getDBConnection();

// Get user by email
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, dietary_preference, role, is_active FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    $stmt->close();
    closeDBConnection($conn);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if account is active
if (!$user['is_active']) {
    echo json_encode(['success' => false, 'message' => 'Account is deactivated. Please contact support']);
    closeDBConnection($conn);
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    closeDBConnection($conn);
    exit;
}

// Update last login
$updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$updateStmt->bind_param("i", $user['id']);
$updateStmt->execute();
$updateStmt->close();

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_role'] = $user['role'];

// Set remember me cookie if requested
if ($remember) {
    $token = bin2hex(random_bytes(32));
    setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
}

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'email' => $user['email'],
        'dietaryPreference' => $user['dietary_preference'],
        'role' => $user['role']
    ]
]);

closeDBConnection($conn);
?>
