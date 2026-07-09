<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$today = date('Y-m-d');

// ==================== ADMIN: HANDLE ACTIONS ====================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Reset spin untuk user tertentu
    if ($action === 'reset_spin') {
        $targetUserId = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE spin_history SET is_used = 2 WHERE user_id = ? AND is_used = 0")->execute([$targetUserId]);
        redirect('spin-wheel.php?admin=1', '✅ Spin user berhasil direset!');
    }
    
    // Hapus history spin
    if ($action === 'delete_spin') {
        $spinId = (int)$_POST['spin_id'];
        $pdo->prepare("DELETE FROM spin_history WHERE id = ?")->execute([$spinId]);
        redirect('spin-wheel.php?admin=1', '🗑️ History spin dihapus!');
    }
    
    // Hapus semua history spin (clear all)
    if ($action === 'clear_all_spins') {
        $pdo->query("DELETE FROM spin_history");
        redirect('spin-wheel.php?admin=1', '🗑️ Semua history spin dihapus!');
    }
    
    // Tambah spin manual untuk user
    if ($action === 'add_spin_manual') {
        $targetUserId = (int)$_POST['user_id'];
        $discount = (int)$_POST['discount'];
        $label = 'Diskon ' . $discount . '%';
        $expiryDate = date('Y-m-d', strtotime('+' . ($_POST['expiry_days'] ?? 30) . ' days'));
        
        $stmt = $pdo->prepare("INSERT INTO spin_history (user_id, discount, prize_label, spin_date, expiry_date, is_used) VALUES (?, ?, ?, NOW(), ?, 0)");
        $stmt->execute([$targetUserId, $discount, $label, $expiryDate]);
        redirect('spin-wheel.php?admin=1', '✅ Diskon manual diberikan ke user!');
    }
    
    // Update konfigurasi spin
    if ($action === 'update_config') {
        $minDiscount = (int)$_POST['min_discount'];
        $maxDiscount = (int)$_POST['max_discount'];
        $expiryDays = (int)$_POST['expiry_days'];
        $minSpentToSpin = (int)$_POST['min_spent'];
        $cooldownDays = (int)$_POST['cooldown_days'];
        $maxSpinsPerDay = (int)$_POST['max_spins_per_day'];
        
        $config = json_encode([
            'min_discount' => $minDiscount,
            'max_discount' => $maxDiscount,
            'expiry_days' => $expiryDays,
            'min_spent' => $minSpentToSpin,
            'cooldown_days' => $cooldownDays,
            'max_spins_per_day' => $maxSpinsPerDay
        ]);
        file_put_contents('spin_config.json', $config);
        redirect('spin-wheel.php?admin=1', '✅ Konfigurasi spin diperbarui!');
    }
    
    // Update status spin (mark as used/unused)
    if ($action === 'toggle_spin_status') {
        $spinId = (int)$_POST['spin_id'];
        $stmt = $pdo->prepare("UPDATE spin_history SET is_used = IF(is_used=1, 0, 1) WHERE id = ?");
        $stmt->execute([$spinId]);
        redirect('spin-wheel.php?admin=1', '✅ Status spin diubah!');
    }
    
    // Hapus semua spin untuk user tertentu
    if ($action === 'clear_user_spins') {
        $targetUserId = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM spin_history WHERE user_id = ?")->execute([$targetUserId]);
        redirect('spin-wheel.php?admin=1', '🗑️ Semua spin user dihapus!');
    }
}

