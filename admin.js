// --- Navigation ---
function openTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// --- Global State ---
let allProducts = []; 
let editingProductId = null; 

// --- Init & Auth ---
document.addEventListener("DOMContentLoaded", () => {
    checkAuth();
});

async function checkAuth() {
    try {
        const res = await fetch('admin_api.php?action=check_auth');
        
        // This catches 404 or 500 server errors
        if (!res.ok) throw new Error("Server error");

        const data = await res.json();
        
        if (data.admin_authenticated) {
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('dashboard').classList.remove('hidden');
            initializeApp();
        } else {
            document.getElementById('login-screen').classList.remove('hidden');
            document.getElementById('dashboard').classList.add('hidden');
        }
    } catch (error) {
        console.error("API failed, defaulting to login screen:", error);
        // This ensures the login screen ALWAYS shows if the API crashes
        document.getElementById('login-screen').classList.remove('hidden');
        document.getElementById('dashboard').classList.add('hidden');
    }
}

async function login() { 
    const user = document.getElementById('username').value;
    const pass = document.getElementById('password').value;
    
    const res = await fetch('admin_api.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: user, password: pass }) // Updated payload
    });
    
    const data = await res.json();
    if (data.success) {
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('dashboard').classList.remove('hidden');
        initializeApp();
    } else {
        document.getElementById('login-error').style.display = 'block';
    }
}

async function logout() {
    // Using POST is best practice for state-changing actions like logout.
    // 'no-store' guarantees the browser won't use a cached version.
    await fetch('admin_api.php?action=logout', { 
        method: 'POST',
        cache: 'no-store' 
    });
    
    // Clear the password field for security/UX
    document.getElementById('password').value = '';
    document.getElementById('username').value = '';
        
    checkAuth();
}

function initializeApp() { 
    fetchCategories();
    fetchProducts();
    fetchOrders();
    fetchUsers();
}

// --- API Calls & Rendering ---

// 1. Categories
async function fetchCategories() {
    const res = await fetch('admin_api.php?action=categories');
    if (res.status === 401) return checkAuth();
    const categories = await res.json();
    
    let options = '<option value="">All Categories</option>';
    let modalOptions = '<option value="" disabled selected>Select a Category</option>';
    
    categories.forEach(c => {
        options += `<option value="${c.id}">${c.name}</option>`;
        modalOptions += `<option value="${c.id}">${c.name}</option>`;
    });
    
    // Check if elements exist before setting to avoid console errors
    const filterCat = document.getElementById('filterCategory');
    const filterOrderCat = document.getElementById('filterOrderCategory');
    const newProdCat = document.getElementById('newProductCategory');
    
    if(filterCat) filterCat.innerHTML = options;
    if(filterOrderCat) filterOrderCat.innerHTML = options;
    if(newProdCat) newProdCat.innerHTML = modalOptions;
}

// 2. Products
async function fetchProducts() {
    const searchInput = document.getElementById('searchProducts');
    const search = searchInput ? searchInput.value : '';
    const categoryInput = document.getElementById('filterCategory');
    const categoryId = categoryInput ? categoryInput.value : '';
    
    const response = await fetch(`admin_api.php?action=products&search=${encodeURIComponent(search)}&category_id=${categoryId}`);
    if (response.status === 401) return checkAuth();
    
    allProducts = await response.json();
    renderProducts();
    renderInventory();
} 

function renderProducts() {
    const tbody = document.getElementById('products-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    allProducts.forEach(p => {
        const imgSrc = p.image_url ? `<img src="${p.image_url}" class="product-thumb" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">` : 'No Image';
        tbody.innerHTML += `
        <tr>
            <td>${p.id}</td>
            <td>${imgSrc}</td>
            <td><strong>${p.productName}</strong><br><small>${p.category_name || 'Uncategorized'}</small></td>
            <td>$${p.price}</td>
            <td>
                <button class="action-btn edit-btn" onclick="editProduct(${p.id})">Edit</button>
                <button class="action-btn delete-btn" onclick="openDeleteProductModal(${p.id})">Delete</button>
            </td>
        </tr>`;
    });
}

