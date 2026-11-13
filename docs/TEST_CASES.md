# Test cases and results

All tests were run manually-scripted against the running app (PHP 8.3 built-in server + MariaDB 10.11) using `curl` for request-level checks (including forged/tampered requests that a browser would never send) and a real Chromium browser (Playwright) for screenshots and drag-and-drop.

**Result: 54 / 54 passed.**

| # | Test case | Result |
|---|-----------|--------|
| 1 | L1 add valid task (302 redirect) | ✅ Pass |
| 2 | L1 task stored in DB | ✅ Pass |
| 3 | L1 success message shown after add | ✅ Pass |
| 4 | L2 empty/blank title rejected with friendly error (200, no insert) | ✅ Pass |
| 5 | L2 title over 150 chars rejected | ✅ Pass |
| 6 | L2 invalid status value rejected | ✅ Pass |
| 7 | L2 impossible date rejected | ✅ Pass |
| 8 | L1 edit own task | ✅ Pass |
| 9 | L2 change status to Completed | ✅ Pass |
| 10 | L2 bogus status via status endpoint rejected (404) | ✅ Pass |
| 11 | L2 search: no match returns 0 cards (Maya has no 'resume') | ✅ Pass |
| 12 | L2 search by title finds exactly 1 ('PHP') | ✅ Pass |
| 13 | L2 status filter shows only the Pending column | ✅ Pass |
| 14 | L2 list view + status filter works | ✅ Pass |
| 15 | L1 delete asks for confirmation first (GET shows page, row still exists) | ✅ Pass |
| 16 | L1 delete after confirm (POST) | ✅ Pass |
| 17 | L1 delete message shown | ✅ Pass |
| 18 | Bonus pagination appears in list view (>8 tasks) | ✅ Pass |
| 19 | L3 logged-out visitor blocked from board.php (302 to login) | ✅ Pass |
| 20 | L3 logged-out visitor blocked from task_form.php (302 to login) | ✅ Pass |
| 21 | L3 logged-out visitor blocked from admin.php (302 to login) | ✅ Pass |
| 22 | L3 logged-out visitor blocked from task_delete.php?id=11 (302 to login) | ✅ Pass |
| 23 | L3 passwords are bcrypt hashes in DB | ✅ Pass |
| 24 | L3 no plain-text password stored | ✅ Pass |
| 25 | L3 IDOR: Maya opening Ravi's task for edit -> 404 | ✅ Pass |
| 26 | L3 IDOR: Maya POST-editing Ravi's task -> 404 and data unchanged | ✅ Pass |
| 27 | L3 IDOR: Maya changing Ravi's status -> 404, unchanged | ✅ Pass |
| 28 | L3 IDOR: Maya opening delete page for Ravi's task -> 404 | ✅ Pass |
| 29 | L3 IDOR: Maya deleting Ravi's task -> 404, still exists | ✅ Pass |
| 30 | L3 Ravi's task never appears in Maya's search | ✅ Pass |
| 31 | L3 member opening admin.php -> 403 | ✅ Pass |
| 32 | L3 member POSTing to admin disable-user -> 403, Ravi still active | ✅ Pass |
| 33 | L3 id in URL query instead of body also blocked | ✅ Pass |
| 34 | L3 CSRF: POST without token -> 419 | ✅ Pass |
| 35 | L3 CSRF: wrong token -> 419 | ✅ Pass |
| 36 | L3 CSRF: logout via plain GET refused (405) | ✅ Pass |
| 37 | L3 register works, forged role=admin ignored (stored as member) | ✅ Pass |
| 38 | L3 duplicate email gives friendly message | ✅ Pass |
| 39 | L2/3 register validation errors (name/email/password/confirm) | ✅ Pass |
| 40 | L3 SQLi in search box returns normal page, no leak | ✅ Pass |
| 41 | L3 SQLi in login email does not log in | ✅ Pass |
| 42 | L3 XSS: stored <script> title is NOT rendered raw | ✅ Pass |
| 43 | L3 XSS: reflected search term escaped | ✅ Pass |
| 44 | L3 CSP header present | ✅ Pass |
| 45 | L3 clickjacking header present | ✅ Pass |
| 46 | L3 session cookie is HttpOnly | ✅ Pass |
| 47 | Admin can open admin.php | ✅ Pass |
| 48 | Admin cannot EDIT another user's task (least privilege) -> 404 | ✅ Pass |
| 49 | Admin cannot disable own account | ✅ Pass |
| 50 | Admin disables Ravi | ✅ Pass |
| 51 | Disabled user cannot log in | ✅ Pass |
| 52 | Admin can delete any task (moderation) | ✅ Pass |
| 53 | L3 brute-force lockout after 5 failures (even correct password blocked) | ✅ Pass |
| 54 | Bad DB credentials -> friendly 500 page, no password/host/SQL leaked | ✅ Pass |

## Assignment testing checklist

| Checklist question | Covered by | Result |
|---|---|---|
| Can a user add a task with valid information? | tests 1-3 | ✅ |
| Does the application reject an empty task title? | test 4 (server) + browser validation (screenshot 06) | ✅ |
| Can a user edit, update status and delete a task? | edit, status, delete tests | ✅ |
| Do search and status filters return the expected tasks? | search / filter tests | ✅ |
| Can a logged-out visitor access the dashboard directly? | logged-out tests (all redirect to login) | ✅ blocked |
| Are passwords stored as hashes? | bcrypt hash tests | ✅ |
| Can a user access or modify another user's tasks? | all `IDOR` tests (view, edit, status, delete, search) | ✅ blocked |
| Prepared statements everywhere? | code review of every query + SQL-injection tests | ✅ |
| Invalid input / DB failure without leaking credentials or raw errors? | bad-credentials test shows a friendly page, details only in the server log | ✅ |

## Bugs found while testing, and how they were fixed

1. **Missing PHP extension crash** - `mb_substr()` is undefined when the `mbstring` extension is off, which crashed the board page. Fixed by adding small fallback functions in `includes/helpers.php`; verified with the extension both off and on.
2. **Open-redirect risk** - the move buttons send a `back` URL, and the first version redirected to whatever it contained. Fixed by only allowing `board.php` URLs (regex whitelist) in `task_status.php`.
3. **Login timing leak** - for an unknown email the app skipped `password_verify`, so a response-time difference could reveal which emails exist. Fixed by always verifying against a dummy hash.
4. **Test-script mistakes (not app bugs)** - e.g. the CSRF test first used another user's task id and got a (correct) 404 before the CSRF check; the test was corrected to use the user's own task.

## Known limitation found in testing

The `includes/` folder is protected from direct web access by `.htaccess` (Apache / XAMPP). The PHP built-in server (`php -S`) ignores `.htaccess`, but those files only define constants/functions and print nothing, so no source or credentials are exposed either way.