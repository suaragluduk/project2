<?php
require 'config.php';
require 'header.php';

// Keamanan: Cek apakah user adalah ADMIN
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Akses Ditolak. Halaman ini khusus Admin.</div>";
    require 'footer.php';
    exit;
}

$success = '';
$error = '';

// --- 1. Logic Update Role ---
if (isset($_POST['update_role'])) {
    $target_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Mencegah admin menghapus akses admin dirinya sendiri
    if ($target_id == $_SESSION['user_id'] && $new_role != 'admin') {
        $error = "Anda tidak bisa mengubah role Anda sendiri menjadi Karyawan saat sedang login.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_id]);
        $success = "Role user berhasil diupdate!";
    }
}

// --- 2. Logic Reset Password ---
if (isset($_POST['reset_password'])) {
    $target_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    if (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } else {
        // Hash password baru sebelum disimpan
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $target_id])) {
            $success = "Password berhasil direset!";
        } else {
            $error = "Terjadi kesalahan saat mereset password.";
        }
    }
}

// Ambil semua user
$users = $pdo->query("SELECT * FROM users ORDER BY username ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3><i class="fas fa-users-cog text-primary"></i> Manage Users Privilege</h3>
        <p class="text-muted mb-0">Kelola hak akses dan reset password pengguna.</p>
    </div>
</div>

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

<div class="card shadow-sm border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Daftar Pengguna Sistem</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Username</th>
                        <th>Status Role</th>
                        <th>Ubah Role</th>
                        <th class="pe-3">Reset Password</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="ps-3 fw-bold">
                            <i class="fas fa-user-circle text-secondary me-1"></i> 
                            <?= htmlspecialchars($u['username']) ?>
                        </td>
                        <td>
                            <?php if($u['role'] == 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Karyawan</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <form method="POST" class="d-flex gap-2 mb-0">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="new_role" class="form-select form-select-sm" style="width: auto;">
                                    <option value="karyawan" <?= $u['role'] == 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                                    <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-sm btn-outline-primary" onclick="return confirm('Ubah role user ini?')">Update</button>
                            </form>
                        </td>
                        
                        <td class="pe-3">
                            <form method="POST" class="d-flex gap-2 mb-0">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="text" name="new_password" class="form-control form-control-sm" placeholder="Ketik password baru..." required minlength="6" style="width: 180px;">
                                <button type="submit" name="reset_password" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin mereset password akun <?= htmlspecialchars($u['username']) ?>?')">
                                    <i class="fas fa-key"></i> Reset
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>