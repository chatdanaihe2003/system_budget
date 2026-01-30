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

// --- Logic จัดการข้อมูล (CRUD) ---

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM money_types_sub WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Subtypesmoney.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $subtype_code = $_POST['subtype_code'];
    $subtype_name = $_POST['subtype_name'];
    $main_type_id = $_POST['main_type_id'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO money_types_sub (budget_year, subtype_code, subtype_name, main_type_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $budget_year, $subtype_code, $subtype_name, $main_type_id);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE money_types_sub SET budget_year=?, subtype_code=?, subtype_name=?, main_type_id=? WHERE id=?");
        $stmt->bind_param("issii", $budget_year, $subtype_code, $subtype_name, $main_type_id, $id);
        $stmt->execute();
    }
    header("Location: Subtypesmoney.php");
    exit();
}

// --- ดึงข้อมูลประเภท(ย่อย) พร้อมชื่อประเภท(หลัก) ---
$sql_sub = "SELECT s.*, m.type_name AS main_type_name 
            FROM money_types_sub s 
            LEFT JOIN money_types_main m ON s.main_type_id = m.id 
            ORDER BY s.budget_year DESC, s.subtype_code ASC";
$result_sub = $conn->query($sql_sub);

// --- ดึงข้อมูลสำหรับ Dropdown ---
// 1. ปีงบประมาณ
$sql_years = "SELECT budget_year FROM fiscal_years ORDER BY budget_year DESC";
$result_years = $conn->query($sql_years);
$years_options = [];
if ($result_years->num_rows > 0) {
    while($y = $result_years->fetch_assoc()) $years_options[] = $y['budget_year'];
} else {
    $years_options[] = date("Y") + 543 + 1;
}

// 2. ประเภท(หลัก)
$sql_mains = "SELECT * FROM money_types_main ORDER BY type_code ASC";
$result_mains = $conn->query($sql_mains);
$main_options = [];
if ($result_mains->num_rows > 0) {
    while($m = $result_mains->fetch_assoc()) $main_options[] = $m;
}

