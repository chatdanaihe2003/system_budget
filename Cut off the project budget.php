<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "ตัดยอดงบประมาณโครงการ - AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'ตัดยอดงบประมาณโครงการ <span style="float:right; font-size:0.9rem; font-weight:normal;">ปีงบประมาณที่ทำงาน: <strong>'.$active_year.'</strong></span>';

// --- เช็คสิทธิ์การเข้าถึง (ซ่อนจาก ID User ทั่วไป) ---
$nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='index.php';</script>";
    exit();
}

// --- ดึงข้อมูลจากตาราง project_outcomes (ค้นหาและกรองตามปีงบประมาณ) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {
    $search_param = "%" . $search . "%";
    // ค้นหาจากรหัสโครงการ หรือ ชื่อโครงการ
    $sql = "SELECT * FROM project_outcomes WHERE (project_code LIKE ? OR project_name LIKE ?) AND budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $search_param, $search_param, $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
} else {
    $sql = "SELECT * FROM project_outcomes WHERE budget_year = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $active_year);
    $stmt->execute();
    $result_projects = $stmt->get_result();
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .total-row { background-color: #f8f9fa !important; font-weight: bold; color: #333; }
    .status-badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; }
    .amount-display { font-size: 1.1rem; font-weight: bold; letter-spacing: 0.5px; }
</style>

<div class="container-fluid pb-5 px-4">
    <div class="content-card mt-4" style="background:#fff; border-radius:15px; padding:25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title m-0 fw-bold text-primary">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i> ตัดยอดงบประมาณโครงการ
            </h2>
            
            <form action="" method="GET" class="d-flex">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 pl-0" placeholder="ค้นหารหัส หรือ ชื่อโครงการ..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary px-4" type="submit">ค้นหา</button>
                </div>
                <?php if($search != ""): ?>
                    <a href="Cut off the project budget.php" class="btn btn-outline-danger ms-2 d-flex align-items-center" title="ล้างการค้นหา"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center py-3" style="width: 5%;">ที่</th>
                        <th class="py-3" style="width: 10%;">รหัสโครงการ</th>
                        <th class="py-3" style="width: 20%;">ชื่อผลผลิต/โครงการ</th>
                        <th class="text-end py-3" style="width: 10%;">งบประมาณรวม</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 1</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 2</th>
                        <th class="text-end py-3" style="width: 9%;">ยอดจัดสรร<br>ครั้งที่ 3</th>
                        <th class="text-end py-3" style="width: 9%;">รวมจัดสรรแล้ว</th>
                        <th class="text-end py-3" style="width: 9%;">คงเหลือตัดได้</th>
                        <th class="text-center py-3" style="width: 10%;">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result_projects->num_rows > 0) {
                        $i = 1;
                        $sum_budget = 0;
                        $sum_alloc1 = 0;
                        $sum_alloc2 = 0;
                        $sum_alloc3 = 0;
                        $sum_allocated = 0;
                        $sum_remaining = 0;

                        while($row = $result_projects->fetch_assoc()) {
                            $total_budget = $row['budget_amount'];
                            $alloc1 = $row['allocation_1'];
                            $alloc2 = $row['allocation_2'];
                            $alloc3 = $row['allocation_3'];
                            // คำนวณยอดที่จัดสรรไปแล้ว
                            $total_allocated = $alloc1 + $alloc2 + $alloc3;
                            // คำนวณยอดคงเหลือ
                            $remaining_balance = $total_budget - $total_allocated;

                            // เก็บผลรวมด้านล่างตาราง
                            $sum_budget += $total_budget;
                            $sum_alloc1 += $alloc1;
                            $sum_alloc2 += $alloc2;
                            $sum_alloc3 += $alloc3;
                            $sum_allocated += $total_allocated;
                            $sum_remaining += $remaining_balance;

                            // กำหนดสีสถานะเงิน
                            $text_color = "text-success";
                            if ($remaining_balance <= 0) {
                                $text_color = "text-danger"; // งบหมด
                            } elseif ($remaining_balance < ($total_budget * 0.2)) {
                                $text_color = "text-warning"; // งบเหลือน้อยกว่า 20%
                            }

                            echo "<tr>";
                            echo "<td class='text-center text-muted'>" . $i++ . "</td>";
                            echo "<td class='fw-bold text-secondary'>" . htmlspecialchars($row['project_code'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                            echo "<td class='text-end fw-bold text-success'>" . ($total_budget > 0 ? number_format($total_budget, 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($alloc1 > 0 ? number_format($alloc1, 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($alloc2 > 0 ? number_format($alloc2, 2) : '-') . "</td>";
                            echo "<td class='text-end text-muted'>" . ($alloc3 > 0 ? number_format($alloc3, 2) : '-') . "</td>";
                            echo "<td class='text-end fw-bold'>" . ($total_allocated > 0 ? number_format($total_allocated, 2) : '-') . "</td>";
                            echo "<td class='text-end fw-bold {$text_color} amount-display'>" . number_format($remaining_balance, 2) . "</td>";
                            
                            echo "<td class='text-center'>";
                            if ($remaining_balance > 0) {
                                // ส่งข้อมูลโครงการไปที่ Javascript เพื่อเปิด Modal
                                $js_data = htmlspecialchars(json_encode([
                                    'id' => $row['id'],
                                    'code' => $row['project_code'],
                                    'name' => $row['project_name'],
                                    'remaining' => $remaining_balance
                                ]), ENT_QUOTES, 'UTF-8');
                                
                                echo "<button class='btn btn-sm btn-primary shadow-sm px-3' onclick='openCutoffModal({$js_data})'>";
                                echo "<i class='fa-solid fa-scissors me-1'></i> ตัดยอด</button>";
                            } else {
                                echo "<span class='badge bg-danger status-badge'>งบประมาณหมด</span>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }

                        // แถวสรุปยอดรวม
                        echo "<tr class='total-row table-light'>";
                        echo "<td colspan='3' class='text-end py-3'><strong>รวมทั้งสิ้น :</strong></td>";
                        echo "<td class='text-end py-3 text-success'><strong>" . number_format($sum_budget, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc1, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc2, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-muted'><strong>" . number_format($sum_alloc3, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-secondary'><strong>" . number_format($sum_allocated, 2) . "</strong></td>";
                        echo "<td class='text-end py-3 text-primary fs-5'><strong>" . number_format($sum_remaining, 2) . "</strong></td>";
                        echo "<td></td>";
                        echo "</tr>";

                    } else {
                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'>";
                        echo "<i class='fa-regular fa-folder-open fs-1 mb-3 d-block'></i>ไม่พบข้อมูลโครงการในปีงบประมาณ $active_year</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="cutoffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <form action="" method="POST" onsubmit="return confirmCutoff(this);">
                <input type="hidden" name="project_id" id="modal_project_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> ทำรายการตัดยอดงบประมาณ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="row">
                            <div class="col-md-3 text-muted fw-bold">รหัสโครงการ:</div>
                            <div class="col-md-9 fw-bold text-dark" id="display_code"></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 text-muted fw-bold">ชื่อโครงการ:</div>
                            <div class="col-md-9 text-dark" id="display_name"></div>
                        </div>
                        <hr>
                        <div class="row align-items-center">
                            <div class="col-md-8 text-end fw-bold text-muted">งบประมาณคงเหลือที่ตัดได้:</div>
                            <div class="col-md-4 text-end fs-4 fw-bold text-success" id="display_remaining">0.00</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">วันที่ทำรายการ <span class="text-danger">*</span></label>
                            <input type="date" name="cutoff_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">เลขที่เอกสาร/ฎีกาอ้างอิง</label>
                            <input type="text" name="ref_document" class="form-control" placeholder="เช่น กค.1234/2569">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">รายการ/รายละเอียดการเบิกจ่าย <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="ระบุรายละเอียดการใช้จ่าย" required></textarea>
                    </div>

                    <div class="row mb-3 align-items-center bg-light p-3 rounded">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary fs-5">จำนวนเงินที่ขอเบิก (บาท)</label>
                            <input type="number" step="0.01" name="request_amount" id="request_amount" class="form-control text-end text-primary fw-bold" placeholder="0.00" onkeyup="copyAmountToApprove()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-danger fs-5">จำนวนเงินที่อนุมัติ (บาท) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="cutoff_amount" id="cutoff_amount" class="form-control form-control-lg text-end text-danger fw-bold" placeholder="0.00" required>
                            <input type="hidden" id="max_remaining" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-check-circle me-1"></i> ยืนยันการตัดยอด</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // ฟังก์ชันเปิด Modal และส่งข้อมูลโครงการเข้าไปแสดง
    function openCutoffModal(data) {
        // จัดการ Input ซ่อน
        document.getElementById('modal_project_id').value = data.id;
        document.getElementById('max_remaining').value = data.remaining;
        
        // แสดง Text
        document.getElementById('display_code').innerText = data.code ? data.code : 'ไม่มีรหัส';
        document.getElementById('display_name').innerText = data.name;
        
        // จัดรูปแบบตัวเลขให้สวยงาม
        let formattedRemaining = parseFloat(data.remaining).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('display_remaining').innerText = formattedRemaining + " ฿";
        
        // ล้างช่องกรอกจำนวนเงิน
        document.getElementById('request_amount').value = '';
        document.getElementById('cutoff_amount').value = '';
        
        // เปิด Modal
        new bootstrap.Modal(document.getElementById('cutoffModal')).show();
    }

    // ฟังก์ชันอำนวยความสะดวก: พิมพ์ช่องขอเบิก แล้วตัวเลขจะเด้งไปช่องอนุมัติอัตโนมัติ
    function copyAmountToApprove() {
        let reqAmount = document.getElementById('request_amount').value;
        document.getElementById('cutoff_amount').value = reqAmount;
    }

    // ฟังก์ชันดักจับก่อนกด Submit ว่าเงินเกินหรือไม่
    function confirmCutoff(form) {
        let maxLimit = parseFloat(document.getElementById('max_remaining').value);
        let inputAmount = parseFloat(document.getElementById('cutoff_amount').value);

        if (inputAmount <= 0) {
            alert('กรุณาระบุจำนวนเงินอนุมัติให้มากกว่า 0 บาท');
            return false;
        }

        if (inputAmount > maxLimit) {
            alert('ไม่สามารถตัดยอดได้!\nจำนวนเงินอนุมัติ (' + inputAmount.toLocaleString() + ' บาท)\nเกินกว่ายอดคงเหลือ (' + maxLimit.toLocaleString() + ' บาท)');
            return false;
        }

        // ถ้ายอดผ่านเงื่อนไข ให้กดตกลง (สามารถปิด Comment บรรทัด return true ลงไปบันทึก DB ได้)
        alert('ระบบจำลอง: การตรวจสอบผ่าน\n(ต้องมีการสร้างตาราง Expenses เพื่อบันทึกข้อมูลจริง)');
        return false; // ตอนนี้ใส่ false ไว้ไม่ให้หน้าโหลดใหม่ จนกว่าจะมีตารางมารองรับ
    }
</script>