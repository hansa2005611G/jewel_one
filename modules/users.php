<?php
require_once '../includes/auth.php';
requireAdmin();
$db   = getDB();
$csrf = generateCSRF();
$msg  = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = in_array($_POST['role']??'', ['admin','cashier']) ? $_POST['role'] : 'cashier';
        $email     = trim($_POST['email'] ?? '');

        if ($username && $fullName && strlen($password) >= 6) {
            try {
                $db->prepare("INSERT INTO users (username,password,full_name,email,role) VALUES (?,?,?,?,?)")
                   ->execute([$username, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), $fullName, $email, $role]);
                $msg = ['type'=>'success', 'text'=>'User created successfully.'];
            } catch (Exception $e) {
                $msg = ['type'=>'error', 'text'=>'Username already exists.'];
            }
        } else {
            $msg = ['type'=>'error', 'text'=>'All fields required. Password min 6 characters.'];
        }
    } elseif ($action === 'toggle_status') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            $curr = $db->prepare("SELECT status FROM users WHERE id=?"); $curr->execute([$uid]);
            $curr = $curr->fetchColumn();
            $new  = $curr === 'active' ? 'inactive' : 'active';
            $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new, $uid]);
            $msg  = ['type'=>'success', 'text'=>"User status changed to $new."];
        }
    } elseif ($action === 'reset_password') {
        $uid  = (int)($_POST['uid'] ?? 0);
        $np   = $_POST['new_password'] ?? '';
        if ($uid && strlen($np) >= 6) {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($np, PASSWORD_BCRYPT, ['cost'=>12]), $uid]);
            $msg = ['type'=>'success', 'text'=>'Password reset successfully.'];
        } else {
            $msg = ['type'=>'error', 'text'=>'Password min 6 characters.'];
        }
    }
}

$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM bills WHERE cashier_id=u.id AND status='completed') as bill_count FROM users u ORDER BY u.created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <h1><i class="fas fa-users" style="color:var(--gold);margin-right:10px"></i>Manage <span>Users</span></h1>
    <div class="page-breadcrumb"><?= count($users) ?> total users</div>
  </div>
  <button class="btn btn-gold" onclick="document.getElementById('addUserModal').style.display='flex'">
    <i class="fas fa-user-plus"></i> Add User
  </button>
</div>

<?php if ($msg): ?>
<div class="card fade-in" style="margin-bottom:16px;border-color:var(--<?= $msg['type']==='success'?'success':'danger' ?>)">
  <div class="card-body" style="padding:14px 18px;color:var(--<?= $msg['type']==='success'?'success':'danger' ?>)">
    <i class="fas fa-<?= $msg['type']==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= h($msg['text']) ?>
  </div>
</div>
<?php endif; ?>

<!-- Users Table -->
<div class="card fade-in">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Username</th>
            <th>Role</th>
            <th>Email</th>
            <th>Bills Created</th>
            <th>Last Login</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:16px">
                  <i class="fas fa-user-circle"></i>
                </div>
                <span style="font-weight:600"><?= h($u['full_name']) ?></span>
              </div>
            </td>
            <td style="color:var(--gray-3)">@<?= h($u['username']) ?></td>
            <td><span class="badge <?= $u['role']==='admin'?'badge-gold':'badge-info' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td style="color:var(--gray-3)"><?= h($u['email'] ?: '—') ?></td>
            <td style="text-align:center"><span class="badge badge-success"><?= $u['bill_count'] ?></span></td>
            <td style="color:var(--gray-4);font-size:12px">
              <?= $u['last_login'] ? date('d M Y h:i A', strtotime($u['last_login'])) : 'Never' ?>
            </td>
            <td>
              <span class="badge <?= $u['status']==='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($u['status']) ?></span>
            </td>
            <td>
              <div style="display:flex;gap:5px">
                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                  <button type="submit" class="btn-icon" title="Toggle Status">
                    <i class="fas fa-<?= $u['status']==='active'?'ban':'check' ?>"></i>
                  </button>
                </form>
                <button class="btn-icon" onclick="openResetPwd(<?= $u['id'] ?>, '<?= h($u['full_name']) ?>')" title="Reset Password">
                  <i class="fas fa-key"></i>
                </button>
                <?php else: ?>
                <span style="color:var(--gray-4);font-size:12px;padding:4px 8px">Current User</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none">
  <div class="modal-box" style="max-width:500px;text-align:left">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-user-plus" style="color:var(--gold)"></i> Add New User</h3>
      <button class="modal-close" onclick="document.getElementById('addUserModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kasun Perera">
        </div>
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" name="username" class="form-control" required placeholder="e.g. kasun123">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password * (min 6 chars)</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" class="form-control">
            <option value="cashier">Cashier</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email (optional)</label>
        <input type="email" name="email" class="form-control" placeholder="user@jewelone.lk">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPwdModal" style="display:none">
  <div class="modal-box" style="max-width:400px;text-align:left">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-key" style="color:var(--gold)"></i> Reset Password</h3>
      <button class="modal-close" onclick="document.getElementById('resetPwdModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <p style="color:var(--gray-3);margin-bottom:16px;font-size:13px">Resetting password for: <strong id="resetUserName" style="color:var(--white)"></strong></p>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="uid" id="resetUserId">
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resetPwdModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResetPwd(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserName').textContent = name;
  document.getElementById('resetPwdModal').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>
