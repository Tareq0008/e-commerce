<?php
header("Content-Type: application/json");
require 'db.php';

// Test database connection
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "message" => "Database connected", "categories_count" => $result['count']]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>