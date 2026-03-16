<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "กำหนดผลผลิตโครงการ ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'โครงการตามแผนปฎิบัติการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- ดึงสิทธิ์ผู้ใช้งานเพื่อตรวจสอบการแสดงผลปุ่ม (Admin และ แผนงาน) ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin_or_planner = ($nav_role === 'admin' || $nav_role === 'แผนงาน');

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ในตารางต้นทาง (project_outcomes) ---
$columns_to_add = [
    'budget_type' => "VARCHAR(50) NULL DEFAULT 'งบประจำ' AFTER project_name",
    'group_name' => "VARCHAR(255) NULL AFTER budget_type", 
    'responsible_person' => "VARCHAR(255) NULL AFTER group_name", 
    'activities' => "TEXT NULL AFTER responsible_person", 
    'budget_amount' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER activities",
    'allocation_1' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER budget_amount",
    'allocation_2' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_1",
    'allocation_3' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_2",
    'allocation_4' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_3", 
    'allocation_5' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_4"  
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM project_outcomes LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE project_outcomes ADD $col_name $col_definition");
    }
}

// --- ตรวจสอบและสร้าง/อัปเดตตารางปลายทาง (budget_allocations) ให้รองรับการเชื่อมข้อมูล ---
$check_table = $conn->query("SHOW TABLES LIKE 'budget_allocations'");
if ($check_table && $check_table->num_rows == 0) {
    $conn->query("CREATE TABLE budget_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        budget_year INT NULL,
        allocation_order INT NULL,
        doc_date DATE NULL,
        doc_no VARCHAR(255) NULL,
        project_code VARCHAR(255) NULL,
        project_name TEXT NULL,
        description TEXT NULL,
        budget_amount DECIMAL(15,2) DEFAULT 0,
        amount DECIMAL(15,2) DEFAULT 0,
        allocation_1 DECIMAL(15,2) DEFAULT 0,
        allocation_2 DECIMAL(15,2) DEFAULT 0,
        allocation_3 DECIMAL(15,2) DEFAULT 0,
        allocation_4 DECIMAL(15,2) DEFAULT 0,
        allocation_5 DECIMAL(15,2) DEFAULT 0
    )");
} else {
    $sync_cols = [
        'project_code' => "VARCHAR(255) NULL AFTER budget_year",
        'project_name' => "TEXT NULL AFTER project_code",
        'budget_amount' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_1' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_2' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_3' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_4' => "DECIMAL(15,2) DEFAULT 0", 
        'allocation_5' => "DECIMAL(15,2) DEFAULT 0"  
    ];
    foreach ($sync_cols as $c_name => $c_def) {
        $chk = $conn->query("SHOW COLUMNS FROM budget_allocations LIKE '$c_name'");
        if ($chk && $chk->num_rows == 0) {
            $conn->query("ALTER TABLE budget_allocations ADD $c_name $c_def");
        }
    }
}

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// ลบข้อมูลหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete']) && isset($_POST['delete_ids'])) {
    $delete_ids = $_POST['delete_ids'];
    if (is_array($delete_ids) && count($delete_ids) > 0) {
        foreach ($delete_ids as $id) {
            $id = intval($id);
            $stmt_get = $conn->prepare("SELECT project_code, budget_year FROM project_outcomes WHERE id = ?");
            $stmt_get->bind_param("i", $id);
            $stmt_get->execute();
            $res_del = $stmt_get->get_result();
            
            if ($row_del = $res_del->fetch_assoc()) {
                $del_code = $row_del['project_code'];
                $del_year = $row_del['budget_year'];

                $stmt = $conn->prepare("DELETE FROM project_outcomes WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $stmt_sync_del = $conn->prepare("DELETE FROM budget_allocations WHERE project_code = ? AND budget_year = ?");
                    if ($stmt_sync_del) {
                        $stmt_sync_del->bind_param("si", $del_code, $del_year);
                        $stmt_sync_del->execute();
                    }
                    
                    $stmt_exp_del = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ?");
                    if ($stmt_exp_del) {
                        $stmt_exp_del->bind_param("i", $id);
                        $stmt_exp_del->execute();
                    }

                    $stmt_with_del = $conn->prepare("DELETE FROM project_withdrawals WHERE project_id = ?");
                    if ($stmt_with_del) {
                        $stmt_with_del->bind_param("i", $id);
                        $stmt_with_del->execute();
                    }
                }
            }
        }
        echo "<script>window.location='Projectoutcomes.php?deleted=1';</script>";
        exit();
    }
}

