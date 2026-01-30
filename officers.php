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

// --- ส่วนจัดการข้อมูล (CRUD Logic) ---

// 1. ลบข้อมูล (Delete)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM officers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: officers.php"); 
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['fullname'];
    
    $p_approve = isset($_POST['p_approve']) ? 1 : 0;
    $p_register = isset($_POST['p_register']) ? 1 : 0;
    $p_withdraw = isset($_POST['p_withdraw']) ? 1 : 0;
    $p_tax = isset($_POST['p_tax']) ? 1 : 0;
    $p_budget = isset($_POST['p_budget']) ? 1 : 0;
    $p_nonbudget = isset($_POST['p_nonbudget']) ? 1 : 0;
    $p_income = isset($_POST['p_income']) ? 1 : 0;
    $p_royal = isset($_POST['p_royal']) ? 1 : 0;
    $p_pay = isset($_POST['p_pay']) ? 1 : 0;

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO officers (fullname, p_approve, p_register, p_withdraw, p_tax, p_budget, p_nonbudget, p_income, p_royal, p_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiiiiiii", $fullname, $p_approve, $p_register, $p_withdraw, $p_tax, $p_budget, $p_nonbudget, $p_income, $p_royal, $p_pay);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE officers SET fullname=?, p_approve=?, p_register=?, p_withdraw=?, p_tax=?, p_budget=?, p_nonbudget=?, p_income=?, p_royal=?, p_pay=? WHERE id=?");
        $stmt->bind_param("siiiiiiiiii", $fullname, $p_approve, $p_register, $p_withdraw, $p_tax, $p_budget, $p_nonbudget, $p_income, $p_royal, $p_pay, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: officers.php");
    exit();
}

// --- ดึงข้อมูลเจ้าหน้าที่ ---
$sql_officers = "SELECT * FROM officers";
$result_officers = $conn->query($sql_officers);

