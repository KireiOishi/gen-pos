<?php
function setNotification($type, $message) {
    $_SESSION['notification'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    return null;
}

function validateLogin($pdo, $userId, $credential) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // If user exists and credential matches
        if ($user && password_verify($credential, $user['credential'])) {   
            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['name']; // Optional: for display
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Gen-POS Login Error: " . $e->getMessage());
        return false;
    }
}


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'User';
}