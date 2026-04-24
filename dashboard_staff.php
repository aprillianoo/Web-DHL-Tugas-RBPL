<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
cekLogin();
$user = $_SESSION['user'];
$username = $user['username'];
$tgl = date('Y-m-d');

// Debug: cek koneksi dan user
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
if (!$user) {
    die("User session not found");
}

// Handle AJAX untuk notifikasi
if (isset($_GET['ajax']) && $_GET['ajax'] == 'notif') {
    // Ambil notifikasi terbaru
    $notifikasi = [];
    $stmt = $conn->prepare("SELECT 'shift' as tipe, CONCAT('Shift hari ini: ', jam_mulai, ' - ', jam_selesai, ' (', tipe, ')') as pesan, tgl_shift as tanggal FROM shift WHERE staff_id = ? AND tgl_shift = ?");
    $stmt->bind_param("is", $user['id'], $tgl);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifikasi[] = $row;
        }
    }
    $stmt = $conn->prepare("SELECT 'izin' as tipe, CONCAT('Izin ', jenis, ' telah ', LOWER(status), ' untuk ', DATE_FORMAT(tanggal_mulai, '%d/%m/%Y')) as pesan, CURDATE() as tanggal FROM izin WHERE user_id = ? AND status != 'Menunggu'");
    $stmt->bind_param("i", $user['id']);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifikasi[] = $row;
        }
    }
    
    if (!empty($notifikasi)) {
        echo '<div class="section-title">Notifikasi</div>';
        foreach($notifikasi as $notif) {
            echo '<div class="dash-card" style="border-left: 4px solid #3B82F6; margin-bottom: 8px;">
                    <div style="font-size: 13px;">' . $notif['pesan'] . '</div>
                    <div style="font-size: 11px; color: #888; margin-top: 5px;">' . date('d M Y', strtotime($notif['tanggal'])) . '</div>
                  </div>';
        }
    }
    exit;
}

// --- LOGIKA ABSENSI ---
if (isset($_POST['absen_masuk'])) {
    $waktu_masuk = date('H:i:s');
    insertAbsensi($conn, $user['id'], $tgl, $waktu_masuk, 'Hadir');
    $pesan_absen = "Absensi masuk berhasil pada " . date('H:i');
}
if (isset($_POST['absen_keluar'])) {
    $waktu_keluar = date('H:i:s');
    updateAbsensiKeluar($conn, $user['id'], $tgl, $waktu_keluar);
    $pesan_absen = "Absensi keluar berhasil pada " . date('H:i');
}

// --- LOGIKA IZIN ---
if (isset($_POST['kirim_izin'])) {
    insertIzin($conn, $user['id'], $_POST['jenis'], $_POST['mulai'], $_POST['selesai'], $_POST['alasan']);
    $pesan_izin = "Pengajuan izin berhasil dikirim";
}

$absen = getAbsensi($conn, $user['id'], $tgl);

// Ambil shift hari ini dan besok
$result = $conn->query("SELECT * FROM shift WHERE staff_id = {$user['id']} AND tgl_shift IN ('$tgl', '" . date('Y-m-d', strtotime('+1 day')) . "') ORDER BY tgl_shift ASC");
$shifts_staff = $result->fetch_all(MYSQLI_ASSOC);

