<?php
include 'config.php';
cekLogin();
$user = $_SESSION['user'];
$tgl_hari_ini = date('Y-m-d');

// --- LOGIKA KONFIRMASI IZIN ---
if (isset($_POST['konfirmasi_izin'])) {
    $izin_id = $_POST['izin_id'];
    $status = $_POST['status'];
    updateIzinStatus($conn, $izin_id, $status);
    // Refresh halaman
    header("Location: dashboard_supervisor.php");
    exit();
}

// --- LOGIKA SIMPAN SHIFT ---
if (isset($_POST['simpan_shift'])) {
    if (!isset($_POST['staff_id']) || empty($_POST['staff_id'])) {
        $error = "Pilih staff terlebih dahulu.";
    } else {
        $supervisor_id = $user['id'];
        $staff_id = $_POST['staff_id'];
        $tgl_shift = $_POST['tgl_shift'];
        $jam_mulai = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];
        $tipe = $_POST['tipe'];
        
        if (insertShift($conn, $supervisor_id, $staff_id, $tgl_shift, $jam_mulai, $jam_selesai, $tipe)) {
            // Refresh agar form bersih
            header("Location: dashboard_supervisor.php");
            exit();
        } else {
            $error = "Gagal menyimpan shift.";
        }
    }
}

// --- AMBIL DATA ABSENSI HARI INI ---
$absen_staff = isset($_SESSION['data_absensi'][$tgl_hari_ini]) ? $_SESSION['data_absensi'][$tgl_hari_ini] : [];
$jml_hadir = count($absen_staff);
$jml_izin = 0; // Data dummy untuk visual
$jml_cuti = 0; // Data dummy untuk visual
$jml_alpa = 0; // Data dummy untuk visual

// Ambil daftar staff untuk dropdown
$result = $conn->query("SELECT id, nama FROM users WHERE role = 'staff'");
$list_staff = $result->fetch_all(MYSQLI_ASSOC);

// Ambil data shift dari database
$result = $conn->query("SELECT s.*, u.nama as nama_staff FROM shift s JOIN users u ON s.staff_id = u.id ORDER BY s.tgl_shift DESC, s.jam_mulai ASC");
$data_shifts = $result->fetch_all(MYSQLI_ASSOC);

