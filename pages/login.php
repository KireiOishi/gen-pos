// <!--   Description: This script displays the login form and handles user authentication.  -->
<?php
session_start();
require_once '../includes/functions.php'; 
require_once '../includes/config.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gen-POS | Login</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="login-background">
    <div class="login-container">
        <div class="login-box">
            <h2>Gen-POS Login</h2>
            <?php include '../includes/notifications.php'; ?>
            <form method="POST" action="../scripts/login_process.php">
                <div class="form-group">
                    <label for="user_id">User ID</label>
                    <input type="text" id="user_id" name="user_id" required>
                </div>
                <div class="form-group">
                    <label for="credential">PIN/Password</label>
                    <input type="password" id="credential" name="credential" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
    </div>
</body>
</html>