function thai_date($timestamp) {
    $thai_day_arr = array("อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์");
    $thai_month_arr = array(
        "0"=>"", "1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน",
        "7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม"
    );
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
    <title>ตั้งค่าเจ้าหน้าที่ - AMSS++</title>
    
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
            --header-gold: #8B8000;
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
        
        /* CSS Active Menu Logic */
        .nav-link-custom { color: #aaa; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .nav-link-custom:hover, .nav-link-custom.active { 
            color: #fff; 
            background-color: #333; 
            border-bottom-color: var(--accent-yellow); 
        }
        
        .dropdown-menu { border-radius: 0; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.2); margin-top: 0; }
        .dropdown-item { padding: 10px 20px; font-size: 0.9rem; }
        .dropdown-item:hover { background-color: var(--bg-light); color: var(--primary-dark); }
        
        /* --- [แก้ไข] ปรับแต่ง CSS สำหรับเมนู Active ให้เป็นตัวหนาและสีดำ --- */
        .dropdown-item.active, .dropdown-item:active {
            background-color: white; 
            color: black !important; /* บังคับตัวหนังสือสีดำ */
            font-weight: bold !important; /* บังคับตัวหนา */
        }
        
        .content-card { 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            padding: 30px; 
            margin-top: 30px; 
            border-top: 5px solid var(--accent-yellow); 
            min-height: 500px;
        }
        
        .page-title { 
            color: #d63384; 
            font-weight: 700; 
            text-align: center; 
            margin-bottom: 25px; 
            font-size: 1.5rem; 
        }
        
        /* --- Table Styles (Gold Theme) --- */
        .table-custom th { 
            background-color: var(--header-gold); 
            color: white; 
            font-weight: 500; 
            text-align: center; 
            vertical-align: middle; 
            font-size: 0.9rem; 
            border: 1px solid rgba(255,255,255,0.2); 
            padding: 10px;
        }
        .table-custom td { 
            vertical-align: middle; 
            text-align: center; 
            font-size: 0.9rem; 
            border-bottom: 1px solid #f0f0f0; 
            padding: 12px 8px; 
            background-color: white !important;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-accent-bg: transparent; }
        .table-striped > tbody > tr:nth-of-type(even) > * { --bs-table-accent-bg: transparent; }

        .col-name { text-align: left !important; font-weight: 500; color: #333; }
        
        .check-icon { color: #198754; font-size: 1.1rem; } 
        .cross-icon { color: #dc3545; font-size: 1.1rem; opacity: 0.3; }

        .btn-add { 
            background-color: #0d6efd; 
            color: white; 
            border-radius: 50px; 
            padding: 8px 25px; 
            font-weight: 600; 
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2); 
            transition: transform 0.2s; 
            cursor: pointer; 
            text-decoration: none;
            border: none;
        }
        .btn-add:hover { transform: translateY(-2px); background-color: #0b5ed7; color: white; }
        
        .action-btn { border: none; background: none; cursor: pointer; transition: 0.2s; margin: 0 2px; }
        .btn-edit { color: #0d6efd; }
        .btn-delete { color: #dc3545; }
        .action-btn:hover { transform: scale(1.2); }
        
        /* Modal Styles */
        .modal-header { background-color: var(--primary-dark); color: white; }
        .modal-title { font-weight: bold; font-family: 'Sarabun'; }
        .btn-close { filter: invert(1); }
        .form-check-label { font-size: 0.95rem; cursor: pointer; }
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
            <small class="text-white-50"><?php echo thai_date(time()); ?></small>
        </div>
        </div>

    <div class="sub-header">เจ้าหน้าที่การเงินและบัญชี</div>

    <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom">รายการหลัก</a>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom active dropdown-toggle" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item <?php echo ($current_page == 'officers.php') ? 'active' : ''; ?>" href="officers.php">เจ้าหน้าที่การเงินฯ</a></li>
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
                    <li><a class="dropdown-item" href="National income.php">เงินรายได้แผ่นดิน</a></li>
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

    <div class="container pb-5">
        <div class="content-card">
            
            <h2 class="page-title m-0 mb-4">กำหนดเจ้าหน้าที่การเงินฯ</h2>
            
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    + เพิ่มข้อมูล
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 5%;">ที่</th>
                            <th rowspan="2" style="width: 25%;">ชื่อเจ้าหน้าที่</th>
                            <th colspan="9">สิทธิ์การเข้าถึง</th>
                            <th rowspan="2" style="width: 10%;">จัดการ</th>
                        </tr>
                        <tr>
                            <th>ผู้อนุมัติ</th>
                            <th>ทะเบียนคุม</th>
                            <th>ทะเบียนขอเบิก</th>
                            <th>ทะเบียนฎีกา</th>
                            <th>เงินงบประมาณ</th>
                            <th>เงินนอกงบฯ</th>
                            <th>รายได้แผ่นดิน</th>
                            <th>เงินทดรองฯ</th>
                            <th>จ่ายเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_officers->num_rows > 0) {
                            $i = 1;
                            while($row = $result_officers->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $i++ . "</td>";
                                echo "<td class='col-name'>" . $row['fullname'] . "</td>";
                                
                                $fields = ['p_approve', 'p_register', 'p_withdraw', 'p_tax', 'p_budget', 'p_nonbudget', 'p_income', 'p_royal', 'p_pay'];
                                foreach ($fields as $field) {
                                    echo "<td>";
                                    if($row[$field] == 1) {
                                        echo '<i class="fa-solid fa-check check-icon"></i>';
                                    }
                                    echo "</td>";
                                }

                                echo '<td>';
                                echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบข้อมูลนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                                echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                            onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                          </button>';
                                echo '</td>';
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='12' class='text-center py-4'>ไม่พบข้อมูลเจ้าหน้าที่</td></tr>";
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
                <form action="officers.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-user-plus"></i> เพิ่มเจ้าหน้าที่ใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" required placeholder="ระบุชื่อ-นามสกุล">
                        </div>
                        <div class="mb-2"><strong>กำหนดสิทธิ์การเข้าถึง:</strong></div>
                        <div class="row g-2">
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_approve" id="add_approve"><label class="form-check-label" for="add_approve">ผู้อนุมัติ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_register" id="add_register"><label class="form-check-label" for="add_register">ทะเบียนคุม</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_withdraw" id="add_withdraw"><label class="form-check-label" for="add_withdraw">ทะเบียนขอเบิก</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_tax" id="add_tax"><label class="form-check-label" for="add_tax">ทะเบียนฎีกา</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_budget" id="add_budget"><label class="form-check-label" for="add_budget">เงินงบประมาณ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_nonbudget" id="add_nonbudget"><label class="form-check-label" for="add_nonbudget">เงินนอกงบฯ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_income" id="add_income"><label class="form-check-label" for="add_income">รายได้แผ่นดิน</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_royal" id="add_royal"><label class="form-check-label" for="add_royal">เงินทดรองฯ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_pay" id="add_pay"><label class="form-check-label" for="add_pay">จ่ายเงิน</label></div></div>
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

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="officers.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลเจ้าหน้าที่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                        </div>
                        <div class="mb-2"><strong>กำหนดสิทธิ์การเข้าถึง:</strong></div>
                        <div class="row g-2">
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_approve" id="edit_p_approve"><label class="form-check-label" for="edit_p_approve">ผู้อนุมัติ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_register" id="edit_p_register"><label class="form-check-label" for="edit_p_register">ทะเบียนคุม</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_withdraw" id="edit_p_withdraw"><label class="form-check-label" for="edit_p_withdraw">ทะเบียนขอเบิก</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_tax" id="edit_p_tax"><label class="form-check-label" for="edit_p_tax">ทะเบียนฎีกา</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_budget" id="edit_p_budget"><label class="form-check-label" for="edit_p_budget">เงินงบประมาณ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_nonbudget" id="edit_p_nonbudget"><label class="form-check-label" for="edit_p_nonbudget">เงินนอกงบฯ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_income" id="edit_p_income"><label class="form-check-label" for="edit_p_income">รายได้แผ่นดิน</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_royal" id="edit_p_royal"><label class="form-check-label" for="edit_p_royal">เงินทดรองฯ</label></div></div>
                            <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_pay" id="edit_p_pay"><label class="form-check-label" for="edit_p_pay">จ่ายเงิน</label></div></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_fullname').value = data.fullname;
            
            document.getElementById('edit_p_approve').checked = (data.p_approve == 1);
            document.getElementById('edit_p_register').checked = (data.p_register == 1);
            document.getElementById('edit_p_withdraw').checked = (data.p_withdraw == 1);
            document.getElementById('edit_p_tax').checked = (data.p_tax == 1);
            document.getElementById('edit_p_budget').checked = (data.p_budget == 1);
            document.getElementById('edit_p_nonbudget').checked = (data.p_nonbudget == 1);
            document.getElementById('edit_p_income').checked = (data.p_income == 1);
            document.getElementById('edit_p_royal').checked = (data.p_royal == 1);
            document.getElementById('edit_p_pay').checked = (data.p_pay == 1);

            var myModal = new bootstrap.Modal(document.getElementById('editModal'));
            myModal.show();
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>