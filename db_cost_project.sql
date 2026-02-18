-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2026 at 07:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_cost_project`
--

-- --------------------------------------------------------

--
-- Table structure for table `cost_groups`
--

CREATE TABLE `cost_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `created_by_role` enum('admin','karyawan') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cost_groups`
--

INSERT INTO `cost_groups` (`id`, `group_name`, `created_by_role`) VALUES
(1, 'Transport', 'admin'),
(5, 'Hotel & Meal', 'admin'),
(6, 'Alat Berat', 'admin'),
(7, 'Sparepart', 'admin'),
(8, 'Mobilisasi', 'admin'),
(11, 'Makan', 'admin'),
(12, 'Jajanan', 'admin'),
(13, 'Uang Makan', 'admin'),
(14, 'Laundry', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `cost_parameters`
--

CREATE TABLE `cost_parameters` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `parameter_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cost_parameters`
--

INSERT INTO `cost_parameters` (`id`, `group_id`, `parameter_name`) VALUES
(1, 1, 'Tiket Pesawat'),
(25, 5, 'Uang Makan'),
(31, 5, 'Hotel'),
(32, 5, 'laundry'),
(34, 1, 'Tiket Speedboat'),
(35, 1, 'Taxi'),
(36, 1, 'Toll & Parkir'),
(37, 6, 'Pompa'),
(38, 6, 'Lowboy Trailer'),
(39, 6, 'Crane 50T'),
(40, 6, 'Mob - Demob Crane 50T'),
(41, 6, 'Crane 25T'),
(42, 6, 'Mob - Demob Crane 25T'),
(43, 7, 'Lubricator O-ring'),
(49, 7, 'Flow Cup Rubber'),
(50, 7, 'H.Temp Grease'),
(51, 7, 'BOP O-ring'),
(52, 7, 'Coverall'),
(53, 7, 'Contingency'),
(54, 8, 'Wireline Unit'),
(57, 8, 'Pompa'),
(58, 8, 'Crane'),
(59, 8, 'Lowbed'),
(60, 8, 'Scaffolding'),
(61, 8, 'Others'),
(67, 11, 'Makan Siang'),
(68, 12, 'Jajanan'),
(69, 13, 'Uang Makan'),
(70, 14, 'Laundry'),
(72, 11, 'Makan Pagi Crew');

-- --------------------------------------------------------

--
-- Table structure for table `parameter_fields`
--

CREATE TABLE `parameter_fields` (
  `id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `field_label` varchar(100) NOT NULL,
  `field_type` enum('text','number') NOT NULL,
  `field_role` enum('general','multiplier','price','extra') DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parameter_fields`
--

INSERT INTO `parameter_fields` (`id`, `parameter_id`, `field_label`, `field_type`, `field_role`) VALUES
(1, 1, 'Deskripsi', 'text', 'general'),
(4, 1, 'Qty', 'number', 'general'),
(5, 1, 'Harga', 'number', 'general'),
(81, 25, 'Qty', 'number', 'general'),
(82, 25, 'Harga', 'number', 'general'),
(83, 25, 'Durasi', 'number', 'general'),
(84, 31, 'Qty', 'number', 'general'),
(85, 31, 'Harga', 'number', 'general'),
(86, 31, 'Durasi', 'number', 'general'),
(87, 32, 'Qty', 'number', 'general'),
(88, 32, 'Harga', 'number', 'general'),
(89, 32, 'Durasi', 'number', 'general'),
(93, 34, 'Deskripsi', 'text', 'general'),
(94, 34, 'Qty', 'number', 'general'),
(95, 34, 'Harga', 'number', 'general'),
(96, 35, 'Deskripsi', 'text', 'general'),
(97, 35, 'Qty', 'number', 'general'),
(98, 35, 'Harga', 'number', 'general'),
(99, 36, 'Deskripsi', 'text', 'general'),
(100, 36, 'Qty', 'number', 'general'),
(101, 36, 'Harga', 'number', 'general'),
(102, 37, 'Qty', 'number', 'general'),
(103, 37, 'Harga', 'number', 'general'),
(104, 37, 'Durasi', 'number', 'general'),
(105, 38, 'Qty', 'number', 'general'),
(106, 38, 'Harga', 'number', 'general'),
(107, 38, 'Durasi', 'number', 'general'),
(108, 39, 'Qty', 'number', 'general'),
(109, 39, 'Harga', 'number', 'general'),
(110, 39, 'Durasi', 'number', 'general'),
(111, 40, 'Qty Mobilisasi', 'number', 'general'),
(112, 40, 'Harga Mobilisasi', 'number', 'general'),
(114, 40, 'Qty Demob', 'number', 'general'),
(115, 40, 'Harga Demob', 'number', 'general'),
(116, 41, 'Qty', 'number', 'general'),
(117, 41, 'Harga', 'number', 'general'),
(118, 41, 'Durasi', 'number', 'general'),
(119, 42, 'Qty Mobilisasi', 'number', 'general'),
(120, 42, 'Harga Mobilisasi', 'number', 'general'),
(121, 42, 'Qty Demob', 'number', 'general'),
(122, 42, 'Harga Demob', 'number', 'general'),
(123, 43, 'Qty', 'number', 'general'),
(124, 43, 'Harga', 'number', 'general'),
(125, 49, 'Qty', 'number', 'general'),
(126, 49, 'Harga', 'number', 'general'),
(127, 50, 'Qty', 'number', 'general'),
(128, 50, 'Harga', 'number', 'general'),
(129, 51, 'Qty', 'number', 'general'),
(130, 51, 'Harga', 'number', 'general'),
(131, 52, 'Qty', 'number', 'general'),
(132, 52, 'Harga', 'number', 'general'),
(133, 53, 'Qty', 'number', 'general'),
(134, 53, 'Harga', 'number', 'general'),
(135, 54, 'Qty', 'number', 'general'),
(136, 54, 'Harga', 'number', 'general'),
(137, 54, 'Shipper / Nama Logistik', 'text', 'general'),
(138, 57, 'Qty', 'number', 'general'),
(139, 57, 'Harga', 'number', 'general'),
(140, 57, 'Shipper / Nama Logistik', 'text', 'general'),
(141, 58, 'Qty', 'number', 'general'),
(142, 58, 'Harga', 'number', 'general'),
(143, 58, 'Shipper / Nama Logistik', 'text', 'general'),
(144, 59, 'Qty', 'number', 'general'),
(145, 59, 'Harga', 'number', 'general'),
(146, 59, 'Shipper / Nama Logistik', 'text', 'general'),
(147, 60, 'Qty', 'number', 'general'),
(148, 60, 'Harga', 'number', 'general'),
(149, 60, 'Shipper / Nama Logistik', 'text', 'general'),
(150, 61, 'Qty', 'number', 'general'),
(151, 61, 'Harga', 'number', 'general'),
(152, 61, 'Shipper / Nama Logistik', 'text', 'general'),
(159, 67, 'Lauk', 'text', 'general'),
(160, 67, 'Harga Lauk', 'number', 'price'),
(161, 68, 'Jajanan', 'text', 'price'),
(162, 69, 'uang makan', 'text', 'price'),
(163, 70, 'Laundry', 'number', 'general'),
(165, 72, 'Makan Pagi Crew', 'text', 'price');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(150) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in days',
  `contract_number` varchar(100) DEFAULT NULL,
  `pic_sabs` varchar(100) DEFAULT NULL,
  `pic_customer` varchar(100) DEFAULT NULL,
  `contract_doc` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `customer_name`, `location`, `duration`, `contract_number`, `pic_sabs`, `pic_customer`, `contract_doc`, `created_at`) VALUES
(1, 'hamiding', 'starenergy', 'hamiding', 90, '1234', 'ayub', 'merry', '', '2026-01-13 08:59:53');

-- --------------------------------------------------------

--
-- Table structure for table `project_costs`
--

CREATE TABLE `project_costs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `dynamic_values` text DEFAULT NULL COMMENT 'Simpan JSON',
  `total_cost` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_costs`
--

INSERT INTO `project_costs` (`id`, `project_id`, `parameter_id`, `dynamic_values`, `total_cost`, `created_at`) VALUES
(9, 1, 1, '{\"Deskripsi\":\"jakarta - tobelo\",\"Qty\":\"4\",\"Harga\":\"2500000\"}', 10000000.00, '2026-01-13 23:07:48'),
(10, 1, 1, '{\"Deskripsi\":\"tobelo - jakarta\",\"Qty\":\"4\",\"Harga\":\"2000000\"}', 8000000.00, '2026-01-13 23:08:15'),
(24, 1, 68, '{\"Jajanan\":\"\"}', 0.00, '2026-01-27 05:32:26'),
(25, 1, 69, '{\"uang makan\":\"\"}', 0.00, '2026-01-27 05:32:39'),
(26, 1, 70, '{\"Laundry\":\"\"}', 0.00, '2026-01-27 05:34:15'),
(28, 1, 67, '{\"Lauk\":\"\",\"Harga Lauk\":\"\"}', 0.00, '2026-01-27 06:09:39'),
(29, 1, 72, '{\"Makan Pagi Crew\":\"\"}', 0.00, '2026-01-27 06:12:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','karyawan') DEFAULT 'karyawan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `role`) VALUES
(2, 'admin', '$2y$10$op5NPac90yaF3xvO6sqXmeyTgW7BRYT0RgqnOQbz71TWfzzWh95k6', '2026-01-13 08:53:10', 'admin'),
(3, 'mario', '$2y$10$1rAbb.XIxkNRSd9TXTqGL.leVHZQWZe.a8JlsuWEAIjP9vW0W2oOi', '2026-01-13 08:55:45', 'admin'),
(4, 'nuri', '$2y$10$3HeqPb0/TTsX/W8SD/OWeez8hTLWJLuaZ0PjEfdFZwbpoNEqNkiqO', '2026-02-10 04:29:26', 'karyawan'),
(5, 'ica', '$2y$10$0l2.rSNyXu8GXTcu8Q9DFu2WjJnSNNfl2jHPaLV/GfwF/qZiHGiJW', '2026-02-10 04:52:47', 'karyawan');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cost_groups`
--
ALTER TABLE `cost_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cost_parameters`
--
ALTER TABLE `cost_parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `parameter_fields`
--
ALTER TABLE `parameter_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parameter_id` (`parameter_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_costs`
--
ALTER TABLE `project_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `parameter_id` (`parameter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cost_groups`
--
ALTER TABLE `cost_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `cost_parameters`
--
ALTER TABLE `cost_parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `parameter_fields`
--
ALTER TABLE `parameter_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_costs`
--
ALTER TABLE `project_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cost_parameters`
--
ALTER TABLE `cost_parameters`
  ADD CONSTRAINT `cost_parameters_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `cost_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parameter_fields`
--
ALTER TABLE `parameter_fields`
  ADD CONSTRAINT `parameter_fields_ibfk_1` FOREIGN KEY (`parameter_id`) REFERENCES `cost_parameters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_costs`
--
ALTER TABLE `project_costs`
  ADD CONSTRAINT `project_costs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_costs_ibfk_2` FOREIGN KEY (`parameter_id`) REFERENCES `cost_parameters` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
