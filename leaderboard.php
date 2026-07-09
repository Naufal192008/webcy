<?php
require_once 'config/database.php';

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$isLoggedIn = isset($_SESSION['user_id']);

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_leaderboard') {
    redirect('leaderboard.php', '✅ Leaderboard direset!');
}

$period = $_GET['period'] ?? 'all';
$periodCondition = '';
$periodLabel = 'Semua Waktu';
if ($period === 'week') { $periodCondition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"; $periodLabel = 'Minggu Ini'; }
elseif ($period === 'month') { $periodCondition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; $periodLabel = 'Bulan Ini'; }
elseif ($period === 'year') { $periodCondition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; $periodLabel = 'Tahun Ini'; }

$stmt = $pdo->query("SELECT u.id, u.full_name, u.email, COUNT(o.id) as total_orders, COALESCE(SUM(o.total_price), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'completed') $periodCondition WHERE u.role = 'user' GROUP BY u.id ORDER BY total_spent DESC LIMIT 20");
$leaders = $stmt->fetchAll();

$userRank = null; $userSpent = 0;
if ($isLoggedIn && !$isAdmin) {
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM (SELECT u.id, COALESCE(SUM(o.total_price), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'completed') $periodCondition WHERE u.role = 'user' GROUP BY u.id) r WHERE r.total_spent > (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') $periodCondition)");
    $stmt->execute([$_SESSION['user_id']]);
    $userRank = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = ? AND status IN ('paid', 'completed') $periodCondition");
    $stmt->execute([$_SESSION['user_id']]);
    $userSpent = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>🏆 Leaderboard - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #FFD700; --silver: #C0C0C0; --bronze: #CD7F32; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0f0c29, #302b63, #24243e); min-height: 100vh; font-family: 'Segoe UI', sans-serif; color: white; padding: 30px 15px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { text-align: center; font-weight: 900; font-size: 2.2rem; background: linear-gradient(135deg, var(--gold), #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.25); border-radius: 50px; padding: 8px 20px; text-decoration: none; margin-bottom: 20px; transition: 0.3s; font-size: 0.9rem; }
        .btn-back:hover { background: rgba(255,255,255,0.08); color: white; }
        .period-tabs { display: flex; justify-content: center; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; }
        .period-tab { padding: 8px 18px; border-radius: 20px; background: rgba(255,255,255,0.08); color: white; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: 0.3s; border: 1px solid transparent; }
        .period-tab:hover, .period-tab.active { background: var(--gold); color: #1a1a2e; border-color: var(--gold); }
        .podium { display: flex; justify-content: center; align-items: flex-end; gap: 12px; margin: 30px 0; height: 200px; }
        .podium-item { text-align: center; flex: 1; max-width: 120px; }
        .podium-block { border-radius: 10px 10px 0 0; margin: 0 auto; transition: 0.3s; }
        .podium-1 .podium-block { background: linear-gradient(180deg, var(--gold), #FFA500); width: 100px; height: 140px; }
        .podium-2 .podium-block { background: linear-gradient(180deg, var(--silver), #999); width: 85px; height: 100px; }
        .podium-3 .podium-block { background: linear-gradient(180deg, var(--bronze), #8B4513); width: 75px; height: 70px; }
        .podium-rank { font-size: 2rem; } .podium-name { font-weight: 700; font-size: 0.8rem; margin-top: 5px; }
        .leader-list { background: rgba(255,255,255,0.04); border-radius: 18px; padding: 15px; backdrop-filter: blur(10px); }
        .leader-item { display: flex; align-items: center; padding: 12px 15px; margin: 5px 0; border-radius: 12px; background: rgba(255,255,255,0.03); transition: 0.3s; gap: 12px; }
        .leader-item:hover { background: rgba(255,255,255,0.08); }
        .rank-num { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; }
        .rank-1 { background: var(--gold); color: #333; } .rank-2 { background: var(--silver); color: #333; } .rank-3 { background: var(--bronze); color: white; } .rank-other { background: rgba(255,255,255,0.1); }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; }
        .user-info { flex: 1; } .user-info .name { font-weight: 700; } .user-info .stats { font-size: 0.8rem; opacity: 0.7; }
        .spent { font-weight: 800; color: var(--gold); }
        .my-rank { background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.3); border-radius: 12px; padding: 15px; text-align: center; margin-bottom: 20px; }
        .admin-bar { background: rgba(233,69,96,0.15); border-radius: 12px; padding: 12px 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        @media (max-width: 500px) { .podium-item { max-width: 80px; } .podium-1 .podium-block { width: 70px; height: 100px; } }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        <h1>🏆 TOP BUYERS</h1>
        <p style="text-align:center;opacity:0.6;margin-bottom:15px;"><?= $periodLabel ?></p>
        
        <?php if ($isAdmin): ?>
        <div class="admin-bar">
            <span><i class="fas fa-crown text-warning"></i> Admin Mode</span>
            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reset_leaderboard"><button type="submit" class="btn btn-sm btn-outline-warning rounded-pill" onclick="return confirm('Reset?')"><i class="fas fa-sync"></i> Reset</button></form>
            <a href="admin.php" class="btn btn-sm btn-outline-light rounded-pill"><i class="fas fa-cog"></i> Admin</a>
        </div>
        <?php endif; ?>
        
        <div class="period-tabs">
            <a href="?period=all" class="period-tab <?= $period==='all'?'active':'' ?>">📅 Semua</a>
            <a href="?period=week" class="period-tab <?= $period==='week'?'active':'' ?>">📅 Minggu</a>
            <a href="?period=month" class="period-tab <?= $period==='month'?'active':'' ?>">📅 Bulan</a>
            <a href="?period=year" class="period-tab <?= $period==='year'?'active':'' ?>">📅 Tahun</a>
        </div>
        
        <?php if ($userRank): ?>
        <div class="my-rank"><p class="mb-1">🎯 Peringkat Kamu: <strong>#<?= $userRank ?></strong></p><p class="mb-0">💰 Total: <strong>Rp <?= number_format($userSpent, 0, ',', '.') ?></strong></p></div>
        <?php endif; ?>
        
        <?php if (count($leaders) >= 3): ?>
        <div class="podium">
            <?php $podiumOrder = [1, 0, 2]; foreach ($podiumOrder as $idx): if (!isset($leaders[$idx])) continue; $l = $leaders[$idx]; $pos = $idx + 1; $pClass = $pos==1?'podium-1':($pos==2?'podium-2':'podium-3'); $medal = $pos==1?'🥇':($pos==2?'🥈':'🥉'); ?>
            <div class="podium-item <?= $pClass ?>"><div class="podium-rank"><?= $medal ?></div><div class="podium-block"></div><div class="podium-name"><?= escape($l['full_name']) ?></div><small>Rp <?= number_format($l['total_spent'], 0, ',', '.') ?></small></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="leader-list">
            <?php if (empty($leaders)): ?><p class="text-center py-4 text-muted">Belum ada data.</p>
            <?php else: foreach ($leaders as $index => $l): $rank = $index + 1; $rankClass = $rank==1?'rank-1':($rank==2?'rank-2':($rank==3?'rank-3':'rank-other')); $initials = strtoupper(substr($l['full_name'], 0, 2)); $medal = $rank==1?'💎':($rank==2?'👑':($rank==3?'🥈':'')); ?>
            <div class="leader-item"><div class="rank-num <?= $rankClass ?>"><?= $rank ?></div><div class="avatar-circle"><?= $initials ?></div><div class="user-info"><div class="name"><?= escape($l['full_name']) ?> <?= $medal ?></div><div class="stats"><?= $l['total_orders'] ?> pesanan</div></div><div class="spent">Rp <?= number_format($l['total_spent'], 0, ',', '.') ?></div></div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
    </div>
</body>
</html>