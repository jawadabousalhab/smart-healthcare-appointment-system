-- Table structure for `activity_logs`
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `ai_logs`
CREATE TABLE `ai_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `related_appointment_id` int(11) DEFAULT NULL,
  `action_taken` varchar(255) DEFAULT NULL,
  `ai_reason` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `related_appointment_id` (`related_appointment_id`),
  CONSTRAINT `ai_logs_ibfk_1` FOREIGN KEY (`related_appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `ai_prediction_models`
CREATE TABLE `ai_prediction_models` (
  `model_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `model_data` longtext DEFAULT NULL,
  `last_trained` timestamp NULL DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  PRIMARY KEY (`model_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `ai_prediction_models_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `ai_settings`
CREATE TABLE `ai_settings` (
  `setting_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `enable_auto_scheduling` tinyint(1) DEFAULT 1,
  `enable_auto_cancellations` tinyint(1) DEFAULT 1,
  `sensitivity_level` enum('low','medium','high') DEFAULT 'medium',
  PRIMARY KEY (`setting_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `ai_settings_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `appointments`
CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `status` enum('pending','approved','cancelled','rescheduled','rejected','auto-cancelled') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `clinic_id` (`clinic_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `backups`
CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size` bigint(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`backup_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `backups`
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`) VALUES ('10','backup_2025-04-13_23-05-07.sql','12630','1','2025-04-14 00:05:07');

-- Table structure for `clinic_doctors`
CREATE TABLE `clinic_doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clinic_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_id` (`clinic_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `clinic_doctors_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`),
  CONSTRAINT `clinic_doctors_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `clinic_it_admins`
CREATE TABLE `clinic_it_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clinic_id` int(11) NOT NULL,
  `it_admin_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `clinic_id` (`clinic_id`),
  KEY `it_admin_id` (`it_admin_id`),
  CONSTRAINT `clinic_it_admins_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`),
  CONSTRAINT `clinic_it_admins_ibfk_2` FOREIGN KEY (`it_admin_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `clinic_it_admins`
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('14','7','15','2025-04-13 19:48:30');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('15','8','16','2025-04-13 19:55:06');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('16','1','18','2025-04-13 20:50:34');

-- Table structure for `clinics`
CREATE TABLE `clinics` (
  `clinic_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `map_coordinates` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`clinic_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `clinics_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `clinics`
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('1','3enaya','Rashhayaa','81751709','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('7','3enaya2','Rashayya','08561581','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('8','Bhmd','Rashaya','08561581','33.48664° N, 35.79563° E','1');

-- Table structure for `doctor_availability`
CREATE TABLE `doctor_availability` (
  `availability_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `status` enum('available','unavailable') DEFAULT 'available',
  PRIMARY KEY (`availability_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `doctor_availability_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `medical_reports`
CREATE TABLE `medical_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `medical_reports_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `medical_reports_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `medical_reports_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `password_resets`
CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `reset_code` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `system_logs`
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `system_settings`
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `maintenance_mode` enum('enabled','disabled') NOT NULL DEFAULT 'disabled',
  `backup_schedule` enum('daily','weekly','monthly') DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings`
INSERT INTO `system_settings` (`id`,`maintenance_mode`,`backup_schedule`,`updated_at`,`updated_by`) VALUES ('1','disabled','','2025-04-14 00:10:59','1');

-- Table structure for `users`
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','it_admin','doctor','patient') NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `needs_password_setup` tinyint(1) DEFAULT 0,
  `password_reset_code` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('1','Jawad Salhab','admin@shc.com','+96170939175',NULL,NULL,'$2y$10$YSgp61FQbu24NrbaHK/ux.dOCz0mBxqrJm0z5BkzBxhvBN80pdTu.','admin',NULL,'2025-04-12 13:42:14',NULL,NULL,'1',NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('12','Jawad Salhab','jawadsalhab67@gmail.com','+96170939175','Rashayya',NULL,'$2y$10$z1cfgQnND6dPJwR.a4rLcOjYCe/x042S2WGTASX0jyn1Zj7M80Vgi','patient',NULL,'2025-04-13 11:18:08','0',NULL,'1',NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('14','Jawad Salhab Ayman','jawad.abousalhab@mubs.edu.lb','+96170939175','Rashayya',NULL,'$2y$10$7QdqjS6dn4cXyZ0WOnpUwurWMcOGboCf5JYXsPCyua9Nc9iCMpanq','patient',NULL,'2025-04-13 11:30:20','0',NULL,'1',NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('15','Daniel','dani_it@shc.com','877878787','Rashayya',NULL,'$2y$10$ieiBMPb11pkjzpoNZqGaquw2a4DooCDugOf4YD2Hzo9QDrtjforo2','it_admin',NULL,'2025-04-13 17:15:00',NULL,NULL,'1',NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('16','3enaya2','3enaya_it@gmail.com','+96170939175',NULL,NULL,'$2y$10$ebTsGrYKJq823OMyGCbMouJpSogncPErMopZfOhuS85HKEoVFcXgK','it_admin',NULL,'2025-04-13 19:53:38','1',NULL,'1',NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`) VALUES ('18','Jawad Salhab','jawadabosalhab11@gmail.com','+96170000561581',NULL,NULL,'$2y$10$lCmnShVK9lpTBEPEZFvrt.BXFFeEF3BeHzjVUMx8xn//1cmoZR2JW','it_admin',NULL,'2025-04-13 20:20:09','1',NULL,'1',NULL);

