<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตั้งค่าเจ้าหน้าที่ - AMSS++";
// กำหนดหน้าปัจจุบันให้ชัดเจน (ใช้ชื่อไฟล์ตรงๆ เลยแม่นยำกว่า)
$current_page = 'officers.php'; 
// ชื่อหน้าบนแถบสีทอง
$page_header = "เจ้าหน้าที่การเงินและบัญชี";

// --------------------------------------------------------------------------------
// --- ส่วนจัดการข้อมูล (CRUD Logic) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล (Delete)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM officers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: officers.php"); 
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['fullname'];
    
    $p_approve = isset($_POST['p_approve']) ? 1 : 0;
    $p_register = isset($_POST['p_register']) ? 1 : 0;
    $p_withdraw = isset($_POST['p_withdraw']) ? 1 : 0;
    $p_tax = isset($_POST['p_tax']) ? 1 : 0;
    $p_budget = isset($_POST['p_budget']) ? 1 : 0;
    $p_nonbudget = isset($_POST['p_nonbudget']) ? 1 : 0;
    $p_income = isset($_POST['p_income']) ? 1 : 0;
    $p_royal = isset($_POST['p_royal']) ? 1 : 0;
    $p_pay = isset($_POST['p_pay']) ? 1 : 0;

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO officers (fullname, p_approve, p_register, p_withdraw, p_tax, p_budget, p_nonbudget, p_income, p_royal, p_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiiiiiii", $fullname, $p_approve, $p_register, $p_withdraw, $p_tax, $p_budget, $p_nonbudget, $p_income, $p_royal, $p_pay);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE officers SET fullname=?, p_approve=?, p_register=?, p_withdraw=?, p_tax=?, p_budget=?, p_nonbudget=?, p_income=?, p_royal=?, p_pay=? WHERE id=?");
        $stmt->bind_param("siiiiiiiiii", $fullname, $p_approve, $p_register, $p_withdraw, $p_tax, $p_budget, $p_nonbudget, $p_income, $p_royal, $p_pay, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: officers.php");
    exit();
}

