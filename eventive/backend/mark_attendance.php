<?php
// ============================================================
// mark_attendance.php — Save attendance (single OR bulk)
// Accepts either:
//   { "event_id": "MA001", "student_id": "S1", "status": "PRESENT" }
//   { "event_id": "MA001", "records": [ {"student_id":"S1","status":"PRESENT"}, ... ] }
// The admin dashboard "Save All" button uses the bulk form.
// All rows are saved in ONE transaction (all succeed or none).
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
require_once 'db_connect.php';

$input    = json_decode(file_get_contents('php://input'), true);
$event_id = isset($input['event_id']) ? trim($input['event_id']) : '';

if ($event_id === '') {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit;
}

// Normalise to a list of records
if (isset($input['records']) && is_array($input['records'])) {
    $records = $input['records'];
} elseif (!empty($input['student_id'])) {
    $records = [[
        'student_id' => $input['student_id'],
        'status'     => $input['status'] ?? 'PRESENT'
    ]];
} else {
    echo json_encode(['success' => false, 'message' => 'No attendance records supplied']);
    exit;
}

$conn  = getDBConnection();
$saved = 0;

foreach ($records as $rec) {
    $sid    = isset($rec['student_id']) ? trim($rec['student_id']) : '';
    $status = strtoupper(trim($rec['status'] ?? ''));

    if ($sid === '' || !in_array($status, ['PRESENT', 'ABSENT'], true)) {
        continue; // skip invalid rows
    }

    // Try UPDATE first; if no row was touched, INSERT.
    $upd = oci_parse($conn,
        "UPDATE ATTENDANCE SET Status = :status
          WHERE EventID = :eid AND StudentID = :sid");
    oci_bind_by_name($upd, ':status', $status);
    oci_bind_by_name($upd, ':eid',    $event_id);
    oci_bind_by_name($upd, ':sid',    $sid);

    if (!oci_execute($upd, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($upd);
        oci_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e['message']]);
        exit;
    }
    $touched = oci_num_rows($upd);
    oci_free_statement($upd);

    if ($touched === 0) {
        $ins = oci_parse($conn,
            "INSERT INTO ATTENDANCE (AttendanceID, EventID, StudentID, Status)
             VALUES (ATT_SEQ.NEXTVAL, :eid, :sid, :status)");
        oci_bind_by_name($ins, ':eid',    $event_id);
        oci_bind_by_name($ins, ':sid',    $sid);
        oci_bind_by_name($ins, ':status', $status);

        if (!oci_execute($ins, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($ins);
            oci_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e['message']]);
            exit;
        }
        oci_free_statement($ins);
    }
    $saved++;
}

if ($saved === 0) {
    oci_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'No valid records to save']);
    oci_close($conn);
    exit;
}

// Commit once -> fires the analytics trigger for the whole batch
oci_commit($conn);
echo json_encode(['success' => true, 'saved' => $saved, 'message' => "Attendance saved for $saved student(s)"]);
oci_close($conn);
?>
