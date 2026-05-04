// Consistent Navbar Component for User Dashboard Pages
function loadNavbar(activePage = '') {
    const navbarHTML = `
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
            <div class="container-fluid px-4">
                <a class="navbar-brand fw-bold" href="dashboard.html">
                    <i class="fas fa-utensils text-success"></i> 
                    Smart Meal Planner
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link ${activePage === 'dashboard' ? 'active fw-bold' : ''}" href="dashboard.html">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${activePage === 'recipes' ? 'active fw-bold' : ''}" href="recipes.html">
                                <i class="fas fa-book"></i> Recipes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${activePage === 'inventory' ? 'active fw-bold' : ''}" href="inventory.html">
                                <i class="fas fa-boxes"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${activePage === 'cart' ? 'active fw-bold' : ''}" href="cart.html">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <span id="cartBadge" class="badge bg-danger rounded-pill ms-1" style="display: none;">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${activePage === 'orders' ? 'active fw-bold' : ''}" href="orders.html">
                                <i class="fas fa-shopping-bag"></i> Orders
                            </a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle ${activePage === 'profile' ? 'active' : ''}" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle fa-lg"></i>
                                <span id="userName" class="ms-1"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item ${activePage === 'profile' ? 'active' : ''}" href="profile.html">
                                        <i class="fas fa-user-cog text-primary"></i> My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" onclick="logoutUser(); return false;">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    `;
    
    // Insert navbar at the beginning of body
    document.body.insertAdjacentHTML('afterbegin', navbarHTML);
    
    // Load user name and cart count
    loadUserName();
    loadCartCount();
}

function loadUserName() {
    fetch('api/get_user_profile.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user) {
                const userName = document.getElementById('userName');
                if (userName) {
                    const name = data.user.name || data.user.email?.split('@')[0] || 'User';
                    userName.textContent = name;
                }
            }
        })
        .catch(err => console.log('User name error:', err));
}

function loadCartCount() {
    fetch('api/cart/get_cart.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.items && data.items.length > 0) {
                const badge = document.getElementById('cartBadge');
                if (badge) {
                    badge.textContent = data.items.length;
                    badge.style.display = 'inline';
                }
            }
        })
        .catch(err => console.log('Cart count error:', err));
}

function logoutUser() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('api/logout.php', { credentials: 'include' })
            .then(() => window.location.href = 'login.html')
            .catch(() => window.location.href = 'login.html');
    }
}
