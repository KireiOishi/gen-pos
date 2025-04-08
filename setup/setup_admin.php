<?php
require_once '../includes/config.php';

// Check if an admin already exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$stmt->execute();
$adminCount = $stmt->fetchColumn();

if ($adminCount > 0) {
    die("An admin user already exists. This script cannot be run again.");
}

$adminId = 'admin';
$adminPassword = 'admin12345'; // Change this for security!
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (user_id, credential, role, name) VALUES (?, ?, 'admin', 'Administrator')");
$stmt->execute([$adminId, $hashedPassword]);

echo "Gen-POS Admin user created!";
?>