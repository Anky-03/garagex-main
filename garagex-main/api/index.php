<?php
// API REST para GarageX
header('Content-Type: application/json');

// Permitir solicitudes desde el mismo origen (CORS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Para solicitudes OPTIONS (pre-flight de CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar que el usuario esté autenticado
session_start();
if (!isset($_SESSION['user_id']) && !preg_match('/\/api\/login/', $_SERVER['REQUEST_URI'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';

// Sanitizar entrada
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}

// Obtener parámetros desde GET
$resource = isset($_GET['resource']) ? sanitize_input($_GET['resource']) : null;
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : null;
$id = isset($_GET['id']) ? sanitize_input($_GET['id']) : null;

// Parsear la URL para obtener parámetros alternativos
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/api/', $request_uri);

if (count($uri_parts) < 2) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado'
    ]);
    exit;
}

// Extraer endpoint y partes
$endpoint = $uri_parts[1];
$endpoint_parts = explode('/', $endpoint);

// Si no hay resource en GET, intentar obtenerlo de la URL
if (!$resource && isset($endpoint_parts[0])) {
    $resource = $endpoint_parts[0];
}

// Si hay más partes y no hay action en GET, usar el segundo segmento como action o id
if (!$action && isset($endpoint_parts[1]) && $endpoint_parts[1] !== 'index.php') {
    $action = $endpoint_parts[1];
}

// Si hay una tercera parte y no hay id en GET, usar el tercer segmento como id
if (!$id && isset($endpoint_parts[2])) {
    $id = $endpoint_parts[2];
}

// Si hay parámetros de consulta en la URL, analizarlos
$query_string = parse_url($request_uri, PHP_URL_QUERY);
if ($query_string) {
    parse_str($query_string, $query_params);
    
    // Si hay resource en los parámetros de consulta y no se ha establecido aún
    if (!$resource && isset($query_params['resource'])) {
        $resource = sanitize_input($query_params['resource']);
    }
    
    // Si hay action en los parámetros de consulta y no se ha establecido aún
    if (!$action && isset($query_params['action'])) {
        $action = sanitize_input($query_params['action']);
    }
    
    // Si hay id en los parámetros de consulta y no se ha establecido aún
    if (!$id && isset($query_params['id'])) {
        $id = sanitize_input($query_params['id']);
    }
}

// Depuración (comentar en producción)
/*
error_log("Resource: " . $resource);
error_log("Action: " . $action);
error_log("ID: " . $id);
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
*/

// Reconstruir endpoint_parts para compatibilidad con el código existente
$endpoint_parts = [];
$endpoint_parts[0] = $resource;
if ($action) {
    $endpoint_parts[1] = $action;
}
if ($id) {
    $endpoint_parts[1] = isset($endpoint_parts[1]) ? $endpoint_parts[1] : $id; // Si no hay action, usar id como second part
    $endpoint_parts[2] = $id;
}

// Obtener el método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Obtener los datos enviados
$data = json_decode(file_get_contents('php://input'), true);
if ($data === null && $method !== 'GET' && $method !== 'DELETE') {
    $data = $_POST;
}

// Manejar las solicitudes según el recurso y el método
switch ($resource) {
    case 'cars':
        require_once 'resources/cars.php';
        break;
    
    case 'users':
        require_once 'resources/users.php';
        break;
    
    case 'login':
        require_once 'resources/auth.php';
        break;
    
    case 'reports':
        require_once 'resources/reports.php';
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Recurso no encontrado'
        ]);
        break;
} 