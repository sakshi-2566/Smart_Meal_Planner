// Dashboard JavaScript
let currentUser = null;
let lastNutritionTotals = { total_calories: 0, total_protein: 0, total_carbs: 0, total_fats: 0 };

// Check authentication on page load
document.addEventListener('DOMContentLoaded', function () {
    console.log('Dashboard.js loaded - Version 3 (Fixed date formatting)');

    // Show page loading indicator
    showPageLoading();

    checkAuth();

    // Load user profile and nutrition separately to avoid Promise issues
    console.log('Dashboard: Calling loadUserProfile');
    loadUserProfile();

    console.log('Dashboard: Calling loadTodayNutrition');
    loadTodayNutrition();

    console.log('Dashboard: Calling loadAIRecommendations');
    loadAIRecommendations();

    console.log('Dashboard: Calling loadTodayMealPlan');
    loadTodayMealPlan(); // Load today's meal plan

    // Check for "cook" parameter from inventory
    handleCookParameter();

    // Hide loading after a short delay to ensure content is loaded
    setTimeout(hidePageLoading, 1000);
});

// Check if user is authenticated
function checkAuth() {
    const user = localStorage.getItem('user');
    if (!user) {
        window.location.href = 'login.html';
        return;
    }
    currentUser = JSON.parse(user);

    // Redirect admin to admin panel
    if (currentUser.role === 'admin') {
        window.location.href = 'admin.html';
        return;
    }

    updateUserDisplay();
}

// Update user display
function updateUserDisplay() {
    if (currentUser) {
        document.getElementById('userName').textContent = currentUser.firstName;
        document.getElementById('welcomeName').textContent = currentUser.firstName;
        document.getElementById('profileName').textContent = `${currentUser.firstName} ${currentUser.lastName}`;
        document.getElementById('profileEmail').textContent = currentUser.email;

        const dietMap = {
            'none': 'No Restrictions',
            'vegetarian': 'Vegetarian',
            'non-vegetarian': 'Non-Vegetarian',
            'vegan': 'Vegan',
            'keto': 'Keto',
            'paleo': 'Paleo',
            'gluten-free': 'Gluten-Free'
        };
        document.getElementById('userDiet').textContent = dietMap[currentUser.dietaryPreference] || 'No preference';
    }
}

// Load user profile
function loadUserProfile() {
    return fetch('api/get_user_profile.php', {
        method: 'GET',
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const profile = data.user;

                // Update BMR and TDEE
                if (profile.bmr) {
                    document.getElementById('userBMR').textContent = Math.round(profile.bmr);
                }
                if (profile.tdee) {
                    document.getElementById('userTDEE').textContent = Math.round(profile.tdee);
                }

                // Update goals
                if (profile.target_calories) {
                    document.getElementById('calorieGoal').textContent = profile.target_calories;
                    document.getElementById('caloriesTarget').textContent = profile.target_calories;
                }
                if (profile.target_protein) {
                    document.getElementById('proteinGoal').textContent = Math.round(profile.target_protein);
                    document.getElementById('proteinTarget').textContent = Math.round(profile.target_protein);
                }
                if (profile.target_carbs) {
                    document.getElementById('carbsGoal').textContent = Math.round(profile.target_carbs);
                    document.getElementById('carbsTarget').textContent = Math.round(profile.target_carbs);
                }
                if (profile.target_fats) {
                    document.getElementById('fatsGoal').textContent = Math.round(profile.target_fats);
                    document.getElementById('fatsTarget').textContent = Math.round(profile.target_fats);
                }

                // Update goal display
                const goalMap = {
                    'weight_loss': 'Weight Loss',
                    'maintenance': 'Maintenance',
                    'muscle_gain': 'Muscle Gain',
                    'athletic': 'Athletic Performance'
                };
                if (profile.goal) {
                    document.getElementById('userGoal').textContent = goalMap[profile.goal] || 'Maintenance';
                }

                // Recalculate progress bars with new targets if we have nutrition data
                if (lastNutritionTotals) {
                    updateNutritionDisplay(lastNutritionTotals);
                }

                return profile; // Return for promise chaining
            }
            return null;
        })
        .catch(error => {
            console.error('Error loading profile:', error);
            return null;
        });
}

