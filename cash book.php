<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "สมุดเงินสด - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'สมุดเงินสด';

// --- Pagination Logic ---
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 30; // ตั้งค่าเริ่มต้นหน้า 30 ตามรูป
$offset = ($page - 1) * $limit;

// ดึงข้อมูล
$sql_data = "SELECT * FROM cash_book ORDER BY id ASC"; 
// หมายเหตุ: ในการใช้งานจริงควรมี WHERE budget_year
$result_data = $conn->query($sql_data);

// คำนวณยอดรวม (จำลองจากข้อมูลทั้งหมดในตาราง เพื่อแสดงใน Footer)
$sql_sum = "SELECT 
    SUM(receive_amount) as total_receive, 
    SUM(pay_amount) as total_pay,
    (SELECT balance_cash FROM cash_book ORDER BY id DESC LIMIT 1) as last_cash,
    (SELECT balance_bank FROM cash_book ORDER BY id DESC LIMIT 1) as last_bank,
    (SELECT balance_gov FROM cash_book ORDER BY id DESC LIMIT 1) as last_gov
    FROM cash_book";
$result_sum = $conn->query($sql_sum);
$row_sum = $result_sum->fetch_assoc();

// ฟังก์ชันวันที่ไทยย่อ (เฉพาะหน้านี้)
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
    .dropdown-item[href*="cash book.php"] {
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
        font-size: 0.85rem;
        padding: 10px 4px;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
        border-left: none;
        border-right: none;
    }
    .table-custom tbody td {
        background-color: #ffffff;
        padding: 8px 4px;
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

    /* Pagination Style ใหม่ */
    .pagination-container { text-align: center; margin-bottom: 20px; font-size: 0.9rem; color: #64748b; }
    .pagination-link { color: #00bcd4; text-decoration: none; margin: 0 2px; padding: 2px 6px; border: 1px solid #e2e8f0; border-radius: 4px; transition: 0.2s;}
    .pagination-link:hover { background-color: #f8fafc; border-color: #cbd5e1; }
    .pagination-active { color: #ffffff; font-weight: bold; padding: 2px 8px; border-radius: 4px; background-color: #00bcd4; border: 1px solid #00bcd4; }

    .td-center { text-align: center; }
    .td-right { text-align: right; }
    .td-left { text-align: left; }

    /* Total Row ปรับให้เป็นเทาฟ้าอ่อน */
    .total-row td { 
        background-color: #f1f5f9 !important; 
        font-weight: bold; 
        color: #0f172a; 
        border-top: 2px solid #cbd5e1;
        padding: 12px 4px;
    } 
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <h2 class="page-title-custom">สมุดเงินสด</h2>
            
            <div class="input-group shadow-sm" style="width: auto; border-radius: 6px; overflow: hidden;">
                <span class="input-group-text bg-white border-end-0 fw-bold" style="color: #0f172a; font-size: 0.9rem;">ปีงบประมาณ</span>
                <select class="form-select form-select-sm border-start-0 border-end-0" style="width: 100px; cursor: pointer; font-size: 0.9rem;">
                    <option value="2568">2568</option>
                </select>
                <select class="form-select form-select-sm border-start-0" style="width: 120px; cursor: pointer; font-size: 0.9rem;">
                    <option>ทุกประเภท</option>
                </select>
                <button class="btn btn-sm px-3 text-white" style="background-color: #00bcd4; border-color: #00bcd4;">เลือก</button>
            </div>
        </div>

        <div class="pagination-container d-flex justify-content-center align-items-center flex-wrap gap-1">
            <a href="#" class="pagination-link">&lt;หน้าแรก</a> 
            <a href="#" class="pagination-link">&lt;&lt;หน้าก่อน</a> 
            <a href="#" class="pagination-link">15</a>
            <a href="#" class="pagination-link">16</a>
            <a href="#" class="pagination-link">17</a>
            <a href="#" class="pagination-link">18</a>
            <a href="#" class="pagination-link">19</a>
            <a href="#" class="pagination-link">20</a>
            <a href="#" class="pagination-link">21</a>
            <a href="#" class="pagination-link">22</a>
            <a href="#" class="pagination-link">23</a>
            <a href="#" class="pagination-link">24</a>
            <a href="#" class="pagination-link">25</a>
            <a href="#" class="pagination-link">26</a>
            <a href="#" class="pagination-link">27</a>
            <a href="#" class="pagination-link">28</a>
            <a href="#" class="pagination-link">29</a>
            <span class="pagination-active mx-1">30</span> 
            <span class="ms-2">หน้า</span>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 3%; border-bottom: 1px solid #e2e8f0;">ที่</th>
                        <th rowspan="2" style="width: 8%; border-bottom: 1px solid #e2e8f0;">วันที่</th>
                        <th rowspan="2" style="width: 8%; border-bottom: 1px solid #e2e8f0;">ที่เอกสาร</th>
                        <th rowspan="2" style="width: 25%; border-bottom: 1px solid #e2e8f0;">รายการ</th>
                        <th rowspan="2" style="width: 10%; border-bottom: 1px solid #e2e8f0;">ลักษณะรายการ</th>
                        <th rowspan="2" style="width: 8%; border-bottom: 1px solid #e2e8f0;">เปลี่ยน</th>
                        <th rowspan="2" style="width: 8%; border-bottom: 1px solid #e2e8f0;">รับ</th>
                        <th rowspan="2" style="width: 8%; border-bottom: 1px solid #e2e8f0;">จ่าย</th>
                        <th colspan="3" style="border-bottom: 1px solid #e2e8f0;">คงเหลือ</th>
                        <th rowspan="2" style="width: 5%; border-bottom: 1px solid #e2e8f0;">รวม</th>
                    </tr>
                    <tr>
                        <th style="border-top: none;">เงินสด</th>
                        <th style="border-top: none;">เงินฝาก<br>ธนาคาร</th>
                        <th style="border-top: none;">เงินฝากส่วน<br>ราชการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        $i = 31; // เริ่มที่ 31 ตามภาพ
                        while($row = $result_data->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center text-secondary'>" . $i++ . "</td>";
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['doc_no']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='td-left'>" . htmlspecialchars($row['transaction_type']) . "</td>";
                            echo "<td class='td-right'>" . ($row['transfer_amount'] ? number_format($row['transfer_amount'], 2) : '') . "</td>";
                            echo "<td class='td-right fw-bold text-success'>" . ($row['receive_amount'] ? number_format($row['receive_amount'], 2) : '') . "</td>";
                            echo "<td class='td-right fw-bold text-danger'>" . ($row['pay_amount'] ? number_format($row['pay_amount'], 2) : '') . "</td>";
                            
                            // คงเหลือ
                            echo "<td class='td-right fw-bold' style='color: #0f172a;'>" . number_format($row['balance_cash'], 2) . "</td>";
                            echo "<td class='td-right fw-bold' style='color: #0f172a;'>" . number_format($row['balance_bank'], 2) . "</td>";
                            echo "<td class='td-right fw-bold' style='color: #0f172a;'>" . number_format($row['balance_gov'], 2) . "</td>";
                            
                            echo "<td class='td-center'>" . htmlspecialchars($row['status_text']) . "</td>";
                            echo "</tr>";
                        }

                        // แถวรวมยอด (Footer)
                        echo "<tr class='total-row'>";
                        echo "<td colspan='6' class='td-center fs-6'>รวม</td>";
                        echo "<td class='td-right text-success fs-6'>" . number_format($row_sum['total_receive'], 2) . "</td>";
                        echo "<td class='td-right text-danger fs-6'>" . number_format($row_sum['total_pay'], 2) . "</td>";
                        echo "<td class='td-right fs-6'>" . number_format($row_sum['last_cash'], 2) . "</td>";
                        echo "<td class='td-right fs-6'>" . number_format($row_sum['last_bank'], 2) . "</td>";
                        echo "<td class='td-right fs-6'>" . number_format($row_sum['last_gov'], 2) . "</td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='12' class='text-center py-5 text-muted'>ไม่พบข้อมูล</td></tr>";
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