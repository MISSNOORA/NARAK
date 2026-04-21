<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "db.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$firstName = trim($_POST["first_name"] ?? "");
$lastName  = trim($_POST["last_name"] ?? "");
$email     = trim($_POST["email"] ?? "");
$phone     = trim($_POST["phone"] ?? "");
$password  = $_POST["password"] ?? "";
$address   = trim($_POST["address"] ?? "");

if (
    empty($firstName) ||
    empty($lastName) ||
    empty($email) ||
    empty($phone) ||
    empty($password)
) {
    header("Location: index.php?error=missing_fields");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?error=invalid_email");
    exit;
}

if (!empty($address) && !filter_var($address, FILTER_VALIDATE_URL)) {
    header("Location: index.php?error=invalid_address");
    exit;
}

$checkSql = "SELECT customer_id FROM customer WHERE email = ?";
$stmt = mysqli_prepare($conn, $checkSql);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    header("Location: index.php?error=email_exists");
    exit;
}

mysqli_stmt_close($stmt);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertSql = "INSERT INTO customer (first_name, last_name, email, phone_number, password_hash, address)
              VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $insertSql);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ssssss",
    $firstName,
    $lastName,
    $email,
    $phone,
    $passwordHash,
    $address
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION["user_id"] = mysqli_insert_id($conn);
    $_SESSION["role"] = "customer";
    $_SESSION["full_name"] = $firstName . " " . $lastName;
    $_SESSION["email"] = $email;

    mysqli_stmt_close($stmt);
    header("Location: customer-dashboard.php");
    exit;
} else {
    mysqli_stmt_close($stmt);
    header("Location: index.php?error=signup_failed");
    exit;
}
?>