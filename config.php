<?php
session_start();
$host = 'sql203.infinityfree.com';
$dbname = 'if0_41736764_dhl';
$username = 'if0_41736764';
$password = 'Ninonino2005';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

function getUserByUsername($conn, $username) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get absensi by user and date
function getAbsensi($conn, $user_id, $tanggal) {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?");
    $stmt->bind_param("is", $user_id, $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to insert absensi
function insertAbsensi($conn, $user_id, $tanggal, $waktu_masuk, $status) {
    $stmt = $conn->prepare("INSERT INTO absensi (user_id, tanggal, waktu_masuk, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $tanggal, $waktu_masuk, $status);
    return $stmt->execute();
}

// Function to update absensi keluar
function updateAbsensiKeluar($conn, $user_id, $tanggal, $waktu_keluar) {
    $stmt = $conn->prepare("UPDATE absensi SET waktu_keluar = ? WHERE user_id = ? AND tanggal = ?");
    $stmt->bind_param("sis", $waktu_keluar, $user_id, $tanggal);
    return $stmt->execute();
}

// Function to insert izin
function insertIzin($conn, $user_id, $jenis, $tanggal_mulai, $tanggal_selesai, $alasan) {
    $stmt = $conn->prepare("INSERT INTO izin (user_id, jenis, tanggal_mulai, tanggal_selesai, alasan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $jenis, $tanggal_mulai, $tanggal_selesai, $alasan);
    return $stmt->execute();
}

// Function to update status izin
function updateIzinStatus($conn, $izin_id, $status) {
    $stmt = $conn->prepare("UPDATE izin SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $izin_id);
    return $stmt->execute();
}

// Function to insert shift
function insertShift($conn, $supervisor_id, $staff_id, $tgl_shift, $jam_mulai, $jam_selesai, $tipe) {
    $stmt = $conn->prepare("INSERT INTO shift (supervisor_id, staff_id, tgl_shift, jam_mulai, jam_selesai, tipe) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $supervisor_id, $staff_id, $tgl_shift, $jam_mulai, $jam_selesai, $tipe);
    return $stmt->execute();
}

// Function to get histori absensi
function getHistoriAbsensi($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE user_id = ? ORDER BY tanggal DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

date_default_timezone_set('Asia/Jakarta');

function cekLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit();
    }
}

function getHeaderStyles() {
    return "
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
      * { margin: 0; padding: 0; box-sizing: border-box; }
      :root {
        --dhl-red: #E60000; --dhl-red-dark: #CC0000; --dhl-yellow: #FFCC00;
        --green: #22C55E; --green-light: #DCFCE7; --blue: #3B82F6; --blue-light: #EFF6FF;
        --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-400: #9CA3AF;
        --gray-500: #6B7280; --gray-700: #374151; --gray-900: #111827; --white: #FFFFFF;
        --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        --radius: 12px; --radius-sm: 8px; --font: 'Plus Jakarta Sans', sans-serif;
      }
      body { font-family: var(--font); background: #F0F0F0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
      .phone { width: 390px; min-height: 844px; background: var(--gray-50); border-radius: 40px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.25); display: flex; flex-direction: column; position: relative; }
      
      /* Header Asli */
      .app-header { background: var(--dhl-red); padding: 16px 20px 12px; flex-shrink: 0; }
      .header-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
      .header-brand { display: flex; align-items: center; gap: 10px; }
      .header-icon { width: 36px; height: 36px; background: var(--dhl-yellow); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
      .header-brand-name { color: white; font-size: 15px; font-weight: 700; }
      .header-brand-sub { color: rgba(255,255,255,0.75); font-size: 12px; }
      .btn-logout { width: 36px; height: 36px; background: rgba(255,255,255,0.15); border: none; border-radius: 10px; color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; }
      .header-user { background: rgba(255,255,255,0.15); border-radius: var(--radius-sm); padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
      .user-avatar { width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; }
      .user-name { color: white; font-size: 14px; font-weight: 600; }
      .user-role { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-top: 2px; }
      .role-manajer { background: #E9D5FF; color: #6B21A8; }
      
      /* Content Asli */
      .content { flex: 1; padding: 16px; background: var(--gray-100); }
      .dash-card { background: white; border-radius: var(--radius); padding: 16px; margin-bottom: 12px; box-shadow: var(--shadow); }
      .dash-title { font-size: 20px; font-weight: 700; color: var(--gray-900); margin-bottom: 2px; }
      .dash-sub { font-size: 13px; color: var(--gray-500); }
      .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 12px; }
      .stat-card { background: white; border-radius: var(--radius); padding: 14px 10px; text-align: center; box-shadow: var(--shadow); }
      @media (max-width: 420px) {
        .stat-grid { grid-template-columns: 1fr; }
      }
      .stat-icon { font-size: 22px; margin-bottom: 6px; }
      .stat-label { font-size: 11px; color: var(--gray-500); margin-bottom: 4px; }
      .stat-val { font-size: 24px; font-weight: 700; color: var(--gray-900); }
      .stat-val.green { color: var(--green); }
      .stat-val.red { color: var(--dhl-red); }
      .section-title { font-size: 16px; font-weight: 700; color: var(--gray-900); margin-bottom: 10px; }
      .empty-state { text-align: center; padding: 32px 16px; color: var(--gray-400); font-size: 13px; }
      .empty-state span { display: block; font-size: 32px; margin-bottom: 8px; }
      .app-footer { text-align: center; padding: 12px; font-size: 11px; color: var(--gray-400); background: var(--gray-100); flex-shrink: 0; }
    </style>";
}
?>
