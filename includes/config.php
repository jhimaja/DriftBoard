<?php
/**
 * Driftboard configuration.
 * Default values match a fresh XAMPP / WAMP / MAMP install (user "root", empty password).
 * To change them WITHOUT editing this file, set environment variables
 * (DB_HOST, DB_NAME, DB_USER, DB_PASS) - handy so no password is ever committed to Git.
 */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'driftboard');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

define('APP_NAME', 'Driftboard');
define('APP_DEBUG', false);          // keep false: never show raw errors to visitors
define('SESSION_IDLE_SECONDS', 1800); // auto logout after 30 minutes of inactivity
define('LOGIN_MAX_FAILS', 5);         // failed logins allowed ...
define('LOGIN_WINDOW_MINUTES', 10);   // ... within this many minutes
define('TASKS_PER_PAGE', 8);          // list-view pagination size
