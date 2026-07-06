<?php
require_once 'config/database.php';

// Get top buyers
$stmt = $pdo->query("
    SELECT u.full_name, u.avatar, 
           COUNT(o.id) as total_orders, 
           COALESCE(SUM(o.total_price), 0) as total_spent,
           u.created_at
    FROM users u 
    LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'completed')
    WHERE u.role = 'user'
    GROUP BY u.id 
    ORDER BY total_spent DESC 
    LIMIT 10
");
$leaders = $stmt->fetchAll();

// Get current user rank
$isLoggedIn = isset($_SESSION['user_id']);
$userRank = null;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank 
        FROM (
            SELECT u.id, COALESCE(SUM(o.total_price), 0) as total_spent
            FROM users u 
            LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'completed')
            WHERE u.role = 'user'
            GROUP BY u.id
        ) as rankings 
        WHERE total_spent > (
            SELECT COALESCE(SUM(total_price), 0) 
            FROM orders 
            WHERE user_id = ? AND status IN ('paid', 'completed')
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userRank = $stmt->fetch()['rank'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; 
            font-family: 'Segoe UI', sans-serif;
            color: white;
            padding: 40px 20px;
        }
        
        .leaderboard-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .leaderboard-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .leaderboard-title h1 {
            font-weight: 900;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 40px;
            height: 200px;
        }
        
        .podium-item {
            text-align: center;
            position: relative;
        }
        
        .podium-block {
            border-radius: 10px 10px 0 0;
            transition: all 0.3s;
        }
        
        .podium-block:hover { transform: translateY(-10px); }
        
        .podium-1 .podium-block {
            background: linear-gradient(180deg, #FFD700, #FFA500);
            width: 100px; height: 160px;
        }
        
        .podium-2 .podium-block {
            background: linear-gradient(180deg, #C0C0C0, #808080);
            width: 90px; height: 120px;
        }
        
        .podium-3 .podium-block {
            background: linear-gradient(180deg, #CD7F32, #8B4513);
            width: 80px; height: 90px;
        }
        
        .podium-rank {
            font-size: 2rem;
            font-weight: 900;
        }
        
        .podium-name {
            font-weight: 700;
            margin-top: 5px;
        }
        
        .leaderboard-list {
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            margin: 8px 0;
            border-radius: 12px;
            background: rgba(255,255,255,0.03);
            transition: all 0.3s;
            gap: 15px;
        }
        
        .leaderboard-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(10px);
        }
        
        .rank-badge {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
        }
        
        .rank-1 { background: #FFD700; color: #333; }
        .rank-2 { background: #C0C0C0; color: #333; }
        .rank-3 { background: #CD7F32; color: white; }
        .rank-other { background: rgba(255,255,255,0.1); color: white; }
        
        .user-avatar {
            width: 45px; height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
        }
        
        .user-info { flex: 1; }
        .user-info .name { font-weight: 700; }
        .user-info .stats { font-size: 0.85rem; opacity: 0.7; }
        
        .spent-amount { font-weight: 800; color: #FFD700; }
        
        .badge-icon {
            font-size: 1.5rem;
        }
        
        .badge-diamond { color: #b9f2ff; text-shadow: 0 0 10px rgba(185,242,255,0.5); }
        .badge-gold { color: #FFD700; text-shadow: 0 0 10px rgba(255,215,0,0.5); }
        .badge-silver { color: #C0C0C0; text-shadow: 0 0 10px rgba(192,192,192,0.5); }
    </style>
</head>
<body>
    <div class="leaderboard-container">
        <div class="leaderboard-title">
            <h1>🏆 TOP BUYERS</h1>
            <p>Peringkat pembeli terbanyak di WebPro UMKM</p>
        </div>
        
        <!-- Podium Top 3 -->
        <?php if (count($leaders) >= 3): ?>
        <div class="podium">
            <?php 
            $podiumOrder = [1, 0, 2]; // 2nd, 1st, 3rd
            foreach ($podiumOrder as $i): 
                if (!isset($leaders[$i])) continue;
                $podiumClass = $i == 0 ? 'podium-1' : ($i == 1 ? 'podium-2' : 'podium-3');
            ?>
            <div class="podium-item <?= $podiumClass ?>">
                <div class="podium-rank">
                    <?= $i == 0 ? '🥇' : ($i == 1 ? '🥈' : '🥉') ?>
                </div>
                <div class="podium-block"></div>
                <div class="podium-name"><?= escape($leaders[$i]['full_name']) ?></div>
                <small>Rp <?= number_format($leaders[$i]['total_spent'], 0, ',', '.') ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- User Rank -->
        <?php if ($isLoggedIn && $userRank): ?>
        <div class="alert alert-info text-center">
            🎯 Peringkat kamu: <strong>#<?= $userRank ?></strong>
        </div>
        <?php endif; ?>
        
        <!-- Leaderboard List -->
        <div class="leaderboard-list">
            <?php foreach ($leaders as $index => $leader): 
                $rank = $index + 1;
                $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                $badgeIcon = '';
                if ($rank == 1) $badgeIcon = '<span class="badge-icon badge-diamond">💎</span>';
                elseif ($rank == 2) $badgeIcon = '<span class="badge-icon badge-gold">👑</span>';
                elseif ($rank == 3) $badgeIcon = '<span class="badge-icon badge-silver">🥈</span>';
                
                $initials = strtoupper(substr($leader['full_name'], 0, 2));
            ?>
            <div class="leaderboard-item">
                <div class="rank-badge <?= $rankClass ?>"><?= $rank ?></div>
                <div class="user-avatar"><?= $initials ?></div>
                <div class="user-info">
                    <div class="name"><?= escape($leader['full_name']) ?> <?= $badgeIcon ?></div>
                    <div class="stats"><?= $leader['total_orders'] ?> pesanan</div>
                </div>
                <div class="spent-amount">Rp <?= number_format($leader['total_spent'], 0, ',', '.') ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($leaders)): ?>
                <p class="text-center py-4">Belum ada data pembeli.</p>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-outline-light rounded-pill px-4">🛒 Mulai Belanja</a>
        </div>
    </div>
</body>
</html>