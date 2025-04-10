<?php
require_once '../includes/init.php';

if (!isLoggedIn() || !isAdmin()) {
    setNotification('error', 'Access denied. Admins only.');
    header("Location: login.php");
    exit;
}

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $userId = trim($_POST['user_id']);
    $credential = trim($_POST['credential']);
    $role = $_POST['role'];
    $name = trim($_POST['name']);

    if (empty($userId) || empty($credential) || empty($role) || empty($name)) {
        setNotification('error', 'All fields are required.');
    } elseif (strlen($credential) < 6) {
        setNotification('error', 'PIN/Password must be at least 6 characters long.');
    } else {
        // Check for duplicate user_id
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            setNotification('error', 'User ID already exists. Please choose a different one.');
        } else {
            $hashedCredential = password_hash($credential, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (user_id, credential, role, name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $hashedCredential, $role, $name]);
                setNotification('success', 'User created successfully!');
            } catch (PDOException $e) {
                setNotification('error', 'Error creating user: ' . $e->getMessage());
            }
        }
    }
    header("Location: user_management.php");
    exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $role = $_POST['role'];
    $credential = trim($_POST['credential']);

    if (empty($name) || empty($role)) {
        setNotification('error', 'Name and role are required.');
    } else {
        try {
            if (!empty($credential)) {
                // Update with new password
                $hashedCredential = password_hash($credential, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, credential = ? WHERE id = ?");
                $stmt->execute([$name, $role, $hashedCredential, $id]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $role, $id]);
            }
            setNotification('success', 'User updated successfully!');
        } catch (PDOException $e) {
            setNotification('error', 'Error updating user: ' . $e->getMessage());
        }
    }
    header("Location: user_management.php");
    exit;
}

// Handle Delete User
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    // Prevent deleting the current user
    if ($id == $_SESSION['user_id']) {
        setNotification('error', 'You cannot delete your own account.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            setNotification('success', 'User deleted successfully!');
        } catch (PDOException $e) {
            setNotification('error', 'Error deleting user: ' . $e->getMessage());
        }
    }
    header("Location: user_management.php");
    exit;
}

// Fetch all users for listing
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Fetch user for editing (if edit mode)
$editUser = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gen-POS | User Management</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>

    <div class="container">
        <h2>User Management</h2>
        <?php include '../includes/notifications.php'; ?>

        <!-- Create/Edit User Form -->
        <h3><?php echo $editUser ? 'Edit User' : 'Create New User'; ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'create'; ?>">
            <?php if ($editUser): ?>
                <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" value="<?php echo $editUser ? $editUser['user_id'] : ''; ?>" <?php echo $editUser ? 'readonly' : 'required'; ?>>
            </div>
            <div class="form-group">
                <label for="credential">PIN/Password <?php echo $editUser ? '(Leave blank to keep unchanged)' : ''; ?></label>
                <input type="text" id="credential" name="credential" placeholder="Enter new PIN/Password" <?php echo !$editUser ? 'required' : ''; ?>>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="admin" <?php echo $editUser && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="cashier" <?php echo $editUser && $editUser['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo $editUser ? $editUser['name'] : ''; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $editUser ? 'Update User' : 'Create User'; ?></button>
            <?php if ($editUser): ?>
                <a href="user_management.php" class="btn btn-primary">Cancel</a>
            <?php endif; ?>
        </form>

        <!-- User List -->
        <h3>All Users</h3>
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td>
                                <a href="user_management.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-primary btn-small">Edit</a>
                                <a href="user_management.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>