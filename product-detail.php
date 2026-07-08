<?php
require_once 'config/database.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$reviewError = '';
$reviewSuccess = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $isLoggedIn) {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $reviewError = 'Rating harus antara 1-5!';
    } elseif (empty($comment)) {
        $reviewError = 'Ulasan tidak boleh kosong!';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        if ($stmt->fetch()) {
            $reviewError = 'Anda sudah memberikan ulasan untuk produk ini!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $id, $rating, $comment]);
            
            // Update product rating
            $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
            $stmt->execute([$id]);
            $stats = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE products SET rating = ?, total_ratings = ? WHERE id = ?");
            $stmt->execute([round($stats['avg_rating'], 2), $stats['total'], $id]);
            
            $reviewSuccess = '✅ Ulasan berhasil dikirim! Terima kasih.';
            
            // Refresh product data
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
        }
    }
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && $isLoggedIn) {
    $quantity = (int)($_POST['quantity'] ?? 1);
    $userId = $_SESSION['user_id'];
    
    if ($quantity > $product['stock']) {
        redirect("product-detail.php?id=$id", '❌ Stok tidak mencukupi!', 'error');
    }
    
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        if ($newQty > $product['stock']) {
            redirect("product-detail.php?id=$id", '❌ Stok tidak mencukupi!', 'error');
        }
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQty, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $id, $quantity]);
    }
    
    // Update sold count
    $stmt = $pdo->prepare("UPDATE products SET sold = sold + ? WHERE id = ?");
    $stmt->execute([$quantity, $id]);
    
    if (isset($_POST['buy_now'])) {
        redirect('checkout.php', '✅ Produk ditambahkan! Silakan checkout.');
    } else {
        redirect("product-detail.php?id=$id", '✅ Produk ditambahkan ke keranjang!');
    }
}

// Get reviews
$stmt = $pdo->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// Get related products
$stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id != ? AND is_active = 1 LIMIT 4");
$stmt->execute([$product['category'], $id]);
$relatedProducts = $stmt->fetchAll();

$discountPrice = $product['discount'] ? $product['price'] - ($product['price'] * $product['discount'] / 100) : $product['price'];

// Parse images - FIX: handle various formats
$images = [];
if (!empty($product['images'])) {
    $rawImages = explode(',', $product['images']);
    foreach ($rawImages as $img) {
        $img = trim($img);
        if (!empty($img)) {
            // Jika gambar adalah path lokal (uploads/...), tambahkan prefix
            if (strpos($img, 'http') === false && strpos($img, '//') === false) {
                $images[] = $img; // Path relatif sudah benar
            } else {
                $images[] = $img;
            }
        }
    }
}
// Fallback jika tidak ada gambar
if (empty($images)) {
    $images = ['https://via.placeholder.com/600x400?text=No+Image'];
}

