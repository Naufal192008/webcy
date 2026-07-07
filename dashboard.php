<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Stats
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $userId")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $userId AND status = 'pending'")->fetchColumn();
$completedCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $userId AND status = 'completed'")->fetchColumn();
$cartCount = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = $userId")->fetchColumn();
$totalSpent = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = $userId AND status IN ('paid', 'completed')")->fetchColumn();
$reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews WHERE user_id = $userId")->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentOrders = $stmt->fetchAll();

// Active spin discount
$stmt = $pdo->prepare("SELECT * FROM spin_history WHERE user_id = ? AND is_used = 0 AND discount > 0 AND expiry_date >= ? ORDER BY spin_date DESC LIMIT 1");
$stmt->execute([$userId, date('Y-m-d')]);
$activeSpin = $stmt->fetch();

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
$unreadNotifications = 0;
foreach ($notifications as $n) { if (!$n['is_read']) $unreadNotifications++; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4e73df">
    <title>Dashboard - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --info: #36b9cc;
            --dark: #1a1a2e;
            --light: #f5f7fa;
            --card-shadow: 0 3px 15px rgba(0,0,0,0.06);
            --radius: 20px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 280px;
            min-height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .user-avatar {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.8rem;
            color: white;
            font-weight: 700;
            border: 3px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-header h6 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        
        .sidebar-header small {
            color: var(--success);
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .sidebar-nav {
            padding: 15px 12px;
        }
        
        .sidebar-nav .nav-label {
            color: rgba(255,255,255,0.3);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 15px 15px 8px;
            font-weight: 700;
        }
        
        .sidebar-nav a {
            color: rgba(255,255,255,0.65);
            padding: 13px 18px;
            margin: 2px 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.93rem;
            transition: all 0.3s;
        }
        
        .sidebar-nav a:hover {
            color: white;
            background: rgba(255,255,255,0.06);
        }
        
        .sidebar-nav a.active {
            color: white;
            background: var(--primary);
            box-shadow: 0 5px 20px rgba(78,115,223,0.3);
        }
        
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-nav a .badge { margin-left: auto; }
        
        /* MAIN */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }
        
        /* HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-weight: 800;
            color: #333;
            font-size: 1.8rem;
        }
        
        .greeting-card {
            background: white;
            border-radius: var(--radius);
            padding: 25px 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .greeting-card .welcome-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            flex-shrink: 0;
        }
        
        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            border-left: 4px solid var(--primary);
            cursor: default;
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-card .stat-icon {
            width: 45px; height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-icon.primary { background: rgba(78,115,223,0.1); color: var(--primary); }
        .stat-card .stat-icon.success { background: rgba(28,200,138,0.1); color: var(--success); }
        .stat-card .stat-icon.warning { background: rgba(246,194,62,0.1); color: var(--warning); }
        .stat-card .stat-icon.danger { background: rgba(231,74,59,0.1); color: var(--danger); }
        .stat-card .stat-icon.info { background: rgba(54,185,204,0.1); color: var(--info); }
        
        .stat-card h3 { font-weight: 800; margin: 0; font-size: 1.6rem; color: #333; }
        .stat-card p { margin: 5px 0 0; color: #888; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        
        /* CARDS */
        .content-card {
            background: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }
        
        .content-card h5 { font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        /* SPIN DISCOUNT BANNER */
        .spin-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: var(--radius);
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .spin-banner .discount-badge {
            background: #FFD700;
            color: #333;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 1.3rem;
        }
        
        /* ORDER LIST */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-item:last-child { border-bottom: none; }
        
        /* RESPONSIVE */
        @media (max-width: 992px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 500px) {
            .main-content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .greeting-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR - TANPA MENU ADMIN PANEL -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 2)) ?></div>
            <h6><?= escape($user['full_name']) ?></h6>
            <small><i class="fas fa-circle"></i> <?= $user['role'] === 'admin' ? 'Admin' : 'Member' ?></small>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-label">Menu Utama</div>
            <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="index.php"><i class="fas fa-store"></i> Belanja</a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> Keranjang
                <?php if ($cartCount > 0): ?><span class="badge bg-danger rounded-pill"><?= $cartCount ?></span><?php endif; ?>
            </a>
            <a href="history.php"><i class="fas fa-history"></i> Riwayat</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
            
            <div class="nav-label">Fitur Keren</div>
            <a href="spin-wheel.php"><i class="fas fa-dharmachakra"></i> 🎡 Spin Diskon</a>
            <a href="leaderboard.php"><i class="fas fa-trophy"></i> 🏆 Leaderboard</a>
            
            <div class="nav-label">Lainnya</div>
            <a href="logout.php" style="color:#e74a3b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <h2><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h2>
            <small class="text-muted"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></small>
        </div>
        
        <!-- Greeting -->
        <div class="greeting-card">
            <div class="welcome-icon">👋</div>
            <div>
                <h5 class="fw-bold mb-1">Selamat Datang, <?= escape($user['full_name']) ?>!</h5>
                <p class="text-muted mb-0"><?= $user['email'] ?> | Total Belanja: <strong>Rp <?= number_format($totalSpent, 0, ',', '.') ?></strong></p>
            </div>
        </div>
        
        <!-- Spin Discount Banner -->
        <?php if ($activeSpin): ?>
        <div class="spin-banner">
            <div>
                <h5 class="fw-bold">🎡 Diskon Aktif!</h5>
                <p class="mb-0 opacity-75">Berlaku sampai <?= date('d M Y', strtotime($activeSpin['expiry_date'])) ?></p>
            </div>
            <div class="discount-badge"><?= $activeSpin['prize_label'] ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-receipt"></i></div>
                <h3><?= $orderCount ?></h3>
                <p>Total Pesanan</p>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                <h3><?= $pendingCount ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card success">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <h3><?= $completedCount ?></h3>
                <p>Selesai</p>
            </div>
            <div class="stat-card info">
                <div class="stat-icon info"><i class="fas fa-shopping-cart"></i></div>
                <h3><?= $cartCount ?></h3>
                <p>Keranjang</p>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon danger"><i class="fas fa-star"></i></div>
                <h3><?= $reviewCount ?></h3>
                <p>Ulasan</p>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Recent Orders -->
            <div class="col-lg-7">
                <div class="content-card">
                    <h5><i class="fas fa-receipt text-primary"></i> Pesanan Terbaru</h5>
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-muted">Belum ada pesanan. <a href="index.php">Mulai belanja!</a></p>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): 
                            $statusColors = ['pending'=>'warning','paid'=>'info','processing'=>'primary','completed'=>'success','cancelled'=>'danger'];
                        ?>
                        <div class="order-item">
                            <div>
                                <strong>#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></strong>
                                <small class="text-muted ms-2"><?= date('d M', strtotime($o['created_at'])) ?></small>
                            </div>
                            <div><?= escape($o['product_name']) ?></div>
                            <div>
                                <span class="badge bg-<?= $statusColors[$o['status']] ?>"><?= $o['status'] ?></span>
                                <strong class="ms-2">Rp <?= number_format($o['total_price'], 0, ',', '.') ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-5">
                <div class="content-card">
                    <h5><i class="fas fa-bolt text-warning"></i> Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-outline-primary rounded-pill"><i class="fas fa-store"></i> Belanja Sekarang</a>
                        <a href="cart.php" class="btn btn-outline-success rounded-pill"><i class="fas fa-shopping-cart"></i> Lihat Keranjang</a>
                        <a href="spin-wheel.php" class="btn btn-warning rounded-pill"><i class="fas fa-dharmachakra"></i> Spin Diskon</a>
                        <a href="profile.php" class="btn btn-outline-info rounded-pill"><i class="fas fa-user"></i> Edit Profil</a>
                    </div>
                </div>
                
                <div class="content-card mt-3">
                    <h5><i class="fas fa-info-circle text-info"></i> Info Pembayaran</h5>
                    <p class="mb-1"><strong>DANA/OVO/GoPay/QRIS</strong></p>
                    <h4 class="text-primary">085710785244</h4>
                    <small class="text-muted">a.n. WebPro UMKM</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('🚀 Dashboard Ready - WebPro UMKM');
        console.log('👤 User: <?= escape($user['full_name']) ?>');
        console.log('💰 Total Spent: Rp <?= number_format($totalSpent, 0, ',', '.') ?>');
    </script>
</body>
</html>