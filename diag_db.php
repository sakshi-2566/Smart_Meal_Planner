<?php
require_once 'config/database.php';
$conn = getDBConnection();

$result = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
$row = $result->fetch_assoc();
echo "Current status column definition: " . $row['Type'] . "\n";

$conn->close();
?>
