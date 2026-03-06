<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายการขอเบิกฯที่ยังไม่ได้วางฎีกา - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการขอเบิกฯที่ยังไม่ได้วางฎีกา';

// --- Pagination Logic ---
$limit = 25; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// นับจำนวนทั้งหมด (กรองตามปีงบประมาณ Active)
// หมายเหตุ: ตรวจสอบว่าตาราง pending_withdrawals มีคอลัมน์ budget_year หรือยังนะครับ
$sql_count = "SELECT COUNT(*) as total FROM pending_withdrawals WHERE budget_year = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $active_year);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$row_count = $result_count->fetch_assoc();
$total_rows = $row_count['total'];
$total_pages = ceil($total_rows / $limit);

// ดึงข้อมูลตามหน้า (กรองตามปีงบประมาณ Active)
$sql_data = "SELECT * FROM pending_withdrawals WHERE budget_year = ? ORDER BY doc_date ASC, id ASC LIMIT ?, ?";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("iii", $active_year, $offset, $limit);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Withdrawal requests that have not yet been submitted for approval.php"] {
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

    /* กล่องคำอธิบายด้านล่าง */
    .description-box {
        background-color: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #00bcd4; /* แถบสีฟ้าด้านซ้ายให้เข้าธีม */
        padding: 20px;
    }
</style>

<div class="container-fluid pb-5 px-3 mt-4">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
            <div>
                <h2 class="page-title-custom">รายการขอเบิกฯที่ยังไม่ได้วางฎีกา</h2>
                <small class="text-secondary" style="font-size: 0.95rem;">ปีงบประมาณ <?php echo $active_year; ?></small>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container d-flex align-items-center">
                <a href="?page=1" class="pagination-link"><i class="fa-solid fa-angles-left"></i></a> 
                <a href="?page=<?php echo max(1, $page-1); ?>" class="pagination-link"><i class="fa-solid fa-angle-left"></i></a> 
                
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
                
                <a href="?page=<?php echo min($total_pages, $page+1); ?>" class="pagination-link"><i class="fa-solid fa-angle-right"></i></a> 
                
                <select class="form-select form-select-sm d-inline-block ms-2" style="width: 70px; border-color: #e2e8f0; color: #475569;" onchange="location = '?page=' + this.value;">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 15%;">วดป</th>
                        <th style="width: 45%; text-align: left;">รายการ</th>
                        <th style="width: 15%; text-align: right;">จำนวนเงิน</th>
                        <th style="width: 25%; text-align: left;">เจ้าหน้าที่</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            echo "<tr>";
                            // ถ้ามี function thai_date_short จะดีมากครับ สมมติว่ามี
                            $date_show = isset($row['doc_date']) ? $row['doc_date'] : '';
                            echo "<td class='text-center text-secondary'>" . htmlspecialchars($date_show) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($row['amount'], 2) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['officer_name']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'>ไม่พบรายการค้างวางฎีกาในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 description-box">
            <h6 class="fw-bold mb-3" style="color: #0f172a;"><i class="fa-solid fa-circle-info me-2" style="color: #00bcd4;"></i>ข้อมูลแจ้งเตือน</h6>
            <div style="font-size: 0.95rem; color: #475569; line-height: 1.8;">
                <p class="mb-0">รายการที่แสดงคือรายการขอเบิกเงินหรือขอยืมเงินที่บันทึกเข้าระบบแล้ว แต่ยังไม่มีการอ้างอิงเลขที่ฎีกาจ่ายเงิน กรุณาตรวจสอบและดำเนินการวางฎีกาให้เรียบร้อย</p>
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