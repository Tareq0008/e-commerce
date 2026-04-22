let currentProducts = [];
let cart = [];
let authMode = 'login'; // 'login' or 'signup'
let currentProductForPurchase = null; // Store product for buy now

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
        if (authMode === 'signup') {
            // Auto login after signup
            toggleAuthModal('login');
        }
    }
}

async function logout() {
    await fetch('customer_api.php?action=logout');
    checkAuthStatus();
    cart = [];
    updateCartBadge();
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
                <img src="${p.image_url || 'https://via.placeholder.com/300'}" alt="${p.productName}" onclick='showProductDetail(${JSON.stringify(p).replace(/'/g, "&#39;")})'>
                <div class="info-box">
                    <div>
                        <h3 onclick='showProductDetail(${JSON.stringify(p).replace(/'/g, "&#39;")})' style="cursor: pointer;">${p.productName}</h3>
                        <p class="desc">${p.description ? p.description.substring(0, 60) + '...' : 'No description.'}</p>
                        <p class="price">$${p.price}</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        ${p.stock > 0 
                            ? `<button class="add-to-cart-btn" onclick='addToCart(${JSON.stringify(p).replace(/'/g, "&#39;")})' style="flex: 1;">Add to Cart</button>
                               <button class="add-to-cart-btn" onclick='buyNow(${JSON.stringify(p).replace(/'/g, "&#39;")})' style="flex: 1; background: #28a745;">Buy Now</button>`
                            : `<button class="add-to-cart-btn" style="background:#ccc; cursor:not-allowed; width:100%;" disabled>Out of Stock</button>`}
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        grid.innerHTML = '<p>Error loading products.</p>';
    }
}

// --- Product Detail Modal ---
function showProductDetail(product) {
    const modal = document.getElementById('product-detail-modal');
    const content = document.getElementById('product-detail-content');
    
    content.innerHTML = `
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <img src="${product.image_url || 'https://via.placeholder.com/400'}" alt="${product.productName}" style="width: 100%; border-radius: 8px;">
            </div>
            <div style="flex: 1;">
                <h2 style="margin-bottom: 15px;">${product.productName}</h2>
                <p style="color: #666; margin-bottom: 15px; line-height: 1.6;">${product.description || 'No description available.'}</p>
                <p style="font-size: 28px; color: #333; margin-bottom: 15px;">$${product.price}</p>
                <p style="margin-bottom: 20px;">Stock: <strong>${product.stock > 0 ? product.stock + ' units' : 'Out of Stock'}</strong></p>
                ${product.stock > 0 
                    ? `<div style="display: flex; gap: 15px;">
                        <button onclick='addToCartAndCloseDetail(${JSON.stringify(product).replace(/'/g, "&#39;")})' style="flex: 1; background: #000; color: #fff; padding: 12px; border: none; border-radius: 4px; cursor: pointer;">Add to Cart</button>
                        <button onclick='buyNowAndCloseDetail(${JSON.stringify(product).replace(/'/g, "&#39;")})' style="flex: 1; background: #28a745; color: #fff; padding: 12px; border: none; border-radius: 4px; cursor: pointer;">Buy Now</button>
                    </div>`
                    : `<button style="width: 100%; background: #ccc; color: #666; padding: 12px; border: none; border-radius: 4px;" disabled>Out of Stock</button>`
                }
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
}

function closeProductDetailModal() {
    document.getElementById('product-detail-modal').classList.add('hidden');
}

function addToCartAndCloseDetail(product) {
    addToCart(product);
    closeProductDetailModal();
}

function buyNowAndCloseDetail(product) {
    closeProductDetailModal();
    buyNow(product);
}

// --- Buy Now with Payment ---
function buyNow(product) {
    // Check if user is logged in
    fetch('customer_api.php?action=check_auth')
        .then(res => res.json())
        .then(data => {
            if (!data.customer_authenticated) {
                alert('Please login first to complete your purchase!');
                toggleAuthModal('login');
                return;
            }
            
            currentProductForPurchase = product;
            showPaymentModal(product);
        });
}

function showPaymentModal(product) {
    const modal = document.getElementById('payment-modal');
    document.getElementById('payment-product-name').innerText = product.productName;
    document.getElementById('payment-amount').innerHTML = `Total Amount: $${product.price}`;
    
    // Clear previous input values
    document.getElementById('card-number').value = '';
    document.getElementById('expiry-date').value = '';
    document.getElementById('cvv').value = '';
    document.getElementById('cardholder-name').value = '';
    
    modal.classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('payment-modal').classList.add('hidden');
    currentProductForPurchase = null;
}

async function processPayment() {
    // Validate payment details
    const cardNumber = document.getElementById('card-number').value;
    const expiryDate = document.getElementById('expiry-date').value;
    const cvv = document.getElementById('cvv').value;
    const cardholderName = document.getElementById('cardholder-name').value;
    
    if (!cardNumber || !expiryDate || !cvv || !cardholderName) {
        alert('Please fill in all payment details');
        return;
    }
    
    // Show processing message
    const payButton = event.target;
    const originalText = payButton.innerText;
    payButton.innerText = 'Processing...';
    payButton.disabled = true;
    
    // Simulate payment processing
    setTimeout(async () => {
        try {
            // Process the purchase
            const res = await fetch('customer_api.php?action=buy_now', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    product: currentProductForPurchase,
                    payment_details: {
                        card_last_four: cardNumber.slice(-4),
                        cardholder_name: cardholderName
                    }
                })
            });
            
            const data = await res.json();
            
            if (data.success) {
                alert(`Payment Successful! 🎉\n\nOrder #${data.order_id}\nProduct: ${currentProductForPurchase.productName}\nAmount: $${currentProductForPurchase.price}\n\nThank you for your purchase!`);
                closePaymentModal();
                
                // Refresh products to update stock
                await loadAllProducts();
                
                // Refresh cart if needed
                await fetchCart();
            } else {
                alert('Purchase failed: ' + data.message);
            }
        } catch (error) {
            alert('An error occurred during purchase. Please try again.');
        } finally {
            payButton.innerText = originalText;
            payButton.disabled = false;
        }
    }, 1500);
}

// --- Cart System ---
async function fetchCart() {
    const res = await fetch('customer_api.php?action=cart');
    const data = await res.json();
    if (data.success) {
        cart = data.cart || [];
    } else {
        cart = [];
    }
    updateCartBadge();
}

async function addToCart(product) {
    // Check if user is logged in
    const authRes = await fetch('customer_api.php?action=check_auth');
    const authData = await authRes.json();
    
    if (!authData.customer_authenticated) {
        alert('Please login first to add items to cart!');
        toggleAuthModal('login');
        return;
    }
    
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
    } else {
        alert(data.message || 'Failed to add to cart');
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
    
    if (!cart || cart.length === 0) {
        container.innerHTML = '<p>Your cart is empty.</p>';
        document.getElementById('cart-total').innerText = '0.00';
        return;
    }
    
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
    document.getElementById('cart-total').innerText = total.toFixed(2);
}

async function checkout() {
    if (!cart || cart.length === 0) {
        alert("Add items to cart first!");
        return;
    }

    const res = await fetch('customer_api.php?action=checkout', { method: 'POST' });
    const data = await res.json();
    
    if (data.success) {
        alert(data.message + " ID: " + data.order_id);
        closeCartModal();
        await fetchCart();
        await loadAllProducts();
    } else {
        alert("Checkout failed. " + (data.message || "Please try again."));
    }
}