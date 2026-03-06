<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ประเภท(ย่อย)ของเงิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการ ประเภท(ย่อย)ของเงิน <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM money_types_sub WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Subtypesmoney.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $subtype_code = $_POST['subtype_code'];
    $subtype_name = $_POST['subtype_name'];
    $main_type_id = $_POST['main_type_id'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO money_types_sub (budget_year, subtype_code, subtype_name, main_type_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $budget_year, $subtype_code, $subtype_name, $main_type_id);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE money_types_sub SET budget_year=?, subtype_code=?, subtype_name=?, main_type_id=? WHERE id=?");
        $stmt->bind_param("issii", $budget_year, $subtype_code, $subtype_name, $main_type_id, $id);
        $stmt->execute();
    }
    header("Location: Subtypesmoney.php");
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา (กรองตาม Active Year) ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    // ถ้ามีการค้นหา ให้กรองด้วย subtype_code และปี Active
    $search_param = "%" . $search . "%";
    $sql_sub = "SELECT s.*, m.type_name AS main_type_name 
                FROM money_types_sub s 
                LEFT JOIN money_types_main m ON s.main_type_id = m.id 
                WHERE s.subtype_code LIKE ? AND s.budget_year = ?
                ORDER BY s.subtype_code ASC";
    $stmt = $conn->prepare($sql_sub);
    $stmt->bind_param("si", $search_param, $active_year);
    $stmt->execute();
    $result_sub = $stmt->get_result();
} else {
    // ถ้าไม่มีการค้นหา ให้ดึงเฉพาะปี Active
    $sql_sub = "SELECT s.*, m.type_name AS main_type_name 
                FROM money_types_sub s 
                LEFT JOIN money_types_main m ON s.main_type_id = m.id 
                WHERE s.budget_year = ?
                ORDER BY s.subtype_code ASC";
    $stmt = $conn->prepare($sql_sub);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_sub = $stmt->get_result();
}

// --- ดึงข้อมูลสำหรับ Dropdown ---
// 1. ประเภท(หลัก)
$sql_mains = "SELECT * FROM money_types_main ORDER BY type_code ASC";
$result_mains = $conn->query($sql_mains);
$main_options = [];
if ($result_mains->num_rows > 0) {
    while($m = $result_mains->fetch_assoc()) {
        // เงื่อนไขสำหรับข้าม (ซ่อน) เงินงบประมาณ ออกจาก Dropdown
        if ($m['type_name'] != 'เงินงบประมาณ') {
            $main_options[] = $m;
        }
    }
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="page-title m-0">ประเภท(ย่อย)ของเงิน</h2>
            
            <div class="d-flex align-items-center">
                <form action="Subtypesmoney.php" method="GET" class="d-flex me-2">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหารหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Subtypesmoney.php" class="btn btn-outline-danger ms-1 d-flex align-items-center justify-content-center"><i class="fa-solid fa-xmark"></i></a>
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
                        <th style="width: 50px;">ที่</th>
                        <th style="width: 80px;">รหัส</th>
                        <th style="width: 100px;">ปีงบฯ</th>
                        <th>ประเภท(ย่อย)</th>
                        <th style="width: 200px;">ประเภท(หลัก)</th>
                        <th style="width: 100px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_sub->num_rows > 0) {
                        $i = 1;
                        while($row = $result_sub->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['subtype_code']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['subtype_name']) . "</td>";
                            echo "<td class='td-left text-muted'>" . htmlspecialchars($row['main_type_name']) . "</td>";

                            // ปุ่มจัดการ
                            echo "<td class='td-center'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['subtype_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                      </button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลประเภท(ย่อย)ของเงิน ในปี $active_year</td></tr>";
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
            <form action="Subtypesmoney.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-folder-plus"></i> เพิ่มข้อมูลใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                        <input type="text" name="budget_year" class="form-control" value="<?php echo $active_year; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="subtype_code" class="form-control" placeholder="เช่น 110">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภท(ย่อย) <span class="text-danger">*</span></label>
                        <input type="text" name="subtype_name" class="form-control" placeholder="เช่น เงินประกันสัญญา" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภท(หลัก) <span class="text-danger">*</span></label>
                        <select name="main_type_id" class="form-select" required>
                            <option value="" selected disabled>-- เลือกประเภทหลัก --</option>
                            <?php foreach($main_options as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo $m['type_name']; ?></option>
                            <?php endforeach; ?>
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
            <form action="Subtypesmoney.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ <span class="text-danger">*</span></label>
                        <input type="text" name="budget_year" id="edit_budget_year" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัส</label>
                        <input type="text" name="subtype_code" id="edit_subtype_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภท(ย่อย) <span class="text-danger">*</span></label>
                        <input type="text" name="subtype_name" id="edit_subtype_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภท(หลัก) <span class="text-danger">*</span></label>
                        <select name="main_type_id" id="edit_main_type_id" class="form-select" required>
                            <?php foreach($main_options as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo $m['type_name']; ?></option>
                            <?php endforeach; ?>
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
        document.getElementById('edit_budget_year').value = data.budget_year;
        document.getElementById('edit_subtype_code').value = data.subtype_code;
        document.getElementById('edit_subtype_name').value = data.subtype_name;
        document.getElementById('edit_main_type_id').value = data.main_type_id;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>