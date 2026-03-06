<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานเงินคงเหลือประจำวัน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายงานเงินคงเหลือประจำวัน';

// --- การจัดการตัวแปรวันที่ ---
$selected_day = isset($_POST['day']) ? $_POST['day'] : date('d');
$selected_month = isset($_POST['month']) ? $_POST['month'] : date('n');
$selected_year = isset($_POST['year']) ? $_POST['year'] : date('Y') + 543;
$selected_budget_year = isset($_POST['budget_year']) ? $_POST['budget_year'] : date('Y') + 543;

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM daily_cash_report ORDER BY category_name ASC, id ASC";
$result_data = $conn->query($sql_data);

// จัดกลุ่มข้อมูล
$data_grouped = [
    'เงินงบประมาณ' => [],
    'เงินนอกงบประมาณ' => [],
    'เงินรายได้แผ่นดิน' => []
];

if ($result_data && $result_data->num_rows > 0) {
    while($row = $result_data->fetch_assoc()) {
        $data_grouped[$row['category_name']][] = $row;
    }
}

// ฟังก์ชันวันที่ไทยสำหรับ Dropdown
function thai_month_arr() {
    return array(
        "1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน",
        "7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม"
    );
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Daily cash balance report.php"] {
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

    /* Row Categories - ปรับเป็นสีเทาฟ้าอ่อนให้เข้ากับธีม */
    .category-row td {
        background-color: #f1f5f9 !important; 
        font-weight: bold;
        color: #0f172a;
        text-align: left;
        padding-left: 15px;
    }
    
    /* Footer/Total Row - สีเทาอ่อนสะอาดตา */
    .total-row td {
        background-color: #f8fafc !important;
        font-weight: bold;
        color: #0f172a;
        border-top: 2px solid #cbd5e1;
    }

    .td-center { text-align: center; }
    .td-right { text-align: right; }
    .td-left { text-align: left; padding-left: 25px !important; }

    /* Filter Form */
    .filter-group { display: flex; gap: 8px; align-items: center; justify-content: flex-end; flex-wrap: wrap; }
    .form-select-sm { min-width: 80px; border-color: #e2e8f0; color: #475569; }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานเงินคงเหลือประจำวัน</h2>
            
            <form method="POST" action="" class="m-0">
                <div class="filter-group shadow-sm p-2 rounded" style="background-color: #ffffff; border: 1px solid #e2e8f0;">
                    <label class="fw-bold" style="color: #0f172a; font-size: 0.9rem;">วันที่</label>
                    <select name="day" class="form-select form-select-sm">
                        <?php for($d=1; $d<=31; $d++): ?>
                            <option value="<?php echo $d; ?>" <?php if($d == $selected_day) echo 'selected'; ?>><?php echo str_pad($d, 2, '0', STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>

                    <label class="fw-bold ms-2" style="color: #0f172a; font-size: 0.9rem;">เดือน</label>
                    <select name="month" class="form-select form-select-sm">
                        <?php foreach(thai_month_arr() as $k => $m): ?>
                            <option value="<?php echo $k; ?>" <?php if($k == $selected_month) echo 'selected'; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="fw-bold ms-2" style="color: #0f172a; font-size: 0.9rem;">ปี</label>
                    <select name="year" class="form-select form-select-sm">
                        <?php for($y=date('Y')+543; $y>=2550; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php if($y == $selected_year) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <label class="fw-bold ms-3" style="color: #0f172a; font-size: 0.9rem;">ปีงบประมาณ</label>
                    <select name="budget_year" class="form-select form-select-sm" style="width: 90px;">
                        <?php for($y=date('Y')+543+1; $y>=2550; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php if($y == $selected_budget_year) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit" class="btn btn-sm text-white ms-2" style="background-color: #00bcd4; border-color: #00bcd4; padding: 4px 15px;">เลือก</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 40%; border-bottom: 1px solid #e2e8f0;">รายการ</th>
                        <th colspan="3" style="width: 45%; border-bottom: 1px solid #e2e8f0;">คงเหลือ</th>
                        <th rowspan="2" style="width: 15%; border-bottom: 1px solid #e2e8f0;">รวม</th>
                    </tr>
                    <tr>
                        <th style="border-top: none;">เงินสด</th>
                        <th style="border-top: none;">เงินฝากธนาคาร</th>
                        <th style="border-top: none;">เงินฝากส่วนราชการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // ตัวแปรเก็บยอดรวมทั้งหมด (Grand Total)
                    $grand_cash = 0;
                    $grand_bank = 0;
                    $grand_gov = 0;
                    $grand_total = 0;

                    // วนลูปตามหมวดหมู่ที่กำหนดไว้
                    $categories_order = ['เงินงบประมาณ', 'เงินนอกงบประมาณ', 'เงินรายได้แผ่นดิน'];

                    foreach ($categories_order as $cat_name) {
                        // แสดงหัวข้อหมวดหมู่
                        echo "<tr class='category-row'><td colspan='5'>$cat_name</td></tr>";

                        // ถ้ามีข้อมูลในหมวดหมู่นั้น
                        if (!empty($data_grouped[$cat_name])) {
                            foreach ($data_grouped[$cat_name] as $item) {
                                $row_total = $item['cash_amount'] + $item['bank_amount'] + $item['gov_deposit_amount'];
                                
                                // สะสมยอดรวม
                                $grand_cash += $item['cash_amount'];
                                $grand_bank += $item['bank_amount'];
                                $grand_gov += $item['gov_deposit_amount'];
                                $grand_total += $row_total;

                                echo "<tr>";
                                echo "<td class='td-left'>- " . htmlspecialchars($item['item_name']) . "</td>";
                                echo "<td class='td-right'>" . number_format($item['cash_amount'], 2) . "</td>";
                                echo "<td class='td-right'>" . number_format($item['bank_amount'], 2) . "</td>";
                                echo "<td class='td-right'>" . number_format($item['gov_deposit_amount'], 2) . "</td>";
                                echo "<td class='td-right fw-bold' style='color: #0f172a;'>" . number_format($row_total, 2) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center text-muted py-3' style='font-size: 0.85rem;'>ไม่มีรายการ</td></tr>";
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td class="td-center" style="font-size: 1rem;">รวมทั้งสิ้น</td>
                        <td class="td-right"><?php echo number_format($grand_cash, 2); ?></td>
                        <td class="td-right"><?php echo number_format($grand_bank, 2); ?></td>
                        <td class="td-right"><?php echo number_format($grand_gov, 2); ?></td>
                        <td class="td-right fs-6 text-danger"><?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tfoot>
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