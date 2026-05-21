<?php
require_once 'includes/auth.php';
requireLogin();
$user      = getCurrentUser();
$currency  = getSetting('currency_symbol', 'Rs.');
$taxEn     = getSetting('tax_enabled', '0');
$taxRate   = getSetting('tax_percentage', '0');
$billNum   = generateBillNumber();
$csrf      = generateCSRF();
include 'includes/header.php';
?>

<meta name="csrf" content="<?= h($csrf) ?>">
<input type="hidden" id="currencySymbol" value="<?= h($currency) ?>">
<input type="hidden" id="taxEnabled"     value="<?= h($taxEn) ?>">
<input type="hidden" id="taxRate"        value="<?= h($taxRate) ?>">

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-cash-register" style="color:var(--gold);margin-right:10px"></i>New <span>Bill</span></h1>
    <div class="page-breadcrumb">POS · Create Invoice · Alt+S to Save · Alt+A to Add Item · Alt+P to Print</div>
  </div>
  <div style="display:flex;gap:10px">
    <button class="btn btn-outline" onclick="clearBill()"><i class="fas fa-redo"></i> Clear</button>
    <button class="btn btn-gold" form="billingForm" type="submit"><i class="fas fa-save"></i> Save & Print</button>
  </div>
</div>

<form id="billingForm" method="POST">
<input type="hidden" name="csrf_token"      value="<?= h($csrf) ?>">
<input type="hidden" name="bill_number"     value="<?= h($billNum) ?>">
<input type="hidden" name="cashier_id"      value="<?= h($user['id']) ?>">
<input type="hidden" id="paymentMethod"     name="payment_method" value="cash">
<input type="hidden" id="hiddenSubtotal"    name="subtotal"    value="0">
<input type="hidden" id="hiddenDiscount"    name="total_discount" value="0">
<input type="hidden" id="hiddenTax"         name="tax_amount"  value="0">
<input type="hidden" id="hiddenGrandTotal"  name="grand_total" value="0">
<input type="hidden" id="hiddenBalance"     name="balance_amount" value="0">

