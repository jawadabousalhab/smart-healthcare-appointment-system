<?php
// AuthMiddleware.php



class AuthMiddleware
{
    public static function check()
    {
        // Check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
            // Not logged in — redirect to login page
            header('Location: ../views/auth/login.html');
            exit();
        }
    }

    public static function checkAdmin()
    {
        // Check if user is authenticated and is admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            // Redirect to not authorized or login page
            header('Location: unauthorized.html');
            exit();
        }
    }
}
