// Inventory Management JavaScript
let allIngredients = [];
let userInventory = [];
let allRecipes = [];
let editingItemId = null;

document.addEventListener('DOMContentLoaded', function () {
    console.log('Inventory.js loaded - Version 2 (No ingredient icons)');
    checkAuth();
    loadIngredients();
    loadInventory();
    loadRecipes();
});

// Helper function to safely parse JSON responses
async function safeJsonParse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('JSON Parse Error:', text);
        throw new Error('Server returned invalid JSON. Check console for details.');
    }
}

function checkAuth() {
    fetch('api/get_user_profile.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                window.location.href = 'login.html';
            }
        });
}

function loadIngredients() {
    fetch('api/get_ingredients.php', {
        credentials: 'include'
    })
        .then(res => safeJsonParse(res))
        .then(data => {
            if (data.success) {
                allIngredients = data.ingredients;
                populateIngredientSelect();
            }
        })
        .catch(error => {
            console.error('Error loading ingredients:', error);
            showAlert('danger', 'Failed to load ingredients list');
        });
}

function populateIngredientSelect() {
    const select = document.getElementById('ingredientSelect');
    select.innerHTML = '<option value="">Select from list...</option>';
    select.innerHTML += '<option value="custom">➕ Add Custom Ingredient</option>';
    select.innerHTML += '<option disabled>──────────</option>';

    allIngredients.forEach(ing => {
        select.innerHTML += `<option value="${ing.id}">${ing.ingredient_name} (${ing.category})</option>`;
    });
}

function handleIngredientSelection() {
    const select = document.getElementById('ingredientSelect');
    const customInput = document.getElementById('customIngredientName');
    const categorySection = document.getElementById('categorySection');

    if (select.value === 'custom') {
        customInput.style.display = 'block';
        customInput.required = true;
        categorySection.style.display = 'block';
        select.required = false;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        categorySection.style.display = 'none';
        select.required = true;
    }
}

function loadInventory() {
    fetch('api/inventory/get_inventory.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                userInventory = data.inventory;
                displayInventory(data.inventory);
                updateStats(data.inventory);
            }
        });
}

