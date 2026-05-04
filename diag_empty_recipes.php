<?php
require_once 'config/database.php';
$conn = getDBConnection();

$sql = "SELECT r.id, r.recipe_name, u.email as owner
        FROM recipes r 
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id 
        WHERE ri.recipe_id IS NULL";

$res = $conn->query($sql);
echo "Recipes with 0 ingredients:\n";
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Name: {$row['recipe_name']} | Owner: {$row['owner']}\n";
}
echo "\nTotal empty recipes: " . $res->num_rows . "\n";
?>
