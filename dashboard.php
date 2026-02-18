<?php
require 'config.php';
require 'header.php';

$userRole = $_SESSION['role'] ?? 'karyawan';

// --- LOGIC ADD PROJECT ---
if (isset($_POST['add_project'])) {
    try {
        $docPath = "";
        if (isset($_FILES['contract_doc']) && $_FILES['contract_doc']['error'] == 0) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES["contract_doc"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            if(move_uploaded_file($_FILES["contract_doc"]["tmp_name"], $targetFilePath)){
                $docPath = $fileName;
            }
        }

        $sql = "INSERT INTO projects (project_name, customer_name, location, duration, contract_number, pic_sabs, pic_customer, contract_doc) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['project_name'], $_POST['customer_name'], $_POST['location'], 
            $_POST['duration'], $_POST['contract_number'], $_POST['pic_sabs'], 
            $_POST['pic_customer'], $docPath
        ]);
        echo "<div class='alert alert-success'>Project berhasil ditambahkan!</div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- LOGIC DELETE PROJECT ---
if (isset($_GET['delete_id'])) {
    // Admin Only delete project? (Opsional, di sini kita biarkan semua bisa hapus project dulu)
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: dashboard.php");
}

// --- FILTER QUERY LOGIC ---
// Jika Admin: True (1=1). Jika Karyawan: cg.created_by_role = 'karyawan'
$roleFilter = ($userRole === 'admin') ? "1=1" : "cg.created_by_role = 'karyawan'";

// 1. STATISTIC: TOTAL PROJECTS
$stmt = $pdo->query("SELECT COUNT(*) as total_proj FROM projects");
$totalProjects = $stmt->fetch()['total_proj'];

// 2. STATISTIC: GRAND TOTAL COST (FILTERED)
// Kita harus JOIN sampai ke cost_groups untuk cek siapa pembuatnya
$sqlTotal = "SELECT SUM(pc.total_cost) as grand_total 
             FROM project_costs pc 
             JOIN cost_parameters cp ON pc.parameter_id = cp.id
             JOIN cost_groups cg ON cp.group_id = cg.id
             WHERE $roleFilter";

$stmt = $pdo->query($sqlTotal);
$grandTotal = $stmt->fetch()['grand_total'];

// 3. GET PROJECTS LIST (FILTERED TOTAL COST PER PROJECT)
// Subquery untuk menghitung total per project juga harus difilter
$sqlProjects = "SELECT p.*, 
                (
                    SELECT SUM(pc.total_cost) 
                    FROM project_costs pc 
                    JOIN cost_parameters cp ON pc.parameter_id = cp.id
                    JOIN cost_groups cg ON cp.group_id = cg.id
                    WHERE pc.project_id = p.id AND $roleFilter
                ) as total_biaya 
                FROM projects p 
                ORDER BY created_at DESC";

$projects = $pdo->query($sqlProjects)->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Projects</h5>
                <p class="card-text fs-3"><?= $totalProjects ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Cost Exposure (<?= ucfirst($userRole) ?> View)</h5>
                <p class="card-text fs-3"><?= formatRupiah($grandTotal ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Daftar Project</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
        <i class="fas fa-plus"></i> Add Project
    </button>
</div>

<table class="table table-striped table-hover shadow-sm">
    <thead class="table-dark">
        <tr>
            <th>Project Name</th>
            <th>Customer</th>
            <th>Duration</th>
            <th>PIC SABS</th>
            <th>Total Cost</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($projects as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['project_name']) ?></td>
            <td><?= htmlspecialchars($p['customer_name']) ?></td>
            <td><?= $p['duration'] ?> Days</td>
            <td><?= htmlspecialchars($p['pic_sabs']) ?></td>
            <td class="fw-bold text-danger"><?= formatRupiah($p['total_biaya'] ?? 0) ?></td>
            <td>
                <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i> View/Cost</a>
                <?php if($p['contract_doc']): ?>
                    <a href="uploads/<?= $p['contract_doc'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-file-pdf"></i> Doc</a>
                <?php endif; ?>
                <a href="dashboard.php?delete_id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus project ini?')"><i class="fas fa-minus"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="modal fade" id="addProjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" enctype="multipart/form-data">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-6 mb-3"><label>Nama Project</label><input type="text" name="project_name" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label>Customer Name</label><input type="text" name="customer_name" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label>Lokasi</label><input type="text" name="location" class="form-control"></div>
            <div class="col-md-6 mb-3"><label>Durasi (Hari)</label><input type="number" name="duration" class="form-control"></div>
            <div class="col-md-6 mb-3"><label>Nomor Kontrak</label><input type="text" name="contract_number" class="form-control"></div>
            <div class="col-md-6 mb-3"><label>Upload Dokumen Kontrak</label><input type="file" name="contract_doc" class="form-control"></div>
            <div class="col-md-6 mb-3"><label>PIC SABS</label><input type="text" name="pic_sabs" class="form-control"></div>
            <div class="col-md-6 mb-3"><label>PIC Customer</label><input type="text" name="pic_customer" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_project" class="btn btn-primary">Save Project</button>
      </div>
    </div>
    </form>
  </div>
</div>

<?php require 'footer.php'; ?>