function displayInventory(items) {
    const container = document.getElementById('inventoryContainer');
    const emptyState = document.getElementById('emptyState');

    if (items.length === 0) {
        container.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';

    container.innerHTML = items.map(item => {
        const expiryStatus = getExpiryStatus(item.expiry_date);
        const expiryClass = expiryStatus.class;
        const expiryText = expiryStatus.text;
        const expiryIcon = expiryStatus.icon;
        
        // Determine if item is vegetarian
        // First check database field, then fallback to logic
        let isVegetarian;
        if (item.is_vegetarian !== undefined && item.is_vegetarian !== null) {
            // Use database value (convert to boolean to handle 0/1 values)
            isVegetarian = Boolean(Number(item.is_vegetarian));
        } else {
            // Fallback to logic-based detection
            isVegetarian = isIngredientVegetarian(item.ingredient_name, item.category);
        }

        return `
            <div class="col-md-4 col-lg-3">
                <div class="inventory-item">
                    <span class="badge bg-${expiryClass}">${item.category || 'Other'}</span>
                    ${isVegetarian ? 
                        '<span class="badge bg-success veg-badge" title="Vegetarian"><i class="fas fa-leaf"></i> VEG</span>' : 
                        '<span class="badge bg-danger non-veg-badge" title="Non-Vegetarian"><i class="fas fa-drumstick-bite"></i> NON-VEG</span>'
                    }
                    <div class="ingredient-name">${item.ingredient_name}</div>
                    <div class="ingredient-quantity">
                        <strong>${item.quantity} ${item.unit}</strong>
                    </div>
                    ${item.expiry_date ? `
                        <div class="expiry-info expiry-${expiryClass}">
                            <i class="fas fa-${expiryIcon}"></i>
                            ${expiryText}
                        </div>
                    ` : ''}
                    <div class="item-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="editItem(${item.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getExpiryStatus(expiryDate) {
    if (!expiryDate) {
        return { class: 'info', text: 'No expiry', icon: 'infinity' };
    }

    const today = new Date();
    const expiry = new Date(expiryDate);
    const daysUntilExpiry = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));

    if (daysUntilExpiry < 0) {
        return { class: 'expired', text: 'Expired', icon: 'times-circle' };
    } else if (daysUntilExpiry <= 3) {
        return { class: 'warning', text: `Expires in ${daysUntilExpiry} days`, icon: 'exclamation-triangle' };
    } else {
        return { class: 'fresh', text: `Expires in ${daysUntilExpiry} days`, icon: 'check-circle' };
    }
}

// Helper function to determine if an ingredient is vegetarian
function isIngredientVegetarian(ingredientName, category) {
    const name = ingredientName.toLowerCase().trim();
    
    // Non-vegetarian ingredients - explicit check
    const nonVegKeywords = [
        'chicken', 'beef', 'pork', 'fish', 'mutton', 'lamb', 'turkey', 'duck', 
        'meat', 'bacon', 'ham', 'sausage', 'prawn', 'shrimp', 'crab', 'lobster', 
        'egg', 'tuna', 'salmon', 'cod', 'goat', 'buffalo'
    ];
    
    // Check if ingredient name contains non-veg keywords
    for (let keyword of nonVegKeywords) {
        if (name.includes(keyword)) {
            return false;
        }
    }
    
    // If category is Protein, be more cautious
    if (category === 'Protein') {
        const vegProteinKeywords = [
            'tofu', 'paneer', 'cheese', 'milk', 'yogurt', 'lentil', 'bean', 
            'chickpea', 'soy', 'quinoa', 'nuts', 'seeds', 'dal', 'pulses'
        ];
        
        for (let keyword of vegProteinKeywords) {
            if (name.includes(keyword)) {
                return true;
            }
        }
        
        // If it's protein but doesn't match veg keywords, assume non-veg
        return false;
    }
    
    // Default to vegetarian for other categories
    return true;
}

function updateStats(items) {
    let total = items.length;
    let expiringSoon = 0;
    let expired = 0;
    let fresh = 0;

    const today = new Date();

    items.forEach(item => {
        if (item.expiry_date) {
            const expiry = new Date(item.expiry_date);
            const daysUntilExpiry = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));

            if (daysUntilExpiry < 0) {
                expired++;
            } else if (daysUntilExpiry <= 3) {
                expiringSoon++;
            } else {
                fresh++;
            }
        } else {
            fresh++;
        }
    });

    document.getElementById('totalItems').textContent = total;
    document.getElementById('expiringSoon').textContent = expiringSoon;
    document.getElementById('expired').textContent = expired;
    document.getElementById('fresh').textContent = fresh;
}

function saveIngredient() {
    const inventoryId = document.getElementById('inventoryId').value;
    const ingredientSelect = document.getElementById('ingredientSelect').value;
    const customName = document.getElementById('customIngredientName').value;
    const category = document.getElementById('ingredientCategory').value;
    const quantity = document.getElementById('quantity').value;
    const unit = document.getElementById('unit').value;
    const expiryDate = document.getElementById('expiryDate').value;

    // Validate
    if (ingredientSelect === 'custom' && !customName) {
        alert('Please enter a custom ingredient name');
        return;
    }
    if (!ingredientSelect || !quantity || !unit) {
        alert('Please fill all required fields');
        return;
    }

    const data = {
        quantity: parseFloat(quantity),
        unit: unit,
        expiry_date: expiryDate || null
    };

    // Handle custom ingredient
    if (ingredientSelect === 'custom') {
        data.custom_ingredient_name = customName;
        data.category = category;
    } else {
        data.ingredient_id = parseInt(ingredientSelect);
    }

    const url = inventoryId ? 'api/inventory/update_inventory.php' : 'api/inventory/add_inventory.php';
    if (inventoryId) {
        data.inventory_id = parseInt(inventoryId);
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
        .then(res => safeJsonParse(res))
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addIngredientModal')).hide();
                loadInventory();
                loadIngredients(); // Reload to get new custom ingredient
                showAlert('success', data.message);
                resetForm();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            showAlert('danger', 'Failed to save ingredient: ' + error.message);
        });
}

function resetForm() {
    document.getElementById('inventoryId').value = '';
    document.getElementById('ingredientSelect').value = '';
    document.getElementById('customIngredientName').value = '';
    document.getElementById('customIngredientName').style.display = 'none';
    document.getElementById('categorySection').style.display = 'none';
    document.getElementById('quantity').value = '';
    document.getElementById('unit').value = 'g';
    document.getElementById('expiryDate').value = '';
    document.getElementById('modalTitle').textContent = 'Add Ingredient';
}

function editItem(id) {
    const item = userInventory.find(i => i.id === id);
    if (!item) return;

    document.getElementById('modalTitle').textContent = 'Edit Ingredient';
    document.getElementById('inventoryId').value = item.id;
    document.getElementById('ingredientSelect').value = item.ingredient_id;
    document.getElementById('quantity').value = item.quantity;
    document.getElementById('unit').value = item.unit;
    document.getElementById('expiryDate').value = item.expiry_date || '';

    new bootstrap.Modal(document.getElementById('addIngredientModal')).show();
}

function deleteItem(id) {
    if (!confirm('Are you sure you want to delete this item?')) return;

    fetch('api/inventory/delete_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ inventory_id: id })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadInventory();
                showAlert('success', 'Item deleted');
            } else {
                showAlert('danger', data.message);
            }
        });
}

function loadRecipes() {
    fetch('api/recipes/get_recipes.php?filter=approved')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allRecipes = data.recipes;
                populateRecipeSelect();
            }
        });
}

function populateRecipeSelect() {
    const select = document.getElementById('recipeSelect');
    select.innerHTML = '<option value="">Select recipe...</option>';

    allRecipes.forEach(recipe => {
        select.innerHTML += `<option value="${recipe.id}">${recipe.recipe_name}</option>`;
    });

    select.addEventListener('change', function () {
        if (this.value) {
            checkRecipeAvailability(this.value);
        }
    });
}

function checkRecipeAvailability(recipeId) {
    fetch(`api/inventory/check_recipe_availability.php?recipe_id=${recipeId}`, {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayAvailability(data);
            }
        });
}

function displayAvailability(data) {
    const container = document.getElementById('availabilityResult');
    const addToShoppingBtn = document.getElementById('addToShoppingBtn');

    const available = data.available_ingredients || [];
    const missing = data.missing_ingredients || [];

    let html = '<h6>Ingredient Availability:</h6>';

    if (available.length > 0) {
        html += '<div class="alert alert-success"><strong>Available:</strong><ul class="mb-0">';
        available.forEach(ing => {
            html += `<li>${ing.ingredient_name}: ${ing.available_quantity} ${ing.unit} (need ${ing.required_quantity} ${ing.unit})</li>`;
        });
        html += '</ul></div>';
    }

    if (missing.length > 0) {
        html += '<div class="alert alert-warning"><strong>Missing:</strong><ul class="mb-0">';
        missing.forEach(ing => {
            html += `<li>${ing.ingredient_name}: Need ${ing.required_quantity} ${ing.unit}</li>`;
        });
        html += '</ul></div>';
        addToShoppingBtn.style.display = 'block';
        addToShoppingBtn.onclick = () => addMissingToShopping(missing);
    } else {
        html += '<div class="alert alert-success">✓ All ingredients available!</div>';
        addToShoppingBtn.style.display = 'none';
    }

    container.innerHTML = html;
}

function addMissingToShopping(missingItems) {
    fetch('api/shopping/add_missing_items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ items: missingItems })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Missing items added to shopping list!');
                bootstrap.Modal.getInstance(document.getElementById('checkRecipeModal')).hide();
            }
        });
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;

    let filtered = userInventory.filter(item => {
        const matchesSearch = item.ingredient_name.toLowerCase().includes(search);
        const matchesCategory = !category || item.category === category;

        let matchesStatus = true;
        if (status) {
            const expiryStatus = getExpiryStatus(item.expiry_date);
            matchesStatus = expiryStatus.class === status;
        }

        return matchesSearch && matchesCategory && matchesStatus;
    });

    displayInventory(filtered);
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}

function logout() {
    fetch('api/logout.php', { credentials: 'include' })
        .then(() => window.location.href = 'login.html');
}

// Reset form when modal closes
document.getElementById('addIngredientModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Add Ingredient';
    document.getElementById('ingredientForm').reset();
    document.getElementById('inventoryId').value = '';
    editingItemId = null;
});


// Generate recipes from inventory
function generateRecipesFromInventory() {
    if (userInventory.length === 0) {
        showAlert('warning', 'Your inventory is empty. Add some ingredients first!');
        return;
    }

    // Show modal with loading state
    const modal = new bootstrap.Modal(document.getElementById('generatedRecipesModal'));
    modal.show();

    document.getElementById('generatedRecipesContainer').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Generating...</span>
            </div>
            <p class="mt-3">Finding recipes based on your ingredients...</p>
        </div>
    `;

    // Get ingredient list
    const ingredients = userInventory.map(item => item.ingredient_name);

    // Call recipe matching API
    fetch('api/inventory/generate_from_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ ingredients: ingredients })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayGeneratedRecipes(data.recipes, data.message);
            } else {
                document.getElementById('generatedRecipesContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> ${data.message}
                </div>
            `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('generatedRecipesContainer').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Failed to generate recipes
            </div>
        `;
        });
}

// Generate AI-powered creative recipe
function generateAIRecipe() {
    if (userInventory.length === 0) {
        showAlert('warning', 'Your inventory is empty. Add some ingredients first!');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('generatedRecipesModal'));
    modal.show();

    document.getElementById('generatedRecipesContainer').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Generating...</span>
            </div>
            <p class="mt-3">AI is creating a unique recipe from your ingredients...</p>
            <small class="text-muted">This may take a few moments</small>
        </div>
    `;

    const ingredients = userInventory.map(item => item.ingredient_name);

    fetch('api/inventory/generate_ai_recipe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ ingredients: ingredients })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayAIRecipe(data.recipe);
            } else {
                document.getElementById('generatedRecipesContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> ${data.message}
                </div>
            `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('generatedRecipesContainer').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Failed to generate AI recipe
            </div>
        `;
        });
}

function displayGeneratedRecipes(recipes, message) {
    const container = document.getElementById('generatedRecipesContainer');

    if (recipes.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No recipes found matching your ingredients.
                Try adding more ingredients to your inventory!
            </div>
            <div class="text-center mt-3">
                <button class="btn btn-primary" onclick="generateAIRecipe()">
                    <i class="fas fa-robot"></i> Generate AI Recipe Instead
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle"></i> ${message || 'Found recipes you can make!'}
        </div>
        <div class="row g-4">
            ${recipes.map(recipe => `
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title">${recipe.recipe_name}</h5>
                                <span class="badge bg-success">${recipe.match_percentage}% Match</span>
                            </div>
                            <p class="card-text text-muted">${recipe.description || ''}</p>
                            
                            <div class="d-flex gap-2 mb-3 flex-wrap">
                                <span class="badge bg-danger">
                                    <i class="fas fa-fire"></i> ${Math.round(recipe.calories)} cal
                                </span>
                                <span class="badge bg-primary">
                                    <i class="fas fa-drumstick-bite"></i> ${Math.round(recipe.protein)}g protein
                                </span>
                                <span class="badge bg-info">
                                    <i class="fas fa-clock"></i> ${(recipe.prep_time || 0) + (recipe.cook_time || 0)} min
                                </span>
                            </div>
                            
                            ${recipe.missing_ingredients && recipe.missing_ingredients.length > 0 ? `
                                <div class="alert alert-warning py-2 mb-2">
                                    <small><strong>Missing:</strong> ${recipe.missing_ingredients.join(', ')}</small>
                                </div>
                            ` : `
                                <div class="alert alert-success py-2 mb-2">
                                    <small><i class="fas fa-check"></i> All ingredients available!</small>
                                </div>
                            `}
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewRecipeDetails(${recipe.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-success" onclick="cookRecipe(${recipe.id})">
                                    <i class="fas fa-fire"></i> Cook This
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
        <div class="text-center mt-4">
            <button class="btn btn-outline-primary" onclick="generateAIRecipe()">
                <i class="fas fa-robot"></i> Generate Creative AI Recipe
            </button>
        </div>
    `;
}

function displayAIRecipe(recipe) {
    const container = document.getElementById('generatedRecipesContainer');

    container.innerHTML = `
        <div class="alert alert-success mb-4">
            <i class="fas fa-robot"></i> AI has created a unique recipe for you!
        </div>
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">${recipe.name}</h3>
                <p class="text-muted">${recipe.description || ''}</p>
                
                <div class="d-flex gap-2 mb-4 flex-wrap">
                    <span class="badge bg-danger">
                        <i class="fas fa-fire"></i> ${Math.round(recipe.calories || recipe.nutrition?.calories || 0)} cal
                    </span>
                    <span class="badge bg-primary">
                        <i class="fas fa-seedling"></i> ${Math.round(recipe.protein || recipe.nutrition?.protein || 0)}g protein
                    </span>
                    <span class="badge bg-warning">
                        <i class="fas fa-bread-slice"></i> ${Math.round(recipe.carbs || recipe.nutrition?.carbs || 0)}g carbs
                    </span>
                    <span class="badge bg-info">
                        <i class="fas fa-cheese"></i> ${Math.round(recipe.fats || recipe.nutrition?.fats || 0)}g fats
                    </span>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-list"></i> Ingredients</h5>
                        <ul class="list-group mb-3">
                            ${(recipe.ingredients || []).map(ing => `
                                <li class="list-group-item">
                                    ${typeof ing === 'string' ? ing : `${ing.quantity || ''} ${ing.unit || ''} ${ing.name || ing.ingredient_name || ''}`}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-clock"></i> Time & Servings</h5>
                        <p><strong>Prep Time:</strong> ${recipe.prep_time || 15} minutes</p>
                        <p><strong>Cook Time:</strong> ${recipe.cook_time || 30} minutes</p>
                        <p><strong>Servings:</strong> ${recipe.servings || 2}</p>
                    </div>
                </div>
                
                <h5 class="mt-3"><i class="fas fa-tasks"></i> Instructions</h5>
                <div class="instructions">
                    ${typeof recipe.instructions === 'string'
            ? `<p>${recipe.instructions}</p>`
            : (recipe.instructions || []).map((step, i) => `
                            <div class="mb-2">
                                <strong>Step ${i + 1}:</strong> ${step}
                            </div>
                        `).join('')
        }
                </div>
                
                <div class="text-center mt-4">
                    <button class="btn btn-success" onclick="saveAIRecipe(${JSON.stringify(recipe).replace(/"/g, '&quot;')})">
                        <i class="fas fa-save"></i> Save This Recipe
                    </button>
                    <button class="btn btn-outline-primary" onclick="generateAIRecipe()">
                        <i class="fas fa-sync"></i> Generate Another
                    </button>
                </div>
            </div>
        </div>
    `;
}

function saveAIRecipe(recipeData) {
    // Parse recipe data if it's a string
    const recipe = typeof recipeData === 'string' ? JSON.parse(recipeData) : recipeData;

    console.log('Saving AI recipe:', recipe);

    // Prepare recipe data for saving
    const saveData = {
        recipe_name: recipe.name || recipe.recipe_name,
        description: recipe.description || 'AI-generated recipe',
        calories: recipe.calories || recipe.nutrition?.calories || 0,
        protein: recipe.protein || recipe.nutrition?.protein || 0,
        carbs: recipe.carbs || recipe.nutrition?.carbs || 0,
        fats: recipe.fats || recipe.nutrition?.fats || 0,
        prep_time: recipe.prep_time || 15,
        cook_time: recipe.cook_time || 30,
        servings: recipe.servings || 2,
        dietary_tags: 'AI-Generated',
        is_public: true,
        ingredients: recipe.ingredients || [],
        instructions: recipe.instructions || ''
    };

    fetch('api/recipes/save_ai_recipe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(saveData)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Recipe saved successfully! You can now find it in Recipe Management.');
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('generatedRecipesModal'));
                if (modal) modal.hide();
            } else {
                showAlert('danger', data.message || 'Failed to save recipe');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Failed to save recipe');
        });
}

function viewRecipeDetails(recipeId) {
    window.open(`recipes.html?view=${recipeId}`, '_blank');
}

function cookRecipe(recipeId) {
    // Redirect to dashboard with this recipe selected
    window.location.href = `dashboard.html?cook=${recipeId}`;
}
