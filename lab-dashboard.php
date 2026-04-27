<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Riyadh');
session_start();
require_once "db.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php");
    exit;
}

$labId = $_SESSION['user_id'];
?>


<?php
$today = date("Y-m-d");
$currentMonth = date("Y-m");

$stats = [
    'today_appointments' => 0,
    'pending_appointments' => 0,
    'results_waiting' => 0,
    'completed_month' => 0
];

/* مواعيد اليوم */
$sqlToday = "SELECT COUNT(*) AS total
             FROM appointment a
             JOIN time_slot ts ON a.slot_id = ts.slot_id
             WHERE a.lab_id = ? AND ts.slot_date = ?";
$stmt = mysqli_prepare($conn, $sqlToday);
mysqli_stmt_bind_param($stmt, "is", $labId, $today);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$stats['today_appointments'] = mysqli_fetch_assoc($res)['total'];

/* طلبات pending */
$sqlPending = "SELECT COUNT(*) AS total
               FROM appointment
               WHERE lab_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $sqlPending);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$stats['pending_appointments'] = mysqli_fetch_assoc($res)['total'];

/* مواعيد مكتملة بدون نتائج */
$sqlWaitingResults = "SELECT COUNT(*) AS total
                      FROM appointment a
                      WHERE a.lab_id = ?
                      AND a.status = 'completed'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM test_result tr
                          WHERE tr.appointment_id = a.appointment_id
                      )";
$stmt = mysqli_prepare($conn, $sqlWaitingResults);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$stats['results_waiting'] = mysqli_fetch_assoc($res)['total'];

/* الطلبات المكتملة هذا الشهر */
$sqlCompletedMonth = "SELECT COUNT(*) AS total
                      FROM appointment
                      WHERE lab_id = ?
                      AND status = 'completed'
                      AND DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = mysqli_prepare($conn, $sqlCompletedMonth);
mysqli_stmt_bind_param($stmt, "is", $labId, $currentMonth);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$stats['completed_month'] = mysqli_fetch_assoc($res)['total'];
?>


<?php
$appointments = [];

$sqlAppointments = "
SELECT 
    a.appointment_id,
    a.status,
    c.customer_id,
    c.first_name,
    c.last_name,
    c.phone_number,
    ts.slot_date,
    ts.slot_time,
    GROUP_CONCAT(tt.test_name SEPARATOR '، ') AS tests
FROM appointment a
JOIN customer c ON a.customer_id = c.customer_id
JOIN time_slot ts ON a.slot_id = ts.slot_id
LEFT JOIN appointment_test_type att ON a.appointment_id = att.appointment_id
LEFT JOIN test_type tt ON att.test_type_id = tt.test_type_id
WHERE a.lab_id = ?
GROUP BY a.appointment_id, a.status, c.first_name, c.last_name, c.phone_number, ts.slot_date, ts.slot_time
ORDER BY ts.slot_date DESC, ts.slot_time DESC
";

$stmt = mysqli_prepare($conn, $sqlAppointments);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $appointments[] = $row;
}
?>




<?php
$labSql = "SELECT lab_id, lab_name, lab_logo, email, phone_number, address
           FROM laboratory
           WHERE lab_id = ?";

$stmt = mysqli_prepare($conn, $labSql);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$labResult = mysqli_stmt_get_result($stmt);
$lab = mysqli_fetch_assoc($labResult);
?>











<?php
$slots = [];

$sqlSlots = "SELECT slot_id, slot_date, slot_time, is_available
             FROM time_slot
             WHERE lab_id = ?
             ORDER BY slot_date ASC, slot_time ASC";

$stmt = mysqli_prepare($conn, $sqlSlots);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $slots[] = $row;
}
?>



<?php
$pendingResults = [];

$sqlPendingResults = "
SELECT 
    a.appointment_id,
    c.first_name,
    c.last_name,
    ts.slot_date,
    GROUP_CONCAT(tt.test_name SEPARATOR ' - ') AS tests
FROM appointment a
JOIN customer c ON a.customer_id = c.customer_id
JOIN time_slot ts ON a.slot_id = ts.slot_id
LEFT JOIN appointment_test_type att ON a.appointment_id = att.appointment_id
LEFT JOIN test_type tt ON att.test_type_id = tt.test_type_id
WHERE a.lab_id = ?
AND a.status = 'completed'
AND NOT EXISTS (
    SELECT 1
    FROM test_result tr
    WHERE tr.appointment_id = a.appointment_id
)
GROUP BY a.appointment_id, c.first_name, c.last_name, ts.slot_date
ORDER BY ts.slot_date DESC
";