// 1. ลบข้อมูลแบบเดี่ยว
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    $stmt_get = $conn->prepare("SELECT project_code, budget_year FROM project_outcomes WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_del = $stmt_get->get_result();
    
    if ($row_del = $res_del->fetch_assoc()) {
        $del_code = $row_del['project_code'];
        $del_year = $row_del['budget_year'];

        $stmt = $conn->prepare("DELETE FROM project_outcomes WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt_sync_del = $conn->prepare("DELETE FROM budget_allocations WHERE project_code = ? AND budget_year = ?");
            if ($stmt_sync_del) {
                $stmt_sync_del->bind_param("si", $del_code, $del_year);
                $stmt_sync_del->execute();
            }
            
            $stmt_exp_del = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ?");
            if ($stmt_exp_del) {
                $stmt_exp_del->bind_param("i", $id);
                $stmt_exp_del->execute();
            }

            $stmt_with_del = $conn->prepare("DELETE FROM project_withdrawals WHERE project_id = ?");
            if ($stmt_with_del) {
                $stmt_with_del->bind_param("i", $id);
                $stmt_with_del->execute();
            }
        }
    }
    echo "<script>window.location='Projectoutcomes.php?deleted=1';</script>";
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['bulk_delete'])) {
    $budget_year = intval($_POST['budget_year']);
    $project_code = $_POST['project_code'];
    $project_name = $_POST['project_name'];
    $budget_type = $_POST['budget_type'] ?? 'งบประจำ';
    $group_name = $_POST['group_name'] ?? ''; 
    $responsible_person = $_POST['responsible_person'] ?? '';
    
    $activities_arr = isset($_POST['activities']) ? array_filter($_POST['activities'], 'trim') : [];
    $activities_json = json_encode(array_values($activities_arr), JSON_UNESCAPED_UNICODE);

    $budget_amount = floatval($_POST['budget_amount'] ?? 0);
    $allocation_1  = floatval($_POST['allocation_1'] ?? 0);
    $allocation_2  = floatval($_POST['allocation_2'] ?? 0);
    $allocation_3  = floatval($_POST['allocation_3'] ?? 0);
    $allocation_4  = floatval($_POST['allocation_4'] ?? 0); 
    $allocation_5  = floatval($_POST['allocation_5'] ?? 0); 

    $total_allocation = $allocation_1 + $allocation_2 + $allocation_3 + $allocation_4 + $allocation_5;
    if ($total_allocation > $budget_amount) {
        echo "<script>alert('จัดสรรไม่ได้ เกินงบประมาณที่กำหนดมา'); window.history.back();</script>";
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO project_outcomes (budget_year, project_code, project_name, budget_type, group_name, responsible_person, activities, budget_amount, allocation_1, allocation_2, allocation_3, allocation_4, allocation_5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssdddddd", $budget_year, $project_code, $project_name, $budget_type, $group_name, $responsible_person, $activities_json, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $allocation_4, $allocation_5);
        
        if ($stmt->execute()) {
            $doc_date = date('Y-m-d');
            $sql_max_sync = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
            $stmt_max_sync = $conn->prepare($sql_max_sync);
            $stmt_max_sync->bind_param("i", $budget_year);
            $stmt_max_sync->execute();
            $row_max_sync = $stmt_max_sync->get_result()->fetch_assoc();
            $alloc_order = intval($row_max_sync['max_order']) + 1;

            $stmt_sync = $conn->prepare("INSERT INTO budget_allocations (budget_year, allocation_order, doc_date, doc_no, project_code, project_name, description, budget_amount, amount, allocation_1, allocation_2, allocation_3, allocation_4, allocation_5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt_sync) {
                $stmt_sync->bind_param("iisssssddddddd", $budget_year, $alloc_order, $doc_date, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $allocation_4, $allocation_5);
                $stmt_sync->execute();
            }
        }
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = intval($_POST['edit_id']);
        
        $stmt_old = $conn->prepare("SELECT project_code FROM project_outcomes WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $old_code = $old_data['project_code'];

        $stmt = $conn->prepare("UPDATE project_outcomes SET budget_year=?, project_code=?, project_name=?, budget_type=?, group_name=?, responsible_person=?, activities=?, budget_amount=?, allocation_1=?, allocation_2=?, allocation_3=?, allocation_4=?, allocation_5=? WHERE id=?");
        $stmt->bind_param("issssssddddddi", $budget_year, $project_code, $project_name, $budget_type, $group_name, $responsible_person, $activities_json, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $allocation_4, $allocation_5, $id);
        
        if ($stmt->execute()) {
            $check_sync = $conn->prepare("SELECT id FROM budget_allocations WHERE project_code = ? AND budget_year = ?");
            $check_sync->bind_param("si", $old_code, $active_year);
            $check_sync->execute();
            $sync_res = $check_sync->get_result();

            if ($sync_res->num_rows > 0) {
                $stmt_sync = $conn->prepare("UPDATE budget_allocations SET budget_year=?, project_code=?, doc_no=?, project_name=?, description=?, budget_amount=?, amount=?, allocation_1=?, allocation_2=?, allocation_3=?, allocation_4=?, allocation_5=? WHERE project_code=? AND budget_year=?");
                if ($stmt_sync) {
                    $stmt_sync->bind_param("issssdddddddsi", $budget_year, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $allocation_4, $allocation_5, $old_code, $active_year);
                    $stmt_sync->execute();
                }
            } else {
                $doc_date = date('Y-m-d');
                $sql_max_sync = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
                $stmt_max_sync = $conn->prepare($sql_max_sync);
                $stmt_max_sync->bind_param("i", $budget_year);
                $stmt_max_sync->execute();
                $row_max_sync = $stmt_max_sync->get_result()->fetch_assoc();
                $alloc_order = intval($row_max_sync['max_order']) + 1;

                $stmt_sync = $conn->prepare("INSERT INTO budget_allocations (budget_year, allocation_order, doc_date, doc_no, project_code, project_name, description, budget_amount, amount, allocation_1, allocation_2, allocation_3, allocation_4, allocation_5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt_sync) {
                    $stmt_sync->bind_param("iisssssddddddd", $budget_year, $alloc_order, $doc_date, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $allocation_4, $allocation_5);
                    $stmt_sync->execute();
                }
            }
        }
    }
    header("Location: Projectoutcomes.php");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    
    $sql_reg = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? AND budget_type = 'งบประจำ' ORDER BY id ASC";
    $stmt_reg = $conn->prepare($sql_reg);
    $stmt_reg->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt_reg->execute();
    $result_regular = $stmt_reg->get_result();

    $sql_proj = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? AND budget_type = 'งบพัฒนาคุณภาพการศึกษา' ORDER BY id ASC";
    $stmt_proj = $conn->prepare($sql_proj);
    $stmt_proj->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt_proj->execute();
    $result_project = $stmt_proj->get_result();
    
} else {
    $sql_reg = "SELECT * FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบประจำ' ORDER BY id ASC";
    $stmt_reg = $conn->prepare($sql_reg);
    $stmt_reg->bind_param("i", $active_year);
    $stmt_reg->execute();
    $result_regular = $stmt_reg->get_result();

    $sql_proj = "SELECT * FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบพัฒนาคุณภาพการศึกษา' ORDER BY id ASC";
    $stmt_proj = $conn->prepare($sql_proj);
    $stmt_proj->bind_param("i", $active_year);
    $stmt_proj->execute();
    $result_project = $stmt_proj->get_result();
}

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

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .action-container { display: flex; justify-content: center; gap: 8px; }
    .table-title { font-size: 1.25rem; font-weight: bold; margin-top: 30px; margin-bottom: 15px; display: inline-block;}
    .badge-reg { background-color: #0d6efd; color: white; padding: 7px 15px; border-radius: 20px; font-size: 1rem; font-weight: bold;}
    .badge-proj { background-color: #198754; color: white; padding: 7px 15px; border-radius: 20px; font-size: 1rem; font-weight: bold;}
    .amount-display { font-size: 1.05rem; font-weight: bold; }
    .grand-total-row { background-color: #fff3cd !important; border-top: 2px solid #ffc107; font-size: 1.1rem; }
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
    
    /* CSS สำหรับกล่องเลื่อนในคอลัมน์ผู้รับผิดชอบ */
    .scrollable-cell {
        max-height: 70px;
        overflow-y: auto;
        white-space: pre-wrap; 
        display: block;
        font-size: 0.9rem;
        padding-right: 5px;
    }
    .scrollable-cell::-webkit-scrollbar { width: 4px; }
    .scrollable-cell::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .scrollable-cell::-webkit-scrollbar-track { background: transparent; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-primary">
                <i class="fa-solid fa-folder-tree me-2"></i> โครงการตามแผนปฎิบัติการ
            </h2>
            
            <div class="d-flex align-items-center">
                <form action="Projectoutcomes.php" method="GET" class="d-flex me-3">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส หรือ ชื่อโครงการ..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary px-3" type="submit">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Projectoutcomes.php" class="btn btn-outline-danger ms-2 d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <span class="badge-reg"><i class="fa-solid fa-building-columns me-1"></i> งบประจำ</span>
            <div class="d-flex gap-2">
                <?php if($is_admin_or_planner): ?>
                    <button type="button" class="btn btn-danger btn-sm shadow-sm px-3" style="border-radius: 8px;" onclick="checkBulkDelete('bulkRegForm', 'chk-reg')">
                        <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                    </button>
                <?php endif; ?>
                <button class="btn btn-primary btn-sm shadow-sm px-3" onclick="openAddModal('งบประจำ')" style="border-radius: 8px;">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล (งบประจำ)
                </button>
            </div>
        </div>
        
        <form action="" method="POST" id="bulkRegForm">
            <input type="hidden" name="bulk_delete" value="1">
            <div class="table-responsive border rounded mb-5">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center py-3" style="width: 5%;">
                                <?php if($is_admin_or_planner): ?>
                                    <input class="form-check-input d-block mx-auto mb-1" type="checkbox" id="selectAllReg">
                                <?php endif; ?>
                                ที่
                            </th>
                            <th class="text-center py-3" style="width: 6%;">ปีงบฯ</th>
                            <th class="py-3" style="width: 8%;">รหัส</th>
                            <th class="py-3" style="width: 15%;">ชื่อโครงการ</th>
                            <th class="py-3" style="width: 10%;">กลุ่มงาน</th>
                            <th class="py-3" style="width: 12%;">ผู้รับผิดชอบโครงการ</th>
                            <th class="text-end py-3" style="width: 8%;">งบประมาณ<br>โครงการ</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 1</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 2</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 3</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 4</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 5</th>
                            <th class="text-end py-3" style="width: 8%;">ยอดคงเหลือ</th>
                            <th class="text-center py-3" style="width: 6%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_budget_reg = 0;
                        if ($result_regular->num_rows > 0) {
                            $i_reg = 1;
                            while($row = $result_regular->fetch_assoc()) {
                                $total_budget_reg += $row['budget_amount'];
                                $remaining_balance = $row['budget_amount'] - ($row['allocation_1'] + $row['allocation_2'] + $row['allocation_3'] + $row['allocation_4'] + $row['allocation_5']);
                                
                                $raw_group = htmlspecialchars($row['group_name'] ?? '-');
                                $group_name_short = array_key_exists($raw_group, $group_abbr) ? $group_abbr[$raw_group] : $raw_group;
                                $p_code = htmlspecialchars($row['project_code'] ?? '-', ENT_QUOTES, 'UTF-8');

                                echo "<tr>";
                                
                                echo "<td class='text-center text-muted'>";
                                if ($is_admin_or_planner) {
                                    echo "<input class='form-check-input chk-reg d-block mx-auto mb-1' type='checkbox' name='delete_ids[]' value='{$row['id']}'>";
                                }
                                echo $i_reg++ . "</td>";

                                echo "<td class='text-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                                echo "<td class='fw-bold text-secondary'>" . $p_code . "</td>";
                                echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                                echo "<td><span title='" . $raw_group . "'>" . $group_name_short . "</span></td>";
                                
                                // เช็คค่าว่างของผู้รับผิดชอบให้แสดง - หากว่าง และใช้ scrollable-cell แทน truncate
                                $raw_resp = trim($row['responsible_person'] ?? '');
                                $resp_person = ($raw_resp !== '') ? htmlspecialchars($raw_resp) : '-';
                                echo "<td><div class='scrollable-cell' title='เลื่อนเพื่อดูทั้งหมด'>" . $resp_person . "</div></td>";
                                
                                echo "<td class='text-end fw-bold text-success'>" . ($row['budget_amount'] > 0 ? number_format($row['budget_amount'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_1'] > 0 ? number_format($row['allocation_1'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_2'] > 0 ? number_format($row['allocation_2'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_3'] > 0 ? number_format($row['allocation_3'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_4'] > 0 ? number_format($row['allocation_4'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_5'] > 0 ? number_format($row['allocation_5'], 2) : '-') . "</td>";
                                
                                $text_color = ($remaining_balance < 0) ? "text-danger" : "text-primary";
                                echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($remaining_balance, 2) . "</td>";
                                
                                echo "<td class='text-center'>";
                                echo "<div class='action-container'>";
                                if ($is_admin_or_planner) {
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger px-2 shadow-sm' title='ลบ' onclick=\"openDeleteModal({$row['id']}, '{$p_code}')\"><i class='fa-solid fa-trash-can'></i></button>";
                                }
                                echo '<button type="button" class="btn btn-sm btn-outline-warning px-2 shadow-sm" title="แก้ไข" onclick=\'openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            
                            echo "<tr class='total-row table-light'>";
                            echo "<td colspan='6' class='text-end py-3'><strong>รวมยอดเงินงบประจำ :</strong></td>";
                            echo "<td class='text-end py-3 text-success fs-6'><strong>" . number_format($total_budget_reg, 2) . "</strong></td>";
                            echo "<td colspan='7'></td>";
                            echo "</tr>";
                        } else {
                            echo "<tr><td colspan='14' class='text-center py-5 text-muted'><i class='fa-solid fa-folder-open fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูล งบประจำ ในปี $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-5">
            <span class="badge-proj"><i class="fa-solid fa-diagram-project me-1"></i> งบพัฒนาคุณภาพการศึกษา</span>
            <div class="d-flex gap-2">
                <?php if($is_admin_or_planner): ?>
                    <button type="button" class="btn btn-danger btn-sm shadow-sm px-3" style="border-radius: 8px;" onclick="checkBulkDelete('bulkProjForm', 'chk-proj')">
                        <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                    </button>
                <?php endif; ?>
                <button class="btn btn-success btn-sm shadow-sm px-3" onclick="openAddModal('งบพัฒนาคุณภาพการศึกษา')" style="border-radius: 8px;">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล (งบพัฒนาคุณภาพฯ)
                </button>
            </div>
        </div>
        
        <form action="" method="POST" id="bulkProjForm">
            <input type="hidden" name="bulk_delete" value="1">
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center py-3" style="width: 5%;">
                                <?php if($is_admin_or_planner): ?>
                                    <input class="form-check-input d-block mx-auto mb-1" type="checkbox" id="selectAllProj">
                                <?php endif; ?>
                                ที่
                            </th>
                            <th class="text-center py-3" style="width: 6%;">ปีงบฯ</th>
                            <th class="py-3" style="width: 8%;">รหัส</th>
                            <th class="py-3" style="width: 15%;">ชื่อโครงการ</th>
                            <th class="py-3" style="width: 10%;">กลุ่มงาน</th>
                            <th class="py-3" style="width: 12%;">ผู้รับผิดชอบโครงการ</th>
                            <th class="text-end py-3" style="width: 8%;">งบประมาณ<br>โครงการ</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 1</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 2</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 3</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 4</th>
                            <th class="text-end py-3" style="width: 6%;">จัดสรร 5</th>
                            <th class="text-end py-3" style="width: 8%;">ยอดคงเหลือ</th>
                            <th class="text-center py-3" style="width: 6%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_budget_proj = 0;
                        if ($result_project->num_rows > 0) {
                            $i_proj = 1;
                            while($row = $result_project->fetch_assoc()) {
                                $total_budget_proj += $row['budget_amount'];
                                $remaining_balance = $row['budget_amount'] - ($row['allocation_1'] + $row['allocation_2'] + $row['allocation_3'] + $row['allocation_4'] + $row['allocation_5']);

                                $raw_group = htmlspecialchars($row['group_name'] ?? '-');
                                $group_name_short = array_key_exists($raw_group, $group_abbr) ? $group_abbr[$raw_group] : $raw_group;
                                $p_code = htmlspecialchars($row['project_code'] ?? '-', ENT_QUOTES, 'UTF-8');

                                echo "<tr>";
                                
                                echo "<td class='text-center text-muted'>";
                                if ($is_admin_or_planner) {
                                    echo "<input class='form-check-input chk-proj d-block mx-auto mb-1' type='checkbox' name='delete_ids[]' value='{$row['id']}'>";
                                }
                                echo $i_proj++ . "</td>";

                                echo "<td class='text-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                                echo "<td class='fw-bold text-secondary'>" . $p_code . "</td>";
                                echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                                echo "<td><span title='" . $raw_group . "'>" . $group_name_short . "</span></td>";
                                
                                // เช็คค่าว่างของผู้รับผิดชอบให้แสดง - หากว่าง และใช้ scrollable-cell แทน truncate
                                $raw_resp = trim($row['responsible_person'] ?? '');
                                $resp_person = ($raw_resp !== '') ? htmlspecialchars($raw_resp) : '-';
                                echo "<td><div class='scrollable-cell' title='เลื่อนเพื่อดูทั้งหมด'>" . $resp_person . "</div></td>";
                                
                                echo "<td class='text-end fw-bold text-success'>" . ($row['budget_amount'] > 0 ? number_format($row['budget_amount'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_1'] > 0 ? number_format($row['allocation_1'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_2'] > 0 ? number_format($row['allocation_2'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_3'] > 0 ? number_format($row['allocation_3'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_4'] > 0 ? number_format($row['allocation_4'], 2) : '-') . "</td>";
                                echo "<td class='text-end text-muted'>" . ($row['allocation_5'] > 0 ? number_format($row['allocation_5'], 2) : '-') . "</td>";
                                
                                $text_color = ($remaining_balance < 0) ? "text-danger" : "text-primary";
                                echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($remaining_balance, 2) . "</td>";
                                
                                echo "<td class='text-center'>";
                                echo "<div class='action-container'>";
                                if ($is_admin_or_planner) {
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger px-2 shadow-sm' title='ลบ' onclick=\"openDeleteModal({$row['id']}, '{$p_code}')\"><i class='fa-solid fa-trash-can'></i></button>";
                                }
                                echo '<button type="button" class="btn btn-sm btn-outline-warning px-2 shadow-sm" title="แก้ไข" onclick=\'openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            
                            echo "<tr class='total-row table-light'>";
                            echo "<td colspan='6' class='text-end py-3'><strong>รวมยอดเงินงบพัฒนาคุณภาพการศึกษา :</strong></td>";
                            echo "<td class='text-end py-3 text-success fs-6'><strong>" . number_format($total_budget_proj, 2) . "</strong></td>";
                            echo "<td colspan='7'></td>";
                            echo "</tr>";
                        } else {
                            echo "<tr><td colspan='14' class='text-center py-5 text-muted'><i class='fa-solid fa-folder-open fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูล งบพัฒนาคุณภาพการศึกษา ในปี $active_year</td></tr>";
                        }
                        ?>
                        <tr class="grand-total-row text-dark">
                            <td colspan="6" class="text-end py-3">
                                <i class="fa-solid fa-sack-dollar text-warning me-2"></i> 
                                <strong>ยอดงบประมาณรวมทั้งสิ้น (งบประจำ + งบพัฒนาคุณภาพการศึกษา) :</strong>
                            </td>
                            <td class="text-end py-3 text-danger fs-5">
                                <strong><?php echo number_format($total_budget_reg + $total_budget_proj, 2); ?></strong>
                            </td>
                            <td colspan="7"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="Projectoutcomes.php" method="POST" onsubmit="return validateBudget(this);">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header bg-primary text-white" id="addModalHeader">
                    <h5 class="modal-title fw-bold" id="modalTitleAdd"><i class="fa-solid fa-folder-plus me-2"></i> เพิ่มข้อมูล</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_year" class="form-control bg-light" value="<?php echo $active_year; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ประเภทงบ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_type" id="modal_budget_type" class="form-control bg-light fw-bold text-primary" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="project_code" class="form-control" placeholder="รหัสจากกรมบัญชีกลาง">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อโครงการ <span class="text-danger">*</span></label>
                        <textarea name="project_name" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">กลุ่มงาน <span class="text-danger">*</span></label>
                            <select name="group_name" class="form-select" required>
                                <option value="" disabled selected>-- เลือกกลุ่มงาน --</option>
                                <option value="กลุ่มอำนวยการ">กลุ่มอำนวยการ</option>
                                <option value="กลุ่มกฎหมายและคดี">กลุ่มกฎหมายและคดี</option>
                                <option value="กลุ่มนโยบายและแผน">กลุ่มนโยบายและแผน</option>
                                <option value="กลุ่มบริหารการเงินและสินทรัพย์">กลุ่มบริหารการเงินและสินทรัพย์</option>
                                <option value="กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร">กลุ่มส่งเสริมการศึกษาทางไกลเทคโนโลยีสารสนเทศและการสื่อสาร</option>
                                <option value="กลุ่มนิเทศ ติดตาม และประเมินผลการจัดการศึกษา">กลุ่มนิเทศติดตามและประเมินผลการจัดการศึกษา</option>
                                <option value="กลุ่มบริหารงานบุคคล">กลุ่มบริหารงานบุคคล</option>
                                <option value="กลุ่มพัฒนาครูและบุคลากรทางการศึกษา">กลุ่มพัฒนาครูและบุคลากรทางการศึกษา</option>
                                <option value="กลุ่มส่งเสริมการจัดการศึกษา">กลุ่มส่งเสริมการจัดการศึกษา</option>
                                <option value="หน่วยตรวจสอบภายใน">หน่วยตรวจสอบภายใน</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ผู้รับผิดชอบโครงการ</label>
                            <textarea name="responsible_person" class="form-control" rows="2" placeholder="ระบุชื่อผู้รับผิดชอบ (ใส่ได้หลายคน)"></textarea>
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark m-0"><i class="fa-solid fa-list-check me-2"></i>กิจกรรมหลัก</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddActivity" onclick="addActivityField()">
                                <i class="fa-solid fa-plus me-1"></i> เพิ่มกิจกรรม
                            </button>
                        </div>
                        <div id="activity_fields_container">
                            <div class="input-group mb-2 activity-row">
                                <input type="text" name="activities[]" class="form-control" placeholder="กิจกรรมที่ 1">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">* สามารถเพิ่มกิจกรรมได้สูงสุด 10 รายการ</small>
                    </div>

                    <div class="row bg-light p-3 rounded mx-0 mt-4 border border-1">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold text-success fs-6">เงินงบประมาณรวมทั้งสิ้น</label>
                            <input type="number" step="0.01" name="budget_amount" class="form-control form-control-lg text-end text-success fw-bold" value="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 4</label>
                            <input type="number" step="0.01" name="allocation_4" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 5</label>
                            <input type="number" step="0.01" name="allocation_5" class="form-control text-end" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="addModalSubmitBtn"><i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="Projectoutcomes.php" method="POST" onsubmit="return validateBudget(this);">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_year" id="edit_budget_year" class="form-control bg-light" required readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ประเภทงบ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_type" id="edit_budget_type" class="form-control bg-light fw-bold" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="project_code" id="edit_project_code" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผลผลิต/โครงการ <span class="text-danger">*</span></label>
                        <textarea name="project_name" id="edit_project_name" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">กลุ่มงาน <span class="text-danger">*</span></label>
                            <select name="group_name" id="edit_group_name" class="form-select" required>
                                <option value="" disabled selected>-- เลือกกลุ่มงาน --</option>
                                <option value="กลุ่มอำนวยการ">กลุ่มอำนวยการ</option>
                                <option value="กลุ่มกฎหมายและคดี">กลุ่มกฎหมายและคดี</option>
                                <option value="กลุ่มนโยบายและแผน">กลุ่มนโยบายและแผน</option>
                                <option value="กลุ่มบริหารการเงินและสินทรัพย์">กลุ่มบริหารการเงินและสินทรัพย์</option>
                                <option value="กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร">กลุ่มส่งเสริมการศึกษาทางไกลเทคโนโลยีสารสนเทศและการสื่อสาร</option>
                                <option value="กลุ่มนิเทศ ติดตาม และประเมินผลการจัดการศึกษา">กลุ่มนิเทศติดตามและประเมินผลการจัดการศึกษา</option>
                                <option value="กลุ่มบริหารงานบุคคล">กลุ่มบริหารงานบุคคล</option>
                                <option value="กลุ่มพัฒนาครูและบุคลากรทางการศึกษา">กลุ่มพัฒนาครูและบุคลากรทางการศึกษา</option>
                                <option value="กลุ่มส่งเสริมการจัดการศึกษา">กลุ่มส่งเสริมการจัดการศึกษา</option>
                                <option value="หน่วยตรวจสอบภายใน">หน่วยตรวจสอบภายใน</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ผู้รับผิดชอบโครงการ</label>
                            <textarea name="responsible_person" id="edit_responsible_person" class="form-control" rows="2" placeholder="ระบุชื่อผู้รับผิดชอบ (ใส่ได้หลายคน)"></textarea>
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark m-0"><i class="fa-solid fa-list-check me-2"></i>กิจกรรมหลัก</label>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark" id="btnEditAddActivity" onclick="addEditActivityField()">
                                <i class="fa-solid fa-plus me-1"></i> เพิ่มกิจกรรม
                            </button>
                        </div>
                        <div id="edit_activity_fields_container">
                            </div>
                        <small class="text-muted d-block mt-1">* สามารถเพิ่มกิจกรรมได้สูงสุด 10 รายการ</small>
                    </div>

                    <div class="row bg-light p-3 rounded mx-0 mt-4 border border-1">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold text-success fs-6">เงินงบประมาณรวมทั้งสิ้น</label>
                            <input type="number" step="0.01" name="budget_amount" id="edit_budget_amount" class="form-control form-control-lg text-end text-success fw-bold">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" id="edit_allocation_1" class="form-control text-end">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" id="edit_allocation_2" class="form-control text-end">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" id="edit_allocation_3" class="form-control text-end">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 4</label>
                            <input type="number" step="0.01" name="allocation_4" id="edit_allocation_4" class="form-control text-end">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">จัดสรรเงินครั้งที่ 5</label>
                            <input type="number" step="0.01" name="allocation_5" id="edit_allocation_5" class="form-control text-end">
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบรายการรหัส <span id="del_project_code" class="text-danger"></span> หรือไม่?</h4>
                <div class="alert alert-warning mt-4 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> ประวัติการเบิกจ่ายจะถูกลบด้วย
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ</a>
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูล <span id="bulkCountText" class="text-danger fw-bold fs-3"></span> รายการ?</h4>
                <p class="text-muted mb-0 fs-5">หากยืนยัน ข้อมูลโครงการและประวัติเบิกจ่ายจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" id="confirmBulkDeleteBtn" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบทั้งหมด</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4">ลบข้อมูลโครงการเรียบร้อยแล้ว</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Projectoutcomes.php'">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ตรวจสอบ URL ว่ามีการลบสำเร็จหรือไม่
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('deleted')) {
            new bootstrap.Modal(document.getElementById('successDeleteModal')).show();
            // ลบ parameter deleted ออกจาก URL
            window.history.replaceState(null, null, window.location.pathname);
        }

        // ระบบ Checkbox ตาราง 1 (งบประจำ)
        const selectAllReg = document.getElementById('selectAllReg');
        const chkRegs = document.querySelectorAll('.chk-reg');
        if (selectAllReg) {
            selectAllReg.addEventListener('change', function() {
                chkRegs.forEach(cb => cb.checked = selectAllReg.checked);
            });
            chkRegs.forEach(cb => {
                cb.addEventListener('change', function() {
                    selectAllReg.checked = document.querySelectorAll('.chk-reg:checked').length === chkRegs.length;
                });
            });
        }
        
        // ระบบ Checkbox ตาราง 2 (งบพัฒนาคุณภาพการศึกษา)
        const selectAllProj = document.getElementById('selectAllProj');
        const chkProjs = document.querySelectorAll('.chk-proj');
        if (selectAllProj) {
            selectAllProj.addEventListener('change', function() {
                chkProjs.forEach(cb => cb.checked = selectAllProj.checked);
            });
            chkProjs.forEach(cb => {
                cb.addEventListener('change', function() {
                    selectAllProj.checked = document.querySelectorAll('.chk-proj:checked').length === chkProjs.length;
                });
            });
        }
    });

    let targetBulkFormId = '';

    // ฟังก์ชันตรวจสอบก่อนกดปุ่มลบหลายรายการ
    function checkBulkDelete(formId, chkClass) {
        const checkedItems = document.querySelectorAll('.' + chkClass + ':checked');
        if (checkedItems.length === 0) {
            alert('กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ');
            return;
        }
        
        targetBulkFormId = formId;
        document.getElementById('bulkCountText').innerText = checkedItems.length;
        
        // ผูกฟังก์ชันให้ปุ่มยืนยัน
        document.getElementById('confirmBulkDeleteBtn').onclick = function() {
            document.getElementById(targetBulkFormId).submit();
        };

        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    }

    // ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูลแบบเดี่ยว
    function openDeleteModal(id, code) {
        document.getElementById('del_project_code').innerText = code;
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    // การจัดการช่องเพิ่มกิจกรรม (สำหรับหน้าเพิ่มข้อมูล)
    let activityCount = 1;

    function addActivityField() {
        if (activityCount >= 10) {
            alert('คุณสามารถเพิ่มกิจกรรมได้สูงสุด 10 รายการเท่านั้น');
            return;
        }
        activityCount++;
        let container = document.getElementById('activity_fields_container');
        let div = document.createElement('div');
        div.className = 'input-group mb-2 activity-row';
        div.innerHTML = `
            <input type="text" name="activities[]" class="form-control" placeholder="กิจกรรมที่ ${activityCount}">
            <button class="btn btn-outline-danger" type="button" onclick="removeActivityField(this)"><i class="fa-solid fa-trash-can"></i></button>
        `;
        container.appendChild(div);
    }

    function removeActivityField(btn) {
        btn.closest('.activity-row').remove();
        activityCount--;
    }

    // การจัดการช่องเพิ่มกิจกรรม (สำหรับหน้าแก้ไขข้อมูล)
    let editActivityCount = 1;

    function addEditActivityField() {
        if (editActivityCount >= 10) {
            alert('คุณสามารถเพิ่มกิจกรรมได้สูงสุด 10 รายการเท่านั้น');
            return;
        }
        editActivityCount++;
        let container = document.getElementById('edit_activity_fields_container');
        let div = document.createElement('div');
        div.className = 'input-group mb-2 edit-activity-row';
        div.innerHTML = `
            <input type="text" name="activities[]" class="form-control" placeholder="กิจกรรมที่ ${editActivityCount}">
            <button class="btn btn-outline-danger" type="button" onclick="removeEditActivityField(this)"><i class="fa-solid fa-trash-can"></i></button>
        `;
        container.appendChild(div);
    }

    function removeEditActivityField(btn) {
        btn.closest('.edit-activity-row').remove();
        editActivityCount--;
    }

    // ฟังก์ชันเปิด Modal สำหรับการเพิ่มข้อมูล
    function openAddModal(type) {
        document.querySelector('#addModal form').reset();
        document.getElementById('modal_budget_type').value = type;
        document.getElementById('modalTitleAdd').innerHTML = '<i class="fa-solid fa-folder-plus me-2"></i> เพิ่มข้อมูล - ' + type;
        
        let header = document.getElementById('addModalHeader');
        let submitBtn = document.getElementById('addModalSubmitBtn');
        let addActBtn = document.getElementById('btnAddActivity');

        if (type === 'งบพัฒนาคุณภาพการศึกษา') {
            header.className = 'modal-header bg-success text-white';
            submitBtn.className = 'btn btn-success px-4 fw-bold';
            addActBtn.className = 'btn btn-sm btn-outline-success';
        } else {
            header.className = 'modal-header bg-primary text-white';
            submitBtn.className = 'btn btn-primary px-4 fw-bold';
            addActBtn.className = 'btn btn-sm btn-outline-primary';
        }

        document.getElementById('activity_fields_container').innerHTML = `
            <div class="input-group mb-2 activity-row">
                <input type="text" name="activities[]" class="form-control" placeholder="กิจกรรมที่ 1">
            </div>
        `;
        activityCount = 1;
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    // ฟังก์ชันเปิด Modal สำหรับการแก้ไขข้อมูล
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_budget_year').value = data.budget_year;
        document.getElementById('edit_budget_type').value = data.budget_type;
        document.getElementById('edit_project_code').value = data.project_code;
        document.getElementById('edit_project_name').value = data.project_name;
        document.getElementById('edit_group_name').value = data.group_name || ''; 
        document.getElementById('edit_responsible_person').value = data.responsible_person || '';
        
        document.getElementById('edit_budget_amount').value = data.budget_amount || 0;
        document.getElementById('edit_allocation_1').value = data.allocation_1 || 0;
        document.getElementById('edit_allocation_2').value = data.allocation_2 || 0;
        document.getElementById('edit_allocation_3').value = data.allocation_3 || 0;
        document.getElementById('edit_allocation_4').value = data.allocation_4 || 0; // [เพิ่มใหม่]
        document.getElementById('edit_allocation_5').value = data.allocation_5 || 0; // [เพิ่มใหม่]
        
        let actContainer = document.getElementById('edit_activity_fields_container');
        actContainer.innerHTML = '';
        editActivityCount = 0;

        let activities = [];
        if (data.activities) {
            try {
                activities = JSON.parse(data.activities);
            } catch (e) {
                console.error("รูปแบบข้อมูลกิจกรรมไม่ถูกต้อง");
            }
        }

        if (activities.length > 0) {
            activities.forEach((act, index) => {
                editActivityCount++;
                let div = document.createElement('div');
                div.className = 'input-group mb-2 edit-activity-row';
                if (index === 0) {
                    div.innerHTML = `<input type="text" name="activities[]" class="form-control" value="${act.replace(/"/g, '&quot;')}" placeholder="กิจกรรมที่ ${editActivityCount}">`;
                } else {
                    div.innerHTML = `
                        <input type="text" name="activities[]" class="form-control" value="${act.replace(/"/g, '&quot;')}" placeholder="กิจกรรมที่ ${editActivityCount}">
                        <button class="btn btn-outline-danger" type="button" onclick="removeEditActivityField(this)"><i class="fa-solid fa-trash-can"></i></button>
                    `;
                }
                actContainer.appendChild(div);
            });
        } else {
            editActivityCount = 1;
            actContainer.innerHTML = `
                <div class="input-group mb-2 edit-activity-row">
                    <input type="text" name="activities[]" class="form-control" placeholder="กิจกรรมที่ 1">
                </div>
            `;
        }

        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }

    // [แก้ไข] ฟังก์ชันตรวจสอบยอดเงินก่อนกดบันทึก (รวมยอดที่ 4 และ 5)
    function validateBudget(form) {
        let budget = parseFloat(form.querySelector('[name="budget_amount"]').value) || 0;
        let alloc1 = parseFloat(form.querySelector('[name="allocation_1"]').value) || 0;
        let alloc2 = parseFloat(form.querySelector('[name="allocation_2"]').value) || 0;
        let alloc3 = parseFloat(form.querySelector('[name="allocation_3"]').value) || 0;
        let alloc4 = parseFloat(form.querySelector('[name="allocation_4"]').value) || 0; // [เพิ่มใหม่]
        let alloc5 = parseFloat(form.querySelector('[name="allocation_5"]').value) || 0; // [เพิ่มใหม่]

        let totalAlloc = alloc1 + alloc2 + alloc3 + alloc4 + alloc5;

        if (totalAlloc > budget) {
            alert('จัดสรรไม่ได้ ยอดรวมการจัดสรร (' + totalAlloc.toLocaleString() + ') เกินงบประมาณที่กำหนดมา (' + budget.toLocaleString() + ')');
            return false; 
        }
        return true; 
    }
</script>