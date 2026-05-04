// Shopping Cart JavaScript
let cartItems = [];
const DELIVERY_FEE = 20.00; // ₹20 delivery fee
const FREE_DELIVERY_THRESHOLD = 200.00; // Free delivery over ₹200

document.addEventListener('DOMContentLoaded', function () {
    checkAuth();
    loadCart();
});

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

function loadCart() {
    fetch('api/cart/get_cart.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                cartItems = data.cart_items;
                displayCart(data.cart_items);
                updateSummary(data.total_items, data.total_price);
            } else {
                showError('Failed to load cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('An error occurred');
        });
}

function displayCart(items) {
    const container = document.getElementById('cartItems');

    if (items.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h5>Your cart is empty</h5>
                <p class="text-muted">Add ingredients from your meal plan or inventory</p>
                <button class="btn btn-primary" onclick="continueShopping()">
                    <i class="fas fa-plus"></i> Start Shopping
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(item => `
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-1">${item.ingredient_name}</h6>
                        <small class="text-muted">
                            <span class="badge bg-secondary">${item.category}</span>
                        </small>
                    </div>
                    <div class="col-md-2 text-center">
                        <strong>${item.quantity} ${item.unit}</strong>
                    </div>
                    <div class="col-md-2 text-center">
                        <strong class="text-success">₹${parseFloat(item.price).toFixed(2)}</strong>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function updateSummary(itemCount, subtotal) {
    document.getElementById('totalItems').textContent = itemCount;
    document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;

    const deliveryFee = subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : DELIVERY_FEE;
    document.getElementById('delivery').textContent = deliveryFee === 0 ? 'FREE' : `₹${deliveryFee.toFixed(2)}`;

    const total = subtotal + deliveryFee;
    document.getElementById('total').textContent = `₹${total.toFixed(2)}`;

    // Enable/disable checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    checkoutBtn.disabled = itemCount === 0;
}

function removeItem(cartId) {
    if (!confirm('Remove this item from cart?')) return;

    fetch('api/cart/remove_from_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ cart_id: cartId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Item removed from cart');
                loadCart();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Failed to remove item');
        });
}

function clearCart() {
    if (!confirm('Are you sure you want to clear your entire cart?')) return;

    fetch('api/cart/clear_cart.php', {
        method: 'POST',
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Cart cleared');
                loadCart();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Failed to clear cart');
        });
}

function fetchAddress() {
    const btn = document.querySelector('button[onclick="fetchAddress()"]');
    const textarea = document.getElementById('deliveryAddress');

    if (!navigator.geolocation) {
        showAlert('warning', 'Geolocation is not supported by your browser');
        return;
    }

    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Locating...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(position => {
        const { latitude, longitude } = position.coords;

        // Use Nominatim (OpenStreetMap) for reverse geocoding with English language preference
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&accept-language=en`)
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';
                btn.disabled = false;

                if (data.display_name) {
                    textarea.value = data.display_name;
                    showAlert('success', 'Address fetched successfully!');
                } else {
                    showAlert('warning', 'Could not determine address. Please enter manually.');
                }
            })
            .catch(err => {
                console.error('Geocoding error:', err);
                btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';
                btn.disabled = false;
                textarea.value = `Lat: ${latitude}, Lon: ${longitude}`; // Fallback
                showAlert('warning', 'Address lookup failed. Coordinates filled.');
            });

    }, error => {
        console.error('Geolocation error:', error);
        btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Use My Location';
        btn.disabled = false;

        switch (error.code) {
            case error.PERMISSION_DENIED:
                showAlert('danger', 'Location access denied. Please enable permission.');
                break;
            case error.POSITION_UNAVAILABLE:
                showAlert('danger', 'Location information is unavailable.');
                break;
            case error.TIMEOUT:
                showAlert('danger', 'Location request timed out.');
                break;
            default:
                showAlert('danger', 'An unknown error occurred.');
        }
    });
}

