<?php
// Lists coordinators (students chosen by admin) for the Add Event form.
require_once 'db_connect.php';

$conn = getDBConnection();
$stmt = oci_parse($conn, "SELECT CoordinatorID, Name FROM COORDINATOR ORDER BY Name");

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

$data = [];
while ($row = oci_fetch_assoc($stmt)) { $data[] = $row; }
echo json_encode($data);

oci_free_statement($stmt);
oci_close($conn);
?>
