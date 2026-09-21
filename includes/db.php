<?php

/**
 * ============================================================
 * FOUNDATION DAY VOTING SYSTEM
 * Database Configuration
 * ============================================================
 */

$host = "localhost";
$username = "root";
$password = "root";
$database = "coopApp";
$port = 3306;


/**
 * Create database connection
 */

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);


/**
 * Check connection
 */

if ($conn->connect_error) {

    die(
        "Database connection failed: " .
        $conn->connect_error
    );

}


/**
 * Set UTF-8 character encoding
 */

$conn->set_charset("utf8mb4");

?>
