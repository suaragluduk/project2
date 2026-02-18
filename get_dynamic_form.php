<?php
// Mulai output buffering untuk menangkap spasi/error yang tidak diinginkan
ob_start();

require 'config.php';

// Hapus semua output sebelumnya (termasuk spasi dari config.php jika ada)
ob_clean(); 

// Set header JSON
header('Content-Type: application/json');

// Matikan tampilan error agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(0);

$response = [];

try {
    $action = $_GET['action'] ?? '';

    if($action == 'get_full_group_structure') {
        
        $group_id = $_GET['group_id'] ?? 0;
        
        if(empty($group_id)){
            echo json_encode([]);
            exit;
        }
        
        // 1. Ambil Parameters
        $stmt = $pdo->prepare("SELECT * FROM cost_parameters WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $params = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Loop setiap parameter untuk ambil fields-nya
        foreach($params as &$p) {
            $stmtFields = $pdo->prepare("SELECT * FROM parameter_fields WHERE parameter_id = ?");
            $stmtFields->execute([$p['id']]);
            $p['fields'] = $stmtFields->fetchAll(PDO::FETCH_ASSOC);
        }

        $response = $params;
    } 
    else {
        $response = [];
    }

} catch (Exception $e) {
    // Jika error, kirim array kosong
    $response = [];
}

// Pastikan hanya JSON murni yang keluar
echo json_encode($response);
exit; // Stop script disini
?>