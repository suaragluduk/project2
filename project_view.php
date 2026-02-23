<?php
require 'config.php';
require 'header.php';

$id = $_GET['id'] ?? 0;
$userRole = $_SESSION['role'] ?? 'karyawan';
$userName = $_SESSION['username'] ?? 'Sistem'; // Ambil username yang login

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) { echo "<div class='alert alert-danger'>Project not found</div>"; require 'footer.php'; exit; }

$success_msg = '';
$error_msg = '';

// --- LOGIC SAVE BULK (ADD NEW COST) ---
if (isset($_POST['save_bulk_costs'])) {
    if(isset($_POST['selected_params']) && is_array($_POST['selected_params'])){
        $pdo->beginTransaction();
        try {
            foreach($_POST['selected_params'] as $param_id => $val) {
                $raw_total = $_POST['total_costs'][$param_id] ?? 0;
                $manual_total = floatval(str_replace('.', '', $raw_total));
                
                $dynamic_data_raw = $_POST['items'][$param_id] ?? [];
                $dynamic_data_clean = [];
                
                foreach($dynamic_data_raw as $key => $value) {
                    if(preg_match('/^[0-9\.]+$/', $value)){
                        $dynamic_data_clean[$key] = str_replace('.', '', $value);
                    } else {
                        // Title Case untuk teks biasa
                        $dynamic_data_clean[$key] = ucwords(strtolower(trim($value))); 
                    }
                }
                
                $json_values = json_encode($dynamic_data_clean);

                // INSERT dengan mencatat created_by_user
                $sql = "INSERT INTO project_costs (project_id, parameter_id, dynamic_values, total_cost, created_by_user) VALUES (?, ?, ?, ?, ?)";
                $stmtCost = $pdo->prepare($sql);
                $stmtCost->execute([$id, $param_id, $json_values, $manual_total, $userName]);
            }
            $pdo->commit();
            $success_msg = "Data biaya berhasil ditambahkan!";
        } catch(Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Pilih minimal satu item untuk disimpan.";
    }
}

// --- LOGIC UPDATE (EDIT SATUAN DARI TABEL) ---
if (isset($_POST['update_cost'])) {
    $cost_id = $_POST['cost_id'];
    $raw_total = $_POST['edit_total_cost'] ?? 0;
    $manual_total = floatval(str_replace('.', '', $raw_total));
    
    $dynamic_data_raw = $_POST['edit_items'] ?? [];
    $dynamic_data_clean = [];
    
    foreach($dynamic_data_raw as $key => $value) {
        if(preg_match('/^[0-9\.]+$/', $value)){
            $dynamic_data_clean[$key] = str_replace('.', '', $value);
        } else {
            $dynamic_data_clean[$key] = ucwords(strtolower(trim($value))); 
        }
    }
    
    $json_values = json_encode($dynamic_data_clean);

    try {
        // UPDATE dengan mencatat updated_by_user dan updated_at
        $stmtUpdate = $pdo->prepare("UPDATE project_costs SET dynamic_values = ?, total_cost = ?, updated_by_user = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$json_values, $manual_total, $userName, $cost_id]);
        $success_msg = "Data biaya berhasil diupdate!";
    } catch(Exception $e) {
        $error_msg = "Gagal update: " . $e->getMessage();
    }
}

// --- LOGIC DELETE ---
if(isset($_GET['del_cost'])){
    $stmt = $pdo->prepare("DELETE FROM project_costs WHERE id = ?");
    $stmt->execute([$_GET['del_cost']]);
    header("Location: project_view.php?id=$id&status=deleted");
    exit;
}

if(isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $success_msg = "Data biaya berhasil dihapus!";
}

// --- FILTER LOGIC & AMBIL DATA ---
$roleFilter = ($userRole === 'admin') ? "1=1" : "cg.created_by_role = 'karyawan'";

// Select data biaya termasuk field audit
$sql = "SELECT pc.*, cp.parameter_name, cg.group_name, cg.created_by_role 
        FROM project_costs pc 
        JOIN cost_parameters cp ON pc.parameter_id = cp.id 
        JOIN cost_groups cg ON cp.group_id = cg.id 
        WHERE pc.project_id = ? AND $roleFilter
        ORDER BY pc.created_at DESC";
        
$costs = $pdo->prepare($sql);
$costs->execute([$id]);
$costList = $costs->fetchAll();

if($userRole === 'admin') {
    $groups = $pdo->query("SELECT * FROM cost_groups ORDER BY group_name ASC")->fetchAll();
} else {
    $groups = $pdo->query("SELECT * FROM cost_groups WHERE created_by_role = 'karyawan' ORDER BY group_name ASC")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= htmlspecialchars($project['project_name']) ?></h3>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
<hr>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $success_msg ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-5 shadow-sm border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calculator"></i> Add Cost (<?= ucfirst($userRole) ?> View)</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="fw-bold">Pilih Group Biaya:</label>
            <select id="group_select" class="form-select">
                <option value="">-- Pilih Group --</option>
                <?php foreach($groups as $g): ?>
                    <option value="<?= $g['id'] ?>">
                        <?= htmlspecialchars($g['group_name']) ?> 
                        <?= ($userRole=='admin') ? '('.$g['created_by_role'].')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <form method="POST">
            <div id="loading_indicator" class="text-center d-none py-3"><div class="spinner-border text-primary"></div></div>
            <div id="error_area"></div>
            <div id="dynamic_table_container"></div>
            <button type="submit" name="save_bulk_costs" id="btn_save" class="btn btn-success w-100 mt-3 d-none"><i class="fas fa-save"></i> Simpan Terpilih</button>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr><th>Group & Parameter</th><th>Detail Input</th><th class="text-end">Total Cost</th><th class="text-center" width="120">Action</th></tr>
        </thead>
        <tbody>
            <?php $grandTotal = 0; foreach($costList as $c): $grandTotal += $c['total_cost']; $vals = json_decode($c['dynamic_values'], true); ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($c['parameter_name']) ?></strong><br>
                    <small class="text-muted"><?= $c['group_name'] ?></small>

                    <div class="mt-2">
                        <small class="text-info fw-bold d-block" style="font-size: 0.75rem;">
                            <i class="fas fa-user-plus"></i> Diinput oleh: <?= htmlspecialchars($c['created_by_user'] ?? 'Sistem') ?> 
                            | <?= $c['created_at'] ? date('d M Y, H:i', strtotime($c['created_at'])) : '-' ?>
                        </small>
                        <?php if(!empty($c['updated_by_user'])): ?>
                        <small class="text-warning fw-bold d-block mt-1" style="font-size: 0.75rem;">
                            <i class="fas fa-user-edit"></i> Terakhir diedit: <?= htmlspecialchars($c['updated_by_user']) ?> 
                            | <?= $c['updated_at'] ? date('d M Y, H:i', strtotime($c['updated_at'])) : '-' ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <ul class="mb-0 small text-muted ps-3">
                    <?php if($vals) foreach($vals as $k=>$v): 
                        $display = is_numeric(str_replace('.','',$v)) ? number_format((float)str_replace('.','',$v), 0, ',', '.') : $v;
                        if($v!=='') echo "<li>$k: <strong class='text-dark'>$display</strong></li>"; 
                    endforeach; ?>
                    </ul>
                </td>
                <td class="text-end fw-bold text-danger"><?= formatRupiah($c['total_cost']) ?></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning text-dark me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['id'] ?>" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="project_view.php?id=<?= $id ?>&del_cost=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus?')" title="Hapus"><i class="fas fa-trash"></i></a>
                </td>
            </tr>

            <?php 
                // Ambil struktur fields untuk parameter ini
                $stmtF = $pdo->prepare("SELECT * FROM parameter_fields WHERE parameter_id = ? ORDER BY id ASC");
                $stmtF->execute([$c['parameter_id']]);
                $fields = $stmtF->fetchAll();
            ?>
            <div class="modal fade" id="editModal<?= $c['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <form method="POST">
                <div class="modal-content border-warning">
                  <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit: <?= htmlspecialchars($c['parameter_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body bg-light">
                    <input type="hidden" name="cost_id" value="<?= $c['id'] ?>">
                    <div class="row g-3">
                        <?php foreach($fields as $f): 
                            $val = $vals[$f['field_label']] ?? '';
                            $isNumeric = ($f['field_type'] == 'number');
                            $displayVal = ($isNumeric && $val !== '') ? number_format((float)str_replace('.','',$val), 0, ',', '.') : $val;
                            
                            $roleClass = '';
                            if($isNumeric) {
                                if($f['field_role'] === 'multiplier') $roleClass = 'edit-calc-multiplier border-danger';
                                else if($f['field_role'] === 'price') $roleClass = 'edit-calc-price border-success';
                                else if($f['field_role'] === 'extra') $roleClass = 'edit-calc-extra border-info';
                            }
                            $keyupEvent = $isNumeric ? "formatRupiahEdit(this, {$c['id']})" : "triggerCalcEdit({$c['id']})";
                            $inputId = "edit-input-{$c['id']}-" . md5($f['field_label']);
                        ?>
                            
                            <?php if($f['field_role'] === 'extra'): 
                                $isChecked = ($val !== '' && (float)str_replace('.','',$val) > 0) ? 'checked' : '';
                                $isDisabled = $isChecked ? '' : 'disabled';
                            ?>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-info mb-0"><?= $f['field_label'] ?></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="checkbox" id="check-<?= $inputId ?>" onchange="toggleExtraEdit(this, '<?= $inputId ?>', <?= $c['id'] ?>)" <?= $isChecked ?>>
                                        </div>
                                        <input type="text" id="<?= $inputId ?>" name="edit_items[<?= $f['field_label'] ?>]" <?= $isDisabled ?> class="form-control <?= $isNumeric?'text-end':'' ?> <?= $roleClass ?>" data-edit-row-id="<?= $c['id'] ?>" onkeyup="<?= $keyupEvent ?>" value="<?= $displayVal ?>" style="text-transform: capitalize;">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-0 fw-bold"><?= $f['field_label'] ?></label>
                                    <input type="text" name="edit_items[<?= $f['field_label'] ?>]" class="form-control form-control-sm <?= $isNumeric?'text-end':'' ?> <?= $roleClass ?>" data-edit-row-id="<?= $c['id'] ?>" onkeyup="<?= $keyupEvent ?>" value="<?= htmlspecialchars($displayVal) ?>" style="text-transform: capitalize;">
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    <div class="row mt-3">
                        <div class="col-md-6 offset-md-6">
                            <label class="form-label fw-bold text-end w-100">Total Cost (Rp)</label>
                            <input type="text" id="edit-total-cost-<?= $c['id'] ?>" name="edit_total_cost" class="form-control fw-bold text-end bg-white border-primary fs-5" onkeyup="formatRupiahEdit(this, <?= $c['id'] ?>)" value="<?= number_format($c['total_cost'], 0, ',', '.') ?>">
                        </div>
                    </div>

                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_cost" class="btn btn-warning fw-bold"><i class="fas fa-save"></i> Update Perubahan</button>
                  </div>
                </div>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light"><tr><td colspan="2" class="text-end fw-bold fs-5">TOTAL PROJECT COST</td><td class="text-end fw-bold fs-4 text-primary"><?= formatRupiah($grandTotal) ?></td><td></td></tr></tfoot>
    </table>
</div>

<script>
// ==========================================
// SCRIPT UNTUK MODAL EDIT
// ==========================================
window.formatRupiahEdit = function(element, rowId) {
    let val = element.value.replace(/[^0-9]/g, '');
    element.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    calculateEditRow(rowId);
};

window.triggerCalcEdit = function(rowId) {
    calculateEditRow(rowId);
}

window.calculateEditRow = function(rowId) {
    const totalField = document.getElementById(`edit-total-cost-${rowId}`);
    
    let multiplierTotal = 1;
    let hasMultiplier = false;
    document.querySelectorAll(`.edit-calc-multiplier[data-edit-row-id="${rowId}"]`).forEach(inp => {
        let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
        if(val > 0) { multiplierTotal *= val; hasMultiplier = true; }
    });
    if(!hasMultiplier) multiplierTotal = 1;

    let priceTotal = 0;
    document.querySelectorAll(`.edit-calc-price[data-edit-row-id="${rowId}"]`).forEach(inp => {
        let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
        priceTotal += val;
    });

    let extraTotal = 0;
    document.querySelectorAll(`.edit-calc-extra[data-edit-row-id="${rowId}"]`).forEach(inp => {
        if(!inp.disabled) { 
            let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
            extraTotal += val;
        }
    });

    let grandTotal = multiplierTotal * (priceTotal + extraTotal);
    totalField.value = grandTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

window.toggleExtraEdit = function(checkbox, inputId, rowId) {
    let inputField = document.getElementById(inputId);
    if(inputField) {
        inputField.disabled = !checkbox.checked;
        if(!checkbox.checked) { inputField.value = ''; } 
        else { inputField.focus(); }
        calculateEditRow(rowId);
    }
};


// ==========================================
// SCRIPT UNTUK ADD NEW (MAIN FORM)
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('group_select');
    const container = document.getElementById('dynamic_table_container');
    const btnSave = document.getElementById('btn_save');
    const loading = document.getElementById('loading_indicator');
    const errorArea = document.getElementById('error_area');

    window.formatRupiahInput = function(element) {
        let val = element.value.replace(/[^0-9]/g, '');
        element.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        calculateRow(element.getAttribute('data-row-id'));
    };
    
    window.triggerCalc = function(element) {
        calculateRow(element.getAttribute('data-row-id'));
    }

    window.calculateRow = function(paramId) {
        const checkbox = document.getElementById(`check-${paramId}`);
        const totalField = document.getElementById(`total-cost-${paramId}`);
        
        let multiplierTotal = 1;
        document.querySelectorAll(`.calc-multiplier[data-row-id="${paramId}"]`).forEach(inp => {
            let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
            if(val > 0) multiplierTotal *= val;
        });

        let priceTotal = 0;
        document.querySelectorAll(`.calc-price[data-row-id="${paramId}"]`).forEach(inp => {
            let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
            priceTotal += val;
        });

        let extraTotal = 0;
        document.querySelectorAll(`.calc-extra[data-row-id="${paramId}"]`).forEach(inp => {
            let extraCheck = document.getElementById(`enable-${inp.name}`); 
            if(extraCheck && extraCheck.checked) {
                let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
                extraTotal += val;
            }
        });

        let grandTotal = multiplierTotal * (priceTotal + extraTotal);
        totalField.value = grandTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        
        if(grandTotal > 0 && checkbox) checkbox.checked = true;
    };

    window.toggleExtra = function(checkbox, inputName, rowId) {
        let inputField = document.getElementsByName(inputName)[0];
        if(inputField) {
            inputField.disabled = !checkbox.checked;
            if(!checkbox.checked) { inputField.value = ''; } else { inputField.focus(); }
            calculateRow(rowId);
        }
    };

    groupSelect.addEventListener('change', function() {
        let groupId = this.value;
        container.innerHTML = ''; errorArea.innerHTML = ''; btnSave.classList.add('d-none');
        if(!groupId) return;

        loading.classList.remove('d-none');
        fetch('get_dynamic_form.php?action=get_full_group_structure&group_id=' + groupId)
        .then(res => res.text()).then(text => JSON.parse(text.trim().replace(/^\uFEFF/, '')))
        .then(params => {
            loading.classList.add('d-none');
            if(params.length === 0) { container.innerHTML = '<div class="alert alert-warning text-center">Group Kosong</div>'; return; }

            let html = `<div class="table-responsive">
            <table class="table table-hover border align-middle">
                <thead class="table-secondary">
                <tr>
                    <th width="40" class="text-center"><i class="fas fa-check"></i></th>
                    <th width="20%">Parameter</th>
                    <th>Input Detail</th>
                    <th width="200">Total (Rp)</th> 
                </tr>
                </thead>
                <tbody>`;

            params.forEach(p => {
                html += `<tr>
                    <td class="text-center bg-light"><input type="checkbox" id="check-${p.id}" name="selected_params[${p.id}]" class="form-check-input cost-check" style="transform: scale(1.3);"></td>
                    <td class="fw-bold">${p.parameter_name}</td>
                    <td><div class="row g-2">`;

                if(p.fields) {
                    p.fields.forEach(f => {
                        let isNumeric = (f.field_type === 'number');
                        let keyupEvent = isNumeric ? 'formatRupiahInput(this)' : 'triggerCalc(this)';
                        
                        let roleClass = '';
                        if(isNumeric) {
                            if(f.field_role === 'multiplier') roleClass = 'calc-multiplier border-danger';
                            else if(f.field_role === 'price') roleClass = 'calc-price border-success';
                            else if(f.field_role === 'extra') roleClass = 'calc-extra border-info';
                        }

                        if(f.field_role === 'extra') {
                            let inputName = `items[${p.id}][${f.field_label}]`;
                            html += `
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-info mb-0">${f.field_label}</label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-text"><input class="form-check-input mt-0" type="checkbox" id="enable-${inputName}" onchange="toggleExtra(this, '${inputName}', ${p.id})"></div>
                                    <input type="text" name="${inputName}" disabled class="form-control ${isNumeric ? 'text-end' : ''} ${roleClass}" data-row-id="${p.id}" onkeyup="${keyupEvent}" placeholder="${isNumeric ? '0' : 'Text...'}" style="text-transform: capitalize;">
                                </div>
                            </div>`;
                        } else {
                            html += `
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-0">${f.field_label}</label>
                                <input type="text" name="items[${p.id}][${f.field_label}]" class="form-control form-control-sm ${isNumeric ? 'text-end' : ''} ${roleClass}" data-row-id="${p.id}" onkeyup="${keyupEvent}" placeholder="${isNumeric ? '0' : 'Text...'}" style="text-transform: capitalize;">
                            </div>`;
                        }
                    });
                }

                html += `</div></td>
                    <td>
                        <div class="input-group">
                            <span class="input-group-text small fw-bold">Rp</span>
                            <input type="text" id="total-cost-${p.id}" name="total_costs[${p.id}]" class="form-control fw-bold text-end bg-light" style="font-size: 1rem; min-width: 130px;" onkeyup="formatRupiahInput(this)" placeholder="0">
                        </div>
                    </td></tr>`;
            });
            html += `</tbody></table></div>`;
            container.innerHTML = html;
            btnSave.classList.remove('d-none');
        }).catch(err => { loading.classList.add('d-none'); errorArea.innerHTML = `<div class="alert alert-danger">${err.message}</div>`; });
    });
});
</script>

<?php require 'footer.php'; ?>