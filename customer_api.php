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

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 1. CUSTOMER AUTHENTICATION
if ($action === 'check_auth' && $method === 'GET') {
    if (isset($_SESSION['customer_id'])) {
        echo json_encode([
            "customer_authenticated" => true, 
            "customer_id" => $_SESSION['customer_id'],
            "customer_name" => $_SESSION['customer_name']
        ]);
    } else {
        echo json_encode(["customer_authenticated" => false]);
    }
    exit;
}

if ($action === 'login' && $method === 'POST') {
    $email = $inputData['email'] ?? '';
    $password = $inputData['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Customer' AND account_status = 'active'");
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
    exit;
}

if ($action === 'signup' && $method === 'POST') {
    $name = $inputData['name'] ?? '';
    $email = $inputData['email'] ?? '';
    $password = $inputData['password'] ?? '';

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email already exists"]);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, role, account_status, password_hash) VALUES (?, ?, 'Customer', 'active', ?)");
    
    if ($stmt->execute([$name, $email, $password_hash])) {
        echo json_encode(["success" => true, "message" => "Account created successfully! Please login."]);
    } else {
        echo json_encode(["success" => false, "message" => "Signup failed"]);
    }
    exit;
}

if ($action === 'logout' && $method === 'GET') {
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

// 2. Browsing (Products & Categories)

if ($action === 'categories' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
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
    exit;
}

// 3. Cart System
if ($action === 'cart' && $method === 'GET') {
    echo json_encode(["success" => true, "cart" => array_values($_SESSION['cart'])]);
    exit;
}

if ($action === 'cart' && $method === 'POST') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(["success" => false, "message" => "Please login first"]);
        exit;
    }
    
    $product = $inputData['product'] ?? null;
    if (!$product) {
        echo json_encode(["success" => false, "message" => "Invalid product"]);
        exit;
    }
    
    // Check stock
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product['id']]);
    $dbProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dbProduct || $dbProduct['stock'] <= 0) {
        echo json_encode(["success" => false, "message" => "Product is out of stock"]);
        exit;
    }
    
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $product['id']) {
            if ($item['quantity'] + 1 > $dbProduct['stock']) {
                echo json_encode(["success" => false, "message" => "Not enough stock"]);
                exit;
            }
            $item['quantity']++;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $product['quantity'] = 1;
        $_SESSION['cart'][] = $product;
    }
    
    echo json_encode(["success" => true, "cart" => array_values($_SESSION['cart'])]);
    exit;
}

if ($action === 'cart' && $method === 'DELETE') {
    $id = $inputData['id'] ?? null;
    if ($id !== null) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $id) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    echo json_encode(["success" => true, "cart" => array_values($_SESSION['cart'])]);
    exit;
}

// 4. Buy Now (Direct Purchase with Payment)
if ($action === 'buy_now' && $method === 'POST') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(["success" => false, "message" => "Please login first"]);
        exit;
    }
    
    $product = $inputData['product'] ?? null;
    $payment_details = $inputData['payment_details'] ?? [];
    
    if (!$product) {
        echo json_encode(["success" => false, "message" => "Invalid product"]);
        exit;
    }
    
    // Check stock again
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product['id']]);
    $dbProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dbProduct || $dbProduct['stock'] <= 0) {
        echo json_encode(["success" => false, "message" => "Product is out of stock"]);
        exit;
    }
    
    // Update stock
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = ? AND stock > 0");
    $stmt->execute([$product['id']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Failed to update stock"]);
        exit;
    }
    
    // Create order
    $customerName = $_SESSION['customer_name'];
    $productName = $product['productName'];
    $price = $product['price'];
    $status = 'Completed';
    $date = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO orders (customer_name, product_name, price, status, order_date) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$customerName, $productName, $price, $status, $date]);
    
    if ($success) {
        $orderId = $pdo->lastInsertId();
        echo json_encode([
            "success" => true, 
            "message" => "Order placed successfully!", 
            "order_id" => $orderId
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to create order"]);
    }
    exit;
}

// 5. Checkout (Cart Purchase)
if ($action === 'checkout' && $method === 'POST') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(["success" => false, "message" => "Please login first"]);
        exit;
    }
    
    if (empty($_SESSION['cart'])) {
        echo json_encode(["success" => false, "message" => "Cart is empty"]);
        exit;
    }

    $customerName = $_SESSION['customer_name'];
    $totalPrice = 0;
    $orderItems = [];
    $allSuccess = true;

    foreach ($_SESSION['cart'] as $item) {
        $orderItems[] = $item['productName'] . " (x" . $item['quantity'] . ")";
        $totalPrice += ($item['price'] * $item['quantity']);
        
        // Update stock
        $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $success = $stmtStock->execute([$item['quantity'], $item['id'], $item['quantity']]);
        
        if ($stmtStock->rowCount() === 0) {
            $allSuccess = false;
            break;
        }
    }

    if (!$allSuccess) {
        echo json_encode(["success" => false, "message" => "Some items are out of stock"]);
        exit;
    }

    $combinedProducts = implode(", ", $orderItems);
    $status = 'Completed';
    $date = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO orders (customer_name, product_name, price, status, order_date) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$customerName, $combinedProducts, $totalPrice, $status, $date]);

    if ($success) {
        $_SESSION['cart'] = [];
        echo json_encode(["success" => true, "message" => "Order placed successfully!", "order_id" => $pdo->lastInsertId()]);
    } else {
        echo json_encode(["success" => false, "message" => "Checkout failed"]);
    }
    exit;
}

// Default response
echo json_encode(["success" => false, "message" => "Invalid action"]);
?>