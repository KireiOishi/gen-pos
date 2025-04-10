<!--   Description: This script handles user login by validating credentials and setting session variables.  -->
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id']);
    $credential = trim($_POST['credential']);
    
    if (validateLogin($pdo, $userId, $credential)) {
        setNotification('success', 'Welcome to Gen-POS, ' . getCurrentUserName() . '!');
        header("Location: ../index.php");
        exit;
    } else {
        setNotification('error', 'Invalid User ID or PIN/Password');
        header("Location: ../pages/login.php");
        exit;
    }
}