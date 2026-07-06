<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];

// Handle remove item
if (isset($_GET['remove'])) {
    $cartId = (int)$_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $userId]);
    redirect('cart.php', 'Item dihapus dari keranjang');
}

// Handle update quantity
if (isset($_POST['update_qty'])) {
    $cartId = (int)$_POST['cart_id'];
    $qty = (int)$_POST['quantity'];
    if ($qty < 1) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartId, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$qty, $cartId, $userId]);
    }
    redirect('cart.php', 'Keranjang diperbarui');
}

// Get cart items
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.discount, p.images, p.stock 
                       FROM cart c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.user_id = ? AND p.is_active = 1");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$totalDiscount = 0;
foreach ($cartItems as $item) {
    $originalPrice = $item['price'] * $item['quantity'];
    $discountAmount = $item['discount'] ? ($originalPrice * $item['discount'] / 100) : 0;
    $subtotal += $originalPrice;
    $totalDiscount += $discountAmount;
}
$grandTotal = $subtotal - $totalDiscount;
$totalItems = array_sum(array_column($cartItems, 'quantity'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .cart-container { max-width: 1000px; margin: 0 auto; }
        .cart-item {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .cart-item:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .cart-item img { width: 120px; height: 90px; object-fit: cover; border-radius: 15px; }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .quantity-btn {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: 2px solid #e0e0e0;
            background: white;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .quantity-btn:hover {
            background: #4e73df;
            color: white;
            border-color: #4e73df;
        }
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 8px;
            font-weight: bold;
            font-size: 1rem;
        }
        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        .btn-checkout {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 16px;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
            color: white;
        }
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-cart i { font-size: 5rem; color: #ddd; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .cart-item .row { flex-direction: column; }
            .cart-item img { width: 100%; height: 200px; margin-bottom: 15px; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-store"></i> Belanja</a>
                <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="cart-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><i class="fas fa-shopping-cart text-primary"></i> Keranjang Belanja</h2>
                <span class="badge bg-primary fs-6"><?= $totalItems ?> Item</span>
            </div>

            <!-- Flash Message -->
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= escape($flash['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Keranjang Kosong</h3>
                    <p class="text-muted">Yuk, mulai belanja sekarang!</p>
                    <a href="index.php" class="btn btn-primary btn-lg rounded-pill px-5 mt-3">
                        <i class="fas fa-store"></i> Belanja Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <?php foreach ($cartItems as $item): 
                            $originalPrice = $item['price'];
                            $discountPrice = $item['discount'] ? $originalPrice - ($originalPrice * $item['discount'] / 100) : $originalPrice;
                            $itemTotal = $discountPrice * $item['quantity'];
                            $images = !empty($item['images']) ? explode(',', $item['images']) : ['https://via.placeholder.com/120x90?text=No+Image'];
                        ?>
                        <div class="cart-item animate__animated animate__fadeInUp">
                            <div class="row align-items-center g-3">
                                <!-- Image -->
                                <div class="col-md-2">
                                    <a href="product-detail.php?id=<?= $item['product_id'] ?>">
                                        <img src="<?= escape(trim($images[0])) ?>" alt="<?= escape($item['name']) ?>" class="w-100">
                                    </a>
                                </div>
                                
                                <!-- Product Info -->
                                <div class="col-md-4">
                                    <h6 class="fw-bold mb-1">
                                        <a href="product-detail.php?id=<?= $item['product_id'] ?>" class="text-decoration-none text-dark">
                                            <?= escape($item['name']) ?>
                                        </a>
                                    </h6>
                                    <?php if ($item['discount']): ?>
                                        <small class="text-decoration-line-through text-muted">Rp <?= number_format($originalPrice, 0, ',', '.') ?></small>
                                        <span class="badge bg-danger ms-1">-<?= $item['discount'] ?>%</span>
                                    <?php endif; ?>
                                    <p class="text-primary fw-bold mb-0">Rp <?= number_format($discountPrice, 0, ',', '.') ?></p>
                                    <small class="text-muted">Stok: <?= $item['stock'] ?></small>
                                </div>
                                
                                <!-- Quantity -->
                                <div class="col-md-3">
                                    <form method="POST" class="quantity-control">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="button" class="quantity-btn" onclick="updateQty(this, -1)">-</button>
                                        <input type="number" name="quantity" class="quantity-input" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" onchange="this.form.submit()" readonly>
                                        <button type="button" class="quantity-btn" onclick="updateQty(this, 1)">+</button>
                                        <input type="hidden" name="update_qty" value="1">
                                    </form>
                                </div>
                                
                                <!-- Subtotal -->
                                <div class="col-md-2 text-end">
                                    <small class="text-muted">Subtotal</small>
                                    <p class="fw-bold text-primary mb-0">Rp <?= number_format($itemTotal, 0, ',', '.') ?></p>
                                </div>
                                
                                <!-- Remove -->
                                <div class="col-md-1 text-end">
                                    <a href="?remove=<?= $item['id'] ?>" class="btn btn-danger btn-sm rounded-circle" 
                                       onclick="return confirm('Hapus <?= escape($item['name']) ?> dari keranjang?')" 
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Continue Shopping -->
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-arrow-left"></i> Lanjut Belanja
                            </a>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="summary-card">
                            <h5 class="fw-bold mb-4">Ringkasan Belanja</h5>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Harga (<?= $totalItems ?> item)</span>
                                <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($totalDiscount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Total Diskon</span>
                                <span>-Rp <?= number_format($totalDiscount, 0, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Biaya Layanan</span>
                                <span class="text-success">Gratis</span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-4">
                                <h5 class="fw-bold">Total</h5>
                                <h4 class="fw-bold text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h4>
                            </div>
                            
                            <a href="checkout.php" class="btn btn-checkout">
                                <i class="fas fa-credit-card"></i> Lanjut ke Checkout
                            </a>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt"></i> Pembayaran aman terenkripsi
                                </small>
                            </div>
                            
                            <!-- Payment Methods -->
                            <div class="text-center mt-3">
                                <small class="text-muted">Metode Pembayaran:</small>
                                <div class="d-flex justify-content-center gap-2 mt-2">
                                    <span class="badge bg-light text-dark">DANA</span>
                                    <span class="badge bg-light text-dark">OVO</span>
                                    <span class="badge bg-light text-dark">GoPay</span>
                                    <span class="badge bg-light text-dark">QRIS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
            <small>Pembayaran: 085710785244 a.n. WebPro UMKM</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateQty(btn, change) {
            const form = btn.closest('form');
            const input = form.querySelector('input[name="quantity"]');
            let newVal = parseInt(input.value) + change;
            const max = parseInt(input.max);
            
            if (newVal < 1) newVal = 1;
            if (newVal > max) {
                Swal.fire('Oops!', 'Stok tidak mencukupi! Maksimal ' + max, 'warning');
                return;
            }
            
            input.value = newVal;
            form.querySelector('input[name="update_qty"]').value = '1';
            form.submit();
        }
        
        // Auto-submit when quantity input changes manually
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>