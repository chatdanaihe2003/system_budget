<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนรับเงินรายได้แผ่นดิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ทะเบียนรับเงินรายได้แผ่นดิน';

// --- ตรวจสอบและสร้างคอลัมน์เพิ่มเติมในฐานข้อมูลให้อัตโนมัติ (เพื่อให้ตรงตามรูป) ---
$columns_to_add = [
    'money_type' => "VARCHAR(255) NULL AFTER doc_no",
    'officer_name' => "VARCHAR(255) NULL AFTER amount"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM receive_national LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE receive_national ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD & Upload)
// --------------------------------------------------------------------------------

// --- สร้างโฟลเดอร์ uploads อัตโนมัติ ---
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ลบไฟล์จริง
    $sql_file = "SELECT file_name FROM receive_national WHERE id = ?";
    $stmt_file = $conn->prepare($sql_file);
    $stmt_file->bind_param("i", $id);
    $stmt_file->execute();
    $res_file = $stmt_file->get_result();
    if ($row = $res_file->fetch_assoc()) {
        if (!empty($row['file_name']) && file_exists("uploads/" . $row['file_name'])) {
            unlink("uploads/" . $row['file_name']);
        }
    }

    $stmt = $conn->prepare("DELETE FROM receive_national WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Receivenational.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $money_type = $_POST['money_type']; // เพิ่มใหม่
    $description = $_POST['description'];
    $transaction_type = $_POST['transaction_type'];
    $amount = $_POST['amount'];
    $officer_name = $_POST['officer_name']; // เพิ่มใหม่
    
    $file_name = null;
    
    // อัปโหลดไฟล์
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $ext = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid() . "_" . time() . "." . $ext; 
        if(move_uploaded_file($_FILES['file_upload']['tmp_name'], "uploads/" . $new_name)){
            $file_name = $new_name;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $sql_max = "SELECT MAX(receive_order) as max_order FROM receive_national WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $auto_receive_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO receive_national (budget_year, receive_order, doc_date, doc_no, money_type, description, transaction_type, amount, officer_name, file_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssdss", $active_year, $auto_receive_order, $doc_date, $doc_no, $money_type, $description, $transaction_type, $amount, $officer_name, $file_name);
        $stmt->execute();
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $receive_order = $_POST['receive_order'];
        
        if ($file_name) {
            $q = $conn->query("SELECT file_name FROM receive_national WHERE id=$id");
            $old = $q->fetch_assoc();
            if(!empty($old['file_name']) && file_exists("uploads/".$old['file_name'])){
                unlink("uploads/".$old['file_name']);
            }
            $stmt = $conn->prepare("UPDATE receive_national SET receive_order=?, doc_date=?, doc_no=?, money_type=?, description=?, transaction_type=?, amount=?, officer_name=?, file_name=? WHERE id=?");
            $stmt->bind_param("isssssdssi", $receive_order, $doc_date, $doc_no, $money_type, $description, $transaction_type, $amount, $officer_name, $file_name, $id);
        } else {
            $stmt = $conn->prepare("UPDATE receive_national SET receive_order=?, doc_date=?, doc_no=?, money_type=?, description=?, transaction_type=?, amount=?, officer_name=? WHERE id=?");
            $stmt->bind_param("isssssdsi", $receive_order, $doc_date, $doc_no, $money_type, $description, $transaction_type, $amount, $officer_name, $id);
        }
        $stmt->execute();
    }
    header("Location: Receivenational.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM receive_national WHERE budget_year = ? ORDER BY receive_order ASC";
$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $active_year);
$stmt->execute();
$result_data = $stmt->get_result();

$sql_next = "SELECT MAX(receive_order) as max_order FROM receive_national WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_receive_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

