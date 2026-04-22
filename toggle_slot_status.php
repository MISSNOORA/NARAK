<?php
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
$slotId = intval($_POST['slot_id'] ?? 0);
$newStatus = intval($_POST['new_status'] ?? -1);

if ($slotId <= 0 || ($newStatus !== 0 && $newStatus !== 1)) {
    header("Location: lab-dashboard.php");
    exit;
}

$sql = "UPDATE time_slot
        SET is_available = ?
        WHERE slot_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $newStatus, $slotId, $labId);
mysqli_stmt_execute($stmt);

header("Location: lab-dashboard.php");
exit;
?>