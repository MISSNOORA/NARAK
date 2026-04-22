<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Must be logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$customerId = (int) $_SESSION['user_id'];

// Read POST data
$labName   = trim($_POST['lab_name']   ?? '');
$date      = trim($_POST['date']       ?? '');
$time      = trim($_POST['time']       ?? '');
$testsJson = trim($_POST['tests']      ?? '');

// Validate
if (!$labName || !$date || !$time || !$testsJson) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
    exit;
}

$tests = json_decode($testsJson, true);
if (!is_array($tests) || empty($tests)) {
    echo json_encode(['success' => false, 'message' => 'لم يتم اختيار أي تحليل']);
    exit;
}

// Get lab_id from lab_name
$stmtLab = mysqli_prepare($conn, "SELECT lab_id FROM laboratory WHERE lab_name = ? LIMIT 1");
mysqli_stmt_bind_param($stmtLab, 's', $labName);
mysqli_stmt_execute($stmtLab);
$labRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtLab));
if (!$labRow) {
    echo json_encode(['success' => false, 'message' => 'المختبر غير موجود']);
    exit;
}
$labId = (int) $labRow['lab_id'];

// Validate time format (expects HH:MM:SS from select)
$timeParsed = $time;
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeParsed)) {
    echo json_encode(['success' => false, 'message' => 'صيغة الوقت غير صحيحة']);
    exit;
}
if (strlen($timeParsed) === 5) $timeParsed .= ':00';

// Find existing available time_slot or create one
$stmtSlot = mysqli_prepare($conn,
    "SELECT slot_id FROM time_slot
     WHERE lab_id = ? AND slot_date = ? AND slot_time = ? AND is_available = 1
     LIMIT 1"
);
mysqli_stmt_bind_param($stmtSlot, 'iss', $labId, $date, $timeParsed);
mysqli_stmt_execute($stmtSlot);
$slotRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSlot));

if ($slotRow) {
    $slotId = (int) $slotRow['slot_id'];
    // Mark slot as unavailable
    mysqli_query($conn, "UPDATE time_slot SET is_available = 0 WHERE slot_id = $slotId");
} else {
    // Create a new time slot
    $stmtNewSlot = mysqli_prepare($conn,
        "INSERT INTO time_slot (lab_id, slot_date, slot_time, is_available) VALUES (?, ?, ?, 0)"
    );
    mysqli_stmt_bind_param($stmtNewSlot, 'iss', $labId, $date, $timeParsed);
    mysqli_stmt_execute($stmtNewSlot);
    $slotId = (int) mysqli_insert_id($conn);
}

// Insert appointment
$stmtAppt = mysqli_prepare($conn,
    "INSERT INTO appointment (customer_id, lab_id, slot_id, status) VALUES (?, ?, ?, 'pending')"
);
mysqli_stmt_bind_param($stmtAppt, 'iii', $customerId, $labId, $slotId);
mysqli_stmt_execute($stmtAppt);
$appointmentId = (int) mysqli_insert_id($conn);

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'فشل إنشاء الموعد']);
    exit;
}

// Insert each selected test into appointment_test_type
$stmtTest = mysqli_prepare($conn,
    "SELECT test_type_id FROM test_type WHERE test_name = ? AND lab_id = ? LIMIT 1"
);
$stmtLink = mysqli_prepare($conn,
    "INSERT INTO appointment_test_type (appointment_id, test_type_id) VALUES (?, ?)"
);

foreach ($tests as $testName) {
    $testName = trim($testName);
    mysqli_stmt_bind_param($stmtTest, 'si', $testName, $labId);
    mysqli_stmt_execute($stmtTest);
    $testRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTest));
    if ($testRow) {
        $testTypeId = (int) $testRow['test_type_id'];
        mysqli_stmt_bind_param($stmtLink, 'ii', $appointmentId, $testTypeId);
        mysqli_stmt_execute($stmtLink);
    }
}

echo json_encode(['success' => true, 'appointment_id' => $appointmentId]);
exit;
?>