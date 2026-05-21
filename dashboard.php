<?php
require_once 'includes/auth.php';
requireLogin();
$user     = getCurrentUser();
$currency = getSetting('currency_symbol', 'Rs.');
$db       = getDB();

// --- Stats ---
function stat($db, $sql, $params = []) {
    $s = $db->prepare($sql);
    $s->execute($params);
    return $s->fetchColumn();
}

$today         = date('Y-m-d');
$weekStart     = date('Y-m-d', strtotime('monday this week'));
$monthStart    = date('Y-m-01');
$yearStart     = date('Y-01-01');

$todaySales    = stat($db, "SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE DATE(created_at)=? AND status='completed'", [$today]);
$weeklySales   = stat($db, "SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE created_at>=? AND status='completed'", [$weekStart]);
$monthlySales  = stat($db, "SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE created_at>=? AND status='completed'", [$monthStart]);
$yearlySales   = stat($db, "SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE created_at>=? AND status='completed'", [$yearStart]);
$totalBills    = stat($db, "SELECT COUNT(*) FROM bills WHERE status='completed'");
$todayBills    = stat($db, "SELECT COUNT(*) FROM bills WHERE DATE(created_at)=? AND status='completed'", [$today]);
$avgBill       = stat($db, "SELECT COALESCE(AVG(grand_total),0) FROM bills WHERE status='completed'");
$totalDiscount = stat($db, "SELECT COALESCE(SUM(total_discount),0) FROM bills WHERE status='completed'");

// Last 7 days chart data
$chartStmt = $db->query("SELECT DATE(created_at) as d, COALESCE(SUM(grand_total),0) as total
    FROM bills WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status='completed'
    GROUP BY DATE(created_at) ORDER BY d ASC");
$chartData = $chartStmt->fetchAll();
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D d', strtotime($d));
    $found = array_filter($chartData, fn($r) => $r['d'] === $d);
    $chartValues[] = $found ? array_values($found)[0]['total'] : 0;
}

// Best selling products
$bestStmt = $db->query("SELECT product_name, SUM(quantity) as total_qty, SUM(final_total) as total_rev
    FROM bill_items GROUP BY product_name ORDER BY total_qty DESC LIMIT 8");
$bestProducts = $bestStmt->fetchAll();

// Recent bills
$recentStmt = $db->query("SELECT b.*, u.full_name as cashier_name FROM bills b
    JOIN users u ON b.cashier_id = u.id WHERE b.status='completed'
    ORDER BY b.created_at DESC LIMIT 10");
$recentBills = $recentStmt->fetchAll();

// Top cashiers
$cashierStmt = $db->query("SELECT u.full_name, COUNT(b.id) as bill_count, COALESCE(SUM(b.grand_total),0) as total
    FROM users u LEFT JOIN bills b ON b.cashier_id = u.id AND b.status='completed'
    WHERE u.role='cashier' GROUP BY u.id ORDER BY total DESC LIMIT 5");
$topCashiers = $cashierStmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1>Dashboard <span>Overview</span></h1>
    <div class="page-breadcrumb">Welcome back, <?= h($user['full_name']) ?> · <?= date('l, F j Y') ?></div>
  </div>
  <a href="billing.php" class="btn btn-gold"><i class="fas fa-plus"></i> New Bill</a>
</div>

<!-- Stats Grid -->
<div class="stats-grid fade-in">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-sun"></i></div>
    <div class="stat-value"><?= number_format($todaySales, 0) ?></div>
    <div class="stat-label">Today's Sales</div>
    <div class="stat-change up"><i class="fas fa-arrow-up"></i><?= $todayBills ?> bills</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-calendar-week"></i></div>
    <div class="stat-value"><?= number_format($weeklySales, 0) ?></div>
    <div class="stat-label">Weekly Sales</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
    <div class="stat-value"><?= number_format($monthlySales, 0) ?></div>
    <div class="stat-label">Monthly Sales</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
    <div class="stat-value"><?= number_format($yearlySales, 0) ?></div>
    <div class="stat-label">Yearly Revenue</div>
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
    <div class="stat-value"><?= number_format($totalDiscount, 0) ?></div>
    <div class="stat-label">Total Discounts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-gem"></i></div>
    <div class="stat-value"><?= $currency ?></div>
    <div class="stat-label">Currency</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2" style="margin-bottom:20px">
  <div class="card fade-in">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-chart-area"></i> Last 7 Days Sales</span>
    </div>
    <div class="card-body">
      <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
    </div>
  </div>

  <div class="card fade-in">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-trophy"></i> Best Selling Products</span>
    </div>
    <div class="card-body">
      <?php if (empty($bestProducts)): ?>
      <div class="empty-state" style="padding:30px 20px">
        <i class="fas fa-gem"></i><p>No product data yet.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($bestProducts as $i => $p): ?>
            <tr>
              <td><span class="badge badge-gold"><?= $i+1 ?></span></td>
              <td><?= h($p['product_name']) ?></td>
              <td><?= number_format($p['total_qty'], 2) ?></td>
              <td style="color:var(--gold)"><?= $currency ?> <?= number_format($p['total_rev'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Transactions & Top Cashiers -->
<div class="grid-2" style="margin-bottom:20px">
  <div class="card fade-in">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-history"></i> Recent Transactions</span>
      <a href="bill_history.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($recentBills)): ?>
      <div class="empty-state"><i class="fas fa-receipt"></i><p>No transactions yet.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Bill No.</th><th>Customer</th><th>Cashier</th><th>Total</th><th>Method</th></tr></thead>
          <tbody>
            <?php foreach ($recentBills as $b): ?>
            <tr>
              <td><a href="print_receipt.php?id=<?= $b['id'] ?>" style="color:var(--gold)"><?= h($b['bill_number']) ?></a></td>
              <td><?= h($b['customer_name'] ?: 'Walk-in') ?></td>
              <td><?= h($b['cashier_name']) ?></td>
              <td style="font-weight:600"><?= $currency ?> <?= number_format($b['grand_total'], 2) ?></td>
              <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$b['payment_method'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card fade-in">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-users"></i> Top Cashiers</span>
    </div>
    <div class="card-body">
      <?php if (empty($topCashiers)): ?>
      <div class="empty-state"><i class="fas fa-user-slash"></i><p>No cashier data yet.</p></div>
      <?php else: ?>
      <?php foreach ($topCashiers as $i => $c): ?>
      <div style="display:flex;align-items:center;gap:14px;padding:10px 0;border-bottom:1px solid rgba(201,168,76,0.06)">
        <div style="width:32px;height:32px;border-radius:50%;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700;font-size:13px"><?= $i+1 ?></div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px"><?= h($c['full_name']) ?></div>
          <div style="font-size:11px;color:var(--gray-4)"><?= number_format($c['bill_count']) ?> bills</div>
        </div>
        <div style="color:var(--gold);font-family:var(--font-display);font-size:16px;font-weight:700"><?= $currency ?> <?= number_format($c['total'], 0) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Sales chart
const ctx = document.getElementById('salesChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{
        label: 'Sales (<?= $currency ?>)',
        data: <?= json_encode(array_map('floatval', $chartValues)) ?>,
        borderColor: '#C9A84C',
        backgroundColor: 'rgba(201,168,76,0.1)',
        borderWidth: 2.5,
        pointBackgroundColor: '#C9A84C',
        pointRadius: 5,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#777' } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#777', callback: v => '<?= $currency ?> ' + v.toLocaleString() } }
      }
    }
  });
}
</script>

<?php include 'includes/footer.php'; ?>
