<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$today = date('Y-m-d');
$showAdmin = isset($_GET['admin']) && $isAdmin;

// ==================== ADMIN: CRUD HADIAH SPINNER ====================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Tambah hadiah baru
    if ($action === 'add_prize') {
        $discount = (int)$_POST['discount'];
        $label = trim($_POST['label']);
        $color = trim($_POST['color']);
        $weight = (int)$_POST['weight'];
        
        $stmt = $pdo->prepare("INSERT INTO spin_prizes (discount, label, color, weight) VALUES (?, ?, ?, ?)");
        $stmt->execute([$discount, $label, $color, $weight]);
        redirect('spin-wheel.php?admin=1', '✅ Hadiah baru ditambahkan ke spinner!');
    }
    
    // Edit hadiah
    if ($action === 'edit_prize') {
        $prizeId = (int)$_POST['prize_id'];
        $discount = (int)$_POST['discount'];
        $label = trim($_POST['label']);
        $color = trim($_POST['color']);
        $weight = (int)$_POST['weight'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE spin_prizes SET discount=?, label=?, color=?, weight=?, is_active=? WHERE id=?");
        $stmt->execute([$discount, $label, $color, $weight, $isActive, $prizeId]);
        redirect('spin-wheel.php?admin=1', '✅ Hadiah berhasil diperbarui!');
    }
    
    // Hapus hadiah
    if ($action === 'delete_prize') {
        $prizeId = (int)$_POST['prize_id'];
        $stmt = $pdo->prepare("DELETE FROM spin_prizes WHERE id = ?");
        $stmt->execute([$prizeId]);
        redirect('spin-wheel.php?admin=1', '🗑️ Hadiah dihapus dari spinner!');
    }
    
    // Update konfigurasi spin
    if ($action === 'update_config') {
        $config = json_encode([
            'expiry_days' => (int)$_POST['expiry_days'],
            'min_spent' => (int)$_POST['min_spent'],
            'cooldown_days' => (int)$_POST['cooldown_days']
        ]);
        file_put_contents('spin_config.json', $config);
        redirect('spin-wheel.php?admin=1', '✅ Konfigurasi diperbarui!');
    }
    
    // Reset spin user
    if ($action === 'reset_user_spin') {
        $targetUserId = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE spin_history SET is_used = 2 WHERE user_id = ? AND is_used = 0")->execute([$targetUserId]);
        redirect('spin-wheel.php?admin=1', '✅ Spin user direset!');
    }
    
    // Hapus history spin
    if ($action === 'delete_spin_history') {
        $spinId = (int)$_POST['spin_id'];
        $pdo->prepare("DELETE FROM spin_history WHERE id = ?")->execute([$spinId]);
        redirect('spin-wheel.php?admin=1', '🗑️ History dihapus!');
    }
    
    // Clear all history
    if ($action === 'clear_all_history') {
        $pdo->query("DELETE FROM spin_history");
        redirect('spin-wheel.php?admin=1', '🗑️ Semua history dihapus!');
    }
}

