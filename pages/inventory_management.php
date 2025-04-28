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
                // Allow numeric barcodes (8-13 digits for EAN-13/UPC-A) or alphanumeric (for Code 128)
                if (!preg_match('/^[0-9A-Za-z]{4,20}$/', $scanned_barcode)) {
                    throw new Exception('Invalid barcode format. Must be 4-20 alphanumeric characters.');
                }
                // Check for duplicate barcode
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
                // Use scanned barcode and generate its image
                $barcode_data = generateProductBarcodeFromScanned($scanned_barcode);
                $stmt->execute([$name, $price, $stock, $scanned_barcode, $barcode_data['barcode_path']]);
            } else {
                // Generate new barcode
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
function generateProductBarcodeFromScanned($barcode) {
    // Paths
    $barcode_dir = '../assets/barcode/';
    $barcode_file = $barcode_dir . $barcode . '.png';

    if (!is_dir($barcode_dir)) {
        mkdir($barcode_dir, 0777, true);
    }

    // Generate the barcode image (without text)
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $barcode_data = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 3, 100);

    // Create image from barcode binary
    $barcode_image = imagecreatefromstring($barcode_data);
    $barcode_width = imagesx($barcode_image);
    $barcode_height = imagesy($barcode_image);

    // Create new image with extra space below for text
    $text_height = 20;
    $total_height = $barcode_height + $text_height;

    $final_image = imagecreatetruecolor($barcode_width, $total_height);

    // Colors
    $white = imagecolorallocate($final_image, 255, 255, 255);
    $black = imagecolorallocate($final_image, 0, 0, 0);
    imagefill($final_image, 0, 0, $white);

    // Copy barcode onto final image
    imagecopy($final_image, $barcode_image, 0, 0, 0, 0, $barcode_width, $barcode_height);

    // Add the barcode text
    $font = __DIR__ . '/arial.ttf'; // Same font as generateProductBarcode
    if (file_exists($font)) {
        imagettftext($final_image, 12, 0, 10, $total_height - 5, $black, $font, $barcode);
    } else {
        // Fallback to built-in font
        imagestring($final_image, 4, 10, $barcode_height + 2, $barcode, $black);
    }

    // Save final image
    imagepng($final_image, $barcode_file);
    imagedestroy($barcode_image);
    imagedestroy($final_image);

    return [
        'barcode' => $barcode,
        'barcode_path' => 'assets/barcode/' . $barcode . '.png'
    ];
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
            <h3>Add New Product</h3>
            <form method="POST" id="addProductForm">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="price">Price ($)</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" required>
                </div>
                <div class="form-group">
                    <label for="scanned_barcode">Barcode (Scan or Leave Blank)</label>
                    <input type="text" id="scanned_barcode" name="scanned_barcode" placeholder="Scan barcode here">
                    <button type="button" onclick="document.getElementById('scanned_barcode').value = ''" class="btn btn-secondary">Clear Barcode</button>
                </div>
                <button type="submit" class="btn btn-primary">Add Product</button>
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