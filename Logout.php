<?php
// 1. เปิดใช้งาน Session เพื่อให้ระบบรู้ว่าเรากำลังจะจัดการกับ Session ไหน
session_start();

// 2. ล้างค่าตัวแปร Session ทั้งหมดที่เคยจำไว้ (เช่น $_SESSION['role'], $_SESSION['username'])
session_unset();

// 3. ทำลาย Session ทิ้งอย่างสมบูรณ์
session_destroy();

// 4. ส่งผู้ใช้งาน (Redirect) เด้งกลับไปที่หน้า Login
header("Location: Login.php");
exit();
?>