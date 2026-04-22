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
$testTypeIds = $_POST['test_type_id'] ?? [];
$resultValues = $_POST['result_value'] ?? [];
$normalRanges = $_POST['normal_range'] ?? [];
$statusFlags = $_POST['status_flag'] ?? [];

if (
    $appointmentId <= 0 ||
    empty($testTypeIds) ||
    count($testTypeIds) !== count($resultValues) ||
    count($testTypeIds) !== count($normalRanges) ||
    count($testTypeIds) !== count($statusFlags)
) {
    header("Location: lab-dashboard.php");
    exit;
}

/* نتأكد أن الموعد يتبع هذا المختبر */
$sqlCheck = "SELECT appointment_id
             FROM appointment
             WHERE appointment_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmt, "ii", $appointmentId, $labId);
mysqli_stmt_execute($stmt);
$checkResult = mysqli_stmt_get_result($stmt);

if (!mysqli_fetch_assoc($checkResult)) {
    header("Location: lab-dashboard.php");
    exit;
}

$sqlInsert = "INSERT INTO test_result
              (appointment_id, test_type_id, result_value, normal_range, status_flag, report_date)
              VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sqlInsert);
$reportDate = date('Y-m-d');

for ($i = 0; $i < count($testTypeIds); $i++) {
    $testTypeId = intval($testTypeIds[$i]);
    $resultValue = trim($resultValues[$i]);
    $normalRange = trim($normalRanges[$i]);
    $statusFlag = trim($statusFlags[$i]);

    if ($testTypeId <= 0 || $resultValue === '' || $statusFlag === '') {
        continue;
    }

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
}

header("Location: lab-dashboard.php");
exit;
?>