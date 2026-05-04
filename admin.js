// Admin Panel JavaScript - Clean & Global Version
console.log("Admin Dashboard Script Loading...");

// Global function declarations for button handlers (avoids scope issues)
window.deleteOrder = function (orderId) {
    if (!confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
        return;
    }
    console.log("Delete Order triggered for ID:", orderId);

    fetch('api/admin/delete_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ order_id: orderId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Order deleted successfully');
                loadDeliveries();
                loadDashboardStats();
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error("Error in deleteOrder:", error);
            showNotification('error', 'Failed to delete order');
        });
};

window.updateOrderStatus = function (orderId, newStatus, selectElement) {
    if (selectElement) selectElement.disabled = true;
    console.log("Updating order", orderId, "to", newStatus);
    fetch('api/admin/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            order_id: orderId,
            status: newStatus
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Order status updated successfully');
                loadDeliveries();
                loadDashboardStats();
            } else {
                showNotification('error', data.message);
                if (selectElement) selectElement.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            showNotification('error', 'Failed to update order status');
            if (selectElement) selectElement.disabled = false;
        });
};

window.viewOrderItems = function (orderId) {
    fetch(`api/get_order_items.php?order_id=${orderId}`, {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const itemsList = data.items.map(item => `${item.ingredient_name}: ${item.quantity} ${item.unit}`).join('\n');
                alert(`Order Items:\n${itemsList}`);
            }
        });
};

window.deleteUser = function (userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    fetch('api/admin/delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ user_id: userId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                loadUsers();
                loadDashboardStats();
            } else {
                showNotification('error', data.message);
            }
        });
};

let currentAdmin = null;

// Initialization
document.addEventListener('DOMContentLoaded', function () {
    console.log("DOM Content Loaded - Initializing Admin Dashboard");
    checkAdminAuth();
    loadDashboardStats();
    loadUsers();
    loadRecipes();
    loadIngredients();
    loadDeliveries();
});

function checkAdminAuth() {
    fetch('api/get_user_profile.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.user.role !== 'admin') {
                alert('Admin access required');
                window.location.href = 'login.html';
                return;
            }
            currentAdmin = data.user;
            document.getElementById('adminName').textContent = currentAdmin.first_name || 'Admin';
        });
}

function loadDashboardStats() {
    fetch('api/admin/get_stats.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
                document.getElementById('totalRecipes').textContent = data.stats.total_recipes || 0;
                document.getElementById('totalMealPlans').textContent = data.stats.total_meal_plans || 0;
            }
        });
}

function loadUsers() {
    fetch('api/admin/get_users.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success) displayUsers(data.users);
        });
}

function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;
    tbody.innerHTML = users.map(user => `
    < tr >
            <td>${user.id}</td>
            <td>${user.first_name} ${user.last_name}</td>
            <td>${user.email}</td>
            <td><span class="badge ${user.role === 'admin' ? 'bg-danger' : 'bg-primary'}">${user.role}</span></td>
            <td><span class="badge ${user.is_active ? 'bg-success' : 'bg-secondary'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>
                ${user.role !== 'admin' ? `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})"><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr >
    `).join('');
}

function loadRecipes() {
    fetch('api/admin/get_recipes.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success) displayRecipes(data.recipes);
        });
}

function displayRecipes(recipes) {
    const tbody = document.getElementById('recipesTableBody');
    if (!tbody) return;
    tbody.innerHTML = recipes.map(recipe => `
    < tr >
            <td>${recipe.id}</td>
            <td><strong>${recipe.recipe_name}</strong></td>
            <td>${Math.round(recipe.calories || 0)}</td>
            <td>${Math.round(recipe.protein || 0)}g</td>
            <td>${Math.round(recipe.carbs || 0)}g</td>
            <td>${Math.round(recipe.fats || 0)}g</td>
            <td><span class="badge bg-success">${recipe.approval_status || 'approved'}</span></td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deleteRecipeAdmin(${recipe.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr >
    `).join('');
}

function loadIngredients() {
    fetch('api/admin/get_ingredients.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success) displayIngredients(data.ingredients);
        });
}

function displayIngredients(ingredients) {
    const tbody = document.getElementById('ingredientsTableBody');
    if (!tbody) return;
    tbody.innerHTML = ingredients.map(ing => `
    < tr >
            <td>${ing.id}</td>
            <td>${ing.ingredient_name}</td>
            <td>${ing.category}</td>
            <td>${ing.calories_per_100g}</td>
            <td>${ing.protein_per_100g}g</td>
            <td>${ing.carbs_per_100g}g</td>
            <td>${ing.fats_per_100g}g</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deleteIngredient(${ing.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr >
    `).join('');
}

function loadDeliveries() {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    fetch('api/admin/get_all_orders.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success) displayDeliveries(data.orders);
        });
}

function displayDeliveries(orders) {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;
    tbody.innerHTML = orders.map(order => `
        <tr>
            <td><strong>#${order.order_number}</strong></td>
            <td>${order.first_name} ${order.last_name}</td>
            <td>${new Date(order.order_date).toLocaleDateString()}</td>
            <td>₹${parseFloat(order.total_amount).toFixed(2)}</td>
            <td><span class="badge ${order.payment_status === 'paid' ? 'bg-success' : 'bg-warning'}">${order.payment_status}</span></td>
            <td>
                <select class="form-select form-select-sm" onchange="updateOrderStatus(${order.id}, this.value, this)" 
                    ${['delivered', 'cancelled'].includes(order.status) ? 'disabled' : ''}>
                    <option value="pending" ${order.status === 'pending' ? 'selected' : ''} 
                        ${['processing', 'shipped', 'delivered', 'cancelled'].includes(order.status) ? 'disabled' : ''}>Ordered</option>
                    <option value="processing" ${order.status === 'processing' ? 'selected' : ''} 
                        ${['shipped', 'delivered', 'cancelled'].includes(order.status) ? 'disabled' : ''}>Packed</option>
                    <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''} 
                        ${['delivered', 'cancelled'].includes(order.status) ? 'disabled' : ''}>Shipped</option>
                    <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''} 
                        ${order.status === 'delivered' ? 'disabled' : ''}>Delivered</option>
                    <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            </td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewOrderItems(${order.id})"><i class="fas fa-list"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteOrder(${order.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function showNotification(type, message) {
    const div = document.createElement('div');
    div.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    div.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 9999;';
    div.innerHTML = `${message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

// Global search (optional)
document.getElementById('orderSearch')?.addEventListener('input', function (e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('#ordersTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

function logout() {
    fetch('api/logout.php', { credentials: 'include' })
        .then(() => window.location.href = 'login.html');
}

window.deleteRecipeAdmin = function (id) {
    if (confirm('Delete recipe?')) {
        fetch('api/recipes/delete_recipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ recipe_id: id })
        }).then(() => loadRecipes());
    }
};

window.deleteIngredient = function (id) {
    if (confirm('Delete ingredient?')) {
        // Implementation for deleting ingredient
    }
};
