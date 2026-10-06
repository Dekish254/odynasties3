CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 phone VARCHAR(40), blood_group ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NULL,
 password_hash VARCHAR(255) NOT NULL, role ENUM('member','admin') NOT NULL DEFAULT 'member',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', profile_picture VARCHAR(255) NULL,
 bio TEXT NULL, location VARCHAR(160) NULL, must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS donations (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,name VARCHAR(120) NOT NULL,email VARCHAR(190),phone VARCHAR(40),blood_group VARCHAR(10) NOT NULL,donation_date DATE NULL,location VARCHAR(160),status ENUM('registered','completed') DEFAULT 'registered',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS events (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(180) NOT NULL,event_date DATE NOT NULL,start_time TIME NULL,end_time TIME NULL,location VARCHAR(180),description TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS news (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200) NOT NULL,excerpt TEXT,image VARCHAR(255),published_at DATE NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL,subject VARCHAR(200),message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS login_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,session_token CHAR(64) NOT NULL UNIQUE,role ENUM('member','admin') NOT NULL,last_seen DATETIME NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,revoked_at DATETIME NULL,INDEX idx_user_active(user_id,revoked_at,last_seen),CONSTRAINT fk_login_sessions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 admin_id INT NULL,
 admin_name VARCHAR(120) NULL,
 action VARCHAR(160) NOT NULL,
 details TEXT NULL,
 ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_admin_created(admin_id, created_at),
 INDEX idx_created(created_at),
 CONSTRAINT fk_admin_activity_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_requests (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,title VARCHAR(180) NOT NULL,category VARCHAR(80) NOT NULL,description TEXT NOT NULL,status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',admin_note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_support_status(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO events(title,event_date,start_time,end_time,location,description) SELECT 'Community Meetup – Nairobi','2026-11-15','10:00:00','14:00:00','Nairobi, Kenya','Meet O group members and partners.' WHERE NOT EXISTS (SELECT 1 FROM events WHERE title='Community Meetup – Nairobi');
INSERT INTO events(title,event_date,start_time,end_time,location,description) SELECT 'Blood Drive Campaign','2026-11-28','09:00:00','16:00:00','Kisumu, Kenya','Community blood donation drive.' WHERE NOT EXISTS (SELECT 1 FROM events WHERE title='Blood Drive Campaign');
INSERT INTO news(title,excerpt,image,published_at) SELECT 'The Power of O: Why Our Blood Group Makes Us Special','Learn how the O blood group community can connect, support and give back.','news-blood.svg','2026-10-05' WHERE NOT EXISTS (SELECT 1 FROM news WHERE title LIKE 'The Power of O:%');
INSERT INTO news(title,excerpt,image,published_at) SELECT 'Successful Blood Donation Drive in Nakuru','A successful community donation event brought members together.','news-drive.svg','2026-09-20' WHERE NOT EXISTS (SELECT 1 FROM news WHERE title LIKE 'Successful Blood Donation%');
INSERT INTO news(title,excerpt,image,published_at) SELECT 'Odynasties Community Grows to 10,000+ Members','Our community continues to grow through connection and support.','news-community.svg','2026-09-10' WHERE NOT EXISTS (SELECT 1 FROM news WHERE title LIKE 'Odynasties Community Grows%');
