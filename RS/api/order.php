<?php
// api/order.php
// POST: Place a new order with ACID transaction
require_once '../config/auth.php';
requireLogin();
header('Content-Type: application/json');
require_once '../config/db.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
 
$input       = json_decode(file_get_contents('php://input'), true);
$table_number = trim($input['table_number'] ?? '');
$order_type  = $input['order_type'] ?? 'dine-in';
$items       = $input['items'] ?? [];
 
if (empty($items)) {
    echo json_encode(['error' => 'items are required']);
    exit;
}
 
$valid_types = ['dine-in', 'takeout', 'delivery'];
if (!in_array($order_type, $valid_types)) {
    echo json_encode(['error' => 'Invalid order_type']);
    exit;
}
 
$db = getDBConnection();
$db->begin_transaction();
 
try {
    // Create order
    $stmt = $db->prepare("INSERT INTO orders (table_number, order_type, status, total_amount) VALUES (?, ?, 'preparing', 0)");
    $tn = $table_number ?: null;
    $stmt->bind_param('ss', $tn, $order_type);
    $stmt->execute();
    $order_id = $db->insert_id;
    $stmt->close();
 
    $total_amount = 0;
 
    foreach ($items as $item) {
        $item_id  = (int)($item['item_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        if (!$item_id || $quantity < 1) throw new Exception("Invalid item data");
 
        // Fetch selling price
        $ps = $db->prepare("SELECT selling_price, is_available FROM menu_items WHERE item_id = ?");
        $ps->bind_param('i', $item_id);
        $ps->execute();
        $row = $ps->get_result()->fetch_assoc();
        $ps->close();
        if (!$row) throw new Exception("Menu item $item_id not found");
        if (!$row['is_available']) throw new Exception("Menu item $item_id is not available");
 
        $unit_price = (float)$row['selling_price'];
        $subtotal   = $unit_price * $quantity;
 
        // Insert order item
        $is = $db->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)");
        $is->bind_param('iiidd', $order_id, $item_id, $quantity, $unit_price, $subtotal);
        $is->execute();
        $is->close();
 
        $total_amount += $subtotal;
    }
 
    // Update order total
    $us = $db->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
    $us->bind_param('di', $total_amount, $order_id);
    $us->execute();
    $us->close();
 
    $db->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'total_amount' => $total_amount]);
 
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
 
$db->close();