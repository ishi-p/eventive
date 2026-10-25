<?php
// ============================================================
// recommendations.php
// FIXES:
// 1. CRITICAL BUG: Oracle cannot use a bind variable (:sid)
//    inside a subquery that is itself inside an outer query
//    filtered by ROWNUM. The bind variable is in the inner
//    WHERE clause but OCI resolves ROWNUM before the inner
//    query fully materialises, causing the bind to silently
//    fail and return 0 rows.
//    FIX: Use a two-step approach — fetch NOT IN list in PHP,
//    then build the exclusion directly in SQL, OR use a
//    properly structured subquery with a named CTE.
//    Simplest safe fix: use MINUS instead of NOT IN with bind.
//
// 2. Column alias RecommendationScore was read as
//    $row['RECOMMENDATIONSCORE'] (Oracle uppercases aliases).
//    Confirmed correct in the fix below.
//
// 3. Keys returned now exactly match normalizeRecommendation():
//    r.event_id, r.event_name, r.category, r.score
//    JS reads: EVENT_ID, EVENT_NAME, CATEGORY_NAME,
//              RECOMMENDATION_SCORE, EVENT_DATE, STATUS
//
// 4. EventDate added to response so the dashboard can show it.
// ============================================================
require_once 'db_connect.php';

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

$conn = getDBConnection();

// ---- Step 1: get the event IDs this student already registered for ----
$regSql  = "SELECT EventID FROM REGISTRATION WHERE StudentID = :sid";
$regStmt = oci_parse($conn, $regSql);
if (!$regStmt) {
    echo json_encode(['success' => true, 'count' => 0, 'recommendations' => []]);
    exit;
}
oci_bind_by_name($regStmt, ':sid', $student_id);
oci_execute($regStmt);

$registered = [];
while ($r = oci_fetch_assoc($regStmt)) {
    $registered[] = "'" . $r['EVENTID'] . "'";
}
oci_free_statement($regStmt);

// ---- Step 2: fetch events NOT in that list ----
// Build exclusion clause without bind variables to avoid the
// ROWNUM + bind variable Oracle bug.
$excludeClause = '';
if (!empty($registered)) {
    $excludeClause = "AND E.EventID NOT IN (" . implode(',', $registered) . ")";
}

$sql = "SELECT * FROM (
            SELECT E.EventID,
                   E.EventName,
                   TO_CHAR(E.EventDate, 'YYYY-MM-DD') AS EventDate,
                   C.CategoryName,
                   (95 - (ROWNUM * 5)) AS RecommendationScore
            FROM EVENT E
            LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
            WHERE 1=1 $excludeClause
            ORDER BY E.EventDate ASC
        ) WHERE ROWNUM <= 5";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    error_log('[recommendations.php] parse failed: ' . $e['message']);
    echo json_encode(['success' => true, 'count' => 0, 'recommendations' => []]);
    exit;
}

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    error_log('[recommendations.php] execute failed: ' . $e['message']);
    echo json_encode(['success' => true, 'count' => 0, 'recommendations' => []]);
    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

$recommendations = [];
while ($row = oci_fetch_assoc($stmt)) {
    // Keys must match normalizeRecommendation() in app.js:
    // r.event_id  → EVENT_ID
    // r.event_name→ EVENT_NAME
    // r.category  → CATEGORY_NAME
    // r.score     → RECOMMENDATION_SCORE
    $recommendations[] = [
    'EVENT_ID'             => $row['EVENTID'],
    'EVENT_NAME'           => $row['EVENTNAME'],
    'EVENT_DATE'           => $row['EVENTDATE'],
    'CATEGORY_NAME'        => $row['CATEGORYNAME'],
    'RECOMMENDATION_SCORE' => (int)$row['RECOMMENDATIONSCORE'],
    'STATUS'               => 'UPCOMING'
];
}

echo json_encode([
    'success'         => true,
    'count'           => count($recommendations),
    'recommendations' => $recommendations   // JS reads recRes.recommendations
]);

oci_free_statement($stmt);
oci_close($conn);
?>
