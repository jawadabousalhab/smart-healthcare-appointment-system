<?php
require_once  '../../vendor/autoload.php';
require_once '../../utils/mailer.php';

function getDoctorName($doctorId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->execute([$doctorId]);
    return $stmt->fetchColumn();
}

function sendRescheduleEmail($patientEmail, $patientName, $date, $time, $doctorId)
{
    try {
        $doctorName = getDoctorName($doctorId);

        $subject = "Appointment Reschedule Notification";

        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .header { color: #2c3e50; font-size: 18px; margin-bottom: 20px; }
                    .content { margin-bottom: 20px; }
                    .footer { color: #7f8c8d; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='header'>Appointment Reschedule Notification</div>
                <div class='content'>
                    <p>Dear $patientName,</p>
                    <p>Your appointment with Dr. $doctorName has been rescheduled to <strong>$date at $time</strong>.</p>
                    <p>Please log in to your Smart Healthcare account to view or manage your appointment.</p>
                    <p>If this change doesn't work for you, please contact us to arrange an alternative time.</p>
                </div>
                <div class='footer'>
                    <p>Thank you,</p>
                    <p>The Smart Healthcare Team</p>
                </div>
            </body>
            </html>
        ";

        return sendEmail($patientEmail, $subject, $message);
    } catch (Exception $e) {
        error_log("Failed to send reschedule email: " . $e->getMessage());
        return false;
    }
}
function sendRescheduleRequestEmail($patientEmail, $patientName, $originalDate, $originalTime, $doctorId, $appointmentId)
{
    try {
        $doctorName = getDoctorName($doctorId);
        $formattedDate = date('F j, Y', strtotime($originalDate));
        $formattedTime = date('g:i a', strtotime($originalTime));

        $subject = "Appointment Reschedule Request";
        $loginUrl = "https://yourdomain.com/patient/login"; // Update with your actual URL

        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .header { color: #2c3e50; font-size: 18px; margin-bottom: 20px; }
                    .content { margin-bottom: 20px; }
                    .button { 
                        display: inline-block; 
                        padding: 10px 20px; 
                        background-color: #3b82f6; 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 5px; 
                        margin-top: 15px;
                    }
                    .footer { color: #7f8c8d; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='header'>Appointment Reschedule Request</div>
                <div class='content'>
                    <p>Dear $patientName,</p>
                    <p>Dr. $doctorName has requested to reschedule your appointment originally scheduled for:</p>
                    <p><strong>$formattedDate at $formattedTime</strong></p>
                    <p>Please log in to your account to select a new appointment time that works for you.</p>
                    <a href='$loginUrl' class='button'>Reschedule Appointment</a>
                    <p>If you have any questions, please contact our office.</p>
                </div>
                <div class='footer'>
                    <p>Thank you,</p>
                    <p>The Smart Healthcare Team</p>
                </div>
            </body>
            </html>
        ";

        return sendEmail($patientEmail, $subject, $message);
    } catch (Exception $e) {
        error_log("Failed to send reschedule request email: " . $e->getMessage());
        return false;
    }
}
