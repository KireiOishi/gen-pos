<?php
require_once '../includes/init.php';

session_destroy();
setNotification('success', 'You have been logged out.');
header("Location: login.php");
exit;
?>