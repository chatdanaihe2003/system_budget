<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตรวจสอบการจัดสรรงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // 'Check budget allocation.php'
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ตรวจสอบการจัดสรรงบประมาณ';

// --- ดึงข้อมูลการจัดสรรงบประมาณ ---
$sql_data = "SELECT * FROM budget_allocation_checks ORDER BY period_no ASC";
$result_data = $conn->query($sql_data);

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Check budget allocation.php"] {
        color: #0f172a !important;   /* สีน้ำเงินเข้ม */
        font-weight: 800 !important;  
        background-color: #f8f9fa !important; 
        border-left: 4px solid #00bcd4; /* เส้นสีฟ้า (Cyan) ด้านหน้าเมนูย่อย */
    }

    /* ตกแต่งการ์ดเนื้อหา (ขอบบนสีฟ้าเหมือนในรูป) */
    .content-card {
        background-color: #ffffff;
        border-radius: 8px;
        padding: 30px 25px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        border-top: 4px solid #00bcd4; /* เส้นขอบบนสีฟ้า (Cyan) */
    }

    /* จัดหัวข้อให้อยู่ซ้ายมือ ตัวหนาสีเข้ม */
    .page-title-custom {
        font-weight: 700;
        color: #1e293b; 
        font-size: 1.4rem;
        margin-bottom: 0;
    }

    /* ตกแต่งตารางให้สะอาดตา ไม่มีเส้นแนวตั้ง (เหมือนในรูป) */
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

    /* กล่องคำอธิบายด้านล่าง */
    .description-box {
        background-color: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #00bcd4; /* แถบสีฟ้าด้านซ้ายให้เข้าธีม */
        padding: 20px;
    }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title-custom">ตรวจสอบการจัดสรรงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h2>
            <div class="page-pagination text-muted" style="font-size: 0.9rem; font-weight: 500;">หน้า [1]</div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 10%;">ใบงวด</th>
                        <th style="width: 40%; text-align: left;">รายการ</th>
                        <th style="width: 20%; text-align: right;">จำนวนเงินตามใบงวด</th>
                        <th style="width: 20%; text-align: right;">จัดสรรกิจกรรมในโครงการ</th>
                        <th style="width: 10%;">คงเหลือ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            $period_amt = $row['period_amount'];
                            $allocated_amt = $row['allocated_amount'];
                            $remaining = $period_amt - $allocated_amt;
                            // กำหนดสีตัวเลขคงเหลือ
                            $text_color = ($remaining < 0) ? 'text-danger' : 'text-success';
                            
                            echo "<tr>";
                            echo "<td class='text-center fw-bold text-secondary'>" . htmlspecialchars($row['period_no']) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($period_amt, 2) . "</td>";
                            echo "<td class='text-end'>" . number_format($allocated_amt, 2) . "</td>";
                            echo "<td class='text-center fw-bold $text_color'>" . number_format($remaining, 2) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>ไม่พบข้อมูลการจัดสรรในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 description-box">
            <h6 class="fw-bold mb-3" style="color: #0f172a;"><i class="fa-solid fa-circle-info me-2" style="color: #00bcd4;"></i>คำอธิบายการตรวจสอบ</h6>
            <div class="row" style="font-size: 0.9rem; color: #475569;">
                <div class="col-md-6">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong class="text-dark">• ใบงวด:</strong> เลขที่เอกสารแจ้งงบประมาณ</li>
                        <li><strong class="text-dark">• จำนวนเงินตามใบงวด:</strong> งบประมาณเต็มที่ได้รับมา</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong class="text-dark">• จัดสรรกิจกรรม:</strong> งบที่นำไปผูกกับโครงการแล้ว</li>
                        <li><strong class="text-dark">• คงเหลือ:</strong> หากเป็น <span class="text-danger fw-bold">ตัวเลขสีแดง</span> หมายถึงจัดสรรเกินงบที่ได้รับ</li>
                    </ul>
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
    document.addEventListener("DOMContentLoaded", function() {
        // ค้นหาลิงก์ใน Navbar ทั้งหมด
        let navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            // หากข้อความในเมนูมีคำว่า "ตรวจสอบ"
            if(link.innerText.includes('ตรวจสอบ')) {
                link.style.color = '#00bcd4'; // เปลี่ยนตัวหนังสือเป็นสีฟ้า (Cyan)
                link.style.borderBottom = '3px solid #00bcd4'; // เพิ่มเส้นใต้สีฟ้า
                link.style.paddingBottom = '5px';
            }
        });
    });
</script>