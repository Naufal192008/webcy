<?php
require_once 'config/database.php';
checkAuth();

$orderId = $_GET['order_id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: history.php');
    exit;
}

$orderNumber = str_pad($order['id'], 6, '0', STR_PAD_LEFT);
$totalPrice = $order['total_price'];
$paymentMethod = $order['payment_method'];
$paymentNumber = '085710785244';
$paymentName = 'WebPro UMKM';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4e73df">
    <title>Pembayaran #<?= $orderNumber ?> - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --info: #36b9cc;
            --dark: #1a1a2e;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            min-height: 100dvh;
            padding: clamp(15px, 3vw, 30px);
        }
        
        .container-box {
            max-width: 550px;
            margin: 0 auto;
        }
        
        /* BACK BUTTON */
        .btn-back-top {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 50px;
            transition: all 0.3s;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }
        
        .btn-back-top:hover {
            color: var(--primary);
            background: #f8f9ff;
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        /* NAVBAR */
        .navbar-payment {
            background: white;
            border-radius: 15px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .navbar-payment .brand {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .navbar-payment .brand i {
            font-size: 1.4rem;
        }
        
        .navbar-payment .nav-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-nav {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #ddd;
            background: white;
            color: #555;
        }
        
        .btn-nav:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-nav.primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-nav.primary:hover {
            background: #224abe;
        }
        
        /* HEADER */
        .payment-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .payment-header .success-icon {
            width: 70px; height: 70px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            color: white;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .payment-header h4 {
            font-weight: 800;
            color: #333;
        }
        
        /* CARDS */
        .card-payment {
            background: white;
            border-radius: 20px;
            padding: clamp(18px, 4vw, 25px);
            margin-bottom: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }
        
        .card-payment h5 {
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ORDER INFO */
        .order-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-info:last-child { border-bottom: none; }
        .order-info .label { color: #888; font-size: 0.9rem; }
        .order-info .value { font-weight: 700; color: #333; }
        
        .total-amount {
            background: linear-gradient(135deg, #f8f9ff, #e8ecff);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
        }
        
        .total-amount .amount {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            font-weight: 900;
            color: var(--primary);
        }
        
        /* PAYMENT NUMBER */
        .payment-number-box {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
        }
        
        .payment-number-box .number {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 800;
            color: #333;
            letter-spacing: 2px;
        }
        
        .payment-number-box .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        
        .payment-number-box .copy-btn:hover { background: #224abe; }
        
        /* QR CODE */
        .qr-section {
            text-align: center;
            padding: 20px;
        }
        
        .qr-section img {
            max-width: 200px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        /* STEPS */
        .steps-list {
            list-style: none;
            padding: 0;
            counter-reset: step;
        }
        
        .steps-list li {
            counter-increment: step;
            padding: 10px 0 10px 45px;
            position: relative;
            font-size: 0.95rem;
            color: #555;
        }
        
        .steps-list li::before {
            content: counter(step);
            position: absolute;
            left: 0;
            top: 8px;
            width: 30px; height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        
        /* BUTTONS */
        .btn-group-bottom {
            display: grid;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-wa {
            background: #25D366;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 14px;
            font-weight: 700;
            font-size: 1rem;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: 0.3s;
        }
        
        .btn-wa:hover { background: #1da851; color: white; }
        
        .btn-outline {
            background: white;
            color: #666;
            border: 2px solid #ddd;
            border-radius: 15px;
            padding: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: 0.3s;
        }
        
        .btn-outline:hover { border-color: #999; color: #333; }
        
        /* COUNTDOWN */
        .countdown-timer {
            background: #fff3cd;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-weight: 700;
            margin: 10px 0;
        }
        
        /* TABS */
        .payment-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 5px;
        }
        
        .payment-tab {
            flex: 1;
            min-width: 70px;
            padding: 10px;
            text-align: center;
            border-radius: 12px;
            background: #f0f0f0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
            border: none;
        }
        
        .payment-tab.active {
            background: var(--primary);
            color: white;
        }
        
        @media (max-width: 400px) {
            .payment-tab { font-size: 0.75rem; padding: 8px 6px; }
        }
    </style>
</head>
<body>
    <div class="container-box">
        
        <!-- NAVBAR -->
        <div class="navbar-payment">
            <a href="index.php" class="brand">
                <i class="fas fa-globe"></i> WebPro UMKM
            </a>
            <div class="nav-actions">
                <a href="index.php" class="btn-nav">
                    <i class="fas fa-store"></i> Belanja
                </a>
                <a href="cart.php" class="btn-nav">
                    <i class="fas fa-shopping-cart"></i> Keranjang
                </a>
                <a href="dashboard.php" class="btn-nav primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- BACK BUTTON -->
        <a href="index.php" class="btn-back-top animate__animated animate__fadeIn">
            <i class="fas fa-arrow-left"></i> Kembali ke Halaman Belanja
        </a>
        
        <!-- HEADER -->
        <div class="payment-header">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h4>Pesanan Berhasil Dibuat!</h4>
            <p class="text-muted">Segera selesaikan pembayaran Anda</p>
        </div>
        
        <!-- ORDER DETAILS -->
        <div class="card-payment">
            <h5><i class="fas fa-receipt text-primary"></i> Detail Pesanan</h5>
            <div class="order-info">
                <span class="label">Order ID</span>
                <span class="value">#<?= $orderNumber ?></span>
            </div>
            <div class="order-info">
                <span class="label">Produk</span>
                <span class="value"><?= escape($order['product_name']) ?></span>
            </div>
            <div class="order-info">
                <span class="label">Metode</span>
                <span class="value"><?= strtoupper($paymentMethod) ?></span>
            </div>
            <div class="total-amount">
                <small class="text-muted">Total Pembayaran</small>
                <div class="amount">Rp <?= number_format($totalPrice, 0, ',', '.') ?></div>
            </div>
        </div>
        
        <!-- PAYMENT METHOD TABS -->
        <div class="card-payment">
            <h5><i class="fas fa-wallet text-primary"></i> Pilih Metode Transfer</h5>
            <div class="payment-tabs" id="paymentTabs">
                <button class="payment-tab active" onclick="showPayment('dana')">DANA</button>
                <button class="payment-tab" onclick="showPayment('ovo')">OVO</button>
                <button class="payment-tab" onclick="showPayment('gopay')">GoPay</button>
                <button class="payment-tab" onclick="showPayment('qris')">QRIS</button>
            </div>
            
            <div id="paymentContent">
                <!-- DANA -->
                <div id="pay-dana" class="payment-detail">
                    <div class="payment-number-box">
                        <small class="text-muted">Nomor DANA</small>
                        <div class="number"><?= $paymentNumber ?></div>
                        <small class="text-muted">a.n. <?= $paymentName ?></small><br>
                        <button class="copy-btn" onclick="copyNumber('<?= $paymentNumber ?>')">
                            <i class="fas fa-copy"></i> Salin Nomor
                        </button>
                    </div>
                </div>
                
                <!-- OVO -->
                <div id="pay-ovo" class="payment-detail" style="display:none;">
                    <div class="payment-number-box">
                        <small class="text-muted">Nomor OVO</small>
                        <div class="number"><?= $paymentNumber ?></div>
                        <small class="text-muted">a.n. <?= $paymentName ?></small><br>
                        <button class="copy-btn" onclick="copyNumber('<?= $paymentNumber ?>')">
                            <i class="fas fa-copy"></i> Salin Nomor
                        </button>
                    </div>
                </div>
                
                <!-- GoPay -->
                <div id="pay-gopay" class="payment-detail" style="display:none;">
                    <div class="payment-number-box">
                        <small class="text-muted">Nomor GoPay</small>
                        <div class="number"><?= $paymentNumber ?></div>
                        <small class="text-muted">a.n. <?= $paymentName ?></small><br>
                        <button class="copy-btn" onclick="copyNumber('<?= $paymentNumber ?>')">
                            <i class="fas fa-copy"></i> Salin Nomor
                        </button>
                    </div>
                </div>
                
                <!-- QRIS -->
                <div id="pay-qris" class="payment-detail" style="display:none;">
                    <div class="qr-section">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=085710785244" alt="QR Code" onerror="this.src='https://via.placeholder.com/200x200?text=QR+Code'">
                        <p class="mt-2 text-muted">Scan dengan aplikasi DANA/OVO/GoPay/LinkAja</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- STEPS -->
        <div class="card-payment">
            <h5><i class="fas fa-list-ol text-primary"></i> Cara Membayar</h5>
            <ol class="steps-list">
                <li>Pilih metode pembayaran di atas</li>
                <li>Buka aplikasi (DANA/OVO/GoPay) di HP Anda</li>
                <li>Pilih menu <strong>Transfer</strong> atau <strong>Kirim</strong></li>
                <li>Masukkan nomor <strong><?= $paymentNumber ?></strong></li>
                <li>Masukkan nominal <strong>Rp <?= number_format($totalPrice, 0, ',', '.') ?></strong></li>
                <li>Konfirmasi & kirim pembayaran</li>
                <li>Klik tombol WhatsApp di bawah untuk konfirmasi</li>
            </ol>
        </div>
        
        <!-- COUNTDOWN -->
        <div class="countdown-timer">
            ⏰ Selesaikan pembayaran dalam: <strong id="countdown">24:00:00</strong>
        </div>
        
        <!-- BUTTONS -->
        <div class="btn-group-bottom">
            <a href="https://wa.me/6285710785244?text=Halo%20Admin%20WebPro%20UMKM%2C%20saya%20sudah%20transfer%20untuk%20order%20%23<?= $orderNumber ?>%20sebesar%20Rp%20<?= number_format($totalPrice, 0, ',', '.') ?>%20via%20<?= strtoupper($paymentMethod) ?>." 
               target="_blank" class="btn-wa">
                <i class="fab fa-whatsapp"></i> Konfirmasi via WhatsApp
            </a>
            <a href="history.php" class="btn-outline">
                <i class="fas fa-history"></i> Lihat Riwayat Pesanan
            </a>
            <a href="index.php" class="btn-outline">
                <i class="fas fa-store"></i> Kembali Belanja
            </a>
        </div>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show payment detail based on tab
        function showPayment(method) {
            document.querySelectorAll('.payment-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            document.querySelectorAll('.payment-detail').forEach(d => d.style.display = 'none');
            const target = document.getElementById('pay-' + method);
            if (target) target.style.display = 'block';
        }
        
        // Copy number to clipboard
        function copyNumber(number) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(number).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil Disalin!',
                        text: number,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                });
            } else {
                const input = document.createElement('input');
                input.value = number;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                Swal.fire('Berhasil Disalin!', number, 'success');
            }
        }
        
        // Countdown timer (24 hours)
        function startCountdown() {
            const endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
            
            setInterval(() => {
                const now = new Date().getTime();
                const distance = endTime - now;
                
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').textContent = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');
            }, 1000);
        }
        
        startCountdown();
    </script>
</body>
</html>