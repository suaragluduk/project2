<?php
// header.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
// Ambil role dari session
$userRole = $_SESSION['role'] ?? 'karyawan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Project Cost Control SABS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">
        <i class="fas fa-building text-warning me-2"></i> Project Cost Control SABS
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="groups.php"><i class="fas fa-layer-group"></i> Cost Groups</a>
        </li>
      </ul>
      
      <ul class="navbar-nav ms-auto align-items-center">
        <?php if($userRole === 'admin'): ?>
            <li class="nav-item me-3">
                <a class="nav-link text-warning fw-bold border border-warning rounded px-3 py-1" href="manage_users.php">
                    <i class="fas fa-users-cog"></i> Manage Users
                </a>
            </li>
        <?php endif; ?>
        
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle fa-lg me-1"></i>
                Hi, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> (<?= ucfirst($userRole) ?>)
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li>
                    <a class="dropdown-item" href="change_password.php">
                        <i class="fas fa-key me-2 text-muted"></i> Ganti Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container pb-5">