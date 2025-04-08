<?php
require_once '../includes/init.php';

if (!isLoggedIn() || !isAdmin()) {
    setNotification('error', 'Access denied. Admins only.');
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gen-POS | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h2>Gen-POS Admin Dashboard</h2>
        <?php include '../includes/notifications.php'; ?>
        <p>Welcome, <?php echo getCurrentUserName(); ?>!</p>
        <a href="logout.php" class="btn btn-primary">Logout</a>
    </div>
</body>
</html>