<?php
/**
 * Test Login - Debug script untuk test password verification
 */

session_start();
require_once __DIR__ . '/config/db.php';

$pdo = getPdo();
$email = 'admin@laptop.com';
$password = 'admin123';

echo "<h2>Testing Login for: $email</h2>";
echo "<pre>";

// Get user from database
$stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "❌ User not found in database!\n";
    exit;
}

echo "✓ User found:\n";
echo "  ID: " . $user['id'] . "\n";
echo "  Name: " . $user['name'] . "\n";
echo "  Email: " . $user['email'] . "\n";
echo "  Role: " . $user['role'] . "\n";
echo "  Password Hash: " . $user['password'] . "\n";
echo "\n";

// Test password verification
echo "Testing password verification:\n";
echo "  Input password: $password\n";
echo "  Stored hash: " . $user['password'] . "\n";

$verifyResult = password_verify($password, $user['password']);

if ($verifyResult) {
    echo "  ✓ password_verify() returned: TRUE\n";
    echo "  ✓ Password is CORRECT!\n";
    echo "\n";
    echo "You should be able to login now.\n";
    echo "\n";
    echo "<a href='login.php'>Go to Login Page</a>\n";
} else {
    echo "  ❌ password_verify() returned: FALSE\n";
    echo "  ❌ Password verification FAILED!\n";
    echo "\n";
    echo "The password hash in database doesn't match 'admin123'\n";
    echo "\n";
    echo "Solution: Update the password hash\n";
    echo "<a href='fix-password.php'>Fix Password Hash</a>\n";
}

echo "</pre>";
?>

