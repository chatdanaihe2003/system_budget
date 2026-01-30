#  Budget Control System
### ระบบบริหารจัดการและควบคุมงบประมาณ (สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2)

 (Web-based Application) คือระบบสารสนเทศเพื่อการบริหารจัดการงบประมาณ การเงิน และพัสดุ ถูกพัฒนาขึ้นเพื่อช่วยอำนวยความสะดวกในการบันทึก ติดตาม และตรวจสอบสถานะทางการเงินต่างๆ ภายในองค์กร ให้มีความถูกต้อง โปร่งใส และสามารถเรียกดูรายงานได้แบบ Real-time

---

## 🚀 คุณสมบัติหลัก (Key Features)

* **Authentication:** ระบบเข้าสู่ระบบ/สมัครสมาชิก และจัดการ Session ผู้ใช้งานที่มีความปลอดภัย (Password Hashing)
* **Budget Allocation:** บันทึกและจัดการการจัดสรรงบประมาณ (พร้อมแนบไฟล์หลักฐาน)
* **Revenue Management:** ทะเบียนรับเงินประเภทต่างๆ
    * รับเงินงบประมาณ
    * รับเงินนอกงบประมาณ
    * รับเงินรายได้แผ่นดิน
* **Withdrawal & Loan:** ระบบทะเบียนคุมหลักฐานขอเบิกและขอยืมเงินโครงการ
* **Refund System:** ระบบทะเบียนคืนเงินโครงการ
* **Configuration:** ตั้งค่าประเภทเงินหลักและประเภทย่อย
* **File Management:** ระบบอัปโหลดและดาวน์โหลดไฟล์เอกสารแนบ (PDF/Images)
* **UI/UX:** ออกแบบด้วย Bootstrap 5 ทันสมัย รองรับการใช้งานผ่านมือถือ (Responsive) พร้อมฟอนต์ไทย (Kanit/Sarabun) อ่านง่าย

---

## 🛠 เทคโนโลยีที่ใช้ (Tech Stack)

* **Backend:** PHP (Native/Vanilla) 7.4 - 8.x
* **Frontend:** HTML5, CSS3, JavaScript
* **Framework:** Bootstrap 5.3
* **Icon Set:** FontAwesome 6.4
* **Database:** MySQL / MariaDB
* **Font:** Google Fonts (Kanit, Sarabun)

---

## 📂 โครงสร้างไฟล์ (File Structure)

* `Login.php` - หน้าเข้าสู่ระบบ
* `Register.php` - หน้าสมัครสมาชิกใหม่
* `Logout.php` - สคริปต์ออกจากระบบ
* `index.php` - หน้าหลัก (Dashboard)
* `Budgetallocation.php` - ทะเบียนรับการจัดสรรงบประมาณ
* `Receivebudget.php` - ทะเบียนรับเงินงบประมาณ
* `Receiveoffbudget.php` - ทะเบียนรับเงินนอกงบประมาณ
* `Receivenational.php` - ทะเบียนรับเงินรายได้แผ่นดิน
* `RequestforWithdrawalProjectLoan.php` - ทะเบียนขอเบิก/ขอยืมเงินโครงการ
* `ProjectRefundRegistration.php` - ทะเบียนคืนเงินโครงการ
* `Subtypesmoney.php` - ตั้งค่าประเภท(ย่อย)ของเงิน
* `uploads/` - โฟลเดอร์สำหรับเก็บไฟล์เอกสารแนบ

---

## 🗄️ โครงสร้างฐานข้อมูล (Database Schema)

กรุณาสร้างฐานข้อมูลชื่อ **`system_budget`** และ Import คำสั่ง SQL ด้านล่างนี้:

```sql
-- สร้างตารางผู้ใช้งาน
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- สร้างตารางปีงบประมาณ (สำหรับ Dropdown)
CREATE TABLE fiscal_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_year INT NOT NULL
);

-- สร้างตารางประเภทเงินหลัก
CREATE TABLE money_types_main (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_code VARCHAR(50),
    type_name VARCHAR(255)
);

-- สร้างตารางประเภทเงินย่อย
CREATE TABLE money_types_sub (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_year INT NOT NULL,
    subtype_code VARCHAR(50),
    subtype_name VARCHAR(255),
    main_type_id INT,
    FOREIGN KEY (main_type_id) REFERENCES money_types_main(id)
);

-- ตารางรับการจัดสรรงบประมาณ
CREATE TABLE budget_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    allocation_order INT,
    doc_date DATE,
    description TEXT,
    amount DECIMAL(15,2),
    file_name VARCHAR(255)
);

-- ตารางรับเงินงบประมาณ
CREATE TABLE receive_budget (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receive_order INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    description TEXT,
    transaction_type VARCHAR(100),
    amount DECIMAL(15,2),
    file_name VARCHAR(255)
);

-- ตารางรับเงินนอกงบประมาณ
CREATE TABLE receive_off_budget (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receive_order INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    description TEXT,
    transaction_type VARCHAR(100),
    amount DECIMAL(15,2),
    file_name VARCHAR(255)
);

-- ตารางรับเงินรายได้แผ่นดิน
CREATE TABLE receive_national (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receive_order INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    description TEXT,
    transaction_type VARCHAR(100),
    amount DECIMAL(15,2),
    file_name VARCHAR(255)
);

-- ตารางคืนเงินโครงการ
CREATE TABLE project_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    refund_order INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    description TEXT,
    amount DECIMAL(15,2),
    is_other_officer TINYINT(1) DEFAULT 0
);

-- ตารางขอเบิก/ขอยืมเงินโครงการ
CREATE TABLE project_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    withdrawal_order INT,
    doc_date DATE,
    doc_no VARCHAR(100),
    description TEXT,
    amount DECIMAL(15,2),
    status INT DEFAULT 1 COMMENT '1=เบิก/ส่งใช้, 2=ยืม',
    deka VARCHAR(100),
    is_other_officer TINYINT(1) DEFAULT 0
);
