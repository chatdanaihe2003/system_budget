<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// --- ดึงสิทธิ์ผู้ใช้งานเพื่อตรวจสอบการแสดงผลปุ่ม (Admin เท่านั้น) ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// --------------------------------------------------------------------------------
// --- ตรวจสอบชื่อคอลัมน์ของ project_expenses อัตโนมัติ ป้องกัน Error SQL ---
// --------------------------------------------------------------------------------
$col_desc = 'description';
$col_amt = 'request_amount';
$has_request_amount = false;
$exp_approver_col = "";

$chk_exp = $conn->query("SHOW COLUMNS FROM project_expenses");
if ($chk_exp) {
    while($c = $chk_exp->fetch_assoc()) {
        if ($c['Field'] === 'details') $col_desc = 'details';
        if ($c['Field'] === 'cutoff_amount') $col_amt = 'cutoff_amount';
        if ($c['Field'] === 'request_amount') $has_request_amount = true;
        
        // หากลุ่มคอลัมน์ที่เก็บ ID คนที่กดอนุมัติในหน้า Approve the cut off amount
        if (in_array($c['Field'], ['approver_id', 'officer_name', 'approved_by', 'admin_id'])) {
            $exp_approver_col = $c['Field'];
        }
    }
}

$amt_cond = "CAST(pe.$col_amt AS DECIMAL(15,2)) = CAST(w.amount AS DECIMAL(15,2))";
if ($has_request_amount) {
    $amt_cond = "(CAST(pe.$col_amt AS DECIMAL(15,2)) = CAST(w.amount AS DECIMAL(15,2)) OR CAST(pe.request_amount AS DECIMAL(15,2)) = CAST(w.amount AS DECIMAL(15,2)))";
}

$join_exp = "LEFT JOIN project_expenses pe ON w.project_id = pe.project_id AND $amt_cond AND pe.approval_status = 'approved'";

$join_u3 = "";
$sel_u3 = ", NULL AS exp_approver_name, NULL AS raw_exp_approver";
if ($exp_approver_col !== "") {
    $join_u3 = "LEFT JOIN users u3 ON pe.$exp_approver_col = CAST(u3.id AS CHAR) OR pe.$exp_approver_col = u3.name";
    $sel_u3 = ", u3.name AS exp_approver_name, pe.$exp_approver_col AS raw_exp_approver";
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (ลบข้อมูล แบบเดี่ยว และ แบบหลายรายการ) ---
// --------------------------------------------------------------------------------

// ลบแบบหลายรายการพร้อมกัน (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete']) && isset($_POST['delete_ids'])) {
    $delete_ids = $_POST['delete_ids'];
    if (is_array($delete_ids) && count($delete_ids) > 0) {
        foreach ($delete_ids as $pid) {
            $pid = intval($pid);
            
            $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND approval_status = 'approved'");
            if($stmt_del_exp){
                $stmt_del_exp->bind_param("i", $pid);
                $stmt_del_exp->execute();
            }

            $stmt_del = $conn->prepare("DELETE FROM project_withdrawals WHERE project_id = ? AND status = 'approved'");
            if($stmt_del){
                $stmt_del->bind_param("i", $pid);
                $stmt_del->execute();
            }
        }
        echo "<script>window.location.href = window.location.pathname + '?deleted=1';</script>";
        exit();
    }
}

// ลบแบบทีละรายการ (เดี่ยว)
if (isset($_GET['delete_id'])) {
    $pid = intval($_GET['delete_id']);
    
    $stmt_del_exp = $conn->prepare("DELETE FROM project_expenses WHERE project_id = ? AND approval_status = 'approved'");
    if($stmt_del_exp){
        $stmt_del_exp->bind_param("i", $pid);
        $stmt_del_exp->execute();
    }

    $stmt_del = $conn->prepare("DELETE FROM project_withdrawals WHERE project_id = ? AND status = 'approved'");
    if($stmt_del){
        $stmt_del->bind_param("i", $pid);
        $stmt_del->execute();
    }
    
    echo "<script>window.location.href = window.location.pathname + '?deleted=1';</script>";
    exit();
}


