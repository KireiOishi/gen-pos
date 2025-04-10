<?php
require_once '../includes/init.php';

if (!isLoggedIn()) {
    setNotification('error', 'Please log in to access the POS.');
    header("Location: login.php");
    exit;
}

// Initialize cart in session if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch all products for the product selection
$stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
$products = $stmt->fetchAll();

// Handle Add to Cart via Product Selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    // Fetch the product to check stock
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        setNotification('error', 'Product not found.');
    } elseif ($quantity <= 0) {
        setNotification('error', 'Quantity must be greater than 0.');
    } elseif ($quantity > $product['stock']) {
        setNotification('error', 'Not enough stock available. Current stock: ' . $product['stock']);
    } else {
        // Add to cart
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity
            ];
        }
        setNotification('success', 'Product added to cart!');
    }
    header("Location: cashier_pos.php");
    exit;
}

// Handle Add to Cart via Barcode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_by_barcode') {
    $barcode = trim($_POST['barcode']);
    $quantity = intval($_POST['quantity']);

    // Fetch the product by barcode
    $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch();

    if (!$product) {
        setNotification('error', 'Invalid barcode or product not found.');
    } elseif ($quantity <= 0) {
        setNotification('error', 'Quantity must be greater than 0.');
    } elseif ($quantity > $product['stock']) {
        setNotification('error', 'Not enough stock available. Current stock: ' . $product['stock']);
    } else {
        // Add to cart
        $product_id = $product['id'];
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity
            ];
        }
        setNotification('success', 'Product added to cart via barcode!');
    }
    header("Location: cashier_pos.php");
    exit;
}

// Handle Remove from Cart
if (isset($_GET['action']) && $_GET['action'] === 'remove_from_cart' && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        setNotification('success', 'Product removed from cart!');
    }
    header("Location: cashier_pos.php");
    exit;
}

// Handle Clear Cart
if (isset($_GET['action']) && $_GET['action'] === 'clear_cart') {
    $_SESSION['cart'] = [];
    setNotification('success', 'Cart cleared successfully!');
    header("Location: cashier_pos.php");
    exit;
}

