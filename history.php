<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];

// Get all orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Check if there's a last order message
$lastOrder = $_SESSION['last_order'] ?? null;
if ($lastOrder) {
    unset($_SESSION['last_order']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .history-container { max-width: 1000px; margin: 0 auto; }
        .order-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 5px solid #4e73df;
        }
        .order-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.1); transform: translateX(5px); }
        .order-card.status-pending { border-left-color: #f6c23e; }
        .order-card.status-paid { border-left-color: #36b9cc; }
        .order-card.status-processing { border-left-color: #4e73df; }
        .order-card.status-completed { border-left-color: #1cc88a; }
        .order-card.status-cancelled { border-left-color: #e74a3b; }
        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-state i { font-size: 5rem; color: #ddd; margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-store"></i> Belanja</a>
                <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="history-container">
            <h2 class="fw-bold mb-4"><i class="fas fa-history text-primary"></i> Riwayat Pesanan</h2>
            
            <?php if ($lastOrder): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Pesanan Berhasil!</h5>
                <p class="mb-1">Pesanan #<?= $lastOrder['id'] ?> telah dibuat.</p>
                <p class="mb-0">Silakan transfer <strong>Rp <?= number_format($lastOrder['total'], 0, ',', '.') ?></strong> ke <strong><?= strtoupper($lastOrder['payment']) ?> 085710785244</strong></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= $flash['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>Belum Ada Pesanan</h3>
                    <p class="text-muted">Anda belum memiliki riwayat pesanan.</p>
                    <a href="index.php" class="btn btn-primary btn-lg rounded-pill px-5 mt-3">
                        <i class="fas fa-store"></i> Mulai Belanja
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): 
                    $statusColors = [
                        'pending' => 'warning',
                        'paid' => 'info',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger'
                    ];
                    $statusIcons = [
                        'pending' => 'clock',
                        'paid' => 'check-circle',
                        'processing' => 'spinner',
                        'completed' => 'check-double',
                        'cancelled' => 'times-circle'
                    ];
                    $statusColor = $statusColors[$order['status']] ?? 'secondary';
                    $statusIcon = $statusIcons[$order['status']] ?? 'info-circle';
                    $date = date('d M Y H:i', strtotime($order['created_at']));
                ?>
                <div class="order-card status-<?= $order['status'] ?> animate__animated animate__fadeInUp">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <h6 class="fw-bold mb-1">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h6>
                            <small class="text-muted"><?= $date ?></small>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1 fw-bold"><?= escape($order['product_name']) ?></p>
                            <small class="text-muted">Pembayaran: <?= strtoupper($order['payment_method']) ?></small>
                        </div>
                        <div class="col-md-2">
                            <strong class="text-primary">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></strong>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-<?= $statusColor ?> status-badge">
                                <i class="fas fa-<?= $statusIcon ?>"></i> 
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Transfer ke:</small><br>
                            <strong>085710785244</strong><br>
                            <small class="text-muted">a.n. WebPro UMKM</small>
                        </div>
                        <div class="col-md-1 text-end">
                            <?php if ($order['status'] === 'pending'): ?>
                                <span class="text-warning" title="Menunggu Pembayaran">
                                    <i class="fas fa-hourglass-half fa-2x"></i>
                                </span>
                            <?php elseif ($order['status'] === 'completed'): ?>
                                <span class="text-success" title="Selesai">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="text-danger" title="Dibatalkan">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </span>
                            <?php else: ?>
                                <span class="text-info" title="Diproses">
                                    <i class="fas fa-sync-alt fa-2x"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Payment Info -->
                <div class="alert alert-info mt-4">
                    <h5 class="fw-bold"><i class="fas fa-info-circle"></i> Informasi Pembayaran</h5>
                    <p class="mb-1">Semua pembayaran ditransfer ke:</p>
                    <h4 class="text-primary">085710785244</h4>
                    <p class="mb-0">a.n. <strong>WebPro UMKM</strong></p>
                    <hr>
                    <small>Metode: DANA | OVO | GoPay | QRIS</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>