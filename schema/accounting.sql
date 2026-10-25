-- Learn Max accounting panel (accounting.learnmaxacademy.ir)
-- Schema inferred from the accounting branch. utf8mb4 / InnoDB.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('manager','reception') NOT NULL DEFAULT 'reception',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acc_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_acc_terms_status (status, createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS levels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL,
  fee INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acc_level_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  firstName VARCHAR(191) NOT NULL DEFAULT '',
  lastName VARCHAR(191) NOT NULL DEFAULT '',
  level VARCHAR(64) NOT NULL DEFAULT '',
  phone VARCHAR(20) NULL DEFAULT NULL,
  classType VARCHAR(64) NOT NULL DEFAULT '',
  studentCode VARCHAR(20) NULL DEFAULT NULL,
  totalPayable INT NOT NULL DEFAULT 0,
  amountPaid INT NOT NULL DEFAULT 0,
  debt INT NOT NULL DEFAULT 0,
  status ENUM('paid','unpaid','pending') NOT NULL DEFAULT 'pending',
  termId VARCHAR(32) NOT NULL DEFAULT '',
  receiptUrl TEXT NULL,
  hasBook TINYINT(1) NOT NULL DEFAULT 0,
  bookName VARCHAR(191) NULL DEFAULT NULL,
  bookPrice INT NOT NULL DEFAULT 0,
  hasInterview TINYINT(1) NOT NULL DEFAULT 0,
  hasDiscount TINYINT(1) NOT NULL DEFAULT 0,
  discountPercent INT NOT NULL DEFAULT 0,
  discountAmount INT NOT NULL DEFAULT 0,
  hasExtraCourse TINYINT(1) NOT NULL DEFAULT 0,
  extraCourseName VARCHAR(191) NULL DEFAULT NULL,
  extraCoursePrice INT NOT NULL DEFAULT 0,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_studentCode (studentCode),
  KEY idx_students_term (termId),
  KEY idx_students_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  studentId VARCHAR(32) NOT NULL,
  termId VARCHAR(32) NOT NULL DEFAULT '',
  paidAmount INT NOT NULL DEFAULT 0,
  date VARCHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_receipts_student (studentId),
  KEY idx_receipts_term (termId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salaries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacherName VARCHAR(191) NOT NULL DEFAULT '',
  role VARCHAR(64) NULL DEFAULT NULL,
  amount INT NOT NULL DEFAULT 0,
  month VARCHAR(64) NULL DEFAULT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'unpaid',
  termId VARCHAR(32) NULL DEFAULT NULL,
  receiptUrl TEXT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'staff',
  detail TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_salaries_term (termId, kind),
  KEY idx_salaries_name (teacherName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL DEFAULT '',
  amount INT NOT NULL DEFAULT 0,
  category VARCHAR(64) NULL DEFAULT NULL,
  date VARCHAR(32) NULL DEFAULT NULL,
  termId VARCHAR(32) NULL DEFAULT NULL,
  receiptUrl TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_expenses_term (termId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_pay_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacherName VARCHAR(191) NOT NULL,
  teacherId INT NULL DEFAULT NULL,
  offlineRate INT NOT NULL DEFAULT 0,
  onlineRate INT NOT NULL DEFAULT 0,
  privateRate INT NOT NULL DEFAULT 0,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teacher_name (teacherName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO levels (name, fee) VALUES
  ('T', 0), ('B', 0), ('E', 0), ('PI', 0), ('I', 0), ('UI', 0), ('ADV', 0),
  ('T5', 0), ('T6', 0), ('T7', 0), ('T8', 0), ('T9', 0), ('T10', 0),
  ('T11', 0), ('T12', 0), ('T13', 0), ('T14', 0), ('T15', 0), ('T16', 0),
  ('B1', 0), ('B2', 0), ('B3', 0), ('B4', 0), ('B5', 0),
  ('E1', 0), ('E2', 0), ('E3', 0), ('E4', 0), ('E5', 0),
  ('PI1', 0), ('PI2', 0), ('PI3', 0), ('PI4', 0), ('PI5', 0),
  ('I1', 0), ('I2', 0), ('I3', 0), ('I4', 0), ('I5', 0),
  ('UI1', 0), ('UI2', 0), ('UI3', 0), ('UI4', 0), ('UI5', 0),
  ('ADV1', 0), ('ADV2', 0), ('ADV3', 0), ('ADV4', 0), ('ADV5', 0),
  ('WT5', 0), ('WT6', 0), ('WT7', 0), ('WT8', 0), ('WT9', 0), ('WT10', 0),
  ('WT11', 0), ('WT12', 0), ('WT13', 0), ('WT14', 0), ('WT15', 0), ('WT16', 0),
  ('CB1', 0), ('CB2', 0), ('CB3', 0), ('CB4', 0), ('CB5', 0),
  ('CE1', 0), ('CE2', 0), ('CE3', 0), ('CE4', 0), ('CE5', 0),
  ('CPI1', 0), ('CPI2', 0), ('CPI3', 0), ('CPI4', 0), ('CPI5', 0),
  ('CI1', 0), ('CI2', 0), ('CI3', 0), ('CI4', 0), ('CI5', 0),
  ('CUI1', 0), ('CUI2', 0), ('CUI3', 0), ('CUI4', 0), ('CUI5', 0),
  ('CADV1', 0), ('CADV2', 0), ('CADV3', 0), ('CADV4', 0), ('CADV5', 0);

SET FOREIGN_KEY_CHECKS = 1;
