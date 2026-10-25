<?php
// ============================================================
// events.php
// FIXES:
// 1. Single-event response: returns UPPERCASE keys (EVENT_NAME,
//    EVENT_DATE, VENUE, etc.) because the modal reads res.event
//    directly WITHOUT normalizeEvent — it uses e.EVENT_NAME etc.
// 2. List response: returns lowercase camelCase keys so
//    normalizeEvent() in app.js can map them correctly.
// 3. Oracle DATE columns are cast to string with TO_CHAR so they
//    arrive as "2026-04-10" not an OCI timestamp object.
// 4. Added oci_execute error handling so failures return JSON
//    instead of a blank page.
// ============================================================
require_once 'db_connect.php';

$conn     = getDBConnection();
$event_id = isset($_GET['event_id']) ? trim($_GET['event_id']) : '';

// ---- SINGLE EVENT (used by openEventModal) ----
if (!empty($event_id)) {

    $sql = "SELECT E.EventID,
                   E.EventName,
                   TO_CHAR(E.EventDate, 'YYYY-MM-DD HH24:MI') AS EventDate,
                   E.BudgetAllocated,
                   C.CategoryName,
                   V.VenueName,
                   (SELECT COUNT(*) FROM REGISTRATION R WHERE R.EventID = E.EventID) AS REG_COUNT
            FROM EVENT E
            LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
            LEFT JOIN VENUE        V ON E.VenueID     = V.VenueID
            WHERE E.EventID = :eid";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        echo json_encode(['success' => false, 'message' => $e['message']]);
        exit;
    }

    oci_bind_by_name($stmt, ':eid', $event_id);

    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => $e['message']]);
        oci_free_statement($stmt);
        oci_close($conn);
        exit;
    }

    $row = oci_fetch_assoc($stmt);

    if ($row) {
        // Modal reads res.event with UPPERCASE keys directly (no normalizeEvent call)
        echo json_encode([
            'success' => true,
            'event' => [
    'event_id'      => $row['EVENTID'],
    'name'          => $row['EVENTNAME'],
    'date'          => $row['EVENTDATE'],
    'venue'         => $row['VENUENAME'],
    'category'      => $row['CATEGORYNAME'],
    'registrations' => (int)$row['REG_COUNT'],
    'max_capacity'  => 100,
    'fee'           => 0,
    'organizer'     => 'College',
    'status'        => 'UPCOMING',
    'description'   => null
]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }

    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

// ---- ALL EVENTS (used by loadEvents / admin event list) ----
$sql = "SELECT E.EventID,
               E.EventName,
               TO_CHAR(E.EventDate, 'YYYY-MM-DD') AS EventDate,
               E.BudgetAllocated,
               C.CategoryName,
               V.VenueName,
               (SELECT COUNT(*) FROM REGISTRATION R WHERE R.EventID = E.EventID) AS REG_COUNT
        FROM EVENT E
        LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
        LEFT JOIN VENUE        V ON E.VenueID     = V.VenueID
        ORDER BY E.EventDate ASC";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

$events = [];
while ($row = oci_fetch_assoc($stmt)) {
    // These lowercase keys match what normalizeEvent() in app.js expects:
    // normalizeEvent maps: e.event_id, e.name, e.date, e.venue, e.category, e.registrations
    $events[] = [
        'event_id'      => $row['EVENTID'],
        'name'          => $row['EVENTNAME'],
        'date'          => $row['EVENTDATE'],
        'budget'        => $row['BUDGETALLOCATED'],
        'category'      => $row['CATEGORYNAME'],
        'venue'         => $row['VENUENAME'],
        'registrations' => (int)$row['REG_COUNT']
    ];
}

echo json_encode([
    'success' => true,
    'count'   => count($events),
    'events'  => $events
]);

oci_free_statement($stmt);
oci_close($conn);
?>
