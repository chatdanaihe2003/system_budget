<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนขอเบิก/ขอยืมเงินโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนขอเบิก/ขอยืมเงินโครงการ';

// --- ตรวจสอบและสร้างคอลัมน์เพิ่มเติมในฐานข้อมูลให้อัตโนมัติ (เพื่อให้ตรงตามรูป) ---
$columns_to_add = [
    'fund_source' => "VARCHAR(255) NULL AFTER amount",
    'dika_no' => "VARCHAR(100) NULL AFTER fund_source",
    'officer_name' => "VARCHAR(255) NULL AFTER dika_no",
    'status' => "VARCHAR(10) NULL"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM project_withdrawals LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE project_withdrawals ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// 2. เปลี่ยนสถานะ (Toggle Status สีเขียว/แดง)
if (isset($_GET['toggle_id'])) {
    $id = $_GET['toggle_id'];
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'green') ? 'red' : 'green';

    $stmt = $conn->prepare("UPDATE project_withdrawals SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = !empty($_POST['doc_date']) ? $_POST['doc_date'] : date('Y-m-d');
    $doc_no = $_POST['doc_no'] ?? '';
    $withdrawal_type = $_POST['withdrawal_type'] ?? 1;
    $description = $_POST['description'] ?? '';
    $project_id = $_POST['project_id'] ?? 0;
    $activity_id = $_POST['activity_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $expense_type = $_POST['expense_type'] ?? '';
    $requester = $_POST['requester'] ?? '';
    $fund_source = $_POST['fund_source'] ?? '';
    $dika_no = $_POST['dika_no'] ?? '';
    $officer_name = $_POST['officer_name'] ?? '';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $sql_max = "SELECT MAX(withdrawal_order) as max_order FROM project_withdrawals WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $auto_withdrawal_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO project_withdrawals 
            (budget_year, withdrawal_order, doc_date, doc_no, withdrawal_type, description, project_id, activity_id, amount, fund_source, expense_type, requester, dika_no, officer_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissisiidsssss", $active_year, $auto_withdrawal_order, $doc_date, $doc_no, $withdrawal_type, $description, $project_id, $activity_id, $amount, $fund_source, $expense_type, $requester, $dika_no, $officer_name);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $withdrawal_order = $_POST['withdrawal_order'] ?? 0; 

        $stmt = $conn->prepare("UPDATE project_withdrawals 
            SET withdrawal_order=?, doc_date=?, doc_no=?, withdrawal_type=?, description=?, project_id=?, activity_id=?, amount=?, fund_source=?, expense_type=?, requester=?, dika_no=?, officer_name=? 
            WHERE id=?");
        $stmt->bind_param("issisiidsssssi", $withdrawal_order, $doc_date, $doc_no, $withdrawal_type, $description, $project_id, $activity_id, $amount, $fund_source, $expense_type, $requester, $dika_no, $officer_name, $id);
        $stmt->execute();
    }
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// --- ดึงข้อมูลปี Active ---
$sql_data = "SELECT * FROM project_withdrawals WHERE budget_year = ? ORDER BY id ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$sql_next = "SELECT MAX(withdrawal_order) as max_order FROM project_withdrawals WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_withdrawal_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

// --- ดึงข้อมูลจากตาราง project_outcomes เพื่อนำไปแสดงใน Dropdown ผลผลิต/โครงการ ---
$projects_opt = [];
$check_po_tb = $conn->query("SHOW TABLES LIKE 'project_outcomes'");
if ($check_po_tb && $check_po_tb->num_rows > 0) {
    // ดึงข้อมูลโครงการของปีงบประมาณปัจจุบัน
    $sql_po_opt = "SELECT id, project_name FROM project_outcomes WHERE budget_year = ? AND project_name IS NOT NULL AND project_name != '' ORDER BY project_name ASC";
    $stmt_po_opt = $conn->prepare($sql_po_opt);
    $stmt_po_opt->bind_param("i", $active_year);
    $stmt_po_opt->execute();
    $res_po_opt = $stmt_po_opt->get_result();
    
    if ($res_po_opt && $res_po_opt->num_rows > 0) {
        while($po = $res_po_opt->fetch_assoc()) {
            $projects_opt[] = [
                'id' => $po['id'],
                'name' => $po['project_name']
            ];
        }
    }
}

// --- ดึงข้อมูลจากตาราง activities เพื่อนำไปแสดงใน Dropdown กิจกรรมหลัก ---
$activities_opt = [];
$act_table = "activities";
$check_act_tb = $conn->query("SHOW TABLES LIKE 'activities'");
// ถ้าไม่มีตาราง activities ลองหาตารางชื่อ activity เผื่อไว้
if(!$check_act_tb || $check_act_tb->num_rows == 0) {
    $check_act_tb2 = $conn->query("SHOW TABLES LIKE 'activity'");
    if($check_act_tb2 && $check_act_tb2->num_rows > 0) {
        $act_table = "activity";
    }
}

if($conn->query("SHOW TABLES LIKE '$act_table'")->num_rows > 0) {
    // เช็คคอลัมน์เพื่อความปลอดภัย
    $act_cols = [];
    $col_q = $conn->query("SHOW COLUMNS FROM $act_table");
    while($c = $col_q->fetch_assoc()) { $act_cols[] = $c['Field']; }
    
    // หาชื่อคอลัมน์ที่เก็บชื่อกิจกรรม
    $act_name_col = "";
    if (in_array('activity_name', $act_cols)) $act_name_col = "activity_name";
    elseif (in_array('name', $act_cols)) $act_name_col = "name";
    elseif (in_array('description', $act_cols)) $act_name_col = "description";
    
    if ($act_name_col != "") {
        // ดึงตามปีงบประมาณถ้ามีคอลัมน์
        if (in_array('budget_year', $act_cols)) {
            $sql_act_opt = "SELECT id, $act_name_col FROM $act_table WHERE budget_year = ? AND $act_name_col IS NOT NULL AND $act_name_col != '' ORDER BY $act_name_col ASC";
            $stmt_act_opt = $conn->prepare($sql_act_opt);
            $stmt_act_opt->bind_param("i", $active_year);
            $stmt_act_opt->execute();
            $res_act_opt = $stmt_act_opt->get_result();
        } else {
            $sql_act_opt = "SELECT id, $act_name_col FROM $act_table WHERE $act_name_col IS NOT NULL AND $act_name_col != '' ORDER BY $act_name_col ASC";
            $res_act_opt = $conn->query($sql_act_opt);
        }
        
        if ($res_act_opt && $res_act_opt->num_rows > 0) {
            while($act = $res_act_opt->fetch_assoc()) {
                $activities_opt[] = [
                    'id' => $act['id'],
                    'name' => $act[$act_name_col]
                ];
            }
        }
    }
}

// --- ค้นหาและดึงข้อมูล ประเภทรายจ่าย (รายการจ่าย) แบบอัตโนมัติ ---
$expense_options = "";
$exp_table = "";

// 1. ค้นหาตารางที่น่าจะเป็นเรื่องรายการจ่าย
$possible_exp_tables = ['expensesbudget', 'expenses_budget', 'expense_budget', 'expensebudget', 'expenses', 'expense_types', 'expense_type'];
foreach ($possible_exp_tables as $ptable) {
    $check_exists = $conn->query("SHOW TABLES LIKE '$ptable'");
    if ($check_exists && $check_exists->num_rows > 0) {
        $exp_table = $ptable;
        break; 
    }
}

if ($exp_table !== "") {
    // 2. ดึงรายชื่อคอลัมน์
    $col_q_exp = $conn->query("SHOW COLUMNS FROM `$exp_table`");
    $exp_cols = [];
    if($col_q_exp){
        while($c = $col_q_exp->fetch_assoc()) {
            $exp_cols[] = $c['Field'];
        }
    }
    
    // 3. หาคอลัมน์ที่เป็นชื่อจริงๆ
    $exp_name_col = "";
    $possible_exp_cols = ['name', 'expense_name', 'budget_name', 'title', 'description', 'expense_type', 'type_name'];
    
    foreach($possible_exp_cols as $p_col) {
        if (in_array($p_col, $exp_cols)) {
            $exp_name_col = $p_col;
            break;
        }
    }

    if ($exp_name_col == "" && count($exp_cols) > 1) {
        $exp_name_col = $exp_cols[1]; // ลองใช้คอลัมน์ที่ 2 หากไม่เจอชื่อที่คาดไว้
    }

    if ($exp_name_col != "") {
        // 4. ดึงข้อมูล
        if (in_array('budget_year', $exp_cols)) {
            $sql_exp = "SELECT DISTINCT `$exp_name_col` FROM `$exp_table` WHERE budget_year = ? AND `$exp_name_col` IS NOT NULL AND `$exp_name_col` != '' ORDER BY `$exp_name_col` ASC";
            $stmt_exp = $conn->prepare($sql_exp);
            $stmt_exp->bind_param("i", $active_year);
            $stmt_exp->execute();
            $res_exp = $stmt_exp->get_result();
        } else {
            $sql_exp = "SELECT DISTINCT `$exp_name_col` FROM `$exp_table` WHERE `$exp_name_col` IS NOT NULL AND `$exp_name_col` != '' ORDER BY `$exp_name_col` ASC";
            $res_exp = $conn->query($sql_exp);
        }

        if ($res_exp && $res_exp->num_rows > 0) {
            $unique_exps = []; 
            while($exp = $res_exp->fetch_assoc()) {
                $e_name = $exp[$exp_name_col];
                if (!in_array($e_name, $unique_exps)) {
                    $unique_exps[] = $e_name;
                    $expense_options .= "<option value='".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."</option>";
                }
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .status-box { width: 18px; height: 18px; display: inline-block; vertical-align: middle; cursor: pointer; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: 0.2s;}
    .status-box:hover { transform: scale(1.1); }
    .bg-green { background-color: #198754; } /* เปลี่ยนเป็นสีเขียว Bootstrap */
    .bg-red { background-color: #dc3545; }   /* เปลี่ยนเป็นสีแดง Bootstrap */
    
    .action-container { display: flex; justify-content: center; gap: 6px; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-primary">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i> ทะเบียนขอเบิก/ขอยืมเงินโครงการ 
                <span class="fs-5 text-secondary fw-normal">ปี <?php echo $active_year; ?></span>
            </h2>
            
            <button class="btn btn-primary shadow-sm px-4" onclick="checkAdminAction('add')" style="border-radius: 8px; font-weight: 500;">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive border rounded mb-4">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="text-center py-3" style="width: 10%;">วดป</th>
                        <th class="py-3" style="width: 12%;">ที่เอกสาร</th>
                        <th class="py-3" style="width: 33%;">รายการ</th>
                        <th class="text-end py-3" style="width: 10%;">จำนวนเงิน</th>
                        <th class="text-center py-3" style="width: 8%;">สถานะ</th>
                        <th class="text-center py-3" style="width: 8%;">รายละเอียด</th>
                        <th class="text-center py-3" style="width: 14%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_amount = 0;
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $withdrawal_order = $row['withdrawal_order'] ?? $i++;
                            $amount = $row['amount'] ?? 0;
                            $withdrawal_type = $row['withdrawal_type'] ?? 1;
                            $total_amount += $amount;
                            
                            $db_status = $row['status'] ?? '';
                            if ($db_status == '') {
                                $current_status = ($withdrawal_type == 4) ? 'green' : 'red';
                            } else {
                                $current_status = $db_status;
                            }
                            $status_class = ($current_status == 'green') ? 'bg-green' : 'bg-red';

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . htmlspecialchars($withdrawal_order) . "</td>";
                            echo "<td class='text-center'>" . thai_date_short($row['doc_date'] ?? '') . "</td>";
                            echo "<td class='fw-bold text-secondary'>" . htmlspecialchars($row['doc_no'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
                            echo "<td class='text-end fw-bold text-success'>" . number_format($amount, 2) . "</td>";
                            
                            echo "<td class='text-center'>
                                    <a href='?toggle_id=".$row['id']."&current_status=".$current_status."' onclick=\"return confirm('ต้องการเปลี่ยนสถานะใช่หรือไม่?');\">
                                        <div class='status-box $status_class' title='คลิกเพื่อเปลี่ยนสถานะ'></div>
                                    </a>
                                  </td>";
                            
                            echo "<td class='text-center'>";
                            echo '<button class="btn btn-sm btn-outline-info px-2 shadow-sm" title="รายละเอียด" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-file-lines"></i></button>';
                            echo "</td>";

                            echo "<td class='text-center'>";
                            echo "<div class='action-container'>";
                            echo '<a href="javascript:void(0)" onclick="checkAdminDelete('.($row['id'] ?? 0).')" class="btn btn-sm btn-outline-danger px-2 shadow-sm" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="btn btn-sm btn-outline-warning px-2 shadow-sm" title="แก้ไข" onclick=\'checkAdminAction("edit", '.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='4' class='text-end py-3'><strong>รวมยอดทั้งหมด :</strong></td>";
                        echo "<td class='text-end py-3 text-danger fs-5'><strong>" . number_format($total_amount, 2) . "</strong></td>";
                        echo "<td colspan='3'></td>";
                        echo "</tr>";
                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'><i class='fa-regular fa-folder-open fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-light rounded border border-1 mt-4">
            <div class="d-flex align-items-center mb-2">
                <div class="status-box bg-green me-3"></div>
                <div class="fw-bold text-dark">แสดงสถานะขอเบิก/ส่งใช้เงินยืม เรียบร้อยแล้ว</div>
            </div>
            <div class="d-flex align-items-center">
                <div class="status-box bg-red me-3"></div>
                <div class="text-muted" style="font-size: 0.95rem;">
                    <strong class="text-dark">แสดงสถานะขอยืมเงิน</strong> 
                    เมื่อมีการส่งใช้เงินยืม เจ้าหน้าที่เพียงคลิกที่สัญลักษณ์สีแดง สถานะก็จะเปลี่ยนเป็นสีเขียว (ลูกหนี้เงินยืมหมดไป)
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-file-invoice-dollar me-2"></i> ลงทะเบียน ขอเบิก/ขอยืมเงินโครงการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="RequestforWithdrawalProjectLoan.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="form_action" value="add">
                    <input type="hidden" name="edit_id" id="edit_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ลำดับที่ (อัตโนมัติ)</label>
                            <input type="number" name="withdrawal_order" id="withdrawal_order" class="form-control bg-light fw-bold" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">วดป ลงทะเบียน <span class="text-danger">*</span></label>
                            <input type="date" name="doc_date" id="doc_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ที่เอกสาร</label>
                            <input type="text" name="doc_no" id="doc_no" class="form-control" placeholder="ระบุเลขที่เอกสาร (ถ้ามี)">
                        </div>
                    </div>

                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold text-dark mb-3"><i class="fa-solid fa-layer-group me-1"></i> ประเภทการทำรายการ</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type1" value="1" checked>
                                    <label class="form-check-label" for="type1">ขอยืมเงินงบประมาณ</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type2" value="2">
                                    <label class="form-check-label" for="type2">ขอยืมเงินนอกงบประมาณ</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type3" value="3">
                                    <label class="form-check-label" for="type3">ขอยืมเงินทดรองราชการ</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type4" value="4">
                                    <label class="form-check-label text-success fw-bold" for="type4">ขอเบิก</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">รายการ <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="description" class="form-control" placeholder="ระบุรายละเอียดรายการ" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ผลผลิต/โครงการ</label>
                            <select name="project_id" id="project_id" class="form-select">
                                <option value="0">เลือกโครงการ</option>
                                <?php foreach ($projects_opt as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">กิจกรรม</label>
                            <select name="activity_id" id="activity_id" class="form-select">
                                <option value="0">เลือกกิจกรรม</option>
                                <?php foreach ($activities_opt as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['id']); ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" id="amount" class="form-control text-end fw-bold text-primary" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">แหล่งของเงิน</label>
                            <input type="text" name="fund_source" id="fund_source" class="form-control" placeholder="ระบุแหล่งเงิน (ถ้ามี)">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ประเภทรายการจ่าย</label>
                            <select name="expense_type" id="expense_type" class="form-select">
                                <option value="">เลือกประเภทรายการจ่าย</option>
                                <?php echo $expense_options; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ผู้ขอเบิก/ขอยืมเงิน</label>
                            <input type="text" name="requester" id="requester" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">ฎีกา</label>
                            <input type="text" name="dika_no" id="dika_no" class="form-control">
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted" style="font-size: 0.85rem;">เจ้าหน้าที่</label>
                            <input type="text" name="officer_name" id="officer_name" class="form-control bg-light" value="<?php echo $_SESSION['name'] ?? ''; ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold w-100 text-center"><i class="fa-solid fa-circle-info me-2"></i> รายละเอียดการขอเบิก/ขอยืมเงิน ปี <?php echo $active_year; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="card border-0 bg-light p-3">
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">วดป ลงทะเบียน :</div>
                        <div class="col-8 text-dark fw-bold" id="v_date"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">ที่เอกสาร :</div>
                        <div class="col-8 text-dark" id="v_no"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted" id="v_type_label">ประเภท :</div>
                        <div class="col-8 text-primary fw-bold" id="v_type"></div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">รายการ :</div>
                        <div class="col-8 text-dark" id="v_desc"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">โครงการ :</div>
                        <div class="col-8 text-dark" id="v_proj"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">กิจกรรม :</div>
                        <div class="col-8 text-dark" id="v_act"></div>
                    </div>
                    <hr>
                    <div class="row mb-3 align-items-center">
                        <div class="col-4 text-end fw-bold text-muted">จำนวนเงิน :</div>
                        <div class="col-8 text-danger fw-bold fs-5" id="v_amount"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">แหล่งเงิน :</div>
                        <div class="col-8 text-dark" id="v_fund"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">ประเภทรายการจ่าย :</div>
                        <div class="col-8 text-dark" id="v_extype"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">ผู้ขอเบิก/ขอยืมเงิน :</div>
                        <div class="col-8 text-dark" id="v_req"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 text-end fw-bold text-muted">ฎีกา :</div>
                        <div class="col-8 text-dark" id="v_dika"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4 text-end fw-bold text-muted">เจ้าหน้าที่ :</div>
                        <div class="col-8 text-dark" id="v_off"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-0 py-3 justify-content-center">
                <button type="button" class="btn btn-secondary px-5" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const projectsList = <?php echo json_encode($projects_opt, JSON_UNESCAPED_UNICODE); ?>;
    const activitiesList = <?php echo json_encode($activities_opt, JSON_UNESCAPED_UNICODE); ?>;
    const currentUserName = '<?php echo isset($_SESSION["fullname"]) ? addslashes($_SESSION["fullname"]) : (isset($_SESSION["name"]) ? addslashes($_SESSION["name"]) : ""); ?>';

    function checkAdminAction(action, data = null) {
        if (action === 'add') { openAddModal(); } else { openEditModal(data); }
    }
    
    function checkAdminDelete(id) {
        if (confirm('ยืนยันลบข้อมูลรายการนี้ใช่หรือไม่?')) { window.location.href = `?delete_id=${id}`; }
    }
    
    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-file-invoice-dollar me-2"></i> ลงทะเบียน ขอเบิก/ขอยืมเงินโครงการ';
        document.getElementById('withdrawal_order').value = '<?php echo $next_withdrawal_order; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('officer_name').value = currentUserName;
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขข้อมูล ทะเบียนขอเบิก/ขอยืมเงิน';
        
        document.getElementById('withdrawal_order').value = data.withdrawal_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('description').value = data.description;
        
        let projSelect = document.getElementById('project_id');
        projSelect.value = data.project_id || 0;
        
        let actSelect = document.getElementById('activity_id');
        actSelect.value = data.activity_id || 0;
        
        document.getElementById('amount').value = data.amount;
        document.getElementById('fund_source').value = data.fund_source || '';

        let expSelect = document.getElementById('expense_type');
        let expValue = data.expense_type || '';
        if(expValue && !Array.from(expSelect.options).some(opt => opt.value === expValue)) {
            expSelect.add(new Option(expValue, expValue));
        }
        expSelect.value = expValue;
        
        document.getElementById('requester').value = data.requester;
        document.getElementById('dika_no').value = data.dika_no || '';
        document.getElementById('officer_name').value = data.officer_name || '';

        let radios = document.getElementsByName('withdrawal_type');
        for(let r of radios) { if(r.value == data.withdrawal_type) r.checked = true; }
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('v_date').innerText = data.doc_date;
        document.getElementById('v_no').innerText = data.doc_no || '-';
        
        let typeLabels = {1:"ขอยืมเงินงบประมาณ", 2:"ขอยืมเงินนอกงบประมาณ", 3:"ขอยืมเงินทดรองราชการ", 4:"ขอเบิก"};
        document.getElementById('v_type_label').innerText = typeLabels[data.withdrawal_type] + " :";
        document.getElementById('v_type').innerText = "ใช่"; 

        document.getElementById('v_desc').innerText = data.description;
        
        let projText = "-";
        if(projectsList && projectsList.length > 0) {
            let foundProj = projectsList.find(p => p.id == data.project_id);
            if(foundProj) projText = foundProj.name;
        }
        document.getElementById('v_proj').innerText = projText; 
        
        let actText = "-";
        if(activitiesList && activitiesList.length > 0) {
            let foundAct = activitiesList.find(a => a.id == data.activity_id);
            if(foundAct) actText = foundAct.name;
        }
        document.getElementById('v_act').innerText = actText;
        
        document.getElementById('v_amount').innerText = parseFloat(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) + " บาท";
        document.getElementById('v_fund').innerText = data.fund_source || '-';
        document.getElementById('v_extype').innerText = data.expense_type || '-';
        document.getElementById('v_req').innerText = data.requester || '-';
        document.getElementById('v_dika').innerText = data.dika_no || '-';
        document.getElementById('v_off').innerText = data.officer_name || '-';
        
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>