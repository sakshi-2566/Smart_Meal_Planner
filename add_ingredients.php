<?php
require_once 'config/database.php';
$conn = getDBConnection();
$user_id = 2; // Based on previous output

$ingredients_to_add = [
    ['name' => 'Rice', 'quantity' => 2, 'unit' => 'kg', 'category' => 'Grains'],
    ['name' => 'Salt', 'quantity' => 500, 'unit' => 'g', 'category' => 'Other'],
    ['name' => 'Broccoli', 'quantity' => 1, 'unit' => 'kg', 'category' => 'Vegetables'],
    ['name' => 'Almonds', 'quantity' => 500, 'unit' => 'g', 'category' => 'Nuts'],
    ['name' => 'Onion', 'quantity' => 2, 'unit' => 'kg', 'category' => 'Vegetables']
];

foreach ($ingredients_to_add as $item) {
    // Find ingredient ID
    $stmt = $conn->prepare("SELECT id FROM ingredients WHERE ingredient_name = ?");
    $stmt->bind_param("s", $item['name']);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $ing_id = $row['id'];
    } else {
        // Create ingredient if not exists
        $stmt = $conn->prepare("INSERT INTO ingredients (ingredient_name, category) VALUES (?, ?)");
        $stmt->bind_param("ss", $item['name'], $item['category']);
        $stmt->execute();
        $ing_id = $conn->insert_id;
    }
    
    // Check if user already has it
    $stmt = $conn->prepare("SELECT id FROM user_inventory WHERE user_id = ? AND ingredient_id = ?");
    $stmt->bind_param("ii", $user_id, $ing_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        // Update
        $stmt = $conn->prepare("UPDATE user_inventory SET quantity = ?, unit = ? WHERE id = ?");
        $stmt->bind_param("dsi", $item['quantity'], $item['unit'], $row['id']);
        $stmt->execute();
        echo "Updated " . $item['name'] . " to " . $item['quantity'] . " " . $item['unit'] . "\n";
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO user_inventory (user_id, ingredient_id, quantity, unit, added_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iids", $user_id, $ing_id, $item['quantity'], $item['unit']);
        $stmt->execute();
        echo "Added " . $item['name'] . ": " . $item['quantity'] . " " . $item['unit'] . "\n";
    }
}

$conn->close();
?>
