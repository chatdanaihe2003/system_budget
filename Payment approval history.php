<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ประวัติการอนุมัติให้เบิก/ยืม ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ประวัติการอนุมัติให้เบิก/ขอยืมเงินโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- ดึงสิทธิ์ผู้ใช้งาน ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($nav_role === 'admin');

// ปลดล็อคให้ ID User และ User ทั่วไป สามารถเข้ามาดูประวัติได้

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (ลบข้อมูล แบบเดี่ยว และ แบบหลายรายการ) เฉพาะ Admin ---
// --------------------------------------------------------------------------------

// ลบแบบหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete']) && isset($_POST['delete_ids'])) {
    if ($is_admin) {
        $delete_ids = $_POST['delete_ids'];
        if (is_array($delete_ids) && count($delete_ids) > 0) {
            foreach ($delete_ids as $id) {
                $id = intval($id);
                
                // ดึงข้อมูลก่อนเพื่อไปลบในหน้าประวัติตัดยอด (project_expenses) ด้วย
                $stmt_get = $conn->prepare("SELECT project_id, doc_date, doc_no, description, amount FROM project_withdrawals WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $res = $stmt_get->get_result();
                if ($row = $res->fetch_assoc()) {
                    // ลบข้อมูลที่ผูกกันใน project_expenses
                    $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND cutoff_date = ? AND ref_document = ? AND description = ? AND request_amount = ? AND approval_status = 'approved' LIMIT 1");
                    if($stmt_del_exp){
                        $stmt_del_exp->bind_param("isssd", $row['project_id'], $row['doc_date'], $row['doc_no'], $row['description'], $row['amount']);
                        $stmt_del_exp->execute();
                    }
                }

                // ลบออกจาก project_withdrawals
                $stmt_del = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
            }
            echo "<script>window.location='".$current_page."?deleted=1';</script>";
            exit();
        }
    }
}

