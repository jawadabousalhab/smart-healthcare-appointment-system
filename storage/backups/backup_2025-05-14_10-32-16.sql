-- Table structure for `activity_logs`
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_activity_logs_user` (`user_id`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `activity_logs`
INSERT INTO `activity_logs` (`log_id`,`user_id`,`action`,`description`,`ip_address`,`created_at`) VALUES ('21','35','backup_created','Created backup: backup_20250423_120342.sql','::1','2025-04-23 13:03:42');
INSERT INTO `activity_logs` (`log_id`,`user_id`,`action`,`description`,`ip_address`,`created_at`) VALUES ('22','35','doctor_updated','Updated doctor: daniel mhanna','::1','2025-04-24 13:37:59');
INSERT INTO `activity_logs` (`log_id`,`user_id`,`action`,`description`,`ip_address`,`created_at`) VALUES ('23','28','appointment_booked','Booked appointment #10 with doctor #27','::1','2025-05-08 15:12:45');

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
  `status` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `clinic_id` (`clinic_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `appointments`
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('2','12','27','1','2025-04-17','23:36:46','completed','Null','next week','2025-04-16 15:38:01');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('3','12','27','1','2025-04-15','23:30:38','completed','Null','Null','2025-04-16 16:31:16');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('4','12','27','1','2025-04-19','08:03:44','completed','Null','2025-04-18 13:04:24','2025-04-18 13:04:24');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('5','28','27','1','2025-04-30','10:00:00','cancelled','like taht\n',NULL,'2025-04-19 13:27:17');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('6','28','27','1','2025-05-10','02:00:00','cancelled','like taht\n',NULL,'2025-04-19 13:28:28');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('7','28','27','1','2025-04-21','01:20:00','completed','issue\n',NULL,'2025-04-19 14:58:16');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('8','28','27','1','2025-04-22','01:20:00','completed','null',NULL,'2025-04-19 15:53:08');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('9','28','27','1','2025-05-04','14:40:46','Confirmed','null','null','2025-05-03 12:43:57');
INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`clinic_id`,`appointment_date`,`appointment_time`,`status`,`reason`,`notes`,`created_at`) VALUES ('10','28','27','1','2025-05-09','14:00:00','completed','Hello','hello','2025-05-08 15:12:45');

