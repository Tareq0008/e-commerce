<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$uploadDir = 'uploads/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$inputData = json_decode(file_get_contents("php://input"), true);

// ==========================================
// 1. PUBLIC ROUTES (Auth, Cart, Browsing)
// ==========================================

// --- Authentication (Admin & Customer) ---
if ($action === 'login' && $method === 'POST') {
    $email = $inputData['email'] ?? '';
    $password = $inputData['password'] ?? '';
    $type = $inputData['type'] ?? 'customer'; // 'admin' or 'customer'

    if ($type === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            echo json_encode(["success" => true, "message" => "Admin Logged in"]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid admin credentials"]);
        }
    } 
    else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Customer'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['customer_id'] = $user['id'];
            $_SESSION['customer_name'] = $user['name'];
            echo json_encode(["success" => true, "message" => "Logged in successfully"]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid email or password"]);
        }
    }
    exit;
}

if ($action === 'signup' && $method === 'POST') {
    $name = $inputData['name'] ?? '';
    $email = $inputData['email'] ?? '';
    $password = $inputData['password'] ?? '';

    if ($name && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, account_status) VALUES (?, ?, ?, 'Customer', 'Active')");
        try {
            $stmt->execute([$name, $email, $hash]);
            echo json_encode(["success" => true, "message" => "Account created! Please log in."]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Email might already exist."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Missing fields."]);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

if ($action === 'check_auth' && $method === 'GET') {
    echo json_encode([
        "admin_authenticated" => isset($_SESSION['admin_id']),
        "customer_authenticated" => isset($_SESSION['customer_id']),
        "customer_name" => $_SESSION['customer_name'] ?? null
    ]);
    exit;
}

// --- Cart System (Session Based) ---
if ($action === 'cart') {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($method === 'GET') {
        echo json_encode(array_values($_SESSION['cart']));
        exit;
    }
    elseif ($method === 'POST') {
        $product = $inputData['product'] ?? null;
        if ($product) {
            $id = $product['id'];
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity'] += 1;
            } else {
                $product['quantity'] = 1;
                $_SESSION['cart'][$id] = $product;
            }
            echo json_encode(["success" => true, "cart" => array_values($_SESSION['cart'])]);
        }
        exit;
    }
    elseif ($method === 'DELETE') {
        $id = $inputData['id'] ?? null;
        if ($id && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        } else if (isset($inputData['clear'])) {
            $_SESSION['cart'] = [];
        }
        echo json_encode(["success" => true, "cart" => array_values($_SESSION['cart'])]);
        exit;
    }
}

// --- Browsing (Products & Categories) ---
if ($action === 'categories' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit; // Exits here! The admin check below won't block it.
}

if ($action === 'products' && $method === 'GET') {
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
    $categoryName = isset($_GET['category']) ? $_GET['category'] : null;

    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE (p.productName LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params = [$search, $search, $search];

    if ($categoryName) {
        $sql .= " AND c.name = ?";
        $params[] = $categoryName;
    }
    
    $sql .= " ORDER BY p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit; // Exits here! The admin check below won't block it.
}

// --- Checkout ---
if ($action === 'checkout' && $method === 'POST') {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(["success" => false, "message" => "Cart is empty."]);
        exit;
    }

    $customerName = $_SESSION['customer_name'] ?? $inputData['customer_name'] ?? 'Guest';
    $totalPrice = 0;
    $productNames = [];

    foreach ($_SESSION['cart'] as $item) {
        $productNames[] = $item['productName'] . " (x" . $item['quantity'] . ")";
        $totalPrice += ($item['price'] * $item['quantity']);
        
        $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $stmtStock->execute([$item['quantity'], $item['id'], $item['quantity']]);
    }

    $combinedProducts = implode(", ", $productNames);
    $status = 'Pending';
    $date = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO orders (customer_name, product_name, price, status, order_date) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$customerName, $combinedProducts, $totalPrice, $status, $date]);

    if ($success) {
        $_SESSION['cart'] = []; // Clear cart
        echo json_encode(["success" => true, "message" => "Order placed successfully!", "order_id" => $pdo->lastInsertId()]);
    } else {
        echo json_encode(["success" => false, "message" => "Checkout failed."]);
    }
    exit;
}

// ==========================================
// 2. ADMIN PROTECTED ROUTES
// ==========================================
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized admin access."]);
    exit;
}

if ($action === 'categories') {
    if ($method === 'POST') {
        $name = $inputData['name'] ?? '';
        $imageUrl = $inputData['image_url'] ?? '';
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, image_url) VALUES (?, ?)");
            $success = $stmt->execute([$name, $imageUrl]);
            echo json_encode(["message" => $success ? "Category created!" : "Failed.", "id" => $pdo->lastInsertId()]);
        }
    }
    else if ($method === 'PUT') {
        $id = $inputData['id'] ?? null;
        $name = $inputData['name'] ?? '';
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $success = $stmt->execute([$name, $id]);
            echo json_encode(["message" => $success ? "Category updated!" : "Failed."]);
        }
    }
    else if ($method === 'DELETE') {
        $id = $inputData['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $success = $stmt->execute([$id]);
            echo json_encode(["message" => $success ? "Category deleted!" : "Failed."]);
        }
    }
}

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

if ($action === 'orders') {
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
    }
    elseif ($method === 'PUT') {
        $id = $inputData['id'];
        $role = $inputData['role'];
        $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
        $success = $stmt->execute([$role, $id]);
        echo json_encode(["message" => $success ? "User role updated." : "Failed."]);
    }
}
?>