<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "กำหนดปีงบประมาณ ";
$current_page = 'yearbudget.php'; // กำหนดชื่อไฟล์ให้ตรงกับเงื่อนไขใน Navbar
$page_header = "กำหนดปีงบประมาณ";

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM year_budget WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // [แก้ไขใหม่] ส่งค่า status=deleted กลับไปเพื่อโชว์ Popup แจ้งเตือนความสำเร็จ
    header("Location: yearbudget.php?status=deleted");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_year = $_POST['budget_year'];
    $is_active = isset($_POST['is_active']) ? 1 : 0; // รับค่า checkbox

    // Logic พิเศษ: ถ้ามีการตั้งเป็นปีปัจจุบัน (is_active = 1) ให้เคลียร์ปีอื่นๆ เป็น 0 ก่อน
    if ($is_active == 1) {
        $conn->query("UPDATE year_budget SET is_active = 0");
        
        // อัปเดต Session ให้เป็นปีปัจจุบันทันที เพื่อให้หน้าอื่นรับรู้
        $_SESSION['active_budget_year'] = $budget_year; 
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO year_budget (budget_year, is_active) VALUES (?, ?)");
        $stmt->bind_param("ii", $budget_year, $is_active);
        $stmt->execute();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE year_budget SET budget_year=?, is_active=? WHERE id=?");
        $stmt->bind_param("iii", $budget_year, $is_active, $id);
        $stmt->execute();
    }
    header("Location: yearbudget.php");
    exit();
}

// --- ดึงข้อมูล ---
$sql_years = "SELECT * FROM year_budget ORDER BY budget_year DESC";
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
        padding: 15px 20px;
        margin-bottom: 25px;
        border-radius: 6px;
        color: #555;
        font-size: 0.95rem;
        line-height: 1.6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .status-active { color: #198754; font-size: 1.3rem; } /* Green Check */
    .status-inactive { color: #dc3545; font-size: 1.3rem; opacity: 0.5; } /* Red Cross */
    .action-container { display: flex; justify-content: center; gap: 8px; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-primary">
                <i class="fa-solid fa-calendar-days me-2"></i> กำหนดปีงบประมาณ
            </h2>
            <button class="btn btn-primary shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addModal" style="border-radius: 8px; font-weight: 500;">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มปีงบประมาณ
            </button>
        </div>

        <div class="info-box">
            <i class="fa-solid fa-circle-info me-2 text-info fs-5 align-middle"></i>
            เป็นการกำหนดปีงบประมาณในการปฏิบัติงาน สามารถกำหนดปีงบประมาณในการปฏิบัติงานได้ โดยการ <strong>เลือก เพิ่มปีงบประมาณ</strong> แล้วพิมพ์ปีงบประมาณ ที่ต้องการปฏิบัติการ ลงในช่องให้พิมพ์ แล้วเลือกปีทำงานปัจจุบันว่า <strong>ใช่ (สวิตช์เปิด)</strong> ระบบก็จะประมวลผลให้เป็นปีที่ต้องการ
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 10%;">ที่</th>
                        <th class="text-center py-3" style="width: 30%;">ปีงบประมาณ</th>
                        <th class="text-center py-3" style="width: 30%;">สถานะปีทำงานปัจจุบัน</th>
                        <th class="text-center py-3" style="width: 30%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // ตรวจสอบกัน Error หาก Query ล้มเหลวจะข้ามไปแสดงส่วน else ทันที
                    if ($result_years && $result_years->num_rows > 0) {
                        $i = 1;
                        while($row = $result_years->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                            echo "<td class='text-center fw-bold text-secondary fs-5'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            
                            // แสดงสถานะ (Active/Inactive)
                            echo "<td class='text-center'>";
                            if($row['is_active'] == 1) {
                                echo '<span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-circle-check me-1"></i> ปีปัจจุบัน</span>';
                            } else {
                                echo '<i class="fa-solid fa-circle-minus text-muted opacity-25" title="ไม่ใช่ปีปัจจุบัน"></i>';
                            }
                            echo "</td>";

                            // ปุ่มจัดการ
                            echo "<td class='text-center'>";
                            echo "<div class='action-container'>";
                            
                            // [แก้ไขใหม่] เปลี่ยนปุ่มลบให้เรียกเปิด Modal 
                            echo '<button type="button" class="btn btn-sm btn-outline-danger px-3 shadow-sm" title="ลบ" onclick="openDeleteModal('.$row['id'].', \''.htmlspecialchars($row['budget_year']).'\')"><i class="fa-solid fa-trash-can"></i> ลบ</button>';

                            echo '<button class="btn btn-sm btn-outline-warning px-3 shadow-sm" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                  </button>';
                            echo "</div>";
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'><i class='fa-regular fa-calendar-xmark fs-2 mb-2 d-block text-light'></i>ยังไม่มีข้อมูลปีงบประมาณ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow border-0">
            <form action="yearbudget.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-plus me-2"></i> เพิ่มปีงบประมาณ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">ปีงบประมาณ (พ.ศ.) <span class="text-danger">*</span></label>
                        <input type="number" name="budget_year" class="form-control form-control-lg text-center fw-bold text-primary" placeholder="เช่น 2567" required min="2500" max="3000">
                    </div>
                    <div class="form-check form-switch bg-light p-3 rounded border">
                        <input class="form-check-input ms-1" type="checkbox" name="is_active" id="add_is_active" style="transform: scale(1.3);">
                        <label class="form-check-label ms-3 fw-bold text-success" for="add_is_active">ตั้งเป็นปีทำงานปัจจุบัน</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow border-0">
            <form action="yearbudget.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขปีงบประมาณ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">ปีงบประมาณ (พ.ศ.) <span class="text-danger">*</span></label>
                        <input type="number" name="budget_year" id="edit_budget_year" class="form-control form-control-lg text-center fw-bold text-info" required>
                    </div>
                    <div class="form-check form-switch bg-light p-3 rounded border">
                        <input class="form-check-input ms-1" type="checkbox" name="is_active" id="edit_is_active" style="transform: scale(1.3);">
                        <label class="form-check-label ms-3 fw-bold text-success" for="edit_is_active">ตั้งเป็นปีทำงานปัจจุบัน</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-info text-white fw-bold px-4"><i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข</button>
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
                <i class="fa-solid fa-calendar-xmark text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูลปี <span id="display_delete_year" class="text-danger"></span> หรือไม่?</h4>
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

<div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-success bg-opacity-10 rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">สำเร็จ!</h4>
                <p class="text-muted fs-6 mb-4">ลบปีงบประมาณสำเร็จ</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location='yearbudget.php'">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // [แก้ไขใหม่] ตรวจสอบ URL ว่ามีการลบสำเร็จหรือไม่ ถ้าสำเร็จให้แสดง Popup
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            if (status === 'deleted') {
                new bootstrap.Modal(document.getElementById('successDeleteModal')).show();
                // ลบพารามิเตอร์ status ออกจาก URL เพื่อไม่ให้ Popup เด้งซ้ำเมื่อ Refresh
                window.history.replaceState(null, null, window.location.pathname);
            }
        }
    });

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_budget_year').value = data.budget_year;
        document.getElementById('edit_is_active').checked = (data.is_active == 1);
        
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }

    // [เพิ่มใหม่] ฟังก์ชันเปิด Modal สำหรับยืนยันการลบข้อมูล
    function openDeleteModal(id, year) {
        // นำปีมาแสดงใน Popup
        document.getElementById('display_delete_year').innerText = year;
        
        // ตั้งค่าลิงก์ไปยังปุ่มลบจริง
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        
        // เปิด Modal
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>