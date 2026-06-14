-- ============================================================
--  MLM BINARY SYSTEM — FULL SCHEMA + SEED DATA (v2)
--  Run once: mysql -u root -p DATABASE < install.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS u938213108_altas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u938213108_altas_db;

-- ─── PACKAGES ────────────────────────────────────────────────
CREATE TABLE packages (
  id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                      VARCHAR(80)      NOT NULL,
  entry_fee                 DECIMAL(12,2)    NOT NULL,
  package_pv_rate           DECIMAL(5,2)     NOT NULL DEFAULT 100.00 COMMENT 'Percentage of entry fee that becomes package PV for binary/direct/indirect basis',
  -- Legacy count-based fields (kept for reference)
  pairing_bonus             DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  daily_pair_cap            TINYINT UNSIGNED NOT NULL DEFAULT 3,
  -- v3: PV-based pairing
  pairing_pv_pct            DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT 'Pairing bonus = paired_pv * (pairing_pv_pct/100) * pv_per_peso_rate',
  daily_pair_pv_cap         DECIMAL(14,2)    NOT NULL DEFAULT 0.00 COMMENT 'Max paired PV per member per day',
  -- Legacy direct-referral fixed amount (kept for reference)
  direct_ref_bonus          DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  -- v4: Direct & Indirect referral bonuses as % of Package PV
  direct_ref_pv_pct         DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT 'Direct referral bonus = package_pv * (direct_ref_pv_pct/100) * pv_per_peso_rate',
  -- v2: Lifetime capping & DFI
  lifetime_cap_multiplier   DECIMAL(5,2)     NOT NULL DEFAULT 3.00,
  reactivation_fee          DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  reactivation_window_days  INT              NOT NULL DEFAULT 15,
  daily_fixed_income        DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  daily_fixed_income_days   INT              NOT NULL DEFAULT 90,
  dfi_pv_pct                DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT 'DFI = package_pv * dfi_pv_pct * pv_per_peso_rate (0 = use fixed daily_fixed_income)',
  status                    ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── INDIRECT REFERRAL LEVELS ────────────────────────────────
CREATE TABLE package_indirect_levels (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  package_id INT UNSIGNED     NOT NULL,
  level      TINYINT UNSIGNED NOT NULL,
  -- Legacy fixed-peso amount (kept for reference)
  bonus      DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  -- v4: Indirect level bonus as % of Package PV
  pv_pct     DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT 'Indirect level bonus = package_pv * (pv_pct/100) * pv_per_peso_rate',
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
  UNIQUE KEY uq_pkg_level (package_id, level)
) ENGINE=InnoDB;

-- ─── PRODUCTS (Phase 5) ───────────────────────────────────────
CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120)     NOT NULL,
  price       DECIMAL(12,2)    NOT NULL,
  pv_value    DECIMAL(14,2)    NOT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── REPEAT PURCHASES (Phase 5) ───────────────────────────────
