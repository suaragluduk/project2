<?php
require 'config.php';
require 'header.php';

// Ambil Role User dari Session
$userRole = $_SESSION['role'] ?? 'karyawan';

// --- 1. LOGIC ADD GROUP ---
if(isset($_POST['add_group'])){
    $group_name = trim($_POST['group_name']);
    if(!empty($group_name)){
        // SIMPAN ROLE PEMBUAT SAAT INSERT
        $stmt = $pdo->prepare("INSERT INTO cost_groups (group_name, created_by_role) VALUES (?, ?)");
        $stmt->execute([$group_name, $userRole]);
        echo "<script>window.location='groups.php';</script>";
    }
}

// --- 2. LOGIC DELETE GROUP ---
if(isset($_GET['del'])){
    $id = $_GET['del'];
    try {
        $pdo->beginTransaction();

        // Cek kepemilikan sebelum hapus (Security)
        if($userRole !== 'admin') {
            $stmtCheck = $pdo->prepare("SELECT created_by_role FROM cost_groups WHERE id = ?");
            $stmtCheck->execute([$id]);
            $groupOwner = $stmtCheck->fetchColumn();
            
            if($groupOwner === 'admin') {
                throw new Exception("Anda tidak berhak menghapus Group milik Admin.");
            }
        }

        // 1. Ambil ID Parameter
        $stmt = $pdo->prepare("SELECT id FROM cost_parameters WHERE group_id = ?");
        $stmt->execute([$id]);
        $paramIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if(!empty($paramIds)){
            $placeholders = implode(',', array_fill(0, count($paramIds), '?'));

            // 2. Hapus Transaksi
            $stmt = $pdo->prepare("DELETE FROM project_costs WHERE parameter_id IN ($placeholders)");
            $stmt->execute($paramIds);

            // 3. Hapus Fields
            $stmt = $pdo->prepare("DELETE FROM parameter_fields WHERE parameter_id IN ($placeholders)");
            $stmt->execute($paramIds);

            // 4. Hapus Parameter
            $stmt = $pdo->prepare("DELETE FROM cost_parameters WHERE group_id = ?");
            $stmt->execute([$id]);
        }

        // 5. Hapus Group
        $stmt = $pdo->prepare("DELETE FROM cost_groups WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        echo "<script>window.location='groups.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

// --- 3. LOGIC VIEW LIST (FILTERED BY ROLE) ---
if($userRole === 'admin') {
    // Admin lihat SEMUA
    $groups = $pdo->query("SELECT * FROM cost_groups ORDER BY id DESC")->fetchAll();
} else {
    // Karyawan HANYA lihat buatan Karyawan
    $groups = $pdo->query("SELECT * FROM cost_groups WHERE created_by_role = 'karyawan' ORDER BY id DESC")->fetchAll();
}
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fas fa-layer-group"></i> Manage Cost Groups</h3>
        <p class="text-muted">
            Role Anda: <strong><?= strtoupper($userRole) ?></strong>. 
            <?= $userRole === 'admin' ? '(Melihat Semua Data)' : '(Hanya melihat data Karyawan)' ?>
        </p>
        
        <form method="POST" class="d-flex gap-2 mb-4 p-3 bg-light border rounded shadow-sm">
            <input type="text" name="group_name" class="form-control" placeholder="Nama Group Baru..." required style="max-width: 400px;">
            <button type="submit" name="add_group" class="btn btn-primary"><i class="fas fa-plus"></i> Add Group</button>
        </form>

        <div class="list-group shadow-sm">
            <?php foreach($groups as $g): ?>
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <div>
                        <a href="group_detail.php?id=<?= $g['id'] ?>" class="fw-bold text-decoration-none fs-5 text-dark">
                            <i class="fas fa-folder me-2 text-warning"></i> <?= htmlspecialchars($g['group_name']) ?>
                        </a>
                        
                        <?php 
                            $stmtC = $pdo->prepare("SELECT count(*) FROM cost_parameters WHERE group_id = ?");
                            $stmtC->execute([$g['id']]);
                            $count = $stmtC->fetchColumn();
                        ?>
                        <small class="d-block text-muted mt-1 ms-4">
                            Berisi <?= $count ?> Parameter. 
                            <span class="badge bg-secondary ms-2"><?= $g['created_by_role'] ?></span>
                        </small>
                    </div>
                    
                    <a href="groups.php?del=<?= $g['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus Group ini beserta isinya?')">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </div>
            <?php endforeach; ?>
            
            <?php if(count($groups) == 0): ?>
                <div class="alert alert-info text-center mt-3">Tidak ada Group yang tersedia untuk role Anda.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>