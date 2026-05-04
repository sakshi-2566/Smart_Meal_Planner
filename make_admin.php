<?php
/**
 * Make User Admin Tool
 * Use this to grant admin privileges to any user
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Make User Admin</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 15px; background: #d4edda; border-radius: 5px; margin: 20px 0; }
        .error { color: red; padding: 15px; background: #f8d7da; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔐 Make User Admin</h1>
    <p>This tool will grant admin privileges to a user.</p>";

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    
    // Update user to admin
    $sql = "UPDATE users SET role = 'admin' WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<div class='success'>
                <h4>✅ Success!</h4>
                <p>User <strong>$email</strong> is now an admin.</p>
                <p>You can now <a href='login.html'>login</a> and access the <a href='admin.html'>admin panel</a>.</p>
            </div>";
        } else {
            echo "<div class='error'>
                <h4>❌ User Not Found</h4>
                <p>No user found with email: <strong>$email</strong></p>
            </div>";
        }
    } else {
        echo "<div class='error'>Error: " . $conn->error . "</div>";
    }
}

// Show all users
$result = $conn->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY id");

echo "<form method='POST' class='mb-4'>
    <div class='mb-3'>
        <label class='form-label'>Select User to Make Admin:</label>
        <select name='email' class='form-select' required>
            <option value=''>-- Select User --</option>";

while ($row = $result->fetch_assoc()) {
    $badge = $row['role'] === 'admin' ? ' (Already Admin)' : '';
    echo "<option value='{$row['email']}'>{$row['first_name']} {$row['last_name']} - {$row['email']}{$badge}</option>";
}

echo "</select>
    </div>
    <button type='submit' class='btn btn-primary'>Make Admin</button>
</form>";

echo "<hr>
<h3>Current Users</h3>
<table class='table table-striped'>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
        </tr>
    </thead>
    <tbody>";

$result = $conn->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $roleClass = $row['role'] === 'admin' ? 'badge bg-danger' : 'badge bg-primary';
    echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['first_name']} {$row['last_name']}</td>
        <td>{$row['email']}</td>
        <td><span class='$roleClass'>{$row['role']}</span></td>
    </tr>";
}

echo "</tbody>
</table>";

closeDBConnection($conn);

echo "<div class='alert alert-info mt-4'>
    <strong>Note:</strong> After making a user admin, they need to logout and login again for changes to take effect.
</div>

<div class='alert alert-warning'>
    <strong>Security:</strong> Delete this file (make_admin.php) after use for security reasons.
</div>

</body>
</html>";
?>
