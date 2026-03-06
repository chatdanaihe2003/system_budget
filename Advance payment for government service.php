<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "จ่ายเงินทดรองราชการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']);
// ชื่อหน้าบนแถบสีทอง
$current_page_check = 'Advance payment for government service.php'; 

$page_header = '<div class="d-flex justify-content-between">
                    <span>จ่ายเงินทดรองราชการ</span>
                    <span>ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>
                </div>';

// --- กำหนดกลุ่มสิทธิ์ที่มีอำนาจจัดการ (เฉพาะ admin และ ADMIN3) ---
$authorized_roles = ['admin', 'ADMIN3'];

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    // ตรวจสอบสิทธิ์ก่อนลบ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); window.location='Advance payment for government service.php';</script>";
        exit();
    }
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM advance_service_payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Advance payment for government service.php");
    exit();
}

// 2. เปลี่ยนสถานะ "อนุมัติ" (เลือกสถานะ เขียว/เหลือง/แดง)
if (isset($_GET['toggle_approval_id']) && isset($_GET['set_status'])) {
    // ตรวจสอบสิทธิ์ก่อนอนุมัติ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์อนุมัติข้อมูลในหน้านี้'); window.location='Advance payment for government service.php';</script>";
        exit();
    }
    $id = $_GET['toggle_approval_id'];
    $new_status = $_GET['set_status']; // รับค่าสถานะที่ส่งมาจาก Modal (pending, approved, rejected)
    
    $stmt = $conn->prepare("UPDATE advance_service_payments SET approval_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: Advance payment for government service.php");
    exit();
}

