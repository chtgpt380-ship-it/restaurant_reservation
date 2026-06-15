<?php
require_once '../config/auth.php';
requireLogin();
header('Content-Type: application/json');
require_once '../config/db.php';
$conn = getDBConnection();

$query = $_GET['query'] ?? '';

if ($query === 'kpi') {
    $sql = "SELECT COALESCE(SUM(gross_amount),0) as revenue, COUNT(DISTINCT order_id) as orders, COALESCE(SUM(quantity_sold),0) as covers FROM fact_restaurant_sales";
    $res = $conn->query($sql)->fetch_assoc();
    $res['avg_ticket'] = $res['orders'] > 0 ? $res['revenue'] / $res['orders'] : 0;
    echo json_encode($res);
} 
elseif ($query === 'time_trend') {
    // Aggregation grouped via historical timeframe categories
    $sql = "SELECT t.meal_period, SUM(f.gross_amount) as revenue FROM fact_restaurant_sales f JOIN dim_time t ON f.time_id = t.time_id GROUP BY t.meal_period ORDER BY FIELD(t.meal_period, 'Breakfast', 'Lunch', 'Dinner', 'Late Night')";
    echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
} 
elseif ($query === 'by_category') {
    $sql = "SELECT m.category, SUM(f.gross_amount) as revenue, SUM(f.quantity_sold) as units FROM fact_restaurant_sales f JOIN dim_menu_item m ON f.dim_item_id = m.dim_item_id GROUP BY m.category";
    echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
} 
elseif ($query === 'top_items') {
    $sql = "SELECT m.name as item_name, m.category, SUM(f.gross_amount) as revenue, SUM(f.quantity_sold) as quantity FROM fact_restaurant_sales f JOIN dim_menu_item m ON f.dim_item_id = m.dim_item_id GROUP BY m.name, m.category ORDER BY revenue DESC LIMIT 5";
    echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
} 
elseif ($query === 'rollup') {
    // Structural Multi-Level hierarchical aggregation via standard MySQL implementation
    $sql = "SELECT t.year_num, t.month_name, t.day_name, SUM(f.gross_amount) as revenue FROM fact_restaurant_sales f JOIN dim_time t ON f.time_id = t.time_id GROUP BY t.year_num, t.month_name, t.day_name WITH ROLLUP";
    echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
} 
elseif ($query === 'dice') {
    $period = $conn->real_escape_string($_GET['period'] ?? '');
    $cat = $conn->real_escape_string($_GET['category'] ?? '');
    
    $whereClause = "WHERE 1=1";
    if (!empty($period)) $whereClause .= " AND t.meal_period = '$period'";
    if (!empty($cat))    $whereClause .= " AND m.category = '$cat'";

    $sql = "SELECT t.meal_period, m.category, m.name as item, SUM(f.gross_amount) as revenue, SUM(f.quantity_sold) as units FROM fact_restaurant_sales f JOIN dim_time t ON f.time_id = t.time_id JOIN dim_menu_item m ON f.dim_item_id = m.dim_item_id $whereClause GROUP BY t.meal_period, m.category, m.name";
    echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
}

$conn->close();
?>