<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'staff') {
        header("Location: my_tickets.php");
    } else {
        header("Location: dashboard.php");
    }
} else {
    header("Location: login.php");
}
exit;
?>