// Ambil data izin yang menunggu konfirmasi
$result = $conn->query("SELECT i.*, u.nama FROM izin i JOIN users u ON i.user_id = u.id WHERE i.status = 'Menunggu' ORDER BY i.tanggal_mulai DESC");
$list_izin_menunggu = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>DHL Warehouse - Dashboard Supervisor</title>
    <?php echo getHeaderStyles(); ?>
    <style>
        /* Tambahan style khusus Supervisor dari file asli Anda */
        .btn-new-shift { width: 100%; background: var(--dhl-red); color: white; border: none; border-radius: var(--radius); padding: 14px; font-size: 15px; font-weight: 700; font-family: var(--font); cursor: pointer; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: var(--shadow-md); transition: opacity 0.2s; }
        .form-card { background: white; border-radius: var(--radius); padding: 16px; margin-bottom: 12px; box-shadow: var(--shadow); }
        .form-title { font-size: 15px; font-weight: 700; color: var(--gray-900); margin-bottom: 14px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 5px; display: block; }
        .form-input, .form-select { width: 100%; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); padding: 10px 12px; font-size: 14px; font-family: var(--font); color: var(--gray-900); margin-bottom: 12px; outline: none; background: white; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-row { display: flex; gap: 8px; }
        .btn-save { flex: 1; background: var(--green); color: white; border: none; border-radius: var(--radius-sm); padding: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-cancel { flex: 1; background: var(--gray-200); color: var(--gray-700); border: none; border-radius: var(--radius-sm); padding: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        
        .shift-card { background: white; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; box-shadow: var(--shadow); display: flex; align-items: flex-start; justify-content: space-between; }
        .shift-name { font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 2px; }
        .shift-date { font-size: 12px; color: var(--gray-500); margin-bottom: 2px; }
        .shift-time { font-size: 13px; color: var(--gray-700); }
        
        .filter-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .filter-label { font-size: 13px; color: var(--gray-600); white-space: nowrap; }
        
        .rekap-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .rekap-card { border-radius: var(--radius-sm); padding: 14px 16px; }
        .rekap-card.hadir { background: #DCFCE7; }
        .rekap-card.izin { background: #FEF3C7; }
        .rekap-card.cuti { background: #DBEAFE; }
        .rekap-card.alpa { background: #FEE2E2; }
        .rekap-label { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 4px; }
        .rekap-val { font-size: 26px; font-weight: 700; }
        .rekap-val.green { color: #166534; }
        .rekap-val.yellow { color: #92400E; }
        .rekap-val.blue { color: #1D4ED8; }
        .rekap-val.red { color: #991B1B; }

        .histori-item { background: white; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; box-shadow: var(--shadow); display: flex; align-items: flex-start; justify-content: space-between; }
        .histori-date { font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px; }
        .histori-times { font-size: 12px; color: var(--gray-500); display: flex; gap: 16px; }
        
        .badge-pagi { background: #FEF3C7; color: #92400E; }
        .badge-siang { background: #FED7AA; color: #9A3412; }
        .badge-malam { background: #E0E7FF; color: #3730A3; }
        .badge-hadir { background: #DCFCE7; color: #166534; }
    </style>
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
                <a href="logout.php" class="btn-logout" title="Logout">↪</a>
            </div>
            <div class="header-user">
                <div class="user-avatar">👤</div>
                <div>
                    <div class="user-name"><?php echo $user['nama']; ?></div>
                    <span class="user-role role-supervisor">Supervisor</span>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="dash-card">
                <div class="dash-title">Dashboard Supervisor</div>
                <div class="dash-sub">Selamat datang, <?php echo $user['nama']; ?></div>
            </div>

            <?php if(isset($error)): ?>
                <div style="background: rgba(255,0,0,0.2); padding: 10px; border-radius: 5px; font-size: 13px; color: #d00; margin-bottom: 12px;">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="tabs" style="display: flex; gap: 8px; margin-bottom: 12px; background: white; border-radius: var(--radius); padding: 6px; box-shadow: var(--shadow);">
                <button class="tab-btn active" id="s-tab-shift" onclick="sTab('shift')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: var(--dhl-red); color: white; font-size: 12px; font-weight: 500; cursor: pointer;">📅 Shift Kerja</button>
                <button class="tab-btn" id="s-tab-rekap" onclick="sTab('rekap')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: transparent; color: var(--gray-500); font-size: 12px; font-weight: 500; cursor: pointer;">📊 Rekap Absensi</button>
                <button class="tab-btn" id="s-tab-izin" onclick="sTab('izin')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: transparent; color: var(--gray-500); font-size: 12px; font-weight: 500; cursor: pointer;">📋 Konfirmasi Izin</button>
            </div>

            <div id="s-shift">
                <button class="btn-new-shift" type="button" onclick="toggleFormShift()" style="z-index: 9999; pointer-events: auto; position: relative;">➕ Buat Shift Baru</button>

                <div id="form-shift-wrapper" style="display:none">
                    <form class="form-card" method="POST">
                        <div class="form-title">Buat Shift Kerja</div>
                        
                        <label class="form-label">Staff</label>
                        <select class="form-select" name="staff_id" required>
                            <option value="">-- Pilih Staff --</option>
                            <?php foreach($list_staff as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo $st['nama']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-input" name="tgl_shift" value="<?php echo $tgl_hari_ini; ?>" required>
                        
                        <div class="form-row">
                            <div>
                                <label class="form-label">Jam Mulai</label>
                                <input type="time" class="form-input" name="jam_mulai" value="08:00" required>
                            </div>
                            <div>
                                <label class="form-label">Jam Selesai</label>
                                <input type="time" class="form-input" name="jam_selesai" value="16:00" required>
                            </div>
                        </div>
                        
                        <label class="form-label">Tipe Shift</label>
                        <select class="form-select" name="tipe" required>
                            <option value="Pagi">Pagi (08:00 - 16:00)</option>
                            <option value="Siang">Siang (16:00 - 00:00)</option>
                            <option value="Malam">Malam (00:00 - 08:00)</option>
                        </select>
                        
                        <div class="btn-row">
                            <button type="submit" name="simpan_shift" class="btn-save">💾 Simpan</button>
                            <button type="button" class="btn-cancel" onclick="toggleFormShift()">✕ Batal</button>
                        </div>
                    </form>
                </div>

                <div class="section-title">Daftar Shift</div>
                <div id="shift-list">
                    <?php if (empty($data_shifts)): ?>
                        <div class="empty-state"><span>📅</span>Belum ada shift</div>
                    <?php else: ?>
                        <?php foreach($data_shifts as $shift): 
                            $badge_class = 'badge-pagi';
                            if ($shift['tipe'] == 'Siang') $badge_class = 'badge-siang';
                            if ($shift['tipe'] == 'Malam') $badge_class = 'badge-malam';
                        ?>
                        <div class="shift-card">
                            <div class="shift-info">
                                <div class="shift-name"><?php echo $shift['nama_staff']; ?></div>
                                <div class="shift-date"><?php echo date('D, d M', strtotime($shift['tgl_shift'])); ?></div>
                                <div class="shift-time"><?php echo $shift['jam_mulai'] . ' - ' . $shift['jam_selesai']; ?></div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $shift['tipe']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="s-rekap" style="display:none">
                <div class="filter-row">
                    <label class="filter-label">Pilih Periode:</label>
                    <input type="date" class="form-input" style="margin:0;flex:1" value="<?php echo $tgl_hari_ini; ?>">
                </div>
                
                <div class="rekap-grid">
                    <div class="rekap-card hadir"><div class="rekap-label">Hadir</div><div class="rekap-val green"><?php echo $jml_hadir; ?></div></div>
                    <div class="rekap-card izin"><div class="rekap-label">Izin</div><div class="rekap-val yellow"><?php echo $jml_izin; ?></div></div>
                    <div class="rekap-card cuti"><div class="rekap-label">Cuti</div><div class="rekap-val blue"><?php echo $jml_cuti; ?></div></div>
                    <div class="rekap-card alpa"><div class="rekap-label">Alpa</div><div class="rekap-val red"><?php echo $jml_alpa; ?></div></div>
                </div>
                
                <div class="section-title">Detail Absensi</div>
                <div id="s-detail-absensi">
                    <?php if (empty($absen_staff)): ?>
                        <div class="empty-state"><span>📋</span>Tidak ada data absensi</div>
                    <?php else: ?>
                        <?php foreach($absen_staff as $username => $data): ?>
                        <div class="histori-item">
                            <div>
                                <div class="histori-date"><?php echo $data['nama']; ?></div>
                                <div class="histori-times"><span>Masuk: <?php echo $data['masuk']; ?></span><span>Keluar: <?php echo $data['keluar']; ?></span></div>
                            </div>
                            <span class="badge badge-hadir">Hadir</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="s-izin" style="display:none">
                <div class="section-title">Konfirmasi Pengajuan Izin (<?php echo count($list_izin_menunggu); ?> menunggu)</div>
                <div id="s-list-izin">
                    <?php if (empty($list_izin_menunggu)): ?>
                        <div class="empty-state"><span>📋</span>Tidak ada pengajuan izin menunggu konfirmasi</div>
                    <?php else: ?>
                        <?php foreach($list_izin_menunggu as $izin): ?>
                        <div class="histori-item">
                            <div>
                                <div class="histori-date"><?php echo $izin['nama']; ?> - <?php echo $izin['jenis']; ?></div>
                                <div class="histori-times">
                                    <span>Tanggal: <?php echo $izin['tanggal_mulai']; ?> s/d <?php echo $izin['tanggal_selesai']; ?></span>
                                </div>
                                <div style="font-size: 12px; color: var(--gray-600); margin-top: 4px;"><?php echo $izin['alasan']; ?></div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <form method="POST" action="dashboard_supervisor.php" style="display: inline;">
                                    <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                    <input type="hidden" name="status" value="Disetujui">
                                    <input type="submit" name="konfirmasi_izin" value="✔ Approve" style="background: #22C55E; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; z-index: 9999; pointer-events: auto; position: relative;">
                                </form>
                                <form method="POST" action="dashboard_supervisor.php" style="display: inline;">
                                    <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                    <input type="hidden" name="status" value="Ditolak">
                                    <input type="submit" name="konfirmasi_izin" value="✖ Reject" style="background: #EF4444; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; z-index: 9999; pointer-events: auto; position: relative;">
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <div class="app-footer">© 2025 DHL Warehouse</div>
    </div>

    <script>
        function sTab(tab) {
            // Sembunyikan semua konten tab
            document.getElementById('s-shift').style.display = (tab === 'shift') ? 'block' : 'none';
            document.getElementById('s-rekap').style.display = (tab === 'rekap') ? 'block' : 'none';
            document.getElementById('s-izin').style.display = (tab === 'izin') ? 'block' : 'none';
            
            // Atur warna tombol tab
            const btnShift = document.getElementById('s-tab-shift');
            const btnRekap = document.getElementById('s-tab-rekap');
            const btnIzin = document.getElementById('s-tab-izin');
            
            btnShift.style.background = (tab === 'shift') ? 'var(--dhl-red)' : 'transparent';
            btnShift.style.color = (tab === 'shift') ? 'white' : 'var(--gray-500)';
            
            btnRekap.style.background = (tab === 'rekap') ? 'var(--dhl-red)' : 'transparent';
            btnRekap.style.color = (tab === 'rekap') ? 'white' : 'var(--gray-500)';
            
            btnIzin.style.background = (tab === 'izin') ? 'var(--dhl-red)' : 'transparent';
            btnIzin.style.color = (tab === 'izin') ? 'white' : 'var(--gray-500)';
        }

        function toggleFormShift() {
            console.log('Toggle clicked!');
            const formWrapper = document.getElementById('form-shift-wrapper');
            formWrapper.style.display = (formWrapper.style.display === 'none') ? 'block' : 'none';
        }
    </script>
</body>
</html>