CREATE TABLE repeat_purchases (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id     INT UNSIGNED     NOT NULL,
  product_id    INT UNSIGNED     NOT NULL,
  quantity      INT UNSIGNED     NOT NULL DEFAULT 1,
  total_pv      DECIMAL(14,2)    NOT NULL,
  total_price   DECIMAL(12,2)    NOT NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_by   INT UNSIGNED     NULL,
  approved_at   TIMESTAMP        NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)  REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  FOREIGN KEY (approved_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── USERS ────────────────────────────────────────────────────
CREATE TABLE users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username          VARCHAR(40)  NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  role              ENUM('member','admin') NOT NULL DEFAULT 'member',
  package_id        INT UNSIGNED NULL,
  reg_code_id           INT UNSIGNED NULL,
  reg_payment_method    ENUM('code','ewallet','pending') NOT NULL DEFAULT 'code',
  reg_paid_by           INT UNSIGNED NULL,

  -- Binary tree placement
  sponsor_id        INT UNSIGNED NULL,
  binary_parent_id  INT UNSIGNED NULL,
  binary_position   ENUM('left','right') NULL,

  -- Legacy pair counters (kept for reference)
  left_count        INT UNSIGNED NOT NULL DEFAULT 0,
  right_count       INT UNSIGNED NOT NULL DEFAULT 0,
  pairs_paid        INT UNSIGNED NOT NULL DEFAULT 0,
  pairs_flushed     INT UNSIGNED NOT NULL DEFAULT 0,
  pairs_paid_today  INT UNSIGNED NOT NULL DEFAULT 0,  -- reset by midnight cron

  -- PV-centered architecture (Phase 1/3)
  left_pv           DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Accumulated PV on left binary leg',
  right_pv          DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Accumulated PV on right binary leg',
  paired_pv         DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Total paired PV lifetime',
  paired_pv_today   DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Paired PV today (reset by midnight cron)',
  flushed_pv        DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'PV flushed in binary pairing',
  personal_pv       DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Personal sales PV from downline product purchases',
  group_pv          DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Group sales PV from downline',

  -- v2: Lifetime capping & DFI
  lifetime_earned       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  cap_status            ENUM('active','capped','perminact') NOT NULL DEFAULT 'active',
  capping_bypass        TINYINT(1)   NOT NULL DEFAULT 0,
  daily_cap_bypass      TINYINT(1)   NOT NULL DEFAULT 0,
  capped_at             TIMESTAMP NULL,
  last_reactivation_at  TIMESTAMP NULL,
  dfi_days_used         INT UNSIGNED NOT NULL DEFAULT 0,
  dfi_active            TINYINT(1)   NOT NULL DEFAULT 1,
  cd_active             TINYINT(1)   NOT NULL DEFAULT 0,
  ewallet_sent_today      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ewallet_sent_this_week  DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  -- Profile
  full_name         VARCHAR(120) NULL,
  email             VARCHAR(120) NULL,
  mobile            VARCHAR(20)  NULL,
  gcash_number      VARCHAR(20)  NULL,
  maya_number       VARCHAR(20)  NULL,
  usdt_address      VARCHAR(100) NULL,
  address           TEXT         NULL,
  photo             VARCHAR(200) NULL,

  ewallet_balance       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  withdrawable_balance  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status                ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  joined_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login        TIMESTAMP NULL,

  FOREIGN KEY (sponsor_id)       REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (binary_parent_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (package_id)       REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── REGISTRATION CODES ───────────────────────────────────────
CREATE TABLE reg_codes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(24)   NOT NULL UNIQUE,
  package_id  INT UNSIGNED  NOT NULL,
  price       DECIMAL(12,2) NOT NULL,
  status      ENUM('unused','used','expired') NOT NULL DEFAULT 'unused',
  is_cd       TINYINT(1)    NOT NULL DEFAULT 0,
  used_by     INT UNSIGNED  NULL,
  created_by  INT UNSIGNED  NOT NULL,
  used_at     TIMESTAMP     NULL,
  expires_at  DATE          NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (package_id) REFERENCES packages(id),
  FOREIGN KEY (used_by)    REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tie reg_codes FKs back to users (added after users table)
ALTER TABLE users ADD FOREIGN KEY (reg_code_id) REFERENCES reg_codes(id) ON DELETE SET NULL;

-- ─── COMMISSIONS ──────────────────────────────────────────────
CREATE TABLE commissions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  type           ENUM('pairing','direct_referral','indirect_referral','daily_fixed_income') NOT NULL,
  amount         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cap_deduction  DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- v2: amount blocked by lifetime cap
  source_user_id INT UNSIGNED  NULL,
  level          TINYINT UNSIGNED NULL,
  pairs_count    TINYINT UNSIGNED NULL,
  description    VARCHAR(255)  NULL,
  status         ENUM('credited','flushed') NOT NULL DEFAULT 'credited',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)        REFERENCES users(id),
  FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── PV TRANSACTIONS (Phase 2) ─────────────────────────────────
CREATE TABLE pv_transactions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  type            ENUM(
                    'package_personal',
                    'package_group',
                    'product_personal',
                    'product_group',
                    'binary_left',
                    'binary_right',
                    'binary_paired',
                    'binary_flushed'
                  ) NOT NULL,
  amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  source_user_id  INT UNSIGNED NULL,
  source_type     ENUM('registration','activation','repeat_purchase') NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_type (user_id, type),
  INDEX idx_created (created_at),
  FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── E-WALLET LEDGER ──────────────────────────────────────────
CREATE TABLE ewallet_ledger (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED  NOT NULL,
  type          ENUM('credit','debit') NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  reference_id  INT UNSIGNED  NULL,
  ref_type      ENUM('commission','payout','reactivation','transfer','topup', 'registration') NULL,  -- v2: added 'reactivation', 'transfer', 'topup', 'registration'
  balance_after DECIMAL(14,2) NOT NULL,
  note          VARCHAR(255)  NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── E-WALLET TRANSFERS ─────────────────────────────────────
CREATE TABLE ewallet_transfers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id     INT UNSIGNED NOT NULL,
  recipient_id  INT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  fee           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_amount    DECIMAL(12,2) NOT NULL,
  status        ENUM('completed','failed') NOT NULL DEFAULT 'completed',
  note          VARCHAR(255) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id)    REFERENCES users(id),
  FOREIGN KEY (recipient_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── E-WALLET ADMIN TOP-UPS ─────────────────────────────────
CREATE TABLE ewallet_admin_topups (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id      INT UNSIGNED NOT NULL,
  recipient_id  INT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  note          VARCHAR(255) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id)     REFERENCES users(id),
  FOREIGN KEY (recipient_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── PAYOUT REQUESTS ──────────────────────────────────────────
CREATE TABLE payout_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED  NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  payout_method  ENUM('gcash','maya','usdt') NOT NULL DEFAULT 'gcash',
  payout_account VARCHAR(100) NOT NULL DEFAULT '',
  service_fee_pct    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  service_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  usdt_rate          DECIMAL(12,4) NOT NULL DEFAULT 0.00,
  usdt_gas_fee       DECIMAL(10,4) NOT NULL DEFAULT 0.00,
  usdt_amount        DECIMAL(12,4) NOT NULL DEFAULT 0.00,
  status        ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  admin_note    TEXT          NULL,
  processed_by  INT UNSIGNED  NULL,
  requested_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at  TIMESTAMP NULL,
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── REACTIVATIONS ────────────────────────────────────────────
CREATE TABLE reactivations (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED  NOT NULL,
  amount_paid         DECIMAL(12,2) NOT NULL,
  previous_earned     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  package_id          INT UNSIGNED  NOT NULL,
  payment_method      ENUM('ewallet','gcash','maya','usdt','admin') NOT NULL DEFAULT 'ewallet',
  status              ENUM('pending','completed','rejected') NOT NULL DEFAULT 'completed',
  admin_note          TEXT          NULL,
  proof_image         VARCHAR(255)  NULL,
  processed_by        INT UNSIGNED  NULL,
  processed_at        TIMESTAMP NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id),
  FOREIGN KEY (package_id)  REFERENCES packages(id),
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_react_user (user_id, created_at),            -- v2
  INDEX idx_react_status (status, created_at),            -- v2
  INDEX fk_reactivations_processed_by (processed_by)
) ENGINE=InnoDB;

-- ─── DAILY FIXED INCOME LOG ───────────────────────────────────
CREATE TABLE daily_fixed_income_log (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED  NOT NULL,
  amount              DECIMAL(12,2) NOT NULL,
  day_number          INT UNSIGNED  NOT NULL,
  cap_status_at_payout ENUM('active','capped','perminact') NOT NULL DEFAULT 'active',
  cap_remaining       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_dfi_user_date (user_id, created_at),         -- v2
  UNIQUE KEY uq_user_day (user_id, day_number)
) ENGINE=InnoDB;

-- ─── COMMISSION-DEDUCT (CD) STATUS ────────────────────────────
CREATE TABLE user_cd_status (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED  NOT NULL,
  target_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  filled_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status          ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  assigned_by     INT UNSIGNED  NOT NULL,
  assigned_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at    TIMESTAMP NULL,
  cancelled_at    TIMESTAMP NULL,
  notes           TEXT NULL,
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_active (user_id, status),
  INDEX idx_assigned_at (assigned_at)
) ENGINE=InnoDB;

-- ─── COMMISSION-DEDUCT LEDGER ─────────────────────────────────
CREATE TABLE cd_ledger (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  user_id              INT UNSIGNED  NOT NULL,
  cd_status_id         INT UNSIGNED  NOT NULL,
  commission_id        INT UNSIGNED  NULL,
  type                 ENUM('pairing','direct_referral','indirect_referral') NOT NULL,
  gross_amount         DECIMAL(12,2) NOT NULL,
  cd_amount            DECIMAL(12,2) NOT NULL,
  withdrawable_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  source_user_id       INT UNSIGNED  NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (cd_status_id)   REFERENCES user_cd_status(id) ON DELETE CASCADE,
  FOREIGN KEY (commission_id)  REFERENCES commissions(id) ON DELETE SET NULL,
  FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_cd (user_id, cd_status_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ─── SYSTEM SETTINGS ──────────────────────────────────────────
CREATE TABLE settings (
  key_name   VARCHAR(80) NOT NULL PRIMARY KEY,
  value      TEXT        NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── INDEXES ──────────────────────────────────────────────────
-- (Indexes already defined inline in CREATE TABLE for reactivations & daily_fixed_income_log)
ALTER TABLE users          ADD INDEX idx_sponsor       (sponsor_id);
ALTER TABLE users          ADD INDEX idx_binary_parent (binary_parent_id, binary_position);
ALTER TABLE users          ADD INDEX idx_role_status   (role, status);
ALTER TABLE users          ADD INDEX idx_cap_status    (cap_status, capped_at);          -- v2
ALTER TABLE users          ADD INDEX idx_dfi_active    (dfi_active, dfi_days_used);      -- v2
ALTER TABLE users          ADD INDEX idx_reg_code      (reg_code_id);
ALTER TABLE commissions    ADD INDEX idx_user_type     (user_id, type, created_at);
ALTER TABLE commissions    ADD INDEX idx_source        (source_user_id);
ALTER TABLE commissions    ADD INDEX idx_status        (status, created_at);
ALTER TABLE reg_codes      ADD INDEX idx_status        (status);
ALTER TABLE ewallet_ledger ADD INDEX idx_user          (user_id, created_at);
ALTER TABLE payout_requests ADD INDEX idx_user_status  (user_id, status);
ALTER TABLE payout_requests ADD INDEX idx_status       (status, requested_at);

-- ─── SEED DATA ────────────────────────────────────────────────

-- Default admin account (password: Admin@1234 — CHANGE ON FIRST LOGIN)
INSERT INTO users (username, password_hash, role, status, full_name, email)
VALUES (
  'admin',
  '$2y$12$h3j0mO9NbtMyLg6EsC4M6eGy6buk0zanOgPmFBIgaI8V5/CUbaYqq', -- Admin@1234
  'admin',
  'active',
  'System Administrator',
  'admin@mlm.local'
);

-- Default starter package (v2 defaults)
INSERT INTO packages (
  name, entry_fee, package_pv_rate, pairing_pv_pct, daily_pair_pv_cap,
  pairing_bonus, daily_pair_cap, direct_ref_pv_pct, direct_ref_bonus,
  lifetime_cap_multiplier, reactivation_fee, reactivation_window_days,
  daily_fixed_income, daily_fixed_income_days, dfi_pv_pct, status
) VALUES (
  'Starter', 10000.00, 100.00, 20.00, 30000.00,
  2000.00, 3, 5.00, 500.00,
  3.00, 10000.00, 15,
  100.00, 90, 0.00, 'active'
);

-- Indirect referral levels for starter package
INSERT INTO package_indirect_levels (package_id, level, bonus, pv_pct) VALUES
  (1, 1,  300.00, 3.00),
  (1, 2,  200.00, 2.00),
  (1, 3,  150.00, 1.50),
  (1, 4,  100.00, 1.00),
  (1, 5,  100.00, 1.00),
  (1, 6,   50.00, 0.50),
  (1, 7,   50.00, 0.50),
  (1, 8,   50.00, 0.50),
  (1, 9,   50.00, 0.50),
  (1, 10,  50.00, 0.50);

-- System settings
INSERT INTO settings (key_name, value) VALUES
  ('site_name',         'Altas Farm'),
  ('site_tagline',      'Build Your Network. Grow Your Income.'),
  ('min_payout',        '500'),
  ('last_reset',        ''),
  ('maintenance_mode',  '0'),
  ('contact_email',     'support@altasfarm.com'),
  ('service_fee_gcash', '0'),
  ('service_fee_maya',  '0'),
  ('service_fee_usdt',  '5'),
  ('usdt_gas_fee',      '2.50'),
  ('gcash_enabled',     '1'),
  ('maya_enabled',      '1'),
  ('dfi_enabled',       '1'),
  ('gcash_number',      ''),
  ('maya_number',       ''),
  ('usdt_address',      ''),
  ('default_cap_multiplier', '3.00'),
  ('reactivation_ewallet_enabled', '1'),
  ('reactivation_external_enabled', '1'),
  ('ewallet_transfer_fee',        '0.00'),
  ('ewallet_min_transfer',        '50.00'),
  ('ewallet_transfer_daily_limit',  '5000.00'),
  ('ewallet_transfer_weekly_limit', '20000.00'),
  ('indirect_referral_enabled',   '1'),
  ('binary_enabled',              '1'),
  ('seat_limit',                  '0'),
  ('pv_per_peso_rate',            '1.0000'),
  ('personal_pv_requirement',     '0.0000');

-- Demo registration code (package 1, price 10500)
INSERT INTO reg_codes (code, package_id, price, created_by)
VALUES ('DEMO-STAR-TKIT', 1, 10500.00, 1);