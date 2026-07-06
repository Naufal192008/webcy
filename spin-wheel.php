<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');

// ==================== CEK DISKON AKTIF ====================
$stmt = $pdo->prepare("SELECT * FROM spin_history WHERE user_id = ? AND is_used = 0 AND discount > 0 AND expiry_date >= ? ORDER BY spin_date DESC LIMIT 1");
$stmt->execute([$userId, $today]);
$activeDiscount = $stmt->fetch();

// ==================== CEK APAKAH BISA SPIN ====================
$canSpin = true;
$spinMessage = '';

if ($activeDiscount) {
    $canSpin = false;
    $spinMessage = 'Kamu masih punya diskon aktif ' . $activeDiscount['prize_label'] . '! Gunakan dulu sebelum spin lagi.';
} else {
    $stmt = $pdo->prepare("SELECT * FROM spin_history WHERE user_id = ? ORDER BY spin_date DESC LIMIT 1");
    $stmt->execute([$userId]);
    $lastSpin = $stmt->fetch();
    
    if ($lastSpin) {
        $lastSpinDate = new DateTime($lastSpin['spin_date']);
        $now = new DateTime();
        $diffDays = $lastSpinDate->diff($now)->days;
        
        if ($lastSpin['discount'] == 0 && $diffDays < 1) {
            $canSpin = false;
            $spinMessage = 'Kamu sudah spin hari ini. Coba lagi besok!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] >= $today) {
            $canSpin = false;
            $spinMessage = 'Kamu masih punya diskon yang belum dipakai!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] < $today) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['expiry_date']]);
            $spentAfterExpiry = $stmt->fetchColumn();
            
            if ($spentAfterExpiry >= 1000000) {
                $canSpin = true;
            } else {
                $waitUntil = new DateTime($lastSpin['expiry_date']);
                $waitUntil->modify('+2 months');
                if ($now < $waitUntil) {
                    $canSpin = false;
                    $spinMessage = 'Diskon hangus. Bisa spin lagi setelah <strong>' . $waitUntil->format('d M Y') . '</strong> atau belanja > Rp 1.000.000';
                }
            }
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 1) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['used_at'] ?? $lastSpin['spin_date']]);
            $spentAfterUse = $stmt->fetchColumn();
            
            if ($spentAfterUse >= 1000000) {
                $canSpin = true;
            } else {
                $canSpin = false;
                $spinMessage = 'Diskon sudah dipakai. Belanja > Rp 1.000.000 untuk spin lagi!';
            }
        }
    }
}

