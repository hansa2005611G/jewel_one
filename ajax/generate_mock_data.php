<?php
/**
 * Mock Data Generator Endpoint
 * Clears existing sales data and populates DB with 6 months of realistic jewelry transactions.
 * Access: Admin only, POST request with CSRF token.
 */
require_once '../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verifyCSRF($csrf)) {
    echo json_encode(['error' => 'CSRF token verification failed']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Disable foreign key checks temporarily to truncate safely
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE bill_items");
    $db->exec("TRUNCATE TABLE bills");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Product definitions with realistic jewelry pricing (in Rs.)
    $products = [
        ['name' => 'Gold Ring 22K', 'price' => 115000.00, 'prob' => 35],
        ['name' => 'Diamond Necklace 18K', 'price' => 280000.00, 'prob' => 10],
        ['name' => 'Ruby Earring Set', 'price' => 95000.00, 'prob' => 15],
        ['name' => 'Silver Bracelet 925', 'price' => 22000.00, 'prob' => 25],
        ['name' => 'Platinum Wedding Band', 'price' => 165000.00, 'prob' => 12],
        ['name' => 'Gold Bangle 22K', 'price' => 210000.00, 'prob' => 18]
    ];

    $customers = [
        ['name' => 'Amara Silva', 'phone' => '0771234567'],
        ['name' => 'Nimal Perera', 'phone' => '0714567890'],
        ['name' => 'Kamal Jayawardena', 'phone' => '0757890123'],
        ['name' => 'Priyantha Bandara', 'phone' => '0721112223'],
        ['name' => 'Dilini Fernando', 'phone' => '0763334445'],
        ['name' => 'Kavindi Alwis', 'phone' => '0774445556'],
        ['name' => 'Anura Kumara', 'phone' => '0715556667'],
        ['name' => 'Sunethra Rajapaksha', 'phone' => '0786667778']
    ];

    $cashiers = [1, 2]; // admin, cashier1
    $payMethods = ['cash', 'card', 'bank_transfer'];

    // Generate bills for the last 180 days (6 months)
    $totalBills = 0;
    $startDate = new DateTime();
    $startDate->modify('-180 days');
    $endDate = new DateTime();

    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    $billStmt = $db->prepare("INSERT INTO bills 
        (bill_number, cashier_id, customer_name, customer_phone, subtotal, total_discount, tax_amount, grand_total, paid_amount, balance_amount, payment_method, status, notes, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)");

    $itemStmt = $db->prepare("INSERT INTO bill_items 
        (bill_id, product_name, quantity, unit_price, discount_type, discount_value, discount_amount, original_price, final_total, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $taxEnabled = getSetting('tax_enabled', '0') === '1';
    $taxRate = (float)getSetting('tax_percentage', '0');

    foreach ($dateRange as $date) {
        $dayOfWeek = (int)$date->format('N'); // 1 = Mon, 7 = Sun
        $month = (int)$date->format('n');      // 1 = Jan, 12 = Dec

        // Seasonality factors:
        // - Weekend multiplier (Friday, Saturday, Sunday)
        // - Seasonal month multipliers (April and December are peak wedding/festival months in Sri Lanka)
        $numSales = 0;
        $isWeekend = ($dayOfWeek >= 5);
        
        $baseProb = $isWeekend ? 0.85 : 0.40;
        
        // Month boosts
        if ($month == 4 || $month == 12) {
            $baseProb += 0.15; // peak months
        }

        // Determine how many bills to generate for this day
        if (mt_rand(0, 100) / 100 <= $baseProb) {
            $numSales = $isWeekend ? mt_rand(2, 5) : mt_rand(1, 2);
            if ($month == 4 || $month == 12) {
                $numSales += mt_rand(1, 2); // additional boost
            }
        }

        for ($s = 0; $s < $numSales; $s++) {
            $totalBills++;
            $billNum = 'JO' . $date->format('ymd') . strtoupper(substr(md5(uniqid()), 0, 4));
            
            // Random cashier
            $cashierId = $cashiers[array_rand($cashiers)];
            
            // Customer (80% chance of walk-in, 20% registered)
            $custName = null;
            $custPhone = null;
            if (mt_rand(1, 10) <= 3) {
                $c = $customers[array_rand($customers)];
                $custName = $c['name'];
                $custPhone = $c['phone'];
            }

            // Payment method probability (60% cash, 30% card, 10% bank)
            $randVal = mt_rand(1, 100);
            if ($randVal <= 60) $payMethod = 'cash';
            elseif ($randVal <= 90) $payMethod = 'card';
            else $payMethod = 'bank_transfer';

            // Items count: 1 (70%), 2 (20%), 3 (10%)
            $itemsCount = 1;
            $iRand = mt_rand(1, 100);
            if ($iRand > 70 && $iRand <= 90) $itemsCount = 2;
            elseif ($iRand > 90) $itemsCount = 3;

            // Generate items
            $billSubtotal = 0;
            $billDiscount = 0;
            $billItems = [];

            // Helper to pick a product by probability weight
            for ($i = 0; $i < $itemsCount; $i++) {
                // Select product using basic weighted random
                $totalProb = 0;
                foreach ($products as $p) $totalProb += $p['prob'];
                $rand = mt_rand(1, $totalProb);
                
                $selectedProd = $products[0];
                $runningSum = 0;
                foreach ($products as $p) {
                    $runningSum += $p['prob'];
                    if ($rand <= $runningSum) {
                        $selectedProd = $p;
                        break;
                    }
                }

                // Check for duplicates in the same bill to avoid overlapping lines
                $duplicate = false;
                foreach ($billItems as $bi) {
                    if ($bi['name'] === $selectedProd['name']) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate) continue;

                $qty = 1;
                // For cheaper items, maybe buy more
                if ($selectedProd['name'] === 'Silver Bracelet 925') {
                    $qty = mt_rand(1, 3);
                } elseif ($selectedProd['name'] === 'Gold Ring 22K') {
                    $qty = mt_rand(1, 2);
                }

                $originalPrice = $qty * $selectedProd['price'];
                
                // Item level discounts (15% chance)
                $discType = 'none';
                $discVal = 0;
                $discAmt = 0;
                if (mt_rand(1, 100) <= 15) {
                    if (mt_rand(1, 2) == 1) {
                        $discType = 'percentage';
                        $discVal = mt_rand(2, 10); // 2% to 10%
                        $discAmt = ($originalPrice * $discVal) / 100;
                    } else {
                        $discType = 'fixed';
                        $discVal = mt_rand(1, 5) * 1000; // Rs. 1000 to 5000
                        $discAmt = min($discVal, $originalPrice);
                    }
                }

                $finalPrice = $originalPrice - $discAmt;
                $billSubtotal += $originalPrice;
                $billDiscount += $discAmt;

                $billItems[] = [
                    'name' => $selectedProd['name'],
                    'qty' => $qty,
                    'price' => $selectedProd['price'],
                    'disc_type' => $discType,
                    'disc_val' => $discVal,
                    'disc_amt' => $discAmt,
                    'original' => $originalPrice,
                    'final' => $finalPrice
                ];
            }

            if (empty($billItems)) continue; // safeguard

            $taxAmt = $taxEnabled ? (($billSubtotal - $billDiscount) * $taxRate / 100) : 0.00;
            $grandTotal = $billSubtotal - $billDiscount + $taxAmt;
            
            // Paid amount calculation
            $paidAmt = $grandTotal;
            if ($payMethod === 'cash') {
                // Round up cash to nearest thousand
                $paidAmt = ceil($grandTotal / 1000) * 1000;
            }
            $balance = $paidAmt - $grandTotal;

            // Generate structured notes (10% chance)
            $note = null;
            if (mt_rand(1, 100) <= 10) {
                $notesList = ['Wedding purchase', 'Gift item', 'Loyal customer discount', 'Custom request'];
                $note = $notesList[array_rand($notesList)];
            }

            // Dates with random hours (during business hours: 9 AM to 7 PM)
            $hour = mt_rand(9, 18);
            $minute = mt_rand(0, 59);
            $second = mt_rand(0, 59);
            
            $createdAt = clone $date;
            $createdAt->setTime($hour, $minute, $second);
            $createdAtStr = $createdAt->format('Y-m-d H:i:s');

            // Save Bill
            $billStmt->execute([
                $billNum, $cashierId, $custName, $custPhone,
                $billSubtotal, $billDiscount, $taxAmt, $grandTotal,
                $paidAmt, $balance, $payMethod, $note,
                $createdAtStr, $createdAtStr
            ]);

            $billId = $db->lastInsertId();

            // Save Bill Items
            foreach ($billItems as $item) {
                $itemStmt->execute([
                    $billId, $item['name'], $item['qty'], $item['price'],
                    $item['disc_type'], $item['disc_val'], $item['disc_amt'],
                    $item['original'], $item['final'], $createdAtStr
                ]);
            }
        }
    }

    $db->commit();
    
    // Log action in audit trail
    logAction($_SESSION['user_id'], 'GENERATE_MOCK_DATA', "Generated {$totalBills} bills and sales history for 6 months.");

    echo json_encode([
        'success' => true, 
        'message' => "Successfully populated the database with {$totalBills} transactions spread across the last 180 days."
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['error' => 'Failed to generate mock data: ' . $e->getMessage()]);
}
