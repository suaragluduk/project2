<?php
require 'config.php';
require 'header.php';

$userRole = $_SESSION['role'] ?? 'karyawan';

// --- LOGIC ADD PROJECT ---
if (isset($_POST['add_project'])) {
    try {
        $project_name = ucwords(strtolower(trim($_POST['project_name'])));
        $customer_name = ucwords(strtolower(trim($_POST['customer_name'])));
        $location = ucwords(strtolower(trim($_POST['location'])));
        $contract_number = strtoupper(trim($_POST['contract_number']));
        $pic_sabs = ucwords(strtolower(trim($_POST['pic_sabs'])));
        $pic_customer = ucwords(strtolower(trim($_POST['pic_customer'])));
        
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
            $project_name, $customer_name, $location, 
            $_POST['duration'], $contract_number, $pic_sabs, 
            $pic_customer, $docPath
        ]);
        echo "<div class='alert alert-success alert-dismissible fade show'>Project berhasil ditambahkan!<button class='btn-close' data-bs-dismiss='alert'></button></div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- LOGIC UPDATE PROJECT (EDIT PROJECT) ---
if (isset($_POST['update_project'])) {
    try {
        $project_id = $_POST['project_id'];
        $project_name = ucwords(strtolower(trim($_POST['project_name'])));
        $customer_name = ucwords(strtolower(trim($_POST['customer_name'])));
        $location = ucwords(strtolower(trim($_POST['location'])));
        $contract_number = strtoupper(trim($_POST['contract_number']));
        $pic_sabs = ucwords(strtolower(trim($_POST['pic_sabs'])));
        $pic_customer = ucwords(strtolower(trim($_POST['pic_customer'])));
        $old_doc = $_POST['old_doc'];
        
        $docPath = $old_doc; // Default pakai file lama
        
        // Jika ada file baru diupload
        if (isset($_FILES['contract_doc']) && $_FILES['contract_doc']['error'] == 0) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES["contract_doc"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            if(move_uploaded_file($_FILES["contract_doc"]["tmp_name"], $targetFilePath)){
                $docPath = $fileName; // Gunakan file baru
                // Hapus file lama untuk hemat ruang
                if (!empty($old_doc) && file_exists($targetDir . $old_doc)) {
                    unlink($targetDir . $old_doc);
                }
            }
        }

        $sql = "UPDATE projects SET project_name=?, customer_name=?, location=?, duration=?, contract_number=?, pic_sabs=?, pic_customer=?, contract_doc=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $project_name, $customer_name, $location, $_POST['duration'], 
            $contract_number, $pic_sabs, $pic_customer, $docPath, $project_id
        ]);
        echo "<div class='alert alert-success alert-dismissible fade show'>Detail Project berhasil diupdate!<button class='btn-close' data-bs-dismiss='alert'></button></div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- LOGIC SOFT DELETE PROJECT ---
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: dashboard.php");
    exit;
}

// --- LOGIC RESTORE PROJECT ---
if (isset($_GET['restore_id']) && $userRole === 'admin') {
    $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 0 WHERE id = ?");
    $stmt->execute([$_GET['restore_id']]);
    header("Location: dashboard.php");
    exit;
}

// --- FILTER QUERY LOGIC UTAMA ---
$roleFilter = ($userRole === 'admin') ? "1=1" : "cg.created_by_role = 'karyawan'";

// 1. STATISTIC
$stmt = $pdo->query("SELECT COUNT(*) as total_proj FROM projects WHERE is_deleted = 0");
$totalProjects = $stmt->fetch()['total_proj'];

$sqlTotal = "SELECT SUM(pc.total_cost) as grand_total 
             FROM project_costs pc 
             JOIN cost_parameters cp ON pc.parameter_id = cp.id
             JOIN cost_groups cg ON cp.group_id = cg.id
             JOIN projects p ON pc.project_id = p.id
             WHERE p.is_deleted = 0 AND $roleFilter";
