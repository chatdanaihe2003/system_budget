<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนขอเบิกเงินคงคลัง - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนขอเบิกเงินคงคลัง';

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ให้ฐานข้อมูลอัตโนมัติ (รองรับฟอร์มใหม่ตามรูป) ---
$columns_to_add = [
    'plan_name' => "VARCHAR(255) NULL AFTER period_no",
    'project_name' => "VARCHAR(255) NULL AFTER plan_name",
    'activity_name' => "VARCHAR(255) NULL AFTER project_name",
    'expense_type' => "VARCHAR(255) NULL AFTER activity_name"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM treasury_withdrawals LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE treasury_withdrawals ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM treasury_withdrawals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: TreasuryWithdrawal.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $petition_no = $_POST['petition_no'] ?? '';
    $doc_no = $_POST['doc_no'] ?? '';
    $period_no = $_POST['period_no'] ?? '';
    $plan_name = $_POST['plan_name'] ?? '';
    $project_name = $_POST['project_name'] ?? '';
    $activity_name = $_POST['activity_name'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount_request = $_POST['amount_request'] ?? 0;
    $amount_tax = $_POST['amount_tax'] ?? 0;
    
    // คำนวณรับจริง
    $amount_net = $amount_request - $amount_tax;
    
    // วันที่และลำดับ (จัดการเบื้องหลังเพื่อไม่ให้รกฟอร์ม)
    $doc_date = !empty($_POST['doc_date']) ? $_POST['doc_date'] : date('Y-m-d');
    $withdrawal_order = $_POST['withdrawal_order'] ?? '';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ลำดับถัดไปอัตโนมัติ
        $sql_max = "SELECT MAX(withdrawal_order) as max_order FROM treasury_withdrawals WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $withdrawal_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO treasury_withdrawals (budget_year, withdrawal_order, doc_date, petition_no, doc_no, period_no, plan_name, project_name, activity_name, expense_type, description, amount_request, amount_tax, amount_net) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssssssssddd", $active_year, $withdrawal_order, $doc_date, $petition_no, $doc_no, $period_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount_request, $amount_tax, $amount_net);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE treasury_withdrawals SET petition_no=?, doc_no=?, period_no=?, plan_name=?, project_name=?, activity_name=?, expense_type=?, description=?, amount_request=?, amount_tax=?, amount_net=? WHERE id=?");
        $stmt->bind_param("sssssssssdddi", $petition_no, $doc_no, $period_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount_request, $amount_tax, $amount_net, $id);
        $stmt->execute();
    }
    header("Location: TreasuryWithdrawal.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM treasury_withdrawals WHERE budget_year = ? ORDER BY withdrawal_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_request = 0;
$total_tax = 0;
$total_net = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ปรับแต่งปุ่มเพิ่มข้อมูลสีน้ำเงินเข้มตามรูป */
    .btn-add {
        background-color: #0b1526 !important; 
        color: white !important;
        border-radius: 8px;
        padding: 8px 25px;
        font-weight: 500;
        border: none;
        transition: 0.3s;
    }
    .btn-add:hover { background-color: #1a2a44 !important; transform: translateY(-1px); }

    /* ปรับแถวรวมให้เด่นชัดแต่ยังดูสะอาด */
    .total-row td {
        background-color: #f8f9fa !important;
        font-weight: bold;
        color: #333;
        border-top: 2px solid #dee2e6;
    }

    /* พื้นหลัง Modal ขาวสะอาด ตามคำสั่งล่าสุด */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px 40px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.95rem; color: #495057; padding-top: 5px; }
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
        
        <h2 class="page-title text-center">ทะเบียนขอเบิกเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">ว/ด/ป</th>
                        <th style="width: 8%;">เลขที่ฎีกา</th>
                        <th style="width: 10%;">เลขที่เอกสาร</th>
                        <th style="width: 5%;">ใบงวด</th>
                        <th style="width: 30%;">รายการ</th>
                        <th style="width: 10%;">ขอเบิก</th>
                        <th style="width: 8%;">ภาษี</th>
                        <th style="width: 10%;">รับจริง</th>
                        <th style="width: 6%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_request += $row['amount_request'];
                            $total_tax += $row['amount_tax'];
                            $total_net += $row['amount_net'];
                            
                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['withdrawal_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['petition_no']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['period_no']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount_request'], 2) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount_tax'], 2) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount_net'], 2) . "</td>";
                            
                            echo "<td class='td-center text-nowrap'>";
                            // เพิ่มปุ่มรายละเอียดเข้าไปในช่องจัดการ เพื่อไม่ให้กระทบสีและคอลัมน์เดิม
                            echo '<button class="action-btn text-info" title="รายละเอียด" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-regular fa-rectangle-list"></i></button>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo "</td>";
                            echo "</tr>";
                        }
                        
                        echo "<tr class='total-row'>";
                        echo "<td colspan='6' class='text-center'>รวมยอดทั้งหมด</td>";
                        echo "<td class='td-right'>" . number_format($total_request, 2) . "</td>";
                        echo "<td class='td-right'>" . number_format($total_tax, 2) . "</td>";
                        echo "<td class='td-right'>" . number_format($total_net, 2) . "</td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลการขอเบิกในปีงบประมาณ $active_year</td></tr>";
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
                <h5 class="modal-title-custom text-teal" id="modalTitle">ขอเบิกเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-3 mb-3 pt-0">
                <div class="form-white-bg mt-2">
                    <form action="TreasuryWithdrawal.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <input type="hidden" name="withdrawal_order" id="withdrawal_order">
                        <input type="hidden" name="doc_date" id="doc_date" value="<?php echo date('Y-m-d'); ?>">

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">เลขที่ฎีกา</div>
                            <div class="col-md-3">
                                <input type="text" name="petition_no" id="petition_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

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

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">จำนวนเงินขอเบิก</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount_request" id="amount_request" class="form-control form-control-sm" onkeyup="calculateNet()" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ภาษี</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount_tax" id="amount_tax" class="form-control form-control-sm" value="0.00" onkeyup="calculateNet()" required>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-3 form-label-custom text-primary">รับจริง</div>
                            <div class="col-md-4">
                                <input type="text" id="amount_net_display" class="form-control form-control-sm text-primary fw-bold" style="background-color: #e9ecef;" readonly>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn-form me-2">ตกลง</button>
                            <button type="button" class="btn-form-light" data-bs-dismiss="modal">ย้อนกลับ</button>
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
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการขอเบิกเงินคงคลัง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">เลขที่ฎีกา</th><td id="detail_petition_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ที่เอกสาร</th><td id="detail_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">เลขที่ใบงวด</th><td id="detail_period_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แผน</th><td id="detail_plan_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผลผลิต/โครงการ</th><td id="detail_project_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลัก</th><td id="detail_activity_name"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการจ่าย</th><td id="detail_expense_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการ</th><td id="detail_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงินขอเบิก</th><td id="detail_amount_request"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ภาษี</th><td id="detail_amount_tax" class="text-danger"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รับจริง</th><td id="detail_amount_net" class="text-success fw-bold fs-5"></td></tr>
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
    // คำนวณยอดรับจริงอัตโนมัติ
    function calculateNet() {
        let req = parseFloat(document.getElementById('amount_request').value) || 0;
        let tax = parseFloat(document.getElementById('amount_tax').value) || 0;
        let net = req - tax;
        document.getElementById('amount_net_display').value = net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.querySelector('#addModal form').reset();
        document.getElementById('amount_net_display').value = '';
        document.getElementById('modalTitle').innerHTML = 'ขอเบิกเงินคงคลัง ปีงบประมาณ <?php echo $active_year; ?>';
        
        var myModal = new bootstrap.Modal(document.getElementById('addModal'));
        myModal.show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข ขอเบิกเงินคงคลัง';
        
        document.getElementById('withdrawal_order').value = data.withdrawal_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('petition_no').value = data.petition_no;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('period_no').value = data.period_no;
        document.getElementById('plan_name').value = data.plan_name || '';
        document.getElementById('project_name').value = data.project_name || '';
        document.getElementById('activity_name').value = data.activity_name || '';
        document.getElementById('expense_type').value = data.expense_type || '';
        document.getElementById('description').value = data.description;
        document.getElementById('amount_request').value = data.amount_request;
        document.getElementById('amount_tax').value = data.amount_tax;
        
        calculateNet();

        var myModal = new bootstrap.Modal(document.getElementById('addModal'));
        myModal.show();
    }

    function openDetailModal(data) {
        document.getElementById('detail_petition_no').innerText = data.petition_no || '-';
        document.getElementById('detail_doc_no').innerText = data.doc_no || '-';
        document.getElementById('detail_period_no').innerText = data.period_no || '-';
        document.getElementById('detail_plan_name').innerText = data.plan_name || '-';
        document.getElementById('detail_project_name').innerText = data.project_name || '-';
        document.getElementById('detail_activity_name').innerText = data.activity_name || '-';
        document.getElementById('detail_expense_type').innerText = data.expense_type || '-';
        document.getElementById('detail_description').innerText = data.description || '-';
        
        document.getElementById('detail_amount_request').innerText = parseFloat(data.amount_request || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' บาท';
        document.getElementById('detail_amount_tax').innerText = parseFloat(data.amount_tax || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' บาท';
        document.getElementById('detail_amount_net').innerText = parseFloat(data.amount_net || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' บาท';

        var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        detailModal.show();
    }
</script>