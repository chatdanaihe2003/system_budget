<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ประเภทรายจ่าย ";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการ ประเภทรายจ่าย';

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM expense_types WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Expensesbudget.php?status=deleted");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $expense_code = $_POST['expense_code'];
    $expense_name = $_POST['expense_name'];
    $budget_category = $_POST['budget_category'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO expense_types (expense_code, expense_name, budget_category) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $expense_code, $expense_name, $budget_category);
        $stmt->execute();
        header("Location: Expensesbudget.php?status=added");
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE expense_types SET expense_code=?, expense_name=?, budget_category=? WHERE id=?");
        $stmt->bind_param("sssi", $expense_code, $expense_name, $budget_category, $id);
        $stmt->execute();
        header("Location: Expensesbudget.php?status=updated");
    }
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    // ถ้ามีการค้นหา ให้กรองด้วย expense_code
    $search_param = "%" . $search . "%";
    $sql_expenses = "SELECT * FROM expense_types WHERE expense_code LIKE ? ORDER BY expense_code ASC";
    $stmt = $conn->prepare($sql_expenses);
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result_expenses = $stmt->get_result();
} else {
    // ถ้าไม่มีการค้นหา ให้ดึงทั้งหมดตามปกติ
    $sql_expenses = "SELECT * FROM expense_types ORDER BY expense_code ASC";
    $result_expenses = $conn->query($sql_expenses);
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* CSS เพิ่มเติมเฉพาะหน้านี้ */
    .info-box {
        background-color: #f8f9fa;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        margin-bottom: 25px;
        border-radius: 4px;
        color: #555;
        font-size: 0.95rem;
        line-height: 1.6;
    }
</style>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="page-title m-0">ประเภทรายจ่าย</h2>
            
            <div class="d-flex align-items-center">
                <form action="Expensesbudget.php" method="GET" class="d-flex me-2">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหารหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Expensesbudget.php" class="btn btn-outline-danger ms-1 d-flex align-items-center justify-content-center"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <button class="btn btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal" style="border-radius: 8px;">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
                </button>
            </div>
        </div>

        <div class="info-box shadow-sm" style="border-radius: 8px;">
            <i class="fa-solid fa-circle-info me-2 text-info"></i>
            หน้านี้ เป็นการกำหนดประเภทรายการจ่าย เช่น เงินเดือน, ค่าจ้างประจำ, ค่าวัสดุ ฯลฯ พร้อมระบุหมวดงบรายจ่ายที่เกี่ยวข้อง
            <br>
            <small class="text-muted ms-4">สามารถเพิ่ม ลบ แก้ไข ข้อมูลได้ตามต้องการ</small>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover table-custom mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 text-center" style="width: 60px;">ที่</th>
                        <th class="py-3 text-center" style="width: 100px;">รหัส</th>
                        <th class="py-3">ประเภทรายการจ่าย</th>
                        <th class="py-3" style="width: 250px;">งบรายจ่าย</th>
                        <th class="py-3 text-center" style="width: 120px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_expenses->num_rows > 0) {
                        $i = 1;
                        while($row = $result_expenses->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                            echo "<td class='text-center fw-bold'>" . htmlspecialchars($row['expense_code']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['expense_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['budget_category']) . "</td>";

                            // ปุ่มจัดการ
                            echo "<td class='text-center'>";
                            // เปลี่ยนเป็นปุ่ม Button เรียก Popup แทน confirm
                            echo '<button type="button" class="action-btn btn-delete border-0 bg-transparent text-danger px-2" onclick="openDeleteModal('.$row['id'].', \''.htmlspecialchars($row['expense_code'], ENT_QUOTES, 'UTF-8').'\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></button>';
                            
                            echo '<button type="button" class="action-btn btn-edit border-0 bg-transparent text-warning px-2" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                      </button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='fa-regular fa-folder-open fs-2 mb-2 d-block opacity-50'></i>ยังไม่มีข้อมูลประเภทรายจ่าย</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <form action="Expensesbudget.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white" style="border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-folder-plus me-2"></i> เพิ่มข้อมูลประเภทรายจ่ายใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="expense_code" class="form-control form-control-lg" placeholder="เช่น 110">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายการจ่าย <span class="text-danger">*</span></label>
                        <input type="text" name="expense_name" class="form-control form-control-lg" placeholder="เช่น เงินเดือน" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">งบรายจ่าย</label>
                        <input type="text" name="budget_category" class="form-control form-control-lg" placeholder="เช่น งบดำเนินงาน (ไม่บังคับ)">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" style="border-radius: 8px;"><i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <form action="Expensesbudget.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header bg-warning text-dark" style="border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขข้อมูลประเภทรายจ่าย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="expense_code" id="edit_expense_code" class="form-control form-control-lg">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายการจ่าย <span class="text-danger">*</span></label>
                        <input type="text" name="expense_name" id="edit_expense_name" class="form-control form-control-lg" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">งบรายจ่าย</label>
                        <input type="text" name="budget_category" id="edit_budget_category" class="form-control form-control-lg" placeholder="เช่น งบดำเนินงาน (ไม่บังคับ)">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4" style="border-radius: 8px;"><i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการลบข้อมูล</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบรายการรหัส <span id="display_delete_code" class="text-danger"></span> หรือไม่?</h4>
                <div class="alert alert-warning mt-4 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> หากลบแล้วจะไม่สามารถกู้คืนข้อมูลได้
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;">
                    <i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4" id="successActionMessage">ทำรายการสำเร็จ</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='Expensesbudget.php'">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ตรวจสอบ URL ว่ามีการทำรายการสำเร็จหรือไม่ ถ้าสำเร็จให้แสดง Popup แจ้งเตือน
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            let msg = '';
            if (status === 'deleted') msg = 'ลบข้อมูลประเภทรายจ่ายเรียบร้อยแล้ว';
            if (status === 'added') msg = 'เพิ่มข้อมูลประเภทรายจ่ายเรียบร้อยแล้ว';
            if (status === 'updated') msg = 'แก้ไขข้อมูลประเภทรายจ่ายเรียบร้อยแล้ว';
            
            if (msg !== '') {
                document.getElementById('successActionMessage').innerText = msg;
                new bootstrap.Modal(document.getElementById('successActionModal')).show();
                // ลบพารามิเตอร์ status ออกจาก URL เพื่อไม่ให้ Popup เด้งซ้ำเมื่อ Refresh
                window.history.replaceState(null, null, window.location.pathname);
            }
        }
    });

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_expense_code').value = data.expense_code;
        document.getElementById('edit_expense_name').value = data.expense_name;
        document.getElementById('edit_budget_category').value = data.budget_category;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }

    // ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูล
    function openDeleteModal(id, code) {
        // นำรหัสมาแสดงใน Popup
        document.getElementById('display_delete_code').innerText = code;
        
        // ตั้งค่าลิงก์ไปยังปุ่มลบจริง
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        
        // เปิด Modal
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>