<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year, Date Functions มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ประเภท(หลัก)ของเงิน - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); // กำหนดหน้าปัจจุบัน
// ชื่อหน้าบนแถบสีทอง
$page_header = 'รายการ ประเภท(หลัก)ของเงิน';

// --- ดึงข้อมูลประเภท(หลัก)ของเงิน ---
$sql_main_money = "SELECT * FROM money_types_main ORDER BY type_code ASC";
$result_main_money = $conn->query($sql_main_money);

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* CSS เพิ่มเติมเฉพาะหน้านี้ */
    .alert-custom {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        border-radius: 4px;
        padding: 10px 15px;
        font-size: 0.9rem;
        text-align: center;
        margin-top: 20px;
    }
</style>

<div class="container pb-5">
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div style="width: 150px;"></div> 
            <h2 class="page-title m-0">ประเภท(หลัก)ของเงิน</h2>
            <div style="width: 150px;"></div> 
        </div>

        <div class="table-responsive mt-2" style="max-width: 800px; margin: 0 auto;">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th style="width: 150px;">รหัส</th>
                        <th>ประเภท(หลัก)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_main_money->num_rows > 0) {
                        while($row = $result_main_money->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td class='td-center'>" . $row['type_code'] . "</td>";
                            echo "<td class='td-left'>" . $row['type_name'] . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2' class='text-center py-4 text-muted'>ไม่มีข้อมูล</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center">
            <div class="alert-custom">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <strong>หมายเหตุ :</strong> เพจนี้ ไม่สามารถปรับแก้ได้ เจตนาแสดงเพื่อทำความเข้าใจในเบื้องต้นถึงประเภทของเงิน
            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>