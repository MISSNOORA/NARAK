<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$customerId    = (int) $_SESSION['user_id'];
$appointmentId = (int) ($_POST['appointment_id'] ?? 0);

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'معرّف الموعد مفقود']);
    exit;
}

// Verify the appointment belongs to this customer and is still pending
$stmt = mysqli_prepare($conn,
    "SELECT slot_id FROM appointment
     WHERE appointment_id = ? AND customer_id = ? AND status = 'pending'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $appointmentId, $customerId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'لا يمكن إلغاء هذا الموعد']);
    exit;
}

$slotId = (int) $row['slot_id'];

// Cancel the appointment
$stmtCancel = mysqli_prepare($conn,
    "UPDATE appointment SET status = 'cancelled' WHERE appointment_id = ?"
);
mysqli_stmt_bind_param($stmtCancel, 'i', $appointmentId);
mysqli_stmt_execute($stmtCancel);

// Free the time slot so others can book it
$stmtSlot = mysqli_prepare($conn,
    "UPDATE time_slot SET is_available = 1 WHERE slot_id = ?"
);
mysqli_stmt_bind_param($stmtSlot, 'i', $slotId);
mysqli_stmt_execute($stmtSlot);

echo json_encode(['success' => true]);
exit;
?>
