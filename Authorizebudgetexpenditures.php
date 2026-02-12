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

// --- ดึงปีงบประมาณที่ทำงานอยู่ (Active Year) ---
$active_year = date("Y") + 543; // ค่าเริ่มต้น
$sql_check_active = "SELECT budget_year FROM fiscal_years WHERE is_active = 1 LIMIT 1";
$result_check_active = $conn->query($sql_check_active);

if ($result_check_active->num_rows > 0) {
    $row_active = $result_check_active->fetch_assoc();
    $active_year = $row_active['budget_year'];
}
// -------------------------------------------------------------

// --- Logic จัดการข้อมูล (CRUD) ---

// 1. ลบข้อมูล (แก้ไขเพิ่มเติม: ลบข้อมูลที่เชื่อมโยงด้วย)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    // [Step 1] ดึงข้อมูล exp_order และ budget_year ก่อนลบ เพื่อเอาไปลบในอีกตาราง
    $stmt_get = $conn->prepare("SELECT exp_order, budget_year FROM budget_expenditures WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    
    if ($result_get->num_rows > 0) {
        $row_del = $result_get->fetch_assoc();
        $del_exp_order = $row_del['exp_order'];
        $del_budget_year = $row_del['budget_year'];

        // [Step 2] ลบข้อมูลจากตารางหลัก (budget_expenditures)
        $stmt = $conn->prepare("DELETE FROM budget_expenditures WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // [Step 3] ลบข้อมูลที่เชื่อมโยงในตาราง approved_main_payments
            $stmt_link_del = $conn->prepare("DELETE FROM approved_main_payments WHERE pay_order = ? AND budget_year = ? AND payment_type = 'เงินงบประมาณ'");
            $stmt_link_del->bind_param("ii", $del_exp_order, $del_budget_year);
            $stmt_link_del->execute();
        }
    }

    header("Location: Authorizebudgetexpenditures.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exp_order = $_POST['exp_order'];
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $ref_withdraw_no = $_POST['ref_withdraw_no'];
    $ref_petition_no = $_POST['ref_petition_no'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // [ส่วนที่ 1] บันทึกลงตารางหลัก (ทะเบียนสั่งจ่ายเงินงบประมาณ)
        $stmt = $conn->prepare("INSERT INTO budget_expenditures (budget_year, exp_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, description, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) { die("Error prepare (budget_expenditures): " . $conn->error); }
        $stmt->bind_param("iisssssd", $active_year, $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount);
        $stmt->execute();

        // [ส่วนที่ 2] ส่งข้อมูลไปที่หน้า Approvedformaintypepayment.php (ตาราง approved_main_payments)
        // [แก้ไข] เพิ่ม doc_no, ref_withdraw_no, ref_petition_no ลงในคำสั่ง INSERT
        $payment_type_fixed = 'เงินงบประมาณ'; 
        $approval_status_init = 'pending';    
        $payment_status_init = 'unpaid';      

        $sql_link = "INSERT INTO approved_main_payments (budget_year, pay_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, description, amount, payment_type, approval_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_link = $conn->prepare($sql_link);
        
        if ($stmt_link === false) { 
             die("Error prepare (approved_main_payments): " . $conn->error . " (กรุณาเช็คว่าตาราง approved_main_payments มีคอลัมน์ doc_no, ref_withdraw_no, ref_petition_no หรือไม่)");
        } else {
            // ผูกตัวแปรเพิ่ม: doc_no(s), ref_withdraw_no(s), ref_petition_no(s)
            $stmt_link->bind_param("iisssssdsss", $active_year, $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $payment_type_fixed, $approval_status_init, $payment_status_init);
            $stmt_link->execute();
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        
        // ดึงข้อมูลเดิมก่อนแก้ไข เพื่อเอา exp_order เดิมไปแก้ไขในตารางเชื่อมโยง
        $stmt_old = $conn->prepare("SELECT exp_order, budget_year FROM budget_expenditures WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $old_exp_order = $old_data['exp_order'];
        $current_budget_year = $old_data['budget_year'];

        // อัปเดตตารางหลัก
        $stmt = $conn->prepare("UPDATE budget_expenditures SET exp_order=?, doc_date=?, doc_no=?, ref_withdraw_no=?, ref_petition_no=?, description=?, amount=? WHERE id=?");
        $stmt->bind_param("isssssdi", $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $id);
        
        if ($stmt->execute()) {
            // [แก้ไข] อัปเดตข้อมูลในตาราง approved_main_payments ให้ครบทุกช่อง
            $stmt_update_link = $conn->prepare("UPDATE approved_main_payments SET pay_order=?, doc_date=?, doc_no=?, ref_withdraw_no=?, ref_petition_no=?, description=?, amount=? WHERE pay_order=? AND budget_year=? AND payment_type='เงินงบประมาณ'");
            $stmt_update_link->bind_param("isssssdii", $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $old_exp_order, $current_budget_year);
            $stmt_update_link->execute();
        }
    }
    header("Location: Authorizebudgetexpenditures.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM budget_expenditures WHERE budget_year = ? ORDER BY exp_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_amount = 0;

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
    <title>ทะเบียนสั่งจ่ายเงินงบประมาณ - AMSS++</title>
    
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
        
        /* Title Color */
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
        
        /* ยกเลิก Striped */
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-accent-bg: transparent; }
        .table-striped > tbody > tr:nth-of-type(even) > * { --bs-table-accent-bg: transparent; }
        
        /* Footer Row Style */
        .total-row td {
            font-weight: bold;
            color: #333;
            border-top: 2px solid #ddd;
            background-color: #f8f9fa !important;
        }
        
        .td-center { text-align: center; }
        .td-right { text-align: right; }
        .td-left { text-align: left; }

        .btn-add { background-color: #efefef; color: #333; border: 1px solid #ccc; padding: 4px 15px; font-weight: 600; box-shadow: 0 2px 2px rgba(0,0,0,0.1); transition: 0.2s; text-decoration: none; display: inline-block; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn-add:hover { background-color: #e0e0e0; color: #000; }
        
        .action-btn { border: none; background: none; cursor: pointer; transition: 0.2s; font-size: 1rem; padding: 0; }
        .btn-edit { color: #f0ad4e; font-size: 1.1rem; } 
        .btn-delete { color: #dc3545; font-size: 1.1rem; } 
        .btn-detail { color: #6c757d; font-size: 1.1rem; } 
        .btn-print { color: #0d6efd; font-size: 1.1rem; } 
        .action-btn:hover { transform: scale(1.2); }

        /* Modal Styles */
        .form-yellow-bg { background-color: #fff9c4; padding: 20px; border-radius: 5px; border: 1px solid #eee; }
        .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; }
        .modal-header { background-color: transparent; border-bottom: none; }
        .modal-title-custom { color: #008080; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
        
        .total-text { color: #d63384; font-weight: bold; font-size: 0.8rem;}
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

    <div class="sub-header">ทะเบียนสั่งจ่ายเงินงบประมาณ</div>

    <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom">รายการหลัก</a>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
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
                    <li><a class="dropdown-item <?php echo ($current_page == 'Authorizebudgetexpenditures.php') ? 'active' : ''; ?>" href="Authorizebudgetexpenditures.php">สั่งจ่ายเงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Orderpaymentoutsidethebudget.php">สั่งจ่ายเงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Orderpaymentofstaterevenue.php">สั่งจ่ายเงินรายได้แผ่นดิน</a></li>
                    <li><a class="dropdown-item" href="Governmentadvancefunds.php">เงินทดรองราชการ</a></li>
                    <li><a class="dropdown-item" href="Approvedformaintypepayment.php">อนุมัติจ่ายเงินประเภทหลัก</a></li>
                    <li><a class="dropdown-item" href="Approved for governmentadvancepayment.php">อนุมัติจ่ายเงินทดรองราชการ</a></li>
                    <li><a class="dropdown-item" href="Major type of payment.php">จ่ายเงินประเภทหลัก</a></li>
                    <li><a class="dropdown-item" href="Advance payment for government service.php">จ่ายเงินทดรองราชการ</a></li>
                </ul>
            </div>

             <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">เปลี่ยนแปลงสถานะ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budget.php">เงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Off_budget_funds.php">เงินนอกงบประมาณ</a></li>
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
            
            <h2 class="page-title">ทะเบียนสั่งจ่ายเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h2>

            <div class="d-flex align-items-center mb-2">
                <button class="btn btn-add" onclick="openAddModal()">
                    เพิ่มรายการสั่งจ่าย
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-custom">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ที่</th>
                            <th style="width: 8%;">วดป</th>
                            <th style="width: 8%;">ที่เอกสาร</th>
                            <th style="width: 8%;">อ้างอิง<br>ขอเบิก</th>
                            <th style="width: 8%;">อ้างอิง<br>ฎีกา</th>
                            <th style="width: 30%;">รายการ</th>
                            <th style="width: 10%;">จำนวนเงิน</th>
                            <th style="width: 5%;">ราย<br>ละเอียด</th>
                            <th style="width: 4%;">ลบ</th>
                            <th style="width: 4%;">แก้ไข</th>
                            <th style="width: 5%;">พิมพ์ใบ<br>สั่งจ่าย</th>
                            <th style="width: 5%;">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                $total_amount += $row['amount'];
                                
                                echo "<tr>";
                                echo "<td class='td-center'>" . $row['exp_order'] . "</td>";
                                echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                                echo "<td class='td-center'>" . $row['doc_no'] . "</td>";
                                echo "<td class='td-center text-warning'>" . ($row['ref_withdraw_no'] ? $row['ref_withdraw_no'] : '') . "</td>"; 
                                echo "<td class='td-center text-danger'>" . ($row['ref_petition_no'] ? $row['ref_petition_no'] : '') . "</td>"; 
                                echo "<td class='td-left'>" . $row['description'] . "</td>";
                                echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                                
                                // รายละเอียด
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-detail" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                                echo "</td>";

                                // ลบ
                                echo "<td class='td-center'>";
                                echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'ยืนยันการลบรายการ? ข้อมูลในหน้าอนุมัติจ่ายจะถูกลบด้วย\')"><i class="fa-solid fa-xmark"></i></a>';
                                echo "</td>";

                                // แก้ไข
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-edit" onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-pen"></i></button>';
                                echo "</td>";

                                // พิมพ์
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-print" onclick="alert(\'พิมพ์ใบสั่งจ่าย\')"><i class="fa-solid fa-print"></i></button>';
                                echo "</td>";

                                // รวม (ถึงนี้)
                                echo "<td class='td-center total-text'>ถึงนี้</td>";
                                echo "</tr>";
                            }
                            
                            // Row รวมยอดสุดท้าย
                            echo "<tr class='total-row'>";
                            echo "<td colspan='6' class='text-center'>รวม</td>";
                            echo "<td class='td-right'>" . number_format($total_amount, 2) . "</td>";
                            echo "<td colspan='5'></td>";
                            echo "</tr>";

                        } else {
                            echo "<tr><td colspan='12' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <h5 class="modal-title-custom" id="modalTitle"> เพิ่มรายการสั่งจ่ายเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h5>
                </div>
                <div class="modal-body form-yellow-bg mx-3 mb-3">
                    <form action="Authorizebudgetexpenditures.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">ที่</div>
                            <div class="col-md-2">
                                <input type="number" name="exp_order" id="exp_order" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">วดป</div>
                            <div class="col-md-3">
                                <input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-3">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">อ้างอิงขอเบิก</div>
                            <div class="col-md-3">
                                <input type="text" name="ref_withdraw_no" id="ref_withdraw_no" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">อ้างอิงฎีกา</div>
                            <div class="col-md-3">
                                <input type="text" name="ref_petition_no" id="ref_petition_no" class="form-control form-control-sm">
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

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-secondary border-dark text-dark" style="background-color: #e0e0e0;">ตกลง</button>
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
                    <h5 class="modal-title-custom">รายละเอียดการสั่งจ่าย</h5>
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
            document.getElementById('modalTitle').innerHTML = 'ลงทะเบียน สั่งจ่ายเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?>';
            document.querySelector('form').reset();
            
            var myModal = new bootstrap.Modal(document.getElementById('addModal'));
            myModal.show();
        }

        function openEditModal(data) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('edit_id').value = data.id;
            document.getElementById('modalTitle').innerHTML = 'แก้ไข สั่งจ่ายเงินงบประมาณ';
            
            document.getElementById('exp_order').value = data.exp_order;
            document.getElementById('doc_date').value = data.doc_date;
            document.getElementById('doc_no').value = data.doc_no;
            document.getElementById('ref_withdraw_no').value = data.ref_withdraw_no;
            document.getElementById('ref_petition_no').value = data.ref_petition_no;
            document.getElementById('description').value = data.description;
            document.getElementById('amount').value = data.amount;

            var myModal = new bootstrap.Modal(document.getElementById('addModal'));
            myModal.show();
        }

        function openDetailModal(data) {
            document.getElementById('view_description').innerText = data.description;
            document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
            var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
            myModal.show();
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>