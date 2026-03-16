<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "แผงควบคุม (Dashboard) ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ภาพรวมระบบ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --------------------------------------------------------------------------------
// --- Logic การดึงข้อมูลเพื่อมาแสดงบน Dashboard ---
// --------------------------------------------------------------------------------

$master_budget = 0;
$master_budget_reg = 0;
$master_budget_proj = 0;

$total_budget = 0;
$total_budget_reg = 0;
$total_budget_proj = 0;

$total_expenses = 0;
$total_expenses_reg = 0;
$total_expenses_proj = 0;

$pending_approvals = 0;
$total_users = 0;

// 0. ดึงงบประมาณตั้งต้นของปีงบประมาณ (พยายามดึงจากตาราง yearbudget ถ้ามี)
$chk_yb = $conn->query("SHOW TABLES LIKE 'yearbudget'");
if ($chk_yb && $chk_yb->num_rows > 0) {
    $yb_res = $conn->query("SELECT * FROM yearbudget WHERE year = '$active_year' OR budget_year = '$active_year' LIMIT 1");
    if ($yb_res && $yb_res->num_rows > 0) {
        $yb_row = $yb_res->fetch_assoc();
        $master_budget = isset($yb_row['amount']) ? floatval($yb_row['amount']) : (isset($yb_row['budget_amount']) ? floatval($yb_row['budget_amount']) : 0);
        $master_budget_reg = isset($yb_row['regular_budget']) ? floatval($yb_row['regular_budget']) : 0;
        $master_budget_proj = isset($yb_row['project_budget']) ? floatval($yb_row['project_budget']) : 0;
    }
}

// 1. ดึงงบจัดสรรรวมทั้งหมดในปีงบปัจจุบัน (จากโครงการ)
$stmt_budget = $conn->prepare("SELECT SUM(budget_amount) as total FROM project_outcomes WHERE budget_year = ?");
if ($stmt_budget) {
    $stmt_budget->bind_param("i", $active_year);
    $stmt_budget->execute();
    $res_budget = $stmt_budget->get_result();
    if ($row = $res_budget->fetch_assoc()) {
        $total_budget = $row['total'] ?? 0;
    }
}

// 1.1 ดึงงบจัดสรร (แยกงบประจำ)
$stmt_budget_reg = $conn->prepare("SELECT SUM(budget_amount) as total FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบประจำ'");
if ($stmt_budget_reg) {
    $stmt_budget_reg->bind_param("i", $active_year);
    $stmt_budget_reg->execute();
    $res_budget_reg = $stmt_budget_reg->get_result();
    if ($row = $res_budget_reg->fetch_assoc()) {
        $total_budget_reg = $row['total'] ?? 0;
    }
}

// 1.2 ดึงงบจัดสรร (แยกงบพัฒนาคุณภาพการศึกษา)
$stmt_budget_proj = $conn->prepare("SELECT SUM(budget_amount) as total FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบพัฒนาคุณภาพการศึกษา'");
if ($stmt_budget_proj) {
    $stmt_budget_proj->bind_param("i", $active_year);
    $stmt_budget_proj->execute();
    $res_budget_proj = $stmt_budget_proj->get_result();
    if ($row = $res_budget_proj->fetch_assoc()) {
        $total_budget_proj = $row['total'] ?? 0;
    }
}

