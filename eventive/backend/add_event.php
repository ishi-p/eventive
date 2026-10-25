<?php
// ============================================================
// add_event.php — Add / Update / Delete events
//
// FIXES:
// 1. Accepts the field names the admin form actually sends
//    (name, date, venue_id, category_id, budget, organizer_id)
//    AND the old names (event_name, event_date, coordinator_id).
// 2. EventID is now AUTO-GENERATED (e.g. MA001, DA002) when not given.
// 3. Accepts <input type="datetime-local"> values ("2026-04-10T14:30").
// 4. Budget of 0 no longer rejected by empty().
// 5. Coordinator is optional-safe: blank -> NULL (if column allows).
// 6. Errors return JSON (no stray PHP warnings).
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$conn   = getDBConnection();
$action = $input['action'] ?? 'add';

// ---- helpers ----
function pick($input, $keys, $default = '') {
    foreach ($keys as $k) {
        if (isset($input[$k]) && $input[$k] !== '') return trim((string)$input[$k]);
    }
    return $default;
}

// "2026-04-10T14:30" / "2026-04-10 14:30" / "2026-04-10"  ->  "2026-04-10 14:30"
function normaliseDate($d) {
    $d = str_replace('T', ' ', trim($d));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?/', $d)) return null;
    $date = substr($d, 0, 10);
    $time = (strlen($d) >= 16) ? substr($d, 11, 5) : '00:00';
    return "$date $time";
}

// Build next EventID: 2-letter category prefix + 3-digit running number
function generateEventId($conn, $categoryId) {
    $prefix = 'EV';
    $s = oci_parse($conn, "SELECT CategoryName FROM EVENTCATEGORY WHERE CategoryID = :c");
    oci_bind_by_name($s, ':c', $categoryId);
    oci_execute($s);
    if ($r = oci_fetch_assoc($s)) {
        $letters = preg_replace('/[^A-Za-z]/', '', $r['CATEGORYNAME']);
        if (strlen($letters) >= 2) $prefix = strtoupper(substr($letters, 0, 2));
    }
    oci_free_statement($s);

    $s = oci_parse($conn,
        "SELECT NVL(MAX(TO_NUMBER(REGEXP_SUBSTR(EventID, '[0-9]+$'))), 0) + 1 AS NXT FROM EVENT");
    oci_execute($s);
    $r   = oci_fetch_assoc($s);
    $nxt = (int)$r['NXT'];
    oci_free_statement($s);

    return $prefix . str_pad($nxt, 3, '0', STR_PAD_LEFT);
}

// ============================================
// ADD
// ============================================
if ($action === 'add') {

    $name   = pick($input, ['event_name', 'name']);
    $dateIn = pick($input, ['event_date', 'date']);
    $cat    = pick($input, ['category_id']);
    $venue  = pick($input, ['venue_id']);
    $coord  = pick($input, ['coordinator_id', 'organizer_id'], null);
    $budget = pick($input, ['budget'], '0');
    $eid    = pick($input, ['event_id']);

    foreach (['Event name' => $name, 'Event date' => $dateIn, 'Category' => $cat, 'Venue' => $venue] as $label => $val) {
        if ($val === '') {
            echo json_encode(['success' => false, 'message' => "$label is required"]);
            exit;
        }
    }
    if (!is_numeric($budget) || $budget < 0) {
        echo json_encode(['success' => false, 'message' => 'Budget must be a non-negative number']);
        exit;
    }
    $date = normaliseDate($dateIn);
    if ($date === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }

    if ($eid === '') $eid = generateEventId($conn, $cat);

    $sql = "INSERT INTO EVENT
              (EventID, EventName, EventDate, BudgetAllocated, CategoryID, VenueID, CoordinatorID)
            VALUES
              (:eid, :name, TO_DATE(:dt, 'YYYY-MM-DD HH24:MI'), :budget, :cat, :venue, :coord)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':eid',    $eid);
    oci_bind_by_name($stmt, ':name',   $name);
    oci_bind_by_name($stmt, ':dt',     $date);
    oci_bind_by_name($stmt, ':budget', $budget);
    oci_bind_by_name($stmt, ':cat',    $cat);
    oci_bind_by_name($stmt, ':venue',  $venue);
    oci_bind_by_name($stmt, ':coord',  $coord);

    // default flag = COMMIT ON SUCCESS -> AFTER INSERT analytics trigger fires
    if (oci_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Event added', 'event_id' => $eid]);
    } else {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => $e['message']]);
    }
    oci_free_statement($stmt);

// ============================================
// UPDATE
// ============================================
} elseif ($action === 'update') {

    $eid = pick($input, ['event_id']);
    if ($eid === '') { echo json_encode(['success' => false, 'message' => 'Event ID required']); exit; }

    $name   = pick($input, ['event_name', 'name']);
    $date   = normaliseDate(pick($input, ['event_date', 'date']));
    $budget = pick($input, ['budget'], '0');
    $cat    = pick($input, ['category_id']);
    $venue  = pick($input, ['venue_id']);
    $coord  = pick($input, ['coordinator_id', 'organizer_id'], null);

    if ($date === null) { echo json_encode(['success' => false, 'message' => 'Invalid date format']); exit; }

    $sql = "UPDATE EVENT SET EventName = :name,
                EventDate = TO_DATE(:dt, 'YYYY-MM-DD HH24:MI'),
                BudgetAllocated = :budget, CategoryID = :cat,
                VenueID = :venue, CoordinatorID = :coord
            WHERE EventID = :eid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name',   $name);
    oci_bind_by_name($stmt, ':dt',     $date);
    oci_bind_by_name($stmt, ':budget', $budget);
    oci_bind_by_name($stmt, ':cat',    $cat);
    oci_bind_by_name($stmt, ':venue',  $venue);
    oci_bind_by_name($stmt, ':coord',  $coord);
    oci_bind_by_name($stmt, ':eid',    $eid);

    if (oci_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Event updated']);
    } else {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => $e['message']]);
    }
    oci_free_statement($stmt);

// ============================================
// DELETE
// ============================================
} elseif ($action === 'delete') {

    $eid = pick($input, ['event_id']);
    if ($eid === '') { echo json_encode(['success' => false, 'message' => 'Event ID required']); exit; }

    $stmt = oci_parse($conn, "DELETE FROM EVENT WHERE EventID = :eid");
    oci_bind_by_name($stmt, ':eid', $eid);

    if (oci_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Event deleted']);
    } else {
        $e = oci_error($stmt); // ORA-02292 if child rows exist (registrations etc.)
        echo json_encode(['success' => false, 'message' => $e['message']]);
    }
    oci_free_statement($stmt);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

oci_close($conn);
?>
