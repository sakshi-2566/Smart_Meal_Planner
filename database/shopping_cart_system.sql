-- Shopping Cart and Order System Tables

-- Shopping Cart Table
CREATE TABLE IF NOT EXISTS shopping_cart (
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
);

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
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
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);

-- Meal Plans Table (if not exists) - matches existing schema
CREATE TABLE IF NOT EXISTS meal_plans (
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
);

-- Meal Plan Items Table
CREATE TABLE IF NOT EXISTS meal_plan_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meal_plan_id INT NOT NULL,
    recipe_id INT NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    meal_date DATE NOT NULL,
    servings INT DEFAULT 1,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id)
);

-- Add price column to ingredients if not exists (prices in Indian Rupees ₹ per gram/ml)
ALTER TABLE ingredients ADD COLUMN IF NOT EXISTS price_per_unit DECIMAL(10,2) DEFAULT 0.10;
ALTER TABLE ingredients ADD COLUMN IF NOT EXISTS available_stock INT DEFAULT 100;

-- Sample ingredient prices in Indian Rupees (₹) - Affordable rates
UPDATE ingredients SET price_per_unit = 0.20 WHERE category = 'Protein';      -- ₹0.20/g = ₹200/kg (chicken, fish, etc.)
UPDATE ingredients SET price_per_unit = 0.05 WHERE category = 'Grains';       -- ₹0.05/g = ₹50/kg (rice, wheat, etc.)
UPDATE ingredients SET price_per_unit = 0.03 WHERE category = 'Vegetables';   -- ₹0.03/g = ₹30/kg (vegetables)
UPDATE ingredients SET price_per_unit = 0.06 WHERE category = 'Fruits';       -- ₹0.06/g = ₹60/kg (fruits)
UPDATE ingredients SET price_per_unit = 0.08 WHERE category = 'Dairy';        -- ₹0.08/g = ₹80/kg (milk, yogurt, etc.)
UPDATE ingredients SET price_per_unit = 0.15 WHERE category = 'Fats';         -- ₹0.15/ml = ₹150/liter (oils)
UPDATE ingredients SET price_per_unit = 0.50 WHERE category = 'Nuts';         -- ₹0.50/g = ₹500/kg (almonds, cashews, etc.)
