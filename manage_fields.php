<?php
require 'config.php';
require 'header.php';

$param_id = $_GET['id'] ?? 0;
$userName = $_SESSION['username'] ?? 'Sistem';

$stmt = $pdo->prepare("SELECT cp.*, cg.group_name, cg.id as group_id FROM cost_parameters cp JOIN cost_groups cg ON cp.group_id = cg.id WHERE cp.id = ?");
$stmt->execute([$param_id]);
$param = $stmt->fetch();

if(!$param) { echo "<div class='alert alert-danger'>Parameter tidak ditemukan!</div>"; require 'footer.php'; exit; }

// --- 1. LOGIC SAVE BULK ---
if(isset($_POST['save_bulk_fields'])){
    $labels = $_POST['labels'];
    $types  = $_POST['types'];
    $roles  = $_POST['roles'];
    try {
        $pdo->beginTransaction();
        for($i = 0; $i < count($labels); $i++){
            // FORMAT: Huruf awal besar
            $label = ucwords(strtolower(trim($labels[$i])));
            $type  = $types[$i];
            $role  = $roles[$i];
            
            if(!empty($label)){
                $stmt = $pdo->prepare("INSERT INTO parameter_fields (parameter_id, field_label, field_type, field_role, created_by_user) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$param_id, $label, $type, $role, $userName]);
            }
        }
        $pdo->commit();
        header("Location: manage_fields.php?id=$param_id&status=success"); exit;
    } catch (Exception $e) { $pdo->rollBack(); echo "<div class='alert alert-danger'>Gagal menyimpan.</div>"; }
}

// --- 2. LOGIC UPDATE EDIT ---
if(isset($_POST['save_edit_field'])){
    // FORMAT: Huruf awal besar
    $label = ucwords(strtolower(trim($_POST['label']))); 
    $type = $_POST['type']; 
    $role = $_POST['role']; 
    $edit_id = $_POST['edit_id'];
    
    $stmt = $pdo->prepare("UPDATE parameter_fields SET field_label=?, field_type=?, field_role=?, updated_by_user=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$label, $type, $role, $userName, $edit_id]);
    
    header("Location: manage_fields.php?id=$param_id&status=updated"); exit;
}

// --- 3. LOGIC DELETE ---
if(isset($_GET['del_field'])){
    $pdo->prepare("DELETE FROM parameter_fields WHERE id = ?")->execute([$_GET['del_field']]);
    header("Location: manage_fields.php?id=$param_id&status=deleted"); exit;
}

$editData = null;
if(isset($_GET['edit_field'])){
    $stmt = $pdo->prepare("SELECT * FROM parameter_fields WHERE id = ?");
    $stmt->execute([$_GET['edit_field']]);
    $editData = $stmt->fetch();
}

$fields = $pdo->prepare("SELECT * FROM parameter_fields WHERE parameter_id = ? ORDER BY id ASC");
$fields->execute([$param_id]);
$fieldList = $fields->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Level 3: Manage Values</h3>
        <small class="text-muted">Parameter: <strong><?= htmlspecialchars($param['parameter_name']) ?></strong></small>
    </div>
    <a href="group_detail.php?id=<?= $param['group_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php if(isset($_GET['status'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> Data berhasil diperbarui!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-success mb-4 shadow-sm">
    <div class="card-header bg-success text-white">
        <?php echo $editData ? '<i class="fas fa-edit"></i> Edit Value' : '<i class="fas fa-plus"></i> Add New Values'; ?>
    </div>
    <div class="card-body bg-light">
        <?php if($editData): ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Nama Label</label>
                    <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($editData['field_label']) ?>" required style="text-transform: capitalize;">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tipe Input</label>
                    <select name="type" class="form-select">
                        <option value="text" <?= $editData['field_type']=='text'?'selected':'' ?>>Teks</option>
                        <option value="number" <?= $editData['field_type']=='number'?'selected':'' ?>>Angka</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Fungsi Rumus</label>
                    <select name="role" class="form-select">
                        <option value="general" <?= $editData['field_role']=='general'?'selected':'' ?>>Info Saja</option>
                        <option value="price" <?= $editData['field_role']=='price'?'selected':'' ?>>Nominal Utama</option>
                        <option value="multiplier" <?= $editData['field_role']=='multiplier'?'selected':'' ?>>Pengali</option>
                        <option value="extra" <?= $editData['field_role']=='extra'?'selected':'' ?>>Extra</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="save_edit_field" class="btn btn-warning w-100">Update</button>
                    <a href="manage_fields.php?id=<?= $param_id ?>" class="btn btn-secondary ms-1">Batal</a>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <div id="fields_container"></div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary" onclick="addEmptyRow()"><i class="fas fa-plus"></i> Tambah Baris Kosong</button>
                    <button type="submit" name="save_bulk_fields" class="btn btn-success px-4"><i class="fas fa-save"></i> Simpan Semua</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">List Values / Komponen Input</div>
    <ul class="list-group list-group-flush">
        <?php foreach($fieldList as $f): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong><?= htmlspecialchars($f['field_label']) ?></strong> 
                <span class="badge bg-light text-dark border ms-1"><?= ucfirst($f['field_type']) ?></span>
                
                <?php if($f['field_role'] == 'multiplier'): ?>
                    <span class="badge bg-danger ms-1">Pengali (x)</span>
                <?php elseif($f['field_role'] == 'price'): ?>
                    <span class="badge bg-success ms-1">Nominal Utama</span>
                <?php elseif($f['field_role'] == 'extra'): ?>
                    <span class="badge bg-info text-dark ms-1">Extra/Bonus</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-1">Info Saja</span>
                <?php endif; ?>

                <br>
                <small class="text-success d-block mt-1">
                    <i class="fas fa-user-plus"></i> <?= htmlspecialchars($f['created_by_user'] ?? 'Sistem') ?> 
                    | <?= $f['created_at'] ? date('d M Y, H:i', strtotime($f['created_at'])) : '-' ?>
                </small>

                <?php if(!empty($f['updated_by_user'])): ?>
                <small class="text-warning fw-bold d-block">
                    <i class="fas fa-user-edit"></i> Terakhir diedit: <?= htmlspecialchars($f['updated_by_user']) ?> 
                    | <?= $f['updated_at'] ? date('d M Y, H:i', strtotime($f['updated_at'])) : '-' ?>
                </small>
                <?php endif; ?>
            </div>
            <div>
                <a href="manage_fields.php?id=<?= $param_id ?>&edit_field=<?= $f['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                <a href="manage_fields.php?id=<?= $param_id ?>&del_field=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus?')"><i class="fas fa-times"></i></a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if(!$editData): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if(empty($fieldList)): ?> addDefaultRows(); <?php else: ?> addEmptyRow(); <?php endif; ?>
});
function addRow(label = '', type = 'text', role = 'general') {
    const container = document.getElementById('fields_container');
    const rowId = Date.now() + Math.random();
    let html = `
    <div class="row g-2 mb-2 align-items-end" id="row-${rowId}">
        <div class="col-md-4">
            <input type="text" name="labels[]" class="form-control" placeholder="Nama Label" value="${label}" required style="text-transform: capitalize;">
        </div>
        <div class="col-md-3"><select name="types[]" class="form-select"><option value="text" ${type === 'text' ? 'selected' : ''}>Teks</option><option value="number" ${type === 'number' ? 'selected' : ''}>Angka</option></select></div>
        <div class="col-md-4"><select name="roles[]" class="form-select"><option value="general" ${role === 'general' ? 'selected' : ''}>Info Saja</option><option value="price" ${role === 'price' ? 'selected' : ''}>Nominal Utama</option><option value="multiplier" ${role === 'multiplier' ? 'selected' : ''}>Pengali</option><option value="extra" ${role === 'extra' ? 'selected' : ''}>Extra</option></select></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger w-100" onclick="removeRow('${rowId}')"><i class="fas fa-times"></i></button></div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}
function removeRow(id) { document.getElementById(`row-${id}`).remove(); }
function addDefaultRows() { addRow('Deskripsi', 'text', 'general'); addRow('Harga', 'number', 'price'); addRow('Durasi', 'number', 'multiplier'); }
function addEmptyRow() { addRow('', 'text', 'general'); }
</script>
<?php endif; ?>

<?php require 'footer.php'; ?>