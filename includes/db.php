<?php
session_start();

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login.php"); // ถอยกลับ 1 ชั้นเพราะไฟล์นี้อยู่ใน includes
    exit();
}

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "system_budget";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงปีงบประมาณ Active (ใช้ได้ทุกหน้า)
$active_year = date("Y") + 543;
$sql_check_active = "SELECT budget_year FROM fiscal_years WHERE is_active = 1 LIMIT 1";
$result_check_active = $conn->query($sql_check_active);
if ($result_check_active && $result_check_active->num_rows > 0) {
    $row_active = $result_check_active->fetch_assoc();
    $active_year = $row_active['budget_year'];
}

// ฟังก์ชันวันที่ไทย (รวมไว้ที่นี่ที่เดียว)
function thai_date($timestamp) {
    $thai_day_arr = array("อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์");
    $thai_month_arr = array("0"=>"","1"=>"มกราคม","2"=>"กุมภาพันธ์","3"=>"มีนาคม","4"=>"เมษายน","5"=>"พฤษภาคม","6"=>"มิถุนายน","7"=>"กรกฎาคม","8"=>"สิงหาคม","9"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม");
    $d = date("j", $timestamp);
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543;
    return "วัน".$thai_day_arr[date("w", $timestamp)]."ที่ $d ".$thai_month_arr[$m]." พ.ศ. $y";
}

function thai_date_short($date_str) {
    if(!$date_str || $date_str == '0000-00-00') return "";
    $timestamp = strtotime($date_str);
    $thai_month_arr = array("0"=>"","1"=>"ม.ค.","2"=>"ก.พ.","3"=>"มี.ค.","4"=>"เม.ย.","5"=>"พ.ค.","6"=>"มิ.ย.","7"=>"ก.ค.","8"=>"ส.ค.","9"=>"ก.ย.","10"=>"ต.ค.","11"=>"พ.ย.","12"=>"ธ.ค.");
    $d = str_pad(date("j", $timestamp), 2, '0', STR_PAD_LEFT); 
    $m = date("n", $timestamp);
    $y = date("Y", $timestamp) + 543; 
    return "$d {$thai_month_arr[$m]} $y"; 
}
?>