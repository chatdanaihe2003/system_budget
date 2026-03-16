<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ทะเบียนขอเบิก/ขอยืมโครงการ ";
$current_page = basename($_SERVER['PHP_SELF']); // ดึงชื่อไฟล์ปัจจุบันอัตโนมัติ
$page_header = 'ทะเบียนขอเบิก/ขอยืมเงินโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- ดึงสิทธิ์ผู้ใช้งาน ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($nav_role === 'admin');

// ป้องกันผู้ไม่มีสิทธิ์เข้าถึง (อ้างอิงจาก Navbar ให้ Admin และ การเงินเข้าได้)
if ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user' || $nav_role === 'แผนงาน') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='index.php';</script>";
    exit();
}

// --- ตรวจสอบ/สร้างตาราง (เผื่อยังไม่มีตารางนี้ในระบบ) ---
$check_table = $conn->query("SHOW TABLES LIKE 'project_withdrawals'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE project_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        budget_year INT,
        withdrawal_order INT DEFAULT 0,
        doc_date DATE,
        doc_no VARCHAR(100),
        withdrawal_type INT DEFAULT 4,
        description TEXT,
        project_id INT,
        activity_id INT DEFAULT 0,
        amount DECIMAL(15,2) DEFAULT 0,
        requester VARCHAR(100),
        officer_name VARCHAR(100),
        status VARCHAR(50) DEFAULT 'green',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// --- ซ่อมแซมฐานข้อมูลอัตโนมัติ ---
$cols_to_add = [
    'withdrawal_order' => "INT DEFAULT 0",
    'request_type' => "VARCHAR(100) NULL",
    'doc_location' => "VARCHAR(255) NULL",
    'expense_type' => "VARCHAR(255) NULL",
    'doc_date' => "DATE NULL",
    'doc_no' => "VARCHAR(100) NULL",
    'description' => "TEXT NULL",
    'requester' => "VARCHAR(100) NULL",
    'officer_name' => "VARCHAR(100) NULL",
    'user_id' => "INT DEFAULT 0"
];
foreach ($cols_to_add as $col => $def) {
    $check_col = $conn->query("SHOW COLUMNS FROM project_withdrawals LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE project_withdrawals ADD $col $def");
    }
}

// --------------------------------------------------------------------------------
// --- ตรวจสอบชื่อคอลัมน์ของ project_expenses อัตโนมัติ ป้องกัน Error SQL ---
// --------------------------------------------------------------------------------
$col_desc = 'description';
$col_amt = 'request_amount';
$has_request_amount = false;
$exp_approver_col = "";

$chk_exp = $conn->query("SHOW COLUMNS FROM project_expenses");
if ($chk_exp) {
    while($c = $chk_exp->fetch_assoc()) {
        if ($c['Field'] === 'details') $col_desc = 'details';
        if ($c['Field'] === 'cutoff_amount') $col_amt = 'cutoff_amount';
        if ($c['Field'] === 'request_amount') $has_request_amount = true;
        
        // หากลุ่มคอลัมน์ที่เก็บ ID คนที่กดอนุมัติในหน้า Approve the cut off amount
        if (in_array($c['Field'], ['approver_id', 'officer_name', 'approved_by', 'admin_id'])) {
            $exp_approver_col = $c['Field'];
        }
    }
}

