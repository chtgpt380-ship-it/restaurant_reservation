<?php
require_once '../config/auth.php';
requireLogin();
header('Content-Type: application/json');
require_once '../config/db.php';
$conn = getDBConnection();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['table_id']) || empty($data['server_name']) || empty($data['items'])) {
    echo json_encode(["success" => false, "error" => "Invalid transaction parameters passed."]);
    exit;
}

$table_id = (int)$data['table_id'];
$server_name = $conn->real_escape_string($data['server_name']);
$items = $data['items'];

// BEGIN ACID TRANSACTION
$conn->begin_transaction();

try {
    // 1. Insert Core Order Ledger
    $stmt = $conn->prepare("INSERT INTO orders (table_id, server_name, status) VALUES (?, ?, 'paid')");
    $stmt->bind_param("is", $table_id, $server_name);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // 2. Loop & Process items
    foreach ($items as $item) {
        $item_id = (int)$item['item_id'];
        $qty = (int)$item['quantity'];

        // Get Price and Verify Stock
        $pStmt = $conn->prepare("SELECT price, inventory_qty FROM menu_items WHERE item_id = ?");
        $pStmt->bind_param("i", $item_id);
        $pStmt->execute();
        $pRes = $pStmt->get_result()->fetch_assoc();

        if (!$pRes || $pRes['inventory_qty'] < $qty) {
            throw new Exception("Insufficient stock parameters for Item ID: " . $item_id);
        }
        $unit_price = $pRes['price'];

        // Write breakdown details
        $dStmt = $conn->prepare("INSERT INTO order_details (order_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $dStmt->bind_param("iiid", $order_id, $item_id, $qty, $unit_price);
        $dStmt->execute();

        // Deplete kitchen operational inventory
        $uStmt = $conn->prepare("UPDATE menu_items SET inventory_qty = inventory_qty - ? WHERE item_id = ?");
        $uStmt->bind_param("ii", $qty, $item_id);
        $uStmt->execute();
    }

    // COMMIT ALL MUTATIONS SUCCESSFULLY
    $conn->commit();
    echo json_encode(["success" => true, "order_id" => $order_id]);

} catch (Exception $e) {
    // ROLLBACK ON FAILURE TO ASSURE DATA SANITY
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>