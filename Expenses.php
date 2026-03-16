<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ทะเบียนรายการ (ตัดยอดโครงการ) ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนรายการเบิกอนุมัติตัดยอดโครงการ';

// --- ดึงสิทธิ์ผู้ใช้งานเพื่อตรวจสอบการแสดงผลปุ่ม ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// --- สร้างตาราง project_expenses อัตโนมัติ หากยังไม่มี ---
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

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (ลบข้อมูล แบบเดี่ยว และ แบบหลายรายการ) ---
// --------------------------------------------------------------------------------

// ลบแบบหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete']) && isset($_POST['delete_ids'])) {
    $delete_ids = $_POST['delete_ids'];
    
    if (is_array($delete_ids) && count($delete_ids) > 0) {
        // วนลูปเพื่อดึงข้อมูลก่อนลบไปหักลบ (คืนเงิน)
        foreach ($delete_ids as $id) {
            $id = intval($id);
            $stmt_del = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
            }
        }
        echo "<script>window.location='Expenses.php?deleted=1';</script>";
        exit();
    }
}

// ลบแบบทีละรายการ (เดี่ยว)
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    
    // ส่ง parameter deleted=1 ไปกับ URL เพื่อให้แสดง Popup Modal สำเร็จ
    echo "<script>window.location='Expenses.php?deleted=1';</script>";
    exit();
}

// --- รับค่าการค้นหา ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search . "%";

// --------------------------------------------------------------------------------
// --- ระบบแบ่งหน้า (Pagination) ---
// --------------------------------------------------------------------------------
$records_per_page = 10; // กำหนดจำนวนรายการต่อหน้า
$page_num = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page_num - 1) * $records_per_page;

// นับจำนวนข้อมูลทั้งหมดเพื่อคำนวณหน้า
if ($search != "") {
    $sql_count = "SELECT COUNT(*) as total_rows 
                  FROM project_expenses e 
                  LEFT JOIN project_outcomes p ON e.project_id = p.id 
                  WHERE e.budget_year = ? AND (p.project_name LIKE ? OR p.project_code LIKE ? OR e.details LIKE ?)";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) $stmt_count->bind_param("isss", $active_year, $search_param, $search_param, $search_param);
} else {
    $sql_count = "SELECT COUNT(*) as total_rows 
                  FROM project_expenses e 
                  LEFT JOIN project_outcomes p ON e.project_id = p.id 
                  WHERE e.budget_year = ?";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) $stmt_count->bind_param("i", $active_year);
}

if ($stmt_count) {
    $stmt_count->execute();
    $total_rows = $stmt_count->get_result()->fetch_assoc()['total_rows'];
    $total_pages = ceil($total_rows / $records_per_page);
} else {
    $total_rows = 0; $total_pages = 1;
}

// --- สร้างคำสั่ง SQL แบบกวาดหาคอลัมน์อัตโนมัติ (เพื่อดึง responsible_person) ---
$has_resp_person = ($conn->query("SHOW COLUMNS FROM project_outcomes LIKE 'responsible_person'")->num_rows > 0);
$sel_p = "p.project_code, p.project_name, p.group_name";
if ($has_resp_person) $sel_p .= ", p.responsible_person";

