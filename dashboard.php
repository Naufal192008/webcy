<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];

// Stats
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $userId")->fetchColumn();
$cartCount = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = $userId")->fetchColumn();
$totalSpent = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = $userId AND status IN ('paid', 'completed')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; display: flex; }
        .sidebar { width: 250px; min-height: 100vh; background: linear-gradient(180deg, #4e73df 0%, #224abe 100%); padding: 20px 0; position: fixed; left: 0; top: 0; }
        .sidebar a { color: rgba(255,255,255,0.8); padding: 14px 25px; display: block; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: white; background: rgba(255,255,255,0.1); }
        .main { margin-left: 250px; padding: 30px; flex: 1; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        @media (max-width: 768px) { .sidebar { width: 100%; min-height: auto; position: relative; } .main { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <h5 class="text-white text-center mb-4">WebPro UMKM</h5>
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="index.php"><i class="fas fa-store"></i> Belanja</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Keranjang</a>
        <a href="history.php"><i class="fas fa-history"></i> Riwayat</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <a href="admin.php" class="text-warning"><i class="fas fa-crown"></i> Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <div class="main">
        <h2 class="mb-4">Dashboard</h2>
        <div class="alert alert-info">Selamat datang, <strong><?= escape($_SESSION['user_name']) ?></strong>!</div>
        
        <div class="row g-4">
            <div class="col-md-3"><div class="stat-card text-center"><h3><?= $orderCount ?></h3><p>Pesanan</p></div></div>
            <div class="col-md-3"><div class="stat-card text-center"><h3><?= $cartCount ?></h3><p>Keranjang</p></div></div>
            <div class="col-md-3"><div class="stat-card text-center"><h4>Rp <?= number_format($totalSpent, 0, ',', '.') ?></h4><p>Total Belanja</p></div></div>
            <div class="col-md-3"><div class="stat-card text-center"><h4><?= strtoupper($_SESSION['user_role']) ?></h4><p>Role</p></div></div>
        </div>
    </div>
</body>
</html>