<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ประเภทรายจ่าย - AMSS++";
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
    header("Location: Expensesbudget.php");
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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE expense_types SET expense_code=?, expense_name=?, budget_category=? WHERE id=?");
        $stmt->bind_param("sssi", $expense_code, $expense_name, $budget_category, $id);
        $stmt->execute();
    }
    header("Location: Expensesbudget.php");
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

                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
                </button>
            </div>
        </div>

        <div class="info-box">
            <i class="fa-solid fa-circle-info me-2"></i>
            หน้านี้ เป็นการกำหนดประเภทรายการจ่าย เช่น เงินเดือน, ค่าจ้างประจำ, ค่าวัสดุ ฯลฯ พร้อมระบุหมวดงบรายจ่ายที่เกี่ยวข้อง
            <br>
            <small class="text-muted">สามารถเพิ่ม ลบ แก้ไข ข้อมูลได้ตามต้องการ</small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 60px;">ที่</th>
                        <th style="width: 100px;">รหัส</th>
                        <th>ประเภทรายการจ่าย</th>
                        <th style="width: 250px;">งบรายจ่าย</th>
                        <th style="width: 120px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_expenses->num_rows > 0) {
                        $i = 1;
                        while($row = $result_expenses->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . $row['expense_code'] . "</td>";
                            echo "<td class='td-left'>" . $row['expense_name'] . "</td>";
                            echo "<td class='td-left'>" . $row['budget_category'] . "</td>";

                            // ปุ่มจัดการ
                            echo "<td class='td-center'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['expense_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                      </button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลประเภทรายจ่าย</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="Expensesbudget.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-folder-plus"></i> เพิ่มข้อมูลใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="expense_code" class="form-control" placeholder="เช่น 110">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายการจ่าย <span class="text-danger">*</span></label>
                        <input type="text" name="expense_name" class="form-control" placeholder="เช่น เงินเดือน" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">งบรายจ่าย <span class="text-danger">*</span></label>
                        <select name="budget_category" class="form-select" required>
                            <option value="" selected disabled>-- เลือกงบรายจ่าย --</option>
                            <option value="งบบุคลากร">งบบุคลากร</option>
                            <option value="งบดำเนินงาน">งบดำเนินงาน</option>
                            <option value="งบลงทุน">งบลงทุน</option>
                            <option value="งบเงินอุดหนุน">งบเงินอุดหนุน</option>
                            <option value="งบรายจ่ายอื่น">งบรายจ่ายอื่น</option>
                            <option value="งบกลาง">งบกลาง</option>
                            <option value="งบอื่นๆ">งบอื่นๆ</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="Expensesbudget.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="expense_code" id="edit_expense_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายการจ่าย <span class="text-danger">*</span></label>
                        <input type="text" name="expense_name" id="edit_expense_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">งบรายจ่าย <span class="text-danger">*</span></label>
                        <select name="budget_category" id="edit_budget_category" class="form-select" required>
                            <option value="งบบุคลากร">งบบุคลากร</option>
                            <option value="งบดำเนินงาน">งบดำเนินงาน</option>
                            <option value="งบลงทุน">งบลงทุน</option>
                            <option value="งบเงินอุดหนุน">งบเงินอุดหนุน</option>
                            <option value="งบรายจ่ายอื่น">งบรายจ่ายอื่น</option>
                            <option value="งบกลาง">งบกลาง</option>
                            <option value="งบอื่นๆ">งบอื่นๆ</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_expense_code').value = data.expense_code;
        document.getElementById('edit_expense_name').value = data.expense_name;
        document.getElementById('edit_budget_category').value = data.budget_category;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>