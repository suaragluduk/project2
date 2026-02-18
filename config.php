<?php
// config.php - Konfigurasi Database & Session

// --- FIX: Cek status session sebelum memulai ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
$host = 'localhost';
$dbname = 'db_cost_project';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// --- HELPER FUNCTIONS ---

// Fungsi format rupiah (Rp 1.000.000)
function formatRupiah($angka){
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi Cek Login
function isLoggedIn(){
    return isset($_SESSION['user_id']);
}

// Fungsi Redirect jika belum login
function checkLogin(){
    if(!isLoggedIn()){
        header("Location: index.php");
        exit;
    }
}
?>