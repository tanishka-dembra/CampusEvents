# CampusConnect | Campus Event Registration System 🎓

[![Tech Stack](https://img.shields.io/badge/Stack-HTML5%20%7C%20CSS3%20%7C%20JS%20%7C%20PHP%20%7C%20MySQL-blue?style=for-the-badge)](https://github.com)
[![Server](https://img.shields.io/badge/Server-XAMPP%20%2F%20Apache-orange?style=for-the-badge)](https://www.apachefriends.org/)
[![Database](https://img.shields.io/badge/Database-MySQL%20(InnoDB)-00758F?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)

A modern, responsive, and secure campus event portal built with **Vanilla HTML, CSS, JavaScript, PHP (PDO), and MySQL (XAMPP)**. The system dynamically computes real-time seat availability, strictly prevents duplicate or over-capacity bookings using atomic database locks, and enforces identity verification through passwordless 6-digit Email OTPs.

---

## 🌟 Key Features

1. **Live Dynamic Seat Tracking (AJAX / Fetch API):**
   - Remaining seats are calculated on the fly: `remaining_seats = capacity - COUNT(registrations)`.
   - Seat numbers, progress bars, and status badges (🟢 Available, 🟠 Filling Fast, 🔴 Housefull) update instantly with **zero page reloads**.

2. **Strict Duplicate & Over-Capacity Prevention:**
   - **Database Constraint:** Composite `UNIQUE(event_id, user_id)` guarantees at the database level that no student can book the same event twice.
   - **Atomic Concurrency Protection:** Utilizes SQL Transactions with row-level locking (`SELECT ... FOR UPDATE`) to prevent race conditions during simultaneous last-seat clicks.

3. **Passwordless Email OTP Authentication:**
   - Zero password vulnerabilities. Students verify their identity via a 6-digit OTP delivered directly to their college email through **Google SMTP** over TLS.
   - Session-bound authorization: Students can only cancel registrations belonging to their verified `$_SESSION['user_id']`.

4. **1-Click Registration & Cancellation:**
   - Authenticated students book passes in a single click without repetitive form filling.
   - Canceling a pass in the "My Bookings" portal instantly frees up the seat for other students in real-time.

5. **Client-Side Search & Filter:**
   - Real-time search by event title and venue.
   - Category filtering (*Tech, Workshops, Cultural, Sports, Career*).

---

## 🏗️ System Architecture & Database Schema

```
WEB DEV PROJECT/
├── index.html               # Main event portal & modal dialogs
├── css/
│   └── style.css            # Responsive dark-theme design system
├── js/
│   └── app.js               # Dynamic DOM updates, Fetch API & OTP logic
├── api/
│   ├── db.php               # PDO database connection (Prepared statements)
│   ├── mailer.php           # Native TLS Google SMTP mailer
│   ├── config.example.php   # Public configuration template
│   ├── send_otp.php         # Generates & dispatches OTP emails
│   ├── verify_otp.php       # Verifies OTP & starts PHP session
│   ├── get_current_user.php # Checks active session status
│   ├── logout.php           # Destroys session
│   ├── get_events.php       # Computes & returns events with live seats
│   ├── register.php         # Atomic registration with row locks
│   ├── cancel.php           # Secure pass cancellation
│   └── my_registrations.php # Fetches active student passes
└── database/
    └── schema.sql           # Database schema & sample events
```

### Relational Database Schema (`campus_events`):
* **`events`**: `id`, `title`, `category`, `description`, `event_date`, `venue`, `capacity`, `banner_image`.
* **`users`**: `id`, `name`, `student_id (UNIQUE)`, `email (UNIQUE)`.
* **`email_otps`**: `id`, `email`, `otp_code`, `expires_at`, `is_used`.
* **`registrations`**: `id`, `event_id`, `user_id`, `registered_at`, `UNIQUE(event_id, user_id)`.

---

## 🚀 Setup & Installation Instructions

### 1. Prerequisites
* Install [XAMPP](https://www.apachefriends.org/) (Apache + MySQL with PHP 8.x).

### 2. Database Setup
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin: `http://localhost/phpmyadmin`
3. Click the **SQL** tab.
4. Copy the contents of `database/schema.sql` and click **Go**.

### 3. Deploy to Apache
Link this repository to your Apache `htdocs` directory:
```cmd
mklink /J "C:\xampp\htdocs\campus-events" "path\to\WEB DEV PROJECT"
```

### 4. Configure Email SMTP (Optional for OTP delivery)
1. Copy `api/config.example.php` to `api/config.php`.
2. Add your Google App Password credentials:
   ```php
   define('SMTP_USER', 'your-email@campus.edu');
   define('SMTP_PASS', 'your-16-char-app-password');
   ```

### 5. Run the Application
Open your browser and navigate to:
```
http://localhost/campus-events/
```

---

## 🛡️ Security Highlights
* **SQL Injection Prevention:** 100% parameter-bound queries via PHP PDO (`PDO::ATTR_EMULATE_PREPARES => false`).
* **XSS Prevention:** Output sanitization on dynamic HTML rendering.
* **Credential Protection:** Private keys and passwords stored in `api/config.php` and excluded via `.gitignore`.

---

## 📄 License & Credits
Developed as part of the Web Development Campus Engineering Project (P3).
