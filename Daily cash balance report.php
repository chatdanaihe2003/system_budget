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

// --- การจัดการตัวแปรวันที่ ---
$selected_day = isset($_POST['day']) ? $_POST['day'] : date('d');
$selected_month = isset($_POST['month']) ? $_POST['month'] : date('n');
$selected_year = isset($_POST['year']) ? $_POST['year'] : date('Y') + 543;
$selected_budget_year = isset($_POST['budget_year']) ? $_POST['budget_year'] : date('Y') + 543;

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM daily_cash_report ORDER BY category_name ASC, id ASC";
$result_data = $conn->query($sql_data);

// จัดกลุ่มข้อมูล
$data_grouped = [
    'เงินงบประมาณ' => [],
    'เงินนอกงบประมาณ' => [],
    'เงินรายได้แผ่นดิน' => []
];

if ($result_data->num_rows > 0) {
    while($row = $result_data->fetch_assoc()) {
        $data_grouped[$row['category_name']][] = $row;
    }
}

// ฟังก์ชันวันที่ไทย
function thai_month_arr() {
    return array(
        "1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน",
        "7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม"
    );
}

// *** เช็คหน้าปัจจุบัน ***
$current_page = basename($_SERVER['PHP_SELF']);
$current_page_encoded = urlencode('Daily cash balance report.php');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเงินคงเหลือประจำวัน - AMSS++</title>
    
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
        
        /* --- Table Styles --- */
        .table-custom th { 
            background-color: var(--header-gold); 
            color: white; 
            font-weight: 500; 
            text-align: center; 
            vertical-align: middle; 
            border: 1px solid rgba(255,255,255,0.2); 
            font-size: 0.9rem; 
            padding: 10px;
        }
        .table-custom td { 
            vertical-align: middle; 
            border-bottom: 1px solid #f0f0f0; 
            padding: 6px 8px; 
            font-size: 0.85rem; 
            background-color: white !important;
        }
        
        /* Row Categories */
        .category-row td {
            background-color: #fce4ec !important; /* สีชมพูอ่อนเหมือนในภาพ */
            font-weight: bold;
            color: #333;
            text-align: left;
            padding-left: 10px;
        }
        
        .item-row td {
            background-color: #ffffe0 !important; /* สีเหลืองอ่อน */
        }
        
        /* Footer/Total Row */
        .total-row td {
            background-color: #ffcdd2 !important; /* สีแดงอ่อน */
            font-weight: bold;
            color: #333;
            border-top: 2px solid #aaa;
        }

        .td-center { text-align: center; }
        .td-right { text-align: right; }
        .td-left { text-align: left; padding-left: 20px; }
        
        /* Filter Form */
        .filter-group { display: flex; gap: 10px; align-items: center; justify-content: center; flex-wrap: wrap; }
        .form-select-sm { min-width: 80px; }
    </style>
