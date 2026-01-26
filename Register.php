<?php
session_start();
// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "system_budget";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_msg = "";
$success_msg = "";

// --- Logic สมัครสมาชิก ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($pass !== $confirm_pass) {
        $error_msg = "รหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบ Username ซ้ำ
        $check = $conn->query("SELECT id FROM users WHERE username = '$user'");
        if ($check->num_rows > 0) {
            $error_msg = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
        } else {
            // Hash Password
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $fullname, $user, $hashed_password);
            
            if ($stmt->execute()) {
                $success_msg = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
                // header("refresh:2;url=Login.php"); // ถ้าต้องการ Redirect อัตโนมัติ
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - AMSS++</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0A192F;
            --accent-yellow: #FFC107;
            --bg-light: #f4f7f6;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            border-top: 5px solid var(--accent-yellow);
        }
        .register-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .register-header h3 { font-weight: 700; margin: 0; font-size: 1.5rem; }
        .register-header p { margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem; }
        .register-body { padding: 30px; }
        .form-control { border-radius: 5px; padding: 10px; }
        .form-control:focus { border-color: var(--accent-yellow); box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25); }
        .btn-register {
            background-color: var(--primary-dark);
            color: white;
            width: 100%;
            padding: 10px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            transition: 0.3s;
        }
        .btn-register:hover { background-color: #152a45; color: var(--accent-yellow); }
        .login-link { text-align: center; margin-top: 15px; font-size: 0.9rem; }
        .login-link a { color: var(--primary-dark); text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; color: #d63384; }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="register-header">
            <h3>AMSS++</h3>
            <p>ระบบบริหารจัดการงบประมาณ</p>
        </div>
        <div class="register-body">
            <h4 class="text-center mb-4" style="color: #555;">สมัครสมาชิกใหม่</h4>
            
            <?php if($error_msg): ?>
                <div class="alert alert-danger py-2 text-center small"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            
            <?php if($success_msg): ?>
                <div class="alert alert-success py-2 text-center small">
                    <?php echo $success_msg; ?> <br>
                    <a href="Login.php" class="alert-link">คลิกที่นี่เพื่อเข้าสู่ระบบ</a>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="fullname" class="form-control" placeholder="ระบุชื่อ-สกุลจริง" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-at"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="ภาษาอังกฤษ ตัวเลข" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="กำหนดรหัสผ่าน" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-check"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="ใส่รหัสผ่านอีกครั้ง" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-register">สมัครสมาชิก</button>
            </form>

            <div class="login-link">
                มีบัญชีผู้ใช้งานแล้ว? <a href="Login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

</body>
</html>