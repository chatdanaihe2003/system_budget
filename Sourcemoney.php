<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดแหล่งของเงิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการ แหล่งของเงิน <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM funding_sources WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Sourcemoney.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $source_code = $_POST['source_code'];
    $source_name = $_POST['source_name'];

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO funding_sources (budget_year, source_code, source_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $budget_year, $source_code, $source_name);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE funding_sources SET budget_year=?, source_code=?, source_name=? WHERE id=?");
        $stmt->bind_param("issi", $budget_year, $source_code, $source_name, $id);
        $stmt->execute();
    }
    header("Location: Sourcemoney.php");
    exit();
}

// --- ส่วนการดึงข้อมูลและค้นหา (กรองตาม Active Year) ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    // กรองด้วย source_code และต้องตรงกับปีทำงานปัจจุบัน
    $search_param = "%" . $search . "%";
    $sql_sources = "SELECT * FROM funding_sources WHERE source_code LIKE ? AND budget_year = ? ORDER BY source_code ASC";
    $stmt = $conn->prepare($sql_sources);
    $stmt->bind_param("si", $search_param, $active_year);
    $stmt->execute();
    $result_sources = $stmt->get_result();
} else {
    // ดึงข้อมูลทั้งหมด ของปีทำงานปัจจุบัน
    $sql_sources = "SELECT * FROM funding_sources WHERE budget_year = ? ORDER BY source_code ASC";
    $stmt = $conn->prepare($sql_sources);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_sources = $stmt->get_result();
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="page-title m-0">กำหนดแหล่งของเงิน</h2>
            
            <div class="d-flex align-items-center">
                <form action="Sourcemoney.php" method="GET" class="d-flex me-2">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหารหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Sourcemoney.php" class="btn btn-outline-danger ms-1 d-flex align-items-center justify-content-center"><i class="fa-solid fa-xmark"></i></a>
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
                        <th style="width: 150px;">รหัส</th>
                        <th>ชื่อแหล่งของเงิน</th>
                        <th style="width: 120px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_sources->num_rows > 0) {
                        $i = 1;
                        while($row = $result_sources->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['source_code']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['source_name']) . "</td>";

                            // ปุ่มจัดการ
                            echo "<td class='td-center'>";
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบรายการรหัส '.$row['source_code'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                      </button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลแหล่งของเงิน ในปี $active_year</td></tr>";
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
            <form action="Sourcemoney.php" method="POST">
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
                        <input type="text" name="source_code" class="form-control" placeholder="รหัสจากกรมบัญชีกลาง">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อแหล่งของเงิน <span class="text-danger">*</span></label>
                        <textarea name="source_name" class="form-control" rows="3" required></textarea>
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
            <form action="Sourcemoney.php" method="POST">
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
                        <input type="text" name="source_code" id="edit_source_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อแหล่งของเงิน <span class="text-danger">*</span></label>
                        <textarea name="source_name" id="edit_source_name" class="form-control" rows="3" required></textarea>
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
        document.getElementById('edit_source_code').value = data.source_code;
        document.getElementById('edit_source_name').value = data.source_name;
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>