</head>
<body>

    <div class="top-header d-flex justify-content-between align-items-center">
        <div><strong>AMSS++</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
        <div class="text-end small">
            ผู้ใช้ : สมชาย นิลสุวรรณ (**Administrator**)<br>
            <?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <div class="sub-header">รายงานเงินคงเหลือประจำวัน</div>

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
                <a href="#" class="nav-link-custom dropdown-toggle <?php echo (strpos(urldecode($current_page), 'Daily cash balance report.php') !== false) ? 'active' : ''; ?>" data-bs-toggle="dropdown">รายงาน</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="Budget allocation report.php">รายงานการจัดสรรงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by project.php">รายงานการใช้จ่ายจำแนกตามโครงการ</a></li>
                    <li><a class="dropdown-item" href="Annuity register.php">ทะเบียนเงินงวด</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by budget code.php">รายงานการใช้จ่ายจำแนกตามรหัสงบประมาณ</a></li>
                    <li><a class="dropdown-item" href="Expenditure report categorized by type of.php">รายงานการใช้จ่ายจำแนกตามประเภทรายการจ่าย</a></li>
                    <li><a class="dropdown-item active" href="Daily cash balance report.php">รายงานเงินคงเหลือประจำวัน</a></li>
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

    <div class="container-fluid pb10 px10">
        <div class="content-card">
            
            <h2 class="page-title">รายงานเงินคงเหลือประจำวัน</h2>

            <form method="POST" action="" class="mb-4">
                <div class="filter-group">
                    <label class="fw-bold">วันที่</label>
                    <select name="day" class="form-select form-select-sm">
                        <?php for($d=1; $d<=31; $d++): ?>
                            <option value="<?php echo $d; ?>" <?php if($d == $selected_day) echo 'selected'; ?>><?php echo $d; ?></option>
                        <?php endfor; ?>
                    </select>

                    <label class="fw-bold">เดือน</label>
                    <select name="month" class="form-select form-select-sm">
                        <?php foreach(thai_month_arr() as $k => $m): ?>
                            <option value="<?php echo $k; ?>" <?php if($k == $selected_month) echo 'selected'; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="fw-bold">ปี</label>
                    <select name="year" class="form-select form-select-sm">
                        <?php for($y=date('Y')+543; $y>=2550; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php if($y == $selected_year) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <label class="fw-bold ms-3">ปีงบประมาณ</label>
                    <select name="budget_year" class="form-select form-select-sm">
                        <?php for($y=date('Y')+543+1; $y>=2550; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php if($y == $selected_budget_year) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit" class="btn btn-secondary btn-sm ms-2">เลือก</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 40%;">รายการ</th>
                            <th colspan="3" style="width: 45%;">คงเหลือ</th>
                            <th rowspan="2" style="width: 15%;">รวม</th>
                        </tr>
                        <tr>
                            <th>เงินสด</th>
                            <th>เงินฝากธนาคาร</th>
                            <th>เงินฝากส่วนราชการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // ตัวแปรเก็บยอดรวมทั้งหมด (Grand Total)
                        $grand_cash = 0;
                        $grand_bank = 0;
                        $grand_gov = 0;
                        $grand_total = 0;

                        // วนลูปตามหมวดหมู่ที่กำหนดไว้ (เพื่อเรียงลำดับตามภาพ)
                        $categories_order = ['เงินงบประมาณ', 'เงินนอกงบประมาณ', 'เงินรายได้แผ่นดิน'];

                        foreach ($categories_order as $cat_name) {
                            // แสดงหัวข้อหมวดหมู่
                            echo "<tr class='category-row'><td colspan='5'>$cat_name</td></tr>";

                            // ถ้ามีข้อมูลในหมวดหมู่นั้น
                            if (!empty($data_grouped[$cat_name])) {
                                foreach ($data_grouped[$cat_name] as $item) {
                                    $row_total = $item['cash_amount'] + $item['bank_amount'] + $item['gov_deposit_amount'];
                                    
                                    // สะสมยอดรวม
                                    $grand_cash += $item['cash_amount'];
                                    $grand_bank += $item['bank_amount'];
                                    $grand_gov += $item['gov_deposit_amount'];
                                    $grand_total += $row_total;

                                    echo "<tr class='item-row'>";
                                    echo "<td class='td-left'>" . $item['item_name'] . "</td>";
                                    echo "<td class='td-right'>" . number_format($item['cash_amount'], 2) . "</td>";
                                    echo "<td class='td-right'>" . number_format($item['bank_amount'], 2) . "</td>";
                                    echo "<td class='td-right'>" . number_format($item['gov_deposit_amount'], 2) . "</td>";
                                    echo "<td class='td-right'>" . number_format($row_total, 2) . "</td>";
                                    echo "</tr>";
                                }
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td class="td-center">รวม</td>
                            <td class="td-right"><?php echo number_format($grand_cash, 2); ?></td>
                            <td class="td-right"><?php echo number_format($grand_bank, 2); ?></td>
                            <td class="td-right"><?php echo number_format($grand_gov, 2); ?></td>
                            <td class="td-right"><?php echo number_format($grand_total, 2); ?></td>
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