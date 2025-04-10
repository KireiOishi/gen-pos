<?php
// Gen-POS On-Premise Database Configuration
$host = 'localhost';
$dbname = 'gen_pos_db';
$dbuser = 'root';           // Default XAMPP username
$dbpass = '';               // Default XAMPP password (empty)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Gen-POS DB Connection Failed: " . $e->getMessage());
    die("Gen-POS is temporarily unavailable. Contact system administrator.");
}