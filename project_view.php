<?php
require 'config.php';
require 'header.php';

$id = $_GET['id'] ?? 0;
$userRole = $_SESSION['role'] ?? 'karyawan';

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) { echo "<div class='alert alert-danger'>Project not found</div>"; exit; }

// --- LOGIC SAVE ---
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
                    // Bersihkan titik jika angka
                    if(preg_match('/^[0-9\.]+$/', $value)){
                        $dynamic_data_clean[$key] = str_replace('.', '', $value);
                    } else {
                        $dynamic_data_clean[$key] = $value; 
                    }
                }
                
                $json_values = json_encode($dynamic_data_clean);

                $sql = "INSERT INTO project_costs (project_id, parameter_id, dynamic_values, total_cost) VALUES (?, ?, ?, ?)";
                $stmtCost = $pdo->prepare($sql);
                $stmtCost->execute([$id, $param_id, $json_values, $manual_total]);
            }
            $pdo->commit();
            echo "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Saved! <button class='btn-close' data-bs-dismiss='alert'></button></div>";
        } catch(Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Pilih minimal satu item.</div>";
    }
}

// --- LOGIC DELETE ---
if(isset($_GET['del_cost'])){
    // Validasi: User hanya boleh hapus cost yang dia punya akses lihat
    $stmt = $pdo->prepare("DELETE FROM project_costs WHERE id = ?");
    $stmt->execute([$_GET['del_cost']]);
    echo "<script>window.location='project_view.php?id=$id';</script>";
}

// --- FILTER LOGIC ---
$roleFilter = ($userRole === 'admin') ? "1=1" : "cg.created_by_role = 'karyawan'";

// Data List (FILTERED)
$sql = "SELECT pc.*, cp.parameter_name, cg.group_name, cg.created_by_role 
        FROM project_costs pc 
        JOIN cost_parameters cp ON pc.parameter_id = cp.id 
        JOIN cost_groups cg ON cp.group_id = cg.id 
        WHERE pc.project_id = ? AND $roleFilter
        ORDER BY pc.created_at DESC";
        
$costs = $pdo->prepare($sql);
$costs->execute([$id]);
$costList = $costs->fetchAll();

// Groups Dropdown (FILTERED)
if($userRole === 'admin') {
    $groups = $pdo->query("SELECT * FROM cost_groups ORDER BY group_name ASC")->fetchAll();
} else {
    $groups = $pdo->query("SELECT * FROM cost_groups WHERE created_by_role = 'karyawan' ORDER BY group_name ASC")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= htmlspecialchars($project['project_name']) ?></h3>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<hr>

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
            <button type="submit" name="save_bulk_costs" id="btn_save" class="btn btn-success w-100 mt-3 d-none">Simpan Semua</button>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr><th>Group & Parameter</th><th>Detail</th><th class="text-end">Total</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php $grandTotal = 0; foreach($costList as $c): $grandTotal += $c['total_cost']; $vals = json_decode($c['dynamic_values'], true); ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($c['parameter_name']) ?></strong><br>
                    <small><?= $c['group_name'] ?></small>
                    <?php if($userRole === 'admin'): ?>
                        <span class="badge bg-secondary ms-1" style="font-size: 0.6em"><?= $c['created_by_role'] ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <ul class="mb-0 small text-muted ps-3">
                    <?php if($vals) foreach($vals as $k=>$v): 
                        $display = is_numeric(str_replace('.','',$v)) ? number_format((float)str_replace('.','',$v), 0, ',', '.') : $v;
                        if($v!=='') echo "<li>$k: <strong>$display</strong></li>"; 
                    endforeach; ?>
                    </ul>
                </td>
                <td class="text-end fw-bold"><?= formatRupiah($c['total_cost']) ?></td>
                <td class="text-center"><a href="project_view.php?id=<?= $id ?>&del_cost=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">X</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light"><tr><td colspan="2" class="text-end fw-bold">TOTAL (Role: <?= ucfirst($userRole) ?>)</td><td class="text-end fw-bold"><?= formatRupiah($grandTotal) ?></td><td></td></tr></tfoot>
    </table>
</div>

<script>
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
        
        // Multiplier
        let multiplierTotal = 1;
        const multipliers = document.querySelectorAll(`.calc-multiplier[data-row-id="${paramId}"]`);
        multipliers.forEach(inp => {
            let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
            if(val > 0) multiplierTotal *= val;
        });

        // Price
        let priceTotal = 0;
        const prices = document.querySelectorAll(`.calc-price[data-row-id="${paramId}"]`);
        prices.forEach(inp => {
            let val = parseFloat(inp.value.replace(/\./g, '')) || 0;
            priceTotal += val;
        });

        // Extra
        let extraTotal = 0;
        const extras = document.querySelectorAll(`.calc-extra[data-row-id="${paramId}"]`);
        extras.forEach(inp => {
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
                    <th width="40"><i class="fas fa-check"></i></th>
                    <th width="20%">Parameter</th>
                    <th>Input Detail</th>
                    <th width="250">Total (IDR)</th> 
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
                                    <input type="text" name="${inputName}" disabled class="form-control ${isNumeric ? 'text-end' : ''} ${roleClass}" data-row-id="${p.id}" onkeyup="${keyupEvent}" placeholder="${isNumeric ? '0' : 'Text...'}">
                                </div>
                            </div>`;
                        } else {
                            html += `
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-0">${f.field_label}</label>
                                <input type="text" name="items[${p.id}][${f.field_label}]" class="form-control form-control-sm ${isNumeric ? 'text-end' : ''} ${roleClass}" data-row-id="${p.id}" onkeyup="${keyupEvent}" placeholder="${isNumeric ? '0' : 'Text...'}">
                            </div>`;
                        }
                    });
                }

                html += `</div></td>
                    <td>
                        <div class="input-group">
                            <span class="input-group-text small fw-bold">Rp</span>
                            <input type="text" id="total-cost-${p.id}" name="total_costs[${p.id}]" 
                                   class="form-control fw-bold text-end bg-light" 
                                   style="font-size: 1.1rem; min-width: 150px;" 
                                   onkeyup="formatRupiahInput(this)" placeholder="0">
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