-- GSAA Global — production schema (MySQL 5.7+ / 8.0)
-- Import this once into a fresh database before first use.
-- Run:  mysql -u youruser -p yourdatabase < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_code VARCHAR(10) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(160) NULL,
  password_hash VARCHAR(255) NOT NULL,
  sponsor_id INT NULL,
  parent_id INT NULL,
  slot TINYINT NULL COMMENT '1,2,3 - which of the parent''s 3 positions',
  package_code VARCHAR(20) NOT NULL DEFAULT 'none',
  status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'inactive',
  kyc_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_members_sponsor FOREIGN KEY (sponsor_id) REFERENCES members(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_parent FOREIGN KEY (parent_id) REFERENCES members(id) ON DELETE SET NULL,
  INDEX idx_sponsor (sponsor_id), INDEX idx_parent (parent_id), INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kyc_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  doc_type VARCHAR(40) NOT NULL DEFAULT 'identity',
  file_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kyc_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS packages (
  code VARCHAR(20) PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sponsor_income DECIMAL(12,2) NOT NULL DEFAULT 0,
  matrix_income DECIMAL(12,2) NOT NULL DEFAULT 0,
  matrix_levels TINYINT NOT NULL DEFAULT 10
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS level_settings (
  level TINYINT PRIMARY KEY,
  target_ids INT NOT NULL,
  reward_per_id DECIMAL(12,2) NOT NULL DEFAULT 0,
  boost_per_id DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS level_awards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  level TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_member_level (member_id, level),
  CONSTRAINT fk_award_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  package_code VARCHAR(20) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
  channel ENUM('razorpay','admin','qr') NOT NULL DEFAULT 'razorpay',
  razorpay_order_id VARCHAR(80) NULL,
  razorpay_payment_id VARCHAR(80) NULL,
  razorpay_signature VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_payments_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_order (razorpay_order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS income_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receiver_id INT NOT NULL,
  from_member_id INT NULL,
  income_type ENUM('direct','parent_level','reward','boost','service','qr') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  level TINYINT NULL,
  payment_id INT NULL,
  status ENUM('available','withdrawn') NOT NULL DEFAULT 'available',
  withdrawal_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_receiver FOREIGN KEY (receiver_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_receiver (receiver_id), INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  url VARCHAR(255) NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'phone',
  payout_method ENUM('pct','fixed') NOT NULL DEFAULT 'pct',
  payout_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  service_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  utr VARCHAR(60) NULL,
  screenshot_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  user_income DECIMAL(12,2) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sub_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS qr_settings (
  id INT PRIMARY KEY DEFAULT 1,
  company_name VARCHAR(120) NULL,
  upi_id VARCHAR(80) NULL,
  instructions TEXT NULL,
  qr_image_path VARCHAR(255) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS qr_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  package_code VARCHAR(20) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  utr VARCHAR(60) NULL,
  screenshot_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  income_on_approval DECIMAL(12,2) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qr_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS withdrawal_settings (
  id INT PRIMARY KEY DEFAULT 1,
  min_amount DECIMAL(12,2) NOT NULL DEFAULT 100,
  basis ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  admin_charge_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  tds_pct DECIMAL(5,2) NOT NULL DEFAULT 2.00,
  auto_enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  cycle_date DATE NOT NULL,
  gross DECIMAL(12,2) NOT NULL,
  admin_charge DECIMAL(12,2) NOT NULL,
  tds DECIMAL(12,2) NOT NULL,
  net DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  bank_ref VARCHAR(120) NULL,
  processed_by INT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wd_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  event VARCHAR(60) NOT NULL,
  subject VARCHAR(160) NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_throttle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(120) NOT NULL,
  attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ident (identifier, attempt_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;

-- Seed data ---------------------------------------------------------------

INSERT INTO packages (code, name, price, sponsor_income, matrix_income, matrix_levels) VALUES
  ('starter', 'Starter package', 49.00, 0.00, 0.00, 0),
  ('premium', 'Premium package', 1555.00, 200.00, 50.00, 10)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO level_settings (level, target_ids, reward_per_id, boost_per_id) VALUES
  (1,3,20,10),(2,9,20,10),(3,27,20,10),(4,81,20,10),
  (5,243,20,10),(6,729,20,10),(7,2187,20,10)
ON DUPLICATE KEY UPDATE target_ids=VALUES(target_ids);

INSERT INTO withdrawal_settings (id, min_amount, basis, admin_charge_pct, tds_pct, auto_enabled)
VALUES (1, 100, 'daily', 5.00, 2.00, 1)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO qr_settings (id, enabled) VALUES (1, 0) ON DUPLICATE KEY UPDATE id=id;

-- Default admin login: username "admin", password "admin123"
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN (see README).
-- Hash below is for "admin123" (bcrypt).
INSERT INTO admin_users (username, password_hash, name) VALUES
  ('admin', '$2y$10$BlsLIwVUtDVmNfxK7dl9LeYpNAhEKo8b06Jt/dqRNFhLf4asUs1ve', 'Admin')
ON DUPLICATE KEY UPDATE username=username;

-- Root member (the business owner's own ID — matrix root). Password: root123
INSERT INTO members (member_code, name, phone, password_hash, sponsor_id, parent_id, package_code, status, kyc_status)
VALUES ('7284519', 'Admin', '9999999999', '$2y$10$Jxa6ARH4I1xn2veSo/kPPuH.px4Qu3FH02S/s1av.828BEOFpVrBi', NULL, NULL, 'premium', 'active', 'approved')
ON DUPLICATE KEY UPDATE member_code=member_code;
