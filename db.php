<?php
$host = "localhost";
$username = "root";
$password = "root";
$database = "narak_db";
$port = 3306;

$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>