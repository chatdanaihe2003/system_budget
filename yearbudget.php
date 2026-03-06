<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดปีงบประมาณ - AMSS++";
$current_page = 'yearbudget.php'; // กำหนดชื่อไฟล์ให้ตรงกับเงื่อนไขใน Navbar
$page_header = "กำหนดปีงบประมาณ";

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM fiscal_years WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: yearbudget.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $is_active = isset($_POST['is_active']) ? 1 : 0; // รับค่า checkbox

    // Logic พิเศษ: ถ้ามีการตั้งเป็นปีปัจจุบัน (is_active = 1) ให้เคลียร์ปีอื่นๆ เป็น 0 ก่อน
    if ($is_active == 1) {
        $conn->query("UPDATE fiscal_years SET is_active = 0");
        
        // อัปเดต Session ให้เป็นปีปัจจุบันทันที เพื่อให้หน้าอื่นรับรู้
        $_SESSION['active_budget_year'] = $budget_year; 
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO fiscal_years (budget_year, is_active) VALUES (?, ?)");
        $stmt->bind_param("ii", $budget_year, $is_active);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE fiscal_years SET budget_year=?, is_active=? WHERE id=?");
        $stmt->bind_param("iii", $budget_year, $is_active, $id);
        $stmt->execute();
    }
    header("Location: yearbudget.php");
    exit();
}

// --- ดึงข้อมูล ---
$sql_years = "SELECT * FROM fiscal_years ORDER BY budget_year DESC";
$result_years = $conn->query($sql_years);

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
    .status-active { color: #198754; font-size: 1.2rem; } /* Green Check */
    .status-inactive { color: #dc3545; font-size: 1.2rem; opacity: 0.5; } /* Red Cross */
</style>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div style="width: 150px;"></div> 
            <h2 class="page-title m-0">กำหนดปีงบประมาณ</h2>
            <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มปีงบประมาณ
            </button>
        </div>

        <div class="info-box">
            <i class="fa-solid fa-circle-info me-2"></i>
            เป็นการกำหนดปีงบประมาณในการปฏิบัติงาน สามารถกำหนดปีงบประมาณในการปฏิบัติงานได้ โดยการ <strong>เลือก ที่เพิ่มปีงบประมาณ</strong> แล้วพิมพ์ปีงบประมาณ ที่ต้องการปฏิบัติการ ลงในช่องให้พิมพ์ แล้วเลือกปีทำงานปัจจุบันว่า <strong>ใช่</strong> ระบบก็จะประมวลผลให้เป็นปีที่ต้องการ
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 80px;">ที่</th>
                        <th>ปีงบประมาณ</th>
                        <th>ปีทำงานปัจจุบัน</th>
                        <th style="width: 150px;">ลบ / แก้ไข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_years->num_rows > 0) {
                        $i = 1;
                        while($row = $result_years->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $i++ . "</td>";
                            echo "<td class='fw-bold'>" . $row['budget_year'] . "</td>";
                            
                            // แสดงสถานะ (Active/Inactive)
                            echo "<td>";
                            if($row['is_active'] == 1) {
                                echo '<i class="fa-solid fa-circle-check status-active"></i>';
                            } else {
                                echo '<i class="fa-solid fa-circle-xmark status-inactive"></i>';
                            }
                            echo "</td>";

                            // ปุ่มจัดการ
                            echo '<td>';
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบข้อมูลปี '.$row['budget_year'].' หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                    </button>';
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลปีงบประมาณ</td></tr>";
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
            <form action="yearbudget.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-calendar-plus"></i> เพิ่มปีงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ (พ.ศ.)</label>
                        <input type="number" name="budget_year" class="form-control" placeholder="เช่น 2567" required min="2500" max="3000">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active">
                        <label class="form-check-label" for="add_is_active">ตั้งเป็นปีทำงานปัจจุบัน</label>
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
            <form action="yearbudget.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขปีงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปีงบประมาณ (พ.ศ.)</label>
                        <input type="number" name="budget_year" id="edit_budget_year" class="form-control" required>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">ตั้งเป็นปีทำงานปัจจุบัน</label>
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
        document.getElementById('edit_is_active').checked = (data.is_active == 1);
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>