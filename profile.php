<?php
require_once 'config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($fullName)) {
        $error = 'Nama lengkap harus diisi!';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, business_name = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$fullName, $phone, $businessName, $address, $userId]);
        
        $_SESSION['user_name'] = $fullName;
        $success = 'Profil berhasil diperbarui!';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Semua field password harus diisi!';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok!';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password baru minimal 8 karakter!';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Password saat ini salah!';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            $success = 'Password berhasil diubah!';
        }
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['avatar']['type'];
        $fileSize = $_FILES['avatar']['size'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Format file tidak diizinkan! (JPG, PNG, GIF, WEBP)';
        } elseif ($fileSize > $maxSize) {
            $error = 'Ukuran file terlalu besar! Maksimal 5MB';
        } else {
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = 'uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$uploadPath, $userId]);
                $success = 'Foto profil berhasil diupload!';
            } else {
                $error = 'Gagal mengupload file!';
            }
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get user stats
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $userId")->fetchColumn();
$totalSpent = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = $userId AND status IN ('paid', 'completed')")->fetchColumn();
$reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews WHERE user_id = $userId")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - WebPro UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .profile-container { max-width: 900px; margin: 0 auto; }
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 3px 20px rgba(0,0,0,0.06);
        }
        .avatar-upload {
            width: 150px; height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #4e73df;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 0 auto;
        }
        .avatar-upload:hover { opacity: 0.8; border-color: #224abe; }
        .stat-card {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s;
        }
        .stat-card:hover { background: #e8e9ff; transform: translateY(-3px); }
        .stat-card h3 { font-weight: 800; color: #4e73df; }
        .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 2px solid #e8e8e8;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.15);
        }
        .form-control:disabled, .form-control[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-globe"></i> WebPro UMKM</a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-store"></i> Belanja</a>
                <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="profile-container">
            <h2 class="fw-bold mb-4"><i class="fas fa-user-circle text-primary"></i> Profil Saya</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= $flash['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card"><h3><?= $orderCount ?></h3><small class="text-muted">Pesanan</small></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card"><h4>Rp <?= number_format($totalSpent, 0, ',', '.') ?></h4><small class="text-muted">Total Belanja</small></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card"><h3><?= $reviewCount ?></h3><small class="text-muted">Ulasan</small></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card"><h4><?= strtoupper($user['role']) ?></h4><small class="text-muted">Role</small></div>
                </div>
            </div>

            <div class="row">
                <!-- Avatar Section -->
                <div class="col-md-4 mb-4">
                    <div class="profile-card text-center">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_avatar" value="1">
                            <label for="avatarInput">
                                <img src="<?= escape($user['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=4e73df&color=fff&size=300') ?>" 
                                     class="avatar-upload" alt="Avatar">
                            </label>
                            <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                            <h5 class="mt-3 fw-bold"><?= escape($user['full_name']) ?></h5>
                            <p class="text-muted"><?= escape($user['email']) ?></p>
                            <small class="text-muted">Klik foto untuk mengubah</small>
                        </form>
                    </div>
                </div>
                
                <!-- Profile Form -->
                <div class="col-md-8">
                    <div class="profile-card">
                        <h5 class="fw-bold mb-4"><i class="fas fa-edit text-primary"></i> Edit Profil</h5>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Nama Lengkap *</label>
                                    <input type="text" name="full_name" class="form-control" value="<?= escape($user['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" class="form-control" value="<?= escape($user['email']) ?>" readonly>
                                    <small class="text-muted">Email tidak dapat diubah</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">No. Handphone</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= escape($user['phone']) ?>" placeholder="085710785244">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Nama Bisnis</label>
                                    <input type="text" name="business_name" class="form-control" value="<?= escape($user['business_name'] ?? '') ?>" placeholder="Nama bisnis Anda">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Alamat</label>
                                    <textarea name="address" class="form-control" rows="2" placeholder="Alamat lengkap"><?= escape($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </form>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="profile-card">
                        <h5 class="fw-bold mb-4"><i class="fas fa-lock text-warning"></i> Ubah Password</h5>
                        <form method="POST">
                            <input type="hidden" name="change_password" value="1">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Password Saat Ini *</label>
                                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Password Baru *</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="Min. 8 karakter" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Konfirmasi Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning rounded-pill px-4">
                                <i class="fas fa-key"></i> Ubah Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> WebPro UMKM. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview avatar before upload
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire('Error', 'Ukuran file terlalu besar! Maksimal 5MB', 'error');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('.avatar-upload').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>