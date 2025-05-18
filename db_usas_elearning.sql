-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 18, 2025 at 11:03 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_usas_elearning`
--

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
CREATE TABLE IF NOT EXISTS `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_id` int NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=MyISAM AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `subject_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`) VALUES
(134, 1, 'How can you control a smart device connected with IoT? / Bagaimanakah anda boleh mengawal peranti pintar yang disambungkan dengan IoT?', 'Game controller / Pengawal permainan', 'Related application / Apl yang berkaitan', 'A transistor / Sebuah transistor', 'Connected keyboard and mouse / Papan kekunci dan tetikus yang disambungkan', 'B', '2025-05-06 17:25:31'),
(135, 1, 'Which of the following is NOT a social networking site? / Antara berikut, yang manakah BUKAN laman rangkaian sosial?', 'Chrome / Chrome', 'Facebook / Facebook', 'X / X', 'TikTok / TikTok', 'A', '2025-05-06 17:25:31'),
(133, 1, 'Apple II is an example of what type of device? Apple II adalah contoh jenis peranti apa?', 'First-generation computer / Komputer generasi pertama', 'Microprocessor / Mikropemproses', 'Computer designed for personal use / Komputer yang direka untuk kegunaan peribadi', 'Smart device in the Internet of Things / Peranti pintar di Internet Perkara', 'C', '2025-05-06 17:25:31'),
(132, 1, 'Which of the following is a collection of worldwide networks that connects millions of businesses, government agencies, educational institutions, and individuals? / Manakah antara berikut merupakan koleksi rangkaian di seluruh dunia yang menghubungkan berjuta- juta perniagaan, agensi kerajaan, institusi pendidikan dan individu?', 'Web / Web', 'Usenet / Usenet', 'Newsnet / Newsnet', 'Internet / Internet', 'D', '2025-05-06 17:25:31'),
(136, 1, 'Kevin is a hotel manager and has seen impatient customer lines at the reception counter. What IoT technology could Kevin use to help solve this problem? Kevin ialah seorang pengurus hotel dan telah melihat barisan pelanggan yang tidak sabar di kaunter penerimaan tetamu. Apakah teknologi IoT yang boleh Kevin gunakan untuk membantu menyelesaikan masalah ini?', 'ATM / ATM', 'Webcam / Kamera web', 'Kiosk / Kiosk', 'Sensor / Penderia', 'C', '2025-05-06 17:25:31'),
(137, 1, 'Which of the following is true about VoIP? / Antara berikut, yang manakah benar tentang VoIP?', 'It uses slow dial-up connections. / Ia menggunakan sambungan dial-up kelajuan perlahan.', 'It uses public switched telephone networks. / Ia menggunakan rangkaian telefon suis awam.', 'Skype is an example of VoIP software. / Skype ialah contoh perisian VoIP.', 'All of the above. / Semua di atas.', 'C', '2025-05-06 17:25:31'),
(131, 1, 'Which of the following is a personal computer that users can carry from place to place? / Antara berikut, yang manakah komputer peribadi yang boleh dibawa oleh pengguna dari satu tempat ke satu tempat?', 'Integrated computer / Komputer bersepadu', 'Desktop computer / Komputer desktop', 'Mobile computer / Komputer mudah alih', 'Capsule computer / Komputer berkapsul', 'C', '2025-05-06 17:25:31'),
(129, 1, 'Which of the following is a popular social networking site? / Antara berikut, yang manakah merupakan laman rangkaian sosial yang popular?', 'Facebook / Facebook', 'Chrome / Chrome', 'Safari / Safari', 'Internet Explorer / Internet Explorer', 'A', '2025-05-06 17:25:31'),
(130, 1, 'Which of the following is true about computers? / Antara berikut, yang manakah benar tentang komputer?', 'Its electronic components process data using instructions. / Komponen elektroniknya memproses data menggunakan arahan.', 'It creates data from information collected using software that directs processing in the computer. / Ia mencipta data daripada maklumat yang dikumpulnya menggunakan perisian yang mengarahkan pemprosesan dalam komputer.', 'It converts data to information or information to data, depending on the information processing cycle status. / Ia menukar data kepada maklumat atau menukar maklumat kepada data, bergantung pada status kitaran pemprosesan maklumat.', 'It is an electronic device that processes data as determined by the computer user when the user enters instructions. / Ia adalah peranti elektronik yang memproses data seperti yang ditentukan oleh pengguna komputer apabila pengguna memasukkan arahan.', 'A', '2025-05-06 17:25:31'),
(128, 1, 'Which of the following consists of electronic components that store instructions waiting to be executed and the data needed by those instructions? / Antara berikut, yang manakah terdiri daripada komponen elektronik yang menyimpan arahan yang menunggu untuk dilaksanakan dan data yang diperlukan oleh arahan tersebut?', 'Processor / Pemproses', 'CPU / CPU', 'Control unit / Unit kawalan', 'Memory / Ingatan', 'D', '2025-05-06 17:25:31');

-- --------------------------------------------------------

--
-- Table structure for table `question_documents`
--

DROP TABLE IF EXISTS `question_documents`;
CREATE TABLE IF NOT EXISTS `question_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `question_documents`
--

