// =============================================
// JEWEL ONE 1 MATALE - Main JS
// =============================================

// Live clock
function updateClock() {
  const el = document.getElementById('topbarTime');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

// Toast notifications
function showToast(msg, type = 'info') {
  const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-gem', warning: 'fa-exclamation-triangle' };
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<i class="fas ${icons[type] || icons.info} toast-icon"></i><span class="toast-msg">${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4200);
}

// Theme toggle
const themeBtn = document.getElementById('themeToggle');
if (themeBtn) {
  themeBtn.addEventListener('click', () => {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.body.classList.toggle('dark-mode', next === 'dark');
    document.body.classList.toggle('light-mode', next === 'light');
    themeBtn.querySelector('i').className = `fas fa-${next === 'dark' ? 'sun' : 'moon'}`;
    // Persist via AJAX
    fetch('ajax/save_setting.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `key=theme_mode&value=${next}&csrf=${document.querySelector('meta[name=csrf]')?.content || ''}`
    });
  });
}

// Sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const topbarToggle  = document.getElementById('topbarToggle');
const sidebar       = document.getElementById('sidebar');
const mainWrapper   = document.getElementById('mainWrapper');

function toggleSidebar() {
  if (!sidebar) return;
  sidebar.classList.toggle('collapsed');
  if (mainWrapper) mainWrapper.classList.toggle('sidebar-collapsed');
}
if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
if (topbarToggle)  topbarToggle.addEventListener('click', toggleSidebar);

// Confirm modal
let confirmCallback = null;
function showConfirm(title, msg, callback) {
  document.getElementById('confirmTitle').textContent   = title;
  document.getElementById('confirmMessage').textContent = msg;
  document.getElementById('confirmModal').style.display = 'flex';
  confirmCallback = callback;
}
document.getElementById('confirmOk')?.addEventListener('click', () => {
  document.getElementById('confirmModal').style.display = 'none';
  if (confirmCallback) confirmCallback();
  confirmCallback = null;
});
document.getElementById('confirmCancel')?.addEventListener('click', () => {
  document.getElementById('confirmModal').style.display = 'none';
  confirmCallback = null;
});

// Loading overlay
function showLoading(msg = 'Processing...') {
  let overlay = document.getElementById('loadingOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `<div class="spinner"></div><p>${msg}</p>`;
    document.body.appendChild(overlay);
  }
  overlay.style.display = 'flex';
}
function hideLoading() {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) overlay.style.display = 'none';
}

