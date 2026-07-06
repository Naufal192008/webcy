<?php
require_once 'config/database.php';

$email = 'nmurtadho1905@gmail.com';
$newPassword = 'NaufalSmk24!';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
$stmt->execute([$hashedPassword, $email]);

echo "✅ Password untuk {$email} telah direset!<br>";
echo "Password baru: <strong>{$newPassword}</strong><br>";
echo "Role: <strong>admin</strong><br><br>";
echo "<a href='login.php'>LOGIN SEKARANG</a>";
?>