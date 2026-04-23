<?php
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
$appointmentId = intval($_POST['appointment_id'] ?? 0);
$customerId = intval($_POST['customer_id'] ?? 0);
$reportType = trim($_POST['report_type'] ?? '');
$reportNote = trim($_POST['report_note'] ?? '');

if ($appointmentId <= 0 || $customerId <= 0 || $reportType === '') {
    header("Location: lab-dashboard.php");
    exit;
}

/* نتأكد أن الموعد لهذا المختبر وهذا العميل */
$sqlCheck = "SELECT appointment_id
             FROM appointment
             WHERE appointment_id = ? AND customer_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmt, "iii", $appointmentId, $customerId, $labId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!mysqli_fetch_assoc($result)) {
    header("Location: lab-dashboard.php");
    exit;
}

$sqlInsert = "INSERT INTO report (appointment_id, customer_id, lab_id, report_type, report_note)
              VALUES (?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param($stmt, "iiiss", $appointmentId, $customerId, $labId, $reportType, $reportNote);
mysqli_stmt_execute($stmt);

header("Location: lab-dashboard.php");
exit;
?>