-- Table structure for `backups`
CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size` bigint(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'full',
  `description` text DEFAULT NULL,
  PRIMARY KEY (`backup_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `backups`
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('10','backup_2025-04-13_23-05-07.sql','12630','1','2025-04-14 00:05:07','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('11','backup_2025-04-13_23-38-42.sql','12830','1','2025-04-14 00:38:42','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('12','backup_2025-04-14_11-08-30.sql','13621','1','2025-04-14 12:08:30','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('21','backup_20250423_120342.sql','3522295','35','2025-04-23 13:03:42','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('22','backup_2025-04-24_12-31-05.sql','31343','1','2025-04-24 13:31:05','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('23','backup_2025-04-24_14-28-30.sql','31638','1','2025-04-24 15:28:30','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('24','backup_2025-05-14_10-24-22.sql','0','1','2025-05-14 11:24:22','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('25','backup_2025-05-14_10-26-58.sql','0','1','2025-05-14 11:26:58','full',NULL);
INSERT INTO `backups` (`backup_id`,`filename`,`size`,`created_by`,`created_at`,`type`,`description`) VALUES ('26','backup_2025-05-14_10-31-07.sql','0','1','2025-05-14 11:31:07','full',NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `clinic_doctors`
INSERT INTO `clinic_doctors` (`id`,`clinic_id`,`doctor_id`) VALUES ('1','1','27');
INSERT INTO `clinic_doctors` (`id`,`clinic_id`,`doctor_id`) VALUES ('3','1','31');
INSERT INTO `clinic_doctors` (`id`,`clinic_id`,`doctor_id`) VALUES ('4','1','29');
INSERT INTO `clinic_doctors` (`id`,`clinic_id`,`doctor_id`) VALUES ('5','13','29');
INSERT INTO `clinic_doctors` (`id`,`clinic_id`,`doctor_id`) VALUES ('6','10','29');

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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `clinic_it_admins`
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('14','7','15','2025-04-13 19:48:30');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('15','8','16','2025-04-13 19:55:06');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('17','9','15','2025-04-14 18:06:44');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('20','10','35','2025-04-23 11:58:02');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('21','11','16','2025-04-24 13:29:20');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('22','12','15','2025-04-24 13:29:26');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('23','13','35','2025-04-24 13:29:33');
INSERT INTO `clinic_it_admins` (`id`,`clinic_id`,`it_admin_id`,`assigned_at`) VALUES ('24','1','35','2025-04-24 15:27:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `clinics`
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('1','3enaya','Rashhayaa','81751709','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('7','3enaya2','Rashayya','08561581','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('8','Bhmd','Rashaya','08561581','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('9','Medical Clinic','Beirut','+96170000561581','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('10','undefined','Rashaya','+96181751709','33.48664° N, 35.79563° E','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('11','Rashayya','West Bekaa','+96108561370','40.5348-10.18446','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('12','Rashayya','West Bekaa','+96108561370','40.5348-10.18446','1');
INSERT INTO `clinics` (`clinic_id`,`name`,`location`,`phone_number`,`map_coordinates`,`created_by`) VALUES ('13','Rashayya','West Bekaa','+96108561370','40.5348-10.18446','1');

-- Table structure for `doctor_availability`
CREATE TABLE `doctor_availability` (
  `availability_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) DEFAULT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `status` enum('available','unavailable') DEFAULT 'available',
  PRIMARY KEY (`availability_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `fk_availability_clinic` (`clinic_id`),
  CONSTRAINT `doctor_availability_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_availability_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `doctor_availability`
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('39','27','1','2025-04-29','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('40','27','1','2025-04-30','09:00:00','17:00:00','unavailable');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('41','27','1','2025-05-01','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('42','27','1','2025-05-02','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('56','27','1','2025-05-01','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('57','27','1','2025-05-02','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('58','27','1','2025-05-03','09:00:00','17:00:00','unavailable');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('59','27','1','2025-05-04','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('60','27','1','2025-05-05','09:00:00','17:00:00','unavailable');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('61','27','1','2025-05-06','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('62','27','1','2025-05-07','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('63','27','1','2025-05-08','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('64','27','1','2025-05-09','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('65','27','1','2025-05-10','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('66','27','1','2025-05-11','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('67','27','1','2025-05-12','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('68','27','1','2025-05-13','09:00:00','17:00:00','available');
INSERT INTO `doctor_availability` (`availability_id`,`doctor_id`,`clinic_id`,`date`,`start_time`,`end_time`,`status`) VALUES ('69','27','1','2025-05-14','09:00:00','17:00:00','unavailable');

-- Table structure for `medical_reports`
CREATE TABLE `medical_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `report_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `medical_reports_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `medical_reports_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `medical_reports_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `medical_reports`
INSERT INTO `medical_reports` (`report_id`,`doctor_id`,`patient_id`,`appointment_id`,`diagnosis`,`prescription`,`report_date`,`report_type`,`notes`,`file_path`) VALUES ('14','27','28','6','Her stomach is dirty','panadol\r\npanadol women fuck fuck','2025-04-28','lab_result','panadol','../../controllers/doctors/medical_reports/report_1745845875_88212dea.pdf');
INSERT INTO `medical_reports` (`report_id`,`doctor_id`,`patient_id`,`appointment_id`,`diagnosis`,`prescription`,`report_date`,`report_type`,`notes`,`file_path`) VALUES ('15','27','12','2','hello','panadol','2025-04-29','progress','hello','../../controllers/doctors/medical_reports/report_1745916094_8f2d20a7.pdf');
INSERT INTO `medical_reports` (`report_id`,`doctor_id`,`patient_id`,`appointment_id`,`diagnosis`,`prescription`,`report_date`,`report_type`,`notes`,`file_path`) VALUES ('16','27','12','2','hello','panadol','2025-04-29','progress','hello','../../controllers/doctors/medical_reports/report_1745916095_3b880cd6.pdf');
INSERT INTO `medical_reports` (`report_id`,`doctor_id`,`patient_id`,`appointment_id`,`diagnosis`,`prescription`,`report_date`,`report_type`,`notes`,`file_path`) VALUES ('17','27','12','3','hello evwee','panadol extra','2025-04-29','lab_result','hello world','../../controllers/doctors/medical_reports/report_1745916166_32f11765.pdf');
INSERT INTO `medical_reports` (`report_id`,`doctor_id`,`patient_id`,`appointment_id`,`diagnosis`,`prescription`,`report_date`,`report_type`,`notes`,`file_path`) VALUES ('18','27','12','3','hello evwee','panadol extra','2025-04-29','lab_result','hello world','../../controllers/doctors/medical_reports/report_1745916166_c95cf7a1.pdf');

-- Table structure for `notifications`
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('alert','message','system','success','warning') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `notifications`
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('7','1','IT Updated ','IT updated Succesfully','success','1','2025-04-14 23:18:32');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('8','1','System Updated ','System Settings updated Succesfully','system','1','2025-04-14 23:37:44');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('9','1','Clinic Updated ','Clinic was added Succesfully','success','1','2025-04-14 23:46:52');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('10','1','System Updated ','System Settings updated Succesfully','system','1','2025-04-15 00:40:31');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('11','1','System Updated ','System Settings updated Succesfully','system','1','2025-04-15 00:40:36');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('12','1','IT Unassigned ','IT unassigned Succesfully','success','1','2025-04-15 11:47:49');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('13','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-15 11:48:12');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('14','1','IT Addition','IT added Succesfully','success','1','2025-04-21 11:30:44');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('15','1','Admin Added ','Admin added successfully','success','1','2025-04-22 21:04:45');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('16','1','IT Unassigned ','IT unassigned Succesfully','success','1','2025-04-22 23:49:37');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('17','1','IT Updated ','IT updated Succesfully','success','1','2025-04-23 00:05:28');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('18','1','IT Updated ','IT updated Succesfully','success','1','2025-04-23 00:08:31');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('19','1','IT Addition','IT added Succesfully','success','1','2025-04-23 00:09:02');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('20','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-23 11:23:02');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('21','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-23 11:58:02');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('22','1','Clinic Updated ','Clinic was added Succesfully','success','1','2025-04-24 13:23:59');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('23','1','Clinic Updated ','Clinic was added Succesfully','success','1','2025-04-24 13:24:03');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('24','1','Clinic Updated ','Clinic was added Succesfully','success','1','2025-04-24 13:24:24');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('25','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-24 13:29:20');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('26','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-24 13:29:26');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('27','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-24 13:29:33');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('28','1','Backup Created ','Backup created Succesfully','success','1','2025-04-24 13:31:05');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('29','1','IT Unassigned ','IT unassigned Succesfully','success','1','2025-04-24 15:26:50');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('30','1','IT assigned ','IT assigned Succesfully','warning','1','2025-04-24 15:27:06');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('31','1','Backup Created ','Backup created Succesfully','success','1','2025-04-24 15:28:30');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('32','1','Backup Created ','Backup created Succesfully','success','0','2025-05-14 11:24:22');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('33','1','Backup Created ','Backup created Succesfully','success','0','2025-05-14 11:26:58');
INSERT INTO `notifications` (`id`,`user_id`,`title`,`message`,`type`,`is_read`,`created_at`) VALUES ('34','1','Backup Created ','Backup created Succesfully','success','0','2025-05-14 11:31:07');

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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `system_logs`
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_logs`
INSERT INTO `system_logs` (`log_id`,`action`,`description`,`created_at`) VALUES ('19','Logs Cleared','All system logs were cleared.','2025-04-24 13:32:05');
INSERT INTO `system_logs` (`log_id`,`action`,`description`,`created_at`) VALUES ('20','Backup Created','A new backup \"backup_2025-04-24_14-28-30.sql\" was created.','2025-04-24 15:28:30');
INSERT INTO `system_logs` (`log_id`,`action`,`description`,`created_at`) VALUES ('21','Backup Created','A new backup \"backup_2025-05-14_10-24-22.sql\" was created.','2025-05-14 11:24:22');
INSERT INTO `system_logs` (`log_id`,`action`,`description`,`created_at`) VALUES ('22','Backup Created','A new backup \"backup_2025-05-14_10-26-58.sql\" was created.','2025-05-14 11:26:58');
INSERT INTO `system_logs` (`log_id`,`action`,`description`,`created_at`) VALUES ('23','Backup Created','A new backup \"backup_2025-05-14_10-31-07.sql\" was created.','2025-05-14 11:31:07');

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
INSERT INTO `system_settings` (`id`,`maintenance_mode`,`backup_schedule`,`updated_at`,`updated_by`) VALUES ('1','disabled','monthly','2025-04-15 00:40:36','1');

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
  `remember_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `notification_settings` text DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('1','Jawad Salhab','admin@shc.com','+96170939175',NULL,'../../uploads/profiles/admin_1_1744632351.png','$2y$10$9YhLmKXg21x2areXn4MGtOCvhqHYcMI1s3nEXP615u5/Vqezw9fK2','admin',NULL,'2025-04-12 13:42:14',NULL,NULL,'1',NULL,'3defa8389ba7c693abb46107257270931a77034ca1ce1628e59b934b781bbda4','2025-05-19 21:12:46','{\"email_system\":1,\"email_security\":1,\"email_updates\":0,\"app_system\":1,\"app_security\":1,\"app_activity\":1}');
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('12','Jawad Salhab','jawadsalhab67@gmail.com','+96170939175','Rashayya',NULL,'$2y$10$z1cfgQnND6dPJwR.a4rLcOjYCe/x042S2WGTASX0jyn1Zj7M80Vgi','patient',NULL,'2025-04-13 11:18:08','0',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('15','Daniel','dani_it@shc.com','877878787','Rashayya',NULL,'$2y$10$ieiBMPb11pkjzpoNZqGaquw2a4DooCDugOf4YD2Hzo9QDrtjforo2','it_admin',NULL,'2025-04-13 17:15:00',NULL,NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('16','3enaya2','3enaya_it@gmail.com','+96170939175',NULL,NULL,'$2y$10$ebTsGrYKJq823OMyGCbMouJpSogncPErMopZfOhuS85HKEoVFcXgK','it_admin',NULL,'2025-04-13 19:53:38','1',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('22','Jinan Abou Salhab ','majoudisalhab96@gmail.com','+96171250738',NULL,NULL,'$2y$10$BqVCls6hPTtYO97RLZn2B.5SQO2Fd753jzH6dNWZxXGucaC6.0oxi','it_admin',NULL,'2025-04-14 18:45:34','1',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('26','Ayman Salhab','ayman@gmail.com','+96170939175',NULL,NULL,'$2y$10$1PSzPFaERSvgRamtvo/NOeKxlfeD6PllU7SrPv6jpjhNpcvTGmWF2','it_admin',NULL,'2025-04-14 22:56:00','1',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('27','Ali AL Takki','jawad.abousalhab@mubs.edu.lb','+9618171798',NULL,'profile_27_1744983206.png','$2y$10$6DYfTQkL0auHhqqmCiAlEe88g.D8M59Qpi8a5D6pepK5KwYV3AeOe','doctor','Dentist','2025-04-15 13:34:49','0',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('28','Jawad Salhab Ayman','patinet1@shc.com','+961708921','Rashayya',NULL,'$2y$10$vVs8ly92jcAq.QOI1lYjb.LdQOGTjAQnlGQIm0XK8md.LK8dMQLhu','patient',NULL,'2025-04-19 12:32:01','0',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('29','daniel mhanna','daniel.mhanna@std.balamand.edu.lb','+96171506091',NULL,NULL,'$2y$10$DmkNOp5Z9IGbB3TaXV10VOqcMI25aId45c07wW6X5z/KdE44cUQR6','doctor','neurosurgeon','2025-04-20 00:36:26','0',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('31','Daniel Mhanna','danielnightlion123@gmail.com','+96171506091',NULL,NULL,'$2y$10$R1uM.NM8sq1WFU2w79NjneH2S6194B.0N1DwzTTIwajG4CQdNh0J.','doctor','neurosurgeon','2025-04-20 00:45:30','0',NULL,'1',NULL,NULL,NULL,NULL);
INSERT INTO `users` (`user_id`,`name`,`email`,`phone_number`,`address`,`profile_picture`,`password`,`role`,`specialization`,`created_at`,`needs_password_setup`,`password_reset_code`,`is_verified`,`verification_code`,`remember_token`,`token_expiry`,`notification_settings`) VALUES ('35','Jawad Salhab','jawadabosalhab11@gmail.com','+96170000561581',NULL,'profile_35_1745405011.png','$2y$10$/AUd9HAQtcaO.ntQenGQ6OtuM2qNgsXv0e6IkuRxcnFjZkUsYpI6a','it_admin',NULL,'2025-04-23 00:08:59','1',NULL,'1',NULL,NULL,NULL,NULL);

