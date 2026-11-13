# Driftboard — a Kanban-style Task Tracker (PHP + MySQL)

Technical assignment: **Intern – IT** · Levels **1, 2 and 3 completed**, plus all three bonus items.

Instead of a plain table, tasks live on a **board with three columns** — *Pending → In Progress → Completed*.
You can drag a card to another column (or use the ← → buttons) to change its status. A list view with
pagination is also available.

---

## 1. Quick setup (XAMPP / WAMP / MAMP — about 5 minutes)

1. Install **XAMPP** and start **Apache** and **MySQL** in the XAMPP control panel.
2. Copy the whole `driftboard` folder into `C:\xampp\htdocs\` (Mac: `/Applications/XAMPP/htdocs/`).
3. Open **http://localhost/phpmyadmin** → click **Import** → choose `database.sql` → **Go**.
   This creates the `driftboard` database, the tables and sample data.
4. Open **http://localhost/driftboard/** in your browser. Done.

**Database settings** live in `includes/config.php`. The defaults (host `127.0.0.1`, user `root`, empty password,
database `driftboard`) match a fresh XAMPP install, so normally you change nothing. If your MySQL has a
password, either edit that file or set environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
(so no password has to be written in the code or committed to Git).

**Requirements:** PHP 8.0 or newer with the `pdo_mysql` extension (enabled by default in XAMPP), MySQL 5.7+ or MariaDB 10.3+.

### No Apache? Use PHP's built-in server
```bash
mysql -u root -p < database.sql          # import once
cd driftboard
php -S localhost:8000                    # then open http://localhost:8000
```

---

## 2. Test logins

| Role   | Email                    | Password    |
|--------|--------------------------|-------------|
| Admin  | admin@driftboard.test    | Admin@123   |
| Member | maya@driftboard.test     | Demo@1234   |
| Member | ravi@driftboard.test     | Demo@1234   |

Use Maya and Ravi to check that one member can never see the other's tasks. You can also register a new account
(it always becomes a *member*). These are demo accounts only — change/delete them for any real use.

---

## 3. Roles and permissions

| Action | Visitor (logged out) | Member | Admin |
|---|:--:|:--:|:--:|
| See login / register pages | ✅ | ✅ | ✅ |
| Open the board and use tasks | ❌ (redirected to login) | ✅ own tasks only | ✅ own tasks only |
| Create / edit / change status of a task | ❌ | ✅ own only | ✅ own only |
| Delete a task | ❌ | ✅ own only | ✅ **any** task (moderation) |
| Open the admin page (users + all tasks) | ❌ | ❌ (403) | ✅ |
| Enable / disable user accounts | ❌ | ❌ | ✅ (not their own) |
| Edit another person's task content | ❌ | ❌ | ❌ (least privilege) |

**Why these rules:** a member only ever needs their own data. An admin needs to keep the system healthy
(block abusive accounts, remove inappropriate tasks) but has no reason to rewrite other people's tasks, so that
power is deliberately *not* given. The role is stored in the database and **is never accepted from a form** —
registering with a forged `role=admin` field is ignored.

**How it is enforced (backend, not just hidden buttons):** every task query contains `WHERE id = ? AND user_id = ?`
(see `includes/tasks.php`). If you change the id in the URL or in a forged request, the database returns no row and the
app answers **404** — the same answer as for a task that doesn't exist, so it doesn't even reveal that the id is real.
The admin page calls `require_admin()` at the top, which returns **403** for non-admins. The user is re-read from
the database on every request, so disabling an account or changing a role takes effect immediately.

---

## 4. Features by level

**Level 1 – basic management:** `tasks` table; add task; list tasks; edit; delete **with a confirmation page**;
success messages after add / update / delete.

**Level 2 – status, search, validation:** statuses Pending / In Progress / Completed; change status (drag & drop or
← → buttons); search by title; status filter (and priority filter); validation on the front end (JavaScript, friendly
inline messages) **and** on the back end (PHP, always re-checks).

**Level 3 – login, authorization, security:** registration + login; `password_hash()` / `password_verify()`;
dashboard only for logged-in users; roles + ownership checks; **prepared statements (PDO) for every query**;
output escaped with `htmlspecialchars` (helper `e()`); **CSRF token** on every state-changing form and request.

**Bonus:** pagination (list view), dashboard summary (total / pending / in progress / completed / overdue + progress
bar), due dates and priority (colour-coded, with "overdue" and "due soon" badges).

### Extra security measures
- Session cookie is `HttpOnly` + `SameSite=Lax`; session id regenerated on login (stops session fixation); 30-minute idle logout.
- Login throttling: 5 wrong attempts within 10 minutes locks that email+IP for a while.
- Same error message for "wrong email" and "wrong password", and constant-time style check, so emails can't be discovered.
- `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` headers (no inline scripts are used, so injected scripts are blocked even if an escape were ever missed).
- Errors are logged on the server only; visitors see a friendly page — no SQL, file paths or credentials are ever shown.
- Logout and delete are POST + CSRF only (a link or image tag on another site cannot trigger them).

---

## 5. Database design

```
users (1) ────────< tasks (many)          login_attempts (stand-alone, for throttling)
```

**users** — `id` PK · `name` · `email` **UNIQUE** · `password_hash` (bcrypt) · `role` ENUM(member, admin) · `is_active` · `created_at`

**tasks** — `id` PK · `user_id` FK → users.id (**ON DELETE CASCADE**) · `title` VARCHAR(150) · `description` TEXT ·
`status` ENUM(pending, in_progress, completed) · `priority` ENUM(low, medium, high) · `due_date` DATE NULL ·
`created_at` · `updated_at` (auto)

**Why these choices (be ready to explain):**
- A separate `users` table with a one-to-many link lets each task have exactly one owner — the basis of the authorization rule.
- `ENUM` for status/priority means even a buggy or tampered request can't store an invalid value.
- `UNIQUE(email)` stops duplicate accounts at the database level (the PHP check alone can't prevent a race).
- `ON DELETE CASCADE` avoids orphaned tasks if a user is ever deleted.
- Indexes `(user_id, status)` and `(user_id, title)` match the exact queries the board uses (filter by status, search by title).
- Passwords are stored only as bcrypt hashes (`VARCHAR(255)` leaves room for future algorithms).

---

## 6. Folder structure

```
driftboard/
├─ index.php            redirects to board or login
├─ login.php  register.php  logout.php
├─ board.php            dashboard, filters, kanban + list view
├─ task_form.php        add AND edit a task (same form)
├─ task_status.php      change status (buttons + drag & drop)
├─ task_delete.php      confirmation page + delete
├─ admin.php            admin-only: users and all tasks
├─ includes/            PHP logic (blocked from browsers by .htaccess)
│   ├─ config.php  db.php  bootstrap.php  helpers.php
│   ├─ auth.php (login, roles, throttling)  csrf.php  tasks.php (all task SQL)  layout.php
├─ assets/css/style.css  assets/js/app.js
├─ database.sql         full schema + sample data
└─ docs/                TEST_CASES.md, screenshots/
```

---

## 7. Completed levels and known limitations

**Completed:** Level 1 ✅  Level 2 ✅  Level 3 ✅  Bonus: pagination ✅, dashboard summary ✅, due date + priority ✅

**Known limitations (honest list):**
- No "forgot password" / email verification (it would need an email server).
- Roles can only be set in the database (e.g. `UPDATE users SET role='admin' WHERE id=…`); there is no screen for promoting users.
- The login lockout is per email + IP, so a determined attacker with many IPs could still guess slowly; a production system would add CAPTCHA or rate limiting at the web-server level.
- Pagination applies to the list view; the board view loads up to 300 matching tasks.
- Drag & drop needs JavaScript; without it the ← → buttons do the same job.
- Sessions use PHP's default file storage and the app expects HTTPS in production (cookie `Secure` flag turns on automatically when HTTPS is used).
- Tested on PHP 8.3 + MariaDB 10.11, Chromium. Not tested on other browsers or on Laravel/CodeIgniter (not used).

---

## 8. Testing

See **`docs/TEST_CASES.md`** — 54 test cases (functional, validation, authorization/IDOR, CSRF, SQL-injection, XSS,
brute-force, error handling) with results, plus the bugs found and fixed. Screenshots are in **`docs/screenshots/`**.

---

## 9. Backing up and restoring the database

**Backup** (creates a full copy of schema + data):
```bash
mysqldump -u root -p --routines --single-transaction driftboard > driftboard_backup_2026-11-13.sql
```
(In phpMyAdmin: select `driftboard` → **Export** → *Quick* → **Go**.)

**Restore** (into the same or a new server):
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS driftboard CHARACTER SET utf8mb4"
mysql -u root -p driftboard < driftboard_backup_2026-11-13.sql
```
(In phpMyAdmin: **Import** the file.) Good practice: schedule the dump daily (cron / Windows Task Scheduler),
keep copies off the server, protect the file (it contains password hashes) and test a restore regularly.
> Note: `database.sql` itself starts with `DROP TABLE`, so importing it **resets** the data to the sample state.

---

## 10. AI assistant disclosure

## 10. AI Assistant Disclosure

An AI coding assistant (**Claude, by Anthropic**) was used to generate the initial version of the Driftboard application and its test scripts. The generated code served as the starting point for the project.

### My Contribution

I worked on setting up the application in my local development environment and getting it running successfully. My contributions included:

- **Environment Setup:** Configured the local development environment using XAMPP.
- **Database Configuration:** Imported the `database.sql` file and configured the database for local execution.
- **MySQL Troubleshooting:** Identified and resolved the MySQL port conflict between the existing MySQL Server and XAMPP.
- **PHP Configuration:** Verified the availability of the required PHP extensions, including `PDO` and `pdo_mysql`.
- **Application Execution:** Successfully launched the Driftboard application locally and accessed its login page.
- **Documentation:** Captured application screenshots and organized the project files for GitHub submission.
- **Project Understanding:** Explored the project structure, database configuration, authentication flow, task management features, and application functionality.

### AI Assistance

The initial application code and test scripts were AI-assisted. I used the generated project to understand the implementation, configure the environment, execute the application, and explore its functionality. I am continuing to improve my understanding of the codebase and its security features.


