<?php
    $dbHost = getenv('DVMD_DB_HOST') ?: '127.0.0.1';
    $dbUser = getenv('DVMD_DB_USER') ?: 'root';
    $dbPass = getenv('DVMD_DB_PASSWORD') ?: '';
    $dbName = getenv('DVMD_DB_NAME') ?: 'dvmd_db';
    $dbPort = (int)(getenv('DVMD_DB_PORT') ?: 3307);

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

    if ($conn->connect_error) {
        die("Could not connect to mysql: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
?>