// Load today's nutrition
function loadTodayNutrition() {
    const today = new Date().toISOString().split('T')[0];

    fetch(`api/get_nutrition_logs.php?start_date=${today}&end_date=${today}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            window.nutritionLoaded = true;
            if (data.success && data.dailyTotals.length > 0) {
                lastNutritionTotals = data.dailyTotals[0];
                updateNutritionDisplay(lastNutritionTotals);
            } else {
                // Reset if no data for today
                lastNutritionTotals = { total_calories: 0, total_protein: 0, total_carbs: 0, total_fats: 0 };
                updateNutritionDisplay(lastNutritionTotals);
            }
        })
        .catch(error => console.error('Error loading nutrition:', error));
}

// Update nutrition display
function updateNutritionDisplay(totals) {
    const calories = totals.total_calories || 0;
    const protein = totals.total_protein || 0;
    const carbs = totals.total_carbs || 0;
    const fats = totals.total_fats || 0;

    // Update stat cards
    document.getElementById('todayCalories').textContent = calories;
    document.getElementById('todayProtein').textContent = Math.round(protein) + 'g';
    document.getElementById('todayCarbs').textContent = Math.round(carbs) + 'g';
    document.getElementById('todayFats').textContent = Math.round(fats) + 'g';

    // Update progress bars
    const calorieGoal = parseInt(document.getElementById('caloriesTarget').textContent);
    const proteinGoal = parseInt(document.getElementById('proteinTarget').textContent);
    const carbsGoal = parseInt(document.getElementById('carbsTarget').textContent);
    const fatsGoal = parseInt(document.getElementById('fatsTarget').textContent);

    updateProgressBar('calories', calories, calorieGoal);
    updateProgressBar('protein', protein, proteinGoal);
    updateProgressBar('carbs', carbs, carbsGoal);
    updateProgressBar('fats', fats, fatsGoal);
}

// Update progress bar
function updateProgressBar(type, current, goal) {
    const percentage = Math.min((current / goal) * 100, 100);
    document.getElementById(`${type}Progress`).textContent = Math.round(current);
    document.getElementById(`${type}Bar`).style.width = percentage + '%';
}

// Generate AI Meal Plan - DEPRECATED
// Meal plans are now automatically generated when users update their profile
// This function is kept for backward compatibility but is no longer used
function generateAIMealPlan() {
    showNotification('info', 'Please update your profile to generate a new meal plan automatically!');
    setTimeout(() => {
        window.location.href = 'profile.html';
    }, 2000);
}

// Load full week meal plan
function loadTodayMealPlan() {
    fetch('api/get_week_meal_plan.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.days && data.days.length > 0) {
                displayWeekMealPlan(data.days);
            } else {
                // Show empty state
                displayMealPlan([]);
            }
        })
        .catch(error => {
            console.error('Error loading meal plan:', error);
            displayMealPlan([]);
        });
}

// Display week meal plan (day-wise)
function displayWeekMealPlan(days) {
    const container = document.getElementById('todayMeals');
    
    // Debug: Log the received data
    console.log('displayWeekMealPlan received:', days);

    if (!days || days.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <p>No meal plan found</p>
                <button class="btn btn-primary" onclick="generateAIMealPlan()">Generate Meal Plan</button>
            </div>
        `;
        return;
    }

    container.innerHTML = days.map((day, index) => {
        // Debug: Log each day's data
        console.log(`Day ${index}:`, day);
        console.log(`Date: "${day.date}" (type: ${typeof day.date})`);
        console.log(`Day name: "${day.day_name}" (type: ${typeof day.day_name})`);
        
        return `
        <div class="day-section mb-4">
            <div class="day-header d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day text-primary"></i> 
                    ${day.day_name || 'Unknown Day'} - ${formatDate(day.date)}
                    ${day.is_today ? '<span class="badge bg-success ms-2">Today</span>' : ''}
                </h5>
                <div class="day-nutrition">
                    <small class="text-muted">
                        <i class="fas fa-fire text-danger"></i> ${Math.round(day.total_calories)} cal
                    </small>
                </div>
            </div>
            <div class="meals-grid">
                ${day.meals.map(meal => `
                    <div class="meal-card">
                        <div class="meal-type-badge ${meal.meal_type}">
                            ${getMealIcon(meal.meal_type)} ${capitalizeFirst(meal.meal_type)}
                        </div>
                        <h6 class="meal-name">${meal.recipe_name}</h6>
                        <div class="meal-nutrition-inline">
                            <span title="Calories"><i class="fas fa-fire text-danger"></i> ${Math.round(meal.calories)}</span>
                            <span title="Protein"><i class="fas fa-seedling text-primary"></i> ${Math.round(meal.protein)}g</span>
                            <span title="Carbs"><i class="fas fa-bread-slice text-warning"></i> ${Math.round(meal.carbs)}g</span>
                            <span title="Fats"><i class="fas fa-cheese text-success"></i> ${Math.round(meal.fats)}g</span>
                        </div>
                        <div class="meal-actions mt-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewMeal(${meal.recipe_id}, '${meal.meal_type}', ${meal.meal_plan_item_id}, ${meal.is_eaten})" title="View Recipe">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm ${meal.is_eaten ? 'btn-success' : 'btn-outline-success'}" 
                                    onclick="logMealComplete(${meal.recipe_id}, '${meal.meal_type}', '${meal.recipe_name.replace(/'/g, "\\'")}', ${Math.round(meal.calories)}, ${Math.round(meal.protein)}, ${Math.round(meal.carbs)}, ${Math.round(meal.fats)}, ${meal.meal_plan_item_id})" 
                                    ${meal.is_eaten ? 'disabled' : ''}
                                    title="${meal.is_eaten ? 'Already Eaten' : 'Mark as Eaten'}">
                                <i class="fas ${meal.is_eaten ? 'fa-check-circle' : 'fa-check'}"></i> ${meal.is_eaten ? 'Logged' : ''}
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
        `;
    }).join('');
}

// Helper functions
function formatDate(dateStr) {
    if (!dateStr || dateStr === '' || dateStr === null || dateStr === undefined) {
        return 'No Date';
    }
    
    // Handle different date formats
    let date;
    
    // Try parsing as-is first
    date = new Date(dateStr);
    
    // If invalid, try parsing as YYYY-MM-DD format specifically
    if (isNaN(date.getTime())) {
        const dateMatch = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (dateMatch) {
            const [, year, month, day] = dateMatch;
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
        }
    }
    
    // If still invalid, try other common formats
    if (isNaN(date.getTime())) {
        // Try MM/DD/YYYY format
        const usDateMatch = dateStr.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (usDateMatch) {
            const [, month, day, year] = usDateMatch;
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
        }
    }
    
    // Final check - if still invalid, return error with debug info
    if (isNaN(date.getTime())) {
        console.error('Invalid date format:', dateStr, typeof dateStr);
        return `Invalid: ${dateStr}`;
    }
    
    const options = { month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function getMealIcon(mealType) {
    const icons = {
        'breakfast': '🌅',
        'lunch': '🌞',
        'dinner': '🌙',
        'snack': '🍎',
        'starter': '🥗',
        'main-course': '🍛',
        'dessert': '🍰'
    };
    return icons[mealType] || '🍽️';
}

function capitalizeFirst(str) {
    if (!str) return 'Meal';
    return str.toString().split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

// Display meal plan (fallback for old code)
function displayMealPlan(meals) {
    const container = document.getElementById('todayMeals');
    if (!meals || meals.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <p>No meals planned</p>
                <button class="btn btn-primary" onclick="generateAIMealPlan()">Generate Meal Plan</button>
            </div>
        `;
        return;
    }

    container.innerHTML = meals.map(meal => `
        <div class="meal-item" data-recipe-id="${meal.recipe_id || meal.id}" data-meal-type="${meal.meal_type}">
            <div class="meal-info">
                <span class="meal-type">${meal.meal_type}</span>
                <div class="meal-name">${meal.recipe_name}</div>
                <div class="meal-nutrition">
                    <span class="text-danger"><i class="fas fa-fire"></i> ${meal.calories}</span>
                    <span class="text-primary"><i class="fas fa-seedling"></i> ${Math.round(meal.protein)}</span>
                    <span class="text-warning"><i class="fas fa-bread-slice"></i> ${Math.round(meal.carbs)}</span>
                    <span class="text-success"><i class="fas fa-cheese"></i> ${Math.round(meal.fats)}</span>
                </div>
            </div>
            <div class="meal-actions">
                <button class="btn btn-sm btn-outline-info" onclick="viewMeal(${meal.recipe_id || meal.id}, '${meal.meal_type}', ${meal.meal_plan_item_id || meal.id}, ${meal.is_eaten || 0})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm ${meal.is_eaten ? 'btn-success' : 'btn-outline-success'}" 
                        onclick="logMealComplete(${meal.recipe_id || meal.id}, '${meal.meal_type}', '${meal.recipe_name.replace(/'/g, "\\'")}', ${meal.calories}, ${Math.round(meal.protein)}, ${Math.round(meal.carbs)}, ${Math.round(meal.fats)}, ${meal.meal_plan_item_id || meal.id})" 
                        ${meal.is_eaten ? 'disabled' : ''}
                        title="${meal.is_eaten ? 'Already Eaten' : 'Mark as Eaten'}">
                    <i class="fas ${meal.is_eaten ? 'fa-check-circle' : 'fa-check'}"></i> ${meal.is_eaten ? 'Logged' : 'Log'}
                </button>
            </div>
        </div>
    `).join('');
}

