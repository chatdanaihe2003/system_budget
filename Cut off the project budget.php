<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ตัดยอดงบประมาณโครงการ ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ตัดยอดงบประมาณโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- เช็คสิทธิ์การเข้าถึง ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
// อนุญาตให้ ID User เข้าถึงได้ตามที่ร้องขอ จึงทำการปิดส่วนที่บล็อกไว้
/*
if ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='index.php';</script>";
    exit();
}
*/

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (ลบข้อมูล แบบเดี่ยว และ แบบหลายรายการ) สำหรับ Admin ---
// --------------------------------------------------------------------------------

// ลบแบบหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete_projects']) && isset($_POST['delete_ids'])) {
    if ($nav_role === 'admin') { // ป้องกันคนอื่นยิง Request เข้ามา
        $delete_ids = $_POST['delete_ids'];
        if (is_array($delete_ids) && count($delete_ids) > 0) {
            foreach ($delete_ids as $id) {
                $id = intval($id);
                // ดึงข้อมูลรหัสโครงการและปีก่อนลบ
                $stmt_get = $conn->prepare("SELECT project_code, budget_year FROM project_outcomes WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $res_del = $stmt_get->get_result();
                if ($row_del = $res_del->fetch_assoc()) {
                    $del_code = $row_del['project_code'];
                    $del_year = $row_del['budget_year'];
                    // ลบตารางหลัก
                    $conn->query("DELETE FROM project_outcomes WHERE id = $id");
                    // ลบตารางที่เชื่อมโยง
                    $conn->query("DELETE FROM budget_allocations WHERE project_code = '$del_code' AND budget_year = $del_year");
                    $conn->query("DELETE FROM project_expenses WHERE project_id = $id");
                }
            }
            echo "<script>window.location='Cut off the project budget.php?deleted=1';</script>";
            exit();
        }
    }
}

// ลบแบบทีละรายการ (เดี่ยว)
if (isset($_GET['delete_project_id'])) {
    if ($nav_role === 'admin') {
        $id = intval($_GET['delete_project_id']);
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
                $conn->query("DELETE FROM budget_allocations WHERE project_code = '$del_code' AND budget_year = $del_year");
                $conn->query("DELETE FROM project_expenses WHERE project_id = $id");
            }
        }
        echo "<script>window.location='Cut off the project budget.php?deleted=1';</script>";
        exit();
    }
}

// --- ตรวจสอบการกดบันทึกตัดยอด (Save Cutoff) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['project_id']) && isset($_POST['request_amount'])) {
    $project_id = $_POST['project_id'];
    $cutoff_date = $_POST['cutoff_date'];
    $ref_document = $_POST['ref_document'];
    $description = $_POST['description'];
    
    // เมื่อไม่มีช่องอนุมัติแล้ว ให้ใช้ request_amount เป็นยอดที่จะตัด (cutoff_amount) ไปเลย
    $request_amount = $_POST['request_amount']; 
    $cutoff_amount = $request_amount; 
    
    // สร้างตารางถ้ายังไม่มี (ปรับโครงสร้างให้ตรงกับความเป็นจริงล่าสุด)
    $check_table = $conn->query("SHOW TABLES LIKE 'project_expenses'");
    if ($check_table->num_rows == 0) {
        $sql_create = "CREATE TABLE project_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            budget_year INT NOT NULL,
            project_id INT NOT NULL,
            expense_date DATE,
            details TEXT,
            cutoff_amount DECIMAL(15,2) DEFAULT 0,
            user_id INT NOT NULL,
            approval_status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $conn->query($sql_create);
    }

    // จัดเตรียมข้อมูลก่อนบันทึก
    $user_id = $_SESSION['user_id'] ?? 1; // ดึง ID ผู้ใช้งาน
    
    // นำเลขที่เอกสารไปรวมในรายละเอียด
    $combined_details = "";
    if (!empty(trim($ref_document))) {
        $combined_details .= "เลขที่เอกสาร/ฎีกา: " . trim($ref_document) . "\n";
    }
    $combined_details .= trim($description);

    // บันทึกลงตาราง project_expenses ด้วยคอลัมน์ที่ถูกต้อง
    $sql_insert = "INSERT INTO project_expenses (budget_year, project_id, expense_date, details, cutoff_amount, user_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_ins = $conn->prepare($sql_insert);
    
    if ($stmt_ins) {
        $stmt_ins->bind_param("iissdi", $active_year, $project_id, $cutoff_date, $combined_details, $cutoff_amount, $user_id);
        if ($stmt_ins->execute()) {
            // ส่งพารามิเตอร์ success ไปกับ URL เพื่อให้ Javascript เปิด Modal แจ้งเตือนสีเขียว
            echo "<script>window.location='Cut off the project budget.php?success=1';</script>";
            exit();
        } else {
            echo "<script>alert('เกิดข้อผิดพลาดในการบันทึก: " . $stmt_ins->error . "');</script>";
        }
    } else {
        die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'><strong><i class='fa-solid fa-triangle-exclamation'></i> SQL Error:</strong> " . $conn->error . "</div></div>");
    }
}

