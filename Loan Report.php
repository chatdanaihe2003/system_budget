<?php
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

// --- ดึงข้อมูล ---
// เรียงลำดับตามประเภทเงินก่อน เพื่อให้ง่ายต่อการจัดกลุ่ม
$sql_data = "SELECT * FROM loan_report_data ORDER BY money_type DESC, loan_date ASC";
$result_data = $conn->query($sql_data);

// ฟังก์ชันวันที่ไทยย่อ
function thai_date_short($date_str) {
    if(!$date_str || $date_str == '0000-00-00') return "";
    $timestamp = strtotime($date_str);
    $thai_month_arr = array("0"=>"","1"=>"ม.ค.","2"=>"ก.พ.","3"=>"มี.ค.","4"=>"เม.ย.","5"=>"พ.ค.","6"=>"มิ.ย.","7"=>"ก.ค.","8"=>"ส.ค.","9"=>"ก.ย.","10"=>"ต.ค.","11"=>"พ.ย.","12"=>"ธ.ค.");
    $d = str_pad(date("j", $timestamp), 2, '0', STR_PAD_LEFT); 
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543;
    $y_short = substr($y, -2);
    return "$d {$thai_month_arr[$m]} $y_short"; 
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
$current_page_encoded = urlencode('Loan Report.php');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานลูกหนี้เงินยืม - AMSS++</title>
    
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
        .dropdown-item.active, .dropdown-item:active { background-color: white; color: var(--primary-dark); font-weight: 500; }

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
        
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-accent-bg: transparent; }
        .table-striped > tbody > tr:nth-of-type(even) > * { --bs-table-accent-bg: transparent; }
        
        .td-center { text-align: center; }
        .td-right { text-align: right; }
        .td-left { text-align: left; }

        /* Status Colors */
        .status-on-time { color: #28a745; font-weight: bold; } /* เขียว */
        .status-overdue { color: #dc3545; font-weight: bold; } /* แดง */

        /* Summary Row */
        .summary-row td { 
            background-color: #e0f7fa !important; /* สีฟ้าอ่อนเหมือนในภาพ */
            font-weight: bold; 
            color: #333; 
            text-align: right;
        }
        .summary-label { text-align: center !important; }
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center">
        <div><strong>AMSS++</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        <div class="text-end small">
            ผู้ใช้ : สมชาย นิลสุวรรณ (**Administrator**)<br>
            <?php echo thai_date_full(time()); ?>
        </div>
    </div>

    <div class="sub-header">รายงานลูกหนี้เงินยืม</div>

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
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Budgetallocation.php', 'Receivebudget.php', 'Receiveoffbudget.php', 'Receivenational.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนรับ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budgetallocation.php">รับการจัดสรรงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receivebudget.php">รับเงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receiveoffbudget.php">รับเงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Receivenational.php">รับเงินรายได้แผ่นดิน</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['RequestforWithdrawalProjectLoan.php', 'ProjectRefundRegistration.php', 'TreasuryWithdrawal.php', 'TreasuryRefundRegister.php', 'Withdrawtheappeal.php', 'Fundrolloverregister.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนขอเบิก</a>
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
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Authorizebudgetexpenditures.php', 'Orderpaymentoutsidethebudget.php', 'Orderpaymentofstaterevenue.php', 'Governmentadvancefunds.php', 'Approvedformaintypepayment.php', 'Approved for governmentadvancepayment.php', 'Major type of payment.php', 'Advance payment for government service.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนจ่าย</a>
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
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Budget.php', 'Off-budget funds.php', 'National income.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">เปลี่ยนแปลงสถานะ</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budget.php">เงินงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Off-budget funds.php">เงินนอกงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="National_revenue.php">เงินรายได้แผ่นดิน</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Check budget allocation.php', 'Check the periodic financial report.php', 'Check main payment type.php', 'Check the government advance payment.php', 'The appeal number does not exist in the system.php', 'Appeals regarding project termination classified by invoice.php', 'Supreme Court Rulings and References for Reimbursement Requests Classified by Ruling.php', 'Withdrawal requests that have not yet been submitted for approval.php', 'Requisition items with incorrect installment vouchers.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ตรวจสอบ</a>
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
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (strpos(urldecode($current_page), 'Loan Report.php') !== false || in_array($current_page, ['Budget allocation report.php', 'Expenditure report categorized by project.php', 'Annuity register.php', 'Expenditure report categorized by budget code.php', 'Expenditure report categorized by type of.php', 'Daily cash balance report.php', 'cash book.php', 'budget report.php', 'Report money outside the budget.php', 'State income report.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">รายงาน</a>
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
                    <li><a class="dropdown-item active" href="Loan Report.php">รายงานลูกหนี้เงินยืม</a></li>
                </ul>
            </div>

            <a href="#" class="nav-link-custom ms-auto">คู่มือ</a>
        </div>
    </div>

    <div class="container-fluid pb-5 px-3">
        <div class="content-card">
            
            <h2 class="page-title">รายงานลูกหนี้เงินยืม</h2>

            <div class="d-flex justify-content-end mb-3">
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-white border-0 fw-bold">ปีงบประมาณ</span>
                    <select class="form-select" style="width: 100px;">
                        <option>2568</option>
                    </select>
                    <button class="btn btn-secondary">เลือก</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-custom">
                    <thead>
                        <tr>
                            <th style="width: 3%;">ที่</th>
                            <th style="width: 10%;">วันยืม</th>
                            <th style="width: 20%;">ผู้ยืม</th>
                            <th style="width: 35%;">รายการ</th>
                            <th style="width: 10%;">จำนวนเงิน</th>
                            <th style="width: 12%;">ประเภทเงิน</th>
                            <th style="width: 10%;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data->num_rows > 0) {
                            $current_type = "";
                            $type_total = 0;
                            $i = 1;
                            
                            // เก็บข้อมูลใส่ array ก่อนเพื่อจัดกลุ่ม
                            $rows = [];
                            while($row = $result_data->fetch_assoc()) {
                                $rows[] = $row;
                            }

                            foreach ($rows as $index => $row) {
                                // ถ้าประเภทเงินเปลี่ยน และไม่ใช่แถวแรก ให้แสดงยอดรวมของกลุ่มก่อนหน้า
                                if ($current_type != "" && $current_type != $row['money_type']) {
                                    echo "<tr class='summary-row'>";
                                    echo "<td colspan='4' class='summary-label'>รวม{$current_type}</td>";
                                    echo "<td>" . number_format($type_total, 2) . "</td>";
                                    echo "<td></td>";
                                    echo "<td></td>";
                                    echo "</tr>";
                                    $type_total = 0; // รีเซ็ตยอดรวม
                                }
                                
                                $current_type = $row['money_type'];
                                $type_total += $row['amount'];

                                // สถานะ
                                $status_class = ($row['status'] == 'on_time') ? 'status-on-time' : 'status-overdue';
                                $status_text = ($row['status'] == 'on_time') ? 'ในเวลา' : 'ครบกำหนด';

                                echo "<tr>";
                                echo "<td class='td-center'>" . $i++ . "</td>";
                                echo "<td class='td-center'>" . thai_date_short($row['loan_date']) . "</td>";
                                echo "<td class='td-left'>" . $row['borrower'] . "</td>";
                                echo "<td class='td-left'>" . $row['description'] . "</td>";
                                echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                                echo "<td class='td-center'>" . $row['money_type'] . "</td>";
                                echo "<td class='td-center " . $status_class . "'>" . $status_text . "</td>";
                                echo "</tr>";

                                // ถ้าเป็นแถวสุดท้าย ให้แสดงยอดรวมด้วย
                                if ($index === count($rows) - 1) {
                                    echo "<tr class='summary-row'>";
                                    echo "<td colspan='4' class='summary-label'>รวม{$current_type}</td>";
                                    echo "<td>" . number_format($type_total, 2) . "</td>";
                                    echo "<td></td>";
                                    echo "<td></td>";
                                    echo "</tr>";
                                }
                            }

                        } else {
                            echo "<tr><td colspan='7' class='text-center py-4 text-muted'>ไม่พบข้อมูล</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>