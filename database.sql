-- Driftboard database export
-- Import this file once (phpMyAdmin > Import, or: mysql -u root -p < database.sql)
-- It creates the database, the tables and some sample data.

CREATE DATABASE IF NOT EXISTS driftboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE driftboard;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS users;

-- USERS: one row per account. Email is UNIQUE (also used to log in).
-- password_hash holds a bcrypt hash made by PHP password_hash() - never the real password.
-- role: 'member' (default) or 'admin'.  is_active lets an admin disable an account.
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(60)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('member','admin') NOT NULL DEFAULT 'member',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- TASKS: every task belongs to exactly one user (one-to-many).
-- ON DELETE CASCADE: removing a user removes their tasks, so no orphan rows are left.
-- status/priority use ENUM so the database itself rejects invalid values.
CREATE TABLE tasks (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT         NOT NULL,
  status      ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  due_date    DATE NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_user_status (user_id, status),   -- fast "my tasks by status"
  KEY idx_tasks_user_title (user_id, title),     -- fast title search per user
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- LOGIN_ATTEMPTS: failed logins only, used to slow down password guessing.
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(120) NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_lookup (email, ip, attempted_at)
) ENGINE=InnoDB;

-- SAMPLE USERS (test logins are listed in README.md)
INSERT INTO users (id, name, email, password_hash, role) VALUES
  (1, 'Alex Admin', 'admin@driftboard.test', '$2y$10$uOb.y83VTheuQlJyiq4rXO0fjALpO1R7JUJUVHz11kWFhVxk2KLNa', 'admin'),
  (2, 'Maya Member', 'maya@driftboard.test', '$2y$10$WCVN90WZLzZ1c4.S/bswXeZ/xSJd2.kobYxs3KSg15Cp1iX9umOOm', 'member'),
  (3, 'Ravi Member', 'ravi@driftboard.test', '$2y$10$WCVN90WZLzZ1c4.S/bswXeZ/xSJd2.kobYxs3KSg15Cp1iX9umOOm', 'member');

-- SAMPLE TASKS (due dates are relative to the day you import, so the board always looks alive)
INSERT INTO tasks (user_id, title, description, status, priority, due_date) VALUES
  (2, 'Finish PHP assignment', 'Complete all three levels and write the README.', 'in_progress', 'high', CURDATE() + INTERVAL 1 DAY),
  (2, 'Record demo video', 'Short screen recording showing login, board and drag and drop.', 'pending', 'medium', CURDATE() + INTERVAL 3 DAY),
  (2, 'Book dentist appointment', '', 'pending', 'low', NULL),
  (2, 'Pay electricity bill', 'Due at the end of the week.', 'pending', 'high', CURDATE() - INTERVAL 1 DAY),
  (2, 'Set up Git repository', 'Create repo, add .gitignore, first commit.', 'completed', 'medium', CURDATE() - INTERVAL 2 DAY),
  (2, 'Read chapter on SQL joins', 'Practice INNER JOIN and LEFT JOIN with the tasks table.', 'completed', 'low', NULL),
  (2, 'Prepare interview answers', 'Be ready to explain the schema, CSRF token and role rules.', 'in_progress', 'medium', CURDATE() + INTERVAL 2 DAY),
  (2, 'Test XSS: <script>alert(1)</script>', 'This title is stored safely and shown as plain text.', 'pending', 'low', NULL),
  (3, 'Plan team offsite', 'Venue, budget and agenda.', 'pending', 'medium', CURDATE() + INTERVAL 7 DAY),
  (3, 'Update resume', 'Add the new internship project.', 'in_progress', 'low', NULL),
  (3, 'Ravi private task', 'Maya must never be able to see or open this one.', 'pending', 'high', CURDATE() + INTERVAL 1 DAY),
  (1, 'Review new user sign-ups', 'Check the admin panel weekly.', 'pending', 'medium', NULL);
