<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php';

// ตั้งค่า Header
$page_title = "สรุปผลการประเมิน ";
$current_page = basename($_SERVER['PHP_SELF']);
$page_header = 'รายงานสรุปผลการประเมินความพึงพอใจ';

// --- Logic การลบข้อมูลประเมินรายบุคคล ---
if (isset($_GET['delete_id'])) {
    $del_uid = $_GET['delete_id'];
    
    // 1. ลบคะแนนของ ID นี้ออกจากตารางผลประเมิน
    $stmt_del1 = $conn->prepare("DELETE FROM assessment_results WHERE user_id = ?");
    if ($stmt_del1) {
        $stmt_del1->bind_param("i", $del_uid);
        $stmt_del1->execute();
    }
    
    // 2. ลบประวัติล็อกของ ID นี้ออก เพื่อเป็นการปลดล็อกให้ไปกรอกใหม่ได้
    $stmt_del2 = $conn->prepare("DELETE FROM assessment_logs WHERE user_id = ?");
    if ($stmt_del2) {
        $stmt_del2->bind_param("i", $del_uid);
        $stmt_del2->execute();
    }
    
    // รีเฟรชหน้าตัวเองเพื่อเคลียร์ค่า URL
    header("Location: Summary of results.php");
    exit();
}

// -------------------------------------------------------------------------
// ดึงข้อมูลทั้งหมดจาก Database 
// -------------------------------------------------------------------------
$eval_data = [];
$check_tb = $conn->query("SHOW TABLES LIKE 'assessment_results'");
if ($check_tb->num_rows > 0) {
    $res = $conn->query("SELECT * FROM assessment_results");
    while ($row = $res->fetch_assoc()) {
        $eval_data[] = $row;
    }
}

$total_assessed = count($eval_data);