// 3. Inventory
function renderInventory() {
    const tbody = document.getElementById('inventory-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    let hasLowStock = false;

    allProducts.forEach(p => {
        let statusBadge = `<span class="badge ok">In Stock</span>`;
        let rowClass = "";
        
        if (p.stock <= 5) {
            statusBadge = `<span class="badge warning">Low Stock</span>`;
            rowClass = "low-stock-row";
            hasLowStock = true;
        }

        tbody.innerHTML += `
        <tr class="${rowClass}">
            <td>${p.id}</td>
            <td>${p.productName}</td>
            <td>${p.stock}</td>
            <td>${statusBadge}</td>
        </tr>`;
    });

    const alertBox = document.getElementById('low-stock-alert');
    if(alertBox) {
        if (hasLowStock) alertBox.classList.remove('hidden');
        else alertBox.classList.add('hidden');
    }
}

// 4. Orders
async function fetchOrders() {
    const searchInput = document.getElementById('searchOrders');
    const search = searchInput ? searchInput.value : '';
    const categoryInput = document.getElementById('filterOrderCategory');
    const categoryId = categoryInput ? categoryInput.value : '';
    
    const response = await fetch(`admin_api.php?action=orders&search=${encodeURIComponent(search)}&category_id=${categoryId}`);
    if (response.status === 401) return checkAuth();
    
    const orders = await response.json();
    const tbody = document.getElementById('orders-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    orders.forEach(o => {
        tbody.innerHTML += `
        <tr>
            <td>#${o.id}</td>
            <td>${o.customer_name}</td>
            <td>${o.product_name}</td>
            <td>$${o.price}</td>
            <td>
                <select onchange="updateOrderStatus(${o.id}, this.value)" style="padding:5px;">
                    <option value="Pending" ${o.status === 'Pending' ? 'selected' : ''}>Pending</option>
                    <option value="Shipped" ${o.status === 'Shipped' ? 'selected' : ''}>Shipped</option>
                    <option value="Delivered" ${o.status === 'Delivered' ? 'selected' : ''}>Delivered</option>
                </select>
            </td>
        </tr>`;
    });
}

async function updateOrderStatus(id, status) {
    await fetch('admin_api.php?action=orders', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    });
}

// 5. Users
async function fetchUsers() {
    const searchInput = document.getElementById('searchUsers');
    const search = searchInput ? searchInput.value : '';
    const roleInput = document.getElementById('filterUserRole');
    const role = roleInput ? roleInput.value : '';
    
    const response = await fetch(`admin_api.php?action=users&search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}`);
    if (response.status === 401) return checkAuth();
    
    const users = await response.json();
    const tbody = document.getElementById('users-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    users.forEach(u => {
        tbody.innerHTML += `
        <tr>
            <td>U-${u.id}</td>
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>
                <select onchange="updateUserRole(${u.id}, this.value)" style="padding:5px;">
                    <option value="Customer" ${u.role === 'Customer' ? 'selected' : ''}>Customer</option>
                    <option value="Assistant" ${u.role === 'Assistant' ? 'selected' : ''}>Assistant</option>
                    <option value="Manager" ${u.role === 'Manager' ? 'selected' : ''}>Manager</option>
                </select>
            </td>
        </tr>`;
    });
}

async function updateUserRole(id, role) {
    await fetch('admin_api.php?action=users', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, role })
    });
}

// --- Modal & Product CRUD ---

function openModal() {
    document.getElementById('productModal').style.display = 'flex'; 
    document.getElementById('modalTitle').innerText = "Add New Product";
    document.getElementById('productId').value = '';
    document.getElementById('newProductName').value = '';
    document.getElementById('newProductPrice').value = '';
    document.getElementById('newProductStock').value = '';
    document.getElementById('newProductDesc').value = '';
    document.getElementById('newProductCategory').value = '';
    document.getElementById('newProductImage').value = '';
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
}

function editProduct(id) {
    const product = allProducts.find(p => p.id == id);
    if (!product) return;

    document.getElementById('modalTitle').innerText = "Edit Product";
    document.getElementById('productId').value = product.id;
    document.getElementById('newProductName').value = product.productName;
    document.getElementById('newProductPrice').value = product.price;
    document.getElementById('newProductStock').value = product.stock;
    document.getElementById('newProductDesc').value = product.description;
    document.getElementById('newProductCategory').value = product.category_id;
    
    document.getElementById('productModal').style.display = 'flex'; 
}
// Add these functions to your admin.js file

