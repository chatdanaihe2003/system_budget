<?php
require_once 'includes/db.php'; 

$page_title = "อนุมัติจ่ายเงิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'อนุมัติจ่ายเงิน';

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (CRUD) ---
// --------------------------------------------------------------------------------

if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM approved_main_payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: Approvedformaintypepayment.php");
    exit();
}

if (isset($_GET['toggle_status_id']) && isset($_GET['set_status'])) {
    $id = $_GET['toggle_status_id'];
    $new_status = $_GET['set_status']; 
    
    $stmt = $conn->prepare("UPDATE approved_main_payments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();

    $stmt_get = $conn->prepare("SELECT * FROM approved_main_payments WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();
    
    if ($res_get->num_rows > 0) {
        $row_data = $res_get->fetch_assoc();
        
        $b_year = $row_data['budget_year'];
        $d_date = $row_data['doc_date'];
        $d_no = $row_data['doc_no'];
        $desc_deduct = "(ตัดจ่าย) " . $row_data['description']; 
        $neg_amount = $row_data['amount'] * -1; 
        $t_type = "โอนเงิน"; 

        $target_table = "";
        if ($row_data['payment_type'] == 'เงินงบประมาณ') {
            $target_table = 'receive_budget';
        } elseif ($row_data['payment_type'] == 'เงินนอกงบประมาณ') {
            $target_table = 'receive_off_budget';
        } elseif ($row_data['payment_type'] == 'เงินรายได้แผ่นดิน') {
            $target_table = 'receive_national';
        }

        if ($target_table != "") {
            if ($new_status == 'approved') {
                $stmt_check = $conn->prepare("SELECT id FROM $target_table WHERE description = ? AND budget_year = ? AND doc_no = ?");
                $stmt_check->bind_param("sis", $desc_deduct, $b_year, $d_no);
                $stmt_check->execute();
                
                if ($stmt_check->get_result()->num_rows == 0) {
                    $stmt_max = $conn->prepare("SELECT MAX(receive_order) as m_order FROM $target_table WHERE budget_year = ?");
                    $stmt_max->bind_param("i", $b_year);
                    $stmt_max->execute();
                    $r_max = $stmt_max->get_result()->fetch_assoc();
                    $rec_order = ($r_max['m_order'] ?? 0) + 1;

                    $stmt_ins = $conn->prepare("INSERT INTO $target_table (budget_year, receive_order, doc_date, doc_no, description, transaction_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt_ins) {
                        $stmt_ins->bind_param("iissssd", $b_year, $rec_order, $d_date, $d_no, $desc_deduct, $t_type, $neg_amount);
                        $stmt_ins->execute();
                    }
                }
            } else {
                $stmt_del = $conn->prepare("DELETE FROM $target_table WHERE description = ? AND budget_year = ? AND doc_no = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("sis", $desc_deduct, $b_year, $d_no);
                    $stmt_del->execute();
                }
            }
        }
    }

    header("Location: Approvedformaintypepayment.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pay_order = $_POST['pay_order'] ?? 0;
    
    $d_day = $_POST['d_day'] ?? date('d');
    $d_month = $_POST['d_month'] ?? date('m');
    $d_year = $_POST['d_year'] ?? date('Y');
    $real_year = (int)$d_year > 2500 ? (int)$d_year - 543 : $d_year;
    $doc_date = sprintf("%04d-%02d-%02d", $real_year, $d_month, $d_day);
    
    $doc_no = $_POST['doc_no'] ?? '';
    $ref_withdraw_no = $_POST['ref_withdraw_no'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $payee = $_POST['payee'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    $ref_petition_no = '';

    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE approved_main_payments SET doc_date=?, doc_no=?, ref_withdraw_no=?, description=?, amount=?, payment_type=?, status=? WHERE id=?");
        $stmt->bind_param("ssssdssi", $doc_date, $doc_no, $ref_withdraw_no, $description, $amount, $payment_type, $status, $id);
        $stmt->execute();
    }
    header("Location: Approvedformaintypepayment.php");
    exit();
}

$sql_data = "SELECT * FROM approved_main_payments WHERE budget_year = ? ORDER BY pay_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

require_once 'includes/header.php';
require_once 'includes/navbar.php';

$thai_months = [
    "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
    "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
    "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
];
?>

<style>
    .status-box { width: 16px; height: 16px; display: inline-block; vertical-align: middle; cursor: pointer; border: 1px solid #ccc; }
    .status-yellow { background-color: #ffff00; }
    .status-green { background-color: #00ff00; }
    .status-red { background-color: #ff0000; }
    .legend-container { margin-top: 20px; font-size: 0.85rem; }
    .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
    .legend-box { width: 14px; height: 14px; margin-right: 8px; border: 1px solid #ccc; }
    
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #dee2e6; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .btn-form { padding: 4px 20px; background-color: #e9ecef; border: 1px solid #ccc; color: #333; border-radius: 4px; font-size: 0.9rem; }
    .btn-form:hover { background-color: #d3d9df; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        <h2 class="page-title mb-4">อนุมัติจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 8%;">ที่เอกสาร</th>
                        <th style="width: 8%;">อ้างอิงขอเบิก</th>
                        <th style="width: 8%;">อ้างอิงฎีกา</th>
                        <th style="width: 30%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 10%;">ประเภทเงิน</th>
                        <th style="width: 5%;">ราย<br>ละเอียด</th>
                        <th style="width: 5%;">อนุมัติ</th>
                        <th style="width: 5%;">แก้ไข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $status_class = 'status-yellow';
                            $status_text = 'รอการอนุมัติ';
                            if($row['status'] == 'approved') { $status_class = 'status-green'; $status_text = 'อนุมัติให้จ่ายเงินได้'; }
                            elseif($row['status'] == 'rejected') { $status_class = 'status-red'; $status_text = 'ไม่อนุมัติ'; }

                            echo "<tr>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['pay_order']) . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-center text-warning'>" . htmlspecialchars($row['ref_withdraw_no'] ?: '') . "</td>"; 
                            echo "<td class='td-center text-danger'>" . htmlspecialchars($row['ref_petition_no'] ?: '') . "</td>"; 
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['payment_type']) . "</td>";
                            echo "<td class='td-center'><button class='action-btn btn-detail' onclick='openDetailModal(".htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').")'><i class='fa-solid fa-list-ul'></i></button></td>";
                            echo "<td class='td-center'><a href='javascript:void(0)' onclick='openStatusModal(".$row['id'].", \"".$row['status']."\")' title='".$status_text."'><div class='status-box ".$status_class."'></div></a></td>";
                            echo "<td class='td-center'><button class='action-btn btn-edit' onclick='openEditModal(".json_encode($row).")'><i class='fa-solid fa-pen'></i></button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="legend-container text-center">
            <div class="legend-item justify-content-center"><div class="legend-box status-yellow"></div> <span>รอการอนุมัติ</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-green"></div> <span>อนุมัติให้จ่ายเงินได้</span></div>
            <div class="legend-item justify-content-center"><div class="legend-box status-red"></div> <span>ไม่อนุมัติ</span></div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header d-block pb-0 border-0">
                <h5 class="modal-title-custom text-teal" id="modalTitle">แก้ไขการอนุมัติจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?></h5>
            </div>
            <div class="modal-body mx-4 mb-4 pt-2">
                <div class="form-white-bg">
                    <form action="Approvedformaintypepayment.php" method="POST">
                        <input type="hidden" name="action" id="form_action" value="edit">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">วันที่</div>
                            <div class="col-md-2">
                                <select name="d_day" id="d_day" class="form-select form-select-sm">
                                    <?php for($i=1; $i<=31; $i++) { echo "<option value='".sprintf("%02d", $i)."'>$i</option>"; } ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">เดือน</div>
                            <div class="col-md-3">
                                <select name="d_month" id="d_month" class="form-select form-select-sm">
                                    <?php foreach($thai_months as $num => $name) { echo "<option value='$num'>$name</option>"; } ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ปี</div>
                            <div class="col-md-3">
                                <input type="number" name="d_year" id="d_year" class="form-control form-control-sm" value="<?php echo $active_year; ?>" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ที่เอกสาร</div>
                            <div class="col-md-4">
                                <input type="text" name="doc_no" id="doc_no" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">อ้างอิงทะเบียนขอเบิก/ขอยืมเงิน</div>
                            <div class="col-md-7">
                                <select name="ref_withdraw_no" id="ref_withdraw_no" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="316 ค่าจ้างจัดทำเอกสารกลุ่มนโยบายและแผน">316 ค่าจ้างจัดทำเอกสารกลุ่มนโยบายและแผน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ประเภทของเงิน</div>
                            <div class="col-md-4">
                                <select name="payment_type" id="payment_type" class="form-select form-select-sm">
                                    <option value="เงินงบประมาณ">เงินงบประมาณ</option>
                                    <option value="เงินนอกงบประมาณ">เงินนอกงบประมาณ</option>
                                    <option value="เงินรายได้แผ่นดิน">เงินรายได้แผ่นดิน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">รายการจ่าย</div>
                            <div class="col-md-8">
                                <input type="text" name="description" id="description" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">ประเภทรายการจ่าย</div>
                            <div class="col-md-4">
                                <select name="expense_type" id="expense_type" class="form-select form-select-sm">
                                    <option value="">เลือก</option>
                                    <option value="ค่าใช้สอย">ค่าใช้สอย</option>
                                    <option value="ค่าวัสดุ">ค่าวัสดุ</option>
                                    <option value="ค่าตอบแทน">ค่าตอบแทน</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2 align-items-center">
                            <div class="col-md-4 form-label-custom">จำนวนเงิน</div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4 form-label-custom">ผู้รับเงิน</div>
                            <div class="col-md-6">
                                <input type="text" name="payee" id="payee" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center justify-content-center">
                            <div class="col-md-6 text-center" style="background-color: #f1f3f5; padding: 15px; border-radius: 8px;">
                                <div class="form-label-custom d-block mb-2" style="text-align: center;">ส่วนของการอนุมัติ:</div>
                                <select name="status" id="status" class="form-select form-select-sm mx-auto" style="width: 200px;">
                                    <option value="pending">รอการอนุมัติ</option>
                                    <option value="approved">การอนุมัติ</option>
                                    <option value="rejected">ไม่อนุมัติ</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-center mt-3 pt-2">
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
            <div class="modal-header d-block"><h5 class="modal-title-custom">รายละเอียด</h5></div>
            <div class="modal-body mx-3 mb-3">
                <div class="form-white-bg">
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">รายการ :</div><div class="col-md-9" id="view_description"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">จำนวนเงิน :</div><div class="col-md-9" id="view_amount"></div></div>
                    <div class="row mb-2"><div class="col-md-3 form-label-custom">ประเภท :</div><div class="col-md-9" id="view_payment_type"></div></div>
                    <div class="text-center mt-3 pt-3 border-top"><button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom mb-0" style="font-size: 1.1rem;">เลือกสถานะการอนุมัติ</h5>
            </div>
            <div class="modal-body text-center mx-2 mb-2">
                <button class="btn w-100 mb-2" style="background-color: #00ff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('approved')">
                    อนุมัติให้จ่ายเงินได้ (สีเขียว)
                </button>
                <button class="btn w-100 mb-2" style="background-color: #ffff00; color: #000; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('pending')">
                    รอการอนุมัติ (สีเหลือง)
                </button>
                <button class="btn w-100" style="background-color: #ff0000; color: #fff; border: 1px solid #aaa; font-weight: bold;" onclick="submitSetStatus('rejected')">
                    ไม่อนุมัติ (สีแดง)
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    let currentStatusId = null;

    function openStatusModal(id, currentStatus) {
        currentStatusId = id; 
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    function submitSetStatus(newStatus) {
        if (currentStatusId !== null) {
            window.location.href = `?toggle_status_id=${currentStatusId}&set_status=${newStatus}`;
        }
    }

    function openEditModal(data) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('modalTitle').innerHTML = 'แก้ไขการอนุมัติจ่ายเงิน ปีงบประมาณ <?php echo $active_year; ?>';
        
        if (data.doc_date) {
            let parts = data.doc_date.split('-');
            if(parts.length === 3) {
                document.getElementById('d_year').value = parseInt(parts[0]) + 543;
                document.getElementById('d_month').value = parts[1];
                document.getElementById('d_day').value = parts[2];
            }
        }
        
        document.getElementById('doc_no').value = data.doc_no || '';
        
        let refSelect = document.getElementById('ref_withdraw_no');
        if(data.ref_withdraw_no && !Array.from(refSelect.options).some(opt => opt.value === data.ref_withdraw_no)) {
            refSelect.add(new Option(data.ref_withdraw_no, data.ref_withdraw_no));
        }
        refSelect.value = data.ref_withdraw_no || '';

        document.getElementById('description').value = data.description || '';
        document.getElementById('amount').value = data.amount || '';
        document.getElementById('payment_type').value = data.payment_type || 'เงินงบประมาณ';
        document.getElementById('status').value = data.status || 'pending';
        
        new bootstrap.Modal(document.getElementById('addModal')).show();
    }

    function openDetailModal(data) {
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_payment_type').innerText = data.payment_type;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>