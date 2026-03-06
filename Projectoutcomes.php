<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดผลผลิตโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'โครงการตามแผนปฎิบัติการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- ตรวจสอบและสร้างคอลัมน์ใหม่ในฐานข้อมูลให้อัตโนมัติ ---
$columns_to_add = [
    'budget_amount' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER project_name",
    'allocation_1' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER budget_amount",
    'allocation_2' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_1",
    'allocation_3' => "DECIMAL(15,2) NULL DEFAULT 0 AFTER allocation_2"
];

foreach ($columns_to_add as $col_name => $col_definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM project_outcomes LIKE '$col_name'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE project_outcomes ADD $col_name $col_definition");
    }
}

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM project_outcomes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Projectoutcomes.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $project_code = $_POST['project_code'];
    $project_name = $_POST['project_name'];
    $budget_amount = $_POST['budget_amount'] ?? 0;
    $allocation_1 = $_POST['allocation_1'] ?? 0;
    $allocation_2 = $_POST['allocation_2'] ?? 0;
    $allocation_3 = $_POST['allocation_3'] ?? 0;

    // --- ตรวจสอบว่ายอดจัดสรรเกินงบประมาณหรือไม่ (Server-side validation) ---
    $total_allocation = $allocation_1 + $allocation_2 + $allocation_3;
    if ($total_allocation > $budget_amount) {
        echo "<script>alert('จัดสรรไม่ได้ เกินงบประมาณที่กำหนดมา'); window.history.back();</script>";
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO project_outcomes (budget_year, project_code, project_name, budget_amount, allocation_1, allocation_2, allocation_3) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdddd", $budget_year, $project_code, $project_name, $budget_amount, $allocation_1, $allocation_2, $allocation_3);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE project_outcomes SET budget_year=?, project_code=?, project_name=?, budget_amount=?, allocation_1=?, allocation_2=?, allocation_3=? WHERE id=?");
        $stmt->bind_param("issddddi", $budget_year, $project_code, $project_name, $budget_amount, $allocation_1, $allocation_2, $allocation_3, $id);
        $stmt->execute();
    }
    header("Location: Projectoutcomes.php");
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา (กรองตาม Active Year) ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    $sql_projects = "SELECT * FROM project_outcomes WHERE project_code LIKE ? AND budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql_projects);
    $stmt->bind_param("si", $search_param, $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
} else {
    $sql_projects = "SELECT * FROM project_outcomes WHERE budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql_projects);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .action-container { display: flex; justify-content: center; gap: 8px; }
</style>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="page-title m-0">โครงการตามแผนปฎิบัติการ</h2>
            
            <div class="d-flex align-items-center">
                <form action="Projectoutcomes.php" method="GET" class="d-flex me-2">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหารหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Projectoutcomes.php" class="btn btn-outline-danger ms-1 d-flex align-items-center justify-content-center"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มข้อมูล
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">ปีงบประมาณ</th>
                        <th style="width: 15%;">รหัส</th>
                        <th style="width: 20%;">ชื่อผลผลิต/โครงการ</th>
                        <th style="width: 10%;">เงินงบประมาณ<br>โครงการ</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 1</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 2</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 3</th>
                        <th style="width: 9%;">ยอดคงเหลือ</th>
                        <th style="width: 6%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_budget = 0;
                    if ($result_projects->num_rows > 0) {
                        $i = 1;
                        while($row = $result_projects->fetch_assoc()) {
                            $total_budget += $row['budget_amount'];
                            
                            // คำนวณยอดคงเหลือ
                            $remaining_balance = $row['budget_amount'] - ($row['allocation_1'] + $row['allocation_2'] + $row['allocation_3']);

                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['project_code']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['project_name']) . "</td>";
                            echo "<td class='td-right text-success fw-bold'>" . ($row['budget_amount'] > 0 ? number_format($row['budget_amount'], 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($row['allocation_1'] > 0 ? number_format($row['allocation_1'], 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($row['allocation_2'] > 0 ? number_format($row['allocation_2'], 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($row['allocation_3'] > 0 ? number_format($row['allocation_3'], 2) : '-') . "</td>";
                            echo "<td class='td-right text-primary fw-bold'>" . number_format($remaining_balance, 2) . "</td>"; // แสดงยอดคงเหลือ
                            
                            // รวมปุ่มจัดการให้อยู่ในช่องเดียวกัน
                            echo "<td class='td-center'>";
                            echo "<div class='action-container'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['project_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'openEditModal('.json_encode($row).')\'><i class="fa-solid fa-pen-to-square"></i></button>';
                            echo "</div>";
                            echo "</td>";

                            echo "</tr>";
                        }
                        // แถวรวมยอด
                        echo "<tr class='total-row'>";
                        echo "<td colspan='4' class='text-center'>รวมยอดเงินงบประมาณโครงการ</td>";
                        echo "<td class='td-right text-success'>" . number_format($total_budget, 2) . "</td>";
                        echo "<td colspan='5'></td>"; // ปรับ colspan จาก 4 เป็น 5 เพราะมีคอลัมน์เพิ่ม
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='10' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลผลผลิต/โครงการ ในปี $active_year</td></tr>";
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
            <form action="Projectoutcomes.php" method="POST" onsubmit="return validateBudget(this);">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-folder-plus"></i> เพิ่มข้อมูลโครงการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_year" class="form-control" value="<?php echo $active_year; ?>" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="project_code" class="form-control" placeholder="รหัสจากกรมบัญชีกลาง">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผลผลิต/โครงการ <span class="text-danger">*</span></label>
                        <textarea name="project_name" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="row bg-light p-3 rounded mx-0">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-success">เงินงบประมาณโครงการ</label>
                            <input type="number" step="0.01" name="budget_amount" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" class="form-control" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="Projectoutcomes.php" method="POST" onsubmit="return validateBudget(this);">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลโครงการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <input type="text" name="budget_year" id="edit_budget_year" class="form-control" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">รหัส</label>
                            <input type="text" name="project_code" id="edit_project_code" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อโครงการ <span class="text-danger">*</span></label>
                        <textarea name="project_name" id="edit_project_name" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="row bg-light p-3 rounded mx-0">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-success">เงินงบประมาณโครงการ</label>
                            <input type="number" step="0.01" name="budget_amount" id="edit_budget_amount" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 1</label>
                            <input type="number" step="0.01" name="allocation_1" id="edit_allocation_1" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 2</label>
                            <input type="number" step="0.01" name="allocation_2" id="edit_allocation_2" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยอดจัดสรรเงินครั้งที่ 3</label>
                            <input type="number" step="0.01" name="allocation_3" id="edit_allocation_3" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4">บันทึกการแก้ไข</button>
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
        document.getElementById('edit_project_code').value = data.project_code;
        document.getElementById('edit_project_name').value = data.project_name;
        
        // ดึงค่าจำนวนเงินมาแสดง (ถ้าไม่มีให้เป็น 0)
        document.getElementById('edit_budget_amount').value = data.budget_amount || 0;
        document.getElementById('edit_allocation_1').value = data.allocation_1 || 0;
        document.getElementById('edit_allocation_2').value = data.allocation_2 || 0;
        document.getElementById('edit_allocation_3').value = data.allocation_3 || 0;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }

    // ฟังก์ชันตรวจสอบยอดเงินก่อนกดบันทึก (Client-side validation)
    function validateBudget(form) {
        let budget = parseFloat(form.querySelector('[name="budget_amount"]').value) || 0;
        let alloc1 = parseFloat(form.querySelector('[name="allocation_1"]').value) || 0;
        let alloc2 = parseFloat(form.querySelector('[name="allocation_2"]').value) || 0;
        let alloc3 = parseFloat(form.querySelector('[name="allocation_3"]').value) || 0;

        let totalAlloc = alloc1 + alloc2 + alloc3;

        if (totalAlloc > budget) {
            alert('จัดสรรไม่ได้ เกินงบประมาณที่กำหนดมา');
            return false; // หยุดการทำงาน ไม่ให้ submit form
        }
        return true; // ยอมให้ submit ได้ตามปกติ
    }
</script>