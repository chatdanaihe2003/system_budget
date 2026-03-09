<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานการจัดสรรงบประมาณ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง (หรือแถบสถานะ)
$page_header = 'รายงานการจัดสรรงบประมาณ';

// --- ส่วนการดึงข้อมูลและค้นหาจากทั้ง 3 ตารางมารวมกัน ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = []; // อาร์เรย์สำหรับเก็บข้อมูลที่จัดกลุ่ม

// 1. ดึงข้อมูลโครงการหลักจากหน้า Projectoutcomes (ตาราง project_outcomes)
$check_po = $conn->query("SHOW TABLES LIKE 'project_outcomes'");
if ($check_po && $check_po->num_rows > 0) {
    $sql_po = "SELECT * FROM project_outcomes WHERE budget_year = ?";
    if ($search != "") {
        $search_param = "%" . $search . "%";
        $sql_po .= " AND (project_code LIKE ? OR project_name LIKE ?)";
        $stmt_po = $conn->prepare($sql_po);
        $stmt_po->bind_param("iss", $active_year, $search_param, $search_param);
    } else {
        $stmt_po = $conn->prepare($sql_po);
        $stmt_po->bind_param("i", $active_year);
    }
    
    if ($stmt_po) {
        $stmt_po->execute();
        $res_po = $stmt_po->get_result();
        while ($row = $res_po->fetch_assoc()) {
            $code = trim($row['project_code'] ?? '');
            $name = trim($row['project_name'] ?? '');
            
            // สร้าง Key เพื่อจัดกลุ่ม ถ้ารหัสไม่มีก็ใช้ชื่อ ถ้าไม่มีทั้งคู่ใช้อันอื่นแทน (ป้องกันข้อมูลหาย)
            $key = ($code !== '') ? $code : 'NO_CODE_' . $name;
            if ($key === 'NO_CODE_') $key = 'NO_CODE_GEN_' . $row['id'];
            
            if (!isset($projects[$key])) {
                $projects[$key] = [
                    'code' => $code !== '' ? $code : '-',
                    'name' => $name !== '' ? $name : '-',
                    'responsible' => '-', // เว้นไว้เนื่องจากยังไม่มีฟิลด์นี้ในฐานข้อมูล
                    'total_budget' => floatval($row['budget_amount'] ?? 0),
                    'activities' => []
                ];
            }
        }
    }
}

// 2. ดึงข้อมูลกิจกรรมย่อยจากหน้า Budgetallocation (ตาราง budget_allocations)
$check_ba = $conn->query("SHOW TABLES LIKE 'budget_allocations'");
if ($check_ba && $check_ba->num_rows > 0) {
    $sql_ba = "SELECT * FROM budget_allocations WHERE budget_year = ?";
    if ($search != "") {
        $search_param = "%" . $search . "%";
        $sql_ba .= " AND (project_code LIKE ? OR project_name LIKE ? OR description LIKE ?)";
        $stmt_ba = $conn->prepare($sql_ba);
        $stmt_ba->bind_param("isss", $active_year, $search_param, $search_param, $search_param);
    } else {
        $stmt_ba = $conn->prepare($sql_ba);
        $stmt_ba->bind_param("i", $active_year);
    }
    
    if ($stmt_ba) {
        $stmt_ba->execute();
        $res_ba = $stmt_ba->get_result();
        while ($row = $res_ba->fetch_assoc()) {
            $code = trim($row['project_code'] ?? '');
            $name = trim($row['project_name'] ?? '');
            
            $key = ($code !== '') ? $code : 'NO_CODE_' . $name;
            if ($key === 'NO_CODE_') $key = 'NO_CODE_BA_' . $row['id'];
            
            // ถ้าไม่มีโครงการนี้อยู่ ให้สร้างขึ้นมาใหม่เพื่อรองรับกิจกรรม
            if (!isset($projects[$key])) {
                $projects[$key] = [
                    'code' => $code !== '' ? $code : '-',
                    'name' => $name !== '' ? $name : (!empty($row['description']) ? $row['description'] : '-'),
                    'responsible' => '-',
                    'total_budget' => floatval($row['budget_amount'] ?? 0),
                    'activities' => []
                ];
            }
            
            // ถ้าตารางหลักไม่มีงบ แต่ตารางนี้มีงบหลัก ให้ดึงมาใส่
            if ($projects[$key]['total_budget'] == 0 && floatval($row['budget_amount'] ?? 0) > 0) {
                $projects[$key]['total_budget'] = floatval($row['budget_amount']);
            }
            
            // เพิ่มกิจกรรมย่อย
            if (!empty($row['description']) || floatval($row['amount'] ?? 0) > 0) {
                $projects[$key]['activities'][] = [
                    'activity_name' => !empty($row['description']) ? $row['description'] : 'รายการจัดสรร',
                    'budget_amount' => floatval($row['amount'] ?? 0),
                    'fund_source' => !empty($row['fund_source']) ? $row['fund_source'] : '-'
                ];
            }
        }
    }
}

