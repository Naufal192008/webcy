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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Checkout - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --warning: #f6c23e; --danger: #e74a3b; }
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .section-card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; box-shadow: 0 3px 20px rgba(0,0,0,0.06); }
        .section-card h5 { font-weight: 700; margin-bottom: 15px; }
        .payment-option { cursor: pointer; border: 3px solid #e0e0e0; border-radius: 15px; padding: 20px; text-align: center; transition: all 0.3s; position: relative; }
        .payment-option:hover { border-color: var(--primary); background: #f8f9ff; transform: translateY(-3px); }
        .payment-option.selected { border-color: var(--primary); background: #f0f4ff; box-shadow: 0 5px 20px rgba(78,115,223,0.2); }
        .payment-option.selected::after { content: '✓'; position: absolute; top: 10px; right: 15px; background: var(--primary); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .payment-option i { font-size: 2.5rem; margin-bottom: 10px; }
        .summary-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); position: sticky; top: 20px; }
        .btn-pay { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 15px; padding: 16px; font-weight: 700; font-size: 1.1rem; width: 100%; transition: all 0.3s; }
        .btn-pay:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        .btn-pay:disabled { background: #ccc; cursor: not-allowed; }
        .form-control { border-radius: 12px; padding: 12px 18px; border: 2px solid #e8e8e8; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.15); }
        .discount-row { color: var(--success); font-weight: 600; }
        @media (max-width: 768px) { .payment-option { margin-bottom: 15px; } .summary-card { position: relative; top: 0; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <div class="ms-auto">
                <a href="cart.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Keranjang</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="fw-bold mb-4"><i class="fas fa-credit-card text-primary"></i> Checkout</h2>
        
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

        <form method="POST" id="checkoutForm">
            <div class="row">
                <div class="col-lg-8">
                    
                    <!-- Customer Info -->
                    <div class="section-card">
                        <h5><i class="fas fa-user text-primary"></i> Informasi Pemesan</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold">Nama</label><input type="text" class="form-control" value="<?= escape($user['full_name']) ?>" readonly></div>
                            <div class="col-md-6 mb-3"><label class="form-label fw-bold">No. HP *</label><input type="tel" name="customer_phone" class="form-control" value="<?= escape($user['phone']) ?>" required></div>
                            <div class="col-12 mb-3"><label class="form-label fw-bold">Catatan</label><input type="text" name="note" class="form-control" placeholder="Tambahkan catatan..."></div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="section-card">
                        <h5><i class="fas fa-box text-primary"></i> Item (<?= count($cartItems) ?>)</h5>
                        <?php foreach ($cartItems as $item): 
                            $price = $item['discount'] ? $item['price'] - ($item['price'] * $item['discount'] / 100) : $item['price'];
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <?php $imgs = !empty($item['images']) ? explode(',', $item['images']) : ['https://via.placeholder.com/60']; ?>
                                <img src="<?= escape(trim($imgs[0])) ?>" style="width:50px;height:40px;object-fit:cover;border-radius:8px;">
                                <div><strong><?= escape($item['name']) ?></strong><br><small class="text-muted"><?= $item['quantity'] ?> x Rp <?= number_format($price, 0, ',', '.') ?></small></div>
                            </div>
                            <strong>Rp <?= number_format($price * $item['quantity'], 0, ',', '.') ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- COUPON SECTION -->
                    <div class="section-card">
                        <h5><i class="fas fa-ticket-alt text-warning"></i> Kode Kupon</h5>
                        <?php if (isset($couponError)): ?>
                            <div class="alert alert-warning"><?= $couponError ?></div>
                        <?php endif; ?>
                        <?php if ($couponData): ?>
                            <div class="alert alert-success d-flex justify-content-between align-items-center">
                                <span>🎫 Kupon <strong><?= escape($couponCode) ?></strong> - Diskon <?= $couponDiscount ?>%</span>
                                <a href="?remove_coupon=1" class="btn btn-sm btn-outline-danger rounded-pill">✕ Hapus</a>
                            </div>
                        <?php else: ?>
                            <div class="input-group">
                                <input type="text" name="coupon_code" class="form-control" placeholder="Masukkan kode kupon..." value="<?= escape($couponCode) ?>">
                                <button type="submit" class="btn btn-warning"><i class="fas fa-ticket-alt"></i> Pakai</button>
                            </div>
                            <small class="text-muted">Punya kode kupon? Masukkan di sini untuk dapat diskon tambahan!</small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Detail Pembayaran -->
                    <div class="section-card">
                        <h5><i class="fas fa-calculator text-primary"></i> Detail Pembayaran</h5>
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span></div>
                        <?php if ($spinDiscountPercent > 0): ?>
                        <div class="d-flex justify-content-between mb-2 discount-row"><span>🎡 Diskon Spin (<?= $spinDiscountPercent ?>%)</span><span>-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span></div>
                        <?php endif; ?>
                        <?php if ($couponDiscountAmount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 discount-row"><span>🎫 Diskon Kupon (<?= $couponDiscount ?>%)</span><span>-Rp <?= number_format($couponDiscountAmount, 0, ',', '.') ?></span></div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2 text-success"><span><i class="fas fa-check-circle"></i> Pajak</span><span>Gratis (0%)</span></div>
                        <hr>
                        <div class="d-flex justify-content-between"><h5 class="fw-bold">Total</h5><h4 class="fw-bold text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h4></div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="section-card">
                        <h5><i class="fas fa-wallet text-primary"></i> Metode Pembayaran</h5>
                        <div class="row g-3">
                            <?php foreach (['dana'=>'DANA','ovo'=>'OVO','gopay'=>'GoPay','qris'=>'QRIS'] as $key=>$label): ?>
                            <div class="col-6 col-md-3">
                                <div class="payment-option" onclick="selectPayment('<?= $key ?>', this)">
                                    <i class="fas fa-<?= $key==='qris'?'qrcode':($key==='dana'?'wallet':'mobile-alt') ?> <?= $key==='dana'?'text-info':($key==='ovo'?'text-purple':($key==='gopay'?'text-success':'text-warning')) ?>"></i>
                                    <h6 class="fw-bold"><?= $label ?></h6>
                                    <small>085710785244</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="payment_method" id="selectedPayment" required>
                    </div>
                    
                </div>
                
                <!-- Summary -->
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-3">Ringkasan</h5>
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span></div>
                        <?php if ($spinDiscountPercent > 0): ?>
                        <div class="d-flex justify-content-between mb-2 discount-row"><span>Diskon Spin</span><span>-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span></div>
                        <?php endif; ?>
                        <?php if ($couponDiscountAmount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 discount-row"><span>Diskon Kupon</span><span>-Rp <?= number_format($couponDiscountAmount, 0, ',', '.') ?></span></div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-3"><h5 class="fw-bold">Total</h5><h4 class="fw-bold text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h4></div>
                        
                        <button type="submit" name="process_checkout" class="btn-pay" id="payButton" disabled><i class="fas fa-lock"></i> Bayar Sekarang</button>
                        <small class="text-muted text-center d-block mt-2"><i class="fas fa-shield-alt"></i> Pembayaran aman</small>
                        
                        <div class="alert alert-info mt-3"><small><strong>Transfer ke:</strong><br>085710785244<br>a.n. WebPro UMKM</small></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container"><p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM.</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPayment(method, el) {
            document.querySelectorAll('.payment-option').forEach(e => e.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedPayment').value = method;
            document.getElementById('payButton').disabled = false;
        }
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (!document.getElementById('selectedPayment').value && e.submitter.name === 'process_checkout') {
                e.preventDefault();
                Swal.fire('Pilih Pembayaran', 'Silakan pilih metode pembayaran!', 'warning');
            }
        });
    </script>
</body>
</html>