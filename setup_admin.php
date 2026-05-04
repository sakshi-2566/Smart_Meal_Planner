<?php
/**
 * Admin Setup Script
 * Run this file once to create the admin user
 * Access: http://localhost/smart-meal-planner/setup_admin.php
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Smart Meal Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        pre {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <h2 class="text-center mb-4">🔧 Admin Setup</h2>
        
        <?php
        $conn = getDBConnection();
        $errors = [];
        $success = [];
        
        // Step 1: Check if role column exists
        echo "<h5>Step 1: Checking database structure...</h5>";
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        
        if ($checkColumn->num_rows == 0) {
            echo "<p class='info'>⚠️ Role column doesn't exist. Adding it now...</p>";
            
            // Add role column
            $alterTable = $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER dietary_preference");
            
            if ($alterTable) {
                echo "<p class='success'>✅ Role column added successfully!</p>";
                
                // Add index
                $conn->query("ALTER TABLE users ADD INDEX idx_role (role)");
                echo "<p class='success'>✅ Index added successfully!</p>";
            } else {
                echo "<p class='error'>❌ Error adding role column: " . $conn->error . "</p>";
                $errors[] = "Failed to add role column";
            }
        } else {
            echo "<p class='success'>✅ Role column already exists!</p>";
        }
        
        // Step 2: Check if admin user exists
        echo "<hr><h5>Step 2: Checking for admin user...</h5>";
        $checkAdmin = $conn->query("SELECT id FROM users WHERE email = 'Admin@smartmealplanner.com'");
        
        if ($checkAdmin->num_rows > 0) {
            $adminUser = $checkAdmin->fetch_assoc();
            echo "<p class='info'>⚠️ Admin user already exists (ID: {$adminUser['id']})</p>";
            
            // Update to admin role if not already
            $conn->query("UPDATE users SET role = 'admin' WHERE email = 'Admin@smartmealplanner.com'");
            echo "<p class='success'>✅ Admin role updated!</p>";
            
            $adminId = $adminUser['id'];
        } else {
            echo "<p class='info'>⚠️ Admin user doesn't exist. Creating it now...</p>";
            
            // Create admin user
            $hashedPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, dietary_preference, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $firstName = 'Admin';
            $lastName = 'User';
            $email = 'Admin@smartmealplanner.com';
            $dietary = 'none';
            $role = 'admin';
            $active = 1;
            
            $stmt->bind_param("ssssssi", $firstName, $lastName, $email, $hashedPassword, $dietary, $role, $active);
            
            if ($stmt->execute()) {
                $adminId = $conn->insert_id;
                echo "<p class='success'>✅ Admin user created successfully! (ID: $adminId)</p>";
                $success[] = "Admin user created";
            } else {
                echo "<p class='error'>❌ Error creating admin user: " . $stmt->error . "</p>";
                $errors[] = "Failed to create admin user";
            }
            $stmt->close();
        }
        
        // Step 3: Check/Create admin profile
        if (isset($adminId)) {
            echo "<hr><h5>Step 3: Checking admin profile...</h5>";
            $checkProfile = $conn->query("SELECT id FROM user_profiles WHERE user_id = $adminId");
            
            if ($checkProfile->num_rows > 0) {
                echo "<p class='success'>✅ Admin profile already exists!</p>";
            } else {
                echo "<p class='info'>⚠️ Admin profile doesn't exist. Creating it now...</p>";
                
                $profileStmt = $conn->prepare("INSERT INTO user_profiles (user_id, age, gender, height, weight, activity_level, goal, bmr, tdee, target_calories, target_protein, target_carbs, target_fats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $age = 30;
                $gender = 'male';
                $height = 175;
                $weight = 75;
                $activity = 'moderate';
                $goal = 'maintenance';
                $bmr = 1750;
                $tdee = 2712;
                $calories = 2712;
                $protein = 203;
                $carbs = 271;
                $fats = 90;
                
                $profileStmt->bind_param("iisddssddiii", $adminId, $age, $gender, $height, $weight, $activity, $goal, $bmr, $tdee, $calories, $protein, $carbs, $fats);
                
                if ($profileStmt->execute()) {
                    echo "<p class='success'>✅ Admin profile created successfully!</p>";
                    $success[] = "Admin profile created";
                } else {
                    echo "<p class='error'>❌ Error creating admin profile: " . $profileStmt->error . "</p>";
                    $errors[] = "Failed to create admin profile";
                }
                $profileStmt->close();
            }
        }
        
        // Step 4: Verify setup
        echo "<hr><h5>Step 4: Verifying setup...</h5>";
        $verify = $conn->query("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.is_active, 
                   p.bmr, p.tdee, p.target_calories
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE u.email = 'Admin@smartmealplanner.com'
        ");
        
        if ($verify->num_rows > 0) {
            $admin = $verify->fetch_assoc();
            echo "<div class='alert alert-success'>";
            echo "<h6>✅ Setup Complete!</h6>";
            echo "<p><strong>Admin Details:</strong></p>";
            echo "<ul>";
            echo "<li>ID: {$admin['id']}</li>";
            echo "<li>Name: {$admin['first_name']} {$admin['last_name']}</li>";
            echo "<li>Email: {$admin['email']}</li>";
            echo "<li>Role: {$admin['role']}</li>";
            echo "<li>Status: " . ($admin['is_active'] ? 'Active' : 'Inactive') . "</li>";
            echo "<li>BMR: {$admin['bmr']}</li>";
            echo "<li>TDEE: {$admin['tdee']}</li>";
            echo "<li>Target Calories: {$admin['target_calories']}</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='alert alert-info'>";
            echo "<h6>🔐 Login Credentials:</h6>";
            echo "<pre>";
            echo "Email: Admin@smartmealplanner.com\n";
            echo "Password: Admin@123";
            echo "</pre>";
            echo "</div>";
            
            echo "<div class='text-center mt-4'>";
            echo "<a href='login.html' class='btn btn-primary btn-lg'>Go to Login</a>";
            echo "<a href='admin.html' class='btn btn-success btn-lg ms-2'>Go to Admin Panel</a>";
            echo "</div>";
            
            echo "<div class='alert alert-warning mt-4'>";
            echo "<strong>⚠️ Security Note:</strong> Delete or rename this setup_admin.php file after setup is complete!";
            echo "</div>";
        } else {
            echo "<div class='alert alert-danger'>";
            echo "<h6>❌ Setup Failed!</h6>";
            echo "<p>Admin user could not be verified. Please check the errors above.</p>";
            echo "</div>";
        }
        
        closeDBConnection($conn);
        ?>
    </div>
</body>
</html>
