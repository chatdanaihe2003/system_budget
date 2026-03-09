<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "อนุมัติจ่ายเงินทดรองราชการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'อนุมัติจ่ายเงินทดรองราชการ';

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM approved_gov_advance_payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Approved for governmentadvancepayment.php");
    exit();
}

// 2. เปลี่ยนสถานะ (เลือกสถานะ เขียว/เหลือง/แดง)
if (isset($_GET['toggle_status_id']) && isset($_GET['set_status'])) {
    $id = $_GET['toggle_status_id'];
    $new_status = $_GET['set_status']; // ค่าที่ส่งมาคือ pending, approved, หรือ rejected
    
    $stmt = $conn->prepare("UPDATE approved_gov_advance_payments SET approval_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: Approved for governmentadvancepayment.php");
    exit();
}

// 3. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pay_order = $_POST['pay_order'];
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $ref_doc_no = $_POST['ref_doc_no'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];

    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE approved_gov_advance_payments SET advance_order=?, doc_date=?, doc_no=?, ref_doc_no=?, description=?, loan_amount=?, approval_status=? WHERE id=?");
        $stmt->bind_param("issssdsi", $pay_order, $doc_date, $doc_no, $ref_doc_no, $description, $amount, $status, $id);
        $stmt->execute();
    }
    header("Location: Approved for governmentadvancepayment.php");
    exit();
}

// --- การค้นหา ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = "";
if ($search != '') {
    $search_query = " AND (advance_order LIKE '%$search%' OR doc_no LIKE '%$search%') ";
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM approved_gov_advance_payments WHERE budget_year = ? $search_query ORDER BY advance_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .dropdown-item[href*="Approved for governmentadvancepayment.php"] {
        color: #000000 !important;   
        font-weight: bold !important;  
    }

    .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; cursor: pointer; border: 1px solid #ccc; transition: transform 0.1s; }
    .status-box:hover { transform: scale(1.2); }
    .status-yellow { background-color: #ffff00; }
    .status-green { background-color: #00ff00; }
    .status-red { background-color: #ff0000; }
    
    .form-white-bg { background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #495057; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #333; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <h2 class="page-title">อนุมัติจ่ายเงินทดรองราชการ ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="row mb-3">
            <div class="col-md-6">
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm w-50" placeholder="ค้นหา รหัส/เลขที่เอกสาร..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> ค้นหา</button>
                    <?php if($search != ''): ?>
                        <a href="Approved for governmentadvancepayment.php" class="btn btn-secondary btn-sm">ล้างค่า</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-6 text-end">
                </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">ที่เอกสาร</th>
                        <th style="width: 8%;">ที่อ้างอิง</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 5%;">รายละเอียด</th>
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
                            if($row['approval_status'] == 'approved') { $status_class = 'status-green'; $status_text = 'อนุมัติให้จ่ายเงินได้'; }
                            elseif($row['approval_status'] == 'rejected') { $status_class = 'status-red'; $status_text = 'ไม่อนุมัติ'; }

                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['advance_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-center text-warning'>" . htmlspecialchars($row['ref_doc_no'] ?: '') . "</td>"; 
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['loan_amount'], 2) . "</td>";
                            
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='checkAdminToggle(".$row['id'].", \"".$row['approval_status']."\")' title='".$status_text."'><div class='status-box ".$status_class."'></div></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='checkAdminAction(\"edit\", ".json_encode($row).")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center py-5 text-muted'>ไม่พบข้อมูลในปีงบประมาณ $active_year</td></tr>";
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
            <div class="modal-header d-block"><h5 class="modal-title-custom" id="modalTitle">รายการอนุมัติจ่ายเงินทดรองราชการ</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <form action="Approved for governmentadvancepayment.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="edit">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่</div><div class="col-md-3"><input type="number" name="pay_order" id="pay_order" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">วดป</div><div class="col-md-4"><input type="date" name="doc_date" id="doc_date" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่เอกสาร</div><div class="col-md-4"><input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">ที่อ้างอิง</div><div class="col-md-4"><input type="text" name="ref_doc_no" id="ref_doc_no" class="form-control form-control-sm"></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">รายการ</div><div class="col-md-9"><input type="text" name="description" id="description" class="form-control form-control-sm" required></div></div>
                        <div class="row mb-3"><div class="col-md-3 form-label-custom">จำนวนเงิน</div><div class="col-md-4"><input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required></div></div>
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
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">สถานะ :</div><div class="col-md-9" id="view_status"></div></div>
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
    let currentStatusId = null; 

    function checkAdminAction(action, data = null) {
        if (action === 'edit') openEditModal(data);
    }

    function checkAdminToggle(id, currentStatus) {
        currentStatusId = id; 
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    function submitSetStatus(newStatus) {
        if (currentStatusId !== null) {
            window.location.href = `?toggle_status_id=${currentStatusId}&set_status=${newStatus}`;
        }
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('pay_order').value = data.advance_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('ref_doc_no').value = data.ref_doc_no;
        document.getElementById('description').value = data.description;
        document.getElementById('amount').value = data.loan_amount;
        document.getElementById('status').value = data.approval_status;
        document.getElementById('modalTitle').innerText = 'แก้ไขรายการอนุมัติจ่ายเงินทดรองราชการ';
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.loan_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        let statusText = 'รอการอนุมัติ';
        if(data.approval_status == 'approved') statusText = 'อนุมัติแล้ว';
        else if(data.approval_status == 'rejected') statusText = 'ไม่อนุมัติ';
        document.getElementById('view_status').innerText = statusText;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>