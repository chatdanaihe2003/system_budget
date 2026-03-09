<?php
require_once 'includes/db.php'; 

$page_title = "ทะเบียนสั่งจ่ายเงินนอกงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนสั่งจ่ายเงินนอกงบประมาณ';

$columns_to_check = [
    'off_budget_type' => 'VARCHAR(255) NULL',
    'expense_type' => 'VARCHAR(255) NULL',
    'payee' => 'VARCHAR(255) NULL'
];

foreach ($columns_to_check as $col => $type) {
    $check_col = $conn->query("SHOW COLUMNS FROM off_budget_expenditures LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE off_budget_expenditures ADD $col $type");
    }
}

if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt_get = $conn->prepare("SELECT exp_order, budget_year FROM off_budget_expenditures WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    
    if ($result_get->num_rows > 0) {
        $row_del = $result_get->fetch_assoc();
        $del_exp_order = $row_del['exp_order'];
        $del_budget_year = $row_del['budget_year'];

        $stmt = $conn->prepare("DELETE FROM off_budget_expenditures WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt_link_del = $conn->prepare("DELETE FROM approved_main_payments WHERE pay_order = ? AND budget_year = ? AND payment_type = 'เงินนอกงบประมาณ'");
            $stmt_link_del->bind_param("ii", $del_exp_order, $del_budget_year);
            $stmt_link_del->execute();
        }
    }
    header("Location: Orderpaymentoutsidethebudget.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_no = isset($_POST['doc_no']) ? $_POST['doc_no'] : '';
    $ref_withdraw_no = isset($_POST['ref_withdraw_no']) ? $_POST['ref_withdraw_no'] : '';
    $ref_petition_no = isset($_POST['ref_petition_no']) ? $_POST['ref_petition_no'] : '';
    $off_budget_type = isset($_POST['off_budget_type']) ? $_POST['off_budget_type'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $expense_type = isset($_POST['expense_type']) ? $_POST['expense_type'] : '';
    $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
    $payee = isset($_POST['payee']) ? $_POST['payee'] : '';
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'add') {
        $doc_date = date('Y-m-d');
        
        $sql_max = "SELECT MAX(exp_order) as max_order FROM off_budget_expenditures WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $exp_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO off_budget_expenditures (budget_year, exp_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, off_budget_type, description, expense_type, amount, payee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisssssssds", $active_year, $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $off_budget_type, $description, $expense_type, $amount, $payee);
            $stmt->execute();
        }

        $payment_type_fixed = 'เงินนอกงบประมาณ'; 
        $approval_status_init = 'pending';    
        $payment_status_init = 'unpaid';      

        $sql_link = "INSERT INTO approved_main_payments (budget_year, pay_order, doc_date, doc_no, ref_withdraw_no, ref_petition_no, description, amount, payment_type, approval_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_link = $conn->prepare($sql_link);
        if ($stmt_link) {
            $stmt_link->bind_param("iisssssdsss", $active_year, $exp_order, $doc_date, $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $payment_type_fixed, $approval_status_init, $payment_status_init);
            $stmt_link->execute();
        }

    } elseif ($action == 'edit') {
        $id = $_POST['edit_id'];
        $stmt_old = $conn->prepare("SELECT exp_order, budget_year FROM off_budget_expenditures WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $old_exp_order = $old_data['exp_order'];
        $current_budget_year = $old_data['budget_year'];

        $stmt = $conn->prepare("UPDATE off_budget_expenditures SET doc_no=?, ref_withdraw_no=?, ref_petition_no=?, off_budget_type=?, description=?, expense_type=?, amount=?, payee=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssssdsi", $doc_no, $ref_withdraw_no, $ref_petition_no, $off_budget_type, $description, $expense_type, $amount, $payee, $id);
            if ($stmt->execute()) {
                $stmt_update_link = $conn->prepare("UPDATE approved_main_payments SET doc_no=?, ref_withdraw_no=?, ref_petition_no=?, description=?, amount=? WHERE pay_order=? AND budget_year=? AND payment_type='เงินนอกงบประมาณ'");
                if ($stmt_update_link) {
                    $stmt_update_link->bind_param("ssssdii", $doc_no, $ref_withdraw_no, $ref_petition_no, $description, $amount, $old_exp_order, $current_budget_year);
                    $stmt_update_link->execute();
                }
            }
        }
    }
    header("Location: Orderpaymentoutsidethebudget.php");
    exit();
}

