<?php
// API REST para autenticación

// Solo permitir POST para login
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Usa POST para iniciar sesión.'
    ]);
    exit;
}

// Validar datos de inicio de sesión
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos obligatorios'
    ]);
    exit;
}

$email = sanitize_input($data['email']);
$password = $data['password'];

// Validar campos
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, completa todos los campos'
    ]);
    exit;
}

// Verificar credenciales
$sql = "SELECT id, nombre, email, password, role FROM usuarios WHERE email = '$email'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 1) {
    $row = mysqli_fetch_assoc($result);
    
    if (password_verify($password, $row['password'])) {
        // Iniciar sesión
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['nombre'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['role'] = $row['role'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'user' => [
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'email' => $row['email'],
                'role' => $row['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Contraseña incorrecta'
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No existe una cuenta con ese correo electrónico'
    ]);
} 