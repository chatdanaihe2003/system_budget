<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานลูกหนี้เงินยืม - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสถานะ
$page_header = 'รายงานลูกหนี้เงินยืม';

// --- ดึงข้อมูล ---
// เรียงลำดับตามประเภทเงินก่อน เพื่อให้ง่ายต่อการจัดกลุ่ม
$sql_data = "SELECT * FROM loan_report_data ORDER BY money_type DESC, loan_date ASC";
$result_data = $conn->query($sql_data);

// ฟังก์ชันวันที่ไทยย่อ (เช็คเผื่อกรณีไม่มีใน db.php)
if (!function_exists('thai_date_short')) {
    function thai_date_short($date_str) {
        if(!$date_str || $date_str == '0000-00-00') return "";
        $timestamp = strtotime($date_str);
        $thai_month_arr = array("0"=>"","1"=>"ม.ค.","2"=>"ก.พ.","3"=>"มี.ค.","4"=>"เม.ย.","5"=>"พ.ค.","6"=>"มิ.ย.","7"=>"ก.ค.","8"=>"ส.ค.","9"=>"ก.ย.","10"=>"ต.ค.","11"=>"พ.ย.","12"=>"ธ.ค.");
        $d = str_pad(date("j", $timestamp), 2, '0', STR_PAD_LEFT); 
        $m = date("n", $timestamp);
        $y = date("Y", $timestamp) + 543;
        $y_short = substr($y, -2);
        return "$d {$thai_month_arr[$m]} $y_short"; 
    }
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Loan Report.php"] {
        color: #0f172a !important;   
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
        font-size: 0.85rem;
        padding: 12px 8px;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
        border-left: none;
        border-right: none;
    }
    .table-custom tbody td {
        background-color: #ffffff;
        padding: 10px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9; /* เส้นคั่นแถวบางๆ */
        border-left: none;
        border-right: none;
        color: #334155;
        font-size: 0.85rem;
    }
    .table-custom tbody tr:hover td {
        background-color: #f8fafc; /* สีพื้นหลังตอนเมาส์ชี้ */
    }

    /* Status Colors */
    .status-on-time { color: #10b981; font-weight: bold; } /* เขียวมรกต */
    .status-overdue { color: #ef4444; font-weight: bold; } /* แดง */

    /* Summary Row ปรับให้เป็นสีฟ้าอ่อนสะอาดตา */
    .summary-row td { 
        background-color: #f0f9ff !important; 
        font-weight: bold; 
        color: #0369a1; 
        text-align: right;
        border-top: 1px solid #bae6fd;
    }
    .summary-label { text-align: center !important; }

    .td-center { text-align: center; }
    .td-right { text-align: right; }
    .td-left { text-align: left; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานลูกหนี้เงินยืม</h2>
            
            <div class="input-group shadow-sm" style="width: auto; border-radius: 6px; overflow: hidden;">
                <span class="input-group-text bg-white border-end-0 fw-bold" style="color: #0f172a; font-size: 0.9rem;">ปีงบประมาณ</span>
                <select class="form-select form-select-sm border-start-0" style="width: 100px; cursor: pointer; font-size: 0.9rem;">
                    <option value="<?php echo $active_year; ?>"><?php echo $active_year; ?></option>
                </select>
                <button class="btn btn-sm px-3 text-white" style="background-color: #00bcd4; border-color: #00bcd4;">เลือก</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 3%;">ที่</th>
                        <th style="width: 10%;">วันยืม</th>
                        <th style="width: 20%;">ผู้ยืม</th>
                        <th style="width: 35%;">รายการ</th>
                        <th style="width: 10%;">จำนวนเงิน</th>
                        <th style="width: 12%;">ประเภทเงิน</th>
                        <th style="width: 10%;">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        $current_type = "";
                        $type_total = 0;
                        $i = 1;
                        
                        // เก็บข้อมูลใส่ array ก่อนเพื่อจัดกลุ่ม
                        $rows = [];
                        while($row = $result_data->fetch_assoc()) {
                            $rows[] = $row;
                        }

                        foreach ($rows as $index => $row) {
                            // ถ้าประเภทเงินเปลี่ยน และไม่ใช่แถวแรก ให้แสดงยอดรวมของกลุ่มก่อนหน้า
                            if ($current_type != "" && $current_type != $row['money_type']) {
                                echo "<tr class='summary-row'>";
                                echo "<td colspan='4' class='summary-label'>รวมลูกหนี้{$current_type}</td>";
                                echo "<td>" . number_format($type_total, 2) . "</td>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "</tr>";
                                $type_total = 0; // รีเซ็ตยอดรวม
                            }
                            
                            $current_type = $row['money_type'];
                            $type_total += $row['amount'];

                            // สถานะ
                            $status_class = ($row['status'] == 'on_time') ? 'status-on-time' : 'status-overdue';
                            $status_text = ($row['status'] == 'on_time') ? 'ในเวลา' : 'ครบกำหนด';

                            echo "<tr>";
                            echo "<td class='td-center text-secondary'>$i</td>"; $i++;
                            echo "<td class='td-center'>" . thai_date_short($row['loan_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['borrower']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-right fw-bold' style='color: #0f172a;'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='td-center'>" . htmlspecialchars($row['money_type']) . "</td>";
                            echo "<td class='td-center " . $status_class . "'>" . $status_text . "</td>";
                            echo "</tr>";

                            // ถ้าเป็นแถวสุดท้าย ให้แสดงยอดรวมด้วย
                            if ($index === count($rows) - 1) {
                                echo "<tr class='summary-row'>";
                                echo "<td colspan='4' class='summary-label'>รวมลูกหนี้{$current_type}</td>";
                                echo "<td>" . number_format($type_total, 2) . "</td>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "</tr>";
                            }
                        }

                    } else { 
                        echo "<tr><td colspan='7' class='text-center py-5 text-muted'>ไม่พบข้อมูลลูกหนี้เงินยืม</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php 
// [4. เรียกใช้ Footer]
require_once 'includes/footer.php'; 
?>

<script>
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