// --------------------------------------------------------------------------------
// --- AUTO SYNC (ระบบดึงข้อมูลอัตโนมัติ) ---
// --------------------------------------------------------------------------------
$res_sync = $conn->query("SHOW TABLES LIKE 'project_expenses'");
if ($res_sync && $res_sync->num_rows > 0) {
    $sync_query = "SELECT * FROM project_expenses WHERE approval_status = 'approved'";
    $res_exp = $conn->query($sync_query);
    
    if ($res_exp && $res_exp->num_rows > 0) {
        while ($exp = $res_exp->fetch_assoc()) {
            $p_id = isset($exp['project_id']) ? intval($exp['project_id']) : 0;
            $amt = isset($exp['cutoff_amount']) ? floatval($exp['cutoff_amount']) : (isset($exp['request_amount']) ? floatval($exp['request_amount']) : 0);
            $desc = !empty($exp['details']) ? $exp['details'] : (!empty($exp['description']) ? $exp['description'] : '-');
            $d_date = !empty($exp['expense_date']) ? $exp['expense_date'] : (!empty($exp['cutoff_date']) ? $exp['cutoff_date'] : date('Y-m-d'));
            
            $check_stmt = $conn->prepare("SELECT id FROM project_withdrawals WHERE project_id = ? AND amount = ? AND description = ?");
            if ($check_stmt) {
                $check_stmt->bind_param("ids", $p_id, $amt, $desc);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                
                if ($check_res->num_rows == 0) {
                    $b_year = isset($exp['budget_year']) ? intval($exp['budget_year']) : intval($active_year);
                    $doc_no = !empty($exp['ref_document']) ? $exp['ref_document'] : 'ไม่มีเลขที่';
                    $req_type = 'ขอเบิก';
                    $status = 'pending';
                    
                    $u_id = isset($exp['user_id']) ? intval($exp['user_id']) : 0;
                    $req_by = 'ไม่ระบุชื่อ';
                    
                    if ($u_id > 0) {
                        $s_user = $conn->query("SELECT name FROM users WHERE id = $u_id");
                        if ($s_user && $s_user->num_rows > 0) $req_by = $s_user->fetch_assoc()['name'];
                    } elseif (!empty($exp['recorded_by'])) {
                        $req_by = $exp['recorded_by'];
                    }
                    
                    // ดึง ID ผู้อนุมัติมาบันทึกเก็บไว้เลยตอน Sync
                    $exp_approver = NULL;
                    if ($exp_approver_col !== '' && !empty($exp[$exp_approver_col])) {
                        $exp_approver = $exp[$exp_approver_col];
                    }
                    
                    $ins_stmt = $conn->prepare("INSERT INTO project_withdrawals 
                        (budget_year, doc_date, doc_no, request_type, description, project_id, amount, requester, status, user_id, officer_name) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($ins_stmt) {
                        $ins_stmt->bind_param("issssidssis", $b_year, $d_date, $doc_no, $req_type, $desc, $p_id, $amt, $req_by, $status, $u_id, $exp_approver);
                        $ins_stmt->execute();
                    }
                }
            }
        }
    }
}

// --------------------------------------------------------------------------------
// --- ดึงข้อมูลประเภทรายจ่ายจากหน้า Expensesbudget.php เพื่อไปใส่ใน Dropdown ---
// --------------------------------------------------------------------------------
$expense_options = [];
$expense_table_name = 'expense_types'; 

$res_exp_type = $conn->query("SHOW TABLES LIKE '$expense_table_name'");
if ($res_exp_type && $res_exp_type->num_rows > 0) {
    $q_exp = $conn->query("SELECT * FROM $expense_table_name ORDER BY id ASC");
    if ($q_exp) {
        while ($r = $q_exp->fetch_assoc()) {
            $e_name = '';
            if (isset($r['name'])) $e_name = $r['name'];
            elseif (isset($r['expense_name'])) $e_name = $r['expense_name'];
            elseif (isset($r['title'])) $e_name = $r['title'];
            else {
                $keys = array_keys($r);
                if(isset($keys[1])) $e_name = $r[$keys[1]];
            }
            if (trim($e_name) !== '') $expense_options[] = trim($e_name);
        }
    }
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (แก้ไข / ลบเดี่ยว / ลบหลายรายการ / อนุมัติ) ---
// --------------------------------------------------------------------------------

// 1. จัดการหลายรายการพร้อมกัน (Bulk Delete หรือ Bulk Approve)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $action_type = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_ids'];

    if (is_array($selected_ids) && count($selected_ids) > 0) {
        if ($action_type === 'delete' && $is_admin) {
            foreach ($selected_ids as $id) {
                $id = intval($id);
                $stmt_get = $conn->prepare("SELECT project_id, description FROM project_withdrawals WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $res = $stmt_get->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND $col_desc = ? AND approval_status = 'approved' LIMIT 1");
                    if($stmt_del_exp){
                        $stmt_del_exp->bind_param("is", $row['project_id'], $row['description']);
                        $stmt_del_exp->execute();
                    }
                }
                $stmt_del = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
            }
            echo "<script>window.location='".$current_page."?status=deleted';</script>";
            exit();
        } 
        elseif ($action_type === 'approve') {
            $approver_name = isset($_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'ผู้ดูแลระบบ');
            foreach ($selected_ids as $id) {
                $id = intval($id);
                $stmt_app = $conn->prepare("UPDATE project_withdrawals SET status='approved', officer_name=? WHERE id=?");
                $stmt_app->bind_param("si", $approver_name, $id);
                $stmt_app->execute();
            }
            echo "<script>window.location='".$current_page."?status=approved';</script>";
            exit();
        }
    }
}

