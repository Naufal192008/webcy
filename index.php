<?php
require_once 'config/database.php';

$stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY is_featured DESC, created_at DESC");
$products = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM ads WHERE is_active = 1 LIMIT 1");
$ad = $stmt->fetch();

$topBuyers = $pdo->query("SELECT u.full_name, COUNT(o.id) as total_orders, COALESCE(SUM(o.total_price), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'completed') WHERE u.role = 'user' GROUP BY u.id ORDER BY total_spent DESC LIMIT 3")->fetchAll();

$isLoggedIn = isset($_SESSION['user_id']);
$cartCount = 0;
if ($isLoggedIn) {
    $cartCount = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0">
    <meta name="theme-color" content="#4e73df">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WebPro UMKM">
    <meta name="description" content="Jasa pembuatan website profesional untuk UMKM Indonesia. Mulai dari 499rb!">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌐</text></svg>">
    <title>WebPro UMKM - Jasa Pembuatan Website Profesional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
            border: none;
            display: flex;
            flex-direction: column;
        }
        .product-card:active { transform: scale(0.98); }
        .product-img {
            height: clamp(160px, 30vw, 220px);
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        .product-card:hover .product-img img { transform: scale(1.06); }
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            z-index: 2;
        }
        .product-badge.featured {
            background: var(--gradient-2);
            top: 42px;
        }
        .product-body {
            padding: clamp(12px, 3vw, 20px);
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .product-category {
            font-size: clamp(0.65rem, 1.5vw, 0.75rem);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .product-title {
            font-weight: 700;
            font-size: clamp(0.95rem, 2.5vw, 1.1rem);
            margin-bottom: 6px;
        }
        .product-price {
            font-weight: 800;
            font-size: clamp(1.1rem, 2.8vw, 1.3rem);
            color: var(--primary);
            margin-top: auto;
        }
        .product-price .original {
            text-decoration: line-through;
            color: #999;
            font-size: clamp(0.7rem, 1.8vw, 0.85rem);
            margin-left: 6px;
        }
        .hero {
            padding: clamp(80px, 15vw, 150px) 0 clamp(40px, 8vw, 80px);
            background: #f8f9ff;
            position: relative;
            overflow: hidden;
        }
        .hero h1 {
            font-size: clamp(1.8rem, 6vw, 3.5rem);
            font-weight: 900;
            line-height: 1.2;
        }
        .hero h1 span {
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        footer {
            background: var(--dark);
            color: white;
            padding: clamp(30px, 5vw, 50px) 0 clamp(20px, 3vw, 30px);
        }
        .top-buyers-section {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: white;
            padding: clamp(25px, 5vw, 40px) 0;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#products">Produk</a></li>
                    <li class="nav-item"><a class="nav-link" href="#top-buyers">Top Buyers</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item ms-lg-2">
                        <a href="spin-wheel.php" class="btn btn-warning btn-sm rounded-pill mt-2 mt-lg-0">🎡 Spin Diskon</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item ms-lg-2">
                            <a href="cart.php" class="btn btn-outline-primary btn-sm rounded-pill position-relative mt-2 mt-lg-0">
                                🛒 Keranjang
                                <?php if ($cartCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $cartCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a href="dashboard.php" class="btn btn-primary btn-sm rounded-pill mt-2 mt-lg-0">Dashboard</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2"><a href="login.php" class="btn btn-primary btn-sm rounded-pill mt-2 mt-lg-0">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 animate-fadeInUp">
                    <h1>Website <span>Profesional</span> untuk UMKM Indonesia</h1>
                    <p class="text-muted">Tingkatkan penjualan dan kredibilitas bisnis Anda dengan website modern, responsif, dan SEO-friendly. Mulai dari 499rb!</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="#products" class="btn btn-primary rounded-pill">🛒 Lihat Produk</a>
                        <a href="spin-wheel.php" class="btn btn-warning rounded-pill">🎡 Spin Diskon</a>
                        <a href="leaderboard.php" class="btn btn-outline-primary rounded-pill">🏆 Leaderboard</a>
                    </div>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <span class="badge bg-success">✅ 500+ Client</span>
                        <span class="badge bg-warning text-dark">⭐ 4.9 Rating</span>
                        <span class="badge bg-info">🔒 Garansi 1 Tahun</span>
                    </div>
                </div>
                <div class="col-lg-6 text-center mt-4 mt-lg-0">
                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600" class="img-fluid rounded-4 shadow-lg" alt="Web Development" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- Ad -->
    <?php if ($ad): ?>
    <div class="text-center py-2 bg-warning fw-bold" style="font-size:clamp(0.8rem,2vw,1rem);">
        <?= $ad['link'] ? "<a href='{$ad['link']}' class='text-dark'>📢 {$ad['text']}</a>" : "📢 {$ad['text']}" ?>
    </div>
    <?php endif; ?>

    <!-- Products -->
    <section id="products" class="py-5">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Produk & Layanan Kami</h2>
            <div class="row g-3 g-md-4">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5"><p class="text-muted">Belum ada produk.</p></div>
                <?php else: ?>
                    <?php foreach ($products as $p): 
                        $dp = $p['discount'] ? $p['price'] - ($p['price'] * $p['discount'] / 100) : $p['price'];
                        $imgs = !empty($p['images']) ? explode(',', $p['images']) : ['https://via.placeholder.com/400x200?text=No+Image'];
                    ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="product-card">
                            <div class="product-img">
                                <img src="<?= escape(trim($imgs[0])) ?>" alt="<?= escape($p['name']) ?>" loading="lazy">
                                <?php if ($p['discount']): ?><span class="product-badge">-<?= $p['discount'] ?>%</span><?php endif; ?>
                                <?php if ($p['is_featured']): ?><span class="product-badge featured">⭐</span><?php endif; ?>
                            </div>
                            <div class="product-body">
                                <div class="product-category"><?= $p['category'] ?></div>
                                <h6 class="product-title"><?= escape($p['name']) ?></h6>
                                <div class="text-warning small"><?= str_repeat('★', floor($p['rating'])) ?><?= str_repeat('☆', 5-floor($p['rating'])) ?></div>
                                <div class="product-price">
                                    Rp <?= number_format($dp, 0, ',', '.') ?>
                                    <?php if ($p['discount']): ?><span class="original">Rp <?= number_format($p['price'], 0, ',', '.') ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="p-2 pt-0">
                                <a href="product-detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill">Detail</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Top Buyers -->
    <?php if (!empty($topBuyers)): ?>
    <section id="top-buyers" class="top-buyers-section">
        <div class="container text-center">
            <h3 class="fw-bold mb-3">🏆 Top Buyers</h3>
            <div class="row g-3 justify-content-center">
                <?php $medals = ['🥇', '🥈', '🥉']; foreach ($topBuyers as $i => $b): ?>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);">
                        <div style="font-size:clamp(1.5rem,5vw,2.5rem);"><?= $medals[$i] ?></div>
                        <h6><?= escape($b['full_name']) ?></h6>
                        <small>Rp <?= number_format($b['total_spent'], 0, ',', '.') ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="leaderboard.php" class="btn btn-outline-light btn-sm rounded-pill mt-3">Lihat Semua</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">FAQ</h2>
            <div class="accordion" id="faqAccordion" style="max-width:700px;margin:0 auto;">
                <div class="accordion-item mb-2 border-0 shadow-sm rounded-3">
                    <button class="accordion-button fw-bold" data-bs-toggle="collapse" data-bs-target="#faq1">Berapa lama proses?</button>
                    <div id="faq1" class="accordion-collapse collapse show"><div class="accordion-body">3-7 hari kerja.</div></div>
                </div>
                <div class="accordion-item mb-2 border-0 shadow-sm rounded-3">
                    <button class="accordion-button collapsed fw-bold" data-bs-toggle="collapse" data-bs-target="#faq2">Pembayaran?</button>
                    <div id="faq2" class="accordion-collapse collapse"><div class="accordion-body">DANA, OVO, GoPay, QRIS ke 085710785244.</div></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <p class="mb-1">&copy; <?= date('Y') ?> WebPro UMKM</p>
            <small class="opacity-75">Pembayaran: 085710785244 a.n. WebPro UMKM</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>