// [แก้ไขใหม่] ตรวจสอบว่ามีคนประเมินหรือยัง ถ้ายังไม่มีให้โชว์หน้าจอที่สวยงามแทน alert()
if ($total_assessed == 0) {
    require_once 'includes/header.php';
    require_once 'includes/navbar.php';
    ?>
    <div class="container-fluid pb-5 px-4 mt-5 text-center">
        <div class="card shadow-sm border-0 mx-auto" style="max-width: 500px; border-radius: 20px;">
            <div class="card-body p-5">
                <i class="fa-solid fa-folder-open text-muted mb-4" style="font-size: 6rem; opacity: 0.3;"></i>
                <h3 class="fw-bold text-dark mb-3">ยังไม่มีข้อมูลการประเมิน</h3>
                <p class="text-muted mb-4 fs-5">ขณะนี้ยังไม่มีผู้ใช้งานท่านใด<br>เข้ามาทำแบบประเมินในระบบ</p>
                <a href="index.php" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold">
                    <i class="fa-solid fa-house me-2"></i> กลับสู่หน้าหลัก
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// --- นับข้อมูลเพศและอายุสำหรับทำตารางสรุปเปอร์เซ็นต์ ---
$gender_counts = ['ชาย' => 0, 'หญิง' => 0];
$age_counts = ['ต่ำกว่า 25 ปี' => 0, '25 - 35 ปี' => 0, '36 - 45 ปี' => 0, '46 - 55 ปี' => 0, '56 ปีขึ้นไป' => 0];

foreach ($eval_data as $data) {
    $g = $data['gender'] ?? '';
    if (isset($gender_counts[$g])) $gender_counts[$g]++;
    
    $a = $data['age_range'] ?? '';
    if (isset($age_counts[$a])) $age_counts[$a]++;
}

// ฟังก์ชันหาค่าร้อยละ
function getPercent($count, $total) {
    if ($total == 0) return 0;
    return ($count / $total) * 100;
}

// ดึงข้อมูลประชากรศาสตร์ของ "คนล่าสุด" มาแสดงในส่วนที่ 1 เพื่อให้ข้อมูลไม่ว่างเปล่า
$latest = end($eval_data);
$info = [
    'ผู้ประเมินล่าสุด' => $latest['user_name'] ?? 'ไม่ระบุ',
    'สิทธิ์การใช้งาน' => $latest['user_role'] ?? 'ไม่ระบุ',
    'เพศ' => $latest['gender'] ?? '-',
    'อายุ' => $latest['age_range'] ?? '-',
    'ตำแหน่ง' => $latest['job_position'] ?? '-',
    'กลุ่มงาน' => $latest['group_name'] ?? '-',
    'ประสบการณ์ใช้งาน' => $latest['exp'] ?? '-'
];

// --- เตรียมคำถามตามโครงสร้างที่ออกแบบไว้ ---
$sections = [
    "ส่วนที่ 2.1 ด้านฟังก์ชันการทำงาน" => [
        "func_0" => "ระบบมีฟังก์ชันการทำงานครอบคลุมตามขั้นตอน",
        "func_1" => "ระบบสามารถคำนวณงบประมาณได้อย่างถูกต้อง",
        "func_2" => "การแสดงผล Dashboard ชัดเจนเข้าใจง่าย",
        "func_3" => "การจัดการสิทธิ์การเข้าถึงข้อมูลเหมาะสม"
    ],
    "ส่วนที่ 2.2 ด้านการใช้งานง่าย" => [
        "use_0" => "การจัดวางเมนูเป็นระเบียบและค้นหาง่าย",
        "use_1" => "ระบบแจ้งเตือน Popup ช่วยให้เข้าใจง่าย",
        "use_2" => "รูปแบบตัวเลขและการเงินอ่านง่ายเป็นมาตรฐาน",
        "use_3" => "ความสะดวกในการใช้งานผ่านอุปกรณ์ต่างๆ"
    ],
    "ส่วนที่ 2.3 ด้านประสิทธิภาพการดำเนินการ" => [
        "perf_0" => "ความเร็วในการโหลดข้อมูลและการประมวลผล",
        "perf_1" => "ความเร็วในการค้นหาข้อมูลโครงการ",
        "perf_2" => "ความถูกต้องของการเปลี่ยนสถานะรายการ"
    ],
    "ส่วนที่ 2.4 ด้านความปลอดภัยและความเชื่อถือ" => [
        "sec_0" => "เสถียรภาพของระบบขณะบันทึกข้อมูล",
        "sec_1" => "ความปลอดภัยในการเข้าสู่ระบบ",
        "sec_2" => "การแจ้งเตือนยืนยันก่อนลบข้อมูล"
    ],
    "ส่วนที่ 3 ผลกระทบต่อการทำงาน" => [
        "impact_0" => "ช่วยลดขั้นตอนและเวลาในการทำงาน",
        "impact_1" => "ลดความผิดพลาดเมื่อเทียบกับการทำมือ",
        "impact_2" => "ช่วยให้การติดตามสถานะมีความโปร่งใส"
    ],
    "ส่วนที่ 4 ความพึงพอใจในภาพรวม" => [
        "overall_0" => "ความพึงพอใจต่อระบบในภาพรวม",
        "overall_1" => "การแนะนำให้หน่วยงานอื่นใช้งาน"
    ]
];

// --- ฟังก์ชันคำนวณทางสถิติ ---
function interpret($mean) {
    if ($mean >= 4.51) return '<span class="badge bg-success px-3 py-2 rounded-pill shadow-sm">มากที่สุด</span>';
    if ($mean >= 3.51) return '<span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm">มาก</span>';
    if ($mean >= 2.51) return '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">ปานกลาง</span>';
    if ($mean >= 1.51) return '<span class="badge bg-orange px-3 py-2 rounded-pill shadow-sm" style="background:#f97316">น้อย</span>';
    return '<span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm">น้อยที่สุด</span>';
}

function calculateSD($scores_array) {
    $count = count($scores_array);
    if ($count <= 1) return 0.00;
    
    $mean = array_sum($scores_array) / $count;
    $variance = 0;
    foreach ($scores_array as $s) {
        $variance += pow($s - $mean, 2);
    }
    return sqrt($variance / ($count - 1)); 
}

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .summary-card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .table-analysis thead { background: #f8fafc; }
    .table-analysis th { color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
    .info-label { color: #64748b; font-weight: 600; font-size: 0.9rem; }
    .info-value { color: #1e293b; font-weight: 700; }
    @media print { .no-print { display: none; } }
</style>

<div class="container-fluid pb-5 px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary m-0"><i class="fa-solid fa-chart-column me-2"></i>วิเคราะห์ผลการประเมิน</h2>
            <p class="text-muted mt-1 mb-0">จำนวนผู้ตอบแบบสอบถามทั้งหมด <strong><?php echo $total_assessed; ?></strong> คน</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline-dark shadow-sm fw-bold px-4 rounded-pill no-print me-2">
                <i class="fa-solid fa-print me-1"></i> พิมพ์รายงาน
            </button>
            <a href="index.php" class="btn btn-primary shadow-sm fw-bold px-4 rounded-pill no-print">
                <i class="fa-solid fa-house me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    <div class="card summary-card mb-4 no-print">
        <div class="card-header bg-white py-3">
            <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-users me-2 text-primary"></i>ข้อมูลทั่วไปของผู้ตอบแบบสอบถามทั้งหมด (ส่วนที่ 1)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-hover table-sm align-middle text-center m-0" style="font-size: 0.9rem;">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="py-2">ID</th>
                            <th class="py-2 text-start">ชื่อ-นามสกุล</th>
                            <th class="py-2">สิทธิ์การใช้งาน</th>
                            <th class="py-2">เพศ</th>
                            <th class="py-2">อายุ</th>
                            <th class="py-2">ตำแหน่ง</th>
                            <th class="py-2">กลุ่มงาน</th>
                            <th class="py-2">ประสบการณ์</th>
                            <th class="py-2">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($eval_data as $data): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($data['user_id'] ?? '-'); ?></td>
                            <td class="text-start fw-bold"><?php echo htmlspecialchars($data['user_name'] ?? '-'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($data['user_role'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($data['gender'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($data['age_range'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($data['job_position'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($data['group_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($data['exp'] ?? '-'); ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger border-0 rounded-circle" 
                                        title="ลบข้อมูลและปลดล็อก ID นี้"
                                        onclick="openDeleteModal('<?php echo htmlspecialchars($data['user_id']); ?>', '<?php echo htmlspecialchars($data['user_name']); ?>')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card summary-card h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3">ตารางที่ 1 ข้อมูลทั่วไปของผู้ตอบแบบสอบถาม (เพศ)</h6>
                    <table class="table table-bordered text-center align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start" style="width: 50%;">เพศของท่าน</th>
                                <th style="width: 25%;">จำนวน</th>
                                <th style="width: 25%;">เปอร์เซ็นต์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-start">ชาย</td>
                                <td><?php echo $gender_counts['ชาย']; ?></td>
                                <td><?php echo number_format(getPercent($gender_counts['ชาย'], $total_assessed), 2); ?>%</td>
                            </tr>
                            <tr>
                                <td class="text-start">หญิง</td>
                                <td><?php echo $gender_counts['หญิง']; ?></td>
                                <td><?php echo number_format(getPercent($gender_counts['หญิง'], $total_assessed), 2); ?>%</td>
                            </tr>
                            <tr class="bg-light fw-bold text-dark">
                                <td class="text-start">รวม</td>
                                <td><?php echo $total_assessed; ?></td>
                                <td>100.00%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card summary-card h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3">ตารางที่ 2 ข้อมูลทั่วไปของผู้ตอบแบบสอบถาม (อายุ)</h6>
                    <table class="table table-bordered text-center align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start" style="width: 50%;">อายุของท่าน</th>
                                <th style="width: 25%;">จำนวน</th>
                                <th style="width: 25%;">เปอร์เซ็นต์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($age_counts as $age => $count): ?>
                            <tr>
                                <td class="text-start"><?php echo $age; ?></td>
                                <td><?php echo $count; ?></td>
                                <td><?php echo number_format(getPercent($count, $total_assessed), 2); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-light fw-bold text-dark">
                                <td class="text-start">รวม</td>
                                <td><?php echo $total_assessed; ?></td>
                                <td>100.00%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php 
    $all_scores = [];
    // เริ่มนับตารางที่ 3 เป็นต้นไป เพราะ 1 และ 2 คือ เพศ และ อายุ
    $table_num = 3; 
    foreach($sections as $sectionTitle => $questions): 
        $section_sum = 0;
        $count = 0;
    ?>
    <div class="card summary-card mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 fw-bold text-secondary">ตารางที่ <?php echo $table_num++; ?> <?php echo $sectionTitle; ?></h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0 table-analysis">
                    <thead class="text-center">
                        <tr>
                            <th style="width: 8%;">ลำดับ</th>
                            <th class="text-start">รายการประเมิน</th>
                            <th style="width: 15%;">ค่าเฉลี่ย ($\bar{X}$)</th>
                            <th style="width: 15%;">S.D.</th>
                            <th style="width: 15%;">ความพึงพอใจ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $idx = 1;
                        foreach($questions as $key => $title): 
                            $current_q_scores = [];
                            foreach($eval_data as $data) {
                                $current_q_scores[] = floatval($data[$key] ?? 0);
                            }
                            
                            $score_mean = array_sum($current_q_scores) / $total_assessed;
                            $score_sd = calculateSD($current_q_scores);

                            $section_sum += $score_mean;
                            $all_scores[] = $score_mean;
                            $count++;
                        ?>
                        <tr class="text-center">
                            <td><?php echo $idx++; ?></td>
                            <td class="text-start fw-bold"><?php echo $title; ?></td>
                            <td class="fs-5 text-primary fw-bold"><?php echo number_format($score_mean, 2); ?></td>
                            <td class="text-muted"><?php echo number_format($score_sd, 2); ?></td>
                            <td><?php echo interpret($score_mean); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        $section_mean = $section_sum / $count;
                        ?>
                        <tr class="bg-light fw-bold text-center">
                            <td colspan="2" class="text-end py-3">เฉลี่ยรวมสรุป :</td>
                            <td class="text-primary fs-5"><?php echo number_format($section_mean, 2); ?></td>
                            <td class="text-muted">-</td>
                            <td><?php echo interpret($section_mean); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php 
    $total_mean = array_sum($all_scores) / count($all_scores);
    ?>
    <div class="card summary-card border-primary border-2 mb-5">
        <div class="card-body p-5 text-center">
            <h3 class="fw-bold text-dark mb-3">สรุปผลความพึงพอใจในภาพรวมทุกด้าน</h3>
            <div class="display-1 fw-bold text-primary mb-3"><?php echo number_format($total_mean, 2); ?></div>
            <div class="fs-4 mb-4"><?php echo interpret($total_mean); ?></div>
            <p class="text-muted">วิเคราะห์โดยระบบคำนวณสถิติอัตโนมัติ  (จากข้อมูลจริงทั้งหมด <?php echo $total_assessed; ?> รายการ)</p>
        </div>
    </div>
    
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> ยืนยันการลบข้อมูล</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size: 5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการลบข้อมูลของ ID: <span id="display_del_id" class="text-danger"></span> หรือไม่?</h4>
                <p class="text-muted fs-5 mb-3 fw-bold" id="display_del_name"></p>
                <div class="alert alert-warning mt-3 mb-0 text-start border-0" style="background-color: #fff3cd; color: #856404; font-size: 0.95rem; border-radius: 10px;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> หากลบแล้ว ID นี้จะสามารถกลับไปทำแบบประเมินใหม่ที่หน้าประเมินความพึงพอใจได้อีกครั้ง
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 16px 16px;">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-5 fw-bold rounded-pill shadow-sm">
                    <i class="fa-solid fa-trash-can me-1"></i> ยืนยันการลบ
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function openDeleteModal(id, name) {
        document.getElementById('display_del_id').innerText = id;
        document.getElementById('display_del_name').innerText = name ? '(' + name + ')' : '';
        document.getElementById('confirmDeleteBtn').href = '?delete_id=' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>