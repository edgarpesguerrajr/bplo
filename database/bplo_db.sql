-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 01:24 AM
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
-- Database: `bplo_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `franchise_no` varchar(100) NOT NULL,
  `franchisee_first_name` varchar(100) NOT NULL,
  `franchisee_middle_name` varchar(100) DEFAULT NULL,
  `franchisee_last_name` varchar(100) NOT NULL,
  `franchisee_ext_name` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `make` varchar(100) DEFAULT NULL,
  `year_model` varchar(10) DEFAULT NULL,
  `motor_no` varchar(100) DEFAULT NULL,
  `plate_no` varchar(50) DEFAULT NULL,
  `driver_first_name` varchar(100) DEFAULT NULL,
  `driver_middle_name` varchar(100) DEFAULT NULL,
  `driver_last_name` varchar(100) DEFAULT NULL,
  `driver_ext_name` varchar(10) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `registration_no` varchar(50) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `franchisee_fee` decimal(10,2) DEFAULT 0.00,
  `sticker_fee` decimal(10,2) DEFAULT 0.00,
  `filing_fee` decimal(10,2) DEFAULT 0.00,
  `penalty_fee` decimal(10,2) DEFAULT 0.00,
  `transfer_fee` decimal(10,2) DEFAULT 0.00,
  `plate_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `or_no` varchar(100) DEFAULT NULL,
  `or_date` date DEFAULT NULL,
  `ctc_no` varchar(100) DEFAULT NULL,
  `ctc_date` date DEFAULT NULL,
  `sticker_no` varchar(50) DEFAULT NULL,
  `toda_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_drivers`
--

CREATE TABLE `document_drivers` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `driver_first_name` varchar(100) DEFAULT NULL,
  `driver_middle_name` varchar(100) DEFAULT NULL,
  `driver_last_name` varchar(100) DEFAULT NULL,
  `driver_ext_name` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_history`
--

CREATE TABLE `document_history` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `franchise_no` varchar(100) DEFAULT NULL,
  `franchisee_first_name` varchar(100) DEFAULT NULL,
  `franchisee_middle_name` varchar(100) DEFAULT NULL,
  `franchisee_last_name` varchar(100) DEFAULT NULL,
  `franchisee_ext_name` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `make` varchar(100) DEFAULT NULL,
  `year_model` varchar(10) DEFAULT NULL,
  `motor_no` varchar(100) DEFAULT NULL,
  `plate_no` varchar(50) DEFAULT NULL,
  `driver_first_name` varchar(100) DEFAULT NULL,
  `driver_middle_name` varchar(100) DEFAULT NULL,
  `driver_last_name` varchar(100) DEFAULT NULL,
  `driver_ext_name` varchar(10) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `registration_no` varchar(100) DEFAULT NULL,
  `sticker_no` varchar(100) DEFAULT NULL,
  `toda_no` varchar(100) DEFAULT NULL,
  `franchisee_fee` decimal(10,2) DEFAULT 0.00,
  `sticker_fee` decimal(10,2) DEFAULT 0.00,
  `filing_fee` decimal(10,2) DEFAULT 0.00,
  `penalty_fee` decimal(10,2) DEFAULT 0.00,
  `transfer_fee` decimal(10,2) DEFAULT 0.00,
  `plate_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `or_no` varchar(100) DEFAULT NULL,
  `or_date` date DEFAULT NULL,
  `ctc_no` varchar(100) DEFAULT NULL,
  `ctc_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('employee','admin') NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `verified`) VALUES
(1, 'Super Admin', 'admin@admin.com', '$2y$10$pIwGr3KSFk6.YE6ZwEQIn.9OQKqOIQGkmfK9eV.PJj4TSBH9DPc4G', 'admin', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `franchise_no` (`franchise_no`);

--
-- Indexes for table `document_drivers`
--
ALTER TABLE `document_drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_id` (`document_id`);

--
-- Indexes for table `document_history`
--
ALTER TABLE `document_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `changed_at` (`changed_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_drivers`
--
ALTER TABLE `document_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_history`
--
ALTER TABLE `document_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document_drivers`
--
ALTER TABLE `document_drivers`
  ADD CONSTRAINT `fk_document_drivers_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_history`
--
ALTER TABLE `document_history`
  ADD CONSTRAINT `document_history_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
