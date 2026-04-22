<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
$appointmentId = intval($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];

if ($appointmentId <= 0 || !in_array($status, $allowedStatuses, true)) {
    header("Location: lab-dashboard.php");
    exit;
}

$sql = "UPDATE appointment
        SET status = ?
        WHERE appointment_id = ? AND lab_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sii", $status, $appointmentId, $labId);
mysqli_stmt_execute($stmt);

header("Location: lab-dashboard.php");
exit;
?>