$stmt = mysqli_prepare($conn, $sqlPendingResults);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $pendingResults[] = $row;
}
?>

<?php
$resultTestsByAppointment = [];

$sqlResultTests = "
SELECT 
    att.appointment_id,
    tt.test_type_id,
    tt.test_name
FROM appointment_test_type att
JOIN test_type tt ON att.test_type_id = tt.test_type_id
JOIN appointment a ON att.appointment_id = a.appointment_id
WHERE a.lab_id = ?
ORDER BY att.appointment_id, tt.test_name
";

$stmt = mysqli_prepare($conn, $sqlResultTests);
mysqli_stmt_bind_param($stmt, "i", $labId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $appointmentId = $row['appointment_id'];

    if (!isset($resultTestsByAppointment[$appointmentId])) {
        $resultTestsByAppointment[$appointmentId] = [];
    }

    $resultTestsByAppointment[$appointmentId][] = [
        'test_type_id' => $row['test_type_id'],
        'test_name' => $row['test_name']
    ];
}
?>

<!DOCTYPE html>
<!--
Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
Click nbfs://nbhost/SystemFileSystem/Templates/Other/html.html to edit this template
-->
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نرعاك - لوحة المختبر</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --deep-red: #520000;
    --muted-brown: #BD9E77;
    --light-beige: #ECC590;
    --medium-brown: #8E775E;
    --ivory: #FFFFF0;
    --sidebar-w: 240px;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Tajawal', sans-serif; background: #f5f0e8; min-height: 100vh; color: #333; }

  .sidebar {
    position: fixed; top: 0; right: 0;
    width: var(--sidebar-w); height: 100vh;
    background: var(--medium-brown);
    display: flex; flex-direction: column; z-index: 100;
  }
.sidebar-logo{
  border-bottom:0.3px solid rgba(255,255,255,0.12);
  line-height:1;
}