// --- ดึงข้อมูลเจ้าหน้าที่ ---
$sql_officers = "SELECT * FROM officers";
$result_officers = $conn->query($sql_officers);

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* CSS เพิ่มเติมเฉพาะหน้านี้ */
    .col-name { text-align: left !important; font-weight: 500; color: #333; }
    .check-icon { color: #198754; font-size: 1.1rem; } 
    .cross-icon { color: #dc3545; font-size: 1.1rem; opacity: 0.3; }
    .form-check-label { font-size: 0.95rem; cursor: pointer; }
</style>

<div class="container pb-5">
    <div class="content-card">
        
        <h2 class="page-title m-0 mb-4">กำหนดเจ้าหน้าที่การเงินฯ</h2>
        
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                + เพิ่มข้อมูล
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 5%;">ที่</th>
                        <th rowspan="2" style="width: 25%;">ชื่อเจ้าหน้าที่</th>
                        <th colspan="9">สิทธิ์การเข้าถึง</th>
                        <th rowspan="2" style="width: 10%;">จัดการ</th>
                    </tr>
                    <tr>
                        <th>ผู้อนุมัติ</th>
                        <th>เงินงวด</th>
                        <th>ขอเบิก</th>
                        <th>วางฎีกา</th>
                        <th>เงินงบประมาณ</th>
                        <th>เงินนอกงบประมาณ</th>
                        <th>เงินรายได้แผ่นดิน</th>
                        <th>เงินทดรองราชการ</th>
                        <th>จ่ายเงิน</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_officers->num_rows > 0) {
                        $i = 1;
                        while($row = $result_officers->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $i++ . "</td>";
                            echo "<td class='col-name'>" . $row['fullname'] . "</td>";
                            
                            $fields = ['p_approve', 'p_register', 'p_withdraw', 'p_tax', 'p_budget', 'p_nonbudget', 'p_income', 'p_royal', 'p_pay'];
                            foreach ($fields as $field) {
                                echo "<td>";
                                if($row[$field] == 1) {
                                    echo '<i class="fa-solid fa-check check-icon"></i>';
                                } else {
                                    echo '<i class="fa-solid fa-xmark cross-icon"></i>';
                                }
                                echo "</td>";
                            }

                            echo '<td>';
                            echo '<a href="?delete_id='.$row['id'].'" class="action-btn btn-delete" onclick="return confirm(\'คุณต้องการลบข้อมูลนี้หรือไม่?\')" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '<button class="action-btn btn-edit" title="แก้ไข" 
                                          onclick="openEditModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-solid fa-pen-to-square"></i>
                                        </button>';
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='12' class='text-center py-4'>ไม่พบข้อมูลเจ้าหน้าที่</td></tr>";
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
            <form action="officers.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus"></i> เพิ่มเจ้าหน้าที่ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" class="form-control" required placeholder="ระบุชื่อ-นามสกุล">
                    </div>
                    <div class="mb-2"><strong>กำหนดสิทธิ์การเข้าถึง:</strong></div>
                    <div class="row g-2">
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_approve" id="add_approve"><label class="form-check-label" for="add_approve">ผู้อนุมัติ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_register" id="add_register"><label class="form-check-label" for="add_register">เงินงวด</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_withdraw" id="add_withdraw"><label class="form-check-label" for="add_withdraw">ขอเบิก</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_tax" id="add_tax"><label class="form-check-label" for="add_tax">วางฎีกา</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_budget" id="add_budget"><label class="form-check-label" for="add_budget">เงินงบประมาณ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_nonbudget" id="add_nonbudget"><label class="form-check-label" for="add_nonbudget">เงินนอกงบประมาณ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_income" id="add_income"><label class="form-check-label" for="add_income">รายได้แผ่นดิน</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_royal" id="add_royal"><label class="form-check-label" for="add_royal">เงินทดรองราชการ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_pay" id="add_pay"><label class="form-check-label" for="add_pay">จ่ายเงิน</label></div></div>
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
            <form action="officers.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลเจ้าหน้าที่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-2"><strong>กำหนดสิทธิ์การเข้าถึง:</strong></div>
                    <div class="row g-2">
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_approve" id="edit_p_approve"><label class="form-check-label" for="edit_p_approve">ผู้อนุมัติ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_register" id="edit_p_register"><label class="form-check-label" for="edit_p_register">เงินงวด</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_withdraw" id="edit_p_withdraw"><label class="form-check-label" for="edit_p_withdraw">ขอเบิก</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_tax" id="edit_p_tax"><label class="form-check-label" for="edit_p_tax">วางฎีกา</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_budget" id="edit_p_budget"><label class="form-check-label" for="edit_p_budget">เงินงบประมาณ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_nonbudget" id="edit_p_nonbudget"><label class="form-check-label" for="edit_p_nonbudget">เงินนอกงบประมาณ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_income" id="edit_p_income"><label class="form-check-label" for="edit_p_income">รายได้แผ่นดิน</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_royal" id="edit_p_royal"><label class="form-check-label" for="edit_p_royal">เงินทดรองราชการ</label></div></div>
                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="p_pay" id="edit_p_pay"><label class="form-check-label" for="edit_p_pay">จ่ายเงิน</label></div></div>
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
        document.getElementById('edit_fullname').value = data.fullname;
        
        document.getElementById('edit_p_approve').checked = (data.p_approve == 1);
        document.getElementById('edit_p_register').checked = (data.p_register == 1);
        document.getElementById('edit_p_withdraw').checked = (data.p_withdraw == 1);
        document.getElementById('edit_p_tax').checked = (data.p_tax == 1);
        document.getElementById('edit_p_budget').checked = (data.p_budget == 1);
        document.getElementById('edit_p_nonbudget').checked = (data.p_nonbudget == 1);
        document.getElementById('edit_p_income').checked = (data.p_income == 1);
        document.getElementById('edit_p_royal').checked = (data.p_royal == 1);
        document.getElementById('edit_p_pay').checked = (data.p_pay == 1);

        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>