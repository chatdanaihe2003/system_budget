<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "สรุปภาพรวมกลุ่มงาน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสถานะ
$page_header = 'สรุปภาพรวมกลุ่มงาน';

// 4. ข้อมูลกลุ่มงาน (Mock Data - หากมีตารางจริงให้เปลี่ยนเป็น SQL Query)
$work_groups = [
    1 => "กลุ่มอำนวยการ",
    2 => "กลุ่มนโยบายและแผน",
    3 => "กลุ่มส่งเสริมการจัดการศึกษา",
    4 => "กลุ่มบริหารงานบุคคล",
    5 => "กลุ่มบริหารการเงินและสินทรัพย์",
    6 => "กลุ่มหน่วยตรวจสอบภายใน",
    7 => "กลุ่มนิเทศติดตามและประเมินผลฯ",
    8 => "กลุ่มส่งเสริมการศึกษาทางไกลเทคโนโลยีสารสนเทศ",
    9 => "กลุ่มพัฒนาครูและบุคลากรฯ",
    10 => "กลุ่มกฎหมายและคดี"
];

// ตัวแปรเก็บยอดรวม
$total_projects = 0;
$total_approved = 0;
$total_disbursed = 0;
$total_remaining = 0;
$total_pending = 0;

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    /* ในที่นี้หน้าสรุปภาพรวมมักอยู่ที่เมนู รายการหลัก */
    .nav-link-custom[href="index.php"] {
        color: #0f172a !important;   
        font-weight: 800 !important;  
        border-bottom: 3px solid #00bcd4;
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
        margin-bottom: 5px;
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
        padding: 12px 8px;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
        border-left: none;
        border-right: none;
    }
    .table-custom tbody td {
        background-color: #ffffff;
        padding: 12px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9; /* เส้นคั่นแถวบางๆ */
        border-left: none;
        border-right: none;
        color: #334155;
        font-size: 0.9rem;
    }
    .table-custom tbody tr:hover td {
        background-color: #f8fafc; /* สีพื้นหลังตอนเมาส์ชี้ */
    }

    /* สีตัวเลขสถานะ */
    .text-approved { color: #10b981; font-weight: 600; } 
    .text-disbursed { color: #ef4444; font-weight: 600; } 
    .text-remaining { color: #3b82f6; font-weight: 600; } 
    .text-pending { color: #f59e0b; font-weight: 600; }   

    /* แถวสรุปยอด (Footer) */
    .total-row td { 
        background-color: #f1f5f9 !important; 
        font-weight: bold; 
        color: #0f172a; 
        border-top: 2px solid #cbd5e1;
        text-align: right;
    }

    .btn-view { 
        background-color: #f1f5f9; 
        color: #475569; 
        border: 1px solid #e2e8f0; 
        padding: 4px 12px; 
        border-radius: 6px; 
        font-size: 0.8rem;
        transition: 0.2s;
    }
    .btn-view:hover {
        background-color: #00bcd4;
        color: white;
        border-color: #00bcd4;
    }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="page-title-custom"><i class="fa-solid fa-chart-pie me-2" style="color: #00bcd4;"></i>สรุปภาพรวมกลุ่มงาน</h2>
                <small class="text-secondary">สรุปข้อมูลโครงการและงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></small>
            </div>
            <div class="page-pagination text-muted mt-2" style="font-size: 0.9rem; font-weight: 500;">หน้า [1]</div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 5%;">ลำดับ</th>
                        <th rowspan="2" style="width: 30%; text-align: left;">กลุ่มงาน</th>
                        <th rowspan="2" style="width: 10%;">จำนวน<br>โครงการ</th>
                        <th colspan="4" style="border-bottom: 1px solid #e2e8f0;">ข้อมูลการเงิน (บาท)</th>
                        <th rowspan="2" style="width: 10%;">รายละเอียด</th>
                    </tr>
                    <tr>
                        <th style="width: 11%; border-top: none;">ได้รับอนุมัติ</th>
                        <th style="width: 11%; border-top: none;">เบิกจ่ายแล้ว</th>
                        <th style="width: 11%; border-top: none;">คงเหลือ</th>
                        <th style="width: 11%; border-top: none;">รออนุมัติ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($work_groups as $index => $group_name) {
                        $project_count = 0;
                        // ตัวอย่าง Mock Data
                        if($index == 7) $project_count = 1; 

                        $approved = 0.00;
                        $disbursed = 0.00;
                        $remaining = 0.00;
                        $pending = 0.00;

                        // สะสมยอดรวม
                        $total_projects += $project_count;
                        $total_approved += $approved;
                        $total_disbursed += $disbursed;
                        $total_remaining += $remaining;
                        $total_pending += $pending;

                        echo "<tr>";
                        echo "<td class='text-center text-secondary'>$index</td>";
                        echo "<td class='text-start fw-bold'>$group_name</td>";
                        echo "<td class='text-center'>$project_count</td>";
                        echo "<td class='text-end text-approved'>" . number_format($approved, 2) . "</td>";
                        echo "<td class='text-end text-disbursed'>" . number_format($disbursed, 2) . "</td>";
                        echo "<td class='text-end text-remaining'>" . number_format($remaining, 2) . "</td>";
                        echo "<td class='text-end text-pending'>" . number_format($pending, 2) . "</td>";
                        echo "<td class='text-center'><button class='btn-view'><i class='fa-solid fa-eye me-1'></i>ดู</button></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="text-center">รวมทั้งสิ้น</td>
                        <td class="text-center"><?php echo $total_projects; ?></td>
                        <td class="text-end"><?php echo number_format($total_approved, 2); ?></td>
                        <td class="text-end"><?php echo number_format($total_disbursed, 2); ?></td>
                        <td class="text-end"><?php echo number_format($total_remaining, 2); ?></td>
                        <td class="text-end"><?php echo number_format($total_pending, 2); ?></td>
                        <td></td>
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
        let navLinks = document.querySelectorAll('.nav-link-custom');
        navLinks.forEach(function(link) {
            if(link.innerText.includes('รายการหลัก')) {
                link.style.color = '#00bcd4'; 
                link.style.borderBottom = '3px solid #00bcd4'; 
                link.style.paddingBottom = '5px';
            }
        });
    });
</script>