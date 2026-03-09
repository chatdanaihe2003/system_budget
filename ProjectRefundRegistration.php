<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนคืนเงินโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ทะเบียนคืนเงินโครงการ';

// --------------------------------------------------------------------------------
// --- ตรวจสอบและเพิ่มคอลัมน์ใหม่ให้ฐานข้อมูลอัตโนมัติ (รองรับฟอร์มใหม่) ---
// --------------------------------------------------------------------------------
$check_col = $conn->query("SHOW COLUMNS FROM project_refunds LIKE 'project_id'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE project_refunds ADD project_id INT NULL, ADD activity_id INT NULL, ADD expense_type VARCHAR(255) NULL, ADD borrower VARCHAR(255) NULL");
}
$check_col2 = $conn->query("SHOW COLUMNS FROM project_refunds LIKE 'officer_name'");
if ($check_col2 && $check_col2->num_rows == 0) {
    $conn->query("ALTER TABLE project_refunds ADD officer_name VARCHAR(255) NULL");
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล (ลบทั้งสองตาราง และลบใน Receivebudget)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ดึงข้อมูลเดิมก่อนลบ เพื่อนำไปลบในหน้า Receivebudget
    $stmt_get = $conn->prepare("SELECT description, budget_year FROM project_refunds WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();
    if ($row_del = $res_get->fetch_assoc()) {
        $old_desc = $row_del['description'];
        $del_budget_year = $row_del['budget_year'];
        
        // ลบในตารางรับเงินงบประมาณ
        $target_desc = "รับคืนเงินยืมโครงการ: " . $old_desc;
        $stmt_del_rec = $conn->prepare("DELETE FROM receive_budget WHERE description = ? AND budget_year = ?");
        if ($stmt_del_rec) {
            $stmt_del_rec->bind_param("si", $target_desc, $del_budget_year);
            $stmt_del_rec->execute();
        }
    }
    
    // ลบในตารางคลัง (อ้างอิงจาก ref_id)
    $stmt2 = $conn->prepare("DELETE FROM treasury_refunds WHERE ref_id = ? AND ref_type = 'project'");
    if ($stmt2) {
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
    }

    // ลบในตารางหลัก
    $stmt = $conn->prepare("DELETE FROM project_refunds WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: ProjectRefundRegistration.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = $_POST['doc_date'] ?? date('Y-m-d');
    $doc_no = $_POST['doc_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $project_id = $_POST['project_id'] ?? 0;
    $activity_id = $_POST['activity_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $expense_type = $_POST['expense_type'] ?? '';
    $borrower = $_POST['borrower'] ?? '';
    $officer_name = $_POST['officer_name'] ?? '';
    $is_other_officer = 0; 

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ลำดับถัดไปอัตโนมัติ
        $sql_max = "SELECT MAX(refund_order) as max_order FROM project_refunds WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $refund_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        // เพิ่มลง Project
        $stmt = $conn->prepare("INSERT INTO project_refunds (budget_year, refund_order, doc_date, doc_no, description, amount, is_other_officer, project_id, activity_id, expense_type, borrower, officer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssdiissss", $active_year, $refund_order, $doc_date, $doc_no, $description, $amount, $is_other_officer, $project_id, $activity_id, $expense_type, $borrower, $officer_name);
        $stmt->execute();
        $new_id = $conn->insert_id;

        // เพิ่มลง Treasury (เพื่อไปแสดงหน้า TreasuryRefundRegister.php)
        $stmt_treasury = $conn->prepare("INSERT INTO treasury_refunds (budget_year, refund_date, doc_no, description, amount, ref_id, ref_type) VALUES (?, ?, ?, ?, ?, ?, 'project')");
        if ($stmt_treasury) {
            $stmt_treasury->bind_param("isssdi", $active_year, $doc_date, $doc_no, $description, $amount, $new_id);
            $stmt_treasury->execute();
        }
        
        // ถ้ามีการติ๊ก Checkbox ให้ไปลงทะเบียนรับเงินงบประมาณด้วย
        if(isset($_POST['save_to_receive']) && $_POST['save_to_receive'] == '1') {
            $check_tbl = $conn->query("SHOW TABLES LIKE 'receive_budget'");
            if($check_tbl->num_rows > 0) {
                $sql_rec_max = "SELECT MAX(receive_order) as m_order FROM receive_budget WHERE budget_year = ?";
                $st_rm = $conn->prepare($sql_rec_max);
                $st_rm->bind_param("i", $active_year);
                $st_rm->execute();
                $rr = $st_rm->get_result()->fetch_assoc();
                $rec_order = ($rr['m_order'] ?? 0) + 1;
                $t_type = "รับเงินสด"; // กำหนดประเภทตั้งต้น
                $desc_receive = "รับคืนเงินยืมโครงการ: " . $description; // ระบุให้รู้ว่ามาจากคืนเงินโครงการ

                $ins_rec = $conn->prepare("INSERT INTO receive_budget (budget_year, receive_order, doc_date, doc_no, description, transaction_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if($ins_rec) {
                    $ins_rec->bind_param("iissssd", $active_year, $rec_order, $doc_date, $doc_no, $desc_receive, $t_type, $amount);
                    $ins_rec->execute();
                }
            }
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $refund_order = $_POST['refund_order']; // รับค่าเดิม
        
        // ดึงข้อมูลเดิมเพื่อนำไปอัปเดตหน้า Receivebudget
        $stmt_old = $conn->prepare("SELECT description, budget_year FROM project_refunds WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_desc = "";
        $curr_budget_year = $active_year;
        if ($row_old = $res_old->fetch_assoc()) {
            $old_desc = $row_old['description'];
            $curr_budget_year = $row_old['budget_year'];
        }
        
        // แก้ไข Project
        $stmt = $conn->prepare("UPDATE project_refunds SET refund_order=?, doc_date=?, doc_no=?, description=?, amount=?, project_id=?, activity_id=?, expense_type=?, borrower=?, officer_name=? WHERE id=?");
        $stmt->bind_param("isssdiisssi", $refund_order, $doc_date, $doc_no, $description, $amount, $project_id, $activity_id, $expense_type, $borrower, $officer_name, $id);
        $stmt->execute();

        // แก้ไข Treasury (ตาม ref_id)
        $stmt_treasury = $conn->prepare("UPDATE treasury_refunds SET refund_date=?, doc_no=?, description=?, amount=? WHERE ref_id=? AND ref_type='project'");
        if ($stmt_treasury) {
            $stmt_treasury->bind_param("sssdi", $doc_date, $doc_no, $description, $amount, $id);
            $stmt_treasury->execute();
        }
        
        // แก้ไขในตารางรับเงินงบประมาณ
        if ($old_desc != "") {
            $old_target_desc = "รับคืนเงินยืมโครงการ: " . $old_desc;
            $new_target_desc = "รับคืนเงินยืมโครงการ: " . $description;
            $stmt_rec_upd = $conn->prepare("UPDATE receive_budget SET doc_date=?, doc_no=?, description=?, amount=? WHERE description=? AND budget_year=?");
            if ($stmt_rec_upd) {
                $stmt_rec_upd->bind_param("ssssdi", $doc_date, $doc_no, $new_target_desc, $amount, $old_target_desc, $curr_budget_year);
                $stmt_rec_upd->execute();
            }
        }
    }
    header("Location: ProjectRefundRegistration.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM project_refunds WHERE budget_year = ? ORDER BY refund_order ASC";
$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $active_year);
$stmt->execute();
$result_data = $stmt->get_result();

$total_amount = 0; 

// --- [ของเดิม] ดึงข้อมูล ผลผลิต/โครงการ จากตาราง project_outcomes ---
$projects_opt = [];
$check_po_tb = $conn->query("SHOW TABLES LIKE 'project_outcomes'");
if ($check_po_tb && $check_po_tb->num_rows > 0) {
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

// --- [ของเดิม] ดึงข้อมูลกิจกรรม จากตาราง activities ---
$activities_opt = [];
$act_table = "activities";
$check_act_tb = $conn->query("SHOW TABLES LIKE 'activities'");
if(!$check_act_tb || $check_act_tb->num_rows == 0) {
    $check_act_tb2 = $conn->query("SHOW TABLES LIKE 'activity'");
    if($check_act_tb2 && $check_act_tb2->num_rows > 0) {
        $act_table = "activity";
    }
}

if($conn->query("SHOW TABLES LIKE '$act_table'")->num_rows > 0) {
    $act_cols = [];
    $col_q = $conn->query("SHOW COLUMNS FROM $act_table");
    while($c = $col_q->fetch_assoc()) { $act_cols[] = $c['Field']; }
    
    $act_name_col = "";
    if (in_array('activity_name', $act_cols)) $act_name_col = "activity_name";
    elseif (in_array('name', $act_cols)) $act_name_col = "name";
    elseif (in_array('description', $act_cols)) $act_name_col = "description";
    
    if ($act_name_col != "") {
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

// --- [เพิ่มส่วนนี้ใหม่] ค้นหาและดึงข้อมูล ประเภทรายจ่าย (รายการจ่าย) แบบอัตโนมัติ ---
$expense_options = "";
$exp_table = "";

$possible_exp_tables = ['expensesbudget', 'expense_budget', 'expenses_budget', 'expensebudget', 'expenses', 'expense_types', 'expense_type', 'type_expense'];

foreach ($possible_exp_tables as $ptable) {
    $check_exists = $conn->query("SHOW TABLES LIKE '$ptable'");
    if ($check_exists && $check_exists->num_rows > 0) {
        $exp_table = $ptable;
        break; 
    }
}

if ($exp_table === "") {
    $tb_check_exp = $conn->query("SHOW TABLES");
    if ($tb_check_exp) {
        while ($tb_row = $tb_check_exp->fetch_array()) {
            $t_name = strtolower($tb_row[0]);
            if (strpos($t_name, 'expense') !== false) {
                if (in_array($t_name, ['receive_budget', 'budget_allocations', 'project_refunds', 'treasury_refunds', 'fund_rollovers', 'project_withdrawals', 'treasury_withdrawals'])) continue;
                $exp_table = $tb_row[0];
                break; 
            }
        }
    }
}

if ($exp_table !== "") {
    $col_q_exp = $conn->query("SHOW COLUMNS FROM `$exp_table`");
    $exp_cols = [];
    if($col_q_exp){
        while($c = $col_q_exp->fetch_assoc()) {
            $exp_cols[] = $c['Field'];
        }
    }
    
    $exp_name_col = "";
    $possible_exp_cols = ['name', 'expense_name', 'budget_name', 'title', 'description', 'expense_type', 'type_name', 'expensesbudget', 'expensebudget'];
    
    foreach($possible_exp_cols as $p_col) {
        foreach($exp_cols as $actual_col) {
            if(strtolower($actual_col) == strtolower($p_col)) {
                $exp_name_col = $actual_col;
                break 2;
            }
        }
    }

    if ($exp_name_col == "") {
        foreach($exp_cols as $col) {
            $lcol = strtolower($col);
            if (!in_array($lcol, ['id', 'budget_year', 'created_at', 'updated_at', 'status'])) {
                $exp_name_col = $col;
                break;
            }
        }
    }

    if ($exp_name_col != "") {
        $has_b_year = false;
        foreach($exp_cols as $col) {
            if(strtolower($col) == 'budget_year') {
                $has_b_year = true;
                break;
            }
        }

        if ($has_b_year) {
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
                $e_name = trim($exp[$exp_name_col]);
                if ($e_name != '' && !in_array($e_name, $unique_exps)) {
                    $unique_exps[] = $e_name;
                    $expense_options .= "<option value='".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."</option>";
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
    /* ปรับแต่งปุ่มลงทะเบียนให้เป็นสีน้ำเงินเข้มตามรูปภาพที่ระบุ */
    .btn-add {
        background-color: #0b1526 !important; 
        color: white !important;
        border-radius: 8px;
        padding: 8px 25px;
        font-weight: 500;
        transition: 0.3s;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .btn-add:hover {
        background-color: #1a2a44 !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }

    .total-row {
        background-color: #fff3cd !important;
        font-weight: bold;
        color: #181818;
    }
    
    /* สีพื้นหลัง Modal แบบฟอร์ม สีขาวสะอาดตา */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 30px; 
        border-radius: 4px; 
        border: 1px solid #dee2e6;
    }
    
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 4px 20px; background-color: #e9ecef; border: 1px solid #ccc; color: #333; border-radius: 4px; font-size: 0.9rem; }
    .btn-form:hover { background-color: #d3d9df; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <h2 class="page-title">ทะเบียนคืนเงินโครงการ ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> ลงทะเบียน
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 10%;">ที่เอกสาร</th>
                        <th style="width: 40%;">รายการ</th>
                        <th style="width: 15%;">จำนวนเงิน</th>
                        <th style="width: 10%;">รายละเอียด</th>
                        <th style="width: 10%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            
                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['refund_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']);
                            if($row['is_other_officer']) echo ' <i class="fa-solid fa-triangle-exclamation text-danger"></i>';
                            echo "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            
                            // ปุ่มรายละเอียด
                            echo "<td class='td-center'>";
                            echo '<button class="action-btn text-info" title="รายละเอียด" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-regular fa-rectangle-list"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'ยืนยันการลบ? ข้อมูลในทะเบียนรับเงินงบประมาณจะถูกลบออกด้วย\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</td>";

                            echo "</tr>";
                        }
                        
                        echo "<tr class='total-row'>";
                        echo "<td colspan='4' class='text-center'>รวม</td>";
                        echo "<td class='td-right'>" . number_format($total_amount, 2) . "</td>";
                        echo "<td colspan='2'></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
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
            <div class="modal-header d-block border-0 pb-0">
                <h5 class="modal-title-custom" id="modalTitle">ลงทะเบียน คืนเงินโครงการ ปีประมาณ<?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-4 mb-4">
                <div class="form-white-bg">
                    <form action="ProjectRefundRegistration.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="refund_order" id="refund_order" value="0">

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">วดป ลงทะเบียน</div>
                            <div class="col-md-4">
                                <input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">รายการ</div>
                            <div class="col-md-7">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">ผลผลิต/โครงการ</div>
                            <div class="col-md-7">
                                <select name="project_id" id="project_id" class="form-select form-select-sm">
                                    <option value="0">เลือก</option>
                                    <?php foreach ($projects_opt as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">กิจกรรม</div>
                            <div class="col-md-7">
                                <select name="activity_id" id="activity_id" class="form-select form-select-sm">
                                    <option value="0">เลือก</option>
                                    <?php foreach ($activities_opt as $a): ?>
                                        <option value="<?php echo htmlspecialchars($a['id']); ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3 d-flex align-items-center">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm me-2" required> บาท
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">ประเภทรายการจ่าย</div>
                            <div class="col-md-4">
                                <select name="expense_type" id="expense_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <?php echo $expense_options; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-4 form-label-custom">ผู้คืนเงินโครงการ</div>
                            <div class="col-md-5">
                                <input type="text" name="borrower" id="borrower" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 form-label-custom">เจ้าหน้าที่</div>
                            <div class="col-md-5">
                                <input type="text" name="officer_name" id="officer_name" class="form-control form-control-sm" value="<?php echo $_SESSION['name'] ?? ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-3 text-center">
                            <div class="col-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="save_to_receive" id="save_to_receive" value="1" checked>
                                    <label class="form-check-label" for="save_to_receive" style="font-size: 0.95rem;">บันทึกข้อมูลในทะเบียนรับเงินงบประมาณด้วย</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <button type="submit" class="btn-form me-2">ตกลง</button>
                            <button type="button" class="btn-form" data-bs-dismiss="modal">ย้อนกลับ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการคืนเงินโครงการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">วดป ลงทะเบียน</th><td id="detail_doc_date"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ที่เอกสาร</th><td id="detail_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการ</th><td id="detail_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">โครงการ</th><td id="detail_project_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรม</th><td id="detail_activity_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงิน</th><td id="detail_amount" class="text-danger fw-bold fs-5"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ประเภทรายการจ่าย</th><td id="detail_expense_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผู้คืนเงินโครงการ</th><td id="detail_borrower"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">เจ้าหน้าที่</th><td id="detail_officer"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ดึงข้อมูล array มาใช้เทียบชื่อใน JavaScript
    const projectsList = <?php echo json_encode($projects_opt, JSON_UNESCAPED_UNICODE); ?>;
    const activitiesList = <?php echo json_encode($activities_opt, JSON_UNESCAPED_UNICODE); ?>;
    const currentUserName = '<?php echo isset($_SESSION["fullname"]) ? addslashes($_SESSION["fullname"]) : (isset($_SESSION["name"]) ? addslashes($_SESSION["name"]) : ""); ?>';

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        document.getElementById('modalTitle').innerHTML = 'ลงทะเบียน คืนเงินโครงการ ปีประมาณ<?php echo $active_year; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('officer_name').value = currentUserName;
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข คืนเงินโครงการ ปีประมาณ<?php echo $active_year; ?>';
        
        document.getElementById('refund_order').value = data.refund_order || '0';
        document.getElementById('doc_date').value = data.doc_date || '<?php echo date('Y-m-d'); ?>';
        document.getElementById('doc_no').value = data.doc_no || '';
        document.getElementById('description').value = data.description || '';
        
        // จัดการเรื่อง Project ID
        let projSelect = document.getElementById('project_id');
        let projValue = data.project_id || '0';
        if (projValue != '0' && !Array.from(projSelect.options).some(opt => opt.value == projValue)) {
            projSelect.add(new Option('โครงการ ID: ' + projValue, projValue));
        }
        projSelect.value = projValue;

        // จัดการเรื่อง Activity ID (ให้แสดงค่าเดิมเวลากดแก้ไขเผื่อไม่ตรงใน Dropdown)
        let actSelect = document.getElementById('activity_id');
        let actValue = data.activity_id || '0';
        if (actValue != '0' && !Array.from(actSelect.options).some(opt => opt.value == actValue)) {
            actSelect.add(new Option('กิจกรรม ID: ' + actValue, actValue));
        }
        actSelect.value = actValue;

        // [แก้ไข] จัดการ Dropdown ประเภทรายการจ่าย (expense_type) ให้แสดงค่าเดิมตอนกดแก้ไข
        let expSelect = document.getElementById('expense_type');
        let expValue = data.expense_type || '';
        if (expValue != '' && !Array.from(expSelect.options).some(opt => opt.value == expValue)) {
            expSelect.add(new Option(expValue, expValue));
        }
        expSelect.value = expValue;

        document.getElementById('borrower').value = data.borrower || '';
        document.getElementById('amount').value = data.amount || '';
        document.getElementById('officer_name').value = data.officer_name || '';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('detail_doc_date').innerText = data.doc_date || '-';
        document.getElementById('detail_doc_no').innerText = data.doc_no || '-';
        document.getElementById('detail_description').innerText = data.description || '-';
        
        let pFound = projectsList.find(p => p.id == data.project_id);
        document.getElementById('detail_project_name').innerText = pFound ? pFound.name : '-';
        
        let aFound = activitiesList.find(a => a.id == data.activity_id);
        document.getElementById('detail_activity_name').innerText = aFound ? aFound.name : '-';
        
        let amount = parseFloat(data.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('detail_amount').innerText = amount + " บาท";

        document.getElementById('detail_expense_type').innerText = data.expense_type || '-';
        document.getElementById('detail_borrower').innerText = data.borrower || '-';
        document.getElementById('detail_officer').innerText = data.officer_name || '-';

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>