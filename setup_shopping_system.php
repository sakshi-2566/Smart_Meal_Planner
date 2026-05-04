<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Shopping & Order System</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; border-radius: 5px; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; border-radius: 5px; }
        h1 { color: #10b981; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #10b981; }
    </style>
</head>
<body>
    <h1>🛒 Shopping & Order System Setup</h1>
    <p>This will set up the shopping cart and order management system.</p>

<?php
require_once 'config/database.php';

$conn = getDBConnection();

echo "<div class='step'><h3>Step 1: Creating Shopping Cart Table</h3>";
$sql = "CREATE TABLE IF NOT EXISTS shopping_cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, ingredient_id)
)";

if ($conn->query($sql)) {
    echo "<div class='success'>✓ Shopping cart table created</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 2: Creating Orders Table</h3>";
$sql = "CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    delivery_address TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "<div class='success'>✓ Orders table created</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 3: Creating Order Items Table</h3>";
$sql = "CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
)";

if ($conn->query($sql)) {
    echo "<div class='success'>✓ Order items table created</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 4: Creating Meal Plans Table</h3>";
$sql = "CREATE TABLE IF NOT EXISTS meal_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_calories INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_dates (start_date, end_date)
)";

if ($conn->query($sql)) {
    echo "<div class='success'>✓ Meal plans table created</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 5: Creating Meal Plan Items Table</h3>";
$sql = "CREATE TABLE IF NOT EXISTS meal_plan_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meal_plan_id INT NOT NULL,
    recipe_id INT NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    meal_date DATE NOT NULL,
    servings INT DEFAULT 1,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id)
)";

if ($conn->query($sql)) {
    echo "<div class='success'>✓ Meal plan items table created</div>";
} else {
    echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 6: Adding Price Columns to Ingredients</h3>";

// Check if columns exist first
$result = $conn->query("SHOW COLUMNS FROM ingredients LIKE 'price_per_unit'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE ingredients ADD COLUMN price_per_unit DECIMAL(10,2) DEFAULT 5.00";
    if ($conn->query($sql)) {
        echo "<div class='success'>✓ Added price_per_unit column</div>";
    } else {
        echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ price_per_unit column already exists</div>";
}

$result = $conn->query("SHOW COLUMNS FROM ingredients LIKE 'available_stock'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE ingredients ADD COLUMN available_stock INT DEFAULT 100";
    if ($conn->query($sql)) {
        echo "<div class='success'>✓ Added available_stock column</div>";
    } else {
        echo "<div class='error'>✗ Error: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ available_stock column already exists</div>";
}
echo "</div>";

echo "<div class='step'><h3>Step 7: Setting Sample Ingredient Prices (in ₹ per gram/ml)</h3>";
$prices = [
    'Protein' => 0.20,        // ₹0.20/g = ₹200/kg (chicken, fish, etc.)
    'Grains' => 0.05,         // ₹0.05/g = ₹50/kg (rice, wheat, etc.)
    'Vegetables' => 0.03,     // ₹0.03/g = ₹30/kg (vegetables)
    'Fruits' => 0.06,         // ₹0.06/g = ₹60/kg (fruits)
    'Dairy' => 0.08,          // ₹0.08/g = ₹80/kg (milk, yogurt, etc.)
    'Fats' => 0.15,           // ₹0.15/ml = ₹150/liter (oils)
    'Nuts' => 0.50            // ₹0.50/g = ₹500/kg (almonds, cashews, etc.)
];

foreach ($prices as $category => $price) {
    $sql = "UPDATE ingredients SET price_per_unit = ? WHERE category = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $price, $category);
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        echo "<div class='success'>✓ Updated $affected $category items to ₹$price per unit</div>";
    }
}
echo "</div>";

closeDBConnection($conn);
?>

    <div class="step">
        <h3>✅ Setup Complete!</h3>
        <p>The shopping cart and order system has been set up successfully.</p>
        
        <h4>What's Next?</h4>
        <ol>
            <li>Go to <a href="profile.html">Profile Page</a> and update your health information</li>
            <li>Click "Generate Meal Plan" when prompted</li>
            <li>System will automatically:
                <ul>
                    <li>Generate 7-day personalized meal plan</li>
                    <li>Check your inventory</li>
                    <li>Add missing ingredients to cart</li>
                </ul>
            </li>
            <li>Go to <a href="cart.html">Shopping Cart</a> to review and checkout</li>
        </ol>
        
        <h4>Features Available:</h4>
        <ul>
            <li>✓ Automated meal plan generation</li>
            <li>✓ Inventory checking</li>
            <li>✓ Auto-add missing ingredients to cart</li>
            <li>✓ Shopping cart management</li>
            <li>✓ Order processing</li>
            <li>✓ Order tracking</li>
        </ul>
    </div>

</body>
</html>
