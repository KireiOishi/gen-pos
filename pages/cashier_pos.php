<?php
require_once '../includes/init.php';

if (!isLoggedIn()) {
    setNotification('error', 'Please log in to access the POS.');
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gen-POS | Cashier POS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h2>Gen-POS Cashier Interface</h2>
        <?php include '../includes/notifications.php'; ?>
        <p>Welcome, <?php echo getCurrentUserName(); ?>!</p>
        <a href="logout.php" class="btn btn-primary">Logout</a>
    </div>
</body>
</html>