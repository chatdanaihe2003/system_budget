<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนรับเงินงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนรับเงินงบประมาณ';

// --- ตรวจสอบและสร้างคอลัมน์ 'recorded_by' ให้อัตโนมัติ (เพื่อรองรับการบันทึกชื่อเจ้าหน้าที่) ---
$check_col = $conn->query("SHOW COLUMNS FROM receive_budget LIKE 'recorded_by'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE receive_budget ADD recorded_by VARCHAR(255) NULL AFTER amount");
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD)
// --------------------------------------------------------------------------------

// 1. ลบข้อมูล
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM receive_budget WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Receivebudget.php");
    exit();
}

// 2. เพิ่ม หรือ แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_date = $_POST['doc_date'];
    $doc_no = $_POST['doc_no'];
    $description = $_POST['description'];
    $transaction_type = $_POST['transaction_type'];
    $amount = $_POST['amount'];
    
    // ดึงชื่อเจ้าหน้าที่จาก Session เพื่อบันทึก
    $recorded_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // หาระดับเลขที่ใบงวดถัดไปอัตโนมัติก่อนบันทึก
        $sql_max = "SELECT MAX(receive_order) as max_order FROM receive_budget WHERE budget_year = ?";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("i", $active_year);
        $stmt_max->execute();
        $res_max = $stmt_max->get_result();
        $row_max = $res_max->fetch_assoc();
        $auto_receive_order = ($row_max['max_order'] ? $row_max['max_order'] : 0) + 1;

        $stmt = $conn->prepare("INSERT INTO receive_budget (budget_year, receive_order, doc_date, doc_no, description, transaction_type, amount, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssds", $active_year, $auto_receive_order, $doc_date, $doc_no, $description, $transaction_type, $amount, $recorded_by);
        $stmt->execute();
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $receive_order = $_POST['receive_order']; // รับค่าเดิมเพื่อคงไว้ตอนแก้ไข
        
        $stmt = $conn->prepare("UPDATE receive_budget SET receive_order=?, doc_date=?, doc_no=?, description=?, transaction_type=?, amount=?, recorded_by=? WHERE id=?");
        $stmt->bind_param("issssdsi", $receive_order, $doc_date, $doc_no, $description, $transaction_type, $amount, $recorded_by, $id);
        $stmt->execute();
    }
    header("Location: Receivebudget.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active และกรองข้อมูลตาม Select ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql_data = "SELECT * FROM receive_budget WHERE budget_year = ?";

if ($filter == 'receive') {
    // รายการรับเงิน (ไม่มีคำว่า ตัดจ่าย และ รับคืนเงินยืม)
    $sql_data .= " AND description NOT LIKE '%(ตัดจ่าย)%' AND description NOT LIKE '%รับคืนเงินยืมโครงการ%'";
} elseif ($filter == 'refund') {
    // รายการคืนเงิน
    $sql_data .= " AND description LIKE '%รับคืนเงินยืมโครงการ%'";
} elseif ($filter == 'pay') {
    // รายการจ่ายเงิน
    $sql_data .= " AND description LIKE '%(ตัดจ่าย)%'";
}

$sql_data .= " ORDER BY receive_order ASC";

$stmt = $conn->prepare($sql_data);
$stmt->bind_param("i", $active_year);
$stmt->execute();
$result_data = $stmt->get_result();

// --- เตรียมเลขที่ใบงวดถัดไปสำหรับการแสดงผลตอนกดเพิ่มข้อมูล ---
$sql_next = "SELECT MAX(receive_order) as max_order FROM receive_budget WHERE budget_year = ?";
$stmt_next = $conn->prepare($sql_next);
$stmt_next->bind_param("i", $active_year);
$stmt_next->execute();
$res_next = $stmt_next->get_result();
$row_next = $res_next->fetch_assoc();
$next_receive_order = ($row_next['max_order'] ? $row_next['max_order'] : 0) + 1;

// ตัวแปรสำหรับรวมยอดเงิน
$total_amount = 0;

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .total-text { color: #d63384; font-weight: bold; }
    .warning-icon { color: #dc3545; margin-left: 5px; }

    /* CSS สำหรับฟอร์มใน Modal ให้สวยงามและเป็นสีขาว */
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 6px 25px; background-color: #e9ecef; border: 1px solid #ccc; color: #000; border-radius: 4px; font-size: 0.95rem; }
    .btn-form:hover { background-color: #d3d9df; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            
            <div style="width: 200px;">
                <form action="Receivebudget.php" method="GET" class="m-0">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>รายการทั้งหมด</option>
                        <option value="receive" <?php echo $filter == 'receive' ? 'selected' : ''; ?>>รายการรับเงิน</option>
                        <option value="refund" <?php echo $filter == 'refund' ? 'selected' : ''; ?>>รายการคืนเงิน</option>
                        <option value="pay" <?php echo $filter == 'pay' ? 'selected' : ''; ?>>รายการจ่ายเงิน</option>
                    </select>
                </form>
            </div>
            
            <h2 class="page-title m-0 text-center">ทะเบียนรับเงินงบประมาณ (ปีงบประมาณ <?php echo $active_year; ?>)</h2>
            
            <div style="width: 200px;" class="text-end">
                <button class="btn btn-add" onclick="checkAdminAction('add')">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มรายการรับ
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 6%;">ที่ใบงวด</th>
                        <th style="width: 8%;">ว/ด/ป</th>
                        <th style="width: 10%;">ที่เอกสาร</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 15%;">ลักษณะรายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 4%;">รายละเอียด</th>
                        <th style="width: 3%;">ลบ</th>
                        <th style="width: 3%;">แก้ไข</th>
                        <th style="width: 3%;">พิมพ์</th>
                        <th style="width: 3%;">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $total_amount += $row['amount'];
                            $desc_text = htmlspecialchars($row['description']);
                            
                            // เช็คว่าเป็นข้อมูลที่เด้งมาจากหน้าอื่นหรือไม่
                            $is_synced_data = false;
                            if (strpos($desc_text, '(ตัดจ่าย)') !== false || strpos($desc_text, 'รับคืนเงินยืมโครงการ') !== false) {
                                $is_synced_data = true;
                            }

                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['receive_order'] . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            
                            // ตรวจสอบว่าเป็นรายการตัดจ่าย หรือยอดเงินติดลบ เท่านั้นถึงจะแดง
                            if (strpos($desc_text, '(ตัดจ่าย)') !== false || $row['amount'] < 0) {
                                // แสดงผลเป็นตัวหนังสือสีแดง
                                echo "<td class='td-left' style='color: red;'>" . $desc_text . " <i class='fa-solid fa-triangle-exclamation'></i></td>";
                                echo "<td class='td-center' style='color: red;'>" . htmlspecialchars($row['transaction_type']) . "</td>";
                                echo "<td class='td-right' style='color: red; font-weight: bold;'>" . number_format($row['amount'], 2) . "</td>";
                            } else {
                                // แสดงผลปกติ (รวมถึงรายการคืนเงินด้วย)
                                echo "<td class='td-left'>" . $desc_text . " <i class='fa-solid fa-triangle-exclamation warning-icon'></i></td>";
                                echo "<td class='td-center'>" . htmlspecialchars($row['transaction_type']) . "</td>";
                                echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            }
                            
                            // ปุ่มรายละเอียด (แสดงตลอด)
                            echo "<td class='td-center'>";
                            echo '<button class="action-btn" title="รายละเอียด" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">
                                          <i class="fa-regular fa-rectangle-list"></i>
                                      </button>';
                            echo "</td>";

                            // ปุ่มลบ (แสดงเสมอ ไม่ว่าเป็นข้อมูลที่ซิงค์มาหรือไม่)
                            echo "<td class='td-center'>";
                            echo '<a href="javascript:void(0)" onclick="checkAdminDelete('.$row['id'].')" class="action-btn btn-delete" title="ลบ"><i class="fa-solid fa-xmark"></i></a>';
                            echo "</td>";

                            // ปุ่มแก้ไข (ถ้าเป็นข้อมูลที่ซิงค์มาจากหน้าอื่น จะไม่แสดงปุ่มแก้ไข)
                            if ($is_synced_data) {
                                echo "<td class='td-center'><span class='text-muted' style='font-size:0.8rem;'>-</span></td>";
                            } else {
                                echo "<td class='td-center'>";
                                echo '<button class="action-btn btn-edit" title="แก้ไข" onclick=\'checkAdminAction("edit", '.json_encode($row).')\'><i class="fa-solid fa-pen"></i></button>';
                                echo "</td>";
                            }

                            // ปุ่มพิมพ์ (แสดงตลอด)
                            echo "<td class='td-center'>";
                            echo '<button class="action-btn btn-print" title="พิมพ์" onclick="printItem('.$row['id'].')"><i class="fa-solid fa-print"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center total-text'>ถึงนี้</td>";
                            echo "</tr>";
                        }
                        
                        echo "<tr class='total-row'>";
                        echo "<td colspan='5' class='text-center'>รวม</td>";
                        echo "<td class='td-right text-success'>" . number_format($total_amount, 2) . "</td>";
                        echo "<td colspan='5'></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='11' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลรายการ ในประเภทที่เลือก</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg" style="max-width: 700px;">
        <div class="modal-content border-0">
            <div class="modal-header d-block pb-2 border-bottom">
                <h5 class="modal-title-custom text-teal" id="modalTitle">เพิ่มรายการรับเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-3 my-3 pt-0">
                <div class="form-white-bg border-0 p-0 mt-3">
                    <form action="Receivebudget.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <input type="hidden" name="receive_order" id="receive_order">
                        <input type="hidden" name="doc_date" id="doc_date">
                        
                        <div class="row mb-3 align-items-center mt-3">
                            <div class="col-md-3 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-6">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-3 form-label-custom">รายการ</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-3 form-label-custom">ลักษณะรายการ</div>
                            <div class="col-md-6">
                                <select name="transaction_type" id="transaction_type" class="form-select form-select-sm" required>
                                    <option value="">เลือก</option>
                                    <option value="รับเช็ค/เงินฝากธนาคาร">รับเช็ค/เงินฝากธนาคาร</option>
                                    <option value="รับเงินสด">รับเงินสด</option>
                                    <option value="โอนเงิน">โอนเงิน</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4 align-items-center">
                            <div class="col-md-3 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" class="btn-form me-2">ตกลง</button>
                            <button type="button" class="btn-form" data-bs-dismiss="modal">ย้อนกลับ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> รายละเอียดการรับเงิน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body mx-3">
                <table class="table table-bordered mb-2 mt-2">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background-color: #f8f9fa;">ที่ใบงวด</th>
                            <td id="detail_receive_order"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">ว/ด/ป</th>
                            <td id="detail_doc_date"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">ที่เอกสาร</th>
                            <td id="detail_doc_no"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">รายการ</th>
                            <td id="detail_desc"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">ลักษณะรายการ</th>
                            <td id="detail_transaction_type"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">จำนวนเงิน</th>
                            <td id="detail_amount" class="text-success fw-bold fs-6"></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f8f9fa;">ผู้บันทึกข้อมูล</th>
                            <td id="detail_recorded_by" class="text-primary fw-bold"></td>
                        </tr>
                    </tbody>
                </table>
                <div id="sync_warning" class="text-danger mt-2 text-center" style="display: none; font-size: 0.9rem;">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <span id="sync_message"></span>
                </div>
            </div>
            <div class="modal-footer border-0 pb-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ฟังก์ชันทำงานแทนสิทธิ์
    function checkAdminAction(action, data = null) {
        if (action === 'add') {
            openAddModal();
        } else {
            openEditModal(data);
        }
    }

    // ฟังก์ชันทำงานแทนสิทธิ์ตอนลบ
    function checkAdminDelete(id) {
        if (confirm('คุณต้องการลบรายการนี้หรือไม่?')) {
            window.location.href = `?delete_id=${id}`;
        }
    }

    function openAddModal() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('edit_id').value = '';
        document.getElementById('modalTitle').innerHTML = 'เพิ่มรายการรับเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?>';
        document.querySelector('#addModal form').reset();
        
        // กำหนดค่าที่ใบงวดอัตโนมัติสำหรับฟอร์มใหม่
        document.getElementById('receive_order').value = '<?php echo $next_receive_order; ?>';
        document.getElementById('doc_date').value = '<?php echo date('Y-m-d'); ?>';

        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไขรายการรับเงินงบประมาณ ปีงบประมาณ <?php echo $active_year; ?>';
        
        // ดึงค่ามาแสดง
        document.getElementById('receive_order').value = data.receive_order;
        document.getElementById('doc_date').value = data.doc_date;
        document.getElementById('doc_no').value = data.doc_no;
        document.getElementById('description').value = data.description;
        document.getElementById('transaction_type').value = data.transaction_type;
        document.getElementById('amount').value = data.amount;
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('detail_receive_order').innerText = data.receive_order || '-';
        document.getElementById('detail_doc_date').innerText = data.doc_date || '-';
        document.getElementById('detail_doc_no').innerText = data.doc_no || '-';
        document.getElementById('detail_desc').innerText = data.description || '-';
        document.getElementById('detail_transaction_type').innerText = data.transaction_type || '-';
        document.getElementById('detail_recorded_by').innerText = data.recorded_by || 'ไม่มีข้อมูลผู้บันทึก';
        
        // จัดฟอร์แมตตัวเลขให้มีลูกน้ำและทศนิยม 2 ตำแหน่ง
        let amount = parseFloat(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        // ตรวจสอบและแสดงข้อความแจ้งเตือนสำหรับข้อมูลที่ซิงค์มา
        let warningBox = document.getElementById('sync_warning');
        let warningMessage = document.getElementById('sync_message');
        warningBox.style.display = 'none'; // ซ่อนไว้ก่อน
        
        if(data.description.includes('(ตัดจ่าย)')) {
            document.getElementById('detail_amount').innerText = amount + " บาท";
            document.getElementById('detail_amount').style.color = "red";
            
            warningMessage.innerText = "ข้อมูลนี้ถูกดึงมาจากหน้า ทะเบียนสั่งจ่ายเงินงบประมาณ หากต้องการแก้ไขตัวเลขให้ไปที่หน้านั้น (สามารถกดลบที่นี่ได้)";
            warningBox.style.display = 'block';
        } else if(data.description.includes('รับคืนเงินยืมโครงการ')) {
            document.getElementById('detail_amount').innerText = amount + " บาท";
            document.getElementById('detail_amount').style.color = "#198754"; // สีเขียว
            
            warningMessage.innerText = "ข้อมูลนี้ถูกดึงมาจากหน้า ทะเบียนคืนเงินโครงการ หากต้องการแก้ไขตัวเลขให้ไปที่หน้านั้น (สามารถกดลบที่นี่ได้)";
            warningBox.style.display = 'block';
        } else {
            // กรณีอื่นๆ
            if(parseFloat(data.amount) < 0) {
                document.getElementById('detail_amount').style.color = "red";
            } else {
                document.getElementById('detail_amount').style.color = "#198754"; 
            }
            document.getElementById('detail_amount').innerText = amount + " บาท";
        }

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    function printItem(id) {
        window.print();
    }
</script>