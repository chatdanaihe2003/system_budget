<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานการจัดสรรงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง (หรือแถบสถานะ)
$page_header = 'รายงานการจัดสรรงบประมาณ';

// --- ส่วนการดึงข้อมูลและค้นหา ---
$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != "") {
    // ถ้ามีการค้นหา ให้กรองด้วย code (รหัสโครงการ)
    $search_param = "%" . $search . "%";
    $sql_data = "SELECT * FROM budget_allocation_reports WHERE code LIKE ? ORDER BY code ASC, activity_name ASC";
    $stmt = $conn->prepare($sql_data);
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result_data = $stmt->get_result();
} else {
    // ถ้าไม่มีการค้นหา ให้ดึงทั้งหมดตามปกติ
    $sql_data = "SELECT * FROM budget_allocation_reports ORDER BY code ASC, activity_name ASC";
    $result_data = $conn->query($sql_data);
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Budget allocation report.php"] {
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

    /* Highlight Project Row (เปลี่ยนเป็นสีเทาอ่อนแทนสีเหลือง เพื่อให้ดูสะอาดตาเข้ากับธีม) */
    .project-row td {
        background-color: #f1f5f9 !important; /* สีเทาอมฟ้าอ่อน */
        font-weight: bold;
        color: #0f172a;
    }
    .activity-row td {
        color: #0369a1; /* สีน้ำเงินสำหรับการแยกกิจกรรม */
    }
</style>

<div class="container-fluid pb-5 px-3 mt-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานการจัดสรรงบประมาณจำแนกตามโครงการ</h2>
            
            <div class="d-flex flex-wrap gap-2">
                <form action="Budget allocation report.php" method="GET" class="d-flex shadow-sm" style="border-radius: 6px; overflow: hidden;">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control border-end-0" placeholder="ค้นหารหัส..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-light border border-start-0" type="submit" style="color: #64748b;"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    <?php if($search != ""): ?>
                        <a href="Budget allocation report.php" class="btn btn-danger btn-sm d-flex align-items-center"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <div class="input-group input-group-sm shadow-sm" style="width: auto; border-radius: 6px; overflow: hidden;">
                    <span class="input-group-text bg-white border-end-0 fw-bold" style="color: #0f172a;">ปีงบประมาณ</span>
                    <select class="form-select border-start-0 border-end-0" style="width: 90px; cursor: pointer;">
                        <option><?php echo $active_year; ?></option>
                        <option><?php echo $active_year - 1; ?></option>
                    </select>
                    <select class="form-select border-start-0" style="width: 140px; cursor: pointer;">
                        <option>ทุกกลุ่ม(งาน)</option>
                    </select>
                    <button class="btn btn-sm px-3 text-white" style="background-color: #00bcd4; border-color: #00bcd4;">เลือก</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 8%;">รหัส</th>
                        <th style="width: 40%; text-align: left;">โครงการ / กิจกรรม</th>
                        <th style="width: 15%; text-align: right;">งบประมาณ</th>
                        <th style="width: 17%; text-align: left;">แหล่งงบประมาณ</th>
                        <th style="width: 15%; text-align: left;">ผู้รับผิดชอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (isset($result_data) && $result_data->num_rows > 0) {
                        $current_code = '';
                        $i = 1;
                        // จัดกลุ่มข้อมูลเพื่อคำนวณยอดรวมโครงการ
                        $projects = [];
                        while($row = $result_data->fetch_assoc()) {
                            $projects[$row['code']]['name'] = $row['project_name'];
                            $projects[$row['code']]['responsible'] = $row['responsible_person']; // สมมติว่าผู้รับผิดชอบเดียวกันทั้งโครงการ
                            $projects[$row['code']]['activities'][] = $row;
                        }

                        foreach($projects as $code => $proj) {
                            // คำนวณยอดรวมโครงการ
                            $total_budget = 0;
                            foreach($proj['activities'] as $act) {
                                $total_budget += $act['budget_amount'];
                            }

                            // แสดงบรรทัดโครงการ (หัวข้อ)
                            echo "<tr class='project-row'>";
                            echo "<td class='text-center'>" . $i++ . "</td>";
                            echo "<td class='text-center'>" . $code . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($proj['name']) . "</td>";
                            echo "<td class='text-end text-danger'>" . number_format($total_budget, 2) . "</td>"; // ยอดรวมเป็นสีแดง
                            echo "<td></td>";
                            echo "<td class='text-start'>" . htmlspecialchars($proj['responsible']) . "</td>";
                            echo "</tr>";

                            // แสดงบรรทัดกิจกรรม (รายการย่อย)
                            foreach($proj['activities'] as $act) {
                                echo "<tr class='activity-row'>";
                                echo "<td></td>";
                                echo "<td></td>"; // เว้นว่างรหัส
                                echo "<td class='text-start ps-5'>- " . htmlspecialchars($act['activity_name']) . "</td>"; // ย่อหน้ากิจกรรม
                                echo "<td class='text-end fw-bold'>" . number_format($act['budget_amount'], 2) . "</td>";
                                echo "<td class='text-start'>" . htmlspecialchars($act['budget_source']) . "</td>";
                                echo "<td></td>";
                                echo "</tr>";
                            }
                        }

                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ไม่พบข้อมูลการจัดสรรงบประมาณ</td></tr>";
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