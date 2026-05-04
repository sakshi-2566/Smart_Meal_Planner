<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Payment Method Column</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #10b981; }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #10b981; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #059669; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>🔧 Setting up Payment Method Column</h2>";

try {
    $conn = getDBConnection();
    
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>ℹ️ payment_method column already exists in orders table</div>";
    } else {
        // Add payment_method column
        $sql = "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'pending' AFTER payment_status";
        
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ payment_method column added successfully!</div>";
        } else {
            echo "<div class='error'>✗ Error adding column: " . $conn->error . "</div>";
        }
    }
    
    // Verify column exists now
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    if ($result->num_rows > 0) {
        echo "<div class='success'>✓ Verified: payment_method column exists in orders table</div>";
    } else {
        echo "<div class='error'>✗ Column still does not exist. Please check database permissions.</div>";
    }
    
    // Show current orders table structure
    echo "<h3>📋 Current Orders Table Structure:</h3>";
    $result = $conn->query("DESCRIBE orders");
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $highlight = ($row['Field'] == 'payment_method' || $row['Field'] == 'payment_status') ? 'style="background: #d4edda;"' : '';
        echo "<tr $highlight>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    closeDBConnection($conn);
    
    echo "<div class='success'><strong>✅ Setup Complete!</strong> You can now use the payment system.</div>";
    echo "<a href='orders.html' class='btn'>Go to Orders Page</a>";
    echo " <a href='dashboard.html' class='btn'>Go to Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
