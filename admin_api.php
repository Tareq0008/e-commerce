<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

// 1. ADMIN AUTHENTICATION
if ($action === 'login' && $method === 'POST') {
    // Fixed: Look for 'username' instead of 'email'
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

// 1. ADMIN AUTHENTICATION
if ($action === 'login' && $method === 'POST') {
    // Fixed: Look for 'username' instead of 'email'
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

// === ADD THIS NEW LOGOUT BLOCK ===
if ($action === 'logout' && $method === 'POST') {
    session_unset();     // Remove all session variables
    session_destroy();   // Destroy the session
    echo json_encode(["success" => true, "message" => "Logged out successfully"]);
    exit;
}
// =================================

// 2. SECURITY GUARD (Protects all code below this line)
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access. Please log in."]);
    exit;
}

// 3. ADMIN ENDPOINTS (Move your existing admin logic here)
if ($action === 'products') {
    // ... Copy your admin product logic here (GET all, POST, PUT, DELETE) ...
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
    } 
    elseif ($method === 'POST') {
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $name = $_POST['productName'];
        $price = $_POST['price'];
        $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $categoryId = $_POST['category_id']; 
        $imageUrl = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $imageUrl = $targetFilePath;
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
            echo json_encode(["message" => $success ? "Product updated!" : "Update failed."]);
        } else {
            if ($name && $price && $imageUrl) {
                $stmt = $pdo->prepare("INSERT INTO products (productName, price, stock, description, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$name, $price, $stock, $description, $categoryId, $imageUrl]);
                echo json_encode(["message" => $success ? "Product created!" : "Creation failed."]);
            } else {
                echo json_encode(["message" => "Please provide name, price, stock, category, and an image."]);
            }
        }
    }
    elseif ($method === 'DELETE') {
        if (!empty($inputData['id'])) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $success = $stmt->execute([$inputData['id']]);
            echo json_encode(["message" => $success ? "Product deleted." : "Deletion failed."]);
        }
    }
}

// if ($action === 'categories') {
//    if ($method === 'POST') {
//         $name = $inputData['name'] ?? '';
//         $imageUrl = $inputData['image_url'] ?? '';
//         if ($name) {
//             $stmt = $pdo->prepare("INSERT INTO categories (name, image_url) VALUES (?, ?)");
//             $success = $stmt->execute([$name, $imageUrl]);
//             echo json_encode(["message" => $success ? "Category created!" : "Failed.", "id" => $pdo->lastInsertId()]);
//         }
//     }
//     else if ($method === 'PUT') {
//         $id = $inputData['id'] ?? null;
//         $name = $inputData['name'] ?? '';
//         if ($id && $name) {
//             $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
//             $success = $stmt->execute([$name, $id]);
//             echo json_encode(["message" => $success ? "Category updated!" : "Failed."]);
//         }
//     }
//     else if ($method === 'DELETE') {
//         $id = $inputData['id'] ?? null;
//         if ($id) {
//             $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
//             $success = $stmt->execute([$id]);
//             echo json_encode(["message" => $success ? "Category deleted!" : "Failed."]);
//         }
//     }
// }
if ($action === 'categories') {
   // ADD THIS GET METHOD HANDLER FIRST:
   if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    else if ($method === 'POST') {
        $name = $inputData['name'] ?? '';
        $imageUrl = $inputData['image_url'] ?? '';
        if ($name) {
            // Check if category already exists
            $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $checkStmt->execute([$name]);
            if ($checkStmt->fetch()) {
                echo json_encode(["message" => "Category already exists!"]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, image_url) VALUES (?, ?)");
                $success = $stmt->execute([$name, $imageUrl]);
                echo json_encode(["message" => $success ? "Category created!" : "Failed.", "id" => $pdo->lastInsertId()]);
            }
        } else {
            echo json_encode(["message" => "Category name is required."]);
        }
    }
    else if ($method === 'PUT') {
        $id = $inputData['id'] ?? null;
        $name = $inputData['name'] ?? '';
        if ($id && $name) {
            // Check if another category already has this name
            $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            $checkStmt->execute([$name, $id]);
            if ($checkStmt->fetch()) {
                echo json_encode(["message" => "Category name already exists!"]);
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $success = $stmt->execute([$name, $id]);
                echo json_encode(["message" => $success ? "Category updated!" : "Failed."]);
            }
        } else {
            echo json_encode(["message" => "Category ID and name are required."]);
        }
    }
    else if ($method === 'DELETE') {
        $id = $inputData['id'] ?? null;
        if ($id) {
            // First, update products with this category to NULL (uncategorized)
            $updateStmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
            $updateStmt->execute([$id]);
            
            // Then delete the category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $success = $stmt->execute([$id]);
            echo json_encode(["message" => $success ? "Category deleted! Products moved to Uncategorized." : "Failed."]);
        } else {
            echo json_encode(["message" => "Category ID is required."]);
        }
    }
}
if ($action === 'users') {
    // ... Copy your admin users logic here ...
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
    }
    elseif ($method === 'PUT') {
        $id = $inputData['id'];
        $role = $inputData['role'];
        $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
        $success = $stmt->execute([$role, $id]);
        echo json_encode(["message" => $success ? "User role updated." : "Failed."]);
    }
}

if ($action === 'orders') {
    // ... Copy your admin orders logic here ...
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? $_GET['category_id'] : null;

        $sql = "SELECT o.*, p.category_id 
                FROM orders o 
                LEFT JOIN products p ON o.product_name = p.productName 
                WHERE (o.customer_name LIKE ? OR o.product_name LIKE ? OR o.id LIKE ?)";
        $params = [$search, $search, $search];

        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY o.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        $customerName = $inputData['customer_name'] ?? 'Guest';
        $productName = $inputData['product_name'] ?? '';
        $price = $inputData['price'] ?? 0;
        
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, product_name, price) VALUES (?, ?, ?)");
        $success = $stmt->execute([$customerName, $productName, $price]);
        
        $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - 1 WHERE productName = ? AND stock > 0");
        $stmtStock->execute([$productName]);

        echo json_encode(["message" => $success ? "Order placed successfully!" : "Order failed.", "order_id" => $pdo->lastInsertId()]);
    }
    elseif ($method === 'PUT') {
        $id = $inputData['id'];
        $status = $inputData['status'];
        $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $success = $stmt->execute([$status, $id]);
        echo json_encode(["message" => $success ? "Order updated." : "Failed."]);
    }
}
?>