// Ambil notifikasi: shift hari ini, izin yang sudah diputuskan
$notifikasi = [];
$result = $conn->query("SELECT 'shift' as tipe, CONCAT('Shift hari ini: ', jam_mulai, ' - ', jam_selesai, ' (', tipe, ')') as pesan, tgl_shift as tanggal FROM shift WHERE staff_id = {$user['id']} AND tgl_shift = '$tgl'");
while ($row = $result->fetch_assoc()) {
    $notifikasi[] = $row;
}
$result = $conn->query("SELECT 'izin' as tipe, CONCAT('Izin ', jenis, ' telah ', LOWER(status), ' untuk ', DATE_FORMAT(tanggal_mulai, '%d/%m/%Y')) as pesan, CURDATE() as tanggal FROM izin WHERE user_id = {$user['id']} AND status != 'Menunggu'");
while ($row = $result->fetch_assoc()) {
    $notifikasi[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Staff - DHL</title>
    <?php echo getHeaderStyles(); ?>
    <script>
        function showTab(tabName) {
            const tabs = ['absensi', 'jadwal', 'histori', 'izin', 'notif'];
            tabs.forEach(t => {
                document.getElementById('st-' + t).style.display = (t === tabName) ? 'block' : 'none';
                document.getElementById('tab-' + t).classList.toggle('active', t === tabName);
            });
        }

        // Polling untuk notifikasi setiap 30 detik
        setInterval(function() {
            fetch('dashboard_staff.php?ajax=notif')
                .then(response => response.text())
                .then(data => {
                    if (data.trim() !== '') {
                        // Update tab notif jika ada notifikasi baru
                        document.getElementById('st-notif').innerHTML = data;
                        // Tambah badge atau alert jika di tab lain
                        if (!document.getElementById('tab-notif').classList.contains('active')) {
                            alert('Notifikasi baru!');
                        }
                    }
                });
        }, 30000);
    </script>
</head>
<body>
    <div class="phone">
        <div class="app-header">
            <div class="header-top">
                <div class="header-brand">
                    <div class="header-icon">🏭</div>
                    <div class="header-brand-text">
                        <div class="header-brand-name">DHL Warehouse</div>
                        <div class="header-brand-sub">Absensi & Shift</div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">↪</a>
            </div>
            <div class="header-user">
                <div class="user-avatar">👤</div>
                <div>
                    <div class="user-name"><?php echo $user['nama']; ?></div>
                    <span class="user-role role-staff">Staff</span>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="dash-card">
                <div class="dash-title">Dashboard Staff</div>
                <div class="dash-sub">Selamat datang, <?php echo $user['nama']; ?></div>
            </div>

            <div class="tabs" style="display: flex; gap: 5px; margin-bottom: 12px; background: white; padding: 5px; border-radius: 12px;">
                <button id="tab-absensi" class="tab-btn active" onclick="showTab('absensi')" style="flex:1; padding: 10px; border:none; border-radius:8px; font-size:12px;">⏰ Absensi</button>
                <button id="tab-jadwal" class="tab-btn" onclick="showTab('jadwal')" style="flex:1; padding: 10px; border:none; border-radius:8px; font-size:12px;">📅 Jadwal</button>
                <button id="tab-histori" class="tab-btn" onclick="showTab('histori')" style="flex:1; padding: 10px; border:none; border-radius:8px; font-size:12px;">📊 Histori</button>
                <button id="tab-izin" class="tab-btn" onclick="showTab('izin')" style="flex:1; padding: 10px; border:none; border-radius:8px; font-size:12px;">📄 Izin</button>
                <button id="tab-notif" class="tab-btn" onclick="showTab('notif')" style="flex:1; padding: 10px; border:none; border-radius:8px; font-size:12px;">🔔 Notif</button>
            </div>

            <div id="st-absensi">
                <div class="dash-card" style="background: #EFF6FF;">
                    <div class="section-title">Shift Hari Ini</div>
                    <?php
                    $shift_hari_ini = array_filter($shifts_staff, function($s) use ($tgl) { return $s['tgl_shift'] == $tgl; });
                    if (!empty($shift_hari_ini)):
                        $shift = reset($shift_hari_ini);
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div><b><?php echo $shift['jam_mulai'] . ' - ' . $shift['jam_selesai']; ?></b></div>
                        <span class="badge" style="background: <?php echo ($shift['tipe'] == 'Pagi') ? '#FEF3C7' : '#E0E7FF'; ?>; color: <?php echo ($shift['tipe'] == 'Pagi') ? '#92400E' : '#3730A3'; ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px;"><?php echo $shift['tipe']; ?></span>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; color: #888;">Tidak ada shift hari ini</div>
                    <?php endif; ?>
                </div>
                
                <div class="dash-card">
                    <div class="section-title">Absensi Hari Ini</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="background: #DCFCE7; padding: 10px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 10px; color: #555;">Masuk</div>
                            <div style="font-size: 18px; font-weight: bold; color: #166534;"><?php echo $absen ? date('H:i', strtotime($absen['waktu_masuk'])) : '-'; ?></div>
                        </div>
                        <div style="background: #DBEAFE; padding: 10px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 10px; color: #555;">Keluar</div>
                            <div style="font-size: 18px; font-weight: bold; color: #1E40AF;"><?php echo $absen && $absen['waktu_keluar'] ? date('H:i', strtotime($absen['waktu_keluar'])) : '-'; ?></div>
                        </div>
                    </div>

                    <form method="POST">
                        <?php if (isset($pesan_absen)): ?>
                            <div style="background: #DCFCE7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 10px; font-size: 12px; text-align: center;">
                                ✅ <?php echo $pesan_absen; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$absen): ?>
                            <button type="submit" name="absen_masuk" class="btn-absensi-masuk" style="width:100%; padding:15px; background:#22C55E; color:white; border:none; border-radius:12px; font-weight:bold;">→ Absensi Masuk</button>
                        <?php elseif (!$absen['waktu_keluar']): ?>
                            <button type="submit" name="absen_keluar" class="btn-absensi-keluar" style="width:100%; padding:15px; background:#3B82F6; color:white; border:none; border-radius:12px; font-weight:bold;">← Absensi Keluar</button>
                        <?php else: ?>
                            <div style="text-align: center; color: #166534; font-weight: bold; padding: 10px;">✅ Absensi Selesai</div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div id="st-jadwal" style="display: none;">
                <div class="dash-card">
                    <div class="section-title">Jadwal Shift</div>
                    <?php if (empty($shifts_staff)): ?>
                        <div style="text-align: center; color: #888; padding: 20px;">Belum ada jadwal shift</div>
                    <?php else: ?>
                        <?php foreach ($shifts_staff as $shift): ?>
                        <div style="background: <?php echo ($shift['tipe'] == 'Pagi') ? '#EFF6FF' : '#FEF3C7'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="font-weight: bold; margin-bottom: 5px;"><?php echo date('l, d M Y', strtotime($shift['tgl_shift'])); ?> - <?php echo ($shift['tgl_shift'] == $tgl) ? 'Hari Ini' : 'Besok'; ?></div>
                            <div>Shift: <?php echo $shift['jam_mulai'] . ' - ' . $shift['jam_selesai']; ?> (<?php echo $shift['tipe']; ?>)</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="st-histori" style="display: none;">
                <div class="dash-card">
                    <div class="section-title">Histori Absensi</div>
                    <?php
                    $histori_absensi = getHistoriAbsensi($conn, $user['id']);
                    if (empty($histori_absensi)) {
                        echo '<div style="text-align: center; color: #888; padding: 20px;">Belum ada data absensi</div>';
                    } else {
                        foreach ($histori_absensi as $absen_hari) {
                            echo '<div style="background: #F9FAFB; padding: 10px; border-radius: 8px; margin-bottom: 8px;">';
                            echo '<div style="font-weight: bold;">' . date('d M Y', strtotime($absen_hari['tanggal'])) . '</div>';
                            echo '<div style="display: flex; justify-content: space-between; margin-top: 5px;">';
                            echo '<span>Masuk: ' . ($absen_hari['waktu_masuk'] ? date('H:i', strtotime($absen_hari['waktu_masuk'])) : '-') . '</span>';
                            echo '<span>Keluar: ' . ($absen_hari['waktu_keluar'] ? date('H:i', strtotime($absen_hari['waktu_keluar'])) : '-') . '</span>';
                            echo '<span class="badge" style="background: #DCFCE7; color: #166534; padding: 2px 6px; border-radius: 10px; font-size: 10px;">' . $absen_hari['status'] . '</span>';
                            echo '</div></div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div id="st-izin" style="display: none;">
                <div class="dash-card">
                    <div class="section-title">Form Pengajuan</div>
                    <?php if (isset($pesan_izin)): ?>
                        <div style="background: #DCFCE7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 10px; font-size: 12px; text-align: center;">
                            ✅ <?php echo $pesan_izin; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <label style="font-size: 12px; font-weight: bold;">Jenis</label>
                        <select name="jenis" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            <option>Izin</option>
                            <option>Cuti</option>
                        </select>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="font-size: 12px; font-weight: bold;">Mulai</label>
                                <input type="date" name="mulai" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: bold;">Selesai</label>
                                <input type="date" name="selesai" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            </div>
                        </div>
                        <label style="font-size: 12px; font-weight: bold;">Alasan</label>
                        <textarea name="alasan" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ddd;"></textarea>
                        <button type="submit" name="kirim_izin" style="width: 100%; padding: 12px; background: #22C55E; color: white; border: none; border-radius: 8px; font-weight: bold;">Kirim Pengajuan</button>
                    </form>
                </div>
            </div>

            <div id="st-notif" style="display: none;">
                <div class="section-title">Notifikasi</div>
                <?php if (empty($notifikasi)): ?>
                    <div class="empty-state"><span>🔔</span>Tidak ada notifikasi</div>
                <?php else: ?>
                    <?php foreach($notifikasi as $notif): ?>
                    <div class="dash-card" style="border-left: 4px solid #3B82F6; margin-bottom: 8px;">
                        <div style="font-size: 13px;"><?php echo $notif['pesan']; ?></div>
                        <div style="font-size: 11px; color: #888; margin-top: 5px;"><?php echo date('d M Y', strtotime($notif['tanggal'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="app-footer">© 2025 DHL Warehouse</div>
    </div>
</body>
</html>