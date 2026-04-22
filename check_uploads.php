<?php
$uploadDir = __DIR__ . '/uploads/';
$categoryUploadDir = __DIR__ . '/uploads/categories/';

echo "Upload directory: " . $uploadDir . "<br>";
echo "Exists: " . (file_exists($uploadDir) ? "Yes" : "No") . "<br>";
echo "Writable: " . (is_writable($uploadDir) ? "Yes" : "No") . "<br><br>";

echo "Category upload directory: " . $categoryUploadDir . "<br>";
echo "Exists: " . (file_exists($categoryUploadDir) ? "Yes" : "No") . "<br>";
echo "Writable: " . (is_writable($categoryUploadDir) ? "Yes" : "No") . "<br>";

// Create directories if they don't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "Created upload directory<br>";
}
if (!file_exists($categoryUploadDir)) {
    mkdir($categoryUploadDir, 0777, true);
    echo "Created category upload directory<br>";
}

// Set permissions
chmod($uploadDir, 0777);
chmod($categoryUploadDir, 0777);
echo "Permissions set to 0777<br>";
?>