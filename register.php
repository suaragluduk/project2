<?php
session_start();
require 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    // Default role saat register sendiri adalah 'karyawan'
    // Admin bisa mengubahnya nanti di menu Manage Users
    $role = 'karyawan'; 

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Semua kolom wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Cek apakah username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = "Username sudah terdaftar! Gunakan yang lain.";
        } else {
            // Hash password & Insert ke Database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            if ($insert->execute([$username, $hashed_password, $role])) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan sistem saat mendaftar.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PT SABS Cost Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .register-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .register-header {
            background: #ffffff;
            padding: 25px 20px 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .logo-img {
            max-width: 100px;
            height: auto;
            margin-bottom: 10px;
        }
        .company-title {
            color: #2c3e50;
            font-weight: 700;
            text-transform: uppercase;
        }
        .btn-success {
            padding: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="register-card bg-white">
    
    <div class="register-header">
        <img src="logo.png" alt="Logo PT SABS" class="logo-img" onerror="this.style.display='none'">
        <h5 class="company-title">PT SABS</h5>
        <p class="text-muted small mb-0">Registrasi Akun Karyawan Baru</p>
    </div>

    <div class="card-body p-4">
        
        <?php if($error): ?>
            <div class="alert alert-danger text-center py-2">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success text-center py-3">
                <i class="fas fa-check-circle me-1"></i> <?= $success ?>
                <br><a href="index.php" class="btn btn-sm btn-outline-success mt-2">Klik disini untuk Login</a>
            </div>
        <?php else: ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Username Baru</label>
                    <input type="text" name="username" class="form-control" placeholder="Buat username unik" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
                </div>

                <button type="submit" class="btn btn-success w-100 rounded-pill mb-3">
                    <i class="fas fa-user-plus me-2"></i> DAFTAR SEKARANG
                </button>
            </form>

            <div class="text-center mt-3 border-top pt-3">
                <small class="text-muted">Sudah punya akun?</small><br>
                <a href="index.php" class="text-decoration-none fw-bold">Kembali ke Login</a>
            </div>

        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>