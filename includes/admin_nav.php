<?php
// Determine the current page to set the active class
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="admin-nav">
    <div class="nav-brand">
        <a href="admin_dashboard.php"><i class="fas fa-cogs"></i> Gen-POS Admin</a>
    </div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="user_management.php" class="<?php echo $currentPage === 'user_management.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="inventory_management.php" class="<?php echo $currentPage === 'inventory_management.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Inventory Management</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>