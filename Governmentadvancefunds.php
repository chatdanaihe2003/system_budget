<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนเงินทดรองราชการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ทะเบียนเงินทดรองราชการ';

// --- กำหนดกลุ่มสิทธิ์ที่มีอำนาจจัดการ (เฉพาะ admin และ ADMIN3) ---
$authorized_roles = ['admin', 'ADMIN3'];

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ให้ฐานข้อมูลอัตโนมัติ (รองรับผู้ยืมเงิน) ---
$check_col = $conn->query("SHOW COLUMNS FROM government_advance_funds LIKE 'borrower'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE government_advance_funds ADD borrower VARCHAR(255) NULL");
}
$check_col2 = $conn->query("SHOW COLUMNS FROM approved_gov_advance_payments LIKE 'borrower'");
if ($check_col2 && $check_col2->num_rows == 0) {
    $conn->query("ALTER TABLE approved_gov_advance_payments ADD borrower VARCHAR(255) NULL");
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล (ลบข้อมูลที่เชื่อมโยงด้วย)
if (isset($_GET['delete_id'])) {
    // ตรวจสอบสิทธิ์ก่อนลบ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์ลบข้อมูลในหน้านี้'); window.location='Governmentadvancefunds.php';</script>";
        exit();
    }

    $id = $_GET['delete_id'];

    // [Step 1] ดึงข้อมูลเดิมก่อนลบ เพื่อนำไปลบในตารางปลายทาง
    $stmt_get = $conn->prepare("SELECT advance_order, budget_year FROM government_advance_funds WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if ($result_get->num_rows > 0) {
        $row_del = $result_get->fetch_assoc();
        $del_order = $row_del['advance_order'];
        $del_year = $row_del['budget_year'];

        // [Step 2] ลบข้อมูลจากตารางหลัก
        $stmt = $conn->prepare("DELETE FROM government_advance_funds WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // [Step 3] ลบข้อมูลที่เชื่อมโยงในตาราง approved_gov_advance_payments
            $stmt_link_del = $conn->prepare("DELETE FROM approved_gov_advance_payments WHERE advance_order = ? AND budget_year = ?");
            if ($stmt_link_del) {
                $stmt_link_del->bind_param("ii", $del_order, $del_year);
                $stmt_link_del->execute();
            }
        }
    }

    header("Location: Governmentadvancefunds.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบสิทธิ์ก่อนบันทึกหรือแก้ไข
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); window.location='Governmentadvancefunds.php';</script>";
        exit();
    }

    $doc_no = $_POST['doc_no'] ?? '';
    $ref_doc_no = $_POST['ref_doc_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $loan_amount = $_POST['loan_amount'] ?? 0;
    $return_amount = $_POST['return_amount'] ?? 0; // รับค่าเงินคืน
    $borrower = $_POST['borrower'] ?? '';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $doc_date = date('Y-m-d'); // ลงวันที่ปัจจุบันอัตโนมัติ

        // หาระดับเลขที่ลำดับถัดไปอัตโนมัติ
        $sql_max = "SELECT MAX(advance_order) as max_order FROM government_advance_funds WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $advance_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        // [ส่วนที่ 1] บันทึกลงตารางหลัก (government_advance_funds)
        $stmt = $conn->prepare("INSERT INTO government_advance_funds (budget_year, advance_order, doc_date, doc_no, ref_doc_no, description, loan_amount, return_amount, borrower) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssdds", $active_year, $advance_order, $doc_date, $doc_no, $ref_doc_no, $description, $loan_amount, $return_amount, $borrower);
        
        if ($stmt->execute()) {
            // [ส่วนที่ 2] ส่งข้อมูลไปที่หน้าอนุมัติจ่าย
            $approval_status_init = 'pending';
            $payment_status_init = 'unpaid';

            $sql_link = "INSERT INTO approved_gov_advance_payments (budget_year, advance_order, doc_date, doc_no, ref_doc_no, description, loan_amount, return_amount, approval_status, payment_status, borrower) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_link = $conn->prepare($sql_link);
            
            if ($stmt_link) {
                $stmt_link->bind_param("iissssddsss", $active_year, $advance_order, $doc_date, $doc_no, $ref_doc_no, $description, $loan_amount, $return_amount, $approval_status_init, $payment_status_init, $borrower);
                $stmt_link->execute();
            }
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];

        $stmt_old = $conn->prepare("SELECT advance_order, budget_year FROM government_advance_funds WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $old_order = $old_data['advance_order'];
        $current_budget_year = $old_data['budget_year'];

        // แก้ไขเฉพาะฟิลด์ที่มีในฟอร์ม (วันเวลาและลำดับคงเดิม) รวมถึงเงินคืน
        $stmt = $conn->prepare("UPDATE government_advance_funds SET doc_no=?, ref_doc_no=?, description=?, loan_amount=?, return_amount=?, borrower=? WHERE id=?");
        $stmt->bind_param("sssddsi", $doc_no, $ref_doc_no, $description, $loan_amount, $return_amount, $borrower, $id);
        
        if ($stmt->execute()) {
            $stmt_update_link = $conn->prepare("UPDATE approved_gov_advance_payments SET doc_no=?, ref_doc_no=?, description=?, loan_amount=?, return_amount=?, borrower=? WHERE advance_order=? AND budget_year=?");
            if ($stmt_update_link) {
                $stmt_update_link->bind_param("sssddsii", $doc_no, $ref_doc_no, $description, $loan_amount, $return_amount, $borrower, $old_order, $current_budget_year);
                $stmt_update_link->execute();
            }
        }
    }
    header("Location: Governmentadvancefunds.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM government_advance_funds WHERE budget_year = ? ORDER BY advance_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_loan = 0;
$total_return = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .total-text { color: #d63384; font-weight: bold; font-size: 0.8rem;}
    
    /* พื้นหลังแบบฟอร์มให้เป็นสีขาว */
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: none; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 6px 25px; background-color: #e9ecef; border: 1px solid #ccc; color: #333; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #d3d9df; color: #333; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">ทะเบียนเงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?></h2>
        <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-add" onclick="checkPermissionAndOpenModal('add')">
                <i class="fa-solid fa-plus me-1"></i> จ่ายเงินทดรองราชการ
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">ที่เอกสาร</th>
                        <th style="width: 8%;">ที่อ้างอิง</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">เงินยืม</th>
                        <th style="width: 10%;">เงินคืน</th>
                        <th style="width: 5%;">รายละเอียด</th>
                        <th style="width: 4%;">ลบ</th>
                        <th style="width: 4%;">แก้ไข</th>
                        <th style="width: 5%;">พิมพ์</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_loan += $row['loan_amount'];
                            $total_return += $row['return_amount'];
                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['advance_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-center text-warning'>" . ($row['ref_doc_no'] ?: '') . "</td>"; 
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['loan_amount'], 2) . "</td>";
                            echo "<td class='td-center'>" . ($row['return_amount'] > 0 ? number_format($row['return_amount'], 2) : '<i class="fa-solid fa-magnifying-glass text-primary"></i>') . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='confirmDelete(".$row['id'].")' class='action-btn btn-delete'><i class='fa-solid fa-xmark'></i></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='checkPermissionAndOpenModal(\"edit\", ".json_encode($row).")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "<td class='td-center'><button class='action-btn btn-print' onclick='alert(\"พิมพ์ใบสั่งจ่าย\")'><i class='fa-solid fa-print'></i></button></td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'><td colspan='5' class='text-center'>คงเหลือยังไม่คืน</td><td class='td-right' colspan='2' style='text-align:center !important; color:red;'>" . number_format($total_loan - $total_return, 2) . "</td><td colspan='4'></td></tr>";
                    } else {
                        echo "<tr><td colspan='11' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
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
            <div class="modal-header d-block pb-3 border-bottom">
                <h5 class="modal-title-custom" id="modalTitle">ทะเบียนเงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-4 my-3 pt-0">
                <div class="form-white-bg border-0 p-0">
                    <form action="Governmentadvancefunds.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-3 align-items-center mt-3">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">อ้างอิงทะเบียนขอเบิก/ขอยืมเงิน</div>
                            <div class="col-md-7">
                                <select name="ref_doc_no" id="ref_doc_no" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="อ้างอิงที่ 1">อ้างอิงที่ 1</option>
                                    <option value="อ้างอิงที่ 2">อ้างอิงที่ 2</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">รายการ</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="loan_amount" id="loan_amount" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1 pt-1 text-start">บาท</div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงินคืน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="return_amount" id="return_amount" class="form-control form-control-sm" value="0.00">
                            </div>
                            <div class="col-md-1 pt-1 text-start">บาท</div>
                        </div>

                        <div class="row mb-4 align-items-center">
                            <div class="col-md-4 form-label-custom">ผู้ยืมเงิน</div>
                            <div class="col-md-6">
                                <input type="text" name="borrower" id="borrower" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block"><h5 class="modal-title-custom">รายละเอียด</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <div class="row mb-2"><div class="col-md-3 form-label-custom" style="text-align: right;">ที่เอกสาร :</div><div class="col-md-9" id="view_doc_no"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom" style="text-align: right;">รายการ :</div><div class="col-md-9" id="view_description"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom" style="text-align: right;">จำนวนเงินยืม :</div><div class="col-md-9" id="view_loan_amount"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom" style="text-align: right;">จำนวนเงินคืน :</div><div class="col-md-9" id="view_return_amount"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom" style="text-align: right;">ผู้ยืมเงิน :</div><div class="col-md-9" id="view_borrower"></div></div>
                    <div class="text-center mt-3 pt-3 border-top"><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const userRole = '<?php echo $_SESSION['role']; ?>';
    const authorizedRoles = ['admin', 'ADMIN3'];

    function checkPermissionAndOpenModal(action, data = null) {
        if (!authorizedRoles.includes(userRole)) { alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); return; }
        if (action === 'add') { openAddModal(); } else { openEditModal(data); }
    }

    function confirmDelete(id) {
        if (!authorizedRoles.includes(userRole)) { alert('คุณไม่มีสิทธิ์ลบข้อมูลในหน้านี้'); return; }
        if (confirm('ยืนยันการลบรายการ? ข้อมูลในหน้าอนุมัติจะถูกลบด้วย')) { window.location.href = '?delete_id=' + id; }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'ทะเบียนเงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        document.getElementById('return_amount').value = '0.00'; // เริ่มต้นเงินคืนที่ 0
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข เงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?>';
        
        document.getElementById('doc_no').value = data.doc_no || '';
        document.getElementById('ref_doc_no').value = data.ref_doc_no || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('loan_amount').value = data.loan_amount || '';
        document.getElementById('return_amount').value = data.return_amount || '0.00';
        document.getElementById('borrower').value = data.borrower || '';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_doc_no').innerText = data.doc_no || '-';
        document.getElementById('view_description').innerText = data.description || '-';
        document.getElementById('view_loan_amount').innerText = parseFloat(data.loan_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_return_amount').innerText = parseFloat(data.return_amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_borrower').innerText = data.borrower || '-';
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>