INSERT INTO `question_documents` (`id`, `subject_id`, `filename`, `filepath`, `uploaded_at`) VALUES
(1, 1, 'Q1.docx', 'uploads/6811bbc108b6b_Q1.docx', '2025-04-30 05:57:21'),
(2, 1, 'soalan.docx', 'uploads/6811d77981178_soalan.docx', '2025-04-30 07:55:37'),
(3, 1, 'soalan.docx', 'uploads/6811d782b3d52_soalan.docx', '2025-04-30 07:55:46'),
(4, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68137809a484d_soalan.docx', '2025-05-01 13:32:58'),
(5, 1, 'soalan.pdf', 'C:\\wamp64\\www\\elearning/uploads/681512e4e07f2_soalan.pdf', '2025-05-02 18:45:58'),
(6, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/6815132615a4c_soalan.docx', '2025-05-02 18:47:02'),
(7, 1, 'soalan.pdf', 'C:\\wamp64\\www\\elearning/uploads/681513ad125ff_soalan.pdf', '2025-05-02 18:49:17'),
(8, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68151c229232d_soalan.docx', '2025-05-02 19:25:22'),
(9, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68151d167992d_soalan.docx', '2025-05-02 19:29:26'),
(11, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681918401c30e_soalan.docx', '2025-05-05 19:57:53'),
(12, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681921e8b1eb2_soalan.docx', '2025-05-05 20:39:04'),
(13, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/6819228636441_soalan.docx', '2025-05-05 20:41:42'),
(14, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681927ad2298a_soalan.docx', '2025-05-05 21:03:41'),
(15, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681927df16813_soalan.docx', '2025-05-05 21:04:31'),
(16, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68192efe69c6d_soalan.docx', '2025-05-05 21:34:54'),
(17, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68192f1fbd864_soalan.docx', '2025-05-05 21:35:27'),
(18, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681931d0dacd2_soalan.docx', '2025-05-05 21:46:57'),
(19, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/681931f08709c_soalan.docx', '2025-05-05 21:47:28'),
(20, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68193317050b4_soalan.docx', '2025-05-05 21:52:23'),
(21, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/68193383a07da_soalan.docx', '2025-05-05 21:54:11'),
(22, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/6819376763440_soalan.docx', '2025-05-05 22:10:47'),
(23, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/6819d2fda90d0_soalan.docx', '2025-05-06 09:14:38'),
(24, 1, 'soalan.docx', 'C:\\wamp64\\www\\elearning/uploads/6819d5a6168ba_soalan.docx', '2025-05-06 09:25:58'),
(25, 1, 'soalan.pdf', 'C:\\wamp64\\www\\elearning/uploads/681a432770b56_soalan.pdf', '2025-05-06 17:13:12'),
(26, 1, 'soalan.pdf', 'C:\\wamp64\\www\\elearning/uploads/681a46088bcce_soalan.pdf', '2025-05-06 17:25:31');

-- --------------------------------------------------------

--
-- Table structure for table `student_results`
--

DROP TABLE IF EXISTS `student_results`;
CREATE TABLE IF NOT EXISTS `student_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `matrix_number` varchar(20) NOT NULL,
  `subject_id` int NOT NULL,
  `score_percentage` decimal(5,2) NOT NULL,
  `correct_answers` int NOT NULL,
  `total_questions` int NOT NULL,
  `attempt_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matrix_number` (`matrix_number`),
  KEY `subject_id` (`subject_id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_results`
--

INSERT INTO `student_results` (`id`, `matrix_number`, `subject_id`, `score_percentage`, `correct_answers`, `total_questions`, `attempt_date`) VALUES
(13, 'D22114691', 1, 50.00, 5, 10, '2025-05-15 16:34:50'),
(12, 'D22114691', 1, 40.00, 4, 10, '2025-05-15 16:32:48'),
(11, 'D22114691', 1, 40.00, 4, 10, '2025-05-15 16:32:03'),
(10, 'D22114691', 1, 20.00, 2, 10, '2025-05-08 08:14:17');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `year` int NOT NULL,
  `semester` varchar(50) NOT NULL,
  `matrix_number` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`,`year`),
  KEY `matrix_number` (`matrix_number`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `year`, `semester`, `matrix_number`, `created_at`) VALUES
(1, 'KOM4021', 'Introduction of IT', 2025, '', 'D22114688', '2025-04-30 05:56:08'),
(8, 'KOM4021', 'Introduction of IT', 2024, '', 'D22114688', '2025-05-06 18:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `matrix_number` varchar(20) NOT NULL,
  `ic_number` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `role` enum('student','lecturer','admin') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`matrix_number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`matrix_number`, `ic_number`, `username`, `role`, `created_at`) VALUES
('D22114691', '040727070605', 'Arif Daniel', 'student', '2025-04-30 05:54:19'),
('D22114688', '040727070605', 'Harith Anaqi', 'lecturer', '2025-04-30 05:54:19'),
('Ad001', '010203', 'Admin001', 'admin', '2025-04-30 05:54:46'),
('D22119090', '040727070605', 'Aery Farhan', 'student', '2025-05-02 17:19:28');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
