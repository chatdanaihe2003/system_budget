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

// --- สร้างโฟลเดอร์ uploads อัตโนมัติ ---
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// --- Logic จัดการข้อมูล (CRUD & Upload) ---

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ลบไฟล์จริง
    $sql_file = "SELECT file_name FROM receive_budget WHERE id = ?";
    $stmt_file = $conn->prepare($sql_file);
    $stmt_file->bind_param("i", $id);
    $stmt_file->execute();
    $res_file = $stmt_file->get_result();
    if ($row = $res_file->fetch_assoc()) {
        if (!empty($row['file_name']) && file_exists("uploads/" . $row['file_name'])) {
            unlink("uploads/" . $row['file_name']);
        }
    }

    $stmt = $conn->prepare("DELETE FROM receive_budget WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Receivebudget.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $receive_order = $_POST['receive_order'];
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $description = $_POST['description'];
    $transaction_type = $_POST['transaction_type'];
    $amount = $_POST['amount'];
    
    $file_name = null;
    
    // อัปโหลดไฟล์
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $ext = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid() . "_" . time() . "." . $ext; 
        if(move_uploaded_file($_FILES['file_upload']['tmp_name'], "uploads/" . $new_name)){
            $file_name = $new_name;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO receive_budget (receive_order, doc_date, doc_no, description, transaction_type, amount, file_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssds", $receive_order, $doc_date, $doc_no, $description, $transaction_type, $amount, $file_name);
        $stmt->execute();
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        
        if ($file_name) {
            $q = $conn->query("SELECT file_name FROM receive_budget WHERE id=$id");
            $old = $q->fetch_assoc();
            if(!empty($old['file_name']) && file_exists("uploads/".$old['file_name'])){
                unlink("uploads/".$old['file_name']);
            }
            
            $stmt = $conn->prepare("UPDATE receive_budget SET receive_order=?, doc_date=?, doc_no=?, description=?, transaction_type=?, amount=?, file_name=? WHERE id=?");
            $stmt->bind_param("issssdsi", $receive_order, $doc_date, $doc_no, $description, $transaction_type, $amount, $file_name, $id);
        } else {
            $stmt = $conn->prepare("UPDATE receive_budget SET receive_order=?, doc_date=?, doc_no=?, description=?, transaction_type=?, amount=? WHERE id=?");
            $stmt->bind_param("issssdi", $receive_order, $doc_date, $doc_no, $description, $transaction_type, $amount, $id);
        }
        $stmt->execute();
    }
    header("Location: Receivebudget.php");
    exit();
}

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM receive_budget ORDER BY receive_order ASC";
$result_data = $conn->query($sql_data);

// ฟังก์ชันวันที่
function thai_date_short($date_str) {
    if(!$date_str) return "";
    $timestamp = strtotime($date_str);
    $thai_month_arr = array("0"=>"","1"=>"ม.ค.","2"=>"ก.พ.","3"=>"มี.ค.","4"=>"เม.ย.","5"=>"พ.ค.","6"=>"มิ.ย.","7"=>"ก.ค.","8"=>"ส.ค.","9"=>"ก.ย.","10"=>"ต.ค.","11"=>"พ.ย.","12"=>"ธ.ค.");
    $d = date("j", $timestamp);
    $m = date("n", $timestamp);
    $y = (date("Y", $timestamp) + 543) - 2500; 
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
    <title>ทะเบียนรับเงินงบประมาณ - AMSS++</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-dark: #0A192F;
            --accent-yellow: #FFC107;
            --accent-gold: #c59d0a;
            --bg-light: #f4f7f6;
            --menu-bg: #212529;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-light);
            color: #333;
        }
        
        /* Header & Nav */
        .top-header { background-color: var(--primary-dark); color: white; padding: 10px 20px; }
        .sub-header { background: linear-gradient(90deg, var(--accent-yellow) 0%, var(--accent-gold) 100%); padding: 8px 20px; font-weight: 700; color: var(--primary-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom { background-color: var(--menu-bg); padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        /* CSS Active Menu Logic */
        .nav-link-custom { color: #aaa; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .nav-link-custom:hover, .nav-link-custom.active { 
            color: #fff; 
            background-color: #333; /* พื้นหลังเทาเข้ม */
            border-bottom-color: var(--accent-yellow); /* เส้นใต้เหลือง */
        }
        
        .dropdown-menu { border-radius: 0; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .dropdown-item:hover { background-color: var(--bg-light); color: var(--primary-dark); }
        
        .content-card { background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; margin-top: 30px; border-top: 5px solid var(--accent-yellow); }
        
        /* Title Color like Image (Pink/Purple) */
        .page-title { color: #d63384; font-weight: 700; text-align: center; margin-bottom: 25px; font-size: 1.4rem; }
        
        /* --- Table Styles (Gold Header like Image af6053.png) --- */
        .table-custom th { 
            background-color: #998a00; /* สีทองเข้ม */
            color: white; 
            font-weight: 500; 
            text-align: center; 
            vertical-align: middle; 
            border: 1px solid rgba(255,255,255,0.2); 
            font-size: 0.85rem; 
        }
        .table-custom td { 
            vertical-align: middle; 
            border-color: #eee; 
            padding: 8px; 
            font-size: 0.85rem; 
            background-color: white !important; /* บังคับพื้นหลังขาว */
        }
        
        /* ยกเลิก Striped (ลายทาง) เพื่อให้เหมือนภาพตัวอย่างที่เป็นสีขาวล้วน */
        .table-striped > tbody > tr:nth-of-type(odd) { --bs-table-accent-bg: transparent; }
        
        .td-center { text-align: center; }
        .td-right { text-align: right; }
        .td-left { text-align: left; }

        .btn-add { background-color: #0d6efd; color: white; border-radius: 50px; padding: 8px 25px; font-weight: 600; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2); transition: transform 0.2s; border: none; cursor: pointer; }
        .btn-add:hover { background-color: #0b5ed7; color: white; transform: translateY(-2px); }
        
        .action-btn { border: none; background: none; cursor: pointer; transition: 0.2s; font-size: 1rem; }
        .btn-edit { color: #ffc107; }
        .btn-delete { color: #dc3545; }
        .btn-file { color: #198754; } /* Green upload icon */
        .btn-print { color: #0d6efd; }
        .action-btn:hover { transform: scale(1.2); }

        .modal-header { background-color: var(--primary-dark); color: white; }
        .btn-close { filter: invert(1); }
        
        .total-text { color: black; font-weight: normal; } 
        .warning-icon { color: #dc3545; margin-left: 5px; }

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
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center">
        <div><strong>AMSS++</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        
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

    <div class="sub-header">ทะเบียนรับเงินงบประมาณ</div>

    <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">รายการหลัก</a>
            
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
                <a href="#" class="nav-link-custom active dropdown-toggle" data-bs-toggle="dropdown">ทะเบียนรับ</a>
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
                <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ทะเบียนจ่าย</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Authorizebudgetexpenditures.php">สั่งจ่ายเงินงบประมาณ</a></li>
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

    <div class="container-fluid pb-5 px-4">
        <div class="content-card">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div style="width: 100px;"></div> 
                <h2 class="page-title m-0">ทะเบียนรับเงินงบประมาณ ปีงบประมาณ 2568</h2>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มรายการ
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ที่/งวด</th>
                            <th style="width: 8%;">ว/ด/ป</th>
                            <th style="width: 10%;">ที่เอกสาร</th>
                            <th style="width: 35%;">รายการ</th>
                            <th style="width: 15%;">ลักษณะรายการ</th>
                            <th style="width: 10%;">จำนวนเงิน</th>
                            <th style="width: 5%;">ราย<br>ละเอียด</th>
                            <th style="width: 4%;">File</th>
                            <th style="width: 4%;">ลบ</th>
                            <th style="width: 4%;">แก้ไข</th>
                            <th style="width: 4%;">พิมพ์</th>
                            <th style="width: 4%;">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                $has_file = !empty($row['file_name']);
                                echo "<tr>";
                                echo "<td class='td-center'>" . $row['receive_order'] . "</td>";
                                echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                                echo "<td class='td-left'>" . $row['doc_no'] . "</td>";
                                echo "<td class='td-left'>" . $row['description'] . " <i class='fa-solid fa-triangle-exclamation warning-icon'></i></td>";
                                echo "<td class='td-center'>" . $row['transaction_type'] . "</td>";
                                echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                                
                                // ปุ่มรายละเอียด
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn" title="รายละเอียด" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                        <i class="fa-regular fa-rectangle-list"></i>
                                      </button>';
                                echo "</td>";

                                // ปุ่ม File
                                echo "<td class='td-center'>";
                                if ($has_file) {
                                    echo '<a href="uploads/'.$row['file_name'].'" target="_blank" class="action-btn btn-file" title="ดาวน์โหลดไฟล์"><i class="fa-solid fa-arrow-up-from-bracket"></i></a>';
                                } else {
                                    echo '<button class="action-btn btn-file" title="คลิกเพื่อแนบไฟล์" onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                            <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                          </button>';
                                }
                                echo "</td>";

                                // ปุ่มลบ
                                echo "<td class='td-center'>";
                                echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-xmark"></i></a>';
                                echo "</td>";

                                // ปุ่มแก้ไข
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-edit" title="แก้ไข" onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-pen"></i></button>';
                                echo "</td>";

                                // ปุ่มพิมพ์
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-print" title="พิมพ์" onclick="printItem('.$row['id'].')"><i class="fa-solid fa-print"></i></button>';
                                echo "</td>";

                                // รวม
                                echo "<td class='td-center total-text'>ถึงนี้</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='12' class='text-center py-4 text-muted'>ยังไม่มีข้อมูล</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="Receivebudget.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="form_action" value="add">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle"><i class="fa-solid fa-file-invoice-dollar"></i> เพิ่มรายการรับ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ที่/งวด</label>
                                <input type="number" name="receive_order" id="receive_order" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">ว/ด/ป</label>
                                <input type="date" name="doc_date" id="doc_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ที่เอกสาร</label>
                            <input type="text" name="doc_no" id="doc_no" class="form-control" placeholder="เช่น ฎีกาที่..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รายการ</label>
                            <input type="text" name="description" id="description" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ลักษณะรายการ</label>
                            <select name="transaction_type" id="transaction_type" class="form-select" required>
                                <option value="รับเช็ค/เงินฝากธนาคาร">รับเช็ค/เงินฝากธนาคาร</option>
                                <option value="รับเงินสด">รับเงินสด</option>
                                <option value="โอนเงิน">โอนเงิน</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">จำนวนเงิน</label>
                            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">แนบไฟล์ (ถ้ามี)</label>
                            <input type="file" name="file_upload" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-circle-info"></i> รายละเอียด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>รายการ:</strong> <span id="detail_desc"></span></p>
                    <p><strong>ที่เอกสาร:</strong> <span id="detail_doc_no"></span></p>
                    <div id="file_download_area" class="text-center mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(data) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('edit_id').value = data.id;
            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> แก้ไขรายการ';
            
            document.getElementById('receive_order').value = data.receive_order;
            document.getElementById('doc_date').value = data.doc_date;
            document.getElementById('doc_no').value = data.doc_no;
            document.getElementById('description').value = data.description;
            document.getElementById('transaction_type').value = data.transaction_type;
            document.getElementById('amount').value = data.amount;
            
            var myModal = new bootstrap.Modal(document.getElementById('addModal'));
            myModal.show();
        }

        document.getElementById('addModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('form_action').value = 'add';
            document.getElementById('edit_id').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-file-invoice-dollar"></i> เพิ่มรายการรับ';
            document.querySelector('form').reset();
        });

        function openDetailModal(data) {
            document.getElementById('detail_desc').innerText = data.description;
            document.getElementById('detail_doc_no').innerText = data.doc_no;
            
            var fileArea = document.getElementById('file_download_area');
            if (data.file_name) {
                fileArea.innerHTML = `<a href="uploads/${data.file_name}" target="_blank" class="btn btn-success btn-sm"><i class="fa-solid fa-download"></i> ดาวน์โหลดไฟล์</a>`;
            } else {
                fileArea.innerHTML = `<span class="text-muted">ไม่มีไฟล์แนบ</span>`;
            }
            var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
            myModal.show();
        }

        function printItem(id) {
            window.print();
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>