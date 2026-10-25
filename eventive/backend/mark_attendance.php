<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$event_id   = $input['event_id'] ?? '';
$student_id = $input['student_id'] ?? '';
$status     = strtoupper($input['status'] ?? 'PRESENT');

if (empty($event_id) || empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Event ID and Student ID required']);
    exit;
}

$conn = getDBConnection();

// check existing
$chkSql = "SELECT COUNT(*) AS CNT FROM ATTENDANCE WHERE EventID = :eid AND StudentID = :sid";
$chkStmt = oci_parse($conn, $chkSql);
oci_bind_by_name($chkStmt, ':eid', $event_id);
oci_bind_by_name($chkStmt, ':sid', $student_id);
oci_execute($chkStmt);
$row = oci_fetch_assoc($chkStmt);
oci_free_statement($chkStmt);

if ($row['CNT'] > 0) {
    $sql = "UPDATE ATTENDANCE 
            SET Status = :status 
            WHERE EventID = :eid AND StudentID = :sid";
} else {
    $sql = "INSERT INTO ATTENDANCE 
            (AttendanceID, EventID, StudentID, Status)
            VALUES (ATT_SEQ.NEXTVAL, :eid, :sid, :status)";
}

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':eid', $event_id);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_bind_by_name($stmt, ':status', $status);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

oci_commit($conn); // 🔥 VERY IMPORTANT

echo json_encode(['success' => true]);

oci_free_statement($stmt);
oci_close($conn);
?>