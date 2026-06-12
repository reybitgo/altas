-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 12, 2026 at 12:19 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u938213108_altas_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cd_ledger`
--

CREATE TABLE `cd_ledger` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `cd_status_id` int NOT NULL,
  `commission_id` int DEFAULT NULL,
  `type` enum('pairing','direct_referral','indirect_referral') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL,
  `cd_amount` decimal(12,2) NOT NULL,
  `withdrawable_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` enum('pairing','direct_referral','indirect_referral','daily_fixed_income') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cap_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_user_id` int UNSIGNED DEFAULT NULL,
  `level` tinyint UNSIGNED DEFAULT NULL,
  `pairs_count` tinyint UNSIGNED DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('credited','flushed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credited',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_fixed_income_log`
--

CREATE TABLE `daily_fixed_income_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `day_number` int UNSIGNED NOT NULL,
  `cap_status_at_payout` enum('active','capped','perminact') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `cap_remaining` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_admin_topups`
--

CREATE TABLE `ewallet_admin_topups` (
  `id` int UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED NOT NULL,
  `recipient_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_ledger`
--

CREATE TABLE `ewallet_ledger` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_id` int UNSIGNED DEFAULT NULL,
  `ref_type` enum('commission','payout','reactivation','transfer','topup','registration') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_after` decimal(14,2) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_transfers`
--

CREATE TABLE `ewallet_transfers` (
  `id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `recipient_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL,
  `status` enum('completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_fee` decimal(12,2) NOT NULL,
  `pairing_bonus` decimal(12,2) NOT NULL,
  `daily_pair_cap` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `direct_ref_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lifetime_cap_multiplier` decimal(5,2) NOT NULL DEFAULT '3.00',
  `reactivation_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reactivation_window_days` int NOT NULL DEFAULT '15',
  `daily_fixed_income` decimal(12,2) NOT NULL DEFAULT '0.00',
  `daily_fixed_income_days` int NOT NULL DEFAULT '90',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `entry_fee`, `pairing_bonus`, `daily_pair_cap`, `direct_ref_bonus`, `lifetime_cap_multiplier`, `reactivation_fee`, `reactivation_window_days`, `daily_fixed_income`, `daily_fixed_income_days`, `status`, `created_at`) VALUES
(1, 'Starter', 10000.00, 1500.00, 5, 1000.00, 3.00, 10000.00, 15, 33.00, 909, 'active', '2026-05-30 13:32:05');

-- --------------------------------------------------------

--
-- Table structure for table `package_indirect_levels`
--

CREATE TABLE `package_indirect_levels` (
  `id` int UNSIGNED NOT NULL,
  `package_id` int UNSIGNED NOT NULL,
  `level` tinyint UNSIGNED NOT NULL,
  `bonus` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_indirect_levels`
--

INSERT INTO `package_indirect_levels` (`id`, `package_id`, `level`, `bonus`) VALUES
(11, 1, 1, 0.00),
(12, 1, 2, 0.00),
(13, 1, 3, 0.00),
(14, 1, 4, 0.00),
(15, 1, 5, 0.00),
(16, 1, 6, 0.00),
(17, 1, 7, 0.00),
(18, 1, 8, 0.00),
(19, 1, 9, 0.00),
(20, 1, 10, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

CREATE TABLE `payout_requests` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payout_method` enum('gcash','maya','usdt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gcash',
  `payout_account` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `service_fee_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `service_fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `usdt_rate` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `usdt_gas_fee` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `usdt_amount` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `processed_by` int UNSIGNED DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reactivations`
--

CREATE TABLE `reactivations` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `previous_earned` decimal(14,2) NOT NULL DEFAULT '0.00',
  `package_id` int UNSIGNED NOT NULL,
  `payment_method` enum('ewallet','gcash','maya','usdt','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ewallet',
  `status` enum('pending','completed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `proof_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_by` int UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reg_codes`
--

CREATE TABLE `reg_codes` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_id` int UNSIGNED NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `status` enum('unused','used','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `used_by` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_cd` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reg_codes`
--

INSERT INTO `reg_codes` (`id`, `code`, `package_id`, `price`, `status`, `used_by`, `created_by`, `used_at`, `expires_at`, `created_at`, `is_cd`) VALUES
(1, 'L3B6-KSF8-MG3S', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(2, 'Q3EW-PT5W-TYTC', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(3, 'RF9L-29LT-TC3A', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(4, '7VTQ-55KZ-YAMW', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(5, 'UWXJ-XZAA-3WZW', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key_name`, `value`, `updated_at`) VALUES
('binary_enabled', '1', '2026-06-09 04:59:56'),
('contact_email', 'support@altasfarm.com', '2026-05-30 13:32:05'),
('default_cap_multiplier', '3.00', '2026-05-30 13:32:05'),
('dfi_enabled', '1', '2026-05-30 13:32:05'),
('ewallet_min_transfer', '50.00', '2026-05-30 13:32:05'),
('ewallet_transfer_daily_limit', '5000', '2026-06-05 15:26:25'),
('ewallet_transfer_fee', '5.00', '2026-06-02 10:46:18'),
('ewallet_transfer_weekly_limit', '20000', '2026-06-05 15:26:25'),
('gcash_enabled', '1', '2026-05-30 13:32:05'),
('gcash_number', '09171234567', '2026-05-30 13:33:17'),
('indirect_referral_enabled', '1', '2026-06-09 05:00:02'),
('last_reset', '', '2026-06-06 10:41:40'),
('maintenance_bypass_token', '', '2026-06-05 15:26:25'),
('maintenance_mode', '0', '2026-06-05 06:54:41'),
('maya_enabled', '1', '2026-05-30 13:32:05'),
('maya_number', '09281234567', '2026-05-30 13:33:17'),
('min_payout', '500', '2026-05-30 13:32:05'),
('reactivation_ewallet_enabled', '1', '2026-05-30 13:32:05'),
('reactivation_external_enabled', '1', '2026-05-30 13:32:05'),
('seat_limit', '2000', '2026-05-30 13:33:17'),
('service_fee_gcash', '0', '2026-05-30 13:32:05'),
('service_fee_maya', '0', '2026-05-30 13:32:05'),
('service_fee_usdt', '5', '2026-05-30 13:32:05'),
('site_name', 'Altas Farm', '2026-05-30 13:32:05'),
('site_tagline', 'Build Your Network. Grow Your Income.', '2026-05-30 13:32:05'),
('usdt_address', 'TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ', '2026-05-30 13:33:17'),
('usdt_gas_fee', '0.7801', '2026-06-04 05:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `package_id` int UNSIGNED DEFAULT NULL,
  `reg_code_id` int UNSIGNED DEFAULT NULL,
  `reg_payment_method` enum('code','ewallet','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'code',
  `reg_paid_by` int UNSIGNED DEFAULT NULL,
  `sponsor_id` int UNSIGNED DEFAULT NULL,
  `binary_parent_id` int UNSIGNED DEFAULT NULL,
  `binary_position` enum('left','right') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `left_count` int UNSIGNED NOT NULL DEFAULT '0',
  `right_count` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_paid` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_flushed` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_paid_today` int UNSIGNED NOT NULL DEFAULT '0',
  `lifetime_earned` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cap_status` enum('active','capped','perminact') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `capping_bypass` tinyint(1) NOT NULL DEFAULT '0',
  `daily_cap_bypass` tinyint(1) NOT NULL DEFAULT '0',
  `capped_at` timestamp NULL DEFAULT NULL,
  `last_reactivation_at` timestamp NULL DEFAULT NULL,
  `dfi_days_used` int UNSIGNED NOT NULL DEFAULT '0',
  `dfi_active` tinyint(1) NOT NULL DEFAULT '1',
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gcash_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maya_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usdt_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ewallet_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `withdrawable_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','suspended','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `cd_active` tinyint(1) NOT NULL DEFAULT '0',
  `ewallet_sent_today` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ewallet_sent_this_week` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `package_id`, `reg_code_id`, `reg_payment_method`, `reg_paid_by`, `sponsor_id`, `binary_parent_id`, `binary_position`, `left_count`, `right_count`, `pairs_paid`, `pairs_flushed`, `pairs_paid_today`, `lifetime_earned`, `cap_status`, `capping_bypass`, `daily_cap_bypass`, `capped_at`, `last_reactivation_at`, `dfi_days_used`, `dfi_active`, `full_name`, `email`, `mobile`, `gcash_number`, `maya_number`, `usdt_address`, `address`, `photo`, `ewallet_balance`, `withdrawable_balance`, `status`, `joined_at`, `last_login`, `cd_active`, `ewallet_sent_today`, `ewallet_sent_this_week`) VALUES
(1, 'admin', '$2y$12$h3j0mO9NbtMyLg6EsC4M6eGy6buk0zanOgPmFBIgaI8V5/CUbaYqq', 'admin', NULL, NULL, 'code', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 'active', 0, 0, NULL, NULL, 0, 1, 'System Administrator', 'admin@mlm.local', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 'active', '2026-05-30 13:32:05', NULL, 0, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `user_cd_status`
--

CREATE TABLE `user_cd_status` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `target_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `filled_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_by` int NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cd_ledger`
--
ALTER TABLE `cd_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_cd` (`user_id`,`cd_status_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`type`,`created_at`),
  ADD KEY `idx_source` (`source_user_id`),
  ADD KEY `idx_status` (`status`,`created_at`);

--
-- Indexes for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_day` (`user_id`,`day_number`),
  ADD KEY `idx_dfi_user_date` (`user_id`,`created_at`);

--
-- Indexes for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`,`created_at`);

--
-- Indexes for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pkg_level` (`package_id`,`level`);

--
-- Indexes for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_status` (`status`,`requested_at`);

--
-- Indexes for table `reactivations`
--
ALTER TABLE `reactivations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `idx_react_user` (`user_id`,`created_at`),
  ADD KEY `idx_react_status` (`status`,`created_at`),
  ADD KEY `fk_reactivations_processed_by` (`processed_by`);

--
-- Indexes for table `reg_codes`
--
ALTER TABLE `reg_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `used_by` (`used_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `idx_sponsor` (`sponsor_id`),
  ADD KEY `idx_binary_parent` (`binary_parent_id`,`binary_position`),
  ADD KEY `idx_role_status` (`role`,`status`),
  ADD KEY `idx_cap_status` (`cap_status`,`capped_at`),
  ADD KEY `idx_dfi_active` (`dfi_active`,`dfi_days_used`),
  ADD KEY `idx_reg_code` (`reg_code_id`);

--
-- Indexes for table `user_cd_status`
--
ALTER TABLE `user_cd_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_active` (`user_id`,`status`),
  ADD KEY `idx_assigned_at` (`assigned_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cd_ledger`
--
ALTER TABLE `cd_ledger`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reactivations`
--
ALTER TABLE `reactivations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reg_codes`
--
ALTER TABLE `reg_codes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_cd_status`
--
ALTER TABLE `user_cd_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  ADD CONSTRAINT `daily_fixed_income_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  ADD CONSTRAINT `ewallet_admin_topups_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ewallet_admin_topups_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  ADD CONSTRAINT `ewallet_ledger_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  ADD CONSTRAINT `ewallet_transfers_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ewallet_transfers_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  ADD CONSTRAINT `package_indirect_levels_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD CONSTRAINT `payout_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payout_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reactivations`
--
ALTER TABLE `reactivations`
  ADD CONSTRAINT `reactivations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reactivations_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `reactivations_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reg_codes`
--
ALTER TABLE `reg_codes`
  ADD CONSTRAINT `reg_codes_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `reg_codes_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reg_codes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`binary_parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_4` FOREIGN KEY (`reg_code_id`) REFERENCES `reg_codes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
