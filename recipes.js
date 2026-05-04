// Global variables
let currentPage = 1;
let currentFilter = 'approved'; // Show approved recipes by default
let currentSearch = '';
let currentDietaryTag = '';
let allIngredients = [];
let isAdmin = false;
let editingRecipeId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for navbar to load, then setup everything
    setTimeout(() => {
        // Setup event listeners first
        setupEventListeners();
        
        // Initialize form with default rows
        initializeForm();
        
        // Then check auth and load data
        checkAuth();
        loadIngredients();
        
        // Load recipes after ensuring DOM is ready
        setTimeout(() => {
            applyFilters(); // This will call loadRecipes()
        }, 200);
    }, 100);
});

function checkAuth() {
    // Check if user is logged in
    fetch('api/get_user_profile.php')
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (!data.success) {
                    window.location.href = 'login.html';
                } else {
                    isAdmin = data.user.role === 'admin';
                    const pendingOption = document.getElementById('pendingOption');
                    if (isAdmin && pendingOption) {
                        pendingOption.style.display = 'block';
                    }
                }
            } catch (e) {
                console.error('Auth check JSON parse error:', e);
                console.error('Response text:', text);
                window.location.href = 'login.html';
            }
        })
        .catch(error => {
            console.error('Auth check error:', error);
            window.location.href = 'login.html';
        });
}

function setupEventListeners() {
    // Logout (optional - navbar might not be loaded yet)
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fetch('api/logout.php').then(() => window.location.href = 'login.html');
        });
    }
    
    // Filters - check if elements exist
    const applyFiltersBtn = document.getElementById('applyFilters');
    const searchInput = document.getElementById('searchInput');
    
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyFilters);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    }
    
    // Add ingredient - check if element exists
    const addIngredientBtn = document.getElementById('addIngredient');
    if (addIngredientBtn) {
        addIngredientBtn.addEventListener('click', addIngredientRow);
    }
    
    // Add step - check if element exists
    const addStepBtn = document.getElementById('addStep');
    if (addStepBtn) {
        addStepBtn.addEventListener('click', addStepRow);
    }
    
    // Save recipe - check if element exists
    const saveRecipeBtn = document.getElementById('saveRecipe');
    if (saveRecipeBtn) {
        saveRecipeBtn.addEventListener('click', saveRecipe);
    }
    
    // Modal reset - check if element exists
    const addRecipeModal = document.getElementById('addRecipeModal');
    if (addRecipeModal) {
        addRecipeModal.addEventListener('hidden.bs.modal', resetForm);
    }
}

function loadIngredients() {
    fetch('api/admin/get_ingredients.php')
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    allIngredients = data.ingredients;
                    updateIngredientSelects();
                } else {
                    console.error('Failed to load ingredients:', data.message);
                    allIngredients = []; // Fallback to empty array
                }
            } catch (e) {
                console.error('Ingredients JSON parse error:', e);
                console.error('Response text:', text);
                allIngredients = []; // Fallback to empty array
            }
        })
        .catch(error => {
            console.error('Error loading ingredients:', error);
            allIngredients = []; // Fallback to empty array
        });
}

function updateIngredientSelects() {
    const selects = document.querySelectorAll('.ingredient-select');
    selects.forEach(select => {
        select.innerHTML = '<option value="">Select ingredient...</option>';
        allIngredients.forEach(ing => {
            select.innerHTML += `<option value="${ing.id}">${ing.ingredient_name}</option>`;
        });
    });
}

function addIngredientRow() {
    const container = document.getElementById('ingredientsContainer');
    const row = document.createElement('div');
    row.className = 'ingredient-row mb-2';
    row.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <select class="form-select ingredient-select" required>
                    <option value="">Select ingredient...</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control ingredient-quantity" 
                       placeholder="Quantity" step="0.01" required>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control ingredient-unit" 
                       placeholder="Unit" value="g">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(row);
    updateIngredientSelects();
    
    // Add remove handler
    row.querySelector('.remove-ingredient').addEventListener('click', function() {
        row.remove();
    });
}

