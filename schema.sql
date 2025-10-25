-- Lodgie - Hostel Booking System Database Schema
-- Author: Professional Senior Full-Stack PHP Developer
-- Engine: InnoDB
-- Collation: utf8mb4_unicode_ci

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `lodgie_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `lodgie_db`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
-- Stores all users (admins, landlords, tenants)
--
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('admin', 'landlord', 'tenant') NOT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hostels`
-- Stores all hostel listings
--
CREATE TABLE `hostels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `landlord_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `price_per_month` DECIMAL(10, 2) NOT NULL,
  `amenities` TEXT DEFAULT NULL, -- Stored as JSON or comma-separated string
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`landlord_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hostel_images`
-- Stores multiple images for each hostel
--
CREATE TABLE `hostel_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hostel_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_thumbnail` BOOLEAN NOT NULL DEFAULT 0,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`hostel_id`) REFERENCES `hostels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
-- Stores booking information
--
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `hostel_id` INT NOT NULL,
  `landlord_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `duration_months` INT NOT NULL DEFAULT 1,
  `total_price` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('pending', 'paid', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hostel_id`) REFERENCES `hostels`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`landlord_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
-- Stores payment transactions from Paystack
--
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `tenant_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `paystack_reference` VARCHAR(100) NOT NULL UNIQUE,
  `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
-- Stores tenant reviews for hostels
--
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `tenant_id` INT NOT NULL,
  `hostel_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `tenant_booking_review` (`tenant_id`, `booking_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hostel_id`) REFERENCES `hostels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
-- Stores system-wide notifications for all users
--
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` BOOLEAN NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Insert Sample Data
--

-- Admin User (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`)
VALUES
('Admin User', 'admin@lodgie.com', '$2y$10$fWJ.xgeBM.sNPYY1yE.aUuJ.3lwn.M/vjdg3iP5.vveY.qL.gBQuC', '1234567890', 'admin', 'active');

-- Landlord User (password: landlord123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`)
VALUES
('Landlord User', 'landlord@lodgie.com', '$2y$10$7Q9F4oT.eN.P19XmP9i0eubv/5JqgoH6m6eG3v9o.Qz/./f.j/0Uq', '0987654321', 'landlord', 'active');

-- Tenant User (password: tenant123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`)
VALUES
('Tenant User', 'tenant@lodgie.com', '$2y$10$yI2m.yL.7E.XyB0v.u3Mh.Jc9E.k8b.w.9/0Q.o.F.k/0L.o.c/mK', '1122334455', 'tenant', 'active');

COMMIT;