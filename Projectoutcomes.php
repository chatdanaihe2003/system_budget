<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดผลผลิตโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'โครงการตามแผนปฎิบัติการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ในตารางต้นทาง (project_outcomes) ---
$columns_to_add = [
    'budget_type' => "VARCHAR(50) NULL DEFAULT 'งบประจำ' AFTER project_name",
    'budget_amount' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER budget_type",
    'allocation_1' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER budget_amount",
    'allocation_2' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_1",
    'allocation_3' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_2"
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
        allocation_3 DECIMAL(15,2) DEFAULT 0
    )");
} else {
    // เพิ่มคอลัมน์ที่อาจจะยังขาดหายไปในตารางปลายทาง
    $sync_cols = [
        'project_code' => "VARCHAR(255) NULL AFTER budget_year",
        'project_name' => "TEXT NULL AFTER project_code",
        'budget_amount' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_1' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_2' => "DECIMAL(15,2) DEFAULT 0",
        'allocation_3' => "DECIMAL(15,2) DEFAULT 0"
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

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ดึงข้อมูลรหัสโครงการและปีก่อนลบ เพื่อนำไปลบในหน้า Budgetallocation ด้วย
    $stmt_get = $conn->prepare("SELECT project_code, budget_year FROM project_outcomes WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_del = $stmt_get->get_result();
    
    if ($row_del = $res_del->fetch_assoc()) {
        $del_code = $row_del['project_code'];
        $del_year = $row_del['budget_year'];

        // ลบข้อมูลจากตารางหลัก
        $stmt = $conn->prepare("DELETE FROM project_outcomes WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // ลบข้อมูลที่เชื่อมโยงในตาราง budget_allocations
            $stmt_sync_del = $conn->prepare("DELETE FROM budget_allocations WHERE project_code = ? AND budget_year = ?");
            if ($stmt_sync_del) {
                $stmt_sync_del->bind_param("si", $del_code, $del_year);
                $stmt_sync_del->execute();
            }
            
            // ลบข้อมูลประวัติการเบิกจ่ายที่เชื่อมโยงในตาราง project_expenses ด้วย
            $stmt_exp_del = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ?");
            if ($stmt_exp_del) {
                $stmt_exp_del->bind_param("i", $id);
                $stmt_exp_del->execute();
            }
        }
    }
    
    header("Location: Projectoutcomes.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $project_code = $_POST['project_code'];
    $project_name = $_POST['project_name'];
    $budget_type = $_POST['budget_type'] ?? 'งบประจำ';
    $budget_amount = $_POST['budget_amount'] ?? 0;
    $allocation_1 = $_POST['allocation_1'] ?? 0;
    $allocation_2 = $_POST['allocation_2'] ?? 0;
    $allocation_3 = $_POST['allocation_3'] ?? 0;

    // --- ตรวจสอบว่ายอดจัดสรรเกินงบประมาณหรือไม่ ---
    $total_allocation = $allocation_1 + $allocation_2 + $allocation_3;
    if ($total_allocation > $budget_amount) {
        echo "<script>alert('จัดสรรไม่ได้ เกินงบประมาณที่กำหนดมา'); window.history.back();</script>";
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // [ส่วนที่ 1] เพิ่มลงตารางหลัก (Project Outcomes)
        $stmt = $conn->prepare("INSERT INTO project_outcomes (budget_year, project_code, project_name, budget_type, budget_amount, allocation_1, allocation_2, allocation_3) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssddd", $budget_year, $project_code, $project_name, $budget_type, $budget_amount, $allocation_1, $allocation_2, $allocation_3);
        
        if ($stmt->execute()) {
            // [ส่วนที่ 2] จำลองข้อมูลเพื่อส่งไปแสดงในหน้า Budgetallocation อย่างสมบูรณ์
            $doc_date = date('Y-m-d');
            
            // หาเลขที่ใบงวดถัดไปในตารางปลายทาง
            $sql_max_sync = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
            $stmt_max_sync = $conn->prepare($sql_max_sync);
            $stmt_max_sync->bind_param("i", $budget_year);
            $stmt_max_sync->execute();
            $row_max_sync = $stmt_max_sync->get_result()->fetch_assoc();
            $alloc_order = ($row_max_sync['max_order'] ? $row_max_sync['max_order'] : 0) + 1;

            $stmt_sync = $conn->prepare("INSERT INTO budget_allocations (budget_year, allocation_order, doc_date, doc_no, project_code, project_name, description, budget_amount, amount, allocation_1, allocation_2, allocation_3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt_sync) {
                // แปลง project_code ให้ไปโชว์ในช่อง doc_no และ project_name โชว์ในช่อง description
                $stmt_sync->bind_param("iisssssddddd", $budget_year, $alloc_order, $doc_date, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3);
                $stmt_sync->execute();
            }
        }
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        
        // ดึง project_code เดิมก่อนอัปเดต เพื่อไปเทียบอัปเดตให้ตรงกันใน budget_allocations
        $stmt_old = $conn->prepare("SELECT project_code FROM project_outcomes WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $old_code = $old_data['project_code'];

        // [ส่วนที่ 1] อัปเดตตารางหลัก
        $stmt = $conn->prepare("UPDATE project_outcomes SET budget_year=?, project_code=?, project_name=?, budget_type=?, budget_amount=?, allocation_1=?, allocation_2=?, allocation_3=? WHERE id=?");
        $stmt->bind_param("issssdddi", $budget_year, $project_code, $project_name, $budget_type, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $id);
        
        if ($stmt->execute()) {
            // [ส่วนที่ 2] อัปเดตข้อมูลปลายทาง (ค้นหาจากรหัสเก่า)
            $check_sync = $conn->prepare("SELECT id FROM budget_allocations WHERE project_code = ? AND budget_year = ?");
            $check_sync->bind_param("si", $old_code, $active_year);
            $check_sync->execute();
            $sync_res = $check_sync->get_result();

            if ($sync_res->num_rows > 0) {
                $stmt_sync = $conn->prepare("UPDATE budget_allocations SET budget_year=?, project_code=?, doc_no=?, project_name=?, description=?, budget_amount=?, amount=?, allocation_1=?, allocation_2=?, allocation_3=? WHERE project_code=? AND budget_year=?");
                if ($stmt_sync) {
                    $stmt_sync->bind_param("issssdddddsi", $budget_year, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $old_code, $active_year);
                    $stmt_sync->execute();
                }
            } else {
                // ถ้าแก้ไขแล้วหาปลายทางไม่เจอ (เช่น ถูกลบทิ้งไปแล้ว) ให้เพิ่มใหม่เลย
                $doc_date = date('Y-m-d');
                $sql_max_sync = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
                $stmt_max_sync = $conn->prepare($sql_max_sync);
                $stmt_max_sync->bind_param("i", $budget_year);
                $stmt_max_sync->execute();
                $row_max_sync = $stmt_max_sync->get_result()->fetch_assoc();
                $alloc_order = ($row_max_sync['max_order'] ? $row_max_sync['max_order'] : 0) + 1;

                $stmt_sync = $conn->prepare("INSERT INTO budget_allocations (budget_year, allocation_order, doc_date, doc_no, project_code, project_name, description, budget_amount, amount, allocation_1, allocation_2, allocation_3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt_sync) {
                    $stmt_sync->bind_param("iisssssddddd", $budget_year, $alloc_order, $doc_date, $project_code, $project_code, $project_name, $project_name, $budget_amount, $budget_amount, $allocation_1, $allocation_2, $allocation_3);
                    $stmt_sync->execute();
                }
            }
        }
    }
    header("Location: Projectoutcomes.php");
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา (กรองตาม Active Year และแยกตาราง) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    
    // ค้นหางบประจำ
    $sql_reg = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? AND budget_type = 'งบประจำ' ORDER BY id ASC";
    $stmt_reg = $conn->prepare($sql_reg);
    $stmt_reg->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt_reg->execute();
    $result_regular = $stmt_reg->get_result();

    // ค้นหางบพัฒนาคุณภาพการศึกษา (แก้ไขชื่อแล้ว)
    $sql_proj = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? AND budget_type = 'งบพัฒนาคุณภาพการศึกษา' ORDER BY id ASC";
    $stmt_proj = $conn->prepare($sql_proj);
    $stmt_proj->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt_proj->execute();
    $result_project = $stmt_proj->get_result();
    
} else {
    // ดึงข้อมูลงบประจำ
    $sql_reg = "SELECT * FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบประจำ' ORDER BY id ASC";
    $stmt_reg = $conn->prepare($sql_reg);
    $stmt_reg->bind_param("i", $active_year);
    $stmt_reg->execute();
    $result_regular = $stmt_reg->get_result();

    // ดึงข้อมูลงบพัฒนาคุณภาพการศึกษา (แก้ไขชื่อแล้ว)
    $sql_proj = "SELECT * FROM project_outcomes WHERE budget_year = ? AND budget_type = 'งบพัฒนาคุณภาพการศึกษา' ORDER BY id ASC";
    $stmt_proj = $conn->prepare($sql_proj);
    $stmt_proj->bind_param("i", $active_year);
    $stmt_proj->execute();
    $result_project = $stmt_proj->get_result();
}

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
            <button class="btn btn-primary btn-sm shadow-sm px-3" onclick="openAddModal('งบประจำ')" style="border-radius: 8px;">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล (งบประจำ)
            </button>
        </div>
        <div class="table-responsive border rounded mb-5">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="text-center py-3" style="width: 8%;">ปีงบประมาณ</th>
                        <th class="py-3" style="width: 12%;">รหัส</th>
                        <th class="py-3" style="width: 25%;">ชื่อผลผลิต/โครงการ</th>
                        <th class="text-end py-3" style="width: 10%;">เงินงบประมาณ<br>โครงการ</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 1</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 2</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 3</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดคงเหลือ</th>
                        <th class="text-center py-3" style="width: 7%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_budget_reg = 0;
                    if ($result_regular->num_rows > 0) {
                        $i_reg = 1;
                        while($row = $result_regular->fetch_assoc()) {
                            $total_budget_reg += $row['budget_amount'];
                            $remaining_balance = $row['budget_amount'] - ($row['allocation_1'] + $row['allocation_2'] + $row['allocation_3']);

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i_reg++ . "</td>";
                            echo "<td class='text-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='fw-bold text-secondary'>" . htmlspecialchars($row['project_code'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                            echo "<td class='text-end fw-bold text-success'>" . ($row['budget_amount'] > 0 ? number_format($row['budget_amount'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_1'] > 0 ? number_format($row['allocation_1'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_2'] > 0 ? number_format($row['allocation_2'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_3'] > 0 ? number_format($row['allocation_3'], 2) : '-') . "</td>";
                            
                            $text_color = ($remaining_balance < 0) ? "text-danger" : "text-primary";
                            echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($remaining_balance, 2) . "</td>";
                            
                            echo "<td class='text-center'>";
                            echo "<div class='action-container'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="btn btn-sm btn-outline-danger px-2 shadow-sm" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['project_code'].' หรือไม่?\n(คำเตือน: ประวัติการเบิกจ่ายจะถูกลบด้วย)\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="btn btn-sm btn-outline-warning px-2 shadow-sm" title="แก้ไข" onclick=\'openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='4' class='text-end py-3'><strong>รวมยอดเงินงบประจำ :</strong></td>";
                        echo "<td class='text-end py-3 text-success fs-6'><strong>" . number_format($total_budget_reg, 2) . "</strong></td>";
                        echo "<td colspan='5'></td>";
                        echo "</tr>";
                    } else {
                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'><i class='fa-solid fa-folder-open fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูล งบประจำ ในปี $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-5">
            <span class="badge-proj"><i class="fa-solid fa-diagram-project me-1"></i> งบพัฒนาคุณภาพการศึกษา</span>
            <button class="btn btn-success btn-sm shadow-sm px-3" onclick="openAddModal('งบพัฒนาคุณภาพการศึกษา')" style="border-radius: 8px;">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล (งบพัฒนาคุณภาพฯ)
            </button>
        </div>
        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="text-center py-3" style="width: 8%;">ปีงบประมาณ</th>
                        <th class="py-3" style="width: 12%;">รหัส</th>
                        <th class="py-3" style="width: 25%;">ชื่อผลผลิต/โครงการ</th>
                        <th class="text-end py-3" style="width: 10%;">เงินงบประมาณ<br>โครงการ</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 1</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 2</th>
                        <th class="text-end py-3" style="width: 8%;">ยอดจัดสรร<br>ครั้งที่ 3</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดคงเหลือ</th>
                        <th class="text-center py-3" style="width: 7%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_budget_proj = 0;
                    if ($result_project->num_rows > 0) {
                        $i_proj = 1;
                        while($row = $result_project->fetch_assoc()) {
                            $total_budget_proj += $row['budget_amount'];
                            $remaining_balance = $row['budget_amount'] - ($row['allocation_1'] + $row['allocation_2'] + $row['allocation_3']);

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i_proj++ . "</td>";
                            echo "<td class='text-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='fw-bold text-secondary'>" . htmlspecialchars($row['project_code'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                            echo "<td class='text-end fw-bold text-success'>" . ($row['budget_amount'] > 0 ? number_format($row['budget_amount'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_1'] > 0 ? number_format($row['allocation_1'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_2'] > 0 ? number_format($row['allocation_2'], 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($row['allocation_3'] > 0 ? number_format($row['allocation_3'], 2) : '-') . "</td>";
                            
                            $text_color = ($remaining_balance < 0) ? "text-danger" : "text-primary";
                            echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($remaining_balance, 2) . "</td>";
                            
                            echo "<td class='text-center'>";
                            echo "<div class='action-container'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="btn btn-sm btn-outline-danger px-2 shadow-sm" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['project_code'].' หรือไม่?\n(คำเตือน: ประวัติการเบิกจ่ายจะถูกลบด้วย)\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="btn btn-sm btn-outline-warning px-2 shadow-sm" title="แก้ไข" onclick=\'openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='4' class='text-end py-3'><strong>รวมยอดเงินงบพัฒนาคุณภาพการศึกษา :</strong></td>";
                        echo "<td class='text-end py-3 text-success fs-6'><strong>" . number_format($total_budget_proj, 2) . "</strong></td>";
                        echo "<td colspan='5'></td>";
                        echo "</tr>";
                    } else {
                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'><i class='fa-solid fa-folder-open fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูล งบพัฒนาคุณภาพการศึกษา ในปี $active_year</td></tr>";
                    }
                    ?>
                    <tr class="grand-total-row text-dark">
                        <td colspan="4" class="text-end py-3">
                            <i class="fa-solid fa-sack-dollar text-warning me-2"></i> 
                            <strong>ยอดงบประมาณรวมทั้งสิ้น (งบประจำ + งบพัฒนาคุณภาพการศึกษา) :</strong>
                        </td>
                        <td class="text-end py-3 text-danger fs-5">
                            <strong><?php echo number_format($total_budget_reg + $total_budget_proj, 2); ?></strong>
                        </td>
                        <td colspan="5"></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="Projectoutcomes.php" method="POST" onsubmit="return validateBudget(this);">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header bg-primary text-white">
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
                        <label class="form-label fw-bold">ชื่อผลผลิต/โครงการ <span class="text-danger">*</span></label>
                        <textarea name="project_name" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark m-0"><i class="fa-solid fa-list-check me-2"></i>กิจกรรมหลัก</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addActivityField()">
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-success fs-6">เงินงบประมาณรวมทั้งสิ้น</label>
                            <input type="number" step="0.01" name="budget_amount" class="form-control form-control-lg text-end text-success fw-bold" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" class="form-control text-end" value="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" class="form-control text-end" value="0.00">
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

                    <div class="row bg-light p-3 rounded mx-0 mt-4 border border-1">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-success fs-6">เงินงบประมาณรวมทั้งสิ้น</label>
                            <input type="number" step="0.01" name="budget_amount" id="edit_budget_amount" class="form-control form-control-lg text-end text-success fw-bold">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" id="edit_allocation_1" class="form-control text-end">
                        </div>
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" id="edit_allocation_2" class="form-control text-end">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ยอดจัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" id="edit_allocation_3" class="form-control text-end">
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

<?php require_once 'includes/footer.php'; ?>

<script>
    // การจัดการช่องเพิ่มกิจกรรม
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

    // ฟังก์ชันเปิด Modal สำหรับการเพิ่มข้อมูล (แยกประเภทงบ)
    function openAddModal(type) {
        // Reset ฟอร์ม
        document.querySelector('#addModal form').reset();
        
        // กำหนดประเภทงบตามปุ่มที่กด
        document.getElementById('modal_budget_type').value = type;
        document.getElementById('modalTitleAdd').innerHTML = '<i class="fa-solid fa-folder-plus me-2"></i> เพิ่มข้อมูล - ' + type;
        
        // Reset ช่องกิจกรรมให้เหลือ 1 ช่อง
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
        
        document.getElementById('edit_budget_amount').value = data.budget_amount || 0;
        document.getElementById('edit_allocation_1').value = data.allocation_1 || 0;
        document.getElementById('edit_allocation_2').value = data.allocation_2 || 0;
        document.getElementById('edit_allocation_3').value = data.allocation_3 || 0;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }

    // ฟังก์ชันตรวจสอบยอดเงินก่อนกดบันทึก
    function validateBudget(form) {
        let budget = parseFloat(form.querySelector('[name="budget_amount"]').value) || 0;
        let alloc1 = parseFloat(form.querySelector('[name="allocation_1"]').value) || 0;
        let alloc2 = parseFloat(form.querySelector('[name="allocation_2"]').value) || 0;
        let alloc3 = parseFloat(form.querySelector('[name="allocation_3"]').value) || 0;

        let totalAlloc = alloc1 + alloc2 + alloc3;

        if (totalAlloc > budget) {
            alert('จัดสรรไม่ได้ ยอดรวมการจัดสรร (' + totalAlloc.toLocaleString() + ') เกินงบประมาณที่กำหนดมา (' + budget.toLocaleString() + ')');
            return false; 
        }
        return true; 
    }
</script>