<?php
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
$customerId = intval($_POST['customer_id'] ?? 0);
$reportType = trim($_POST['report_type'] ?? '');
$reportNote = trim($_POST['report_note'] ?? '');

if ($customerId <= 0 || $reportType === '') {
    header("Location: lab-dashboard.php");
    exit;
}

/* ندمج النوع + الملاحظة */
$reason = $reportType;

if (!empty($reportNote)) {
    $reason .= " - " . $reportNote;
}

$reportDate = date('Y-m-d');
$status = 'open';

/* نحفظ البلاغ */
$sqlInsert = "INSERT INTO report (customer_id, lab_id, reason, report_date, status)
              VALUES (?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param($stmt, "iisss", $customerId, $labId, $reason, $reportDate, $status);
mysqli_stmt_execute($stmt);

header("Location: lab-dashboard.php");
exit;
?>