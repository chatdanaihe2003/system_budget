<?php
// [1. เรียกใช้ DB และตั้งค่าพื้นฐาน]
require_once 'includes/db.php'; 

// ตั้งค่า Header
$page_title = "แบบประเมินความพึงพอใจ ";
$current_page = basename($_SERVER['PHP_SELF']); 
$page_header = 'แบบประเมินความพึงพอใจการใช้งานระบบ';

// ดึงข้อมูลจาก Session มาแสดงผล
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_fullname = isset($_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Guest');
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '-';

// --- สร้างตารางถ้ายังไม่มี ---
$conn->query("CREATE TABLE IF NOT EXISTS assessment_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, user_name VARCHAR(100), user_role VARCHAR(50),
    gender VARCHAR(20), age_range VARCHAR(50), job_position VARCHAR(100), group_name VARCHAR(100), exp VARCHAR(50),
    func_0 INT, func_1 INT, func_2 INT, func_3 INT,
    use_0 INT, use_1 INT, use_2 INT, use_3 INT,
    perf_0 INT, perf_1 INT, perf_2 INT,
    sec_0 INT, sec_1 INT, sec_2 INT,
    impact_0 INT, impact_1 INT, impact_2 INT,
    overall_0 INT, overall_1 INT,
    fav TEXT, prob TEXT, req TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assessment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    user_id INT, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// [โค้ดแก้ไขบัคฐานข้อมูลอัตโนมัติ] ป้องกัน Error กรณีตารางเก่าไม่มีคอลัมน์ใหม่
$expected_cols = [
    'user_name' => "VARCHAR(100)", 'user_role' => "VARCHAR(50)",
    'gender' => "VARCHAR(20)", 'age_range' => "VARCHAR(50)", 
    'job_position' => "VARCHAR(100)", 'group_name' => "VARCHAR(100)", 'exp' => "VARCHAR(50)",
    'func_0' => "INT", 'func_1' => "INT", 'func_2' => "INT", 'func_3' => "INT",
    'use_0' => "INT", 'use_1' => "INT", 'use_2' => "INT", 'use_3' => "INT",
    'perf_0' => "INT", 'perf_1' => "INT", 'perf_2' => "INT",
    'sec_0' => "INT", 'sec_1' => "INT", 'sec_2' => "INT",
    'impact_0' => "INT", 'impact_1' => "INT", 'impact_2' => "INT",
    'overall_0' => "INT", 'overall_1' => "INT",
    'fav' => "TEXT", 'prob' => "TEXT", 'req' => "TEXT"
];
foreach ($expected_cols as $col => $def) {
    $check_col = $conn->query("SHOW COLUMNS FROM assessment_results LIKE '$col'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE assessment_results ADD $col $def");
    }
}

// [โค้ดซ่อมแซมบัคอัตโนมัติ] หากมีประวัติว่า ID นี้ประเมินแล้ว แต่ไม่มีคะแนนในระบบ ให้ลบประวัติออกเพื่อปลดล็อก
$conn->query("DELETE FROM assessment_logs WHERE user_id NOT IN (SELECT user_id FROM assessment_results)");

// --- Logic การบันทึกข้อมูลลงฐานข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // เช็คว่า ID นี้เคยประเมินหรือยัง เพื่อป้องกันการกดส่งซ้ำ (เช็คตอน Submit ทีเดียว)
    $chk = $conn->query("SELECT id FROM assessment_logs WHERE user_id = '$user_id'");
    if ($chk->num_rows == 0 && $user_id != 0) {
        
        // บันทึกคะแนนลง DB เพื่อให้หน้า Summary นำไปคำนวณ
        $stmt = $conn->prepare("INSERT INTO assessment_results (
            user_id, user_name, user_role, gender, age_range, job_position, group_name, exp, 
            func_0, func_1, func_2, func_3, use_0, use_1, use_2, use_3, perf_0, perf_1, perf_2, 
            sec_0, sec_1, sec_2, impact_0, impact_1, impact_2, overall_0, overall_1, fav, prob, req
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // ตรวจสอบว่าคำสั่ง Prepare ทำงานสำเร็จ (ผ่านการอัปเดตตารางแล้ว) เพื่อป้องกัน Error หน้าขาว
        if ($stmt) {
            $fav = $_POST['fav'] ?? ''; $prob = $_POST['prob'] ?? ''; $req = $_POST['req'] ?? '';
            
            // ใช้ 30 Parameter
            $stmt->bind_param("isssssssiiiiiiiiiiiiiiiiiiisss", 
                $user_id, $user_fullname, $user_role, 
                $_POST['gender'], $_POST['age_range'], $_POST['job_position'], $_POST['group_name'], $_POST['exp'],
                $_POST['func_0'], $_POST['func_1'], $_POST['func_2'], $_POST['func_3'],
                $_POST['use_0'], $_POST['use_1'], $_POST['use_2'], $_POST['use_3'],
                $_POST['perf_0'], $_POST['perf_1'], $_POST['perf_2'],
                $_POST['sec_0'], $_POST['sec_1'], $_POST['sec_2'],
                $_POST['impact_0'], $_POST['impact_1'], $_POST['impact_2'],
                $_POST['overall_0'], $_POST['overall_1'],
                $fav, $prob, $req
            );
            $stmt->execute();
            
            // บันทึกล็อกว่า ID นี้ทำการประเมินไปแล้ว
            $conn->query("INSERT INTO assessment_logs (user_id) VALUES ('$user_id')");
        } else {
            die("<div style='text-align:center; margin-top:50px;'><h3>เกิดข้อผิดพลาดฐานข้อมูล: " . $conn->error . "</h3><a href='point.php'>กลับไปหน้าประเมิน</a></div>");
        }
    }
    
    // เด้งกลับมาที่หน้าเดิม (point.php) เพื่อให้แสดงการ์ดขอบคุณ
    header("Location: point.php");
    exit();
}

// --- ตรวจสอบว่า User นี้เคยทำแบบประเมินไปแล้วหรือไม่ ---
$already_submitted = false;
$chk_status = $conn->query("SELECT id FROM assessment_logs WHERE user_id = '$user_id'");
if ($chk_status && $chk_status->num_rows > 0) {
    $already_submitted = true;
}

// รายชื่อกลุ่มงานในระบบ (อ้างอิงจากหน้า Dashboard)
$all_groups = [
    "กลุ่มอำนวยการ", "กลุ่มนโยบายและแผน", "กลุ่มส่งเสริมการจัดการศึกษา", "กลุ่มบริหารงานบุคคล",
    "กลุ่มบริหารการเงินและสินทรัพย์", "หน่วยตรวจสอบภายใน", "กลุ่มนิเทศ ติดตาม และประเมินผลการจัดการศึกษา",
    "กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร", "กลุ่มพัฒนาครูและบุคลากรทางการศึกษา", "กลุ่มกฎหมายและคดี"
];

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .assessment-section {
        background: #f8f9fa;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        border-left: 6px solid #0ea5e9;
        box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    }
    .section-title {
        color: #0369a1;
        font-weight: 800;
        margin-bottom: 20px;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 10px;
    }
    .question-row {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease-in-out;
    }
    .question-row:hover {
        border-color: #0ea5e9;
        box-shadow: 0 5px 15px rgba(14, 165, 233, 0.1);
        transform: translateY(-2px);
    }
    .score-option {
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        transition: 0.2s;
    }
    .score-option:hover { background: #e0f2fe; }
    .form-check-input:checked { background-color: #0ea5e9; border-color: #0ea5e9; }
    .user-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 10px 15px;
        border-radius: 10px;
        border: 1px solid #bae6fd;
        display: inline-block;
    }
    
    /* สไตล์สำหรับการ์ดขอบคุณ (เมื่อเคยประเมินแล้ว) */
    .thank-you-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 60px 40px;
        text-align: center;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
        border-top: 10px solid #10b981;
        max-width: 750px;
        margin: 60px auto;
        position: relative;
        overflow: hidden;
    }
    .thank-you-card::before {
        content: '\f004';
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        font-size: 18rem;
        color: #10b981;
        opacity: 0.03;
        top: -40px;
        right: -40px;
        transform: rotate(-15deg);
    }
    .thank-you-icon {
        font-size: 5.5rem;
        color: #10b981;
        margin-bottom: 25px;
        animation: scaleIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    @keyframes scaleIn {
        from { transform: scale(0); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .thank-you-title {
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 15px;
        font-size: 2.2rem;
    }
    .thank-you-name {
        color: #0ea5e9;
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 25px;
    }
    .thank-you-message {
        font-size: 1.15rem;
        color: #475569;
        line-height: 1.8;
        margin-bottom: 35px;
        background: #f8fafc;
        padding: 25px;
        border-radius: 16px;
        border: 2px dashed #cbd5e1;
    }
</style>

<div class="container-fluid pb-5 px-4 mt-4">
    <?php if ($already_submitted): ?>
        <div class="thank-you-card">
            <div class="thank-you-icon">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
            <h2 class="thank-you-title">ขอขอบพระคุณเป็นอย่างยิ่ง</h2>
            <div class="thank-you-name">คุณ <?php echo htmlspecialchars($user_fullname); ?></div>
            
            <div class="thank-you-message">
                ที่สละเวลาอันมีค่าในการช่วยประเมินความพึงพอใจการใช้งาน <strong>"ระบบแผนงานและงบประมาณ "</strong><br>
                <div class="mt-4 text-start d-inline-block">
                    <i class="fa-solid fa-quote-left text-muted opacity-50 me-2 fs-5"></i> 
                    <span class="text-dark fw-bold">ทางผู้จัดทำขออนุญาตนำข้อมูลและข้อเสนอแนะที่ท่านกรอกมา ไปใช้ประกอบการทำวิจัยและพัฒนาระบบให้มีประสิทธิภาพยิ่งขึ้นต่อไป ขอบพระคุณมากครับผม</span>
                    <i class="fa-solid fa-quote-right text-muted opacity-50 ms-2 fs-5"></i>
                </div>
            </div>
            
            <a href="index.php" class="btn btn-primary btn-lg px-5 shadow-sm fw-bold mt-2 rounded-pill">
                <i class="fa-solid fa-house me-2"></i> กลับสู่หน้าหลัก
            </a>
        </div>

    <?php else: ?>
        <form action="point.php" method="POST" id="assessmentForm">
            <div class="content-card" style="background:#fff; border-radius:24px; padding:40px; box-shadow: 0 10px 40px rgba(0,0,0,0.06);">
                
                <div class="text-center mb-5">
                    <h1 class="fw-bold text-primary mb-3"><i class="fa-solid fa-file-pen me-3"></i>แบบประเมินความพึงพอใจ</h1>
                    <p class="text-muted fs-5">โปรดให้คะแนนตามความเป็นจริง เพื่อนำข้อมูลไปประกอบการวิจัยและพัฒนาต่อไป</p>
                    <hr style="width: 120px; margin: auto; border: 3px solid #0ea5e9; border-radius: 10px; opacity: 1;">
                </div>

                <div class="assessment-section">
                    <h5 class="section-title"><i class="fa-solid fa-user-tag me-2"></i> ส่วนที่ 1: ข้อมูลทั่วไปของผู้ตอบแบบสอบถาม</h5>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ชื่อผู้ใช้งานที่ประเมิน</label>
                            <div class="user-badge w-100 fs-6">
                                <i class="fa-solid fa-circle-user me-2"></i><?php echo htmlspecialchars($user_fullname); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role)</label>
                            <div class="user-badge w-100 fs-6" style="background: #fef2f2; color: #b91c1c; border-color: #fecaca;">
                                <i class="fa-solid fa-shield-halved me-2"></i><?php echo htmlspecialchars($user_role); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">เพศ <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderMale" value="ชาย" required>
                                    <label class="form-check-label fs-6" for="genderMale">ชาย</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderFemale" value="หญิง">
                                    <label class="form-check-label fs-6" for="genderFemale">หญิง</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">อายุ <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" name="age_range" required style="border-radius: 10px;">
                                <option value="" disabled selected>-- เลือกช่วงอายุ --</option>
                                <option>ต่ำกว่า 25 ปี</option>
                                <option>25 - 35 ปี</option>
                                <option>36 - 45 ปี</option>
                                <option>46 - 55 ปี</option>
                                <option>56 ปีขึ้นไป</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">ตำแหน่ง <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg" name="job_position" placeholder="กรอกตำแหน่งของท่าน" required style="border-radius: 10px;">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">กลุ่มงานที่สังกัด <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg border-primary" name="group_name" required style="border-radius: 10px; background-color: #f0f9ff;">
                                <option value="" disabled selected>-- เลือกกลุ่มงาน --</option>
                                <?php foreach($all_groups as $group): ?>
                                    <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">ประสบการณ์การใช้ระบบสารสนเทศ</label>
                            <div class="d-flex gap-4 flex-wrap mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exp" id="exp1" value="น้อยกว่า 1 ปี" required>
                                    <label class="form-check-label fs-6" for="exp1">น้อยกว่า 1 ปี</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exp" id="exp2" value="1 - 3 ปี">
                                    <label class="form-check-label fs-6" for="exp2">1 - 3 ปี</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exp" id="exp3" value="มากกว่า 3 ปี">
                                    <label class="form-check-label fs-6" for="exp3">มากกว่า 3 ปี</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="assessment-section">
                    <h5 class="section-title"><i class="fa-solid fa-chart-line me-2"></i> ส่วนที่ 2: การประเมินประสิทธิภาพของระบบ (5 ระดับคะแนน)</h5>
                    <p class="text-muted mb-4"><i class="fa-solid fa-circle-info me-2"></i> 5 = มากที่สุด, 4 = มาก, 3 = ปานกลาง, 2 = น้อย, 1 = น้อยที่สุด</p>
                    
                    <h6 class="fw-bold text-primary mb-3 py-2 px-3 bg-white rounded shadow-sm border-start border-primary border-4">1. ด้านฟังก์ชันการทำงาน (Functional Suitability)</h6>
                    <?php 
                    renderQuestions([
                        "ระบบมีฟังก์ชันการทำงานครอบคลุมตามขั้นตอนการตัดยอดงบประมาณ (เช่น การขอเบิก, การอนุมัติ)",
                        "ระบบสามารถคำนวณงบประมาณคงเหลือได้อย่างถูกต้องแม่นยำ",
                        "การแสดงผลในหน้า Dashboard ช่วยให้เห็นภาพรวมของงบประมาณได้อย่างชัดเจน",
                        "ระบบมีการจัดการสิทธิ์การเข้าถึงข้อมูลตามบทบาทหน้าที่ได้อย่างเหมาะสม"
                    ], "func");
                    ?>

                    <h6 class="fw-bold text-primary mb-3 mt-4 py-2 px-3 bg-white rounded shadow-sm border-start border-primary border-4">2. ด้านการใช้งานง่าย (Usability)</h6>
                    <?php 
                    renderQuestions([
                        "การจัดวางเมนูและโครงสร้างหน้าเว็บ (Navbar) มีความเป็นระเบียบและค้นหาง่าย",
                        "ข้อความแจ้งเตือน (Popup Modal) สีเขียว/แดง ช่วยให้เข้าใจผลการทำรายการได้ทันที",
                        "รูปแบบตัวเลขและการแสดงผลทางการเงิน (Comma, Decimal) อ่านง่ายและเป็นมาตรฐาน",
                        "ระบบมีความสะดวกในการใช้งานผ่านอุปกรณ์ต่างๆ (Responsive Design)"
                    ], "use");
                    ?>

                    <h6 class="fw-bold text-primary mb-3 mt-4 py-2 px-3 bg-white rounded shadow-sm border-start border-primary border-4">3. ด้านประสิทธิภาพในการดำเนินการ (Performance Efficiency)</h6>
                    <?php 
                    renderQuestions([
                        "ความเร็วในการโหลดข้อมูลและการประมวลผลข้อมูลขนาดใหญ่",
                        "ความเร็วในการค้นหาข้อมูลโครงการผ่านรหัสหรือชื่อโครงการ",
                        "ความถูกต้องของการเปลี่ยนสถานะรายการเมื่อมีการบันทึกข้อมูล"
                    ], "perf");
                    ?>

                    <h6 class="fw-bold text-primary mb-3 mt-4 py-2 px-3 bg-white rounded shadow-sm border-start border-primary border-4">4. ด้านความปลอดภัยและความน่าเชื่อถือ (Security & Reliability)</h6>
                    <?php 
                    renderQuestions([
                        "ระบบมีเสถียรภาพ ไม่เกิดข้อผิดพลาด (Error) ขณะบันทึกข้อมูลสำคัญ",
                        "ระบบการเข้าสู่ระบบ (Login) มีความปลอดภัยและมีความเป็นส่วนตัวของข้อมูล",
                        "การแจ้งเตือนยืนยันก่อนลบข้อมูล (Delete Confirmation) ช่วยป้องกันความผิดพลาดได้ดี"
                    ], "sec");
                    ?>
                </div>

                <div class="assessment-section">
                    <h5 class="section-title"><i class="fa-solid fa-bolt me-2"></i> ส่วนที่ 3: ด้านผลกระทบต่อการทำงาน (Impact on Productivity)</h5>
                    <?php 
                    renderQuestions([
                        "ระบบช่วยลดขั้นตอนและเวลาในการเดินเอกสารตัดยอดงบประมาณ",
                        "ระบบช่วยลดความผิดพลาดในการคำนวณงบประมาณเมื่อเทียบกับการทำมือ (Manual)",
                        "ระบบช่วยให้การติดตามสถานะการขอเบิก/ขอยืมเงินมีความโปร่งใส ตรวจสอบได้"
                    ], "impact");
                    ?>
                </div>

                <div class="assessment-section">
                    <h5 class="section-title"><i class="fa-solid fa-face-smile me-2"></i> ส่วนที่ 4: ความพึงพอใจในภาพรวม</h5>
                    <?php 
                    renderQuestions([
                        "ท่านมีความพึงพอใจต่อการใช้งานระบบแผนงานและงบประมาณ ในภาพรวมเพียงใด",
                        "ท่านจะแนะนำให้หน่วยงานอื่นใช้งานระบบแผนงานและงบประมาณ นี้หรือไม่"
                    ], "overall");
                    ?>
                </div>

                <div class="assessment-section">
                    <h5 class="section-title"><i class="fa-solid fa-comment-medical me-2"></i> ส่วนที่ 5: ข้อเสนอแนะอื่นๆ เพื่อการพัฒนา</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold">จุดที่ท่านประทับใจที่สุดในระบบ</label>
                        <textarea class="form-control form-control-lg" name="fav" rows="2" placeholder="ระบุความประทับใจ..." style="border-radius: 12px;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ปัญหาหรืออุปสรรคที่พบในการใช้งาน</label>
                        <textarea class="form-control form-control-lg" name="prob" rows="2" placeholder="ระบุปัญหาที่พบ..." style="border-radius: 12px;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ฟังก์ชันที่ต้องการให้เพิ่มในอนาคต</label>
                        <textarea class="form-control form-control-lg" name="req" rows="2" placeholder="ระบุข้อเสนอแนะเพิ่มเติม..." style="border-radius: 12px;"></textarea>
                    </div>
                </div>

                <div class="text-center pt-4">
                    <button type="button" class="btn btn-primary btn-lg px-5 py-3 shadow fw-bold rounded-pill" onclick="openConfirmModal()" style="font-size: 1.1rem; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-paper-plane me-2"></i> ส่งแบบประเมินความพึงพอใจ
                    </button>
                </div>

            </div>
        </form>
    <?php endif; ?>
</div>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-question me-2"></i> ยืนยันการส่งแบบประเมิน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-file-circle-check text-primary mb-3" style="font-size: 5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">กรุณาตรวจสอบข้อมูลก่อนกดส่ง</h4>
                <div class="alert alert-info mt-4 mb-0 text-start border-0" style="background-color: #e0f2fe; color: #0369a1; font-size: 0.95rem; border-radius: 10px;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>คำเตือน:</strong> 1 บัญชีผู้ใช้งานสามารถทำแบบประเมินได้เพียง 1 ครั้งเท่านั้น หากกดยืนยันแล้ว ข้อมูลจะถูกบันทึกเข้าระบบทันทีและไม่สามารถกลับมาแก้ไขได้
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 16px 16px;">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">กลับไปตรวจสอบ</button>
                <button type="button" class="btn btn-primary px-5 fw-bold rounded-pill shadow-sm" onclick="document.getElementById('assessmentForm').submit();">
                    <i class="fa-solid fa-paper-plane me-1"></i> ยืนยันการส่งข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
// ฟังก์ชันช่วยสร้างแถวคำถามคะแนน 1-5 ให้ดูคลีนขึ้น
function renderQuestions($questions, $prefix) {
    foreach ($questions as $key => $q) {
        $id = $prefix . "_" . $key;
        echo '<div class="question-row d-flex justify-content-between align-items-center flex-wrap gap-3">';
        echo '  <div style="flex: 1; min-width: 300px; font-size: 1.05rem;" class="text-dark">' . ($key+1) . '. ' . $q . '</div>';
        echo '  <div class="d-flex gap-2 align-items-center bg-light px-3 py-2 rounded-pill border">';
        for ($score = 5; $score >= 1; $score--) {
            echo '    <div class="form-check form-check-inline m-0 score-option">';
            echo '      <input class="form-check-input" type="radio" name="'.$id.'" id="'.$id.'_'.$score.'" value="'.$score.'" required>';
            echo '      <label class="form-check-label fw-bold text-secondary" for="'.$id.'_'.$score.'">'.$score.'</label>';
            echo '    </div>';
        }
        echo '  </div>';
        echo '</div>';
    }
}
require_once 'includes/footer.php'; 
?>

<script>
    // เปิด Popup ตรวจสอบ ถ้าฟอร์มกรอกครบถ้วน
    function openConfirmModal() {
        const form = document.getElementById('assessmentForm');
        
        if (form.checkValidity()) {
            new bootstrap.Modal(document.getElementById('confirmSubmitModal')).show();
        } else {
            // ฟ้องเตือนให้กรอกให้ครบ
            form.reportValidity();
        }
    }
</script>