<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

$page_title = "จัดการผู้ใช้งาน ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'จัดการผู้ใช้งานและกำหนดสิทธิ์ระบบ';

// --- เช็คสิทธิ์ ADMIN ---
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>alert('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถเข้าถึงหน้านี้ได้'); window.location='index.php';</script>";
    exit();
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูลผู้ใช้งาน (CRUD) ---
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้'); window.location='manage_users.php';</script>";
        exit();
    }
    $conn->query("DELETE FROM users WHERE id = $id");
    header("Location: manage_users.php");
    exit();
}

// 2. บันทึกการแก้ไข/เพิ่ม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = intval($_POST['edit_id'] ?? 0);
    $username = $conn->real_escape_string(trim($_POST['username']));
    $name = $conn->real_escape_string(trim($_POST['name']));
    $role = $conn->real_escape_string(trim($_POST['role'])); 
    $password = trim($_POST['password']);

    // สร้างคำสั่ง SQL สำหรับอัปเดตแบบเรียบง่ายที่สุด
    if ($action == 'add') {
        $pw = md5($password);
        // เช็คก่อนว่ามีคอลัมน์ fullname ไหม เผื่อฐานข้อมูลยังไม่อัปเดต
        $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'fullname'");
        if($check_col && $check_col->num_rows > 0) {
            $sql = "INSERT INTO users (username, password, name, fullname, role) VALUES ('$username', '$pw', '$name', '$name', '$role')";
        } else {
            $sql = "INSERT INTO users (username, password, name, role) VALUES ('$username', '$pw', '$name', '$role')";
        }
    } elseif ($action == 'edit') {
        if (!empty($password)) {
            $pw = md5($password);
            $sql = "UPDATE users SET username='$username', password='$pw', name='$name', role='$role' WHERE id=$id";
        } else {
            $sql = "UPDATE users SET username='$username', name='$name', role='$role' WHERE id=$id";
        }
        
        // แอบอัปเดต fullname ด้วยถ้ามีคอลัมน์นี้อยู่
        $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'fullname'");
        if($check_col && $check_col->num_rows > 0) {
            $conn->query("UPDATE users SET fullname='$name' WHERE id=$id");
        }
    }

    // ทำการ Execute SQL และดักจับ Error
    if ($conn->query($sql)) {
        // เช็คว่ามีแถวถูกอัปเดตจริงๆ หรือไม่
        if ($conn->affected_rows > 0 || $action == 'add') {
            // อัปเดต SESSION ทันทีถ้าแก้ไขบัญชีตัวเอง
            if ($id == $_SESSION['user_id']) {
                $_SESSION['username'] = $username;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;
            }
            // ส่งค่ากลับไปเพื่อเปิด Popup แจ้งเตือนสวยๆ
            header("Location: manage_users.php?status=success&role=" . urlencode($role));
            exit();
        } else {
            header("Location: manage_users.php?status=nochange");
            exit();
        }
    } else {
        die("เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: " . $conn->error . "<br>คำสั่ง SQL: " . $sql);
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด (เปลี่ยนเป็น ASC เพื่อให้คนสมัครก่อนขึ้นก่อนตามลำดับ)
$result_data = $conn->query("SELECT * FROM users ORDER BY id ASC");

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ปรับแต่งส่วน Header ของหน้า */
    .page-title-box {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 20px 25px;
        border-radius: 15px;
        border-left: 5px solid #0ea5e9;
    }
    
    /* สไตล์ตารางให้สวยงาม */
    .table-manage-users {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-manage-users thead {
        background-color: #f1f5f9;
    }
    .table-manage-users th {
        color: #475569;
        font-weight: 700;
        padding: 15px;
        border-bottom: 2px solid #cbd5e1;
        vertical-align: middle;
    }
    .table-manage-users td {
        padding: 12px 15px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        background-color: #ffffff;
        transition: background-color 0.2s ease;
    }
    .table-manage-users tbody tr:hover td {
        background-color: #f8fafc !important;
    }
    
    /* สไตล์ปุ่ม Action */
    .btn-action {
        border-radius: 8px;
        font-weight: 600;
        padding: 6px 12px;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        border: none;
    }
    .btn-edit-user {
        background-color: #fffbeb;
        color: #d97706;
    }
    .btn-edit-user:hover {
        background-color: #d97706;
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(217, 119, 6, 0.2);
    }
    .btn-delete-user {
        background-color: #fef2f2;
        color: #dc2626;
    }
    .btn-delete-user:hover {
        background-color: #dc2626;
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);
    }
    
    /* Form Modal Style */
    .form-control-custom {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 12px 15px;
        transition: all 0.2s ease;
    }
    .form-control-custom:focus {
        background-color: #ffffff;
        border-color: #0ea5e9;
        box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
    }
    .form-label-custom {
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
    }
</style>

<div class="container-fluid pb-5 px-4 mt-4">
    <div class="content-card shadow-sm border-0" style="background:#fff; border-radius:20px; padding:30px;">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center page-title-box mb-4">
            <h3 class="m-0 fw-bold text-dark mb-3 mb-md-0"><i class="fa-solid fa-users-gear me-2 text-primary"></i> จัดการผู้ใช้งานระบบ</h3>
            <button class="btn btn-primary px-4 py-2 fw-bold shadow-sm rounded-pill" onclick="openAddModal()">
                <i class="fa-solid fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่
            </button>
        </div>

        <div class="table-responsive" style="border-radius: 12px;">
            <table class="table table-hover align-middle table-manage-users m-0">
                <thead class="text-center">
                    <tr>
                        <th style="width: 8%;">ลำดับ</th>
                        <th class="text-start" style="width: 25%;">ชื่อผู้ใช้ (Username)</th>
                        <th class="text-start" style="width: 30%;">ชื่อ-นามสกุล</th>
                        <th style="width: 17%;">สิทธิ์การใช้งาน</th>
                        <th style="width: 20%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1; // เริ่มต้นตัวแปรนับลำดับที่ 1
                    while($row = $result_data->fetch_assoc()): 
                        $display_name = !empty($row['name']) ? $row['name'] : ($row['fullname'] ?? '-');
                        $current_role = $row['role'];
                        $display_role = $current_role;
                        
                        // กำหนดสี Badge และจับมัดรวมสิทธิ์ที่ซ้ำซ้อนให้เป็นคำว่า 'User'
                        $bg_class = "bg-secondary"; 
                        $u_role = strtolower(trim($current_role));
                        
                        if ($u_role === 'admin') {
                            $bg_class = "bg-danger";
                            $display_role = 'Admin';
                        } elseif ($u_role === 'การเงิน') {
                            $bg_class = "bg-success";
                        } elseif ($u_role === 'แผนงาน') {
                            $bg_class = "bg-info text-dark";
                        } elseif ($u_role === 'user' || $u_role === 'id user' || $u_role === 'userทั่วไป') {
                            $bg_class = "bg-primary";
                            $display_role = 'User'; // แสดงผลเป็น User เท่านั้น
                        }
                    ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?php echo $i++; ?></td>
                        <td class="text-start fw-bold text-dark">
                            <i class="fa-solid fa-circle-user text-muted opacity-50 me-2"></i><?php echo htmlspecialchars($row['username']); ?>
                        </td>
                        <td class="text-start"><?php echo htmlspecialchars($display_name); ?></td>
                        <td class="text-center">
                            <span class="badge <?php echo $bg_class; ?> px-3 py-2 shadow-sm rounded-pill" style="font-size:0.85rem; letter-spacing: 0.5px;">
                                <?php echo !empty($display_role) ? htmlspecialchars($display_role) : 'ยังไม่กำหนด'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-action btn-edit-user me-2" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                            </button>
                            <button class="btn btn-action btn-delete-user" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                                <i class="fa-solid fa-trash-can"></i> ลบ
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" style="z-index: 1060; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-header text-white" id="modalHeaderBg" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-user-gear me-2"></i> ข้อมูลผู้ใช้งาน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label form-label-custom">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-at"></i></span>
                            <input type="text" name="username" id="username" class="form-control form-control-custom border-start-0" placeholder="กรอกชื่อผู้ใช้งาน..." required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label form-label-custom">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-id-card"></i></span>
                            <input type="text" name="name" id="name" class="form-control form-control-custom border-start-0" placeholder="กรอกชื่อและนามสกุลจริง..." required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label form-label-custom">กำหนดสิทธิ์ระบบ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-shield-halved"></i></span>
                            <select name="role" id="role" class="form-select form-control-custom border-start-0" required>
                                <option value="" disabled selected>-- โปรดเลือกสิทธิ์การใช้งาน --</option>
                                <option value="Admin">Admin (ผู้ดูแลระบบ)</option>
                                <option value="แผนงาน">แผนงาน</option>
                                <option value="การเงิน">การเงิน</option>
                                <option value="User">User (ผู้ใช้งานทั่วไป)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-1">
                        <label class="form-label form-label-custom" id="passwordLabel">รหัสผ่าน <span class="text-danger" id="passwordReq">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control form-control-custom border-start-0" placeholder="กรอกรหัสผ่าน...">
                        </div>
                        <small class="text-danger mt-1 d-block" id="passwordHelp" style="display: none !important;"><i class="fa-solid fa-circle-info me-1"></i> เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 py-3" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold rounded-pill shadow-sm" id="btnSubmit">
                        <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1070; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการลบผู้ใช้</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-user-slash text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบผู้ใช้งานนี้หรือไม่?</h4>
                <p class="text-muted fs-5 mb-0 fw-bold" id="display_del_user"></p>
                <div class="alert alert-danger mt-4 mb-0 text-start border-0" style="background-color: #fef2f2; color: #b91c1c; border-radius: 10px;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> การลบข้อมูลจะไม่สามารถกู้คืนได้ และผู้ใช้งานนี้จะไม่สามารถเข้าสู่ระบบได้อีก
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 16px 16px;">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtnLink" class="btn btn-danger px-5 fw-bold rounded-pill shadow-sm">
                    <i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-body p-5 text-center">
                <div id="alertIcon"></div>
                <h3 class="fw-bold mt-4 mb-2 text-dark" id="alertTitle"></h3>
                <p class="text-muted fs-5 mb-4" id="alertMessage"></p>
                <button type="button" class="btn btn-lg w-100 fw-bold rounded-pill shadow-sm" id="alertBtn" data-bs-dismiss="modal">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ตรวจสอบ URL Parameter เพื่อแสดง Popup แจ้งเตือนหลังจากบันทึกข้อมูล
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            const role = urlParams.get('role');
            
            const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
            
            if (status === 'success') {
                document.getElementById('alertIcon').innerHTML = '<i class="fa-regular fa-circle-check text-success" style="font-size: 5rem;"></i>';
                document.getElementById('alertTitle').innerText = 'บันทึกสำเร็จ!';
                document.getElementById('alertMessage').innerHTML = 'ข้อมูลถูกอัปเดตเรียบร้อยแล้ว<br>สิทธิ์ปัจจุบันคือ: <strong>' + decodeURIComponent(role) + '</strong>';
                document.getElementById('alertBtn').className = 'btn btn-success btn-lg w-100 fw-bold rounded-pill shadow-sm';
            } else if (status === 'nochange') {
                document.getElementById('alertIcon').innerHTML = '<i class="fa-solid fa-circle-exclamation text-warning" style="font-size: 5rem;"></i>';
                document.getElementById('alertTitle').innerText = 'ไม่มีการเปลี่ยนแปลง';
                document.getElementById('alertMessage').innerText = 'ข้อมูลเหมือนเดิมทุกประการ ไม่มีการอัปเดตใดๆ';
                document.getElementById('alertBtn').className = 'btn btn-warning text-dark btn-lg w-100 fw-bold rounded-pill shadow-sm';
            }
            
            alertModal.show();
            // ลบ parameter ออกจาก URL เพื่อไม่ให้แสดงซ้ำเมื่อรีเฟรช
            window.history.replaceState(null, null, window.location.pathname);
        }
    });

    // ฟังก์ชันเปิด Modal ลบ
    function confirmDelete(id, user) {
        document.getElementById('display_del_user').innerText = 'Username: ' + user;
        document.getElementById('confirmDeleteBtnLink').href = 'manage_users.php?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
    }

    // ฟังก์ชันเปิด Modal เพิ่มผู้ใช้
    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('username').value = '';
        document.getElementById('name').value = '';
        document.getElementById('role').value = ''; // ให้ว่างเพื่อให้เลือกใหม่
        
        document.getElementById('password').required = true;
        document.getElementById('passwordReq').style.display = 'inline';
        document.getElementById('passwordHelp').style.setProperty('display', 'none', 'important');
        
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่';
        document.getElementById('modalHeaderBg').className = 'modal-header bg-primary text-white';
        document.getElementById('btnSubmit').className = 'btn btn-primary px-5 fw-bold rounded-pill shadow-sm';
        
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }

    // ฟังก์ชันเปิด Modal แก้ไขผู้ใช้
    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id; 
        document.getElementById('username').value = data.username;
        document.getElementById('name').value = data.name || data.fullname || '';
        
        let roleSelect = document.getElementById('role');
        let dbRole = data.role ? data.role.trim().toLowerCase() : '';
        
        if (dbRole === 'id user' || dbRole === 'userทั่วไป' || dbRole === 'user') {
            dbRole = 'user';
        }
        
        let isFound = false;
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].value.toLowerCase() === dbRole) {
                roleSelect.selectedIndex = i;
                isFound = true;
                break;
            }
        }
        if (!isFound) {
            roleSelect.value = ''; 
        }

        document.getElementById('password').required = false;
        document.getElementById('passwordReq').style.display = 'none';
        document.getElementById('passwordHelp').style.setProperty('display', 'block', 'important');
        
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-pen me-2"></i> แก้ไขข้อมูลผู้ใช้งาน';
        document.getElementById('modalHeaderBg').className = 'modal-header bg-warning text-dark';
        document.getElementById('btnSubmit').className = 'btn btn-warning text-dark px-5 fw-bold rounded-pill shadow-sm';
        
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }
</script>