<?php
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
$slotDate = $_POST['slot_date'] ?? '';
$slotTime = $_POST['slot_time'] ?? '';

if (empty($slotDate) || empty($slotTime)) {
    header("Location: lab-dashboard.php");
    exit;
}

/* نتأكد أن التاريخ اليوم أو بعده */
if ($slotDate < date('Y-m-d')) {
    header("Location: lab-dashboard.php");
    exit;
}

/* منع التكرار — نفس المختبر والتاريخ والوقت */
$dup = mysqli_prepare($conn, "SELECT slot_id FROM time_slot WHERE lab_id = ? AND slot_date = ? AND slot_time = ? LIMIT 1");
mysqli_stmt_bind_param($dup, "iss", $labId, $slotDate, $slotTime);
mysqli_stmt_execute($dup);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup))) {
    header("Location: lab-dashboard.php");
    exit;
}

/* إضافة الوقت */
$sql = "INSERT INTO time_slot (lab_id, slot_date, slot_time, is_available)
        VALUES (?, ?, ?, 1)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $labId, $slotDate, $slotTime);

if (mysqli_stmt_execute($stmt)) {
    header("Location: lab-dashboard.php");
    exit;
} else {
    header("Location: lab-dashboard.php");
    exit;
}
?>