// ==================== LOAD CONFIG ====================
$spinConfig = [
    'min_discount' => 5,
    'max_discount' => 50,
    'expiry_days' => 30,
    'min_spent' => 1000000,
    'cooldown_days' => 1,
    'max_spins_per_day' => 1
];
if (file_exists('spin_config.json')) {
    $spinConfig = array_merge($spinConfig, json_decode(file_get_contents('spin_config.json'), true) ?: []);
}

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
        
        if ($lastSpin['discount'] == 0 && $diffDays < $spinConfig['cooldown_days']) {
            $canSpin = false;
            $spinMessage = 'Kamu sudah spin hari ini. Coba lagi besok!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] >= $today) {
            $canSpin = false;
            $spinMessage = 'Kamu masih punya diskon yang belum dipakai!';
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 0 && $lastSpin['expiry_date'] < $today) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['expiry_date']]);
            $spentAfterExpiry = $stmt->fetchColumn();
            
            if ($spentAfterExpiry >= $spinConfig['min_spent']) {
                $canSpin = true;
            } else {
                $waitUntil = new DateTime($lastSpin['expiry_date']);
                $waitUntil->modify('+2 months');
                if ($now < $waitUntil) {
                    $canSpin = false;
                    $spinMessage = 'Diskon hangus. Bisa spin lagi setelah <strong>' . $waitUntil->format('d M Y') . '</strong> atau belanja > Rp ' . number_format($spinConfig['min_spent'], 0, ',', '.');
                }
            }
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 1) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt->execute([$userId, $lastSpin['used_at'] ?? $lastSpin['spin_date']]);
            $spentAfterUse = $stmt->fetchColumn();
            
            if ($spentAfterUse >= $spinConfig['min_spent']) {
                $canSpin = true;
            } else {
                $canSpin = false;
                $spinMessage = 'Diskon sudah dipakai. Belanja > Rp ' . number_format($spinConfig['min_spent'], 0, ',', '.') . ' untuk spin lagi!';
            }
        }
    }
}

// ==================== HANDLE SPIN (USER) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSpin && !isset($_POST['action'])) {
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
    $expiryDate = date('Y-m-d', strtotime('+' . $spinConfig['expiry_days'] . ' days'));
    
    $stmt = $pdo->prepare("INSERT INTO spin_history (user_id, discount, prize_label, spin_date, expiry_date, is_used) VALUES (?, ?, ?, NOW(), ?, 0)");
    $stmt->execute([$userId, $prize['discount'], $prize['label'], $expiryDate]);
    
    echo json_encode(['success' => true, 'prize' => $prize, 'winIndex' => $selectedIndex, 'expiry' => date('d M Y', strtotime($expiryDate))]);
    exit;
}

// Total belanja
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed')");
$stmt->execute([$userId]);
$totalSpent = $stmt->fetchColumn();

// ==================== ADMIN DATA ====================
$allSpins = [];
$userSpinStats = [];
$allUsers = [];
$showAdmin = isset($_GET['admin']) && $isAdmin;

