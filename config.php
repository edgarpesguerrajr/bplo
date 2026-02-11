<?php

$host = "localhost";
$user = "root";
$password = "";
$database   = "bplo_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8 for proper handling of special characters (Ñ, ñ, etc.)
$conn->set_charset("utf8mb4");

?>