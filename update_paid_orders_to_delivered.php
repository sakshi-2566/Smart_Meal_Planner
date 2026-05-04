<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Paid Orders to Delivered</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #10b981; }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #059669; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>🔄 Updating Paid Orders to Delivered Status</h2>";

try {
    $conn = getDBConnection();
    
    // Get count of orders to update
    $count_sql = "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'paid' AND status != 'delivered'";
    $result = $conn->query($count_sql);
    $count = $result->fetch_assoc()['count'];
    
    if ($count == 0) {
        echo "<div class='info'>ℹ️ No orders need updating. All paid orders are already marked as delivered.</div>";
    } else {
        echo "<div class='info'>📦 Found $count paid order(s) that need to be updated to delivered status.</div>";
        
        // Update all paid orders to delivered
        $update_sql = "UPDATE orders 
                       SET status = 'delivered', 
                           delivery_date = NOW() 
                       WHERE payment_status = 'paid' 
                       AND status != 'delivered'";
        
        if ($conn->query($update_sql)) {
            $updated = $conn->affected_rows;
            echo "<div class='success'>✅ Successfully updated $updated order(s) to delivered status!</div>";
            
            // Show updated orders
            echo "<h3>📋 Updated Orders:</h3>";
            $orders_sql = "SELECT order_number, total_amount, status, payment_status, delivery_date 
                          FROM orders 
                          WHERE payment_status = 'paid' 
                          ORDER BY delivery_date DESC 
                          LIMIT 20";
            $result = $conn->query($orders_sql);
            
            echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
            echo "<tr style='background: #10b981; color: white;'>
                    <th>Order Number</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Delivery Date</th>
                  </tr>";
            
            while ($order = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($order['order_number']) . "</strong></td>";
                echo "<td>₹" . number_format($order['total_amount'], 2) . "</td>";
                echo "<td><span style='color: green; font-weight: bold;'>" . ucfirst($order['status']) . "</span></td>";
                echo "<td><span style='color: green;'>" . ucfirst($order['payment_status']) . "</span></td>";
                echo "<td>" . date('d M Y, h:i A', strtotime($order['delivery_date'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Error updating orders: " . $conn->error . "</div>";
        }
    }
    
    closeDBConnection($conn);
    
    echo "<div class='success'><strong>✅ Update Complete!</strong></div>";
    echo "<a href='orders.html' class='btn'>View Orders</a>";
    echo " <a href='dashboard.html' class='btn'>Go to Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
