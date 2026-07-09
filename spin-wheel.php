<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$today = date('Y-m-d');

// ==================== ADMIN: HANDLE CRUD ACTIONS ====================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ========== TAMBAH SPIN MANUAL ==========
    if ($action === 'add_spin') {
        $targetUserId = (int)$_POST['user_id'];
        $discount = (int)$_POST['discount'];
        $label = 'Diskon ' . $discount . '%';
        $expiryDate = date('Y-m-d', strtotime('+' . (int)$_POST['expiry_days'] . ' days'));
        $isUsed = isset($_POST['is_used']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO spin_history (user_id, discount, prize_label, spin_date, expiry_date, is_used) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([$targetUserId, $discount, $label, $expiryDate, $isUsed]);
        redirect('spin-wheel.php?admin=1', '✅ Data spin berhasil ditambahkan!');
    }
    
    // ========== EDIT SPIN ==========
    if ($action === 'edit_spin') {
        $spinId = (int)$_POST['spin_id'];
        $discount = (int)$_POST['discount'];
        $label = 'Diskon ' . $discount . '%';
        $expiryDate = $_POST['expiry_date'];
        $isUsed = isset($_POST['is_used']) ? 1 : 0;
        $targetUserId = (int)$_POST['user_id'];
        
        $stmt = $pdo->prepare("UPDATE spin_history SET user_id = ?, discount = ?, prize_label = ?, expiry_date = ?, is_used = ? WHERE id = ?");
        $stmt->execute([$targetUserId, $discount, $label, $expiryDate, $isUsed, $spinId]);
        redirect('spin-wheel.php?admin=1', '✅ Data spin berhasil diperbarui!');
    }
    
    // ========== HAPUS SPIN ==========
    if ($action === 'delete_spin') {
        $spinId = (int)$_POST['spin_id'];
        $pdo->prepare("DELETE FROM spin_history WHERE id = ?")->execute([$spinId]);
        redirect('spin-wheel.php?admin=1', '🗑️ Data spin dihapus!');
    }
    
    // ========== HAPUS SEMUA ==========
    if ($action === 'clear_all') {
        $pdo->query("DELETE FROM spin_history");
        redirect('spin-wheel.php?admin=1', '🗑️ Semua data spin dihapus!');
    }
}

// ==================== LOAD CONFIG ====================
$spinConfig = ['min_discount' => 5, 'max_discount' => 50, 'expiry_days' => 30, 'min_spent' => 1000000, 'cooldown_days' => 1];
if (file_exists('spin_config.json')) {
    $spinConfig = array_merge($spinConfig, json_decode(file_get_contents('spin_config.json'), true) ?: []);
}

