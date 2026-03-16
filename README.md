📊 ระบบแผนงานและงบประมาณ (System Budget)
สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2
(Web-based Application) คือระบบสารสนเทศเพื่อการบริหารจัดการโครงการ แผนปฏิบัติการ และการควบคุมงบประมาณ ถูกพัฒนาขึ้นเพื่อช่วยอำนวยความสะดวกในการบันทึกข้อมูลโครงการ จัดสรรยอดงบประมาณ ติดตามการตัดยอดเงิน และตรวจสอบสถานะทางการเงินต่างๆ ภายในองค์กร ให้มีความถูกต้อง โปร่งใส และสามารถเรียกดูรายงานได้แบบ Real-time

🚀 คุณสมบัติหลัก (Key Features)
Authentication: ระบบเข้าสู่ระบบที่ปลอดภัย แบ่งระดับสิทธิ์การใช้งาน (Admin, แผนงาน, การเงิน, User)

Dashboard: แสดงภาพรวมงบประมาณรายปี ยอดเบิกจ่ายจริง และยอดคงเหลือแยกตามประเภทงบ (งบประจำ / งบพัฒนาคุณภาพฯ)

Project Management: จัดการโครงการตามแผนปฏิบัติการ รองรับการจัดสรรงบประมาณเพิ่มได้สูงสุด 5 รอบ

Budget Cutoff System: ระบบส่งคำขอตัดยอดงบประมาณโครงการเพื่อรอการพิจารณาตรวจสอบจากเจ้าหน้าที่

Withdrawal & Loan: ทะเบียนขอเบิกเงินและขอยืมเงินงบประมาณ พร้อมระบบพิจารณาอนุมัติ

Tracking & Status: ติดตามสถานะคำขอเบิกเงินได้แบบ Real-time (รออนุมัติ / อนุมัติแล้ว / ไม่อนุมัติ)

Report & Export: ออกรายงานสรุปการเบิกจ่ายรายโครงการ รองรับการสั่งพิมพ์ (Print) และการส่งออกเป็นไฟล์ Excel

Responsive UI: ออกแบบมาให้ใช้งานง่าย (User-friendly) รองรับการแสดงผลทุกหน้าจอผ่าน Bootstrap 5

🛠 เทคโนโลยีที่ใช้ (Tech Stack)
Backend: PHP (Native/Vanilla) 7.4 - 8.x

Frontend: HTML5, CSS3, JavaScript (Vanilla JS)

Framework: Bootstrap 5.3

Icon Set: FontAwesome 6.4

Database: MySQL / MariaDB

Font: Google Fonts (Kanit, Sarabun)

📂 โครงสร้างไฟล์ที่สำคัญ (File Structure)
Login.php - หน้าเข้าสู่ระบบ

index.php - หน้าแรกแสดงสถิติเบื้องต้น

Dashboard.php - แผงควบคุมแสดงภาพรวมการเงินและกลุ่มงาน

Projectoutcomes.php - ระบบจัดการโครงการและการจัดสรรเงิน (1-5 รอบ)

Cut off the project budget.php - หน้าสำหรับ User บันทึกขอตัดยอดงบประมาณ

Approve the cut off amount.php - หน้าสำหรับเจ้าหน้าที่ตรวจสอบและอนุมัติตัดยอด

NewRequestforWithdrawalProjectLoan.php - ทะเบียนขอเบิก/ขอยืมเงินโครงการ

Payment approval history.php - ประวัติการอนุมัติให้เบิก/ยืมเงิน

project report.php - รายงานสรุปรายละเอียดการใช้จ่ายรายโครงการ

manage_users.php - ระบบจัดการข้อมูลผู้ใช้งาน (เฉพาะ Admin)

includes/ - โฟลเดอร์เก็บไฟล์เชื่อมต่อฐานข้อมูล (db.php) และส่วนหัว/ท้าย (header, footer, navbar)

## 🗄️ โครงสร้างฐานข้อมูล (Database Schema)

กรุณาสร้างฐานข้อมูลชื่อ **`system_budget`** และ Import คำสั่ง SQL ด้านล่างนี้:

```sql
-- 1. ตารางผู้ใช้งาน
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'แผนงาน', 'การเงิน', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. ตารางโครงการตามแผนปฏิบัติการ
CREATE TABLE project_outcomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_year INT NOT NULL,
    project_code VARCHAR(50),
    project_name VARCHAR(255) NOT NULL,
    group_name VARCHAR(255),
    responsible_person VARCHAR(255),
    budget_amount DECIMAL(15,2) DEFAULT 0,
    allocation_1 DECIMAL(15,2) DEFAULT 0,
    allocation_2 DECIMAL(15,2) DEFAULT 0,
    allocation_3 DECIMAL(15,2) DEFAULT 0,
    allocation_4 DECIMAL(15,2) DEFAULT 0,
    allocation_5 DECIMAL(15,2) DEFAULT 0,
    budget_type VARCHAR(100) COMMENT 'งบประจำ/งบพัฒนาคุณภาพฯ'
);

-- 3. ตารางบันทึกการขอตัดยอดงบประมาณ (สำหรับขั้นตอนตรวจสอบ)
CREATE TABLE project_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_year INT NOT NULL,
    project_id INT NOT NULL,
    expense_date DATE,
    details TEXT,
    cutoff_amount DECIMAL(15,2) DEFAULT 0,
    request_amount DECIMAL(15,2) DEFAULT 0,
    user_id INT NOT NULL,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approver_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. ตารางทะเบียนขอเบิก/ขอยืม (ข้อมูลหลักฐานการเงิน)
CREATE TABLE project_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_year INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    doc_location VARCHAR(255),
    request_type VARCHAR(100) COMMENT 'ขอเบิก/ขอยืม',
    expense_type VARCHAR(255),
    description TEXT,
    project_id INT,
    amount DECIMAL(15,2) DEFAULT 0,
    requester VARCHAR(100),
    officer_name VARCHAR(100) COMMENT 'ผู้อนุมัติให้เบิก/ยืม',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    user_id INT DEFAULT 0
);