// Load AI Recommendations
function loadAIRecommendations() {
    fetch('api/get_ai_recommendations.php', {
        credentials: 'include'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success && data.recommendations) {
                    displayRecommendations(data.recommendations);
                }
            } catch (e) {
                console.error('Failed to parse JSON:', text.substring(0, 200));
                // Show default recommendations on error
                displayRecommendations([{
                    icon: 'fa-info-circle',
                    color: 'info',
                    message: 'Complete your profile to get personalized AI recommendations!'
                }]);
            }
        })
        .catch(error => {
            console.error('Error loading recommendations:', error);
            // Show default recommendations on error
            displayRecommendations([{
                icon: 'fa-info-circle',
                color: 'info',
                message: 'Complete your profile to get personalized AI recommendations!'
            }]);
        });
}

// Display recommendations
function displayRecommendations(recommendations) {
    const container = document.getElementById('aiRecommendations');
    if (!recommendations || recommendations.length === 0) return;

    container.innerHTML = recommendations.map(rec => `
        <div class="recommendation-item">
            <i class="fas ${rec.icon} text-${rec.color}"></i>
            <p>${rec.message}</p>
        </div>
    `).join('');
}

// Quick Actions
function logMeal() {
    // Redirect to profile to update and generate meal plan
    window.location.href = 'profile.html';
}

function generateRecipe() {
    // Redirect to inventory to generate AI recipe
    window.location.href = 'inventory.html';
}

function createShoppingList() {
    // Redirect to orders page to see purchase history
    window.location.href = 'orders.html';
}

function viewAnalytics() {
    // Show nutrition analytics modal
    showNutritionAnalytics();
}

