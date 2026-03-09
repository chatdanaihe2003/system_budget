<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตรวจสอบการจัดสรรงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ตรวจสอบการจัดสรรงบประมาณ';

// --- ดึงข้อมูลจาก 2 หน้ามารวมกันเพื่อแสดงผลให้ครบถ้วน 100% ---
$merged_data = [];

// 1. ดึงข้อมูลจากหน้า Budgetallocation.php (ตาราง budget_allocations)
$check_ba = $conn->query("SHOW TABLES LIKE 'budget_allocations'");
if ($check_ba && $check_ba->num_rows > 0) {
    $sql_ba = "SELECT * FROM budget_allocations WHERE budget_year = ? ORDER BY id ASC";
    $stmt_ba = $conn->prepare($sql_ba);
    $stmt_ba->bind_param("i", $active_year);
    $stmt_ba->execute();
    $res_ba = $stmt_ba->get_result();
    while($row = $res_ba->fetch_assoc()) {
        $merged_data[] = $row;
    }
}

// 2. ดึงข้อมูลจากหน้า Projectoutcomes.php (ตาราง project_outcomes)
$check_po = $conn->query("SHOW TABLES LIKE 'project_outcomes'");
if ($check_po && $check_po->num_rows > 0) {
    $sql_po = "SELECT * FROM project_outcomes WHERE budget_year = ? ORDER BY id ASC";
    $stmt_po = $conn->prepare($sql_po);
    $stmt_po->bind_param("i", $active_year);
    $stmt_po->execute();
    $res_po = $stmt_po->get_result();
    
    while($row_po = $res_po->fetch_assoc()) {
        // เช็คว่ารายการนี้เคยถูกเพิ่มใน budget_allocations ไปแล้วหรือยัง ป้องกันการแสดงข้อมูลซ้ำเบิ้ล
        $is_duplicate = false;
        foreach ($merged_data as $m) {
            if (!empty($row_po['project_code']) && !empty($m['project_code']) && $row_po['project_code'] === $m['project_code']) {
                $is_duplicate = true; break;
            }
            if (!empty($row_po['project_name']) && (!empty($m['project_name']) && $row_po['project_name'] === $m['project_name'])) {
                $is_duplicate = true; break;
            }
            if (!empty($row_po['project_name']) && (!empty($m['description']) && $row_po['project_name'] === $m['description'])) {
                $is_duplicate = true; break;
            }
        }
        
        // ถ้ายังไม่มีข้อมูลนี้ ให้ดึงมาแสดงด้วย
        if (!$is_duplicate) {
            // จำลองโครงสร้างข้อมูลให้กดเปิดดูรายละเอียด Modal ได้ปกติ
            $row_po['allocation_order'] = '-';
            $row_po['doc_no'] = $row_po['project_code'];
            $row_po['doc_date'] = '-';
            $row_po['ref_alloc_doc'] = '-';
            $row_po['plan_type'] = '-';
            $row_po['project_type'] = $row_po['project_name'];
            $row_po['main_activity'] = '-';
            $row_po['sub_activity'] = '-';
            $row_po['fund_source'] = '-';
            $row_po['account_code'] = '-';
            $row_po['expense_budget'] = '-';
            $row_po['description'] = $row_po['project_name'];
            $row_po['detail_desc'] = '-';
            $row_po['amount'] = $row_po['budget_amount'];
            $row_po['recorded_by'] = 'ส่งข้อมูลจากหน้าผลผลิตโครงการ';
            $row_po['file_name'] = '';
            
            $merged_data[] = $row_po;
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .action-container { display: flex; justify-content: center; gap: 8px; }
    
    /* CSS สำหรับ Modal รายละเอียด */
    .form-white-bg { background-color: #ffffff; padding: 25px 40px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .form-label-custom { font-weight: normal; text-align: right; font-size: 0.95rem; color: #000; padding-top: 5px; }
    .modal-header { border-bottom: 1px solid #dee2e6; background-color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #006666; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div style="width: 100px;"></div> 
            <h2 class="page-title m-0">ตรวจสอบการจัดสรรงบประมาณ (ปีงบประมาณ <?php echo $active_year; ?>)</h2>
            <div style="width: 100px;"></div> 
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">ปีงบประมาณ</th>
                        <th style="width: 15%;">รหัส</th>
                        <th style="width: 20%;">ชื่อผลผลิต/โครงการ</th>
                        <th style="width: 10%;">เงินงบประมาณ<br>โครงการ</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 1</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 2</th>
                        <th style="width: 9%;">ยอดจัดสรรเงิน<br>ครั้งที่ 3</th>
                        <th style="width: 9%;">ยอดคงเหลือ</th>
                        <th style="width: 6%;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_budget = 0;
                    if (count($merged_data) > 0) {
                        $i = 1;
                        foreach($merged_data as $row) {
                            // ดึงค่าให้ตรงกับคอลัมน์ (ถ้ามี amount ให้ใช้ amount ก่อน ถ้าไม่มีให้ใช้ budget_amount)
                            $display_budget = (!empty($row['budget_amount']) && $row['budget_amount'] > 0) ? $row['budget_amount'] : ($row['amount'] ?? 0);
                            $total_budget += $display_budget;
                            
                            $alloc_1 = $row['allocation_1'] ?? 0;
                            $alloc_2 = $row['allocation_2'] ?? 0;
                            $alloc_3 = $row['allocation_3'] ?? 0;
                            
                            // คำนวณยอดคงเหลือ
                            $remaining_balance = $display_budget - ($alloc_1 + $alloc_2 + $alloc_3);

                            $display_code = !empty($row['project_code']) ? $row['project_code'] : ($row['doc_no'] ?? '-');
                            $display_name = !empty($row['project_name']) ? $row['project_name'] : ($row['description'] ?? '-');

                            echo "<tr>";
                            echo "<td class='td-center'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['budget_year']) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($display_code) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($display_name) . "</td>";
                            echo "<td class='td-right text-success fw-bold'>" . ($display_budget > 0 ? number_format($display_budget, 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($alloc_1 > 0 ? number_format($alloc_1, 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($alloc_2 > 0 ? number_format($alloc_2, 2) : '-') . "</td>";
                            echo "<td class='td-right'>" . ($alloc_3 > 0 ? number_format($alloc_3, 2) : '-') . "</td>";
                            echo "<td class='td-right text-primary fw-bold'>" . number_format($remaining_balance, 2) . "</td>";
                            
                            // แสดงเฉพาะปุ่มรายละเอียด
                            echo "<td class='td-center'>";
                            echo "<div class='action-container'>";
                            echo '<button class="action-btn" title="รายละเอียดเพิ่มเติม" onclick=\'openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')\'><i class="fa-regular fa-rectangle-list"></i></button>';
                            echo "</div>";
                            echo "</td>";

                            echo "</tr>";
                        }
                        // แถวรวมยอด
                        echo "<tr class='total-row'>";
                        echo "<td colspan='4' class='text-center'>รวมยอดเงินงบประมาณโครงการ</td>";
                        echo "<td class='td-right text-success'>" . number_format($total_budget, 2) . "</td>";
                        echo "<td colspan='5'></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='10' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลการจัดสรรงบประมาณ ในปี $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title text-primary fw-bold"><i class="fa-solid fa-circle-info"></i> ข้อมูลการจัดสรรงบประมาณอย่างละเอียด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body mx-3">
                <table class="table table-bordered table-sm mb-0 mt-2">
                    <tbody>
                        <tr><th style="width: 35%; background-color: #f8f9fa;">ที่ใบงวด</th><td id="view_allocation_order"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">วันที่เอกสาร</th><td id="view_doc_date"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ที่เอกสาร / รหัสโครงการ</th><td id="view_doc_no"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">อ้างถึงหนังสือจัดสรร</th><td id="view_ref_alloc_doc"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แผนงาน</th><td id="view_plan_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผลผลิต/โครงการ</th><td id="view_project_type"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลัก</th><td id="view_main_activity"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">กิจกรรมหลักเพิ่มเติม</th><td id="view_sub_activity"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">แหล่งของเงิน</th><td id="view_fund_source"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รหัสทางบัญชี</th><td id="view_account_code"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">งบรายจ่าย</th><td id="view_expense_budget"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายการ</th><td id="view_description"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">รายละเอียดเพิ่มเติม</th><td id="view_detail_desc"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">เงินงบประมาณโครงการ</th><td id="view_amount" class="text-danger fw-bold fs-6"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ผู้บันทึกข้อมูล</th><td id="view_recorded_by"></td></tr>
                        <tr><th style="background-color: #f8f9fa;">ไฟล์แนบ</th><td id="view_file"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 pb-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button></div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openDetailModal(data) {
        document.getElementById('view_allocation_order').innerText = data.allocation_order || '-';
        
        let code = data.doc_no || data.project_code || '-';
        document.getElementById('view_doc_no').innerText = code;
        
        document.getElementById('view_doc_date').innerText = data.doc_date || '-';
        document.getElementById('view_ref_alloc_doc').innerText = data.ref_alloc_doc || '-';
        document.getElementById('view_plan_type').innerText = data.plan_type || '-';
        
        let pname = data.project_type || data.project_name || '-';
        document.getElementById('view_project_type').innerText = pname;
        
        document.getElementById('view_main_activity').innerText = data.main_activity || '-';
        document.getElementById('view_sub_activity').innerText = data.sub_activity || '-';
        document.getElementById('view_fund_source').innerText = data.fund_source || '-';
        document.getElementById('view_account_code').innerText = data.account_code || '-';
        document.getElementById('view_expense_budget').innerText = data.expense_budget || '-';
        
        let desc = data.description || data.project_name || '-';
        document.getElementById('view_description').innerText = desc;
        
        document.getElementById('view_detail_desc').innerText = data.detail_desc || '-';
        document.getElementById('view_recorded_by').innerText = data.recorded_by || '-';
        
        let amt = (data.budget_amount && data.budget_amount > 0) ? data.budget_amount : (data.amount || 0);
        document.getElementById('view_amount').innerText = parseFloat(amt).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        
        var fileArea = document.getElementById('view_file');
        if (data.file_name) {
            fileArea.innerHTML = `<a href="uploads/${data.file_name}" target="_blank" class="btn btn-success btn-sm py-0"><i class="fa-solid fa-download"></i> ดาวน์โหลดไฟล์แนบ</a>`;
        } else {
            fileArea.innerHTML = `<span class="text-muted"><i class="fa-solid fa-file-circle-xmark"></i> ไม่มีไฟล์แนบ</span>`;
        }
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>