.logo-img{
  width:120px;
  height:90px;
  display:block;
  margin:0 auto;
}

  .sidebar-user {
    padding: 0.5px 0.5px; 
    display: flex; 
    align-items: center; 
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
  }
  .user-avatar {
    width: 50px; 
    height: 50px; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-size: 1rem; 
    font-weight: 700; 
    color: var(--deep-red); 
    flex-shrink: 0;
  }
  .user-info strong { font-size: 0.88rem; color: #fff; display: block; font-weight: 600; }
  .user-info span { font-size: 0.75rem; color: rgba(255,255,255,0.5); }

  nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
  .nav-section { font-size: 0.68rem; font-weight: 700; color: rgba(255,255,255,0.35); letter-spacing: 1.2px; text-transform: uppercase; padding: 12px 12px 6px; }
  .nav-item {
    display: flex; align-items: center; gap: 12px; padding: 11px 14px;
    border-radius: 10px; cursor: pointer; transition: all 0.2s; margin-bottom: 2px;
    color: rgba(255,255,255,0.7); font-size: 0.88rem; font-weight: 500; text-decoration: none;
  }
  .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
  .nav-item.active { background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; }
  .nav-item .icon { font-size: 1.1rem; width: 20px; text-align: center; }

  .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.12); }
  .logout-btn {
    display: flex; align-items: center; gap: 10px; padding: 11px 14px;
    border-radius: 10px; cursor: pointer; color: rgba(255,255,255,0.5);
    font-size: 0.85rem; transition: all 0.2s; text-decoration: none;
  }
  .logout-btn:hover { color: #fff; background: rgba(255,255,255,0.08); }

  .main { margin-right: var(--sidebar-w); min-height: 100vh; padding: 32px 36px; }
  .lab-avatar {
    width: 50px; height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;}

  .page-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px;
  }
  .page-title h1 { font-size: 1.6rem; font-weight: 800; color: var(--deep-red); }
  .page-title p { font-size: 0.85rem; color: var(--medium-brown); margin-top: 2px; }

  .btn { padding: 10px 22px; border-radius: 10px; font-family: 'Tajawal', sans-serif; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
  .btn-primary { background: var(--deep-red); color: #fff; }
  .btn-primary:hover { background: #3d0000; transform: translateY(-1px); }
  .btn-outline { background: #fff; color: var(--deep-red); border: 1.5px solid var(--deep-red); }
  .btn-outline:hover { background: rgba(82,0,0,0.05); }

  .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
  .stat-card {
    background: #fff; border-radius: 16px; padding: 22px 20px;
    position: relative; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  }
  .stat-card::after {
    content: ''; position: absolute; top: 0; right: 0;
    width: 4px; height: 100%; background: var(--medium-brown); border-radius: 0 16px 16px 0;
  }
  .stat-card:nth-child(1)::after { background: var(--deep-red); }
  .stat-card:nth-child(3)::after { background: var(--light-beige); }
  .stat-card:nth-child(4)::after { background: #2d7a3a; }
  .stat-num { font-size: 2rem; font-weight: 800; color: var(--medium-brown); line-height: 1; }
  .stat-card:nth-child(1) .stat-num { color: var(--deep-red); }
  .stat-card:nth-child(3) .stat-num { color: #b58a3a; }
  .stat-card:nth-child(4) .stat-num { color: #2d7a3a; }
  .stat-label { font-size: 0.8rem; color: #888; margin-top: 6px; font-weight: 500; }

  .card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .card-title { font-size: 1rem; font-weight: 700; color: #222; }

  .status-badge { font-size: 0.72rem; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
  .status-pending { background: #fff8e6; color: #c8860a; }
  .status-confirmed { background: #e8f4ea; color: #2d7a3a; }
  .status-progress { background: #e8f0fc; color: #2a5cc4; }
  .status-done { background: #f0f0f0; color: #666; }
  .status-cancelled { background: #fce8e8; color: #c42a2a; }

  .section { display: none; }
  .section.active { display: block; }

  .table-header {
    display: grid; gap: 8px; padding: 10px 0;
    font-size: 0.72rem; font-weight: 700; color: #aaa; letter-spacing: 0.5px;
    border-bottom: 2px solid #f0ebe4; margin-bottom: 4px;
  }
  .table-row {
    display: grid; gap: 8px; padding: 14px 0;
    align-items: center; border-bottom: 1px solid #f0ebe4;
  }
  .table-row:last-child { border-bottom: none; }
  .btn-report{
  font-family:'Tajawal', sans-serif;
  font-size:0.75rem;
  padding:6px 14px;
  border-radius:8px;
  border:none;
  background:#fce8e8;
  color:#c42a2a;
  cursor:pointer;
  transition:all 0.2s;
}

.btn-report:hover{
  background:#f7dede;
}

.action-cell{
  display:flex;
  align-items:center;
  gap:8px;
  justify-content:flex-start;
}

.status-select{
  font-family:'Tajawal', sans-serif;
  font-size:0.75rem;
  border:1px solid #e8e0d8;
  border-radius:8px;
  padding:6px 10px;
  color:#555;
  background:#fff;
  cursor:pointer;
  outline:none;
  min-width:120px;
}

.report-icon-btn{
  width:34px;
  height:34px;
  border:none;
  border-radius:8px;
  background:#fce8e8;
  color:#c42a2a;
  cursor:pointer;
  font-size:0.95rem;
  display:flex;
  align-items:center;
  justify-content:center;
  transition:all 0.2s;
}

.report-icon-btn:hover{
  background:#f5dede;
}

  /* slots calendar */
  .month-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 6px; margin-top: 12px; }
  .day-cell {
    aspect-ratio: 1; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 0.82rem; font-weight: 500; cursor: pointer;
    transition: all 0.15s;
  }
  .day-cell.empty { visibility: hidden; }
  .day-cell.available { background: rgba(82,0,0,0.07); color: var(--deep-red); }
  .day-cell.available:hover { background: var(--deep-red); color: #fff; }
  .day-cell.booked { background: var(--light-beige); color: var(--deep-red); font-weight: 700; }
  .day-cell.past { background: #f5f5f5; color: #ccc; cursor: default; }
  .day-cell.today { background: var(--deep-red); color: #fff; font-weight: 700; }

  .day-name { font-size: 0.7rem; font-weight: 700; color: #aaa; text-align: center; padding: 4px 0; }
</style>
</head>
<body>
<?php include 'welcome_toast.php'; ?>

<aside class="sidebar">
  <div class="sidebar-logo">
    <img src="images/1.png" alt="نرعاك" class="logo-img">
  </div>
  <div class="sidebar-user">
      <img src="<?php echo htmlspecialchars($lab['lab_logo']); ?>" alt="lab-logo" class="user-avatar">
    <div class="user-info">
        
      <strong><?php echo htmlspecialchars($lab['lab_name']); ?></strong>
      
      
      
      
      
      <span>مختبر معتمد</span>
    </div>
  </div>
  <nav>
    <div class="nav-section">الإدارة</div>
    <a class="nav-item active" href="#" onclick="showSection('home',this)">
      <span class="icon">🏠</span> الرئيسية
    </a>
    <a class="nav-item" href="#" onclick="showSection('appointments',this)">
      <span class="icon">📅</span> طلبات المواعيد
    </a>
    <a class="nav-item" href="#" onclick="showSection('slots',this)">
      <span class="icon">🗓️</span> إدارة الأوقات
    </a>
    <a class="nav-item" href="#" onclick="showSection('results',this)">
      <span class="icon">📋</span> رفع النتائج
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="logout.php"><span>🚪</span> تسجيل الخروج</a>
  </div>
</aside>

<main class="main">

  <!-- HOME -->
  <div class="section active" id="sec-home">
  <div class="page-header">
    <div class="page-title">
      <h1>مرحباً، <?php echo htmlspecialchars($lab['lab_name']); ?></h1>
      <p><?php echo date('Y-m-d'); ?></p>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['today_appointments']; ?></div>
      <div class="stat-label">مواعيد اليوم</div>
    </div>

    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['pending_appointments']; ?></div>
      <div class="stat-label">طلبات قيد الانتظار</div>
    </div>

    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['results_waiting']; ?></div>
      <div class="stat-label">نتائج لم تُرفع</div>
    </div>

    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['completed_month']; ?></div>
      <div class="stat-label">طلبات مكتملة (الشهر)</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
      <div class="card-header">
        <div class="card-title">مواعيد اليوم</div>
        <a href="#" style="font-size:0.78rem;color:var(--deep-red);font-weight:600;text-decoration:none;" onclick="showSection('appointments',document.querySelector('[onclick*=appointments]'))">عرض الكل</a>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($appointments as $appt): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:#faf8f5;border-radius:10px;">
            <div>
              <div style="font-size:0.85rem;font-weight:600;color:#222;">
                <?php echo htmlspecialchars($appt['first_name'] . " " . $appt['last_name']); ?>
              </div>

              <div style="font-size:0.75rem;color:#999;">
                <?php echo htmlspecialchars($appt['tests']); ?> •
                <?php echo htmlspecialchars(date("g:i A", strtotime($appt['slot_time']))); ?>
              </div>
            </div>

            <?php
              $statusClass = "status-pending";
              $statusText = "في الانتظار";

              if ($appt['status'] == "confirmed") {
                $statusClass = "status-confirmed";
                $statusText = "مؤكد";
              } elseif ($appt['status'] == "completed") {
                $statusClass = "status-done";
                $statusText = "مكتمل";
              } elseif ($appt['status'] == "cancelled") {
                $statusClass = "status-cancelled";
                $statusText = "ملغي";
              }
            ?>

            <span class="status-badge <?php echo $statusClass; ?>">
              <?php echo $statusText; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
  <div class="card-header">
    <div class="card-title">نتائج تنتظر الرفع</div>
    <a href="#" style="font-size:0.78rem;color:var(--deep-red);font-weight:600;text-decoration:none;" onclick="showSection('results',document.querySelector('[onclick*=results]'))">رفع النتائج</a>
  </div>

  <div style="display:flex;flex-direction:column;gap:10px;">
    <?php if (!empty($pendingResults)): ?>
      <?php foreach ($pendingResults as $item): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:#fff8e6;border-radius:10px;border-right:3px solid #c8860a;">
          <div>
            <div style="font-size:0.85rem;font-weight:600;color:#222;">
              <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
            </div>
            <div style="font-size:0.75rem;color:#999;">
              <?php echo htmlspecialchars($item['tests']); ?> • <?php echo htmlspecialchars($item['slot_date']); ?>
            </div>
          </div>

          <button class="btn btn-primary" style="padding:6px 14px;font-size:0.78rem;"
                  onclick="showSection('results',document.querySelector('[onclick*=results]'))">
            رفع
          </button>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="padding:12px;background:#faf8f5;border-radius:10px;color:#777;font-size:0.85rem;">
        لا توجد نتائج تنتظر الرفع حالياً
      </div>
    <?php endif; ?>
  </div>
</div>
  </div>
</div>

  <!-- APPOINTMENTS -->
  <div class="section" id="sec-appointments">
    <div class="page-header">
      <div class="page-title"><h1>طلبات المواعيد</h1><p>إدارة وتتبع مواعيد العملاء</p></div>
    </div>

    <div class="card">
      <div class="table-header" style="grid-template-columns:1.5fr 1.5fr 1.5fr 1fr 1fr 1fr 0.8fr;">
        <div>العميل</div>
        <div>الفحوصات</div>
        <div>التاريخ</div>
        <div>الوقت</div>
        <div style="text-align:center">الحالة</div>
        <div style="text-align:center">الإجراء</div>
        <div style="text-align:center">بلاغ</div>
      </div>

      <?php foreach ($appointments as $appt): ?>
  <div class="table-row" style="grid-template-columns:1.5fr 1.5fr 1.5fr 1fr 1fr 1fr 0.8fr;">
    
    <div>
      <div style="font-size:0.88rem;font-weight:600;">
        <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?>
      </div>
      <div style="font-size:0.73rem;color:#999;">
        <?php echo htmlspecialchars($appt['phone_number']); ?>
      </div>
    </div>

    <div style="font-size:0.82rem;color:var(--medium-brown);">
      <?php echo htmlspecialchars($appt['tests'] ?? '—'); ?>
    </div>

    <div style="font-size:0.82rem;">
      <?php echo htmlspecialchars($appt['slot_date']); ?>
    </div>

    <div style="font-size:0.82rem;">
      <?php echo htmlspecialchars(date("g:i A", strtotime($appt['slot_time']))); ?>
    </div>

    <div style="text-align:center">
      <?php
        $statusClass = 'status-pending';
        $statusText = 'في الانتظار';

        if ($appt['status'] === 'confirmed') {
            $statusClass = 'status-confirmed';
            $statusText = 'مؤكد';
        } elseif ($appt['status'] === 'completed') {
            $statusClass = 'status-done';
            $statusText = 'مكتمل';
        } elseif ($appt['status'] === 'cancelled') {
            $statusClass = 'status-cancelled';
            $statusText = 'ملغي';
        }
      ?>
      <span class="status-badge <?php echo $statusClass; ?>">
        <?php echo $statusText; ?>
      </span>
    </div>

    <div style="text-align:center">
      <form method="POST" action="update_lab_appointment_status.php">
        <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
        <select name="status" onchange="this.form.submit()" class="status-select">
          <option value="">تحديث</option>
          <option value="pending">في الانتظار</option>
          <option value="confirmed">مؤكد</option>
          <option value="completed">مكتمل</option>
          <option value="cancelled">ملغي</option>
        </select>
      </form>
    </div>

    <div style="text-align:center">
      <button
  class="btn-report"
  type="button"
  onclick="openReportPopup(
    '<?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name'], ENT_QUOTES); ?>',
    <?php echo (int)$appt['appointment_id']; ?>,
    <?php echo (int)$appt['customer_id']; ?>
  )"
>
  🚩
</button>
    </div>

  </div>
<?php endforeach; ?>
        </div>
  </div>

  
  <!-- SLOTS MANAGEMENT -->
  <div class="section" id="sec-slots">
  <div class="page-header">
    <div class="page-title">
      <h1>إدارة الأوقات المتاحة</h1>
      <p>أضف وأدر الأوقات المتاحة للزيارات المنزلية</p>
    </div>
    <button class="btn btn-primary" onclick="alert('تم حفظ الأوقات المتاحة بنجاح')">حفظ التعديلات</button>
  </div>
      <div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">إضافة وقت جديد</div>
  </div>

  <form method="POST" action="add_time_slot.php" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;">
    
    <div>
      <label style="display:block;font-size:0.82rem;font-weight:600;color:#444;margin-bottom:6px;">التاريخ</label>
      <input type="date" name="slot_date" required
             style="width:100%;padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;">
    </div>

    <div>
      <label style="display:block;font-size:0.82rem;font-weight:600;color:#444;margin-bottom:6px;">الوقت</label>
      <input type="time" name="slot_time" required
             style="width:100%;padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;">
    </div>

    <div>
      <button type="submit" class="btn btn-primary" style="width:100%;">إضافة وقت</button>
    </div>

  </form>
</div>

  <?php
  $currentYear = date('Y');
  $currentMonthNum = date('m');
  $daysInMonth = date('t');
  $firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonthNum-01"));
  $todayDate = date('Y-m-d');

  $slotsMap = [];

  $sqlSlotsCalendar = "SELECT slot_date, is_available
                       FROM time_slot
                       WHERE lab_id = ?
                       AND YEAR(slot_date) = ?
                       AND MONTH(slot_date) = ?";
  $stmt = mysqli_prepare($conn, $sqlSlotsCalendar);
  mysqli_stmt_bind_param($stmt, "iii", $labId, $currentYear, $currentMonthNum);
  mysqli_stmt_execute($stmt);
  $resultSlotsCalendar = mysqli_stmt_get_result($stmt);

  while ($row = mysqli_fetch_assoc($resultSlotsCalendar)) {
      $dayNum = (int)date('j', strtotime($row['slot_date']));

      if (!isset($slotsMap[$dayNum])) {
          $slotsMap[$dayNum] = [
              'has_available' => false,
              'has_booked' => false
          ];
      }

      if ((int)$row['is_available'] === 1) {
          $slotsMap[$dayNum]['has_available'] = true;
      } else {
          $slotsMap[$dayNum]['has_booked'] = true;
      }
  }

  $monthNames = [
      '01' => 'يناير',
      '02' => 'فبراير',
      '03' => 'مارس',
      '04' => 'أبريل',
      '05' => 'مايو',
      '06' => 'يونيو',
      '07' => 'يوليو',
      '08' => 'أغسطس',
      '09' => 'سبتمبر',
      '10' => 'أكتوبر',
      '11' => 'نوفمبر',
      '12' => 'ديسمبر'
  ];

  $currentMonthTitle = $monthNames[$currentMonthNum] . ' ' . $currentYear;
  ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><?php echo $currentMonthTitle; ?></div>
        <div style="display:flex;gap:8px;">
          <div style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:#888;">
            <div style="width:12px;height:12px;background:rgba(82,0,0,0.07);border-radius:3px;"></div>متاح
          </div>
          <div style="display:flex;align-items:center;gap:5px;font-size:0.72rem;color:#888;">
            <div style="width:12px;height:12px;background:var(--light-beige);border-radius:3px;"></div>محجوز
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:8px;">
        <div class="day-name">أح</div>
        <div class="day-name">إث</div>
        <div class="day-name">ثل</div>
        <div class="day-name">أر</div>
        <div class="day-name">خم</div>
        <div class="day-name">جم</div>
        <div class="day-name">سب</div>
      </div>

      <div class="month-grid">
        <?php for ($i = 0; $i < $firstDayOfMonth; $i++): ?>
          <div class="day-cell empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <?php
          $fullDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonthNum, $day);

          if ($fullDate < $todayDate) {
              $cellClass = 'past';
          } elseif ($fullDate === $todayDate) {
              $cellClass = 'today';
          } elseif (isset($slotsMap[$day])) {
              if ($slotsMap[$day]['has_booked']) {
                  $cellClass = 'booked';
              } elseif ($slotsMap[$day]['has_available']) {
                  $cellClass = 'available';
              } else {
                  $cellClass = 'available';
              }
          } else {
              $cellClass = 'available';
          }
          ?>

          <div class="day-cell <?php echo $cellClass; ?>">
            <?php echo $day; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">الأوقات المسجلة</div>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php if (!empty($slots)): ?>
          <?php foreach ($slots as $slot): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:#faf8f5;border-radius:10px;">
              <div>
                <div style="font-size:0.85rem;font-weight:600;">
                  <?php echo htmlspecialchars($slot['slot_date']); ?>
                </div>
                <div style="font-size:0.75rem;color:#999;margin-top:3px;">
                  <?php echo htmlspecialchars(date('g:i A', strtotime($slot['slot_time']))); ?>
                </div>
              </div>

              <div style="display:flex;gap:8px;align-items:center;">
                <?php if ((int)$slot['is_available'] === 1): ?>
  <span style="font-size:0.75rem;color:#2d7a3a;background:#e8f4ea;padding:3px 10px;border-radius:20px;">متاح</span>

  <form method="POST" action="toggle_slot_status.php" style="margin:0;">
    <input type="hidden" name="slot_id" value="<?php echo $slot['slot_id']; ?>">
    <input type="hidden" name="new_status" value="0">
    <button type="submit" style="font-size:0.75rem;padding:4px 10px;border:1px solid #e8e0d8;border-radius:8px;background:#fff;cursor:pointer;font-family:Tajawal,sans-serif;">
      تعطيل
    </button>
  </form>
<?php else: ?>
  <span style="font-size:0.75rem;color:#c8860a;background:#fff8e6;padding:3px 10px;border-radius:20px;">محجوز</span>

  <form method="POST" action="toggle_slot_status.php" style="margin:0;">
    <input type="hidden" name="slot_id" value="<?php echo $slot['slot_id']; ?>">
    <input type="hidden" name="new_status" value="1">
    <button type="submit" style="font-size:0.75rem;padding:4px 10px;border:1px solid var(--deep-red);border-radius:8px;background:#fff;color:var(--deep-red);cursor:pointer;font-family:Tajawal,sans-serif;">
      تفعيل
    </button>
  </form>
<?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="padding:14px;background:#faf8f5;border-radius:10px;font-size:0.85rem;color:#777;">
            لا توجد أوقات مسجلة حالياً
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

  <!-- UPLOAD RESULTS -->
  <div class="section" id="sec-results">
    <div class="page-header">
      <div class="page-title"><h1>رفع النتائج</h1><p>أدخل نتائج الفحوصات للعملاء</p></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px;">
      <!-- progress list -->
      <div class="card">
        <div class="card-header"><div class="card-title">طلبات تنتظر النتائج</div></div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <?php if (!empty($pendingResults)): ?>
  <?php foreach ($pendingResults as $index => $item): ?>
    <div
      id="result-item-<?php echo $index + 1; ?>"
      onclick="selectResult(<?php echo $index + 1; ?>)"
      style="padding:12px;background:#fff8e6;border-radius:10px;border-right:3px solid #c8860a;cursor:pointer;border:1.5px solid transparent;"
    >
      <div style="font-size:0.88rem;font-weight:600;color:#222;">
        <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
      </div>
      <div style="font-size:0.75rem;color:#999;margin-top:2px;">
        <?php echo htmlspecialchars($item['tests']); ?> • <?php echo htmlspecialchars($item['slot_date']); ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div style="padding:12px;background:#faf8f5;border-radius:10px;color:#777;font-size:0.85rem;">
    لا توجد طلبات تنتظر رفع النتائج حالياً
  </div>
<?php endif; ?>
            
        </div>
      </div>

      <!-- Result form -->
      <div class="card" id="result-form">
        <div class="card-header"><div class="card-title">إدخال النتائج</div></div>
        <div style="text-align:center;padding:40px 0;color:#bbb;font-size:0.9rem;">
          <div style="font-size:2rem;margin-bottom:10px;">📋</div>
          اختر طلباً من القائمة لإدخال نتائجه
        </div>
      </div>
    </div>
  </div>

</main>
    
    <div id="reportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:999;align-items:center;justify-content:center;">
  <div style="width:420px;max-width:92%;background:#fff;border-radius:16px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <h3 style="font-size:1.1rem;color:#222;margin-bottom:8px;">رفع بلاغ</h3>
    <p id="reportUserName" style="font-size:0.85rem;color:#777;margin-bottom:18px;">العميل: —</p>
    
    <input type="hidden" id="reportAppointmentId">
    <input type="hidden" id="reportCustomerId">

    <label style="display:block;font-size:0.82rem;font-weight:600;color:#444;margin-bottom:6px;">نوع البلاغ</label>
    <select id="reportType" style="width:100%;padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;margin-bottom:14px;">
      <option value="">اختر نوع البلاغ</option>
      <option>عدم التجاوب</option>
      <option>سلوك غير مناسب</option>
      <option>بيانات غير صحيحة</option>
      <option>اخرى </option>
    </select>

    <label style="display:block;font-size:0.82rem;font-weight:600;color:#444;margin-bottom:6px;">ملاحظات إضافية</label>
    <textarea id="reportNote" rows="4" style="width:100%;padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;resize:none;margin-bottom:18px;" placeholder="اكتب ملاحظات مختصرة..."></textarea>

    <div style="display:flex;gap:10px;justify-content:flex-start;">
      <button onclick="submitReport()" style="padding:10px 18px;border:none;border-radius:10px;background:#c42a2a;color:#fff;font-family:Tajawal,sans-serif;font-weight:700;cursor:pointer;">إرسال البلاغ</button>
      <button onclick="closeReportPopup()" style="padding:10px 18px;border:none;border-radius:10px;background:#f1f1f1;color:#555;font-family:Tajawal,sans-serif;font-weight:700;cursor:pointer;">إلغاء</button>
    </div>
  </div>
</div>
<script>
const resultData = <?php
echo json_encode(
    array_map(function($item) use ($resultTestsByAppointment) {
        $appointmentId = $item['appointment_id'];
        return [
            'appointment_id' => $appointmentId,
            'name' => $item['first_name'] . ' ' . $item['last_name'],
            'date' => $item['slot_date'],
            'tests_text' => $item['tests'],
            'tests' => $resultTestsByAppointment[$appointmentId] ?? []
        ];
    }, $pendingResults),
    JSON_UNESCAPED_UNICODE
);
?>;
</script>
<script>
function showSection(name, el) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (el) el.classList.add('active');
}

function selectResult(index) {
  document.querySelectorAll('[id^=result-item]').forEach(el => {
    el.style.border = '1.5px solid transparent';
    el.style.background = '#fff8e6';
  });

  const selectedItem = document.getElementById('result-item-' + index);
  if (selectedItem) {
    selectedItem.style.border = '1.5px solid var(--deep-red)';
    selectedItem.style.background = 'rgba(82,0,0,0.04)';
  }

  const item = resultData[index - 1];
  if (!item) return;

  let html = `<div class="card-header"><div class="card-title">إدخال نتائج: ${item.name}</div></div>`;
  html += `<div style="font-size:0.8rem;color:var(--medium-brown);margin-bottom:20px;">الفحوصات: ${item.tests_text} — <span style="font-size:0.75rem;background:#e8f0fc;color:#2a5cc4;padding:3px 10px;border-radius:20px;">مكتمل وينتظر النتائج</span></div>`;

  html += `<form method="POST" action="save_test_results.php">`;
  html += `<input type="hidden" name="appointment_id" value="${item.appointment_id}">`;

  item.tests.forEach((test, i) => {
    html += `
      <div style="margin-bottom:16px;">
        <label style="font-size:0.82rem;font-weight:600;color:#444;display:block;margin-bottom:6px;">${test.test_name}</label>

        <input type="hidden" name="test_type_id[]" value="${test.test_type_id}">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <input
            type="text"
            name="result_value[]"
            placeholder="القيمة"
            required
            style="padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;text-align:right;"
          >

          <input
            type="text"
            name="normal_range[]"
            placeholder="النطاق الطبيعي"
            style="padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;text-align:right;"
          >

          <select
            name="status_flag[]"
            required
            style="padding:11px 14px;border:1.5px solid #e8e0d8;border-radius:10px;font-family:Tajawal,sans-serif;font-size:0.9rem;outline:none;background:#faf8f5;"
          >
            <option value="">اختر الحالة</option>
            <option value="normal">طبيعي</option>
            <option value="low">منخفض</option>
            <option value="high">مرتفع</option>
          </select>
        </div>
      </div>
    `;
  });

  html += `<button class="btn btn-primary" style="width:100%;margin-top:8px;" type="submit">رفع النتائج ✓</button>`;
  html += `</form>`;

  document.getElementById('result-form').innerHTML = html;
}

let selectedReportedUser = '';

function openReportPopup(userName, appointmentId, customerId) {
  selectedReportedUser = userName;
  document.getElementById('reportUserName').textContent = 'العميل: ' + userName;
  document.getElementById('reportAppointmentId').value = appointmentId;
  document.getElementById('reportCustomerId').value = customerId;
  document.getElementById('reportType').value = '';
  document.getElementById('reportNote').value = '';
  document.getElementById('reportModal').style.display = 'flex';
}

function closeReportPopup() {
  document.getElementById('reportModal').style.display = 'none';
}

function submitReport() {
  const type = document.getElementById('reportType').value;
  const note = document.getElementById('reportNote').value.trim();
  const appointmentId = document.getElementById('reportAppointmentId').value;
  const customerId = document.getElementById('reportCustomerId').value;

  if (!type) {
    alert('اختر نوع البلاغ أولاً');
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'save_report.php';

  const fields = {
    appointment_id: appointmentId,
    customer_id: customerId,
    report_type: type,
    report_note: note
  };

  for (const key in fields) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = fields[key];
    form.appendChild(input);
  }

  document.body.appendChild(form);
  form.submit();
}
</script>
</body>
</html>
