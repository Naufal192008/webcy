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
    
    // Validasi stok
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
    
    // Jika tombol "Beli Sekarang" yang ditekan, langsung ke checkout
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
$images = !empty($product['images']) ? explode(',', $product['images']) : ['https://via.placeholder.com/600x400?text=No+Image'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($product['name']) ?> - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        
        .main-image { 
            width: 100%; height: 450px; object-fit: cover; border-radius: 20px; 
            cursor: zoom-in; transition: 0.3s; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .main-image:hover { transform: scale(1.02); }
        
        .thumbnail { 
            width: 80px; height: 60px; object-fit: cover; border-radius: 10px; 
            cursor: pointer; border: 2px solid transparent; transition: 0.3s; 
        }
        .thumbnail.active, .thumbnail:hover { border-color: #4e73df; opacity: 0.8; }
        
        .review-card { 
            border-left: 4px solid #4e73df; padding: 20px; margin-bottom: 20px; 
            background: #f8f9fa; border-radius: 0 15px 15px 0; 
        }
        
        .star-rating { direction: rtl; display: inline-flex; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 2rem; color: #ddd; cursor: pointer; transition: 0.2s; }
        .star-rating input:checked ~ label { color: #f6c23e; }
        .star-rating label:hover, .star-rating label:hover ~ label { color: #f6c23e; }
        
        .btn-buy {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 15px; padding: 16px;
            font-weight: 700; font-size: 1.1rem; transition: 0.3s;
        }
        .btn-buy:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 30px rgba(102,126,234,0.4); 
            color: white; 
        }
        
        .btn-cart {
            background: white; color: #4e73df; border: 2px solid #4e73df;
            border-radius: 15px; padding: 16px; font-weight: 700; font-size: 1.1rem; transition: 0.3s;
        }
        .btn-cart:hover { 
            background: #4e73df; color: white; transform: translateY(-3px); 
        }
        
        .price-card {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            border-radius: 20px; padding: 25px; border: 2px solid #e0e4ff;
        }
        
        .info-badge {
            display: inline-block; padding: 8px 15px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem;
        }
        
        @media (max-width: 768px) { 
            .main-image { height: 300px; } 
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-store"></i> Belanja</a>
                <?php if ($isLoggedIn): ?>
                    <a href="cart.php" class="btn btn-warning btn-sm position-relative">
                        <i class="fas fa-shopping-cart"></i> Keranjang
                        <?php 
                        $cartCount = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
                        if ($cartCount > 0): 
                        ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cartCount ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light btn-sm"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php#products">Produk</a></li>
                <li class="breadcrumb-item active"><?= escape($product['name']) ?></li>
            </ol>
        </nav>

        <!-- Flash Message -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <img src="<?= escape(trim($images[0])) ?>" class="main-image" id="mainImage" alt="<?= escape($product['name']) ?>">
                <?php if (count($images) > 1): ?>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <?php foreach ($images as $i => $img): ?>
                    <img src="<?= escape(trim($img)) ?>" class="thumbnail <?= $i === 0 ? 'active' : '' ?>" 
                         onclick="document.getElementById('mainImage').src='<?= escape(trim($img)) ?>'; 
                                  document.querySelectorAll('.thumbnail').forEach(t=>t.classList.remove('active')); 
                                  this.classList.add('active');">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div class="col-md-6">
                <h1 class="fw-bold mb-2"><?= escape($product['name']) ?></h1>
                
                <!-- Rating -->
                <div class="mb-3">
                    <span class="text-warning fs-5">
                        <?= str_repeat('★', floor($product['rating'])) ?><?= str_repeat('☆', 5 - floor($product['rating'])) ?>
                    </span>
                    <span class="ms-2 fw-bold"><?= number_format($product['rating'], 1) ?></span>
                    <span class="text-muted">(<?= $product['total_ratings'] ?> ulasan)</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-shopping-bag"></i> <?= $product['sold'] ?> Terjual</span>
                </div>
                
                <!-- Price -->
                <div class="price-card mb-4">
                    <?php if ($product['discount']): ?>
                        <span class="text-decoration-line-through text-muted fs-5">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
                        <span class="badge bg-danger ms-2 fs-6">-<?= $product['discount'] ?>%</span>
                    <?php endif; ?>
                    <h2 class="text-primary fw-bold mb-0">Rp <?= number_format($discountPrice, 0, ',', '.') ?></h2>
                </div>
                
                <!-- Description -->
                <div class="mb-4">
                    <h5 class="fw-bold">📝 Deskripsi</h5>
                    <p class="text-muted"><?= nl2br(escape($product['description'] ?? 'Tidak ada deskripsi')) ?></p>
                </div>
                
                <!-- Stock & Category -->
                <div class="d-flex gap-2 mb-4">
                    <span class="info-badge bg-<?= $product['stock'] > 0 ? 'success' : 'danger' ?>-subtle text-<?= $product['stock'] > 0 ? 'success' : 'danger' ?>">
                        <i class="fas fa-box"></i> Stok: <?= $product['stock'] > 0 ? $product['stock'] : 'Habis' ?>
                    </span>
                    <span class="info-badge bg-info-subtle text-info">
                        <i class="fas fa-tag"></i> <?= ucfirst($product['category']) ?>
                    </span>
                    <?php if ($product['is_featured']): ?>
                    <span class="info-badge bg-warning-subtle text-warning">
                        <i class="fas fa-star"></i> Unggulan
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <?php if ($isLoggedIn): ?>
                <form method="POST" class="d-grid gap-2">
                    <input type="hidden" name="add_to_cart" value="1">
                    <div class="input-group mb-2">
                        <span class="input-group-text fw-bold">Jumlah</span>
                        <input type="number" name="quantity" class="form-control fw-bold" value="1" min="1" max="<?= $product['stock'] ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-cart flex-fill"><i class="fas fa-cart-plus"></i> Keranjang</button>
                        <button type="submit" name="buy_now" class="btn-buy flex-fill"><i class="fas fa-bolt"></i> Beli Sekarang</button>
                    </div>
                </form>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-lg w-100 rounded-pill">
                        <i class="fas fa-sign-in-alt"></i> Login untuk Membeli
                    </a>
                <?php endif; ?>
                
                <!-- Payment Info -->
                <div class="mt-4 p-3 bg-light rounded-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt"></i> Pembayaran aman via DANA/OVO/GoPay/QRIS ke <strong>085710785244</strong>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Reviews Section -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4">
                    <i class="fas fa-star text-warning"></i> Ulasan (<?= count($reviews) ?>)
                </h3>
                
                <?php if ($reviewError): ?>
                    <div class="alert alert-danger"><?= $reviewError ?></div>
                <?php endif; ?>
                <?php if ($reviewSuccess): ?>
                    <div class="alert alert-success"><?= $reviewSuccess ?></div>
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
                                <div class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                        <label for="star<?= $i ?>">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ulasan Anda</label>
                                <textarea name="comment" class="form-control" rows="3" placeholder="Ceritakan pengalaman Anda..." required></textarea>
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
                    <p class="text-muted">Belum ada ulasan untuk produk ini. Jadilah yang pertama!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card animate__animated animate__fadeInUp">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= escape($review['full_name']) ?></strong>
                                <div class="text-warning">
                                    <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                </div>
                            </div>
                            <small class="text-muted"><?= date('d M Y', strtotime($review['created_at'])) ?></small>
                        </div>
                        <p class="mb-0"><?= escape($review['comment']) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4">📦 Produk Terkait</h3>
                <div class="row g-4">
                    <?php foreach ($relatedProducts as $rp): 
                        $rpPrice = $rp['discount'] ? $rp['price'] - ($rp['price'] * $rp['discount'] / 100) : $rp['price'];
                        $rpImages = !empty($rp['images']) ? explode(',', $rp['images']) : ['https://via.placeholder.com/400x220?text=No+Image'];
                    ?>
                    <div class="col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-3">
                            <img src="<?= escape(trim($rpImages[0])) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="<?= escape($rp['name']) ?>">
                            <div class="card-body">
                                <h6 class="fw-bold"><?= escape($rp['name']) ?></h6>
                                <span class="text-primary fw-bold">Rp <?= number_format($rpPrice, 0, ',', '.') ?></span>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <a href="product-detail.php?id=<?= $rp['id'] ?>" class="btn btn-outline-primary btn-sm w-100">Lihat Detail</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
            <small>Pembayaran: DANA/OVO/GoPay/QRIS - 085710785244</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>