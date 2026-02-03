<?php
// fix_database.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "system_budget";

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>กำลังตรวจสอบและซ่อมแซมฐานข้อมูล...</h3>";

// รายชื่อคอลัมน์ที่ต้องมี
$columns_needed = [
    "withdrawal_order" => "INT DEFAULT 0",
    "doc_date" => "DATE NULL",
    "doc_no" => "VARCHAR(50) NULL",
    "withdrawal_type" => "INT DEFAULT 1 COMMENT '1=ยืมงบ, 2=ยืมนอก, 3=ยืมทดรอง, 4=เบิก'",
    "description" => "VARCHAR(255) NULL",
    "project_id" => "INT DEFAULT 0",
    "activity_id" => "INT DEFAULT 0",
    "amount" => "DECIMAL(15,2) DEFAULT 0.00",
    "expense_type" => "VARCHAR(100) NULL",
    "requester" => "VARCHAR(100) NULL",
    "status" => "INT DEFAULT 1",
    "deka" => "VARCHAR(100) NULL",
    "is_other_officer" => "TINYINT(1) DEFAULT 0"
];

// ตรวจสอบทีละคอลัมน์
foreach ($columns_needed as $col_name => $col_def) {
    $check = $conn->query("SHOW COLUMNS FROM project_withdrawals LIKE '$col_name'");
    if ($check->num_rows == 0) {
        // ถ้าไม่มี ให้เพิ่มเข้าไป
        $sql = "ALTER TABLE project_withdrawals ADD COLUMN $col_name $col_def";
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color:green;'>✅ เพิ่มคอลัมน์ <strong>$col_name</strong> สำเร็จ</p>";
        } else {
            echo "<p style='color:red;'>❌ เพิ่มคอลัมน์ $col_name ไม่สำเร็จ: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:blue;'>ℹ️ คอลัมน์ <strong>$col_name</strong> มีอยู่แล้ว (ไม่ต้องทำอะไร)</p>";
    }
}

echo "<hr><h3>เสร็จเรียบร้อย! กลับไปที่หน้า <a href='RequestforWithdrawalProjectLoan.php'>ทะเบียนขอเบิก</a> ได้เลยครับ</h3>";
$conn->close();
?>