$total_amount = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #fff3cd !important; font-weight: bold; color: #333; }
    .total-text { color: #d63384; font-weight: bold; }
    .warning-icon { color: #dc3545; margin-left: 5px; }
    .btn-file { color: #198754; font-size: 1.1rem; border: none; background: none; cursor: pointer; transition: 0.2s; }
    .btn-file:hover { transform: scale(1.2); }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div style="width: 100px;"></div> 
            <h2 class="page-title m-0">ทะเบียนรับเงินรายได้แผ่นดิน (ปีงบประมาณ <?php echo $active_year; ?>)</h2>
            <button class="btn btn-add" onclick="checkAdminAction('add')">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มรายการรับ
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่/งวด</th>
                        <th style="width: 8%;">ว/ด/ป</th>
                        <th style="width: 10%;">ที่เอกสาร</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 15%;">ลักษณะรายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 5%;">รายละเอียด</th>
                        <th style="width: 4%;">File</th>
                        <th style="width: 4%;">ลบ</th>
                        <th style="width: 4%;">แก้ไข</th>
                        <th style="width: 4%;">พิมพ์</th>
                        <th style="width: 4%;">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            $has_file = !empty($row['file_name']);
                            $desc_text = htmlspecialchars($row['description']);
                            $is_synced_data = (strpos($desc_text, '(ตัดจ่าย)') !== false || strpos($desc_text, 'รับคืนเงินยืมโครงการ') !== false);
                            $is_deduct = (strpos($desc_text, '(ตัดจ่าย)') !== false);
                            
                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['receive_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            
                            if ($is_deduct || $row['amount'] < 0) {
                                echo "<td class='td-left' style='color: red;'>" . $desc_text . " <i class='fa-solid fa-triangle-exclamation'></i></td>";
                                echo "<td class='td-center' style='color: red;'>" . htmlspecialchars($row['transaction_type']) . "</td>";
                                echo "<td class='td-right' style='color: red; font-weight: bold;'>" . number_format($row['amount'], 2) . "</td>";
                            } else {
                                echo "<td class='td-left'>" . $desc_text . " <i class='fa-solid fa-triangle-exclamation warning-icon'></i></td>";
                                echo "<td class='td-center'>" . htmlspecialchars($row['transaction_type']) . "</td>";
                                echo "<td class='td-right text-success fw-bold'>" . number_format($row['amount'], 2) . "</td>";
                            }
                            
                            echo "<td class='td-center'><button class='action-btn' title='รายละเอียด' onclick='openDetailModal(" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")'><i class='fa-regular fa-rectangle-list'></i></button></td>";
                            echo "<td class='td-center'>" . ($is_synced_data ? "-" : ($has_file ? "<a href='uploads/".$row['file_name']."' target='_blank' class='btn-file'><i class='fa-solid fa-arrow-up-from-bracket'></i></a>" : "<button class='action-btn' style='color:#ccc;' onclick='checkAdminAction(\"edit\", ".json_encode($row).")'><i class='fa-solid fa-arrow-up-from-bracket'></i></button>")) . "</td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='checkAdminDelete(" . $row['id'] . ")' class='action-btn btn-delete' title='ลบ'><i class='fa-solid fa-xmark'></i></a></td>";
                            echo "<td class='td-center'>" . ($is_synced_data ? "<span class='text-muted' style='font-size:0.8rem;'>-</span>" : "<button class='action-btn btn-edit' title='แก้ไข' onclick='checkAdminAction(\"edit\", " . json_encode($row) . ")'><i class='fa-solid fa-pen-to-square'></i></button>") . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-print' title='พิมพ์' onclick='printItem(" . $row['id'] . ")'><i class='fa-solid fa-print'></i></button></td>";
                            echo "<td class='td-center total-text'>ถึงนี้</td>";
                            echo "</tr>";
                        }
                        echo "<tr class='total-row'><td colspan='5' class='text-center'>รวม</td><td class='td-right'>" . number_format($total_amount, 2) . "</td><td colspan='6'></td></tr>";
                    } else {
                        echo "<tr><td colspan='12' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลรายการในปี " . $active_year . "</td></tr>";
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
            <form action="Receivenational.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มข้อมูลการรับเงินรายได้แผ่นดิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="receive_order" id="receive_order">
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">วันที่เอกสาร</label></div>
                        <div class="col-md-6"><input type="date" name="doc_date" id="doc_date" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">ที่เอกสาร</label></div>
                        <div class="col-md-6"><input type="text" name="doc_no" id="doc_no" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">ประเภทของเงิน</label></div>
                        <div class="col-md-6">
                            <select name="money_type" id="money_type" class="form-select">
                                <option value="">เลือก</option>
                                <option value="340 รายได้เบ็ดเตล็ดอื่น">340 รายได้เบ็ดเตล็ดอื่น</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">รายการ</label></div>
                        <div class="col-md-8"><input type="text" name="description" id="description" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">ลักษณะรายการ</label></div>
                        <div class="col-md-6">
                            <select name="transaction_type" id="transaction_type" class="form-select" required>
                                <option value="">เลือก</option>
                                <option value="เงินสด">เงินสด</option>
                                <option value="รับเช็ค/เงินฝากธนาคาร">รับเช็ค/เงินฝากธนาคาร</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">จำนวนเงิน</label></div>
                        <div class="col-md-4"><input type="number" step="0.01" name="amount" id="amount" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">เจ้าหน้าที่</label></div>
                        <div class="col-md-6"><input type="text" name="officer_name" id="officer_name" class="form-control" value="<?php echo $_SESSION['name'] ?? ''; ?>"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-end"><label class="fw-bold">แนบไฟล์</label></div>
                        <div class="col-md-6"><input type="file" name="file_upload" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="submit" class="btn btn-secondary px-4">ตกลง</button>
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">ย้อนกลับ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">รายละเอียดรายการรับเงินรายได้แผ่นดิน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">วันที่เอกสาร :</div>
                            <div class="col-8" id="det_date"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">ที่เอกสาร :</div>
                            <div class="col-8" id="det_no"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">ประเภทของเงิน :</div>
                            <div class="col-8" id="det_money"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">รายการ :</div>
                            <div class="col-8" id="det_desc"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">ลักษณะรายการ :</div>
                            <div class="col-8" id="det_trans"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">จำนวนเงิน :</div>
                            <div class="col-8" id="det_amount"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">เจ้าหน้าที่ :</div>
                            <div class="col-8" id="det_officer"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-end fw-bold">ไฟล์แนบ :</div>
                            <div class="col-8" id="det_file"></div>
                        </div>
                    </div>
                </div>
                <div id="sync_warning" class="text-danger mt-3 text-center" style="display:none;">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <span id="sync_message"></span>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><< กลับหน้าก่อน</button>
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
        if (confirm('คุณต้องการลบรายการนี้หรือไม่?')) { window.location.href = `?delete_id=${id}`; }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerText = 'เพิ่มข้อมูลการรับเงินรายได้แผ่นดิน ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        document.getElementById('receive_order').value = '<?php echo $next_receive_order; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerText = 'แก้ไขรายการ';
        document.getElementById('receive_order').value = data.receive_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('money_type').value = data.money_type || '';
        document.getElementById('description').value = data.description;
        document.getElementById('transaction_type').value = data.transaction_type;
        document.getElementById('amount').value = data.amount;
        document.getElementById('officer_name').value = data.officer_name || '';
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('det_date').innerText = data.doc_date;
        document.getElementById('det_no').innerText = data.doc_no;
        document.getElementById('det_money').innerText = data.money_type || '-';
        document.getElementById('det_desc').innerText = data.description;
        document.getElementById('det_trans').innerText = data.transaction_type;
        document.getElementById('det_officer').innerText = data.officer_name || '-';
        
        let amount = parseFloat(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
        document.getElementById('det_amount').innerText = amount + " บาท";
        document.getElementById('det_amount').style.color = (data.description.includes('(ตัดจ่าย)') || data.amount < 0) ? "red" : "#198754";

        document.getElementById('det_file').innerHTML = data.file_name ? `<a href="uploads/${data.file_name}" target="_blank" class="btn btn-sm btn-success">ดูไฟล์แนบ</a>` : '-';

        let warningBox = document.getElementById('sync_warning');
        warningBox.style.display = 'none';
        if (data.description.includes('(ตัดจ่าย)')) {
            document.getElementById('sync_message').innerText = "ข้อมูลนี้ถูกดึงมาจากหน้า ทะเบียนสั่งจ่ายเงินรายได้แผ่นดิน";
            warningBox.style.display = 'block';
        }
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    function printItem(id) { window.print(); }
</script>