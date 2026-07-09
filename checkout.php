<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$error = '';

// ==================== CEK KUPON ====================
$couponCode = trim($_POST['coupon_code'] ?? $_SESSION['applied_coupon'] ?? '');
$couponDiscount = 0;
$couponData = null;

if (!empty($couponCode)) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND used_count < max_uses AND expiry_date >= ?");
    $stmt->execute([$couponCode, date('Y-m-d')]);
    $couponData = $stmt->fetch();
    
    if ($couponData) {
        $couponDiscount = $couponData['discount'];
        $_SESSION['applied_coupon'] = $couponCode;
    } else {
        $couponError = 'Kupon tidak valid atau sudah kadaluarsa!';
        unset($_SESSION['applied_coupon']);
    }
}

// Hapus kupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    redirect('checkout.php', 'Kupon dihapus!');
}

// ==================== CEK DISKON SPIN WHEEL ====================
$spinDiscountPercent = 0;
$spinDiscountAmount = 0;
$activeSpinId = null;

$stmt = $pdo->prepare("SELECT * FROM spin_history WHERE user_id = ? AND is_used = 0 AND discount > 0 AND expiry_date >= ? ORDER BY spin_date DESC LIMIT 1");
$stmt->execute([$userId, date('Y-m-d')]);
$activeSpin = $stmt->fetch();

if ($activeSpin) {
    $spinDiscountPercent = $activeSpin['discount'];
    $activeSpinId = $activeSpin['id'];
}

// Get cart items
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.discount, p.stock, p.images FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.is_active = 1");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    redirect('cart.php', 'Keranjang masih kosong!', 'warning');
}

// ==================== PERHITUNGAN ====================
$subtotal = 0;
foreach ($cartItems as $item) {
    $price = $item['discount'] ? $item['price'] - ($item['price'] * $item['discount'] / 100) : $item['price'];
    $subtotal += $price * $item['quantity'];
}

// Diskon spin wheel
if ($spinDiscountPercent > 0) {
    $spinDiscountAmount = $subtotal * $spinDiscountPercent / 100;
}

// Diskon kupon
$couponDiscountAmount = 0;
if ($couponDiscount > 0) {
    $couponDiscountAmount = ($subtotal - $spinDiscountAmount) * $couponDiscount / 100;
}

$grandTotal = $subtotal - $spinDiscountAmount - $couponDiscountAmount;

