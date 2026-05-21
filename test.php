<?php
/**
 * DATABASE CONNECTION TEST
 * Use this to verify your database connection and debug login issues
 * Access: http://localhost/jewel_one/test.php
 */

echo "<h1>🧪 JEWEL ONE Database & Login Diagnostic Test</h1>";
echo "<hr>";

// ============ TEST 1: Database Connection ============
echo "<h2>Test 1: Database Connection</h2>";
try {
    require_once 'includes/config.php';
    $db = getDB();
    echo "✅ <strong>Database connected successfully!</strong><br>";
    echo "Host: " . DB_HOST . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "User: " . DB_USER . "<br>";
} catch (Exception $e) {
    echo "❌ <strong>Database connection FAILED!</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    die("Cannot proceed without database connection.");
}

// ============ TEST 2: Check Users Table ============
echo "<h2>Test 2: Users Table Check</h2>";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Users table exists.<br>";
    echo "Total users in database: <strong>" . $result['count'] . "</strong><br>";
} catch (Exception $e) {
    echo "❌ Users table check FAILED!<br>";
    echo "Error: " . $e->getMessage() . "<br>";
}

// ============ TEST 3: List All Users (for debugging) ============
echo "<h2>Test 3: Existing Users in Database</h2>";
try {
    $stmt = $db->prepare("SELECT id, username, full_name, role, status FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "✅ Found " . count($users) . " user(s):<br>";
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['full_name'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . $user['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No users found in database!<br>";
        echo "The database may not have been imported properly.";
    }
} catch (Exception $e) {
    echo "❌ Error listing users: " . $e->getMessage() . "<br>";
}

// ============ TEST 4: Test Login Function ============
echo "<h2>Test 4: Login Function Test</h2>";
require_once 'includes/auth.php';

$test_username = 'admin';
$test_password = 'admin123';

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$test_username]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User '<strong>" . $test_username . "</strong>' found in database.<br>";
        echo "Password hash in DB: " . substr($user['password'], 0, 20) . "...<br>";
        
        if (password_verify($test_password, $user['password'])) {
            echo "✅ <strong>Password verification PASSED!</strong><br>";
            echo "Login should work with username: <strong>" . $test_username . "</strong> and password: <strong>" . $test_password . "</strong>";
        } else {
            echo "❌ <strong>Password verification FAILED!</strong><br>";
            echo "The password '<strong>" . $test_password . "</strong>' does not match the hash in database.<br>";
            echo "You may need to reset the password or re-import the database.";
        }
    } else {
        echo "❌ User '<strong>" . $test_username . "</strong>' not found or not active!<br>";
        echo "Check the users table or database import.";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// ============ TEST 5: Config Check ============
echo "<h2>Test 5: Configuration Check</h2>";
echo "APP_URL: <strong>" . APP_URL . "</strong><br>";
echo "SESSION_TIMEOUT: <strong>" . SESSION_TIMEOUT . " seconds</strong><br>";
echo "DB_CHARSET: <strong>" . DB_CHARSET . "</strong><br>";

// ============ TEST 6: File Permissions ============
echo "<h2>Test 6: File & Folder Permissions</h2>";
$paths = [
    'uploads/logo',
    'backup',
    'includes/config.php'
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "✅ <strong>$path</strong> - Permissions: $perms<br>";
    } else {
        echo "⚠️ <strong>$path</strong> - NOT FOUND<br>";
    }
}

// ============ TEST 7: Session Test ============
echo "<h2>Test 7: Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✅ Session started successfully.<br>";
echo "Session ID: <strong>" . session_id() . "</strong><br>";
echo "Session save path: <strong>" . session_save_path() . "</strong><br>";

echo "<hr>";
echo "<p><strong>✅ All tests completed! Check results above.</strong></p>";
echo "<p><a href='login.php'>← Back to Login</a></p>";
?>
