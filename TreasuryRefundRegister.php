<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

$page_title = "ทะเบียนคืนเงินคงคลัง - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนคืนเงินคงคลัง';

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ให้ฐานข้อมูลอัตโนมัติ (รองรับฟอร์มใหม่ตามรูป) ---
$columns_to_add = [
    'period_no' => "VARCHAR(100) NULL AFTER doc_no",
    'plan_name' => "VARCHAR(255) NULL AFTER period_no",
    'project_name' => "VARCHAR(255) NULL AFTER plan_name",
    'activity_name' => "VARCHAR(255) NULL AFTER project_name",
    'expense_type' => "VARCHAR(255) NULL AFTER activity_name"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM treasury_refunds LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE treasury_refunds ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM treasury_refunds WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: TreasuryRefundRegister.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $refund_order = $_POST['refund_order'] ?? 0;
    $refund_date = $_POST['doc_date'] ?? date('Y-m-d'); 
    $doc_no = $_POST['doc_no'] ?? '';
    $period_no = $_POST['period_no'] ?? '';
    $plan_name = $_POST['plan_name'] ?? '';
    $project_name = $_POST['project_name'] ?? '';
    $activity_name = $_POST['activity_name'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? 0;

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO treasury_refunds (budget_year, refund_order, refund_date, doc_no, period_no, plan_name, project_name, activity_name, expense_type, description, amount, ref_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual')");
        $stmt->bind_param("iissssssssd", $active_year, $refund_order, $refund_date, $doc_no, $period_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount);
        $stmt->execute();
        
        // ถ้ามีการติ๊ก Checkbox ให้ไปลงทะเบียนรับเงินงบประมาณ (จำลอง logic ตามรูปภาพ)
        if(isset($_POST['save_to_receive']) && $_POST['save_to_receive'] == '1') {
            $check_tbl = $conn->query("SHOW TABLES LIKE 'receive_budget'");
            if($check_tbl->num_rows > 0) {
                $sql_rec_max = "SELECT MAX(receive_order) as m_order FROM receive_budget WHERE budget_year = ?";
                $st_rm = $conn->prepare($sql_rec_max);
                $st_rm->bind_param("i", $active_year);
                $st_rm->execute();
                $rr = $st_rm->get_result()->fetch_assoc();
                $rec_order = ($rr['m_order'] ?? 0) + 1;
                $t_type = "รับเงินสด"; 
                $desc_receive = "รับคืนเงินคงคลัง: " . $description; 

                $ins_rec = $conn->prepare("INSERT INTO receive_budget (budget_year, receive_order, doc_date, doc_no, description, transaction_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if($ins_rec) {
                    $ins_rec->bind_param("iissssd", $active_year, $rec_order, $refund_date, $doc_no, $desc_receive, $t_type, $amount);
                    $ins_rec->execute();
                }
            }
        }
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE treasury_refunds SET refund_order=?, refund_date=?, doc_no=?, period_no=?, plan_name=?, project_name=?, activity_name=?, expense_type=?, description=?, amount=? WHERE id=?");
        $stmt->bind_param("issssssssdi", $refund_order, $refund_date, $doc_no, $period_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount, $id);
        $stmt->execute();
    }
    header("Location: TreasuryRefundRegister.php");
    exit();
}

// --- ดึงข้อมูล (สำคัญ: คอลัมน์ต้องตรงกับ SQL ด้านบน) ---
$sql_data = "SELECT * FROM treasury_refunds WHERE budget_year = ? ORDER BY refund_date ASC, id ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// --- เตรียมเลขลำดับถัดไป
$sql_next = "SELECT MAX(refund_order) as max_order FROM treasury_refunds WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_refund_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

$total_amount = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ปุ่มเพิ่มข้อมูลสีน้ำเงินเข้ม */
    .btn-add {
        background-color: #0b1526 !important; 
        color: white !important;
        border-radius: 8px;
        padding: 8px 25px;
        font-weight: 500;
        border: none;
        transition: 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .btn-add:hover { background-color: #1a2a44 !important; transform: translateY(-1px); }

    .total-row { background-color: #fff3cd !important; font-weight: bold; color: #181818; }

    /* พื้นหลัง Modal ขาวสะอาด */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px 40px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.95rem; color: #333; padding-top: 6px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    
    /* สไตล์ปุ่มเทาในฟอร์ม */
    .btn-form { padding: 6px 25px; background-color: #6c757d; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #5c636a; color: #fff; }
    .btn-form-light { padding: 6px 25px; background-color: #f8f9fa; border: 1px solid #ced4da; color: #333; border-radius: 4px; font-size: 0.95rem; }
    .btn-form-light:hover { background-color: #e2e6ea; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title">ทะเบียนคืนเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 15%;">เลขที่เอกสาร</th>
                        <th style="width: 40%;">รายการ</th>
                        <th style="width: 15%;">จำนวนเงิน</th>
                        <th style="width: 15%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            $is_from_project = ($row['ref_type'] == 'project');

                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['refund_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['refund_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['description']);
                            if($is_from_project) echo ' <span class="badge bg-info ms-2" style="font-size: 0.7rem;">คืนจากโครงการ</span>';
                            echo "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            
                            echo "<td class='td-center text-nowrap'>";
                            echo '<button class="action-btn text-info" title="รายละเอียด" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-regular fa-rectangle-list"></i></button>';
                            if($is_from_project) {
                                echo '<span class="text-muted small ms-2">จัดการที่หน้าโครงการ</span>';
                            } else {
                                echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                                echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'ยืนยันการลบ?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'><td colspan='4' class='text-center'>รวมยอดคืนเงินคงคลังทั้งสิ้น</td><td class='td-right'>" . number_format($total_amount, 2) . "</td><td></td></tr>";
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header d-block pb-2 border-0">
                <h5 class="modal-title-custom text-teal" id="modalTitle">คืนเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-3 mb-3 pt-0">
                <div class="form-white-bg mt-2">
                    <form action="TreasuryRefundRegister.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-5">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">เลขที่ใบงวด</div>
                            <div class="col-md-8">
                                <select name="period_no" id="period_no" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="งวดที่ 1">งวดที่ 1</option>
                                    <option value="งวดที่ 2">งวดที่ 2</option>
                                    <option value="งวดที่ 3">งวดที่ 3</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">แผน</div>
                            <div class="col-md-7">
                                <select name="plan_name" id="plan_name" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="แผนงานพื้นฐาน">แผนงานพื้นฐาน</option>
                                    <option value="แผนงานยุทธศาสตร์">แผนงานยุทธศาสตร์</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ผลผลิต/โครงการ</div>
                            <div class="col-md-9">
                                <select name="project_name" id="project_name" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="โครงการ A">โครงการ A</option>
                                    <option value="โครงการ B">โครงการ B</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">กิจกรรมหลัก</div>
                            <div class="col-md-9">
                                <select name="activity_name" id="activity_name" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="กิจกรรม 1">กิจกรรม 1</option>
                                    <option value="กิจกรรม 2">กิจกรรม 2</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">รายการจ่าย</div>
                            <div class="col-md-5">
                                <select name="expense_type" id="expense_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="ค่าตอบแทน ใช้สอยและวัสดุ">ค่าตอบแทน ใช้สอยและวัสดุ</option>
                                    <option value="ค่าสาธารณูปโภค">ค่าสาธารณูปโภค</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 mt-3 align-items-center">
                            <div class="col-md-3 form-label-custom">รายการ</div>
                            <div class="col-md-9">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-3 form-label-custom">จำนวนเงินคืน</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-3 text-center">
                            <div class="col-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="save_to_receive" id="save_to_receive" value="1" checked>
                                    <label class="form-check-label" for="save_to_receive" style="font-size: 0.95rem;">บันทึกข้อมูลในทะเบียนรับเงินงบประมาณด้วย</label>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="refund_order" id="refund_order">
                        <input type="hidden" name="doc_date" id="doc_date" value="<?php echo date('Y-m-d'); ?>">

                        <div class="text-center mt-4 pt-3">
                            <button type="submit" class="btn btn-secondary px-4 me-2">ตกลง</button>
                            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button>
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
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการคืนเงินคงคลัง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">ที่เอกสาร</th><td id="detail_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">เลขที่ใบงวด</th><td id="detail_period_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แผน</th><td id="detail_plan_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผลผลิต/โครงการ</th><td id="detail_project_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลัก</th><td id="detail_activity_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการจ่าย</th><td id="detail_expense_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการ</th><td id="detail_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงินคืน</th><td id="detail_amount" class="text-danger fw-bold fs-5"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        document.getElementById('modalTitle').innerHTML = 'คืนเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?>';
        
        document.getElementById('refund_order').value = '<?php echo $next_refund_order; ?>';
        
        var myModal = new bootstrap.Modal(document.getElementById('addModal'));
        myModal.show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข ข้อมูลการคืนเงิน';
        
        document.getElementById('refund_order').value = data.refund_order;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('period_no').value = data.period_no || '';
        document.getElementById('plan_name').value = data.plan_name || '';
        document.getElementById('project_name').value = data.project_name || '';
        document.getElementById('activity_name').value = data.activity_name || '';
        document.getElementById('expense_type').value = data.expense_type || '';
        document.getElementById('description').value = data.description;
        document.getElementById('amount').value = data.amount;

        var myModal = new bootstrap.Modal(document.getElementById('addModal'));
        myModal.show();
    }

    function openDetailModal(data) {
        document.getElementById('detail_doc_no').innerText = data.doc_no || '-';
        document.getElementById('detail_period_no').innerText = data.period_no || '-';
        document.getElementById('detail_plan_name').innerText = data.plan_name || '-';
        document.getElementById('detail_project_name').innerText = data.project_name || '-';
        document.getElementById('detail_activity_name').innerText = data.activity_name || '-';
        document.getElementById('detail_expense_type').innerText = data.expense_type || '-';
        document.getElementById('detail_description').innerText = data.description || '-';
        
        let amount = parseFloat(data.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
        document.getElementById('detail_amount').innerText = amount + ' บาท';

        var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        detailModal.show();
    }
</script>