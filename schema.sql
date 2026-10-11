CREATE DATABASE IF NOT EXISTS paibaekpea_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE paibaekpea_db;
CREATE TABLE IF NOT EXISTS users (
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(30) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 fullname VARCHAR(100) NOT NULL,
 bio TEXT NOT NULL,
 avatar VARCHAR(64) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS categories (
 category_id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS trips (
 trip_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 category_id INT NOT NULL,
 title VARCHAR(150) NOT NULL,
 description TEXT NOT NULL,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 visibility ENUM('public','private') NOT NULL DEFAULT 'private',
 cover VARCHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
 FOREIGN KEY(category_id) REFERENCES categories(category_id),
 INDEX dates_visibility(visibility,start_date,end_date),
 INDEX owner(user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS trip_places (
 place_id INT AUTO_INCREMENT PRIMARY KEY,
 trip_id INT NOT NULL,
 name VARCHAR(150) NOT NULL,
 province VARCHAR(100) NOT NULL,
 visit_date DATE NOT NULL,
 story TEXT NOT NULL,
 sort_order INT NOT NULL DEFAULT 1,
 FOREIGN KEY(trip_id) REFERENCES trips(trip_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS place_photos (
 photo_id INT AUTO_INCREMENT PRIMARY KEY,
 place_id INT NOT NULL,
 filename VARCHAR(64) NOT NULL UNIQUE,
 FOREIGN KEY(place_id) REFERENCES trip_places(place_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookmarks (
 user_id INT NOT NULL,
 trip_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,trip_id),
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
 FOREIGN KEY(trip_id) REFERENCES trips(trip_id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO categories(category_id,name) VALUES
(1,'ธรรมชาติและภูเขา'),(2,'ทะเลและชายหาด'),(3,'วัฒนธรรมและเมือง'),(4,'คาเฟ่และอาหาร'),(5,'ผจญภัย');
