<?php
require_once 'config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_REQUEST['action'] ?? '';

// Auto-create and select the database if no db_name was set at login
if (empty($_SESSION['db_name'])) {
    $conn->query("CREATE DATABASE IF NOT EXISTS studentdb");
    $conn->select_db('studentdb');
    $_SESSION['db_name'] = 'studentdb';
}

// Create students table if it does not exist
$conn->query("CREATE TABLE IF NOT EXISTS students (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    first_name   VARCHAR(50)  NOT NULL,
    last_name    VARCHAR(50)  NOT NULL,
    age          INT,
    college_name VARCHAR(100),
    program_name VARCHAR(100),
    year         INT,
    semester     VARCHAR(20)
)");

// ── Helpers ───────────────────────────────────────────────────────────────
function respond($data) {
    echo json_encode($data);
    exit;
}

function fail($msg, $code = 400) {
    http_response_code($code);
    respond(['ok' => false, 'error' => $msg]);
}

function clean($conn, $val) {
    return $conn->real_escape_string(trim($val));
}

// ── LIST ──────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $res  = $conn->query('SELECT * FROM students ORDER BY id DESC');
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    respond(['ok' => true, 'rows' => $rows]);
}

// ── ADD ───────────────────────────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fn  = clean($conn, $_POST['first_name']  ?? '');
    $ln  = clean($conn, $_POST['last_name']   ?? '');
    $age = intval($_POST['age']               ?? 0);
    $col = clean($conn, $_POST['college_name'] ?? '');
    $prg = clean($conn, $_POST['program_name'] ?? '');
    $yr  = intval($_POST['year']              ?? 1);
    $sem = clean($conn, $_POST['semester']    ?? '');

    if (!$fn || !$ln || !$col || !$prg || !$sem) {
        fail('Missing required fields.');
    }

    $sql = "INSERT INTO students
                (first_name, last_name, age, college_name, program_name, year, semester)
            VALUES
                ('$fn', '$ln', $age, '$col', '$prg', $yr, '$sem')";

    if (!$conn->query($sql)) {
        fail($conn->error, 500);
    }
    respond(['ok' => true, 'id' => $conn->insert_id]);
}

// ── UPDATE ────────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = intval($_POST['id']               ?? 0);
    $fn  = clean($conn, $_POST['first_name']  ?? '');
    $ln  = clean($conn, $_POST['last_name']   ?? '');
    $age = intval($_POST['age']               ?? 0);
    $col = clean($conn, $_POST['college_name'] ?? '');
    $prg = clean($conn, $_POST['program_name'] ?? '');
    $yr  = intval($_POST['year']              ?? 1);
    $sem = clean($conn, $_POST['semester']    ?? '');

    if (!$id || !$fn || !$ln) {
        fail('Missing required fields.');
    }

    $sql = "UPDATE students SET
                first_name   = '$fn',
                last_name    = '$ln',
                age          = $age,
                college_name = '$col',
                program_name = '$prg',
                year         = $yr,
                semester     = '$sem'
            WHERE id = $id";

    if (!$conn->query($sql)) {
        fail($conn->error, 500);
    }
    respond(['ok' => true, 'affected' => $conn->affected_rows]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        fail('Invalid ID.');
    }

    if (!$conn->query("DELETE FROM students WHERE id = $id")) {
        fail($conn->error, 500);
    }
    respond(['ok' => true, 'affected' => $conn->affected_rows]);
}

fail('Unknown action.', 404);
?>
