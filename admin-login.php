<?php
require_once 'config/database.php';

// If already logged in as admin, redirect to admin panel
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token keamanan tidak valid!';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $adminKey = $_POST['admin_key'] ?? '';
        
        if (empty($email) || empty($password) || empty($adminKey)) {
            $error = 'Semua field harus diisi!';
        } elseif (!validateEmail($email)) {
            $error = 'Format email tidak valid!';
        } elseif (!verifyAdminKey($adminKey)) {
            $error = 'Kunci admin tidak valid!';
        } else {
            // Rate limiting
            checkRateLimit('admin_login_' . $email, 3, 600);
            
            // Check user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Login success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_admin'] = true;
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Log login
                logLogin($user['id'], 'success');
                
                // Regenerate session
                session_regenerate_id(true);
                
                redirect('admin.php', 'Selamat datang Admin!', 'success');
            } else {
                $error = 'Email, password, atau kunci admin salah!';
                logLogin($user['id'] ?? null, 'failed');
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .background-animation {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            background: linear-gradient(45deg, #0a0a0a, #1a1a2e, #16213e, #0f3460);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            max-width: 450px;
            width: 90%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header .shield-icon {
            font-size: 4rem;
            color: #e94560;
            margin-bottom: 15px;
        }
        
        .login-header h2 {
            color: white;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group .input-icon {
            position: relative;
        }
        
        .form-group .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1.1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e94560;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 20px rgba(233, 69, 96, 0.2);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #e94560, #c23152);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4);
            background: linear-gradient(135deg, #ff6b81, #e94560);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background: rgba(233, 69, 96, 0.2);
            border: 1px solid rgba(233, 69, 96, 0.3);
            color: #ff6b81;
        }
        
        .alert-success {
            background: rgba(28, 200, 138, 0.2);
            border: 1px solid rgba(28, 200, 138, 0.3);
            color: #1cc88a;
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        
        .back-link a:hover {
            color: white;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.8rem;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="background-animation"></div>
    
    <div class="login-container">
        <div class="login-header">
            <div class="shield-icon">
                <i class="fas fa-shield-haltered"></i>
            </div>
            <h2>Admin Access</h2>
            <p>Masukkan kredensial admin dan kunci keamanan</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= escape($error) ?>
            </div>
        <?php endif; ?>
        
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <i class="fas fa-info-circle"></i> <?= escape($flash['text']) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label>Email Admin</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="admin@webpro.com" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Kunci Admin (Admin Key)</label>
                <div class="input-icon">
                    <i class="fas fa-key"></i>
                    <input type="password" name="admin_key" class="form-control" placeholder="Masukkan kunci admin" required>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Akses Admin Panel
            </button>
        </form>
        
        <div class="back-link">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
        
        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <span>256-bit Encryption • CSRF Protected • Rate Limited</span>
        </div>
    </div>
</body>
</html>