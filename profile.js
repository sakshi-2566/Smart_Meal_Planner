// Profile Page JavaScript
let currentUser = null;

document.addEventListener('DOMContentLoaded', function () {
    checkAuth();
    loadUserProfile();
    setupEventListeners();
});

function checkAuth() {
    fetch('api/get_user_profile.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                window.location.href = 'login.html';
            } else {
                currentUser = data.user;
                populateProfile(data.user);
            }
        })
        .catch(error => {
            console.error('Auth check failed:', error);
            window.location.href = 'login.html';
        });
}

function populateProfile(user) {
    // Sidebar
    document.getElementById('profileName').textContent = `${user.first_name} ${user.last_name}`;
    document.getElementById('profileEmail').textContent = user.email;

    // Calculate days since member
    const createdDate = new Date(user.created_at);
    const today = new Date();
    const daysMember = Math.floor((today - createdDate) / (1000 * 60 * 60 * 24));
    document.getElementById('memberSince').textContent = daysMember;

    // Personal Info Tab
    document.getElementById('firstName').value = user.first_name || '';
    document.getElementById('lastName').value = user.last_name || '';
    document.getElementById('email').value = user.email || '';

    // Health Tab
    document.getElementById('age').value = user.age || '';
    document.getElementById('gender').value = user.gender || '';
    document.getElementById('height').value = user.height || '';
    document.getElementById('weight').value = user.weight || '';
    document.getElementById('activityLevel').value = user.activity_level || 'moderate';
    document.getElementById('goal').value = user.goal || 'maintenance';

    // Show calculated metrics if available
    // Show calculated metrics if available, even if they are 0
    if (user.bmr !== null && user.bmr !== undefined) {
        showCalculatedMetrics({
            bmr: user.bmr,
            tdee: user.tdee,
            targetCalories: user.target_calories,
            targetProtein: user.target_protein,
            targetCarbs: user.target_carbs || 0,
            targetFats: user.target_fats || 0
        });
    }

    // Preferences Tab
    document.getElementById('dietaryPreference').value = user.dietary_preference || 'none';
}

function setupEventListeners() {
    // Personal Info Form
    document.getElementById('personalInfoForm').addEventListener('submit', function (e) {
        e.preventDefault();
        updatePersonalInfo();
    });

    // Health Form
    document.getElementById('healthForm').addEventListener('submit', function (e) {
        e.preventDefault();
        updateHealthInfo();
    });

    // Preferences Form
    document.getElementById('preferencesForm').addEventListener('submit', function (e) {
        e.preventDefault();
        updatePreferences();
    });


}

function updatePersonalInfo() {
    const data = {
        firstName: document.getElementById('firstName').value,
        lastName: document.getElementById('lastName').value
    };

    fetch('api/update_personal_info.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Personal information updated successfully!');

                // Update localStorage
                const user = JSON.parse(localStorage.getItem('user') || '{}');
                user.firstName = document.getElementById('firstName').value;
                user.lastName = document.getElementById('lastName').value;
                localStorage.setItem('user', JSON.stringify(user));

                loadUserProfile();
            } else {
                showAlert('danger', data.message || 'Failed to update');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred');
        });
}

function updateHealthInfo() {
    const data = {
        age: parseInt(document.getElementById('age').value),
        gender: document.getElementById('gender').value,
        height: parseFloat(document.getElementById('height').value),
        weight: parseFloat(document.getElementById('weight').value),
        activityLevel: document.getElementById('activityLevel').value,
        goal: document.getElementById('goal').value
    };

    fetch('api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Health information updated successfully!');

                if (data.calculations) {
                    showCalculatedMetrics(data.calculations);

                    // Update localStorage with goals if they are stored there
                    const user = JSON.parse(localStorage.getItem('user') || '{}');
                    user.goal = document.getElementById('goal').value;
                    localStorage.setItem('user', JSON.stringify(user));
                }


            } else {
                showAlert('danger', data.message || 'Failed to update');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred');
        });
}













function updatePreferences() {
    const data = {
        dietaryPreference: document.getElementById('dietaryPreference').value,
        allergies: document.getElementById('allergies').value
    };

    fetch('api/update_preferences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Preferences updated successfully!');

                // Update localStorage
                const user = JSON.parse(localStorage.getItem('user') || '{}');
                user.dietaryPreference = document.getElementById('dietaryPreference').value;
                localStorage.setItem('user', JSON.stringify(user));
            } else {
                showAlert('danger', data.message || 'Failed to update');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred');
        });
}



function showCalculatedMetrics(calculations) {
    document.getElementById('bmrValue').textContent = Math.round(calculations.bmr);
    document.getElementById('tdeeValue').textContent = Math.round(calculations.tdee);
    document.getElementById('targetCaloriesValue').textContent = Math.round(calculations.targetCalories);
    document.getElementById('targetProteinValue').textContent = Math.round(calculations.targetProtein) + 'g';
    document.getElementById('calculatedMetrics').style.display = 'block';
}

function loadUserProfile() {
    fetch('api/get_user_profile.php', {
        credentials: 'include'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                populateProfile(data.user);
            }
        });
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

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function logout() {
    fetch('api/logout.php', {
        credentials: 'include'
    })
        .then(() => {
            window.location.href = 'login.html';
        });
}