function showNutritionAnalytics() {
    // Fetch user's nutrition data
    fetch('api/get_nutrition_logs.php?days=7', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayAnalyticsModal(data);
            } else {
                showNotification('info', 'No nutrition data available yet. Start logging meals!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Failed to load analytics');
        });
}

function displayAnalyticsModal(data) {
    const modalHtml = `
        <div class="modal fade" id="analyticsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-chart-bar"></i> Your Nutrition Analytics (Last 7 Days)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-fire text-danger fa-2x mb-2"></i>
                                    <h4>${Math.round(data.avg_calories || 0)}</h4>
                                    <small>Avg Calories/Day</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-seedling text-primary fa-2x mb-2"></i>
                                    <h4>${Math.round(data.avg_protein || 0)}g</h4>
                                    <small>Avg Protein/Day</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-bread-slice text-warning fa-2x mb-2"></i>
                                    <h4>${Math.round(data.avg_carbs || 0)}g</h4>
                                    <small>Avg Carbs/Day</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-cheese text-success fa-2x mb-2"></i>
                                    <h4>${Math.round(data.avg_fats || 0)}g</h4>
                                    <small>Avg Fats/Day</small>
                                </div>
                            </div>
                        </div>
                        
                        <h6>Recent Meals Logged:</h6>
                        <div class="list-group">
                            ${data.recent_meals && data.recent_meals.length > 0 ?
            data.recent_meals.slice(0, 5).map(meal => `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong>${meal.meal_name || 'Meal'}</strong>
                                            <small class="text-muted">${new Date(meal.logged_at).toLocaleDateString()}</small>
                                        </div>
                                        <small>
                                            🔥 ${meal.calories} cal | 
                                            🌱 ${Math.round(meal.protein)}g | 
                                            🍞 ${Math.round(meal.carbs)}g | 
                                            🧀 ${Math.round(meal.fats)}g
                                        </small>
                                    </div>
                                `).join('')
            : '<p class="text-muted">No meals logged yet</p>'
        }
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('analyticsModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('analyticsModal'));
    modal.show();

    document.getElementById('analyticsModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

function viewMeal(recipeId, mealType = 'meal', mealPlanItemId = null, isEaten = 0) {
    console.log('Loading recipe:', recipeId, 'Type:', mealType, 'ID:', mealPlanItemId, 'Logged:', isEaten);

    // Fetch recipe details
    fetch(`api/recipes/get_recipe_details.php?recipe_id=${recipeId}`, {
        credentials: 'include'
    })
        .then(res => {
            console.log('Response status:', res.status);
            return res.text(); // Get as text first to see what we're getting
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showRecipeModal(data.recipe, mealType, mealPlanItemId, isEaten);
                } else {
                    showNotification('error', data.message || 'Failed to load recipe details');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was:', text);
                showNotification('error', 'Server returned invalid response');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showNotification('error', 'Failed to load recipe details: ' + error.message);
        });
}

function getMealFAIcon(mealType) {
    const icons = {
        'breakfast': 'fa-mug-hot',
        'lunch': 'fa-sun',
        'dinner': 'fa-moon',
        'snack': 'fa-apple-alt',
        'starter': 'fa-bowl-food',
        'main-course': 'fa-utensils',
        'dessert': 'fa-ice-cream'
    };
    return icons[mealType] || 'fa-utensils';
}

function showRecipeModal(recipe, mealType = 'meal', mealPlanItemId = null, isEaten = 0) {
    // Remove existing modal if any
    const existingModal = document.getElementById('recipeModal');
    if (existingModal) {
        existingModal.remove();
    }

    const modalHtml = `
        <div class="modal fade" id="recipeModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas ${getMealFAIcon(mealType)}"></i> ${recipe.recipe_name}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <span class="badge bg-white text-primary border border-primary fs-5 px-4 py-2 rounded-pill">
                                ${capitalizeFirst(mealType)}
                            </span>
                        </div>

                        ${recipe.description ? `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> ${recipe.description}
                            </div>
                        ` : ''}
                        
                        <div class="row g-3 mb-4">
                            <div class="col-3">
                                <div class="nutrition-box text-center p-3 bg-light rounded">
                                    <i class="fas fa-fire text-danger fa-2x mb-2"></i>
                                    <h4>${Math.round(recipe.calories)}</h4>
                                    <small>Calories</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="nutrition-box text-center p-3 bg-light rounded">
                                    <i class="fas fa-seedling text-primary fa-2x mb-2"></i>
                                    <h4>${Math.round(recipe.protein)}g</h4>
                                    <small>Protein</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="nutrition-box text-center p-3 bg-light rounded">
                                    <i class="fas fa-bread-slice text-warning fa-2x mb-2"></i>
                                    <h4>${Math.round(recipe.carbs)}g</h4>
                                    <small>Carbs</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="nutrition-box text-center p-3 bg-light rounded">
                                    <i class="fas fa-cheese text-success fa-2x mb-2"></i>
                                    <h4>${Math.round(recipe.fats)}g</h4>
                                    <small>Fats</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4 text-center">
                            <div class="col-4">
                                <i class="fas fa-clock text-info"></i>
                                <strong>Prep:</strong> ${recipe.prep_time || 15} min
                            </div>
                            <div class="col-4">
                                <i class="fas fa-fire-burner text-danger"></i>
                                <strong>Cook:</strong> ${recipe.cook_time || 30} min
                            </div>
                            <div class="col-4">
                                <i class="fas fa-users text-success"></i>
                                <strong>Servings:</strong> ${recipe.servings || 2}
                            </div>
                        </div>
                        
                        <h5 class="mb-3"><i class="fas fa-list-ul text-success"></i> Ingredients</h5>
                        <div class="list-group mb-4">
                            ${recipe.ingredients && recipe.ingredients.length > 0 ?
            recipe.ingredients.map(ing => `
                                    <div class="list-group-item">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <strong>${ing.quantity} ${ing.unit}</strong> ${ing.ingredient_name}
                                    </div>
                                `).join('')
            : '<p class="text-muted">No ingredients listed</p>'
        }
                        </div>
                        
                        <h5 class="mb-3"><i class="fas fa-tasks text-primary"></i> Step-by-Step Instructions</h5>
                        <div class="instructions-list">
                            ${recipe.steps && recipe.steps.length > 0 ?
            recipe.steps.map((step, index) => `
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex">
                                                <div class="step-number-badge me-3">
                                                    <span class="badge bg-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                                        ${step.step_number || index + 1}
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <p class="mb-1">${step.instruction || step.step_description}</p>
                                                    ${step.duration ? `<small class="text-muted"><i class="fas fa-clock"></i> ${step.duration} minutes</small>` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')
            : recipe.instructions ?
                recipe.instructions.split('\\n').map((instruction, index) => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex">
                                                    <div class="step-number-badge me-3">
                                                        <span class="badge bg-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                                            ${index + 1}
                                                        </span>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <p class="mb-1">${instruction.trim()}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')
                : '<p class="text-muted">No instructions available</p>'
        }
                        </div>
                        
                        ${recipe.dietary_tags ? `
                            <div class="mt-4">
                                <h6><i class="fas fa-leaf"></i> Dietary Information:</h6>
                                <div class="mt-2">
                                    ${recipe.dietary_tags.split(',').map(tag =>
            `<span class="badge bg-secondary me-1">${tag.trim()}</span>`
        ).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn ${isEaten ? 'btn-success' : 'btn-success'}" 
                                onclick="logMealComplete(${recipe.id}, '${mealType}', '${recipe.recipe_name.replace(/'/g, "\\'")}', ${Math.round(recipe.calories)}, ${Math.round(recipe.protein)}, ${Math.round(recipe.carbs)}, ${Math.round(recipe.fats)}, ${mealPlanItemId})"
                                ${isEaten ? 'disabled' : ''}>
                            <i class="fas ${isEaten ? 'fa-check-circle' : 'fa-check'}"></i> ${isEaten ? 'Logged' : 'Mark as Eaten'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('recipeModal'));
    modal.show();

    document.getElementById('recipeModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

function logMealComplete(recipeId, mealType, recipeName, calories, protein, carbs, fats, mealPlanItemId = null) {
    console.log('logMealComplete called for:', recipeName, 'Recipe ID:', recipeId, 'Item ID:', mealPlanItemId);
    // Get button element
    const btn = event.target.closest('button');

    // Check if already logged (button is disabled)
    if (btn.disabled) {
        showNotification('info', 'This meal has already been logged');
        return;
    }

    // If nutrition data not provided as parameters, try to extract from card
    if (typeof recipeName === 'undefined' || recipeName === null || typeof calories === 'undefined') {
        const mealCard = btn.closest('.meal-card') || btn.closest('.meal-item');

        if (!mealCard) {
            // Last resort: try to get from modal if open
            const modal = document.querySelector('.modal.show');
            if (modal) {
                recipeName = modal.querySelector('.modal-title')?.textContent?.replace(/[🍽️🌅🌞🌙🍎]/g, '').trim() || 'Meal';
                // For modal, we need to fetch recipe details
                console.log('Logging from modal - nutrition data should be passed as parameters');
            } else {
                showNotification('error', 'Could not find meal information. Please try again.');
                return;
            }
        } else {
            recipeName = mealCard.querySelector('.meal-name')?.textContent ||
                mealCard.querySelector('h6')?.textContent || 'Meal';

            // Extract nutrition from the card
            const inlineNutrition = mealCard.querySelectorAll('.meal-nutrition-inline span');
            if (inlineNutrition.length >= 4) {
                calories = parseInt(inlineNutrition[0]?.textContent.match(/\d+/)?.[0]) || 0;
                protein = parseInt(inlineNutrition[1]?.textContent.match(/\d+/)?.[0]) || 0;
                carbs = parseInt(inlineNutrition[2]?.textContent.match(/\d+/)?.[0]) || 0;
                fats = parseInt(inlineNutrition[3]?.textContent.match(/\d+/)?.[0]) || 0;
            } else {
                const nutritionSpans = mealCard.querySelectorAll('.meal-nutrition span');
                calories = parseInt(nutritionSpans[0]?.textContent.match(/\d+/)?.[0]) || 0;
                protein = parseInt(nutritionSpans[1]?.textContent.match(/\d+/)?.[0]) || 0;
                carbs = parseInt(nutritionSpans[2]?.textContent.match(/\d+/)?.[0]) || 0;
                fats = parseInt(nutritionSpans[3]?.textContent.match(/\d+/)?.[0]) || 0;
            }
        }
    }

    // Ensure we have valid nutrition data
    calories = parseInt(calories) || 0;
    protein = parseInt(protein) || 0;
    carbs = parseInt(carbs) || 0;
    fats = parseInt(fats) || 0;

    // Validate we have at least calories
    if (calories === 0) {
        showNotification('error', 'Could not determine meal nutrition information');
        return;
    }

    // Log the meal
    fetch('api/log_meal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            recipe_id: recipeId,
            meal_type: mealType,
            calories: calories,
            protein: protein,
            carbs: carbs,
            fats: fats,
            log_date: new Date().toISOString().split('T')[0],
            meal_plan_item_id: mealPlanItemId
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Deduct ingredients from inventory
                console.log('Triggering inventory deduction for Recipe ID:', recipeId);
                deductIngredientsFromInventory(recipeId);

                showNotification('success', `${recipeName} logged! +${calories} cal added to today's total`);

                // Mark as completed - update ALL buttons for this meal
                markMealAsLogged(recipeId, mealType, mealPlanItemId);

                // Update nutrition stats immediately with animation
                updateNutritionStatsWithAnimation(calories, protein, carbs, fats);

                // Also reload from server to ensure accuracy
                setTimeout(() => loadTodayNutrition(), 500);
                
                // Reload meal plan to get updated eaten status from database
                setTimeout(() => loadTodayMealPlan(), 1000);
            } else {
                showNotification('error', 'Failed to log meal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error logging meal');
        });
}

function deductIngredientsFromInventory(recipeId) {
    console.log('deductIngredientsFromInventory starting for Recipe ID:', recipeId);
    fetch('api/deduct_ingredients_from_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ recipe_id: recipeId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.deducted_items && data.deducted_items.length > 0) {
                console.log('Ingredients deducted:', data.deducted_items);
                // Show a subtle notification about inventory update
                const count = data.deducted_items.length;
                showNotification('info', `Inventory updated: ${count} ingredient${count > 1 ? 's' : ''} deducted.`);
            } else if (!data.success) {
                console.warn('Could not deduct ingredients:', data.message);
            }
        })
        .catch(error => {
            console.error('Error deducting ingredients:', error);
        });
}

// Show section
function showSection(section) {
    if (section === 'profile') {
        window.location.href = 'profile.html';
    } else if (section === 'recipes') {
        window.location.href = 'recipes.html';
    } else if (section === 'meals') {
        // Scroll to today's meals section
        document.getElementById('todayMeals')?.scrollIntoView({ behavior: 'smooth' });
    } else if (section === 'shopping') {
        window.location.href = 'inventory.html';
    } else {
        showNotification('info', `${section.charAt(0).toUpperCase() + section.slice(1)} section coming soon!`);
    }
}

// Logout
function logout() {
    fetch('api/logout.php', {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        })
        .catch(error => {
            console.error('Error:', error);
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        });
}

// Show notification
function showNotification(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show notification-alert`;
    alertDiv.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}


// Show Meal Preferences Modal
window.showMealPreferencesModal = function () {
    const modalHtml = `
        <div class="modal fade" id="mealPreferencesModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-utensils"></i> Customize Today's Meal Plan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="lead">Let's personalize your meal plan for today!</p>
                        
                        <form id="mealPreferencesForm">
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-leaf text-success"></i> Dietary Preference *
                                </label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="dietType" id="veg" value="vegetarian" checked>
                                        <label class="btn btn-outline-success w-100" for="veg">
                                            <i class="fas fa-seedling"></i> Vegetarian
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="dietType" id="nonveg" value="non-vegetarian">
                                        <label class="btn btn-outline-danger w-100" for="nonveg">
                                            <i class="fas fa-drumstick-bite"></i> Non-Vegetarian
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-globe text-primary"></i> Cuisine Type *
                                </label>
                                <select class="form-select" id="cuisineType" required>
                                    <option value="">Select cuisine...</option>
                                    <option value="Indian">🇮🇳 Indian</option>
                                    <option value="Chinese">🇨🇳 Chinese</option>
                                    <option value="Italian">🇮🇹 Italian</option>
                                    <option value="Mexican">🇲🇽 Mexican</option>
                                    <option value="Thai">🇹🇭 Thai</option>
                                    <option value="Japanese">🇯🇵 Japanese</option>
                                    <option value="Mediterranean">🌊 Mediterranean</option>
                                    <option value="American">🇺🇸 American</option>
                                    <option value="Continental">🍽️ Continental</option>
                                    <option value="Mixed">🌍 Mixed (Variety)</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-pepper-hot text-danger"></i> Spice Level *
                                </label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="spiceLevel" id="mild" value="mild" checked>
                                        <label class="btn btn-outline-info w-100" for="mild">
                                            <i class="fas fa-smile"></i> Mild
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="spiceLevel" id="medium" value="medium">
                                        <label class="btn btn-outline-warning w-100" for="medium">
                                            <i class="fas fa-fire"></i> Medium
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="spiceLevel" id="spicy" value="spicy">
                                        <label class="btn btn-outline-danger w-100" for="spicy">
                                            <i class="fas fa-fire-alt"></i> Spicy
                                        </label>
                                    </div>
                                </div>
                            </div>



                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-list text-info"></i> Include Meal Types *
                                </label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="checkbox" class="btn-check" id="mainCourse" value="main-course" checked>
                                        <label class="btn btn-outline-success w-100" for="mainCourse">
                                            <i class="fas fa-utensils"></i> Main Course
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="checkbox" class="btn-check" id="starter" value="starter">
                                        <label class="btn btn-outline-info w-100" for="starter">
                                            <i class="fas fa-bowl-food"></i> Starter
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="checkbox" class="btn-check" id="dessert" value="dessert">
                                        <label class="btn btn-outline-warning w-100" for="dessert">
                                            <i class="fas fa-ice-cream"></i> Dessert
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>What happens next:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Generate personalized meal plan for today</li>
                                    <li>Check inventory for ingredients</li>
                                    <li>Add missing items to cart automatically</li>
                                </ul>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="generateMealPlanFromDashboard()">
                            <i class="fas fa-magic"></i> Generate Meal Plan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('mealPreferencesModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('mealPreferencesModal'));
    modal.show();
}

window.generateMealPlanFromDashboard = function () {
    const dietType = document.querySelector('input[name="dietType"]:checked').value;
    const cuisineType = document.getElementById('cuisineType').value;
    const spiceLevel = document.querySelector('input[name="spiceLevel"]:checked').value;

    const mealTypes = [];
    if (document.getElementById('mainCourse').checked) mealTypes.push('main-course');
    if (document.getElementById('starter').checked) mealTypes.push('starter');
    if (document.getElementById('dessert').checked) mealTypes.push('dessert');

    if (!cuisineType) {
        alert('Please select a cuisine type');
        return;
    }
    if (mealTypes.length === 0) {
        alert('Please select at least one meal type');
        return;
    }

    const modal = bootstrap.Modal.getInstance(document.getElementById('mealPreferencesModal'));
    modal.hide();

    // Show loading modal
    showLoadingModal('Generating your personalized meal plan...', 'This may take 30-60 seconds as we create custom recipes for you.');

    fetch('api/generate_ai_meal_plan_with_preferences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            days: 1,  // Generate only 1 day meal plan
            preferences: {
                diet_type: dietType,
                cuisine: cuisineType,
                spice_level: spiceLevel,
                spice_level: spiceLevel,
                meal_types: mealTypes
            }
        })
    })
        .then(res => res.json())
        .then(data => {
            hideLoadingModal();
            if (data.success) {
                showMealPlanResults(data);
                loadTodayMealPlan(); // Reload the meal plan
            } else {
                console.error('API Error:', data);
                showNotification('error', data.message || 'Failed to generate meal plan');
                if (data.debug) {
                    console.error('Debug Info:', data.debug);
                }
            }
        })
        .catch(error => {
            hideLoadingModal();
            console.error('Error:', error);
            showNotification('error', 'Error generating meal plan. Please try again.');
        });
}