function addStepRow() {
    const container = document.getElementById('stepsContainer');
    const stepNumber = container.children.length + 1;
    const row = document.createElement('div');
    row.className = 'step-row mb-2';
    row.innerHTML = `
        <div class="input-group">
            <span class="input-group-text">${stepNumber}</span>
            <textarea class="form-control step-description" rows="2" 
                      placeholder="Describe this step..." required></textarea>
            <button type="button" class="btn btn-danger remove-step">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
    
    // Add remove handler
    row.querySelector('.remove-step').addEventListener('click', function() {
        row.remove();
        updateStepNumbers();
    });
}

function updateStepNumbers() {
    const steps = document.querySelectorAll('.step-row');
    steps.forEach((step, index) => {
        step.querySelector('.input-group-text').textContent = index + 1;
    });
}

function applyFilters() {
    // Check if required elements exist
    const filterTypeEl = document.getElementById('filterType');
    const searchInputEl = document.getElementById('searchInput');
    const dietaryFilterEl = document.getElementById('dietaryFilter');
    
    if (!filterTypeEl || !searchInputEl || !dietaryFilterEl) {
        console.error('Required filter elements not found, retrying in 500ms...');
        // Retry after a short delay in case DOM isn't ready
        setTimeout(applyFilters, 500);
        return;
    }
    
    currentFilter = filterTypeEl.value;
    currentSearch = searchInputEl.value;
    currentDietaryTag = dietaryFilterEl.value;
    currentPage = 1;
    
    // Show filter info
    const filterInfo = document.getElementById('filterInfo');
    const filterInfoText = document.getElementById('filterInfoText');
    
    if (filterInfo && filterInfoText) {
        if (currentFilter === 'ai_generated') {
            filterInfoText.textContent = 'Showing AI-generated recipes created from your meal plans. These recipes are personalized based on your preferences.';
            filterInfo.style.display = 'block';
        } else {
            filterInfo.style.display = 'none';
        }
    }
    
    loadRecipes();
}

function loadRecipes() {
    // Check if container exists
    const recipesContainer = document.getElementById('recipesContainer');
    if (!recipesContainer) {
        console.error('Recipes container not found');
        return;
    }
    
    const params = new URLSearchParams({
        filter: currentFilter,
        search: currentSearch,
        dietary_tag: currentDietaryTag,
        page: currentPage,
        limit: 12
    });
    
    recipesContainer.innerHTML = `
        <div class="col-12 loading-spinner">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    fetch(`api/recipes/get_recipes.php?${params}`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text();
        })
        .then(text => {
            // Check if response starts with HTML error (like PHP errors)
            if (text.trim().startsWith('<')) {
                console.error('Server returned HTML instead of JSON:', text);
                throw new Error('Server error - please check server logs');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayRecipes(data.recipes);
                    displayPagination(data.pagination);
                } else {
                    throw new Error(data.message || 'Failed to load recipes');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                recipesContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error parsing server response. Please try again.
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading recipes:', error);
            recipesContainer.innerHTML = `
                <div class="col-12 text-center">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading recipes: ${error.message}
                    </div>
                </div>
            `;
        });
}

