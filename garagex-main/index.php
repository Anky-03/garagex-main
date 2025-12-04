<?php
session_start();
// Redireccionar a login si no hay sesión, o al dashboard si ya hay sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
} else {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
}
exit();
?> 