// 2. ดึงยอดเบิกจ่ายที่อนุมัติแล้วจากตาราง project_withdrawals 
$check_with_table = $conn->query("SHOW TABLES LIKE 'project_withdrawals'");
if ($check_with_table->num_rows > 0) {
    $stmt_with = $conn->prepare("SELECT SUM(amount) as total FROM project_withdrawals WHERE status = 'approved' AND budget_year = ?");
    if ($stmt_with) {
        $stmt_with->bind_param("i", $active_year);
        $stmt_with->execute();
        $res_with = $stmt_with->get_result();
        if ($row = $res_with->fetch_assoc()) {
            $total_expenses = $row['total'] ?? 0;
        }
    }
    
    // 2.1 ดึงยอดเบิกจ่ายที่อนุมัติแล้ว (แยกงบประจำ)
    $stmt_with_reg = $conn->prepare("SELECT SUM(w.amount) as total FROM project_withdrawals w LEFT JOIN project_outcomes p ON w.project_id = p.id WHERE w.status = 'approved' AND w.budget_year = ? AND p.budget_type = 'งบประจำ'");
    if ($stmt_with_reg) {
        $stmt_with_reg->bind_param("i", $active_year);
        $stmt_with_reg->execute();
        $res_with_reg = $stmt_with_reg->get_result();
        if ($row = $res_with_reg->fetch_assoc()) {
            $total_expenses_reg = $row['total'] ?? 0;
        }
    }
    
    // 2.2 ดึงยอดเบิกจ่ายที่อนุมัติแล้ว (แยกงบพัฒนาคุณภาพการศึกษา)
    $stmt_with_proj = $conn->prepare("SELECT SUM(w.amount) as total FROM project_withdrawals w LEFT JOIN project_outcomes p ON w.project_id = p.id WHERE w.status = 'approved' AND w.budget_year = ? AND p.budget_type = 'งบพัฒนาคุณภาพการศึกษา'");
    if ($stmt_with_proj) {
        $stmt_with_proj->bind_param("i", $active_year);
        $stmt_with_proj->execute();
        $res_with_proj = $stmt_with_proj->get_result();
        if ($row = $res_with_proj->fetch_assoc()) {
            $total_expenses_proj = $row['total'] ?? 0;
        }
    }
}

// 4. ดึงจำนวนคำขอที่รออนุมัติ (ยังคงอิงจาก project_expenses)
$check_exp_table = $conn->query("SHOW TABLES LIKE 'project_expenses'");
if ($check_exp_table->num_rows > 0) {
    $stmt_pending = $conn->prepare("SELECT COUNT(id) as count_pending FROM project_expenses WHERE approval_status = 'pending' AND budget_year = ?");
    if ($stmt_pending) {
        $stmt_pending->bind_param("i", $active_year);
        $stmt_pending->execute();
        $res_pending = $stmt_pending->get_result();
        if ($row = $res_pending->fetch_assoc()) {
            $pending_approvals = $row['count_pending'] ?? 0;
        }
    }
}

// 3. คำนวณยอดคงเหลือ
$remaining_balance = $total_budget - $total_expenses;
$remaining_balance_reg = $total_budget_reg - $total_expenses_reg; // ยอดคงเหลืองบประจำ
$remaining_balance_proj = $total_budget_proj - $total_expenses_proj; // ยอดคงเหลืองบพัฒนาคุณภาพฯ

// 3.1 คำนวณเปอร์เซ็นต์เบิกจ่าย เพื่อไปแสดง Progress Bar
$expense_percent = 0;
if ($total_budget > 0) {
    $expense_percent = ($total_expenses / $total_budget) * 100;
}
$expense_percent = min(100, max(0, $expense_percent)); // บังคับให้อยู่ในกรอบ 0-100%

// กำหนดสี Progress Bar ตามเปอร์เซ็นต์ที่ใช้ไป
$progress_color_class = 'bg-success'; // ใช้ไปน้อย (ปกติเขียว)
if ($expense_percent >= 80) {
    $progress_color_class = 'bg-danger'; // ใช้ไปเกิน 80% อันตราย
} elseif ($expense_percent >= 50) {
    $progress_color_class = 'bg-warning'; // ใช้ไประดับกลางๆ 50-79%
}

// 5. ดึงจำนวนผู้ใช้งานทั้งหมด
$check_user_table = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_user_table->num_rows > 0) {
    $res_users = $conn->query("SELECT COUNT(id) as count_users FROM users");
    if ($res_users && $row = $res_users->fetch_assoc()) {
        $total_users = $row['count_users'] ?? 0;
    }
}

// --------------------------------------------------------------------------------
// --- ส่วนที่เพิ่มใหม่: คำนวณสรุปภาพรวมแต่ละกลุ่มงาน และเก็บรายละเอียดรายโครงการ ---
// --------------------------------------------------------------------------------
$all_groups = [
    "กลุ่มอำนวยการ",
    "กลุ่มนโยบายและแผน",
    "กลุ่มส่งเสริมการจัดการศึกษา",
    "กลุ่มบริหารงานบุคคล",
    "กลุ่มบริหารการเงินและสินทรัพย์",
    "หน่วยตรวจสอบภายใน",
    "กลุ่มนิเทศ ติดตาม และประเมินผลการจัดการศึกษา",
    "กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร",
    "กลุ่มพัฒนาครูและบุคลากรทางการศึกษา",
    "กลุ่มกฎหมายและคดี"
];

