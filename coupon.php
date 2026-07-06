<?php
require_once 'config/database.php';
checkAdmin();

// Generate coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $discount = (int)$_POST['discount'];
    $maxUses = (int)$_POST['max_uses'];
    $expiry = $_POST['expiry_date'];
    
    $code = 'WEBPRO' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    
    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount, max_uses, used_count, expiry_date) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$code, $discount, $maxUses, $expiry]);
    
    $generatedCode = $code;
}

// Table coupons (jalankan di SQL)
// CREATE TABLE coupons (
//     id INT PRIMARY KEY AUTO_INCREMENT,
//     code VARCHAR(20) UNIQUE,
//     discount INT,
//     max_uses INT DEFAULT 100,
//     used_count INT DEFAULT 0,
//     expiry_date DATE,
//     is_active TINYINT(1) DEFAULT 1,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Kupon - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; padding: 40px; font-family: 'Segoe UI', sans-serif; }
        .coupon-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .coupon-card::before {
            content: '';
            position: absolute;
            width: 50px; height: 50px;
            background: #f5f7fa;
            border-radius: 50%;
            top: -25px; left: -25px;
        }
        .coupon-card::after {
            content: '';
            position: absolute;
            width: 50px; height: 50px;
            background: #f5f7fa;
            border-radius: 50%;
            bottom: -25px; right: -25px;
        }
        .coupon-code {
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: 5px;
            border: 2px dashed rgba(255,255,255,0.5);
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🎫 Generate Kupon Diskon</h2>
        
        <?php if (isset($generatedCode)): ?>
        <div class="coupon-card mb-4">
            <h4>✅ Kupon Berhasil Dibuat!</h4>
            <div class="coupon-code"><?= $generatedCode ?></div>
            <p class="mt-2">Diskon: <?= $discount ?>% | Exp: <?= $expiry ?></p>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label>Diskon (%)</label>
                            <input type="number" name="discount" class="form-control" required min="1" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Max Penggunaan</label>
                            <input type="number" name="max_uses" class="form-control" value="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" name="generate" class="btn btn-primary w-100">🎫 Generate</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <h4>Daftar Kupon</h4>
        <table class="table">
            <thead><tr><th>Kode</th><th>Diskon</th><th>Digunakan</th><th>Max</th><th>Expiry</th></tr></thead>
            <tbody>
                <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><code><?= $c['code'] ?></code></td>
                    <td><?= $c['discount'] ?>%</td>
                    <td><?= $c['used_count'] ?></td>
                    <td><?= $c['max_uses'] ?></td>
                    <td><?= $c['expiry_date'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>