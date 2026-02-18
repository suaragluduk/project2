<?php
require 'config.php';
require 'header.php';

$param_id = $_GET['id'] ?? 0;

// Ambil info parameter
$stmt = $pdo->prepare("SELECT cp.*, cg.group_name, cg.id as group_id FROM cost_parameters cp JOIN cost_groups cg ON cp.group_id = cg.id WHERE cp.id = ?");
$stmt->execute([$param_id]);
$param = $stmt->fetch();

if(!$param) {
    echo "<div class='alert alert-danger'>Parameter tidak ditemukan! <a href='groups.php'>Kembali</a></div>";
    require 'footer.php';
    exit;
}

// --- 1. LOGIC SAVE BULK (ADD BANYAK SEKALIGUS) ---
if(isset($_POST['save_bulk_fields'])){
    $labels = $_POST['labels'];
    $types  = $_POST['types'];
    $roles  = $_POST['roles'];
    
    try {
        $pdo->beginTransaction();
        
        // Loop setiap baris input
        for($i = 0; $i < count($labels); $i++){
            $label = trim($labels[$i]);
            $type  = $types[$i];
            $role  = $roles[$i];
            
            // Simpan hanya jika Label tidak kosong
            if(!empty($label)){
                $stmt = $pdo->prepare("INSERT INTO parameter_fields (parameter_id, field_label, field_type, field_role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$param_id, $label, $type, $role]);
            }
        }
        
        $pdo->commit();
        header("Location: manage_fields.php?id=$param_id&status=success");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

// --- 2. LOGIC UPDATE (EDIT SATUAN) ---
if(isset($_POST['save_edit_field'])){
    $label = $_POST['label'];
    $type  = $_POST['type'];
    $role  = $_POST['role'];
    $edit_id = $_POST['edit_id'];
    
    $stmt = $pdo->prepare("UPDATE parameter_fields SET field_label=?, field_type=?, field_role=? WHERE id=?");
    $stmt->execute([$label, $type, $role, $edit_id]);
    
    header("Location: manage_fields.php?id=$param_id&status=updated");
    exit;
}

// --- 3. LOGIC DELETE ---
if(isset($_GET['del_field'])){
    $stmt = $pdo->prepare("DELETE FROM parameter_fields WHERE id = ?");
    $stmt->execute([$_GET['del_field']]);
    header("Location: manage_fields.php?id=$param_id&status=deleted");
    exit;
}

// Ambil Data Edit (Jika ada)
$editData = null;
if(isset($_GET['edit_field'])){
    $stmt = $pdo->prepare("SELECT * FROM parameter_fields WHERE id = ?");
    $stmt->execute([$_GET['edit_field']]);
    $editData = $stmt->fetch();
}

// Ambil List Fields yang sudah ada
$fields = $pdo->prepare("SELECT * FROM parameter_fields WHERE parameter_id = ? ORDER BY id ASC");
$fields->execute([$param_id]);
$fieldList = $fields->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Level 3: Manage Values</h3>
        <small class="text-muted">Parameter: <strong><?= htmlspecialchars($param['parameter_name']) ?></strong> (Group: <?= htmlspecialchars($param['group_name']) ?>)</small>
    </div>
    <a href="group_detail.php?id=<?= $param['group_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php if(isset($_GET['status'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> Data berhasil disimpan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-success mb-4 shadow-sm">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <span>
            <?php if($editData): ?>
                <i class="fas fa-edit"></i> Edit Value Field
            <?php else: ?>
                <i class="fas fa-plus-circle"></i> Add New Values (Bulk)
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body bg-light">
        
        <?php if($editData): ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Nama Label</label>
                    <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($editData['field_label']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tipe Input</label>
                    <select name="type" class="form-select">
                        <option value="text" <?= $editData['field_type']=='text'?'selected':'' ?>>Teks (Huruf)</option>
                        <option value="number" <?= $editData['field_type']=='number'?'selected':'' ?>>Angka (Numeric)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Fungsi Rumus</label>
                    <select name="role" class="form-select">
                        <option value="general" <?= $editData['field_role']=='general'?'selected':'' ?>>Info Saja (No Calc)</option>
                        <option value="price" <?= $editData['field_role']=='price'?'selected':'' ?>>Nominal Utama (Harga)</option>
                        <option value="multiplier" <?= $editData['field_role']=='multiplier'?'selected':'' ?>>Pengali (Durasi/Qty)</option>
                        <option value="extra" <?= $editData['field_role']=='extra'?'selected':'' ?>>Extra (Bonus)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="save_edit_field" class="btn btn-warning w-100">Update</button>
                    <a href="manage_fields.php?id=<?= $param_id ?>" class="btn btn-secondary ms-1">Batal</a>
                </div>
            </form>

        <?php else: ?>
            <form method="POST" id="bulkForm">
                <div id="fields_container">
                    </div>

                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary" onclick="addEmptyRow()">
                        <i class="fas fa-plus"></i> Tambah Baris Kosong
                    </button>
                    <button type="submit" name="save_bulk_fields" class="btn btn-success px-4">
                        <i class="fas fa-save"></i> Simpan Semua Values
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<div class="card">
    <div class="card-header">List Values / Komponen Input yang Sudah Ada</div>
    <ul class="list-group list-group-flush">
        <?php foreach($fieldList as $f): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong><?= htmlspecialchars($f['field_label']) ?></strong>
                <br>
                <?php if($f['field_role'] == 'multiplier'): ?>
                    <span class="badge bg-danger">Pengali (x)</span>
                <?php elseif($f['field_role'] == 'price'): ?>
                    <span class="badge bg-success">Nominal Utama (Harga)</span>
                <?php elseif($f['field_role'] == 'extra'): ?>
                    <span class="badge bg-info text-dark">Extra/Bonus (+checkbox)</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Info Saja (Teks)</span>
                <?php endif; ?>
                <span class="badge bg-light text-dark border ms-1"><?= ucfirst($f['field_type']) ?></span>
            </div>
            <div>
                <a href="manage_fields.php?id=<?= $param_id ?>&edit_field=<?= $f['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                <a href="manage_fields.php?id=<?= $param_id ?>&del_field=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus?')"><i class="fas fa-times"></i></a>
            </div>
        </li>
        <?php endforeach; ?>
        
        <?php if(empty($fieldList)): ?>
            <li class="list-group-item text-center text-muted py-3">Belum ada values. Silakan simpan form di atas.</li>
        <?php endif; ?>
    </ul>
</div>

<?php if(!$editData): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cek apakah sudah ada field? Jika KOSONG, baru kita tampilkan 3 default.
    // Jika sudah ada field, kita tampilkan 1 baris kosong saja agar tidak dobel.
    <?php if(empty($fieldList)): ?>
        addDefaultRows();
    <?php else: ?>
        addEmptyRow(); 
    <?php endif; ?>
});

function addRow(label = '', type = 'text', role = 'general') {
    const container = document.getElementById('fields_container');
    const rowId = Date.now() + Math.random(); // Unique ID untuk delete row

    let html = `
    <div class="row g-2 mb-2 align-items-end" id="row-${rowId}">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Nama Label</label>
            <input type="text" name="labels[]" class="form-control" placeholder="Contoh: Harga Satuan" value="${label}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Tipe Input</label>
            <select name="types[]" class="form-select">
                <option value="text" ${type === 'text' ? 'selected' : ''}>Teks (Huruf)</option>
                <option value="number" ${type === 'number' ? 'selected' : ''}>Angka (Numeric)</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Fungsi Rumus</label>
            <select name="roles[]" class="form-select">
                <option value="general" ${role === 'general' ? 'selected' : ''}>Info Saja (No Calc)</option>
                <option value="price" ${role === 'price' ? 'selected' : ''}>Nominal Utama (Harga)</option>
                <option value="multiplier" ${role === 'multiplier' ? 'selected' : ''}>Pengali (Durasi/Qty)</option>
                <option value="extra" ${role === 'extra' ? 'selected' : ''}>Extra (Bonus)</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger w-100" onclick="removeRow('${rowId}')" title="Hapus Baris">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', html);
}

function removeRow(id) {
    document.getElementById(`row-${id}`).remove();
}

function addDefaultRows() {
    // 1. Deskripsi (Teks, Info Saja)
    addRow('Deskripsi', 'text', 'general');
    // 2. Harga (Angka, Nominal Utama)
    addRow('Harga', 'number', 'price');
    // 3. Durasi (Angka, Pengali)
    addRow('Durasi', 'number', 'multiplier');
}

function addEmptyRow() {
    addRow('', 'text', 'general');
}
</script>
<?php endif; ?>

<?php require 'footer.php'; ?>