$group_stats = [];
$projects_detail = []; // ตัวแปรเก็บรายละเอียดโครงการเพื่อไปแสดงใน Popup ที่ 2

foreach($all_groups as $g) {
    $group_stats[$g] = ['count' => 0, 'budget' => 0, 'allocated' => 0, 'expense' => 0, 'pending' => 0];
    $projects_detail[$g] = []; // เตรียม Array ว่างไว้เก็บโครงการของแต่ละกลุ่ม
}

$total_projects_count = 0;
$total_allocated_all = 0; // ตัวแปรใหม่สำหรับเก็บยอดจัดสรรทั้งหมด

// ดึงข้อมูลโครงการทั้งหมดในปีงบเพื่อหายอดจัดสรรและนับจำนวน (ใช้ SELECT * เพื่อดึงคอลัมน์การจัดสรร)
$sql_p = "SELECT * FROM project_outcomes WHERE budget_year = ?";
$stmt_p = $conn->prepare($sql_p);
if($stmt_p) {
    $stmt_p->bind_param("i", $active_year);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    $project_map = []; // เก็บ mapping id => group_name เพื่อไว้ไปอิงตอนดึงการเบิกจ่าย
    
    while($p = $res_p->fetch_assoc()) {
        $total_projects_count++;
        $g = $p['group_name'];
        $pid = $p['id'];
        $project_map[$pid] = $g;
        
        // คำนวณยอด "จัดสรร 1-5" แบบ Dynamic 
        $p_allocated = 0;
        for ($k = 1; $k <= 5; $k++) {
            if(isset($p['allocation_'.$k])) $p_allocated += floatval($p['allocation_'.$k]);
            elseif(isset($p['allocation'.$k])) $p_allocated += floatval($p['allocation'.$k]);
            elseif(isset($p['alloc_'.$k])) $p_allocated += floatval($p['alloc_'.$k]);
            elseif(isset($p['alloc'.$k])) $p_allocated += floatval($p['alloc'.$k]);
        }
        
        $total_allocated_all += $p_allocated; // สะสมยอดจัดสรรทั้งหมดของทุกโครงการ
        
        if(isset($group_stats[$g])) {
            $group_stats[$g]['count']++;
            $group_stats[$g]['budget'] += floatval($p['budget_amount']);
            $group_stats[$g]['allocated'] += $p_allocated;
            
            // เก็บรายละเอียดโครงการ
            $projects_detail[$g][$pid] = [
                'code' => $p['project_code'],
                'name' => $p['project_name'],
                'budget' => floatval($p['budget_amount']),
                'allocated' => $p_allocated,
                'approved' => 0,
                'pending' => 0
            ];
        }
    }
}

// ดึงข้อมูลการเบิกจ่ายเพื่อหายอดอนุมัติและรออนุมัติของแต่ละกลุ่ม และ แต่ละโครงการ
if ($check_exp_table->num_rows > 0) {
    $sql_e = "SELECT project_id, cutoff_amount, approval_status FROM project_expenses WHERE budget_year = ?";
    $stmt_e = $conn->prepare($sql_e);
    if($stmt_e) {
        $stmt_e->bind_param("i", $active_year);
        $stmt_e->execute();
        $res_e = $stmt_e->get_result();
        while($e = $res_e->fetch_assoc()) {
            $pid = $e['project_id'];
            if(isset($project_map[$pid])) {
                $g = $project_map[$pid];
                if(isset($group_stats[$g])) {
                    if($e['approval_status'] == 'approved') {
                        $group_stats[$g]['expense'] += $e['cutoff_amount'];
                        $projects_detail[$g][$pid]['approved'] += $e['cutoff_amount']; // บวกยอดให้รายโครงการ
                    } elseif($e['approval_status'] == 'pending') {
                        $group_stats[$g]['pending'] += $e['cutoff_amount'];
                        $projects_detail[$g][$pid]['pending'] += $e['cutoff_amount']; // บวกยอดรออนุมัติให้รายโครงการ
                    }
                }
            }
        }
    }
}


// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* สไตล์สำหรับ Dashboard Cards */
    .dash-card {
        background: var(--white);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        transition: var(--transition);
        display: flex;
        flex-direction: column; 
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .dash-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    /* เส้นขอบตกแต่งด้านซ้ายของการ์ด */
    .dash-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 5px;
    }
    .card-master::before { background-color: #7e22ce; } /* Purple for master budget */
    .card-budget::before { background-color: #3b82f6; } /* Blue */
    .card-expenses::before { background-color: #ef4444; } /* Red */
    .card-balance::before { background-color: #10b981; } /* Green */
    .card-pending::before { background-color: #f59e0b; } /* Yellow */
    .card-users::before { background-color: #8b5cf6; } /* Purple */
    .card-projects::before { background-color: #0ea5e9; } /* Sky Blue */

    .dash-icon {
        width: 70px;
        height: 70px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-right: 20px;
        flex-shrink: 0;
    }
    
    /* สีพื้นหลังของไอคอน */
    .icon-master { background-color: #f3e8ff; color: #7e22ce; }
    .icon-budget { background-color: #eff6ff; color: #3b82f6; }
    .icon-expenses { background-color: #fef2f2; color: #ef4444; }
    .icon-balance { background-color: #ecfdf5; color: #10b981; }
    .icon-pending { background-color: #fffbeb; color: #f59e0b; }
    .icon-users { background-color: #f5f3ff; color: #8b5cf6; }
    .icon-projects { background-color: #e0f2fe; color: #0ea5e9; }

    .dash-info {
        flex-grow: 1;
    }
    .dash-title {
        color: var(--text-muted);
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .dash-value {
        color: var(--text-main);
        font-size: 1.8rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
    }
    .dash-currency {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-muted);
        margin-left: 5px;
    }

    .welcome-banner {
        background: linear-gradient(135deg, #44a9fc, #150bd6);
        border-radius: 16px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(55, 48, 163, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .welcome-text h2 { font-weight: 700; margin-bottom: 5px; font-size: 1.8rem;}
    .welcome-text p { margin: 0; opacity: 0.9; font-size: 1rem;}
    .welcome-icon { font-size: 4rem; opacity: 0.2; }

    /* สไตล์สำหรับตาราง Modal ใหม่ให้สวยงามขึ้น */
    .table-summary {
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-summary th {
        vertical-align: middle;
        font-size: 0.95rem;
        color: #475569;
        font-weight: 700;
        padding: 15px;
        border-bottom: 2px solid #cbd5e1;
    }
    .table-summary td {
        vertical-align: middle;
        font-size: 1rem;
        padding: 12px 15px;
        border-bottom: 1px solid #f1f5f9;
        font-weight: 500;
    }
    .table-summary tbody tr:hover {
        background-color: #f8fafc;
    }
    .table-summary tbody tr:nth-child(even) {
        background-color: #fcfcfc;
    }
    .summary-total-row td {
        background-color: #f1f5f9 !important;
        font-weight: 800 !important;
        border-top: 2px solid #94a3b8;
    }
    /* เพิ่มพื้นหลังจางๆ ให้กับคอลัมน์การเงิน */
    .col-bg-finance {
        background-color: rgba(248, 250, 252, 0.5);
    }
    
    /* สไตล์ปุ่มดูรายละเอียดแบบในรูป (แต่เป็นสีฟ้า) */
    .btn-view-details {
        background-color: #ffffff;
        color: #0ea5e9;
        border: 2px solid #0ea5e9;
        border-radius: 20px;
        font-weight: 700;
        padding: 4px 16px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    .btn-view-details:hover {
        background-color: #0ea5e9;
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(14, 165, 233, 0.2);
    }
</style>

<div class="container-fluid pb-5 px-4 mt-4">
    
    <div class="welcome-banner">
        <div class="welcome-text">
            <h2>สวัสดี, <?php echo htmlspecialchars($_SESSION['name'] ?? 'ผู้ใช้งาน'); ?> 👋</h2>
            <p> ภาพรวมปีงบประมาณ <?php echo $active_year; ?></p>
        </div>
        <div class="welcome-icon">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <div class="col-xl-4 col-md-6">
            <div class="dash-card card-budget h-100">
                <div class="d-flex align-items-center w-100">
                    <div class="dash-icon icon-budget">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                    <div class="dash-info">
                        <div class="dash-title">งบประมาณตามแผนการใช้จ่ายงบประมาณ</div>
                        <div class="dash-value text-primary">
                            <?php echo number_format($total_budget, 2); ?> <span class="dash-currency">฿</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-top w-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-building-columns me-1 text-primary"></i> งบประจำ</span>
                        <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo number_format($total_budget_reg, 2); ?> ฿</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-diagram-project me-1 text-success"></i> งบพัฒนาคุณภาพฯ</span>
                        <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo number_format($total_budget_proj, 2); ?> ฿</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2" style="border-top: 1px dashed #e2e8f0;">
                        <span class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-hand-holding-dollar me-1 text-info"></i> จัดสรรทั้งหมด</span>
                        <span class="fw-bold text-info" style="font-size: 0.95rem;"><?php echo number_format($total_allocated_all, 2); ?> ฿</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="dash-card card-expenses h-100">
                <div class="d-flex align-items-center w-100">
                    <div class="dash-icon icon-expenses">
                        <i class="fa-solid fa-money-bill-trend-up"></i>
                    </div>
                    <div class="dash-info">
                        <div class="dash-title">เบิกจ่ายแล้ว (อนุมัติจริง)</div>
                        <div class="dash-value text-danger">
                            <?php echo number_format($total_expenses, 2); ?> <span class="dash-currency">฿</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-top w-100">
                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <span class="text-muted" style="font-size: 0.85rem;">อัตราการใช้จ่ายงบประมาณ</span>
                        <span class="fw-bold" style="font-size: 0.9rem;"><?php echo number_format($expense_percent, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $progress_color_class; ?>" 
                             role="progressbar" 
                             style="width: <?php echo $expense_percent; ?>%" 
                             aria-valuenow="<?php echo $expense_percent; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="dash-card card-balance h-100">
                <div class="d-flex align-items-center w-100">
                    <div class="dash-icon icon-balance">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                    <div class="dash-info">
                        <div class="dash-title">งบประมาณคงเหลือ</div>
                        <div class="dash-value <?php echo ($remaining_balance < 0) ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($remaining_balance, 2); ?> <span class="dash-currency">฿</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-top w-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-building-columns me-1 text-primary"></i> งบประจำคงเหลือ</span>
                        <span class="fw-bold <?php echo ($remaining_balance_reg < 0) ? 'text-danger' : 'text-dark'; ?>" style="font-size: 0.95rem;"><?php echo number_format($remaining_balance_reg, 2); ?> ฿</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-diagram-project me-1 text-success"></i> งบพัฒนาคุณภาพฯคงเหลือ</span>
                        <span class="fw-bold <?php echo ($remaining_balance_proj < 0) ? 'text-danger' : 'text-dark'; ?>" style="font-size: 0.95rem;"><?php echo number_format($remaining_balance_proj, 2); ?> ฿</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-xl-4 col-md-6">
            <div class="dash-card card-projects pe-4 flex-row justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="dash-icon icon-projects">
                        <i class="fa-solid fa-folder-tree"></i>
                    </div>
                    <div class="dash-info">
                        <div class="dash-title">โครงการทั้งหมด</div>
                        <div class="dash-value" style="color: #0ea5e9 !important;">
                            <?php echo number_format($total_projects_count); ?> <span class="dash-currency" style="font-size: 1.2rem;">โครงการ</span>
                        </div>
                    </div>
                </div>
                <button class="btn-view-details" data-bs-toggle="modal" data-bs-target="#groupSummaryModal">ดูรายละเอียด</button>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="dash-card card-pending pe-4 flex-row justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="dash-icon icon-pending">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="dash-info">
                        <div class="dash-title">คำขอรออนุมัติตัดยอด</div>
                        <div class="dash-value text-warning" style="color: #d97706 !important;">
                            <?php echo number_format($pending_approvals); ?> <span class="dash-currency" style="font-size: 1.2rem;">รายการ</span>
                        </div>
                    </div>
                </div>
                <?php 
                $dash_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
                if($pending_approvals > 0 && ($dash_role === 'admin' || $dash_role === 'แผนงาน')): 
                ?>
                    <a href="Approve the cut off amount.php" class="btn btn-sm btn-outline-warning rounded-pill fw-bold px-3 text-nowrap shadow-sm">ดูคำขอ</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="dash-card card-users flex-row justify-content-start">
                <div class="dash-icon icon-users">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="dash-info">
                    <div class="dash-title">ผู้ใช้งานในระบบทั้งหมด</div>
                    <div class="dash-value text-purple" style="color: #7c3aed !important;">
                        <?php echo number_format($total_users); ?> <span class="dash-currency" style="font-size: 1.2rem;">คน</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="groupSummaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-header bg-white border-bottom px-4 pt-4 pb-3" style="border-radius: 16px 16px 0 0;">
                <div>
                    <h4 class="modal-title fw-bold text-dark mb-1"><i class="fa-solid fa-chart-pie me-2 text-primary"></i> สรุปภาพรวมกลุ่มงาน</h4>
                    <span class="text-muted" style="font-size: 0.95rem;">สรุปข้อมูลโครงการและงบประมาณ (รวมทุกประเภทงบ) ของแต่ละกลุ่มงาน ประจำปีงบประมาณ <strong><?php echo $active_year; ?></strong></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="background-color: #f1f5f9; border-radius: 50%; padding: 10px;"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive m-0">
                    <table class="table table-hover align-middle text-center table-summary m-0">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle" style="width: 5%;">ลำดับ</th>
                                <th rowspan="2" class="align-middle text-start" style="width: 25%;">กลุ่มงาน</th>
                                <th rowspan="2" class="align-middle" style="width: 10%;">จำนวน<br>โครงการ</th>
                                <th colspan="4" class="text-center" style="background-color: #e2e8f0;">ข้อมูลการเงิน (บาท)</th>
                                <th rowspan="2" class="align-middle" style="width: 8%;">รายละเอียด</th>
                            </tr>
                            <tr>
                                <th style="background-color: #f8fafc; width: 13%;">จัดสรรไปแล้วทั้งหมด</th>
                                <th style="background-color: #f8fafc; width: 13%;">เบิกจ่ายแล้ว</th>
                                <th style="background-color: #f8fafc; width: 13%;">คงเหลือ</th>
                                <th style="background-color: #f8fafc; width: 13%;">รออนุมัติ<br>(Pending)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            $sum_proj = 0;
                            $sum_bud = 0;
                            $sum_alloc = 0;
                            $sum_exp = 0;
                            $sum_bal = 0;
                            $sum_pen = 0;

                            foreach ($all_groups as $gname) {
                                $c = $group_stats[$gname]['count'];
                                $bud = $group_stats[$gname]['budget'];
                                $alloc = $group_stats[$gname]['allocated'];
                                $exp = $group_stats[$gname]['expense'];
                                $pen = $group_stats[$gname]['pending'];
                                $bal = $bud - $exp;

                                $sum_proj += $c;
                                $sum_bud += $bud;
                                $sum_alloc += $alloc;
                                $sum_exp += $exp;
                                $sum_bal += $bal;
                                $sum_pen += $pen;

                                echo "<tr>";
                                echo "<td class='text-muted'>{$i}</td>";
                                echo "<td class='text-start text-dark fw-bold'>{$gname}</td>";
                                echo "<td class='text-dark fs-5'>{$c}</td>";
                                
                                // คอลัมน์การเงิน
                                echo "<td class='text-info fw-bold col-bg-finance'>" . number_format($alloc, 2) . "</td>";
                                echo "<td class='text-danger fw-bold col-bg-finance'>" . number_format($exp, 2) . "</td>";
                                echo "<td class='text-primary fw-bold col-bg-finance'>" . number_format($bal, 2) . "</td>";
                                echo "<td class='fw-bold col-bg-finance' style='color: #d97706;'>" . number_format($pen, 2) . "</td>";
                                
                                // ปุ่มดูรายละเอียด เปลี่ยนจาก a href เป็นเปิด Modal 2
                                $escaped_gname = htmlspecialchars($gname, ENT_QUOTES, 'UTF-8');
                                echo "<td><button class='btn btn-sm btn-primary px-3 shadow-sm' style='border-radius: 8px;' onclick=\"openProjectDetailModal('{$escaped_gname}')\"><i class='fa-solid fa-eye'></i> ดู</button></td>";
                                echo "</tr>";
                                $i++;
                            }
                            ?>
                            <tr class="summary-total-row">
                                <td colspan="2" class="text-end text-dark fs-5">รวมทั้งสิ้น :</td>
                                <td class="text-dark fs-4"><?php echo number_format($sum_proj); ?></td>
                                <td class="text-info fs-5"><?php echo number_format($sum_alloc, 2); ?></td>
                                <td class="text-danger fs-5"><?php echo number_format($sum_exp, 2); ?></td>
                                <td class="text-primary fs-5"><?php echo number_format($sum_bal, 2); ?></td>
                                <td class="fs-5" style="color: #d97706;"><?php echo number_format($sum_pen, 2); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3" style="border-radius: 0 0 16px 16px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold shadow-sm" data-bs-dismiss="modal" style="border-radius: 8px;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="projectDetailModal" tabindex="-1" aria-hidden="true" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header text-white border-bottom px-4 pt-4 pb-3" style="background: linear-gradient(135deg, #0ea5e9, #2563eb); border-radius: 16px 16px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold mb-1"><i class="fa-solid fa-list-check me-2"></i> รายละเอียดโครงการ</h5>
                    <span style="font-size: 0.95rem; opacity: 0.9;" id="detailModalGroupName">กลุ่มงาน...</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="table-responsive bg-white border rounded shadow-sm">
                    <table class="table table-hover align-middle table-bordered m-0" id="detailModalTable">
                        <thead class="table-light text-center">
                            <tr>
                                <th class="py-3" style="width: 5%;">ที่</th>
                                <th class="py-3" style="width: 35%;">ชื่อโครงการ</th>
                                <th class="py-3" style="width: 15%;">จัดสรรแล้ว</th>
                                <th class="py-3" style="width: 15%;">เบิกจ่ายแล้ว</th>
                                <th class="py-3" style="width: 15%;">รออนุมัติ</th>
                                <th class="py-3" style="width: 15%;">คงเหลือ</th>
                            </tr>
                        </thead>
                        <tbody id="detailModalBody">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 py-3">
                <button type="button" class="btn btn-secondary px-4 fw-bold shadow-sm" data-bs-dismiss="modal" style="border-radius: 8px;">ปิด</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ส่งข้อมูล Array แบบละเอียดจาก PHP มาให้ JavaScript ใช้งาน
    const projectDetailsData = <?php echo json_encode($projects_detail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

    // ฟังก์ชันเปิด Modal 2 แสดงรายชื่อโครงการ
    function openProjectDetailModal(groupName) {
        // อัปเดตชื่อกลุ่มงานบนหัว Modal
        document.getElementById('detailModalGroupName').innerText = groupName;
        
        const tbody = document.getElementById('detailModalBody');
        tbody.innerHTML = ''; // ล้างข้อมูลเก่า
        
        const formatMoney = (num) => parseFloat(num).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // ตรวจสอบว่ามีข้อมูลโครงการในกลุ่มนี้หรือไม่
        if (projectDetailsData[groupName] && Object.keys(projectDetailsData[groupName]).length > 0) {
            let i = 1;
            const projects = projectDetailsData[groupName];
            
            // วนลูปสร้างแถวตาราง
            for (const pid in projects) {
                const p = projects[pid];
                const balance = p.budget - p.approved; // ยอดคงเหลือ (งบรวม - อนุมัติแล้ว)
                
                let codeStr = p.code ? `<span class="text-primary fw-bold" style="font-size: 0.85rem;">[${p.code}]</span><br>` : '';

                let tr = `<tr>
                    <td class="text-center text-muted">${i++}</td>
                    <td>${codeStr}<span class="text-dark fw-bold">${p.name}</span></td>
                    <td class="text-end text-info fw-bold">${formatMoney(p.allocated)}</td>
                    <td class="text-end text-danger fw-bold">${formatMoney(p.approved)}</td>
                    <td class="text-end" style="color: #d97706 !important; font-weight: bold;">${formatMoney(p.pending)}</td>
                    <td class="text-end text-primary fw-bold" style="font-size: 1.05rem;">${formatMoney(balance)}</td>
                </tr>`;
                
                tbody.innerHTML += tr;
            }
        } else {
            // กรณีไม่มีโครงการเลย
            tbody.innerHTML = `<tr>
                <td colspan="6" class="text-center py-5 text-muted bg-white">
                    <i class="fa-solid fa-folder-open fs-1 mb-3 d-block text-secondary opacity-50"></i>
                    <h5 class="fw-bold">ยังไม่มีโครงการ</h5>
                    <p class="mb-0">กลุ่มงานนี้ยังไม่มีโครงการในปีงบประมาณนี้</p>
                </td>
            </tr>`;
        }

        // แสดง Modal 2 (สามารถเปิดซ้อน Modal 1 ได้เลย)
        new bootstrap.Modal(document.getElementById('projectDetailModal')).show();
    }
</script>