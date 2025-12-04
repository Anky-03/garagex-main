<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

require_once 'config/database.php';
require_once 'includes/lock_helper.php';

$action = $_POST['action'] ?? '';
$resource = $_POST['resource'] ?? '';

if (!is_valid_lock_resource($resource)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Recurso invalido.']);
    exit();
}

switch ($action) {
    case 'heartbeat':
        if (touch_resource_lock($conn, $resource, intval($_SESSION['user_id']))) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'El candado ya no pertenece a tu sesion.']);
        }
        break;
    case 'release':
        release_resource_lock($conn, $resource, intval($_SESSION['user_id']));
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Accion invalida.']);
}