// ==================== LOAD DATA HADIAH DARI DATABASE ====================
$prizes = $pdo->query("SELECT * FROM spin_prizes WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

// Fallback jika tabel kosong
if (empty($prizes)) {
    $prizes = [
        ['id' => 1, 'discount' => 5, 'label' => 'Diskon 5%', 'color' => '#FF6B6B', 'weight' => 20],
        ['id' => 2, 'discount' => 10, 'label' => 'Diskon 10%', 'color' => '#4ECDC4', 'weight' => 20],
        ['id' => 3, 'discount' => 15, 'label' => 'Diskon 15%', 'color' => '#45B7D1', 'weight' => 18],
        ['id' => 4, 'discount' => 20, 'label' => 'Diskon 20%', 'color' => '#96CEB4', 'weight' => 15],
        ['id' => 5, 'discount' => 25, 'label' => 'Diskon 25%', 'color' => '#FFEAA7', 'weight' => 12],
        ['id' => 6, 'discount' => 30, 'label' => 'Diskon 30%', 'color' => '#DDA0DD', 'weight' => 8],
        ['id' => 7, 'discount' => 50, 'label' => 'Diskon 50%', 'color' => '#FFD700', 'weight' => 5],
        ['id' => 8, 'discount' => 0, 'label' => 'Coba Lagi', 'color' => '#98D8C8', 'weight' => 2],
    ];
}

// ==================== LOAD CONFIG ====================
$spinConfig = ['expiry_days' => 30, 'min_spent' => 1000000, 'cooldown_days' => 1];
if (file_exists('spin_config.json')) {
    $spinConfig = array_merge($spinConfig, json_decode(file_get_contents('spin_config.json'), true) ?: []);
}

// ==================== CEK DISKON AKTIF USER ====================
$stmt = $pdo->prepare("SELECT * FROM spin_history WHERE user_id = ? AND is_used IN (0, 2) AND discount > 0 AND expiry_date >= ? ORDER BY spin_date DESC LIMIT 1");
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
        
        if ($lastSpin['discount'] == 0 && $diffDays < $spinConfig['cooldown_days']) {
            $canSpin = false;
            $spinMessage = 'Kamu sudah spin. Coba lagi besok!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] >= $today) {
            $canSpin = false;
            $spinMessage = 'Kamu masih punya diskon yang belum dipakai!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] < $today) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['expiry_date']]);
            $spentAfterExpiry = $stmt->fetchColumn();
            if ($spentAfterExpiry < $spinConfig['min_spent']) {
                $canSpin = false;
                $spinMessage = 'Diskon hangus. Belanja > Rp ' . number_format($spinConfig['min_spent'], 0, ',', '.') . ' untuk spin lagi!';
            }
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 1) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['used_at'] ?? $lastSpin['spin_date']]);
            $spentAfterUse = $stmt->fetchColumn();
            if ($spentAfterUse < $spinConfig['min_spent']) {
                $canSpin = false;
                $spinMessage = 'Diskon sudah dipakai. Belanja > Rp ' . number_format($spinConfig['min_spent'], 0, ',', '.') . ' untuk spin lagi!';
            }
        }
    }
}

// ==================== HANDLE SPIN (USER) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSpin && !isset($_POST['action'])) {
    $weights = array_column($prizes, 'weight');
    $totalWeight = array_sum($weights);
    $random = mt_rand(1, $totalWeight);
    $cumulative = 0;
    $selectedIndex = 0;
    foreach ($weights as $i => $w) { $cumulative += $w; if ($random <= $cumulative) { $selectedIndex = $i; break; } }
    
    $prize = $prizes[$selectedIndex];
    $expiryDate = date('Y-m-d', strtotime('+' . $spinConfig['expiry_days'] . ' days'));
    
    $stmt = $pdo->prepare("INSERT INTO spin_history (user_id, discount, prize_label, spin_date, expiry_date, is_used) VALUES (?, ?, ?, NOW(), ?, 0)");
    $stmt->execute([$userId, $prize['discount'], $prize['label'], $expiryDate]);
    
    echo json_encode(['success' => true, 'prize' => $prize, 'winIndex' => $selectedIndex, 'expiry' => date('d M Y', strtotime($expiryDate))]);
    exit;
}

// Total belanja user
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed')");
$stmt->execute([$userId]);
$totalSpent = $stmt->fetchColumn();