// ==================== PROCESS CHECKOUT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_checkout'])) {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    
    if (empty($paymentMethod)) {
        $error = 'Silakan pilih metode pembayaran!';
    } elseif (empty($customerPhone)) {
        $error = 'Nomor HP harus diisi!';
    } else {
        try {
            $pdo->beginTransaction();
            
            $productNames = array_map(function($item) { return $item['name']; }, $cartItems);
            $productName = implode(', ', $productNames);
            
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, product_name, total_price, payment_method, payment_number, note, status) VALUES (?, ?, ?, ?, '085710785244', ?, 'pending')");
            $stmt->execute([$userId, $productName, $grandTotal, $paymentMethod, $note]);
            $orderId = $pdo->lastInsertId();
            
            // Tandai diskon spin sudah dipakai
            if ($activeSpinId) {
                $stmt = $pdo->prepare("UPDATE spin_history SET is_used = 1, used_at = NOW(), order_id = ? WHERE id = ?");
                $stmt->execute([$orderId, $activeSpinId]);
            }
            
            // Update kupon used_count
            if ($couponData) {
                $stmt = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
                $stmt->execute([$couponCode]);
                unset($_SESSION['applied_coupon']);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("UPDATE products SET sold = sold + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $pdo->commit();
            redirect('payment.php?order_id=' . $orderId);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memproses pesanan: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get cart count for navbar
$cartCount = count($cartItems);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4e73df">
    <title>Checkout - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --secondary: #764ba2;
            --success: #1cc88a;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --info: #36b9cc;
            --dark: #1a1a2e;
            --light: #f5f7fa;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --radius-sm: 10px;
            --radius-md: 15px;
            --radius-lg: 20px;
            --shadow-sm: 0 3px 15px rgba(0,0,0,0.06);
            --shadow-md: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* NAVBAR */
        .navbar {
            background: rgba(255,255,255,0.97) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            padding: 10px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar-brand i {
            color: var(--primary);
            -webkit-text-fill-color: initial;
            font-size: 1.5rem;
        }
        
        .btn-back-nav {
            background: #f0f0f0;
            color: #555;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back-nav:hover {
            background: #e0e0e0;
            color: #333;
            transform: translateX(-3px);
        }
        
        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 30px 0;
        }
        
        .section-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .section-card h5 {
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        
        .section-card h5 i {
            color: var(--primary);
        }
        
        /* PAYMENT OPTIONS */
        .payment-option {
            cursor: pointer;
            border: 3px solid #e0e0e0;
            border-radius: var(--radius-md);
            padding: 20px 15px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            height: 100%;
            background: white;
        }
        
        .payment-option:hover {
            border-color: var(--primary);
            background: #f8f9ff;
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }
        
        .payment-option.selected {
            border-color: var(--primary);
            background: #f0f4ff;
            box-shadow: 0 5px 20px rgba(78,115,223,0.2);
        }
        
        .payment-option.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 15px;
            background: var(--primary);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .payment-option i {
            font-size: 2.2rem;
            margin-bottom: 8px;
        }
        
        .payment-option h6 {
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .payment-option small {
            color: #888;
            font-size: 0.8rem;
        }
        
        /* SUMMARY CARD */
        .summary-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 90px;
        }
        
        .summary-card h5 {
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* BUTTONS */
        .btn-pay {
            background: var(--gradient-1);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            padding: 16px;
            font-weight: 700;
            font-size: 1.05rem;
            width: 100%;
            transition: var(--transition);
            letter-spacing: 0.5px;
        }
        
        .btn-pay:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-pay:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-back-shop {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 50px;
            transition: var(--transition);
            margin-bottom: 20px;
            background: white;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-back-shop:hover {
            color: var(--primary);
            background: #f8f9ff;
            transform: translateX(-5px);
            box-shadow: var(--shadow-md);
        }
        
        /* FORM */
        .form-control {
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            border: 2px solid #e8e8e8;
            transition: var(--transition);
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.1);
        }
        
        /* COUPON */
        .coupon-alert {
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .discount-row {
            color: var(--success);
            font-weight: 600;
        }
        
        .total-row {
            font-size: 1.2rem;
            font-weight: 700;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }
        
        /* FOOTER */
        footer {
            background: var(--dark);
            color: white;
            padding: 30px 0;
            margin-top: auto;
            text-align: center;
        }
        
        footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        
        footer a:hover { color: white; }
        
        /* ITEM IMAGE */
        .item-img {
            width: 55px;
            height: 45px;
            object-fit: cover;
            border-radius: 8px;
            background: #f0f0f0;
        }
        
        /* RESPONSIVE */
        @media (max-width: 992px) {
            .summary-card {
                position: relative;
                top: 0;
            }
        }
        
        @media (max-width: 576px) {
            .section-card {
                padding: 18px;
            }
            .payment-option {
                padding: 15px 10px;
            }
            .payment-option i {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-globe"></i> WebPro UMKM
            </a>
            <div class="d-flex gap-2 align-items-center">
                <a href="index.php" class="btn-back-nav">
                    <i class="fas fa-arrow-left"></i> Kembali Belanja
                </a>
                <a href="cart.php" class="btn-back-nav">
                    <i class="fas fa-shopping-cart"></i> Keranjang
                </a>
                <a href="dashboard.php" class="btn-back-nav">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="container">
            
            <!-- Back Button -->
            <a href="index.php" class="btn-back-shop animate__animated animate__fadeIn">
                <i class="fas fa-arrow-left"></i> Kembali ke Halaman Belanja
            </a>
            
            <h2 class="fw-bold mb-4">
                <i class="fas fa-credit-card text-primary"></i> Checkout
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show animate__animated animate__fadeIn">
                    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'info-circle' ?>"></i>
                    <?= $flash['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <div class="row g-4">
                    
                    <!-- LEFT COLUMN -->
                    <div class="col-lg-8">
                        
                        <!-- Customer Info -->
                        <div class="section-card">
                            <h5><i class="fas fa-user"></i> Informasi Pemesan</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Nama Lengkap</label>
                                    <input type="text" class="form-control bg-light" value="<?= escape($user['full_name']) ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" class="form-control bg-light" value="<?= escape($user['email']) ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">No. Handphone *</label>
                                    <input type="tel" name="customer_phone" class="form-control" value="<?= escape($user['phone']) ?>" placeholder="085710785244" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Catatan (Opsional)</label>
                                    <input type="text" name="note" class="form-control" placeholder="Tambahkan catatan untuk pesanan...">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="section-card">
                            <h5><i class="fas fa-box"></i> Item Pesanan (<?= count($cartItems) ?> item)</h5>
                            <?php foreach ($cartItems as $item): 
                                $price = $item['discount'] ? $item['price'] - ($item['price'] * $item['discount'] / 100) : $item['price'];
                                $itemTotal = $price * $item['quantity'];
                                $imgs = !empty($item['images']) ? explode(',', $item['images']) : ['https://via.placeholder.com/55x45'];
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= escape(trim($imgs[0])) ?>" class="item-img" alt="<?= escape($item['name']) ?>" onerror="this.src='https://via.placeholder.com/55x45'">
                                    <div>
                                        <strong><?= escape($item['name']) ?></strong>
                                        <?php if ($item['discount']): ?>
                                            <br><small class="text-muted">Diskon produk: <?= $item['discount'] ?>%</small>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= $item['quantity'] ?> x Rp <?= number_format($price, 0, ',', '.') ?></small>
                                    </div>
                                </div>
                                <strong>Rp <?= number_format($itemTotal, 0, ',', '.') ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Coupon Section -->
                        <div class="section-card">
                            <h5><i class="fas fa-ticket-alt text-warning"></i> Kode Kupon</h5>
                            <?php if (isset($couponError)): ?>
                                <div class="alert alert-warning coupon-alert"><?= $couponError ?></div>
                            <?php endif; ?>
                            <?php if ($couponData): ?>
                                <div class="alert alert-success coupon-alert d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span>🎫 Kupon <strong><?= escape($couponCode) ?></strong> - Diskon <strong><?= $couponDiscount ?>%</strong></span>
                                    <a href="?remove_coupon=1" class="btn btn-sm btn-outline-danger rounded-pill">✕ Hapus Kupon</a>
                                </div>
                            <?php else: ?>
                                <div class="input-group">
                                    <input type="text" name="coupon_code" class="form-control" placeholder="Masukkan kode kupon..." value="<?= escape($couponCode) ?>">
                                    <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-ticket-alt"></i> Pakai Kupon</button>
                                </div>
                                <small class="text-muted mt-1 d-block">Punya kode kupon? Masukkan di sini untuk dapat diskon tambahan!</small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Detail Pembayaran -->
                        <div class="section-card">
                            <h5><i class="fas fa-calculator"></i> Detail Pembayaran</h5>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?= count($cartItems) ?> item)</span>
                                <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($spinDiscountPercent > 0): ?>
                            <div class="d-flex justify-content-between mb-2 discount-row">
                                <span><i class="fas fa-ticket-alt"></i> Diskon Spin Wheel (<?= $spinDiscountPercent ?>%)</span>
                                <span>-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span>
                            </div>
                            <small class="text-success d-block mb-2">Berlaku sampai: <?= date('d M Y', strtotime($activeSpin['expiry_date'])) ?></small>
                            <?php endif; ?>
                            
                            <?php if ($couponDiscountAmount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 discount-row">
                                <span><i class="fas fa-ticket-alt"></i> Diskon Kupon (<?= $couponDiscount ?>%)</span>
                                <span>-Rp <?= number_format($couponDiscountAmount, 0, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span><i class="fas fa-check-circle"></i> Pajak</span>
                                <span>Gratis (0%)</span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span><i class="fas fa-check-circle"></i> Biaya Layanan</span>
                                <span>Gratis</span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between total-row">
                                <span>Total Pembayaran</span>
                                <span class="text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($spinDiscountPercent > 0 || $couponDiscountAmount > 0): ?>
                            <small class="text-success mt-1 d-block">
                                <i class="fas fa-info-circle"></i> 
                                Kamu hemat total Rp <?= number_format($spinDiscountAmount + $couponDiscountAmount, 0, ',', '.') ?>!
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="section-card">
                            <h5><i class="fas fa-wallet"></i> Metode Pembayaran</h5>
                            <p class="text-muted small mb-3">Pilih salah satu metode pembayaran:</p>
                            
                            <div class="row g-3" id="paymentOptions">
                                <div class="col-6 col-md-3">
                                    <div class="payment-option" onclick="selectPayment('dana', this)">
                                        <i class="fas fa-wallet text-info"></i>
                                        <h6>DANA</h6>
                                        <small>085710785244</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="payment-option" onclick="selectPayment('ovo', this)">
                                        <i class="fas fa-mobile-alt" style="color:#9b59b6;"></i>
                                        <h6>OVO</h6>
                                        <small>085710785244</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="payment-option" onclick="selectPayment('gopay', this)">
                                        <i class="fas fa-google-wallet text-success"></i>
                                        <h6>GoPay</h6>
                                        <small>085710785244</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="payment-option" onclick="selectPayment('qris', this)">
                                        <i class="fas fa-qrcode text-warning"></i>
                                        <h6>QRIS</h6>
                                        <small>Scan QR</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="payment_method" id="selectedPayment" required>
                        </div>
                        
                    </div>
                    
                    <!-- RIGHT COLUMN - SUMMARY -->
                    <div class="col-lg-4">
                        <div class="summary-card">
                            <h5><i class="fas fa-receipt text-primary"></i> Ringkasan Pesanan</h5>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($spinDiscountPercent > 0): ?>
                            <div class="d-flex justify-content-between mb-2 discount-row">
                                <span>Diskon Spin</span>
                                <span>-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($couponDiscountAmount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 discount-row">
                                <span>Diskon Kupon</span>
                                <span>-Rp <?= number_format($couponDiscountAmount, 0, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Pajak</span>
                                <span>Gratis</span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="fw-bold mb-0">Total</h5>
                                <h4 class="fw-bold text-primary mb-0">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h4>
                            </div>
                            
                            <button type="submit" name="process_checkout" class="btn-pay" id="payButton" disabled>
                                <i class="fas fa-lock"></i> Bayar Sekarang
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt"></i> Pembayaran aman & terenkripsi
                                </small>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <h6 class="fw-bold mb-2"><i class="fas fa-info-circle"></i> Info Transfer</h6>
                                <p class="mb-1"><strong>Nomor:</strong> 085710785244</p>
                                <p class="mb-0"><strong>Atas Nama:</strong> WebPro UMKM</p>
                                <hr>
                                <small>DANA | OVO | GoPay | QRIS</small>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> <strong>WebPro UMKM</strong>. All rights reserved.</p>
            <small class="opacity-75">Pembayaran: DANA/OVO/GoPay/QRIS - 085710785244</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select payment method
        function selectPayment(method, element) {
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedPayment').value = method;
            document.getElementById('payButton').disabled = false;
        }
        
        // Validate before submit
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.name === 'process_checkout') {
                const payment = document.getElementById('selectedPayment').value;
                if (!payment) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih Metode Pembayaran',
                        text: 'Silakan pilih metode pembayaran terlebih dahulu!',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }
                
                e.preventDefault();
                Swal.fire({
                    title: 'Konfirmasi Pesanan',
                    html: `
                        <p>Total pembayaran:</p>
                        <h3 class="text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h3>
                        <p>Metode: <strong>${payment.toUpperCase()}</strong></p>
                        <p>Pastikan data sudah benar!</p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Pesan Sekarang!',
                    cancelButtonText: 'Cek Lagi',
                    confirmButtonColor: '#4e73df'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Submit form
                        const form = document.getElementById('checkoutForm');
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'process_checkout';
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                        form.submit();
                    }
                });
            }
        });
        
        console.log('🛒 Checkout Page - WebPro UMKM');
    </script>
</body>
</html>