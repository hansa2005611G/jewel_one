<?php
require_once 'includes/auth.php';
requireLogin();
$user     = getCurrentUser();
$db       = getDB();
$currency = getSetting('currency_symbol', 'Rs.');
$csrf     = generateCSRF();

// Filters
$search   = trim($_GET['search']   ?? '');
$cashier  = (int)($_GET['cashier'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$perPage  = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

// Build WHERE
$where  = ["b.status != 'cancelled'"];
$params = [];

if ($search) {
    $where[]  = "(b.bill_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($cashier && $user['role'] === 'admin') {
    $where[]  = "b.cashier_id = ?";
    $params[] = $cashier;
} elseif ($user['role'] === 'cashier') {
    $where[]  = "b.cashier_id = ?";
    $params[] = $user['id'];
}
if ($dateFrom) { $where[] = "DATE(b.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(b.created_at) <= ?"; $params[] = $dateTo;   }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM bills b $whereSQL");
$countStmt->execute($params);
$total     = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Fetch
$listStmt = $db->prepare("SELECT b.*, u.full_name as cashier_name FROM bills b
    JOIN users u ON b.cashier_id=u.id $whereSQL ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$bills    = $listStmt->fetchAll();

// Cashiers for filter
$cashiersStmt = $db->query("SELECT id, full_name FROM users WHERE role='cashier' AND status='active' ORDER BY full_name");
$cashiers     = $cashiersStmt->fetchAll();

include 'includes/header.php';
?>
<meta name="csrf" content="<?= h($csrf) ?>">

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-receipt" style="color:var(--gold);margin-right:10px"></i>Bill <span>History</span></h1>
    <div class="page-breadcrumb">Showing <?= number_format($total) ?> bills</div>
  </div>
  <a href="billing.php" class="btn btn-gold"><i class="fas fa-plus"></i> New Bill</a>
</div>

<!-- Search & Filters -->
<div class="card fade-in" style="margin-bottom:20px">
  <div class="card-body">
    <form method="GET" id="filterForm">
      <div class="form-row" style="align-items:flex-end">
        <div class="form-group" style="flex:2">
          <label class="form-label">Search</label>
          <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Bill number, customer name, phone..."
              value="<?= h($search) ?>">
          </div>
        </div>
        <?php if ($user['role'] === 'admin'): ?>
        <div class="form-group">
          <label class="form-label">Cashier</label>
          <select name="cashier" class="form-control">
            <option value="">All Cashiers</option>
            <?php foreach ($cashiers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cashier == $c['id'] ? 'selected' : '' ?>><?= h($c['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
        </div>
        <div class="form-group" style="flex:0;min-width:auto">
          <button type="submit" class="btn btn-gold"><i class="fas fa-search"></i> Filter</button>
        </div>
        <div class="form-group" style="flex:0;min-width:auto">
          <a href="bill_history.php" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Bills Table -->
<div class="card fade-in">
  <div class="card-body" style="padding:0">
    <?php if (empty($bills)): ?>
    <div class="empty-state">
      <i class="fas fa-receipt"></i>
      <h3>No Bills Found</h3>
      <p>No bills match your search criteria.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Bill No.</th>
            <th>Date & Time</th>
            <th>Customer</th>
            <th>Cashier</th>
            <th>Items</th>
            <th>Total</th>
            <th>Discount</th>
            <th>Paid</th>
            <th>Method</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bills as $b): ?>
          <?php
            $itemCount = (int)$db->prepare("SELECT COUNT(*) FROM bill_items WHERE bill_id=?")->execute([$b['id']]) ? $db->query("SELECT COUNT(*) FROM bill_items WHERE bill_id={$b['id']}")->fetchColumn() : 0;
          ?>
          <tr id="bill_row_<?= $b['id'] ?>">
            <td>
              <a href="print_receipt.php?id=<?= $b['id'] ?>" style="color:var(--gold);font-weight:700">
                <?= h($b['bill_number']) ?>
              </a>
            </td>
            <td style="white-space:nowrap">
              <div><?= date('d M Y', strtotime($b['created_at'])) ?></div>
              <div style="font-size:11px;color:var(--gray-4)"><?= date('h:i A', strtotime($b['created_at'])) ?></div>
            </td>
            <td>
              <div><?= h($b['customer_name'] ?: 'Walk-in') ?></div>
              <?php if ($b['customer_phone']): ?>
              <div style="font-size:11px;color:var(--gray-4)"><?= h($b['customer_phone']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= h($b['cashier_name']) ?></td>
            <td style="text-align:center"><span class="badge badge-info"><?= $itemCount ?></span></td>
            <td style="font-weight:700;color:var(--gold);font-family:var(--font-display);font-size:15px">
              <?= $currency ?> <?= number_format($b['grand_total'], 2) ?>
            </td>
            <td style="color:var(--danger)">
              <?php if ($b['total_discount'] > 0): ?>
              -<?= $currency ?> <?= number_format($b['total_discount'], 2) ?>
              <?php else: ?>
              <span style="color:var(--gray-4)">—</span>
              <?php endif; ?>
            </td>
            <td><?= $currency ?> <?= number_format($b['paid_amount'], 2) ?></td>
            <td><span class="badge badge-gold"><?= ucfirst(str_replace('_',' ',$b['payment_method'])) ?></span></td>
            <td>
              <?php $bc = $b['status'] === 'completed' ? 'badge-success' : 'badge-gold'; ?>
              <span class="badge <?= $bc ?>"><?= ucfirst($b['status']) ?></span>
            </td>
            <td>
              <div style="display:flex;gap:5px">
                <a href="print_receipt.php?id=<?= $b['id'] ?>" class="btn-icon" title="View/Print">
                  <i class="fas fa-print"></i>
                </a>
                <a href="billing.php?duplicate=<?= $b['id'] ?>" class="btn-icon" title="Duplicate">
                  <i class="fas fa-copy"></i>
                </a>
                <?php if ($user['role'] === 'admin'): ?>
                <button class="btn-icon" style="color:var(--danger);border-color:rgba(224,82,96,0.2)" onclick="deleteBill(<?= $b['id'] ?>)" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding:16px">
      <?php if ($page > 1): ?>
      <a class="page-btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>"><i class="fas fa-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
      <a class="page-btn <?= $p==$page?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a class="page-btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
