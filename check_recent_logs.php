<?php
require_once 'config/database.php';
$conn = getDBConnection();
echo "--- Nutrition Logs for Today (2025-12-21) ---\n";
$res = $conn->query("SELECT * FROM nutrition_logs WHERE log_date = '2025-12-21' OR created_at >= '2025-12-21 00:00:00' ORDER BY id DESC");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$conn->close();
?>
