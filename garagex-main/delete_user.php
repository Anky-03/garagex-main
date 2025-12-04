<?php
session_start();
require_once 'config/database.php';

// Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Acceso denegado.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_users.php');
    exit();
}

$id = intval($_GET['id']);

// Prevenir borrar al último admin
$admins = mysqli_query($conn, "SELECT id FROM usuarios WHERE role = 'admin'");
if ($admins && mysqli_num_rows($admins) <= 1) {
    $row = mysqli_fetch_assoc($admins);
    if ($row && intval($row['id']) === $id) {
        $_SESSION['message'] = 'No se puede eliminar al último administrador.';
        $_SESSION['alert_type'] = 'danger';
        header('Location: admin_users.php');
        exit();
    }
}

$sql = "DELETE FROM usuarios WHERE id = $id";
if (mysqli_query($conn, $sql)) {
    $_SESSION['message'] = 'Usuario eliminado.';
    $_SESSION['alert_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error: ' . mysqli_error($conn);
    $_SESSION['alert_type'] = 'danger';
}
header('Location: admin_users.php');
exit();
