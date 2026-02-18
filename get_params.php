<?php
require 'config.php';

if(isset($_GET['group_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM group_params WHERE group_id = ?");
    $stmt->execute([$_GET['group_id']]);
    $params = $stmt->fetchAll();
    
    // Kembalikan data dalam format JSON agar bisa dibaca Javascript
    header('Content-Type: application/json');
    echo json_encode($params);
}
?>