// Handle Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (empty($_SESSION['cart'])) {
        setNotification('error', 'Cart is empty.');
    } elseif (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        setNotification('error', 'User ID not found in session. Please log in again.');
    } else {

        $transactionStarted = false;
        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            // Calculate total
            $total = 0;
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $total += $item['price'] * $item['quantity'];
            }

            // Create transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (cashier_id, total) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $total]);
            $transaction_id = $pdo->lastInsertId();

            // Insert transaction items and update stock
            foreach ($_SESSION['cart'] as $product_id => $item) {
                // Insert into transaction_items
                $stmt = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$transaction_id, $product_id, $item['quantity'], $item['price']]);

                // Update stock
                $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $product_id]);
            }

            // Clear the cart
            $_SESSION['cart'] = [];

            $pdo->commit();
            setNotification('success', 'Transaction completed successfully!');
        } catch (Exception $e) {
            if ($transactionStarted) {
                $pdo->rollBack();
            }
            setNotification('error', 'Error completing transaction: ' . $e->getMessage());
        }
    }
    header("Location: cashier_pos.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gen-POS | Cashier POS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Enhanced POS Layout */
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 1.5rem;
            padding: 1rem;
            min-height: 80vh;
        }

        .pos-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .glass-box {
            flex: 1;
            overflow-y: auto;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th, .cart-table td {
            padding: 0.75rem;
            text-align: left;
        }

        .cart-table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: bold;
        }

        .cart-table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .total-row {
            font-weight: bold;
            font-size: 1.2rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            color: white;
            width: 90%;
            max-width: 400px;
        }

        .modal-content h3 {
            margin-bottom: 1rem;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .pos-container {
                grid-template-columns: 1fr;
            }

            .pos-section {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/cashier_nav.php'; ?>
    <div class="cashier-background"></div>

    <div class="container">
        <h2>Cashier POS Interface</h2>
        <?php include '../includes/notifications.php'; ?>
        <p>Welcome, <?php echo getCurrentUserName(); ?> (User ID: <?php echo getCurrentUserId(); ?>)!</p>

        <div class="pos-container">
            <!-- Product Selection Section -->
            <div class="pos-section">
                <!-- Add Product by Barcode -->
                <div class="glass-box">
                    <h3><i class="fas fa-barcode"></i> Scan Barcode</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_by_barcode">
                        <div class="form-group">
                            <label for="barcode">Barcode</label>
                            <input type="text" id="barcode" name="barcode" placeholder="Enter Barcode" required autofocus>
                        </div>
                        <div class="form-group">
                            <label for="quantity_barcode">Quantity</label>
                            <input type="number" id="quantity_barcode" name="quantity" min="1" value="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to Cart</button>
                    </form>
                </div>

                <!-- Add Product by Selection -->
                <div class="glass-box">
                    <h3><i class="fas fa-list"></i> Select Product</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_to_cart">
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select id="product_id" name="product_id" required>
                                <option value="">-- Select a Product --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']) . ' ($' . number_format($product['price'], 2) . ') - Stock: ' . $product['stock']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to Cart</button>
                    </form>
                </div>
            </div>

            <!-- Cart Section -->
            <div class="pos-section">
                <div class="glass-box">
                    <h3><i class="fas fa-shopping-cart"></i> Cart</h3>
                    <?php if (empty($_SESSION['cart'])): ?>
                        <p>Cart is empty.</p>
                    <?php else: ?>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price ($)</th>
                                    <th>Qty</th>
                                    <th>Subtotal ($)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="cart-items">
                                <?php 
                                $total = 0;
                                foreach ($_SESSION['cart'] as $product_id => $item):
                                    $subtotal = $item['price'] * $item['quantity'];
                                    $total += $subtotal;
                                ?>
                                    <tr data-product-id="<?php echo $product_id; ?>">
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="subtotal"><?php echo htmlspecialchars(number_format($subtotal, 2)); ?></td>
                                        <td>
                                            <a href="cashier_pos.php?action=remove_from_cart&product_id=<?php echo $product_id; ?>" class="btn btn-danger btn-small"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3">Total</td>
                                    <td id="cart-total"><?php echo htmlspecialchars(number_format($total, 2)); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="openCheckoutModal()"><i class="fas fa-check"></i> Checkout</button>
                            <a href="cashier_pos.php?action=clear_cart" class="btn btn-danger"><i class="fas fa-times"></i> Clear Cart</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Checkout Summary Section -->
            <div class="pos-section">
                <div class="glass-box">
                    <h3><i class="fas fa-receipt"></i> Summary</h3>
                    <p><strong>Total Items:</strong> <?php echo count($_SESSION['cart']); ?></p>
                    <p><strong>Total Amount:</strong> $<span id="summary-total"><?php echo number_format($total ?? 0, 2); ?></span></p>
                    <p><strong>Cashier:</strong> <?php echo getCurrentUserName(); ?></p>
                    <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
            </div>
        </div>

        <!-- Checkout Confirmation Modal -->
        <div id="checkoutModal" class="modal">
            <div class="modal-content">
                <h3>Confirm Checkout</h3>
                <p>Are you sure you want to complete this transaction?</p>
                <p><strong>Total Amount:</strong> $<span id="modal-total"><?php echo number_format($total ?? 0, 2); ?></span></p>
                <div class="modal-buttons">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirm</button>
                    </form>
                    <button class="btn btn-danger" onclick="closeCheckoutModal()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Real-time total calculation (if needed for future dynamic updates)
        function updateCartTotal() {
            let total = 0;
            document.querySelectorAll('#cart-items tr:not(.total-row)').forEach(row => {
                const subtotal = parseFloat(row.querySelector('.subtotal').textContent.replace(',', ''));
                total += subtotal;
            });
            document.getElementById('cart-total').textContent = total.toFixed(2);
            document.getElementById('summary-total').textContent = total.toFixed(2);
            document.getElementById('modal-total').textContent = total.toFixed(2);
        }

        // Modal functions
        function openCheckoutModal() {
            if (document.querySelectorAll('#cart-items tr:not(.total-row)').length === 0) {
                alert('Cart is empty!');
                return;
            }
            document.getElementById('checkoutModal').style.display = 'flex';
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
        }

        // Auto-focus barcode input on page load
        document.getElementById('barcode').focus();

        // Update total on page load
        updateCartTotal();
    </script>
</body>
</html>z