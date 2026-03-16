<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "อนุมัติการตัดยอดงบประมาณ ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'อนุมัติการตัดยอดงบประมาณโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- เช็คสิทธิ์การเข้าถึง (อาจจะอนุญาตเฉพาะ Admin หรือ การเงิน) ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='index.php';</script>";
    exit();
}

// --- ตรวจสอบและสร้างคอลัมน์สถานะการอนุมัติให้อัตโนมัติ ---
$check_col = $conn->query("SHOW COLUMNS FROM project_expenses LIKE 'approval_status'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE project_expenses ADD approval_status VARCHAR(50) DEFAULT 'pending'");
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (อนุมัติ / ไม่อนุมัติ / ลบ) ---
// --------------------------------------------------------------------------------

// [เพิ่มใหม่] ลบแบบหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete']) && isset($_POST['delete_ids'])) {
    $delete_ids = $_POST['delete_ids'];
    if (is_array($delete_ids) && count($delete_ids) > 0) {
        foreach ($delete_ids as $id) {
            $id = intval($id);
            $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
            if($stmt){
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
        }
        echo "<script>window.location='Approve the cut off amount.php?deleted=1';</script>";
        exit();
    }
}

// 1. ลบรายการคำขอ (ลบทิ้งออกจากระบบจริงๆ) แบบเดี่ยว
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
    if($stmt){
        $stmt->bind_param("i", $id);
        if($stmt->execute()) {
            echo "<script>window.location='Approve the cut off amount.php?deleted=1';</script>";
            exit();
        }
    }
}

