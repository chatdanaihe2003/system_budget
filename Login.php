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

// --- Logic เข้าสู่ระบบ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$user'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // ตรวจสอบรหัสผ่าน (Hash)
        if (password_verify($pass, $row['password'])) {
            // Login สำเร็จ -> เก็บ Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];
            
            // Redirect ไปหน้าแรก
            header("Location: index.php");
            exit();
        } else {
            $error_msg = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error_msg = "ไม่พบชื่อผู้ใช้งานนี้";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - AMSS++</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-dark: #0A192F;
            --accent-yellow: #FFC107;
            --bg-light: #f4f7f6;
        }
        body {
            /* เรียกใช้ฟอนต์ Kanit */
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            border-top: 5px solid var(--accent-yellow);
        }
        .login-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .login-header i { font-size: 3rem; color: var(--accent-yellow); margin-bottom: 10px; }
        .login-header h3 { font-weight: 600; margin: 0; font-size: 1.6rem; letter-spacing: 0.5px; }
        .login-header h6 { font-weight: 300; margin-top: 5px; opacity: 0.9; }
        .login-header small { font-weight: 300; opacity: 0.7; }
        
        .login-body { padding: 30px; }
        
        .form-control { border-radius: 5px; padding: 12px; font-weight: 300; }
        .form-control:focus { border-color: var(--accent-yellow); box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25); }
        
        .btn-login {
            background-color: var(--primary-dark);
            color: white;
            width: 100%;
            padding: 12px;
            font-weight: 500; /* ปรับความหนาให้เข้ากับฟอนต์ */
            border: none;
            border-radius: 5px;
            transition: 0.3s;
            font-size: 1.1rem;
        }
        .btn-login:hover { background-color: #152a45; color: var(--accent-yellow); }
        
        .register-link { text-align: center; margin-top: 20px; font-size: 0.9rem; font-weight: 300; }
        .register-link a { color: var(--primary-dark); text-decoration: none; font-weight: 500; }
        .register-link a:hover { text-decoration: underline; color: #d63384; }
        
        .form-label { font-weight: 400; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-coins"></i>
            <h3>Budget control system</h3>
            <h6>ระบบบริหารจัดการงบประมาณ</h6>
            <small>สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</small>
        </div>
        <div class="login-body">
            <h4 class="text-center mb-4 text-secondary" style="font-weight: 600;">เข้าสู่ระบบ</h4>
            
            <?php if($error_msg): ?>
                <div class="alert alert-danger py-2 text-center small">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้งาน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-key"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-login">เข้าสู่ระบบ</button>
            </form>

            <div class="register-link">
                ยังไม่มีบัญชีผู้ใช้งาน? <a href="Register.php">สมัครสมาชิกใหม่</a>
            </div>
        </div>
    </div>

</body>
</html>