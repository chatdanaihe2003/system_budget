-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 10:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `system_budget`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessment_logs`
--

CREATE TABLE `assessment_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'รหัสผู้ใช้งานที่ทำแบบประเมินไปแล้ว',
  `action` varchar(50) NOT NULL DEFAULT 'evaluated' COMMENT 'สถานะการทำรายการ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_logs`
--

INSERT INTO `assessment_logs` (`id`, `user_id`, `action`, `created_at`) VALUES
(3, 5, 'evaluated', '2026-03-13 08:20:02'),
(4, 7, 'evaluated', '2026-03-13 08:23:02'),
(5, 10, 'evaluated', '2026-03-13 08:36:14'),
(6, 8, 'evaluated', '2026-03-13 09:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_results`
--

CREATE TABLE `assessment_results` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'รหัสผู้ใช้งานที่ทำแบบประเมิน',
  `user_name` varchar(100) NOT NULL COMMENT 'ชื่อผู้ทำแบบประเมิน',
  `user_role` varchar(50) NOT NULL COMMENT 'สิทธิ์ของผู้ประเมิน',
  `gender` varchar(20) DEFAULT NULL COMMENT 'เพศ',
  `age_range` varchar(50) DEFAULT NULL COMMENT 'ช่วงอายุ',
  `job_position` varchar(100) DEFAULT NULL COMMENT 'ตำแหน่งงาน',
  `group_name` varchar(100) DEFAULT NULL COMMENT 'สังกัดกลุ่มงาน',
  `exp` varchar(50) DEFAULT NULL COMMENT 'ประสบการณ์การทำงาน',
  `func_0` int(1) NOT NULL DEFAULT 0,
  `func_1` int(1) NOT NULL DEFAULT 0,
  `func_2` int(1) NOT NULL DEFAULT 0,
  `func_3` int(1) NOT NULL DEFAULT 0,
  `use_0` int(1) NOT NULL DEFAULT 0,
  `use_1` int(1) NOT NULL DEFAULT 0,
  `use_2` int(1) NOT NULL DEFAULT 0,
  `use_3` int(1) NOT NULL DEFAULT 0,
  `perf_0` int(1) NOT NULL DEFAULT 0,
  `perf_1` int(1) NOT NULL DEFAULT 0,
  `perf_2` int(1) NOT NULL DEFAULT 0,
  `sec_0` int(1) NOT NULL DEFAULT 0,
  `sec_1` int(1) NOT NULL DEFAULT 0,
  `sec_2` int(1) NOT NULL DEFAULT 0,
  `impact_0` int(1) NOT NULL DEFAULT 0,
  `impact_1` int(1) NOT NULL DEFAULT 0,
  `impact_2` int(1) NOT NULL DEFAULT 0,
  `overall_0` int(1) NOT NULL DEFAULT 0,
  `overall_1` int(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่ทำแบบประเมิน',
  `fav` text DEFAULT NULL,
  `prob` text DEFAULT NULL,
  `req` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_results`
--

INSERT INTO `assessment_results` (`id`, `user_id`, `user_name`, `user_role`, `gender`, `age_range`, `job_position`, `group_name`, `exp`, `func_0`, `func_1`, `func_2`, `func_3`, `use_0`, `use_1`, `use_2`, `use_3`, `perf_0`, `perf_1`, `perf_2`, `sec_0`, `sec_1`, `sec_2`, `impact_0`, `impact_1`, `impact_2`, `overall_0`, `overall_1`, `created_at`, `fav`, `prob`, `req`) VALUES
(3, 5, 'ธีศักดิ์ ศรีวงศ์', 'USERทั่วไป', 'ชาย', '25 - 35 ปี', 'เจ้าหน้าที่ ICT', 'กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร', 'มากกว่า 3 ปี', 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, '2026-03-13 08:20:02', '', '', ''),
(4, 7, 'นางเปรมขวัญ  กรายไทยสงค์', 'USERทั่วไป', 'หญิง', '46 - 55 ปี', 'ผู้อำนวยการกลุ่มบริหารงานการเงินและสินทรัพย์', 'กลุ่มบริหารการเงินและสินทรัพย์', '1 - 3 ปี', 5, 4, 5, 4, 4, 4, 5, 5, 5, 4, 5, 5, 4, 4, 4, 5, 5, 5, 4, '2026-03-13 08:23:02', '', '', ''),
(5, 10, 'ชญาณ์นันท์  วริศธาดาศิริกุล', 'USERทั่วไป', 'หญิง', '46 - 55 ปี', 'นักวิชาการเงินและบัญชีชำนาญการ', 'กลุ่มบริหารการเงินและสินทรัพย์', '1 - 3 ปี', 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, '2026-03-13 08:36:14', 'สะดวกในการใช้งาน', '', ''),
(6, 8, 'ภัทรินทร์ เทวิน', 'USERทั่วไป', 'หญิง', '25 - 35 ปี', 'เจ้าพนักงานธุรการปฏิบัติงาน', 'กลุ่มนโยบายและแผน', '1 - 3 ปี', 4, 4, 5, 4, 5, 5, 5, 5, 5, 5, 4, 5, 4, 4, 5, 5, 5, 4, 4, '2026-03-13 09:35:13', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `budget_allocations`
--

CREATE TABLE `budget_allocations` (
  `id` int(11) NOT NULL,
  `budget_year` int(11) DEFAULT NULL,
  `allocation_order` int(11) DEFAULT NULL,
  `doc_date` date DEFAULT NULL,
  `doc_no` varchar(255) DEFAULT NULL,
  `project_code` varchar(255) DEFAULT NULL,
  `project_name` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `budget_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `allocation_1` decimal(15,2) DEFAULT 0.00,
  `allocation_2` decimal(15,2) DEFAULT 0.00,
  `allocation_3` decimal(15,2) DEFAULT 0.00,
  `allocation_4` decimal(15,2) DEFAULT 0.00,
  `allocation_5` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses_budget`
--

CREATE TABLE `expenses_budget` (
  `id` int(11) NOT NULL,
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ',
  `expense_name` varchar(255) NOT NULL COMMENT 'ชื่อหมวดรายจ่าย (เช่น ค่าตอบแทน, ค่าใช้สอย, ค่าวัสดุ)',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดเพิ่มเติม (ถ้ามี)',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'สถานะการใช้งาน: active (เปิดใช้), inactive (ปิดใช้)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึกข้อมูล'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_types`
--

CREATE TABLE `expense_types` (
  `id` int(11) NOT NULL,
  `expense_code` varchar(50) NOT NULL,
  `expense_name` varchar(255) NOT NULL,
  `budget_category` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_types`
--

INSERT INTO `expense_types` (`id`, `expense_code`, `expense_name`, `budget_category`, `created_at`) VALUES
(1, '777', 'โอ', '', '2026-03-13 03:54:00'),
(2, '110', 'งบลงทุน', '', '2026-03-13 03:54:13'),
(3, '554', 'งบรายจ่าย', '', '2026-03-13 03:54:20'),
(4, '240', 'งบดำเนินการ', '', '2026-03-13 03:54:34'),
(5, '', 'หกฟห', '', '2026-03-13 04:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `project_expenses`
--

CREATE TABLE `project_expenses` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'รหัสโครงการ (อ้างอิงจากตาราง project_outcomes)',
  `user_id` int(11) NOT NULL COMMENT 'รหัสผู้ใช้งานที่ส่งคำขอตัดยอด (อ้างอิงจากตาราง users)',
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ',
  `expense_date` date NOT NULL COMMENT 'วันที่ทำรายการขอตัดยอด',
  `details` text NOT NULL COMMENT 'รายละเอียดการขอเบิกจ่าย',
  `cutoff_amount` decimal(15,2) NOT NULL COMMENT 'จำนวนเงินที่ขอตัดยอด',
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'สถานะ: pending(รออนุมัติ), approved(อนุมัติแล้ว), rejected(ไม่อนุมัติ)',
  `approver_id` int(11) DEFAULT NULL COMMENT 'รหัสผู้ที่กดอนุมัติ (อ้างอิงจากตาราง users)',
  `approved_at` datetime DEFAULT NULL COMMENT 'วันเวลาที่กดอนุมัติ หรือ ไม่อนุมัติ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึกข้อมูลเข้าสู่ระบบ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_expenses`
--

INSERT INTO `project_expenses` (`id`, `project_id`, `user_id`, `budget_year`, `expense_date`, `details`, `cutoff_amount`, `approval_status`, `approver_id`, `approved_at`, `created_at`) VALUES
(27, 9, 1, 2569, '2026-03-16', 'ีรีส', 20000.00, 'approved', NULL, NULL, '2026-03-16 04:00:25'),
(28, 8, 1, 2569, '2026-03-16', 'หกฟกฟห', 50000.00, 'approved', NULL, NULL, '2026-03-16 06:38:32'),
(29, 17, 4, 2569, '2026-03-16', 'adasdas', 1600.00, 'approved', NULL, NULL, '2026-03-16 06:59:04'),
(30, 12, 4, 2569, '2026-03-16', 'หฟกฟ', 1800.00, 'approved', NULL, NULL, '2026-03-16 07:18:06'),
(31, 9, 1, 2569, '2026-03-16', 'kjjrt', 30000.00, 'approved', NULL, NULL, '2026-03-16 08:59:16');

-- --------------------------------------------------------

--
-- Table structure for table `project_outcomes`
--

CREATE TABLE `project_outcomes` (
  `id` int(11) NOT NULL,
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ (เช่น 2567)',
  `project_code` varchar(50) DEFAULT NULL COMMENT 'รหัสโครงการ',
  `project_name` varchar(255) NOT NULL COMMENT 'ชื่อโครงการ',
  `group_name` varchar(100) NOT NULL COMMENT 'กลุ่มงานที่รับผิดชอบ (ใช้กรองให้ User เห็นเฉพาะกลุ่มตัวเอง)',
  `responsible_person` varchar(255) DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `budget_type` varchar(100) NOT NULL COMMENT 'ประเภทงบ (เช่น งบประจำ, งบพัฒนาคุณภาพการศึกษา)',
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'งบประมาณรวมที่ได้รับจัดสรร',
  `allocation_1` decimal(15,2) DEFAULT 0.00,
  `allocation_2` decimal(15,2) DEFAULT 0.00,
  `allocation_3` decimal(15,2) DEFAULT 0.00,
  `allocation_4` decimal(15,2) DEFAULT 0.00,
  `allocation_5` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างโครงการ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_outcomes`
--

INSERT INTO `project_outcomes` (`id`, `budget_year`, `project_code`, `project_name`, `group_name`, `responsible_person`, `activities`, `budget_type`, `budget_amount`, `allocation_1`, `allocation_2`, `allocation_3`, `allocation_4`, `allocation_5`, `created_at`) VALUES
(8, 2569, '', 'ค่าเบี้ยเลี้ยง พาหนะ ค่าเช่าที่พัก ค่าชดเชยค่าน้ำมัน\r\n', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 100000.00, 20000.00, 10000.00, 10000.00, 10000.00, 0.00, '2026-03-13 08:57:54'),
(9, 2569, '', 'ค่าซ่อมแซมยานพาหนะและค่าขนส่ง', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 100000.00, 30000.00, 10000.00, 10000.00, 0.00, 0.00, '2026-03-13 08:58:50'),
(10, 2569, '', 'ค่าซ่อมแซมครุภัณฑ์ \r\n', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 120000.00, 20000.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 08:59:38'),
(11, 2569, '', 'ค่าวัสดุสำนักงานและวัสดุอื่น ๆ\r\n', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 200000.00, 30000.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:00:09'),
(12, 2569, '', 'ค่าเช่าเครื่องถ่ายเอกสาร รายเดือน (ต.ค.68-ก.ย.69)\r\n', 'กลุ่มอำนวยการ', 'ผอ.กลุ่มอำนวยการ', '[]', 'งบประจำ', 76800.00, 76800.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:01:01'),
(13, 2569, '', 'ค่าวัสดุเชื้อเพลิงและหล่อลื่น\r\n', 'กลุ่มอำนวยการ', 'ผอ.กลุ่มอำนวยการ', '[]', 'งบประจำ', 160000.00, 50000.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:01:42'),
(14, 2569, '', 'ค่าซ่อมแซมสิ่งก่อสร้าง\r\n', 'กลุ่มอำนวยการ', 'ผอ.กลุ่มอำนวยการ', '[]', 'งบประจำ', 150000.00, 100000.00, 21310.00, 0.00, 0.00, 0.00, '2026-03-13 09:02:21'),
(15, 2569, '', 'ค่า พ.ร.บ./ประกันรถยนต์ราชการส่วนกลาง', 'กลุ่มอำนวยการ', 'ผอ.กลุ่มอำนวยการ', '[]', 'งบประจำ', 30000.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:03:22'),
(16, 2569, '', 'ค่าเติมน้ำยาเครื่องดับเพลิง', 'กลุ่มอำนวยการ', 'ผอ.กลุ่มอำนวยการ', '[]', 'งบประจำ', 20000.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:04:00'),
(17, 2569, '', 'ค่าเช่าใช้บริการพื้นที่จัดทำเว็บไซต์ สพป.ชลบุรี เขต 2\r\n', 'กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร', 'ผอ.กลุ่ม DLICT', '[]', 'งบประจำ', 21600.00, 21600.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:05:03'),
(18, 2569, '', 'ค่าเช่าพื้นที่จัดทำระบบสารบรรณอิเล็กทรอนิกส์ ของ สพป.ชลบุรี เขต 2(ต.ค.68-ก.ย.69)\r\n', 'กลุ่มส่งเสริมการศึกษาทางไกล เทคโนโลยีสารสนเทศและการสื่อสาร', 'ผอ.กลุ่ม DLICT', '[]', 'งบประจำ', 38520.00, 38520.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:06:46'),
(19, 2569, '', 'ค่าจ้างเหมาบริการผู้ปฏิบัติงาน ใน สพป.ชลบุรี เขต 2 ต่อเนื่อง จำนวน 9 คน(ต.ค.68-มี.ค.69)\r\n', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 1114800.00, 557400.00, 0.00, 0.00, 0.00, 0.00, '2026-03-13 09:07:29'),
(20, 2569, '', 'ค่าสาธารณูปโภค(ไฟฟ้า ประปา โทรศัพท์ ไปรษณีย์ และอินเทอร์เน็ต)\r\n', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผอ.กลุ่มบริหารงานการเงินฯ', '[]', 'งบประจำ', 1100000.00, 300000.00, 100000.00, 0.00, 0.00, 0.00, '2026-03-13 09:08:19'),
(21, 2569, '', 'การประชุมการบริหารงานบุคคลของข้าราชการครูและบุคลากรทางการศึกษา\r\nสังกัด สพป.ชลบุรี เขต 2 ปีงบประมาณ พ.ศ. 2569(ประชุม อ.ก.ค.ศ.)\r\n', 'กลุ่มบริหารงานบุคคล', 'ผอ.กลุ่มบริหารงานบุคคล', '[]', 'งบประจำ', 130000.00, 50000.00, 20000.00, 0.00, 0.00, 0.00, '2026-03-13 09:09:20'),
(23, 2569, '', 'ค่าใช้จ่ายเบ็ดเตล็ด', 'กลุ่มบริหารการเงินและสินทรัพย์', 'ผู้อำนวยการกลุ่มการเงิน', '[]', 'งบพัฒนาคุณภาพการศึกษา', 38280.00, 5578.00, 7200.00, 0.00, 0.00, 0.00, '2026-03-16 03:17:33');

-- --------------------------------------------------------

--
-- Table structure for table `project_withdrawals`
--

CREATE TABLE `project_withdrawals` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `budget_year` int(4) NOT NULL,
  `withdrawal_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'ยอดเงินที่อนุมัติเบิกจ่ายจริง',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `withdrawal_order` int(11) DEFAULT 0,
  `request_type` varchar(100) DEFAULT NULL,
  `doc_location` varchar(255) DEFAULT NULL,
  `expense_type` varchar(255) DEFAULT NULL,
  `doc_date` date DEFAULT NULL,
  `doc_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requester` varchar(100) DEFAULT NULL,
  `officer_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT 0,
  `cutoff_approver` varchar(100) DEFAULT NULL,
  `payment_approver` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_withdrawals`
--

INSERT INTO `project_withdrawals` (`id`, `project_id`, `budget_year`, `withdrawal_date`, `amount`, `status`, `created_at`, `withdrawal_order`, `request_type`, `doc_location`, `expense_type`, `doc_date`, `doc_no`, `description`, `requester`, `officer_name`, `user_id`, `cutoff_approver`, `payment_approver`) VALUES
(15, 17, 2569, '0000-00-00', 21600.00, 'rejected', '2026-03-13 09:25:24', 0, 'ขอเบิก', NULL, NULL, '2026-03-13', '-', 'ค่าเช่าพื้นที่จัดทำเว็บไซต์ สพป.ชลบุรีเขต2  ปีงบประมาณ2569', 'ผอ.กลุ่ม DLICT', NULL, 0, NULL, NULL),
(16, 18, 2569, '0000-00-00', 38520.00, 'rejected', '2026-03-13 09:25:32', 0, 'ขอเบิก', NULL, NULL, '2026-03-13', '-', 'ค่าเช่าพื้นที่จัดทำระบบสารบัญอิเล็กทรอนิกส์', 'ผอ.กลุ่ม DLICT', NULL, 0, NULL, NULL),
(18, 23, 2569, '0000-00-00', 12778.00, 'rejected', '2026-03-16 03:33:21', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'ค่าใช้จ่ายเบ็ดเตล็ด', 'ผู้ดูแลระบบ (Admin)', NULL, 0, NULL, NULL),
(19, 21, 2569, '0000-00-00', 70000.00, 'rejected', '2026-03-16 03:35:48', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', '700', 'ผู้ดูแลระบบ (Admin)', NULL, 0, NULL, NULL),
(20, 18, 2569, '0000-00-00', 38520.00, 'rejected', '2026-03-16 03:37:35', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'sdad', 'นาย ฉัตรดนัย เห็มทิพย์', NULL, 0, NULL, NULL),
(21, 9, 2569, '0000-00-00', 20000.00, 'approved', '2026-03-16 06:33:25', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'ีรีส', 'ผู้ดูแลระบบ (Admin)', 'ผู้ดูแลระบบ (Admin)', 0, NULL, NULL),
(22, 8, 2569, '0000-00-00', 50000.00, 'approved', '2026-03-16 06:38:41', 0, 'ขอเบิก', 'sda', 'งบรายจ่าย', '2026-03-16', '-', 'หกฟกฟห', 'ผู้ดูแลระบบ (Admin)', 'นาง การเงิน เงินไม่มี', 0, NULL, NULL),
(23, 17, 2569, '0000-00-00', 1600.00, 'approved', '2026-03-16 06:59:31', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'adasdas', 'นาย ฉัตรดนัย เห็มทิพย์', 'นาง การเงิน เงินไม่มี', 0, NULL, NULL),
(24, 12, 2569, '0000-00-00', 1800.00, 'approved', '2026-03-16 07:18:21', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'หฟกฟ', 'นาย ฉัตรดนัย เห็มทิพย์', 'นาง การเงิน เงินไม่มี', 0, NULL, NULL),
(25, 9, 2569, '0000-00-00', 30000.00, 'pending', '2026-03-16 09:03:22', 0, 'ขอเบิก', NULL, NULL, '2026-03-16', '-', 'kjjrt', 'ผู้ดูแลระบบ (Admin)', 'ผู้ดูแลระบบ (Admin)', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'User',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `fullname`, `role`, `created_at`) VALUES
(1, 'admin', '202cb962ac59075b964b07152d234b70', 'ผู้ดูแลระบบ (Admin)', 'ผู้ดูแลระบบ (Admin)', 'Admin', '2026-03-13 03:07:06'),
(2, 'plan', '25d55ad283aa400af464c76d713c07ad', 'นาง วางแผน ดักปล้น', 'นาง วางแผน ดักปล้น', 'แผนงาน', '2026-03-13 04:11:54'),
(3, 'finance', '25d55ad283aa400af464c76d713c07ad', 'นาง การเงิน เงินไม่มี', 'นาง การเงิน เงินไม่มี', 'การเงิน', '2026-03-13 04:12:28'),
(4, 'kingroes1', '25d55ad283aa400af464c76d713c07ad', 'นาย ฉัตรดนัย เห็มทิพย์', 'นาย ฉัตรดนัย เห็มทิพย์', 'User', '2026-03-13 04:12:42'),
(5, 'teera', '81dc9bdb52d04dc20036dbd8313ed055', 'ธีศักดิ์ ศรีวงศ์', 'ธีศักดิ์ ศรีวงศ์', 'การเงิน', '2026-03-13 08:15:18'),
(6, 'rath009', '88f4a99ab20a12fc7f22e256ea678930', 'นายสมรัฐ บัวทอง', 'นายสมรัฐ บัวทอง', 'แผนงาน', '2026-03-13 08:16:50'),
(7, 'prem123', 'e10adc3949ba59abbe56e057f20f883e', 'นางเปรมขวัญ  กรายไทยสงค์', 'นางเปรมขวัญ  กรายไทยสงค์', 'การเงิน', '2026-03-13 08:22:10'),
(8, 'pattarin', '3dc70b52f682f77007baed70289dda8f', 'ภัทรินทร์ เทวิน', 'ภัทรินทร์ เทวิน', 'แผนงาน', '2026-03-13 08:25:15'),
(9, 'chaya', '19c802f3121ea9e1ee690c96d0104eaf', 'นางสาวชญานิศ  มีแสง', 'นางสาวชญานิศ  มีแสง', 'แผนงาน', '2026-03-13 08:31:48'),
(10, 'chayanan', '81dc9bdb52d04dc20036dbd8313ed055', 'ชญาณ์นันท์  วริศธาดาศิริกุล', NULL, 'USERทั่วไป', '2026-03-13 08:33:35'),
(11, 'Thanasorn', '9be02f475c13acea2af3e2cc208b9341', 'ธนสร ดีศรี', NULL, 'USERทั่วไป', '2026-03-13 08:35:23'),
(12, 'Mn.chon2', '86625c53a137b480d8c7c0eb34925f7d', 'สุดารัตน์ พรมลอย', NULL, 'USERทั่วไป', '2026-03-13 09:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `year_budget`
--

CREATE TABLE `year_budget` (
  `id` int(11) NOT NULL,
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ (เช่น 2567, 2568)',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'สถานะปีปัจจุบัน: 1 = กำลังใช้งาน, 0 = ปิดปีงบประมาณแล้ว',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่เพิ่มข้อมูล'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `year_budget`
--

INSERT INTO `year_budget` (`id`, `budget_year`, `is_active`, `created_at`) VALUES
(1, 2567, 0, '2026-03-13 03:17:50'),
(2, 2568, 0, '2026-03-13 03:40:30'),
(3, 2569, 1, '2026-03-13 03:40:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessment_logs`
--
ALTER TABLE `assessment_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `assessment_results`
--
ALTER TABLE `assessment_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses_budget`
--
ALTER TABLE `expenses_budget`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_types`
--
ALTER TABLE `expense_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_expenses`
--
ALTER TABLE `project_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_outcomes`
--
ALTER TABLE `project_outcomes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_withdrawals`
--
ALTER TABLE `project_withdrawals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `year_budget`
--
ALTER TABLE `year_budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `budget_year` (`budget_year`) COMMENT 'ป้องกันการเพิ่มปีงบประมาณซ้ำ';

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessment_logs`
--
ALTER TABLE `assessment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `assessment_results`
--
ALTER TABLE `assessment_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `expenses_budget`
--
ALTER TABLE `expenses_budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_types`
--
ALTER TABLE `expense_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_expenses`
--
ALTER TABLE `project_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `project_outcomes`
--
ALTER TABLE `project_outcomes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `project_withdrawals`
--
ALTER TABLE `project_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `year_budget`
--
ALTER TABLE `year_budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