function displayRecipes(recipes) {
    const container = document.getElementById('recipesContainer');
    
    if (recipes.length === 0) {
        let emptyMessage = 'Generate meal plans from your dashboard to create personalized recipes!';
        if (currentFilter === 'ai_generated') {
            emptyMessage = 'No AI-generated recipes found. Generate meal plans from your dashboard to create AI recipes!';
        } else if (currentFilter === 'my') {
            emptyMessage = 'You don\'t have any recipes yet. Generate meal plans from your dashboard to get personalized AI recipes!';
        } else if (currentFilter === 'approved' || currentFilter === 'all') {
            emptyMessage = 'No recipes available. Try generating meal plans to create personalized recipes!';
        }
        
        container.innerHTML = `
            <div class="col-12 empty-state">
                <i class="fas fa-${currentFilter === 'ai_generated' ? 'robot' : 'magic'}"></i>
                <h4>No recipes found</h4>
                <p>${emptyMessage}</p>
                <a href="dashboard.html" class="btn btn-success mt-2">
                    <i class="fas fa-magic"></i> Generate Meal Plans
                </a>
            </div>
        `;
        return;
    }
    
    container.innerHTML = recipes.map(recipe => `
        <div class="col-md-4">
            <div class="card recipe-card" ${recipe.is_ai_generated ? 'data-ai-generated="true"' : ''}>
                <div class="position-relative">
                    ${recipe.image_url ? 
                        `<img src="${recipe.image_url}" class="recipe-image" alt="${recipe.recipe_name}">` :
                        `<div class="recipe-image bg-secondary d-flex align-items-center justify-content-center">
                            <i class="fas fa-utensils fa-3x text-white"></i>
                        </div>`
                    }
                    ${recipe.is_ai_generated ? 
                        '<span class="badge bg-gradient bg-info recipe-badge"><i class="fas fa-robot"></i> AI Generated</span>' : ''
                    }
                    ${getStatusBadge(recipe.approval_status)}
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <h5 class="card-title mb-0">${recipe.recipe_name}</h5>
                    </div>
                    
                    <div class="rating-stars mb-2">
                        ${getStarRating(recipe.avg_rating)}
                        <small class="text-muted">(${recipe.rating_count})</small>
                    </div>
                    
                    <p class="card-text text-muted small">${recipe.description || 'No description'}</p>
                    
                    <div class="d-flex gap-2 mb-2">
                        ${recipe.prep_time ? `<span class="badge bg-light text-dark">
                            <i class="fas fa-clock"></i> ${recipe.prep_time + recipe.cook_time} min
                        </span>` : ''}
                        ${recipe.servings ? `<span class="badge bg-light text-dark">
                            <i class="fas fa-users"></i> ${recipe.servings} servings
                        </span>` : ''}
                    </div>
                    
                    <div class="nutrition-info">
                        <div class="nutrition-item">
                            <strong>${Math.round(recipe.calories)}</strong>
                            <small>Calories</small>
                        </div>
                        <div class="nutrition-item">
                            <strong>${Math.round(recipe.protein)}g</strong>
                            <small>Protein</small>
                        </div>
                        <div class="nutrition-item">
                            <strong>${Math.round(recipe.carbs)}g</strong>
                            <small>Carbs</small>
                        </div>
                        <div class="nutrition-item">
                            <strong>${Math.round(recipe.fats)}g</strong>
                            <small>Fats</small>
                        </div>
                    </div>
                    
                    <div class="recipe-actions mt-3">
                        <button class="btn btn-sm btn-outline-success" onclick="viewRecipe(${recipe.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${canEditRecipe(recipe) ? `
                            <button class="btn btn-sm btn-outline-primary" onclick="editRecipe(${recipe.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRecipe(${recipe.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                        ${isAdmin && recipe.approval_status === 'pending' ? `
                            <button class="btn btn-sm btn-success" onclick="approveRecipe(${recipe.id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="rejectRecipe(${recipe.id})">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning recipe-status">Pending</span>',
        'approved': '<span class="badge bg-success recipe-status">Approved</span>',
        'rejected': '<span class="badge bg-danger recipe-status">Rejected</span>'
    };
    return badges[status] || '';
}

function getStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += i <= rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
    }
    return stars;
}

function canEditRecipe(recipe) {
    // User can edit their own recipes or admin can edit any
    return isAdmin || recipe.user_id === parseInt(sessionStorage.getItem('user_id'));
}

