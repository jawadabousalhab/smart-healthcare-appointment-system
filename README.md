
# Smart Healthcare Appointment System

A smart web-based appointment management system for healthcare providers and patients. This system uses AI to predict doctor availability and features role-based dashboards for Admin, IT Admin, Doctor, and Patient.

---

## 🚀 Features

- AI-based doctor availability prediction
- Patient appointment booking
- Doctor availability and scheduling dashboard
- Admin and IT admin panel
- Medical report generation
- Email notifications using PHPMailer 
- Responsive UI Tailwind CSS
- Secure login system with hashed passwords

---

## ⚙️ Technologies Used

- **Frontend**: HTML, CSS, JavaScript, Tailwind CSS, Chart.js, Intl-Tel-Input
- **Backend**: PHP
- **Database**: MySQL
- **AI**: MistralAI API (or custom logic)
- **Email Service**: PHPMailer 

---

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/smart-healthcare-system.git
cd smart-healthcare-system
```

### 2. Setup PHP Backend

- Ensure PHP 8+ and MySQL are installed.
- Import the provided SQL dump into **phpMyAdmin** (or MySQL CLI).
- Set up your config file with:
  - DB credentials
  - SMTP credentials
  - MistralAI API key
  - Google Maps API



## 👤 Admin Login Setup

To access the **Admin Dashboard**, you must manually insert an admin user into the `users` table in phpMyAdmin.

### 🔐 Required Fields:

| Field        | Value Example             |
|--------------|---------------------------|
| `email`      | `admin@example.com`       |
| `password`   | A **hashed password**     |
| `role`       | `admin`                   |
| `is_verified`|  `1`           |

### 🔧 How to Generate a Hashed Password

Run the following PHP snippet to hash your password:

```php
<?php
echo password_hash("yourPasswordHere", PASSWORD_DEFAULT);
```

Copy the generated hash and paste it in the `password` field in phpMyAdmin.

**Example SQL Insert:**

```sql
INSERT INTO users (name, email, password, role, status)
VALUES ('Admin', 'admin@example.com', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'admin', 'active');
```

> Replace the hashed string with your own generated hash.

---

## 🔒 Security Notes

- Never store plaintext passwords.
- Ensure `.env` and sensitive files are excluded via `.gitignore`.
- Use HTTPS in production.
- Protect your `/admin` and backend routes.


---

## 📬 Contact

For any questions or contributions, please open an issue or contact the maintainer.