// Format number as currency
function formatCurrency(amount, symbol = 'Rs.') {
  return `${symbol} ${parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
}

// =============================================
// BILLING PAGE LOGIC
// =============================================
if (document.getElementById('billingForm')) {
  let itemCount = 0;
  const CURRENCY = document.getElementById('currencySymbol')?.value || 'Rs.';
  const TAX_ENABLED = document.getElementById('taxEnabled')?.value === '1';
  const TAX_RATE = parseFloat(document.getElementById('taxRate')?.value || 0);

  function addItemRow(data = {}) {
    itemCount++;
    const id = `item_${itemCount}`;
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = id;
    row.dataset.index = itemCount;
    row.innerHTML = `
      <div class="form-group">
        <label class="form-label">Product Name</label>
        <input type="text" class="form-control item-name" name="items[${itemCount}][name]"
          placeholder="e.g. Gold Ring 22K" value="${data.name || ''}" required autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Qty</label>
        <input type="number" class="form-control item-qty" name="items[${itemCount}][qty]"
          min="0.001" step="any" placeholder="1" value="${data.qty || 1}" required>
      </div>
      <div class="form-group">
        <label class="form-label">Unit Price</label>
        <input type="number" class="form-control item-price" name="items[${itemCount}][price]"
          min="0" step="any" placeholder="0.00" value="${data.price || ''}" required>
      </div>
      <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;padding-bottom:2px">
        <label class="toggle-switch" title="Apply Discount">
          <input type="checkbox" class="item-disc-toggle" name="items[${itemCount}][has_discount]">
          <span class="toggle-slider"></span>
        </label>
        <span class="form-label" style="margin:0;white-space:nowrap">Discount</span>
      </div>
      <div class="item-total-display">
        <div class="orig-price" id="${id}_orig">—</div>
        <div class="disc-amt" id="${id}_disc">—</div>
        <div class="final-price" id="${id}_total">${CURRENCY} 0.00</div>
      </div>
      <button type="button" class="remove-item-btn" onclick="removeItem('${id}')" title="Remove">
        <i class="fas fa-times"></i>
      </button>
      <!-- Discount row (hidden by default) -->
      <div class="item-discount-row" id="${id}_discrow" style="display:none">
        <label class="form-label" style="margin:0;white-space:nowrap">Type:</label>
        <select class="form-control item-disc-type" name="items[${itemCount}][disc_type]" style="width:120px">
          <option value="percentage">% Percent</option>
          <option value="fixed">Fixed Amount</option>
        </select>
        <input type="number" class="form-control item-disc-val" name="items[${itemCount}][disc_val]"
          min="0" step="any" placeholder="0" value="0" style="width:90px">
        <span class="form-label" style="margin:0;color:var(--gray-4);font-size:11px" id="${id}_discinfo">0% off</span>
      </div>
    `;
    document.getElementById('itemsContainer').appendChild(row);

    // Event listeners for this row
    row.querySelectorAll('.item-qty, .item-price').forEach(el => el.addEventListener('input', () => calcRowTotal(id)));
    row.querySelector('.item-disc-toggle').addEventListener('change', function() {
      const discrow = document.getElementById(`${id}_discrow`);
      discrow.style.display = this.checked ? 'flex' : 'none';
      calcRowTotal(id);
    });
    row.querySelector('.item-disc-type').addEventListener('change', () => calcRowTotal(id));
    row.querySelector('.item-disc-val').addEventListener('input', () => calcRowTotal(id));

    calcRowTotal(id);
    updateBillTotals();
    return id;
  }

  function calcRowTotal(id) {
    const row   = document.getElementById(id);
    if (!row) return;
    const qty   = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const hasDisc = row.querySelector('.item-disc-toggle').checked;
    const discType = row.querySelector('.item-disc-type').value;
    const discVal  = parseFloat(row.querySelector('.item-disc-val').value) || 0;

    const original = qty * price;
    let discAmt = 0;

    if (hasDisc && original > 0) {
      discAmt = discType === 'percentage' ? (original * discVal / 100) : Math.min(discVal, original);
    }

    const finalTotal = Math.max(0, original - discAmt);

    // Update displays
    const origEl  = document.getElementById(`${id}_orig`);
    const discEl  = document.getElementById(`${id}_disc`);
    const totalEl = document.getElementById(`${id}_total`);
    const infoEl  = document.getElementById(`${id}_discinfo`);

    if (discAmt > 0) {
      origEl.style.display  = 'block';
      discEl.style.display  = 'block';
      origEl.textContent    = `${CURRENCY} ${original.toFixed(2)}`;
      discEl.textContent    = `- ${CURRENCY} ${discAmt.toFixed(2)}`;
      infoEl.textContent    = discType === 'percentage' ? `${discVal}% off` : `${CURRENCY} ${discVal} off`;
    } else {
      origEl.style.display  = 'none';
      discEl.style.display  = 'none';
      infoEl.textContent    = '';
    }
    totalEl.textContent = `${CURRENCY} ${finalTotal.toFixed(2)}`;

    // Store computed values in hidden inputs
    let h = row.querySelector('.h-original'); if (!h) { h = document.createElement('input'); h.type='hidden'; h.className='h-original'; h.name=`items[${row.dataset.index}][original]`; row.appendChild(h); }
    let hd = row.querySelector('.h-discamt'); if (!hd) { hd = document.createElement('input'); hd.type='hidden'; hd.className='h-discamt'; hd.name=`items[${row.dataset.index}][disc_amount]`; row.appendChild(hd); }
    let hf = row.querySelector('.h-final');   if (!hf) { hf = document.createElement('input'); hf.type='hidden'; hf.className='h-final'; hf.name=`items[${row.dataset.index}][final_total]`; row.appendChild(hf); }
    h.value  = original.toFixed(2);
    hd.value = discAmt.toFixed(2);
    hf.value = finalTotal.toFixed(2);

    updateBillTotals();
  }

  function removeItem(id) {
    const row = document.getElementById(id);
    if (!row) return;
    if (document.querySelectorAll('.item-row').length === 1) {
      showToast('At least one item is required.', 'warning'); return;
    }
    row.style.animation = 'fadeOutToast 0.2s ease forwards';
    setTimeout(() => { row.remove(); updateBillTotals(); }, 200);
  }
  window.removeItem = removeItem;

  function updateBillTotals() {
    let subtotal = 0, totalDisc = 0;
    document.querySelectorAll('.item-row').forEach(row => {
      const orig = parseFloat(row.querySelector('.h-original')?.value || 0);
      const disc = parseFloat(row.querySelector('.h-discamt')?.value  || 0);
      const fin  = parseFloat(row.querySelector('.h-final')?.value    || 0);
      subtotal  += orig;
      totalDisc += disc;
    });

    const taxAmt   = TAX_ENABLED ? ((subtotal - totalDisc) * TAX_RATE / 100) : 0;
    const grand    = Math.max(0, subtotal - totalDisc + taxAmt);
    const paid     = parseFloat(document.getElementById('paidAmount')?.value || 0);
    const balance  = paid - grand;

    setText('displaySubtotal',  `${CURRENCY} ${subtotal.toFixed(2)}`);
    setText('displayDiscount',  `${CURRENCY} ${totalDisc.toFixed(2)}`);
    setText('displayTax',       `${CURRENCY} ${taxAmt.toFixed(2)}`);
    setText('displayGrandTotal',`${CURRENCY} ${grand.toFixed(2)}`);

    // Hidden inputs for form submission
    setVal('hiddenSubtotal',   subtotal.toFixed(2));
    setVal('hiddenDiscount',   totalDisc.toFixed(2));
    setVal('hiddenTax',        taxAmt.toFixed(2));
    setVal('hiddenGrandTotal', grand.toFixed(2));
    setVal('hiddenBalance',    balance.toFixed(2));

    // Balance display
    const balEl = document.getElementById('displayBalance');
    const balWrap = document.getElementById('balanceWrap');
    if (balEl) {
      balEl.textContent = `${CURRENCY} ${Math.abs(balance).toFixed(2)}`;
      if (balWrap) {
        balWrap.classList.toggle('negative', balance < 0);
        const lbl = balWrap.querySelector('.b-label');
        if (lbl) lbl.textContent = balance < 0 ? 'Amount Due' : 'Change';
      }
    }
  }

  function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
  function setVal(id, val)  { const el = document.getElementById(id); if (el) el.value = val; }

  // Paid amount live update
  document.getElementById('paidAmount')?.addEventListener('input', updateBillTotals);

  // Payment method buttons
  document.querySelectorAll('.pay-method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const input = document.getElementById('paymentMethod');
      if (input) input.value = btn.dataset.method;
    });
  });

  // Add item button
  document.getElementById('addItemBtn')?.addEventListener('click', () => addItemRow());

  // Initialize with one row
  addItemRow();

  // Keyboard shortcuts
  document.addEventListener('keydown', e => {
    if (e.altKey && e.key === 'a') { e.preventDefault(); addItemRow(); showToast('Item row added (Alt+A)', 'info'); }
    if (e.altKey && e.key === 's') { e.preventDefault(); document.getElementById('billingForm')?.requestSubmit(); }
    if (e.altKey && e.key === 'p') { e.preventDefault(); printReceipt(); }
  });

  // Form submit via AJAX
  document.getElementById('billingForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const items = document.querySelectorAll('.item-row');
    if (items.length === 0) { showToast('Add at least one item.', 'error'); return; }

    let valid = true;
    items.forEach(row => {
      const name  = row.querySelector('.item-name')?.value.trim();
      const price = parseFloat(row.querySelector('.item-price')?.value || 0);
      if (!name || price <= 0) { valid = false; }
    });

    if (!valid) { showToast('Please fill all product names and prices.', 'error'); return; }

    showLoading('Saving Bill...');
    const fd = new FormData(this);

    try {
      const res = await fetch('ajax/save_bill.php', { method: 'POST', body: fd });
      const data = await res.json();
      hideLoading();
      if (data.success) {
        showToast('Bill saved successfully!', 'success');
        setTimeout(() => {
          if (data.bill_id) {
            window.location.href = `print_receipt.php?id=${data.bill_id}&auto=1`;
          }
        }, 800);
      } else {
        showToast(data.error || 'Failed to save bill.', 'error');
      }
    } catch (err) {
      hideLoading();
      showToast('Network error. Please try again.', 'error');
    }
  });
}

// Print receipt
function printReceipt() {
  window.print();
}
window.printReceipt = printReceipt;

// Delete bill with confirm
function deleteBill(id) {
  showConfirm('Delete Bill', 'This bill will be permanently deleted. Continue?', async () => {
    showLoading('Deleting...');
    try {
      const res  = await fetch('ajax/delete_bill.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&csrf=${document.querySelector('meta[name=csrf]')?.content || ''}`
      });
      const data = await res.json();
      hideLoading();
      if (data.success) {
        showToast('Bill deleted.', 'success');
        document.getElementById(`bill_row_${id}`)?.remove();
      } else {
        showToast(data.error || 'Delete failed.', 'error');
      }
    } catch { hideLoading(); showToast('Error deleting bill.', 'error'); }
  });
}
window.deleteBill = deleteBill;

// Auto-dismiss alerts
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => el.remove(), 4000);
});
