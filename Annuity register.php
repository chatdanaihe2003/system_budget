<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year และ Function Date มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ - AMSS++";
// กำหนดหน้าปัจจุบัน (ชื่อไฟล์ต้องตรงกับเงื่อนไขใน Navbar เพื่อให้เมนู Active)
// หมายเหตุ: ชื่อไฟล์นี้มีเว้นวรรค อาจทำให้เช็คยากในบาง Server แต่ถ้าระบบเดิมใช้ได้ก็ใช้ตามนี้ครับ
$current_page = 'Annuity register.php'; 

// ชื่อหน้าบนแถบสีทอง
$page_header = "ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ"; // หรือ "ทะเบียนเงินงวด" ตามแต่ต้องการ

// --------------------------------------------------------------------------------
// --- ส่วน Logic การจัดการข้อมูล (Read & Pagination) ---
// --------------------------------------------------------------------------------

// --- Pagination Logic ---
$limit = 20; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// นับจำนวนทั้งหมด
$sql_count = "SELECT COUNT(*) as total FROM annuity_register";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_rows = $row_count['total'];
$total_pages = ceil($total_rows / $limit);

// ดึงข้อมูลตามหน้า
$sql_data = "SELECT * FROM annuity_register ORDER BY installment_no ASC LIMIT $offset, $limit";
$result_data = $conn->query($sql_data);

// คำนวณยอดรวมทั้งหมด (Grand Total)
$sql_sum = "SELECT SUM(amount) as grand_total FROM annuity_register";
$result_sum = $conn->query($sql_sum);
$row_sum = $result_sum->fetch_assoc();
$grand_total = $row_sum['grand_total'];

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* CSS เพิ่มเติมเฉพาะหน้านี้ */
    .pagination-container { text-align: center; margin-bottom: 20px; font-size: 0.9rem; color: #d63384; font-weight: bold; }
    .pagination-link { color: #d63384; text-decoration: none; margin: 0 2px; }
    .pagination-link:hover { text-decoration: underline; }
    .pagination-active { color: red; font-weight: bold; font-size: 1.1rem; }
    
    .btn-detail { color: #6c757d; font-size: 1.1rem; border: none; background: none; cursor: pointer; transition: 0.2s; } 
    .btn-detail:hover { transform: scale(1.2); }
    
    .total-row td { background-color: #ffdce5 !important; font-weight: bold; color: #333; } 
    .form-yellow-bg { background-color: #fff9c4; padding: 20px; border-radius: 5px; border: 1px solid #eee; }
    .form-label-custom { font-weight: bold; text-align: right; font-size: 0.9rem; }
    .modal-header { background-color: transparent; border-bottom: none; }
    .modal-title-custom { color: #008080; font-weight: bold; width: 100%; text-align: center; font-size: 1.3rem;}
</style>

<div class="container-fluid pb-5 px-3">
    <div class="content-card">
        
        <h2 class="page-title">ทะเบียนโอนการเปลี่ยนแปลงการจัดสรรงบประมาณ ปีงบประมาณ <?php echo $active_year; ?></h2>

        <div class="pagination-container">
            หน้า 
            <?php
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $page) {
                    echo "[<span class='pagination-active'>$i</span>]";
                } else {
                    echo "[<a href='?page=$i' class='pagination-link'>$i</a>]";
                }
            }
            ?>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">ที่/งวด</th>
                        <th style="width: 8%;">วดป</th>
                        <th style="width: 50%;">รายการ</th>
                        <th style="width: 15%;">จำนวนเงิน</th>
                        <th style="width: 5%;">ราย<br>ละเอียด</th>
                        <th style="width: 8%;">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['installment_no'] . "</td>";
                            // ใช้ฟังก์ชัน thai_date_short() จาก includes/db.php
                            echo "<td class='td-center'>" . thai_date_short($row['doc_date']) . "</td>";
                            echo "<td class='td-left'>" . $row['description'] . "</td>";
                            echo "<td class='td-right'>" . number_format($row['amount'], 2) . "</td>";
                            
                            echo "<td class='td-center'>";
                            echo '<button class="btn-detail" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                            echo "</td>";

                            echo "<td class='td-center'>" . $row['balance_status'] . "</td>";
                            echo "</tr>";
                        }

                        echo "<tr class='total-row'>";
                        echo "<td colspan='3' class='td-center'>รวม</td>";
                        echo "<td class='td-right'>" . number_format($grand_total, 2) . "</td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='6' class='text-center py-4 text-muted'>ไม่พบข้อมูล</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header d-block">
                <h5 class="modal-title-custom">รายละเอียด</h5>
            </div>
            <div class="modal-body form-yellow-bg mx-3 mb-3">
                <div class="row mb-2">
                    <div class="col-md-3 form-label-custom">ที่/งวด :</div>
                    <div class="col-md-9" id="view_installment_no"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 form-label-custom">วดป :</div>
                    <div class="col-md-9" id="view_doc_date"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 form-label-custom">รายการ :</div>
                    <div class="col-md-9" id="view_description"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 form-label-custom">จำนวนเงิน :</div>
                    <div class="col-md-9" id="view_amount"></div>
                </div>
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openDetailModal(data) {
        document.getElementById('view_installment_no').innerText = data.installment_no;
        document.getElementById('view_doc_date').innerText = data.doc_date;
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_amount').innerText = parseFloat(data.amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    }
</script>