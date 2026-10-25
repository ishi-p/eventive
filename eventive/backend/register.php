<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
// ============================================================
// register.php
// FIXES:
// 1. Added oci_commit() — Oracle in OCI8 defaults to manual
//    transaction mode. Without commit the INSERT is rolled back
//    when the connection closes, so the row never persists.
// 2. Added oci_execute error check with oci_error() response.
// 3. rand() ID is kept for simplicity (college project).
//    If you have a sequence REG_SEQ, replace rand() with:
//    SELECT REG_SEQ.NEXTVAL FROM DUAL
// ============================================================
require_once 'db_connect.php';

$input      = json_decode(file_get_contents('php://input'), true);
$student_id = isset($input['student_id']) ? trim($input['student_id']) : '';
$event_id   = isset($input['event_id'])   ? trim($input['event_id'])   : '';

if (empty($student_id) || empty($event_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID and Event ID are required']);
    exit;
}

$conn = getDBConnection();

// ---- Duplicate check FIRST (old code inserted attendance before this) ----
$chkStmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM REGISTRATION WHERE StudentID = :sid AND EventID = :eid");
oci_bind_by_name($chkStmt, ':sid', $student_id);
oci_bind_by_name($chkStmt, ':eid', $event_id);
oci_execute($chkStmt);
$chkRow = oci_fetch_assoc($chkStmt);
oci_free_statement($chkStmt);

if ((int)$chkRow['CNT'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Already registered for this event']);
    oci_close($conn);
    exit;
}

// ---- Next RegistrationID (no rand() collisions). Replace with REG_SEQ.NEXTVAL if you have one ----
$idStmt = oci_parse($conn, "SELECT NVL(MAX(RegistrationID),0)+1 AS NXT FROM REGISTRATION");
oci_execute($idStmt);
$reg_id = (int)oci_fetch_assoc($idStmt)['NXT'];
oci_free_statement($idStmt);

// ---- Registration + default ABSENT attendance in ONE transaction ----
$stmt = oci_parse($conn, "INSERT INTO REGISTRATION (RegistrationID, StudentID, EventID) VALUES (:rid, :sid, :eid)");
oci_bind_by_name($stmt, ':rid', $reg_id);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_bind_by_name($stmt, ':eid', $event_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $e = oci_error($stmt);
    oci_rollback($conn);
    error_log('[register.php] INSERT failed: ' . $e['message']);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    oci_close($conn);
    exit;
}
oci_free_statement($stmt);

// Same ATT_SEQ as mark_attendance.php (old code used a string uniqid -> ORA-01722 on NUMBER column)
$att = oci_parse($conn, "INSERT INTO ATTENDANCE (AttendanceID, StudentID, EventID, Status)
                         VALUES (ATT_SEQ.NEXTVAL, :sid, :eid, 'ABSENT')");
oci_bind_by_name($att, ':sid', $student_id);
oci_bind_by_name($att, ':eid', $event_id);

if (!oci_execute($att, OCI_NO_AUTO_COMMIT)) {
    $e = oci_error($att);
    oci_rollback($conn);
    error_log('[register.php] ATTENDANCE insert failed: ' . $e['message']);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    oci_close($conn);
    exit;
}
oci_free_statement($att);

oci_commit($conn);
echo json_encode(['success' => true, 'message' => 'Registration successful']);
oci_close($conn);
?>
