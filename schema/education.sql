-- Learn Max educational panel (edu.learnmaxacademy.ir)
-- Schema inferred from the education branch. utf8mb4 / InnoDB.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(191) NOT NULL DEFAULT '',
  phone VARCHAR(20) NULL DEFAULT NULL,
  father_phone VARCHAR(20) NULL DEFAULT NULL,
  mother_phone VARCHAR(20) NULL DEFAULT NULL,
  avatar VARCHAR(255) NULL DEFAULT NULL,
  role ENUM(
    'supervisor','teacher','student','consultant',
    'german_supervisor','german_teacher','german_student'
  ) NOT NULL DEFAULT 'student',
  remember_token VARCHAR(64) NULL DEFAULT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role (role, is_deleted),
  KEY idx_users_remember (remember_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  year VARCHAR(32) NULL DEFAULT NULL,
  starts_on DATE NULL DEFAULT NULL,
  ends_on DATE NULL DEFAULT NULL,
  accounting_term_id VARCHAR(32) NULL DEFAULT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_terms_open (is_deleted, is_archived, starts_on),
  KEY idx_terms_acc (accounting_term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  term_id INT UNSIGNED NULL DEFAULT NULL,
  origin_term_id INT UNSIGNED NULL DEFAULT NULL,
  class_type VARCHAR(32) NOT NULL DEFAULT 'offline',
  online_link VARCHAR(500) NULL DEFAULT NULL,
  schedule VARCHAR(191) NULL DEFAULT NULL,
  schedule_type VARCHAR(32) NULL DEFAULT NULL,
  class_time VARCHAR(64) NULL DEFAULT NULL,
  teacher_id INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL DEFAULT NULL,
  forward_next TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_classes_term (term_id, is_deleted, is_archived),
  KEY idx_classes_teacher (teacher_id),
  KEY idx_classes_origin (origin_term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_class_student (class_id, user_id),
  KEY idx_cs_user (user_id, is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_date DATE NOT NULL,
  is_present TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'absent',
  reason TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (class_id, user_id, class_date),
  KEY idx_att_date (class_date),
  KEY idx_att_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_sms_log (
  class_id INT NOT NULL,
  user_id INT NOT NULL,
  class_date DATE NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, user_id, class_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_late_sms_log (
  class_id INT NOT NULL,
  user_id INT NOT NULL,
  class_date DATE NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, user_id, class_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_plan_sms_log (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  sent_by INT NOT NULL DEFAULT 0,
  family VARCHAR(80) NOT NULL DEFAULT '',
  phone VARCHAR(20) NOT NULL DEFAULT '',
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rollcall_missing_alerts (
  class_id INT NOT NULL,
  class_date DATE NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, class_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alocom_rollcall_attempts (
  class_id INT NOT NULL,
  class_date DATE NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT '',
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, class_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_welcome_sms_log (
  user_id INT NOT NULL,
  file_number VARCHAR(20) NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_welcome_sms_pending (
  user_id INT NOT NULL,
  file_number VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alocom_credentials (
  panel_user_id INT NOT NULL,
  alocom_user_id INT NOT NULL,
  username VARCHAR(100) NOT NULL,
  password_plain VARCHAR(100) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (panel_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alocom_access_state (
  event_id INT NOT NULL,
  alocom_userid INT NOT NULL,
  panel_user_id INT DEFAULT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'auto',
  enrolled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, alocom_userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eas_management (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  interview_date VARCHAR(32) NULL DEFAULT NULL,
  interview_time VARCHAR(16) NULL DEFAULT NULL,
  interviewer_name VARCHAR(191) NULL DEFAULT NULL,
  interviewer_id INT NULL DEFAULT NULL,
  interview_status TINYINT NOT NULL DEFAULT 0,
  observation_status TINYINT NOT NULL DEFAULT 0,
  poll_status TINYINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_eas_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homework_status (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  homework_date DATE NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'not_submitted',
  PRIMARY KEY (id),
  UNIQUE KEY uq_homework (class_id, user_id, homework_date),
  KEY idx_hw_date (class_id, homework_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  session_date DATE NOT NULL,
  content MEDIUMTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_session_plan (class_id, session_date),
  KEY idx_sp_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS error_gravity (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  content MEDIUMTEXT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_by INT UNSIGNED NULL DEFAULT NULL,
  deleted_at DATETIME NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_error_gravity (class_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_quizzes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  quiz_1 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_2 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_3 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_4 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_5 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_6 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_7 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_8 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_9 DECIMAL(5,2) NULL DEFAULT NULL,
  quiz_10 DECIMAL(5,2) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_quiz (user_id, class_id),
  KEY idx_sq_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supervisor_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  supervisor_score DECIMAL(5,2) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_supervisor_score (user_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mind_lingo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  communication_dev TINYINT NULL DEFAULT NULL,
  learning_eng TINYINT NULL DEFAULT NULL,
  learning_cons TINYINT NULL DEFAULT NULL,
  personal_growth TINYINT NULL DEFAULT NULL,
  teacher_note TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mind_lingo (class_id, student_id),
  KEY idx_ml_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS observation_forms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  supervisor_id INT UNSIGNED NULL DEFAULT NULL,
  payload MEDIUMTEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_obs_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  teacher_id INT NOT NULL,
  session_no TINYINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  body MEDIUMTEXT,
  teacher_stored_name VARCHAR(255) NULL,
  teacher_original_name VARCHAR(255) NULL,
  teacher_mime VARCHAR(80) NULL,
  teacher_size INT NOT NULL DEFAULT 0,
  due_at DATE NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_asg_class (class_id),
  KEY idx_asg_teacher (teacher_id),
  KEY idx_asg_session (class_id, session_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  class_id INT NOT NULL,
  student_id INT NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(80) NOT NULL DEFAULT '',
  file_size INT NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  UNIQUE KEY uq_asg_student (assignment_id, student_id),
  KEY idx_asub_class (class_id),
  KEY idx_asub_student (student_id),
  KEY idx_asub_asg (assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_key VARCHAR(24) NULL DEFAULT NULL,
  level_key VARCHAR(24) NULL DEFAULT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
  file_size INT NOT NULL DEFAULT 0,
  page_count INT NOT NULL DEFAULT 0,
  extract_cursor INT NOT NULL DEFAULT 0,
  extract_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  extract_error VARCHAR(500) NULL,
  uploaded_by INT NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_qb_level (level_key),
  KEY idx_qb_course (course_key),
  KEY idx_qb_status (extract_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_book_pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  page_no INT NOT NULL,
  body MEDIUMTEXT,
  UNIQUE KEY uq_qbp (book_id, page_no),
  KEY idx_qbp_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  teacher_id INT NOT NULL,
  book_id INT NOT NULL,
  template_no TINYINT UNSIGNED NOT NULL,
  session_no TINYINT UNSIGNED NULL,
  difficulty VARCHAR(16) NOT NULL DEFAULT 'medium',
  page_from INT NOT NULL DEFAULT 1,
  page_to INT NOT NULL DEFAULT 1,
  questions_json MEDIUMTEXT,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_qe_class (class_id),
  KEY idx_qe_teacher (teacher_id),
  KEY idx_qe_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  class_id INT NOT NULL,
  student_id INT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'in_progress',
  started_at DATETIME NULL,
  submitted_at DATETIME NULL,
  answers_json MEDIUMTEXT,
  result_json MEDIUMTEXT,
  score_raw DECIMAL(8,2) NULL,
  score_max_raw DECIMAL(8,2) NULL,
  score_total DECIMAL(8,2) NULL,
  score_max DECIMAL(8,2) NULL,
  pending_count INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_qa_exam_student (exam_id, student_id),
  KEY idx_qa_class (class_id),
  KEY idx_qa_student (student_id),
  KEY idx_qa_exam (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term_plan_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  session_no TINYINT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  source VARCHAR(16) NOT NULL DEFAULT 'supervisor',
  created_by INT NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tp_class_session (class_id, session_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term_plan_task_checks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  class_id INT NOT NULL,
  teacher_id INT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  method VARCHAR(64) NULL DEFAULT NULL,
  method_custom VARCHAR(191) NULL DEFAULT NULL,
  teacher_remark TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tp_task (task_id),
  KEY idx_tp_check_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term_plan_session_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  session_no TINYINT UNSIGNED NOT NULL,
  remark TEXT NULL,
  supervisor_id INT NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tp_remark (class_id, session_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term_plan_level_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  level_key VARCHAR(16) NOT NULL,
  session_no TINYINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  title VARCHAR(500) NOT NULL,
  KEY idx_tp_tpl_level (level_key, session_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term_plan_meta (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v VARCHAR(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consultant_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  consultant_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cr_student (student_id),
  KEY idx_cr_consultant (consultant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS psych_test_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  test_key VARCHAR(16) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  answers_json MEDIUMTEXT NOT NULL,
  scores_json MEDIUMTEXT NULL,
  submitted_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pta_student_test (student_id, test_key),
  KEY idx_pta_student (student_id),
  KEY idx_pta_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supervisor_ai_votes (
  user_id INT NOT NULL,
  pair_hash CHAR(40) NOT NULL,
  kind ENUM('like','dislike') NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  username VARCHAR(64) NOT NULL DEFAULT '',
  full_name VARCHAR(190) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, pair_hash),
  KEY kind_idx (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supervisor_ai_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pair_hash CHAR(40) NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  username VARCHAR(64) NOT NULL DEFAULT '',
  full_name VARCHAR(190) NOT NULL DEFAULT '',
  mm_ok TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY user_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS german_fees (
  level VARCHAR(8) NOT NULL PRIMARY KEY,
  group_fee INT NOT NULL DEFAULT 0,
  private_fee INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS german_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  level VARCHAR(8) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  session_count INT NOT NULL DEFAULT 10,
  fee_per_session INT NOT NULL DEFAULT 0,
  teacher_id INT NOT NULL DEFAULT 0,
  schedule VARCHAR(80) NOT NULL DEFAULT '',
  schedule_type VARCHAR(20) NOT NULL DEFAULT '',
  session_status_json TEXT NULL,
  session_dates_json TEXT NULL,
  note TEXT NULL,
  created_by INT NOT NULL DEFAULT 0,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gc_teacher (teacher_id),
  KEY idx_gc_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS german_class_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  student_id INT NOT NULL,
  fee_total INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gcs (class_id, student_id),
  KEY idx_gcs_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS german_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  student_id INT NOT NULL,
  amount INT NOT NULL DEFAULT 0,
  receipt_path VARCHAR(255) NOT NULL DEFAULT '',
  receipt_name VARCHAR(190) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gp_class_student (class_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO german_fees (level, group_fee, private_fee) VALUES
  ('A1', 246000, 930000),
  ('A2', 246000, 930000),
  ('B1', 246000, 930000),
  ('B2', 246000, 930000);

SET FOREIGN_KEY_CHECKS = 1;