// ฟังก์ชันวันที่ไทย
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
    <title>ประเภท(ย่อย)ของเงิน - AMSS++</title>
    
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
        
        /* Styles from Mainmoney.php / Expensesbudget.php */
        .top-header { background-color: var(--primary-dark); color: white; padding: 10px 20px; }
        .sub-header { background: linear-gradient(90deg, var(--accent-yellow) 0%, var(--accent-gold) 100%); padding: 8px 20px; font-weight: 700; color: var(--primary-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom { background-color: var(--menu-bg); padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .nav-link-custom { color: #aaa; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .nav-link-custom:hover, .nav-link-custom.active { color: #fff; background-color: rgba(255,255,255,0.1); border-bottom-color: var(--accent-yellow); }
        .dropdown-menu { border-radius: 0; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .dropdown-item:hover { background-color: var(--bg-light); color: var(--primary-dark); }
        
        /* [แก้ไข] เพิ่มสไตล์สำหรับเมนู Active ให้เป็นตัวหนาสีดำ */
        .dropdown-item.active, .dropdown-item:active {
            background-color: white; 
            color: black !important; /* บังคับตัวหนังสือสีดำ */
            font-weight: bold !important; /* บังคับตัวหนา */
        }
        
        .content-card { background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; margin-top: 30px; border-top: 5px solid var(--accent-yellow); }
        .page-title { color: #d63384; font-weight: 700; text-align: center; margin-bottom: 25px; font-size: 1.6rem; }
        
        /* Table Styles (Matching Theme) */
        .table-custom th { background-color: #998a00; color: white; font-weight: 500; text-align: center; vertical-align: middle; border: 1px solid rgba(255,255,255,0.2); }
        .table-custom td { vertical-align: middle; border-color: #eee; padding: 10px; }
        
        .td-center { text-align: center; }
        .td-left { text-align: left; padding-left: 20px; }

        .btn-add { background-color: #0d6efd; color: white; border-radius: 50px; padding: 8px 25px; font-weight: 600; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2); transition: transform 0.2s; text-decoration: none; border:none;}
        .btn-add:hover { transform: translateY(-2px); background-color: #0b5ed7; color: white; }
        
        .action-btn { border: none; background: none; cursor: pointer; transition: 0.2s; }
        .btn-edit { color: #0d6efd; }
        .btn-delete { color: #dc3545; }
        .action-btn:hover { transform: scale(1.2); }

        .modal-header { background-color: var(--primary-dark); color: white; }
        .btn-close { filter: invert(1); }

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
        <div><strong>Budget control system</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        
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

    <div class="sub-header">รายการ ประเภท(ย่อย)ของเงิน</div>

   <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom">รายการหลัก</a>
            
            <div class="dropdown">
                <a href="#" class="nav-link-custom active dropdown-toggle" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="officers.php">เจ้าหน้าที่การเงินฯ</a></li>
                    <li><a class="dropdown-item" href="yearbudget.php">ปีงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="plan.php">แผนงาน</a></li>
                    <li><a class="dropdown-item" href="Projectoutcomes.php">ผลผลิตโครงการ</a></li>
                    <li><a class="dropdown-item" href="Activity.php">กิจกรรมหลัก</a></li>
                    <li><a class="dropdown-item" href="Sourcemoney.php">แหล่งของเงิน</a></li>
                    <li><a class="dropdown-item" href="Expensesbudget.php">งบรายจ่าย</a></li>
                    <li><a class="dropdown-item" href="Mainmoney.php">ประเภท(หลัก)ของเงิน</a></li>
                    
                    <li><a class="dropdown-item <?php echo ($current_page == 'Subtypesmoney.php') ? 'active' : ''; ?>" href="Subtypesmoney.php">ประเภท(ย่อย)ของเงิน</a></li>
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
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div style="width: 150px;"></div> 
                <h2 class="page-title m-0">ประเภท(ย่อย)ของเงิน</h2>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ที่</th>
                            <th style="width: 80px;">รหัส</th>
                            <th style="width: 100px;">ปีงบฯ</th>
                            <th>ประเภท(ย่อย)</th>
                            <th style="width: 200px;">ประเภท(หลัก)</th>
                            <th style="width: 100px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_sub->num_rows > 0) {
                            $i = 1;
                            while($row = $result_sub->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='td-center'>" . $i++ . "</td>";
                                echo "<td class='td-center'>" . $row['subtype_code'] . "</td>";
                                echo "<td class='td-center'>" . $row['budget_year'] . "</td>";
                                echo "<td class='td-left'>" . $row['subtype_name'] . "</td>";
                                echo "<td class='td-left text-muted'>" . $row['main_type_name'] . "</td>";

                                // ปุ่มจัดการ
                                echo "<td class='td-center'>";
                                echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['subtype_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                                echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                            onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                      </button>';
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลประเภท(ย่อย)ของเงิน</td></tr>";
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
                <form action="Subtypesmoney.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-folder-plus"></i> เพิ่มข้อมูลใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ</label>
                            <select name="budget_year" class="form-select" required>
                                <?php foreach($years_options as $y): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="subtype_code" class="form-control" placeholder="เช่น 110" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภท(ย่อย)</label>
                            <input type="text" name="subtype_name" class="form-control" placeholder="เช่น เงินประกันสัญญา" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภท(หลัก)</label>
                            <select name="main_type_id" class="form-select" required>
                                <option value="" selected disabled>-- เลือกประเภทหลัก --</option>
                                <?php foreach($main_options as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo $m['type_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
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
                <form action="Subtypesmoney.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูล</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ</label>
                            <select name="budget_year" id="edit_budget_year" class="form-select" required>
                                <?php foreach($years_options as $y): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="subtype_code" id="edit_subtype_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภท(ย่อย)</label>
                            <input type="text" name="subtype_name" id="edit_subtype_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภท(หลัก)</label>
                            <select name="main_type_id" id="edit_main_type_id" class="form-select" required>
                                <?php foreach($main_options as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo $m['type_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
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
            document.getElementById('edit_budget_year').value = data.budget_year;
            document.getElementById('edit_subtype_code').value = data.subtype_code;
            document.getElementById('edit_subtype_name').value = data.subtype_name;
            document.getElementById('edit_main_type_id').value = data.main_type_id;
            
            var myModal = new bootstrap.Modal(document.getElementById('editModal'));
            myModal.show();
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>