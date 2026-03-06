<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ';

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD & Upload) 
// --------------------------------------------------------------------------------

// --- สร้างโฟลเดอร์ uploads อัตโนมัติ (ถ้ายังไม่มี) ---
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ในฐานข้อมูล เพื่อรองรับฟอร์มให้ครบถ้วนตามรูปภาพ ---
$columns_to_add = [
    'doc_no' => "VARCHAR(255) NULL AFTER doc_date",
    'ref_alloc_doc' => "VARCHAR(255) NULL AFTER doc_no",
    'plan_type' => "VARCHAR(255) NULL AFTER ref_alloc_doc",
    'project_type' => "VARCHAR(255) NULL AFTER plan_type",
    'main_activity' => "VARCHAR(255) NULL AFTER project_type",
    'sub_activity' => "TEXT NULL AFTER main_activity",
    'fund_source' => "VARCHAR(255) NULL AFTER sub_activity",
    'account_code' => "VARCHAR(255) NULL AFTER fund_source",
    'expense_budget' => "VARCHAR(255) NULL AFTER account_code",
    'detail_desc' => "TEXT NULL AFTER description",
    'recorded_by' => "VARCHAR(255) NULL AFTER amount" // เพิ่มคอลัมน์ผู้บันทึกข้อมูล
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM budget_allocations LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE budget_allocations ADD $col_name $col_definition");
    }
}

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ลบไฟล์จริงออกจาก Server
    $sql_file = "SELECT file_name FROM budget_allocations WHERE id = ?";
    $stmt_file = $conn->prepare($sql_file);
    $stmt_file->bind_param("i", $id);
    $stmt_file->execute();
    $res_file = $stmt_file->get_result();
    if ($row = $res_file->fetch_assoc()) {
        if (!empty($row['file_name']) && file_exists("uploads/" . $row['file_name'])) {
            unlink("uploads/" . $row['file_name']);
        }
    }

    $stmt = $conn->prepare("DELETE FROM budget_allocations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Budgetallocation.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // รับค่าจากฟอร์มให้ครบทุกช่อง
    $doc_no = $_POST['doc_no'] ?? '';
    $doc_date = $_POST['doc_date'] ?? '';
    $ref_alloc_doc = $_POST['ref_alloc_doc'] ?? '';
    $plan_type = $_POST['plan_type'] ?? '';
    $project_type = $_POST['project_type'] ?? '';
    $main_activity = $_POST['main_activity'] ?? '';
    $sub_activity = $_POST['sub_activity'] ?? '';
    $fund_source = $_POST['fund_source'] ?? '';
    $account_code = $_POST['account_code'] ?? '';
    $expense_budget = $_POST['expense_budget'] ?? '';
    $description = $_POST['description'] ?? '';
    $detail_desc = $_POST['detail_desc'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    
    $file_name = null;
    
    // ดึงชื่อผู้บันทึกจาก Session
    $recorded_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin'; 

    // จัดการอัปโหลดไฟล์
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $ext = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid() . "_" . time() . "." . $ext; 
        if(move_uploaded_file($_FILES['file_upload']['tmp_name'], "uploads/" . $new_name)){
            $file_name = $new_name;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ใบงวดถัดไปอัตโนมัติก่อนบันทึก
        $sql_max = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $auto_allocation_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $sql_insert = "INSERT INTO budget_allocations (budget_year, allocation_order, doc_no, doc_date, ref_alloc_doc, plan_type, project_type, main_activity, sub_activity, fund_source, account_code, expense_budget, description, detail_desc, amount, recorded_by, file_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("iissssssssssssdss", $active_year, $auto_allocation_order, $doc_no, $doc_date, $ref_alloc_doc, $plan_type, $project_type, $main_activity, $sub_activity, $fund_source, $account_code, $expense_budget, $description, $detail_desc, $amount, $recorded_by, $file_name);
        $stmt->execute();
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $allocation_order = $_POST['allocation_order']; // คงค่าเดิม
        
        if ($file_name) {
            // ลบไฟล์เก่าถ้ามีการอัปโหลดใหม่
            $q = $conn->query("SELECT file_name FROM budget_allocations WHERE id=$id");
            $old = $q->fetch_assoc();
            if(!empty($old['file_name']) && file_exists("uploads/".$old['file_name'])){
                unlink("uploads/".$old['file_name']);
            }
            
            $sql_update = "UPDATE budget_allocations SET allocation_order=?, doc_no=?, doc_date=?, ref_alloc_doc=?, plan_type=?, project_type=?, main_activity=?, sub_activity=?, fund_source=?, account_code=?, expense_budget=?, description=?, detail_desc=?, amount=?, file_name=? WHERE id=?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("isssssssssssssi", $allocation_order, $doc_no, $doc_date, $ref_alloc_doc, $plan_type, $project_type, $main_activity, $sub_activity, $fund_source, $account_code, $expense_budget, $description, $detail_desc, $amount, $file_name, $id);
        } else {
            $sql_update = "UPDATE budget_allocations SET allocation_order=?, doc_no=?, doc_date=?, ref_alloc_doc=?, plan_type=?, project_type=?, main_activity=?, sub_activity=?, fund_source=?, account_code=?, expense_budget=?, description=?, detail_desc=?, amount=? WHERE id=?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("isssssssssssssi", $allocation_order, $doc_no, $doc_date, $ref_alloc_doc, $plan_type, $project_type, $main_activity, $sub_activity, $fund_source, $account_code, $expense_budget, $description, $detail_desc, $amount, $id);
        }
        $stmt->execute();
    }
    header("Location: Budgetallocation.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM budget_allocations WHERE budget_year = ? ORDER BY allocation_order ASC";
$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $active_year);
$stmt->execute();
$result_data = $stmt->get_result();

$sql_next = "SELECT MAX(allocation_order) as max_order FROM budget_allocations WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_allocation_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

$total_amount = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .warning-icon { color: #dc3545; margin-left: 5px; }
    .total-text { color: #d63384; font-weight: bold; }
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .btn-file { color: #6c757d; font-size: 1.1rem; border: none; background: none; cursor: pointer; transition: 0.2s;}
    .btn-file:hover { transform: scale(1.2); }
    .btn-file-active { color: #198754; font-size: 1.1rem; border: none; background: none; cursor: pointer; transition: 0.2s;}
    .btn-file-active:hover { transform: scale(1.2); }

    /* CSS สำหรับฟอร์มใน Modal ให้คงดีไซน์ขาวสะอาดตามเดิม */
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 6px 25px; background-color: #0d6efd; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #0b5ed7; color: #fff; }
    .btn-form-secondary { padding: 6px 25px; background-color: #6c757d; border: none; color: #fff; border-radius: 4px; font-size: 0.95rem; }
    .btn-form-secondary:hover { background-color: #5c636a; color: #fff; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div style="width: 100px;"></div> 
            <h2 class="page-title m-0">ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ (ปีงบประมาณ <?php echo $active_year; ?>)</h2>
            <button class="btn btn-add" onclick="checkAdminAction('add')">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มรายการ
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่ใบงวด</th>
                        <th style="width: 8%;">ว/ด/ป</th>
                        <th style="width: 10%;">หนังสือเลขที่</th>
                        <th style="width: 32%;">รายการ</th>
                        <th style="width: 12%;">จำนวนเงิน</th>
                        <th style="width: 5%;">รายละเอียด</th>
                        <th style="width: 5%;">File</th>
                        <th style="width: 5%;">ลบ</th>
                        <th style="width: 5%;">แก้ไข</th>
                        <th style="width: 5%;">พิมพ์</th>
                        <th style="width: 5%;">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            $has_file = !empty($row['file_name']);
                            echo "<tr>";
                            echo "<td class='td-center fw-bold'>" . $row['allocation_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no'] ?? '-') . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']) . " <i class='fa-solid fa-triangle-exclamation warning-icon'></i></td>";
                            echo "<td class='td-right fw-bold text-success'>" . number_format($row['amount'], 2) . "</td>";
                            
                            echo "<td class='td-center'>";
                            echo '<button class="action-btn" title="รายละเอียด" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-regular fa-rectangle-list"></i>
                                      </button>';
                            echo "</td>";

                            echo "<td class='td-center'>";
                            if ($has_file) {
                                echo '<a href="uploads/'.$row['file_name'].'" target="_blank" class="btn-file-active" title="ดาวน์โหลดไฟล์"><i class="fa-solid fa-arrow-up-from-bracket"></i></a>';
                            } else {
                                echo '<button class="btn-file" title="คลิกเพื่อแนบไฟล์" onclick=\'checkAdminAction("edit", '.json_encode($row).')\'>
                                            <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                      </button>';
                            }
                            echo "</td>";

                            echo "<td class='td-center'>";
                            echo '<a href="javascript:void(0)" onclick="checkAdminDelete('.$row['id'].')" class="action-btn btn-delete" title="ลบ"><i class="fa-solid fa-xmark"></i></a>';
                            echo "</td>";

                            echo "<td class='td-center'>";
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'checkAdminAction("edit", '.json_encode($row).')\'><i class="fa-solid fa-pen"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center'>";
                            echo '<button class="action-btn btn-print" title="พิมพ์" onclick="printItem('.$row['id'].')"><i class="fa-solid fa-print"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center total-text'>ถึงนี้</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'>";
                        echo "<td colspan='4' class='text-center'>รวม</td>";
                        echo "<td class='td-right text-success'>" . number_format($total_amount, 2) . "</td>";
                        echo "<td colspan='6'></td>";
                        echo "</tr>";
                    } else {
                        echo "<tr><td colspan='11' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลรายการในปี " . $active_year . "</td></tr>";
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
            <div class="modal-header d-block pb-2 border-bottom">
                <h5 class="modal-title-custom text-teal" id="modalTitle">เพิ่มรายการจัดสรรงบประมาณ</h5>
            </div>
            <div class="modal-body mx-3 my-3 pt-0">
                <div class="form-white-bg border-0 p-0 mt-3">
                    <form action="Budgetallocation.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ที่ใบงวด (อัตโนมัติ)</div>
                            <div class="col-md-3">
                                <input type="number" name="allocation_order" id="allocation_order" class="form-control form-control-sm" style="background-color: #f1f5f9; cursor: not-allowed;" readonly required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">วันที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">รายการ</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">อ้างถึงหนังสือจัดสรร</div>
                            <div class="col-md-4">
                                <input type="text" name="ref_alloc_doc" id="ref_alloc_doc" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">แผนงาน</div>
                            <div class="col-md-5">
                                <select name="plan_type" id="plan_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="แผนงานพื้นฐาน">แผนงานพื้นฐาน</option>
                                    <option value="แผนงานยุทธศาสตร์">แผนงานยุทธศาสตร์</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ผลผลิต/โครงการ</div>
                            <div class="col-md-8">
                                <select name="project_type" id="project_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="ผลผลิตที่ 1">ผลผลิตที่ 1</option>
                                    <option value="โครงการ A">โครงการ A</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">กิจกรรมหลัก</div>
                            <div class="col-md-8">
                                <select name="main_activity" id="main_activity" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="กิจกรรมที่ 1">กิจกรรมที่ 1</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-start">
                            <div class="col-md-4 form-label-custom mt-1">กิจกรรมหลักเพิ่มเติม</div>
                            <div class="col-md-6">
                                <textarea name="sub_activity" id="sub_activity" rows="2" class="form-control form-control-sm"></textarea>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">แหล่งของเงิน</div>
                            <div class="col-md-5">
                                <select name="fund_source" id="fund_source" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="เงินงบประมาณ">เงินงบประมาณ</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">รหัสทางบัญชี</div>
                            <div class="col-md-3">
                                <input type="text" name="account_code" id="account_code" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">งบรายจ่าย</div>
                            <div class="col-md-3">
                                <select name="expense_budget" id="expense_budget" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="งบบุคลากร">งบบุคลากร</option>
                                    <option value="งบดำเนินงาน">งบดำเนินงาน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-start">
                            <div class="col-md-4 form-label-custom mt-1">รายละเอียดเพิ่มเติม</div>
                            <div class="col-md-6">
                                <textarea name="detail_desc" id="detail_desc" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1 pt-1 text-start">บาท</div>
                        </div>

                        <div class="row mb-4 align-items-center">
                            <div class="col-md-4 form-label-custom">แนบไฟล์ (PDF/รูปภาพ)</div>
                            <div class="col-md-7">
                                <input type="file" name="file_upload" id="file_upload" class="form-control form-control-sm">
                                <small class="text-muted d-block mt-1">* อัปโหลดไฟล์เพื่อบันทึก (เลือกได้)</small>
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
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> ข้อมูลการจัดสรรงบประมาณอย่างละเอียด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body mx-3">
                <table class="table table-bordered table-sm mb-0 mt-2">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">ที่ใบงวด</th><td id="view_allocation_order"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">วันที่เอกสาร</th><td id="view_doc_date"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ที่เอกสาร</th><td id="view_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">อ้างถึงหนังสือจัดสรร</th><td id="view_ref_alloc_doc"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แผนงาน</th><td id="view_plan_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผลผลิต/โครงการ</th><td id="view_project_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลัก</th><td id="view_main_activity"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลักเพิ่มเติม</th><td id="view_sub_activity"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แหล่งของเงิน</th><td id="view_fund_source"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รหัสทางบัญชี</th><td id="view_account_code"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">งบรายจ่าย</th><td id="view_expense_budget"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการ</th><td id="view_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายละเอียดเพิ่มเติม</th><td id="view_detail_desc"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">จำนวนเงิน</th><td id="view_amount" class="text-danger fw-bold fs-6"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผู้บันทึกข้อมูล</th><td id="view_recorded_by"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ไฟล์แนบ</th><td id="view_file"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 pb-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const userRole = '<?php echo $_SESSION['role']; ?>';

    function checkAdminAction(action, data = null) {
        if (action === 'add') {
            openAddModal();
        } else {
            openEditModal(data);
        }
    }

    function checkAdminDelete(id) {
        if (confirm('คุณต้องการลบรายการนี้หรือไม่? (ไฟล์แนบจะถูกลบไปด้วย)')) {
            window.location.href = `?delete_id=${id}`;
        }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'เพิ่มรายการจัดสรรงบประมาณ';
        document.querySelector('#addModal form').reset();
        
        // กำหนดค่าที่ใบงวดอัตโนมัติสำหรับฟอร์มใหม่
        document.getElementById('allocation_order').value = '<?php echo $next_allocation_order; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไขรายการจัดสรร / แนบไฟล์เพิ่มเติม';
        
        document.getElementById('allocation_order').value = data.allocation_order || '';
        document.getElementById('doc_no').value = data.doc_no || '';
        document.getElementById('doc_date').value = data.doc_date || '';
        document.getElementById('ref_alloc_doc').value = data.ref_alloc_doc || '';
        document.getElementById('plan_type').value = data.plan_type || '';
        document.getElementById('project_type').value = data.project_type || '';
        document.getElementById('main_activity').value = data.main_activity || '';
        document.getElementById('sub_activity').value = data.sub_activity || '';
        document.getElementById('fund_source').value = data.fund_source || '';
        document.getElementById('account_code').value = data.account_code || '';
        document.getElementById('expense_budget').value = data.expense_budget || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('detail_desc').value = data.detail_desc || '';
        document.getElementById('amount').value = data.amount || '';
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_allocation_order').innerText = data.allocation_order || '-';
        document.getElementById('view_doc_no').innerText = data.doc_no || '-';
        document.getElementById('view_doc_date').innerText = data.doc_date || '-';
        document.getElementById('view_ref_alloc_doc').innerText = data.ref_alloc_doc || '-';
        document.getElementById('view_plan_type').innerText = data.plan_type || '-';
        document.getElementById('view_project_type').innerText = data.project_type || '-';
        document.getElementById('view_main_activity').innerText = data.main_activity || '-';
        document.getElementById('view_sub_activity').innerText = data.sub_activity || '-';
        document.getElementById('view_fund_source').innerText = data.fund_source || '-';
        document.getElementById('view_account_code').innerText = data.account_code || '-';
        document.getElementById('view_expense_budget').innerText = data.expense_budget || '-';
        document.getElementById('view_description').innerText = data.description || '-';
        document.getElementById('view_detail_desc').innerText = data.detail_desc || '-';
        document.getElementById('view_recorded_by').innerText = data.recorded_by || 'ไม่มีข้อมูลผู้บันทึก';
        document.getElementById('view_amount').innerText = parseFloat(data.amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        
        var fileArea = document.getElementById('view_file');
        if (data.file_name) {
            fileArea.innerHTML = `<a href="uploads/${data.file_name}" target="_blank" class="btn btn-success btn-sm py-0"><i class="fa-solid fa-download"></i> ดาวน์โหลดไฟล์แนบ</a>`;
        } else {
            fileArea.innerHTML = `<span class="text-muted"><i class="fa-solid fa-file-circle-xmark"></i> ไม่มีไฟล์แนบ</span>`;
        }
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    function printItem(id) {
        window.print();
    }
</script>