// Get cart count
$cartCount = 0;
if ($isLoggedIn) {
    $cartCount = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4e73df">
    <title><?= escape($product['name']) ?> - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
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
            background: var(--light);
            color: #333;
            line-height: 1.6;
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
        
        .nav-link {
            font-weight: 600;
            color: #555 !important;
            transition: var(--transition);
        }
        
        .nav-link:hover { color: var(--primary) !important; }
        
        /* BREADCRUMB */
        .breadcrumb {
            background: transparent;
            padding: 15px 0;
            margin: 0;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* MAIN IMAGE */
        .main-image-container {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: var(--shadow-md);
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background: #f8f8f8;
            transition: var(--transition);
            cursor: zoom-in;
        }
        
        .main-image:hover {
            transform: scale(1.02);
        }
        
        @media (max-width: 768px) {
            .main-image { height: 280px; }
        }
        
        /* THUMBNAILS */
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .thumbnail {
            width: 70px;
            height: 55px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
            opacity: 0.7;
        }
        
        .thumbnail:hover {
            opacity: 1;
            border-color: var(--primary);
        }
        
        .thumbnail.active {
            opacity: 1;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(78,115,223,0.2);
        }
        
        /* PRODUCT INFO */
        .product-title {
            font-weight: 800;
            font-size: 1.8rem;
            color: #222;
            margin-bottom: 10px;
        }
        
        .rating-stars {
            color: #f6c23e;
            font-size: 1.1rem;
        }
        
        .price-card {
            background: linear-gradient(135deg, #f8f9ff, #e8ecff);
            border-radius: var(--radius-md);
            padding: 20px 25px;
            margin: 20px 0;
            border: 2px solid #dde3ff;
        }
        
        .price-card .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 1rem;
        }
        
        .price-card .final-price {
            font-weight: 900;
            font-size: 2rem;
            color: var(--primary);
        }
        
        .price-card .discount-badge {
            background: var(--danger);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .info-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .info-tag.stock { background: #e8f5e9; color: #2e7d32; }
        .info-tag.category { background: #e3f2fd; color: #1565c0; }
        .info-tag.featured { background: #fff8e1; color: #f57f17; }
        .info-tag.sold { background: #fce4ec; color: #c62828; }
        
        /* BUTTONS */
        .btn-add-cart {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: var(--radius-sm);
            padding: 14px 24px;
            font-weight: 700;
            font-size: 1rem;
            transition: var(--transition);
            flex: 1;
        }
        
        .btn-add-cart:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(78,115,223,0.3);
        }
        
        .btn-buy-now {
            background: var(--gradient-1);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 14px 24px;
            font-weight: 700;
            font-size: 1rem;
            transition: var(--transition);
            flex: 1;
        }
        
        .btn-buy-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78,115,223,0.4);
            color: white;
        }
        
        /* REVIEWS */
        .review-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--warning);
        }
        
        .review-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .star-rating-input {
            direction: rtl;
            display: inline-flex;
        }
        
        .star-rating-input input { display: none; }
        
        .star-rating-input label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .star-rating-input input:checked ~ label { color: #f6c23e; }
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label { color: #f6c23e; }
        
        /* RELATED PRODUCTS */
        .related-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: none;
            height: 100%;
        }
        
        .related-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
        }
        
        .related-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        /* FOOTER */
        footer {
            background: var(--dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }
        
        footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        
        footer a:hover { color: white; }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .product-title { font-size: 1.4rem; }
            .price-card .final-price { font-size: 1.5rem; }
            .btn-add-cart, .btn-buy-now { padding: 12px 18px; font-size: 0.9rem; }
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
                <a href="index.php" class="nav-link"><i class="fas fa-store"></i> Home</a>
                <a href="index.php#products" class="nav-link">Produk</a>
                <?php if ($isLoggedIn): ?>
                    <a href="cart.php" class="btn btn-outline-primary btn-sm rounded-pill position-relative">
                        <i class="fas fa-shopping-cart"></i> Keranjang
                        <?php if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="dashboard.php" class="btn btn-primary btn-sm rounded-pill"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm rounded-pill"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li class="breadcrumb-item"><a href="index.php#products">Produk</a></li>
                <li class="breadcrumb-item active"><?= escape($product['name']) ?></li>
            </ol>
        </nav>

        <!-- Flash Message -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'info-circle' ?>"></i>
                <?= $flash['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- PRODUCT IMAGES -->
            <div class="col-md-6">
                <div class="main-image-container">
                    <img src="<?= escape($images[0]) ?>" 
                         class="main-image" 
                         id="mainImage" 
                         alt="<?= escape($product['name']) ?>"
                         onerror="this.src='https://via.placeholder.com/600x400?text=No+Image'">
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="thumbnail-container">
                    <?php foreach ($images as $i => $img): ?>
                    <img src="<?= escape($img) ?>" 
                         class="thumbnail <?= $i === 0 ? 'active' : '' ?>" 
                         onclick="changeMainImage('<?= escape($img) ?>', this)"
                         onerror="this.src='https://via.placeholder.com/70x55?text=...'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- PRODUCT INFO -->
            <div class="col-md-6">
                <h1 class="product-title"><?= escape($product['name']) ?></h1>
                
                <!-- Rating -->
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <div class="rating-stars">
                        <?= str_repeat('★', floor($product['rating'])) ?><?= str_repeat('☆', 5 - floor($product['rating'])) ?>
                    </div>
                    <span class="fw-bold"><?= number_format($product['rating'], 1) ?></span>
                    <span class="text-muted">(<?= $product['total_ratings'] ?> ulasan)</span>
                    <span class="mx-1">|</span>
                    <span class="text-success"><i class="fas fa-shopping-bag"></i> <?= $product['sold'] ?> Terjual</span>
                </div>
                
                <!-- Price -->
                <div class="price-card">
                    <?php if ($product['discount']): ?>
                        <span class="original-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
                        <span class="discount-badge">-<?= $product['discount'] ?>%</span>
                    <?php endif; ?>
                    <div class="final-price">Rp <?= number_format($discountPrice, 0, ',', '.') ?></div>
                    <?php if ($product['discount']): ?>
                        <small class="text-success">Hemat Rp <?= number_format($product['price'] - $discountPrice, 0, ',', '.') ?></small>
                    <?php endif; ?>
                </div>
                
                <!-- Tags -->
                <div class="mb-3">
                    <span class="info-tag stock"><i class="fas fa-box"></i> Stok: <?= $product['stock'] ?></span>
                    <span class="info-tag category"><i class="fas fa-tag"></i> <?= ucfirst($product['category']) ?></span>
                    <span class="info-tag sold"><i class="fas fa-fire"></i> <?= $product['sold'] ?> Terjual</span>
                    <?php if ($product['is_featured']): ?>
                    <span class="info-tag featured"><i class="fas fa-star"></i> Unggulan</span>
                    <?php endif; ?>
                </div>
                
                <!-- Description -->
                <div class="mb-4">
                    <h5 class="fw-bold">📝 Deskripsi</h5>
                    <p class="text-muted"><?= nl2br(escape($product['description'] ?? 'Tidak ada deskripsi')) ?></p>
                </div>
                
                <!-- Action Buttons -->
                <?php if ($isLoggedIn): ?>
                <form method="POST" class="d-grid gap-2">
                    <input type="hidden" name="add_to_cart" value="1">
                    <div class="input-group mb-2">
                        <span class="input-group-text fw-bold"><i class="fas fa-shopping-basket"></i> Jumlah</span>
                        <input type="number" name="quantity" class="form-control fw-bold text-center" value="1" min="1" max="<?= $product['stock'] ?>" style="max-width:100px;">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-add-cart"><i class="fas fa-cart-plus"></i> Keranjang</button>
                        <button type="submit" name="buy_now" class="btn-buy-now"><i class="fas fa-bolt"></i> Beli Sekarang</button>
                    </div>
                </form>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-lg w-100 rounded-pill">
                        <i class="fas fa-sign-in-alt"></i> Login untuk Membeli
                    </a>
                <?php endif; ?>
                
                <!-- Payment Info -->
                <div class="mt-3 p-3 bg-light rounded-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt"></i> Pembayaran aman via DANA/OVO/GoPay/QRIS ke <strong>085710785244</strong>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- REVIEWS SECTION -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4">
                    <i class="fas fa-star text-warning"></i> Ulasan (<?= count($reviews) ?>)
                </h3>
                
                <?php if ($reviewError): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX"><?= $reviewError ?></div>
                <?php endif; ?>
                <?php if ($reviewSuccess): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn"><?= $reviewSuccess ?></div>
                <?php endif; ?>
                
                <!-- Review Form -->
                <?php if ($isLoggedIn): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold">✍️ Tulis Ulasan</h5>
                        <form method="POST">
                            <input type="hidden" name="submit_review" value="1">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Rating</label>
                                <div class="star-rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                        <label for="star<?= $i ?>">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ulasan Anda</label>
                                <textarea name="comment" class="form-control" rows="3" placeholder="Ceritakan pengalaman Anda dengan produk ini..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-paper-plane"></i> Kirim Ulasan
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <a href="login.php">Login</a> untuk memberikan ulasan.
                    </div>
                <?php endif; ?>
                
                <!-- Reviews List -->
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Belum ada ulasan untuk produk ini. Jadilah yang pertama!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): 
                        $initials = strtoupper(substr($review['full_name'], 0, 1));
                    ?>
                    <div class="review-card animate__animated animate__fadeInUp">
                        <div class="d-flex align-items-start gap-3">
                            <div class="review-avatar"><?= $initials ?></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <strong><?= escape($review['full_name']) ?></strong>
                                        <div class="text-warning">
                                            <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= date('d M Y', strtotime($review['created_at'])) ?></small>
                                </div>
                                <p class="mb-0 mt-2"><?= escape($review['comment']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RELATED PRODUCTS -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4">📦 Produk Terkait</h3>
                <div class="row g-3">
                    <?php foreach ($relatedProducts as $rp): 
                        $rpPrice = $rp['discount'] ? $rp['price'] - ($rp['price'] * $rp['discount'] / 100) : $rp['price'];
                        $rpImages = !empty($rp['images']) ? explode(',', $rp['images']) : ['https://via.placeholder.com/400x220?text=No+Image'];
                    ?>
                    <div class="col-6 col-md-3">
                        <a href="product-detail.php?id=<?= $rp['id'] ?>" class="text-decoration-none">
                            <div class="related-card">
                                <img src="<?= escape(trim($rpImages[0])) ?>" alt="<?= escape($rp['name']) ?>" onerror="this.src='https://via.placeholder.com/400x160?text=No+Image'">
                                <div class="p-3">
                                    <h6 class="fw-bold text-dark"><?= escape($rp['name']) ?></h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-primary fw-bold">Rp <?= number_format($rpPrice, 0, ',', '.') ?></span>
                                        <?php if ($rp['discount']): ?>
                                        <span class="badge bg-danger">-<?= $rp['discount'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold"><i class="fas fa-globe"></i> WebPro UMKM</h5>
                    <p class="opacity-75 small">Jasa pembuatan website profesional untuk UMKM Indonesia. Berkualitas, terjangkau, dan terpercaya.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold">Link Cepat</h5>
                    <a href="index.php" class="d-block mb-1">Home</a>
                    <a href="spin-wheel.php" class="d-block mb-1">🎡 Spin Diskon</a>
                    <a href="leaderboard.php" class="d-block mb-1">🏆 Leaderboard</a>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold">Pembayaran</h5>
                    <p class="opacity-75 small">DANA | OVO | GoPay | QRIS</p>
                    <p class="opacity-75 small">085710785244 a.n. WebPro UMKM</p>
                </div>
            </div>
            <hr style="border-color:rgba(255,255,255,0.1);">
            <p class="text-center mb-0 opacity-50 small">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Change main image on thumbnail click
        function changeMainImage(src, element) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            element.classList.add('active');
        }
        
        // Image zoom on hover (optional)
        const mainImage = document.getElementById('mainImage');
        mainImage.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width * 100;
            const y = (e.clientY - rect.top) / rect.height * 100;
            this.style.transformOrigin = x + '% ' + y + '%';
            this.style.transform = 'scale(1.5)';
        });
        
        mainImage.addEventListener('mouseleave', function() {
            this.style.transformOrigin = 'center center';
            this.style.transform = 'scale(1)';
        });
        
        console.log('🛍️ Product Detail - WebPro UMKM');
    </script>
</body>
</html>