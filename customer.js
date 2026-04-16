let currentCategoryProducts = [];
let selectedProduct = null;

function showCategories() {
    document.getElementById('category-view').classList.remove('hidden');
    document.getElementById('product-view').classList.add('hidden');
    document.getElementById('detail-view').classList.add('hidden');
}

function backToGrid() {
    document.getElementById('product-view').classList.remove('hidden');
    document.getElementById('detail-view').classList.add('hidden');
}

async function loadCategory(categoryName) {
    document.getElementById('category-view').classList.add('hidden');
    document.getElementById('product-view').classList.remove('hidden');
    document.getElementById('category-title').innerText = categoryName;
    
    const productGrid = document.getElementById('product-grid');
    productGrid.innerHTML = '<p>Loading items...</p>';

    try {
        const response = await fetch(`api.php?action=products&category=${encodeURIComponent(categoryName)}`);
        currentCategoryProducts = await response.json();
        
        productGrid.innerHTML = '';
        if (currentCategoryProducts.length === 0) {
            productGrid.innerHTML = '<p>No products available yet.</p>';
            return;
        }

        currentCategoryProducts.forEach(product => {
            productGrid.innerHTML += `
                <div class="product-card" onclick="viewProduct(${product.id})">
                    <img src="${product.image_url}" alt="${product.productName}">
                    <div class="info-box">
                        <p>${product.productName}</p>
                        <p class="price">$${product.price}</p>
                    </div>
                </div>
            `;
        });
    } catch (error) {
        productGrid.innerHTML = '<p>Error loading products.</p>';
    }
}

function viewProduct(id) {
    selectedProduct = currentCategoryProducts.find(p => p.id == id);
    if(!selectedProduct) return;

    document.getElementById('product-view').classList.add('hidden');
    document.getElementById('detail-view').classList.remove('hidden');

    const container = document.getElementById('detail-container');
    container.innerHTML = `
        <img src="${selectedProduct.image_url}">
        <div class="detail-info">
            <h2>${selectedProduct.productName}</h2>
            <p class="price">$${selectedProduct.price}</p>
            <p class="desc">${selectedProduct.description || 'No description available for this luxury item.'}</p>
            ${selectedProduct.stock > 0 
                ? `<button class="purchase-btn" onclick="openPaymentModal()">Buy Now</button>`
                : `<button class="purchase-btn" style="background:#ccc; cursor:not-allowed;" disabled>Out of Stock</button>`}
        </div>
    `;
}

// --- Modals ---
function toggleAuthModal(type) {
    const modal = document.getElementById('auth-modal');
    if(modal.classList.contains('hidden')) {
        document.getElementById('auth-title').innerText = type === 'login' ? 'Login' : 'Create Account';
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function openPaymentModal() {
    document.getElementById('payment-item-name').innerText = `Purchasing: ${selectedProduct.productName} ($${selectedProduct.price})`;
    document.getElementById('payment-modal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('payment-modal').classList.add('hidden');
}

async function confirmPurchase() {
    const custName = document.getElementById('cust-name').value || "Guest Customer";
    
    // Send order to API
    const response = await fetch('api.php?action=orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            customer_name: custName,
            product_name: selectedProduct.productName,
            price: selectedProduct.price
        })
    });
    
    const result = await response.json();
    alert(`Payment Successful! Your Order ID is #ORD-${result.order_id}`); 
    
    closePaymentModal();
    showCategories(); // return home
}
