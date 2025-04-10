<?php
// Determine the current page to set the active class
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="cashier-nav">
    <div class="nav-brand">
        <a href="cashier_pos.php"><i class="fas fa-cash-register"></i> Gen-POS Cashier</a>
    </div>
    <ul class="nav-links">
        <li><a href="cashier_pos.php" class="<?php echo $currentPage === 'cashier_pos.php' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> POS</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>