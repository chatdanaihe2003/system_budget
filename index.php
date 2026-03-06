<?php
// [1. เรียกใช้ DB] (รวม Session, Check Login, Active Year มาให้แล้ว)
require_once 'includes/db.php'; 

// ตั้งค่าตัวแปรสำหรับ Header
$page_title = "หน้าหลัก - ระบบการเงินและบัญชี AMSS++";
$current_page = basename($_SERVER['PHP_SELF']); 
// ชื่อหน้าบนแถบ Sub-header
$page_header = 'แผงควบคุมระบบการเงิน';

// [2. & 3. เรียกใช้ Header และ Navbar]
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    /* ปรับแต่ง Hero Section ให้ดูพรีเมียมขึ้น (สไตล์ Glassmorphism) */
    .hero-container {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        /* พื้นหลังไล่เฉดสีอ่อนๆ ให้ดูสะอาดตา */
        background: radial-gradient(circle at top right, #f0f9ff 0%, #e0f2fe 100%);
        min-height: calc(100vh - 250px);
    }

    .hero-card {
        /* ใช้สีขาวโปร่งแสงแบบกระจก */
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        width: 100%;
        max-width: 900px;
        border-radius: 40px;
        /* เงาแบบฟุ้งกระจาย (Soft Depth Shadow) */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08), 
                    inset 0 0 0 1px rgba(255, 255, 255, 0.5);
        padding: 80px 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 60px;
        position: relative;
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .hero-card:hover {
        transform: translateY(-10px);
        background: rgba(255, 255, 255, 0.85);
        box-shadow: 0 40px 80px -20px rgba(30, 58, 138, 0.15);
    }

    /* ไอคอนประดับพื้นหลังจางๆ */
    .hero-bg-icon {
        position: absolute;
        color: var(--secondary-color);
        opacity: 0.03;
        z-index: 0;
        pointer-events: none;
    }
    .icon-bg-1 { font-size: 18rem; right: -40px; top: -20px; transform: rotate(-10deg); }
    .icon-bg-2 { font-size: 10rem; left: -20px; bottom: -20px; transform: rotate(15deg); }

    .hero-content {
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 45px;
    }

    /* Wrapper สำหรับโลโก้ตรงกลาง */
    .logo-visual-wrapper {
        position: relative;
        width: 180px;
        height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* กล่องสีขาวมนๆ ตรงกลาง (ใส่ไอคอนเงินตามโจทย์) */
    .inner-white-box {
        background: var(--white);
        width: 130px;
        height: 130px;
        border-radius: 35px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        transform: rotate(-8deg);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        z-index: 2;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .hero-card:hover .inner-white-box {
        transform: rotate(0deg) scale(1.05);
    }

    /* ปรับแต่งไอคอนเงิน (Coin/Dollar) */
    .inner-white-box i {
        font-size: 5rem;
        background: linear-gradient(135deg, var(--primary-dark) 0%, #475569 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }

    /* ข้อความสไตล์ Modern Minimal */
    .hero-text {
        display: flex;
        flex-direction: column;
    }

    .hero-text h2.label-top {
        font-size: 2.8rem;
        font-weight: 700;
        color: var(--accent-color);
        margin: 0;
        letter-spacing: -1px;
    }

    .hero-text h1.main-title {
        font-size: 5.5rem;
        font-weight: 800;
        color: var(--primary-dark);
        margin: -10px 0;
        line-height: 1;
        letter-spacing: -3px;
        background: linear-gradient(to bottom, var(--primary-dark), #475569);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hero-text h2.label-bottom {
        font-size: 3.5rem;
        font-weight: 300;
        color: #94a3b8;
        margin: 0;
        letter-spacing: -1px;
    }

    /* จุดบอกสถานะด้านล่างขวา */
    .status-dots {
        position: absolute;
        bottom: 30px;
        right: 40px;
        display: flex;
        gap: 6px;
    }
    .s-dot { width: 6px; height: 6px; background: #e2e8f0; border-radius: 50%; }
    .s-dot.active { background: var(--accent-color); width: 20px; border-radius: 10px; }

    @media (max-width: 768px) {
        .hero-card { flex-direction: column; padding: 60px 30px; gap: 30px; }
        .hero-text { text-align: center; }
        .hero-text h1.main-title { font-size: 4rem; }
        .hero-text h2.label-top { font-size: 2rem; }
        .hero-text h2.label-bottom { font-size: 2.5rem; }
    }
</style>

<main class="hero-container">
    <div class="hero-card">
        <i class="fa-solid fa-wallet hero-bg-icon icon-bg-1"></i>
        <i class="fa-solid fa-chart-line hero-bg-icon icon-bg-2"></i>

        <div class="hero-content">
            <div class="logo-visual-wrapper">
                <div class="inner-white-box">
                    <i class="fa-solid fa-circle-dollar-to-slot"></i>
                </div>
            </div>

            <div class="hero-text">
                <h2 class="label-top">ระบบ</h2>
                <h1 class="main-title">การเงิน</h1>
                <h2 class="label-bottom">และบัญชี</h2>
            </div>
        </div>

        <div class="status-dots">
            <div class="s-dot active"></div>
            <div class="s-dot"></div>
            <div class="s-dot"></div>
        </div>
    </div>
</main>

<?php 
// [4. เรียกใช้ Footer]
require_once 'includes/footer.php'; 
?>