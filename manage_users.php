<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

$page_title = "จัดการผู้ใช้งาน - AMSS++";
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
            echo "<script>alert('บันทึกสำเร็จ! สิทธิ์ปัจจุบันคือ: $role'); window.location='manage_users.php';</script>";
            exit();
        } else {
            // ถ้าระบบบอกว่าผ่าน แต่ไม่มีแถวเปลี่ยน แสดงว่าค่าที่ส่งมา "เหมือนเดิมทุกอย่าง" 
            echo "<script>alert('ข้อมูลเหมือนเดิม ไม่มีการเปลี่ยนแปลง'); window.location='manage_users.php';</script>";
            exit();
        }
    } else {
        // ถ้ารัน SQL ไม่ผ่าน ให้โชว์ Error ของฐานข้อมูลมาเลย จะได้รู้ว่าเกิดอะไรขึ้น
        die("เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: " . $conn->error . "<br>คำสั่ง SQL: " . $sql);
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด (เปลี่ยนเป็น ASC เพื่อให้คนสมัครก่อนขึ้นก่อนตามลำดับ)
$result_data = $conn->query("SELECT * FROM users ORDER BY id ASC");

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title m-0 fw-bold text-primary"><i class="fa-solid fa-users-gear me-2"></i> จัดการผู้ใช้งาน</h2>
            <button class="btn btn-primary px-4" onclick="openAddModal()">
                <i class="fa-solid fa-plus me-1"></i> เพิ่มผู้ใช้งาน
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">ลำดับ</th>
                        <th>ชื่อผู้ใช้ (Username)</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th class="text-center">สิทธิ์การใช้งาน</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1; // เริ่มต้นตัวแปรนับลำดับที่ 1
                    while($row = $result_data->fetch_assoc()): 
                        $display_name = !empty($row['name']) ? $row['name'] : ($row['fullname'] ?? '-');
                        $current_role = $row['role'];
                        
                        // กำหนดสี Badge ตาม 4 สิทธิ์
                        $bg_class = "bg-secondary"; 
                        $u_role = strtolower(trim($current_role));
                        
                        if ($u_role === 'admin') $bg_class = "bg-danger";
                        elseif ($u_role === 'การเงิน') $bg_class = "bg-success";
                        elseif ($u_role === 'แผนงาน') $bg_class = "bg-info text-dark";
                        elseif ($u_role === 'user' || $u_role === 'id user') $bg_class = "bg-primary";
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?php echo $i++; ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($display_name); ?></td>
                        <td class="text-center">
                            <span class="badge <?php echo $bg_class; ?> px-3 py-2" style="font-size:0.85rem; min-width:120px;">
                                <?php echo !empty($current_role) ? htmlspecialchars($current_role) : 'ยังไม่กำหนด'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning text-white me-1" onclick='openEditModal(<?php echo json_encode($row); ?>)'>
                                <i class="fa-solid fa-user-pen"></i> แก้ไข
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['username']; ?>')">
                                <i class="fa-solid fa-trash"></i> ลบ
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border-0">
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">ข้อมูลผู้ใช้</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">กำหนดสิทธิ์ระบบ</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="">-- เลือกสิทธิ์ --</option>
                            <option value="Admin">Admin</option>
                            <option value="แผนงาน">แผนงาน</option>
                            <option value="การเงิน">การเงิน</option>
                            <option value="User">User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">รหัสผ่าน (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-primary px-4">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function confirmDelete(id, user) {
        if(confirm('ต้องการลบผู้ใช้งาน ' + user + ' ใช่หรือไม่?')) {
            window.location.href = 'manage_users.php?delete_id=' + id;
        }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('username').value = '';
        document.getElementById('name').value = '';
        document.getElementById('role').value = '';
        document.getElementById('password').required = true;
        document.getElementById('modalTitle').innerText = 'เพิ่มผู้ใช้งานใหม่';
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id; // ยังคงใช้ ID จริงจาก DB สำหรับอ้างอิงตอนแก้ไข
        document.getElementById('username').value = data.username;
        document.getElementById('name').value = data.name || data.fullname || '';
        
        // เลือกสิทธิ์ใน Dropdown 
        let roleSelect = document.getElementById('role');
        let dbRole = data.role ? data.role.trim() : '';
        let isFound = false;
        
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].value.toLowerCase() === dbRole.toLowerCase()) {
                roleSelect.selectedIndex = i;
                isFound = true;
                break;
            }
        }
        if (!isFound) {
            roleSelect.value = ''; 
        }

        document.getElementById('password').required = false;
        document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลผู้ใช้งาน';
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }
</script>