$stmt = $pdo->query($sqlTotal);
$grandTotal = $stmt->fetch()['grand_total'];

// 3. GET PROJECTS LIST
$sqlProjects = "SELECT p.*, 
                (
                    SELECT SUM(pc.total_cost) 
                    FROM project_costs pc 
                    JOIN cost_parameters cp ON pc.parameter_id = cp.id
                    JOIN cost_groups cg ON cp.group_id = cg.id
                    WHERE pc.project_id = p.id AND $roleFilter
                ) as total_biaya 
                FROM projects p 
                WHERE p.is_deleted = 0
                ORDER BY p.created_at DESC";
$projects = $pdo->query($sqlProjects)->fetchAll();

// 4. GET DELETED PROJECTS
$deletedProjects = [];
if ($userRole === 'admin') {
    $deletedProjects = $pdo->query("SELECT * FROM projects WHERE is_deleted = 1 ORDER BY created_at DESC")->fetchAll();
}

// ==========================================
// --- LOGIC CHART KHUSUS ADMIN ---
// ==========================================
$groupCosts = []; $dailyCosts = []; $weeklyCosts = []; $monthlyCosts = [];
$allProjectsList = [];
$selectedChartProject = $_GET['chart_project_id'] ?? '';

if ($userRole === 'admin') {
    $allProjectsList = $pdo->query("SELECT id, project_name FROM projects WHERE is_deleted = 0 ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $projectFilterSql = "";
    $projectFilterParams = [];
    if (!empty($selectedChartProject)) {
        $projectFilterSql = " AND pc.project_id = ? ";
        $projectFilterParams[] = $selectedChartProject;
    }

    $sqlGroup = "SELECT cg.group_name, SUM(pc.total_cost) as total 
                 FROM project_costs pc 
                 JOIN cost_parameters cp ON pc.parameter_id = cp.id 
                 JOIN cost_groups cg ON cp.group_id = cg.id 
                 JOIN projects p ON pc.project_id = p.id
                 WHERE p.is_deleted = 0 $projectFilterSql
                 GROUP BY cg.id ORDER BY total DESC";
    $stmtGroup = $pdo->prepare($sqlGroup);
    $stmtGroup->execute($projectFilterParams);
    $groupCosts = $stmtGroup->fetchAll(PDO::FETCH_ASSOC);

    $sqlDaily = "SELECT DATE(pc.created_at) as tgl, SUM(pc.total_cost) as total 
                 FROM project_costs pc 
                 JOIN projects p ON pc.project_id = p.id
                 WHERE p.is_deleted = 0 AND pc.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $projectFilterSql
                 GROUP BY DATE(pc.created_at) ORDER BY tgl ASC";
    $stmtDaily = $pdo->prepare($sqlDaily);
    $stmtDaily->execute($projectFilterParams);
    $dailyCosts = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

    $sqlWeekly = "SELECT CONCAT(YEAR(pc.created_at), '-W', WEEK(pc.created_at)) as pekan, SUM(pc.total_cost) as total 
                  FROM project_costs pc 
                  JOIN projects p ON pc.project_id = p.id
                  WHERE p.is_deleted = 0 AND pc.created_at >= DATE_SUB(CURDATE(), INTERVAL 5 WEEK) $projectFilterSql
                  GROUP BY YEARWEEK(pc.created_at) ORDER BY YEARWEEK(pc.created_at) ASC";
    $stmtWeekly = $pdo->prepare($sqlWeekly);
    $stmtWeekly->execute($projectFilterParams);
    $weeklyCosts = $stmtWeekly->fetchAll(PDO::FETCH_ASSOC);

    $sqlMonthly = "SELECT DATE_FORMAT(pc.created_at, '%Y-%m') as bulan, SUM(pc.total_cost) as total 
                   FROM project_costs pc 
                   JOIN projects p ON pc.project_id = p.id
                   WHERE p.is_deleted = 0 AND pc.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $projectFilterSql
                   GROUP BY DATE_FORMAT(pc.created_at, '%Y-%m') ORDER BY bulan ASC";
    $stmtMonthly = $pdo->prepare($sqlMonthly);
    $stmtMonthly->execute($projectFilterParams);
    $monthlyCosts = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);
}

