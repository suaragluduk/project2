<?php
require 'config.php';
require 'header.php';

$group_id = $_GET['id'] ?? 0;
$userName = $_SESSION['username'] ?? 'Sistem';
$error = '';   
$success = ''; 

$stmt = $pdo->prepare("SELECT * FROM cost_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if(!$group) { echo "<div class='alert alert-danger'>Group tidak ditemukan!</div>"; require 'footer.php'; exit; }

// --- 1. LOGIC SIMPAN (ADD / UPDATE) ---
if(isset($_POST['save_param'])){
    // FORMAT: Huruf awal besar
    $param_name = ucwords(strtolower(trim($_POST['param_name'])));
    $edit_id    = $_POST['edit_id'] ?? 0; 
    
    if(empty($param_name)) { 
        $error = "Nama parameter tidak boleh kosong."; 
    } else {
        $check = $pdo->prepare("SELECT id FROM cost_parameters WHERE group_id = ? AND parameter_name = ? AND id != ?");
        $check->execute([$group_id, $param_name, $edit_id]);
        
        if($check->rowCount() > 0){
            $error = "Gagal! Nama parameter sudah ada.";
        } else {
            if(!empty($edit_id)){
                $stmt = $pdo->prepare("UPDATE cost_parameters SET parameter_name = ?, updated_by_user = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$param_name, $userName, $edit_id]);
                $success = "Parameter berhasil di-rename!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO cost_parameters (group_id, parameter_name, created_by_user) VALUES (?, ?, ?)");
                $stmt->execute([$group_id, $param_name, $userName]);
                $success = "Parameter baru berhasil ditambahkan!";
            }
        }
    }
}

// --- 2. LOGIC DELETE ---
if(isset($_GET['del_param'])){
    $pdo->prepare("DELETE FROM cost_parameters WHERE id = ?")->execute([$_GET['del_param']]);
    header("Location: group_detail.php?id=$group_id&status=deleted"); exit;
}

// --- 3. LOGIC DUPLICATE ---
if(isset($_GET['dup_param'])){
    try {
        $pdo->beginTransaction();
        $src_id = $_GET['dup_param'];
        $stmtSrc = $pdo->prepare("SELECT * FROM cost_parameters WHERE id = ?");
        $stmtSrc->execute([$src_id]);
        $srcParam = $stmtSrc->fetch();

        if($srcParam) {
            $newName = $srcParam['parameter_name'] . " (Copy)";
            $stmtNew = $pdo->prepare("INSERT INTO cost_parameters (group_id, parameter_name, created_by_user) VALUES (?, ?, ?)");
            $stmtNew->execute([$group_id, $newName, $userName]);
            $newParamId = $pdo->lastInsertId();

            $stmtFields = $pdo->prepare("SELECT * FROM parameter_fields WHERE parameter_id = ?");
            $stmtFields->execute([$src_id]);
            $srcFields = $stmtFields->fetchAll();

            $sqlInsertField = "INSERT INTO parameter_fields (parameter_id, field_label, field_type, field_role, created_by_user) VALUES (?, ?, ?, ?, ?)";
            $stmtInsertField = $pdo->prepare($sqlInsertField);

            foreach($srcFields as $f) {
                $stmtInsertField->execute([$newParamId, $f['field_label'], $f['field_type'], $f['field_role'], $userName]);
            }

            $pdo->commit();
            header("Location: group_detail.php?id=$group_id&status=dup_success"); exit;
        }
    } catch (Exception $e) { $pdo->rollBack(); $error = "Gagal duplicate: " . $e->getMessage(); }
}

if(isset($_GET['status'])){
    if($_GET['status'] == 'dup_success') $success = "Duplikasi sukses! Silakan rename parameter hasil copy.";
    if($_GET['status'] == 'deleted') $success = "Parameter berhasil dihapus.";
}

$editData = null;
if(isset($_GET['edit_param'])){
    $stmt = $pdo->prepare("SELECT * FROM cost_parameters WHERE id = ?");
    $stmt->execute([$_GET['edit_param']]);
    $editData = $stmt->fetch();
}

$params = $pdo->prepare("SELECT * FROM cost_parameters WHERE group_id = ? ORDER BY id ASC");
$params->execute([$group_id]);
$paramList = $params->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="fas fa-folder-open text-warning"></i> <?= htmlspecialchars($group['group_name']) ?></h3>
    <a href="groups.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

<div class="card mb-4 border-primary shadow-sm">
    <div class="card-header bg-primary text-white">Level 2: Manage Parameters</div>
    <div class="card-body">
        <form method="POST" class="d-flex gap-2">
            <input type="hidden" name="edit_id" value="<?= $editData['id'] ?? '' ?>">
            <input type="text" name="param_name" class="form-control" placeholder="Nama Parameter" value="<?= htmlspecialchars($editData['parameter_name'] ?? '') ?>" required style="text-transform: capitalize;">
            <button type="submit" name="save_param" class="btn <?= $editData ? 'btn-warning' : 'btn-success' ?>">
                <i class="fas <?= $editData ? 'fa-save' : 'fa-plus' ?>"></i> <?= $editData ? 'Update' : 'Add' ?>
            </button>
            <?php if($editData): ?>
                <a href="group_detail.php?id=<?= $group_id ?>" class="btn btn-secondary">Batal</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle shadow-sm">
        <thead class="table-light">
            <tr><th>Nama Parameter / Item</th><th class="text-center" width="150">Action</th></tr>
        </thead>
        <tbody>
            <?php foreach($paramList as $p): ?>
            <tr class="<?= ($editData && $editData['id'] == $p['id']) ? 'table-warning' : '' ?>">
                <td>
                    <a href="manage_fields.php?id=<?= $p['id'] ?>" class="fw-bold text-decoration-none text-primary fs-5">
                        <i class="fas fa-cube me-2"></i> <?= htmlspecialchars($p['parameter_name']) ?>
                    </a>
                    <div class="ms-4 mt-1">
                        <small class="text-info fw-bold d-block">
                            <i class="fas fa-user-plus"></i> Dibuat oleh: <?= htmlspecialchars($p['created_by_user'] ?? 'Sistem') ?> 
                            | <?= $p['created_at'] ? date('d M Y, H:i', strtotime($p['created_at'])) : '-' ?>
                        </small>
                        <?php if(!empty($p['updated_by_user'])): ?>
                        <small class="text-warning fw-bold d-block mt-1">
                            <i class="fas fa-user-edit"></i> Terakhir diedit oleh: <?= htmlspecialchars($p['updated_by_user']) ?> 
                            | <?= $p['updated_at'] ? date('d M Y, H:i', strtotime($p['updated_at'])) : '-' ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center">
                    <a href="group_detail.php?id=<?= $group_id ?>&dup_param=<?= $p['id'] ?>" class="btn btn-sm btn-info text-white" onclick="return confirm('Duplicate?')"><i class="fas fa-copy"></i></a>
                    <a href="group_detail.php?id=<?= $group_id ?>&edit_param=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-pencil-alt"></i></a>
                    <a href="group_detail.php?id=<?= $group_id ?>&del_param=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require 'footer.php'; ?>