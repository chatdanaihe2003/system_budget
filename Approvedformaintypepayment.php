<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "อนุมัติจ่ายเงิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'อนุมัติจ่ายเงิน';

// --- กำหนดกลุ่มสิทธิ์ที่มีอำนาจจัดการ (เฉพาะ admin และ ADMIN3) ---
$authorized_roles = ['admin', 'ADMIN3'];

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    // ตรวจสอบสิทธิ์ก่อนลบ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จะแก้ไข หรืออนุมัติในหน้านี้'); window.location='Approvedformaintypepayment.php';</script>";
        exit();
    }
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM approved_main_payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Approvedformaintypepayment.php");
    exit();
}

// 2. เปลี่ยนสถานะ (เลือกสถานะ เขียว/เหลือง/แดง)
if (isset($_GET['toggle_status_id']) && isset($_GET['set_status'])) {
    // ตรวจสอบสิทธิ์ก่อนเปลี่ยนสถานะอนุมัติ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จะแก้ไข หรืออนุมัติในหน้านี้'); window.location='Approvedformaintypepayment.php';</script>";
        exit();
    }
    $id = $_GET['toggle_status_id'];
    $new_status = $_GET['set_status']; // ค่าที่ส่งมาคือ pending, approved, หรือ rejected
    
    // อัปเดตสถานะในตารางหลัก (approved_main_payments)
    $stmt = $conn->prepare("UPDATE approved_main_payments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();

    // =====================================================================================
    // --- ส่วนเพิ่มใหม่: จัดการส่งข้อมูลไปตัดยอดที่หน้าทะเบียนรับเงินต่างๆ เมื่ออนุมัติ ---
    // =====================================================================================
    
    // ดึงข้อมูลรายการที่เพิ่งเปลี่ยนสถานะ
    $stmt_get = $conn->prepare("SELECT * FROM approved_main_payments WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();
    
    if ($res_get->num_rows > 0) {
        $row_data = $res_get->fetch_assoc();
        
        $b_year = $row_data['budget_year'];
        $d_date = $row_data['doc_date'];
        $d_no = $row_data['doc_no'];
        $desc_deduct = "(ตัดจ่าย) " . $row_data['description']; // เติมคำว่าตัดจ่าย
        $neg_amount = $row_data['amount'] * -1; // หักยอดต้องเป็นค่าติดลบ
        $t_type = "โอนเงิน"; 

        // เลือกตารางปลายทาง ตามประเภทเงิน
        $target_table = "";
        if ($row_data['payment_type'] == 'เงินงบประมาณ') {
            $target_table = 'receive_budget';
        } elseif ($row_data['payment_type'] == 'เงินนอกงบประมาณ') {
            $target_table = 'receive_off_budget';
        } elseif ($row_data['payment_type'] == 'เงินรายได้แผ่นดิน') {
            $target_table = 'receive_national';
        }

        // หากมีตารางปลายทางตรงตามเงื่อนไข ให้ไปทำรายการ
        if ($target_table != "") {
            if ($new_status == 'approved') {
                // หากเปลี่ยนเป็น "สีเขียว" -> นำข้อมูลไปเพิ่มในตารางรับเงินที่เกี่ยวข้อง
                // เช็คก่อนว่ามีข้อมูลถูกตัดไปหรือยัง จะได้ไม่ซ้ำซ้อน
                $stmt_check = $conn->prepare("SELECT id FROM $target_table WHERE description = ? AND budget_year = ? AND doc_no = ?");
                $stmt_check->bind_param("sis", $desc_deduct, $b_year, $d_no);
                $stmt_check->execute();
                
                if ($stmt_check->get_result()->num_rows == 0) {
                    // หาเลขที่ใบงวดถัดไปในตารางเป้าหมาย
                    $stmt_max = $conn->prepare("SELECT MAX(receive_order) as m_order FROM $target_table WHERE budget_year = ?");
                    $stmt_max->bind_param("i", $b_year);
                    $stmt_max->execute();
                    $r_max = $stmt_max->get_result()->fetch_assoc();
                    $rec_order = ($r_max['m_order'] ?? 0) + 1;

                    // บันทึกข้อมูลลงฐานข้อมูลเป้าหมาย
                    $stmt_ins = $conn->prepare("INSERT INTO $target_table (budget_year, receive_order, doc_date, doc_no, description, transaction_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt_ins) {
                        $stmt_ins->bind_param("iissssd", $b_year, $rec_order, $d_date, $d_no, $desc_deduct, $t_type, $neg_amount);
                        $stmt_ins->execute();
                    }
                }
            } else {
                // หากเปลี่ยนกลับเป็น "สีเหลือง" หรือ "สีแดง" -> ลบข้อมูลออกจากตารางรับเงินที่เกี่ยวข้อง
                $stmt_del = $conn->prepare("DELETE FROM $target_table WHERE description = ? AND budget_year = ? AND doc_no = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("sis", $desc_deduct, $b_year, $d_no);
                    $stmt_del->execute();
                }
            }
        }
    }
    // =====================================================================================

    header("Location: Approvedformaintypepayment.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบสิทธิ์ก่อนบันทึกหรือแก้ไข
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จะแก้ไข หรืออนุมัติในหน้านี้'); window.location='Approvedformaintypepayment.php';</script>";
        exit();
    }
    $pay_order = $_POST['pay_order'];
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $ref_withdraw_no = $_POST['ref_withdraw_no'];
    $ref_petition_no = $_POST['ref_petition_no'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $payment_type = $_POST['payment_type'];
    $status = $_POST['status'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO approved_main_payments (budget_year, pay_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, description, amount, payment_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssdss", $active_year, $pay_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $payment_type, $status);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE approved_main_payments SET pay_order=?, doc_date=?, doc_no=?, ref_withdraw_no=?, ref_petition_no=?, description=?, amount=?, payment_type=?, status=? WHERE id=?");
        $stmt->bind_param("isssssdssi", $pay_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $payment_type, $status, $id);
        $stmt->execute();
    }
    header("Location: Approvedformaintypepayment.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM approved_main_payments WHERE budget_year = ? ORDER BY pay_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; cursor: pointer; border: 1px solid #ccc; }
    .status-yellow { background-color: #ffff00; }
    .status-green { background-color: #00ff00; }
    .status-red { background-color: #ff0000; }
    .legend-container { margin-top: 20px; font-size: 0.85rem; }
    .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
    .legend-box { width: 14px; height: 14px; margin-right: 8px; border: 1px solid #ccc; }
    /* เปลี่ยนสีพื้นหลังเป็นขาวสะอาดเหมือนหน้าสั่งจ่าย */
    .form-white-bg { background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #495057; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">อนุมัติจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?></h2>

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
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">ที่เอกสาร</th>
                        <th style="width: 8%;">อ้างอิงขอเบิก</th>
                        <th style="width: 8%;">อ้างอิงฎีกา</th>
                        <th style="width: 30%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 10%;">ประเภทเงิน</th>
                        <th style="width: 5%;">ราย<br>ละเอียด</th>
                        <th style="width: 5%;">อนุมัติ</th>
                        <th style="width: 5%;">แก้ไข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $status_class = 'status-yellow';
                            $status_text = 'รอการอนุมัติ';
                            if($row['status'] == 'approved') { $status_class = 'status-green'; $status_text = 'อนุมัติให้จ่ายเงินได้'; }
                            elseif($row['status'] == 'rejected') { $status_class = 'status-red'; $status_text = 'ไม่อนุมัติ'; }

                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['pay_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-center text-warning'>" . htmlspecialchars($row['ref_withdraw_no'] ?: '') . "</td>"; 
                            echo "<td class='td-center text-danger'>" . htmlspecialchars($row['ref_petition_no'] ?: '') . "</td>"; 
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['payment_type']) . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='checkAdminToggle(".$row['id'].", \"".$row['status']."\")' title='".$status_text."'><div class='status-box ".$status_class."'></div></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='checkAdminAction(\"edit\", ".json_encode($row).")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="legend-container text-center">
            <div class="legend-item justify-content-center"><div class="legend-box status-yellow"></div> <span>รอการอนุมัติ</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-green"></div> <span>อนุมัติให้จ่ายเงินได้</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-red"></div> <span>ไม่อนุมัติ</span></div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block"><h5 class="modal-title-custom" id="modalTitle">ลงทะเบียนอนุมัติจ่าย</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <form action="Approvedformaintypepayment.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่</div><div class="col-md-3"><input type="number" name="pay_order" id="pay_order" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">วดป</div><div class="col-md-4"><input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่เอกสาร</div><div class="col-md-4"><input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">อ้างอิงขอเบิก</div><div class="col-md-4"><input type="text" name="ref_withdraw_no" id="ref_withdraw_no" class="form-control form-control-sm"></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">อ้างอิงฎีกา</div><div class="col-md-4"><input type="text" name="ref_petition_no" id="ref_petition_no" class="form-control form-control-sm"></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">รายการ</div><div class="col-md-9"><input type="text" name="description" id="description" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">จำนวนเงิน</div><div class="col-md-4"><input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ประเภทเงิน</div><div class="col-md-4">
                            <select name="payment_type" id="payment_type" class="form-select form-select-sm">
                                <option value="เงินงบประมาณ">เงินงบประมาณ</option>
                                <option value="เงินนอกงบประมาณ">เงินนอกงบประมาณ</option>
                                <option value="เงินรายได้แผ่นดิน">เงินรายได้แผ่นดิน</option>
                            </select>
                        </div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">สถานะ</div><div class="col-md-4">
                            <select name="status" id="status" class="form-select form-select-sm">
                                <option value="pending">รอการอนุมัติ</option>
                                <option value="approved">อนุมัติ</option>
                                <option value="rejected">ไม่อนุมัติ</option>
                            </select>
                        </div></div>
                        <div class="text-center mt-4 pt-3 border-top"><button type="submit" class="btn btn-primary px-4 me-2">ตกลง</button><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button></div>
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
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">ประเภท :</div><div class="col-md-9" id="view_payment_type"></div></div>
                    <div class="text-center mt-3 pt-3 border-top"><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom mb-0" style="font-size: 1.1rem;">เลือกสถานะการอนุมัติ</h5>
            </div>
            <div class="modal-body text-center mx-2 mb-2">
                <button class="btn w-100 mb-2" style="background-color: #00ff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('approved')">
                    อนุมัติให้จ่ายเงินได้ (สีเขียว)
                </button>
                <button class="btn w-100 mb-2" style="background-color: #ffff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('pending')">
                    รอการอนุมัติ (สีเหลือง)
                </button>
                <button class="btn w-100" style="background-color: #ff0000; color: #fff; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('rejected')">
                    ไม่อนุมัติ (สีแดง)
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const userRole = '<?php echo $_SESSION['role']; ?>';
    const authorizedRoles = ['admin', 'ADMIN3'];
    let currentStatusId = null; // ตัวแปรเก็บ ID ที่กำลังจะเปลี่ยนสถานะ

    function checkAdminAction(action, data = null) {
        if (!authorizedRoles.includes(userRole)) {
            alert('คุณไม่มีสิทธิ์จะแก้ไข หรืออนุมัติในหน้านี้');
            return;
        }
        if (action === 'add') openAddModal();
        else openEditModal(data);
    }

    // ฟังก์ชันเมื่อคลิกที่กล่องสถานะ
    function checkAdminToggle(id, currentStatus) {
        if (!authorizedRoles.includes(userRole)) {
            alert('คุณไม่มีสิทธิ์จะแก้ไข หรืออนุมัติในหน้านี้');
            return;
        }
        currentStatusId = id; // เก็บ ID ไว้ใช้ตอนเลือกสี
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    // ฟังก์ชันส่งค่าสถานะที่เลือกกลับไปที่ PHP
    function submitSetStatus(newStatus) {
        if (currentStatusId !== null) {
            window.location.href = `?toggle_status_id=${currentStatusId}&set_status=${newStatus}`;
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
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('ref_withdraw_no').value = data.ref_withdraw_no;
        document.getElementById('ref_petition_no').value = data.ref_petition_no;
        document.getElementById('description').value = data.description;
        document.getElementById('amount').value = data.amount;
        document.getElementById('payment_type').value = data.payment_type;
        document.getElementById('status').value = data.status;
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_payment_type').innerText = data.payment_type;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>