function displayPagination(pagination) {
    const container = document.getElementById('pagination');
    if (!container) {
        console.error('Pagination container not found');
        return;
    }
    
    let html = '';
    
    if (pagination && pagination.total_pages > 1) {
        for (let i = 1; i <= pagination.total_pages; i++) {
            html += `
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        }
    }
    
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadRecipes();
    window.scrollTo(0, 0);
}



function viewRecipe(recipeId) {
    // Fetch specific recipe details
    fetch(`api/recipes/get_recipe_details.php?recipe_id=${recipeId}`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success && data.recipe) {
                    showRecipeDetails(data.recipe);
                } else {
                    throw new Error(data.message || 'Recipe not found');
                }
            } catch (e) {
                console.error('JSON parse error in viewRecipe:', e);
                console.error('Response text:', text);
                alert('Error loading recipe details');
            }
        })
        .catch(error => {
            console.error('Error loading recipe:', error);
            alert('Error loading recipe: ' + error.message);
        });
}

function showRecipeDetails(recipe) {
    document.getElementById('viewRecipeName').textContent = recipe.recipe_name;
    document.getElementById('viewRecipeContent').innerHTML = `
        ${recipe.image_url ? `<img src="${recipe.image_url}" class="img-fluid rounded mb-3" alt="${recipe.recipe_name}">` : ''}
        
        <div class="recipe-detail-section">
            <p>${recipe.description || 'No description available'}</p>
            <div class="d-flex gap-3 mb-3">
                <span><i class="fas fa-clock"></i> Prep: ${recipe.prep_time || 0} min</span>
                <span><i class="fas fa-fire"></i> Cook: ${recipe.cook_time || 0} min</span>
                <span><i class="fas fa-users"></i> Servings: ${recipe.servings || 1}</span>
            </div>
        </div>
        
        <div class="recipe-detail-section">
            <h6><i class="fas fa-list"></i> Ingredients</h6>
            <ul class="ingredient-list">
                ${recipe.ingredients.map(ing => `
                    <li>${ing.quantity} ${ing.unit} ${ing.ingredient_name}</li>
                `).join('')}
            </ul>
        </div>
        
        <div class="recipe-detail-section">
            <h6><i class="fas fa-tasks"></i> Instructions</h6>
            <ol class="step-list">
                ${recipe.steps.map(step => `
                    <li>${step.step_description}</li>
                `).join('')}
            </ol>
        </div>
        
        <div class="recipe-detail-section">
            <h6><i class="fas fa-chart-pie"></i> Nutrition Information</h6>
            <div class="row text-center">
                <div class="col-3">
                    <h4 class="text-success">${Math.round(recipe.calories)}</h4>
                    <small>Calories</small>
                </div>
                <div class="col-3">
                    <h4 class="text-success">${Math.round(recipe.protein)}g</h4>
                    <small>Protein</small>
                </div>
                <div class="col-3">
                    <h4 class="text-success">${Math.round(recipe.carbs)}g</h4>
                    <small>Carbs</small>
                </div>
                <div class="col-3">
                    <h4 class="text-success">${Math.round(recipe.fats)}g</h4>
                    <small>Fats</small>
                </div>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('viewRecipeModal')).show();
}

function editRecipe(recipeId) {
    // Load recipe data and populate form
    fetch(`api/recipes/get_recipe_details.php?recipe_id=${recipeId}`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success && data.recipe) {
                    populateEditForm(data.recipe);
                } else {
                    throw new Error(data.message || 'Recipe not found');
                }
            } catch (e) {
                console.error('JSON parse error in editRecipe:', e);
                console.error('Response text:', text);
                alert('Error loading recipe for editing');
            }
        })
        .catch(error => {
            console.error('Error loading recipe for edit:', error);
            alert('Error loading recipe: ' + error.message);
        });
}

function populateEditForm(recipe) {
    editingRecipeId = recipe.id;
    document.getElementById('modalTitle').textContent = 'Edit Recipe';
    document.getElementById('recipeId').value = recipe.id;
    document.getElementById('recipeName').value = recipe.recipe_name;
    document.getElementById('recipeDescription').value = recipe.description || '';
    document.getElementById('prepTime').value = recipe.prep_time || '';
    document.getElementById('cookTime').value = recipe.cook_time || '';
    document.getElementById('servings').value = recipe.servings || 1;
    document.getElementById('imageUrl').value = recipe.image_url || '';
    document.getElementById('dietaryTags').value = recipe.dietary_tags || '';
    document.getElementById('isPublic').checked = recipe.is_public == 1;
    
    // Clear and populate ingredients
    const ingredientsContainer = document.getElementById('ingredientsContainer');
    ingredientsContainer.innerHTML = '';
    recipe.ingredients.forEach(ing => {
        addIngredientRow();
        const lastRow = ingredientsContainer.lastElementChild;
        lastRow.querySelector('.ingredient-select').value = ing.ingredient_id;
        lastRow.querySelector('.ingredient-quantity').value = ing.quantity;
        lastRow.querySelector('.ingredient-unit').value = ing.unit;
    });
    
    // Clear and populate steps
    const stepsContainer = document.getElementById('stepsContainer');
    stepsContainer.innerHTML = '';
    recipe.steps.forEach(step => {
        addStepRow();
        const lastRow = stepsContainer.lastElementChild;
        lastRow.querySelector('.step-description').value = step.step_description;
    });
    
    new bootstrap.Modal(document.getElementById('addRecipeModal')).show();
}

