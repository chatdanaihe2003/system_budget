<?php
// --- ส่วนแสดง Error (เปิดไว้ชั่วคราวเพื่อแก้ปัญหาจอขาว) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -----------------------------------------------------

session_start(); 

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// 2. เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "system_budget";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. ดึงปีงบประมาณ Active (ใส่ Try-Catch ป้องกันจอขาว)
$active_year = date("Y") + 543; 
$sql_check_active = "SELECT budget_year FROM fiscal_years WHERE is_active = 1 LIMIT 1";

// ใช้ @ เพื่อปิด error ชั่วคราวถ้า query พัง แล้วเช็คค่าเอาเอง
$result_check_active = $conn->query($sql_check_active);

if ($result_check_active && $result_check_active->num_rows > 0) {
    $row_active = $result_check_active->fetch_assoc();
    $active_year = $row_active['budget_year'];
}

// 4. ข้อมูลกลุ่มงาน (Mock Data - หากมีตารางจริงให้เปลี่ยนเป็น SQL Query)
$work_groups = [
    1 => "กลุ่มอำนวยการ",
    2 => "กลุ่มนโยบายและแผน",
    3 => "กลุ่มส่งเสริมการจัดการศึกษา",
    4 => "กลุ่มบริหารงานบุคคล",
    5 => "กลุ่มบริหารการเงินและสินทรัพย์",
    6 => "กลุ่มหน่วยตรวจสอบภายใน",
    7 => "กลุ่มนิเทศติดตามและประเมินผลฯ",
    8 => "กลุ่มส่งเสริมการศึกษาทางไกลเทคโนโลยีสารสนเทศ",
    9 => "กลุ่มพัฒนาครูและบุคลากรฯ",
    10 => "กลุ่มกฎหมายและคดี"
];

// ตัวแปรเก็บยอดรวม
$total_projects = 0;
$total_approved = 0;
$total_disbursed = 0;
$total_remaining = 0;
$total_pending = 0;

