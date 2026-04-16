let currentProducts = [];
let cart = [];
let authMode = 'login'; // 'login' or 'signup'

// Fallback images since the database doesn't have them yet
const categoryImages = {
    "Womens Shoulder Bags": "https://images.unsplash.com/photo-1584916201218-f4242ceb4809?w=500&auto=format&fit=crop&q=60",
    "Womens Shoes": "https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=500&auto=format&fit=crop&q=60",
    "Mens Shoes": "https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=500&auto=format&fit=crop&q=60",
    "cloth": "https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=500&auto=format&fit=crop&q=60",
    "default": "https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=500&auto=format&fit=crop&q=60"
};

document.addEventListener("DOMContentLoaded", () => {
    checkAuthStatus();
    fetchCart();
    loadCategories();
    loadAllProducts();
});

// --- Authentication ---
async function checkAuthStatus() {
    const res = await fetch('customer_api.php?action=check_auth');
    const data = await res.json();
    
    if (data.customer_authenticated) {
        document.getElementById('nav-login').classList.add('hidden');
        document.getElementById('nav-signup').classList.add('hidden');
        document.getElementById('nav-logout').classList.remove('hidden');
        document.getElementById('nav-user-greet').classList.remove('hidden');
        document.getElementById('user-name-display').innerText = data.customer_name;
    } else {
        document.getElementById('nav-login').classList.remove('hidden');
        document.getElementById('nav-signup').classList.remove('hidden');
        document.getElementById('nav-logout').classList.add('hidden');
        document.getElementById('nav-user-greet').classList.add('hidden');
    }
}

function toggleAuthModal(mode) {
    const modal = document.getElementById('auth-modal');
    const nameInput = document.getElementById('auth-name');
    
    if (mode) {
        authMode = mode;
        document.getElementById('auth-title').innerText = mode === 'login' ? 'Login' : 'Sign Up';
        if (mode === 'signup') nameInput.classList.remove('hidden');
        else nameInput.classList.add('hidden');
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

async function submitAuth() {
    const name = document.getElementById('auth-name').value;
    const email = document.getElementById('auth-email').value;
    const password = document.getElementById('auth-password').value;

    const payload = { email, password, type: 'customer' };
    if (authMode === 'signup') payload.name = name;

    const res = await fetch(`customer_api.php?action=${authMode}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    
    const data = await res.json();
    alert(data.message);
    if (data.success) {
        toggleAuthModal();
        if (authMode === 'login') checkAuthStatus();
    }
}

async function logout() {
    await fetch('customer_api.php?action=logout');
    checkAuthStatus();
    alert("Logged out successfully.");
}

// --- Categories & Products ---
async function loadCategories() {
    const slider = document.getElementById('category-slider');
    slider.innerHTML = '<p>Loading categories...</p>';
    
    try {
        const res = await fetch('customer_api.php?action=categories');
        const categories = await res.json();
        
        slider.innerHTML = categories.map(cat => {
            // Priority: 1. DB Image 2. Hardcoded Match 3. Default Fallback Image
            const img = cat.image_url || categoryImages[cat.name] || categoryImages["default"];
            
            return `
                <div class="category-circle-wrapper" onclick="loadCategory('${cat.name}')">
                    <img src="${img}" class="category-circle" alt="${cat.name}">
                    <div class="category-name">${cat.name}</div>
                </div>
            `;
        }).join('');
    } catch (e) {
        slider.innerHTML = '<p>Error loading categories.</p>';
    }
}

async function loadAllProducts() {
    document.getElementById('view-title').innerText = "All Products";
    fetchAndDisplayProducts(`customer_api.php?action=products`);
}

async function loadCategory(categoryName) {
    document.getElementById('view-title').innerText = categoryName;
    fetchAndDisplayProducts(`customer_api.php?action=products&category=${encodeURIComponent(categoryName)}`);
}

async function handleSearch(event) {
    if (event.key === 'Enter') {
        const query = event.target.value;
        document.getElementById('view-title').innerText = `Search Results for "${query}"`;
        fetchAndDisplayProducts(`customer_api.php?action=products&search=${encodeURIComponent(query)}`);
    }
}

async function fetchAndDisplayProducts(url) {
    const grid = document.getElementById('product-grid');
    grid.innerHTML = '<p>Loading...</p>';
    try {
        const res = await fetch(url);
        currentProducts = await res.json();
        
        if (currentProducts.length === 0) {
            grid.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">No products found.</p>';
            return;
        }

        grid.innerHTML = currentProducts.map(p => `
            <div class="product-card">
                <img src="${p.image_url || 'https://via.placeholder.com/300'}" alt="${p.productName}">
                <div class="info-box">
                    <div>
                        <h3>${p.productName}</h3>
                        <p class="desc">${p.description ? p.description.substring(0, 60) + '...' : 'No description.'}</p>
                        <p class="price">$${p.price}</p>
                    </div>
                    ${p.stock > 0 
                        ? `<button class="add-to-cart-btn" onclick='addToCart(${JSON.stringify(p)})'>Add to Cart</button>`
                        : `<button class="add-to-cart-btn" style="background:#ccc; cursor:not-allowed;" disabled>Out of Stock</button>`}
                </div>
            </div>
        `).join('');
    } catch (e) {
        grid.innerHTML = '<p>Error loading products.</p>';
    }
}

// --- Cart System ---
async function fetchCart() {
    const res = await fetch('customer_api.php?action=cart');
    cart = await res.json();
    updateCartBadge();
}

async function addToCart(product) {
    const res = await fetch('customer_api.php?action=cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product })
    });
    const data = await res.json();
    if (data.success) {
        cart = data.cart;
        updateCartBadge();
        alert(`${product.productName} added to cart!`);
    }
}

async function removeFromCart(id) {
    const res = await fetch('customer_api.php?action=cart', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
        cart = data.cart;
        updateCartBadge();
        renderCartItems();
    }
}

function updateCartBadge() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    document.getElementById('cart-count').innerText = count;
}

function openCartModal() {
    renderCartItems();
    document.getElementById('cart-modal').classList.remove('hidden');
}

function closeCartModal() {
    document.getElementById('cart-modal').classList.add('hidden');
}

function renderCartItems() {
    const container = document.getElementById('cart-items');
    let total = 0;
    
    if (cart.length === 0) {
        container.innerHTML = '<p>Your cart is empty.</p>';
    } else {
        container.innerHTML = cart.map(item => {
            total += (item.price * item.quantity);
            return `
                <div class="cart-item">
                    <div>
                        <strong>${item.productName}</strong><br>
                        $${item.price} x ${item.quantity}
                    </div>
                    <button onclick="removeFromCart(${item.id})">Remove</button>
                </div>
            `;
        }).join('');
    }
    document.getElementById('cart-total').innerText = total.toFixed(2);
}

async function checkout() {
    if (cart.length === 0) return alert("Add items to cart first!");

    const res = await fetch('customer_api.php?action=checkout', { method: 'POST' });
    const data = await res.json();
    
    if (data.success) {
        alert(data.message + " ID: " + data.order_id);
        closeCartModal();
        fetchCart(); // refresh cart
        loadAllProducts(); // refresh stock
    } else {
        alert("Checkout failed. Are you logged in?");
    }
}