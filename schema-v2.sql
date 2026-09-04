-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2026 at 03:46 PM
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
-- Database: `payment_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_approvals`
--

CREATE TABLE `admin_approvals` (
  `id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actioned_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `target_user_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-08-31 12:04:18'),
(2, 2, 'ENABLE_MFA', 2, 'User activated Google Authenticator MFA', '::1', '2026-08-31 13:40:57'),
(3, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 15:41:22'),
(4, 2, 'EXPORT_CSV_REPORT', NULL, 'Exported financial transactions to CSV. Scope: ALL_RECORDS', '::1', '2026-09-02 16:43:27'),
(5, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 16:43:51'),
(6, 2, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 4 (Yessss) of amount 500 USD [Type: income].', '::1', '2026-09-02 16:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('KHR','USD') NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `description`, `amount`, `currency`, `type`, `category`, `is_deleted`, `date`, `created_at`) VALUES
(1, 2, 'Meat loaf', 3000.00, 'KHR', 'expense', 'ម្ហូបអាហារ', 0, '2026-08-20 09:12:00', '2026-09-02 15:45:48'),
(2, 2, 'Opening Salary', 1500.00, 'USD', 'income', 'ប្រាក់ខែ', 0, '2026-08-28 08:00:00', '2026-09-02 15:45:48'),
(3, 2, 'Office Supplies', 45000.00, 'KHR', 'expense', 'សម្ភារៈការិយាល័យ', 0, '2026-08-29 14:30:00', '2026-09-02 15:45:48'),
(4, 2, 'Yessss', 500.00, 'USD', 'income', 'ម្ហូបអាហារ', 0, '2026-09-02 23:43:00', '2026-09-02 16:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_history`
--

CREATE TABLE `transaction_history` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_description` varchar(255) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `original_currency` enum('KHR','USD') NOT NULL,
  `original_type` enum('income','expense') NOT NULL,
  `original_category` varchar(100) NOT NULL,
  `new_description` varchar(255) DEFAULT NULL,
  `new_amount` decimal(15,2) DEFAULT NULL,
  `new_currency` enum('KHR','USD') DEFAULT NULL,
  `new_type` enum('income','expense') DEFAULT NULL,
  `new_category` varchar(100) DEFAULT NULL,
  `action_type` enum('UPDATE','DELETE') NOT NULL,
  `actioned_by` int(11) NOT NULL,
  `actioned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'active',
  `mfa_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `status`, `mfa_secret`, `created_at`) VALUES
(1, 'superadmin_cambodia', '$2y$10$l3xzH43TkC4vAEgzkTOEQ.hLNjiBWSrjwgyDVDjsU9rjWIoYe5w7O', 'super_admin', 'active', NULL, '2026-09-04 13:32:21'),
(2, 'admin_sophors', '$2y$10$l3xzH43TkC4vAEgzkTOEQ.hLNjiBWSrjwgyDVDjsU9rjWIoYe5w7O', 'admin', 'active', NULL, '2026-09-04 13:32:21'),
(3, 'khmer_user1', '$2y$10$l3xzH43TkC4vAEgzkTOEQ.hLNjiBWSrjwgyDVDjsU9rjWIoYe5w7O', 'user', 'active', NULL, '2026-09-04 13:32:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `target_user_id` (`target_user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_user_id` (`target_user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `actioned_by` (`actioned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transaction_history`
--
ALTER TABLE `transaction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD CONSTRAINT `admin_approvals_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_approvals_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_approvals_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD CONSTRAINT `transaction_history_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_history_ibfk_3` FOREIGN KEY (`actioned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