// ==================== HANDLE SPIN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSpin) {
    $prizes = [
        ['discount' => 5, 'label' => 'Diskon 5%', 'color' => '#FF6B6B'],
        ['discount' => 10, 'label' => 'Diskon 10%', 'color' => '#4ECDC4'],
        ['discount' => 15, 'label' => 'Diskon 15%', 'color' => '#45B7D1'],
        ['discount' => 20, 'label' => 'Diskon 20%', 'color' => '#96CEB4'],
        ['discount' => 25, 'label' => 'Diskon 25%', 'color' => '#FFEAA7'],
        ['discount' => 30, 'label' => 'Diskon 30%', 'color' => '#DDA0DD'],
        ['discount' => 50, 'label' => 'Diskon 50%', 'color' => '#FFD700'],
        ['discount' => 0, 'label' => 'Coba Lagi', 'color' => '#98D8C8'],
    ];
    
    $weights = [20, 20, 18, 15, 12, 8, 5, 2];
    $totalWeight = array_sum($weights);
    $random = mt_rand(1, $totalWeight);
    $cumulative = 0;
    $selectedIndex = 0;
    foreach ($weights as $i => $w) { $cumulative += $w; if ($random <= $cumulative) { $selectedIndex = $i; break; } }
    
    $prize = $prizes[$selectedIndex];
    $expiryDate = date('Y-m-d', strtotime('+1 month'));
    
    $stmt = $pdo->prepare("INSERT INTO spin_history (user_id, discount, prize_label, spin_date, expiry_date, is_used) VALUES (?, ?, ?, NOW(), ?, 0)");
    $stmt->execute([$userId, $prize['discount'], $prize['label'], $expiryDate]);
    
    echo json_encode(['success' => true, 'prize' => $prize, 'winIndex' => $selectedIndex, 'expiry' => date('d M Y', strtotime($expiryDate))]);
    exit;
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed')");
$stmt->execute([$userId]);
$totalSpent = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🎡 Spin Wheel Diskon - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #1a1a2e;
            --card-bg: rgba(255,255,255,0.05);
            --gold: #FFD700;
            --text: #ffffff;
            --text-dim: rgba(255,255,255,0.6);
            --wheel-size: min(340px, 80vw);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #0f0c29, #1a1a2e, #16213e);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            padding: 15px;
            overflow-x: hidden;
        }
        
        .stars-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--duration) infinite;
            animation-delay: var(--delay);
            opacity: 0;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }
        
        .container-box {
            position: relative;
            z-index: 1;
            background: var(--card-bg);
            border-radius: 25px;
            padding: clamp(20px, 5vw, 40px);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            width: 100%;
            max-width: 600px;
            text-align: center;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 {
            font-weight: 900;
            font-size: clamp(1.4rem, 4vw, 2rem);
            background: linear-gradient(135deg, var(--gold), #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }
        
        .subtitle {
            color: var(--text-dim);
            margin-bottom: 20px;
            font-size: clamp(0.8rem, 2.5vw, 0.95rem);
        }
        
        /* WHEEL CONTAINER */
        .wheel-outer {
            position: relative;
            display: inline-block;
            margin: 10px auto;
        }
        
        canvas {
            border-radius: 50%;
            box-shadow: 
                0 0 40px rgba(255,215,0,0.2),
                0 0 80px rgba(120,80,220,0.12),
                inset 0 0 30px rgba(0,0,0,0.3);
            width: var(--wheel-size);
            height: var(--wheel-size);
            max-width: 100%;
        }
        
        .pointer-arrow {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: clamp(2rem, 6vw, 2.5rem);
            color: var(--gold);
            z-index: 10;
            filter: drop-shadow(0 0 8px rgba(255,215,0,0.7));
            animation: pointerBounce 1.2s infinite;
            line-height: 1;
        }
        
        @keyframes pointerBounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-8px); }
        }
        
        /* BUTTONS */
        .btn-spin {
            background: linear-gradient(135deg, var(--gold), #FFA500);
            color: #1a1a2e;
            border: none;
            padding: clamp(12px, 3vw, 16px) clamp(30px, 8vw, 55px);
            border-radius: 50px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 8px 25px rgba(255,215,0,0.3);
            width: auto;
            max-width: 100%;
        }
        
        .btn-spin:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(255,215,0,0.5);
        }
        
        .btn-spin:disabled {
            background: #555;
            color: #999;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .btn-shop {
            display: inline-block;
            background: var(--gold);
            color: #1a1a2e;
            border-radius: 50px;
            padding: clamp(10px, 2.5vw, 14px) clamp(20px, 5vw, 35px);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            margin: 8px 4px;
            font-size: clamp(0.85rem, 2.5vw, 1rem);
        }
        
        .btn-shop:hover { background: #FFA500; transform: scale(1.03); color: #1a1a2e; }
        
        .btn-back {
            display: inline-block;
            color: var(--text-dim);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50px;
            padding: clamp(8px, 2vw, 10px) clamp(18px, 4vw, 28px);
            text-decoration: none;
            transition: all 0.3s;
            margin-top: 10px;
            font-weight: 500;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.08);
            color: white;
            border-color: rgba(255,255,255,0.5);
        }
        
        /* CARDS */
        .info-card {
            background: rgba(255,255,255,0.07);
            border-radius: 18px;
            padding: clamp(15px, 3vw, 20px);
            margin: 15px 0;
            text-align: left;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .info-card h5 { margin-bottom: 10px; font-weight: 700; }
        
        .discount-show {
            background: linear-gradient(135deg, var(--gold), #FFA500);
            color: #1a1a2e;
            padding: clamp(14px, 3vw, 18px);
            border-radius: 18px;
            margin: 15px 0;
            font-weight: 900;
            font-size: clamp(1.3rem, 4vw, 1.8rem);
            text-align: center;
            word-break: break-word;
        }
        
        .expiry-tag {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: clamp(0.75rem, 2vw, 0.9rem);
        }
        
        /* RULES */
        .rules-box {
            text-align: left;
            background: rgba(255,255,255,0.03);
            padding: clamp(14px, 3vw, 18px) clamp(16px, 3vw, 22px);
            border-radius: 15px;
            margin-top: 15px;
            font-size: clamp(0.75rem, 2vw, 0.88rem);
            line-height: 1.7;
        }
        
        .rules-box h6 { font-weight: 700; margin-bottom: 8px; color: var(--gold); }
        .rules-box ul { list-style: none; padding: 0; }
        .rules-box ul li { padding: 3px 0; }
        .rules-box ul li::before { content: '•'; color: var(--gold); font-weight: bold; margin-right: 8px; }
        
        /* COLORS */
        .text-gold { color: var(--gold); }
        .text-green { color: #1cc88a; }
        .text-red { color: #e74a3b; }
        .text-blue { color: #36b9cc; }
        
        /* RESULT AREA */
        #resultArea { min-height: 20px; }
        
        @media (max-width: 400px) {
            :root { --wheel-size: 260px; }
            .container-box { padding: 15px 12px; border-radius: 18px; }
            .info-card { padding: 12px; }
            .btn-spin { padding: 14px 35px; font-size: 0.95rem; }
        }
        
        @media (min-width: 768px) {
            :root { --wheel-size: 380px; }
        }
    </style>
</head>
<body>

    <!-- Stars Background -->
    <div class="stars-bg" id="starsBg"></div>

    <div class="container-box">
        
        <h1>🎡 SPIN & WIN DISKON!</h1>
        <p class="subtitle">Putar roda keberuntungan & dapatkan diskon hingga 50%!</p>
        
        <!-- ========== DISKON AKTIF ========== -->
        <?php if ($activeDiscount): ?>
        <div class="info-card text-center">
            <h5 class="text-gold"><i class="fas fa-ticket-alt"></i> Kamu Punya Diskon Aktif!</h5>
            <div class="discount-show"><?= $activeDiscount['prize_label'] ?></div>
            <p>⏰ Berlaku sampai: <strong><?= date('d M Y', strtotime($activeDiscount['expiry_date'])) ?></strong></p>
            <p class="text-blue"><i class="fas fa-info-circle"></i> Diskon otomatis dipotong saat checkout.</p>
            <a href="index.php" class="btn-shop">🛒 Belanja Sekarang</a>
        </div>
        <?php endif; ?>
        
        <!-- ========== TIDAK BISA SPIN ========== -->
        <?php if ($spinMessage && !$activeDiscount): ?>
        <div class="info-card text-center">
            <h5 class="text-gold"><i class="fas fa-info-circle"></i> Info Spin</h5>
            <p><?= $spinMessage ?></p>
            <p class="text-blue">💰 Total belanja: <strong>Rp <?= number_format($totalSpent, 0, ',', '.') ?></strong></p>
            <?php if ($totalSpent < 1000000): ?>
            <p style="font-size:0.85rem;opacity:0.7;">Belanja > Rp 1.000.000 untuk spin!</p>
            <?php endif; ?>
            <a href="index.php" class="btn-shop">🛒 Belanja Dulu</a>
        </div>
        <?php endif; ?>
        
        <!-- ========== WHEEL ========== -->
        <?php if ($canSpin): ?>
        <div class="wheel-outer">
            <div class="pointer-arrow">▼</div>
            <canvas id="wheelCanvas"></canvas>
        </div>
        
        <div id="resultArea"></div>
        
        <button class="btn-spin" id="spinButton" onclick="startSpin()">🎰 SPIN SEKARANG!</button>
        <?php endif; ?>
        
        <!-- ========== RULES ========== -->
        <div class="rules-box">
            <h6>📋 Aturan Spin Wheel:</h6>
            <ul>
                <li>Dapatkan diskon acak <strong>5% - 50%</strong></li>
                <li>Diskon berlaku <strong>1 bulan</strong> sejak didapat</li>
                <li>Diskon hanya <strong>1 kali pakai</strong> saat checkout</li>
                <li>Diskon hangus jika tidak dipakai dalam 1 bulan</li>
                <li><strong>Belanja > Rp 1.000.000</strong> untuk spin lagi</li>
                <li>Jika diskon hangus, tunggu <strong>2 bulan</strong> untuk spin gratis</li>
            </ul>
        </div>
        
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        
    </div>

    <script>
    // ==================== STARS BACKGROUND ====================
    (function() {
        const container = document.getElementById('starsBg');
        const count = 80;
        let html = '';
        for (let i = 0; i < count; i++) {
            const size = Math.random() * 3 + 1;
            const x = Math.random() * 100;
            const y = Math.random() * 100;
            const duration = Math.random() * 3 + 2;
            const delay = Math.random() * 3;
            html += `<div class="star" style="left:${x}%;top:${y}%;width:${size}px;height:${size}px;--duration:${duration}s;--delay:${delay}s;"></div>`;
        }
        container.innerHTML = html;
    })();
    
    <?php if ($canSpin): ?>
    
    // ==================== WHEEL SETUP ====================
    const canvas = document.getElementById('wheelCanvas');
    const ctx = canvas.getContext('2d');
    
    // Sesuaikan ukuran canvas dengan CSS variabel
    function resizeCanvas() {
        const style = getComputedStyle(document.documentElement);
        const size = parseInt(style.getPropertyValue('--wheel-size'));
        const dpr = window.devicePixelRatio || 1;
        canvas.width = size * dpr;
        canvas.height = size * dpr;
        canvas.style.width = size + 'px';
        canvas.style.height = size + 'px';
        ctx.scale(dpr, dpr);
        drawWheel();
    }
    
    const segments = [
        { label: '5%', color: '#FF6B6B', discount: 5 },
        { label: '10%', color: '#4ECDC4', discount: 10 },
        { label: '15%', color: '#45B7D1', discount: 15 },
        { label: '20%', color: '#96CEB4', discount: 20 },
        { label: '25%', color: '#FFEAA7', discount: 25 },
        { label: '30%', color: '#DDA0DD', discount: 30 },
        { label: '50%', color: '#FFD700', discount: 50 },
        { label: '😅', color: '#98D8C8', discount: 0 },
    ];
    
    const numSegments = segments.length;
    const arcSize = (2 * Math.PI) / numSegments;
    let currentRotation = 0;
    let isSpinning = false;
    
    function getSize() {
        const style = getComputedStyle(document.documentElement);
        return parseInt(style.getPropertyValue('--wheel-size'));
    }
    
    function drawWheel() {
        const size = getSize();
        const cx = size / 2;
        const cy = size / 2;
        const radius = size / 2 - 10;
        
        ctx.clearRect(0, 0, size, size);
        
        for (let i = 0; i < numSegments; i++) {
            const startAngle = i * arcSize + currentRotation;
            const endAngle = startAngle + arcSize;
            
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = segments[i].color;
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.5)';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(startAngle + arcSize / 2);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#1a1a2e';
            ctx.font = `bold ${size * 0.045}px "Segoe UI", sans-serif`;
            ctx.fillText(segments[i].label, radius - size * 0.06, size * 0.016);
            ctx.restore();
        }
        
        // Center
        const centerR = size * 0.07;
        ctx.beginPath();
        ctx.arc(cx, cy, centerR, 0, 2 * Math.PI);
        ctx.fillStyle = '#FFD700';
        ctx.fill();
        ctx.strokeStyle = '#1a1a2e';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        ctx.fillStyle = '#1a1a2e';
        ctx.font = `bold ${size * 0.035}px "Segoe UI", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('SPIN', cx, cy);
    }
    
    function startSpin() {
        if (isSpinning) return;
        isSpinning = true;
        
        const btn = document.getElementById('spinButton');
        btn.disabled = true;
        btn.textContent = '🎰 BERPUTAR...';
        document.getElementById('resultArea').innerHTML = '';
        
        const weights = [20, 20, 18, 15, 12, 8, 5, 2];
        const totalWeight = weights.reduce((a, b) => a + b, 0);
        let random = Math.floor(Math.random() * totalWeight) + 1;
        let winIndex = 0, cumulative = 0;
        for (let i = 0; i < weights.length; i++) {
            cumulative += weights[i];
            if (random <= cumulative) { winIndex = i; break; }
        }
        
        const pointerAngle = -Math.PI / 2;
        const segmentMiddle = winIndex * arcSize + arcSize / 2;
        const fullSpins = (5 + Math.floor(Math.random() * 4)) * 2 * Math.PI;
        const targetRotation = fullSpins + (pointerAngle - segmentMiddle);
        const finalRotation = currentRotation + targetRotation;
        
        const duration = 4500;
        const startTime = performance.now();
        const startRotation = currentRotation;
        
        function animate(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 4);
            currentRotation = startRotation + targetRotation * eased;
            drawWheel();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                currentRotation = finalRotation % (2 * Math.PI);
                drawWheel();
                
                fetch('spin-wheel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                .then(r => r.json())
                .then(data => {
                    showResult(data.prize, data.expiry);
                    isSpinning = false;
                    btn.textContent = '🎰 SPIN SEKARANG!';
                })
                .catch(() => {
                    showResult(segments[winIndex], '06 Aug 2026');
                    isSpinning = false;
                    btn.textContent = '🎰 SPIN SEKARANG!';
                });
            }
        }
        
        requestAnimationFrame(animate);
    }
    
    function showResult(prize, expiry) {
        const area = document.getElementById('resultArea');
        if (prize.discount > 0) {
            area.innerHTML = `
                <div class="info-card text-center" style="animation: fadeInUp 0.5s ease;">
                    <h3 style="color:#FFD700;">🎉 SELAMAT!</h3>
                    <div class="discount-show">Kamu Dapat ${prize.label}!</div>
                    <span class="expiry-tag">⏰ Berlaku sampai: <strong>${expiry}</strong></span>
                    <p class="text-blue mt-3">Diskon otomatis dipotong saat checkout!</p>
                    <a href="index.php" class="btn-shop">🛒 Belanja Sekarang</a>
                </div>`;
            Swal.fire({
                title: '🎉 ' + prize.label + '!',
                html: `<p>Diskon berlaku sampai <strong>${expiry}</strong></p><p>Gunakan sebelum hangus!</p>`,
                icon: 'success',
                confirmButtonText: 'OK, SIAP!',
                background: '#1a1a2e',
                color: 'white',
                confirmButtonColor: '#FFD700'
            });
        } else {
            area.innerHTML = `
                <div class="info-card text-center" style="animation: fadeInUp 0.5s ease;">
                    <h3>😅 Belum Beruntung</h3>
                    <p>Kamu dapat <strong>Coba Lagi</strong></p>
                    <p style="font-size:0.85rem;opacity:0.7;">Coba lagi besok!</p>
                    <a href="index.php" class="btn-shop">🛒 Belanja Dulu</a>
                </div>`;
        }
    }
    
    // Init
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    <?php endif; ?>
    </script>
</body>
</html>