if ($showAdmin) {
    $allSpins = $pdo->query("SELECT s.*, u.full_name, u.email FROM spin_history s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.spin_date DESC LIMIT 200")->fetchAll();
    $userSpinStats = $pdo->query("SELECT u.id, u.full_name, u.email, COUNT(s.id) as total_spins, SUM(CASE WHEN s.discount > 0 THEN 1 ELSE 0 END) as wins, MAX(s.spin_date) as last_spin FROM users u LEFT JOIN spin_history s ON u.id = s.user_id WHERE u.role = 'user' GROUP BY u.id ORDER BY total_spins DESC")->fetchAll();
    $allUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'user' ORDER BY full_name")->fetchAll();
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
        :root { --bg: #1a1a2e; --gold: #FFD700; --wheel-size: min(340px, 80vw); --text: #ffffff; --text-dim: rgba(255,255,255,0.6); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0f0c29, #1a1a2e, #16213e); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; color: var(--text); padding: 15px; }
        .container-box { background: rgba(255,255,255,0.05); border-radius: 25px; padding: clamp(20px, 5vw, 40px); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); max-width: 800px; width: 100%; text-align: center; }
        h1 { font-weight: 900; font-size: clamp(1.4rem, 4vw, 2rem); background: linear-gradient(135deg, var(--gold), #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 5px; }
        .subtitle { color: var(--text-dim); margin-bottom: 20px; font-size: 0.9rem; }
        .wheel-outer { position: relative; display: inline-block; margin: 10px auto; }
        canvas { border-radius: 50%; box-shadow: 0 0 40px rgba(255,215,0,0.2); width: var(--wheel-size); height: var(--wheel-size); }
        .pointer-arrow { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); font-size: 2.5rem; color: var(--gold); z-index: 10; animation: bounce 1.2s infinite; }
        @keyframes bounce { 0%,100%{transform:translateX(-50%) translateY(0);} 50%{transform:translateX(-50%) translateY(-8px);} }
        .btn-spin { background: linear-gradient(135deg, var(--gold), #FFA500); color: #1a1a2e; border: none; padding: 14px 45px; border-radius: 50px; font-size: 1.1rem; font-weight: 800; cursor: pointer; transition: 0.3s; margin: 15px 0; text-transform: uppercase; letter-spacing: 2px; }
        .btn-spin:hover:not(:disabled) { transform: scale(1.05); box-shadow: 0 10px 30px rgba(255,215,0,0.4); }
        .btn-spin:disabled { background: #555; color: #999; cursor: not-allowed; }
        .btn-back { display: inline-block; color: var(--text-dim); border: 1px solid rgba(255,255,255,0.25); border-radius: 50px; padding: 8px 22px; text-decoration: none; margin: 5px; transition: 0.3s; font-size: 0.85rem; }
        .btn-back:hover { background: rgba(255,255,255,0.08); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.75rem; border-radius: 20px; }
        .info-card { background: rgba(255,255,255,0.07); border-radius: 15px; padding: 18px; margin: 12px 0; text-align: left; border: 1px solid rgba(255,255,255,0.08); }
        .discount-show { background: linear-gradient(135deg, var(--gold), #FFA500); color: #1a1a2e; padding: 16px; border-radius: 15px; font-weight: 900; font-size: 1.6rem; text-align: center; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; color: white; }
        .admin-table th, .admin-table td { padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left; }
        .admin-table th { color: var(--gold); font-weight: 700; background: rgba(255,255,255,0.05); position: sticky; top: 0; }
        .admin-table input, .admin-table select { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; }
        .scroll-box { max-height: 350px; overflow-y: auto; border-radius: 10px; }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .text-gold { color: var(--gold); } .text-warning { color: #f6c23e; }
        @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container-box">
        <h1>🎡 SPIN & WIN DISKON!</h1>
        <p class="subtitle">Putar roda & dapatkan diskon hingga 50%!</p>
        
        <?php if ($showAdmin): ?>
        <!-- ==================== ADMIN PANEL ==================== -->
        <div class="info-card">
            <h5 class="text-warning"><i class="fas fa-cog"></i> Admin - Konfigurasi Spin</h5>
            <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="update_config">
                <div class="col-4"><label class="small">Min Diskon (%)</label><input type="number" name="min_discount" class="form-control form-control-sm" value="<?= $spinConfig['min_discount'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Max Diskon (%)</label><input type="number" name="max_discount" class="form-control form-control-sm" value="<?= $spinConfig['max_discount'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Expiry (hari)</label><input type="number" name="expiry_days" class="form-control form-control-sm" value="<?= $spinConfig['expiry_days'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Min Belanja (Rp)</label><input type="number" name="min_spent" class="form-control form-control-sm" value="<?= $spinConfig['min_spent'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Cooldown (hari)</label><input type="number" name="cooldown_days" class="form-control form-control-sm" value="<?= $spinConfig['cooldown_days'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-4"><label class="small">Max Spin/hari</label><input type="number" name="max_spins_per_day" class="form-control form-control-sm" value="<?= $spinConfig['max_spins_per_day'] ?>" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-12"><button type="submit" class="btn btn-warning btn-sm w-100 mt-2"><i class="fas fa-save"></i> Simpan Konfigurasi</button></div>
            </form>
        </div>
        
        <!-- ADD SPIN MANUAL -->
        <div class="info-card">
            <h5 class="text-warning"><i class="fas fa-gift"></i> Beri Diskon Manual ke User</h5>
            <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="add_spin_manual">
                <div class="col-4"><label class="small">User</label><select name="user_id" class="form-select form-select-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"><?php foreach ($allUsers as $u): ?><option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-3"><label class="small">Diskon (%)</label><input type="number" name="discount" class="form-control form-control-sm" value="10" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-3"><label class="small">Expiry (hari)</label><input type="number" name="expiry_days" class="form-control form-control-sm" value="30" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);"></div>
                <div class="col-2 d-flex align-items-end"><button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-plus"></i></button></div>
            </form>
        </div>
        
        <!-- HISTORY SPIN -->
        <div class="grid-2">
            <div class="info-card">
                <h5 class="text-warning"><i class="fas fa-list"></i> History Spin (<?= count($allSpins) ?>)</h5>
                <div class="scroll-box">
                    <table class="admin-table">
                        <thead><tr><th>User</th><th>Hasil</th><th>Tgl</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if (empty($allSpins)): ?><tr><td colspan="5" class="text-center py-3">Belum ada data</td></tr>
                            <?php else: foreach ($allSpins as $s): ?>
                            <tr>
                                <td><small><?= escape($s['full_name'] ?? 'User #'.$s['user_id']) ?></small></td>
                                <td><strong><?= $s['prize_label'] ?></strong></td>
                                <td><small><?= date('d/m', strtotime($s['spin_date'])) ?></small></td>
                                <td><span class="badge-status bg-<?= $s['is_used']==1?'success':($s['is_used']==0?'warning':'secondary') ?>"><?= $s['is_used']==1?'✅':($s['is_used']==0?'🕐':'🔄') ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle_spin_status"><input type="hidden" name="spin_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-info" title="Toggle Status" style="padding:2px 6px;font-size:0.7rem;"><i class="fas fa-exchange-alt"></i></button></form>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_spin"><input type="hidden" name="spin_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')" style="padding:2px 6px;font-size:0.7rem;"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <form method="POST" class="mt-2"><input type="hidden" name="action" value="clear_all_spins"><button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Hapus SEMUA history spin?')"><i class="fas fa-trash"></i> Hapus Semua History</button></form>
            </div>
            
            <!-- USER STATS -->
            <div class="info-card">
                <h5 class="text-warning"><i class="fas fa-chart-bar"></i> Statistik User (<?= count($userSpinStats) ?>)</h5>
                <div class="scroll-box">
                    <table class="admin-table">
                        <thead><tr><th>User</th><th>Spin</th><th>Win</th><th>Rate</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($userSpinStats as $stat): ?>
                            <tr>
                                <td><small><?= escape($stat['full_name']) ?></small></td>
                                <td><?= $stat['total_spins'] ?></td>
                                <td><?= $stat['wins'] ?></td>
                                <td><?= $stat['total_spins'] > 0 ? round($stat['wins']/$stat['total_spins']*100) : 0 ?>%</td>
                                <td>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reset_spin"><input type="hidden" name="user_id" value="<?= $stat['id'] ?>"><button class="btn btn-sm btn-warning" title="Reset Spin" style="padding:2px 6px;font-size:0.7rem;"><i class="fas fa-undo"></i></button></form>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="clear_user_spins"><input type="hidden" name="user_id" value="<?= $stat['id'] ?>"><button class="btn btn-sm btn-danger" onclick="return confirm('Hapus semua spin user ini?')" style="padding:2px 6px;font-size:0.7rem;"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
            <p>💰 Diskon otomatis dipotong saat checkout!</p>
            <a href="index.php" class="btn btn-warning rounded-pill px-4">🛒 Belanja Sekarang</a>
        </div>
        
        <?php elseif ($spinMessage && !$activeDiscount): ?>
        <!-- USER: Tidak Bisa Spin -->
        <div class="info-card text-center">
            <h5 class="text-warning"><i class="fas fa-info-circle"></i> Info</h5>
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
        
        <!-- Rules -->
        <div class="info-card" style="font-size:0.85rem;">
            <h6 class="text-gold">📋 Aturan:</h6>
            <ul style="padding-left:18px;">
                <li>Diskon acak 5% - 50%</li>
                <li>Berlaku <?= $spinConfig['expiry_days'] ?> hari</li>
                <li>1 kali pakai saat checkout</li>
                <li>Belanja > Rp <?= number_format($spinConfig['min_spent'], 0, ',', '.') ?> untuk spin lagi</li>
            </ul>
        </div>
        
        <?php if ($isAdmin && !$showAdmin): ?><a href="?admin=1" class="btn-back">🔧 Admin Mode</a><?php endif; ?>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <script>
    <?php if ($canSpin && !$showAdmin && !$activeDiscount): ?>
    const canvas = document.getElementById('wheelCanvas');
    const ctx = canvas.getContext('2d');
    const segments = [
        { label: '5%', color: '#FF6B6B', discount: 5 }, { label: '10%', color: '#4ECDC4', discount: 10 },
        { label: '15%', color: '#45B7D1', discount: 15 }, { label: '20%', color: '#96CEB4', discount: 20 },
        { label: '25%', color: '#FFEAA7', discount: 25 }, { label: '30%', color: '#DDA0DD', discount: 30 },
        { label: '50%', color: '#FFD700', discount: 50 }, { label: '😅', color: '#98D8C8', discount: 0 },
    ];
    const n = segments.length, arc = (2*Math.PI)/n;
    let rot = 0, spinning = false;
    
    function getSize() { return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--wheel-size')); }
    function draw() {
        const s = getSize(), cx = s/2, cy = s/2, r = s/2-10;
        ctx.clearRect(0,0,s,s);
        for(let i=0;i<n;i++){const sa=i*arc+rot,ea=sa+arc;ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,r,sa,ea);ctx.closePath();ctx.fillStyle=segments[i].color;ctx.fill();ctx.strokeStyle='rgba(255,255,255,0.5)';ctx.lineWidth=2;ctx.stroke();ctx.save();ctx.translate(cx,cy);ctx.rotate(sa+arc/2);ctx.textAlign='right';ctx.fillStyle='#1a1a2e';ctx.font='bold '+s*0.045+'px Segoe UI';ctx.fillText(segments[i].label,r-s*0.06,s*0.016);ctx.restore();}
        ctx.beginPath();ctx.arc(cx,cy,s*0.07,0,2*Math.PI);ctx.fillStyle='#FFD700';ctx.fill();ctx.fillStyle='#1a1a2e';ctx.font='bold '+s*0.035+'px Segoe UI';ctx.textAlign='center';ctx.fillText('SPIN',cx,cy+2);
    }
    function startSpin() {
        if(spinning)return;spinning=true;document.getElementById('spinButton').disabled=true;document.getElementById('spinButton').textContent='🎰 BERPUTAR...';document.getElementById('resultArea').innerHTML='';
        const w=[20,20,18,15,12,8,5,2];const tw=w.reduce((a,b)=>a+b,0);let r=Math.floor(Math.random()*tw)+1,wi=0,c=0;
        for(let i=0;i<w.length;i++){c+=w[i];if(r<=c){wi=i;break;}}
        const pa=-Math.PI/2,sm=wi*arc+arc/2,fs=(5+Math.floor(Math.random()*4))*2*Math.PI,tr=fs+(pa-sm),fr=rot+tr,dur=4500,st=performance.now(),sr=rot;
        function anim(now){const e=now-st,p=Math.min(e/dur,1),ea=1-Math.pow(1-p,4);rot=sr+tr*ea;draw();if(p<1)requestAnimationFrame(anim);else{rot=fr%(2*Math.PI);draw();fetch('spin-wheel.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'}}).then(r=>r.json()).then(d=>{document.getElementById('resultArea').innerHTML=segments[wi].discount>0?`<div class="info-card text-center"><h3>🎉 ${segments[wi].label}!</h3><p>Berlaku sampai: ${d.expiry}</p><a href="index.php" class="btn btn-warning rounded-pill px-3">🛒 Belanja</a></div>`:`<div class="info-card text-center"><h3>😅 Coba Lagi!</h3></div>`;spinning=false;document.getElementById('spinButton').textContent='🎰 SPIN SEKARANG!';}).catch(()=>{spinning=false;document.getElementById('spinButton').textContent='🎰 SPIN SEKARANG!';});}}
        requestAnimationFrame(anim);
    }
    function resize(){const s=getSize();canvas.width=s*(window.devicePixelRatio||1);canvas.height=s*(window.devicePixelRatio||1);canvas.style.width=s+'px';canvas.style.height=s+'px';ctx.scale(window.devicePixelRatio||1,window.devicePixelRatio||1);draw();}
    resize();window.addEventListener('resize',resize);
    <?php endif; ?>
    </script>
</body>
</html>