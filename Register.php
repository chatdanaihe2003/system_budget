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
            // เข้ารหัส Password ให้ตรงกับระบบหลัก (md5)
            $hashed_password = md5($pass);
            
            // กำหนดสิทธิ์เริ่มต้นเป็น 'USERทั่วไป'
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'USERทั่วไป')");
            $stmt->bind_param("sss", $fullname, $user, $hashed_password);
            
            if ($stmt->execute()) {
                $success_msg = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
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
    <title>สมัครสมาชิก - AMSS++ Budget System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Modern Theme Palette (Same as Header.php & Login.php) */
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
            background: radial-gradient(circle at top left, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .register-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-blue) 100%);
            color: white;
            padding: 35px 20px;
            text-align: center;
        }

        /* ตกแต่งพื้นที่ใส่โลโก้ให้ใหญ่ขึ้น (เหมือนหน้า Login) */
        .header-icon-wrapper {
            background: var(--white);
            width: 120px; /* ขยายขนาดวงกลม */
            height: 120px; /* ขยายขนาดวงกลม */
            border-radius: 50%; /* ปรับเป็นทรงกลมให้ดูทางการ */
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            overflow: hidden; /* ป้องกันภาพล้นขอบ */
        }

        /* จัดระเบียบรูปภาพให้อยู่ตรงกลางและขยายขนาดให้เต็มขึ้น */
        .header-icon-wrapper img {
            max-width: 90%; /* ขยายรูปให้เกือบเต็มวงกลม */
            height: auto;
            object-fit: contain;
        }

        .register-header h3 { 
            font-weight: 700; 
            margin: 0; 
            font-size: 1.5rem; 
            letter-spacing: -0.5px;
        }

        .register-header h6 { 
            font-weight: 300; 
            margin-top: 5px; 
            color: var(--accent-cyan);
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .register-header small { 
            display: block;
            margin-top: 10px;
            font-weight: 300; 
            opacity: 0.6; 
            font-size: 0.75rem;
        }
        
        .register-body { padding: 40px; }
        
        .form-label { 
            font-weight: 500; 
            color: var(--primary-dark);
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            margin-bottom: 18px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--secondary-blue);
            width: 45px;
            justify-content: center;
        }

        .form-control { 
            border: 1px solid #e2e8f0;
            padding: 11px 15px; 
            font-weight: 400;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus { 
            border-color: var(--secondary-blue); 
            box-shadow: none;
            background-color: var(--white);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--primary-blue) 100%);
            color: white;
            width: 100%;
            padding: 13px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            transition: var(--transition);
            font-size: 1.05rem;
            margin-top: 10px;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
        }

        .btn-register:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(59, 130, 246, 0.3);
            color: var(--white);
        }
        
        .login-link { 
            text-align: center; 
            margin-top: 25px; 
            font-size: 0.9rem; 
            color: #64748b;
        }

        .login-link a { 
            color: var(--secondary-blue); 
            text-decoration: none; 
            font-weight: 600; 
        }

        .login-link a:hover { 
            text-decoration: underline; 
            color: var(--primary-blue); 
        }

        /* Alert Styling */
        .alert {
            border-radius: 12px;
            font-size: 0.85rem;
            border: none;
        }
        
        .role-notice {
            font-size: 0.8rem;
            color: #64748b;
            text-align: center;
            margin-top: 15px;
            background-color: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="register-header">
            <div class="header-icon-wrapper">
                <img src="images/สพฐ._3_D_ไม่มีขอบ-removebg-preview.png" alt="โลโก้ สพฐ.">
            </div>
            <h3>Planning and budgeting system</h3>
            <h6>ระบบแผนงานและงบประมาณ</h6>
            <small>สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</small>
        </div>

        <div class="register-body">
            <h5 class="text-center mb-4 fw-bold" style="color: var(--primary-dark);">สร้างบัญชีผู้ใช้งานใหม่</h5>
            
            <?php if($error_msg): ?>
                <div class="alert alert-danger mb-3 text-center">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success_msg): ?>
                <div class="alert alert-success mb-3 text-center">
                    <i class="fa-solid fa-circle-check me-2"></i> <?php echo $success_msg; ?> <br>
                    <a href="Login.php" class="fw-bold text-decoration-none" style="color: #0f5132;">เข้าสู่ระบบได้ที่นี่</a>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-1">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-address-card"></i></span>
                        <input type="text" name="fullname" class="form-control" placeholder="ระบุชื่อ-นามสกุลจริง" required>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="ใช้สำหรับเข้าสู่ระบบ" required>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="กำหนดรหัสผ่าน" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-shield-check"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="ระบุรหัสผ่านอีกครั้ง" required>
                    </div>
                </div>

                <button type="submit" class="btn-register">
                    ยืนยันการสมัครสมาชิก <i class="fa-solid fa-paper-plane ms-2"></i>
                </button>
                
                <div class="role-notice">
                    <i class="fa-solid fa-circle-info text-primary"></i> 
                    เมื่อสมัครสำเร็จ ระบบจะกำหนดสิทธิ์เริ่มต้นเป็น <strong>USERทั่วไป</strong> หากต้องการเปลี่ยนแปลงสิทธิ์ กรุณาติดต่อผู้ดูแลระบบ (Admin)
                </div>
            </form>

            <div class="login-link">
                เป็นสมาชิกอยู่แล้ว? <a href="Login.php">กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

</body>
</html>