<div class="billing-layout fade-in">
  <!-- LEFT PANEL -->
  <div class="billing-panel">

    <!-- Bill Info Bar -->
    <div class="bill-info-bar">
      <div class="bill-number-badge">
        <div class="label">Bill Number</div>
        <div class="value"><?= h($billNum) ?></div>
      </div>
      <div class="bill-number-badge">
        <div class="label">Date</div>
        <div class="value" style="font-size:15px"><?= date('d M Y') ?></div>
      </div>
      <div class="bill-number-badge">
        <div class="label">Cashier</div>
        <div class="value" style="font-size:14px;letter-spacing:1px"><?= h($user['full_name']) ?></div>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-user"></i> Customer Details <span style="color:var(--gray-4);font-size:12px;font-family:var(--font-body)">(Optional)</span></span>
      </div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" placeholder="Walk-in Customer">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="customer_phone" class="form-control" placeholder="+94 77 123 4567">
          </div>
          <div class="form-group" style="flex:2">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Any special notes...">
          </div>
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-gem"></i> Bill Items</span>
        <span style="font-size:12px;color:var(--gray-4)">Add unlimited items</span>
      </div>
      <div class="card-body">
        <!-- Column Headers -->
        <div style="display:grid;grid-template-columns:3fr 80px 100px auto 110px 36px;gap:8px;padding:0 4px 8px;border-bottom:1px solid rgba(201,168,76,0.1);margin-bottom:10px">
          <span class="form-label" style="margin:0">Product Name</span>
          <span class="form-label" style="margin:0">Qty</span>
          <span class="form-label" style="margin:0">Unit Price</span>
          <span class="form-label" style="margin:0">Disc.</span>
          <span class="form-label" style="margin:0;text-align:right">Total</span>
          <span></span>
        </div>

        <div id="itemsContainer"></div>

        <button type="button" class="add-item-btn" id="addItemBtn">
          <i class="fas fa-plus-circle"></i> Add Item (Alt+A)
        </button>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL: Bill Summary -->
  <div class="bill-summary">
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-file-invoice-dollar"></i> Bill Summary</span>
      </div>
      <div class="card-body">

        <!-- Totals -->
        <div class="summary-row">
          <span class="s-label">Subtotal</span>
          <span class="s-value" id="displaySubtotal"><?= $currency ?> 0.00</span>
        </div>
        <div class="summary-row">
          <span class="s-label" style="color:var(--danger)">Total Discount</span>
          <span class="s-value" id="displayDiscount" style="color:var(--danger)"><?= $currency ?> 0.00</span>
        </div>
        <?php if ($taxEn == '1'): ?>
        <div class="summary-row">
          <span class="s-label">Tax (<?= h($taxRate) ?>%)</span>
          <span class="s-value" id="displayTax"><?= $currency ?> 0.00</span>
        </div>
        <?php else: ?>
        <input type="hidden" id="displayTax">
        <?php endif; ?>

        <hr class="summary-divider">
        <div class="summary-row grand">
          <span class="s-label">Grand Total</span>
          <span class="s-value" id="displayGrandTotal"><?= $currency ?> 0.00</span>
        </div>

        <!-- Payment Method -->
        <div style="margin:14px 0 8px">
          <label class="form-label">Payment Method</label>
          <div class="payment-methods">
            <button type="button" class="pay-method-btn active" data-method="cash"><i class="fas fa-money-bill-wave"></i> Cash</button>
            <button type="button" class="pay-method-btn" data-method="card"><i class="fas fa-credit-card"></i> Card</button>
            <button type="button" class="pay-method-btn" data-method="bank_transfer"><i class="fas fa-university"></i> Bank</button>
          </div>
        </div>

        <!-- Paid Amount -->
        <div class="form-group">
          <label class="form-label">Amount Paid by Customer</label>
          <input type="number" id="paidAmount" name="paid_amount" class="form-control"
            min="0" step="any" placeholder="0.00" style="font-size:18px;font-weight:700;text-align:right">
        </div>

        <!-- Balance -->
        <div class="balance-display" id="balanceWrap">
          <span class="b-label">Change</span>
          <span class="b-value" id="displayBalance"><?= $currency ?> 0.00</span>
        </div>

        <hr class="summary-divider">

        <!-- Actions -->
        <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:14px;font-size:14px;margin-bottom:10px">
          <i class="fas fa-save"></i> Save Bill & Print Receipt
        </button>
        <button type="button" class="btn btn-outline" style="width:100%;justify-content:center" onclick="saveDraft()">
          <i class="fas fa-file-alt"></i> Save as Draft
        </button>
      </div>
    </div>

    <!-- Shortcuts reminder -->
    <div class="card" style="margin-top:14px">
      <div class="card-body" style="padding:14px 16px">
        <div style="font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold-dim);margin-bottom:10px">Keyboard Shortcuts</div>
        <?php foreach ([['Alt+A','Add new item'],['Alt+S','Save bill'],['Alt+P','Print receipt'],['Alt+C','Clear bill']] as [$k,$v]): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;color:var(--gray-3)">
          <kbd style="background:var(--dark-4);border:1px solid rgba(201,168,76,0.2);padding:2px 7px;border-radius:4px;color:var(--gold);font-size:10px"><?= $k ?></kbd>
          <span><?= $v ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</form>

<script>
function clearBill() {
  showConfirm('Clear Bill', 'This will remove all items and reset the form. Continue?', () => {
    document.getElementById('itemsContainer').innerHTML = '';
    document.getElementById('paidAmount').value = '';
    document.getElementById('paymentMethod').value = 'cash';
    document.querySelectorAll('.pay-method-btn').forEach((b,i) => b.classList.toggle('active', i===0));
    // Re-add one row
    if (typeof addItemRow === 'function') addItemRow();
    showToast('Bill cleared.', 'info');
  });
}

function saveDraft() {
  document.querySelector('input[name="csrf_token"]').closest('form').querySelectorAll('[name="payment_method"]');
  // Add hidden draft field
  let df = document.getElementById('draftFlag');
  if (!df) { df = document.createElement('input'); df.type = 'hidden'; df.id = 'draftFlag'; df.name = 'is_draft'; document.getElementById('billingForm').appendChild(df); }
  df.value = '1';
  document.getElementById('billingForm').requestSubmit();
}

// Alt+C = clear
document.addEventListener('keydown', e => {
  if (e.altKey && e.key === 'c') { e.preventDefault(); clearBill(); }
});
</script>

<?php include 'includes/footer.php'; ?>
