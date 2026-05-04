<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "--- User Inventory ---\n";
$res = $conn->query("SELECT ui.*, i.ingredient_name FROM user_inventory ui JOIN ingredients i ON ui.ingredient_id = i.id");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- Recent Recipes & Ingredients ---\n";
$res = $conn->query("SELECT r.id, r.recipe_name, ri.ingredient_id, ri.quantity, ri.unit, i.ingredient_name 
                    FROM recipes r 
                    JOIN recipe_ingredients ri ON r.id = ri.recipe_id 
                    JOIN ingredients i ON ri.ingredient_id = i.id 
                    ORDER BY r.id DESC LIMIT 20");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

$conn->close();
?>
