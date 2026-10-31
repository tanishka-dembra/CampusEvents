# 📝 CampusConnect — Revision Guide

A student-friendly reference covering **potential bugs, security gaps, hidden gotchas, and common pitfalls** in the codebase. Each issue includes the exact file/line, why it matters, and a concrete solution.

---

## Table of Contents

1. [Security Vulnerabilities](#1-security-vulnerabilities)
2. [Bugs & Inconsistencies](#2-bugs--inconsistencies)
3. [Multi-File Traps (Change One, Break Another)](#3-multi-file-traps)
4. [PHP Gotchas That Will Confuse You](#4-php-gotchas)
5. [JavaScript Gotchas](#5-javascript-gotchas)
6. [Database Gotchas](#6-database-gotchas)
7. [Common Modification Scenarios & What Can Go Wrong](#7-common-modification-scenarios)

---

## 1. Security Vulnerabilities

### 1.1 No CSRF Protection on Admin Forms
**Files:** `admin/add_event.php`, `admin/edit_event.php`, `admin/delete_event.php`

**The Problem:** Admin forms use plain `<form method="POST">` with no CSRF token. A malicious webpage could trick an admin's browser into submitting a hidden form that creates/deletes events.

**Why It Matters:** If an admin is logged in and visits a malicious site, that site can auto-submit a form to `http://localhost/campus-events/admin/delete_event.php?id=1` and delete events.

**Solution:**
```php
// In each admin form page, generate a token:
// At the top of the PHP block:
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In the HTML form, add a hidden field:
// <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// When handling POST, verify it:
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error = 'Invalid request. Please try again.';
}
```

---

### 1.2 No Rate Limiting on OTP Requests
**File:** `api/send_otp.php`

**The Problem:** Anyone can spam `POST /api/send_otp.php` with any email address unlimited times. This could:
- Flood someone's inbox
- Exhaust your Gmail sending quota (500 emails/day)
- Fill up the `email_otps` table

**Solution:**
```php
// Add this after email validation in send_otp.php:
$rateStmt = $pdo->prepare("
    SELECT COUNT(*) FROM email_otps 
    WHERE LOWER(email) = LOWER(?) 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$rateStmt->execute([$email]);
if ((int)$rateStmt->fetchColumn() >= 3) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Wait 5 minutes.']);
    exit;
}
```

---

### 1.3 CORS is Wide Open
**Files:** All `api/*.php` files (line ~14 in each)

**The Problem:** `Access-Control-Allow-Origin: *` means ANY website can call your API from the browser.

**Why It Matters:** Fine for local development, but if deployed, any website could register users for events or cancel passes.

**Solution:**
```php
// Replace this:
header('Access-Control-Allow-Origin: *');

// With this (restrict to your own domain):
header('Access-Control-Allow-Origin: http://localhost');
```

---

### 1.4 SSL Verification Disabled in Mailer
**File:** `api/mailer.php`

**The Problem:** The SMTP connection has `verify_peer => false` and `allow_self_signed => true`. This means a man-in-the-middle could intercept your SMTP credentials.

**Why It Matters:** Only affects production. XAMPP on Windows doesn't ship proper CA certificates, so this is **required locally**. If you ever deploy this, enable verification.

**Solution (for production only):**
```php
// Change in mailer.php's stream_context_create:
'verify_peer' => true,
'verify_peer_name' => true,
'allow_self_signed' => false,
```

---

### 1.5 Database Error Messages Leak Internal Details
**Files:** `api/db.php` (line 35), `api/register.php` (line 132), all API files

**The Problem:** Error responses include `$e->getMessage()` which exposes database table names, column names, and server paths to the browser.

**Solution:**
```php
// In production, replace:
'error' => 'Registration failed: ' . $e->getMessage()

// With:
'error' => 'Something went wrong. Please try again later.'

// Log the real error server-side instead:
error_log('Registration failed: ' . $e->getMessage());
```

---

## 2. Bugs & Inconsistencies

### 2.1 ⚠️ OTP Expiry Mismatch — UI Says 5 Minutes, Code Uses 10
**Files:**
- `index.html` line 186: `Code expires in 5 minutes.`
- `api/send_otp.php` line 40: `INTERVAL 10 MINUTE`

**The Problem:** Students see "5 minutes" in the UI but actually have 10 minutes. Confusing, and they might rush unnecessarily.

**Solution:** Change one to match the other.
```html
<!-- In index.html line 186, change to: -->
<small class="form-hint">Code expires in 10 minutes.</small>
```

---

### 2.2 Banner Images Collected But Never Displayed
**Files:**
- `database/schema.sql` — `banner_image VARCHAR(255)` column exists
- `admin/add_event.php` & `admin/edit_event.php` — forms collect it
- `api/get_events.php` — returns it in the JSON
- `js/app.js` — **never renders it** on the event cards

**The Problem:** Admin fills in a banner image filename, it's stored in the DB, but no image ever shows. Also, there's no `images/` folder and no file upload — the form just asks for a filename as text.

**Solution:** Either remove the field entirely, or render it:
```javascript
// In app.js's createEventCardHTML(), add before the card-title:
${event.banner_image ? `<img src="images/${escapeHTML(event.banner_image)}" alt="" class="card-banner">` : ''}
```
And create an `images/` folder to put banner images in.

---

### 2.3 Toast Icons Are Empty Strings
**File:** `js/app.js` (~line 650)

**The Problem:** The toast notification system has an icon object with empty strings:
```javascript
const icons = { success: '', warning: '', error: '' };
```
The emojis are embedded directly in the message strings instead (e.g., `'✅ Registration confirmed!'`). The icon `<span>` in the toast HTML is wasted.

**Solution:** Either populate the icons:
```javascript
const icons = { success: '✅', warning: '⚠️', error: '❌' };
```
Or remove the icon span from the toast HTML.

---

### 2.4 Admin and Student Sessions Can Destroy Each Other
**Files:** `api/logout.php`, `admin/logout.php`

**The Problem:** Both use `session_destroy()` which destroys the **entire** PHP session. If someone is logged in as both admin and student in the same browser:
- Student logout → admin session is also destroyed
- Admin logout → student session is also destroyed

**Solution:** Instead of destroying the full session, only unset the relevant keys:
```php
// In api/logout.php (student), replace session_destroy() with:
unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['student_id'], $_SESSION['user_email']);

// In admin/logout.php (admin), replace session_destroy() with:
unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username']);
```

---

## 3. Multi-File Traps

> **These are the #1 reason students break the project.** Changing something in one file often requires changes in 2-3 other files.

### 3.1 Adding a New Event Category

If asked to add a new category (e.g., "Seminar"), you must change **4 places**:

| # | File | What to change |
|---|------|----------------|
| 1 | `index.html` (~line 53) | Add a new `<button class="pill" data-cat="Seminar">` to the category pills |
| 2 | `admin/add_event.php` (~line 243) | Add `<option value="Seminar">Seminar</option>` to the category dropdown |
| 3 | `admin/edit_event.php` (~line 255) | Same — add the option to the edit form dropdown |
| 4 | `js/app.js` | No change needed — client-side filter matches by `data-cat` attribute dynamically |

**Common mistake:** Adding it only in the admin form but not in `index.html`, so the student portal can't filter by the new category.

---

### 3.2 Adding a New Field to Events (e.g., "Organizer Name")

You must touch **7 files**:

| # | File | Change |
|---|------|--------|
| 1 | `database/schema.sql` | Add column: `organizer VARCHAR(100)` to events table |
| 2 | `api/get_events.php` | The `SELECT e.*` already fetches all columns — no change needed here |
| 3 | `admin/add_event.php` | Add form input + include in INSERT query + `$formData` array |
| 4 | `admin/edit_event.php` | Add form input + include in UPDATE query + `$formData` array |
| 5 | `admin/dashboard.php` | Add column to the events table if you want it visible |
| 6 | `js/app.js` | Add to `createEventCardHTML()` to display on student cards |
| 7 | Run SQL | `ALTER TABLE events ADD COLUMN organizer VARCHAR(100) AFTER venue;` |

**Common mistake:** Adding the column in SQL and the form, but forgetting to include it in the INSERT/UPDATE prepared statement — the value silently won't save.

---

### 3.3 Changing the Database Name or Credentials

Two files to update:

| # | File | What |
|---|------|------|
| 1 | `api/db.php` line 11 | `$dbname = 'campus_events';` |
| 2 | `database/schema.sql` line 5 | `CREATE DATABASE IF NOT EXISTS campus_events;` |
| 3 | `database/admin_table.sql` line 6 | `USE campus_events;` |

**Common mistake:** Changing the name in `db.php` but not in the SQL files, so re-importing creates a different database.

---

## 4. PHP Gotchas

### 4.1 `db.php` Defines `$username` and `$password` as Variables
**File:** `api/db.php` lines 12-13

```php
$username = 'root';
$password = '';
```

**The Trap:** `admin/login.php` includes `db.php` (line 24). If you name the POST variables `$username` and `$password` instead of `$inputUser` and `$inputPass`, they'll **overwrite** the database credentials and break the PDO connection.

The original author was careful — they used `$inputUser` and `$inputPass` (line 26-27) specifically to avoid this collision.

**Lesson:** When including `db.php`, never use `$username`, `$password`, `$host`, `$dbname`, `$charset`, `$dsn`, `$options`, or `$pdo` as your own variable names.

---

### 4.2 `session_start()` Must Come Before Any Output
**Files:** All files that use sessions

**The Trap:** If you add ANY HTML, echo, or even a blank line before `<?php` (or before `session_start()`), PHP will throw: `"Cannot send session cookie - headers already sent"`.

**Example of breaking it:**
```php
 
<?php          // ← That blank line ABOVE causes the error!
session_start();
```

**Rule:** `session_start()` must be called before any output, including whitespace before `<?php`.

---

### 4.3 `password_hash()` Generates a Different Hash Every Time
**File:** `database/admin_table.sql`, `admin/login.php`

**The Trap:** Running `password_hash('admin123', PASSWORD_DEFAULT)` twice gives **different strings** every time (because of random salt). This is normal! `password_verify()` handles this.

**Common mistake:** A student hashes the password, compares the hash string directly with `===` instead of using `password_verify()`, and it never matches.

```php
// WRONG — will never match:
if ($inputPass === $admin['password_hash']) { ... }

// CORRECT — handles salting automatically:
if (password_verify($inputPass, $admin['password_hash'])) { ... }
```

---

### 4.4 `trim()` on Passwords Can Strip Valid Characters
**File:** `admin/login.php` line 27

```php
$inputPass = trim($_POST['password'] ?? '');
```

**The Trap:** If someone sets a password that starts or ends with spaces, `trim()` silently removes them. The hash was generated from the untrimmed password, so `password_verify()` fails.

**Solution:** Don't trim passwords:
```php
$inputPass = $_POST['password'] ?? '';
```

---

### 4.5 `json_decode()` Returns `null` on Invalid JSON — Not an Error
**Files:** `api/register.php` line 36, `api/cancel.php` line 32, etc.

```php
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
```

**The Trap:** If a student sends malformed JSON, `json_decode()` silently returns `null`, and `?? $_POST` kicks in. If `$_POST` is also empty, `$input` becomes an empty array — all values will be missing but **no error is thrown**. The code falls through to the "Invalid event selected" check, but the error message is misleading.

---

## 5. JavaScript Gotchas

### 5.1 `escapeHTML()` Must Be Used on ALL Dynamic Content
**File:** `js/app.js`

**The Trap:** The codebase has a custom `escapeHTML()` function to prevent XSS. If you add a new field to the event card and forget to wrap it:

```javascript
// WRONG — XSS vulnerability if event title contains <script>:
<h3>${event.title}</h3>

// CORRECT:
<h3>${escapeHTML(event.title)}</h3>
```

**Rule:** Every `${event.something}` inside HTML template literals MUST be wrapped in `escapeHTML()`.

---

### 5.2 `updateEventSeatsLocally()` Updates DOM Without Server Call
**File:** `js/app.js`

**The Trap:** After register/cancel, the UI updates seats **locally** using the `remaining_seats` value returned by the API. It does NOT re-fetch all events. This means:

- If another student registered between your page load and your registration, your seat counts are stale for OTHER events
- The local count for YOUR event is correct (server returns it)

**Why this matters:** If you're debugging "wrong seat numbers", the local DOM might be out of sync. A full page refresh always shows the latest data from the server.

---

### 5.3 `allEvents[]` Is the Single Source of Truth for Filtering
**File:** `js/app.js`

**The Trap:** Search and category filters work on `allEvents[]` array stored in memory. If you modify the DOM directly (e.g., with `document.getElementById`) without updating `allEvents[]`, your changes disappear the moment someone types in the search box or clicks a category pill (because it re-renders from `allEvents[]`).

---

### 5.4 The Pending Registration Pattern
**File:** `js/app.js`

**The Trap:** There's a variable `pendingEventIdToRegister`. When an unauthenticated user clicks "Register", it stores the event ID, opens the login modal, and after OTP verification, auto-triggers the registration.

If you move or rename this variable, users will click Register, log in, and then... nothing happens. The auto-registration silently breaks.

---

## 6. Database Gotchas

### 6.1 `ON DELETE CASCADE` Means Deleting an Event Deletes ALL Its Registrations
**File:** `database/schema.sql`

```sql
FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
```

**The Trap:** If you delete an event from the admin panel, every registration for that event is automatically deleted too. There's no "archive" — data is permanently lost.

**Common mistake:** A student adds a "soft delete" column (`is_deleted`) to events but forgets about CASCADE — the registrations are still hard-deleted if they use `DELETE` by accident.

---

### 6.2 `UNIQUE(event_id, user_id)` on Registrations
**File:** `database/schema.sql`

**The Trap:** This database constraint prevents duplicate bookings. If `register.php`'s application-level duplicate check somehow fails (race condition), the database constraint catches it and throws `PDOException` with code `23000`.

The code catches this specifically on line 120 of `register.php`. If you remove or rename this catch block, duplicate registration attempts will show a generic "500 Internal Server Error" instead of a friendly message.

---

### 6.3 `UNIQUE` on `users.student_id` — Collision Risk
**File:** `database/schema.sql`, `api/verify_otp.php` line 69

```php
$assignedId = !empty($studentId) ? $studentId : 'STU' . random_int(1000, 9999);
```

**The Trap:** If a student doesn't provide a student ID, the code generates a random one like `STU4821`. With only 9000 possibilities (`1000-9999`), after ~100 users, there's a significant chance of collision → `PDOException` for duplicate `student_id` → user can't register.

**Solution:**
```php
$assignedId = !empty($studentId) ? $studentId : 'STU' . random_int(100000, 999999);
```

---

### 6.4 No Index on `email_otps.email` — Slow Lookup at Scale
**File:** `database/schema.sql`

**The Problem:** The `email_otps` table has no index on `email`. Every OTP lookup does a full table scan. With thousands of OTPs, the `LOWER(email) = LOWER(?)` query gets slow.

**Solution:**
```sql
ALTER TABLE email_otps ADD INDEX idx_email (email);
```

---

## 7. Common Modification Scenarios

### "Add an event image upload instead of a text filename"

**What you'd need:**
1. Change the form input from `type="text"` to `type="file"` in `add_event.php` and `edit_event.php`
2. Add `enctype="multipart/form-data"` to the `<form>` tag (without this, file uploads silently fail!)
3. Handle `$_FILES['banner_image']` instead of `$_POST['banner_image']`
4. Use `move_uploaded_file()` to save to an `images/` directory
5. Store just the filename in the database

**The #1 mistake:** Forgetting `enctype="multipart/form-data"` — the form submits, no errors, but `$_FILES` is empty.

---

### "Make the admin dashboard show event registration count in real-time"

**The Trap:** The admin panel is server-rendered PHP — there's no JavaScript fetching data. To add real-time updates, you'd need to add a `setInterval` + `fetch()` to poll an API endpoint, similar to how the student portal works.

You can't just "make it update" — you'd be converting part of the admin panel from server-rendered to client-side JS, which is a bigger change than it looks.

---

### "Add an event end-date or multi-day events"

**What you'd need to change:**
1. `database/schema.sql` — add `event_end_date DATETIME`
2. `admin/add_event.php` — add form field + INSERT
3. `admin/edit_event.php` — add form field + UPDATE
4. `api/get_events.php` — already uses `SELECT e.*`, so it auto-includes new columns
5. `js/app.js` — update `createEventCardHTML()` to display date range
6. Validation — ensure `event_end_date >= event_date`

**Common mistake:** Adding the column and form but forgetting to add it to the INSERT prepared statement's `VALUES (?, ?, ?, ?, ?, ?, ?)` — you'll get a column count mismatch error.

---

### "Allow admin to remove a specific student's registration"

**What you'd need:**
1. Add a "Remove" button per row in `admin/view_registrations.php`
2. Create a new endpoint or handler (e.g., `admin/remove_registration.php`)
3. Use `DELETE FROM registrations WHERE id = ?` (no user_id check needed since admin)
4. Redirect back to `view_registrations.php?id=EVENT_ID`

**The Trap:** Don't use the student's `api/cancel.php` — it checks `$_SESSION['user_id']` ownership. The admin isn't the student, so it would return 403 Forbidden.

---

> **💡 Final Tip:** When in doubt, always trace the full flow: **HTML form → PHP handler → SQL query → Response → JavaScript handler**. Most bugs happen because one link in this chain was changed without updating the others.
