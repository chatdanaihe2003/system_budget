<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตรวจสอบจ่ายเงินทดรองราชการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // 'Check the government advance payment.php'
// ชื่อหน้าบนแถบสีทอง
$page_header = 'ตรวจสอบการจ่ายเงินทดรองราชการ';

// --- Logic เปลี่ยนสถานะ (Toggle Status) ---
if (isset($_GET['toggle_id']) && isset($_GET['type'])) {
    $id = $_GET['toggle_id'];
    $type = $_GET['type'];
    $current = $_GET['current'];

    if ($type == 'approval') {
        // วนลูปสถานะ: pending -> approved -> rejected -> pending
        $new_status = 'pending';
        if ($current == 'pending') $new_status = 'approved';
        elseif ($current == 'approved') $new_status = 'rejected';
        elseif ($current == 'rejected') $new_status = 'pending';
        
        $stmt = $conn->prepare("UPDATE approved_gov_advance_payments SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
    } elseif ($type == 'payment') {
        // วนลูปสถานะ: unpaid -> paid -> unpaid
        $new_status = ($current == 'unpaid') ? 'paid' : 'unpaid';
        
        $stmt = $conn->prepare("UPDATE approved_gov_advance_payments SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
    }

    header("Location: Check the government advance payment.php");
    exit();
}

// --- ดึงข้อมูลเฉพาะปี Active ---
$sql_data = "SELECT * FROM approved_gov_advance_payments WHERE budget_year = ? ORDER BY advance_order ASC";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $active_year);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้เป็นสีดำเข้มและตัวหนา */
    .dropdown-item[href*="Check the government advance payment.php"] {
        color: #0f172a !important;   /* สีดำ/น้ำเงินเข้ม */
        font-weight: 800 !important;  
        background-color: #f8f9fa !important; 
        border-left: 4px solid #06b6d4; /* เปลี่ยนขีดหน้าเมนูเป็นสี Cyan ตามธีมภาพ */
    }

    /* ตกแต่งการ์ดเนื้อหา (เหมือนในรูปภาพเป๊ะๆ) */
    .content-card {
        background-color: #ffffff;
        border-radius: 8px;
        padding: 30px 25px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        border-top: 4px solid #06b6d4; /* เส้นขอบบนสีฟ้าอมเขียว (Cyan) ตามรูป */
    }

    /* จัดหัวข้อให้อยู่ซ้ายมือ ตัวหนาสีเข้ม */
    .page-title-custom {
        font-weight: 700;
        color: #1e293b; 
        font-size: 1.4rem;
        margin-bottom: 0;
    }

    /* ตกแต่งตารางให้สะอาดตา ไม่มีเส้นตั้ง (เหมือนในรูป) */
    .table-custom {
        border-collapse: collapse;
        width: 100%;
        margin-top: 10px;
    }
    .table-custom thead th {
        background-color: #f8fafc; /* พื้นหลังเทาอ่อนมากๆ */
        color: #64748b; /* ตัวหนังสือสีเทา */
        text-align: center;
        vertical-align: middle;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
    }
    .table-custom tbody td {
        padding: 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9; /* เส้นคั่นแถวบางๆ */
        color: #334155;
    }
    .table-custom tbody tr:hover {
        background-color: #f8fafc;
    }

    /* ตกแต่งกล่องสถานะ */
    .status-box { 
        width: 20px; 
        height: 20px; 
        display: inline-block; 
        vertical-align: middle; 
        border-radius: 4px;
        cursor: pointer; 
        transition: transform 0.2s, box-shadow 0.2s; 
    }
    .status-box:hover { 
        transform: scale(1.15); 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .status-yellow { background-color: #fbbf24; border: 1px solid #f59e0b; }
    .status-green { background-color: #22c55e; border: 1px solid #16a34a; }
    .status-red { background-color: #ef4444; border: 1px solid #dc2626; }

    /* ปุ่มรายละเอียด (Action Button) */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
        margin: 0 2px;
    }
    .btn-detail { background-color: #f1f5f9; color: #64748b; }
    .btn-detail:hover { background-color: #e2e8f0; color: #0f172a; }

    /* คำอธิบายด้านล่าง */
    .legend-container {
        display: flex;
        justify-content: center;
        gap: 25px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #475569;
    }
    .description-text { 
        font-size: 0.95rem; 
        color: #475569; 
        line-height: 1.8; 
        text-align: justify; 
        text-indent: 40px; 
        background-color: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    /* ตกแต่ง Modal เป็นสีขาวสะอาด */
    .form-white-bg { 
        background-color: #ffffff; 
        padding: 25px; 
        border-radius: 8px; 
        border: 1px solid #dee2e6; 
    }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; color: #475569; }
    .modal-header { border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .modal-title-custom { color: #0f172a; font-weight: bold; width: 100%; text-align: center; font-size: 1.2rem;}
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title-custom">ตรวจสอบการจ่ายเงินทดรองราชการ ปี <?php echo $active_year; ?></h2>
            <div class="page-pagination text-muted" style="font-size: 0.9rem; font-weight: 500;">หน้า [1]</div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่</th>
                        <th style="width: 10%;">วดป</th>
                        <th style="width: 40%; text-align: left;">รายการ</th>
                        <th style="width: 15%; text-align: right;">จำนวนเงิน</th>
                        <th style="width: 10%;">รายละเอียด</th>
                        <th style="width: 10%;">อนุมัติ</th>
                        <th style="width: 10%;">จ่ายเงิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            
                            // สถานะอนุมัติ
                            $app_class = 'status-yellow';
                            $app_text = 'รอการอนุมัติ (คลิกเพื่อเปลี่ยน)';
                            if($row['approval_status'] == 'approved') { $app_class = 'status-green'; $app_text = 'อนุมัติแล้ว (คลิกเพื่อเปลี่ยน)'; }
                            elseif($row['approval_status'] == 'rejected') { $app_class = 'status-red'; $app_text = 'ไม่อนุมัติ (คลิกเพื่อเปลี่ยน)'; }

                            // สถานะจ่ายเงิน
                            $pay_class = 'status-red';
                            $pay_text = 'ยังไม่ได้จ่ายเงิน (คลิกเพื่อเปลี่ยน)';
                            if($row['payment_status'] == 'paid') { $pay_class = 'status-green'; $pay_text = 'จ่ายเงินแล้ว (คลิกเพื่อเปลี่ยน)'; }

                            echo "<tr>";
                            echo "<td class='text-center'>" . htmlspecialchars($row['advance_order']) . "</td>";
                            echo "<td class='text-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($row['loan_amount'], 2) . "</td>";
                            
                            // ปุ่มรายละเอียด
                            $js_data = array(
                                'description' => $row['description'],
                                'amount' => $row['loan_amount']
                            );
                            echo "<td class='text-center'>";
                            echo '<button class="action-btn btn-detail" onclick="openDetailModal('.htmlspecialchars(json_encode($js_data), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                            echo "</td>";

                            // อนุมัติ (Toggle)
                            echo "<td class='text-center'>";
                            echo '<a href="?toggle_id='.$row['id'].'&type=approval&current='.$row['approval_status'].'" title="'.$app_text.'"><div class="status-box '.$app_class.'"></div></a>';
                            echo "</td>";

                            // จ่ายเงิน (Toggle)
                            echo "<td class='text-center'>";
                            echo '<a href="?toggle_id='.$row['id'].'&type=payment&current='.$row['payment_status'].'" title="'.$pay_text.'"><div class="status-box '.$pay_class.'"></div></a>';
                            echo "</td>";

                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="legend-container">
            <div class="legend-item">
                <div class="status-box status-yellow" style="cursor: default;"></div> <span>รอการอนุมัติ</span>
            </div>
            <div class="legend-item">
                <div class="status-box status-green" style="cursor: default;"></div> <span>อนุมัติให้จ่ายเงินได้ / จ่ายเงินแล้ว</span>
            </div>
            <div class="legend-item">
                <div class="status-box status-red" style="cursor: default;"></div> <span>ไม่อนุมัติ / ยังไม่ได้จ่ายเงิน</span>
            </div>
        </div>
        
        <div class="mt-4 border-0">
            <p class="description-text">
                <strong class="text-dark">หมายเหตุ:</strong> หน้านี้ เป็นการตรวจสอบการจ่ายเงินทดรองราชการ เพื่อให้ทราบว่ามีรายการใดบ้างที่จ่ายเงินเรียบร้อยแล้ว ซึ่งถ้ารายการใดจ่ายเงินเรียบร้อยแล้ว จะปรากฏสัญลักษณ์สีเขียวช่องจ่ายเงิน ถ้ารายการใด ยังไม่จ่ายเงินจะปรากฏสัญลักษณ์สีแดง ที่ช่องจ่ายเงิน
            </p>
        </div>

    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom">รายละเอียดรายการทดรองราชการ</h5>
            </div>
            <div class="modal-body mx-3 mb-3 mt-3">
                <div class="form-white-bg">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">รายการ :</div>
                        <div class="col-md-9 text-dark" id="view_description"></div>
                    </div>
                    <hr class="text-muted">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">จำนวนเงิน :</div>
                        <div class="col-md-9 fw-bold text-dark" id="view_amount"></div>
                    </div>
                    <div class="text-center mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button>
                    </div>
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
    function openDetailModal(data) {
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    }
</script>