$groupLabels = json_encode(array_column($groupCosts, 'group_name'));
$groupData   = json_encode(array_column($groupCosts, 'total'));
$dailyLabels = json_encode(array_column($dailyCosts, 'tgl'));
$dailyData   = json_encode(array_column($dailyCosts, 'total'));
$weeklyLabels = json_encode(array_column($weeklyCosts, 'pekan'));
$weeklyData   = json_encode(array_column($weeklyCosts, 'total'));
$monthlyLabels = json_encode(array_column($monthlyCosts, 'bulan'));
$monthlyData   = json_encode(array_column($monthlyCosts, 'total'));
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-white bg-primary mb-3 shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-project-diagram me-2"></i>Total Projects (Aktif)</h5>
                <p class="card-text fs-3 fw-bold"><?= $totalProjects ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-success mb-3 shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-wallet me-2"></i>Total Cost Exposure (<?= ucfirst($userRole) ?> View)</h5>
                <p class="card-text fs-3 fw-bold"><?= formatRupiah($grandTotal ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>

<?php if($userRole === 'admin'): ?>
<div class="card shadow-sm mb-4 border-secondary">
    <div class="card-header bg-secondary text-white fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-line me-2"></i> Cost Analytics Dashboard</span>
        <form method="GET" class="d-flex mb-0" id="chartFilterForm">
            <select name="chart_project_id" class="form-select form-select-sm me-2" style="width: 250px;" onchange="document.getElementById('chartFilterForm').submit();">
                <option value="">-- Semua Project Aktif --</option>
                <?php foreach($allProjectsList as $prj): ?>
                    <option value="<?= $prj['id'] ?>" <?= ($selectedChartProject == $prj['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prj['project_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body bg-light">
        <div class="row">
            <div class="col-md-5 mb-3">
                <div class="card shadow-sm border-info h-100">
                    <div class="card-header bg-info text-white fw-bold"><i class="fas fa-chart-pie me-2"></i> Cost per Group Biaya</div>
                    <div class="card-body">
                        <?php if(empty($groupCosts)): ?><div class="text-muted text-center mt-5">Belum ada data biaya.</div>
                        <?php else: ?><div style="position: relative; height: 320px; width: 100%;"><canvas id="groupChart"></canvas></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-7 mb-3">
                <div class="card shadow-sm border-warning h-100">
                    <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-bar me-2"></i> Trend Cost Over Time</span>
                        <select id="timeFilter" class="form-select form-select-sm" style="width: auto; font-weight: normal;">
                            <option value="daily">Harian (30 Hari Terakhir)</option>
                            <option value="weekly">Mingguan (5 Minggu Terakhir)</option>
                            <option value="monthly">Bulanan (6 Bulan Terakhir)</option>
                        </select>
                    </div>
                    <div class="card-body"><div style="position: relative; height: 320px; width: 100%;"><canvas id="timeChart"></canvas></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="fas fa-list text-primary me-2"></i>Daftar Project</h3>
    <div>
        <?php if($userRole === 'admin'): ?>
            <button type="button" class="btn btn-outline-danger shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#recycleBinModal">
                <i class="fas fa-trash-restore"></i> Recycle Bin
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
            <i class="fas fa-plus"></i> Add Project
        </button>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover shadow-sm align-middle border">
        <thead class="table-dark">
            <tr>
                <th>Project Name</th>
                <th>Customer</th>
                <th>Duration</th>
                <th>PIC SABS</th>
                <th>Total Cost</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($projects as $p): ?>
            <tr>
                <td class="fw-bold">
                    <a href="#" class="text-primary text-decoration-none" data-bs-toggle="modal" data-bs-target="#editProjectModal<?= $p['id'] ?>" title="Klik untuk Edit Detail">
                        <i class="fas fa-edit me-1"></i> <?= htmlspecialchars($p['project_name']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($p['customer_name']) ?></td>
                <td><?= $p['duration'] ?> Days</td>
                <td><?= htmlspecialchars($p['pic_sabs']) ?></td>
                <td class="fw-bold text-danger"><?= formatRupiah($p['total_biaya'] ?? 0) ?></td>
                <td class="text-center">
                    <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i> View/Cost</a>
                    <a href="dashboard.php?delete_id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin pindahkan project ini ke Recycle Bin?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>

            <div class="modal fade" id="editProjectModal<?= $p['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <form method="POST" enctype="multipart/form-data">
                <div class="modal-content border-info">
                  <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detail & Edit Project: <?= htmlspecialchars($p['project_name']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body bg-light">
                    <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="old_doc" value="<?= $p['contract_doc'] ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6"><label class="fw-bold small">Nama Project</label><input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($p['project_name']) ?>" required style="text-transform: capitalize;"></div>
                        <div class="col-md-6"><label class="fw-bold small">Customer Name</label><input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($p['customer_name']) ?>" required style="text-transform: capitalize;"></div>
                        <div class="col-md-6"><label class="fw-bold small">Lokasi</label><input type="text" name="location" class="form-control" value="<?= htmlspecialchars($p['location']) ?>" style="text-transform: capitalize;"></div>
                        <div class="col-md-6"><label class="fw-bold small">Durasi (Hari)</label><input type="number" name="duration" class="form-control" value="<?= htmlspecialchars($p['duration']) ?>"></div>
                        <div class="col-md-6"><label class="fw-bold small">Nomor Kontrak</label><input type="text" name="contract_number" class="form-control" value="<?= htmlspecialchars($p['contract_number']) ?>" style="text-transform: uppercase;"></div>
                        <div class="col-md-6"><label class="fw-bold small">PIC SABS</label><input type="text" name="pic_sabs" class="form-control" value="<?= htmlspecialchars($p['pic_sabs']) ?>" style="text-transform: capitalize;"></div>
                        <div class="col-md-6"><label class="fw-bold small">PIC Customer</label><input type="text" name="pic_customer" class="form-control" value="<?= htmlspecialchars($p['pic_customer']) ?>" style="text-transform: capitalize;"></div>
                        
                        <div class="col-md-12 mt-3 p-3 bg-white border rounded">
                            <label class="fw-bold small text-primary d-block mb-2"><i class="fas fa-file-contract"></i> Dokumen Kontrak</label>
                            <?php if($p['contract_doc']): ?>
                                <div class="mb-3">
                                    <a href="uploads/<?= $p['contract_doc'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> Lihat / Download Dokumen Saat Ini</a>
                                </div>
                            <?php else: ?>
                                <div class="mb-3 text-danger small fst-italic">Belum ada dokumen yang diupload untuk project ini.</div>
                            <?php endif; ?>
                            <label class="small text-muted">Upload file baru untuk mengganti dokumen lama (.pdf, .jpg)</label>
                            <input type="file" name="contract_doc" class="form-control form-control-sm">
                        </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_project" class="btn btn-info text-white fw-bold"><i class="fas fa-save"></i> Simpan Perubahan</button>
                  </div>
                </div>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($projects)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data project.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="addProjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" enctype="multipart/form-data">
    <div class="modal-content border-primary">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-folder-plus"></i> Add New Project</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <div class="row g-3">
            <div class="col-md-6"><label class="fw-bold small">Nama Project</label><input type="text" name="project_name" class="form-control" required style="text-transform: capitalize;"></div>
            <div class="col-md-6"><label class="fw-bold small">Customer Name</label><input type="text" name="customer_name" class="form-control" required style="text-transform: capitalize;"></div>
            <div class="col-md-6"><label class="fw-bold small">Lokasi</label><input type="text" name="location" class="form-control" style="text-transform: capitalize;"></div>
            <div class="col-md-6"><label class="fw-bold small">Durasi (Hari)</label><input type="number" name="duration" class="form-control"></div>
            <div class="col-md-6"><label class="fw-bold small">Nomor Kontrak</label><input type="text" name="contract_number" class="form-control" style="text-transform: uppercase;"></div>
            <div class="col-md-6"><label class="fw-bold small">Upload Dokumen Kontrak (.pdf, .jpg)</label><input type="file" name="contract_doc" class="form-control"></div>
            <div class="col-md-6"><label class="fw-bold small">PIC SABS</label><input type="text" name="pic_sabs" class="form-control" style="text-transform: capitalize;"></div>
            <div class="col-md-6"><label class="fw-bold small">PIC Customer</label><input type="text" name="pic_customer" class="form-control" style="text-transform: capitalize;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="add_project" class="btn btn-primary"><i class="fas fa-save"></i> Save Project</button>
      </div>
    </div>
    </form>
  </div>
</div>

<?php if($userRole === 'admin'): ?>
<div class="modal fade" id="recycleBinModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Recycle Bin (Project Terhapus)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th class="ps-3">Project Name</th><th>Customer</th><th>Status</th><th class="text-center pe-3">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($deletedProjects as $dp): ?>
                <tr>
                    <td class="ps-3 text-decoration-line-through text-muted"><?= htmlspecialchars($dp['project_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($dp['customer_name']) ?></td>
                    <td class="text-muted"><small>*(Soft Deleted)*</small></td>
                    <td class="text-center pe-3"><a href="dashboard.php?restore_id=<?= $dp['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Kembalikan project ini?')"><i class="fas fa-undo"></i> Restore</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($deletedProjects)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Recycle bin kosong.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if($userRole === 'admin'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
Chart.register(ChartDataLabels);
document.addEventListener('DOMContentLoaded', function() {
    const formatRp = (value) => {
        if (value >= 1000000000) return 'Rp ' + (value / 1000000000).toFixed(1) + ' M';
        if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
        if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
        return 'Rp ' + value;
    };
    const ctxGroup = document.getElementById('groupChart');
    if(ctxGroup) {
        new Chart(ctxGroup.getContext('2d'), {
            type: 'doughnut',
            data: { labels: <?= $groupLabels ?>, datasets: [{ data: <?= $groupData ?>, backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14'], borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' }, datalabels: { color: '#fff', font: { weight: 'bold', size: 11 }, formatter: function(value, context) { return formatRp(value); }, textStrokeColor: '#000', textStrokeWidth: 2, align: 'center' } } }
        });
    }
    const timeDataSets = {
        daily: { labels: <?= $dailyLabels ?>, data: <?= $dailyData ?> },
        weekly: { labels: <?= $weeklyLabels ?>, data: <?= $weeklyData ?> },
        monthly: { labels: <?= $monthlyLabels ?>, data: <?= $monthlyData ?> }
    };
    const ctxTime = document.getElementById('timeChart');
    if(ctxTime) {
        let timeChart = new Chart(ctxTime.getContext('2d'), {
            type: 'bar',
            data: { labels: timeDataSets.daily.labels, datasets: [{ label: 'Total Cost', data: timeDataSets.daily.data, backgroundColor: 'rgba(255, 193, 7, 0.8)', borderColor: '#ffc107', borderWidth: 1, borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return formatRp(value); } } } }, plugins: { legend: { display: false }, datalabels: { display: false } } }
        });
        document.getElementById('timeFilter').addEventListener('change', function() {
            const selectedVal = this.value;
            timeChart.data.labels = timeDataSets[selectedVal].labels;
            timeChart.data.datasets[0].data = timeDataSets[selectedVal].data;
            timeChart.update();
        });
    }
});
</script>
<?php endif; ?>

<?php require 'footer.php'; ?>