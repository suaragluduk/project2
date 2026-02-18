<?php
require 'config.php';
require 'header.php';

$error = '';
$success = '';

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek password lama di database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 1. Verifikasi Password Lama
        if (!password_verify($old_password, $user['password'])) {
            $error = "Password Lama salah!";
        } 
        // 2. Cek kesesuaian Password Baru
        elseif ($new_password !== $confirm_password) {
            $error = "Konfirmasi Password Baru tidak cocok!";
        }
        // 3. Validasi panjang password
        elseif (strlen($new_password) < 6) {
            $error = "Password Baru minimal 6 karakter!";
        }
        else {
            // 4. Update Password Baru
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update->execute([$new_hash, $user_id])) {
                $success = "Password berhasil diubah!";
            } else {
                $error = "Terjadi kesalahan sistem.";
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-primary mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-key"></i> Ganti Password</h5>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Password Lama</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    
                    <hr>

                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Min. 6 karakter">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Simpan Password Baru
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>