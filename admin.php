<?php
require_once 'config/database.php';
checkAdmin();

// ==================== HANDLE ALL ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ========== ADD/EDIT PRODUCT ==========
    if ($action === 'add_product' || $action === 'edit_product') {
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $price = (float)$_POST['price'];
        $discount = (int)$_POST['discount'];
        $stock = (int)$_POST['stock'];
        $description = trim($_POST['description']);
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        
        $uploadedImages = [];
        
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $totalFiles = count($_FILES['images']['name']);
            
            if ($totalFiles < 3 && $action === 'add_product') {
                redirect('admin.php?tab=products', '⚠️ Minimal upload 3 gambar!', 'error');
            }
            if ($totalFiles > 5) {
                redirect('admin.php?tab=products', '⚠️ Maksimal 5 gambar!', 'error');
            }
            
            for ($i = 0; $i < $totalFiles; $i++) {
                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]
                ];
                
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $result = secureUpload($file);
                    if (isset($result['success'])) {
                        $uploadedImages[] = $result['success'];
                    }
                }
            }
        }
        
        if ($action === 'edit_product' && empty($uploadedImages)) {
            $stmt = $pdo->prepare("SELECT images FROM products WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $old = $stmt->fetch();
            $uploadedImages = !empty($old['images']) ? explode(',', $old['images']) : [];
        }
        
        if ($action === 'edit_product' && !empty($uploadedImages)) {
            $stmt = $pdo->prepare("SELECT images FROM products WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $old = $stmt->fetch();
            if ($old && !empty($old['images'])) {
                $oldImages = explode(',', $old['images']);
                foreach ($oldImages as $oldImg) {
                    if (file_exists(trim($oldImg))) {
                        unlink(trim($oldImg));
                    }
                }
            }
        }
        
        $imagesString = implode(',', $uploadedImages);
        
        if ($action === 'add_product') {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, price, discount, stock, description, images, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $price, $discount, $stock, $description, $imagesString, $featured]);
            redirect('admin.php?tab=products', '✅ Produk berhasil ditambahkan!');
        } else {
            $id = (int)$_POST['product_id'];
            $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, price=?, discount=?, stock=?, description=?, images=?, is_featured=? WHERE id=?");
            $stmt->execute([$name, $category, $price, $discount, $stock, $description, $imagesString, $featured, $id]);
            redirect('admin.php?tab=products', '✅ Produk berhasil diperbarui!');
        }
    }
    
    // ========== DELETE PRODUCT ==========
    if ($action === 'delete_product') {
        $id = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("SELECT images FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product && !empty($product['images'])) {
            $images = explode(',', $product['images']);
            foreach ($images as $img) {
                if (file_exists(trim($img))) unlink(trim($img));
            }
        }
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=products', '🗑️ Produk dihapus!');
    }
    
    // ========== UPDATE ORDER STATUS ==========
    if ($action === 'update_order') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], (int)$_POST['order_id']]);
        redirect('admin.php?tab=orders', '✅ Status pesanan diperbarui!');
    }
    
    // ========== DELETE ORDER ==========
    if ($action === 'delete_order') {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([(int)$_POST['order_id']]);
        redirect('admin.php?tab=orders', '🗑️ Pesanan dihapus!');
    }
    
    // ========== TOGGLE USER ROLE ==========
    if ($action === 'toggle_role') {
        $stmt = $pdo->prepare("UPDATE users SET role = IF(role='admin', 'user', 'admin') WHERE id = ?");
        $stmt->execute([(int)$_POST['user_id']]);
        redirect('admin.php?tab=users', '✅ Role user diubah!');
    }
    
    // ========== TOGGLE USER STATUS ==========
    if ($action === 'toggle_status') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        $stmt->execute([(int)$_POST['user_id']]);
        redirect('admin.php?tab=users', '✅ Status user diubah!');
    }
    
    // ========== SAVE ADS ==========
    if ($action === 'save_ads') {
        $pdo->query("UPDATE ads SET is_active = 0");
        $stmt = $pdo->prepare("INSERT INTO ads (text, link, is_active) VALUES (?, ?, ?)");
        $stmt->execute([trim($_POST['ad_text']), trim($_POST['ad_link']), isset($_POST['ad_active']) ? 1 : 0]);
        redirect('admin.php?tab=ads', '✅ Iklan berhasil disimpan!');
    }
    
    // ========== REPLY CHAT ==========
    if ($action === 'reply_chat') {
        $stmt = $pdo->prepare("INSERT INTO chats (user_name, message, is_admin) VALUES ('Admin', ?, 1)");
        $stmt->execute([trim($_POST['message'])]);
        // Mark all as read
        $pdo->query("UPDATE chats SET is_read = 1 WHERE is_admin = 0");
        redirect('admin.php?tab=chats', '✅ Balasan terkirim!');
    }
}

