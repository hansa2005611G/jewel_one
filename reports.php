<?php
require_once 'includes/auth.php';
requireAdmin();
$db       = getDB();
$currency = getSetting('currency_symbol', 'Rs.');

$period    = $_GET['period'] ?? 'monthly';
$dateFrom  = $_GET['date_from'] ?? date('Y-m-01');
$dateTo    = $_GET['date_to']   ?? date('Y-m-d');

// Summary stats
$totalRev   = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$totalRev->execute([$dateFrom, $dateTo]); $totalRev = $totalRev->fetchColumn();

$totalBills = $db->prepare("SELECT COUNT(*) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$totalBills->execute([$dateFrom, $dateTo]); $totalBills = $totalBills->fetchColumn();

$totalDisc  = $db->prepare("SELECT COALESCE(SUM(total_discount),0) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$totalDisc->execute([$dateFrom, $dateTo]); $totalDisc = $totalDisc->fetchColumn();

$avgBill    = $totalBills > 0 ? $totalRev / $totalBills : 0;

// Daily breakdown
$dailyStmt = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as bill_count, SUM(grand_total) as revenue, SUM(total_discount) as discounts
    FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY DATE(created_at) ORDER BY d DESC");
$dailyStmt->execute([$dateFrom, $dateTo]);
$dailyData  = $dailyStmt->fetchAll();

// Best products
$prodStmt   = $db->prepare("SELECT bi.product_name, SUM(bi.quantity) as total_qty, SUM(bi.final_total) as total_rev, COUNT(DISTINCT bi.bill_id) as bill_count
    FROM bill_items bi JOIN bills b ON bi.bill_id=b.id WHERE DATE(b.created_at) BETWEEN ? AND ? AND b.status='completed'
    GROUP BY bi.product_name ORDER BY total_rev DESC LIMIT 15");
$prodStmt->execute([$dateFrom, $dateTo]);
$topProducts = $prodStmt->fetchAll();

// Cashier performance
$cashStmt   = $db->prepare("SELECT u.full_name, COUNT(b.id) as bill_count, COALESCE(SUM(b.grand_total),0) as revenue, COALESCE(SUM(b.total_discount),0) as discounts
    FROM users u LEFT JOIN bills b ON b.cashier_id=u.id AND DATE(b.created_at) BETWEEN ? AND ? AND b.status='completed'
    WHERE u.role='cashier' GROUP BY u.id ORDER BY revenue DESC");
$cashStmt->execute([$dateFrom, $dateTo]);
$cashierData = $cashStmt->fetchAll();

// Payment method breakdown
$payStmt    = $db->prepare("SELECT payment_method, COUNT(*) as cnt, SUM(grand_total) as total FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY payment_method");
$payStmt->execute([$dateFrom, $dateTo]);
$payData    = $payStmt->fetchAll();

// Chart: revenue by day
$chartDates  = array_column($dailyData, 'd');
$chartRevs   = array_column($dailyData, 'revenue');
$chartBills  = array_column($dailyData, 'bill_count');
// Reverse for chronological
$chartDates  = array_reverse($chartDates);
$chartRevs   = array_reverse($chartRevs);
$chartBills  = array_reverse($chartBills);

include 'includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-chart-bar" style="color:var(--gold);margin-right:10px"></i>Reports & <span>Analytics</span></h1>
    <div class="page-breadcrumb">Financial overview and performance metrics</div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>" class="btn btn-outline"><i class="fas fa-file-pdf"></i> Export PDF</a>
    <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
  </div>
</div>

<!-- Date Filter -->
<div class="card fade-in" style="margin-bottom:20px">
  <div class="card-body">
    <form method="GET">
      <div class="form-row" style="align-items:flex-end">
        <div class="form-group">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Quick Select</label>
          <select name="period" class="form-control" onchange="applyPeriod(this.value)">
            <option value="today" <?= $period==='today'?'selected':'' ?>>Today</option>
            <option value="week"  <?= $period==='week' ?'selected':'' ?>>This Week</option>
            <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>This Month</option>
            <option value="yearly"  <?= $period==='yearly' ?'selected':'' ?>>This Year</option>
            <option value="custom"  <?= $period==='custom' ?'selected':'' ?>>Custom Range</option>
          </select>
        </div>
        <div class="form-group" style="flex:0;min-width:auto">
          <button type="submit" class="btn btn-gold"><i class="fas fa-sync"></i> Update</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-coins"></i></div>
    <div class="stat-value"><?= number_format($totalRev, 0) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
    <div class="stat-value"><?= number_format($totalBills) ?></div>
    <div class="stat-label">Total Bills</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-calculator"></i></div>
    <div class="stat-value"><?= number_format($avgBill, 0) ?></div>
    <div class="stat-label">Average Bill</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-tags"></i></div>
    <div class="stat-value"><?= number_format($totalDisc, 0) ?></div>
    <div class="stat-label">Total Discounts</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2 fade-in" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-chart-line"></i> Revenue Trend</span></div>
    <div class="card-body">
      <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-chart-pie"></i> Payment Methods</span></div>
    <div class="card-body">
      <div class="chart-wrap"><canvas id="paymentChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Daily Breakdown -->
<div class="card fade-in" style="margin-bottom:20px">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-calendar-day"></i> Daily Breakdown</span>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($dailyData)): ?>
    <div class="empty-state"><i class="fas fa-chart-bar"></i><p>No data for selected period.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Bills</th><th>Revenue</th><th>Discounts</th><th>Net Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($dailyData as $d): ?>
          <tr>
            <td><?= date('l, d M Y', strtotime($d['d'])) ?></td>
            <td><span class="badge badge-info"><?= $d['bill_count'] ?></span></td>
            <td style="color:var(--gold);font-weight:600"><?= $currency ?> <?= number_format($d['revenue'],2) ?></td>
            <td style="color:var(--danger)">-<?= $currency ?> <?= number_format($d['discounts'],2) ?></td>
            <td style="color:var(--success);font-weight:700"><?= $currency ?> <?= number_format($d['revenue']-$d['discounts'],2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Products & Cashiers -->
<div class="grid-2 fade-in">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-gem"></i> Top Products</span></div>
    <div class="card-body" style="padding:0">
      <?php if (empty($topProducts)): ?>
      <div class="empty-state"><i class="fas fa-gem"></i><p>No product data.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($topProducts as $i => $p): ?>
            <tr>
              <td><span class="badge badge-gold"><?= $i+1 ?></span></td>
              <td><?= h($p['product_name']) ?></td>
              <td><?= number_format($p['total_qty'],2) ?></td>
              <td style="color:var(--gold);font-weight:700"><?= $currency ?> <?= number_format($p['total_rev'],2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-users"></i> Cashier Performance</span></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Cashier</th><th>Bills</th><th>Revenue</th><th>Discounts</th></tr></thead>
          <tbody>
            <?php foreach ($cashierData as $c): ?>
            <tr>
              <td><?= h($c['full_name']) ?></td>
              <td><?= $c['bill_count'] ?></td>
              <td style="color:var(--gold);font-weight:700"><?= $currency ?> <?= number_format($c['revenue'],2) ?></td>
              <td style="color:var(--danger)">-<?= $currency ?> <?= number_format($c['discounts'],2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function applyPeriod(val) {
  const today = new Date();
  const fmt = d => d.toISOString().split('T')[0];
  let from, to = fmt(today);
  if (val === 'today') from = to;
  else if (val === 'week') { const mon = new Date(today); mon.setDate(today.getDate()-today.getDay()+1); from=fmt(mon); }
  else if (val === 'monthly') from = fmt(new Date(today.getFullYear(),today.getMonth(),1));
  else if (val === 'yearly')  from = fmt(new Date(today.getFullYear(),0,1));
  else return;
  document.querySelector('[name=date_from]').value = from;
  document.querySelector('[name=date_to]').value   = to;
}

// Revenue Chart
const rCtx = document.getElementById('revenueChart')?.getContext('2d');
if (rCtx) {
  new Chart(rCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_map(fn($d)=>date('d M',strtotime($d)), $chartDates)) ?>,
      datasets: [{
        label: 'Revenue',
        data: <?= json_encode(array_map('floatval',$chartRevs)) ?>,
        backgroundColor: 'rgba(201,168,76,0.5)',
        borderColor: '#C9A84C',
        borderWidth: 2,
        borderRadius: 5
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#777', maxTicksLimit: 10 } },
        y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#777', callback: v => '<?= $currency ?> '+v.toLocaleString() } }
      }
    }
  });
}

// Payment chart
const pCtx = document.getElementById('paymentChart')?.getContext('2d');
const payData = <?= json_encode(array_values($payData)) ?>;
if (pCtx && payData.length) {
  new Chart(pCtx, {
    type: 'doughnut',
    data: {
      labels: payData.map(p => p.payment_method.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase())),
      datasets: [{
        data: payData.map(p => parseFloat(p.total)),
        backgroundColor: ['rgba(201,168,76,0.7)', 'rgba(91,155,213,0.7)', 'rgba(76,175,130,0.7)'],
        borderColor: ['#C9A84C','#5B9BD5','#4CAF82'],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#aaa', padding: 16 } } }
    }
  });
}
</script>

<?php include 'includes/footer.php'; ?>