function saveRecipe() {
    // Collect form data
    const ingredients = [];
    document.querySelectorAll('.ingredient-row').forEach(row => {
        const id = row.querySelector('.ingredient-select').value;
        const qty = row.querySelector('.ingredient-quantity').value;
        const unit = row.querySelector('.ingredient-unit').value;
        if (id && qty) {
            ingredients.push({ingredient_id: parseInt(id), quantity: parseFloat(qty), unit});
        }
    });
    
    const steps = [];
    document.querySelectorAll('.step-row').forEach(row => {
        const desc = row.querySelector('.step-description').value;
        if (desc) {
            steps.push({description: desc});
        }
    });
    
    if (ingredients.length === 0 || steps.length === 0) {
        alert('Please add at least one ingredient and one step');
        return;
    }
    
    const recipeData = {
        recipe_name: document.getElementById('recipeName').value,
        description: document.getElementById('recipeDescription').value,
        prep_time: parseInt(document.getElementById('prepTime').value) || 0,
        cook_time: parseInt(document.getElementById('cookTime').value) || 0,
        servings: parseInt(document.getElementById('servings').value) || 1,
        image_url: document.getElementById('imageUrl').value,
        dietary_tags: document.getElementById('dietaryTags').value,
        is_public: document.getElementById('isPublic').checked,
        ingredients,
        steps
    };
    
    const url = editingRecipeId ? 'api/recipes/edit_recipe.php' : 'api/recipes/add_recipe.php';
    if (editingRecipeId) {
        recipeData.recipe_id = editingRecipeId;
    }
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(recipeData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('addRecipeModal')).hide();
            loadRecipes();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteRecipe(recipeId) {
    if (!confirm('Are you sure you want to delete this recipe?')) return;
    
    fetch('api/recipes/delete_recipe.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({recipe_id: recipeId})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadRecipes();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function approveRecipe(recipeId) {
    fetch('api/recipes/approve_recipe.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({recipe_id: recipeId, action: 'approve'})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadRecipes();
        }
    });
}

function rejectRecipe(recipeId) {
    const reason = prompt('Rejection reason (optional):');
    fetch('api/recipes/approve_recipe.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({recipe_id: recipeId, action: 'reject', rejection_reason: reason})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadRecipes();
        }
    });
}

function resetForm() {
    editingRecipeId = null;
    const modalTitle = document.getElementById('modalTitle');
    const recipeForm = document.getElementById('recipeForm');
    const recipeId = document.getElementById('recipeId');
    
    if (modalTitle) modalTitle.textContent = 'Add New Recipe';
    if (recipeForm) recipeForm.reset();
    if (recipeId) recipeId.value = '';
    
    // Reset to one ingredient and one step
    const ingredientsContainer = document.getElementById('ingredientsContainer');
    const stepsContainer = document.getElementById('stepsContainer');
    
    if (ingredientsContainer) {
        ingredientsContainer.innerHTML = '';
        addIngredientRow();
    }
    
    if (stepsContainer) {
        stepsContainer.innerHTML = '';
        addStepRow();
    }
}

function initializeForm() {
    // Initialize form with default ingredient and step rows
    const ingredientsContainer = document.getElementById('ingredientsContainer');
    const stepsContainer = document.getElementById('stepsContainer');
    
    if (ingredientsContainer && ingredientsContainer.children.length === 0) {
        addIngredientRow();
    }
    
    if (stepsContainer && stepsContainer.children.length === 0) {
        addStepRow();
    }
}
