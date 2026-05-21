<?php
require_once '../includes/auth.php';
requireAdmin();
$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['shop_name','shop_address','shop_phone','shop_email','tax_enabled','tax_percentage',
               'currency_symbol','receipt_footer','thermal_size','theme_mode','receipt_copies'];
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
           ->execute([$f, $val, $val]);
    }

    // Handle logo upload
    if (!empty($_FILES['logo']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
            $filename = 'logo_' . time() . '.' . $ext;
            $dest     = dirname(__DIR__) . '/uploads/logo/' . $filename;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('logo_path',?) ON DUPLICATE KEY UPDATE setting_value=?")
                   ->execute([$filename, $filename]);
            }
        }
    }
    $msg = ['type'=>'success','text'=>'Settings saved successfully.'];
}

// Load all settings
$settingsRows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['setting_key']] = $r['setting_value'];

function s($settings, $key, $default='') { return $settings[$key] ?? $default; }

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-cog" style="color:var(--gold);margin-right:10px"></i>System <span>Settings</span></h1>
    <div class="page-breadcrumb">Configure shop details, tax, receipts, and display</div>
  </div>
</div>

<?php if ($msg): ?>
<div class="card fade-in" style="margin-bottom:16px;border-color:var(--success)">
  <div class="card-body" style="padding:14px 18px;color:var(--success)">
    <i class="fas fa-check-circle"></i> <?= h($msg['text']) ?>
  </div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="grid-2 fade-in" style="gap:20px">

  <!-- Shop Info -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-store"></i> Shop Information</span></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Shop Name</label>
        <input type="text" name="shop_name" class="form-control" value="<?= h(s($settings,'shop_name','JEWEL ONE 1')) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Address</label>
        <textarea name="shop_address" class="form-control" rows="2"><?= h(s($settings,'shop_address','Matale, Sri Lanka')) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input type="text" name="shop_phone" class="form-control" value="<?= h(s($settings,'shop_phone')) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="shop_email" class="form-control" value="<?= h(s($settings,'shop_email')) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Shop Logo</label>
        <?php $logo = s($settings,'logo_path'); ?>
        <?php if ($logo): ?>
        <div style="margin-bottom:10px">
          <img src="<?= APP_URL ?>/uploads/logo/<?= h($logo) ?>" style="max-height:60px;border:1px solid rgba(201,168,76,0.3);padding:4px;border-radius:6px" alt="Logo">
        </div>
        <?php endif; ?>
        <input type="file" name="logo" class="form-control" accept="image/*">
        <span style="font-size:11px;color:var(--gray-4)">PNG, JPG, WEBP. Max 2MB.</span>
      </div>
    </div>
  </div>

  <!-- Tax & Currency -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><span class="card-title"><i class="fas fa-percentage"></i> Tax & Currency</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Currency Symbol</label>
          <input type="text" name="currency_symbol" class="form-control" value="<?= h(s($settings,'currency_symbol','Rs.')) ?>" placeholder="Rs.">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;gap:10px;justify-content:space-between">
            Enable Tax
            <label class="toggle-switch">
              <input type="checkbox" name="tax_enabled" value="1" <?= s($settings,'tax_enabled')=='1'?'checked':'' ?>>
              <span class="toggle-slider"></span>
            </label>
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">Tax Percentage (%)</label>
          <input type="number" name="tax_percentage" class="form-control" min="0" max="100" step="0.01"
            value="<?= h(s($settings,'tax_percentage','0')) ?>" placeholder="e.g. 8">
        </div>
      </div>
    </div>

    <!-- Receipt Settings -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-receipt"></i> Receipt Settings</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Receipt Footer Text</label>
          <input type="text" name="receipt_footer" class="form-control" value="<?= h(s($settings,'receipt_footer','Thank You Come Again')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Thermal Printer Size</label>
          <select name="thermal_size" class="form-control">
            <option value="58mm" <?= s($settings,'thermal_size')==='58mm'?'selected':'' ?>>58mm</option>
            <option value="80mm" <?= s($settings,'thermal_size','80mm')==='80mm'?'selected':'' ?>>80mm</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Receipt Copies</label>
          <input type="number" name="receipt_copies" class="form-control" min="1" max="3"
            value="<?= h(s($settings,'receipt_copies','1')) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- UI Settings -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-palette"></i> UI Settings</span></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Default Theme</label>
        <select name="theme_mode" class="form-control">
          <option value="dark"  <?= s($settings,'theme_mode','dark')==='dark' ?'selected':'' ?>>Dark Mode (Default)</option>
          <option value="light" <?= s($settings,'theme_mode')==='light'?'selected':'' ?>>Light Mode</option>
        </select>
      </div>
    </div>
  </div>

</div><!-- grid-2 -->

<div style="margin-top:20px;text-align:right">
  <button type="submit" class="btn btn-gold" style="padding:14px 32px;font-size:14px">
    <i class="fas fa-save"></i> Save All Settings
  </button>
</div>
</form>

<?php include '../includes/footer.php'; ?>
