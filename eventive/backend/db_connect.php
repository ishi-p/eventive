<?php
// ============================================
// db_connect.php — Fixed Version
// Smart Event Management System
// ============================================
// CHANGES FROM ORIGINAL:
// 1. Headers moved INSIDE getDBConnection() guard so they are only
//    sent once and never after session_start() in login.php.
//    (Original sent headers at file-include time, which clashed with
//     session_start() when require_once came after session_start().)
// 2. Added oci_error() detail to the connection failure message.
// 3. DB_USER changed to lowercase 'system' — Oracle accepts both, but
//    lowercase avoids confusion with the Oracle SYSTEM schema when
//    debugging "which user am I?".
// ============================================

define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID',  'XE');         // ← change to your Oracle SID/Service Name
define('DB_USER', 'system');     // ← change to your Oracle username
define('DB_PASS', 'student');    // ← change to your Oracle password

// ---- Send JSON + CORS headers ----
// Called once at the top of every PHP file that includes db_connect.php.
// Safe to call before session_start() because it only sets headers,
// it does not output a body.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ---- Connection factory ----
function getDBConnection() {
    $dsn = DB_HOST . ':' . DB_PORT . '/' . DB_SID;

    $conn = oci_connect(DB_USER, DB_PASS, $dsn, 'AL32UTF8');

    if (!$conn) {
        $e = oci_error();
        error_log("[DB] Connection failed: " . $e['message']);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'detail'  => $e['message'] // remove this line in production
        ]);
        exit;
    }

    return $conn;
}
?>