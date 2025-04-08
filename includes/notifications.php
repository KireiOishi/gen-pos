<?php
$notification = getNotification();
if ($notification): ?>
    <div class="notification <?php echo $notification['type']; ?>">
        <?php echo $notification['message']; ?>
    </div>
<?php endif; ?>