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
    <!-- Add Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <div class="container">
        <h2>Admin Dashboard</h2>
        <?php include '../includes/notifications.php'; ?>
        <p>Welcome, <?php echo getCurrentUserName(); ?>
    </div>
</body>
</html>