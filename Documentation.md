# Smart Healthcare Appointment System Documentation

---

## Table of Contents

1. [Project Overview](#1-project-overview)  
2. [Features](#2-features)  
3. [System Architecture](#3-system-architecture)  
4. [Technology Stack](#4-technology-stack)  
5. [Database Schema](#5-database-schema-key-tables)  
6. [User Roles and Permissions](#6-user-roles-and-permissions)  
7. [Functionalities](#7-functionalities)  
8. [AI Integration](#8-ai-integration)  
9. [Email Integration](#9-email-integration)  
10. [Installation and Setup](#10-installation-and-setup)  
11. [Usage Instructions](#11-usage-instructions)  
12. [Security Considerations](#12-security-considerations)  
13. [Future Enhancements](#13-future-enhancements)  
14. [References](#14-references)  

---

## 1. Project Overview

The **Smart Healthcare Appointment System** is a web-based application designed to simplify and automate patient appointments with doctors. It leverages AI to predict doctor availability and provides an efficient booking process. The system supports multiple user roles including Admin, IT Admin, Doctor, and Patient, each with specific access and functionalities.

---

## 2. Features

- **User Authentication:** Secure login and registration for all users.  
- **Role-Based Dashboards:** Custom dashboards for Admin, IT Admin, Doctors, and Patients.  
- **Doctor Availability Management:** Doctors can set and update their availability.  
- **AI-Powered Availability Prediction:** AI predicts doctors' future availability based on past data.  
- **Appointment Booking:** Patients can search doctors by specialization, location, and book appointments.  
- **Notification System:** Email/SMS notifications for appointment confirmation, cancellations, and reminders.  
- **Admin Panel:** Manage users, clinics, doctors, and IT admins.  
- **Secure Session Management:** Uses cookies for sessions.  
- **Transactional Email Integration:** Using PHPMailer with MailerSend SMTP.  

---

## 3. System Architecture

- **Frontend:**  
  - PHP, HTML, CSS for landing pages and some user views.  
  - React.js for dynamic user dashboards and components.

- **Backend:**  
  - PHP for API and server logic.  
  - MySQL for database management.

- **AI Module:**  
  - Integrated OpenAI or custom AI to analyze historical doctor schedules and predict availability.

- **Email Service:**  
  - PHPMailer using MailerSend SMTP for transactional emails.

---

## 4. Technology Stack

| Layer         | Technology/Tool                                                   |
|---------------|--------------------------------------------------------------------|
| Frontend      | PHP, React, HTML, CSS, JavaScript, Tailwind CSS, Chart.js, intl-tel-input |
| Backend       | PHP                                                                |
| Database      | MySQL                                                              |
| AI Integration| Mistral AI model                                      |
| Email Service | PHPMailer, MailerSend SMTP                                         |
| Version Control | Git                                                              |


---

## 5. Database Schema (Key Tables)

- **users:** stores user info, roles, login credentials.  
- **doctor_availability:** tracks each doctor’s available dates and time slots.  
- **appointments:** stores booked appointment details.  
- **clinics:** stores clinic information.  
- **medical_reports:** stores reports created by doctors for patients.  
- **notifications:** manages system notifications.  

---

## 6. User Roles and Permissions

| Role      | Permissions                                            |
|-----------|--------------------------------------------------------|
| Admin     | Manage all users, clinics, system settings             |
| IT Admin  | Manage clinics, doctor accounts, clinic locations      |
| Doctor    | Manage availability, appointments, create reports      |
| Patient   | Search doctors, book appointments, view reports        |

---

## 7. Functionalities

### Admin Dashboard
- Add/view clinics  
- Manage users and IT admins  
- Oversee system activity  

### IT Admin Dashboard
- Edit clinic information (location, contact)  
- Manage doctor accounts  

### Doctor Dashboard
- Set/modify availability  
- View and manage appointments (accept, cancel, reschedule)  
- Create medical reports  
- AI availability prediction with confirmation modal  

### Patient Dashboard
- Search doctors by name, location, specialization  
- Book, view, cancel appointments  
- AI chatbot assistance for doctor search  

---

## 8. AI Integration

- Uses historical availability data to predict future availability.  
- Doctors select prediction range (e.g., next 7 days).  
- Prediction results shown in a modal with checkboxes for doctor confirmation.  
- Existing availability data is excluded from AI suggestions.  

---

## 9. Email Integration

- PHPMailer configured with MailerSend SMTP for transactional emails.  
- Emails include appointment confirmations, verification notices, and reminders.  
- Uses a verified domain with DKIM/SPF for deliverability.  

---

## 10. Installation and Setup

### Prerequisites
- PHP 8+  
- MySQL 8+  
- Composer  
- Node.js (for React frontend)  
- SMTP credentials for MailerSend  

### Steps
1. Clone the repository.  
2. Run `composer install` for PHP dependencies.  
3. Configure `.env` with database credentials, SMTP settings, and OpenAI API key.  
4. Import the database schema into MySQL.  
5. Run React frontend setup:  
   - `npm install`  
   - `npm start` or `npm build` for production  
6. Configure Apache/Nginx virtual host for PHP backend.  
7. Test email sending functionality.  
8. Launch the application.  

---

## 11. Usage Instructions

- Register as a patient or doctor.  
- Doctors verify their accounts and set availability.  
- Patients search doctors and book appointments.  
- Doctors manage bookings and generate medical reports.  
- Admins and IT admins manage clinics and users.  

---

## 12. Security Considerations

- Passwords hashed with `password_hash()`.  
- Input sanitization and prepared statements to prevent SQL Injection.  
- CSRF tokens for form submissions.  
- HTTPS recommended for deployment.  
- Session management with secure cookies.  
- Email verification for new users.  
- Rate limiting on API endpoints.  

---

## 13. Future Enhancements

- Integration with electronic medical records (EMR).  
- Telemedicine support.  
- Insurance claim processing.  
- More advanced AI for patient-doctor matching.  
- Mobile app integration.  

---

## 14. References

- [PHPMailer](https://github.com/PHPMailer/PHPMailer)  
- [MailerSend](https://www.mailersend.com/)  
- [OpenAI API Documentation](https://platform.openai.com/docs/)  
- [PHP Official Docs](https://www.php.net/manual/en/)  
- [React Official Docs](https://reactjs.org/docs/getting-started.html)
- [Dashboards](https://www.youtube.com/watch?app=desktop&v=g6HqL18plx4)
- [Tailwind CSS](https://tailwindcss.com/docs/)
- [Chart.js](https://www.chartjs.org/docs/)  
- [intl-tel-input](https://github.com/jackocnr/intl-tel-input) 