// 2. ลบแบบทีละรายการ (เดี่ยว) - เฉพาะ Admin
if (isset($_GET['delete_id'])) {
    if ($is_admin) {
        $id = intval($_GET['delete_id']);
        $stmt_get = $conn->prepare("SELECT project_id, description FROM project_withdrawals WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result();
        if ($row = $res->fetch_assoc()) {
            $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND $col_desc = ? AND approval_status = 'approved' LIMIT 1");
            if($stmt_del_exp){
                $stmt_del_exp->bind_param("is", $row['project_id'], $row['description']);
                $stmt_del_exp->execute();
            }
        }
        $stmt = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
        if($stmt){
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        echo "<script>window.location='".$current_page."?status=deleted';</script>";
        exit();
    }
}

// 3. บันทึกการแก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $edit_id = intval($_POST['edit_id']);
    $doc_date = $_POST['doc_date'];
    $doc_no = isset($_POST['doc_no']) ? $_POST['doc_no'] : '';
    $doc_location = isset($_POST['doc_location']) ? $_POST['doc_location'] : '';
    $request_type = $_POST['request_type'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $description = $_POST['description'];
    $amount = $_POST['amount'];

    $stmt = $conn->prepare("UPDATE project_withdrawals SET doc_date=?, doc_no=?, doc_location=?, request_type=?, expense_type=?, description=?, amount=? WHERE id=?");
    if($stmt){
        $stmt->bind_param("ssssssdi", $doc_date, $doc_no, $doc_location, $request_type, $expense_type, $description, $amount, $edit_id);
        if ($stmt->execute()) {
            echo "<script>window.location='".$current_page."?status=updated';</script>";
            exit();
        }
    }
}

// 4. บันทึกการอนุมัติแบบเดี่ยว
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $approve_id = intval($_POST['approve_id']);
    $approved_amount = floatval($_POST['approved_amount']); 
    
    $approver_name = isset($_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'ผู้ดูแลระบบ');
    
    $stmt_get = $conn->prepare("SELECT project_id, description, amount FROM project_withdrawals WHERE id = ?");
    $stmt_get->bind_param("i", $approve_id);
    $stmt_get->execute();
    $res = $stmt_get->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $proj_id = $row['project_id'];
        $desc = $row['description'];

        $stmt_update_exp = $conn->prepare("UPDATE project_expenses SET $col_amt = ? WHERE project_id = ? AND $col_desc = ? LIMIT 1");
        if($stmt_update_exp){
            $stmt_update_exp->bind_param("dis", $approved_amount, $proj_id, $desc);
            $stmt_update_exp->execute();
        }
    }

    $stmt = $conn->prepare("UPDATE project_withdrawals SET status='approved', amount=?, officer_name=? WHERE id=?");
    if($stmt){
        $stmt->bind_param("dsi", $approved_amount, $approver_name, $approve_id);
        if ($stmt->execute()) {
            echo "<script>window.location='".$current_page."?status=approved';</script>";
            exit();
        }
    }
}

// 5. บันทึกการไม่อนุมัติ (Reject) 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $reject_id = intval($_POST['approve_id']);
    
    $stmt_get = $conn->prepare("SELECT project_id, description FROM project_withdrawals WHERE id = ?");
    $stmt_get->bind_param("i", $reject_id);
    $stmt_get->execute();
    $res = $stmt_get->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND $col_desc = ? AND approval_status = 'approved' LIMIT 1");
        if($stmt_del_exp){
            $stmt_del_exp->bind_param("is", $row['project_id'], $row['description']);
            $stmt_del_exp->execute();
        }
    }

    $stmt_rej = $conn->prepare("UPDATE project_withdrawals SET status='rejected' WHERE id=?");
    if($stmt_rej){
        $stmt_rej->bind_param("i", $reject_id);
        if ($stmt_rej->execute()) {
            echo "<script>window.location='".$current_page."?status=rejected';</script>";
            exit();
        }
    }
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// --- รับค่าการค้นหา ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search . "%";

// --- [อัปเดต SQL Query] ให้ดึงข้อมูลชื่อผู้อนุมัติและ p.budget_type แบบครอบคลุม ---
$amt_cond = "pe.$col_amt = w.amount";
if ($has_request_amount) {
    $amt_cond = "(pe.$col_amt = w.amount OR pe.request_amount = w.amount)";
}

$join_exp = "LEFT JOIN project_expenses pe ON w.project_id = pe.project_id AND $amt_cond";

$join_u3 = "";
$sel_u3 = ", NULL AS exp_approver_name, NULL AS raw_exp_approver";
if ($exp_approver_col !== "") {
    $join_u3 = "LEFT JOIN users u3 ON pe.$exp_approver_col = CAST(u3.id AS CHAR) OR pe.$exp_approver_col = u3.name";
    $sel_u3 = ", u3.name AS exp_approver_name, pe.$exp_approver_col AS raw_exp_approver";
}

$sql_base = "SELECT w.*, p.project_code, p.project_name, p.group_name, p.budget_type, 
                    u.name AS real_requester_name, 
                    u2.name AS actual_approver_name
                    $sel_u3
             FROM project_withdrawals w 
             LEFT JOIN project_outcomes p ON w.project_id = p.id 
             LEFT JOIN users u ON w.user_id = u.id OR w.requester = u.id 
             LEFT JOIN users u2 ON w.officer_name = CAST(u2.id AS CHAR) OR w.officer_name = u2.name 
             $join_exp
             $join_u3
             WHERE w.budget_year = ? AND w.status NOT IN ('approved', 'rejected')";

if ($search != "") {
    $sql_data = $sql_base . " AND (p.project_name LIKE ? OR p.project_code LIKE ? OR w.doc_no LIKE ? OR w.description LIKE ?) ORDER BY w.id DESC";
    $stmt_data = $conn->prepare($sql_data);
    if($stmt_data){
        $stmt_data->bind_param("issss", $active_year, $search_param, $search_param, $search_param, $search_param);
    } else {
        die("<div class='container mt-5'><div class='alert alert-danger'><strong>SQL Error:</strong> " . $conn->error . "</div></div>");
    }
} else {
    $sql_data = $sql_base . " ORDER BY w.id DESC";
    $stmt_data = $conn->prepare($sql_data);
    if($stmt_data){
        $stmt_data->bind_param("i", $active_year);
    } else {
        die("<div class='container mt-5'><div class='alert alert-danger'><strong>SQL Error:</strong> " . $conn->error . "</div></div>");
    }
}

$stmt_data->execute();
$result_data = $stmt_data->get_result();

