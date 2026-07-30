<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    empty($_SESSION['db_host']) ||
    empty($_SESSION['db_user']) ||
    !array_key_exists('db_pass', $_SESSION)
) {
    header('Location: login.php');
    exit;
}

$db_host = $_SESSION['db_host'];
$db_user = $_SESSION['db_user'];
$db_pass = $_SESSION['db_pass'];
$db_name = !empty($_SESSION['db_name']) ? $_SESSION['db_name'] : '';

try {
    $conn = $db_name !== ''
        ? new mysqli($db_host, $db_user, $db_pass, $db_name)
        : new mysqli($db_host, $db_user, $db_pass);

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
} catch (Exception $e) {
    $_SESSION['db_error'] = $e->getMessage();
    unset($_SESSION['db_host'], $_SESSION['db_user'],
          $_SESSION['db_pass'], $_SESSION['db_name']);
    header('Location: login.php');
    exit;
}

$conn->set_charset('utf8mb4');
?>