$sql_data = "SELECT * FROM off_budget_expenditures WHERE budget_year = ? ORDER BY exp_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$total_amount = 0;

// --- [ปรับปรุงใหม่] ค้นหาและดึงข้อมูลจากตาราง Expensesbudget เพื่อมาแสดงในช่อง ประเภทรายการจ่าย ---
$expense_type_options = "";
$exp_table = "";

// 1. ค้นหาตารางที่น่าจะเป็น Expensesbudget
$possible_exp_tables = ['expensesbudget', 'expense_budget', 'expenses_budget', 'expensebudget', 'expenses', 'expense_types', 'expense_type', 'type_expense'];
foreach ($possible_exp_tables as $ptable) {
    $check_exists = $conn->query("SHOW TABLES LIKE '$ptable'");
    if ($check_exists && $check_exists->num_rows > 0) {
        $exp_table = $ptable;
        break; 
    }
}

if ($exp_table === "") {
    $tb_check_exp = $conn->query("SHOW TABLES");
    if ($tb_check_exp) {
        while ($tb_row = $tb_check_exp->fetch_array()) {
            $t_name = strtolower($tb_row[0]);
            if (strpos($t_name, 'expense') !== false) {
                // ข้ามตารางที่ไม่ใช่เป้าหมาย
                if (in_array($t_name, ['receive_budget', 'budget_allocations', 'project_refunds', 'treasury_refunds', 'fund_rollovers', 'project_withdrawals', 'treasury_withdrawals', 'budget_expenditures', 'approved_main_payments', 'off_budget_expenditures'])) continue;
                $exp_table = $tb_row[0];
                break; 
            }
        }
    }
}

