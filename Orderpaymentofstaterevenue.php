<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน';

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ให้ฐานข้อมูลอัตโนมัติ (รองรับฟอร์มใหม่ตามรูป) ---
$columns_to_check = [
    'money_type' => 'VARCHAR(255) NULL',
    'transaction_type' => 'VARCHAR(255) NULL',
    'payee' => 'VARCHAR(255) NULL'
];

foreach ($columns_to_check as $col => $type) {
    $check_col = $conn->query("SHOW COLUMNS FROM state_revenue_expenditures LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE state_revenue_expenditures ADD $col $type");
    }
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล (ลบข้อมูลที่เชื่อมโยงด้วย)
if (isset($_GET['delete_id'])) {
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
    $doc_no = $_POST['doc_no'] ?? '';
    $money_type = $_POST['money_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $transaction_type = $_POST['transaction_type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $payee = $_POST['payee'] ?? '';
    
    // กำหนดวันที่ และลำดับอัตโนมัติ (ไม่ให้โชว์ในฟอร์ม)
    $doc_date = date('Y-m-d');
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        // หาระดับลำดับถัดไปอัตโนมัติ
        $sql_max = "SELECT MAX(exp_order) as max_order FROM state_revenue_expenditures WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $exp_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        // [ส่วนที่ 1] บันทึกลงตารางหลัก
        $stmt = $conn->prepare("INSERT INTO state_revenue_expenditures (budget_year, exp_order, doc_date, doc_no, money_type, description, transaction_type, amount, payee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssds", $active_year, $exp_order, $doc_date, $doc_no, $money_type, $description, $transaction_type, $amount, $payee);
        $stmt->execute();

        // [ส่วนที่ 2] ส่งข้อมูลไปที่หน้า Approvedformaintypepayment.php
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

    } elseif ($action == 'edit') {
        $id = $_POST['edit_id'];

        $stmt_old = $conn->prepare("SELECT exp_order, budget_year FROM state_revenue_expenditures WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $old_exp_order = $old_data['exp_order'];
        $current_budget_year = $old_data['budget_year'];

        $stmt = $conn->prepare("UPDATE state_revenue_expenditures SET doc_no=?, money_type=?, description=?, transaction_type=?, amount=?, payee=? WHERE id=?");
        $stmt->bind_param("ssssdsi", $doc_no, $money_type, $description, $transaction_type, $amount, $payee, $id);
        
        if ($stmt->execute()) {
            // อัปเดตข้อมูลในหน้าอนุมัติจ่ายให้ตรงกัน
            $stmt_update_link = $conn->prepare("UPDATE approved_main_payments SET doc_no=?, description=?, amount=? WHERE pay_order=? AND budget_year=? AND payment_type='เงินรายได้แผ่นดิน'");
            $stmt_update_link->bind_param("ssdii", $doc_no, $description, $amount, $old_exp_order, $current_budget_year);
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
        <h2 class="page-title">ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?></h2>
        <div class="d-flex align-items-center mb-2">
            <button class="btn btn-add" onclick="openAddModal()">เพิ่มรายการสั่งจ่าย</button>
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
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='openEditModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-pen'></i></button></td>";
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
            <div class="modal-header d-block pb-2">
                <h5 class="modal-title-custom text-teal" id="modalTitle">เพิ่มข้อมูลสั่งจ่ายเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-3 mb-3 pt-0">
                <div class="form-white-bg border-0 p-0 mt-2">
                    <form action="Orderpaymentofstaterevenue.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2 align-items-center mt-3">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ประเภทของเงิน</div>
                            <div class="col-md-5">
                                <select name="money_type" id="money_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="340 รายได้เบ็ดเตล็ดอื่น">340 รายได้เบ็ดเตล็ดอื่น</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">รายการจ่าย</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ลักษณะรายการ</div>
                            <div class="col-md-4">
                                <select name="transaction_type" id="transaction_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="เงินสด">เงินสด</option>
                                    <option value="โอนเงิน">โอนเงิน</option>
                                    <option value="เช็ค">เช็ค</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">ผู้รับเงิน</div>
                            <div class="col-md-6">
                                <input type="text" name="payee" id="payee" class="form-control form-control-sm">
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
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการสั่งจ่าย</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">วันที่</th><td id="view_doc_date"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ที่เอกสาร</th><td id="view_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ประเภทของเงิน</th><td id="view_money_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการจ่าย</th><td id="view_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ลักษณะรายการ</th><td id="view_transaction_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงิน</th><td id="view_amount" class="text-danger fw-bold fs-5"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผู้รับเงิน</th><td id="view_payee"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal"><< กลับหน้าก่อน</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function confirmDelete(id) {
        if (confirm('ยืนยันการลบรายการ? ข้อมูลในหน้าอนุมัติจ่ายจะถูกลบด้วย')) { window.location.href = '?delete_id=' + id; }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'เพิ่มข้อมูลสั่งจ่ายเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไขข้อมูลสั่งจ่ายเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?>';
        
        document.getElementById('doc_no').value = data.doc_no || '';
        document.getElementById('money_type').value = data.money_type || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('transaction_type').value = data.transaction_type || '';
        document.getElementById('amount').value = data.amount || '';
        document.getElementById('payee').value = data.payee || '';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_doc_date').innerText = data.doc_date || '-';
        document.getElementById('view_doc_no').innerText = data.doc_no || '-';
        document.getElementById('view_money_type').innerText = data.money_type || '-';
        document.getElementById('view_description').innerText = data.description || '-';
        document.getElementById('view_transaction_type').innerText = data.transaction_type || '-';
        document.getElementById('view_amount').innerText = parseFloat(data.amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_payee').innerText = data.payee || '-';

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>