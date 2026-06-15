<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'restaurant_system');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]));
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>