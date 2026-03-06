<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนขอเบิก/ขอยืมเงินโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนขอเบิก/ขอยืมเงินโครงการ';

// --- ตรวจสอบและสร้างคอลัมน์เพิ่มเติมในฐานข้อมูลให้อัตโนมัติ (เพื่อให้ตรงตามรูป) ---
$columns_to_add = [
    'fund_source' => "VARCHAR(255) NULL AFTER amount",
    'dika_no' => "VARCHAR(100) NULL AFTER fund_source",
    'officer_name' => "VARCHAR(255) NULL AFTER dika_no",
    'status' => "VARCHAR(10) NULL"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM project_withdrawals LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE project_withdrawals ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM project_withdrawals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// 2. เปลี่ยนสถานะ (Toggle Status สีเขียว/แดง)
if (isset($_GET['toggle_id'])) {
    $id = $_GET['toggle_id'];
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'green') ? 'red' : 'green';

    $stmt = $conn->prepare("UPDATE project_withdrawals SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = !empty($_POST['doc_date']) ? $_POST['doc_date'] : date('Y-m-d');
    $doc_no = $_POST['doc_no'] ?? '';
    $withdrawal_type = $_POST['withdrawal_type'] ?? 1;
    $description = $_POST['description'] ?? '';
    $project_id = $_POST['project_id'] ?? 0;
    $activity_id = $_POST['activity_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $expense_type = $_POST['expense_type'] ?? '';
    $requester = $_POST['requester'] ?? '';
    $fund_source = $_POST['fund_source'] ?? '';
    $dika_no = $_POST['dika_no'] ?? '';
    $officer_name = $_POST['officer_name'] ?? '';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $sql_max = "SELECT MAX(withdrawal_order) as max_order FROM project_withdrawals WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $auto_withdrawal_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO project_withdrawals 
            (budget_year, withdrawal_order, doc_date, doc_no, withdrawal_type, description, project_id, activity_id, amount, fund_source, expense_type, requester, dika_no, officer_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissisiidsssss", $active_year, $auto_withdrawal_order, $doc_date, $doc_no, $withdrawal_type, $description, $project_id, $activity_id, $amount, $fund_source, $expense_type, $requester, $dika_no, $officer_name);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $withdrawal_order = $_POST['withdrawal_order'] ?? 0; 

        $stmt = $conn->prepare("UPDATE project_withdrawals 
            SET withdrawal_order=?, doc_date=?, doc_no=?, withdrawal_type=?, description=?, project_id=?, activity_id=?, amount=?, fund_source=?, expense_type=?, requester=?, dika_no=?, officer_name=? 
            WHERE id=?");
        $stmt->bind_param("issisiidsssssi", $withdrawal_order, $doc_date, $doc_no, $withdrawal_type, $description, $project_id, $activity_id, $amount, $fund_source, $expense_type, $requester, $dika_no, $officer_name, $id);
        $stmt->execute();
    }
    header("Location: RequestforWithdrawalProjectLoan.php");
    exit();
}

// --- ดึงข้อมูลปี Active ---
$sql_data = "SELECT * FROM project_withdrawals WHERE budget_year = ? ORDER BY id ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$sql_next = "SELECT MAX(withdrawal_order) as max_order FROM project_withdrawals WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_withdrawal_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

$projects_opt = [
    ['id' => 1, 'name' => '006 การพัฒนาบุคลากร (OD)...'],
    ['id' => 2, 'name' => 'โครงการปรับปรุงภูมิทัศน์'],
    ['id' => 3, 'name' => 'โครงการจัดซื้อครุภัณฑ์']
];

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .btn-add { background-color: #0b1526 !important; color: white !important; border-radius: 8px; padding: 8px 25px; font-weight: 500; border: none; transition: 0.3s; }
    .btn-add:hover { background-color: #1a2a44 !important; transform: translateY(-1px); }
    .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 5px; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); }
    .bg-green { background-color: #00ff00; }
    .bg-red { background-color: #ff0000; }
    
    .form-white-bg { background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #333; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title text-center mb-4">ทะเบียนขอเบิก/ขอยืมเงินโครงการ ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" onclick="checkAdminAction('add')">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 10%;">ที่เอกสาร</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 5%;">สถานะ</th>
                        <th style="width: 8%;">รายละเอียด</th>
                        <th style="width: 17%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_amount = 0;
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $withdrawal_order = $row['withdrawal_order'] ?? $i++;
                            $amount = $row['amount'] ?? 0;
                            $withdrawal_type = $row['withdrawal_type'] ?? 1;
                            $total_amount += $amount;
                            
                            $db_status = $row['status'] ?? '';
                            if ($db_status == '') {
                                $current_status = ($withdrawal_type == 4) ? 'green' : 'red';
                            } else {
                                $current_status = $db_status;
                            }
                            $status_class = ($current_status == 'green') ? 'bg-green' : 'bg-red';

                            echo "<tr>";
                            echo "<td class='text-center'>" . htmlspecialchars($withdrawal_order) . "</td>";
                            echo "<td class='text-center'>" . thai_date_short($row['doc_date'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($row['doc_no'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
                            echo "<td class='text-end'>" . number_format($amount, 2) . "</td>";
                            
                            echo "<td class='text-center'>
                                    <a href='?toggle_id=".$row['id']."&current_status=".$current_status."' onclick=\"return confirm('ต้องการเปลี่ยนสถานะใช่หรือไม่?');\">
                                        <div class='status-box $status_class' title='คลิกเพื่อเปลี่ยนสถานะ'></div>
                                    </a>
                                  </td>";
                            
                            echo "<td class='text-center'>";
                            echo '<button class="action-btn text-info" title="รายละเอียด" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-regular fa-rectangle-list"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center'>";
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'checkAdminAction("edit", '.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo '<a href="javascript:void(0)" onclick="checkAdminDelete('.($row['id'] ?? 0).')" class="action-btn btn-delete" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "<tr style='background-color: #f8f9fa; font-weight: bold;'>";
                        echo "<td colspan='4' class='text-center'>รวมยอดทั้งหมด</td>";
                        echo "<td class='text-end text-danger'>" . number_format($total_amount, 2) . "</td>";
                        echo "<td colspan='3'></td>";
                        echo "</tr>";
                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 p-4" style="background-color: #ffffff; border-radius: 8px; border: 1px solid #dee2e6;">
            <div class="d-flex align-items-start mb-3">
                <div class="status-box bg-green mt-1" style="width: 18px; height: 18px;"></div>
                <div class="ms-3" style="font-size: 1rem; color: #333; font-weight: 500;">แสดงสถานะขอเบิก/ส่งใช้เงินยืม</div>
            </div>
            <div class="d-flex align-items-start border-top pt-3">
                <div class="status-box bg-red mt-1" style="width: 18px; height: 18px;"></div>
                <div class="ms-3" style="font-size: 1rem; color: #333; font-weight: 500; line-height: 1.5;">
                    แสดงสถานะขอยืมเงิน ซึ่งเมื่อมีการส่งใช้เงินยืม เจ้าหน้าที่เพียงคลิกที่ สัญลักษณ์ สีแดง สถานะก็จะเปลี่ยนเป็นสีเขียว ลูกหนี้เงินยืมก็หมดไป
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom" id="modalTitle">ลงทะเบียน ขอเบิก/ขอยืมเงินโครงการ</h5>
            </div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <form action="RequestforWithdrawalProjectLoan.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ที่ (อัตโนมัติ)</div>
                            <div class="col-md-3">
                                <input type="number" name="withdrawal_order" id="withdrawal_order" class="form-control form-control-sm" style="background-color: #f1f5f9; cursor: not-allowed;" readonly required>
                            </div>
                            <div class="col-md-2 form-label-custom">วดป ลงทะเบียน</div>
                            <div class="col-md-4">
                                <input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3"></div>
                            <div class="col-md-9">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type1" value="1" checked>
                                    <label class="form-check-label" for="type1">ขอยืมเงินงบประมาณ</label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type2" value="2">
                                    <label class="form-check-label" for="type2">ขอยืมเงินนอกงบประมาณ</label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type3" value="3">
                                    <label class="form-check-label" for="type3">ขอยืมเงินทดรองราชการ</label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="withdrawal_type" id="type4" value="4">
                                    <label class="form-check-label" for="type4">ขอเบิก</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">รายการ</div>
                            <div class="col-md-9">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">โครงการ</div>
                            <div class="col-md-9">
                                <select name="project_id" id="project_id" class="form-select form-select-sm">
                                    <option value="0">เลือก</option>
                                    <?php foreach ($projects_opt as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">กิจกรรม</div>
                            <div class="col-md-9">
                                <select name="activity_id" id="activity_id" class="form-select form-select-sm">
                                    <option value="0">เลือกโครงการก่อน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-2 pt-1">บาท</div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">แหล่งเงิน</div>
                            <div class="col-md-9">
                                <input type="text" name="fund_source" id="fund_source" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ประเภทรายการจ่าย</div>
                            <div class="col-md-4">
                                <select name="expense_type" id="expense_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="ค่าใช้สอย">ค่าใช้สอย</option>
                                    <option value="ค่าวัสดุ">ค่าวัสดุ</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ผู้ขอเบิก/ขอยืมเงิน</div>
                            <div class="col-md-6">
                                <input type="text" name="requester" id="requester" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ฎีกา</div>
                            <div class="col-md-4">
                                <input type="text" name="dika_no" id="dika_no" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">เจ้าหน้าที่</div>
                            <div class="col-md-6">
                                <input type="text" name="officer_name" id="officer_name" class="form-control form-control-sm" value="<?php echo $_SESSION['name'] ?? ''; ?>">
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-4 me-2">ตกลง</button>
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button>
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
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold w-100 text-center mt-2 text-teal">ทะเบียน คุมหลักฐานขอเบิก/ขอยืมเงิน ปีงบประมาณ <?php echo $active_year; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4 mx-2 mb-2 rounded">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">วดป ลงทะเบียน :</div><div class="col-8" id="v_date"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">ที่เอกสาร :</div><div class="col-8" id="v_no"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold" id="v_type_label">ขอยืมเงินงบประมาณ :</div><div class="col-8" id="v_type"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">รายการ :</div><div class="col-8" id="v_desc"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">โครงการ :</div><div class="col-8" id="v_proj"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">กิจกรรม :</div><div class="col-8" id="v_act"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">จำนวนเงิน :</div><div class="col-8 text-danger fw-bold" id="v_amount"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">แหล่งเงิน :</div><div class="col-8" id="v_fund"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">ประเภทรายการจ่าย :</div><div class="col-8" id="v_extype"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">ผู้ขอเบิก/ขอยืมเงิน :</div><div class="col-8" id="v_req"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">ฎีกา :</div><div class="col-8" id="v_dika"></div></div>
                    <div class="row mb-2"><div class="col-4 text-end fw-bold">เจ้าหน้าที่ :</div><div class="col-8" id="v_off"></div></div>
                </div></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal"><< กลับหน้าก่อน</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function checkAdminAction(action, data = null) {
        if (action === 'add') { openAddModal(); } else { openEditModal(data); }
    }
    function checkAdminDelete(id) {
        if (confirm('ยืนยันลบ?')) { window.location.href = `?delete_id=${id}`; }
    }
    
    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        document.getElementById('withdrawal_order').value = '<?php echo $next_withdrawal_order; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('officer_name').value = '<?php echo $_SESSION['name'] ?? ''; ?>';
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('withdrawal_order').value = data.withdrawal_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('description').value = data.description;
        document.getElementById('project_id').value = data.project_id;
        document.getElementById('activity_id').value = data.activity_id;
        document.getElementById('amount').value = data.amount;
        document.getElementById('fund_source').value = data.fund_source || '';
        document.getElementById('expense_type').value = data.expense_type;
        document.getElementById('requester').value = data.requester;
        document.getElementById('dika_no').value = data.dika_no || '';
        document.getElementById('officer_name').value = data.officer_name || '';

        let radios = document.getElementsByName('withdrawal_type');
        for(let r of radios) { if(r.value == data.withdrawal_type) r.checked = true; }
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('v_date').innerText = data.doc_date;
        document.getElementById('v_no').innerText = data.doc_no || '-';
        
        let typeLabels = {1:"ขอยืมเงินงบประมาณ", 2:"ขอยืมเงินนอกงบประมาณ", 3:"ขอยืมเงินทดรองราชการ", 4:"ขอเบิก"};
        document.getElementById('v_type_label').innerText = typeLabels[data.withdrawal_type] + " :";
        document.getElementById('v_type').innerText = "ใช่"; 

        document.getElementById('v_desc').innerText = data.description;
        
        let projText = "006 การพัฒนาบุคลากร (OD)..."; // ดึงชื่อโครงการจากอาร์เรย์มาแสดงแทน ID ในงานจริง
        document.getElementById('v_proj').innerText = projText; 
        
        document.getElementById('v_act').innerText = data.activity_id || '-';
        document.getElementById('v_amount').innerText = parseFloat(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) + " บาท";
        document.getElementById('v_fund').innerText = data.fund_source || '-';
        document.getElementById('v_extype').innerText = data.expense_type || '-';
        document.getElementById('v_req').innerText = data.requester || '-';
        document.getElementById('v_dika').innerText = data.dika_no || '-';
        document.getElementById('v_off').innerText = data.officer_name || '-';
        
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>