// Show meal plan results modal (same as profile.js)
function showMealPlanResults(data) {
    const existingModal = document.getElementById('resultsModal');
    if (existingModal) {
        existingModal.remove();
    }

    const mealPlan = data.meal_plan || {};
    const meals = mealPlan.meals || [];
    const days = mealPlan.days || 1;
    const cartItemsAdded = data.cart_items_added || 0;
    const cartSummary = data.cart_summary || { total_price: 0, total_items: 0 };
    const missingIngredients = data.missing_ingredients || [];

    const resultsHtml = `
        <div class="modal fade" id="resultsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-check-circle"></i> Meal Plan Generated Successfully!
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <strong>Great news!</strong> Your personalized meal plan for today is ready!
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-primary">${meals.length}</h3>
                                        <p class="mb-0">Meals Planned</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-warning">${cartItemsAdded}</h3>
                                        <p class="mb-0">Items Added to Cart</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-success">₹${parseFloat(cartSummary.total_price || 0).toFixed(2)}</h3>
                                        <p class="mb-0">Cart Total</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${missingIngredients.length > 0 ? `
                            <h6 class="text-danger"><i class="fas fa-shopping-cart"></i> Missing Ingredients Added to Cart:</h6>
                            <div class="list-group mb-4" style="max-height: 150px; overflow-y: auto;">
                                ${missingIngredients.map(ing => `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>${ing.ingredient_name}</span>
                                        <span class="badge bg-danger">${ing.quantity} ${ing.unit} - ₹${ing.price.toFixed(2)}</span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle"></i> You have almost all ingredients in your inventory!
                            </div>
                        `}

                        ${data.used_ingredients && data.used_ingredients.length > 0 ? `
                            <h6 class="text-success"><i class="fas fa-check-circle"></i> Used Ingredients (From Inventory):</h6>
                            <div class="list-group mb-3" style="max-height: 150px; overflow-y: auto;">
                                ${data.used_ingredients.map(ing => `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>${ing.ingredient_name}</span>
                                        <span class="badge bg-success">${ing.quantity} ${ing.unit}</span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='dashboard.html'">
                            <i class="fas fa-utensils"></i> View Meals
                        </button>
                        <button type="button" class="btn btn-success" onclick="window.location.href='cart.html'">
                            <i class="fas fa-shopping-cart"></i> View Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', resultsHtml);
    const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
    modal.show();

    document.getElementById('resultsModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}


// Show loading modal
function showLoadingModal(title, message) {
    const loadingHtml = `
        <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="mb-2">${title}</h5>
                        <p class="text-muted mb-0">${message}</p>
                        <small class="text-muted d-block mt-3">
                            <i class="fas fa-magic"></i> AI is crafting your perfect meals...
                        </small>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('loadingModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', loadingHtml);
    const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
    modal.show();
}

// Hide loading modal
function hideLoadingModal() {
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        const modal = bootstrap.Modal.getInstance(loadingModal);
        if (modal) {
            modal.hide();
        }
        setTimeout(() => loadingModal.remove(), 300);
    }
}


// Show page loading overlay
function showPageLoading() {
    const loadingOverlay = `
        <div id="pageLoadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div class="text-center">
                <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="text-success">Loading Dashboard...</h5>
                <p class="text-muted">Preparing your personalized nutrition insights</p>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', loadingOverlay);
}

// Hide page loading overlay
function hidePageLoading() {
    const overlay = document.getElementById('pageLoadingOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s';
        setTimeout(() => overlay.remove(), 300);
    }
}


// Update nutrition stats with animation when meal is logged
function updateNutritionStatsWithAnimation(calories, protein, carbs, fats) {
    // Get current values
    const currentCalories = parseInt(document.getElementById('todayCalories').textContent) || 0;
    const currentProtein = parseInt(document.getElementById('todayProtein').textContent) || 0;
    const currentCarbs = parseInt(document.getElementById('todayCarbs').textContent) || 0;
    const currentFats = parseInt(document.getElementById('todayFats').textContent) || 0;

    // Calculate new values
    const newCalories = currentCalories + calories;
    const newProtein = currentProtein + protein;
    const newCarbs = currentCarbs + carbs;
    const newFats = currentFats + fats;

    // Animate the stat cards
    animateStatCard('todayCalories', currentCalories, newCalories);
    animateStatCard('todayProtein', currentProtein, newProtein, 'g');
    animateStatCard('todayCarbs', currentCarbs, newCarbs, 'g');
    animateStatCard('todayFats', currentFats, newFats, 'g');

    // Update progress bars
    const calorieGoal = parseInt(document.getElementById('caloriesTarget').textContent) || 2000;
    const proteinGoal = parseInt(document.getElementById('proteinTarget').textContent) || 150;
    const carbsGoal = parseInt(document.getElementById('carbsTarget').textContent) || 250;
    const fatsGoal = parseInt(document.getElementById('fatsTarget').textContent) || 65;

    updateProgressBar('calories', newCalories, calorieGoal);
    updateProgressBar('protein', newProtein, proteinGoal);
    updateProgressBar('carbs', newCarbs, carbsGoal);
    updateProgressBar('fats', newFats, fatsGoal);
}

// Animate a stat card value change
function animateStatCard(elementId, fromValue, toValue, suffix = '') {
    const element = document.getElementById(elementId);
    if (!element) return;

    const duration = 1000; // 1 second
    const steps = 30;
    const increment = (toValue - fromValue) / steps;
    const stepDuration = duration / steps;

    let currentStep = 0;

    // Add highlight animation
    element.parentElement.parentElement.classList.add('stat-card-highlight');

    const interval = setInterval(() => {
        currentStep++;
        const currentValue = Math.round(fromValue + (increment * currentStep));
        element.textContent = currentValue + suffix;

        if (currentStep >= steps) {
            clearInterval(interval);
            element.textContent = toValue + suffix;
            // Remove highlight after animation
            setTimeout(() => {
                element.parentElement.parentElement.classList.remove('stat-card-highlight');
            }, 500);
        }
    }, stepDuration);
}


// Mark all buttons for a specific meal as logged
function markMealAsLogged(recipeId, mealType, mealPlanItemId = null) {
    // Find all buttons for this meal and mark them as logged
    let selector = `button[onclick*="logMealComplete(${recipeId}, '${mealType}'"]`;
    if (mealPlanItemId) {
        selector = `button[onclick*="${mealPlanItemId})"]`;
    }

    const buttons = document.querySelectorAll(selector);
    buttons.forEach(btn => {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Logged';
        btn.classList.remove('btn-outline-success');
        btn.classList.add('btn-success');
        btn.disabled = true;
        btn.title = 'Already Eaten';
    });
}

// Handle ?cook=ID parameter from inventory
function handleCookParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const recipeId = urlParams.get('cook');

    if (recipeId) {
        console.log('Handle Cook Parameter for Recipe ID:', recipeId);
        // Clean URL to prevent re-triggering on refresh
        window.history.replaceState({}, document.title, window.location.pathname);

        // Fetch recipe details and show modal
        fetch(`api/recipes/get_recipe_details.php?id=${recipeId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showRecipeModal(data.recipe, 'snack', null, false);
                } else {
                    showNotification('error', 'Could not load recipe for preparation');
                }
            })
            .catch(err => {
                console.error('Error loading cook recipe:', err);
                showNotification('error', 'Error loading recipe');
            });
    }
}
