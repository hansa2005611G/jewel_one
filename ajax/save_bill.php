<?php
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request']); exit;
}

try {
    $db = getDB();

    // Collect bill data
    $billNumber   = trim($_POST['bill_number'] ?? '');
    $cashierId    = (int)($_POST['cashier_id'] ?? 0);
    $custName     = trim($_POST['customer_name'] ?? '');
    $custPhone    = trim($_POST['customer_phone'] ?? '');
    $subtotal     = (float)($_POST['subtotal'] ?? 0);
    $totalDisc    = (float)($_POST['total_discount'] ?? 0);
    $taxAmt       = (float)($_POST['tax_amount'] ?? 0);
    $grandTotal   = (float)($_POST['grand_total'] ?? 0);
    $paidAmt      = (float)($_POST['paid_amount'] ?? 0);
    $balance      = (float)($_POST['balance_amount'] ?? 0);
    $payMethod    = in_array($_POST['payment_method'] ?? '', ['cash','card','bank_transfer']) ? $_POST['payment_method'] : 'cash';
    $notes        = trim($_POST['notes'] ?? '');
    $isDraft      = ($_POST['is_draft'] ?? '0') === '1';
    $status       = $isDraft ? 'draft' : 'completed';

    // Validation
    if (empty($billNumber))  throw new Exception('Bill number missing.');
    if ($cashierId <= 0)     throw new Exception('Invalid cashier.');
    if ($grandTotal < 0)     throw new Exception('Invalid totals.');

    $items = $_POST['items'] ?? [];
    if (empty($items))       throw new Exception('No items in bill.');

    // Validate items
    $cleanItems = [];
    foreach ($items as $idx => $item) {
        $name     = trim($item['name'] ?? '');
        $qty      = (float)($item['qty'] ?? 0);
        $price    = (float)($item['price'] ?? 0);
        $discType = in_array($item['disc_type'] ?? '', ['percentage','fixed','none']) ? ($item['disc_type'] ?? 'none') : 'none';
        $discVal  = (float)($item['disc_val'] ?? 0);
        $discAmt  = (float)($item['disc_amount'] ?? 0);
        $original = (float)($item['original'] ?? 0);
        $final    = (float)($item['final_total'] ?? 0);
        $hasDisc  = isset($item['has_discount']);

        if (empty($name) || $qty <= 0 || $price < 0) continue;
        if (!$hasDisc) { $discType = 'none'; $discVal = 0; $discAmt = 0; }

        $cleanItems[] = compact('name','qty','price','discType','discVal','discAmt','original','final');
    }

    if (empty($cleanItems)) throw new Exception('No valid items provided.');

    // Generate unique bill number if collision
    $exists = $db->prepare("SELECT id FROM bills WHERE bill_number = ?");
    $exists->execute([$billNumber]);
    if ($exists->fetch()) {
        $billNumber = generateBillNumber() . rand(10,99);
    }

    // Insert bill
    $stmt = $db->prepare("INSERT INTO bills
        (bill_number, cashier_id, customer_name, customer_phone, subtotal, total_discount,
         tax_amount, grand_total, paid_amount, balance_amount, payment_method, status, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->execute([
        $billNumber, $cashierId,
        $custName ?: null, $custPhone ?: null,
        $subtotal, $totalDisc, $taxAmt, $grandTotal,
        $paidAmt, $balance, $payMethod, $status, $notes ?: null
    ]);

    $billId = $db->lastInsertId();

    // Insert items
    $iStmt = $db->prepare("INSERT INTO bill_items
        (bill_id, product_name, quantity, unit_price, discount_type, discount_value,
         discount_amount, original_price, final_total)
        VALUES (?,?,?,?,?,?,?,?,?)");

    foreach ($cleanItems as $item) {
        $iStmt->execute([
            $billId,
            $item['name'],
            $item['qty'],
            $item['price'],
            $item['discType'],
            $item['discVal'],
            $item['discAmt'],
            $item['original'],
            $item['final']
        ]);
    }

    logAction($cashierId, 'CREATE_BILL', "Bill #{$billNumber} created, Total: {$grandTotal}");

    echo json_encode(['success' => true, 'bill_id' => $billId, 'bill_number' => $billNumber]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
