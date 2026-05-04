// Orders Page JavaScript

document.addEventListener('DOMContentLoaded', function () {
    checkAuth();
    loadOrders();
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

function loadOrders() {
    fetch('api/get_orders.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayOrders(data.orders);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load orders');
        });
}

function displayOrders(orders) {
    const container = document.getElementById('ordersContainer');

    if (orders.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                <h4>No orders yet</h4>
                <p class="text-muted">Your order history will appear here</p>
                <a href="cart.html" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Go Shopping
                </a>
            </div>
        `;
        return;
    }

    container.innerHTML = orders.map(order => `
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Order #${order.order_number}</strong>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-calendar"></i> ${formatDate(order.order_date)}
                    </small>
                </div>
                <div class="text-end">
                    <h5 class="mb-0 text-success">₹${parseFloat(order.total_amount).toFixed(2)}</h5>
                    <span class="badge bg-${getStatusColor(order.status)}">${order.status.toUpperCase()}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h6>Items (${order.items_count || 0}):</h6>
                        <div id="items-${order.id}" class="mb-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="loadOrderItems(${order.id})">
                                <i class="fas fa-eye"></i> View Items
                            </button>
                        </div>
                        ${order.delivery_address ? `
                            <p class="mb-0">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                <strong>Delivery:</strong> ${order.delivery_address}
                            </p>
                        ` : ''}
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-1">
                            <strong>Payment:</strong> 
                            <span class="badge bg-${order.payment_status === 'paid' ? 'success' : 'warning'}">
                                ${order.payment_status}
                            </span>
                        </p>
                        ${order.payment_status === 'pending' ? `
                            <button class="btn btn-warning btn-sm mt-2" onclick="makePayment(${order.id}, ${order.total_amount}, '${order.order_number}')">
                                <i class="fas fa-mobile-alt"></i> Pay via UPI
                            </button>
                        ` : `
                            <p class="mb-0 mt-2">
                                <i class="fas fa-check-circle text-success"></i> 
                                <small class="text-success">Payment Completed</small>
                            </p>
                            <div class="mt-2 text-primary">
                                <i class="fas fa-truck"></i> 
                                <small><strong>Status:</strong> ${order.status.toUpperCase()}</small>
                            </div>
                        `}
                        ${order.delivery_date ? `
                            <p class="mb-0 mt-2">
                                <i class="fas fa-truck"></i> 
                                Delivered: ${formatDate(order.delivery_date)}
                            </p>
                        ` : ''}
                        ${order.payment_status === 'paid' ? `
                            <div class="mt-3">
                                <a href="api/orders/generate_invoice.php?order_id=${order.id}" class="btn btn-sm btn-outline-dark w-100" target="_blank">
                                    <i class="fas fa-file-invoice"></i> Download Invoice
                                </a>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function loadOrderItems(orderId) {
    const container = document.getElementById(`items - ${orderId} `);
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Loading...';

    fetch(`api / get_order_items.php ? order_id = ${orderId} `, {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = `
    < div class="list-group" >
        ${data.items.map(item => `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${item.ingredient_name}</span>
                            <span>
                                <strong>${item.quantity} ${item.unit}</strong> - 
                                <span class="text-success">₹${parseFloat(item.subtotal).toFixed(2)}</span>
                            </span>
                        </div>
                    `).join('')
                    }
                </div >
    `;
            } else {
                container.innerHTML = '<p class="text-danger">Failed to load items</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<p class="text-danger">Error loading items</p>';
        });
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'ordered': 'warning',
        'confirmed': 'info',
        'processing': 'info',
        'packed': 'info',
        'shipped': 'primary',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function showError(message) {
    document.getElementById('ordersContainer').innerHTML = `
    < div class="alert alert-danger" >
        <i class="fas fa-exclamation-circle"></i> ${message}
        </div >
    `;
}

function logout() {
    fetch('api/logout.php', { credentials: 'include' })
        .then(() => window.location.href = 'login.html');
}


function makePayment(orderId, amount, orderNumber) {
    showPaymentModal(orderId, amount, orderNumber);
}

function showPaymentModal(orderId, amount, orderNumber) {
    const modalHtml = `
    < div class="modal fade" id = "paymentModal" data - bs - backdrop="static" tabindex = "-1" >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-mobile-alt"></i> Complete Payment for Order #${orderNumber}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h3 class="text-success">Amount to Pay: ₹${amount}</h3>
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
                        <button class="btn btn-success btn-lg" onclick="processOrderPayment(${orderId}, 'UPI', ${amount})">
                            <i class="fas fa-check-circle"></i> Pay ₹${amount} via UPI
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
        </div >
    `;

    const existingModal = document.getElementById('paymentModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function processOrderPayment(orderId, method, amount) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
    const modalBody = document.querySelector('#paymentModal .modal-body');

    modalBody.innerHTML = `
    < div class="text-center py-5" >
            <div class="spinner-border text-success mb-3" style="width: 3rem; height: 3rem;"></div>
            <h5>Processing ${method} Payment...</h5>
            <p class="text-muted">Please wait while we verify your payment</p>
        </div >
    `;

    setTimeout(() => {
        fetch('api/update_order_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                order_id: orderId,
                payment_method: method,
                payment_status: 'paid'
            })
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status} `);
                }
                return res.json();
            })
            .then(data => {
                console.log('Payment response:', data);
                modal.hide();
                if (data.success) {
                    showPaymentSuccess(method, amount);
                    setTimeout(() => loadOrders(), 1500); // Reload orders after success modal
                } else {
                    showAlert('danger', `Payment failed: ${data.message || 'Unknown error'} `);
                }
            })
            .catch(error => {
                modal.hide();
                console.error('Payment error:', error);
                showAlert('danger', `Payment failed: ${error.message}. Please try again.`);
            });
    }, 2000);
}

function showPaymentSuccess(method, amount) {
    const modalHtml = `
    < div class="modal fade" id = "paymentSuccessModal" data - bs - backdrop="static" tabindex = "-1" >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle"></i> Payment Successful!
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4>Payment Completed!</h4>
                    <p class="text-muted">Your payment has been processed successfully</p>

                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <p class="mb-1"><strong>Amount Paid:</strong></p>
                            <h4 class="text-success">₹${amount}</h4>
                            <p class="mb-0 mt-2">
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> Paid via ${method}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i> Your order status has been updated
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="closeSuccessModal()">
                        <i class="fas fa-check"></i> OK
                    </button>
                </div>
            </div>
        </div>
        </div >
    `;

    const existingModal = document.getElementById('paymentSuccessModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));
    modal.show();
}

function closeSuccessModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentSuccessModal'));
    if (modal) modal.hide();
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert - ${type} alert - dismissible fade show`;
    alertDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
`;

    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}
