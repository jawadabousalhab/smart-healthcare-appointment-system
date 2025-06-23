<?php
session_start();
session_unset();
if (session_destroy()) // Destroying All Sessions
{
    header("Location: ../../index.html"); // Redirecting To Home Page
}
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}
header('Location: ../../index.php');
exit();


header('Location: login.html');
exit();