// ==================== GET DATA ====================
$tab = $_GET['tab'] ?? 'dashboard';

$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
$orders = $pdo->query("SELECT o.*, u.full_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$ad = $pdo->query("SELECT * FROM ads WHERE is_active = 1 LIMIT 1")->fetch();
$chats = $pdo->query("SELECT * FROM chats ORDER BY created_at DESC LIMIT 100")->fetchAll();

// Stats
$totalProducts = count($products);
$totalOrders = count($orders);
$totalUsers = count($users);
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$revenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status IN ('paid', 'completed')")->fetchColumn();
$unreadChats = $pdo->query("SELECT COUNT(*) FROM chats WHERE is_admin = 0 AND is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <title>Admin Panel - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-bg: #0a0a0a;
            --sidebar-hover: #1a1a2e;
            --accent: #e94560;
            --gold: #FFD700;
            --success: #1cc88a;
            --warning: #f6c23e;
            --info: #36b9cc;
            --danger: #e74a3b;
            --text-light: #858796;
            --card-bg: #ffffff;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        
        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width: 280px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 1000;
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-header {
            padding: 30px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(233,69,96,0.15), transparent);
        }
        
        .admin-avatar {
            width: 70px; height: 70px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            color: white;
            border: 3px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-header h5 { color: white; font-weight: 700; margin: 0; font-size: 1.1rem; }
        .sidebar-header small { color: var(--accent); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; font-size: 0.7rem; }
        
        .sidebar-nav { padding: 20px 15px; }
        
        .sidebar-nav .nav-label {
            color: rgba(255,255,255,0.3);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 20px 20px 10px;
            font-weight: 700;
        }
        
        .sidebar-nav a {
            color: rgba(255,255,255,0.6);
            padding: 13px 20px;
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
        
        .sidebar-nav a:hover { color: white; background: rgba(255,255,255,0.06); }
        .sidebar-nav a.active { color: white; background: var(--accent); box-shadow: 0 5px 20px rgba(233,69,96,0.3); }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 1rem; }
        .sidebar-nav a .badge { margin-left: auto; font-size: 0.7rem; }
        
        /* ==================== MAIN CONTENT ==================== */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 { font-weight: 800; color: #333; margin: 0; font-size: 1.7rem; }
        .page-header h2 i { color: var(--accent); margin-right: 10px; }
        
        /* ==================== STATS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            transition: all 0.3s;
            border-left: 4px solid var(--accent);
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        .stat-card.purple { border-left-color: #9b59b6; }
        
        .stat-icon {
            width: 55px; height: 55px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }
        
        .stat-icon.primary { background: rgba(78,115,223,0.1); color: #4e73df; }
        .stat-icon.success { background: rgba(28,200,138,0.1); color: var(--success); }
        .stat-icon.warning { background: rgba(246,194,62,0.1); color: var(--warning); }
        .stat-icon.danger { background: rgba(233,69,96,0.1); color: var(--accent); }
        .stat-icon.info { background: rgba(54,185,204,0.1); color: var(--info); }
        .stat-icon.purple { background: rgba(155,89,182,0.1); color: #9b59b6; }
        
        .stat-info h3 { font-weight: 800; margin: 0; font-size: 1.7rem; color: #333; }
        .stat-info p { margin: 3px 0 0; color: var(--text-light); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        
        /* ==================== CARDS ==================== */
        .table-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            overflow-x: auto;
            margin-bottom: 25px;
        }
        
        .table-card h5 { font-weight: 700; margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px; }
        
        .table th {
            font-weight: 700;
            background: #f8f9fa;
            padding: 14px 15px;
            border-bottom: 2px solid #eee;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .table td { padding: 14px 15px; vertical-align: middle; font-size: 0.93rem; }
        .table tr:hover { background: #f8f9ff; }
        
        /* ==================== FORM ==================== */
        .form-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 18px;
            border: 2px solid #e8e8e8;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.1);
        }
        
        /* ==================== IMAGE UPLOAD ==================== */
        .image-upload-zone {
            border: 3px dashed #ddd;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .image-upload-zone:hover { border-color: #4e73df; background: #f0f4ff; }
        .image-upload-zone i { font-size: 3rem; color: #4e73df; margin-bottom: 10px; }
        
        .image-preview-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .image-preview {
            width: 120px; height: 90px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .product-thumb {
            width: 60px; height: 45px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #eee;
        }
        
        /* ==================== CHAT ==================== */
        .chat-messages {
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 15px;
        }
        
        .chat-bubble {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 18px;
            margin-bottom: 8px;
            max-width: 75%;
            font-size: 0.93rem;
        }
        
        .chat-bubble.user { background: #e8e8e8; }
        .chat-bubble.admin { background: var(--accent); color: white; }
        
        /* ==================== SECTION ==================== */
        .admin-section { display: none; }
        .admin-section.active { display: block; animation: fadeIn 0.4s ease; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ==================== BADGES ==================== */
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-action:hover { transform: scale(1.05); }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        }
        
        @media (max-width: 576px) {
            .main-content { padding: 15px; }
            .page-header h2 { font-size: 1.3rem; }
            .stat-card { padding: 18px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.2rem; }
            .stat-info h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

    <!-- ==================== SIDEBAR ==================== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="admin-avatar"><i class="fas fa-crown"></i></div>
            <h5><?= escape($_SESSION['user_name']) ?></h5>
            <small>Super Admin</small>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-label">Menu Utama</div>
            
            <a href="#" class="active" data-section="dashboard">
                <i class="fas fa-th-large"></i> Dashboard
                <?php if ($pendingOrders > 0): ?>
                <span class="badge bg-warning rounded-pill"><?= $pendingOrders ?></span>
                <?php endif; ?>
            </a>
            
            <a href="#" data-section="products">
                <i class="fas fa-box"></i> Produk
                <span class="badge bg-info rounded-pill"><?= $totalProducts ?></span>
            </a>
            
            <a href="#" data-section="orders">
                <i class="fas fa-receipt"></i> Pesanan
                <span class="badge bg-success rounded-pill"><?= $totalOrders ?></span>
            </a>
            
            <a href="#" data-section="users">
                <i class="fas fa-users"></i> Users
                <span class="badge bg-secondary rounded-pill"><?= $totalUsers ?></span>
            </a>
            
            <a href="#" data-section="chats">
                <i class="fas fa-comments"></i> Chat
                <?php if ($unreadChats > 0): ?>
                <span class="badge bg-danger rounded-pill"><?= $unreadChats ?></span>
                <?php endif; ?>
            </a>
            
            <a href="#" data-section="ads">
                <i class="fas fa-ad"></i> Iklan
            </a>
            
            <div class="nav-label">Fitur Keren</div>
            
            <a href="spin-wheel.php" target="_blank">
                <i class="fas fa-dharmachakra"></i> 🎡 Spin Wheel
            </a>
            
            <a href="leaderboard.php" target="_blank">
                <i class="fas fa-trophy"></i> 🏆 Leaderboard
            </a>
            
            <a href="coupon.php" target="_blank">
                <i class="fas fa-ticket-alt"></i> 🎫 Generate Kupon
            </a>
            
            <div class="nav-label">Lainnya</div>
            
            <a href="index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> Lihat Website
            </a>
            
            <a href="logout.php" style="color: #e74a3b !important;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="main-content">
        
        <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= $flash['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ==================== DASHBOARD SECTION ==================== -->
        <div class="admin-section active" id="dashboardSection">
            <div class="page-header">
                <h2><i class="fas fa-th-large"></i> Dashboard Overview</h2>
                <span class="text-muted"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y H:i') ?></span>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card" style="border-left-color: #4e73df;">
                    <div class="stat-icon primary"><i class="fas fa-box"></i></div>
                    <div class="stat-info"><p>Total Produk</p><h3><?= $totalProducts ?></h3></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon success"><i class="fas fa-receipt"></i></div>
                    <div class="stat-info"><p>Total Pesanan</p><h3><?= $totalOrders ?></h3></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon warning"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-info"><p>Pendapatan</p><h4 style="color:var(--success);font-size:1.3rem;">Rp <?= number_format($revenue, 0, ',', '.') ?></h4></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon danger"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><p>Users</p><h3><?= $totalUsers ?></h3></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon info"><i class="fas fa-clock"></i></div>
                    <div class="stat-info"><p>Pending</p><h3><?= $pendingOrders ?></h3></div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="fas fa-cog"></i></div>
                    <div class="stat-info"><p>Processing</p><h3><?= $processingOrders ?></h3></div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="table-card">
                        <h5><i class="fas fa-bolt text-warning"></i> Quick Actions</h5>
                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-outline-primary rounded-pill" onclick="switchSection('products'); showProductForm();">
                                <i class="fas fa-plus"></i> Tambah Produk Baru
                            </button>
                            <button class="btn btn-outline-success rounded-pill" onclick="switchSection('orders');">
                                <i class="fas fa-eye"></i> Lihat Pesanan Pending
                            </button>
                            <a href="coupon.php" class="btn btn-outline-warning rounded-pill">
                                <i class="fas fa-ticket-alt"></i> Generate Kupon Diskon
                            </a>
                            <a href="spin-wheel.php" class="btn btn-outline-info rounded-pill">
                                <i class="fas fa-dharmachakra"></i> Spin Wheel Page
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-card">
                        <h5><i class="fas fa-shield-alt text-success"></i> Status Keamanan</h5>
                        <ul class="list-unstyled mt-3">
                            <li class="mb-2">✅ <strong>CSRF Protection</strong> - Active</li>
                            <li class="mb-2">✅ <strong>XSS Prevention</strong> - Active</li>
                            <li class="mb-2">✅ <strong>SQL Injection</strong> - Protected (PDO)</li>
                            <li class="mb-2">✅ <strong>File Upload</strong> - Type & Size Validated</li>
                            <li class="mb-2">✅ <strong>Password</strong> - Bcrypt Hashed</li>
                            <li class="mb-2">✅ <strong>Session</strong> - HttpOnly & Secure</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PRODUCTS SECTION ==================== -->
        <div class="admin-section" id="productsSection">
            <div class="page-header">
                <h2><i class="fas fa-box"></i> Kelola Produk</h2>
                <button class="btn btn-primary rounded-pill px-4" onclick="showProductForm()">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
            </div>
            
            <!-- Product Form -->
            <div class="form-card" id="productFormCard" style="display:none;">
                <h5 id="productFormTitle"><i class="fas fa-plus-circle"></i> Tambah Produk Baru</h5>
                <hr>
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <input type="hidden" name="action" id="formAction" value="add_product">
                    <input type="hidden" name="product_id" id="editProductId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">📌 Nama Produk *</label>
                            <input type="text" name="name" id="prodName" class="form-control" required placeholder="Masukkan nama produk">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">📂 Kategori *</label>
                            <select name="category" id="prodCategory" class="form-select">
                                <option value="website">🌐 Website</option>
                                <option value="landing">📄 Landing Page</option>
                                <option value="ecommerce">🛒 E-Commerce</option>
                                <option value="company">🏢 Company Profile</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">💰 Harga (Rp) *</label>
                            <input type="number" name="price" id="prodPrice" class="form-control" required placeholder="499000">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">🏷️ Diskon (%)</label>
                            <input type="number" name="discount" id="prodDiscount" class="form-control" value="0" min="0" max="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">📦 Stok</label>
                            <input type="number" name="stock" id="prodStock" class="form-control" value="999">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">📝 Deskripsi</label>
                            <textarea name="description" id="prodDescription" class="form-control" rows="4" placeholder="Deskripsi produk lengkap..."></textarea>
                        </div>
                        
                        <!-- Image Upload -->
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">🖼️ Upload Gambar * <small class="text-danger">(Min 3, Max 5)</small></label>
                            <div class="image-upload-zone" id="uploadZone" onclick="document.getElementById('imageInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h5>Klik untuk Upload Gambar</h5>
                                <p class="text-muted">JPG, PNG, GIF, WEBP (Max 5MB per file)</p>
                                <small class="text-danger">Minimal 3 gambar, Maksimal 5 gambar</small>
                            </div>
                            <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display:none;" onchange="previewImages()">
                            <div class="image-preview-container mt-3" id="imagePreview"></div>
                            <div id="existingImages"></div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_featured" id="prodFeatured" class="form-check-input">
                                <label class="form-check-label fw-bold">⭐ Produk Unggulan (Featured)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success rounded-pill px-4"><i class="fas fa-save"></i> Simpan Produk</button>
                        <button type="button" class="btn btn-secondary rounded-pill px-4" onclick="hideProductForm()"><i class="fas fa-times"></i> Batal</button>
                    </div>
                </form>
            </div>
            
            <!-- Products Table -->
            <div class="table-card">
                <h5>📋 Daftar Produk (<?= $totalProducts ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Diskon</th>
                                <th>Rating</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada produk</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): 
                                    $firstImage = !empty($p['images']) ? explode(',', $p['images'])[0] : 'https://via.placeholder.com/60x45';
                                ?>
                                <tr>
                                    <td><img src="<?= escape(trim($firstImage)) ?>" class="product-thumb" alt=""></td>
                                    <td><strong><?= escape($p['name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= escape($p['category']) ?></span></td>
                                    <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                                    <td><?= $p['discount'] ? "<span class='badge bg-danger'>-{$p['discount']}%</span>" : '-' ?></td>
                                    <td><?= str_repeat('⭐', floor($p['rating'])) ?> (<?= $p['total_ratings'] ?>)</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-action me-1" onclick="editProduct(<?= $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus produk ini?')">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button class="btn btn-sm btn-danger btn-action"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== ORDERS SECTION ==================== -->
        <div class="admin-section" id="ordersSection">
            <div class="page-header"><h2><i class="fas fa-receipt"></i> Manajemen Pesanan</h2></div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Produk</th>
                                <th>Total</th>
                                <th>Pembayaran</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada pesanan</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): 
                                    $statusColors = ['pending'=>'warning','paid'=>'info','processing'=>'primary','completed'=>'success','cancelled'=>'danger'];
                                ?>
                                <tr>
                                    <td><code>#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></code></td>
                                    <td><?= escape($o['full_name'] ?? '-') ?><br><small class="text-muted"><?= escape($o['email'] ?? '') ?></small></td>
                                    <td><?= escape($o['product_name']) ?></td>
                                    <td><strong>Rp <?= number_format($o['total_price'], 0, ',', '.') ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($o['payment_method'] ?? '-') ?></span></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_order">
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:130px;">
                                                <?php foreach (['pending', 'paid', 'processing', 'completed', 'cancelled'] as $s): ?>
                                                    <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td><small><?= date('d M Y', strtotime($o['created_at'])) ?></small></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus pesanan?')">
                                            <input type="hidden" name="action" value="delete_order">
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <button class="btn btn-sm btn-danger btn-action"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== USERS SECTION ==================== -->
        <div class="admin-section" id="usersSection">
            <div class="page-header"><h2><i class="fas fa-users"></i> Manajemen Users</h2></div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Login</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><strong><?= escape($u['full_name']) ?></strong></td>
                                <td><?= escape($u['email']) ?></td>
                                <td><?= escape($u['phone'] ?? '-') ?></td>
                                <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'info' ?>"><?= $u['role'] ?></span></td>
                                <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><small><?= $u['last_login'] ? date('d M Y', strtotime($u['last_login'])) : '-' ?></small></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_role">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-warning btn-action me-1" title="Toggle Role"><i class="fas fa-exchange-alt"></i></button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-<?= $u['is_active'] ? 'secondary' : 'success' ?> btn-action" title="Toggle Status"><i class="fas fa-power-off"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== CHATS SECTION ==================== -->
        <div class="admin-section" id="chatsSection">
            <div class="page-header"><h2><i class="fas fa-comments"></i> Chat Messages</h2></div>
            <div class="table-card">
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($chats)): ?>
                        <p class="text-center text-muted py-5">Belum ada chat</p>
                    <?php else: ?>
                        <?php foreach (array_reverse($chats) as $chat): ?>
                        <div class="mb-2 <?= $chat['is_admin'] ? 'text-end' : '' ?>">
                            <small class="text-muted"><?= escape($chat['user_name'] ?? 'Guest') ?> - <?= date('d M H:i', strtotime($chat['created_at'])) ?></small>
                            <div class="chat-bubble <?= $chat['is_admin'] ? 'admin' : 'user' ?>">
                                <?= escape($chat['message']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" class="d-flex gap-2 mt-3">
                    <input type="hidden" name="action" value="reply_chat">
                    <input type="text" name="message" class="form-control rounded-pill" placeholder="Ketik balasan..." required>
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-paper-plane"></i> Kirim</button>
                </form>
            </div>
        </div>

        <!-- ==================== ADS SECTION ==================== -->
        <div class="admin-section" id="adsSection">
            <div class="page-header"><h2><i class="fas fa-ad"></i> Kelola Iklan</h2></div>
            <div class="form-card">
                <h5>📢 Atur Iklan Banner</h5>
                <hr>
                <form method="POST">
                    <input type="hidden" name="action" value="save_ads">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Teks Iklan *</label>
                        <input type="text" name="ad_text" class="form-control" value="<?= escape($ad['text'] ?? '') ?>" required maxlength="200" placeholder="Contoh: 🎉 Diskon 50%!">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link Iklan</label>
                        <input type="url" name="ad_link" class="form-control" value="<?= escape($ad['link'] ?? '') ?>" placeholder="https://...">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="ad_active" class="form-check-input" <?= ($ad['is_active'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold">Aktifkan Iklan</label>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save"></i> Simpan Iklan</button>
                </form>
            </div>
        </div>
        
    </div>

    <!-- ==================== SCRIPTS ==================== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ==================== NAVIGATION ====================
        function switchSection(section) {
            document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
            const activeLink = document.querySelector(`[data-section="${section}"]`);
            if (activeLink) activeLink.classList.add('active');
            
            document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
            const targetSection = document.getElementById(section + 'Section');
            if (targetSection) targetSection.classList.add('active');
        }
        
        document.querySelectorAll('.sidebar-nav a[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                switchSection(this.dataset.section);
            });
        });
        
        // ==================== PRODUCT FORM ====================
        function showProductForm() {
            document.getElementById('productFormCard').style.display = 'block';
            document.getElementById('productFormTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Produk Baru';
            document.getElementById('productForm').reset();
            document.getElementById('formAction').value = 'add_product';
            document.getElementById('editProductId').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('existingImages').innerHTML = '';
            switchSection('products');
            document.getElementById('productFormCard').scrollIntoView({ behavior: 'smooth' });
        }
        
        function hideProductForm() {
            document.getElementById('productFormCard').style.display = 'none';
        }
        
        async function editProduct(productId) {
            try {
                const response = await fetch('ajax/get_product.php?id=' + productId);
                const product = await response.json();
                
                document.getElementById('productFormCard').style.display = 'block';
                document.getElementById('productFormTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Produk';
                document.getElementById('formAction').value = 'edit_product';
                document.getElementById('editProductId').value = product.id;
                document.getElementById('prodName').value = product.name;
                document.getElementById('prodCategory').value = product.category;
                document.getElementById('prodPrice').value = product.price;
                document.getElementById('prodDiscount').value = product.discount;
                document.getElementById('prodStock').value = product.stock;
                document.getElementById('prodDescription').value = product.description || '';
                document.getElementById('prodFeatured').checked = product.is_featured == 1;
                
                if (product.images) {
                    const images = product.images.split(',');
                    document.getElementById('existingImages').innerHTML = `
                        <label class="form-label mt-2 fw-bold">Gambar Saat Ini:</label>
                        <div class="image-preview-container">
                            ${images.map(img => `<img src="${img.trim()}" class="image-preview">`).join('')}
                        </div>
                        <small class="text-muted">Upload gambar baru untuk mengganti semua gambar</small>
                    `;
                }
                
                switchSection('products');
                document.getElementById('productFormCard').scrollIntoView({ behavior: 'smooth' });
            } catch (error) {
                Swal.fire('Error', 'Gagal memuat data produk', 'error');
            }
        }
        
        // ==================== IMAGE PREVIEW ====================
        function previewImages() {
            const files = document.getElementById('imageInput').files;
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (files.length < 3) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Minimal upload 3 gambar!',
                    toast: true,
                    position: 'top-end'
                });
            }
            
            if (files.length > 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Maksimal 5 gambar!'
                });
                document.getElementById('imageInput').value = '';
                return;
            }
            
            for (let i = 0; i < files.length; i++) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML += `<img src="${e.target.result}" class="image-preview">`;
                };
                reader.readAsDataURL(files[i]);
            }
            
            document.getElementById('existingImages').innerHTML = `
                <small class="text-success">✅ ${files.length} gambar dipilih</small>
            `;
        }
        
        // ==================== KEYBOARD SHORTCUTS ====================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') { e.preventDefault(); switchSection('dashboard'); }
            if (e.ctrlKey && e.key === 'p') { e.preventDefault(); switchSection('products'); }
            if (e.ctrlKey && e.key === 'o') { e.preventDefault(); switchSection('orders'); }
            if (e.ctrlKey && e.key === 'u') { e.preventDefault(); switchSection('users'); }
            if (e.ctrlKey && e.key === 'c') { e.preventDefault(); switchSection('chats'); }
        });
        
        console.log('🚀 Admin Panel Ready - WebPro UMKM');
        console.log('👤 Admin: <?= escape($_SESSION['user_name']) ?>');
        console.log('📦 Products: <?= $totalProducts ?> | 📋 Orders: <?= $totalOrders ?> | 👥 Users: <?= $totalUsers ?>');
        console.log('⌨️ Shortcuts: Ctrl+D Dashboard | Ctrl+P Products | Ctrl+O Orders | Ctrl+U Users | Ctrl+C Chats');
    </script>
</body>
</html>