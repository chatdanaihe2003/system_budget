<?php
session_start(); // 1. เริ่มต้น Session (สำคัญมาก ต้องอยู่บรรทัดแรก)

// 2. ตรวจสอบว่าได้ Login หรือยัง ถ้ายังให้เด้งไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// --- ส่วนการเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "system_budget"; 

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงข้อมูลเมนู
$sql = "SELECT * FROM menu_items ORDER BY sort_order ASC";
$result = $conn->query($sql);

// --- ฟังก์ชันวันที่ไทย ---
function thai_date($timestamp) {
    $thai_day_arr = array("อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์");
    $thai_month_arr = array(
        "0"=>"", "1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน",
        "7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม"
    );
    $d = date("j", $timestamp);
    $w = date("w", $timestamp);
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543;
    return "วัน{$thai_day_arr[$w]}ที่ $d {$thai_month_arr[$m]} พ.ศ. $y";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMSS++ การเงินและบัญชี</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-dark: #0A192F;
            --accent-yellow: #FFC107;
            --accent-yellow-dark: #FFB300;
            --menu-bg: #212529;
            --bg-light: #f4f7f6;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-light);
            color: #333;
        }

        /* --- Header Section --- */
        .top-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 12px 20px;
            font-weight: 400;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .system-name {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .user-info {
            font-size: 0.9rem;
            text-align: right;
        }
        .user-role {
            color: var(--accent-yellow);
            font-weight: 700;
            text-transform: uppercase;
        }
        /* ปุ่ม Logout */
        .btn-logout {
            color: #ff6b6b;
            text-decoration: none;
            margin-left: 10px;
            font-size: 0.85rem;
            border: 1px solid #ff6b6b;
            padding: 2px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            background-color: #ff6b6b;
            color: white;
        }

        /* --- Sub Header --- */
        .sub-header {
            background: linear-gradient(90deg, var(--accent-yellow) 0%, var(--accent-yellow-dark) 100%);
            padding: 8px 20px;
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.05rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }

        /* --- Navbar --- */
        .navbar-custom {
            background-color: var(--menu-bg);
            padding: 0 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .menu-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .menu-link {
            color: #aaa;
            padding: 12px 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: none;
        }
        .menu-link i {
            margin-right: 8px;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        /* Hover Effect */
        .menu-link:hover, .menu-link.show {
            color: #fff;
            background-color: #333; 
            border-bottom-color: var(--accent-yellow);
        }
        .menu-link:hover i {
            transform: translateY(-2px);
            color: var(--accent-yellow);
        }

        /* --- Dropdown Styles --- */
        .dropdown-menu {
            border-radius: 0;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin-top: 0;
            padding: 0;
        }
        .dropdown-item {
            padding: 10px 20px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-item:hover {
            background-color: var(--bg-light);
            color: var(--primary-dark);
            padding-left: 25px;
            transition: all 0.2s;
        }

        /* --- Main Content --- */
        .main-content {
            min-height: 75vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        .hero-card {
            background: linear-gradient(135deg, #ffe066 0%, #ffca28 100%);
            border-radius: 20px;
            padding: 50px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .hero-body {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 40px;
            position: relative;
            z-index: 2;
        }
        .money-img-container {
            animation: float 4s ease-in-out infinite;
        }
        .money-img {
            max-width: 220px;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.15));
        }
        .hero-title h1 {
            font-weight: 800;
            font-size: 3.5rem;
            color: var(--primary-dark);
            margin: 0;
            line-height: 1.2;
            text-shadow: 2px 2px 0px rgba(255,255,255,0.4);
        }
        .bg-decoration {
            position: absolute;
            bottom: -20px;
            right: -20px;
            opacity: 0.15;
            color: var(--primary-dark);
            transform: rotate(-15deg);
            z-index: 1;
        }
        .bg-decoration-2 {
            position: absolute;
            top: -30px;
            left: -30px;
            opacity: 0.1;
            color: #fff;
            z-index: 1;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        @media (max-width: 768px) {
            .hero-body {
                flex-direction: column;
                text-align: center;
            }
            .hero-title h1 {
                font-size: 2.5rem;
            }
            .money-img {
                max-width: 150px;
                margin-bottom: 20px;
            }
            .menu-container {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
            <span class="system-name text-warning">AMSS++</span> 
            สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2
        </div>
        
        <div class="user-info">
            <div>
                ผู้ใช้ : <?php echo htmlspecialchars($_SESSION['fullname']); ?> 
                (<span class="user-role">**<?php echo $_SESSION['role']; ?>**</span>)
                <a href="Logout.php" class="btn-logout" onclick="return confirm('ยืนยันออกจากระบบ?');">
                    <i class="fa-solid fa-power-off"></i> ออก
                </a>
            </div>
            <small class="text-white-50"><?php echo thai_date(time()); ?></small>
        </div>
        </div>

    <div class="sub-header">
        <i class="fa-solid fa-coins me-2"></i> ระบบการเงินและบัญชี
    </div>

    <nav class="navbar-custom">
        <div class="container-fluid menu-container">
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    
                    // --- 1. Dropdown: ตั้งค่าระบบ ---
                    if ($row['name'] == 'ตั้งค่าระบบ') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="officers.php">เจ้าหน้าที่การเงินฯ</a></li>';
                        echo '    <li><a class="dropdown-item" href="yearbudget.php">ปีงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="plan.php">แผนงาน</a></li>';
                        echo '    <li><a class="dropdown-item" href="Projectoutcomes.php">ผลผลิตโครงการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Activity.php">กิจกรรมหลัก</a></li>';
                        echo '    <li><a class="dropdown-item" href="Sourcemoney.php">แหล่งของเงิน</a></li>';
                        echo '    <li><a class="dropdown-item" href="Expensesbudget.php">งบรายจ่าย</a></li>';
                        echo '    <li><a class="dropdown-item" href="Mainmoney.php">ประเภท(หลัก)ของเงิน</a></li>';
                        echo '    <li><a class="dropdown-item" href="Subtypesmoney.php">ประเภท(ย่อย)ของเงิน</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    } 
                    // --- 2. Dropdown: ทะเบียนรับ ---
                    elseif ($row['name'] == 'ทะเบียนรับ') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Budgetallocation.php">รับการจัดสรรงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Receivebudget.php">รับเงินงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Receiveoffbudget.php">รับเงินนอกงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Receivenational.php">รับเงินรายได้แผ่นดิน</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    } 
                    // --- 3. Dropdown: ทะเบียนขอเบิก ---
                    elseif ($row['name'] == 'ทะเบียนขอเบิก') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Withdrawproject.php">ทะเบียนขอเบิก/ขอยืมเงินโครงการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="ProjectRefundRegistration.php">***ทะเบียนคืนเงินโครงการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="TreasuryWithdrawal.php">ทะเบียนขอเบิกเงินคงคลัง</a></li>';
                        echo '    <li><a class="dropdown-item" href="TreasuryRefundRegister.php">***ทะเบียนคืนเงินคงคลัง</a></li>';
                        echo '    <li><a class="dropdown-item" href="Withdrawtheappeal.php">***ยกเลิกฎีกา</a></li>';
                        echo '    <li><a class="dropdown-item" href="Fundrolloverregister.php">ทะเบียนเงินกันเหลื่อมปี</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    }
                    // --- 4. Dropdown: ทะเบียนจ่าย ---
                    elseif ($row['name'] == 'ทะเบียนจ่าย') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Authorizebudgetexpenditures.php">สั่งจ่ายเงินงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Orderpaymentoutsidethebudget.php">สั่งจ่ายเงินนอกงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Orderpaymentofstaterevenue.php">สั่งจ่ายเงินรายได้แผ่นดิน</a></li>';
                        echo '    <li><a class="dropdown-item" href="Governmentadvancefunds.php">เงินทดรองราชการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Approvedformaintypepayment.php">อนุมัติจ่ายเงินประเภทหลัก</a></li>';
                        echo '    <li><a class="dropdown-item" href="Approved for governmentadvancepayment.php">อนุมัติจ่ายเงินทดรองราชการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Major type of payment.php">จ่ายเงินประเภทหลัก</a></li>';
                        echo '    <li><a class="dropdown-item" href="Advance payment for government service.php">จ่ายเงินทดรองราชการ</a></li>';
                        echo '  </ul>';
                        echo '</div>';

                        // --- 5. เพิ่ม Dropdown: เปลี่ยนแปลงสถานะ ---
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa-solid fa-pen-to-square"></i>'; 
                        echo '    <span>เปลี่ยนแปลงสถานะ</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Budget.php">เงินงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Off-budget funds.php">เงินนอกงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="National_revenue.php">เงินรายได้แผ่นดิน</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    }
                    // --- 6. Dropdown: ตรวจสอบ ---
                    elseif ($row['name'] == 'ตรวจสอบ') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Check budget allocation.php">ตรวจสอบการจัดสรรงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Check the periodic financial report.php">รายงานเงินประจำงวด</a></li>';
                        echo '    <li><a class="dropdown-item" href="Check main payment type.php">จ่ายเงินประเภทหลัก</a></li>';
                        echo '    <li><a class="dropdown-item" href="Check the government advance payment.php">จ่ายเงินทดรองราชการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="The appeal number does not exist in the system.php">เลขที่ฎีกาที่ไม่มีในระบบ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Appeals regarding project termination classified by invoice.php">ฎีกากับการตัดโครงการจำแนกตามใบงวด</a></li>';
                        echo '    <li><a class="dropdown-item" href="Supreme Court Rulings and References for Reimbursement Requests Classified by Ruling.php">ฎีกากับการอ้างอิงการขอเบิกจำแนกตามฎีกา</a></li>';
                        echo '    <li><a class="dropdown-item" href="Withdrawal requests that have not yet been submitted for approval.php">รายการขอเบิกฯที่ยังไม่ได้วางฎีกา</a></li>';
                        echo '    <li><a class="dropdown-item" href="Requisition items with incorrect installment vouchers.php">รายการขอเบิกฯที่วางฎีกาผิดใบงวด</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    }
                    // --- 7. Dropdown: รายงาน (เพิ่มใหม่) ---
                    elseif ($row['name'] == 'รายงาน') {
                        echo '<div class="dropdown">';
                        echo '  <a href="#" class="menu-link dropdown-toggle" data-bs-toggle="dropdown">';
                        echo '    <i class="fa ' . $row["icon"] . '"></i>';
                        echo '    <span>' . $row["name"] . '</span>';
                        echo '  </a>';
                        echo '  <ul class="dropdown-menu">';
                        echo '    <li><a class="dropdown-item" href="Budget allocation report.php">รายงานการจัดสรรงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Expenditure report categorized by project.php">รายงานการใช้จ่ายจำแนกตามโครงการ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Annuity register.php">ทะเบียนเงินงวด</a></li>';
                        echo '    <li><a class="dropdown-item" href="Expenditure report categorized by budget code.php">รายงานการใช้จ่ายจำแนกตามรหัสงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Expenditure report categorized by type of.php">รายงานการใช้จ่ายจำแนกตามประเภทรายการจ่าย</a></li>';
                        echo '    <li><a class="dropdown-item" href="Daily cash balance report.php">รายงานเงินคงเหลือประจำวัน</a></li>';
                        echo '    <li><a class="dropdown-item" href="cash book.php">สมุดเงินสด</a></li>';
                        echo '    <li><a class="dropdown-item" href="budget report.php">รายงานเงินงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="Report money outside the budget.php">รายงานเงินนอกงบประมาณ</a></li>';
                        echo '    <li><a class="dropdown-item" href="State income report.php">รายงานเงินรายได้แผ่นดิน</a></li>';
                        echo '    <li><a class="dropdown-item" href="Loan Report.php">รายงานลูกหนี้เงินยืม</a></li>';
                        echo '  </ul>';
                        echo '</div>';
                    }
                    else {
                        // เมนูปกติ
                        echo '<a href="' . $row["link"] . '" class="menu-link">';
                        echo '<i class="fa ' . $row["icon"] . '"></i>';
                        echo '<span>' . $row["name"] . '</span>';
                        echo '</a>';
                    }

                }
            } else {
                echo '<span class="text-muted p-3">ไม่มีเมนู</span>';
            }
            ?>
        </div>
    </nav>

    <div class="container main-content">
        <div class="hero-card">
            <div class="bg-decoration">
                <i class="fa-solid fa-sack-dollar fa-10x"></i>
            </div>
            <div class="bg-decoration-2">
                 <i class="fa-solid fa-chart-line fa-8x"></i>
            </div>

            <div class="hero-body">
                <div class="money-img-container">
                    <img src="https://cdn-icons-png.flaticon.com/512/2454/2454269.png" alt="Money Pile" class="money-img">
                </div>
                
                <div class="hero-title">
                    <h1>การเงิน<br>และบัญชี</h1>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>