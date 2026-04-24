<?php
include 'config.php';
cekLogin();
$user = $_SESSION['user'];
$tgl_hari_ini = date('Y-m-d');

if (isset($_POST['tambah_staff'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $email = $_POST['email'];
    
    // Cek username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "Username sudah digunakan.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, nama, jabatan, email) VALUES (?, ?, 'staff', ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $password, $nama, $jabatan, $email);
        if ($stmt->execute()) {
            $pesan = "Staff berhasil ditambahkan.";
            // Refresh list staff
            $result = $conn->query("SELECT * FROM users WHERE role = 'staff'");
            $list_staff = $result->fetch_all(MYSQLI_ASSOC);
            $total_staff = count($list_staff);
        } else {
            $error = "Gagal menambahkan staff.";
        }
    }
}

if (isset($_POST['edit_staff'])) {
    $staff_id = $_POST['staff_id'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $staff_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "Username sudah digunakan.";
    } else {
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nama = ?, jabatan = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $username, $password, $nama, $jabatan, $email, $staff_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, nama = ?, jabatan = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $nama, $jabatan, $email, $staff_id);
        }
        if ($stmt->execute()) {
            $pesan = "Staff berhasil diupdate.";
            $result = $conn->query("SELECT * FROM users WHERE role = 'staff'");
            $list_staff = $result->fetch_all(MYSQLI_ASSOC);
            $total_staff = count($list_staff);
        } else {
            $error = "Gagal update staff.";
        }
    }
}

$result = $conn->query("SELECT * FROM users WHERE role = 'staff'");
$list_staff = $result->fetch_all(MYSQLI_ASSOC);
$total_staff = count($list_staff);

$result = $conn->query("SELECT COUNT(*) as hadir FROM absensi WHERE tanggal = '$tgl_hari_ini'");
$jumlah_hadir = $result->fetch_assoc()['hadir'];
$jumlah_tidak_hadir = $total_staff - $jumlah_hadir;

$result = $conn->query("SELECT u.nama, a.waktu_masuk, a.waktu_keluar FROM absensi a JOIN users u ON a.user_id = u.id WHERE a.tanggal = '$tgl_hari_ini'");
$data_hari_ini = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT COUNT(*) as total_shift FROM shift WHERE tgl_shift = '$tgl_hari_ini'");
$jumlah_shift = $result->fetch_assoc()['total_shift'];
$result = $conn->query("SELECT COUNT(DISTINCT staff_id) as staff_scheduled FROM shift WHERE tgl_shift = '$tgl_hari_ini'");
$staff_scheduled = $result->fetch_assoc()['staff_scheduled'];

