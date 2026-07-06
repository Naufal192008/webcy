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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --dana: #108EE9;
            --ovo: #9B59B6;
            --gopay: #00AA13;
            --qris: #FF6B35;
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .payment-card {
            background: white;
            border-radius: 25px;
            padding: 40px 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .amount-display {
            font-size: 2.5rem;
            font-weight: 900;
            color: #4e73df;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
        }
        
        .payment-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 20px;
            border: 3px solid #e0e0e0;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            font-weight: 700;
            gap: 10px;
        }
        
        .payment-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .payment-btn.dana { border-color: var(--dana); }
        .payment-btn.dana:hover { background: #E8F4FD; }
        .payment-btn.dana i { color: var(--dana); }
        
        .payment-btn.ovo { border-color: var(--ovo); }
        .payment-btn.ovo:hover { background: #F3E8F8; }
        .payment-btn.ovo i { color: var(--ovo); }
        
        .payment-btn.gopay { border-color: var(--gopay); }
        .payment-btn.gopay:hover { background: #E8F5E9; }
        .payment-btn.gopay i { color: var(--gopay); }
        
        .payment-btn.qris { border-color: var(--qris); }
        .payment-btn.qris:hover { background: #FFF0E8; }
        .payment-btn.qris i { color: var(--qris); }
        
        .payment-btn i { font-size: 2.5rem; }
        
        .qris-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .qris-section img {
            max-width: 200px;
            border-radius: 15px;
        }
        
        .copy-btn {
            background: #4e73df;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .copy-btn:hover { background: #224abe; }
        
        .countdown {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            font-weight: 700;
        }
        
        .step-list {
            text-align: left;
            background: #f0f4ff;
            border-radius: 15px;
            padding: 20px 30px;
            margin: 20px 0;
        }
        
        .step-list li {
            margin-bottom: 10px;
            padding: 5px 0;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <!-- Header -->
            <div class="mb-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h4 class="fw-bold">Pesanan Berhasil Dibuat!</h4>
                <p class="text-muted">Segera lakukan pembayaran</p>
            </div>
            
            <!-- Order Info -->
            <div class="order-info">
                <small class="text-muted">ORDER #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></small>
                <div class="amount-display mt-2">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></div>
                <small class="text-muted"><?= $order['product_name'] ?></small>
            </div>
            
            <!-- Countdown -->
            <div class="countdown">
                ⏰ Bayar sebelum: <strong id="countdown">24:00:00</strong>
            </div>
            
            <!-- Payment Methods -->
            <h5 class="fw-bold mt-4">Pilih Metode Pembayaran</h5>
            <div class="payment-methods">
                <a href="https://link.dana.id/qr/085710785244" target="_blank" class="payment-btn dana">
                    <i class="fas fa-wallet"></i>
                    <span>DANA</span>
                    <small>Buka Aplikasi</small>
                </a>
                <a href="ovo://payment?amount=<?= $order['total_price'] ?>&phone=085710785244" class="payment-btn ovo">
                    <i class="fas fa-mobile-alt"></i>
                    <span>OVO</span>
                    <small>Buka Aplikasi</small>
                </a>
                <a href="gopay://pay?amount=<?= $order['total_price'] ?>&phone=085710785244" class="payment-btn gopay">
                    <i class="fas fa-google-wallet"></i>
                    <span>GoPay</span>
                    <small>Buka Aplikasi</small>
                </a>
                <a href="#qrisSection" class="payment-btn qris" onclick="document.getElementById('qrisSection').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-qrcode"></i>
                    <span>QRIS</span>
                    <small>Scan QR</small>
                </a>
            </div>
            
            <!-- Manual Transfer Info -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="fw-bold">Transfer Manual</h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="text-primary mb-0">085710785244</h5>
                            <small class="text-muted">a.n. WebPro UMKM</small>
                        </div>
                        <button class="copy-btn" onclick="copyNumber()">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Steps -->
            <div class="step-list mt-4">
                <h6 class="fw-bold">📋 Cara Pembayaran:</h6>
                <ol>
                    <li>Pilih metode pembayaran di atas</li>
                    <li>Transfer tepat <strong>Rp <?= number_format($order['total_price'], 0, ',', '.') ?></strong></li>
                    <li>Ke nomor <strong>085710785244</strong> a.n. WebPro UMKM</li>
                    <li>Admin akan verifikasi pembayaran Anda</li>
                    <li>Website akan diproses setelah pembayaran dikonfirmasi</li>
                </ol>
            </div>
            
            <!-- QRIS Section -->
            <div id="qrisSection" class="qris-section mt-4">
                <h5 class="fw-bold mb-3">Scan QRIS</h5>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://link.dana.id/qr/085710785244?amount=<?= $order['total_price'] ?>" 
                     alt="QRIS Code" class="img-fluid mb-3">
                <p class="text-muted">Scan dengan aplikasi DANA/OVO/GoPay/LinkAja</p>
            </div>
            
            <!-- Buttons -->
            <div class="d-grid gap-2 mt-4">
                <a href="https://wa.me/6285710785244?text=Halo%20Admin%2C%20saya%20sudah%20transfer%20untuk%20order%20%23<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>%20sebesar%20Rp%20<?= number_format($order['total_price'], 0, ',', '.') ?>" 
                   target="_blank" class="btn btn-success btn-lg rounded-pill">
                    <i class="fab fa-whatsapp"></i> Konfirmasi via WhatsApp
                </a>
                <a href="history.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="fas fa-history"></i> Lihat Riwayat Pesanan
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown timer (24 jam)
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
        
        // Copy number
        function copyNumber() {
            navigator.clipboard.writeText('085710785244').then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Disalin!',
                    text: '085710785244',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        }
        
        // Start countdown
        startCountdown();
        
        // Auto-redirect ke aplikasi jika mobile
        function openPaymentApp(method) {
            const amount = <?= $order['total_price'] ?>;
            const phone = '085710785244';
            
            switch(method) {
                case 'dana':
                    window.location.href = `https://link.dana.id/qr/${phone}`;
                    break;
                case 'ovo':
                    window.location.href = `ovo://payment?amount=${amount}&phone=${phone}`;
                    break;
                case 'gopay':
                    window.location.href = `gopay://pay?amount=${amount}&phone=${phone}`;
                    break;
            }
        }
    </script>
</body>
</html>