// --- ส่วนของการส่งออก (Export to Excel) รายโครงการ ---
if (isset($_GET['export']) && $_GET['export'] == 'excel' && isset($_GET['project_id'])) {
    $export_pid = intval($_GET['project_id']);
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Project_Report_{$export_pid}_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "\xEF\xBB\xBF"; 
    
    $chk_pw = $conn->query("SHOW COLUMNS FROM project_withdrawals");
    $has_payment_approver = false;
    if ($chk_pw) {
        while($c = $chk_pw->fetch_assoc()) {
            if ($c['Field'] === 'payment_approver') $has_payment_approver = true;
        }
    }
    $pw_approver_col = $has_payment_approver ? "w.payment_approver" : "w.officer_name";

    // เพิ่มการดึง p.budget_type ด้วย
    $sql_export = "SELECT w.*, p.project_code, p.project_name, p.responsible_person, p.budget_type,
                          u1.name AS real_requester_name, 
                          u2.name AS real_payment_approver
                   FROM project_withdrawals w 
                   LEFT JOIN project_outcomes p ON w.project_id = p.id 
                   LEFT JOIN users u1 ON w.user_id = u1.id OR w.requester = CAST(u1.id AS CHAR) OR w.requester = u1.name
                   LEFT JOIN users u2 ON $pw_approver_col = CAST(u2.id AS CHAR) OR $pw_approver_col = u2.name
                   WHERE w.budget_year = ? AND w.status = 'approved' AND w.project_id = ? 
                   ORDER BY w.doc_date ASC, w.id ASC";
    
    $stmt_export = $conn->prepare($sql_export);
    $stmt_export->bind_param("ii", $active_year, $export_pid);
    $stmt_export->execute();
    $res_export = $stmt_export->get_result();

    $proj_name = ""; $proj_code = ""; $resp_person = ""; $budget_type = "";
    $rows = [];
    while($r = $res_export->fetch_assoc()) {
        if($proj_name == "") {
            $proj_name = $r['project_name'] ?? 'ไม่พบชื่อโครงการ (โครงการอาจถูกลบไปแล้ว)';
            $proj_code = $r['project_code'];
            $resp_person = $r['responsible_person'];
            $budget_type = $r['budget_type'] ?? '-';
        }
        $rows[] = $r;
    }

    echo "<h3>รายงานรายละเอียดการเบิกจ่ายโครงการ</h3>";
    echo "<p><b>รหัสโครงการ:</b> " . htmlspecialchars($proj_code ?: '-') . "</p>";
    echo "<p><b>ชื่อโครงการ:</b> " . htmlspecialchars($proj_name) . "</p>";
    echo "<p><b>ผู้รับผิดชอบโครงการ:</b> " . htmlspecialchars($resp_person ?: '-') . "</p>";
    echo "<p><b>ประเภทงบประมาณ:</b> " . htmlspecialchars($budget_type) . "</p>"; // แสดงประเภทงบประมาณ

    echo "<table border='1'>";
    // --- เอาผู้อนุมัติตัดยอดออก ---
    echo "<tr>
            <th style='background-color:#f4f4f4;'>ที่</th>
            <th style='background-color:#f4f4f4;'>วันที่อนุมัติตัดยอด</th>
            <th style='background-color:#f4f4f4;'>อ้างอิงเอกสาร</th>
            <th style='background-color:#f4f4f4;'>รายละเอียด/รายการการจ่าย</th>
            <th style='background-color:#f4f4f4;'>ยอดเงินที่ตัด (บาท)</th>
            <th style='background-color:#f4f4f4;'>ผู้ขอตัดยอด</th>
            <th style='background-color:#f4f4f4;'>ผู้อนุมัติให้เบิก/ให้ยืม</th>
          </tr>";
    
    $i = 1;
    $sum_export = 0;
    foreach($rows as $row) {
        $sum_export += $row['amount'];
        
        $req_name = !empty($row['real_requester_name']) ? $row['real_requester_name'] : $row['requester'];
        
        $with_approver = !empty($row['real_payment_approver']) ? $row['real_payment_approver'] : ($has_payment_approver ? $row['payment_approver'] : $row['officer_name']);
        if (is_numeric($with_approver)) {
            $uq = $conn->query("SELECT name FROM users WHERE id = " . intval($with_approver));
            if ($uq && $uq->num_rows > 0) $with_approver = $uq->fetch_assoc()['name'];
        }
        
        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td>" . thai_date_short($row['doc_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['doc_no'] ?: '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($req_name ?: '-') . "</td>";
        echo "<td>" . htmlspecialchars($with_approver ?: '-') . "</td>";
        echo "</tr>";
    }
    // ปรับ colspan ให้ตรง
    echo "<tr><td colspan='4' style='text-align:right; font-weight:bold; background-color:#eef2ff;'>รวมยอดเบิกจ่ายที่อนุมัติแล้วทั้งสิ้น:</td><td style='background-color:#eef2ff;'><strong>" . number_format($sum_export, 2) . "</strong></td><td colspan='2' style='background-color:#eef2ff;'></td></tr>";
    echo "</table>";
    exit();
}

