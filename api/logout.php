<?php
session_start();
header('Content-Type: application/json');

// Destroy session
session_unset();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
