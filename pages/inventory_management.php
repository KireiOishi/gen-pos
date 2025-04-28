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
    $scanned_barcode = isset($_POST['scanned_barcode']) ? trim($_POST['scanned_barcode']) : '';

    // Validate inputs
    if (empty($name) || $price <= 0 || $stock < 0) {
        setNotification('error', 'All fields are required, and price must be greater than 0.');
    } else {
        try {
            // Validate scanned barcode if provided
            if (!empty($scanned_barcode)) {
                if (!preg_match('/^[0-9A-Za-z]{4,20}$/', $scanned_barcode)) {
                    throw new Exception('Invalid barcode format. Must be 4-20 alphanumeric characters.');
                }
                $stmt = $pdo->prepare("SELECT id FROM products WHERE barcode = ?");
                $stmt->execute([$scanned_barcode]);
                if ($stmt->fetch()) {
                    throw new Exception('Barcode already exists in the database.');
                }
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Insert product
            $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, barcode, barcode_path) VALUES (?, ?, ?, ?, ?)");
            if (!empty($scanned_barcode)) {
                $barcode_data = generateProductBarcodeFromScanned($scanned_barcode);
                $stmt->execute([$name, $price, $stock, $scanned_barcode, $barcode_data['barcode_path']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, barcode, barcode_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $stock, null, null]);
                $product_id = $pdo->lastInsertId();
                $barcode_data = generateProductBarcode($product_id);
                $stmt = $pdo->prepare("UPDATE products SET barcode = ?, barcode_path = ? WHERE id = ?");
                $stmt->execute([$barcode_data['barcode'], $barcode_data['barcode_path'], $product_id]);
            }

            $pdo->commit();
            setNotification('success', 'Product added successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
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
    $scanned_barcode = trim($_POST['scanned_barcode']);

    if (empty($name) || $price <= 0 || $stock < 0) {
        setNotification('error', 'All fields are required, and price must be greater than 0.');
    } else {
        try {
            // Fetch the current product to compare barcode
            $stmt = $pdo->prepare("SELECT barcode, barcode_path FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $old_product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old_product) {
                throw new Exception('Product not found.');
            }

            // Validate new barcode if changed
            if ($scanned_barcode !== $old_product['barcode']) {
                if (!empty($scanned_barcode)) {
                    if (!preg_match('/^[0-9A-Za-z]{4,20}$/', $scanned_barcode)) {
                        throw new Exception('Invalid barcode format. Must be 4-20 alphanumeric characters.');
                    }
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE barcode = ? AND id != ?");
                    $stmt->execute([$scanned_barcode, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Barcode already exists in the database.');
                    }
                }

                // Delete old barcode image if it exists
                if ($old_product['barcode_path'] && file_exists('../' . $old_product['barcode_path'])) {
                    unlink('../' . $old_product['barcode_path']);
                }
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Update product
            $barcode_data = $scanned_barcode !== $old_product['barcode'] && !empty($scanned_barcode) ? generateProductBarcodeFromScanned($scanned_barcode) : ['barcode' => $old_product['barcode'], 'barcode_path' => $old_product['barcode_path']];
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, barcode = ?, barcode_path = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $barcode_data['barcode'], $barcode_data['barcode_path'], $id]);

            $pdo->commit();
            setNotification('success', 'Product updated successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
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
        $stmt = $pdo->prepare("SELECT barcode_path FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product['barcode_path'] && file_exists('../' . $product['barcode_path'])) {
            unlink('../' . $product['barcode_path']);
        }
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        setNotification('success', 'Product deleted successfully!');
    } catch (PDOException $e) {
        setNotification('error', 'Error deleting product: ' . $e->getMessage());
    }
    header("Location: inventory_management.php");
    exit;
}

// Update barcode paths and generate barcodes for existing products
$stmt = $pdo->query("SELECT * FROM products");
$products_to_update = $stmt->fetchAll();
foreach ($products_to_update as $product) {
    try {
        $regenerate = empty($product['barcode']) || empty($product['barcode_path']) ||
                      strpos($product['barcode_path'], 'assets/qr_code/') !== false ||
                      !file_exists('../' . $product['barcode_path']);
        if ($regenerate) {
            if ($product['barcode_path'] && file_exists('../' . $product['barcode_path'])) {
                unlink('../' . $product['barcode_path']);
            }
            $barcode_data = generateProductBarcode($product['id']);
            $stmt = $pdo->prepare("UPDATE products SET barcode = ?, barcode_path = ? WHERE id = ?");
            $stmt->execute([$barcode_data['barcode'], $barcode_data['barcode_path'], $product['id']]);
        }
    } catch (Exception $e) {
        setNotification('error', 'Error updating barcode for product ID ' . $product['id'] . ': ' . $e->getMessage());
    }
}

// Fetch product for editing
$editProduct = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editProduct) {
            setNotification('error', 'Product not found.');
            header("Location: inventory_management.php");
            exit;
        }
    } catch (PDOException $e) {
        setNotification('error', 'Error fetching product: ' . $e->getMessage());
        header("Location: inventory_management.php");
        exit;
    }
}

// Fetch all products for listing
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to generate barcode image for scanned barcode
function generateProductBarcodeFromScanned($barcode) {
    $barcode_dir = '../assets/barcode/';
    $barcode_file = $barcode_dir . $barcode . '.png';

    if (!is_dir($barcode_dir)) {
        mkdir($barcode_dir, 0777, true);
    }

    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $barcode_data = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 3, 100);

    $barcode_image = imagecreatefromstring($barcode_data);
    $barcode_width = imagesx($barcode_image);
    $barcode_height = imagesy($barcode_image);

    $text_height = 20;
    $total_height = $barcode_height + $text_height;

    $final_image = imagecreatetruecolor($barcode_width, $total_height);

    $white = imagecolorallocate($final_image, 255, 255, 255);
    $black = imagecolorallocate($final_image, 0, 0, 0);
    imagefill($final_image, 0, 0, $white);

    imagecopy($final_image, $barcode_image, 0, 0, 0, 0, $barcode_width, $barcode_height);

    $font = __DIR__ . '/arial.ttf';
    if (file_exists($font)) {
        imagettftext($final_image, 12, 0, 10, $total_height - 5, $black, $font, $barcode);
    } else {
        imagestring($final_image, 4, 10, $barcode_height + 2, $barcode, $black);
    }

    imagepng($final_image, $barcode_file);
    imagedestroy($barcode_image);
    imagedestroy($final_image);

    return [
        'barcode' => $barcode,
        'barcode_path' => 'assets/barcode/' . $barcode . '.png'
    ];
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
            <form method="POST" id="productForm">
                <input type="hidden" name="action" value="<?php echo $editProduct ? 'edit' : 'add'; ?>">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editProduct['id']); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (₱)</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?php echo $editProduct ? htmlspecialchars($editProduct['price']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" value="<?php echo $editProduct ? htmlspecialchars($editProduct['stock']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="scanned_barcode">Barcode <?php echo $editProduct ? '' : '(Scan or Leave Blank)'; ?></label>
                    <input type="text" id="scanned_barcode" name="scanned_barcode" placeholder="<?php echo $editProduct ? '' : 'Scan barcode here'; ?>" value="<?php echo $editProduct && $editProduct['barcode'] ? htmlspecialchars($editProduct['barcode']) : ''; ?>">
                    <?php if (!$editProduct): ?>
                        <button type="button" onclick="document.getElementById('scanned_barcode').value = ''" class="btn btn-secondary">Clear Barcode</button>
                    <?php endif; ?>
                </div>
                <?php if ($editProduct && $editProduct['barcode_path']): ?>
                    <div class="form-group">
                        <label>Current Barcode</label>
                        <img src="../<?php echo htmlspecialchars($editProduct['barcode_path']); ?>" alt="Barcode" class="barcode-img">
                        <p><?php echo htmlspecialchars($editProduct['barcode']); ?></p>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"><?php echo $editProduct ? 'Update Product' : 'Add Product'; ?></button>
                <?php if ($editProduct): ?>
                    <a href="inventory_management.php" class="btn btn-secondary">Cancel</a>
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
                            <th>Price (₱)</th>
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
                                <td>₱<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></td>
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
            <p>Total Value: ₱<?php echo number_format(array_sum(array_map(function($product) { return $product['price'] * $product['stock']; }, $products)), 2); ?></p>
        </div>
    </div>

    <script>
        // Auto-focus the barcode input for adding products, but not in edit mode
        <?php if (!$editProduct): ?>
            document.getElementById('scanned_barcode').focus();
        <?php endif; ?>

        // Auto-submit form when barcode is scanned (for add mode only)
        <?php if (!$editProduct): ?>
            document.getElementById('scanned_barcode').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('productForm').submit();
                }
            });
        <?php endif; ?>

        // Real-time barcode validation
        document.getElementById('scanned_barcode').addEventListener('input', (e) => {
            const barcode = e.target.value;
            const regex = /^[0-9A-Za-z]{4,20}$/;
            if (barcode && !regex.test(barcode)) {
                e.target.style.borderColor = 'red';
                alert('Invalid barcode format. Use 4-20 alphanumeric characters.');
            } else {
                e.target.style.borderColor = '';
            }
        });
    </script>
</body>
</html>