// ลบแบบทีละรายการ (เดี่ยว)
if (isset($_GET['delete_id'])) {
    if ($is_admin) {
        $id = intval($_GET['delete_id']);
        
        // ดึงข้อมูลก่อนเพื่อไปลบในหน้าประวัติตัดยอด (project_expenses) ด้วย
        $stmt_get = $conn->prepare("SELECT project_id, doc_date, doc_no, description, amount FROM project_withdrawals WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result();
        if ($row = $res->fetch_assoc()) {
            $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND cutoff_date = ? AND ref_document = ? AND description = ? AND request_amount = ? AND approval_status = 'approved' LIMIT 1");
            if($stmt_del_exp){
                $stmt_del_exp->bind_param("isssd", $row['project_id'], $row['doc_date'], $row['doc_no'], $row['description'], $row['amount']);
                $stmt_del_exp->execute();
            }
        }

        $stmt = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>window.location='".$current_page."?deleted=1';</script>";
        exit();
    }
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

// นับรวมข้อมูลทั้งหมด ไม่ว่าจะสถานะอะไรก็ตาม
if ($search != "") {
    $sql_count = "SELECT COUNT(*) as total_rows 
                  FROM project_withdrawals w 
                  LEFT JOIN project_outcomes p ON w.project_id = p.id 
                  WHERE w.budget_year = ? AND (p.project_name LIKE ? OR p.project_code LIKE ? OR w.doc_no LIKE ? OR w.description LIKE ?)";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("issss", $active_year, $search_param, $search_param, $search_param, $search_param);
} else {
    $sql_count = "SELECT COUNT(*) as total_rows 
                  FROM project_withdrawals w 
                  LEFT JOIN project_outcomes p ON w.project_id = p.id 
                  WHERE w.budget_year = ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $active_year);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total_rows'];
$total_pages = ceil($total_rows / $records_per_page);


// --- ดึงข้อมูลทั้งหมดรวมถึงที่รออนุมัติ มาแสดงผล พร้อม JOIN กับตาราง users เผื่อกรณีค่าเก่าเป็น ID ---
if ($search != "") {
    $sql_data = "SELECT w.*, p.project_code, p.project_name, p.group_name, u.name AS real_approver_name 
                 FROM project_withdrawals w 
                 LEFT JOIN project_outcomes p ON w.project_id = p.id 
                 LEFT JOIN users u ON w.officer_name = u.id 
                 WHERE w.budget_year = ? AND (p.project_name LIKE ? OR p.project_code LIKE ? OR w.doc_no LIKE ? OR w.description LIKE ?)
                 ORDER BY w.withdrawal_order DESC, w.id DESC 
                 LIMIT ?, ?";
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->bind_param("issssii", $active_year, $search_param, $search_param, $search_param, $search_param, $offset, $records_per_page);
} else {
    $sql_data = "SELECT w.*, p.project_code, p.project_name, p.group_name, u.name AS real_approver_name 
                 FROM project_withdrawals w 
                 LEFT JOIN project_outcomes p ON w.project_id = p.id 
                 LEFT JOIN users u ON w.officer_name = u.id 
                 WHERE w.budget_year = ?
                 ORDER BY w.withdrawal_order DESC, w.id DESC 
                 LIMIT ?, ?";
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->bind_param("iii", $active_year, $offset, $records_per_page);
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

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .badge-custom { padding: 6px 12px; font-weight: normal; font-size: 0.85rem; border-radius: 6px; }
    .amount-display { font-size: 1.05rem; font-weight: bold; }
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
</style>

<div class="container-fluid pb-5 px-4">
    <form action="" method="POST" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete" value="1">
        
        <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title m-0 fw-bold text-success">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i> ประวัติการอนุมัติให้เบิก/ยืม
                </h2>
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 pl-0" placeholder="ค้นหาชื่อ, รหัสโครงการ, เลขที่..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-success px-3" type="button" onclick="window.location.href='<?php echo $current_page; ?>?search='+document.getElementById('searchInput').value;">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="<?php echo $current_page; ?>" class="btn btn-outline-danger d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>

                    <?php if ($is_admin): ?>
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
                            <?php if ($is_admin): ?>
                            <th class="text-center py-3" style="width: 3%;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <?php endif; ?>
                            
                            <th class="text-center py-3" style="width: 5%;">ที่</th>
                            <th class="text-center py-3" style="width: 10%;">วันที่</th>
                            <th class="py-3" style="width: 12%;">อ้างอิงเอกสาร</th>
                            <th class="py-3" style="width: 25%;">โครงการ / รายละเอียดการขอเบิก</th>
                            <th class="text-end py-3" style="width: 12%;">จำนวนเงิน (บาท)</th>
                            <th class="text-center py-3" style="width: 15%;">ผู้เบิก / ผู้อนุมัติให้เบิก/ให้ยืม</th>
                            <th class="text-center py-3" style="width: 10%;">สถานะ</th>
                            <?php if ($is_admin): ?>
                            <th class="text-center py-3" style="width: 8%;">จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_amount = 0;
                        $i = $offset + 1; // ลำดับที่เริ่มจาก offset ของหน้านั้นๆ

                        if ($result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                // บวกยอดเฉพาะที่อนุมัติแล้วเท่านั้น
                                if (isset($row['status']) && $row['status'] === 'approved') {
                                    $total_amount += $row['amount'];
                                }
                                
                                $proj_code = $row['project_code'] ? $row['project_code'] : '-';
                                $proj_name = htmlspecialchars($row['project_name'] ?? 'ไม่พบชื่อโครงการ');
                                
                                // ตัดข้อความชื่อกลุ่มงานให้สั้นลง
                                $raw_group = htmlspecialchars($row['group_name'] ?? '-');
                                $group_name_short = array_key_exists($raw_group, $group_abbr) ? $group_abbr[$raw_group] : $raw_group;

                                // --- หาชื่อผู้อนุมัติ ---
                                // เช็คว่า real_approver_name มีค่าไหม (ได้จากการ JOIN ด้วย ID)
                                // ถ้าไม่มี แสดงว่าเค้าบันทึกเป็นชื่อตรงๆ มาแล้ว (ระบบใหม่) ก็ให้ใช้ officer_name ได้เลย
                                $display_approver = '-';
                                if (!empty($row['real_approver_name'])) {
                                    $display_approver = $row['real_approver_name'];
                                } elseif (!empty($row['officer_name']) && $row['officer_name'] !== '0') {
                                    $display_approver = $row['officer_name'];
                                }
                                $display_approver = htmlspecialchars($display_approver);

                                echo "<tr>";
                                
                                // Checkbox ของแต่ละรายการ (เฉพาะ Admin)
                                if ($is_admin) {
                                    echo "<td class='text-center'>
                                            <input class='form-check-input item-checkbox' type='checkbox' name='delete_ids[]' value='{$row['id']}'>
                                          </td>";
                                }

                                echo "<td class='text-center text-muted fw-bold'>" . $i++ . "</td>";
                                echo "<td class='text-center'>" . thai_date_short($row['doc_date']) . "</td>";
                                echo "<td><span class='badge bg-secondary badge-custom'>" . htmlspecialchars($row['doc_no'] ?: 'ไม่มีเลขที่') . "</span></td>";
                                
                                echo "<td>
                                        <div class='text-primary fw-bold mb-1' style='font-size:0.85rem;'>[$proj_code] $proj_name</div>
                                        <div class='text-dark small'><i class='fa-solid fa-angles-right text-muted me-1'></i>" . htmlspecialchars($row['description']) . "</div>
                                      </td>";
                                      
                                echo "<td class='text-end fw-bold text-success amount-display'>" . number_format($row['amount'], 2) . "</td>";
                                
                                // --- [แก้ไข] แสดง ผู้ขอเบิก และ ผู้อนุมัติให้เบิก/ให้ยืม ---
                                echo "<td class='text-center small'>
                                        <div class='text-muted'>ผู้ขอ: <span class='text-dark'>".htmlspecialchars($row['requester'] ?? '-')."</span></div>
                                        <div class='text-muted'>กลุ่มงาน: <span class='text-dark' title='" . $raw_group . "'>".$group_name_short."</span></div>
                                        <div class='text-muted'>ผู้อนุมัติให้เบิก/ให้ยืม: <span class='text-primary fw-bold'>".$display_approver."</span></div>
                                      </td>";
                                      
                                // --- แสดงสถานะ ---
                                echo "<td class='text-center'>";
                                $cur_status = $row['status'] ?? 'pending';
                                if ($cur_status === 'approved') {
                                    echo "<span class='badge bg-success badge-custom'><i class='fa-solid fa-check me-1'></i>อนุมัติแล้ว</span>";
                                } elseif ($cur_status === 'rejected') {
                                    echo "<span class='badge bg-danger badge-custom'><i class='fa-solid fa-xmark me-1'></i>ยกเลิก</span>";
                                } else {
                                    echo "<span class='badge bg-warning text-dark badge-custom'><i class='fa-solid fa-hourglass-half me-1'></i>รออนุมัติ</span>";
                                }
                                echo "</td>";

                                // ส่วนของปุ่มลบ (เฉพาะ Admin)
                                if ($is_admin) {
                                    echo "<td class='text-center'>";
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2' title='ลบรายการประวัตินี้' onclick='openDeleteModal(".$row['id'].")'><i class='fa-solid fa-trash-can'></i></button>";
                                    echo "</td>";
                                }

                                echo "</tr>";
                            }
                            
                            // จัดการช่องว่างสำหรับแถวรวมยอด
                            $colspan_left = $is_admin ? 5 : 4;
                            $colspan_right = $is_admin ? 2 : 1;

                            echo "<tr class='total-row'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมยอดอนุมัติ (เฉพาะที่อนุมัติแล้วหน้านี้) :</strong></td>";
                            echo "<td class='text-end py-3 text-success fs-5'><strong>" . number_format($total_amount, 2) . "</strong></td>";
                            echo "<td colspan='{$colspan_right}'></td>";
                            echo "</tr>";

                        } else {
                            $colspan_all = $is_admin ? 9 : 7;
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-solid fa-folder-open fs-1 mb-3 d-block text-light'></i>ยังไม่มีประวัติการอนุมัติให้เบิก/ยืม ในปีงบประมาณ $active_year</td></tr>";
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

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-danger bg-opacity-10 rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-trash-can text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">ยืนยันการลบ</h4>
                <p class="text-muted fs-6 mb-3">คุณต้องการลบประวัตินี้ใช่หรือไม่?</p>
                <div class="alert alert-warning text-start border-0 p-2 mb-4" style="background-color: #fff3cd; color: #856404; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-circle-info me-1"></i> ข้อมูลที่ลบจะไม่สามารถกู้คืนได้
                </div>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary fw-bold w-50" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger fw-bold w-50" style="border-radius: 8px;">ลบข้อมูล</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-danger bg-opacity-10 rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-layer-group text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">ลบหลายรายการ</h4>
                <p class="text-muted fs-6 mb-3">คุณต้องการลบข้อมูล <span id="bulkCountText" class="text-danger fw-bold fs-4"></span> รายการ?</p>
                <div class="alert alert-warning text-start border-0 p-2 mb-4" style="background-color: #fff3cd; color: #856404; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-circle-info me-1"></i> ข้อมูลทั้งหมดที่เลือกจะถูกลบถาวร
                </div>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary fw-bold w-50" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                    <button type="button" class="btn btn-danger fw-bold w-50" onclick="document.getElementById('bulkDeleteForm').submit();" style="border-radius: 8px;">ลบทั้งหมด</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4">ลบประวัติเรียบร้อยแล้ว</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='<?php echo $current_page; ?>'">ตกลง</button>
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
            // เปลี่ยนมาใช้ Modal แทน Alert ธรรมดา
            new bootstrap.Modal(document.getElementById('alertNoSelectModal')).show();
            return;
        }
        
        document.getElementById('bulkCountText').innerText = checkedItems.length;
        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    }

    // ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูล (แบบเดี่ยว)
    function openDeleteModal(id) {
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>