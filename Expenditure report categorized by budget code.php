<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานการใช้จ่ายงบประมาณจำแนกตามรหัสงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายงานการใช้จ่ายจำแนกตามรหัสงบประมาณ';

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM expenditure_budget_codes ORDER BY budget_code ASC, id ASC";
$result_data = $conn->query($sql_data);

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Expenditure report categorized by budget code.php"] {
        color: #0f172a !important;   /* สีน้ำเงินเข้ม */
        font-weight: 800 !important;  
        background-color: #f8f9fa !important; 
        border-left: 4px solid #00bcd4; /* เส้นสีฟ้า (Cyan) ด้านหน้าเมนูย่อย */
    }

    /* ตกแต่งการ์ดเนื้อหา (ขอบบนสีฟ้า) */
    .content-card {
        background-color: #ffffff;
        border-radius: 8px;
        padding: 30px 25px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        border-top: 4px solid #00bcd4; /* เส้นขอบบนสีฟ้า (Cyan) */
        margin-top: 20px;
    }

    /* จัดหัวข้อให้อยู่ซ้ายมือ ตัวหนาสีเข้ม */
    .page-title-custom {
        font-weight: 700;
        color: #1e293b; 
        font-size: 1.4rem;
        margin-bottom: 0;
    }

    /* ตกแต่งตารางให้สะอาดตา ไม่มีเส้นแนวตั้ง (White Theme) */
    .table-custom {
        border-collapse: collapse;
        width: 100%;
        margin-top: 10px;
    }
    .table-custom thead th {
        background-color: #f8fafc; /* พื้นหัวตารางสีเทาอ่อนมากๆ */
        color: #64748b; /* ตัวหนังสือสีเทา */
        text-align: center;
        vertical-align: middle;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
        border-left: none;
        border-right: none;
    }
    .table-custom tbody td {
        background-color: #ffffff;
        padding: 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9; /* เส้นคั่นแถวบางๆ */
        border-left: none;
        border-right: none;
        color: #334155;
    }
    .table-custom tbody tr:hover td {
        background-color: #f8fafc; /* สีพื้นหลังตอนเมาส์ชี้ */
    }

    /* Row Styles */
    .header-row td { 
        background-color: #f1f5f9 !important; /* เปลี่ยนจากสีเหลืองเป็นสีเทาฟ้าอ่อน */
        font-weight: bold; 
        color: #0f172a;
    }
    .detail-row td { color: #475569; }

    .btn-detail { color: #64748b; font-size: 1.1rem; border: none; background: none; cursor: pointer; transition: 0.2s;} 
    .btn-detail:hover { transform: scale(1.2); color: #0f172a; }

    /* ปรับแต่ง Modal ให้พื้นหลังสะอาดตา */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #475569; }
    .modal-header { border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #0f172a; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานการใช้จ่ายงบประมาณจำแนกตามรหัสงบประมาณ</h2>
            
            <div class="input-group shadow-sm" style="width: auto; border-radius: 6px; overflow: hidden;">
                <span class="input-group-text bg-white border-end-0 fw-bold" style="color: #0f172a; font-size: 0.9rem;">ปีงบประมาณ</span>
                <select class="form-select form-select-sm border-start-0" style="width: 100px; cursor: pointer; font-size: 0.9rem;">
                    <option value="<?php echo $active_year; ?>"><?php echo $active_year; ?></option>
                    <option value="<?php echo $active_year - 1; ?>"><?php echo $active_year - 1; ?></option>
                </select>
                <button class="btn btn-sm px-3 text-white" style="background-color: #00bcd4; border-color: #00bcd4;">เลือก</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 15%; text-align: left;">รหัสงบประมาณ</th>
                        <th style="width: 30%; text-align: left;">งบรายจ่าย</th>
                        <th style="width: 12%; text-align: right;">เงินตามใบงวด</th>
                        <th style="width: 12%; text-align: right;">ฎีกาเบิก</th>
                        <th style="width: 10%; text-align: right;">คืนคลัง</th>
                        <th style="width: 10%; text-align: right;">คงเหลือ</th>
                        <th style="width: 6%;">%จ่าย</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        $groups = [];
                        while($row = $result_data->fetch_assoc()) {
                            $groups[$row['budget_code']]['items'][] = $row;
                        }

                        $i = 1;
                        foreach($groups as $code => $group) {
                            // คำนวณยอดรวมของกลุ่ม (Header)
                            $total_budget_period = 0;
                            $total_withdrawal = 0;
                            $total_return = 0;

                            foreach($group['items'] as $item) {
                                $total_budget_period += $item['budget_period_amount'];
                                $total_withdrawal += $item['withdrawal_amount'];
                                $total_return += $item['return_amount'];
                            }
                            
                            $balance = $total_budget_period - $total_withdrawal + $total_return;
                            $percent = ($total_budget_period > 0) ? ($total_withdrawal / $total_budget_period) * 100 : 0;

                            // 1. แสดงแถว Header (สีเทาฟ้าอ่อน)
                            echo "<tr class='header-row'>";
                            echo "<td class='text-center'>" . $i++ . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($code) . "</td>";
                            echo "<td></td>";
                            echo "<td class='text-end'>" . number_format($total_budget_period, 2) . "</td>";
                            echo "<td class='text-end text-danger'>" . number_format($total_withdrawal, 2) . "</td>";
                            echo "<td class='text-end text-success'>" . number_format($total_return, 2) . "</td>";
                            echo "<td class='text-end'>" . number_format($balance, 2) . "</td>";
                            echo "<td class='text-center'>" . (($percent > 0) ? number_format($percent, 2) : '') . "</td>";
                            echo "</tr>";

                            // 2. แสดงแถวย่อย (รายการ)
                            foreach($group['items'] as $item) {
                                echo "<tr class='detail-row'>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "<td class='text-start ps-4'>- " . htmlspecialchars($item['expenditure_item']) . "</td>";
                                echo "<td></td>"; // เงินตามใบงวด (ว่างไว้ตามภาพ)
                                echo "<td class='text-end fw-bold'>" . number_format($item['withdrawal_amount'], 2) . "</td>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "<td class='text-center'>";
                                echo '<button class="btn-detail" onclick="openDetailModal('.htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                                echo "</td>";
                                echo "</tr>";
                            }
                        }

                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>ไม่พบข้อมูลจำแนกตามรหัสงบประมาณ</td></tr>";
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
            <div class="modal-header d-block">
                <h5 class="modal-title-custom">รายละเอียดงบรายจ่าย</h5>
            </div>
            <div class="modal-body mx-3 mb-3 mt-3">
                <div class="form-white-bg">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">รหัสงบประมาณ :</div>
                        <div class="col-md-9 text-dark fw-bold" id="view_budget_code"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">งบรายจ่าย :</div>
                        <div class="col-md-9 text-dark" id="view_expenditure_item"></div>
                    </div>
                    <hr class="text-muted">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">ฎีกาเบิก :</div>
                        <div class="col-md-9 text-danger fw-bold" id="view_withdrawal_amount"></div>
                    </div>
                    <div class="text-center mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// [4. เรียกใช้ Footer]
require_once 'includes/footer.php'; 
?>

<script>
    function openDetailModal(data) {
        document.getElementById('view_budget_code').innerText = data.budget_code;
        document.getElementById('view_expenditure_item').innerText = data.expenditure_item;
        document.getElementById('view_withdrawal_amount').innerText = parseFloat(data.withdrawal_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    }

    // Script สำหรับทำไฮไลต์เมนู "รายงาน" ใน Navbar ให้เป็นสีฟ้า
    document.addEventListener("DOMContentLoaded", function() {
        let navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            // หากข้อความในเมนูหลักมีคำว่า "รายงาน"
            if(link.innerText.includes('รายงาน')) {
                link.style.color = '#00bcd4'; // เปลี่ยนตัวหนังสือเป็นสีฟ้า (Cyan)
                link.style.borderBottom = '3px solid #00bcd4'; // เพิ่มเส้นใต้สีฟ้า
                link.style.paddingBottom = '5px';
            }
        });
    });
</script>