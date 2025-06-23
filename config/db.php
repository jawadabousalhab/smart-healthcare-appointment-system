<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smarthealthcare');
define('DB_USER', 'root');
define('DB_PASS', '');
function getPDO()
{
    $host = 'localhost';
    $db   = 'smarthealthcare';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET time_zone = '+03:00';");  // Set MySQL time zone (if not already set)
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}