// ฟังก์ชันวันที่ (ครอบด้วย function_exists ป้องกัน Error ชื่อซ้ำ)
if (!function_exists('thai_date_full_header')) {
    function thai_date_full_header($timestamp) {
        $thai_day_arr = array("อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์");
        $thai_month_arr = array("0"=>"","1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน","7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม");
        $d = date("j", $timestamp);
        $m = date("n", $timestamp);
        $y = date("Y", $timestamp) + 543;
        return "วัน" . $thai_day_arr[date("w", $timestamp)] . "ที่ $d $thai_month_arr[$m] พ.ศ. $y";
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปภาพรวมกลุ่มงาน - AMSS++</title>
    
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
            --header-gold: #8B8000;
        }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--bg-light); color: #333; }
        
        .top-header { background-color: var(--primary-dark); color: white; padding: 10px 20px; }
        .user-info { font-size: 0.9rem; text-align: right; }
        .user-role { color: var(--accent-yellow); font-weight: 700; text-transform: uppercase; }
        .btn-logout { color: #ff6b6b; text-decoration: none; margin-left: 10px; border: 1px solid #ff6b6b; padding: 2px 8px; border-radius: 4px; }
        
        .sub-header { background: linear-gradient(90deg, var(--accent-yellow) 0%, var(--accent-gold) 100%); padding: 8px 20px; font-weight: 700; color: var(--primary-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom { background-color: var(--menu-bg); padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .nav-link-custom { color: #aaa; padding: 12px 20px; text-decoration: none; display: inline-block; }
        .nav-link-custom:hover, .nav-link-custom.active { color: #fff; background-color: #333; border-bottom-color: var(--accent-yellow); }
        
        .dropdown-menu { border-radius: 0; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .dropdown-item.active, .dropdown-item:active { background-color: white; color: black !important; font-weight: bold !important; }

        .content-card { background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; margin-top: 30px; border-top: 5px solid var(--accent-yellow); }
        .page-title { color: #008080; font-weight: 700; text-align: center; font-size: 1.4rem; } 
        .page-subtitle { text-align: center; color: #666; font-size: 0.95rem; margin-bottom: 25px; }

        /* Table */
        .table-custom th { background-color: var(--header-gold); color: white; text-align: center; vertical-align: middle; border: 1px solid rgba(255,255,255,0.2); font-size: 0.9rem; padding: 10px; }
        .table-custom td { vertical-align: middle; border-bottom: 1px solid #f0f0f0; padding: 8px; font-size: 0.9rem; background-color: white !important; }
        .total-row td { background-color: #f8f9fa !important; font-weight: bold; border-top: 2px solid #ccc; text-align: right; }

        .text-approved { color: #198754; font-weight: 600; } 
        .text-disbursed { color: #dc3545; font-weight: 600; } 
        .text-remaining { color: #0d6efd; font-weight: 600; } 
        .text-pending { color: #fd7e14; font-weight: 600; }   

        .btn-view { background-color: #0d6efd; color: white; border: none; padding: 2px 10px; border-radius: 4px; font-size: 0.8rem; }
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center">
        <div><strong>AMSS++</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        <div class="user-info">
            <div>
                ผู้ใช้ : <?php echo htmlspecialchars($_SESSION['fullname']); ?> 
                (<span class="user-role">**<?php echo $_SESSION['role']; ?>**</span>)
                <a href="Logout.php" class="btn-logout" onclick="return confirm('ยืนยันออกจากระบบ?');"><i class="fa-solid fa-power-off"></i> ออก</a>
            </div>
            <small class="text-white-50"><?php echo thai_date_full_header(time()); ?></small>
        </div>
    </div>

    <div class="sub-header">สรุปภาพรวมกลุ่มงาน</div>

    <div class="navbar-custom">
        <div class="container-fluid d-flex flex-wrap">
            <a href="index.php" class="nav-link-custom active">รายการหลัก</a>
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

    <div class="container-fluid pb-5 px-3">
        <div class="content-card">
            
            <h2 class="page-title"><i class="fa-solid fa-chart-pie"></i> สรุปภาพรวมกลุ่มงาน</h2>
            <div class="page-subtitle">สรุปข้อมูลโครงการและงบประมาณ (เฉพาะโครงการพัฒนาคุณภาพฯ) ของแต่ละกลุ่มงาน ปีงบประมาณ <?php echo $active_year; ?></div>

            <div class="table-responsive">
                <table class="table table-striped table-custom">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 5%;">ลำดับ</th>
                            <th rowspan="2" style="width: 30%;">กลุ่มงาน</th>
                            <th rowspan="2" style="width: 10%;">จำนวน<br>โครงการ</th>
                            <th colspan="4">ข้อมูลการเงิน (บาท)</th>
                            <th rowspan="2" style="width: 10%;">รายละเอียด</th>
                        </tr>
                        <tr>
                            <th style="width: 11%;">ได้รับอนุมัติ</th>
                            <th style="width: 11%;">เบิกจ่ายแล้ว</th>
                            <th style="width: 11%;">คงเหลือ</th>
                            <th style="width: 11%;">รออนุมัติ<br>(Pending)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($work_groups as $index => $group_name) {
                            $project_count = 0;
                            // ตัวอย่าง Mock Data (กลุ่มที่ 7 มี 1 โครงการ)
                            if($index == 7) $project_count = 1; 

                            $approved = 0.00;
                            $disbursed = 0.00;
                            $remaining = 0.00;
                            $pending = 0.00;

                            // สะสมยอดรวม
                            $total_projects += $project_count;
                            $total_approved += $approved;
                            $total_disbursed += $disbursed;
                            $total_remaining += $remaining;
                            $total_pending += $pending;

                            echo "<tr>";
                            echo "<td class='text-center'>$index</td>";
                            echo "<td class='text-start'>$group_name</td>";
                            echo "<td class='text-center'>$project_count</td>";
                            echo "<td class='text-end text-approved'>" . number_format($approved, 2) . "</td>";
                            echo "<td class='text-end text-disbursed'>" . number_format($disbursed, 2) . "</td>";
                            echo "<td class='text-end text-remaining'>" . number_format($remaining, 2) . "</td>";
                            echo "<td class='text-end text-pending'>" . number_format($pending, 2) . "</td>";
                            echo "<td class='text-center'><button class='btn-view'><i class='fa-solid fa-eye'></i> ดู</button></td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" class="text-center">รวมทั้งสิ้น:</td>
                            <td class="text-center"><?php echo $total_projects; ?></td>
                            <td class="text-end"><?php echo number_format($total_approved, 2); ?></td>
                            <td class="text-end"><?php echo number_format($total_disbursed, 2); ?></td>
                            <td class="text-end"><?php echo number_format($total_remaining, 2); ?></td>
                            <td class="text-end"><?php echo number_format($total_pending, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>