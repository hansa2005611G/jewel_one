<?php
require_once 'includes/auth.php';
requireLogin();

$id       = (int)($_GET['id'] ?? 0);
$autoPrint= isset($_GET['auto']);
$db       = getDB();

$billStmt = $db->prepare("SELECT b.*, u.full_name as cashier_name FROM bills b JOIN users u ON b.cashier_id=u.id WHERE b.id=?");
$billStmt->execute([$id]);
$bill     = $billStmt->fetch();

if (!$bill) { header('Location: bill_history.php'); exit; }

$itemsStmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id=? ORDER BY id ASC");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$shopName    = getSetting('shop_name', 'JEWEL ONE 1');
$shopAddr    = getSetting('shop_address', 'Matale, Sri Lanka');
$shopPhone   = getSetting('shop_phone', '');
$currency    = getSetting('currency_symbol', 'Rs.');
$footer      = getSetting('receipt_footer', 'Thank You Come Again');
$thermalSize = getSetting('thermal_size', '80mm');
$receiptWidth= $thermalSize === '58mm' ? '220px' : '302px';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - <?= h($bill['bill_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
<style>
  @media print {
    body * { visibility: hidden !important; }
    #receiptPrintArea, #receiptPrintArea * { visibility: visible !important; }
    #receiptPrintArea { position:fixed; top:0; left:0; width:<?= $receiptWidth ?>; padding:0; margin:0; }
    .no-print { display:none !important; }
    .receipt-preview { box-shadow:none; border:none; }
  }
  .receipt-preview {
    background: #fff;
    color: #000;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    width: <?= $receiptWidth ?>;
    margin: 0 auto;
    padding: 14px 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  }
  .r-center { text-align: center; }
  .r-bold   { font-weight: bold; }
  .r-sm     { font-size: 10px; }
  .r-lg     { font-size: 14px; }
  .r-divider { border: none; border-top: 1px dashed #999; margin: 6px 0; }
  .r-row    { display: flex; justify-content: space-between; margin: 2px 0; }
  .r-row .name { flex: 1; max-width: 55%; word-break: break-word; }
  .r-row .val  { text-align: right; }
  .r-grand  { border-top: 2px solid #000; padding-top: 4px; margin-top: 4px; font-size: 14px; font-weight: bold; }
</style>
</head>
<body class="dark-mode" style="background:var(--dark-1)">

<!-- No-print toolbar -->
<div class="no-print" style="padding:20px;display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
  <button class="btn btn-gold" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
  <a href="billing.php" class="btn btn-outline"><i class="fas fa-plus"></i> New Bill</a>
  <a href="bill_history.php" class="btn btn-outline"><i class="fas fa-history"></i> Bill History</a>
  <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-home"></i> Dashboard</a>
</div>

<div class="no-print" style="text-align:center;margin-bottom:16px">
  <span style="font-size:12px;color:var(--gray-4);letter-spacing:1px">RECEIPT PREVIEW — <?= h($thermalSize) ?> Width</span>
</div>

<!-- RECEIPT -->
<div id="receiptPrintArea">
<div class="receipt-preview">

  <!-- Shop Header -->
  <div class="r-center r-bold r-lg"><?= strtoupper(h($shopName)) ?></div>
  <div class="r-center r-sm"><?= h($shopAddr) ?></div>
  <?php if ($shopPhone): ?>
  <div class="r-center r-sm">Tel: <?= h($shopPhone) ?></div>
  <?php endif; ?>

  <hr class="r-divider">

  <!-- Bill Info -->
  <div class="r-row"><span>Bill No:</span><span class="r-bold"><?= h($bill['bill_number']) ?></span></div>
  <div class="r-row"><span>Date:</span><span><?= date('d-m-Y', strtotime($bill['created_at'])) ?></span></div>
  <div class="r-row"><span>Time:</span><span><?= date('h:i A', strtotime($bill['created_at'])) ?></span></div>
  <div class="r-row"><span>Cashier:</span><span><?= h($bill['cashier_name']) ?></span></div>
  <?php if ($bill['customer_name']): ?>
  <div class="r-row"><span>Customer:</span><span><?= h($bill['customer_name']) ?></span></div>
  <?php endif; ?>
  <?php if ($bill['customer_phone']): ?>
  <div class="r-row"><span>Phone:</span><span><?= h($bill['customer_phone']) ?></span></div>
  <?php endif; ?>

  <hr class="r-divider">

  <!-- Column headers -->
  <div class="r-row r-bold" style="font-size:10px">
    <span class="name">ITEM</span>
    <span>QTY</span>
    <span>PRICE</span>
    <span class="val">TOTAL</span>
  </div>
  <hr class="r-divider">

  <!-- Items -->
  <?php foreach ($items as $item): ?>
  <div style="margin-bottom:4px">
    <div class="r-bold" style="font-size:11px"><?= h($item['product_name']) ?></div>
    <div class="r-row r-sm">
      <span>  <?= number_format($item['quantity'],2) ?> x <?= $currency ?><?= number_format($item['unit_price'],2) ?></span>
      <?php if ($item['discount_type'] !== 'none' && $item['discount_amount'] > 0): ?>
      <span style="color:#555">(Disc: -<?= $currency ?><?= number_format($item['discount_amount'],2) ?>)</span>
      <?php endif; ?>
      <span class="val r-bold"><?= $currency ?><?= number_format($item['final_total'],2) ?></span>
    </div>
  </div>
  <?php endforeach; ?>

  <hr class="r-divider">

  <!-- Totals -->
  <div class="r-row"><span>Subtotal</span><span><?= $currency ?><?= number_format($bill['subtotal'],2) ?></span></div>
  <?php if ($bill['total_discount'] > 0): ?>
  <div class="r-row"><span>Discount</span><span>-<?= $currency ?><?= number_format($bill['total_discount'],2) ?></span></div>
  <?php endif; ?>
  <?php if ($bill['tax_amount'] > 0): ?>
  <div class="r-row"><span>Tax</span><span><?= $currency ?><?= number_format($bill['tax_amount'],2) ?></span></div>
  <?php endif; ?>
  <div class="r-row r-grand">
    <span>TOTAL</span>
    <span><?= $currency ?><?= number_format($bill['grand_total'],2) ?></span>
  </div>
  <div class="r-row r-sm">
    <span>Paid (<?= ucfirst(str_replace('_',' ',$bill['payment_method'])) ?>)</span>
    <span><?= $currency ?><?= number_format($bill['paid_amount'],2) ?></span>
  </div>
  <?php $bal = $bill['paid_amount'] - $bill['grand_total']; ?>
  <div class="r-row r-bold">
    <span><?= $bal >= 0 ? 'Change' : 'Balance Due' ?></span>
    <span><?= $currency ?><?= number_format(abs($bal),2) ?></span>
  </div>

  <?php if ($bill['notes']): ?>
  <hr class="r-divider">
  <div class="r-sm">Note: <?= h($bill['notes']) ?></div>
  <?php endif; ?>

  <hr class="r-divider">
  <div class="r-center r-bold" style="margin:8px 0"><?= h($footer) ?></div>
  <div class="r-center r-sm">Powered by JEWEL ONE POS</div>

</div><!-- .receipt-preview -->
</div><!-- #receiptPrintArea -->

<?php if ($autoPrint): ?>
<script>
  window.addEventListener('load', () => {
    setTimeout(() => window.print(), 800);
  });
</script>
<?php endif; ?>
</body>
</html>
