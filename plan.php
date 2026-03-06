<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดแผนงาน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการ แผนงาน <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM plans WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: plan.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $plan_code = $_POST['plan_code'];
    $plan_name = $_POST['plan_name'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO plans (budget_year, plan_code, plan_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $budget_year, $plan_code, $plan_name);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE plans SET budget_year=?, plan_code=?, plan_name=? WHERE id=?");
        $stmt->bind_param("issi", $budget_year, $plan_code, $plan_name, $id);
        $stmt->execute();
    }
    header("Location: plan.php");
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา (กรองตาม Active Year) ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    $sql_plans = "SELECT * FROM plans WHERE plan_code LIKE ? AND budget_year = ? ORDER BY plan_code ASC";
    $stmt = $conn->prepare($sql_plans);
    $stmt->bind_param("si", $search_param, $active_year);
    $stmt->execute();
    $result_plans = $stmt->get_result();
} else {
    $sql_plans = "SELECT * FROM plans WHERE budget_year = ? ORDER BY plan_code ASC";
    $stmt = $conn->prepare($sql_plans);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_plans = $stmt->get_result();
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 text-start">กำหนดแผนงาน</h2>
            
            <div class="d-flex align-items-center">
                <form action="plan.php" method="GET" class="d-flex me-2">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหา รหัสแผนงาน..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search): ?>
                        <a href="plan.php" class="btn btn-outline-danger ms-1 d-flex align-items-center justify-content-center" title="ล้างการค้นหา"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 60px;">ที่</th>
                        <th style="width: 120px;">ปีงบประมาณ</th>
                        <th style="width: 100px;">รหัส</th>
                        <th>ชื่อแผนงาน</th>
                        <th style="width: 120px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_plans->num_rows > 0) {
                        $i = 1;
                        while($row = $result_plans->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . $row['budget_year'] . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['plan_code']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['plan_name']) . "</td>";
                            echo "<td class='td-center'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบแผนงานรหัส '.$row['plan_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลแผนงาน ในปี $active_year</td></tr>";
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
            <form action="plan.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-folder-plus"></i> เพิ่มแผนงานใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                        <input type="text" name="budget_year" class="form-control" value="<?php echo $active_year; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัสแผนงาน</label>
                        <input type="text" name="plan_code" class="form-control" placeholder="เช่น 001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อแผนงาน <span class="text-danger">*</span></label>
                        <textarea name="plan_name" class="form-control" rows="3" required></textarea>
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
            <form action="plan.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลแผนงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                        <input type="text" name="budget_year" id="edit_budget_year" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัสแผนงาน</label>
                        <input type="text" name="plan_code" id="edit_plan_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อแผนงาน <span class="text-danger">*</span></label>
                        <textarea name="plan_name" id="edit_plan_name" class="form-control" rows="3" required></textarea>
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
        document.getElementById('edit_budget_year').value = data.budget_year;
        document.getElementById('edit_plan_code').value = data.plan_code;
        document.getElementById('edit_plan_name').value = data.plan_name;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>