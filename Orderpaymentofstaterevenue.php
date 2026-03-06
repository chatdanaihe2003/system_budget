<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน';

// --- กำหนดกลุ่มสิทธิ์ที่มีอำนาจจัดการ (เฉพาะ admin และ ADMIN4) ---
$authorized_roles = ['admin', 'ADMIN4'];

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล (ลบข้อมูลที่เชื่อมโยงด้วย)
if (isset($_GET['delete_id'])) {
    // ตรวจสอบสิทธิ์ก่อนลบ
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์ลบข้อมูลในหน้านี้'); window.location='Orderpaymentofstaterevenue.php';</script>";
        exit();
    }

    $id = $_GET['delete_id'];

    // [Step 1] ดึงข้อมูลเดิมก่อนลบ
    $stmt_get = $conn->prepare("SELECT exp_order, budget_year FROM state_revenue_expenditures WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if ($result_get->num_rows > 0) {
        $row_del = $result_get->fetch_assoc();
        $del_exp_order = $row_del['exp_order'];
        $del_budget_year = $row_del['budget_year'];

        // [Step 2] ลบข้อมูลจากตารางหลัก
        $stmt = $conn->prepare("DELETE FROM state_revenue_expenditures WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // [Step 3] ลบข้อมูลที่เชื่อมโยงในหน้า Approvedformaintypepayment.php
            $stmt_link_del = $conn->prepare("DELETE FROM approved_main_payments WHERE pay_order = ? AND budget_year = ? AND payment_type = 'เงินรายได้แผ่นดิน'");
            $stmt_link_del->bind_param("ii", $del_exp_order, $del_budget_year);
            $stmt_link_del->execute();
        }
    }
    
    header("Location: Orderpaymentofstaterevenue.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบสิทธิ์ก่อนบันทึกหรือแก้ไข
    if (!in_array($_SESSION['role'], $authorized_roles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); window.location='Orderpaymentofstaterevenue.php';</script>";
        exit();
    }

    $exp_order = $_POST['exp_order'];
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // [ส่วนที่ 1] บันทึกลงตารางหลัก
        $stmt = $conn->prepare("INSERT INTO state_revenue_expenditures (budget_year, exp_order, doc_date, doc_no, description, amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssd", $active_year, $exp_order, $doc_date, $doc_no, $description, $amount);
        $stmt->execute();

        // [ส่วนที่ 2] ส่งข้อมูลไปที่หน้า Approvedformaintypepayment.php (ตาราง approved_main_payments)
        $payment_type_fixed = 'เงินรายได้แผ่นดิน';
        $approval_status_init = 'pending';
        $payment_status_init = 'unpaid';
        $empty_ref = ''; 

        $sql_link = "INSERT INTO approved_main_payments (budget_year, pay_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, description, amount, payment_type, approval_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_link = $conn->prepare($sql_link);
        
        if ($stmt_link) {
            $stmt_link->bind_param("iisssssdsss", $active_year, $exp_order, $doc_date, $doc_no, $empty_ref, $empty_ref, $description, $amount, $payment_type_fixed, $approval_status_init, $payment_status_init);
            $stmt_link->execute();
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];

        $stmt_old = $conn->prepare("SELECT exp_order, budget_year FROM state_revenue_expenditures WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $old_exp_order = $old_data['exp_order'];
        $current_budget_year = $old_data['budget_year'];

        $stmt = $conn->prepare("UPDATE state_revenue_expenditures SET exp_order=?, doc_date=?, doc_no=?, description=?, amount=? WHERE id=?");
        $stmt->bind_param("isssdi", $exp_order, $doc_date, $doc_no, $description, $amount, $id);
        
        if ($stmt->execute()) {
            // อัปเดตข้อมูลในหน้าอนุมัติจ่ายให้ตรงกัน
            $stmt_update_link = $conn->prepare("UPDATE approved_main_payments SET pay_order=?, doc_date=?, doc_no=?, description=?, amount=? WHERE pay_order=? AND budget_year=? AND payment_type='เงินรายได้แผ่นดิน'");
            $stmt_update_link->bind_param("isssdii", $exp_order, $doc_date, $doc_no, $description, $amount, $old_exp_order, $current_budget_year);
            $stmt_update_link->execute();
        }
    }
    header("Location: Orderpaymentofstaterevenue.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM state_revenue_expenditures WHERE budget_year = ? ORDER BY exp_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_amount = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .total-text { color: #d63384; font-weight: bold; font-size: 0.8rem;}
    .form-white-bg { background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #495057; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?></h2>
        <div class="d-flex align-items-center mb-2">
            <button class="btn btn-add" onclick="checkPermissionAndOpenModal('add')">เพิ่มรายการสั่งจ่าย</button>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 10%;">ที่เอกสาร</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 5%;">ราย<br>ละเอียด</th>
                        <th style="width: 4%;">ลบ</th>
                        <th style="width: 4%;">แก้ไข</th>
                        <th style="width: 5%;">พิมพ์</th>
                        <th style="width: 5%;">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['exp_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='confirmDelete(".$row['id'].")' class='action-btn btn-delete'><i class='fa-solid fa-xmark'></i></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='checkPermissionAndOpenModal(\"edit\", ".json_encode($row).")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "<td class='td-center'><button class='action-btn btn-print' onclick='alert(\"ฟังก์ชันพิมพ์กำลังปรับปรุง\")'><i class='fa-solid fa-print'></i></button></td>";
                            echo "<td class='td-center total-text'>ถึงนี้</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'><td colspan='4' class='text-center'>รวม</td><td class='td-right'>" . number_format($total_amount, 2) . "</td><td colspan='5'></td></tr>";
                    } else {
                        echo "<tr><td colspan='10' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
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
            <div class="modal-header d-block"><h5 class="modal-title-custom" id="modalTitle">ลงทะเบียน สั่งจ่ายเงินรายได้แผ่นดิน</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <form action="Orderpaymentofstaterevenue.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่</div><div class="col-md-3"><input type="number" name="exp_order" id="exp_order" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">วดป</div><div class="col-md-4"><input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่เอกสาร</div><div class="col-md-4"><input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">รายการ</div><div class="col-md-9"><input type="text" name="description" id="description" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">จำนวนเงิน</div><div class="col-md-4"><input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required></div><div class="col-md-1 pt-1">บาท</div></div>
                        <div class="text-center mt-4 pt-3 border-top"><button type="submit" class="btn btn-primary px-4 me-2">ตกลง</button><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const userRole = '<?php echo $_SESSION['role']; ?>';
    const authorizedRoles = ['admin', 'ADMIN4'];

    function checkPermissionAndOpenModal(action, data = null) {
        if (!authorizedRoles.includes(userRole)) { alert('คุณไม่มีสิทธิ์จัดการข้อมูลในหน้านี้'); return; }
        if (action === 'add') { openAddModal(); } else { openEditModal(data); }
    }

    function confirmDelete(id) {
        if (!authorizedRoles.includes(userRole)) { alert('คุณไม่มีสิทธิ์ลบข้อมูลในหน้านี้'); return; }
        if (confirm('ยืนยันการลบรายการ? ข้อมูลในหน้าอนุมัติจ่ายจะถูกลบด้วย')) { window.location.href = '?delete_id=' + id; }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'ลงทะเบียน สั่งจ่ายเงินรายได้แผ่นดิน ปี ' + <?php echo $active_year; ?>;
        document.querySelector('#addModal form').reset();
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข สั่งจ่ายเงินรายได้แผ่นดิน';
        document.getElementById('exp_order').value = data.exp_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('description').value = data.description;
        document.getElementById('amount').value = data.amount;
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        alert("รายการ: " + data.description + "\nจำนวนเงิน: " + parseFloat(data.amount).toLocaleString('th-TH') + " บาท");
    }
</script>