// ==================== CEK DISKON AKTIF USER ====================
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
            $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt2->execute([$userId, $lastSpin['expiry_date']]);
            $spentAfterExpiry = $stmt2->fetchColumn();
            
            if ($spentAfterExpiry >= $spinConfig['min_spent']) {
                $canSpin = true;
            } else {
                $waitUntil = new DateTime($lastSpin['expiry_date']);
                $waitUntil->modify('+2 months');
                if ($now < $waitUntil) {
                    $canSpin = false;
                    $spinMessage = 'Diskon hangus. Bisa spin lagi setelah ' . $waitUntil->format('d M Y') . ' atau belanja > Rp ' . number_format($spinConfig['min_spent'], 0, ',', '.');
                }
            }
        } elseif ($lastSpin['discount'] > 0 && $lastSpin['is_used'] == 1) {
            $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') AND created_at > ?");
            $stmt2->execute([$userId, $lastSpin['used_at'] ?? $lastSpin['spin_date']]);
            $spentAfterUse = $stmt2->fetchColumn();
            
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
    $cumulative = 0; $selectedIndex = 0;
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
$showAdmin = isset($_GET['admin']) && $isAdmin;

if ($showAdmin) {
    $allSpins = $pdo->query("SELECT s.*, u.full_name, u.email FROM spin_history s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.spin_date DESC")->fetchAll();
    $allUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'user' ORDER BY full_name")->fetchAll();
}

// Data untuk edit (jika ada parameter edit)
$editSpin = null;
if ($showAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM spin_history WHERE id = ?");
    $stmt->execute([$editId]);
    $editSpin = $stmt->fetch();
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
        .container-box { background: rgba(255,255,255,0.05); border-radius: 25px; padding: clamp(20px, 5vw, 40px); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); max-width: 900px; width: 100%; text-align: center; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
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
        .info-card { background: rgba(255,255,255,0.07); border-radius: 15px; padding: 18px; margin: 12px 0; text-align: left; border: 1px solid rgba(255,255,255,0.08); }
        .discount-show { background: linear-gradient(135deg, var(--gold), #FFA500); color: #1a1a2e; padding: 16px; border-radius: 15px; font-weight: 900; font-size: 1.6rem; text-align: center; }
        
        /* ADMIN TABLE */
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; color: white; }
        .admin-table th, .admin-table td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; }
        .admin-table th { color: var(--gold); font-weight: 700; background: rgba(255,255,255,0.05); white-space: nowrap; }
        .admin-table tr:hover { background: rgba(255,255,255,0.04); }
        .admin-table input, .admin-table select { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 10px; border-radius: 8px; font-size: 0.82rem; width: 100%; }
        .admin-table input[type="date"] { color-scheme: dark; }
        .admin-table input[type="checkbox"] { width: auto; transform: scale(1.2); }
        .scroll-box { max-height: 450px; overflow-y: auto; border-radius: 10px; }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .btn-action { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; border: none; cursor: pointer; transition: 0.2s; margin: 1px; }
        .btn-action:hover { transform: scale(1.05); }
        .btn-edit { background: #ffc107; color: #333; } .btn-del { background: #dc3545; color: white; } .btn-save { background: #28a745; color: white; }
        .text-gold { color: var(--gold); } .text-warning { color: #f6c23e; }
        
        @media (max-width: 600px) {
            .admin-table { font-size: 0.7rem; }
            .admin-table th, .admin-table td { padding: 6px 8px; }
        }
    </style>
</head>
<body>
    <div class="container-box">
        <h1>🎡 SPIN & WIN DISKON!</h1>
        <p class="subtitle">Putar roda & dapatkan diskon hingga 50%!</p>
        
        <?php if ($showAdmin): ?>
        <!-- ==================== ADMIN PANEL CRUD ==================== -->
        
        <!-- FORM TAMBAH/EDIT -->
        <div class="info-card">
            <h5 class="text-warning">
                <i class="fas fa-<?= $editSpin ? 'edit' : 'plus-circle' ?>"></i> 
                <?= $editSpin ? 'Edit Data Spin' : 'Tambah Data Spin Baru' ?>
            </h5>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editSpin ? 'edit_spin' : 'add_spin' ?>">
                <?php if ($editSpin): ?>
                <input type="hidden" name="spin_id" value="<?= $editSpin['id'] ?>">
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="small">User</label>
                        <select name="user_id" class="form-select form-select-sm" style="background:rgba(255,255,255,0.08);color:white;border:1px solid rgba(255,255,255,0.2);">
                            <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($editSpin && $editSpin['user_id'] == $u['id']) ? 'selected' : '' ?>><?= escape($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small">Diskon (%)</label>
                        <input type="number" name="discount" class="form-control form-control-sm" value="<?= $editSpin ? $editSpin['discount'] : '10' ?>" min="0" max="100" style="background:rgba(255,255,255,0.08);color:white;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <div class="col-md-2">
                        <label class="small">Expiry</label>
                        <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= $editSpin ? $editSpin['expiry_date'] : date('Y-m-d', strtotime('+30 days')) ?>" style="background:rgba(255,255,255,0.08);color:white;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <div class="col-md-2">
                        <label class="small">Expiry (hari)</label>
                        <input type="number" name="expiry_days" class="form-control form-control-sm" value="30" style="background:rgba(255,255,255,0.08);color:white;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <div class="col-md-1">
                        <label class="small">Used</label>
                        <input type="checkbox" name="is_used" <?= ($editSpin && $editSpin['is_used']) ? 'checked' : '' ?> style="margin-top:8px;transform:scale(1.3);">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-save w-100">
                            <i class="fas fa-<?= $editSpin ? 'save' : 'plus' ?>"></i> <?= $editSpin ? 'Update' : 'Tambah' ?>
                        </button>
                        <?php if ($editSpin): ?>
                        <a href="?admin=1" class="btn btn-sm btn-secondary ms-1" style="padding:5px 10px;">✕</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- TABEL DATA SPIN -->
        <div class="info-card">
            <h5 class="text-warning">
                <i class="fas fa-table"></i> Data Spin History (<?= count($allSpins) ?>)
                <form method="POST" style="display:inline;float:right;" onsubmit="return confirm('Hapus SEMUA data spin?')">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-sm btn-del"><i class="fas fa-trash"></i> Hapus Semua</button>
                </form>
            </h5>
            <div class="scroll-box">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Diskon</th>
                            <th>Label</th>
                            <th>Tgl Spin</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allSpins)): ?>
                            <tr><td colspan="8" class="text-center py-4">Belum ada data spin</td></tr>
                        <?php else: ?>
                            <?php foreach ($allSpins as $s): ?>
                            <tr>
                                <td>#<?= $s['id'] ?></td>
                                <td><?= escape($s['full_name'] ?? 'User #'.$s['user_id']) ?></td>
                                <td><strong><?= $s['discount'] ?>%</strong></td>
                                <td><?= $s['prize_label'] ?></td>
                                <td><?= date('d/m/Y', strtotime($s['spin_date'])) ?></td>
                                <td><?= $s['expiry_date'] ? date('d/m/Y', strtotime($s['expiry_date'])) : '-' ?></td>
                                <td><span class="badge-status bg-<?= $s['is_used']==1?'success':($s['is_used']==0?'warning':'secondary') ?>"><?= $s['is_used']==1?'Dipakai':($s['is_used']==0?'Aktif':'Reset') ?></span></td>
                                <td>
                                    <a href="?admin=1&edit=<?= $s['id'] ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus data ini?')">
                                        <input type="hidden" name="action" value="delete_spin">
                                        <input type="hidden" name="spin_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn-action btn-del"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            <ul style="padding-left:18px;"><li>Diskon acak 5% - 50%</li><li>Berlaku <?= $spinConfig['expiry_days'] ?> hari</li><li>1 kali pakai saat checkout</li><li>Belanja > Rp <?= number_format($spinConfig['min_spent'], 0, ',', '.') ?> untuk spin lagi</li></ul>
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