$result = $conn->query("SELECT s.*, u.nama as nama_staff FROM shift s JOIN users u ON s.staff_id = u.id ORDER BY s.tgl_shift DESC, s.jam_mulai ASC");
$data_shifts = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>DHL Warehouse - Dashboard Manajer</title>
    <?php echo getHeaderStyles(); ?>
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
                    <span class="user-role role-manajer">Manajer</span>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="dash-card">
                <div class="dash-title">Dashboard Manajer</div>
                <div class="dash-sub">Selamat datang, <?php echo $user['nama']; ?></div>
            </div>

            <?php if(isset($error)): ?>
                <div style="background: rgba(255,0,0,0.2); padding: 10px; border-radius: 5px; font-size: 13px; color: #d00; margin-bottom: 12px;">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if(isset($pesan)): ?>
                <div style="background: rgba(0,255,0,0.2); padding: 10px; border-radius: 5px; font-size: 13px; color: #0d0; margin-bottom: 12px;">✅ <?php echo $pesan; ?></div>
            <?php endif; ?>

            <div class="tabs" style="display: flex; gap: 8px; margin-bottom: 12px; background: white; border-radius: var(--radius); padding: 6px; box-shadow: var(--shadow);">
                <button class="tab-btn active" id="m-tab-ringkasan" onclick="mTab('ringkasan')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: var(--dhl-red); color: white; font-size: 12px; font-weight: 500; cursor: pointer;">📊 Ringkasan</button>
                <button class="tab-btn" id="m-tab-staff" onclick="mTab('staff')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: transparent; color: var(--gray-500); font-size: 12px; font-weight: 500; cursor: pointer;">👥 Staff</button>
                <button class="tab-btn" id="m-tab-jadwal" onclick="mTab('jadwal')" style="flex: 1; padding: 9px 4px; border: none; border-radius: var(--radius-sm); background: transparent; color: var(--gray-500); font-size: 12px; font-weight: 500; cursor: pointer;">📅 Jadwal</button>
            </div>

            <div id="m-ringkasan">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-label">Total Staff</div>
                        <div class="stat-val"><?php echo $total_staff; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-label">Hadir</div>
                        <div class="stat-val green"><?php echo $jumlah_hadir; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">❌</div>
                        <div class="stat-label">Tidak Hadir</div>
                        <div class="stat-val red"><?php echo $jumlah_tidak_hadir; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-label">Shift Hari Ini</div>
                        <div class="stat-val"><?php echo $jumlah_shift; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-label">Staff Terjadwal</div>
                        <div class="stat-val"><?php echo $staff_scheduled; ?></div>
                    </div>
                </div>
                
                <div class="dash-card">
                    <div class="section-title">Absensi Hari Ini</div>
                    <?php if (empty($data_hari_ini)): ?>
                        <div class="empty-state"><span>📋</span>Belum ada data absensi</div>
                    <?php else: ?>
                        <?php foreach($data_hari_ini as $username => $absen): ?>
                            <div style="background: white; border: 1px solid #E5E7EB; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px;"><?php echo $absen['nama']; ?></div>
                                    <div style="font-size: 12px; color: var(--gray-500); display: flex; gap: 16px;">
                                        <span>Masuk: <?php echo $absen['waktu_masuk'] ?: '-'; ?></span>
                                        <span>Keluar: <?php echo $absen['waktu_keluar'] ?: '-'; ?></span>
                                    </div>
                                </div>
                                <span class="badge" style="background: #DCFCE7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;">Hadir</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="m-staff" style="display:none">
                <button class="btn-new-shift" type="button" onclick="toggleFormStaff()" style="margin-bottom: 12px;">➕ Tambah Staff Baru</button>

                <div id="form-staff-wrapper" style="display:none; margin-bottom: 12px;">
                    <form class="form-card" method="POST" style="background: white; border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow);">
                        <div class="form-title" style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px; text-align: center;">➕ Tambah Staff Baru</div>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                            <div>
                                <input type="text" class="form-input" name="username" aria-label="Username" placeholder="Masukkan username" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                            </div>
                            
                            <div>
                                <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Password</label>
                                <input type="password" class="form-input" name="password" placeholder="Masukkan password" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Nama</label>
                                    <input type="text" class="form-input" name="nama" placeholder="Nama lengkap" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Jabatan</label>
                                    <input type="text" class="form-input" name="jabatan" value="Warehouse Staff" placeholder="Jabatan" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Email</label>
                                <input type="email" class="form-input" name="email" placeholder="email@domain.com" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                            </div>
                        </div>
                        
                        <div class="btn-row" style="display: flex; gap: 8px; margin-top: 20px;">
                            <button type="submit" name="tambah_staff" class="btn-save" style="flex: 1; padding: 12px; background: var(--dhl-red); color: white; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer;">💾 Tambah Staff</button>
                            <button type="button" class="btn-cancel" onclick="toggleFormStaff()" style="flex: 1; padding: 12px; background: var(--gray-200); color: var(--gray-700); border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer;">✕ Batal</button>
                        </div>
                    </form>
                </div>

                <div id="form-edit-staff-wrapper" style="display:none; margin-bottom: 12px;">
                    <form class="form-card" method="POST" style="background: white; border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow);">
                        <div class="form-title" style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px; text-align: center;">✏️ Edit Staff</div>
                        <input type="hidden" name="staff_id" id="edit_staff_id">
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                            <div>
                                <input type="text" class="form-input" name="username" id="edit_username" aria-label="Username" placeholder="Masukkan username" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                            </div>
                            
                            <div>
                                <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Password (kosongkan jika tidak ingin ganti)</label>
                                <input type="password" class="form-input" name="password" placeholder="Biarkan kosong jika tidak ganti" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;">
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Nama</label>
                                    <input type="text" class="form-input" name="nama" id="edit_nama" placeholder="Nama lengkap" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Jabatan</label>
                                    <input type="text" class="form-input" name="jabatan" id="edit_jabatan" placeholder="Jabatan" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label" style="font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; display: block;">Email</label>
                                <input type="email" class="form-input" name="email" id="edit_email" placeholder="email@domain.com" style="width: 100%; padding: 12px; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 14px;" required>
                            </div>
                        </div>
                        
                        <div class="btn-row" style="display: flex; gap: 8px; margin-top: 20px;">
                            <button type="submit" name="edit_staff" class="btn-save" style="flex: 1; padding: 12px; background: var(--dhl-red); color: white; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer;">💾 Update Staff</button>
                            <button type="button" class="btn-cancel" onclick="toggleFormEditStaff()" style="flex: 1; padding: 12px; background: var(--gray-200); color: var(--gray-700); border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer;">✕ Batal</button>
                        </div>
                    </form>
                </div>

                <div class="section-title">Data Staff</div>
                <div id="staff-list">
                    <?php foreach($list_staff as $staff): ?>
                    <div style="background: white; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; box-shadow: var(--shadow); display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <div style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 2px;"><?php echo $staff['nama']; ?></div>
                            <div style="font-size: 12px; color: var(--gray-500);"><?php echo $staff['jabatan']; ?></div>
                            <div style="font-size: 12px; color: var(--gray-400); margin-bottom: 6px;"><?php echo $staff['email']; ?></div>
                            <span class="badge" style="background: #BBF7D0; color: #166534; padding: 2px 10px; border-radius: 20px; font-size: 11px;">Staff</span>
                        </div>
                        <div style="color: var(--blue); font-size: 18px; cursor: pointer;" onclick="editStaff(<?php echo $staff['id']; ?>, '<?php echo $staff['username']; ?>', '<?php echo $staff['nama']; ?>', '<?php echo $staff['jabatan']; ?>', '<?php echo $staff['email']; ?>')">✏️</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="m-jadwal" style="display:none">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <label style="font-size: 13px; color: var(--gray-600); white-space: nowrap;">Filter Tanggal:</label>
                    <input type="date" style="margin:0;flex:1; width: 100%; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); padding: 10px 12px; font-size: 14px; font-family: var(--font); color: var(--gray-900); outline: none;" value="<?php echo $tgl_hari_ini; ?>">
                </div>
                
                <div class="section-title">Jadwal Shift</div>
                <div id="m-jadwal-list">
                    <?php if (empty($data_shifts)): ?>
                        <div class="empty-state"><span>📅</span>Tidak ada jadwal</div>
                    <?php else: ?>
                        <?php 
                        $shifts_reverse = array_reverse($data_shifts);
                        foreach($shifts_reverse as $shift): 
                        ?>
                        <div style="background: white; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; box-shadow: var(--shadow); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 2px;"><?php echo $shift['nama_staff']; ?></div>
                                <div style="font-size: 13px; color: var(--gray-500);"><?php echo $shift['jam_mulai'] . ' - ' . $shift['jam_selesai']; ?></div>
                            </div>
                            <span class="badge" style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; <?php 
                                if($shift['tipe']=='Pagi') echo 'background: #FEF3C7; color: #92400E;';
                                else if($shift['tipe']=='Siang') echo 'background: #FED7AA; color: #9A3412;';
                                else echo 'background: #E0E7FF; color: #3730A3;';
                            ?>"><?php echo $shift['tipe']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="section-title" style="margin-top:12px">Rekap Absensi</div>
                <div>
                    <?php if (empty($data_hari_ini)): ?>
                        <div class="empty-state"><span>📋</span>Tidak ada data absensi</div>
                    <?php else: ?>
                        <?php foreach($data_hari_ini as $username => $absen): ?>
                            <div style="background: white; border-radius: var(--radius); padding: 14px 16px; margin-bottom: 8px; box-shadow: var(--shadow); display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px;"><?php echo $absen['nama']; ?></div>
                                    <div style="font-size: 12px; color: var(--gray-500); display: flex; gap: 16px;">
                                        <span>Masuk: <?php echo $absen['waktu_masuk'] ?: '-'; ?></span>
                                        <span>Keluar: <?php echo $absen['waktu_keluar'] ?: '-'; ?></span>
                                    </div>
                                </div>
                                <span class="badge" style="background: #DCFCE7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;">Hadir</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="app-footer">© 2025 DHL Warehouse</div>
    </div>
      
    <script>
        function mTab(tab) {
            document.getElementById('m-ringkasan').style.display = (tab === 'ringkasan') ? 'block' : 'none';
            document.getElementById('m-staff').style.display = (tab === 'staff') ? 'block' : 'none';
            document.getElementById('m-jadwal').style.display = (tab === 'jadwal') ? 'block' : 'none';
            
            const btnRingkasan = document.getElementById('m-tab-ringkasan');
            const btnStaff = document.getElementById('m-tab-staff');
            const btnJadwal = document.getElementById('m-tab-jadwal');
            
            [btnRingkasan, btnStaff, btnJadwal].forEach(btn => {
                btn.style.background = 'transparent';
                btn.style.color = 'var(--gray-500)';
            });
            
            const activeBtn = document.getElementById('m-tab-' + tab);
            activeBtn.style.background = 'var(--dhl-red)';
            activeBtn.style.color = 'white';
        }

        function toggleFormStaff() {
            const formWrapper = document.getElementById('form-staff-wrapper');
            formWrapper.style.display = (formWrapper.style.display === 'none') ? 'block' : 'none';
        }

        function editStaff(id, username, nama, jabatan, email) {
            document.getElementById('edit_staff_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_jabatan').value = jabatan;
            document.getElementById('edit_email').value = email;
            document.getElementById('form-edit-staff-wrapper').style.display = 'block';
        }

        function toggleFormEditStaff() {
            document.getElementById('form-edit-staff-wrapper').style.display = 'none';
        }
    </script>
</body>
</html>