// --- ดึงข้อมูลจากตาราง project_outcomes (ค้นหาและกรองตามปีงบประมาณ) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    // ค้นหาจากรหัสโครงการ หรือ ชื่อโครงการ
    $sql = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
} else {
    $sql = "SELECT * FROM project_outcomes WHERE budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
}

// เตรียม Statement การดึงข้อมูลประวัติการตัดยอดไว้ล่วงหน้า
$stmt_exp = $conn->prepare("
    SELECT 
        SUM(CASE WHEN approval_status = 'approved' THEN cutoff_amount ELSE 0 END) as approved_cut,
        SUM(CASE WHEN approval_status = 'pending' THEN cutoff_amount ELSE 0 END) as pending_cut
    FROM project_expenses 
    WHERE project_id = ?
");
$bind_proj_id = 0;
if ($stmt_exp) {
    $stmt_exp->bind_param("i", $bind_proj_id);
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .status-badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; }
    .amount-display { font-size: 1.1rem; font-weight: bold; letter-spacing: 0.5px; }
    /* สไตล์สำหรับ Checkbox */
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
</style>

<div class="container-fluid pb-5 px-4">
    <form action="" method="POST" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete_projects" value="1">
        
        <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title m-0 fw-bold text-primary">
                    <i class="fa-solid fa-hand-holding-dollar me-2"></i> ตัดยอดงบประมาณโครงการ
                </h2>
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส หรือ ชื่อโครงการ..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary px-4" type="button" onclick="window.location.href='Cut off the project budget.php?search='+document.getElementById('searchInput').value;">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Cut off the project budget.php" class="btn btn-outline-danger d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>

                    <?php if ($nav_role === 'admin'): ?>
                        <button type="button" class="btn btn-danger shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkDelete()">
                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php if ($nav_role === 'admin'): ?>
                            <th class="text-center py-3" style="width: 3%;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <?php endif; ?>
                            
                            <th class="text-center py-3" style="width: 3%;">ที่</th>
                            <th class="py-3" style="width: 8%;">รหัสโครงการ</th>
                            <th class="py-3" style="width: 15%;">ชื่อโครงการ</th>
                            <th class="text-end py-3" style="width: 8%;">งบประมาณรวม</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดจัดสรร<br>ครั้งที่ 1</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดจัดสรร<br>ครั้งที่ 2</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดจัดสรร<br>ครั้งที่ 3</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดจัดสรร<br>ครั้งที่ 4</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดจัดสรร<br>ครั้งที่ 5</th>
                            <th class="text-end py-3" style="width: 8%;">รวมจัดสรรแล้ว</th>
                            <th class="text-end py-3" style="width: 10%;">งบประมาณคงเหลือ<br><span style="font-size:0.75rem; font-weight:normal;"></span></th>
                            
                            <?php if ($nav_role !== 'แผนงาน'): // ซ่อนหัวตารางสำหรับแผนงาน ?>
                            <th class="text-center py-3" style="width: 10%;">ดำเนินการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_projects->num_rows > 0) {
                            $i = 1;
                            $sum_budget = 0;
                            $sum_alloc1 = 0;
                            $sum_alloc2 = 0;
                            $sum_alloc3 = 0;
                            $sum_alloc4 = 0;
                            $sum_alloc5 = 0;
                            $sum_allocated = 0;
                            $sum_available = 0; 

                            while($row = $result_projects->fetch_assoc()) {
                                $total_budget = $row['budget_amount'];
                                $alloc1 = $row['allocation_1'];
                                $alloc2 = $row['allocation_2'];
                                $alloc3 = $row['allocation_3'];
                                $alloc4 = $row['allocation_4'] ?? 0;
                                $alloc5 = $row['allocation_5'] ?? 0;
                                
                                // คำนวณยอดที่จัดสรรไปแล้ว
                                $total_allocated = $alloc1 + $alloc2 + $alloc3 + $alloc4 + $alloc5;
                                
                                // ดึงข้อมูลการตัดยอด โดยแยกที่ "อนุมัติแล้ว" และ "กำลังรออนุมัติ"
                                $approved_cut = 0;
                                $pending_cut = 0;
                                if ($stmt_exp) {
                                    $bind_proj_id = $row['id'];
                                    $stmt_exp->execute();
                                    $res_exp = $stmt_exp->get_result();
                                    if ($exp_row = $res_exp->fetch_assoc()) {
                                        $approved_cut = $exp_row['approved_cut'] ? $exp_row['approved_cut'] : 0;
                                        $pending_cut = $exp_row['pending_cut'] ? $exp_row['pending_cut'] : 0;
                                    }
                                    $res_exp->free();
                                }
                                
                                // คำนวณว่ายอดที่อนุมัติแล้ว ไปตัดรอบไหนบ้าง (ตามลำดับ)
                                $temp_cut = $approved_cut;
                                $cut1 = 0; $cut2 = 0; $cut3 = 0; $cut4 = 0; $cut5 = 0;
                                
                                if ($alloc1 > 0) {
                                    $cut1 = ($temp_cut >= $alloc1) ? $alloc1 : $temp_cut;
                                    $temp_cut -= $cut1;
                                }
                                if ($alloc2 > 0 && $temp_cut > 0) {
                                    $cut2 = ($temp_cut >= $alloc2) ? $alloc2 : $temp_cut;
                                    $temp_cut -= $cut2;
                                }
                                if ($alloc3 > 0 && $temp_cut > 0) {
                                    $cut3 = ($temp_cut >= $alloc3) ? $alloc3 : $temp_cut;
                                    $temp_cut -= $cut3;
                                }
                                if ($alloc4 > 0 && $temp_cut > 0) {
                                    $cut4 = ($temp_cut >= $alloc4) ? $alloc4 : $temp_cut;
                                    $temp_cut -= $cut4;
                                }
                                if ($alloc5 > 0 && $temp_cut > 0) {
                                    $cut5 = ($temp_cut >= $alloc5) ? $alloc5 : $temp_cut;
                                    $temp_cut -= $cut5;
                                }
                                if ($temp_cut > 0) {
                                    $cut1 += $temp_cut; // กรณียอดเบิกเกินยอดจัดสรรที่ตั้งไว้
                                }

                                // ยอดคงเหลือที่สามารถเบิกได้ (คงเหลือตัดได้)
                                // = ยอดจัดสรรรวมทั้งหมด - (ยอดที่อนุมัติเบิกไปแล้ว + ยอดที่รออนุมัติเบิก)
                                $total_cut_all = $approved_cut + $pending_cut;
                                $available_to_withdraw = $total_allocated - $total_cut_all;

                                // เก็บผลรวมด้านล่างตาราง
                                $sum_budget += $total_budget;
                                $sum_alloc1 += $alloc1;
                                $sum_alloc2 += $alloc2;
                                $sum_alloc3 += $alloc3;
                                $sum_alloc4 += $alloc4;
                                $sum_alloc5 += $alloc5;
                                $sum_allocated += $total_allocated;
                                $sum_available += $available_to_withdraw;

                                echo "<tr>";

                                // Checkbox ของแต่ละรายการ (เฉพาะ Admin)
                                if ($nav_role === 'admin') {
                                    echo "<td class='text-center'>
                                            <input class='form-check-input item-checkbox' type='checkbox' name='delete_ids[]' value='{$row['id']}'>
                                          </td>";
                                }

                                echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                                echo "<td class='fw-bold text-secondary'>" . htmlspecialchars($row['project_code'] ?? '-') . "</td>";
                                echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                                echo "<td class='text-end fw-bold text-success'>" . ($total_budget > 0 ? number_format($total_budget, 2) : '-') . "</td>";
                                
                                // แสดงยอดจัดสรรและยอดที่ตัดในตารางช่องจัดสรร 1
                                echo "<td class='text-end'>";
                                echo "<div class='text-dark'>" . ($alloc1 > 0 ? number_format($alloc1, 2) : '-') . "</div>";
                                if ($alloc1 > 0 || $cut1 > 0) { 
                                    $rem1 = $alloc1 - $cut1;
                                    echo "<div class='text-danger mt-1' style='font-size: 0.75rem;'>อนุมัติแล้ว: " . number_format($cut1, 2) . "</div>";
                                    echo "<div class='text-success' style='font-size: 0.75rem;'>คงเหลือ: " . number_format($rem1, 2) . "</div>";
                                }
                                echo "</td>";
                                
                                // แสดงยอดจัดสรรและยอดที่ตัดในตารางช่องจัดสรร 2
                                echo "<td class='text-end'>";
                                echo "<div class='text-dark'>" . ($alloc2 > 0 ? number_format($alloc2, 2) : '-') . "</div>";
                                if ($alloc2 > 0 || $cut2 > 0) { 
                                    $rem2 = $alloc2 - $cut2;
                                    echo "<div class='text-danger mt-1' style='font-size: 0.75rem;'>อนุมัติแล้ว: " . number_format($cut2, 2) . "</div>";
                                    echo "<div class='text-success' style='font-size: 0.75rem;'>คงเหลือ: " . number_format($rem2, 2) . "</div>";
                                }
                                echo "</td>";
                                
                                // แสดงยอดจัดสรรและยอดที่ตัดในตารางช่องจัดสรร 3
                                echo "<td class='text-end'>";
                                echo "<div class='text-dark'>" . ($alloc3 > 0 ? number_format($alloc3, 2) : '-') . "</div>";
                                if ($alloc3 > 0 || $cut3 > 0) { 
                                    $rem3 = $alloc3 - $cut3;
                                    echo "<div class='text-danger mt-1' style='font-size: 0.75rem;'>อนุมัติแล้ว: " . number_format($cut3, 2) . "</div>";
                                    echo "<div class='text-success' style='font-size: 0.75rem;'>คงเหลือ: " . number_format($rem3, 2) . "</div>";
                                }
                                echo "</td>";
                                
                                // แสดงยอดจัดสรรและยอดที่ตัดในตารางช่องจัดสรร 4
                                echo "<td class='text-end'>";
                                echo "<div class='text-dark'>" . ($alloc4 > 0 ? number_format($alloc4, 2) : '-') . "</div>";
                                if ($alloc4 > 0 || $cut4 > 0) { 
                                    $rem4 = $alloc4 - $cut4;
                                    echo "<div class='text-danger mt-1' style='font-size: 0.75rem;'>อนุมัติแล้ว: " . number_format($cut4, 2) . "</div>";
                                    echo "<div class='text-success' style='font-size: 0.75rem;'>คงเหลือ: " . number_format($rem4, 2) . "</div>";
                                }
                                echo "</td>";
                                
                                // แสดงยอดจัดสรรและยอดที่ตัดในตารางช่องจัดสรร 5
                                echo "<td class='text-end'>";
                                echo "<div class='text-dark'>" . ($alloc5 > 0 ? number_format($alloc5, 2) : '-') . "</div>";
                                if ($alloc5 > 0 || $cut5 > 0) { 
                                    $rem5 = $alloc5 - $cut5;
                                    echo "<div class='text-danger mt-1' style='font-size: 0.75rem;'>อนุมัติแล้ว: " . number_format($cut5, 2) . "</div>";
                                    echo "<div class='text-success' style='font-size: 0.75rem;'>คงเหลือ: " . number_format($rem5, 2) . "</div>";
                                }
                                echo "</td>";

                                echo "<td class='text-end fw-bold'>" . ($total_allocated > 0 ? number_format($total_allocated, 2) : '-') . "</td>";
                                
                                // แสดงยอดคงเหลือตัดได้ (จากยอดจัดสรร)
                                $text_color = ($available_to_withdraw < 0) ? "text-danger" : "text-info";
                                echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($available_to_withdraw, 2) . "</td>";
                                
                                // ซ่อนข้อมูลการดำเนินการ สำหรับแผนงาน (Admin และการเงินยังเห็นตามปกติ)
                                if ($nav_role !== 'แผนงาน') {
                                    echo "<td class='text-center text-nowrap'>";
                                    // ปุ่มตัดยอดจะขึ้นก็ต่อเมื่อ มียอดที่สามารถเบิกได้เหลืออยู่
                                    if ($available_to_withdraw > 0) {
                                        // ส่งข้อมูลที่จำเป็นไปให้ Javascript รวมชื่อผู้รับผิดชอบโครงการและกลุ่มงานด้วย
                                        $js_data = htmlspecialchars(json_encode([
                                            'id' => $row['id'],
                                            'code' => $row['project_code'],
                                            'name' => $row['project_name'],
                                            'resp_person' => $row['responsible_person'],
                                            'group_name' => $row['group_name'],
                                            'budget_type' => $row['budget_type'] ?? '-', // เพิ่มการส่งประเภทงบประมาณ
                                            'activities' => $row['activities'] ?? '[]', 
                                            'budget_amount' => $total_budget,
                                            'alloc1' => $alloc1,
                                            'alloc2' => $alloc2,
                                            'alloc3' => $alloc3,
                                            'alloc4' => $alloc4,
                                            'alloc5' => $alloc5,
                                            'available_to_withdraw' => $available_to_withdraw
                                        ]), ENT_QUOTES, 'UTF-8');
                                        
                                        echo "<button type='button' class='btn btn-sm btn-primary shadow-sm px-2' onclick='openCutoffModal({$js_data})'>";
                                        echo "<i class='fa-solid fa-scissors me-1'></i> ตัดยอด</button>";
                                    } else if ($total_allocated <= 0) {
                                        echo "<span class='badge bg-secondary status-badge'>ยังไม่ได้รับจัดสรร</span>";
                                    } else {
                                        echo "<span class='badge bg-danger status-badge'>งบจัดสรรหมดแล้ว</span>";
                                    }

                                    // แสดงปุ่มลบโครงการ (เฉพาะ Admin)
                                    if ($nav_role === 'admin') {
                                        echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2 ms-1' title='ลบโครงการนี้' onclick='openDeleteModal({$row['id']})'><i class='fa-solid fa-trash-can'></i></button>";
                                    }

                                    echo "</td>";
                                }
                                echo "</tr>";
                            }

                            // จัดการจำนวนช่อง Colspan แถวสรุปรวมให้ตรง (บวก 1 ถ้าเป็นแอดมินเพราะมีช่อง Checkbox เพิ่มมา)
                            $colspan_left = ($nav_role === 'admin') ? 4 : 3;

                            // แถวสรุปยอดรวม
                            echo "<tr class='total-row table-light'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมทั้งสิ้น :</strong></td>";
                            echo "<td class='text-end py-3 text-success'><strong>" . number_format($sum_budget, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc1, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc2, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc3, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc4, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc5, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-secondary'><strong>" . number_format($sum_allocated, 2) . "</strong></td>";
                            echo "<td class='text-end py-3 text-info fs-5'><strong>" . number_format($sum_available, 2) . "</strong></td>";
                            
                            // ซ่อนช่องตารางสุดท้ายให้ตรงกับด้านบนสำหรับแผนงาน
                            if ($nav_role !== 'แผนงาน') {
                                echo "<td></td>";
                            }
                            
                            echo "</tr>";

                        } else {
                            $colspan_all = ($nav_role === 'admin') ? 13 : 12;
                            // ลด colspan ลง 1 กรณีเป็นแผนงาน (เพราะถูกลบ 1 คอลัมน์ออกไป)
                            if ($nav_role === 'แผนงาน') {
                                $colspan_all = 11;
                            }
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-regular fa-folder-open fs-1 mb-3 d-block'></i>ไม่พบข้อมูลโครงการในปีงบประมาณ $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="cutoffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST" id="cutoffForm" onsubmit="return confirmCutoff(this);">
                <input type="hidden" name="project_id" id="modal_project_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> ทำรายการตัดยอดงบประมาณ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="row">
                            <div class="col-md-3 text-muted fw-bold">รหัสโครงการ:</div>
                            <div class="col-md-9 fw-bold text-dark" id="display_code"></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 text-muted fw-bold">ชื่อโครงการ:</div>
                            <div class="col-md-9 text-dark" id="display_name"></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 text-muted fw-bold">ผู้รับผิดชอบโครงการ:</div>
                            <div class="col-md-9 text-dark" id="display_resp_person"></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 text-muted fw-bold">กลุ่มงาน:</div>
                            <div class="col-md-9 text-dark" id="display_group_name"></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 text-muted fw-bold">ประเภทงบประมาณ:</div>
                            <div class="col-md-9 text-info fw-bold" id="display_budget_type"></div>
                        </div>
                        
                        <div class="row mt-2" id="row_activities" style="display: none;">
                            <div class="col-md-3 text-muted fw-bold">กิจกรรมหลัก:</div>
                            <div class="col-md-9 text-dark">
                                <ul id="display_activities" class="mb-0 ps-3" style="font-size: 0.95rem;"></ul>
                            </div>
                        </div>

                        <hr>
                        <div class="row mb-2">
                            <div class="col-md-3 text-end fw-bold text-muted">งบประมาณทั้งหมด:</div>
                            <div class="col-md-9 fw-bold text-dark" id="display_budget_amount"></div>
                        </div>
                        
                        <div class="row mb-1" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 1:</div>
                            <div class="col-md-9" id="display_alloc1"></div>
                        </div>
                        <div class="row mb-1" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 2:</div>
                            <div class="col-md-9" id="display_alloc2"></div>
                        </div>
                        <div class="row mb-1" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 3:</div>
                            <div class="col-md-9" id="display_alloc3"></div>
                        </div>
                        <div class="row mb-1" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 4:</div>
                            <div class="col-md-9" id="display_alloc4"></div>
                        </div>
                        <div class="row mb-3" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 5:</div>
                            <div class="col-md-9" id="display_alloc5"></div>
                        </div>
                        <hr>
                        <div class="row align-items-center">
                            <div class="col-md-8 text-end fw-bold text-muted">งบประมาณคงเหลือที่สามารถขอตัดยอดได้:</div>
                            <div class="col-md-4 text-end fs-4 fw-bold text-success" id="display_available_withdraw">0.00 ฿</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">วันที่ทำรายการ <span class="text-danger">*</span></label>
                            <input type="date" name="cutoff_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">เลขที่เอกสาร/ฎีกาอ้างอิง</label>
                            <input type="text" name="ref_document" class="form-control" placeholder="เช่น กค.1234/2569">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">รายการ/รายละเอียดการเบิกจ่าย <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="ระบุรายละเอียดการใช้จ่าย" required></textarea>
                    </div>

                    <div class="row mb-3 align-items-center bg-light p-3 rounded">
                        <div class="col-md-8 text-end">
                            <label class="form-label fw-bold text-primary fs-5 mb-0">จำนวนเงินที่ขอตัดยอด (บาท) <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="request_amount" id="request_amount" class="form-control form-control-lg text-end text-primary fw-bold" placeholder="0.00" required>
                            <input type="hidden" id="max_available_withdraw" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> ส่งคำขอเบิก/ตัดยอด</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-primary text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-question me-2"></i> ยืนยันการทำรายการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-paper-plane text-primary mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">ยืนยันการส่งคำขอตัดยอดงบประมาณ?</h4>
                <p class="text-muted mb-0 fs-5 mt-2">จำนวนเงินขอตัดยอด: <br><span id="confirm_request_amount" class="fw-bold text-primary fs-3"></span> บาท</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" id="btnActualSubmit" class="btn btn-primary px-4 fw-bold" style="border-radius: 8px;"><i class="fa-solid fa-check me-1"></i> ยืนยันการส่งคำขอ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successSubmitModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4">ส่งคำขอตัดยอดเพื่อรออนุมัติเรียบร้อยแล้ว</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Cut off the project budget.php'">ตกลง</button>
            </div>
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูลโครงการนี้ใช่หรือไม่?</h4>
                <p class="text-muted mb-0 fs-5">ข้อมูลประวัติการเบิกจ่ายที่เกี่ยวข้องจะถูกลบถาวรด้วย</p>
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูล <span id="bulkCountText" class="text-danger fw-bold fs-3"></span> โครงการ?</h4>
                <p class="text-muted mb-0 fs-5">หากยืนยัน ข้อมูลโครงการและประวัติที่เกี่ยวข้องจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" onclick="document.getElementById('bulkDeleteForm').submit();" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบทั้งหมด</button>
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
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Cut off the project budget.php'">ตกลง</button>
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
                <h4 class="fw-bold text-dark mb-2">แจ้งเตือน</h4>
                <p class="text-muted fs-6 mb-4">กรุณาเลือกรายการอย่างน้อย 1 รายการ</p>
                <button type="button" class="btn btn-warning text-dark px-5 fw-bold w-100" style="border-radius: 8px;" data-bs-dismiss="modal">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ตรวจสอบ URL ว่ามีการบันทึกสำเร็จหรือลบสำเร็จหรือไม่
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            new bootstrap.Modal(document.getElementById('successSubmitModal')).show();
            window.history.replaceState(null, null, window.location.pathname);
        } else if (urlParams.has('deleted')) {
            new bootstrap.Modal(document.getElementById('successDeleteModal')).show();
            window.history.replaceState(null, null, window.location.pathname);
        }
        
        // ผูกปุ่มยืนยันส่งคำขอตัดยอด
        document.getElementById('btnActualSubmit').addEventListener('click', function() {
            document.getElementById('cutoffForm').submit();
        });

        // ระบบ Checkbox สำหรับเลือกทั้งหมด (Select All)
        const selectAllCb = document.getElementById('selectAll');
        const itemCbs = document.querySelectorAll('.item-checkbox');

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function() {
                itemCbs.forEach(cb => {
                    cb.checked = selectAllCb.checked;
                });
            });
            
            // ถ้าไปติ๊กย่อยๆ ออก ให้เอาติ๊กหัวตารางออกด้วย
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = document.querySelectorAll('.item-checkbox:checked').length === itemCbs.length;
                    selectAllCb.checked = allChecked;
                });
            });
        }
    });

    // ฟังก์ชันเปิด Modal และส่งข้อมูลโครงการเข้าไปแสดงสำหรับตัดยอด
    function openCutoffModal(data) {
        // จัดการ Input ซ่อน
        document.getElementById('modal_project_id').value = data.id;
        document.getElementById('max_available_withdraw').value = data.available_to_withdraw;
        
        // แสดง Text รหัส และ ชื่อ และ ผู้รับผิดชอบ และ กลุ่มงาน
        document.getElementById('display_code').innerText = data.code ? data.code : 'ไม่มีรหัส';
        document.getElementById('display_name').innerText = data.name;
        document.getElementById('display_resp_person').innerText = data.resp_person ? data.resp_person : '-';
        document.getElementById('display_group_name').innerText = data.group_name ? data.group_name : '-';
        document.getElementById('display_budget_type').innerText = data.budget_type ? data.budget_type : '-';
        
        // --- จัดการแสดงผลกิจกรรมหลัก ---
        let actRow = document.getElementById('row_activities');
        let actList = document.getElementById('display_activities');
        actList.innerHTML = ''; // ล้างข้อมูลเก่า
        
        let acts = [];
        if (data.activities) {
            try {
                acts = JSON.parse(data.activities);
            } catch (e) {
                console.error("รูปแบบข้อมูลกิจกรรมไม่ถูกต้อง");
            }
        }

        if (acts.length > 0) {
            let hasValidActivity = false;
            acts.forEach(act => {
                if(act.trim() !== '') {
                    let li = document.createElement('li');
                    li.innerText = act;
                    actList.appendChild(li);
                    hasValidActivity = true;
                }
            });
            
            if (hasValidActivity) {
                actRow.style.display = 'flex'; // แสดงบรรทัดกิจกรรม
            } else {
                actRow.style.display = 'none'; // ซ่อนถ้ามีแต่ช่องว่าง
            }
        } else {
            actRow.style.display = 'none'; // ซ่อนถ้าไม่มีกิจกรรม
        }
        // ------------------------------
        
        // แสดงงบประมาณรวม
        document.getElementById('display_budget_amount').innerText = parseFloat(data.budget_amount).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ฿";
        
        // ฟังก์ชันช่วยจัด Format ตัวเลข
        const formatMoney = (num) => parseFloat(num).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        // แสดงเฉพาะยอดจัดสรรใน Popup Modal
        let a1 = parseFloat(data.alloc1) || 0; 
        document.getElementById('display_alloc1').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a1) + " ฿</span>";

        let a2 = parseFloat(data.alloc2) || 0; 
        document.getElementById('display_alloc2').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a2) + " ฿</span>";

        let a3 = parseFloat(data.alloc3) || 0; 
        document.getElementById('display_alloc3').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a3) + " ฿</span>";
        
        let a4 = parseFloat(data.alloc4) || 0; 
        document.getElementById('display_alloc4').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a4) + " ฿</span>";

        let a5 = parseFloat(data.alloc5) || 0; 
        document.getElementById('display_alloc5').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a5) + " ฿</span>";
        
        // จัดรูปแบบตัวเลขยอดคงเหลือที่เบิกได้
        document.getElementById('display_available_withdraw').innerText = formatMoney(data.available_to_withdraw) + " ฿";
        
        // ล้างช่องกรอกจำนวนเงิน
        document.getElementById('request_amount').value = '';
        
        // เปิด Modal
        new bootstrap.Modal(document.getElementById('cutoffModal')).show();
    }

    // ฟังก์ชันดักจับก่อนกด Submit ว่าเงินเกินหรือไม่
    function confirmCutoff(form) {
        let maxLimit = parseFloat(document.getElementById('max_available_withdraw').value);
        let inputAmount = parseFloat(document.getElementById('request_amount').value);

        if (isNaN(inputAmount) || inputAmount <= 0) {
            alert('กรุณาระบุจำนวนเงินที่ขอเบิกให้มากกว่า 0 บาท');
            return false;
        }

        if (inputAmount > maxLimit) {
            alert('ไม่สามารถทำรายการได้!\nจำนวนเงินที่ขอเบิก (' + inputAmount.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' บาท)\nเกินกว่ายอดคงเหลือที่สามารถเบิกได้ (' + maxLimit.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' บาท)');
            return false;
        }

        // แสดง Modal ยืนยัน แทน confirm() ของเดิม
        document.getElementById('confirm_request_amount').innerText = inputAmount.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        new bootstrap.Modal(document.getElementById('confirmSubmitModal')).show();
        
        return false; // ป้องกันไม่ให้ฟอร์ม submit ทันที
    }

    // ฟังก์ชันตรวจสอบก่อนกดปุ่มลบหลายรายการ
    function checkBulkDelete() {
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        if (checkedItems.length === 0) {
            alert("กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ");
            return;
        }
        
        // เอาจำนวนที่เลือกไปแสดงใน Popup ก่อนลบ
        document.getElementById('bulkCountText').innerText = checkedItems.length;
        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    }

    // ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูล (แบบเดี่ยว)
    function openDeleteModal(id) {
        document.getElementById('confirmDeleteBtn').href = '?delete_project_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>