if ($exp_table !== "") {
    // 2. ดึงรายชื่อคอลัมน์ของตาราง
    $col_q_exp = $conn->query("SHOW COLUMNS FROM `$exp_table`");
    $exp_cols = [];
    if($col_q_exp){
        while($c = $col_q_exp->fetch_assoc()) {
            $exp_cols[] = $c['Field'];
        }
    }
    
    // 3. หาคอลัมน์ที่น่าจะเก็บชื่อประเภทรายจ่าย
    $exp_name_col = "";
    $possible_exp_cols = ['name', 'expense_name', 'budget_name', 'title', 'description', 'expense_type', 'type_name', 'expensesbudget', 'expensebudget', 'expense', 'type'];
    
    foreach($possible_exp_cols as $p_col) {
        foreach($exp_cols as $actual_col) {
            if(strtolower($actual_col) == strtolower($p_col)) {
                $exp_name_col = $actual_col;
                break 2;
            }
        }
    }

    if ($exp_name_col == "") {
        foreach($exp_cols as $col) {
            $lcol = strtolower($col);
            if (!in_array($lcol, ['id', 'budget_year', 'created_at', 'updated_at', 'status', 'date'])) {
                $exp_name_col = $col;
                break;
            }
        }
    }

    if ($exp_name_col != "") {
        // 4. คิวรี่ข้อมูลออกมาสร้าง Option
        $has_b_year = false;
        foreach($exp_cols as $col) {
            if(strtolower($col) == 'budget_year') {
                $has_b_year = true;
                break;
            }
        }

        if ($has_b_year) {
            $sql_exp = "SELECT DISTINCT `$exp_name_col` FROM `$exp_table` WHERE budget_year = ? AND `$exp_name_col` IS NOT NULL AND `$exp_name_col` != '' ORDER BY `$exp_name_col` ASC";
            $stmt_exp = $conn->prepare($sql_exp);
            $stmt_exp->bind_param("i", $active_year);
            $stmt_exp->execute();
            $res_exp = $stmt_exp->get_result();
        } else {
            $sql_exp = "SELECT DISTINCT `$exp_name_col` FROM `$exp_table` WHERE `$exp_name_col` IS NOT NULL AND `$exp_name_col` != '' ORDER BY `$exp_name_col` ASC";
            $res_exp = $conn->query($sql_exp);
        }

        if ($res_exp && $res_exp->num_rows > 0) {
            $unique_exps = []; 
            while($exp = $res_exp->fetch_assoc()) {
                $e_name = trim($exp[$exp_name_col]);
                if ($e_name != '' && !in_array($e_name, $unique_exps)) {
                    $unique_exps[] = $e_name;
                    $expense_type_options .= "<option value='".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($e_name, ENT_QUOTES, 'UTF-8')."</option>";
                }
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .total-text { color: #d63384; font-weight: bold; font-size: 0.8rem;}
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: none; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 6px 25px; background-color: #0d6efd; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #0b5ed7; color: #fff; }
    .btn-form-secondary { padding: 6px 25px; background-color: #6c757d; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form-secondary:hover { background-color: #5c636a; color: #fff; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">ทะเบียนสั่งจ่ายเงินนอกงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h2>
        <div class="d-flex align-items-center mb-2">
            <button class="btn btn-add" onclick="openAddModal()">เพิ่มรายการสั่งจ่าย</button>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">ที่เอกสาร</th>
                        <th style="width: 8%;">อ้างอิง<br>ขอเบิก</th>
                        <th style="width: 8%;">อ้างอิง<br>ฎีกา</th>
                        <th style="width: 30%;">รายการ</th>
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
                            echo "<td class='td-center'>" . $row['doc_no'] . "</td>";
                            echo "<td class='td-center text-warning'>" . htmlspecialchars($row['ref_withdraw_no'] ? $row['ref_withdraw_no'] : '') . "</td>"; 
                            echo "<td class='td-center text-danger'>" . htmlspecialchars($row['ref_petition_no'] ? $row['ref_petition_no'] : '') . "</td>"; 
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='confirmDelete(".$row['id'].")' class='action-btn btn-delete'><i class='fa-solid fa-xmark'></i></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='openEditModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "<td class='td-center'><button class='action-btn btn-print' onclick='alert(\"ฟังก์ชันพิมพ์กำลังปรับปรุง\")'><i class='fa-solid fa-print'></i></button></td>";
                            echo "<td class='td-center total-text'>ถึงนี้</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'><td colspan='6' class='text-center'>รวม</td><td class='td-right'>" . number_format($total_amount, 2) . "</td><td colspan='5'></td></tr>";
                    } else {
                        echo "<tr><td colspan='12' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลในปี $active_year</td></tr>";
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
                <h5 class="modal-title-custom" id="modalTitle">เพิ่มข้อมูลสั่งจ่ายเงินนอกงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-3 mb-3 pt-0">
                <div class="form-white-bg border-0 p-0 mt-2">
                    <form action="Orderpaymentoutsidethebudget.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2 align-items-center mt-3">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">อ้างอิงทะเบียนขอเบิก/ขอยืมเงิน</div>
                            <div class="col-md-7">
                                <select name="ref_withdraw_no" id="ref_withdraw_no" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">อ้างอิงเลขที่ฎีกา</div>
                            <div class="col-md-7">
                                <select name="ref_petition_no" id="ref_petition_no" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ประเภทของเงิน</div>
                            <div class="col-md-5">
                                <select name="off_budget_type" id="off_budget_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="เงินรายได้สถานศึกษา">เงินรายได้สถานศึกษา</option>
                                    <option value="เงินบริจาค">เงินบริจาค</option>
                                    <option value="เงินอุดหนุน">เงินอุดหนุน</option>
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
                            <div class="col-md-4 form-label-custom">ประเภทรายการจ่าย</div>
                            <div class="col-md-5">
                                <select name="expense_type" id="expense_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <?php echo $expense_type_options; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1 pt-1 text-start">บาท</div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">ผู้รับเงิน</div>
                            <div class="col-md-6">
                                <input type="text" name="payee" id="payee" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn-form me-2">ตกลง</button>
                            <button type="button" class="btn-form-secondary" data-bs-dismiss="modal">ย้อนกลับ</button>
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
            <div class="modal-header bg-light">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการสั่งจ่าย</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">ที่เอกสาร</th><td id="view_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">อ้างอิงทะเบียนขอเบิก/ขอยืมเงิน</th><td id="view_ref_withdraw_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">อ้างอิงเลขที่ฎีกา</th><td id="view_ref_petition_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ประเภทของเงิน</th><td id="view_off_budget_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการจ่าย</th><td id="view_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ประเภทรายการจ่าย</th><td id="view_expense_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงิน</th><td id="view_amount" class="text-danger fw-bold fs-5"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผู้รับเงิน</th><td id="view_payee"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
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
        document.getElementById('modalTitle').innerHTML = 'เพิ่มข้อมูลสั่งจ่ายเงินนอกงบประมาณ ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไขข้อมูลสั่งจ่ายเงินนอกงบประมาณ ปีงบประมาณ <?php echo $active_year; ?>';
        
        document.getElementById('doc_no').value = data.doc_no || '';
        
        // จัดการ Option ที่ไม่ได้มีใน List ตั้งต้น ให้แสดงค่าขึ้นมาได้เมื่อกดแก้ไข
        let refWithdraw = document.getElementById('ref_withdraw_no');
        if(data.ref_withdraw_no) {
            if(!Array.from(refWithdraw.options).some(opt => opt.value === data.ref_withdraw_no)) {
                refWithdraw.add(new Option(data.ref_withdraw_no, data.ref_withdraw_no));
            }
        }
        refWithdraw.value = data.ref_withdraw_no || '';

        let refPetition = document.getElementById('ref_petition_no');
        if(data.ref_petition_no) {
            if(!Array.from(refPetition.options).some(opt => opt.value === data.ref_petition_no)) {
                refPetition.add(new Option(data.ref_petition_no, data.ref_petition_no));
            }
        }
        refPetition.value = data.ref_petition_no || '';

        document.getElementById('off_budget_type').value = data.off_budget_type || '';
        document.getElementById('description').value = data.description || '';

        // [แก้ไข] จัดการ Dropdown สำหรับประเภทรายการจ่าย ให้แสดงข้อมูลเก่าได้เมื่อกดแก้ไข
        let expSelect = document.getElementById('expense_type');
        let expValue = data.expense_type || '';
        if(expValue && !Array.from(expSelect.options).some(opt => opt.value === expValue)) {
            expSelect.add(new Option(expValue, expValue));
        }
        expSelect.value = expValue;

        document.getElementById('amount').value = data.amount || '';
        document.getElementById('payee').value = data.payee || '';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_doc_no').innerText = data.doc_no || '-';
        document.getElementById('view_ref_withdraw_no').innerText = data.ref_withdraw_no || '-';
        document.getElementById('view_ref_petition_no').innerText = data.ref_petition_no || '-';
        document.getElementById('view_off_budget_type').innerText = data.off_budget_type || '-';
        document.getElementById('view_description').innerText = data.description || '-';
        document.getElementById('view_expense_type').innerText = data.expense_type || '-';
        document.getElementById('view_amount').innerText = parseFloat(data.amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_payee').innerText = data.payee || '-';

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>