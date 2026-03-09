<?php
// [1. เรียกใช้ DB]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ทะเบียนรายการเบิกจ่าย (ตัดยอดโครงการ) - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ทะเบียนรายการเบิกจ่ายโครงการ';

// --- สร้างตาราง project_expenses อัตโนมัติ หากยังไม่มี ---
$check_table = $conn->query("SHOW TABLES LIKE 'project_expenses'");
if ($check_table->num_rows == 0) {
    $sql_create = "CREATE TABLE project_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        budget_year INT NOT NULL,
        project_id INT NOT NULL,
        cutoff_date DATE,
        ref_document VARCHAR(100),
        description TEXT,
        request_amount DECIMAL(15,2) DEFAULT 0,
        cutoff_amount DECIMAL(15,2) DEFAULT 0,
        recorded_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $conn->query($sql_create);
}

// --------------------------------------------------------------------------------
// --- Logic จัดการข้อมูล (ลบข้อมูล และคืนเงินกลับเข้าโครงการ) ---
// --------------------------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // ดึงข้อมูลก่อนลบเพื่อจะเอาไปหักลบ (คืนเงิน)
    $stmt_get = $conn->prepare("SELECT project_id, cutoff_amount FROM project_expenses WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();
    
    if ($res_get->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM project_expenses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    
    header("Location: Expenses.php");
    exit();
}

// --- ดึงข้อมูลประวัติการเบิกจ่าย พร้อมเชื่อม (JOIN) กับชื่อโครงการ ---
$sql_data = "SELECT e.*, p.project_code, p.project_name 
             FROM project_expenses e 
             LEFT JOIN project_outcomes p ON e.project_id = p.id 
             WHERE e.budget_year = ? 
             ORDER BY e.cutoff_date DESC, e.id DESC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

$total_request = 0;
$total_cutoff = 0;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row td { background-color: #f8f9fa !important; font-weight: bold; color: #333; border-top: 2px solid #ddd; }
    .badge-custom { padding: 5px 10px; font-weight: normal; font-size: 0.85rem; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title m-0 fw-bold text-success">
                <i class="fa-solid fa-receipt me-2"></i> ประวัติการเบิกจ่าย (ตัดยอดโครงการ)
            </h2>
            <a href="Cut off the project budget.php" class="btn btn-primary" style="border-radius: 8px;">
                <i class="fa-solid fa-arrow-left me-1"></i> กลับไปหน้าตัดยอด
            </a>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="text-center py-3" style="width: 10%;">วันที่ตัดยอด</th>
                        <th class="py-3" style="width: 12%;">อ้างอิงเอกสาร</th>
                        <th class="py-3" style="width: 25%;">โครงการที่เบิกจ่าย</th>
                        <th class="py-3" style="width: 20%;">รายละเอียด/รายการ</th>
                        <th class="text-end py-3" style="width: 10%;">ยอดที่ขอตัดยอด</th>
                        <th class="text-end py-3" style="width: 10%;">ยอดอนุมัติ (ตัดจริง)</th>
                        <th class="text-center py-3" style="width: 8%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        $i = 1;
                        while($row = $result_data->fetch_assoc()) {
                            $total_request += $row['request_amount'];
                            $total_cutoff += $row['cutoff_amount'];
                            
                            $proj_code = $row['project_code'] ? $row['project_code'] : '-';
                            $proj_name = htmlspecialchars($row['project_name'] ?? 'ไม่พบชื่อโครงการ');

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                            echo "<td class='text-center'>" . thai_date_short($row['cutoff_date']) . "</td>";
                            echo "<td><span class='badge bg-secondary badge-custom'>" . htmlspecialchars($row['ref_document'] ?: 'ไม่มีเลขที่') . "</span></td>";
                            
                            echo "<td>
                                    <div class='text-primary fw-bold' style='font-size:0.85rem;'>[$proj_code]</div>
                                    <div class='text-dark'>$proj_name</div>
                                  </td>";
                                  
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end text-muted'>" . number_format($row['request_amount'], 2) . "</td>";
                            echo "<td class='text-end fw-bold text-danger'>" . number_format($row['cutoff_amount'], 2) . "</td>";
                            
                            echo "<td class='text-center'>";
                            echo "<a href='?delete_id=".$row['id']."' class='action-btn btn-delete' onclick=\"return confirm('คุณต้องการลบประวัติการเบิกจ่ายนี้ใช่หรือไม่?');\" title='ยกเลิกรายการนี้'><i class='fa-solid fa-trash-can'></i></a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        
                        echo "<tr class='total-row'>";
                        echo "<td colspan='5' class='text-end py-3'><strong>รวมยอดการเบิกจ่ายทั้งหมด :</strong></td>";
                        echo "<td class='text-end py-3 text-muted'>" . number_format($total_request, 2) . "</td>";
                        echo "<td class='text-end py-3 text-danger fs-6'>" . number_format($total_cutoff, 2) . "</td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>";
                        echo "<i class='fa-solid fa-file-invoice fs-1 mb-3 d-block text-light'></i>ยังไม่มีประวัติการเบิกจ่ายตัดยอดในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>