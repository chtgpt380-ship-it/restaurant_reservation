<?php
// api/restock.php
// POST: Receive ingredient stock via ACID transaction
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
$supplier_id = (int)($input['supplier_id'] ?? 0);
$items       = $input['items'] ?? [];
$notes       = trim($input['notes'] ?? '');
 
if (!$supplier_id || empty($items)) {
    echo json_encode(['error' => 'supplier_id and items are required']);
    exit;
}
 
$db = getDBConnection();
$db->begin_transaction();
 
try {
    // Create restock record
    $stmt = $db->prepare("INSERT INTO ingredient_restock (supplier_id, status, total_cost) VALUES (?, 'received', 0)");
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $restock_id = $db->insert_id;
    $stmt->close();
 
    $total_cost = 0;
    $ref = 'RS-' . str_pad($restock_id, 4, '0', STR_PAD_LEFT);
 
    foreach ($items as $item) {
        $ingredient_id = (int)($item['ingredient_id'] ?? 0);
        $quantity      = (float)($item['quantity'] ?? 0);
        if (!$ingredient_id || $quantity <= 0) throw new Exception("Invalid item data");
 
        // Get current unit cost
        $ps = $db->prepare("SELECT unit_cost FROM ingredients WHERE ingredient_id = ?");
        $ps->bind_param('i', $ingredient_id);
        $ps->execute();
        $irow = $ps->get_result()->fetch_assoc();
        $ps->close();
        if (!$irow) throw new Exception("Ingredient $ingredient_id not found");
 
        $unit_cost = (float)$irow['unit_cost'];
 
        // Insert restock item
        $is = $db->prepare("INSERT INTO restock_items (restock_id, ingredient_id, quantity, unit_cost) VALUES (?,?,?,?)");
        $is->bind_param('iidd', $restock_id, $ingredient_id, $quantity, $unit_cost);
        $is->execute();
        $is->close();
 
        // Update ingredient stock
        $us = $db->prepare("UPDATE ingredients SET stock_qty = stock_qty + ? WHERE ingredient_id = ?");
        $us->bind_param('di', $quantity, $ingredient_id);
        $us->execute();
        $us->close();
 
        // Log stock movement
        $ms = $db->prepare("INSERT INTO stock_movements (ingredient_id, movement_type, quantity, reference_no, notes) VALUES (?, 'restock', ?, ?, ?)");
        $ms->bind_param('idss', $ingredient_id, $quantity, $ref, $notes);
        $ms->execute();
        $ms->close();
 
        $total_cost += $unit_cost * $quantity;
    }
 
    // Update restock total
    $ts = $db->prepare("UPDATE ingredient_restock SET total_cost = ? WHERE restock_id = ?");
    $ts->bind_param('di', $total_cost, $restock_id);
    $ts->execute();
    $ts->close();
 
    $db->commit();
    echo json_encode(['success' => true, 'restock_id' => $restock_id, 'reference' => $ref, 'total_cost' => $total_cost]);
 
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
 
$db->close();