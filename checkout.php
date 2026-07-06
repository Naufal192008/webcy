<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$error = '';

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
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.discount, p.stock, p.images 
                       FROM cart c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.user_id = ? AND p.is_active = 1");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// Redirect if cart empty
if (empty($cartItems)) {
    redirect('cart.php', 'Keranjang masih kosong!', 'warning');
}

// ==================== PERHITUNGAN DETAIL ====================
$subtotal = 0;

foreach ($cartItems as $item) {
    $price = $item['discount'] ? $item['price'] - ($item['price'] * $item['discount'] / 100) : $item['price'];
    $subtotal += $price * $item['quantity'];
}

// Hitung diskon dari spin wheel
if ($spinDiscountPercent > 0) {
    $spinDiscountAmount = $subtotal * $spinDiscountPercent / 100;
}

$totalAfterSpinDiscount = $subtotal - $spinDiscountAmount;
$taxPercent = 0;
$taxAmount = $totalAfterSpinDiscount * $taxPercent / 100;
$grandTotal = $totalAfterSpinDiscount + $taxAmount;

// ==================== PROCESS CHECKOUT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            
            // Get product names
            $productNames = array_map(function($item) { return $item['name']; }, $cartItems);
            $productName = implode(', ', $productNames);
            
            // Create order
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, product_name, total_price, payment_method, payment_number, note, status) VALUES (?, ?, ?, ?, '085710785244', ?, 'pending')");
            $stmt->execute([$userId, $productName, $grandTotal, $paymentMethod, $note]);
            $orderId = $pdo->lastInsertId();
            
            // Tandai diskon spin sudah dipakai
            if ($activeSpinId) {
                $stmt = $pdo->prepare("UPDATE spin_history SET is_used = 1, used_at = NOW(), order_id = ? WHERE id = ?");
                $stmt->execute([$orderId, $activeSpinId]);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Update product sold count
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("UPDATE products SET sold = sold + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $pdo->commit();
            
            // Redirect to payment page
            redirect('payment.php?order_id=' . $orderId);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memproses pesanan: ' . $e->getMessage();
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --warning: #f6c23e;
        }
        
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 3px 20px rgba(0,0,0,0.06);
        }
        
        .section-card h5 { font-weight: 700; margin-bottom: 20px; }
        
        .payment-option {
            cursor: pointer;
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            position: relative;
        }
        
        .payment-option:hover {
            border-color: var(--primary);
            background: #f8f9ff;
            transform: translateY(-3px);
        }
        
        .payment-option.selected {
            border-color: var(--primary);
            background: #f0f4ff;
            box-shadow: 0 5px 20px rgba(78,115,223,0.2);
        }
        
        .payment-option.selected::after {
            content: '✓';
            position: absolute;
            top: 10px; right: 15px;
            background: var(--primary);
            color: white;
            width: 30px; height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .payment-option i { font-size: 2.5rem; margin-bottom: 10px; }
        
        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .btn-pay {
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
        
        .btn-pay:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
        }
        
        .btn-pay:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 2px solid #e8e8e8;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.15);
        }
        
        .discount-badge {
            background: #fff3cd;
            border: 2px dashed #f6c23e;
            border-radius: 10px;
            padding: 15px 18px;
            margin: 10px 0;
            font-weight: 600;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .detail-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            border-top: 2px solid #eee;
            padding-top: 15px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .payment-option { margin-bottom: 15px; }
            .summary-card { position: relative; top: 0; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-globe"></i> WebPro UMKM
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="cart.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali ke Keranjang
                </a>
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="fw-bold mb-4">
            <i class="fas fa-credit-card text-primary"></i> Checkout
        </h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    
                    <!-- Customer Info -->
                    <div class="section-card">
                        <h5><i class="fas fa-user text-primary"></i> Informasi Pemesan</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nama Lengkap</label>
                                <input type="text" class="form-control" value="<?= escape($user['full_name']) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" class="form-control" value="<?= escape($user['email']) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">No. Handphone *</label>
                                <input type="tel" name="customer_phone" class="form-control" 
                                       value="<?= escape($user['phone']) ?>" required 
                                       placeholder="085710785244">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Catatan (Opsional)</label>
                                <input type="text" name="note" class="form-control" 
                                       placeholder="Tambahkan catatan untuk pesanan...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="section-card">
                        <h5><i class="fas fa-box text-primary"></i> Item Pesanan (<?= count($cartItems) ?> item)</h5>
                        
                        <?php foreach ($cartItems as $item): 
                            $price = $item['discount'] ? $item['price'] - ($item['price'] * $item['discount'] / 100) : $item['price'];
                            $itemTotal = $price * $item['quantity'];
                            $images = !empty($item['images']) ? explode(',', $item['images']) : ['https://via.placeholder.com/60'];
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= escape(trim($images[0])) ?>" 
                                     style="width:60px;height:45px;object-fit:cover;border-radius:10px;" 
                                     alt="<?= escape($item['name']) ?>">
                                <div>
                                    <strong><?= escape($item['name']) ?></strong>
                                    <?php if ($item['discount']): ?>
                                        <br><small class="text-muted">Diskon produk: <?= $item['discount'] ?>%</small>
                                    <?php endif; ?>
                                    <br><small class="text-muted">
                                        <?= $item['quantity'] ?> x Rp <?= number_format($price, 0, ',', '.') ?>
                                    </small>
                                </div>
                            </div>
                            <strong>Rp <?= number_format($itemTotal, 0, ',', '.') ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Detail Pembayaran -->
                    <div class="section-card">
                        <h5><i class="fas fa-calculator text-primary"></i> Detail Pembayaran</h5>
                        
                        <div class="detail-row">
                            <span>Subtotal (<?= count($cartItems) ?> item)</span>
                            <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($spinDiscountPercent > 0): ?>
                        <div class="discount-badge">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-ticket-alt text-warning"></i>
                                    <strong>Diskon Spin Wheel (<?= $spinDiscountPercent ?>%)</strong>
                                    <br><small class="text-muted">Berlaku sampai: <?= date('d M Y', strtotime($activeSpin['expiry_date'])) ?></small>
                                </div>
                                <span class="text-success fw-bold fs-5">-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-row text-success">
                            <span><i class="fas fa-check-circle"></i> Pajak (0%)</span>
                            <span>Gratis</span>
                        </div>
                        
                        <div class="detail-row text-success">
                            <span><i class="fas fa-check-circle"></i> Biaya Layanan</span>
                            <span>Gratis</span>
                        </div>
                        
                        <div class="detail-row total">
                            <span>Total Pembayaran</span>
                            <span class="text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($spinDiscountPercent > 0): ?>
                        <small class="text-success">
                            <i class="fas fa-info-circle"></i> 
                            Kamu hemat Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?> dari Spin Wheel!
                        </small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="section-card">
                        <h5><i class="fas fa-wallet text-primary"></i> Metode Pembayaran</h5>
                        <p class="text-muted mb-3">Pilih salah satu metode pembayaran:</p>
                        
                        <div class="row g-3" id="paymentOptions">
                            <div class="col-6 col-md-3">
                                <div class="payment-option" onclick="selectPayment('dana', this)">
                                    <i class="fas fa-wallet text-info"></i>
                                    <h6 class="fw-bold">DANA</h6>
                                    <small class="text-muted">085710785244</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="payment-option" onclick="selectPayment('ovo', this)">
                                    <i class="fas fa-mobile-alt" style="color:#9b59b6;"></i>
                                    <h6 class="fw-bold">OVO</h6>
                                    <small class="text-muted">085710785244</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="payment-option" onclick="selectPayment('gopay', this)">
                                    <i class="fas fa-google-wallet text-success"></i>
                                    <h6 class="fw-bold">GoPay</h6>
                                    <small class="text-muted">085710785244</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="payment-option" onclick="selectPayment('qris', this)">
                                    <i class="fas fa-qrcode text-warning"></i>
                                    <h6 class="fw-bold">QRIS</h6>
                                    <small class="text-muted">Scan QR</small>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="selectedPayment" required>
                    </div>
                    
                </div>
                
                <!-- Right Column - Summary -->
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-receipt text-primary"></i> Ringkasan Pesanan
                        </h5>
                        
                        <div class="detail-row">
                            <span>Subtotal</span>
                            <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($spinDiscountPercent > 0): ?>
                        <div class="detail-row text-success">
                            <span>Diskon Spin (<?= $spinDiscountPercent ?>%)</span>
                            <span>-Rp <?= number_format($spinDiscountAmount, 0, ',', '.') ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-row text-success">
                            <span>Pajak</span>
                            <span>Gratis</span>
                        </div>
                        
                        <hr>
                        
                        <div class="detail-row total">
                            <span>Total</span>
                            <span class="text-primary">Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
                        </div>
                        
                        <button type="submit" class="btn-pay mt-3" id="payButton" disabled>
                            <i class="fas fa-lock"></i> Bayar Sekarang
                        </button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt"></i> Pembayaran aman terenkripsi
                            </small>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <h6 class="fw-bold"><i class="fas fa-info-circle"></i> Info Transfer</h6>
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

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
            <small>Pembayaran: DANA/OVO/GoPay/QRIS - 085710785244</small>
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
        
        // Confirm before submit
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const payment = document.getElementById('selectedPayment').value;
            
            if (!payment) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih Pembayaran',
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
                    e.target.submit();
                }
            });
        });
    </script>
</body>
</html>