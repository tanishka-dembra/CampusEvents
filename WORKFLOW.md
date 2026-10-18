# CampusConnect — Detailed Workflow

This document explains the complete internal workflow of the CampusConnect Campus Event Registration System, covering every user interaction, API call, database operation, and security mechanism.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Student Portal Workflow](#2-student-portal-workflow)
   - 2.1 Page Load & Event Fetching
   - 2.2 Search & Filter
   - 2.3 OTP Login Flow
   - 2.4 Event Registration
   - 2.5 View My Bookings & Cancellation
   - 2.6 Logout
3. [Admin Panel Workflow](#3-admin-panel-workflow)
   - 3.1 Admin Login
   - 3.2 Dashboard & Event Listing
   - 3.3 Add Event
   - 3.4 Edit Event
   - 3.5 Delete Event
   - 3.6 View Registrations
   - 3.7 Admin Logout
4. [Database Schema & Relationships](#4-database-schema--relationships)
5. [Security Mechanisms](#5-security-mechanisms)
6. [Request-Response Flow Diagrams](#6-request-response-flow-diagrams)

---

## 1. System Overview

The application consists of two independent interfaces sharing the same database:

```
┌─────────────────────────────────────────────────────┐
│                    BROWSER                          │
│                                                     │
│   ┌───────────────────┐   ┌───────────────────┐     │
│   │  Student Portal   │   │   Admin Panel     │     │
│   │  (index.html)     │   │  (admin/*.php)    │     │
│   │  + app.js         │   │  Server-rendered  │     │
│   │  SPA / Fetch API  │   │  PHP forms        │     │
│   └────────┬──────────┘   └────────┬──────────┘     │
└────────────┼───────────────────────┼────────────────┘
             │ AJAX (JSON)           │ Form POST
             ▼                       ▼
┌─────────────────────────────────────────────────────┐
│              APACHE + PHP (XAMPP)                    │
│                                                     │
│   ┌──────────────┐        ┌──────────────────┐      │
│   │  api/*.php   │        │  admin/*.php     │      │
│   │  JSON APIs   │        │  Page Controllers│      │
│   └──────┬───────┘        └──────┬───────────┘      │
│          │                       │                  │
│          └───────────┬───────────┘                  │
│                      ▼                              │
│            ┌──────────────────┐                     │
│            │   api/db.php     │                     │
│            │   PDO Connection │                     │
│            └────────┬─────────┘                     │
└─────────────────────┼───────────────────────────────┘
                      ▼
            ┌──────────────────┐
            │  MySQL (InnoDB)  │
            │  campus_events   │
            └──────────────────┘
```

**Key difference:**
- **Student Portal** → Single-page app (`index.html` + `app.js`) that calls `api/*.php` endpoints via `fetch()` and gets JSON responses. No page reloads.
- **Admin Panel** → Traditional server-rendered PHP pages with HTML forms. Each action is a full page load.

---

## 2. Student Portal Workflow

### 2.1 Page Load & Event Fetching

```
User opens http://localhost/campus-events/
         │
         ▼
    index.html loads
         │
         ▼
    app.js → DOMContentLoaded
         │
         ├──→ fetch('api/get_current_user.php')
         │         │
         │         ▼
         │    Checks $_SESSION['user_id']
         │         │
         │         ├─ Session exists → Returns user data → Show logged-in navbar
         │         └─ No session    → Returns logged_in: false → Show guest navbar
         │
         └──→ fetch('api/get_events.php')
                   │
                   ▼
              SELECT e.*, (capacity - COUNT(r.id)) AS remaining_seats
              FROM events e LEFT JOIN registrations r ...
                   │
                   ▼
              Returns JSON array of events with live seat counts
                   │
                   ▼
              app.js renders event cards into the DOM
              (badges: 🟢 Available / 🟠 Filling Fast / 🔴 Housefull)
```

### 2.2 Search & Filter

All filtering happens **client-side** in `app.js` — no server calls:

```
User types in search box or clicks category pill
         │
         ▼
    app.js filters the `allEvents[]` array in memory
         │
         ├─ Matches against: title, venue, description (case-insensitive)
         ├─ Matches against: category pill selection
         │
         ▼
    Re-renders filtered event cards (no fetch call)
```

### 2.3 OTP Login Flow

```
User clicks "Student Login / Sign Up"
         │
         ▼
    Auth modal opens → Step 1: Email + Name + Student ID form
         │
         ▼
    User submits email
         │
         ▼
    fetch('api/send_otp.php', POST {email})
         │
         ▼
    ┌─────── send_otp.php ───────┐
    │                            │
    │  1. Validate email format  │
    │  2. Generate 6-digit OTP   │
    │     (random_int 100000-    │
    │      999999)               │
    │  3. Invalidate old unused  │
    │     OTPs for this email    │
    │  4. INSERT new OTP into    │
    │     email_otps table       │
    │     (expires: NOW() +      │
    │      10 minutes)           │
    │  5. Send HTML email via    │
    │     Google SMTP (TLS)      │
    │     using mailer.php       │
    └────────────┬───────────────┘
                 │
                 ▼
    Modal switches to Step 2: Enter OTP
         │
         ▼
    User enters 6-digit code
         │
         ▼
    fetch('api/verify_otp.php', POST {email, otp_code, name, student_id})
         │
         ▼
    ┌─────── verify_otp.php ─────┐
    │                            │
    │  1. Look up OTP in DB:     │
    │     WHERE email = ?        │
    │     AND otp_code = ?       │
    │     AND is_used = 0        │
    │     AND expires_at >= NOW()│
    │                            │
    │  2. Mark OTP as used       │
    │     (is_used = 1)          │
    │                            │
    │  3. Find user by email     │
    │     ├─ Exists → fetch user │
    │     └─ New → INSERT into   │
    │        users table         │
    │                            │
    │  4. Set PHP session:       │
    │     $_SESSION['user_id']   │
    │     $_SESSION['user_name'] │
    │     $_SESSION['user_email']│
    └────────────┬───────────────┘
                 │
                 ▼
    Modal closes → Navbar shows username → Cards show "Register Pass →"
         │
         ▼
    If user clicked register before logging in (pendingEventIdToRegister),
    auto-triggers registration now
```

### 2.4 Event Registration

```
Logged-in user clicks "Register Pass →" on an event card
         │
         ▼
    Button text → "Booking seat..."
         │
         ▼
    fetch('api/register.php', POST {event_id})
         │
         ▼
    ┌─────── register.php ───────────────────────────────────┐
    │                                                        │
    │  1. Check $_SESSION['user_id'] exists                  │
    │     └─ Missing → 401 + auth_required: true             │
    │                                                        │
    │  2. BEGIN TRANSACTION                                   │
    │                                                        │
    │  3. Duplicate check:                                    │
    │     SELECT FROM registrations WHERE event_id AND user_id│
    │     └─ Found → ROLLBACK → 409 "Already registered"     │
    │                                                        │
    │  4. Capacity check with row lock:                       │
    │     SELECT capacity, COUNT(registrations) ... FOR UPDATE│
    │     └─ remaining <= 0 → ROLLBACK → 400 "Full capacity" │
    │                                                        │
    │  5. INSERT INTO registrations (event_id, user_id)       │
    │                                                        │
    │  6. COMMIT → Return remaining_seats                     │
    └────────────────────────┬───────────────────────────────┘
                             │
                             ▼
    app.js → updateEventSeatsLocally()
         │
         ├─ Updates badge (🟢/🟠/🔴)
         ├─ Updates progress bar width & color
         ├─ Updates "Booked: X (Y%)" text
         └─ Updates hero stats (total open seats)
    
    ** No page reload — all DOM updates happen in-place **
```

### 2.5 View My Bookings & Cancellation

```
User clicks "My Bookings" in navbar
         │
         ▼
    Bookings modal opens
         │
         ▼
    fetch('api/my_registrations.php')
         │
         ▼
    SELECT events + registrations WHERE user_id = $_SESSION['user_id']
         │
         ▼
    Modal shows list of booked events with "Cancel Pass" buttons
         │
         ▼
    User clicks "Cancel Pass" → Browser confirm() dialog
         │
         ▼
    fetch('api/cancel.php', POST {registration_id})
         │
         ▼
    ┌─────── cancel.php ─────────────────────────────────┐
    │                                                    │
    │  1. Verify $_SESSION['user_id'] exists             │
    │                                                    │
    │  2. Ownership check:                                │
    │     SELECT FROM registrations                       │
    │     WHERE id = ? AND user_id = ?                    │
    │     └─ Not found → 403 "Unauthorized"               │
    │                                                    │
    │  3. DELETE FROM registrations WHERE id = ?           │
    │                                                    │
    │  4. Re-count remaining seats                        │
    │     → Return new remaining_seats                    │
    └───────────────────┬────────────────────────────────┘
                        │
                        ▼
    app.js removes booking from modal list
    app.js → updateEventSeatsLocally() on the main grid
    (seat freed up instantly, badge/bar updated)
```

### 2.6 Logout

```
User clicks "Logout"
         │
         ▼
    fetch('api/logout.php')
         │
         ▼
    session_destroy() → Returns success
         │
         ▼
    app.js → setLoggedOutUser()
    Re-renders cards (buttons change to "Login to Register →")
```

---

## 3. Admin Panel Workflow

> All admin pages are **server-rendered PHP**. Each action triggers a full page load or redirect.
> Every admin page starts with `require_once 'auth_check.php'` which checks
> `$_SESSION['admin_logged_in'] === true` and redirects to `login.php` if not.

### 3.1 Admin Login

```
Admin opens /admin/login.php
         │
         ▼
    HTML form: username + password
         │
         ▼
    Form POST → login.php handles it:
         │
         ├─ require db.php (sets up $pdo)
         ├─ Read POST values into $inputUser / $inputPass
         │   (separate variable names to avoid collision with db.php's $username/$password)
         │
         ├─ SELECT * FROM admins WHERE username = ?
         ├─ password_verify($inputPass, $admin['password_hash'])
         │
         ├─ Match → Set $_SESSION['admin_logged_in'] = true
         │          Redirect → dashboard.php
         │
         └─ No match → Show "Invalid username or password" error
```

### 3.2 Dashboard & Event Listing

```
Admin lands on /admin/dashboard.php
         │
         ▼
    auth_check.php → Verify session
         │
         ▼
    SELECT e.*, COUNT(r.id) AS registered_count
    FROM events e LEFT JOIN registrations r ...
    ORDER BY event_date ASC
         │
         ▼
    Render HTML table with columns:
    ID | Title | Category | Date | Venue | Capacity | Booked | Actions
                                                               │
                                                    ┌──────────┼──────────┐
                                                    ▼          ▼          ▼
                                                  [View]    [Edit]    [Delete]
```

### 3.3 Add Event

```
Admin clicks "+ Add New Event" on dashboard
         │
         ▼
    GET /admin/add_event.php → Renders empty form
         │
         ▼
    Admin fills: Title, Category (dropdown), Date, Description, Venue, Capacity
         │
         ▼
    Form POST → add_event.php handles it:
         │
         ├─ Validate: all required fields filled, capacity > 0
         │   └─ Fail → Show error, re-fill form with submitted values
         │
         ├─ INSERT INTO events (title, category, description, event_date, venue, capacity, banner_image)
         │
         └─ Redirect → dashboard.php?success=added → Green alert shown
```

### 3.4 Edit Event

```
Admin clicks "Edit" on an event row
         │
         ▼
    GET /admin/edit_event.php?id=3 → Fetches event from DB → Renders pre-filled form
         │
         ▼
    Admin modifies fields
         │
         ▼
    Form POST → edit_event.php handles it:
         │
         ├─ Validate fields
         ├─ UPDATE events SET title=?, category=?, ... WHERE id=?
         └─ Redirect → dashboard.php?success=updated → Green alert shown
```

### 3.5 Delete Event

```
Admin clicks "Delete" on an event row
         │
         ▼
    GET /admin/delete_event.php?id=3 → Shows confirmation page
         │
         ├─ Displays event title
         ├─ Warns: "This will also remove all student registrations"
         ├─ Two buttons: [Yes, Delete] [No, Go Back]
         │
         ▼
    Admin clicks "Yes, Delete"
         │
         ▼
    Form POST → delete_event.php handles it:
         │
         ├─ DELETE FROM events WHERE id = ?
         │   (registrations auto-deleted via ON DELETE CASCADE)
         │
         └─ Redirect → dashboard.php?success=deleted → Green alert shown
```

### 3.6 View Registrations

```
Admin clicks "View" on an event row
         │
         ▼
    GET /admin/view_registrations.php?id=3
         │
         ▼
    Fetches event details + all registrations:
    SELECT r.*, u.name, u.student_id, u.email
    FROM registrations r JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ?
         │
         ▼
    Renders:
    ┌──────────────────────────────────────────────┐
    │  Event Title                                 │
    │  📅 Date | 📍 Venue                          │
    │                                              │
    │  [Capacity: 50]  [Registered: 12]  [Left: 38]│
    └──────────────────────────────────────────────┘
    
    ┌────┬──────────────┬───────────┬───────────────┬──────────────┐
    │ #  │ Student Name │ Student ID│ Email         │ Registered At│
    ├────┼──────────────┼───────────┼───────────────┼──────────────┤
    │ 1  │ Aarav Sharma │ STU202401 │ aarav@cam...  │ Oct 18, 2026 │
    │ 2  │ Priya Patel  │ STU202419 │ priya@cam...  │ Oct 18, 2026 │
    └────┴──────────────┴───────────┴───────────────┴──────────────┘
```

### 3.7 Admin Logout

```
Admin clicks "Logout" in navbar
         │
         ▼
    /admin/logout.php → session_destroy() → Redirect to login.php
```

---

## 4. Database Schema & Relationships

```
┌──────────────┐       ┌──────────────────┐       ┌──────────────┐
│   events     │       │  registrations   │       │    users     │
├──────────────┤       ├──────────────────┤       ├──────────────┤
│ id (PK)      │◄──┐   │ id (PK)          │   ┌──►│ id (PK)      │
│ title        │   │   │ event_id (FK) ───┼───┘   │ name         │
│ category     │   └───┼─ user_id (FK) ───┼───────│ student_id   │
│ description  │       │ registered_at    │       │ email        │
│ event_date   │       │                  │       │ created_at   │
│ venue        │       │ UNIQUE(event_id, │       └──────────────┘
│ capacity     │       │        user_id)  │
│ banner_image │       └──────────────────┘
│ created_at   │
└──────────────┘

┌──────────────┐       ┌──────────────┐
│  email_otps  │       │    admins    │
├──────────────┤       ├──────────────┤
│ id (PK)      │       │ id (PK)      │
│ email        │       │ username     │
│ otp_code     │       │ password_hash│
│ expires_at   │       │ created_at   │
│ is_used      │       └──────────────┘
│ created_at   │
└──────────────┘
```

**Key constraints:**
- `registrations.UNIQUE(event_id, user_id)` → Prevents duplicate bookings at DB level
- `registrations.event_id` → `ON DELETE CASCADE` → Deleting an event removes all its registrations
- `registrations.user_id` → `ON DELETE CASCADE` → Deleting a user removes all their registrations
- `users.email` → `UNIQUE` → One account per email
- `admins.username` → `UNIQUE` → No duplicate admin usernames

---

## 5. Security Mechanisms

| Layer | Mechanism | Implementation |
|-------|-----------|----------------|
| **SQL Injection** | Prepared statements | All queries use `$pdo->prepare()` with `?` placeholders. `ATTR_EMULATE_PREPARES = false` enforces native preparation. |
| **XSS** | Output escaping | `htmlspecialchars()` in admin PHP pages. Custom `escapeHTML()` in `app.js` for client rendering. |
| **Race Conditions** | Row-level locking | `register.php` uses `SELECT ... FOR UPDATE` inside a transaction to prevent two users grabbing the last seat simultaneously. |
| **Duplicate Bookings** | DB constraint + app check | `UNIQUE(event_id, user_id)` at DB level. Application also checks before INSERT. `PDOException` code `23000` caught as fallback. |
| **Session Hijacking** | PHP sessions | OTP login sets `$_SESSION['user_id']`. Admin login sets `$_SESSION['admin_logged_in']`. Separate session namespaces. |
| **Unauthorized Cancellation** | Ownership verification | `cancel.php` checks `WHERE id = ? AND user_id = ?` — students can only cancel their own bookings. |
| **Admin Access Control** | Session guard | Every admin page requires `auth_check.php` which redirects unauthenticated users to `login.php`. |
| **Password Storage** | bcrypt hashing | Admin passwords stored via `password_hash()` (bcrypt), verified with `password_verify()`. |
| **Credential Protection** | .gitignore | `api/config.php` (SMTP credentials) excluded from version control. Only `config.example.php` is committed. |

---

## 6. Request-Response Flow Diagrams

### Student Registration (Full Journey)

```
Browser (app.js)                    Server (PHP/MySQL)
      │                                    │
      │──── GET get_events.php ───────────►│ Query events + seat counts
      │◄─── JSON [{event1}, {event2}] ─────│
      │                                    │
      │  User clicks "Register Pass →"     │
      │  (not logged in)                   │
      │                                    │
      │──── POST send_otp.php ────────────►│ Generate OTP
      │     {email: "student@uni.edu"}     │ Store in email_otps
      │◄─── {success: true} ──────────────│ Send via SMTP → Gmail
      │                                    │
      │  User enters OTP code              │
      │                                    │
      │──── POST verify_otp.php ──────────►│ Validate OTP
      │     {email, otp_code, name, id}    │ Find/create user
      │◄─── {success, user: {...}} ────────│ Set $_SESSION
      │                                    │
      │  Auto-triggers pending register    │
      │                                    │
      │──── POST register.php ────────────►│ BEGIN TRANSACTION
      │     {event_id: 3}                  │ Check duplicate
      │                                    │ Check capacity (FOR UPDATE)
      │                                    │ INSERT registration
      │◄─── {success, remaining_seats: 22}─│ COMMIT
      │                                    │
      │  DOM updated instantly             │
      │  (badge, progress bar, stats)      │
```

### Admin Event Management (Full Journey)

```
Browser                              Server (PHP)
      │                                    │
      │──── GET login.php ────────────────►│ Render login form
      │◄─── HTML login page ──────────────│
      │                                    │
      │──── POST login.php ───────────────►│ Verify credentials
      │     {username, password}           │ password_verify()
      │◄─── 302 Redirect dashboard.php ───│ Set $_SESSION
      │                                    │
      │──── GET dashboard.php ────────────►│ auth_check.php ✓
      │◄─── HTML events table ────────────│ Query all events + counts
      │                                    │
      │──── GET add_event.php ────────────►│ Render empty form
      │◄─── HTML form ────────────────────│
      │                                    │
      │──── POST add_event.php ───────────►│ Validate & INSERT
      │◄─── 302 Redirect dashboard.php ───│ ?success=added
      │                                    │
      │──── GET view_registrations.php ───►│ Query registrations
      │     ?id=3                          │ JOIN users
      │◄─── HTML student table ───────────│ Show stats + list
```