// 2. จัดการปุ่ม อนุมัติ / ไม่อนุมัติ จาก Popup Modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $req_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        // ดึงข้อมูลที่กำลังจะอนุมัติ เพื่อส่งไปตาราง project_withdrawals
        $stmt_get = $conn->prepare("SELECT * FROM project_expenses WHERE id = ?");
        $stmt_get->bind_param("i", $req_id);
        $stmt_get->execute();
        $exp_data = $stmt_get->get_result()->fetch_assoc();

        if ($exp_data) {
            // 2.1 เปลี่ยนสถานะในตาราง project_expenses เป็น approved 
            $stmt = $conn->prepare("UPDATE project_expenses SET approval_status = 'approved' WHERE id = ?");
            if($stmt){
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
            }

            // 2.2 นำข้อมูลส่งไปตาราง project_withdrawals
            $b_year = $exp_data['budget_year'];
            $doc_date = $exp_data['expense_date'] ?? ($exp_data['cutoff_date'] ?? date('Y-m-d'));
            $doc_no = $exp_data['ref_document'] ?? '-';
            $desc = $exp_data['details'] ?? ($exp_data['description'] ?? '-');
            $proj_id = $exp_data['project_id'];
            $amt = $exp_data['cutoff_amount'] ?? ($exp_data['request_amount'] ?? 0);
            
            // ดึงชื่อผู้ขอเบิก/ผู้ทำรายการที่แท้จริง
            $req_by = 'ไม่ระบุ';
            if(isset($exp_data['user_id'])) {
                $s_user = $conn->query("SELECT name FROM users WHERE id = ".intval($exp_data['user_id']));
                if($s_user && $s_user->num_rows > 0) {
                    $req_by = $s_user->fetch_assoc()['name'];
                } else {
                    $req_by = "User ID: " . $exp_data['user_id'];
                }
            } elseif(isset($exp_data['recorded_by'])) {
                $req_by = $exp_data['recorded_by'];
            } else {
                $get_proj = $conn->query("SELECT * FROM project_outcomes WHERE id = " . intval($proj_id));
                if($get_proj && $get_proj->num_rows > 0) {
                    $proj_data = $get_proj->fetch_assoc();
                    if(!empty($proj_data['responsible_person'])) {
                        $req_by = $proj_data['responsible_person'];
                    }
                }
            }
            
            $req_type = 'ขอเบิก'; 
            $status = 'pending'; 
            
            // --- [ส่วนที่แก้ไข] ดึงชื่อคนอนุมัติ (คนที่ล็อกอินอยู่) เพื่อส่งไปเป็น officer_name ---
            $approver_name = isset($_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'ผู้ดูแลระบบ');
            
            // บันทึกลงตาราง ทะเบียนขอเบิก พร้อมแนบชื่อคนอนุมัติไปด้วย
            $stmt_ins = $conn->prepare("INSERT INTO project_withdrawals 
                (budget_year, doc_date, doc_no, request_type, description, project_id, amount, requester, officer_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if($stmt_ins){
                // i s s s s i d s s s (1 int, 4 string, 1 int, 1 double, 3 string)
                $stmt_ins->bind_param("issssidsss", $b_year, $doc_date, $doc_no, $req_type, $desc, $proj_id, $amt, $req_by, $approver_name, $status);
                $stmt_ins->execute();
            }
        }

        echo "<script>window.location='Approve the cut off amount.php?status=approved_success';</script>";
        exit();

    } elseif ($action === 'reject') {
        // เปลี่ยนสถานะเป็น rejected
        $stmt = $conn->prepare("UPDATE project_expenses SET approval_status = 'rejected' WHERE id = ?");
        if($stmt){
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
        }
        echo "<script>window.location='Approve the cut off amount.php?status=rejected_success';</script>";
        exit();
    }
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// --- สร้างคำสั่ง SQL แบบกวาดหาคอลัมน์อัตโนมัติ (ป้องกัน Error และหาชื่อเจอแน่นอน) ---
$has_resp_person = ($conn->query("SHOW COLUMNS FROM project_outcomes LIKE 'responsible_person'")->num_rows > 0);
$has_user_id = ($conn->query("SHOW COLUMNS FROM project_expenses LIKE 'user_id'")->num_rows > 0);

// [แก้ไข] เพิ่มการดึง p.budget_type เข้าไปใน $sel_p
$sel_p = "p.project_code, p.project_name, p.group_name, p.budget_type";
if ($has_resp_person) $sel_p .= ", p.responsible_person";

$sel_u = "";
$join_u = "";
if ($has_user_id) {
    $sel_u = ", u.name AS requester_name";
    $join_u = "LEFT JOIN users u ON e.user_id = u.id";
}

// --- ดึงข้อมูลเฉพาะรายการที่ "รอการอนุมัติ" (pending) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search . "%";

if ($search != "") {
    $sql = "SELECT e.*, $sel_p $sel_u
            FROM project_expenses e
            JOIN project_outcomes p ON e.project_id = p.id
            $join_u
            WHERE e.budget_year = ? AND e.approval_status = 'pending' 
            AND (p.project_code LIKE ? OR p.project_name LIKE ?)
            ORDER BY e.id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'><strong><i class='fa-solid fa-triangle-exclamation'></i> เกิดข้อผิดพลาดในฐานข้อมูล:</strong> " . $conn->error . "</div></div>"); }
    $stmt->bind_param("iss", $active_year, $search_param, $search_param);
} else {
    $sql = "SELECT e.*, $sel_p $sel_u
            FROM project_expenses e
            JOIN project_outcomes p ON e.project_id = p.id
            $join_u
            WHERE e.budget_year = ? AND e.approval_status = 'pending'
            ORDER BY e.id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'><strong><i class='fa-solid fa-triangle-exclamation'></i> เกิดข้อผิดพลาดในฐานข้อมูล:</strong> " . $conn->error . "</div></div>"); }
    $stmt->bind_param("i", $active_year);
}
$stmt->execute();
$result_requests = $stmt->get_result();

?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .status-badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; }
    .amount-display { font-size: 1.1rem; font-weight: bold; letter-spacing: 0.5px; }
    .readonly-data { background-color: #f8f9fa; padding: 8px 12px; border-radius: 6px; border: 1px solid #e9ecef; color: #495057; font-weight: 500;}
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-warning">
                <i class="fa-solid fa-file-signature me-2"></i> อนุมัติการตัดยอดงบประมาณ
            </h2>
            
            <div class="d-flex align-items-center flex-wrap gap-2">
                <form action="" method="GET" class="d-flex m-0">
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส, ชื่อโครงการ..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary px-4" type="submit">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Approve the cut off amount.php" class="btn btn-outline-danger ms-2 d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </form>

                <?php if ($nav_role === 'admin'): ?>
                    <button type="button" class="btn btn-danger shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkDelete()">
                        <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <form action="" method="POST" id="bulkDeleteForm">
            <input type="hidden" name="bulk_delete" value="1">
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php if ($nav_role === 'admin'): ?>
                            <th class="text-center py-3" style="width: 3%;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <?php endif; ?>

                            <th class="text-center py-3" style="width: 5%;">ที่</th>
                            <th class="text-center py-3" style="width: 10%;">วันที่ขอเบิก</th>
                            <th class="py-3" style="width: 20%;">ชื่อโครงการ</th>
                            <th class="py-3" style="width: 15%;">ผู้รับผิดชอบ</th>
                            <th class="py-3" style="width: 17%;">รายการ/รายละเอียด</th>
                            <th class="text-end py-3" style="width: 12%;">จำนวนเงินที่ขอเบิก</th>
                            <th class="text-center py-3" style="width: 10%;">สถานะ</th>
                            <th class="text-center py-3" style="width: 8%;">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_requests && $result_requests->num_rows > 0) {
                            $i = 1;
                            $total_req_amount = 0;

                            while($row = $result_requests->fetch_assoc()) {
                                $amt_disp = $row['cutoff_amount'] ?? ($row['request_amount'] ?? 0);
                                $total_req_amount += $amt_disp;
                                
                                $date_disp = $row['expense_date'] ?? ($row['cutoff_date'] ?? date('Y-m-d'));
                                $desc_disp = $row['details'] ?? ($row['description'] ?? '-');

                                // ชื่อผู้รับผิดชอบโครงการ
                                $resp_person = !empty($row['responsible_person']) ? htmlspecialchars($row['responsible_person']) : '-';
                                
                                // แยกดึงชื่อผู้ทำรายการ (ผู้ที่คลิกขอเบิกเข้ามา)
                                $actual_requester = '-';
                                if (!empty($row['requester_name'])) {
                                    $actual_requester = $row['requester_name']; 
                                } elseif (!empty($row['recorded_by'])) {
                                    $actual_requester = $row['recorded_by'];
                                }
                                $actual_requester = htmlspecialchars($actual_requester);

                                echo "<tr>";

                                if ($nav_role === 'admin') {
                                    echo "<td class='text-center'>
                                            <input class='form-check-input item-checkbox' type='checkbox' name='delete_ids[]' value='{$row['id']}'>
                                          </td>";
                                }

                                echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                                echo "<td class='text-center'>" . thai_date_short($date_disp) . "</td>";
                                echo "<td>
                                        <div class='text-primary fw-bold' style='font-size:0.85rem;'>[" . htmlspecialchars($row['project_code'] ?? '-') . "]</div>
                                        <div class='text-dark mb-1'>" . htmlspecialchars($row['project_name']) . "</div>
                                      </td>";
                                echo "<td>" . $resp_person . "</td>";
                                echo "<td>" . htmlspecialchars($desc_disp) . "</td>";
                                echo "<td class='text-end fw-bold text-danger amount-display'>" . number_format($amt_disp, 2) . "</td>";
                                
                                echo "<td class='text-center'><span class='badge bg-warning text-dark status-badge'><i class='fa-solid fa-hourglass-half me-1'></i> รอการอนุมัติ</span></td>";
                                
                                echo "<td class='text-center text-nowrap'>";
                                
                                // ส่งข้อมูลเข้า Javascript เพื่อเปิด Modal พร้อมแนบ actual_requester และ budget_type
                                $js_data = htmlspecialchars(json_encode([
                                    'id' => $row['id'],
                                    'date' => thai_date_short($date_disp),
                                    'code' => $row['project_code'],
                                    'name' => $row['project_name'],
                                    'group_name' => $row['group_name'] ?? '-', 
                                    'budget_type' => $row['budget_type'] ?? '-', // เพิ่มข้อมูลประเภทงบประมาณ
                                    'desc' => $desc_disp,
                                    'amount' => $amt_disp,
                                    'resp_person' => $resp_person,
                                    'actual_requester' => $actual_requester 
                                ]), ENT_QUOTES, 'UTF-8');
                                
                                echo "<button type='button' class='btn btn-sm btn-success shadow-sm px-2 me-1' title='ตรวจสอบและอนุมัติ' onclick='openApproveModal({$js_data})'><i class='fa-solid fa-check-to-slot'></i></button>";
                                
                                if ($nav_role === 'admin') {
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2' title='ลบรายการนี้' onclick='openDeleteModal(".$row['id'].")'><i class='fa-solid fa-trash-can'></i></button>";
                                }
                                
                                echo "</td>";
                                echo "</tr>";
                            }

                            // คำนวณ Colspan ให้ถูกต้อง (Admin = 6 คอลัมน์ทางซ้าย, ทั่วไป = 5 คอลัมน์ทางซ้าย)
                            $colspan_left = ($nav_role === 'admin') ? 6 : 5;

                            echo "<tr class='total-row table-light'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมยอดขอตัดยอดที่รออนุมัติ :</strong></td>";
                            echo "<td class='text-end py-3 text-danger fs-5'><strong>" . number_format($total_req_amount, 2) . "</strong></td>";
                            echo "<td colspan='2'></td>";
                            echo "</tr>";

                        } else {
                            // คำนวณ Colspan ทั้งหมดเมื่อไม่มีข้อมูล (Admin = 9 คอลัมน์, ทั่วไป = 8 คอลัมน์)
                            $colspan_all = ($nav_role === 'admin') ? 9 : 8;
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-regular fa-circle-check fs-1 mb-3 d-block text-success'></i>ไม่มีรายการรออนุมัติในขณะนี้</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST" id="formApproveModal"> 
                <input type="hidden" name="request_id" id="modal_request_id">
                <input type="hidden" name="action" id="modal_action" value=""> 
                
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-check me-2"></i> พิจารณาอนุมัติการตัดยอดงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <span class="badge bg-warning text-dark fs-6 px-4 py-2" style="border-radius: 30px;">
                            <i class='fa-solid fa-hourglass-half me-1'></i> สถานะปัจจุบัน: รอการอนุมัติ
                        </span>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">วันที่ขอตัดยอด</label>
                            <div class="readonly-data" id="disp_date"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">โครงการที่ขอตัดยอด</label>
                            <div class="readonly-data">
                                <div class="mb-0"><span class="text-primary fw-bold" id="disp_code"></span> - <span id="disp_name"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">ผู้รับผิดชอบโครงการ</label>
                            <div class="readonly-data text-dark">
                                <i class="fa-solid fa-user-tie me-2"></i><span id="disp_resp_person"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">ผู้ขอตัดยอด (ผู้ทำรายการ)</label>
                            <div class="readonly-data text-primary fw-bold">
                                <i class="fa-solid fa-user-pen me-2"></i><span id="disp_actual_requester"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">กลุ่มงาน</label>
                            <div class="readonly-data" id="disp_group_name"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">ประเภทงบประมาณ</label>
                            <div class="readonly-data text-info fw-bold" id="disp_budget_type"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">รายการ/รายละเอียดการตัดยอด</label>
                            <div class="readonly-data" id="disp_desc" style="min-height: 60px;"></div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row align-items-center bg-light p-3 rounded mt-2 border border-danger border-opacity-25">
                        <div class="col-md-7 text-end">
                            <h5 class="fw-bold text-dark mb-0">จำนวนเงินที่ขอตัดยอด :</h5>
                        </div>
                        <div class="col-md-5 text-end">
                            <h3 class="fw-bold text-danger mb-0" id="disp_amount">0.00 ฿</h3>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 d-flex justify-content-center py-3">
                    <button type="button" class="btn btn-secondary px-4 me-auto" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                    
                    <button type="button" class="btn btn-outline-danger px-4 fw-bold" onclick="showConfirmRejectModal()">
                        <i class="fa-solid fa-xmark me-1"></i> ไม่อนุมัติ
                    </button>
                    
                    <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="showConfirmApproveModal()">
                        <i class="fa-solid fa-check me-1"></i> อนุมัติการตัดยอด
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-question me-2"></i> ยืนยันการอนุมัติ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-check-circle text-success mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">ยืนยันการอนุมัติการตัดยอดนี้ใช่หรือไม่?</h4>
                <p class="text-muted mb-0 fs-6">ข้อมูลจะถูกส่งไปยังหน้าทะเบียนขอเบิกโครงการ</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-success px-4 fw-bold" style="border-radius: 8px;" onclick="submitApproveForm()"><i class="fa-solid fa-check me-1"></i> ยืนยันอนุมัติ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmRejectModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการไม่อนุมัติ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-xmark-circle text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณแน่ใจหรือไม่ว่าต้องการ ปฏิเสธ (ไม่อนุมัติ) คำขอนี้?</h4>
                <p class="text-muted mb-0 fs-6">ข้อมูลจะถูกบันทึกสถานะว่าไม่อนุมัติไว้ในประวัติ</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;" onclick="submitRejectForm()"><i class="fa-solid fa-xmark me-1"></i> ยืนยันไม่อนุมัติ</button>
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
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Approve the cut off amount.php'">ตกลง</button>
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
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบคำขอนี้ใช่หรือไม่?</h4>
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

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('deleted')) {
            new bootstrap.Modal(document.getElementById('successActionModal')).show();
            document.getElementById('successActionMessage').innerHTML = "ลบคำขอเรียบร้อยแล้ว";
            window.history.replaceState(null, null, window.location.pathname);
        }
        
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            let msg = '';
            if (status === 'approved_success') {
                msg = 'อนุมัติรายการสำเร็จ!<br>ข้อมูลถูกส่งไปยังหน้าทะเบียนเรียบร้อยแล้ว';
            } else if (status === 'rejected_success') {
                msg = 'บันทึกการไม่อนุมัติรายการเรียบร้อยแล้ว<br>สามารถดูประวัติได้ที่หน้า Expenses';
            }
            
            if (msg !== '') {
                document.getElementById('successActionMessage').innerHTML = msg;
                new bootstrap.Modal(document.getElementById('successActionModal')).show();
                window.history.replaceState(null, null, window.location.pathname);
            }
        }

        const selectAllCb = document.getElementById('selectAll');
        const itemCbs = document.querySelectorAll('.item-checkbox');

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function() {
                itemCbs.forEach(cb => { cb.checked = selectAllCb.checked; });
            });
            
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = document.querySelectorAll('.item-checkbox:checked').length === itemCbs.length;
                    selectAllCb.checked = allChecked;
                });
            });
        }
    });

    function openApproveModal(data) {
        document.getElementById('modal_request_id').value = data.id;
        document.getElementById('disp_date').innerText = data.date;
        document.getElementById('disp_code').innerText = data.code ? '[' + data.code + ']' : '';
        document.getElementById('disp_name').innerText = data.name;
        document.getElementById('disp_resp_person').innerText = data.resp_person;
        
        document.getElementById('disp_actual_requester').innerText = data.actual_requester;
        
        document.getElementById('disp_group_name').innerText = data.group_name;
        document.getElementById('disp_budget_type').innerText = data.budget_type; // แสดงประเภทงบประมาณที่นี่
        document.getElementById('disp_desc').innerText = data.desc;
        
        let amt = parseFloat(data.amount).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('disp_amount').innerText = amt + " บาท";
        
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }

    function showConfirmApproveModal() { new bootstrap.Modal(document.getElementById('confirmApproveModal')).show(); }
    function showConfirmRejectModal() { new bootstrap.Modal(document.getElementById('confirmRejectModal')).show(); }
    
    function submitApproveForm() {
        document.getElementById('modal_action').value = 'approve';
        document.getElementById('formApproveModal').submit();
    }

    function submitRejectForm() {
        document.getElementById('modal_action').value = 'reject';
        document.getElementById('formApproveModal').submit();
    }

    function openDeleteModal(id) {
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function checkBulkDelete() {
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        if (checkedItems.length === 0) {
            alert("กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ");
            return;
        }
        document.getElementById('bulkCountText').innerText = checkedItems.length;
        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    }
</script>