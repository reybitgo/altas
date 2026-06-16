-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 15, 2026 at 05:25 AM
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

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`id`, `user_id`, `type`, `amount`, `cap_deduction`, `source_user_id`, `level`, `pairs_count`, `description`, `status`, `created_at`) VALUES
(1, 1, 'direct_referral', 0.00, 1000.00, 2, NULL, NULL, 'Direct referral blocked — lifetime cap reached', 'flushed', '2026-06-13 05:40:32'),
(2, 1, 'direct_referral', 1000.00, 1000.00, 2, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 05:40:32'),
(3, 1, 'direct_referral', 0.00, 1000.00, 2, NULL, NULL, 'Direct referral blocked — lifetime cap reached', 'flushed', '2026-06-13 05:40:32'),
(4, 2, 'direct_referral', 1000.00, 0.00, 3, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 06:22:57'),
(5, 2, 'pairing', 1500.00, 0.00, 5, NULL, 1, '1 pair(s) × ₱1,500.00', 'credited', '2026-06-13 09:38:25'),
(6, 3, 'direct_referral', 1000.00, 0.00, 5, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 09:38:25'),
(8, 2, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56'),
(9, 3, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56'),
(10, 5, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56');

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

--
-- Dumping data for table `daily_fixed_income_log`
--

INSERT INTO `daily_fixed_income_log` (`id`, `user_id`, `amount`, `day_number`, `cap_status_at_payout`, `cap_remaining`, `created_at`) VALUES
(1, 2, 33.00, 1, 'active', 27467.00, '2026-06-14 02:23:56'),
(2, 3, 33.00, 1, 'active', 28967.00, '2026-06-14 02:23:56'),
(3, 5, 33.00, 1, 'active', 29967.00, '2026-06-14 02:23:56');

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

--
-- Dumping data for table `ewallet_admin_topups`
--

INSERT INTO `ewallet_admin_topups` (`id`, `admin_id`, `recipient_id`, `amount`, `note`, `created_at`) VALUES
(1, 1, 1, 100000.00, '', '2026-06-13 05:37:50');

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

--
-- Dumping data for table `ewallet_ledger`
--

INSERT INTO `ewallet_ledger` (`id`, `user_id`, `type`, `amount`, `reference_id`, `ref_type`, `balance_after`, `note`, `created_at`) VALUES
(1, 1, 'credit', 100000.00, 1, 'topup', 100000.00, 'Admin top-up by @admin', '2026-06-13 05:37:50'),
(2, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas01', '2026-06-13 05:40:32'),
(3, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas01 (registered by @admin)', '2026-06-13 05:40:32'),
(4, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas02', '2026-06-13 06:22:57'),
(5, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas02 (registered by @admin)', '2026-06-13 06:22:57'),
(6, 2, 'credit', 1000.00, 4, 'commission', 1000.00, 'Direct referral bonus', '2026-06-13 06:22:57'),
(7, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas03', '2026-06-13 09:38:25'),
(8, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas03 (registered by @admin)', '2026-06-13 09:38:25'),
(9, 2, 'credit', 1500.00, 5, 'commission', 2500.00, 'Pairing bonus — 1 pair(s)', '2026-06-13 09:38:25'),
(10, 3, 'credit', 1000.00, 6, 'commission', 1000.00, 'Direct referral bonus', '2026-06-13 09:38:25'),
(12, 2, 'credit', 33.00, 8, 'commission', 2533.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56'),
(13, 3, 'credit', 33.00, 9, 'commission', 1033.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56'),
(14, 5, 'credit', 33.00, 10, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56');

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
  `package_pv_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT 'Percentage of entry fee that becomes package PV for binary/direct/indirect basis',
  `pairing_bonus` decimal(12,2) NOT NULL,
  `pairing_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Pairing bonus = paired_pv * (pairing_pv_pct/100) * pv_per_peso_rate',
  `daily_pair_cap` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `daily_pair_pv_cap` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Max paired PV per member per day',
  `direct_ref_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `direct_ref_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Direct referral bonus = package_pv * (direct_ref_pv_pct/100) * pv_per_peso_rate',
  `lifetime_cap_multiplier` decimal(5,2) NOT NULL DEFAULT '3.00',
  `reactivation_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reactivation_window_days` int NOT NULL DEFAULT '15',
  `daily_fixed_income` decimal(12,2) NOT NULL DEFAULT '0.00',
  `daily_fixed_income_days` int NOT NULL DEFAULT '90',
  `dfi_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Optional DFI as % of package PV; 0 falls back to daily_fixed_income',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `entry_fee`, `package_pv_rate`, `pairing_bonus`, `pairing_pv_pct`, `daily_pair_cap`, `daily_pair_pv_cap`, `direct_ref_bonus`, `direct_ref_pv_pct`, `lifetime_cap_multiplier`, `reactivation_fee`, `reactivation_window_days`, `daily_fixed_income`, `daily_fixed_income_days`, `dfi_pv_pct`, `status`, `created_at`) VALUES
(1, 'Starter', 10000.00, 100.00, 1500.00, 30.00, 5, 50000.00, 1000.00, 20.00, 3.00, 10000.00, 15, 33.00, 909, 0.00, 'active', '2026-05-30 13:32:05'),
(2, 'Test Package', 10000.00, 100.00, 2000.00, 40.00, 3, 30000.00, 1000.00, 20.00, 3.00, 0.00, 15, 0.00, 90, 0.00, 'active', '2026-06-13 05:26:29');

-- --------------------------------------------------------

--
-- Table structure for table `package_indirect_levels`
--

CREATE TABLE `package_indirect_levels` (
  `id` int UNSIGNED NOT NULL,
  `package_id` int UNSIGNED NOT NULL,
  `level` tinyint UNSIGNED NOT NULL,
  `bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Indirect level bonus = package_pv * (pv_pct/100) * pv_per_peso_rate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_indirect_levels`
--

INSERT INTO `package_indirect_levels` (`id`, `package_id`, `level`, `bonus`, `pv_pct`) VALUES
(11, 1, 1, 0.00, 0.00),
(12, 1, 2, 0.00, 0.00),
(13, 1, 3, 0.00, 0.00),
(14, 1, 4, 0.00, 0.00),
(15, 1, 5, 0.00, 0.00),
(16, 1, 6, 0.00, 0.00),
(17, 1, 7, 0.00, 0.00),
(18, 1, 8, 0.00, 0.00),
(19, 1, 9, 0.00, 0.00),
(20, 1, 10, 0.00, 0.00),
(31, 2, 1, 0.00, 0.00),
(32, 2, 2, 0.00, 0.00),
(33, 2, 3, 0.00, 0.00),
(34, 2, 4, 0.00, 0.00),
(35, 2, 5, 0.00, 0.00),
(36, 2, 6, 0.00, 0.00),
(37, 2, 7, 0.00, 0.00),
(38, 2, 8, 0.00, 0.00),
(39, 2, 9, 0.00, 0.00),
(40, 2, 10, 0.00, 0.00);

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
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `pv_value` decimal(14,2) NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pv_transactions`
--

CREATE TABLE `pv_transactions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Member whose PV balance is affected',
  `type` enum('package_personal','package_group','product_personal','product_group','binary_left','binary_right','binary_paired','binary_flushed') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of PV movement',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'PV amount added (always positive)',
  `source_user_id` int UNSIGNED DEFAULT NULL COMMENT 'The member whose action generated this PV',
  `source_type` enum('registration','activation','repeat_purchase') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pv_transactions`
--

INSERT INTO `pv_transactions` (`id`, `user_id`, `type`, `amount`, `source_user_id`, `source_type`, `created_at`) VALUES
(4, 3, 'package_personal', 10000.00, 5, 'registration', '2026-06-13 09:38:25');

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
-- Table structure for table `repeat_purchases`
--

CREATE TABLE `repeat_purchases` (
  `id` int UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `total_pv` decimal(14,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('last_reset', '2026-06-14 10:23:56', '2026-06-14 02:23:56'),
('maintenance_bypass_token', '', '2026-06-05 15:26:25'),
('maintenance_mode', '0', '2026-06-05 06:54:41'),
('maya_enabled', '1', '2026-05-30 13:32:05'),
('maya_number', '09281234567', '2026-05-30 13:33:17'),
('min_payout', '500', '2026-05-30 13:32:05'),
('personal_pv_requirement', '0.0000', '2026-06-14 06:41:52'),
('pv_per_peso_rate', '0.5000', '2026-06-13 05:34:35'),
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
  `left_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Accumulated PV on left binary leg',
  `right_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Accumulated PV on right binary leg',
  `paired_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Total paired PV lifetime',
  `flushed_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'PV that has been flushed in pairing',
  `personal_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Personal sales PV (package + product)',
  `group_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Group sales PV from downline',
  `pairs_paid` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_flushed` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_paid_today` int UNSIGNED NOT NULL DEFAULT '0',
  `paired_pv_today` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Paired PV today (midnight reset)',
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

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `package_id`, `reg_code_id`, `reg_payment_method`, `reg_paid_by`, `sponsor_id`, `binary_parent_id`, `binary_position`, `left_count`, `right_count`, `left_pv`, `right_pv`, `paired_pv`, `flushed_pv`, `personal_pv`, `group_pv`, `pairs_paid`, `pairs_flushed`, `pairs_paid_today`, `paired_pv_today`, `lifetime_earned`, `cap_status`, `capping_bypass`, `daily_cap_bypass`, `capped_at`, `last_reactivation_at`, `dfi_days_used`, `dfi_active`, `full_name`, `email`, `mobile`, `gcash_number`, `maya_number`, `usdt_address`, `address`, `photo`, `ewallet_balance`, `withdrawable_balance`, `status`, `joined_at`, `last_login`, `cd_active`, `ewallet_sent_today`, `ewallet_sent_this_week`) VALUES
(1, 'admin', '$2y$12$h3j0mO9NbtMyLg6EsC4M6eGy6buk0zanOgPmFBIgaI8V5/CUbaYqq', 'admin', NULL, NULL, 'code', NULL, NULL, NULL, NULL, 3, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 10000.00, 0, 0, 0, 0.00, 0.00, 'capped', 0, 0, '2026-06-13 05:40:32', NULL, 0, 0, 'System Administrator', 'admin@mlm.local', NULL, NULL, NULL, NULL, NULL, NULL, 100000.00, 30000.00, 'active', '2026-05-30 13:32:05', '2026-06-13 05:10:07', 0, 0.00, 0.00),
(2, 'altas01', '$2y$12$Z91qLZ4783O.mc4scD9eIOiVbOkf0moK1XuQhFVBpw.VL5.QLMpXm', 'member', 1, NULL, 'ewallet', 1, 1, 1, 'left', 1, 1, 10000.00, 10000.00, 10000.00, 0.00, 0.00, 10000.00, 1, 0, 0, 0.00, 2533.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2533.00, 2533.00, 'active', '2026-06-13 05:40:32', '2026-06-13 06:23:31', 0, 0.00, 0.00),
(3, 'altas02', '$2y$12$U1m53EQ.ar7xxk.XmlUZ1efgeUViylsMggd3nB8BTUggGuYSzNIpy', 'member', 1, NULL, 'ewallet', 1, 2, 2, 'left', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 10000.00, 0, 0, 0, 0.00, 1033.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1033.00, 1033.00, 'active', '2026-06-13 06:22:57', NULL, 0, 0.00, 0.00),
(5, 'altas03', '$2y$12$LNW8SOE8n0AQLIBPKMk7eOgG9UajuK9t7/wF15GrRehjl6UrVRkbW', 'member', 1, NULL, 'ewallet', 1, 3, 2, 'right', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 33.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 33.00, 33.00, 'active', '2026-06-13 09:38:25', '2026-06-13 09:42:35', 0, 0.00, 0.00),
(6, 'altas04', '$2y$12$IlC2KqEPVY9uNlPgyEKwy.klgmb9icMWuE4L7pmaaGswhKrBPqfw.', 'member', NULL, NULL, 'pending', NULL, 5, 5, 'left', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 0.00, 'active', 0, 0, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 'pending', '2026-06-13 09:45:16', '2026-06-13 09:45:16', 0, 0.00, 0.00);

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
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`type`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `source_user_id` (`source_user_id`);

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
-- Indexes for table `repeat_purchases`
--
ALTER TABLE `repeat_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `approved_by` (`approved_by`);

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
-- AUTO_INCREMENT for table `repeat_purchases`
--
ALTER TABLE `repeat_purchases`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Constraints for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  ADD CONSTRAINT `pv_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pv_transactions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `repeat_purchases`
--
ALTER TABLE `repeat_purchases`
  ADD CONSTRAINT `repeat_purchases_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repeat_purchases_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `repeat_purchases_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
