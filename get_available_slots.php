<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['slots' => []]);
    exit;
}

$labName = trim($_GET['lab_name'] ?? '');
$date    = trim($_GET['date']     ?? '');

if (!$labName || !$date || $date < date('Y-m-d')) {
    echo json_encode(['slots' => []]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT lab_id FROM laboratory WHERE lab_name = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $labName);
mysqli_stmt_execute($stmt);
$labRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$labRow) {
    echo json_encode(['slots' => []]);
    exit;
}
$labId = (int)$labRow['lab_id'];

$stmt2 = mysqli_prepare($conn,
    "SELECT slot_time FROM time_slot
     WHERE lab_id = ? AND slot_date = ? AND is_available = 1
     ORDER BY slot_time ASC"
);
mysqli_stmt_bind_param($stmt2, 'is', $labId, $date);
mysqli_stmt_execute($stmt2);
$result = mysqli_stmt_get_result($stmt2);

$slots = [];
while ($row = mysqli_fetch_assoc($result)) {
    $slots[] = $row['slot_time'];
}

echo json_encode(['slots' => $slots]);
exit;
?>
