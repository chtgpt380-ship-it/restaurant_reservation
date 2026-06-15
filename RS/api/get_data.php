<?php
require_once '../config/auth.php';
requireLogin();
header('Content-Type: application/json');
require_once '../config/db.php';
$conn = getDBConnection();

$type = $_GET['type'] ?? '';

if ($type === 'menu') {
    $res = $conn->query("SELECT m.item_id, m.name, m.price, m.inventory_qty as stock, c.name as category FROM menu_items m JOIN menu_categories c ON m.category_id = c.category_id");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
} elseif ($type === 'tables') {
    $res = $conn->query("SELECT * FROM dining_tables");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
} elseif ($type === 'orders') {
    $res = $conn->query("SELECT o.order_id, t.table_num, o.server_name, o.order_time, o.status, COALESCE(SUM(d.quantity * d.unit_price), 0) as total FROM orders o JOIN dining_tables t ON o.table_id = t.table_id LEFT JOIN order_details d ON o.order_id = d.order_id GROUP BY o.order_id ORDER BY o.order_id DESC LIMIT 10");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
} else {
    echo json_encode(["success" => false, "error" => "Invalid type parameter"]);
}
$conn->close();
?>