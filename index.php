<?php
include 'config.php';
$error = "";

if (isset($_POST['login'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    
    // Debug: cek koneksi database
    if ($conn->connect_error) {
        $error = "Database connection failed: " . $conn->connect_error;
    } else {
        $user = getUserByUsername($conn, $u);
        if ($user) {
            if ($user['password'] === $p) {
                $_SESSION['user'] = $user;
                header("Location: dashboard_" . $user['role'] . ".php");
                exit();
            } else {
                $error = "Password salah";
            }
        } else {
            $error = "Username tidak ditemukan";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - DHL Warehouse</title>
    <?php echo getHeaderStyles(); ?>
    <style>
        .login-page { background: linear-gradient(160deg, #FFB800, #E60000); height: 844px; color: white; text-align: center; padding: 80px 30px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.2); color: white; }
        .btn-login { width: 100%; padding: 15px; background: white; color: #E60000; border: none; border-radius: 10px; font-weight: bold; margin-top: 20px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="phone">
        <div class="login-page">
            <div style="font-size: 50px;">🏭</div>
            <h2>DHL Warehouse</h2>
            <p>Sistem Absensi & Manajemen Shift</p>
            
            <?php if($error): ?>
                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 5px; font-size: 13px;">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login" class="btn-login">Login</button>
            </form>
            <p style="font-size: 14px; line-height: 1.6; max-width: 320px; margin: 20px auto 0;">contoh user :<br>
                manager - 123<br>
                supervisor - 123<br>
                staff - 123</p>
        </div>
    </div>
</body>
</html>
