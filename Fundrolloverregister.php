<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนเงินกันไว้เบิกเหลื่อมปี - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ทะเบียนเงินกันไว้เบิกเหลื่อมปี';

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM fund_rollovers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Fundrolloverregister.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = $_POST['doc_date'] ?? date('Y-m-d');
    $petition_no = $_POST['petition_no'] ?? '';
    $doc_no = $_POST['doc_no'] ?? '';
    $plan_name = $_POST['plan_name'] ?? '';
    $project_name = $_POST['project_name'] ?? '';
    $activity_name = $_POST['activity_name'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount_request = $_POST['amount_request'] ?? 0;
    $amount_tax = $_POST['amount_tax'] ?? 0;
    $amount_net = $amount_request - $amount_tax;

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ลำดับถัดไปอัตโนมัติก่อนบันทึก
        $sql_max = "SELECT MAX(rollover_order) as max_order FROM fund_rollovers WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $rollover_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO fund_rollovers (budget_year, rollover_order, doc_date, petition_no, doc_no, plan_name, project_name, activity_name, expense_type, description, amount_request, amount_tax, amount_net) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssssssddd", $active_year, $rollover_order, $doc_date, $petition_no, $doc_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount_request, $amount_tax, $amount_net);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $rollover_order = $_POST['rollover_order'];
        $stmt = $conn->prepare("UPDATE fund_rollovers SET rollover_order=?, doc_date=?, petition_no=?, doc_no=?, plan_name=?, project_name=?, activity_name=?, expense_type=?, description=?, amount_request=?, amount_tax=?, amount_net=? WHERE id=?");
        $stmt->bind_param("issssssssdddi", $rollover_order, $doc_date, $petition_no, $doc_no, $plan_name, $project_name, $activity_name, $expense_type, $description, $amount_request, $amount_tax, $amount_net, $id);
        $stmt->execute();
    }
    header("Location: Fundrolloverregister.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM fund_rollovers WHERE budget_year = ? ORDER BY rollover_order ASC";
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
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .btn-add:hover { background-color: #1a2a44 !important; transform: translateY(-1px); }

    /* ปรับแต่งตารางสีขาวสะอาดตา */
    .table-custom { background-color: #ffffff !important; border: 1px solid #dee2e6; }
    .table-custom thead th { background-color: #f8f9fa !important; color: #333; border-bottom: 2px solid #dee2e6; text-align: center; }
    
    /* แก้ไขแถวรวมให้เป็นสีเทาอ่อน/ขาว แทนสีเหลือง */
    .total-row td {
        background-color: #f8f9fa !important; 
        font-weight: bold;
        color: #181818;
        border-top: 2px solid #dee2e6;
    }

    /* พื้นหลัง Modal ขาวสะอาด */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px 40px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.95rem; color: #495057; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    
    .btn-form { padding: 6px 25px; background-color: #6c757d; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #5c636a; color: #fff; }
    .btn-form-light { padding: 6px 25px; background-color: #f8f9fa; border: 1px solid #ced4da; color: #333; border-radius: 4px; font-size: 0.95rem; }
    .btn-form-light:hover { background-color: #e2e6ea; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <h2 class="page-title text-center mb-4">ทะเบียนเงินกันไว้เบิกเหลื่อมปี ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> ลงทะเบียน
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">เลขที่ฎีกา</th>
                        <th style="width: 10%;">เลขที่เอกสาร</th>
                        <th style="width: 35%;">รายการ</th>
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
                            $val_request = $row['amount_request'] ?? 0;
                            $val_tax = $row['amount_tax'] ?? 0;
                            $val_net = $row['amount_net'] ?? 0;

                            $total_request += $val_request;
                            $total_tax += $val_tax;
                            $total_net += $val_net;
                            
                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['rollover_order'] ?? '') . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date'] ?? '') . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['petition_no'] ?? '') . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no'] ?? '') . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description'] ?? '') . "</td>";
                            echo "<td class='td-right'>" . number_format($val_request, 2) . "</td>";
                            echo "<td class='td-right'>" . number_format($val_tax, 2) . "</td>";
                            echo "<td class='td-right text-success fw-bold'>" . number_format($val_net, 2) . "</td>";
                            
                            echo "<td class='td-center text-nowrap'>";
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo "</td>";
                            echo "</tr>";
                        }
                        
                        echo "<tr class='total-row'>";
                        echo "<td colspan='5' class='text-center'>รวมยอดทั้งหมด</td>";
                        echo "<td class='td-right text-danger'>" . number_format($total_request, 2) . "</td>";
                        echo "<td class='td-right text-danger'>" . number_format($total_tax, 2) . "</td>";
                        echo "<td class='td-right text-success'>" . number_format($total_net, 2) . "</td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='9' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
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
                <h5 class="modal-title-custom text-teal" id="modalTitle">ลงทะเบียน ขอเบิกเงินกันไว้เบิกเหลื่อมปี</h5>
            </div>
            <div class="modal-body mx-4 mb-4 pt-0">
                <div class="form-white-bg mt-2">
                    <form action="Fundrolloverregister.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <input type="hidden" name="rollover_order" id="rollover_order">
                        <input type="hidden" name="doc_date" id="doc_date" value="<?php echo date('Y-m-d'); ?>">

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">เลขที่ฎีกา</div>
                            <div class="col-md-3">
                                <input type="text" name="petition_no" id="petition_no" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-5">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm">
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
                                <input type="number" step="0.01" name="amount_request" id="amount_request" class="form-control form-control-sm" required oninput="calcNet()">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-3 form-label-custom">ภาษี</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount_tax" id="amount_tax" class="form-control form-control-sm" oninput="calcNet()">
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <div class="col-md-3 form-label-custom text-primary">รับจริง</div>
                            <div class="col-md-4">
                                <input type="number" id="amount_net_input" class="form-control form-control-sm text-primary fw-bold" readonly style="background-color: #f8f9fa;">
                            </div>
                        </div>
                        
                        <div class="row mb-3 text-center">
                            <div class="col-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="save_to_receive" id="save_to_receive" value="1">
                                    <label class="form-check-label text-muted" for="save_to_receive" style="font-size: 0.95rem;">บันทึกข้อมูลในทะเบียนรับเงินงบประมาณด้วย</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-form me-2">ตกลง</button>
                            <button type="button" class="btn btn-form-light" data-bs-dismiss="modal">ย้อนกลับ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function calcNet() {
        const req = parseFloat(document.getElementById('amount_request').value) || 0;
        const tax = parseFloat(document.getElementById('amount_tax').value) || 0;
        document.getElementById('amount_net_input').value = (req - tax).toFixed(2);
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'ขอเบิกเงินกันไว้เบิกเหลื่อมปี ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        document.getElementById('amount_net_input').value = '';
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข ข้อมูลเงินกันไว้เบิกเหลื่อมปี';
        
        document.getElementById('rollover_order').value = data.rollover_order ?? '';
        document.getElementById('doc_date').value = data.doc_date ?? '';
        document.getElementById('petition_no').value = data.petition_no ?? '';
        document.getElementById('doc_no').value = data.doc_no ?? '';
        document.getElementById('plan_name').value = data.plan_name ?? '';
        document.getElementById('project_name').value = data.project_name ?? '';
        document.getElementById('activity_name').value = data.activity_name ?? '';
        document.getElementById('expense_type').value = data.expense_type ?? '';
        document.getElementById('description').value = data.description ?? '';
        document.getElementById('amount_request').value = data.amount_request ?? '0';
        document.getElementById('amount_tax').value = data.amount_tax ?? '0';
        calcNet();

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }
</script>