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

if ($appointmentId <= 0 || $reportType === '') {
    header("Location: lab-dashboard.php");
    exit;
}

/* نتأكد أن الموعد يتبع هذا المختبر */
$sqlCheck = "SELECT appointment_id
             FROM appointment
             WHERE appointment_id = ? AND customer_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmt, "iii", $appointmentId, $customerId, $labId);
mysqli_stmt_execute($stmt);
$checkResult = mysqli_stmt_get_result($stmt);

if (!mysqli_fetch_assoc($checkResult)) {
    header("Location: lab-dashboard.php");
    exit;
}

/* نجيب أول test_type_id مربوط بهذا الموعد */
$sqlGetTestType = "SELECT test_type_id
                   FROM appointment_test_type
                   WHERE appointment_id = ?
                   LIMIT 1";

$stmt = mysqli_prepare($conn, $sqlGetTestType);
mysqli_stmt_bind_param($stmt, "i", $appointmentId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$testRow = mysqli_fetch_assoc($result);

if (!$testRow) {
    header("Location: lab-dashboard.php");
    exit;
}

$testTypeId = (int)$testRow['test_type_id'];

/* نحفظ البلاغ داخل test_result */
$sqlInsert = "INSERT INTO test_result
              (appointment_id, test_type_id, result_value, normal_range, status_flag, report_date)
              VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sqlInsert);

$resultValue = 'بلاغ';
$normalRange = $reportNote;
$statusFlag = $reportType;
$reportDate = date('Y-m-d');

mysqli_stmt_bind_param(
    $stmt,
    "iissss",
    $appointmentId,
    $testTypeId,
    $resultValue,
    $normalRange,
    $statusFlag,
    $reportDate
);

mysqli_stmt_execute($stmt);

header("Location: lab-dashboard.php");
exit;
?>