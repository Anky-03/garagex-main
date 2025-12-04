<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar si el usuario es admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "No tienes permiso para eliminar vehículos.";
    $_SESSION['alert_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// Verificar si se proporcionó ID del carro
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID de vehículo no proporcionado.";
    $_SESSION['alert_type'] = "danger";
    header("Location: admin_dashboard.php");
    exit();
}

$car_id = mysqli_real_escape_string($conn_fed, $_GET['id']);

// Obtener información del carro para verificar que existe
$check_sql = "SELECT * FROM carros WHERE id = '$car_id'";
$check_result = mysqli_query($conn_fed, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['message'] = "El vehículo no existe.";
    $_SESSION['alert_type'] = "danger";
    header("Location: admin_dashboard.php");
    exit();
}

// Eliminar el carro

$sql = "DELETE FROM carros WHERE id = '$car_id'";

if (mysqli_query($conn_fed, $sql)) {
    $_SESSION['message'] = "Vehículo eliminado correctamente.";
    $_SESSION['alert_type'] = "success";
} else {
    $_SESSION['message'] = "Error al eliminar el vehículo: " . mysqli_error($conn_fed);
    $_SESSION['alert_type'] = "danger";
}

header("Location: admin_dashboard.php");
exit(); 