<?php
// Gen-POS On-Premise Database Configuration
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=gen_pos_db",
        "root",           // Default XAMPP username
        "",               // Default XAMPP password (empty)
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Gen-POS DB Connection Failed: " . $e->getMessage());
    die("Gen-POS is temporarily unavailable. Contact system administrator.");
}