// 3. ดึงข้อมูลจากหน้า Check budget allocation (ตาราง check_budget_allocations)
$check_cba = $conn->query("SHOW TABLES LIKE 'check_budget_allocations'");
if ($check_cba && $check_cba->num_rows > 0) {
    $sql_cba = "SELECT * FROM check_budget_allocations WHERE budget_year = ?";
    if ($search != "") {
        $search_param = "%" . $search . "%";
        $sql_cba .= " AND (project_code LIKE ? OR project_name LIKE ? OR description LIKE ?)";
        $stmt_cba = $conn->prepare($sql_cba);
        $stmt_cba->bind_param("isss", $active_year, $search_param, $search_param, $search_param);
    } else {
        $stmt_cba = $conn->prepare($sql_cba);
        $stmt_cba->bind_param("i", $active_year);
    }
    
    if ($stmt_cba) {
        $stmt_cba->execute();
        $res_cba = $stmt_cba->get_result();
        while ($row = $res_cba->fetch_assoc()) {
            $code = trim($row['project_code'] ?? '');
            $name = trim($row['project_name'] ?? '');
            
            $key = ($code !== '') ? $code : 'NO_CODE_' . $name;
            if ($key === 'NO_CODE_') $key = 'NO_CODE_CBA_' . $row['id'];
            
            if (!isset($projects[$key])) {
                $projects[$key] = [
                    'code' => $code !== '' ? $code : '-',
                    'name' => $name !== '' ? $name : (!empty($row['description']) ? $row['description'] : '-'),
                    'responsible' => '-',
                    'total_budget' => floatval($row['budget_amount'] ?? 0),
                    'activities' => []
                ];
            }
            
            // ตรวจสอบข้อมูลซ้ำ (เพราะ Budget allocation กับ Check budget มักจะซิงค์กัน)
            $is_dup = false;
            $act_name = !empty($row['description']) ? $row['description'] : 'รายการจัดสรร';
            $act_amount = floatval($row['amount'] ?? 0);
            
            foreach ($projects[$key]['activities'] as $act) {
                if ($act['activity_name'] === $act_name && floatval($act['budget_amount']) === $act_amount) {
                    $is_dup = true;
                    break;
                }
            }
            
            // ถ้าไม่ซ้ำ ถึงจะเอามาแสดง
            if (!$is_dup && ($act_name !== 'รายการจัดสรร' || $act_amount > 0)) {
                $projects[$key]['activities'][] = [
                    'activity_name' => $act_name,
                    'budget_amount' => $act_amount,
                    'fund_source' => !empty($row['fund_source']) ? $row['fund_source'] : '-'
                ];
            }
        }
    }
}

// จัดเรียงรหัสโครงการ
ksort($projects);

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
                        <input type="text" name="search" class="form-control border-end-0" placeholder="ค้นหารหัส/ชื่อ..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <th style="width: 10%;">รหัส</th>
                        <th style="width: 40%; text-align: left;">โครงการ / กิจกรรม</th>
                        <th style="width: 15%; text-align: right;">งบประมาณ</th>
                        <th style="width: 15%; text-align: left;">แหล่งงบประมาณ</th>
                        <th style="width: 15%; text-align: left;">ผู้รับผิดชอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($projects) > 0) {
                        $i = 1;
                        foreach($projects as $key => $proj) {
                            
                            // คำนวณงบประมาณ: ถ้าไม่มีงบหลัก ให้เอางบย่อยมาบวกกัน
                            $display_budget = $proj['total_budget'];
                            if ($display_budget == 0 && count($proj['activities']) > 0) {
                                foreach($proj['activities'] as $act) {
                                    $display_budget += $act['budget_amount'];
                                }
                            }

                            // 1. แสดงแถวโครงการ (แถวหัวข้อหลัก)
                            echo "<tr class='project-row'>";
                            echo "<td class='text-center'>" . $i++ . "</td>";
                            echo "<td class='text-center text-primary'>" . htmlspecialchars($proj['code']) . "</td>";
                            echo "<td class='text-start text-primary'>" . htmlspecialchars($proj['name']) . "</td>";
                            echo "<td class='text-end text-danger'>" . number_format($display_budget, 2) . "</td>"; 
                            echo "<td></td>";
                            echo "<td class='text-start'>" . htmlspecialchars($proj['responsible']) . "</td>";
                            echo "</tr>";

                            // 2. แสดงแถวกิจกรรมย่อย ภายใต้โครงการนั้นๆ
                            foreach($proj['activities'] as $act) {
                                echo "<tr class='activity-row'>";
                                echo "<td></td>";
                                echo "<td></td>"; 
                                echo "<td class='text-start ps-5'>- " . htmlspecialchars($act['activity_name']) . "</td>";
                                echo "<td class='text-end fw-bold text-success'>" . number_format($act['budget_amount'], 2) . "</td>";
                                echo "<td class='text-start'>" . htmlspecialchars($act['fund_source']) . "</td>";
                                echo "<td></td>";
                                echo "</tr>";
                            }
                        }

                    } else {
                        // ถ้ายังไม่มีข้อมูลในระบบเลย จะแสดงแถวเปล่าๆ แจ้งเตือนสวยๆ ให้ครับ
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลการจัดสรรงบประมาณในปีงบประมาณ $active_year</td></tr>";
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