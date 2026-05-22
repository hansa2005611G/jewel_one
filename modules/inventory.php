<?php
require_once '../includes/auth.php';
requireAdmin();
$db   = getDB();
$csrf = generateCSRF();
$msg  = '';

$currency = getSetting('currency_symbol', 'Rs.');

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $sku             = trim($_POST['sku'] ?? '');
        $name            = trim($_POST['name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $current_stock   = (float)($_POST['current_stock'] ?? 0);
        $min_stock_level = (float)($_POST['min_stock_level'] ?? 5);
        $cost_price      = (float)($_POST['cost_price'] ?? 0);
        $unit_price      = (float)($_POST['unit_price'] ?? 0);

        if (empty($sku)) $sku = null;

        if ($name) {
            try {
                $stmt = $db->prepare("INSERT INTO products (sku, name, category, current_stock, min_stock_level, cost_price, unit_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sku, $name, $category, $current_stock, $min_stock_level, $cost_price, $unit_price]);
                $msg = ['type' => 'success', 'text' => 'Product added successfully.'];
            } catch (Exception $e) {
                $msg = ['type' => 'error', 'text' => 'Product name or SKU already exists.'];
            }
        } else {
            $msg = ['type' => 'error', 'text' => 'Product Name is required.'];
        }
    } elseif ($action === 'edit') {
        $id              = (int)($_POST['id'] ?? 0);
        $sku             = trim($_POST['sku'] ?? '');
        $name            = trim($_POST['name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $current_stock   = (float)($_POST['current_stock'] ?? 0);
        $min_stock_level = (float)($_POST['min_stock_level'] ?? 5);
        $cost_price      = (float)($_POST['cost_price'] ?? 0);
        $unit_price      = (float)($_POST['unit_price'] ?? 0);

        if (empty($sku)) $sku = null;

        if ($id && $name) {
            try {
                $stmt = $db->prepare("UPDATE products SET sku = ?, name = ?, category = ?, current_stock = ?, min_stock_level = ?, cost_price = ?, unit_price = ? WHERE id = ?");
                $stmt->execute([$sku, $name, $category, $current_stock, $min_stock_level, $cost_price, $unit_price, $id]);
                $msg = ['type' => 'success', 'text' => 'Product updated successfully.'];
            } catch (Exception $e) {
                $msg = ['type' => 'error', 'text' => 'Product name or SKU already exists.'];
            }
        } else {
            $msg = ['type' => 'error', 'text' => 'Product ID and Name are required.'];
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $msg = ['type' => 'success', 'text' => 'Product deleted successfully.'];
            } catch (Exception $e) {
                $msg = ['type' => 'error', 'text' => 'Failed to delete product.'];
            }
        }
    }
}

// Fetch products based on filter
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'low_stock') {
    $products = $db->query("SELECT * FROM products WHERE current_stock <= min_stock_level AND current_stock > 0 ORDER BY current_stock ASC")->fetchAll();
} elseif ($filter === 'out_of_stock') {
    $products = $db->query("SELECT * FROM products WHERE current_stock = 0 ORDER BY name ASC")->fetchAll();
} else {
    $products = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-boxes" style="color:var(--gold);margin-right:10px"></i>Inventory <span>Management</span></h1>
    <div class="page-breadcrumb"><?= count($products) ?> products displayed</div>
  </div>
  <button class="btn btn-gold" onclick="document.getElementById('addProductModal').style.display='flex'">
    <i class="fas fa-plus"></i> Add Product
  </button>
</div>

<?php if ($msg): ?>
<div class="card fade-in" style="margin-bottom:16px;border-color:var(--<?= $msg['type']==='success'?'success':'danger' ?>)">
  <div class="card-body" style="padding:14px 18px;color:var(--<?= $msg['type']==='success'?'success':'danger' ?>)">
    <i class="fas fa-<?= $msg['type']==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= h($msg['text']) ?>
  </div>
</div>
<?php endif; ?>

<!-- Quick Filters -->
<div class="fade-in" style="display:flex;gap:10px;margin-bottom:16px">
  <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-gold' : 'btn-outline' ?>" style="font-size:12px;padding:8px 16px"><i class="fas fa-list"></i> All Items</a>
  <a href="?filter=low_stock" class="btn <?= $filter === 'low_stock' ? 'btn-gold' : 'btn-outline' ?>" style="font-size:12px;padding:8px 16px;border-color:var(--warning);color:<?= $filter === 'low_stock' ? 'var(--black)' : 'var(--warning)' ?>"><i class="fas fa-exclamation-triangle"></i> Low Stock</a>
  <a href="?filter=out_of_stock" class="btn <?= $filter === 'out_of_stock' ? 'btn-gold' : 'btn-outline' ?>" style="font-size:12px;padding:8px 16px;border-color:var(--danger);color:<?= $filter === 'out_of_stock' ? 'var(--black)' : 'var(--danger)' ?>"><i class="fas fa-times-circle"></i> Out of Stock</a>
</div>

<!-- Products Table -->
<div class="card fade-in">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product Name</th>
            <th>Category</th>
            <th style="text-align:right">Cost Price</th>
            <th style="text-align:right">Selling Price</th>
            <th style="text-align:right">Margin (Profit)</th>
            <th style="text-align:center">Stock Status</th>
            <th style="text-align:center">Current Stock</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
          <tr>
            <td colspan="9" style="text-align:center;color:var(--gray-4);padding:24px">No products found matching this filter.</td>
          </tr>
          <?php endif; ?>
          <?php foreach ($products as $p): 
            $profit = $p['unit_price'] - $p['cost_price'];
            $marginPct = $p['unit_price'] > 0 ? ($profit / $p['unit_price']) * 100 : 0;
            
            // Stock classification
            if ($p['current_stock'] == 0) {
                $stockClass = 'badge-danger';
                $stockStatus = 'Out of Stock';
            } elseif ($p['current_stock'] <= $p['min_stock_level']) {
                $stockClass = 'badge-warning';
                $stockStatus = 'Low Stock';
            } else {
                $stockClass = 'badge-success';
                $stockStatus = 'In Stock';
            }
          ?>
          <tr>
            <td style="font-family:monospace;color:var(--gold);font-weight:600"><?= h($p['sku'] ?: 'N/A') ?></td>
            <td>
              <span style="font-weight:600;color:var(--white)"><?= h($p['name']) ?></span>
            </td>
            <td><span class="badge badge-info"><?= h($p['category'] ?: 'General') ?></span></td>
            <td style="text-align:right;color:var(--gray-3)"><?= $currency ?> <?= number_format($p['cost_price'], 2) ?></td>
            <td style="text-align:right;font-weight:600;color:var(--gold-light)"><?= $currency ?> <?= number_format($p['unit_price'], 2) ?></td>
            <td style="text-align:right;color:var(--success)">
              <?= $currency ?> <?= number_format($profit, 2) ?>
              <span style="font-size:11px;color:var(--gray-4);display:block"><?= number_format($marginPct, 1) ?>% margin</span>
            </td>
            <td style="text-align:center">
              <span class="badge <?= $stockClass ?>"><?= $stockStatus ?></span>
            </td>
            <td style="text-align:center;font-weight:700;color:var(--white)">
              <?= parseFloatFormat($p['current_stock']) ?>
              <span style="font-size:11px;color:var(--gray-4);display:block;font-weight:400">Min: <?= parseFloatFormat($p['min_stock_level']) ?></span>
            </td>
            <td>
              <div style="display:flex;gap:5px">
                <button class="btn-icon" onclick='openEditProduct(<?= json_encode($p) ?>)' title="Edit Product">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon" style="color:var(--danger)" onclick="deleteProduct(<?= $p['id'] ?>, '<?= h(addslashes($p['name'])) ?>')" title="Delete Product">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProductModal" style="display:none">
  <div class="modal-box" style="max-width:550px;text-align:left">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-plus" style="color:var(--gold)"></i> Add Product to Inventory</h3>
      <button class="modal-close" onclick="document.getElementById('addProductModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">SKU / Item Code (optional)</label>
          <input type="text" name="sku" class="form-control" placeholder="e.g. RING-001">
        </div>
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Gold Ring 22K 4g">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="Gold Ring">Gold Ring</option>
            <option value="Necklace">Necklace</option>
            <option value="Bracelet">Bracelet</option>
            <option value="Earrings">Earrings</option>
            <option value="Pendant">Pendant</option>
            <option value="Bangle">Bangle</option>
            <option value="Gemstone">Gemstone</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Starting Stock *</label>
          <input type="number" name="current_stock" class="form-control" required min="0" step="any" value="50">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Min Stock Level (Reorder Threshold)</label>
          <input type="number" name="min_stock_level" class="form-control" required min="0" step="any" value="5">
        </div>
        <div class="form-group">
          <label class="form-label">Cost Price *</label>
          <input type="number" name="cost_price" class="form-control" required min="0" step="any" placeholder="0.00">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Selling Price (Unit Price) *</label>
        <input type="number" name="unit_price" class="form-control" required min="0" step="any" placeholder="0.00">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addProductModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProductModal" style="display:none">
  <div class="modal-box" style="max-width:550px;text-align:left">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-edit" style="color:var(--gold)"></i> Edit Inventory Product</h3>
      <button class="modal-close" onclick="document.getElementById('editProductModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">SKU / Item Code (optional)</label>
          <input type="text" name="sku" id="editSku" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" id="editName" class="form-control" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" id="editCategory" class="form-control">
            <option value="Gold Ring">Gold Ring</option>
            <option value="Necklace">Necklace</option>
            <option value="Bracelet">Bracelet</option>
            <option value="Earrings">Earrings</option>
            <option value="Pendant">Pendant</option>
            <option value="Bangle">Bangle</option>
            <option value="Gemstone">Gemstone</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Current Stock *</label>
          <input type="number" name="current_stock" id="editCurrentStock" class="form-control" required min="0" step="any">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Min Stock Level (Reorder Threshold)</label>
          <input type="number" name="min_stock_level" id="editMinStockLevel" class="form-control" required min="0" step="any">
        </div>
        <div class="form-group">
          <label class="form-label">Cost Price *</label>
          <input type="number" name="cost_price" id="editCostPrice" class="form-control" required min="0" step="any">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Selling Price (Unit Price) *</label>
        <input type="number" name="unit_price" id="editUnitPrice" class="form-control" required min="0" step="any">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editProductModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Update Product</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditProduct(p) {
  document.getElementById('editId').value = p.id;
  document.getElementById('editSku').value = p.sku || '';
  document.getElementById('editName').value = p.name;
  document.getElementById('editCategory').value = p.category || '';
  document.getElementById('editCurrentStock').value = p.current_stock;
  document.getElementById('editMinStockLevel').value = p.min_stock_level;
  document.getElementById('editCostPrice').value = p.cost_price;
  document.getElementById('editUnitPrice').value = p.unit_price;
  document.getElementById('editProductModal').style.display = 'flex';
}

function deleteProduct(id, name) {
  showConfirm('Delete Product', `Are you sure you want to delete product "${name}"? This action cannot be undone.`, () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
  });
}
</script>

<?php 
// Helper function to format float values in PHP
function parseFloatFormat($val) {
    return (float)$val == (int)$val ? (int)$val : number_format($val, 3);
}

include '../includes/footer.php'; 
?>
