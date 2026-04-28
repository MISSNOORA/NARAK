<?php
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    echo json_encode(['success' => false]);
    exit;
}

$labId  = (int)$_SESSION['user_id'];
$date   = trim($_POST['slot_date'] ?? '');
$time   = trim($_POST['slot_time'] ?? ''); // HH:MM
$action = trim($_POST['action']    ?? ''); // 'enable' | 'disable'

$allowed = ['10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];

if (!$date || !in_array($time, $allowed, true) || !in_array($action, ['enable','disable'], true)) {
    echo json_encode(['success' => false]);
    exit;
}

if ($date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'لا يمكن تعديل أوقات في الماضي']);
    exit;
}

$timeDb = $time . ':00'; // HH:MM:SS

/* Find existing slot */
$stmt = mysqli_prepare($conn, "SELECT slot_id FROM time_slot WHERE lab_id = ? AND slot_date = ? AND slot_time = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "iss", $labId, $date, $timeDb);
mysqli_stmt_execute($stmt);
$slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$slotId = $slot ? (int)$slot['slot_id'] : null;

/* Helper: does this slot have an active appointment? */
function hasActiveAppointment($conn, $slotId) {
    if (!$slotId) return false;
    $chk = mysqli_prepare($conn, "SELECT appointment_id FROM appointment WHERE slot_id = ? AND status NOT IN ('cancelled') LIMIT 1");
    mysqli_stmt_bind_param($chk, "i", $slotId);
    mysqli_stmt_execute($chk);
    return (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
}

if ($action === 'enable') {
    if ($slotId) {
        if (hasActiveAppointment($conn, $slotId)) {
            echo json_encode(['success' => false, 'message' => 'الوقت محجوز من قبل عميل']);
            exit;
        }
        $upd = mysqli_prepare($conn, "UPDATE time_slot SET is_available = 1 WHERE slot_id = ?");
        mysqli_stmt_bind_param($upd, "i", $slotId);
        mysqli_stmt_execute($upd);
    } else {
        $ins = mysqli_prepare($conn, "INSERT INTO time_slot (lab_id, slot_date, slot_time, is_available) VALUES (?, ?, ?, 1)");
        mysqli_stmt_bind_param($ins, "iss", $labId, $date, $timeDb);
        mysqli_stmt_execute($ins);
    }
    echo json_encode(['success' => true, 'new_status' => 'available']);

} else { // disable
    if (!$slotId) {
        echo json_encode(['success' => true, 'new_status' => 'disabled']);
        exit;
    }
    if (hasActiveAppointment($conn, $slotId)) {
        echo json_encode(['success' => false, 'message' => 'لا يمكن تعطيل وقت محجوز من قبل عميل']);
        exit;
    }
    $upd = mysqli_prepare($conn, "UPDATE time_slot SET is_available = 0 WHERE slot_id = ?");
    mysqli_stmt_bind_param($upd, "i", $slotId);
    mysqli_stmt_execute($upd);
    echo json_encode(['success' => true, 'new_status' => 'disabled']);
}
exit;
?>
