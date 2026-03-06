<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานเงินคงเหลือตามใบงวด - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // 'Check the periodic financial report.php'
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายงานเงินคงเหลือตามใบงวด';

// --- ดึงข้อมูล ---
$sql_data = "SELECT * FROM periodic_financial_reports ORDER BY period_no ASC";
$result_data = $conn->query($sql_data);

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* บังคับตัวหนังสือใน Dropdown ของหน้าปัจจุบันให้มีขีดด้านหน้าสีฟ้า */
    .dropdown-item[href*="Check the periodic financial report.php"] {
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

    /* ปรับแต่ง Modal ให้พื้นหลังสะอาดตา */
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
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title-custom">รายงานเงินคงเหลือตามใบงวด ปีงบประมาณ <?php echo $active_year; ?></h2>
            
            <div class="input-group shadow-sm" style="width: auto; border-radius: 6px; overflow: hidden;">
                <span class="input-group-text bg-white border-end-0 fw-bold" style="color: #0f172a; font-size: 0.9rem;">ปีงบประมาณ</span>
                <select class="form-select form-select-sm border-start-0" style="width: 100px; cursor: pointer; font-size: 0.9rem;">
                    <option value="<?php echo $active_year; ?>"><?php echo $active_year; ?></option>
                </select>
                <button class="btn btn-sm px-3 text-white" style="background-color: #00bcd4; border-color: #00bcd4;">เลือก</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">เลขที่<br>ใบงวด</th>
                        <th style="width: 35%; text-align: left;">รายการ</th>
                        <th style="width: 12%; text-align: right;">จำนวนเงิน</th>
                        <th style="width: 12%; text-align: right;">ฎีกาเบิก</th>
                        <th style="width: 10%; text-align: right;">คืนคลัง</th>
                        <th style="width: 10%; text-align: right;">คงเหลือ</th>
                        <th style="width: 8%;">%จ่าย</th>
                        <th style="width: 8%;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_data && $result_data->num_rows > 0) {
                        while($row = $result_data->fetch_assoc()) {
                            // คำนวณคงเหลือ
                            $remaining = $row['total_amount'] - $row['withdrawn_amount'] + $row['returned_amount'];
                            
                            // คำนวณ % จ่าย
                            $percent_paid = 0;
                            if ($row['total_amount'] > 0) {
                                $percent_paid = ($row['withdrawn_amount'] / $row['total_amount']) * 100;
                            }

                            echo "<tr>";
                            echo "<td class='text-center fw-bold text-secondary'>" . htmlspecialchars($row['period_no']) . "</td>";
                            echo "<td class='text-start'>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td class='text-end'>" . number_format($row['total_amount'], 2) . "</td>";
                            echo "<td class='text-end'>" . number_format($row['withdrawn_amount'], 2) . "</td>";
                            echo "<td class='text-end'>" . number_format($row['returned_amount'], 2) . "</td>";
                            echo "<td class='text-end fw-bold' style='color: #0f172a;'>" . number_format($remaining, 2) . "</td>";
                            echo "<td class='text-center'>" . number_format($percent_paid, 2) . "%</td>";
                            
                            // ปุ่มรายละเอียด
                            echo "<td class='text-center'>";
                            echo '<button class="btn btn-sm btn-light border shadow-sm" style="color: #64748b;" onclick="openDetailModal('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')"><i class="fa-solid fa-list-ul"></i></button>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลในปีงบประมาณ $active_year</td></tr>";
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
                <h5 class="modal-title-custom">รายละเอียดใบงวด</h5>
            </div>
            <div class="modal-body mx-3 mb-3 mt-3">
                <div class="form-white-bg">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">เลขที่ใบงวด :</div>
                        <div class="col-md-9 text-dark" id="view_period_no"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">รายการ :</div>
                        <div class="col-md-9 text-dark" id="view_description"></div>
                    </div>
                    <hr class="text-muted">
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">จำนวนเงิน :</div>
                        <div class="col-md-9 fw-bold text-dark" id="view_total_amount"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">ฎีกาเบิก :</div>
                        <div class="col-md-9 text-danger" id="view_withdrawn_amount"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 form-label-custom">คืนคลัง :</div>
                        <div class="col-md-9 text-success" id="view_returned_amount"></div>
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
        document.getElementById('view_period_no').innerText = data.period_no;
        document.getElementById('view_description').innerText = data.description;
        document.getElementById('view_total_amount').innerText = parseFloat(data.total_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_withdrawn_amount').innerText = parseFloat(data.withdrawn_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        document.getElementById('view_returned_amount').innerText = parseFloat(data.returned_amount).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
        
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    }

    // Script สำหรับทำไฮไลต์เมนู "ตรวจสอบ" ใน Navbar ตามรูปภาพ
    document.addEventListener("DOMContentLoaded", function() {
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