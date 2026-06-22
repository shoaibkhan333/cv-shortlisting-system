<?php
/**
 * Copy this file to includes/db_config_live.php on your free host,
 * fill in values from your hosting control panel, then rename or replace db_config.php.
 *
 * InfinityFree: hPanel → MySQL Databases → copy Host, Database name, Username, Password
 */

define('DB_HOST', 'sqlXXX.infinityfree.com');  // from hosting panel (sometimes localhost)
define('DB_USER', 'epiz_XXXXXX');              // your MySQL username
define('DB_PASS', 'your_mysql_password');      // your MySQL password
define('DB_NAME', 'epiz_XXXXXX_cvshort');      // your database name

function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

function redirect($url) {
    header("Location: $url");
    exit();
}
