<?php
require_once '../includes/auth.php';
requireAdmin();
$db  = getDB();
$msg = '';

if (isset($_POST['action']) && $_POST['action'] === 'backup') {
    $filename  = 'jewel_one_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backupDir = dirname(__DIR__) . '/backup/';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $filepath  = $backupDir . $filename;

    // Generate SQL dump using PDO
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql    = "-- JEWEL ONE POS BACKUP\n-- Date: " . date('Y-m-d H:i:s') . "\n-- DB: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Structure
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql   .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql   .= $create['Create Table'] . ";\n\n";

        // Data
        $rows   = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols   = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql   .= "INSERT INTO `$table` ($cols) VALUES\n";
            $vals   = [];
            foreach ($rows as $row) {
                $escaped = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $row);
                $vals[] = '(' . implode(', ', $escaped) . ')';
            }
            $sql .= implode(",\n", $vals) . ";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($filepath, $sql);

    logAction($_SESSION['user_id'], 'DB_BACKUP', "Backup created: $filename");

    if (isset($_POST['download'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    $msg = ['type'=>'success','text'=>"Backup created: $filename"];
}

// List backups
$backupDir = dirname(__DIR__) . '/backup/';
$backups   = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '*.sql') as $f) {
        $backups[] = ['name'=>basename($f),'size'=>filesize($f),'time'=>filemtime($f)];
    }
    usort($backups, fn($a,$b)=>$b['time']-$a['time']);
}

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-database" style="color:var(--gold);margin-right:10px"></i>Database <span>Backup</span></h1>
    <div class="page-breadcrumb"><?= count($backups) ?> backup files stored</div>
  </div>
</div>

<?php if ($msg): ?>
<div class="card fade-in" style="margin-bottom:16px;border-color:var(--success)">
  <div class="card-body" style="padding:14px 18px;color:var(--success)">
    <i class="fas fa-check-circle"></i> <?= h($msg['text']) ?>
  </div>
</div>
<?php endif; ?>

<!-- Backup Actions -->
<div class="grid-2 fade-in" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-download"></i> Create Backup</span></div>
    <div class="card-body">
      <p style="color:var(--gray-3);font-size:13px;margin-bottom:20px;line-height:1.6">
        Creates a complete SQL dump of the entire database including all bills, users, and settings.
        Backups are stored on the server and can be downloaded.
      </p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <form method="POST">
          <input type="hidden" name="action" value="backup">
          <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Save Backup on Server</button>
        </form>
        <form method="POST">
          <input type="hidden" name="action" value="backup">
          <input type="hidden" name="download" value="1">
          <button type="submit" class="btn btn-outline"><i class="fas fa-download"></i> Backup & Download</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Database Info</span></div>
    <div class="card-body">
      <?php
      $tables = $db->query("SHOW TABLE STATUS")->fetchAll();
      $totalRows = 0; $totalSize = 0;
      foreach ($tables as $t) { $totalRows += $t['Rows']; $totalSize += $t['Data_length'] + $t['Index_length']; }
      ?>
      <div class="summary-row"><span class="s-label">Database</span><span class="s-value"><?= DB_NAME ?></span></div>
      <div class="summary-row"><span class="s-label">Tables</span><span class="s-value"><?= count($tables) ?></span></div>
      <div class="summary-row"><span class="s-label">Total Rows</span><span class="s-value"><?= number_format($totalRows) ?></span></div>
      <div class="summary-row"><span class="s-label">DB Size</span><span class="s-value"><?= round($totalSize/1024, 1) ?> KB</span></div>
    </div>
  </div>
</div>

<!-- Backup Files List -->
<div class="card fade-in">
  <div class="card-header"><span class="card-title"><i class="fas fa-folder-open"></i> Stored Backups</span></div>
  <div class="card-body" style="padding:0">
    <?php if (empty($backups)): ?>
    <div class="empty-state"><i class="fas fa-database"></i><h3>No Backups Yet</h3><p>Create your first backup above.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($backups as $b): ?>
          <tr>
            <td><i class="fas fa-file-code" style="color:var(--gold);margin-right:8px"></i><?= h($b['name']) ?></td>
            <td><?= round($b['size']/1024, 1) ?> KB</td>
            <td><?= date('d M Y h:i A', $b['time']) ?></td>
            <td>
              <a href="download_backup.php?file=<?= urlencode($b['name']) ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-download"></i> Download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
