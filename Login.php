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
        
        // ตรวจสอบรหัสผ่านแบบ MD5 (ให้ตรงกับหน้า Register และ Manage Users)
        if (md5($pass) === $row['password']) {
            // Login สำเร็จ -> เก็บ Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['name'] = $row['name']; // ใช้ name ให้ตรงกับคอลัมน์ใน DB
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
    <title>เข้าสู่ระบบ - AMSS++ Budget System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Modern Theme Palette (Same as Header.php) */
        :root {
            --primary-dark: #0f172a;    /* Navy Dark */
            --primary-blue: #1e3a8a;    /* Deep Blue */
            --secondary-blue: #3b82f6;  /* Royal Blue */
            --accent-cyan: #06b6d4;     /* Cyan */
            --bg-light: #f1f5f9;        /* Light Slate Gray */
            --white: #ffffff;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-light);
            /* เพิ่มพื้นหลังไล่เฉดสีอ่อนๆ เพื่อความทันสมัย */
            background: radial-gradient(circle at top right, #f0f9ff 0%, #e0f2fe 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-blue) 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        /* ตกแต่งพื้นที่ใส่โลโก้ให้ใหญ่ขึ้น */
        .header-icon-wrapper {
            background: var(--white);
            width: 120px; /* ขยายขนาดวงกลม */
            height: 120px; /* ขยายขนาดวงกลม */
            border-radius: 50%; /* ปรับเป็นทรงกลม */
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        /* จัดระเบียบรูปภาพให้อยู่ตรงกลางและขยายขนาดให้เต็มขึ้น */
        .header-icon-wrapper img {
            max-width: 90%; /* ขยายรูปให้เกือบเต็มวงกลม */
            height: auto;
            object-fit: contain;
        }

        .login-header h3 { 
            font-weight: 700; 
            margin: 0; 
            font-size: 1.5rem; 
            letter-spacing: -0.5px;
        }

        .login-header h6 { 
            font-weight: 300; 
            margin-top: 5px; 
            color: var(--accent-cyan);
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .login-header small { 
            display: block;
            margin-top: 10px;
            font-weight: 300; 
            opacity: 0.6; 
            font-size: 0.75rem;
        }
        
        .login-body { padding: 40px 35px; }
        
        .form-label { 
            font-weight: 500; 
            color: var(--primary-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--secondary-blue);
            padding-left: 15px;
            padding-right: 15px;
        }

        .form-control { 
            border: 1px solid #e2e8f0;
            padding: 12px; 
            font-weight: 400;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus { 
            border-color: var(--secondary-blue); 
            box-shadow: none;
            background-color: var(--white);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--primary-blue) 100%);
            color: white;
            width: 100%;
            padding: 14px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            transition: var(--transition);
            font-size: 1.1rem;
            margin-top: 10px;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
        }

        .btn-login:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(59, 130, 246, 0.3);
            color: var(--white);
        }
        
        .register-link { 
            text-align: center; 
            margin-top: 25px; 
            font-size: 0.9rem; 
            color: #64748b;
        }

        .register-link a { 
            color: var(--secondary-blue); 
            text-decoration: none; 
            font-weight: 600; 
        }

        .register-link a:hover { 
            text-decoration: underline; 
            color: var(--primary-blue); 
        }

        /* Error Alert Styling */
        .alert-custom {
            background-color: #fff1f2;
            border: 1px solid #fecdd3;
            color: #e11d48;
            border-radius: 10px;
            font-size: 0.85rem;
            padding: 10px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="header-icon-wrapper">
                <img src="images/สพฐ._3_D_ไม่มีขอบ-removebg-preview.png" alt="โลโก้ สพฐ.">
            </div>
            <h3>Budget Control System</h3>
            <h6>ระบบบริหารจัดการงบประมาณ</h6>
            <small>สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</small>
        </div>

        <div class="login-body">
            <h5 class="text-center mb-4 fw-bold" style="color: var(--primary-dark);">ยินดีต้อนรับ</h5>
            
            <?php if($error_msg): ?>
                <div class="alert alert-custom mb-4 text-center">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้งาน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="ระบุชื่อผู้ใช้งาน" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="ระบุรหัสผ่าน" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    เข้าสู่ระบบ <i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>
                </button>
            </form>

            <div class="register-link">
                ยังไม่มีบัญชีผู้ใช้งาน? <a href="Register.php">สร้างบัญชีใหม่</a>
            </div>
        </div>
    </div>

</body>
</html>