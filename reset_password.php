<?php
/**
 * PASSWORD RESET SCRIPT
 * This script resets the admin password to: admin123
 * Access: http://localhost/jewel_one/reset_password.php
 */

echo "<h1>🔐 JEWEL ONE - Password Reset</h1>";
echo "<hr>";

try {
    require_once 'includes/config.php';
    $db = getDB();
    
    $username = 'admin';
    $new_password = 'admin123';
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    echo "Attempting to reset password for user: <strong>" . $username . "</strong><br>";
    echo "New password: <strong>" . $new_password . "</strong><br>";
    echo "Password hash: " . substr($hashed_password, 0, 30) . "...<br><br>";
    
    // Update password in database
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
    $result = $stmt->execute([$hashed_password, $username]);
    
    if ($result) {
        echo "✅ <strong>Password reset successfully!</strong><br><br>";
        echo "You can now log in with:<br>";
        echo "<strong>Username:</strong> admin<br>";
        echo "<strong>Password:</strong> admin123<br><br>";
        
        // Verify the reset
        $stmt = $db->prepare("SELECT username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (password_verify($new_password, $user['password'])) {
            echo "✅ <strong>Password verification PASSED!</strong><br>";
        } else {
            echo "❌ Password verification failed!<br>";
        }
    } else {
        echo "❌ Failed to update password!<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='login.php'>← Go to Login</a></p>";
?>