// อาร์เรย์สำหรับแปลงชื่อกลุ่มงานให้สั้นลง
$group_abbr = [
    "กลุ่มอำนวยการ" => "กลุ่มอำนวยการ",
    "กลุ่มกฎหมายและคดี" => "กลุ่มกฎหมายฯ",
    "กลุ่มนโยบายและแผน" => "กลุ่มนโยบายฯ",
    "กลุ่มบริหารการเงินและสินทรัพย์" => "กลุ่มการเงินฯ",
    "กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร" => "กลุ่มส่งเสริมการศึกษาทางไกล",
    "กลุ่มนิเทศ ติดตาม และประเมินผลการจัดการศึกษา" => "กลุ่มนิเทศฯ",
    "กลุ่มบริหารงานบุคคล" => "กลุ่มบุคคลฯ",
    "กลุ่มพัฒนาครูและบุคลากรทางการศึกษา" => "กลุ่มพัฒนาครูฯ",
    "กลุ่มส่งเสริมการจัดการศึกษา" => "กลุ่มส่งเสริมฯ",
    "หน่วยตรวจสอบภายใน" => "หน่วยตรวจสอบฯ"
];
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .badge-custom { padding: 6px 12px; font-weight: normal; font-size: 0.85rem; border-radius: 6px; }
    .amount-display { font-size: 1.05rem; font-weight: bold; }
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
    .readonly-box { background-color: #f8f9fa; padding: 8px 12px; border-radius: 6px; border: 1px solid #e9ecef; color: #495057; }
    
    .nav-link-custom.active {
        color: #0dcaf0 !important; 
        border-bottom: 3px solid #0dcaf0 !important; 
        padding-bottom: 5px; 
        font-weight: bold;
    }
</style>

<div class="container-fluid pb-5 px-4">
    <form action="" method="POST" id="bulkActionForm">
        <input type="hidden" name="bulk_action" id="bulk_action_type" value="">
        
        <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title m-0 fw-bold text-primary">
                    <i class="fa-solid fa-book-journal-whills me-2"></i> ทะเบียนขอเบิก/ขอยืมเงินโครงการ
                </h2>
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส, ชื่อโครงการ, เลขที่..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary px-4" type="button" onclick="window.location.href='<?php echo $current_page; ?>?search='+document.getElementById('searchInput').value;">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="<?php echo $current_page; ?>" class="btn btn-outline-danger d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>

                    <button type="button" class="btn btn-success shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkAction('approve')">
                        <i class="fa-solid fa-check-to-slot me-1"></i> อนุมัติรายการที่เลือก
                    </button>

                    <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-danger shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkAction('delete')">
                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center py-3" style="width: 3%;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <th class="text-center py-3" style="width: 5%;">ที่</th>
                            <th class="text-center py-3" style="width: 10%;">วันที่</th>
                            <th class="py-3" style="width: 12%;">อ้างอิงเอกสาร</th>
                            <th class="py-3" style="width: 25%;">โครงการ / รายละเอียดการขอเบิก</th>
                            <th class="text-end py-3" style="width: 12%;">จำนวนเงิน (บาท)</th>
                            <th class="text-center py-3" style="width: 15%;">ผู้รับผิดชอบโครงการ / กลุ่มงาน / ผู้อนุมัติตัดยอด</th>
                            <th class="text-center py-3" style="width: 12%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_amount = 0;
                        $i = 1;

                        if ($result_data && $result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                $total_amount += $row['amount'];
                                
                                $proj_code = $row['project_code'] ? $row['project_code'] : '-';
                                $proj_name = htmlspecialchars($row['project_name'] ?? 'ไม่พบชื่อโครงการ');
                                
                                // ตัดข้อความกลุ่มงาน
                                $raw_group = htmlspecialchars($row['group_name'] ?? '-');
                                $group_name_short = array_key_exists($raw_group, $group_abbr) ? $group_abbr[$raw_group] : $raw_group;

                                // แปลงชื่อผู้ขอ
                                $display_requester = '-';
                                if (!empty($row['real_requester_name'])) {
                                    $display_requester = $row['real_requester_name'];
                                } elseif (!empty($row['requester']) && $row['requester'] !== '0') {
                                    $display_requester = $row['requester'];
                                }
                                $display_requester = htmlspecialchars($display_requester);
                                
                                // -------------------------------------------------------------
                                // [แก้ไขจุดสำคัญ] ระบบสแกนหาชื่อ "ผู้ขอเบิก" และ "ผู้อนุมัติ" โดยแปลง ID ให้เป็นชื่อจริง
                                // -------------------------------------------------------------
                                
                                // 2. หาชื่อผู้อนุมัติตัดยอด
                                $display_approver = '-';
                                if (!empty($row['officer_name']) && $row['officer_name'] !== '0') {
                                    $val = $row['officer_name'];
                                    if (is_numeric($val)) {
                                        $uq = $conn->query("SELECT name FROM users WHERE id = " . intval($val));
                                        if ($uq && $uq->num_rows > 0) $display_approver = $uq->fetch_assoc()['name'];
                                        else $display_approver = $val;
                                    } else {
                                        $display_approver = $val;
                                    }
                                }
                                
                                // ถ้ายังไม่ได้ชื่อผู้อนุมัติ ให้ไปขุดหาจากตาราง project_expenses
                                if ($display_approver === '-' || empty(trim($display_approver))) {
                                    $p_id = intval($row['project_id']);
                                    $q_exp = $conn->query("SELECT * FROM project_expenses WHERE project_id = $p_id AND approval_status = 'approved' ORDER BY id DESC LIMIT 1");
                                    if ($q_exp && $q_exp->num_rows > 0) {
                                        $exp = $q_exp->fetch_assoc();
                                        $possible_cols = ['approver_id', 'approved_by', 'officer_name', 'admin_id', 'user_id'];
                                        foreach ($possible_cols as $c) {
                                            if (!empty($exp[$c])) {
                                                $val = $exp[$c];
                                                if (is_numeric($val)) {
                                                    $uq = $conn->query("SELECT name FROM users WHERE id = " . intval($val));
                                                    if ($uq && $uq->num_rows > 0) {
                                                        $display_approver = $uq->fetch_assoc()['name'];
                                                        break;
                                                    }
                                                } else {
                                                    $display_approver = $val;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($display_approver === '-' || empty(trim($display_approver))) {
                                    $display_approver = 'รอการอนุมัติ'; 
                                }
                                $display_approver = htmlspecialchars($display_approver);
                                // -------------------------------------------------------------

                                echo "<tr>";
                                
                                // Checkbox ย่อยของแต่ละแถว
                                echo "<td class='text-center'>
                                        <input class='form-check-input item-checkbox' type='checkbox' name='selected_ids[]' value='{$row['id']}'>
                                      </td>";

                                echo "<td class='text-center text-muted fw-bold'>" . $i++ . "</td>";
                                echo "<td class='text-center'>" . thai_date_short($row['doc_date']) . "</td>";
                                echo "<td><span class='badge bg-secondary badge-custom'>" . htmlspecialchars($row['doc_no'] ?: 'ไม่มีเลขที่') . "</span></td>";
                                
                                echo "<td>
                                        <div class='text-primary fw-bold mb-1' style='font-size:0.85rem;'>[$proj_code] $proj_name</div>
                                        <div class='text-dark small'><i class='fa-solid fa-angles-right text-muted me-1'></i>" . htmlspecialchars($row['description']) . "</div>
                                      </td>";
                                      
                                echo "<td class='text-end fw-bold text-success amount-display'>" . number_format($row['amount'], 2) . "</td>";
                                
                                echo "<td class='text-center small'>
                                        <div class='text-muted'>ผู้ขอ: <span class='text-dark'>".$display_requester."</span></div>
                                        <div class='text-muted'>กลุ่มงาน: <span class='text-dark' title='$raw_group'>".$group_name_short."</span></div>
                                        <div class='text-muted mt-1 pt-1 border-top border-dashed'>ผู้อนุมัติตัดยอด: <span class='text-primary fw-bold'>".$display_approver."</span></div>
                                      </td>";
                                
                                echo "<td class='text-center text-nowrap'>";
                                echo "<div class='d-flex flex-column align-items-center justify-content-center'>";
                                
                                // ส่วนของปุ่ม แก้ไข ลบ และ อนุมัติ
                                echo "<div>";
                                // ส่งข้อมูลเข้า Javascript สำหรับปุ่มแก้ไขและดูรายละเอียด
                                $js_data = htmlspecialchars(json_encode([
                                    'id' => $row['id'],
                                    'order' => $row['withdrawal_order'] ?? $row['id'],
                                    'date' => $row['doc_date'],
                                    'doc' => $row['doc_no'],
                                    'doc_location' => $row['doc_location'] ?? '',
                                    'request_type' => $row['request_type'] ?? '',
                                    'expense_type' => $row['expense_type'] ?? '',
                                    'proj_name' => "[$proj_code] $proj_name",
                                    'group_name' => $row['group_name'] ?? '-', 
                                    'budget_type' => $row['budget_type'] ?? '-', // เพิ่มการส่งประเภทงบประมาณเข้า JS
                                    'requester' => $display_requester,
                                    'approver' => $display_approver,
                                    'desc' => $row['description'],
                                    'amount' => $row['amount']
                                ]), ENT_QUOTES, 'UTF-8');

                                // ปุ่มอนุมัติให้ยืมเงิน/เบิกเงิน
                                echo "<button type='button' class='btn btn-sm btn-success shadow-sm px-2 me-1' title='อนุมัติให้ยืมเงิน/เบิกเงิน' onclick='openApproveModal({$js_data})'><i class='fa-solid fa-check-to-slot'></i></button>";

                                // ปุ่มแก้ไข 
                                echo "<button type='button' class='btn btn-sm btn-outline-warning shadow-sm px-2 me-1' title='แก้ไขข้อมูล' onclick='openEditModal({$js_data})'><i class='fa-solid fa-pen-to-square'></i></button>";

                                // ปุ่มลบเดี่ยว (เฉพาะ Admin)
                                if ($is_admin) {
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2' title='ลบรายการ' onclick='openDeleteModal(".$row['id'].")'><i class='fa-solid fa-trash-can'></i></button>";
                                }
                                echo "</div>"; // ปิด div สำหรับปุ่ม
                                
                                echo "</div>"; // ปิด div flex-column
                                echo "</td>";
                                echo "</tr>";
                            }
                            
                            $colspan_left = 5;

                            echo "<tr class='total-row'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมยอดขอเบิก/ขอยืมโครงการ :</strong></td>";
                            echo "<td class='text-end py-3 text-success fs-5'><strong>" . number_format($total_amount, 2) . "</strong></td>";
                            echo "<td colspan='2'></td>";
                            echo "</tr>";

                        } else {
                            $colspan_all = 8;
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-solid fa-book-journal-whills fs-1 mb-3 d-block text-light'></i>ยังไม่มีทะเบียนรายการรออนุมัติในปีงบประมาณ $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST" id="formApproveModal">
                <input type="hidden" name="action" id="modal_action" value="approve">
                <input type="hidden" name="approve_id" id="approve_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-to-slot me-2"></i> อนุมัติให้ยืมเงิน/เบิกเงิน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border shadow-sm mb-4">
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">เลขทะเบียนรับ DB:</div>
                            <div class="col-md-9 fw-bold text-primary fs-5" id="view_disp_order"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">โครงการ:</div>
                            <div class="col-md-9 text-dark fw-bold" id="view_disp_proj_name"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">ผู้ขอเบิก/ขอยืม:</div>
                            <div class="col-md-9 text-dark" id="view_disp_requester"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">กลุ่มงาน:</div>
                            <div class="col-md-9 text-dark" id="view_disp_group_name"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">ประเภทงบประมาณ:</div>
                            <div class="col-md-9 text-info fw-bold" id="view_disp_budget_type"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-muted fw-bold">ผู้อนุมัติตัดยอด:</div>
                            <div class="col-md-9 text-dark" id="view_disp_cutoff_approver"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1">วันที่ทำรายการ</label>
                            <div class="readonly-box" id="view_doc_date"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1">ประเภทการทำรายการ</label>
                            <div class="readonly-box" id="view_request_type"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted fw-bold mb-1">ประเภทรายจ่าย</label>
                        <div class="readonly-box" id="view_expense_type"></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1">อ้างอิงเอกสาร</label>
                            <div class="readonly-box" id="view_doc_no"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1">ที่เอกสาร</label>
                            <div class="readonly-box" id="view_doc_location"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted fw-bold mb-1">รายละเอียดการขอเบิก/ขอยืม</label>
                        <div class="readonly-box" id="view_description" style="min-height: 60px;"></div>
                    </div>

                    <div class="row mb-3 align-items-center bg-light p-3 rounded border">
                        <div class="col-md-8 text-end">
                            <label class="fw-bold text-success fs-6 mb-0">จำนวนเงินที่อนุมัติ (บาท)</label>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="approved_amount" id="approve_amount_input" class="form-control form-control-lg text-end text-success fw-bold" required>
                            <small class="text-muted d-block text-end mt-1">ยอดที่อนุมัติจริง</small>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-center">
                    <button type="button" class="btn btn-danger px-4 fw-bold me-3" onclick="openConfirmRejectModal()">
                        <i class="fa-solid fa-ban me-1"></i> ไม่อนุมัติ (ส่งเงินคืน)
                    </button>
                    <button type="submit" class="btn btn-success px-4 fw-bold" onclick="document.getElementById('modal_action').value='approve';">
                        <i class="fa-solid fa-check me-1"></i> ยืนยันอนุมัติ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmRejectModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-ban me-2"></i> ยืนยันการไม่อนุมัติรายการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-money-bill-transfer text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">ยืนยันไม่อนุมัติรายการนี้ใช่หรือไม่?</h4>
                <div class="alert alert-warning mt-4 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> ยอดเงินจะถูกคืนกลับเข้าสู่โครงการอัตโนมัติ
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;" onclick="executeReject()">
                    <i class="fa-solid fa-ban me-1"></i> ยืนยันไม่อนุมัติ
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขข้อมูลทะเบียนขอเบิก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border shadow-sm mb-4">
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">เลขทะเบียนรับ DB:</div>
                            <div class="col-md-9 fw-bold text-primary fs-5" id="disp_order"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">โครงการ:</div>
                            <div class="col-md-9 text-dark fw-bold" id="disp_proj_name"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">ผู้ขอเบิก/ขอยืม:</div>
                            <div class="col-md-9 text-dark" id="disp_requester"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">กลุ่มงาน:</div>
                            <div class="col-md-9 text-dark" id="disp_group_name"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">ประเภทงบประมาณ:</div>
                            <div class="col-md-9 text-info fw-bold" id="disp_budget_type"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 text-muted fw-bold">ผู้อนุมัติตัดยอด:</div>
                            <div class="col-md-9 text-dark" id="disp_cutoff_approver_edit"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-muted fw-bold">ผู้อนุมัติให้เบิก/ให้ยืม:</div>
                            <div class="col-md-9 text-primary fw-bold" id="disp_payment_approver"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่ทำรายการ <span class="text-danger">*</span></label>
                            <input type="date" name="doc_date" id="edit_doc_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ประเภทการทำรายการ <span class="text-danger">*</span></label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="request_type" id="type_borrow" value="ขอยืมเงินงบประมาณ" required>
                                    <label class="form-check-label text-dark" for="type_borrow">ขอยืมเงินงบประมาณ</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="request_type" id="type_withdraw" value="ขอเบิก" required>
                                    <label class="form-check-label text-dark" for="type_withdraw">ขอเบิก</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายจ่าย <span class="text-danger">*</span></label>
                        <select name="expense_type" id="edit_expense_type" class="form-select" required>
                            <option value="" disabled selected>-- เลือกประเภทรายจ่าย --</option>
                            <?php foreach($expense_options as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($expense_options)): ?>
                            <small class="text-danger">* ไม่พบข้อมูลประเภทรายจ่ายในระบบ</small>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">อ้างอิงเอกสาร</label>
                            <input type="text" name="doc_no" id="edit_doc_no" class="form-control" placeholder="ระบุเลขที่เอกสาร (ถ้ามี)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ที่เอกสาร</label>
                            <input type="text" name="doc_location" id="edit_doc_location" class="form-control" placeholder="ระบุที่เก็บเอกสาร (ถ้ามี)">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">รายละเอียดการขอเบิก/ขอยืม <span class="text-danger">*</span></label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="row mb-3 align-items-center bg-light p-3 rounded border">
                        <div class="col-md-8 text-end">
                            <label class="form-label fw-bold text-success fs-6 mb-0">จำนวนเงินที่เบิก (บาท) <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control form-control-lg text-end text-success fw-bold" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4"><i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการลบข้อมูล</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-regular fa-circle-xmark text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบทะเบียนนี้ใช่หรือไม่?</h4>
                <div class="alert alert-warning mt-4 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> หากลบรายการนี้ ประวัติการเบิกจ่ายที่อนุมัติแล้วในหน้า <b>ตัดยอดโครงการ</b> จะถูกลบออกไปด้วย และไม่สามารถกู้คืนได้
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertNoSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-warning bg-opacity-25 rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-exclamation text-warning" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">แจ้งเตือน!</h4>
                <p class="text-muted fs-6 mb-4">กรุณาเลือกรายการอย่างน้อย 1 รายการ</p>
                <button type="button" class="btn btn-warning text-dark px-5 fw-bold w-100" style="border-radius: 8px;" data-bs-dismiss="modal">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can-arrow-up me-2"></i> ยืนยันการลบหลายรายการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-layer-group text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูล <span id="bulkCountText" class="text-danger fw-bold fs-3"></span> ทะเบียน?</h4>
                <div class="alert alert-warning mt-4 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> หากลบรายการเหล่านี้ ประวัติการเบิกจ่ายที่อนุมัติแล้วในหน้า <b>ตัดยอดโครงการ</b> จะถูกลบออกไปด้วยทั้งหมด
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" onclick="document.getElementById('bulkActionForm').submit();" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบทั้งหมด</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-to-slot me-2"></i> ยืนยันการอนุมัติ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-check-circle text-success mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการอนุมัติ <span id="bulkApproveCountText" class="text-success fw-bold fs-3"></span> รายการ?</h4>
                <p class="text-muted mb-0 fs-5">ข้อมูลที่ถูกอนุมัติจะถูกบันทึกและซ่อนจากหน้านี้</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-success px-4 fw-bold" onclick="document.getElementById('bulkActionForm').submit();" style="border-radius: 8px;"><i class="fa-solid fa-check me-1"></i> ยืนยันอนุมัติ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;" id="successIcon"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4" id="successActionMessage"></p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='<?php echo $current_page; ?>'">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ตรวจสอบ URL ว่ามีการทำรายการสำเร็จหรือไม่
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            let msg = '';
            
            if (status === 'deleted') {
                msg = 'ลบข้อมูลทะเบียนและประวัติเรียบร้อยแล้ว';
            } else if (status === 'updated') {
                msg = 'แก้ไขข้อมูลทะเบียนเรียบร้อยแล้ว';
            } else if (status === 'approved') {
                msg = 'อนุมัติรายการเรียบร้อยแล้ว';
            } else if (status === 'rejected') {
                msg = 'ไม่อนุมัติรายการและคืนยอดเงินเข้าโครงการเรียบร้อยแล้ว';
                let icon = document.getElementById('successIcon');
                if(icon) {
                    icon.className = 'fa-solid fa-ban text-danger';
                    icon.parentElement.className = 'd-flex justify-content-center align-items-center mx-auto bg-danger bg-opacity-10 rounded-circle mb-4';
                }
            }
            
            if (msg !== '') {
                document.getElementById('successActionMessage').innerText = msg;
                new bootstrap.Modal(document.getElementById('successActionModal')).show();
                window.history.replaceState(null, null, window.location.pathname);
            }
        }

        // ระบบ Checkbox สำหรับเลือกทั้งหมด (Select All)
        const selectAllCb = document.getElementById('selectAll');
        const itemCbs = document.querySelectorAll('.item-checkbox');

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function() {
                itemCbs.forEach(cb => {
                    cb.checked = selectAllCb.checked;
                });
            });
            
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = document.querySelectorAll('.item-checkbox:checked').length === itemCbs.length;
                    selectAllCb.checked = allChecked;
                });
            });
        }
    });

    // ฟังก์ชันตรวจสอบการทำรายการหลายรายการ (ใช้ร่วมกันทั้ง ลบ และ อนุมัติ)
    function checkBulkAction(actionType) {
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        if (checkedItems.length === 0) {
            new bootstrap.Modal(document.getElementById('alertNoSelectModal')).show();
            return;
        }
        
        document.getElementById('bulk_action_type').value = actionType;

        if (actionType === 'delete') {
            document.getElementById('bulkCountText').innerText = checkedItems.length;
            new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
        } else if (actionType === 'approve') {
            document.getElementById('bulkApproveCountText').innerText = checkedItems.length;
            new bootstrap.Modal(document.getElementById('bulkApproveModal')).show();
        }
    }

    // แสดงหน้าต่างยืนยันการไม่อนุมัติสีแดง-ขาว
    function openConfirmRejectModal() {
        new bootstrap.Modal(document.getElementById('confirmRejectModal')).show();
    }

    // โค้ดส่งฟอร์มเพื่อบันทึกไม่อนุมัติ
    function executeReject() {
        document.getElementById('modal_action').value = 'reject';
        document.getElementById('formApproveModal').submit();
    }

    // ฟังก์ชันเปิด Modal อนุมัติ
    function openApproveModal(data) {
        document.getElementById('modal_action').value = 'approve';
        
        document.getElementById('approve_id').value = data.id;
        document.getElementById('view_disp_order').innerText = data.order;
        document.getElementById('view_disp_proj_name').innerText = data.proj_name;
        document.getElementById('view_disp_requester').innerText = data.requester ? data.requester : '-';
        document.getElementById('view_disp_group_name').innerText = data.group_name ? data.group_name : '-';
        
        // อัปเดตข้อมูลประเภทงบประมาณ
        document.getElementById('view_disp_budget_type').innerText = data.budget_type ? data.budget_type : '-';
        document.getElementById('view_disp_cutoff_approver').innerText = data.approver ? data.approver : '-';
        
        // แปลงรูปแบบวันที่เป็นไทยแบบย่อ
        const dateObj = new Date(data.date);
        const thaiMonths = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
        const displayDate = !isNaN(dateObj) ? dateObj.getDate() + ' ' + thaiMonths[dateObj.getMonth()] + ' ' + (dateObj.getFullYear() + 543) : '-';

        // ตั้งค่าฟิลด์ Input Text (Readonly view)
        document.getElementById('view_doc_date').innerText = displayDate;
        document.getElementById('view_request_type').innerText = data.request_type ? data.request_type : '-';
        document.getElementById('view_expense_type').innerText = data.expense_type ? data.expense_type : '-';
        document.getElementById('view_doc_no').innerText = data.doc ? data.doc : '-';
        document.getElementById('view_doc_location').innerText = data.doc_location ? data.doc_location : '-';
        document.getElementById('view_description').innerText = data.desc ? data.desc : '-';
        
        // เอายอดเงินมาใส่ใน input เผื่อแก้ไขยอดอนุมัติ
        document.getElementById('approve_amount_input').value = data.amount;

        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }

    // ฟังก์ชันเปิด Modal แก้ไขข้อมูล (Edit Modal)
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('disp_order').innerText = data.order;
        document.getElementById('disp_proj_name').innerText = data.proj_name;
        document.getElementById('disp_requester').innerText = data.requester ? data.requester : '-';
        document.getElementById('disp_group_name').innerText = data.group_name ? data.group_name : '-';
        
        // อัปเดตข้อมูลประเภทงบประมาณ
        document.getElementById('disp_budget_type').innerText = data.budget_type ? data.budget_type : '-';
        document.getElementById('disp_cutoff_approver_edit').innerText = data.approver ? data.approver : '-';
        
        document.getElementById('disp_payment_approver').innerText = 'รอการพิจารณา'; // ตามบริบทหน้านี้คือรออนุมัติให้เบิก
        
        // ตั้งค่าฟิลด์ Input
        document.getElementById('edit_doc_date').value = data.date;
        document.getElementById('edit_doc_no').value = data.doc;
        document.getElementById('edit_doc_location').value = data.doc_location;
        document.getElementById('edit_description').value = data.desc;
        document.getElementById('edit_amount').value = data.amount;

        // ตั้งค่า Dropdown ประเภทรายจ่าย
        let expSelect = document.getElementById('edit_expense_type');
        if (data.expense_type) {
            expSelect.value = data.expense_type;
        } else {
            expSelect.value = "";
        }

        // ตั้งค่า Radio ประเภทการทำรายการ
        if (data.request_type === 'ขอเบิก') {
            document.getElementById('type_withdraw').checked = true;
        } else if (data.request_type === 'ขอยืมเงินงบประมาณ') {
            document.getElementById('type_borrow').checked = true;
        } else {
            document.getElementById('type_withdraw').checked = false;
            document.getElementById('type_borrow').checked = false;
        }

        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูล (แบบเดี่ยว)
    function openDeleteModal(id) {
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>