// Delete Product Modal Functions
function openDeleteProductModal(productId) {
    document.getElementById('deleteProductModal').style.display = 'flex';
    document.getElementById('deleteProductId').value = productId;
}

function closeDeleteProductModal() {
    document.getElementById('deleteProductModal').style.display = 'none';
    document.getElementById('deleteProductId').value = '';
}

async function executeDeleteProduct() {
    const id = document.getElementById('deleteProductId').value;
    
    if (!id) {
        console.error('No product ID found');
        return;
    }
    
    try {
        const response = await fetch('admin_api.php?action=products', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Close the modal
            closeDeleteProductModal();
            // Refresh the products list
            await fetchProducts();
        } else {
            alert(result.message || "Failed to delete product.");
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        alert("An error occurred while deleting the product.");
    }
}

async function saveProduct() {
    const formData = new FormData();
    const id = document.getElementById('productId').value;
    
    if (id) formData.append('id', id);
    formData.append('productName', document.getElementById('newProductName').value);
    formData.append('price', document.getElementById('newProductPrice').value);
    formData.append('stock', document.getElementById('newProductStock').value);
    formData.append('description', document.getElementById('newProductDesc').value);
    formData.append('category_id', document.getElementById('newProductCategory').value);
    
    const imageFile = document.getElementById('newProductImage').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    await fetch('admin_api.php?action=products', {
        method: 'POST',
        body: formData
    });

    closeModal();
    fetchProducts();
}



// Global variables
let removeCategoryImageFlag = false;

// Fetch categories for dropdowns
async function fetchCategories() {
    try {
        const res = await fetch('admin_api.php?action=categories');
        if (res.status === 401) return checkAuth();
        const categories = await res.json();
        
        let options = '<option value="">All Categories</option>';
        let modalOptions = '<option value="" disabled selected>Select a Category</option>';
        
        categories.forEach(c => {
            options += `<option value="${c.id}">${c.name}</option>`;
            modalOptions += `<option value="${c.id}">${c.name}</option>`;
        });
        
        const filterCat = document.getElementById('filterCategory');
        const filterOrderCat = document.getElementById('filterOrderCategory');
        const newProdCat = document.getElementById('newProductCategory');
        
        if(filterCat) filterCat.innerHTML = options;
        if(filterOrderCat) filterOrderCat.innerHTML = options;
        if(newProdCat) newProdCat.innerHTML = modalOptions;
        
        return categories;
    } catch (error) {
        console.error('Error fetching categories:', error);
        return [];
    }
}

// Render categories in manage modal
async function renderManageCategories() {
    try {
        const res = await fetch('admin_api.php?action=categories');
        if (res.status === 401) return checkAuth();
        const categories = await res.json();
        
        const listDiv = document.getElementById('categoriesList');
        listDiv.innerHTML = '';
        
        if (categories.length === 0) {
            listDiv.innerHTML = '<p style="padding: 15px; text-align: center; color: #777;">No categories found. Click "Add New Category" to create one.</p>';
            return;
        }
        
        categories.forEach(c => {
            const imageHtml = c.image_url && c.image_url !== '' 
                ? `<img src="${c.image_url}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;">` 
                : '<div style="width: 40px; height: 40px; background: #ddd; border-radius: 4px; margin-right: 10px; display: inline-block;"></div>';
            
            listDiv.innerHTML += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee;">
                <div style="display: flex; align-items: center;">
                    ${imageHtml}
                    <span style="font-weight: bold;">${escapeHtml(c.name)}</span>
                </div>
                <div>
                    <button class="action-btn edit-btn" style="padding: 5px 10px; font-size: 12px; margin: 0 5px;" onclick="openEditCategoryModal(${c.id}, '${escapeHtml(c.name).replace(/'/g, "\\'")}', '${c.image_url || ''}')">Edit</button>
                    <button class="action-btn delete-btn" style="padding: 5px 10px; font-size: 12px; margin: 0;" onclick="openDeleteCategoryModal(${c.id})">Delete</button>
                </div>
            </div>`;
        });
    } catch (error) {
        console.error('Error rendering categories:', error);
        document.getElementById('categoriesList').innerHTML = '<p style="padding: 15px; text-align: center; color: red;">Error loading categories</p>';
    }
}

function openAddCategoryModal() {
    document.getElementById('categoryActionModal').style.display = 'flex';
    document.getElementById('categoryActionTitle').innerText = 'Add New Category';
    document.getElementById('actionCategoryId').value = '';
    document.getElementById('categoryNameInput').value = '';
    document.getElementById('categoryImageInput').value = '';
    document.getElementById('currentCategoryImage').style.display = 'none';
    removeCategoryImageFlag = false;
}

function openEditCategoryModal(id, currentName, currentImageUrl) {
    document.getElementById('categoryActionModal').style.display = 'flex';
    document.getElementById('categoryActionTitle').innerText = 'Edit Category';
    document.getElementById('actionCategoryId').value = id;
    document.getElementById('categoryNameInput').value = currentName;
    document.getElementById('categoryImageInput').value = '';
    removeCategoryImageFlag = false;
    
    if (currentImageUrl && currentImageUrl !== '') {
        document.getElementById('currentCategoryImage').style.display = 'block';
        document.getElementById('categoryImagePreview').src = currentImageUrl;
    } else {
        document.getElementById('currentCategoryImage').style.display = 'none';
    }
}

function removeCategoryImage() {
    removeCategoryImageFlag = true;
    document.getElementById('currentCategoryImage').style.display = 'none';
    document.getElementById('categoryImagePreview').src = '';
}

function closeCategoryActionModal() {
    document.getElementById('categoryActionModal').style.display = 'none';
    removeCategoryImageFlag = false;
}

async function saveCategoryAction() {
    const id = document.getElementById('actionCategoryId').value;
    const name = document.getElementById('categoryNameInput').value.trim();
    const imageFile = document.getElementById('categoryImageInput').files[0];
    
    if (!name) {
        alert("Please enter a category name.");
        return;
    }
    
    // For new category, image is required
    if (!id && !imageFile) {
        alert("Please select an image for the category.");
        return;
    }
    
    const formData = new FormData();
    formData.append('name', name);
    
    if (id) {
        formData.append('id', id);
    }
    
    if (imageFile) {
        formData.append('category_image', imageFile);
    }
    
    try {
        const response = await fetch('admin_api.php?action=categories', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // REMOVED: alert(result.message);
            closeCategoryActionModal();
            await fetchCategories();
            await fetchProducts();
            await renderManageCategories();
            // Also refresh the manage categories modal if open
            const manageModal = document.getElementById('manageCategoriesModal');
            if (manageModal && manageModal.style.display === 'flex') {
                await renderManageCategories();
            }
        } else {
            alert(result.message || "Failed to save category.");
        }
    } catch (error) {
        console.error('Error saving category:', error);
        alert("An error occurred while saving the category.");
    }
}

function openDeleteCategoryModal(id) {
    document.getElementById('deleteCategoryModal').style.display = 'flex';
    document.getElementById('deleteCategoryId').value = id;
}

function closeDeleteCategoryModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
}

async function executeDeleteCategory() {
    const id = document.getElementById('deleteCategoryId').value;
    
    try {
        const res = await fetch('admin_api.php?action=categories', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        
        const result = await res.json();
        
        if (result.success) {
            // REMOVED: alert(result.message);
            closeDeleteCategoryModal();
            await fetchCategories();
            await fetchProducts();
            await renderManageCategories();
        } else {
            alert(result.message || "Failed to delete category.");
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        alert("An error occurred while deleting the category.");
    }
}

function openManageCategoriesModal() {
    document.getElementById('manageCategoriesModal').style.display = 'flex';
    renderManageCategories();
}

function closeManageCategoriesModal() {
    document.getElementById('manageCategoriesModal').style.display = 'none';
}

function openCategoryModal() {
    document.getElementById('categoryModal').style.display = 'flex';
    document.getElementById('newCategoryName').value = '';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

async function saveCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    
    if (!name) {
        alert("Please enter a category name.");
        return;
    }
    
    try {
        const response = await fetch('admin_api.php?action=categories', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeCategoryModal();
            await fetchCategories();
        } else {
            alert(result.message || "Failed to create category.");
        }
    } catch (error) {
        console.error('Error creating category:', error);
        alert("An error occurred.");
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}