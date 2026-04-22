<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

// Upload directories
$uploadDir = __DIR__ . '/uploads/';
$categoryUploadDir = __DIR__ . '/uploads/categories/';

// Create directories if they don't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($categoryUploadDir)) {
    mkdir($categoryUploadDir, 0777, true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

// ============ ADMIN AUTHENTICATION ============
if ($action === 'login' && $method === 'POST') {
    $username = $inputData['username'] ?? '';
    $password = $inputData['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        echo json_encode(["success" => true, "admin_authenticated" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    }
    exit;
}

if ($action === 'check_auth') {
    echo json_encode(["admin_authenticated" => isset($_SESSION['admin_id'])]);
    exit;
}

if ($action === 'logout' && $method === 'POST') {
    session_unset();
    session_destroy();
    echo json_encode(["success" => true, "message" => "Logged out successfully"]);
    exit;
}

// Check authentication for all other endpoints
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// ============ CATEGORIES ============
if ($action === 'categories') {
    // GET categories
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($categories);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }
    
    // POST - Create or update category
    if ($method === 'POST') {
        $name = $_POST['name'] ?? '';
        $id = $_POST['id'] ?? null;
        
        if (empty($name)) {
            echo json_encode(["success" => false, "message" => "Category name is required"]);
            exit;
        }
        
        // Handle image upload
        $imageUrl = null;
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
            $fileName = 'cat_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $targetPath = $categoryUploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetPath)) {
                $imageUrl = 'uploads/categories/' . $fileName;
            }
        }
        
        if ($id) {
            // Update existing category
            if ($imageUrl) {
                // Delete old image
                $stmt = $pdo->prepare("SELECT image_url FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old && $old['image_url'] && file_exists(__DIR__ . '/' . $old['image_url'])) {
                    unlink(__DIR__ . '/' . $old['image_url']);
                }
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, image_url = ? WHERE id = ?");
                $success = $stmt->execute([$name, $imageUrl, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $success = $stmt->execute([$name, $id]);
            }
            echo json_encode(["success" => $success, "message" => $success ? "Category updated" : "Update failed"]);
        } else {
            // Create new category
            if (!$imageUrl) {
                echo json_encode(["success" => false, "message" => "Image is required for new category"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO categories (name, image_url) VALUES (?, ?)");
            $success = $stmt->execute([$name, $imageUrl]);
            echo json_encode(["success" => $success, "message" => $success ? "Category created" : "Creation failed", "id" => $pdo->lastInsertId()]);
        }
        exit;
    }
    
    // DELETE category
    if ($method === 'DELETE') {
        $id = $inputData['id'] ?? null;
        if ($id) {
            // Get and delete image
            $stmt = $pdo->prepare("SELECT image_url FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat && $cat['image_url'] && file_exists(__DIR__ . '/' . $cat['image_url'])) {
                unlink(__DIR__ . '/' . $cat['image_url']);
            }
            
            // Update products
            $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
            $stmt->execute([$id]);
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $success = $stmt->execute([$id]);
            echo json_encode(["success" => $success, "message" => $success ? "Category deleted" : "Delete failed"]);
        } else {
            echo json_encode(["success" => false, "message" => "Category ID required"]);
        }
        exit;
    }
}

// ============ PRODUCTS ============
if ($action === 'products') {
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? $_GET['category_id'] : null;

        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE (p.productName LIKE ? OR p.description LIKE ?)";
        $params = [$search, $search];

        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY p.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    
    if ($method === 'POST') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['productName'] ?? '';
        $price = $_POST['price'] ?? 0;
        $stock = $_POST['stock'] ?? 0;
        $description = $_POST['description'] ?? '';
        $categoryId = $_POST['category_id'] ?? null;
        $imageUrl = null;

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imageUrl = 'uploads/' . $fileName;
            }
        }

        if ($id) {
            if ($imageUrl) {
                $stmt = $pdo->prepare("UPDATE products SET productName=?, price=?, stock=?, description=?, category_id=?, image_url=? WHERE id=?");
                $success = $stmt->execute([$name, $price, $stock, $description, $categoryId, $imageUrl, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE products SET productName=?, price=?, stock=?, description=?, category_id=? WHERE id=?");
                $success = $stmt->execute([$name, $price, $stock, $description, $categoryId, $id]);
            }
            echo json_encode(["success" => $success, "message" => $success ? "Product updated" : "Update failed"]);
        } else {
            if (!$imageUrl) {
                echo json_encode(["success" => false, "message" => "Image is required"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO products (productName, price, stock, description, category_id, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $success = $stmt->execute([$name, $price, $stock, $description, $categoryId, $imageUrl]);
            echo json_encode(["success" => $success, "message" => $success ? "Product created" : "Creation failed"]);
        }
        exit;
    }
    
    if ($method === 'DELETE') {
        $id = $inputData['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $success = $stmt->execute([$id]);
            echo json_encode(["success" => $success, "message" => $success ? "Product deleted" : "Delete failed"]);
        }
        exit;
    }
}

// ============ ORDERS ============
if ($action === 'orders') {
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_name LIKE ? OR product_name LIKE ? ORDER BY id DESC");
        $stmt->execute([$search, $search]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    
    if ($method === 'PUT') {
        $id = $inputData['id'] ?? null;
        $status = $inputData['status'] ?? '';
        if ($id && $status) {
            $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
            $success = $stmt->execute([$status, $id]);
            echo json_encode(["success" => $success, "message" => $success ? "Order updated" : "Update failed"]);
        }
        exit;
    }
}

// ============ USERS ============
if ($action === 'users') {
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
        $role = isset($_GET['role']) && $_GET['role'] !== '' ? $_GET['role'] : null;
        
        $sql = "SELECT * FROM users WHERE (name LIKE ? OR email LIKE ?)";
        $params = [$search, $search];
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    
    if ($method === 'PUT') {
        $id = $inputData['id'] ?? null;
        $role = $inputData['role'] ?? '';
        if ($id && $role) {
            $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
            $success = $stmt->execute([$role, $id]);
            echo json_encode(["success" => $success, "message" => $success ? "User role updated" : "Update failed"]);
        }
        exit;
    }
}

echo json_encode(["success" => false, "message" => "Invalid action"]);
?>