<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนยกเลิกฎีกา - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนยกเลิกฎีกา';

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM treasury_refunds WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Withdrawtheappeal.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $refund_date = $_POST['doc_date'] ?? date('Y-m-d'); // เปลี่ยนชื่อตัวแปรให้ตรงกับ DB
    $doc_no = $_POST['doc_no'] ?? '';        // เลขที่เอกสารอ้างอิง
    $period_no = $_POST['period_no'] ?? '';  // เลขฎีกา
    $description = $_POST['description'] ?? ''; // สาเหตุการยกเลิก

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ลำดับถัดไปอัตโนมัติก่อนบันทึก
        $sql_max = "SELECT MAX(refund_order) as max_order FROM treasury_refunds WHERE budget_year = ? AND ref_type = 'cancel'";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $refund_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        // เพิ่มคอลัมน์ ref_type = 'cancel' เพื่อแยกประเภท
        $stmt = $conn->prepare("INSERT INTO treasury_refunds (budget_year, refund_order, refund_date, doc_no, period_no, description, ref_type) VALUES (?, ?, ?, ?, ?, ?, 'cancel')");
        $stmt->bind_param("iissss", $active_year, $refund_order, $refund_date, $doc_no, $period_no, $description);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $refund_order = $_POST['refund_order'];
        $stmt = $conn->prepare("UPDATE treasury_refunds SET refund_order=?, refund_date=?, doc_no=?, period_no=?, description=? WHERE id=?");
        $stmt->bind_param("issssi", $refund_order, $refund_date, $doc_no, $period_no, $description, $id);
        $stmt->execute();
    }
    header("Location: Withdrawtheappeal.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active และประเภทที่เป็นการยกเลิก (cancel) ---
$sql_data = "SELECT * FROM treasury_refunds WHERE budget_year = ? AND ref_type = 'cancel' ORDER BY refund_order ASC";
$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $active_year);
$stmt->execute();
$result_data = $stmt->get_result();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ปุ่มเพิ่มข้อมูลสีน้ำเงินเข้มตามรูปแบบที่คุณต้องการ */
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

    /* พื้นหลัง Modal สีขาวสะอาด */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    
    .btn-form { padding: 4px 20px; background-color: #e9ecef; border: 1px solid #ccc; color: #333; border-radius: 4px; font-size: 0.9rem; }
    .btn-form:hover { background-color: #d3d9df; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <h2 class="page-title">ทะเบียนยกเลิกฎีกา ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="d-flex align-items-center mb-3 justify-content-end">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">ว/ด/ป</th>
                        <th style="width: 10%;">ฎีกา</th>
                        <th style="width: 20%;">เลขที่เอกสารอ้างอิง</th>
                        <th style="width: 45%;">สาเหตุการยกเลิก</th>
                        <th style="width: 10%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            // ป้องกัน Warning ด้วยการตรวจสอบชื่อคอลัมน์ที่หลากหลาย
                            $display_date = $row['refund_date'] ?? $row['doc_date'] ?? date('Y-m-d');
                            $display_period = $row['period_no'] ?? '';

                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['refund_order'] ?? '0') . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($display_date) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($display_period) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no'] ?? '') . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description'] ?? '') . "</td>";
                            
                            echo "<td class='td-center text-nowrap'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลการยกเลิกฎีกาในปี $active_year</td></tr>";
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
            <div class="modal-header d-block pb-0 border-0">
                <h5 class="modal-title-custom text-teal" id="modalTitle">ยกเลิกฎีกา ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-4 mb-4">
                <div class="form-white-bg">
                    <form action="Withdrawtheappeal.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <input type="hidden" name="refund_order" id="refund_order">
                        <input type="hidden" name="doc_date" id="doc_date" value="<?php echo date('Y-m-d'); ?>">

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">เลขที่ฎีกา</div>
                            <div class="col-md-2">
                                <input type="text" name="period_no" id="period_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">ที่เอกสารอ้างอิง</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 form-label-custom">สาเหตุการยกเลิก</div>
                            <div class="col-md-9">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-form me-2">ตกลง</button>
                            <button type="button" class="btn btn-form" data-bs-dismiss="modal">ย้อนกลับ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'ยกเลิกฎีกา ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        
        // รีเซ็ตวันที่ให้เป็นปัจจุบันสำหรับรายการใหม่
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไข ข้อมูลยกเลิกฎีกา';
        
        document.getElementById('refund_order').value = data.refund_order || '';
        document.getElementById('doc_date').value = data.refund_date || data.doc_date;
        document.getElementById('period_no').value = data.period_no || '';
        document.getElementById('doc_no').value = data.doc_no || '';
        document.getElementById('description').value = data.description || '';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }
</script>