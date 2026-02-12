<?php
session_start(); // 1. เริ่มต้น Session

// 2. ตรวจสอบว่าได้ Login หรือยัง ถ้ายังให้เด้งไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

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

// --- [ส่วนที่เพิ่มใหม่] ดึงปีงบประมาณที่ทำงานอยู่ (Active Year) ---
$active_year = date("Y") + 543; // ค่าเริ่มต้น
$sql_check_active = "SELECT budget_year FROM fiscal_years WHERE is_active = 1 LIMIT 1";
$result_check_active = $conn->query($sql_check_active);

if ($result_check_active->num_rows > 0) {
    $row_active = $result_check_active->fetch_assoc();
    $active_year = $row_active['budget_year'];
}
// -------------------------------------------------------------

// --- Logic จัดการข้อมูล (CRUD) ---

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM major_payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Major type of payment.php");
    exit();
}

// 2. สลับสถานะ "อนุมัติ" (สำหรับการทดสอบ Admin)
if (isset($_GET['toggle_approval_id'])) {
    $id = $_GET['toggle_approval_id'];
    $current = $_GET['current_status'];
    $new_status = 'pending';
    if ($current == 'pending') $new_status = 'approved';
    elseif ($current == 'approved') $new_status = 'rejected';
    elseif ($current == 'rejected') $new_status = 'pending';

    $stmt = $conn->prepare("UPDATE major_payments SET approval_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: Major type of payment.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล (รวมถึงการบันทึกการจ่ายเงิน)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pay_order = $_POST['pay_order'];
    $doc_date = $_POST['doc_date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $payment_type = $_POST['payment_type'];
    
    // รับค่าสถานะ
    $approval_status = $_POST['approval_status']; 
    $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : 'unpaid';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // [แก้ไข] เพิ่ม budget_year ลงในคำสั่ง INSERT
        $stmt = $conn->prepare("INSERT INTO major_payments (budget_year, pay_order, doc_date, description, amount, payment_type, approval_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdsss", $active_year, $pay_order, $doc_date, $description, $amount, $payment_type, $approval_status, $payment_status);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE major_payments SET pay_order=?, doc_date=?, description=?, amount=?, payment_type=?, approval_status=?, payment_status=? WHERE id=?");
        $stmt->bind_param("issdsssi", $pay_order, $doc_date, $description, $amount, $payment_type, $approval_status, $payment_status, $id);
        $stmt->execute();
    }
    header("Location: Major type of payment.php");
    exit();
}

// --- [แก้ไข] ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM major_payments WHERE budget_year = ? ORDER BY pay_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// ฟังก์ชันวันที่ไทยย่อ
function thai_date_short($date_str) {
    if(!$date_str || $date_str == '0000-00-00') return "";
    $timestamp = strtotime($date_str);
    $thai_month_arr = array("0"=>"","1"=>"ม.ค.","2"=>"ก.พ.","3"=>"มี.ค.","4"=>"เม.ย.","5"=>"พ.ค.","6"=>"มิ.ย.","7"=>"ก.ค.","8"=>"ส.ค.","9"=>"ก.ย.","10"=>"ต.ค.","11"=>"พ.ย.","12"=>"ธ.ค.");
    $d = str_pad(date("j", $timestamp), 2, '0', STR_PAD_LEFT); 
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543; 
    return "$d {$thai_month_arr[$m]} $y"; 
}

function thai_date_full($timestamp) {
    $thai_day_arr = array("อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์");
    $thai_month_arr = array("0"=>"","1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน","7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม");
    $d = date("j", $timestamp);
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543;
    return "วัน" . $thai_day_arr[date("w", $timestamp)] . "ที่ $d $thai_month_arr[$m] พ.ศ. $y";
}

// *** เช็คหน้าปัจจุบัน ***
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จ่ายเงินประเภทหลัก - AMSS++</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /* Theme: Gold/Olive + White */
        :root {
            --primary-dark: #0A192F;
            --accent-yellow: #FFC107;
            --accent-gold: #c59d0a;
            --bg-light: #f4f7f6;
            --menu-bg: #212529;
            --header-gold: #8B8000; /* สีทองเข้ม */
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-light);
            color: #333;
        }
        
        .top-header { background-color: var(--primary-dark); color: white; padding: 10px 20px; }
        
        /* User Info & Logout Button Styles */
        .user-info { font-size: 0.9rem; text-align: right; }
        .user-role { color: var(--accent-yellow); font-weight: 700; text-transform: uppercase; }
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
        .btn-logout:hover { background-color: #ff6b6b; color: white; }

        .sub-header { background: linear-gradient(90deg, var(--accent-yellow) 0%, var(--accent-gold) 100%); padding: 8px 20px; font-weight: 700; color: var(--primary-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom { background-color: var(--menu-bg); padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        /* Active Menu Logic */
        .nav-link-custom { color: #aaa; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .nav-link-custom:hover, .nav-link-custom.active { 
            color: #fff; 
            background-color: #333; 
            border-bottom-color: var(--accent-yellow); 
        }
        
        .dropdown-menu { border-radius: 0; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .dropdown-item:hover { background-color: var(--bg-light); color: var(--primary-dark); }
        
        /* [แก้ไข] Override Active Dropdown ให้เป็นสีดำ ตัวหนา */
        .dropdown-item.active, .dropdown-item:active {
            background-color: white; 
            color: black !important; /* บังคับตัวหนังสือสีดำ */
            font-weight: bold !important; /* บังคับตัวหนา */
        }

        .content-card { background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; margin-top: 30px; border-top: 5px solid var(--accent-yellow); }
        
        .page-title { color: #008080; font-weight: 700; text-align: center; margin-bottom: 25px; font-size: 1.4rem; } 
        
        /* --- Table Styles (Dark Gold / White) --- */
        .table-custom th { 
            background-color: var(--header-gold); 
            color: white; 
            font-weight: 500; 
            text-align: center; 
            vertical-align: middle; 
            border: 1px solid rgba(255,255,255,0.2); 
            font-size: 0.85rem; 
            padding: 8px 4px;
        }
        .table-custom td { 
            vertical-align: middle; 
            border-bottom: 1px solid #f0f0f0; 
            padding: 6px 4px; 
            font-size: 0.85rem; 
            background-color: white !important;
        }
        
        /* Cancel Striped */
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-accent-bg: transparent; }
        .table-striped > tbody > tr:nth-of-type(even) > * { --bs-table-accent-bg: transparent; }
        
        .td-center { text-align: center; }
        .td-right { text-align: right; }
        .td-left { text-align: left; }

        .btn-add { background-color: #efefef; color: #333; border: 1px solid #ccc; padding: 4px 15px; font-weight: 600; box-shadow: 0 2px 2px rgba(0,0,0,0.1); transition: 0.2s; text-decoration: none; display: inline-block; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn-add:hover { background-color: #e0e0e0; color: #000; }
        
        .action-btn { border: none; background: none; cursor: pointer; transition: 0.2s; font-size: 1rem; padding: 0; }
        .btn-edit { color: #f0ad4e; font-size: 1.1rem; } 
        .btn-delete { color: #dc3545; font-size: 1.1rem; } 
        .btn-detail { color: #6c757d; font-size: 1.1rem; } 
        .action-btn:hover { transform: scale(1.2); }

        /* Status Boxes */
        .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; cursor: pointer; border: 1px solid #ccc; }
        .status-yellow { background-color: #ffff00; }
        .status-green { background-color: #00ff00; }
        .status-red { background-color: #ff0000; }

        /* Modal Styles */
        .form-yellow-bg { background-color: #fff9c4; padding: 20px; border-radius: 5px; border: 1px solid #eee; }
        .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; }
        .modal-header { background-color: transparent; border-bottom: none; }
        .modal-title-custom { color: #008080; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
        
        /* Legend */
        .legend-container { margin-top: 20px; font-size: 0.85rem; }
        .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
        .legend-box { width: 14px; height: 14px; margin-right: 8px; border: 1px solid #ccc; }
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center">
        <div><strong>Budget control system</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        
        <div class="user-info">
            <div>
                ผู้ใช้ : <?php echo htmlspecialchars($_SESSION['fullname']); ?> 
                (<span class="user-role">**<?php echo $_SESSION['role']; ?>**</span>)
                <a href="Logout.php" class="btn-logout" onclick="return confirm('ยืนยันออกจากระบบ?');">
                    <i class="fa-solid fa-power-off"></i> ออก
                </a>
            </div>
            <small class="text-white-50"><?php echo thai_date_full(time()); ?></small>
        </div>
        </div>

    <div class="sub-header">จ่ายเงินประเภทหลัก</div>

    <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom">รายการหลัก</a>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['officers.php', 'yearbudget.php', 'plan.php', 'Projectoutcomes.php', 'Activity.php', 'Sourcemoney.php', 'Expensesbudget.php', 'Mainmoney.php', 'Subtypesmoney.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="officers.php">เจ้าหน้าที่การเงินฯ</a></li>
                    <li><a class="dropdown-item" href="yearbudget.php">ปีงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="plan.php">แผนงาน</a></li>
                    <li><a class="dropdown-item" href="Projectoutcomes.php">ผลผลิตโครงการ</a></li>
                    <li><a class="dropdown-item" href="Activity.php">กิจกรรมหลัก</a></li>
                    <li><a class="dropdown-item" href="Sourcemoney.php">แหล่งของเงิน</a></li>
                    <li><a class="dropdown-item" href="Expensesbudget.php">งบรายจ่าย</a></li>
                    <li><a class="dropdown-item" href="Mainmoney.php">ประเภท(หลัก)ของเงิน</a></li>
                    <li><a class="dropdown-item" href="Subtypesmoney.php">ประเภท(ย่อย)ของเงิน</a></li>
                </ul>
            </div>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ทะเบียนรับ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budgetallocation.php">รับการจัดสรรงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receivebudget.php">รับเงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receiveoffbudget.php">รับเงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receivenational.php">รับเงินรายได้แผ่นดิน</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ทะเบียนขอเบิก</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="RequestforWithdrawalProjectLoan.php">ทะเบียนขอเบิก/ขอยืมเงินโครงการ</a></li>
                    <li><a class="dropdown-item" href="ProjectRefundRegistration.php">***ทะเบียนคืนเงินโครงการ</a></li>
                    <li><a class="dropdown-item" href="TreasuryWithdrawal.php">ทะเบียนขอเบิกเงินคงคลัง</a></li>
                    <li><a class="dropdown-item" href="TreasuryRefundRegister.php">***ทะเบียนคืนเงินคงคลัง</a></li>
                    <li><a class="dropdown-item" href="Withdrawtheappeal.php">***ยกเลิกฎีกา</a></li>
                    <li><a class="dropdown-item" href="Fundrolloverregister.php">ทะเบียนเงินกันเหลื่อมปี</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="nav-link-custom active dropdown-toggle" data-bs-toggle="dropdown">ทะเบียนจ่าย</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Authorizebudgetexpenditures.php">สั่งจ่ายเงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Orderpaymentoutsidethebudget.php">สั่งจ่ายเงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Orderpaymentofstaterevenue.php">สั่งจ่ายเงินรายได้แผ่นดิน</a></li>
                    <li><a class="dropdown-item" href="Governmentadvancefunds.php">เงินทดรองราชการ</a></li>
                    <li><a class="dropdown-item" href="Approvedformaintypepayment.php">อนุมัติจ่ายเงินประเภทหลัก</a></li>
                    <li><a class="dropdown-item" href="Approved for governmentadvancepayment.php">อนุมัติจ่ายเงินทดรองราชการ</a></li>
                    
                    <li><a class="dropdown-item <?php echo ($current_page == 'Major type of payment.php') ? 'active' : ''; ?>" href="Major type of payment.php">จ่ายเงินประเภทหลัก</a></li>
                    
                    <li><a class="dropdown-item" href="Advance payment for government service.php">จ่ายเงินทดรองราชการ</a></li>
                </ul>
            </div>

             <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">เปลี่ยนแปลงสถานะ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budget.php">เงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Off-budget funds.php">เงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="National_revenue.php">เงินรายได้แผ่นดิน</a></li>
                </ul>
            </div>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ตรวจสอบ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Check budget allocation.php">ตรวจสอบการจัดสรรงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Check the periodic financial report.php">รายงานเงินประจำงวด</a></li>
                    <li><a class="dropdown-item" href="Check main payment type.php">จ่ายเงินประเภทหลัก</a></li>
                    <li><a class="dropdown-item" href="Check the government advance payment.php">จ่ายเงินทดรองราชการ</a></li>
                    <li><a class="dropdown-item" href="The appeal number does not exist in the system.php">เลขที่ฎีกาที่ไม่มีในระบบ</a></li>
                    <li><a class="dropdown-item" href="Appeals regarding project termination classified by invoice.php">ฎีกากับการตัดโครงการจำแนกตามใบงวด</a></li>
                    <li><a class="dropdown-item" href="Supreme Court Rulings and References for Reimbursement Requests Classified by Ruling.php">ฎีกากับการอ้างอิงการขอเบิกจำแนกตามฎีกา</a></li>
                    <li><a class="dropdown-item" href="Withdrawal requests that have not yet been submitted for approval.php">รายการขอเบิกฯที่ยังไม่ได้วางฎีกา</a></li>
                    <li><a class="dropdown-item" href="Requisition items with incorrect installment vouchers.php">รายการขอเบิกฯที่วางฎีกาผิดใบงวด</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">รายงาน</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budget allocation report.php">รายงานการจัดสรรงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by project.php">รายงานการใช้จ่ายจำแนกตามโครงการ</a></li>
                    <li><a class="dropdown-item" href="Annuity register.php">ทะเบียนเงินงวด</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by budget code.php">รายงานการใช้จ่ายจำแนกตามรหัสงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by type of.php">รายงานการใช้จ่ายจำแนกตามประเภทรายการจ่าย</a></li>
                    <li><a class="dropdown-item" href="Daily cash balance report.php">รายงานเงินคงเหลือประจำวัน</a></li>
                    <li><a class="dropdown-item" href="cash book.php">สมุดเงินสด</a></li>
                    <li><a class="dropdown-item" href="budget report.php">รายงานเงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Report money outside the budget.php">รายงานเงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="State income report.php">รายงานเงินรายได้แผ่นดิน</a></li>
                    <li><a class="dropdown-item" href="Loan Report.php">รายงานลูกหนี้เงินยืม</a></li>
                </ul>
            </div>

            <a href="#" class="nav-link-custom ms-auto">คู่มือ</a>
        </div>
    </div>

    <div class="container-fluid pb-5 px-3">
        <div class="content-card">
            
            <h2 class="page-title">จ่ายเงินประเภทหลัก ปีงบประมาณ <?php echo $active_year; ?></h2>

            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-add" onclick="openAddModal()">
                    + เพิ่มรายการ
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-custom">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ที่</th>
                            <th style="width: 10%;">วดป</th>
                            <th style="width: 35%;">รายการ</th>
                            <th style="width: 10%;">จำนวนเงิน</th>
                            <th style="width: 10%;">ประเภทเงิน</th>
                            <th style="width: 5%;">ราย<br>ละเอียด</th>
                            <th style="width: 5%;">อนุมัติ</th>
                            <th style="width: 5%;">จ่ายเงิน</th>
                            <th style="width: 5%;">บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                
                                // สถานะอนุมัติ (Approval)
                                $app_class = 'status-yellow';
                                $app_text = 'รอการอนุมัติ';
                                if($row['approval_status'] == 'approved') { $app_class = 'status-green'; $app_text = 'อนุมัติแล้ว'; }
                                elseif($row['approval_status'] == 'rejected') { $app_class = 'status-red'; $app_text = 'ไม่อนุมัติ'; }

                                // สถานะจ่ายเงิน (Payment)
                                $pay_class = 'status-red';
                                $pay_text = 'ยังไม่ได้จ่ายเงิน';
                                if($row['payment_status'] == 'paid') { $pay_class = 'status-green'; $pay_text = 'จ่ายเงินแล้ว'; }

                                echo "<tr>";
                                echo "<td class='td-center'>" . $row['pay_order'] . "</td>";
                                echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                                echo "<td class='td-left'>" . $row['description'] . "</td>";
                                echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                                echo "<td class='td-center'>" . $row['payment_type'] . "</td>";
                                
                                // รายละเอียด
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-detail" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                                echo "</td>";

                                // อนุมัติ (Toggle Status - จำลองให้ Admin กดเล่นได้)
                                echo "<td class='td-center'>";
                                echo '<a href="?toggle_approval_id='.$row['id'].'&current_status='.$row['approval_status'].'" title="'.$app_text.'"><div class="status-box '.$app_class.'"></div></a>';
                                echo "</td>";

                                // จ่ายเงิน (Display Only - เปลี่ยนผ่านปุ่มบันทึก)
                                echo "<td class='td-center'>";
                                echo '<div class="status-box '.$pay_class.'" title="'.$pay_text.'"></div>';
                                echo "</td>";

                                // บันทึก (Edit/Process Payment) - รูปดินสอ
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-edit" onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')" title="บันทึกการจ่าย"><i class="fa-solid fa-pen"></i></button>';
                                echo "</td>";

                                echo "</tr>";
                            }

                        } else {
                            echo "<tr><td colspan='9' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="legend-container text-center">
                <div class="legend-item justify-content-center">
                    <div class="legend-box status-yellow"></div> <span>รอการอนุมัติ</span>
                </div>
                <div class="legend-item justify-content-center">
                    <div class="legend-box status-green"></div> <span>อนุมัติให้จ่ายเงินได้ / จ่ายเงินแล้ว</span>
                </div>
                <div class="legend-item justify-content-center">
                    <div class="legend-box status-red"></div> <span>ไม่อนุมัติ / ยังไม่ได้จ่ายเงิน</span>
                </div>
            </div>
            
            <div class="mt-4 p-3 bg-light border rounded">
                <p><strong>คำอธิบาย:</strong> หน้านี้จะมีสิทธิ์เฉพาะผู้ที่ได้รับสิทธิ์ในการจ่ายเงินตามการตั้งค่าระบบไว้เบื้องต้นแล้วเท่านั้น เมื่อจะจ่ายเงินประเภทหลักรายการใด ให้สังเกตสัญลักษณ์ในช่องอนุมัติ ถ้าเป็นสี <span class="text-success fw-bold">เขียว</span> แสดงว่าผ่านการอนุมัติให้จ่ายแล้ว แต่ถ้าเป็นสี <span class="text-danger fw-bold">แดง</span> แสดงว่าผู้อนุมัติไม่อนุมัติให้จ่าย ถ้าเป็นสี <span class="text-warning fw-bold" style="background-color:#333;">เหลือง</span> แสดงว่ารอการอนุมัติ</p>
                <p>ถ้าตรวจสอบแล้ว ผู้จ่ายเงินต้องการจ่ายรายการใด ให้เลือกที่ <i class="fa-solid fa-pen text-warning"></i> แล้วบันทึกในส่วนของการจ่าย เมื่อบันทึกเรียบร้อยแล้ว ช่องจ่ายเงินที่เดิมปรากฏสัญลักษณ์ <span class="text-danger fw-bold">สีแดง</span> ก็จะเปลี่ยนเป็น <span class="text-success fw-bold">สีเขียว</span> หมายความว่ารายการดังกล่าวได้จ่ายเงินให้ผู้มีสิทธิ์เรียบร้อยแล้ว</p>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <h5 class="modal-title-custom" id="modalTitle">บันทึกการจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?></h5>
                </div>
                <div class="modal-body form-yellow-bg mx-3 mb-3">
                    <form action="Major type of payment.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">ที่</div>
                            <div class="col-md-2">
                                <input type="number" name="pay_order" id="pay_order" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">วดป</div>
                            <div class="col-md-3">
                                <input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">รายการ</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1">บาท</div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">ประเภทเงิน</div>
                            <div class="col-md-4">
                                <select name="payment_type" id="payment_type" class="form-select form-select-sm">
                                    <option value="เงินงบประมาณ">เงินงบประมาณ</option>
                                    <option value="เงินนอกงบประมาณ">เงินนอกงบประมาณ</option>
                                    <option value="เงินรายได้แผ่นดิน">เงินรายได้แผ่นดิน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">สถานะอนุมัติ</div>
                            <div class="col-md-4">
                                <select name="approval_status" id="approval_status" class="form-select form-select-sm">
                                    <option value="pending">รอการอนุมัติ</option>
                                    <option value="approved">อนุมัติแล้ว</option>
                                    <option value="rejected">ไม่อนุมัติ</option>
                                </select>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">สถานะการจ่ายเงิน</div>
                            <div class="col-md-9">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_status" id="pay_unpaid" value="unpaid" checked>
                                    <label class="form-check-label text-danger fw-bold" for="pay_unpaid">ยังไม่ได้จ่าย</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_status" id="pay_paid" value="paid">
                                    <label class="form-check-label text-success fw-bold" for="pay_paid">จ่ายเงินแล้ว</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-secondary border-dark text-dark" style="background-color: #e0e0e0;">บันทึก</button>
                            <button type="button" class="btn btn-secondary border-dark text-dark" style="background-color: #e0e0e0;" data-bs-dismiss="modal">ย้อนกลับ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <h5 class="modal-title-custom">รายละเอียด</h5>
                </div>
                <div class="modal-body form-yellow-bg mx-3 mb-3">
                    <div class="row mb-2">
                        <div class="col-md-3 form-label-custom">รายการ :</div>
                        <div class="col-md-9" id="view_description"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 form-label-custom">จำนวนเงิน :</div>
                        <div class="col-md-9" id="view_amount"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 form-label-custom">ประเภทเงิน :</div>
                        <div class="col-md-9" id="view_payment_type"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3 form-label-custom">สถานะ :</div>
                        <div class="col-md-9" id="view_status"></div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('form_action').value = 'add';
            document.getElementById('edit_id').value = '';
            // [แก้ไข] แสดงปีงบประมาณใน JavaScript Modal Title
            document.getElementById('modalTitle').innerHTML = 'เพิ่มรายการจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?>';
            document.querySelector('#addModal form').reset();
            
            var myModal = new bootstrap.Modal(document.getElementById('addModal'));
            myModal.show();
        }

        function openEditModal(data) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('edit_id').value = data.id;
            document.getElementById('modalTitle').innerHTML = 'บันทึกการจ่ายเงิน / แก้ไข';
            
            document.getElementById('pay_order').value = data.pay_order;
            document.getElementById('doc_date').value = data.doc_date;
            document.getElementById('description').value = data.description;
            document.getElementById('amount').value = data.amount;
            document.getElementById('payment_type').value = data.payment_type;
            document.getElementById('approval_status').value = data.approval_status;
            
            if(data.payment_status == 'paid') {
                document.getElementById('pay_paid').checked = true;
            } else {
                document.getElementById('pay_unpaid').checked = true;
            }

            var myModal = new bootstrap.Modal(document.getElementById('addModal'));
            myModal.show();
        }

        function openDetailModal(data) {
            document.getElementById('view_description').innerText = data.description;
            document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
            document.getElementById('view_payment_type').innerText = data.payment_type;
            
            let statusText = 'รอการอนุมัติ';
            if(data.status == 'approved') statusText = 'อนุมัติแล้ว';
            else if(data.status == 'rejected') statusText = 'ไม่อนุมัติ';
            document.getElementById('view_status').innerText = statusText;

            var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
            myModal.show();
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>