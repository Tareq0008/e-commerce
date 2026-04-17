<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

// 1. CUSTOMER AUTHENTICATION
if ($action === 'login' && $method === 'POST') {
    $email = $inputData['email'] ?? '';
    $password = $inputData['password'] ?? '';

    // ... Customer login logic setting $_SESSION['customer_id'] ...
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
?>