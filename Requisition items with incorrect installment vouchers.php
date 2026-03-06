<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายการขอเบิกฯที่วางฎีกาผิดใบงวด - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'รายการขอเบิกฯที่วางฎีกาผิดใบงวด';

// --- Pagination Logic ---
$limit = 25; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 1. นับจำนวนทั้งหมด (กรองตามปี Active)
$sql_count = "SELECT COUNT(*) as total FROM incorrect_installment_requests WHERE budget_year = ?";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    $stmt_count->bind_param("i", $active_year);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $total_rows = $row_count['total'];
    $total_pages = ceil($total_rows / $limit);
} else {
    $total_rows = 0;
    $total_pages = 0;
}

// 2. ดึงข้อมูลตามหน้า (กรองตามปี Active)
$sql_data = "SELECT * FROM incorrect_installment_requests WHERE budget_year = ? ORDER BY doc_date ASC, id ASC LIMIT ?, ?";
$stmt_data = $conn->prepare($sql_data);

if ($stmt_data === false) {
    // กรณีตารางยังไม่มีคอลัมน์ budget_year จะไม่ Fatal Error แต่จะแจ้งเตือนแทน
    $error_msg = "โครงสร้างตารางไม่ถูกต้อง: " . $conn->error;
} else {
    $stmt_data->bind_param("iii", $active_year, $offset, $limit);
    $stmt_data->execute();
    $result_data = $stmt_data->get_result();
}

// [เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Requisition items with incorrect installment vouchers.php"] {
        color: #0f172a !important;   
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
        margin-bottom: 5px;
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

    /* ตกแต่ง Pagination ให้ดูสะอาดตา (White Theme) */
    .pagination-container { font-size: 0.9rem; color: #64748b; }
    .pagination-link { color: #00bcd4; text-decoration: none; margin: 0 4px; padding: 4px 10px; border-radius: 4px; border: 1px solid #e2e8f0; background: #ffffff; transition: 0.2s;}
    .pagination-link:hover { background-color: #f1f5f9; border-color: #cbd5e1; }
    .pagination-active { color: #ffffff; font-weight: bold; padding: 4px 12px; border-radius: 4px; background-color: #00bcd4; border: 1px solid #00bcd4; }
</style>

<div class="container-fluid pb-5 px-3 mt-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
            <div>
                <h2 class="page-title-custom">รายการขอเบิกฯที่วางฎีกาผิดใบงวด</h2>
                <small class="text-secondary" style="font-size: 0.95rem;">ปีงบประมาณ <?php echo $active_year; ?></small>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container d-flex align-items-center">
                <a href="?page=1" class="pagination-link"><i class="fa-solid fa-angles-left"></i> &lt;&lt;</a> 
                <a href="?page=<?php echo max(1, $page-1); ?>" class="pagination-link"><i class="fa-solid fa-angle-left"></i> ก่อนหน้า</a> 
                
                <?php
                $start_loop = max(1, $page - 2);
                $end_loop = min($total_pages, $page + 2);
                for ($i = $start_loop; $i <= $end_loop; $i++) {
                    if ($i == $page) {
                        echo '<span class="pagination-active mx-1">'.$i.'</span> ';
                    } else {
                        echo '<a href="?page='.$i.'" class="pagination-link">'.$i.'</a> ';
                    }
                }
                ?>
                
                <a href="?page=<?php echo min($total_pages, $page+1); ?>" class="pagination-link">ถัดไป <i class="fa-solid fa-angle-right"></i></a> 
                
                <select class="form-select form-select-sm d-inline-block ms-2" style="width: 70px; border-color: #e2e8f0; color: #475569;" onchange="location = '?page=' + this.value;">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger m-3 shadow-sm border-0 border-start border-4 border-danger">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error_msg; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ที่</th>
                            <th style="width: 15%;">วดป</th>
                            <th style="width: 45%; text-align: left;">รายการ</th>
                            <th style="width: 15%; text-align: right;">จำนวนเงิน</th>
                            <th style="width: 20%; text-align: left;">เจ้าหน้าที่</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_data && $result_data->num_rows > 0) {
                            $i = $offset + 1;
                            while($row = $result_data->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='text-center text-secondary'>" . $i . "</td>"; $i++;
                                echo "<td class='text-center'>" . htmlspecialchars($row['doc_date']) . "</td>";
                                echo "<td class='text-start'>" . htmlspecialchars($row['description']) . "</td>";
                                echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($row['amount'], 2) . "</td>";
                                echo "<td class='text-start'>" . htmlspecialchars($row['officer_name']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-5 text-muted'>ไม่พบรายการผิดพลาดในปีงบประมาณ $active_year</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            // หากข้อความในเมนูหลักมีคำว่า "ตรวจสอบ"
            if(link.innerText.includes('ตรวจสอบ')) {
                link.style.color = '#00bcd4'; // เปลี่ยนตัวหนังสือเป็นสีฟ้า (Cyan)
                link.style.borderBottom = '3px solid #00bcd4'; // เพิ่มเส้นใต้สีฟ้า
                link.style.paddingBottom = '5px';
            }
        });
    });
</script>