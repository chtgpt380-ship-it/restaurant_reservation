<?php
require_once '../config/auth.php';
requireLogin(['admin','manager']);
header('Content-Type: text/plain');
require_once '../config/db.php';
$conn = getDBConnection();

echo "=== INITIATING RESTAURANT ETL SYNCHRONIZATION PROCESS ===\n\n";

// 1. EXTRACT and process raw analytical dimensions
$orderQuery = "SELECT o.order_id, o.order_time, o.server_name, t.table_num, d.item_id, m.name as item_name, c.name as category_name, d.quantity, d.unit_price FROM orders o JOIN dining_tables t ON o.table_id = t.table_id JOIN order_details d ON o.order_id = d.order_id JOIN menu_items m ON d.item_id = m.item_id JOIN menu_categories c ON m.category_id = c.category_id WHERE o.status = 'paid'";

$records = $conn->query($orderQuery);
$processedCount = 0;

while ($row = $records->fetch_assoc()) {
    $full_dt = $row['order_time'];
    $ts = strtotime($full_dt);
    $hour = (int)date('H', $ts);
    
    // Transform Operational Data Logic (Calculate Meal Phase Structure)
    $meal_period = 'Late Night';
    if ($hour >= 6 && $hour < 11)       $meal_period = 'Breakfast';
    elseif ($hour >= 11 && $hour < 16)  $meal_period = 'Lunch';
    elseif ($hour >= 16 && $hour < 22)  $meal_period = 'Dinner';

    $day_name   = date('l', $ts);
    $month_name = date('F', $ts);
    $year_num   = (int)date('Y', $ts);

    // 2. LOAD Dimension Records safely
    $conn->query("INSERT IGNORE INTO dim_time (full_datetime, day_name, hour_num, meal_period, month_name, year_num) VALUES ('$full_dt', '$day_name', $hour, '$meal_period', '$month_name', $year_num)");
    $time_id = $conn->query("SELECT time_id FROM dim_time WHERE full_datetime = '$full_dt'")->fetch_assoc()['time_id'];

    $item_id   = (int)$row['item_id'];
    $item_name = $conn->real_escape_string($row['item_name']);
    $category  = $conn->real_escape_string($row['category_name']);
    
    $checkItem = $conn->query("SELECT dim_item_id FROM dim_menu_item WHERE item_id = $item_id");
    if ($checkItem->num_rows == 0) {
        $conn->query("INSERT INTO dim_menu_item (item_id, name, category) VALUES ($item_id, '$item_name', '$category')");
        $dim_item_id = $conn->insert_id;
    } else {
        $dim_item_id = $checkItem->fetch_assoc()['dim_item_id'];
    }

    $server = $conn->real_escape_string($row['server_name']);
    $t_num  = $conn->real_escape_string($row['table_num']);
    
    $checkServ = $conn->query("SELECT dim_service_id FROM dim_service WHERE server_name = '$server' AND table_num = '$t_num'");
    if ($checkServ->num_rows == 0) {
        $conn->query("INSERT INTO dim_service (server_name, table_num) VALUES ('$server', '$t_num')");
        $dim_service_id = $conn->insert_id;
    } else {
        $dim_service_id = $checkServ->fetch_assoc()['dim_service_id'];
    }

    // 3. LOAD Optimized Fact Analytics Data Matrix
    $order_id = (int)$row['order_id'];
    $qty      = (int)$row['quantity'];
    $u_price  = (float)$row['unit_price'];
    $gross    = $qty * $u_price;

    $checkFact = $conn->query("SELECT fact_id FROM fact_restaurant_sales WHERE order_id = $order_id AND dim_item_id = $dim_item_id");
    if ($checkFact->num_rows == 0) {
        $conn->query("INSERT INTO fact_restaurant_sales (time_id, dim_item_id, dim_service_id, order_id, quantity_sold, unit_price, gross_amount) VALUES ($time_id, $dim_item_id, $dim_service_id, $order_id, $qty, $u_price, $gross)");
        $processedCount++;
    }
}

echo "Data transformation complete.\n";
echo "Total target entries synced successfully into OLAP Star Architecture: " . $processedCount . " lines.\n";
$conn->close();
?>