// --- ดึงข้อมูลประวัติการเบิกจ่าย ---
if ($search != "") {
    $sql_data = "SELECT e.*, $sel_p, u.name AS requester_name 
                 FROM project_expenses e 
                 LEFT JOIN project_outcomes p ON e.project_id = p.id 
                 LEFT JOIN users u ON e.user_id = u.id 
                 WHERE e.budget_year = ? AND (p.project_name LIKE ? OR p.project_code LIKE ? OR e.details LIKE ?)
                 ORDER BY e.expense_date DESC, e.id DESC 
                 LIMIT ?, ?";
    $stmt_data = $conn->prepare($sql_data);
    if (!$stmt_data) { die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'><strong><i class='fa-solid fa-triangle-exclamation'></i> เกิดข้อผิดพลาดในฐานข้อมูล:</strong> " . $conn->error . "</div></div>"); }
    $stmt_data->bind_param("isssii", $active_year, $search_param, $search_param, $search_param, $offset, $records_per_page);
} else {
    $sql_data = "SELECT e.*, $sel_p, u.name AS requester_name 
                 FROM project_expenses e 
                 LEFT JOIN project_outcomes p ON e.project_id = p.id 
                 LEFT JOIN users u ON e.user_id = u.id 
                 WHERE e.budget_year = ? 
                 ORDER BY e.expense_date DESC, e.id DESC 
                 LIMIT ?, ?";
    $stmt_data = $conn->prepare($sql_data);
    if (!$stmt_data) { die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'><strong><i class='fa-solid fa-triangle-exclamation'></i> เกิดข้อผิดพลาดในฐานข้อมูล:</strong> " . $conn->error . "</div></div>"); }
    $stmt_data->bind_param("iii", $active_year, $offset, $records_per_page);
}

$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_request = 0;
$total_cutoff = 0;

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

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .badge-custom { padding: 5px 10px; font-weight: normal; font-size: 0.85rem; }
    .status-badge { padding: 5px 10px; font-size: 0.8rem; border-radius: 12px; font-weight: bold; }
    
    /* สไตล์สำหรับ Checkbox */
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
</style>

<div class="container-fluid pb-5 px-4">
    <form action="" method="POST" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete" value="1">
        
        <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title m-0 fw-bold text-success">
                    <i class="fa-solid fa-receipt me-2"></i> ประวัติการตัดยอดโครงการ
                </h2>
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 pl-0" placeholder="ค้นหาชื่อ, รหัสโครงการ, รายละเอียด..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-success px-3" type="button" onclick="window.location.href='Expenses.php?search='+document.getElementById('searchInput').value;">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Expenses.php" class="btn btn-outline-danger d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>

                    <?php if ($nav_role === 'admin'): ?>
                        <button type="button" class="btn btn-danger shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkDelete()">
                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                        </button>
                    <?php endif; ?>

                    <a href="Cut off the project budget.php" class="btn btn-primary shadow-sm ms-2" style="border-radius: 8px;">
                        <i class="fa-solid fa-arrow-left me-1"></i> กลับไปหน้าตัดยอด
                    </a>
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
                            
                            <th class="text-center py-3" style="width: 4%;">ที่</th>
                            <th class="text-center py-3" style="width: 9%;">วันที่ตัดยอด</th>
                            <th class="py-3" style="width: 9%;">อ้างอิงเอกสาร</th>
                            <th class="py-3" style="width: 15%;">ชื่อโครงการ</th>
                            <th class="py-3" style="width: 12%;">ผู้รับผิดชอบโครงการ</th>
                            <th class="py-3" style="width: 11%;">กลุ่มงาน</th>
                            <th class="py-3" style="width: 13%;">รายละเอียด/รายการ</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดที่ขอตัดยอด</th>
                            <th class="text-end py-3" style="width: 7%;">ยอดอนุมัติตัดจริง</th>
                            <th class="text-center py-3" style="width: 8%;">สถานะ</th>
                            
                            <?php if ($nav_role === 'admin'): ?>
                            <th class="text-center py-3" style="width: 5%;">จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data && $result_data->num_rows > 0) {
                            $i = $offset + 1; // ให้เลขลำดับเรียงต่อจากหน้าก่อนหน้า
                            while($row = $result_data->fetch_assoc()) {
                                
                                $amt = $row['cutoff_amount'] ?? 0;

                                // นำยอดไปบวกผลรวมเฉพาะรายการที่ 'อนุมัติแล้ว'
                                if (isset($row['approval_status']) && $row['approval_status'] === 'approved') {
                                    $total_request += $amt;
                                    $total_cutoff += $amt;
                                }
                                
                                $proj_code = $row['project_code'] ? $row['project_code'] : '-';
                                $proj_name = htmlspecialchars($row['project_name'] ?? 'ไม่พบชื่อโครงการ');
                                
                                // ดึงชื่อผู้รับผิดชอบโครงการ (กวาดหาจากทุกคอลัมน์ที่เป็นไปได้ โดยให้ความสำคัญกับ responsible_person ก่อน)
                                $resp_person = '-';
                                if (!empty($row['responsible_person'])) {
                                    $resp_person = $row['responsible_person'];
                                } elseif (!empty($row['requester_name'])) {
                                    $resp_person = $row['requester_name'];
                                } elseif (!empty($row['recorded_by'])) {
                                    $resp_person = $row['recorded_by'];
                                }
                                $resp_person = htmlspecialchars($resp_person);
                                
                                // ตัดข้อความชื่อกลุ่มงานให้สั้นลง
                                $raw_group = htmlspecialchars($row['group_name'] ?? '-');
                                $group_name_short = array_key_exists($raw_group, $group_abbr) ? $group_abbr[$raw_group] : $raw_group;

                                echo "<tr>";
                                
                                // Checkbox ของแต่ละรายการ (เฉพาะ Admin)
                                if ($nav_role === 'admin') {
                                    echo "<td class='text-center'>
                                            <input class='form-check-input item-checkbox' type='checkbox' name='delete_ids[]' value='{$row['id']}'>
                                          </td>";
                                }

                                echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                                echo "<td class='text-center'>" . thai_date_short($row['expense_date'] ?? date('Y-m-d')) . "</td>";
                                echo "<td><span class='badge bg-secondary badge-custom'>" . htmlspecialchars($row['ref_document'] ?? '-') . "</span></td>";
                                
                                echo "<td>
                                        <div class='text-primary fw-bold' style='font-size:0.85rem;'>[$proj_code]</div>
                                        <div class='text-dark'>$proj_name</div>
                                      </td>";
                                      
                                echo "<td><span class='text-primary fw-bold'>" . $resp_person . "</span></td>";
                                echo "<td><span title='" . $raw_group . "'>" . $group_name_short . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['details'] ?? '') . "</td>";
                                echo "<td class='text-end text-muted'>" . number_format($amt, 2) . "</td>";
                                
                                // หากเป็น rejected หรือ pending ยอดอนุมัติจะแสดงขีด (หรือ 0) ก็ได้
                                $cutoff_display = (isset($row['approval_status']) && $row['approval_status'] !== 'approved') ? "-" : number_format($amt, 2);
                                echo "<td class='text-end fw-bold text-danger'>" . $cutoff_display . "</td>";
                                
                                // คอลัมน์สถานะ (ตรวจสอบ approval_status)
                                echo "<td class='text-center'>";
                                $status = $row['approval_status'] ?? 'pending';
                                if ($status === 'approved') {
                                    echo "<span class='badge bg-success status-badge'><i class='fa-solid fa-check me-1'></i> อนุมัติแล้ว</span>";
                                } elseif ($status === 'rejected') {
                                    echo "<span class='badge bg-danger status-badge'><i class='fa-solid fa-xmark me-1'></i> ไม่อนุมัติ</span>";
                                } else {
                                    echo "<span class='badge bg-warning text-dark status-badge'><i class='fa-solid fa-hourglass-half me-1'></i> รออนุมัติ</span>";
                                }
                                echo "</td>";
                                
                                // ซ่อนคอลัมน์ลบ ถ้าไม่ใช่ Admin
                                if ($nav_role === 'admin') {
                                    echo "<td class='text-center'>";
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2' title='ยกเลิก/ลบรายการนี้' onclick='openDeleteModal(".$row['id'].")'><i class='fa-solid fa-trash-can'></i></button>";
                                    echo "</td>";
                                }
                                
                                echo "</tr>";
                            }
                            
                            // จัดการจำนวนช่อง Colspan แถวสรุปรวมให้ตรง
                            $colspan_left = ($nav_role === 'admin') ? 8 : 7;
                            $colspan_right = ($nav_role === 'admin') ? 2 : 1;

                            echo "<tr class='total-row'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมยอดการเบิกจ่าย (เฉพาะที่อนุมัติแล้วในหน้านี้) :</strong></td>";
                            echo "<td class='text-end py-3 text-muted'>" . number_format($total_request, 2) . "</td>";
                            echo "<td class='text-end py-3 text-danger fs-6'>" . number_format($total_cutoff, 2) . "</td>";
                            echo "<td colspan='{$colspan_right}'></td>";
                            echo "</tr>";

                        } else {
                            $colspan_all = ($nav_role === 'admin') ? 12 : 10;
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-solid fa-file-invoice fs-1 mb-3 d-block text-light'></i>ยังไม่มีประวัติการอนุมัติตัดยอดในปีงบประมาณ $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php if($page_num <= 1){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php echo "?search=".urlencode($search)."&page=".($page_num - 1); ?>">ก่อนหน้า</a>
                    </li>
                    
                    <?php for($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?php if($page_num == $p){ echo 'active'; } ?>">
                        <a class="page-link" href="<?php echo "?search=".urlencode($search)."&page=".$p; ?>"><?php echo $p; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php if($page_num >= $total_pages){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php echo "?search=".urlencode($search)."&page=".($page_num + 1); ?>">ถัดไป</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </form>
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบประวัติการตัดยอดนี้ใช่หรือไม่?</h4>
                <p class="text-muted mb-0 fs-5">หากลบแล้วจะไม่สามารถกู้คืนข้อมูลได้</p>
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
                <p class="text-muted mb-0 fs-5">หากยืนยัน ข้อมูลทั้งหมดที่ถูกเลือกจะถูกลบถาวร</p>
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
                <p class="text-muted fs-6 mb-4">ลบประวัติการตัดยอดเรียบร้อยแล้ว</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Expenses.php'">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ตรวจสอบ URL ว่ามีการลบสำเร็จหรือไม่ ถ้าสำเร็จให้แสดง Popup แจ้งเตือนสีเขียว
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('deleted')) {
            new bootstrap.Modal(document.getElementById('successDeleteModal')).show();
            // ลบ parameter deleted ออกจาก URL
            window.history.replaceState(null, null, window.location.pathname);
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
            
            // ถ้าไปติ๊กย่อยๆ ออก ให้เอาติ๊กหัวตารางออกด้วย
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = document.querySelectorAll('.item-checkbox:checked').length === itemCbs.length;
                    selectAllCb.checked = allChecked;
                });
            });
        }
    });

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
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>