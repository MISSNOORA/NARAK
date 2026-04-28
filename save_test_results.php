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

if ($appointmentId <= 0 || empty($testTypeIds)) {
    header("Location: lab-dashboard.php");
    exit;
}

/* تحقق من الموعد */
$sqlCheck = "SELECT appointment_id
             FROM appointment
             WHERE appointment_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmt, "ii", $appointmentId, $labId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!mysqli_fetch_assoc($res)) {
    header("Location: lab-dashboard.php");
    exit;
}

$reportDate = date('Y-m-d');

/* نجيب النطاق */
$sqlGetRange = "SELECT normal_range
                FROM test_type
                WHERE test_type_id = ?";

$getStmt = mysqli_prepare($conn, $sqlGetRange);

/* الإدخال */
$sqlInsert = "INSERT INTO test_result
              (appointment_id, test_type_id, result_value, normal_range, status_flag, report_date)
              VALUES (?, ?, ?, ?, ?, ?)";

$insertStmt = mysqli_prepare($conn, $sqlInsert);

for ($i = 0; $i < count($testTypeIds); $i++) {

    $testTypeId = intval($testTypeIds[$i]);
    $resultValue = trim($resultValues[$i]);

    if ($testTypeId <= 0 || $resultValue === '' || !is_numeric($resultValue)) {
        header("Location: lab-dashboard.php?error=invalid");
        exit;
    }

    /* نجيب النطاق */
    mysqli_stmt_bind_param($getStmt, "i", $testTypeId);
    mysqli_stmt_execute($getStmt);
    $rangeRes = mysqli_stmt_get_result($getStmt);
    $rangeRow = mysqli_fetch_assoc($rangeRes);

    if (!$rangeRow) continue;

    $normalRange = $rangeRow['normal_range']; // مثال: 50 - 125

    /* نفصل الأرقام */
    $parts = explode('-', $normalRange);

    $min = floatval(trim($parts[0]));
    $max = floatval(trim($parts[1]));

    /* نحدد الحالة */
    if ($resultValue < $min) {
        $statusFlag = "low";
    } elseif ($resultValue > $max) {
        $statusFlag = "high";
    } else {
        $statusFlag = "normal";
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        "iissss",
        $appointmentId,
        $testTypeId,
        $resultValue,
        $normalRange,
        $statusFlag,
        $reportDate
    );

    mysqli_stmt_execute($insertStmt);
}

header("Location: lab-dashboard.php");
exit;
?>