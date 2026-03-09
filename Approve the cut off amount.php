<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "อนุมัติการตัดยอดงบประมาณ - AMSS++";
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
    // เพิ่มคอลัมน์สถานะ (pending = รออนุมัติ, approved = อนุมัติแล้ว, rejected = ไม่อนุมัติ)
    $conn->query("ALTER TABLE project_expenses ADD approval_status VARCHAR(50) DEFAULT 'pending' AFTER recorded_by");
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (อนุมัติ / ไม่อนุมัติ / ลบ) ---
// --------------------------------------------------------------------------------

// 1. ลบรายการคำขอ
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        echo "<script>alert('ลบรายการคำขอเรียบร้อยแล้ว'); window.location='Approve the cut off amount.php';</script>";
        exit();
    }
}

// 2. จัดการปุ่ม อนุมัติ / ไม่อนุมัติ จาก Popup Modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $req_id = $_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        // [แก้ไขใหม่] ดึงข้อมูลที่กำลังจะอนุมัติ เพื่อส่งไปตาราง project_withdrawals
        $stmt_get = $conn->prepare("SELECT * FROM project_expenses WHERE id = ?");
        $stmt_get->bind_param("i", $req_id);
        $stmt_get->execute();
        $exp_data = $stmt_get->get_result()->fetch_assoc();

        if ($exp_data) {
            // 2.1 เปลี่ยนสถานะในตาราง project_expenses เป็น approved
            $stmt = $conn->prepare("UPDATE project_expenses SET approval_status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $req_id);
            $stmt->execute();

            // 2.2 นำข้อมูลส่งไปตาราง project_withdrawals (เพื่อให้ไปแสดงหน้า ทะเบียนขอเบิก/ขอยืมเงินโครงการ)
            $b_year = $exp_data['budget_year'];
            $doc_date = $exp_data['cutoff_date'];
            $doc_no = $exp_data['ref_document'];
            $desc = $exp_data['description'];
            $proj_id = $exp_data['project_id'];
            $amt = $exp_data['request_amount'];
            $req_by = $exp_data['recorded_by'];
            
            $w_type = 4; // ให้ประเภทเป็น 4 = ขอเบิก
            $status = 'green'; // สถานะอนุมัติแล้วให้เป็นสีเขียว
            $act_id = 0;
            $app_by = $_SESSION['name'] ?? 'Admin';

            // หา withdrawal_order ถัดไปในปีนั้น
            $sql_max = "SELECT MAX(withdrawal_order) as max_order FROM project_withdrawals WHERE budget_year = ?";
            $stmt_max = $conn->prepare($sql_max);
            $stmt_max->bind_param("i", $b_year);
            $stmt_max->execute();
            $res_max = $stmt_max->get_result();
            $row_max = $res_max->fetch_assoc();
            $auto_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

            // บันทึกลงตาราง
            $stmt_ins = $conn->prepare("INSERT INTO project_withdrawals 
                (budget_year, withdrawal_order, doc_date, doc_no, withdrawal_type, description, project_id, activity_id, amount, requester, officer_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("iissisiidsss", $b_year, $auto_order, $doc_date, $doc_no, $w_type, $desc, $proj_id, $act_id, $amt, $req_by, $app_by, $status);
            $stmt_ins->execute();
        }

        // แจ้งเตือนและเด้งไปหน้า RequestforWithdrawalProjectLoan.php
        echo "<script>alert('อนุมัติรายการสำเร็จ! ข้อมูลถูกส่งไปยังหน้าทะเบียนขอเบิกโครงการเรียบร้อยแล้ว'); window.location='RequestforWithdrawalProjectLoan.php';</script>";
        exit();

    } elseif ($action === 'reject') {
        // ถ้าไม่อนุมัติ ให้ลบทิ้ง
        $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        echo "<script>alert('ไม่อนุมัติรายการ (ระบบได้ลบคำขอนี้ทิ้งแล้ว)'); window.location='Approve the cut off amount.php';</script>";
        exit();
    }
}

// --- ดึงข้อมูลเฉพาะรายการที่ "รอการอนุมัติ" (pending) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search . "%";

// ดึงข้อมูลการขอเบิก (project_expenses) มาเชื่อมกับชื่อโครงการ (project_outcomes)
if ($search != "") {
    $sql = "SELECT e.*, p.project_code, p.project_name 
            FROM project_expenses e
            JOIN project_outcomes p ON e.project_id = p.id
            WHERE e.budget_year = ? AND e.approval_status = 'pending' 
            AND (p.project_code LIKE ? OR p.project_name LIKE ? OR e.ref_document LIKE ?)
            ORDER BY e.cutoff_date ASC, e.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $active_year, $search_param, $search_param, $search_param);
} else {
    $sql = "SELECT e.*, p.project_code, p.project_name 
            FROM project_expenses e
            JOIN project_outcomes p ON e.project_id = p.id
            WHERE e.budget_year = ? AND e.approval_status = 'pending'
            ORDER BY e.cutoff_date ASC, e.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $active_year);
}
$stmt->execute();
$result_requests = $stmt->get_result();

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .status-badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; }
    .amount-display { font-size: 1.1rem; font-weight: bold; letter-spacing: 0.5px; }
    
    /* สไตล์สำหรับข้อมูลใน Modal ให้ดูอ่านง่าย */
    .readonly-data { background-color: #f8f9fa; padding: 8px 12px; border-radius: 6px; border: 1px solid #e9ecef; color: #495057; font-weight: 500;}
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-warning">
                <i class="fa-solid fa-file-signature me-2"></i> อนุมัติการตัดยอดงบประมาณ
            </h2>
            
            <form action="" method="GET" class="d-flex">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส, ชื่อโครงการ หรือเลขที่เอกสาร..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary px-4" type="submit">ค้นหา</button>
                </div>
                <?php if($search != ""): ?>
                    <a href="Approve the cut off amount.php" class="btn btn-outline-danger ms-2 d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="text-center py-3" style="width: 10%;">วันที่ขอเบิก</th>
                        <th class="py-3" style="width: 12%;">เลขที่เอกสารอ้างอิง</th>
                        <th class="py-3" style="width: 25%;">โครงการที่ขอเบิก</th>
                        <th class="py-3" style="width: 20%;">รายการ/รายละเอียด</th>
                        <th class="text-end py-3" style="width: 12%;">จำนวนเงินที่ขอเบิก</th>
                        <th class="text-center py-3" style="width: 8%;">สถานะ</th>
                        <th class="text-center py-3" style="width: 8%;">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_requests->num_rows > 0) {
                        $i = 1;
                        $total_req_amount = 0;

                        while($row = $result_requests->fetch_assoc()) {
                            $total_req_amount += $row['request_amount'];

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                            echo "<td class='text-center'>" . thai_date_short($row['cutoff_date']) . "</td>";
                            echo "<td><span class='badge bg-secondary' style='font-weight:normal;'>" . htmlspecialchars($row['ref_document'] ?: 'ไม่มีเลขที่') . "</span></td>";
                            echo "<td>
                                    <div class='text-primary fw-bold' style='font-size:0.85rem;'>[" . htmlspecialchars($row['project_code'] ?? '-') . "]</div>
                                    <div class='text-dark'>" . htmlspecialchars($row['project_name']) . "</div>
                                  </td>";
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end fw-bold text-danger amount-display'>" . number_format($row['request_amount'], 2) . "</td>";
                            
                            // สถานะรอการอนุมัติ (สีส้ม)
                            echo "<td class='text-center'><span class='badge bg-warning text-dark status-badge'><i class='fa-solid fa-hourglass-half me-1'></i> รอการอนุมัติ</span></td>";
                            
                            echo "<td class='text-center text-nowrap'>";
                            
                            // ส่งข้อมูลเข้า Javascript เพื่อเปิด Modal
                            $js_data = htmlspecialchars(json_encode([
                                'id' => $row['id'],
                                'date' => thai_date_short($row['cutoff_date']),
                                'doc' => $row['ref_document'],
                                'code' => $row['project_code'],
                                'name' => $row['project_name'],
                                'desc' => $row['description'],
                                'amount' => $row['request_amount'],
                                'user' => $row['recorded_by']
                            ]), ENT_QUOTES, 'UTF-8');
                            
                            // ปุ่มพิจารณาตัดยอด (เปิด Modal)
                            echo "<button class='btn btn-sm btn-success shadow-sm px-2 me-1' title='ตรวจสอบและอนุมัติ' onclick='openApproveModal({$js_data})'><i class='fa-solid fa-check-to-slot'></i></button>";
                            
                            // ปุ่มลบ
                            echo "<a href='?delete_id=".$row['id']."' class='btn btn-sm btn-outline-danger shadow-sm px-2' title='ลบรายการนี้' onclick=\"return confirm('ยืนยันการลบคำขอตัดยอดงบประมาณนี้ใช่หรือไม่?');\"><i class='fa-solid fa-trash-can'></i></a>";
                            
                            echo "</td>";
                            echo "</tr>";
                        }

                        // แถวสรุปยอดรวม
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='5' class='text-end py-3'><strong>รวมยอดขอเบิกที่รออนุมัติ :</strong></td>";
                        echo "<td class='text-end py-3 text-danger fs-5'><strong>" . number_format($total_req_amount, 2) . "</strong></td>";
                        echo "<td colspan='2'></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>";
                        echo "<i class='fa-regular fa-circle-check fs-1 mb-3 d-block text-success'></i>ไม่มีรายการรออนุมัติในขณะนี้</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST">
                <input type="hidden" name="request_id" id="modal_request_id">
                
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
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">วันที่ขอเบิก</label>
                            <div class="readonly-data" id="disp_date"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">เลขที่เอกสาร/ฎีกาอ้างอิง</label>
                            <div class="readonly-data" id="disp_doc"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">โครงการที่ขอเบิก</label>
                            <div class="readonly-data">
                                <span class="text-primary fw-bold" id="disp_code"></span> - <span id="disp_name"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">รายการ/รายละเอียดการเบิกจ่าย</label>
                            <div class="readonly-data" id="disp_desc" style="min-height: 60px;"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">ผู้บันทึกคำขอ</label>
                            <div class="readonly-data" id="disp_user"></div>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="row align-items-center bg-light p-3 rounded mt-2 border border-danger border-opacity-25">
                        <div class="col-md-7 text-end">
                            <h5 class="fw-bold text-dark mb-0">จำนวนเงินที่ขอเบิกเพื่อตัดยอด :</h5>
                        </div>
                        <div class="col-md-5 text-end">
                            <h3 class="fw-bold text-danger mb-0" id="disp_amount">0.00 ฿</h3>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 d-flex justify-content-center py-3">
                    <button type="button" class="btn btn-secondary px-4 me-auto" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                    
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger px-4 fw-bold" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการ ปฏิเสธ และลบคำขอนี้?');">
                        <i class="fa-solid fa-xmark me-1"></i> ไม่อนุมัติ
                    </button>
                    
                    <button type="submit" name="action" value="approve" class="btn btn-success px-5 fw-bold shadow-sm" onclick="return confirm('ยืนยันการอนุมัติการตัดยอดนี้ใช่หรือไม่?');">
                        <i class="fa-solid fa-check me-1"></i> อนุมัติการเบิกจ่าย
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ฟังก์ชันรับข้อมูลจากตารางมาแสดงใน Modal แบบแก้ไขไม่ได้
    function openApproveModal(data) {
        document.getElementById('modal_request_id').value = data.id;
        
        document.getElementById('disp_date').innerText = data.date;
        document.getElementById('disp_doc').innerText = data.doc ? data.doc : 'ไม่มีระบุ';
        document.getElementById('disp_code').innerText = data.code ? '[' + data.code + ']' : '';
        document.getElementById('disp_name').innerText = data.name;
        document.getElementById('disp_desc').innerText = data.desc;
        document.getElementById('disp_user').innerText = data.user;
        
        // จัดรูปแบบตัวเลขจำนวนเงิน
        let amt = parseFloat(data.amount).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('disp_amount').innerText = amt + " บาท";
        
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }
</script>