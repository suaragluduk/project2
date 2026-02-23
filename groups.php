<?php
require 'config.php';
require 'header.php';

$userRole = $_SESSION['role'] ?? 'karyawan';
$userName = $_SESSION['username'] ?? 'Sistem';

// --- 1. LOGIC ADD GROUP ---
if(isset($_POST['add_group'])){
    // FORMAT: Huruf awal besar
    $group_name = ucwords(strtolower(trim($_POST['group_name'])));
    
    if(!empty($group_name)){
        $stmt = $pdo->prepare("INSERT INTO cost_groups (group_name, created_by_role, created_by_user) VALUES (?, ?, ?)");
        $stmt->execute([$group_name, $userRole, $userName]);
        echo "<script>window.location='groups.php';</script>";
    }
}

// --- 2. LOGIC DELETE GROUP ---
if(isset($_GET['del'])){
    $id = $_GET['del'];
    try {
        $pdo->beginTransaction();

        if($userRole !== 'admin') {
            $stmtCheck = $pdo->prepare("SELECT created_by_role FROM cost_groups WHERE id = ?");
            $stmtCheck->execute([$id]);
            $groupOwner = $stmtCheck->fetchColumn();
            if($groupOwner === 'admin') throw new Exception("Anda tidak berhak menghapus Group milik Admin.");
        }

        $stmt = $pdo->prepare("SELECT id FROM cost_parameters WHERE group_id = ?");
        $stmt->execute([$id]);
        $paramIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if(!empty($paramIds)){
            $placeholders = implode(',', array_fill(0, count($paramIds), '?'));
            $pdo->prepare("DELETE FROM project_costs WHERE parameter_id IN ($placeholders)")->execute($paramIds);
            $pdo->prepare("DELETE FROM parameter_fields WHERE parameter_id IN ($placeholders)")->execute($paramIds);
            $pdo->prepare("DELETE FROM cost_parameters WHERE group_id = ?")->execute([$id]);
        }

        $pdo->prepare("DELETE FROM cost_groups WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo "<script>window.location='groups.php';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

// --- 3. LOGIC VIEW LIST ---
if($userRole === 'admin') {
    $groups = $pdo->query("SELECT * FROM cost_groups ORDER BY id DESC")->fetchAll();
} else {
    $groups = $pdo->query("SELECT * FROM cost_groups WHERE created_by_role = 'karyawan' ORDER BY id DESC")->fetchAll();
}
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fas fa-layer-group"></i> Manage Cost Groups</h3>
        <p class="text-muted">Role Anda: <strong><?= strtoupper($userRole) ?></strong>.</p>
        
        <form method="POST" class="d-flex gap-2 mb-4 p-3 bg-light border rounded shadow-sm">
            <input type="text" name="group_name" class="form-control" placeholder="Nama Group Baru..." required style="max-width: 400px; text-transform: capitalize;">
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
                        <div class="mt-2 ms-4">
                            <small class="text-muted d-block mb-1">Berisi <?= $count ?> Parameter.</small>
                            <small class="text-primary fw-bold" style="font-size: 0.8em;">
                                <i class="fas fa-user-edit"></i> Dibuat oleh: <?= htmlspecialchars($g['created_by_user'] ?? 'Sistem') ?> 
                                | <i class="fas fa-clock"></i> <?= $g['created_at'] ? date('d M Y, H:i', strtotime($g['created_at'])) : '-' ?>
                            </small>
                            <span class="badge bg-secondary ms-2" style="font-size: 0.7em;">Role: <?= $g['created_by_role'] ?></span>
                        </div>
                    </div>
                    
                    <a href="groups.php?del=<?= $g['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus Group ini beserta isinya?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            <?php endforeach; ?>
            
            <?php if(count($groups) == 0): ?>
                <div class="alert alert-info text-center mt-3">Tidak ada Group yang tersedia.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>