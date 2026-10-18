-- ========================================================
-- Campus Event Registration System
-- Database: campus_events (Updated with OTP & Users)
-- Engine: InnoDB (required for Foreign Keys & Transactions)
-- ========================================================

CREATE DATABASE IF NOT EXISTS campus_events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_events;

-- Drop tables in child-to-parent order
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS email_otps;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS events;

-- --------------------------------------------------------
-- 1. Table structure for table: events
-- --------------------------------------------------------
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    venue VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    banner_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Table structure for table: users (Students)
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table structure for table: email_otps (Passwordless Auth)
-- --------------------------------------------------------
CREATE TABLE email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_otp (email, otp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table structure for table: registrations (Event Bookings)
-- --------------------------------------------------------
CREATE TABLE registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- UNIQUE Constraint: Guarantees a student cannot register twice for the same event
    CONSTRAINT unique_user_event UNIQUE (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Seed Data: Campus Events
-- --------------------------------------------------------
INSERT INTO events (id, title, category, description, event_date, venue, capacity, banner_image) VALUES
(1, 'AI & Web3 Innovation Hackathon', 'Tech', 'A 24-hour intense hackathon where student teams build real-world AI, Web3, and Full-Stack solutions. Mentors from top tech firms will be present.', '2026-11-15 09:00:00', 'Tech Auditorium A', 50, 'hackathon.jpg'),
(2, 'Hands-on UI/UX Design Workshop', 'Workshop', 'Learn modern Figma workflows, component-driven design systems, and rapid prototyping principles with live critiques.', '2026-11-18 14:00:00', 'Design Studio Lab 3', 5, 'workshop.jpg'),
(3, 'Inter-College Robotics Combat', 'Tech', 'Watch custom combat robots fight in the battle arena for supremacy. Wireless bots up to 15kg weight category allowed.', '2026-11-22 10:30:00', 'Mechanical Workshop Arena', 25, 'robotics.jpg'),
(4, 'Annual Campus Cultural Fest', 'Cultural', 'An electrifying evening of live music, dance performances, stand-up comedy, and food stalls showcasing campus talent.', '2026-11-25 18:00:00', 'Open Air Amphitheatre', 100, 'cultural.jpg'),
(5, 'Speed Chess Championship', 'Sports', 'Rapid 5-minute blitz chess tournament with FIDE rated arbiters. Cash prizes and trophies for the top three rankers.', '2026-11-28 11:00:00', 'Student Activity Center', 16, 'chess.jpg'),
(6, 'Industry Career & Internship Expo', 'Career', 'Meet hiring managers, tech recruiters, and industry leaders from 20+ partner tech companies looking for campus talent.', '2026-12-02 10:00:00', 'Main Convention Hall', 40, 'career.jpg');

-- --------------------------------------------------------
-- 6. Seed Data: Sample Students
-- --------------------------------------------------------
INSERT INTO users (id, name, student_id, email) VALUES
(1, 'Aarav Sharma', 'STU202401', 'aarav.sharma@campus.edu'),
(2, 'Priya Patel', 'STU202419', 'priya.patel@campus.edu');

-- --------------------------------------------------------
-- 7. Seed Data: Sample Registrations
-- Event 2 (Capacity 5) has 2 registered students -> 3 seats left!
-- --------------------------------------------------------
INSERT INTO registrations (event_id, user_id) VALUES
(2, 1),
(2, 2);
