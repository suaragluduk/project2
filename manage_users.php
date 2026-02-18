<?php
require 'config.php';
require 'header.php';

// Keamanan: Cek apakah user adalah ADMIN
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Akses Ditolak. Halaman ini khusus Admin.</div>";
    exit;
}

// Logic Update Role
if (isset($_POST['update_role'])) {
    $target_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Mencegah admin menghapus akses admin dirinya sendiri (opsional tapi bagus)
    if ($target_id == $_SESSION['user_id'] && $new_role != 'admin') {
        echo "<div class='alert alert-warning'>Anda tidak bisa mengubah role Anda sendiri menjadi Karyawan saat sedang login.</div>";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_id]);
        echo "<div class='alert alert-success'>Role user berhasil diupdate!</div>";
    }
}

// Ambil semua user
$users = $pdo->query("SELECT * FROM users ORDER BY username ASC")->fetchAll();
?>

<h3>Manage Users Privilege</h3>
<p>Ubah hak akses user (Karyawan / Admin).</p>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Username</th>
                    <th>Current Role</th>
                    <th>Change Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <?php if($u['role'] == 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark">Karyawan</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="new_role" class="form-select form-select-sm" style="width: auto;">
                                <option value="karyawan" <?= $u['role'] == 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                                <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit" name="update_role" class="btn btn-sm btn-primary">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'footer.php'; ?>