// ตั้งค่า Header
$page_title = "รายงานรายละเอียดโครงการ ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'รายงานรายละเอียดโครงการ (ตัดยอดสำเร็จ) <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- รับค่าการค้นหา ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search . "%";

// --- ดึงข้อมูลจากตาราง เพิ่ม p.budget_type ---
$sql_data = "SELECT w.project_id, p.project_code, p.project_name, p.responsible_person, p.budget_type,
                    COUNT(w.id) as expense_count, SUM(w.amount) as total_cutoff 
             FROM project_withdrawals w 
             LEFT JOIN project_outcomes p ON w.project_id = p.id 
             WHERE w.budget_year = ? AND w.status = 'approved' ";

if ($search != "") {
    $sql_data .= "AND (p.project_name LIKE ? OR p.project_code LIKE ? OR w.doc_no LIKE ?) ";
}
$sql_data .= "GROUP BY w.project_id ORDER BY p.project_code ASC";

$stmt_data = $conn->prepare($sql_data);
if ($search != "") {
    $stmt_data->bind_param("isss", $active_year, $search_param, $search_param, $search_param);
} else {
    $stmt_data->bind_param("i", $active_year);
}
$stmt_data->execute();
$result_data = $stmt_data->get_result();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ใช้ Clean Indigo Theme เหมือน Expenses.php */
    .total-row td { background-color: #f8fafc !important; font-weight: bold; color: #333; border-top: 2px solid #e2e8f0; }
    .badge-custom { padding: 5px 10px; font-weight: normal; font-size: 0.85rem; }
    .content-card { 
        background: #ffffff; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
        padding: 35px; 
        margin-top: 25px; 
        border-top: 5px solid #4f46e5; /* Indigo */
        min-height: 500px; 
    }
    
    /* ปุ่ม Excel */
    .btn-excel {
        background-color: #10b981;
        color: white;
        border: none;
        transition: all 0.3s;
    }
    .btn-excel:hover {
        background-color: #059669;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }
    .action-container { display: flex; justify-content: center; flex-wrap: wrap; gap: 6px; }
    
    /* สไตล์สำหรับ Checkbox */
    .form-check-input { width: 1.2rem; height: 1.2rem; cursor: pointer; }
</style>

<div class="container-fluid pb-5 px-4">
    <form action="" method="POST" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete" value="1">

        <div class="content-card mt-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title m-0 fw-bold" style="color: #312e81;">
                    <i class="fa-solid fa-file-invoice me-2"></i> รายงานการเบิกจ่ายโครงการ
                    <small class="text-muted fs-6 ms-2 fw-normal">(เฉพาะรายการที่อนุมัติแล้ว)</small>
                </h2>
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="input-group shadow-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 pl-0" placeholder="ค้นหาชื่อ, รหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn text-white px-3" type="button" style="background-color: #4f46e5; border-color: #4f46e5;" onclick="window.location.href='?search='+document.getElementById('searchInput').value;">ค้นหา</button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="<?php echo $current_page; ?>" class="btn btn-outline-danger d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>

                    <?php if ($nav_role === 'admin'): ?>
                        <button type="button" class="btn btn-danger shadow-sm ms-2" style="border-radius: 8px;" onclick="checkBulkDelete()">
                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> ลบรายการที่เลือก
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive border rounded shadow-sm">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <?php if ($nav_role === 'admin'): ?>
                            <th class="text-center py-3" style="width: 3%;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <?php endif; ?>

                            <th class="text-center py-3 text-muted" style="width: 5%;">ที่</th>
                            <th class="py-3 text-muted" style="width: 15%;">รหัสโครงการ</th>
                            <th class="py-3 text-muted" style="width: 25%;">ชื่อโครงการ</th>
                            <th class="py-3 text-muted" style="width: 15%;">ผู้รับผิดชอบ / ประเภทงบ</th>
                            <th class="text-center py-3 text-muted" style="width: 10%;">จำนวนรายการเบิก</th>
                            <th class="text-end py-3 text-muted" style="width: 12%;">รวมยอดอนุมัติ (บาท)</th>
                            <th class="text-center py-3 text-muted" style="width: 15%;">คำสั่ง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data->num_rows > 0) {
                            $i = 1;
                            $grand_total_cutoff = 0;
                            
                            $chk_pw = $conn->query("SHOW COLUMNS FROM project_withdrawals");
                            $has_payment_approver = false;
                            if ($chk_pw) {
                                while($c = $chk_pw->fetch_assoc()) {
                                    if ($c['Field'] === 'payment_approver') $has_payment_approver = true;
                                }
                            }
                            $pw_approver_col = $has_payment_approver ? "w.payment_approver" : "w.officer_name";

                            $sql_details = "SELECT w.doc_date as cutoff_date, w.doc_no as ref_document, w.description, w.amount as cutoff_amount,
                                                   w.requester, w.officer_name, w.user_id";
                            if ($has_payment_approver) $sql_details .= ", w.payment_approver";
                            
                            $sql_details .= ", u1.name AS real_requester_name,
                                               u2.name AS real_payment_approver
                                            FROM project_withdrawals w 
                                            LEFT JOIN users u1 ON w.user_id = u1.id OR w.requester = CAST(u1.id AS CHAR) OR w.requester = u1.name
                                            LEFT JOIN users u2 ON $pw_approver_col = CAST(u2.id AS CHAR) OR $pw_approver_col = u2.name
                                            WHERE w.project_id = ? AND w.budget_year = ? AND w.status = 'approved' 
                                            ORDER BY w.doc_date ASC";
                            $stmt_details = $conn->prepare($sql_details);

                            while($row = $result_data->fetch_assoc()) {
                                $grand_total_cutoff += $row['total_cutoff'];
                                
                                $proj_id = $row['project_id']; 
                                $proj_code = $row['project_code'] ? $row['project_code'] : '-';
                                $proj_name = htmlspecialchars($row['project_name'] ?? 'ไม่พบชื่อโครงการ (โครงการอาจถูกลบไปแล้ว)');
                                $resp_person = htmlspecialchars($row['responsible_person'] ?? '-');
                                $budget_type = htmlspecialchars($row['budget_type'] ?? '-'); // ดึงประเภทงบ
                                $exp_count = $row['expense_count'];
                                $total_amt = number_format($row['total_cutoff'], 2);

                                $stmt_details->bind_param("ii", $proj_id, $active_year);
                                $stmt_details->execute();
                                $res_details = $stmt_details->get_result();
                                
                                $details_arr = [];
                                while($d = $res_details->fetch_assoc()) {
                                    $req_name = !empty($d['real_requester_name']) ? $d['real_requester_name'] : $d['requester'];
                                    
                                    $with_approver = '-';
                                    if (!empty($d['real_payment_approver'])) {
                                        $with_approver = $d['real_payment_approver'];
                                    } else {
                                         $val = isset($d['payment_approver']) ? $d['payment_approver'] : $d['officer_name'];
                                         if (!empty($val)) {
                                              if (is_numeric($val)) {
                                                  $uq = $conn->query("SELECT name FROM users WHERE id = " . intval($val));
                                                  if ($uq && $uq->num_rows > 0) $with_approver = $uq->fetch_assoc()['name'];
                                                  else $with_approver = $val;
                                              } else {
                                                  $with_approver = $val;
                                              }
                                         }
                                    }

                                    $details_arr[] = [
                                        'date' => thai_date_short($d['cutoff_date']),
                                        'doc' => htmlspecialchars($d['ref_document'] ?: '-'),
                                        'desc' => htmlspecialchars($d['description']),
                                        'amount' => number_format($d['cutoff_amount'], 2),
                                        'requester' => htmlspecialchars($req_name ?: '-'),
                                        'with_approver' => htmlspecialchars($with_approver ?: '-')
                                    ];
                                }

                                echo "<tr>";

                                if ($nav_role === 'admin') {
                                    echo "<td class='text-center'>
                                            <input class='form-check-input item-checkbox' type='checkbox' name='delete_ids[]' value='{$proj_id}'>
                                          </td>";
                                }

                                echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                                echo "<td><div class='text-primary fw-bold' style='font-size:0.9rem; color:#4f46e5 !important;'>[$proj_code]</div></td>";
                                echo "<td class='text-dark fw-bold'>" . $proj_name . "</td>";
                                
                                // แสดงชื่อผู้รับผิดชอบ และประเภทงบประมาณ
                                echo "<td>
                                        <div class='text-dark mb-1'>" . $resp_person . "</div>
                                        <div class='text-info fw-bold' style='font-size:0.8rem;'>" . $budget_type . "</div>
                                      </td>";
                                      
                                echo "<td class='text-center'><span class='badge bg-secondary badge-custom'>" . $exp_count . " รายการ</span></td>";
                                echo "<td class='text-end fw-bold text-danger fs-6'>" . $total_amt . "</td>";
                                
                                echo "<td class='text-center'>";
                                echo "<div class='action-container'>";
                                
                                // ส่ง $budget_type เข้าไปใน js เพื่อพิมพ์
                                $print_data = htmlspecialchars(json_encode([
                                    'code' => $proj_code,
                                    'name' => $proj_name,
                                    'person' => $resp_person,
                                    'budget_type' => $budget_type,
                                    'total' => $total_amt,
                                    'details' => $details_arr
                                ]), ENT_QUOTES, 'UTF-8');

                                echo "<button type='button' class='btn btn-sm text-white shadow-sm px-3' style='background-color: #3b82f6; border-radius: 6px;' onclick='printProjectReport({$print_data})' title='พิมพ์รายงาน'>
                                        <i class='fa-solid fa-print me-1'></i> พิมพ์
                                      </button>";
                                      
                                echo "<a href='?export=excel&project_id={$proj_id}' class='btn btn-sm btn-excel shadow-sm px-3' style='border-radius: 6px;' title='ส่งออก Excel'>
                                        <i class='fa-solid fa-file-excel me-1'></i> Excel
                                      </a>";
                                
                                if ($nav_role === 'admin') {
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger shadow-sm px-2' style='border-radius: 6px;' title='ลบข้อมูลการเบิกจ่ายโครงการนี้' onclick='openDeleteModal({$proj_id})'><i class='fa-solid fa-trash-can'></i></button>";
                                }

                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            
                            $colspan_left = ($nav_role === 'admin') ? 6 : 5;

                            echo "<tr class='total-row'>";
                            echo "<td colspan='{$colspan_left}' class='text-end py-3'><strong>รวมยอดการเบิกจ่ายที่อนุมัติแล้วทั้งหมด :</strong></td>";
                            echo "<td class='text-end py-3 text-danger fs-5'><strong>" . number_format($grand_total_cutoff, 2) . "</strong></td>";
                            echo "<td></td>";
                            echo "</tr>";

                        } else {
                            $colspan_all = ($nav_role === 'admin') ? 8 : 7;
                            echo "<tr><td colspan='{$colspan_all}' class='text-center py-5 text-muted'>";
                            echo "<i class='fa-solid fa-folder-open fs-1 mb-3 d-block text-light'></i>ไม่พบข้อมูลรายงานในปีงบประมาณ $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการลบข้อมูล</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-regular fa-circle-xmark text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูลโครงการนี้ใช่หรือไม่?</h4>
                <p class="text-muted mb-0 fs-5">ประวัติการเบิกจ่ายของโครงการนี้ทั้งหมดจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can-arrow-up me-2"></i> ยืนยันการลบหลายรายการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-layer-group text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูล <span id="bulkCountText" class="text-danger fw-bold fs-3"></span> โครงการ?</h4>
                <p class="text-muted mb-0 fs-5">ข้อมูลการเบิกจ่ายของโครงการที่ถูกเลือกทั้งหมดจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" onclick="document.getElementById('bulkDeleteForm').submit();" style="border-radius: 8px;"><i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบทั้งหมด</button>
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
                <p class="text-muted fs-6 mb-4">ลบข้อมูลการเบิกจ่ายเรียบร้อยแล้ว</p>
                <button type="button" class="btn btn-success px-5 fw-bold w-100" style="border-radius: 8px;" onclick="window.location.href = window.location.pathname">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertNoSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-center align-items-center mx-auto bg-warning bg-opacity-25 rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-exclamation text-warning" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">แจ้งเตือน</h4>
                <p class="text-muted fs-6 mb-4">กรุณาเลือกรายการอย่างน้อย 1 รายการ</p>
                <button type="button" class="btn btn-warning text-dark px-5 fw-bold w-100" style="border-radius: 8px;" data-bs-dismiss="modal">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('deleted')) {
            new bootstrap.Modal(document.getElementById('successDeleteModal')).show();
            window.history.replaceState(null, null, window.location.pathname);
        }

        const selectAllCb = document.getElementById('selectAll');
        const itemCbs = document.querySelectorAll('.item-checkbox');

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function() {
                itemCbs.forEach(cb => {
                    cb.checked = selectAllCb.checked;
                });
            });
            
            itemCbs.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = document.querySelectorAll('.item-checkbox:checked').length === itemCbs.length;
                    selectAllCb.checked = allChecked;
                });
            });
        }
    });

    function checkBulkDelete() {
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        if (checkedItems.length === 0) {
            new bootstrap.Modal(document.getElementById('alertNoSelectModal')).show();
            return;
        }
        
        document.getElementById('bulkCountText').innerText = checkedItems.length;
        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    }

    function openDeleteModal(id) {
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function printProjectReport(data) {
        let printWindow = window.open('', '_blank', 'width=1000,height=700');
        
        let detailsHtml = '';
        data.details.forEach((item, index) => {
            detailsHtml += `
                <tr>
                    <td style="text-align:center;">${index + 1}</td>
                    <td style="text-align:center;">${item.date}</td>
                    <td>${item.doc}</td>
                    <td>${item.desc}</td>
                    <td style="text-align:right;">${item.amount}</td>
                    <td>${item.requester}</td>
                    <td>${item.with_approver}</td>
                </tr>
            `;
        });
        
        let htmlContent = `
            <!DOCTYPE html>
            <html lang="th">
            <head>
                <meta charset="UTF-8">
                <title>พิมพ์รายงานเบิกจ่ายโครงการ - ${data.code}</title>
                <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { 
                        font-family: 'Sarabun', sans-serif; 
                        padding: 40px; 
                        color: #333; 
                        line-height: 1.6;
                    }
                    .header { 
                        text-align: center; 
                        margin-bottom: 20px; 
                        border-bottom: 2px solid #333; 
                        padding-bottom: 20px;
                    }
                    .header h2 { margin: 0 0 10px 0; font-size: 24px; }
                    .header p { margin: 0; font-size: 16px; color: #555; }
                    
                    .proj-info { margin-bottom: 20px; font-size: 16px; }
                    .proj-info p { margin: 5px 0; }
                    
                    .table-detail { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin-top: 10px; 
                    }
                    .table-detail th, .table-detail td { 
                        border: 1px solid #ccc; 
                        padding: 10px 12px; 
                        font-size: 15px;
                    }
                    .table-detail th { 
                        background-color: #f4f4f4; 
                        text-align: center;
                    }
                    
                    .amount-row { background-color: #eef2ff !important; }
                    .amount-text { font-size: 18px; color: #b91c1c; }
                    
                    .sign-area {
                        margin-top: 80px;
                        display: flex;
                        justify-content: flex-end;
                    }
                    .sign-box {
                        text-align: center;
                        width: 250px;
                    }
                    .sign-line {
                        border-bottom: 1px dashed #333;
                        margin-bottom: 10px;
                        height: 30px;
                    }
                    
                    @media print {
                        body { padding: 0; }
                        button { display: none; }
                        @page { size: landscape; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>รายงานสรุปรายละเอียดการเบิกจ่ายโครงการ</h2>
                    <p>ระบบแผนงานและงบประมาณ</p>
                </div>
                
                <div class="proj-info">
                    <p><strong>รหัสโครงการ:</strong> ${data.code}</p>
                    <p><strong>ชื่อโครงการ:</strong> ${data.name}</p>
                    <p><strong>ผู้รับผิดชอบโครงการ:</strong> ${data.person}</p>
                    <p><strong>ประเภทงบประมาณ:</strong> ${data.budget_type}</p>
                </div>
                
                <table class="table-detail">
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วันที่ตัดยอด</th>
                        <th style="width: 10%;">เอกสารอ้างอิง</th>
                        <th style="width: 25%;">รายละเอียดการจ่าย</th>
                        <th style="width: 10%;">จำนวนเงิน (บาท)</th>
                        <th style="width: 15%;">ผู้ขอตัดยอด</th>
                        <th style="width: 15%;">ผู้อนุมัติให้เบิก/ยืม</th>
                    </tr>
                    ${detailsHtml}
                    <tr class="amount-row">
                        <th colspan="4" style="text-align:right;">รวมยอดอนุมัติทั้งสิ้น</th>
                        <td style="text-align:right;"><strong class="amount-text">${data.total}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </table>

                <div class="sign-area">
                    <div class="sign-box">
                        <div class="sign-line"></div>
                        <p>( ....................................................... )</p>
                        <p>ผู้รับผิดชอบ / ผู้รายงาน</p>
                        <p>วันที่ ....... / ....... / ...........</p>
                    </div>
                </div>

                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                        }, 500);
                    }
                <\/script>
            </body>
            </html>
        `;
        
        printWindow.document.write(htmlContent);
        printWindow.document.close();
    }
</script>