// ==================== ADMIN DATA ====================
$allSpins = [];
$allUsers = [];
if ($showAdmin) {
    $allSpins = $pdo->query("SELECT s.*, u.full_name FROM spin_history s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.spin_date DESC LIMIT 100")->fetchAll();
    $allUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'user' ORDER BY full_name")->fetchAll();
    $allPrizes = $pdo->query("SELECT * FROM spin_prizes ORDER BY id ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🎡 Spin Wheel - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bg: #1a1a2e; --gold: #FFD700; --wheel-size: min(300px, 75vw); --text: #ffffff; --text-dim: rgba(255,255,255,0.6); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0f0c29, #1a1a2e, #16213e); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; color: var(--text); padding: 15px; }
        .container-box { background: rgba(255,255,255,0.05); border-radius: 25px; padding: clamp(15px, 4vw, 30px); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); max-width: 850px; width: 100%; text-align: center; }
        h1 { font-weight: 900; font-size: clamp(1.3rem, 4vw, 1.8rem); background: linear-gradient(135deg, var(--gold), #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 3px; }
        .subtitle { color: var(--text-dim); margin-bottom: 15px; font-size: 0.85rem; }
        .wheel-outer { position: relative; display: inline-block; margin: 8px auto; }
        canvas { border-radius: 50%; box-shadow: 0 0 40px rgba(255,215,0,0.2); width: var(--wheel-size); height: var(--wheel-size); }
        .pointer-arrow { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); font-size: 2rem; color: var(--gold); z-index: 10; animation: bounce 1.2s infinite; }
        @keyframes bounce { 0%,100%{transform:translateX(-50%) translateY(0);} 50%{transform:translateX(-50%) translateY(-6px);} }
        .btn-spin { background: linear-gradient(135deg, var(--gold), #FFA500); color: #1a1a2e; border: none; padding: 12px 40px; border-radius: 50px; font-size: 1rem; font-weight: 800; cursor: pointer; transition: 0.3s; margin: 12px 0; text-transform: uppercase; letter-spacing: 1px; }
        .btn-spin:hover:not(:disabled) { transform: scale(1.05); }
        .btn-spin:disabled { background: #555; color: #999; cursor: not-allowed; }
        .btn-back { display: inline-block; color: var(--text-dim); border: 1px solid rgba(255,255,255,0.25); border-radius: 50px; padding: 7px 18px; text-decoration: none; margin: 4px; transition: 0.3s; font-size: 0.8rem; }
        .btn-back:hover { background: rgba(255,255,255,0.08); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 0.7rem; border-radius: 15px; }
        .info-card { background: rgba(255,255,255,0.07); border-radius: 15px; padding: 15px; margin: 10px 0; text-align: left; border: 1px solid rgba(255,255,255,0.08); }
        .discount-show { background: linear-gradient(135deg, var(--gold), #FFA500); color: #1a1a2e; padding: 14px; border-radius: 15px; font-weight: 900; font-size: 1.4rem; text-align: center; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; color: white; }
        .admin-table th, .admin-table td { padding: 6px 8px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left; }
        .admin-table th { color: var(--gold); font-weight: 700; background: rgba(255,255,255,0.05); }
        .admin-table input, .admin-table select { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 5px 8px; border-radius: 5px; font-size: 0.75rem; width: 100%; }
        .scroll-box { max-height: 300px; overflow-y: auto; border-radius: 10px; }
        .badge-status { padding: 3px 8px; border-radius: 10px; font-size: 0.65rem; font-weight: 600; }
        .color-preview { width: 25px; height: 25px; border-radius: 50%; display: inline-block; border: 2px solid rgba(255,255,255,0.3); }
        .text-gold { color: var(--gold); } .text-warning { color: #f6c23e; }
        @media (max-width: 500px) { :root { --wheel-size: 240px; } }
    </style>
</head>
<body>
    <div class="container-box">
        <h1>🎡 SPIN & WIN DISKON!</h1>
        <p class="subtitle">Putar roda & dapatkan diskon hingga 50%!</p>
        
        <?php if ($showAdmin): ?>
        <!-- ==================== ADMIN PANEL ==================== -->
        
        <!-- CRUD HADIAH SPINNER -->
        <div class="info-card">
            <h5 class="text-warning"><i class="fas fa-gift"></i> Kelola Hadiah Spinner (<?= count($allPrizes ?? $prizes) ?> item)</h5>
            <div class="scroll-box">
                <table class="admin-table">
                    <thead><tr><th>Label</th><th>Diskon</th><th>Warna</th><th>Weight</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach (($allPrizes ?? $prizes) as $p): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="action" value="edit_prize">
                                <input type="hidden" name="prize_id" value="<?= $p['id'] ?>">
                                <td><input type="text" name="label" value="<?= escape($p['label']) ?>" style="width:100px;"></td>
                                <td><input type="number" name="discount" value="<?= $p['discount'] ?>" style="width:55px;"></td>
                                <td><input type="color" name="color" value="<?= $p['color'] ?>" style="width:35px;height:30px;padding:2px;"></td>
                                <td><input type="number" name="weight" value="<?= $p['weight'] ?>" style="width:50px;"></td>
                                <td><input type="checkbox" name="is_active" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>></td>
                                <td>
                                    <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-save"></i></button>
                                    </form>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_prize"><input type="hidden" name="prize_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tambah Hadiah Baru -->
            <form method="POST" class="row g-1 mt-2">
                <input type="hidden" name="action" value="add_prize">
                <div class="col-3"><input type="text" name="label" class="form-control form-control-sm" placeholder="Label" required style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-2"><input type="number" name="discount" class="form-control form-control-sm" placeholder="Diskon%" required style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-2"><input type="color" name="color" class="form-control form-control-sm" value="#FF6B6B" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);height:32px;"></div>
                <div class="col-2"><input type="number" name="weight" class="form-control form-control-sm" placeholder="Weight" value="10" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-3"><button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-plus"></i> Tambah Hadiah</button></div>
            </form>
        </div>
        
        <!-- Konfigurasi -->
        <div class="info-card">
            <h5 class="text-warning"><i class="fas fa-cog"></i> Konfigurasi</h5>
            <form method="POST" class="row g-1">
                <input type="hidden" name="action" value="update_config">
                <div class="col-4"><label class="small">Expiry (hari)</label><input type="number" name="expiry_days" class="form-control form-control-sm" value="<?= $spinConfig['expiry_days'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Min Belanja (Rp)</label><input type="number" name="min_spent" class="form-control form-control-sm" value="<?= $spinConfig['min_spent'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Cooldown (hari)</label><input type="number" name="cooldown_days" class="form-control form-control-sm" value="<?= $spinConfig['cooldown_days'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-12"><button type="submit" class="btn btn-warning btn-sm w-100 mt-1"><i class="fas fa-save"></i> Simpan</button></div>
            </form>
        </div>
        
        <!-- History Spin + Reset User -->
        <div class="info-card">
            <h5 class="text-warning"><i class="fas fa-history"></i> History & Reset</h5>
            <div class="scroll-box">
                <table class="admin-table">
                    <thead><tr><th>User</th><th>Hasil</th><th>Tgl</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if (empty($allSpins)): ?><tr><td colspan="4" class="text-center py-2">Belum ada data</td></tr>
                        <?php else: foreach ($allSpins as $s): ?>
                        <tr>
                            <td><small><?= escape($s['full_name'] ?? 'User #'.$s['user_id']) ?></small></td>
                            <td><?= $s['prize_label'] ?></td>
                            <td><small><?= date('d/m', strtotime($s['spin_date'])) ?></small></td>
                            <td>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_spin_history"><input type="hidden" name="spin_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')" style="padding:2px 5px;font-size:0.65rem;"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="row g-1 mt-2">
                <div class="col-6">
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_user_spin">
                        <select name="user_id" class="form-select form-select-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"><?php foreach ($allUsers as $u): ?><option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option><?php endforeach; ?></select>
                        <button type="submit" class="btn btn-info btn-sm w-100 mt-1"><i class="fas fa-undo"></i> Reset Spin User</button>
                    </form>
                </div>
                <div class="col-6">
                    <form method="POST"><input type="hidden" name="action" value="clear_all_history"><button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Hapus SEMUA?')"><i class="fas fa-trash"></i> Clear All</button></form>
                </div>
            </div>
        </div>
        
        <a href="spin-wheel.php" class="btn-back">👤 Mode User</a>
        
        <?php elseif ($activeDiscount): ?>
        <!-- USER: Diskon Aktif -->
        <div class="info-card text-center">
            <h5 class="text-warning"><i class="fas fa-ticket-alt"></i> Diskon Aktif!</h5>
            <div class="discount-show"><?= $activeDiscount['prize_label'] ?></div>
            <p class="mt-2">⏰ Berlaku sampai: <strong><?= date('d M Y', strtotime($activeDiscount['expiry_date'])) ?></strong></p>
            <a href="index.php" class="btn btn-warning rounded-pill px-4">🛒 Belanja Sekarang</a>
        </div>
        
        <?php elseif ($spinMessage && !$activeDiscount): ?>
        <!-- USER: Tidak Bisa Spin -->
        <div class="info-card text-center">
            <h5 class="text-warning">ℹ️ Info</h5>
            <p><?= $spinMessage ?></p>
            <p>💰 Total belanja: <strong>Rp <?= number_format($totalSpent, 0, ',', '.') ?></strong></p>
            <a href="index.php" class="btn btn-warning rounded-pill px-4">🛒 Belanja Dulu</a>
        </div>
        
        <?php else: ?>
        <!-- USER: Wheel -->
        <div class="wheel-outer"><div class="pointer-arrow">▼</div><canvas id="wheelCanvas"></canvas></div>
        <div id="resultArea"></div>
        <button class="btn-spin" id="spinButton" onclick="startSpin()">🎰 SPIN SEKARANG!</button>
        <?php endif; ?>
        
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        <?php if ($isAdmin && !$showAdmin): ?><a href="?admin=1" class="btn-back">🔧 Admin Mode</a><?php endif; ?>
    </div>

    <script>
    <?php if ($canSpin && !$showAdmin && !$activeDiscount): ?>
    const canvas = document.getElementById('wheelCanvas');
    const ctx = canvas.getContext('2d');
    // Data hadiah dari PHP
    const segments = <?= json_encode(array_values(array_map(function($p) { return ['label' => $p['discount'] > 0 ? $p['discount'].'%' : '😅', 'color' => $p['color'], 'discount' => (int)$p['discount']]; }, $prizes))) ?>;
    const weights = <?= json_encode(array_values(array_map(function($p) { return (int)$p['weight']; }, $prizes))) ?>;
    const n = segments.length, arc = (2*Math.PI)/n;
    let rot = 0, spinning = false;
    
    function getSize() { return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--wheel-size')); }
    function draw() {
        const s = getSize(), cx = s/2, cy = s/2, r = s/2-8;
        ctx.clearRect(0,0,s,s);
        for(let i=0;i<n;i++){const sa=i*arc+rot,ea=sa+arc;ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,r,sa,ea);ctx.closePath();ctx.fillStyle=segments[i].color;ctx.fill();ctx.strokeStyle='rgba(255,255,255,0.5)';ctx.lineWidth=1.5;ctx.stroke();ctx.save();ctx.translate(cx,cy);ctx.rotate(sa+arc/2);ctx.textAlign='right';ctx.fillStyle='#1a1a2e';ctx.font='bold '+s*0.04+'px Segoe UI';ctx.fillText(segments[i].label,r-s*0.05,s*0.014);ctx.restore();}
        ctx.beginPath();ctx.arc(cx,cy,s*0.06,0,2*Math.PI);ctx.fillStyle='#FFD700';ctx.fill();ctx.fillStyle='#1a1a2e';ctx.font='bold '+s*0.03+'px Segoe UI';ctx.textAlign='center';ctx.fillText('SPIN',cx,cy+1);
    }
    function startSpin() {
        if(spinning)return;spinning=true;document.getElementById('spinButton').disabled=true;document.getElementById('spinButton').textContent='🎰 BERPUTAR...';document.getElementById('resultArea').innerHTML='';
        const tw=weights.reduce((a,b)=>a+b,0);let r=Math.floor(Math.random()*tw)+1,wi=0,c=0;
        for(let i=0;i<weights.length;i++){c+=weights[i];if(r<=c){wi=i;break;}}
        const pa=-Math.PI/2,sm=wi*arc+arc/2,fs=(5+Math.floor(Math.random()*4))*2*Math.PI,tr=fs+(pa-sm),fr=rot+tr,dur=4000,st=performance.now(),sr=rot;
        function anim(now){const e=now-st,p=Math.min(e/dur,1),ea=1-Math.pow(1-p,4);rot=sr+tr*ea;draw();if(p<1)requestAnimationFrame(anim);else{rot=fr%(2*Math.PI);draw();fetch('spin-wheel.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'}}).then(r=>r.json()).then(d=>{document.getElementById('resultArea').innerHTML=segments[wi].discount>0?`<div class="info-card text-center"><h3>🎉 ${segments[wi].label}!</h3><p>Berlaku: ${d.expiry}</p><a href="index.php" class="btn btn-warning rounded-pill px-3">🛒 Belanja</a></div>`:`<div class="info-card text-center"><h3>😅 Coba Lagi!</h3></div>`;spinning=false;document.getElementById('spinButton').textContent='🎰 SPIN SEKARANG!';}).catch(()=>{spinning=false;document.getElementById('spinButton').textContent='🎰 SPIN SEKARANG!';});}}
        requestAnimationFrame(anim);
    }
    function resize(){const s=getSize();canvas.width=s*(window.devicePixelRatio||1);canvas.height=s*(window.devicePixelRatio||1);canvas.style.width=s+'px';canvas.style.height=s+'px';ctx.scale(window.devicePixelRatio||1,window.devicePixelRatio||1);draw();}
    resize();window.addEventListener('resize',resize);
    <?php endif; ?>
    </script>
</body>
</html>