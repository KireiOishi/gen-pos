<?php
require_once '../includes/init.php';

if (!isLoggedIn() || !isAdmin()) {
    setNotification('error', 'Access denied. Admins only.');
    header("Location: login.php");
    exit;
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    if (empty($name) || $price <= 0 || $stock < 0) {
        setNotification('error', 'All fields are required, and price must be greater than 0.');
    } else {
        try {
            // Insert the product without barcode first to get the product ID
            $stmt = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $stock]);
            $product_id = $pdo->lastInsertId();

            // Generate barcode for the product
            $barcode_data = generateProductBarcode($product_id);

            // Update the product with the barcode data
            $stmt = $pdo->prepare("UPDATE products SET barcode = ?, barcode_path = ? WHERE id = ?");
            $stmt->execute([$barcode_data['barcode'], $barcode_data['barcode_path'], $product_id]);

            setNotification('success', 'Product added successfully!');
        } catch (Exception $e) {
            setNotification('error', 'Error adding product: ' . $e->getMessage());
        }
    }
    header("Location: inventory_management.php");
    exit;
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $regenerate_barcode = isset($_POST['regenerate_barcode']) && $_POST['regenerate_barcode'] === '1';

    if (empty($name) || $price <= 0 || $stock < 0) {
        setNotification('error', 'All fields are required, and price must be greater than 0.');
    } else {
        try {
            // Fetch the current product to get the old barcode path
            $stmt = $pdo->prepare("SELECT barcode_path FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $old_product = $stmt->fetch();

            // Update product details
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $id]);

            // Regenerate barcode if requested
            if ($regenerate_barcode) {
                // Delete the old barcode image if it exists
                if ($old_product['barcode_path'] && file_exists('../' . $old_product['barcode_path'])) {
                    unlink('../' . $old_product['barcode_path']);
                }

                // Generate a new barcode
                $barcode_data = generateProductBarcode($id);

                // Update the barcode data
                $stmt = $pdo->prepare("UPDATE products SET barcode = ?, barcode_path = ? WHERE id = ?");
                $stmt->execute([$barcode_data['barcode'], $barcode_data['barcode_path'], $id]);
            }

            setNotification('success', 'Product updated successfully!');
        } catch (Exception $e) {
            setNotification('error', 'Error updating product: ' . $e->getMessage());
        }
    }
    header("Location: inventory_management.php");
    exit;
}

// Handle Delete Product
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Fetch the product to get the barcode path
        $stmt = $pdo->prepare("SELECT barcode_path FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        // Delete the barcode image if it exists
        if ($product['barcode_path'] && file_exists('../' . $product['barcode_path'])) {
            unlink('../' . $product['barcode_path']);
        }

        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        setNotification('success', 'Product deleted successfully!');
    } catch (PDOException $e) {
        setNotification('error', 'Error deleting product: ' . $e->getMessage());
    }
    header("Location: inventory_management.php");
    exit;
}

// Update barcode paths and regenerate barcodes for existing products
$stmt = $pdo->query("SELECT * FROM products");
$products_to_update = $stmt->fetchAll();
foreach ($products_to_update as $product) {
    try {
        // Check if barcode path needs updating or regenerating
        $regenerate = false;
        $old_path = $product['barcode_path'];

        // Regenerate if barcode or path is missing
        if (empty($product['barcode']) || empty($product['barcode_path'])) {
            $regenerate = true;
        }
        // Regenerate if the path uses the old "qr_code" folder
        elseif (strpos($product['barcode_path'], 'assets/qr_code/') !== false) {
            $regenerate = true;
        }
        // Regenerate if the barcode image file is missing
        elseif (!file_exists('../' . $product['barcode_path'])) {
            $regenerate = true;
        }

        if ($regenerate) {
            // Delete the old barcode image if it exists
            if ($old_path && file_exists('../' . $old_path)) {
                unlink('../' . $old_path);
            }

            // Generate a new barcode
            $barcode_data = generateProductBarcode($product['id']);
            $stmt = $pdo->prepare("UPDATE products SET barcode = ?, barcode_path = ? WHERE id = ?");
            $stmt->execute([$barcode_data['barcode'], $barcode_data['barcode_path'], $product['id']]);
        }
    } catch (Exception $e) {
        setNotification('error', 'Error updating barcode for product ID ' . $product['id'] . ': ' . $e->getMessage());
    }
}

// Fetch all products for listing
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();

// Fetch product for editing (if edit mode)
$editProduct = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $editProduct = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gen-POS | Inventory Management</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    <div class="admin-background"></div>

    <div class="container">
        <h2>Inventory Management</h2>
        <?php include '../includes/notifications.php'; ?>

        <!-- Add/Edit Product Form -->
        <div class="glass-box">
            <h3><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editProduct ? 'edit' : 'add'; ?>">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="price">Price ($)</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?php echo $editProduct ? htmlspecialchars($editProduct['price']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" value="<?php echo $editProduct ? htmlspecialchars($editProduct['stock']) : ''; ?>" required>
                </div>
                <?php if ($editProduct): ?>
                    <div class="form-group">
                        <label for="regenerate_barcode">Regenerate Barcode?</label>
                        <input type="checkbox" id="regenerate_barcode" name="regenerate_barcode" value="1">
                    </div>
                    <?php if ($editProduct['barcode_path']): ?>
                        <div class="form-group">
                            <label>Current Barcode</label>
                            <img src="../<?php echo htmlspecialchars($editProduct['barcode_path']); ?>" alt="Barcode" class="barcode-img">
                            <p><?php echo htmlspecialchars($editProduct['barcode']); ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"><?php echo $editProduct ? 'Update Product' : 'Add Product'; ?></button>
                <?php if ($editProduct): ?>
                    <a href="inventory_management.php" class="btn btn-primary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Product List -->
        <div class="glass-box">
            <h3>All Products</h3>
            <?php if (empty($products)): ?>
                <p>No products found.</p>
            <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Price ($)</th>
                            <th>Stock</th>
                            <th>Barcode</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($product['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($product['stock']); ?></td>
                                <td>
                                    <?php if ($product['barcode_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($product['barcode_path']); ?>" alt="Barcode" class="barcode-img">
                                        <p><?php echo htmlspecialchars($product['barcode']); ?></p>
                                    <?php else: ?>
                                        <span>No Barcode</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                <td>
                                    <a href="inventory_management.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="inventory_management.php?action=delete&id=<?php echo $product['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete this product?');"><i class="fas fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Inventory Report -->
        <div class="glass-box">
            <h3>Inventory Report</h3>
            <p>Total Products: <?php echo count($products); ?></p>
            <p>Total Stock: <?php echo array_sum(array_column($products, 'stock')); ?></p>
            <p>Total Value: $<?php echo number_format(array_sum(array_map(function($product) { return $product['price'] * $product['stock']; }, $products)), 2); ?></p>
        </div>
    </div>
</body>
</html>