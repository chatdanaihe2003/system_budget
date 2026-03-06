<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานการใช้จ่าย จำแนกตามประเภทรายการจ่าย - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายงานการใช้จ่าย จำแนกตามประเภทรายการจ่าย';

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM expenditure_by_types ORDER BY type_code ASC";
$result_data = $conn->query($sql_data);

// คำนวณยอดรวม (ถ้าต้องการใช้)
$total_amount = 0;
$sql_sum = "SELECT SUM(amount) as total FROM expenditure_by_types";
$result_sum = $conn->query($sql_sum);
if ($result_sum && $result_sum->num_rows > 0) {
    $row_sum = $result_sum->fetch_assoc();
    $total_amount = $row_sum['total'];
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Expenditure report categorized by type of.php"] {
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
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานการใช้จ่าย จำแนกตามประเภทรายการจ่าย</h2>
            
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
                        <th style="width: 8%;">ที่</th>
                        <th style="width: 10%;">รหัส</th>
                        <th style="width: 50%; text-align: left;">ประเภทรายการจ่าย</th>
                        <th style="width: 20%; text-align: right;">จำนวนเงิน</th>
                        <th style="width: 12%;">ร้อยละ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        $i = 1;
                        while($row = $result_data->fetch_assoc()) {
                            
                            $pct = ($row['percentage'] > 0) ? number_format($row['percentage'], 2) : '';

                            echo "<tr>";
                            echo "<td class='text-center text-secondary'>" . $i++ . "</td>";
                            echo "<td class='text-center fw-bold'>" . htmlspecialchars($row['type_code']) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['type_name']) . "</td>";
                            echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='text-center'>" . htmlspecialchars($pct) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>ไม่พบข้อมูล</td></tr>";
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