function checkout() {
    const deliveryAddress = document.getElementById('deliveryAddress').value.trim();

    if (!deliveryAddress) {
        showAlert('warning', 'Please enter a delivery address');
        document.getElementById('deliveryAddress').focus();
        return;
    }

    if (cartItems.length === 0) {
        showAlert('warning', 'Your cart is empty');
        return;
    }

    // Calculate total
    const subtotal = cartItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    const deliveryFee = subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : DELIVERY_FEE;
    const total = subtotal + deliveryFee;

    // Show payment modal
    showPaymentModal(total, deliveryAddress);
}

function showPaymentModal(amount, deliveryAddress) {
    const modalHtml = `
        <div class="modal fade" id="paymentModal" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-mobile-alt"></i> UPI Payment
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <h3 class="text-success">Amount to Pay: ₹${amount.toFixed(2)}</h3>
                        </div>
                        
                        <!-- UPI Payment Section -->
                        <div class="text-center mb-4">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/e/e1/UPI-Logo-vector.svg" 
                                 alt="UPI" style="height: 50px;" class="mb-3">
                            <p class="text-muted">Pay securely using UPI</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Enter UPI ID</label>
                            <input type="text" class="form-control" id="upiId" 
                                   placeholder="yourname@paytm / yourname@gpay" 
                                   value="demo@paytm">
                            <small class="text-muted">Use demo@paytm for testing</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-lg" onclick="processPayment('UPI', '${deliveryAddress}', ${amount})">
                                <i class="fas fa-check-circle"></i> Pay ₹${amount.toFixed(2)} via UPI
                            </button>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Demo Mode:</strong> This is a dummy payment gateway. 
                            No real money will be charged. Click the payment button to simulate successful payment.
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt text-success"></i> 
                                Secure payment powered by UPI
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('paymentModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function processPayment(method, deliveryAddress, amount) {
    // Show processing
    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
    const modalBody = document.querySelector('#paymentModal .modal-body');

    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success mb-3" style="width: 3rem; height: 3rem;"></div>
            <h5>Processing ${method} Payment...</h5>
            <p class="text-muted">Please wait while we verify your payment</p>
        </div>
    `;

    // Simulate payment processing delay
    setTimeout(() => {
        // Process the actual order
        fetch('api/cart/checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                delivery_address: deliveryAddress,
                payment_method: method,
                payment_status: 'paid'
            })
        })
            .then(res => res.json())
            .then(data => {
                modal.hide();
                if (data.success) {
                    showOrderSuccess(data.order, method);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                modal.hide();
                console.error('Error:', error);
                showAlert('danger', 'Payment failed. Please try again.');
            });
    }, 2000); // 2 second delay to simulate processing
}

function showOrderSuccess(order, paymentMethod = 'Online') {
    const modalHtml = `
        <div class="modal fade" id="orderSuccessModal" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-check-circle"></i> Payment Successful!
                        </h5>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>Thank you for your order!</h4>
                        <p class="text-muted">Your payment has been processed successfully</p>
                        
                        <div class="card bg-light mt-3">
                            <div class="card-body">
                                <p class="mb-1"><strong>Order Number:</strong></p>
                                <h5 class="text-primary">${order.order_number}</h5>
                                <p class="mb-1 mt-3"><strong>Amount Paid:</strong></p>
                                <h4 class="text-success">₹${order.total_amount}</h4>
                                <p class="mb-1"><small class="text-muted">${order.items_count} items</small></p>
                                <p class="mb-0 mt-2">
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Paid via ${paymentMethod}
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-truck"></i> Estimated delivery: 2-3 business days
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="goToDashboard()">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="viewOrders()">
                            <i class="fas fa-list"></i> View Orders
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('orderSuccessModal'));
    modal.show();

    // Clear cart display
    loadCart();
}

function continueShopping() {
    window.location.href = 'inventory.html';
}

function goToDashboard() {
    window.location.href = 'dashboard.html';
}

function viewOrders() {
    window.location.href = 'orders.html';
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

function showError(message) {
    document.getElementById('cartItems').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> ${message}
        </div>
    `;
}

function logout() {
    fetch('api/logout.php', { credentials: 'include' })
        .then(() => window.location.href = 'login.html');
}