// 2.1 เปลี่ยนสถานะ "จ่ายเงิน" (เลือกสถานะ เขียว/เหลือง/แดง)
if (isset($_GET['toggle_payment_id']) && isset($_GET['set_payment_status'])) {
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); window.location='Advance payment for government service.php';</script>";
        exit();
    }
    $id = $_GET['toggle_payment_id'];
    $new_status = $_GET['set_payment_status']; // paid, pending, unpaid
    
    $stmt = $conn->prepare("UPDATE advance_service_payments SET payment_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: Advance payment for government service.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบสิทธิ์ก่อนบันทึกหรือแก้ไข
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); window.location='Advance payment for government service.php';</script>";
        exit();
    }
    $pay_order = $_POST['pay_order'];
    $doc_date = $_POST['doc_date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    
    $approval_status = $_POST['approval_status']; 
    $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : 'unpaid';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO advance_service_payments (budget_year, pay_order, doc_date, description, amount, approval_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdss", $active_year, $pay_order, $doc_date, $description, $amount, $approval_status, $payment_status);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE advance_service_payments SET pay_order=?, doc_date=?, description=?, amount=?, approval_status=?, payment_status=? WHERE id=?");
        $stmt->bind_param("issdssi", $pay_order, $doc_date, $description, $amount, $approval_status, $payment_status, $id);
        $stmt->execute();
    }
    header("Location: Advance payment for government service.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM advance_service_payments WHERE budget_year = ? ORDER BY pay_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; cursor: pointer; border: 1px solid #ccc; transition: transform 0.1s;}
    .status-box:hover { transform: scale(1.2); }
    .status-yellow { background-color: #ffff00; }
    .status-green { background-color: #00ff00; }
    .status-red { background-color: #ff0000; }
    .legend-container { margin-top: 20px; font-size: 0.85rem; }
    .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
    .legend-box { width: 14px; height: 14px; margin-right: 8px; border: 1px solid #ccc; }
    /* เปลี่ยนสีพื้นหลังเป็นขาวสะอาด */
    .form-white-bg { background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #495057; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">จ่ายเงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-add" onclick="checkAdminAction('add')">
                + เพิ่มรายการ
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 5%;">รายละเอียด</th>
                        <th style="width: 5%;">อนุมัติ</th>
                        <th style="width: 5%;">จ่ายเงิน</th>
                        <th style="width: 5%;">บันทึก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $app_class = 'status-yellow';
                            $app_text = 'รอการอนุมัติ';
                            if($row['approval_status'] == 'approved') { $app_class = 'status-green'; $app_text = 'อนุมัติแล้ว'; }
                            elseif($row['approval_status'] == 'rejected') { $app_class = 'status-red'; $app_text = 'ไม่อนุมัติ'; }

                            $pay_class = 'status-red';
                            $pay_text = 'ยังไม่ได้จ่ายเงิน';
                            if($row['payment_status'] == 'paid') { $pay_class = 'status-green'; $pay_text = 'จ่ายเงินแล้ว'; }
                            elseif($row['payment_status'] == 'pending') { $pay_class = 'status-yellow'; $pay_text = 'รอจ่ายเงิน'; }

                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['pay_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            
                            // เปลี่ยนเป็นเรียกฟังก์ชันเปิด Modal เลือกสีสถานะ
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='checkAdminToggle(".$row['id'].")' title='".$app_text."'><div class='status-box ".$app_class."'></div></a></td>";
                            
                            // เปลี่ยนสถานะจ่ายเงินให้เรียกฟังก์ชันเปิด Modal 
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='checkAdminPaymentToggle(".$row['id'].")' title='".$pay_text."'><div class='status-box ".$pay_class."'></div></a></td>";
                            
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='checkAdminAction(\"edit\", ".json_encode($row).")' title='บันทึกการจ่าย'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="legend-container text-center">
            <div class="legend-item justify-content-center"><div class="legend-box status-yellow"></div> <span>รอการอนุมัติ / รอจ่ายเงิน</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-green"></div> <span>อนุมัติให้จ่ายเงินได้ / จ่ายเงินแล้ว</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-red"></div> <span>ไม่อนุมัติ / ยังไม่ได้จ่ายเงิน</span></div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block"><h5 class="modal-title-custom" id="modalTitle">บันทึกการจ่ายเงิน</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <form action="Advance payment for government service.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่</div><div class="col-md-3"><input type="number" name="pay_order" id="pay_order" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">วดป</div><div class="col-md-4"><input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">รายการ</div><div class="col-md-9"><input type="text" name="description" id="description" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">จำนวนเงิน</div><div class="col-md-4"><input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">สถานะอนุมัติ</div><div class="col-md-4">
                            <select name="approval_status" id="approval_status" class="form-select form-select-sm">
                                <option value="pending">รอการอนุมัติ</option>
                                <option value="approved">อนุมัติแล้ว</option>
                                <option value="rejected">ไม่อนุมัติ</option>
                            </select>
                        </div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">สถานะการจ่ายเงิน</div><div class="col-md-9">
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="payment_status" id="pay_unpaid" value="unpaid" checked><label class="form-check-label text-danger fw-bold" for="pay_unpaid">ยังไม่ได้จ่าย</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="payment_status" id="pay_pending" value="pending"><label class="form-check-label text-warning fw-bold" for="pay_pending">รอจ่ายเงิน</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="payment_status" id="pay_paid" value="paid"><label class="form-check-label text-success fw-bold" for="pay_paid">จ่ายเงินแล้ว</label></div>
                        </div></div>
                        <div class="text-center mt-4 pt-3 border-top"><button type="submit" class="btn btn-primary px-4 me-2">บันทึก</button><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button></div>
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
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">รายการ :</div><div class="col-md-9" id="view_description"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">จำนวนเงิน :</div><div class="col-md-9" id="view_amount"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">สถานะ :</div><div class="col-md-9" id="view_status"></div></div>
                    <div class="text-center mt-3 pt-3 border-top"><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approvalStatusModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom mb-0" style="font-size: 1.1rem;">เลือกสถานะการอนุมัติ</h5>
            </div>
            <div class="modal-body text-center mx-2 mb-2">
                <button class="btn w-100 mb-2" style="background-color: #00ff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitApprovalStatus('approved')">อนุมัติแล้ว (สีเขียว)</button>
                <button class="btn w-100 mb-2" style="background-color: #ffff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitApprovalStatus('pending')">รอการอนุมัติ (สีเหลือง)</button>
                <button class="btn w-100" style="background-color: #ff0000; color: #fff; border: 1px solid #aaa; font-weight: bold;" onclick="submitApprovalStatus('rejected')">ไม่อนุมัติ (สีแดง)</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentStatusModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom mb-0" style="font-size: 1.1rem;">เลือกสถานะการจ่ายเงิน</h5>
            </div>
            <div class="modal-body text-center mx-2 mb-2">
                <button class="btn w-100 mb-2" style="background-color: #00ff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitPaymentStatus('paid')">จ่ายเงินแล้ว (สีเขียว)</button>
                <button class="btn w-100 mb-2" style="background-color: #ffff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitPaymentStatus('pending')">รอจ่ายเงิน (สีเหลือง)</button>
                <button class="btn w-100" style="background-color: #ff0000; color: #fff; border: 1px solid #aaa; font-weight: bold;" onclick="submitPaymentStatus('unpaid')">ยังไม่ได้จ่าย (สีแดง)</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const userRole = '<?php echo $_SESSION['role']; ?>';
    const authRoles = ['admin', 'ADMIN3'];
    let currentApprovalId = null; // เก็บ ID ปัจจุบันที่กำลังเปลี่ยนสถานะอนุมัติ
    let currentPaymentId = null; // เก็บ ID ปัจจุบันที่กำลังเปลี่ยนสถานะจ่ายเงิน

    function checkAdminAction(action, data = null) {
        if (!authRoles.includes(userRole)) {
            alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้');
            return;
        }
        if (action === 'add') openAddModal();
        else openEditModal(data);
    }

    // ฟังก์ชันจัดการคลิกกล่องสี อนุมัติ
    function checkAdminToggle(id) {
        if (!authRoles.includes(userRole)) {
            alert('คุณไม่มีสิทธิ์อนุมัติข้อมูลในหน้านี้');
            return;
        }
        currentApprovalId = id;
        new bootstrap.Modal(document.getElementById('approvalStatusModal')).show();
    }

    // ฟังก์ชันส่งค่าสถานะอนุมัติกลับไปประมวลผล
    function submitApprovalStatus(status) {
        if (currentApprovalId !== null) {
            window.location.href = `?toggle_approval_id=${currentApprovalId}&set_status=${status}`;
        }
    }

    // ฟังก์ชันจัดการคลิกกล่องสี จ่ายเงิน
    function checkAdminPaymentToggle(id) {
        if (!authRoles.includes(userRole)) {
            alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้');
            return;
        }
        currentPaymentId = id;
        new bootstrap.Modal(document.getElementById('paymentStatusModal')).show();
    }

    // ฟังก์ชันส่งค่าสถานะจ่ายเงินกลับไปประมวลผล
    function submitPaymentStatus(status) {
        if (currentPaymentId !== null) {
            window.location.href = `?toggle_payment_id=${currentPaymentId}&set_payment_status=${status}`;
        }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('pay_order').value = data.pay_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('description').value = data.description;
        document.getElementById('amount').value = data.amount;
        document.getElementById('approval_status').value = data.approval_status;
        
        if(data.payment_status == 'paid') document.getElementById('pay_paid').checked = true;
        else if(data.payment_status == 'pending') document.getElementById('pay_pending').checked = true;
        else document.getElementById('pay_unpaid').checked = true;
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        let statusText = 'รอการอนุมัติ';
        if(data.approval_status == 'approved') statusText = 'อนุมัติแล้ว';
        else if(data.approval_status == 'rejected') statusText = 'ไม่อนุมัติ';
        document.getElementById('view_status').innerText = statusText;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>