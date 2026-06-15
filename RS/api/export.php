<?php
require_once '../config/auth.php';
requireLogin();
require_once '../config/db.php';
$conn = getDBConnection();

$type = $_GET['type'] ?? 'sales';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="restaurant_' . $type . '_export.csv"');

$output = fopen('php://output', 'w');

if ($type === 'orders') {
    fputcsv($output, ['Order ID', 'Table Number', 'Server', 'Timestamp', 'Status']);
    $res = $conn->query("SELECT o.order_id, t.table_num, o.server_name, o.order_time, o.status FROM orders o JOIN dining_tables t ON o.table_id = t.table_id");
    while ($row = $res->fetch_assoc()) fputcsv($output, $row);
} else {
    fputcsv($output, ['Fact ID', 'Period', 'Item', 'Category', 'Server', 'Table', 'Qty', 'Revenue']);
    $res = $conn->query("SELECT f.fact_id, t.meal_period, m.name, m.category, s.server_name, s.table_num, f.quantity_sold, f.gross_amount FROM fact_restaurant_sales f JOIN dim_time t ON f.time_id = t.time_id JOIN dim_menu_item m ON f.dim_item_id = m.dim_item_id JOIN dim_service s ON f.dim_service_id = s.dim_service_id");
    while ($row = $res->fetch_assoc()) fputcsv($output, $row);
}
fclose($output);
$conn->close();
?>