<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ตัดยอดงบประมาณโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ตัดยอดงบประมาณโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- เช็คสิทธิ์การเข้าถึง (ซ่อนจาก ID User ทั่วไป) ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='index.php';</script>";
    exit();
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
    
    $recorded_by = $_SESSION['name'] ?? 'Admin';

    // สร้างตารางถ้ายังไม่มี (เพิ่มคอลัมน์ approval_status เข้าไปด้วย)
    $check_table = $conn->query("SHOW TABLES LIKE 'project_expenses'");
    if ($check_table->num_rows == 0) {
        $sql_create = "CREATE TABLE project_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            budget_year INT NOT NULL,
            project_id INT NOT NULL,
            cutoff_date DATE,
            ref_document VARCHAR(100),
            description TEXT,
            request_amount DECIMAL(15,2) DEFAULT 0,
            cutoff_amount DECIMAL(15,2) DEFAULT 0,
            recorded_by VARCHAR(100),
            approval_status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $conn->query($sql_create);
    }

    // บันทึกลงตาราง project_expenses
    $sql_insert = "INSERT INTO project_expenses (budget_year, project_id, cutoff_date, ref_document, description, request_amount, cutoff_amount, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_ins = $conn->prepare($sql_insert);
    $stmt_ins->bind_param("iisssdds", $active_year, $project_id, $cutoff_date, $ref_document, $description, $request_amount, $cutoff_amount, $recorded_by);
    
    if ($stmt_ins->execute()) {
        // บันทึกเสร็จ ให้อยู่ที่หน้าเดิม
        echo "<script>alert('ส่งคำขอตัดยอดเพื่อรออนุมัติเรียบร้อยแล้ว!'); window.location='Cut off the project budget.php';</script>";
        exit();
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
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-primary">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i> ตัดยอดงบประมาณโครงการ
            </h2>
            
            <form action="" method="GET" class="d-flex">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส หรือ ชื่อโครงการ..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary px-4" type="submit">ค้นหา</button>
                </div>
                <?php if($search != ""): ?>
                    <a href="Cut off the project budget.php" class="btn btn-outline-danger ms-2 d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="py-3" style="width: 10%;">รหัสโครงการ</th>
                        <th class="py-3" style="width: 20%;">ชื่อผลผลิต/โครงการ</th>
                        <th class="text-end py-3" style="width: 10%;">งบประมาณรวม</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 1</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 2</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 3</th>
                        <th class="text-end py-3" style="width: 9%;">รวมจัดสรรแล้ว</th>
                        <th class="text-end py-3" style="width: 9%;">งบประมาณคงเหลือ<br><span style="font-size:0.75rem; font-weight:normal;"></span></th>
                        <th class="text-center py-3" style="width: 10%;">ดำเนินการ</th>
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
                        $sum_allocated = 0;
                        $sum_available = 0; 

                        while($row = $result_projects->fetch_assoc()) {
                            $total_budget = $row['budget_amount'];
                            $alloc1 = $row['allocation_1'];
                            $alloc2 = $row['allocation_2'];
                            $alloc3 = $row['allocation_3'];
                            // คำนวณยอดที่จัดสรรไปแล้ว
                            $total_allocated = $alloc1 + $alloc2 + $alloc3;
                            
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
                            $cut1 = 0; $cut2 = 0; $cut3 = 0;
                            
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
                            if ($temp_cut > 0) {
                                $cut1 += $temp_cut;
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
                            $sum_allocated += $total_allocated;
                            $sum_available += $available_to_withdraw;

                            echo "<tr>";
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

                            echo "<td class='text-end fw-bold'>" . ($total_allocated > 0 ? number_format($total_allocated, 2) : '-') . "</td>";
                            
                            // แสดงยอดคงเหลือตัดได้ (จากยอดจัดสรร)
                            $text_color = ($available_to_withdraw < 0) ? "text-danger" : "text-info";
                            echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($available_to_withdraw, 2) . "</td>";
                            
                            echo "<td class='text-center'>";
                            // ปุ่มตัดยอดจะขึ้นก็ต่อเมื่อ มียอดที่สามารถเบิกได้เหลืออยู่
                            if ($available_to_withdraw > 0) {
                                // ส่งข้อมูลที่จำเป็นไปให้ Javascript
                                $js_data = htmlspecialchars(json_encode([
                                    'id' => $row['id'],
                                    'code' => $row['project_code'],
                                    'name' => $row['project_name'],
                                    'budget_amount' => $total_budget,
                                    'alloc1' => $alloc1,
                                    'alloc2' => $alloc2,
                                    'alloc3' => $alloc3,
                                    'available_to_withdraw' => $available_to_withdraw
                                ]), ENT_QUOTES, 'UTF-8');
                                
                                echo "<button class='btn btn-sm btn-primary shadow-sm px-3' onclick='openCutoffModal({$js_data})'>";
                                echo "<i class='fa-solid fa-scissors me-1'></i> ตัดยอด</button>";
                            } else if ($total_allocated <= 0) {
                                echo "<span class='badge bg-secondary status-badge'>ยังไม่ได้รับจัดสรร</span>";
                            } else {
                                echo "<span class='badge bg-danger status-badge'>งบจัดสรรหมดแล้ว</span>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }

                        // แถวสรุปยอดรวม
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='3' class='text-end py-3'><strong>รวมทั้งสิ้น :</strong></td>";
                        echo "<td class='text-end py-3 text-success'><strong>" . number_format($sum_budget, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc1, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc2, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc3, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-secondary'><strong>" . number_format($sum_allocated, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-info fs-5'><strong>" . number_format($sum_available, 2) . "</strong></td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'>";
                        echo "<i class='fa-regular fa-folder-open fs-1 mb-3 d-block'></i>ไม่พบข้อมูลโครงการในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="cutoffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST" onsubmit="return confirmCutoff(this);">
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
                        <div class="row mb-3" style="font-size: 0.95rem;">
                            <div class="col-md-3 text-end text-muted fw-bold">ยอดจัดสรรครั้งที่ 3:</div>
                            <div class="col-md-9" id="display_alloc3"></div>
                        </div>
                        <hr>
                        <div class="row align-items-center">
                            <div class="col-md-8 text-end fw-bold text-muted">งบประมาณคงเหลือที่สามารถขอเบิกได้:</div>
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
                            <label class="form-label fw-bold text-primary fs-5 mb-0">จำนวนเงินที่ขอเบิก (บาท) <span class="text-danger">*</span></label>
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

<?php require_once 'includes/footer.php'; ?>

<script>
    // ฟังก์ชันเปิด Modal และส่งข้อมูลโครงการเข้าไปแสดง
    function openCutoffModal(data) {
        // จัดการ Input ซ่อน
        document.getElementById('modal_project_id').value = data.id;
        document.getElementById('max_available_withdraw').value = data.available_to_withdraw;
        
        // แสดง Text รหัส และ ชื่อ
        document.getElementById('display_code').innerText = data.code ? data.code : 'ไม่มีรหัส';
        document.getElementById('display_name').innerText = data.name;
        
        // แสดงงบประมาณรวม
        document.getElementById('display_budget_amount').innerText = parseFloat(data.budget_amount).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ฿";
        
        // ฟังก์ชันช่วยจัด Format ตัวเลข
        const formatMoney = (num) => parseFloat(num).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        // แสดงเฉพาะยอดจัดสรรใน Popup Modal (ลบ อนุมัติไปแล้วและคงเหลือ ออกตามที่ระบุ)
        let a1 = parseFloat(data.alloc1) || 0; 
        document.getElementById('display_alloc1').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a1) + " ฿</span>";

        let a2 = parseFloat(data.alloc2) || 0; 
        document.getElementById('display_alloc2').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a2) + " ฿</span>";

        let a3 = parseFloat(data.alloc3) || 0; 
        document.getElementById('display_alloc3').innerHTML = "<span class='text-dark fw-bold'>" + formatMoney(a3) + " ฿</span>";
        
        // จัดรูปแบบตัวเลขยอดคงเหลือที่เบิกได้ (จากงบประมาณรวมทั้งหมด)
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

        if (inputAmount <= 0) {
            alert('กรุณาระบุจำนวนเงินที่ขอเบิกให้มากกว่า 0 บาท');
            return false;
        }

        if (inputAmount > maxLimit) {
            alert('ไม่สามารถทำรายการได้!\nจำนวนเงินที่ขอเบิก (' + inputAmount.toLocaleString() + ' บาท)\nเกินกว่ายอดคงเหลือที่สามารถเบิกได้ (' + maxLimit.toLocaleString() + ' บาท)');
            return false;
        }

        // เปลี่ยนเป็น Popup ยืนยันของจริง หากกดยืนยันจะ return true ให้ฟอร์มทำงาน
        return confirm('ยืนยันการส่งคำขอตัดยอดงบประมาณจำนวน ' + inputAmount.toLocaleString() + ' บาท ใช่หรือไม่?');
    }
</script>