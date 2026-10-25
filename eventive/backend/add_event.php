<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$conn   = getDBConnection();


// ============================================
// ADD / UPDATE / DELETE
// ============================================
if ($method === 'POST') {

    $action = isset($input['action']) ? $input['action'] : 'add';


    // ============================================
    // ADD EVENT
    // ============================================
    if ($action === 'add') {

        $required = ['event_id','event_name','event_date','budget','category_id','venue_id','coordinator_id'];

        foreach ($required as $field) {
            if (empty($input[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "$field is required"
                ]);
                exit;
            }
        }

        $eid  = $input['event_id'];   // e.g. MA001
        $name = $input['event_name'];
        $date = $input['event_date']; // YYYY-MM-DD
        $budget = $input['budget'];
        $cat  = $input['category_id'];
        $venue = $input['venue_id'];
        $coord = $input['coordinator_id'];

        $sql = "INSERT INTO EVENT
                (EventID, EventName, EventDate, BudgetAllocated, CategoryID, VenueID, CoordinatorID)
                VALUES
                (:eid, :name, TO_DATE(:date,'YYYY-MM-DD'), :budget, :cat, :venue, :coord)";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ':eid', $eid);
        oci_bind_by_name($stmt, ':name', $name);
        oci_bind_by_name($stmt, ':date', $date);
        oci_bind_by_name($stmt, ':budget', $budget);
        oci_bind_by_name($stmt, ':cat', $cat);
        oci_bind_by_name($stmt, ':venue', $venue);
        oci_bind_by_name($stmt, ':coord', $coord);

        if (oci_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Event added']);
        } else {
            $e = oci_error($stmt);
            echo json_encode(['success' => false, 'message' => $e['message']]);
        }

        oci_free_statement($stmt);
    }


    // ============================================
    // UPDATE EVENT
    // ============================================
    elseif ($action === 'update') {

        $eid = $input['event_id'] ?? '';

        if (empty($eid)) {
            echo json_encode(['success' => false, 'message' => 'Event ID required']);
            exit;
        }

        $name = $input['event_name'] ?? '';
        $date = $input['event_date'] ?? '';
        $budget = $input['budget'] ?? 0;
        $cat = $input['category_id'] ?? '';
        $venue = $input['venue_id'] ?? '';
        $coord = $input['coordinator_id'] ?? '';

        $sql = "UPDATE EVENT SET
                    EventName = :name,
                    EventDate = TO_DATE(:date,'YYYY-MM-DD'),
                    BudgetAllocated = :budget,
                    CategoryID = :cat,
                    VenueID = :venue,
                    CoordinatorID = :coord
                WHERE EventID = :eid";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ':name', $name);
        oci_bind_by_name($stmt, ':date', $date);
        oci_bind_by_name($stmt, ':budget', $budget);
        oci_bind_by_name($stmt, ':cat', $cat);
        oci_bind_by_name($stmt, ':venue', $venue);
        oci_bind_by_name($stmt, ':coord', $coord);
        oci_bind_by_name($stmt, ':eid', $eid);

        if (oci_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Event updated']);
        } else {
            $e = oci_error($stmt);
            echo json_encode(['success' => false, 'message' => $e['message']]);
        }

        oci_free_statement($stmt);
    }


    // ============================================
    // DELETE EVENT
    // ============================================
    elseif ($action === 'delete') {

        $eid = $input['event_id'] ?? '';

        if (empty($eid)) {
            echo json_encode(['success' => false, 'message' => 'Event ID required']);
            exit;
        }

        $sql = "DELETE FROM EVENT WHERE EventID = :eid";

        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':eid', $eid);

        if (oci_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Event deleted']);
        } else {
            $e = oci_error($stmt);
            echo json_encode(['success' => false, 'message' => $e['message']]);
        }

        oci_free_statement($stmt);
    }


    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

oci_close($conn);
?>