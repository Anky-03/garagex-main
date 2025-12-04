<?php
// API REST para recursos de usuarios

// Obtener ID del recurso si existe
$id = isset($endpoint_parts[1]) ? sanitize_input($endpoint_parts[1]) : null;
$subresource = isset($endpoint_parts[2]) ? $endpoint_parts[2] : null;

// Verificar si se está solicitando un subrecurso (ej: /users/1/cars)
if ($id && $subresource === 'cars') {
    // Verificar permisos (solo admin o el propio usuario)
    if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $id) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso para ver los vehículos de este usuario'
        ]);
        exit;
    }
    
    // Obtener los carros del usuario
    $sql = "SELECT * FROM carros WHERE id_usuario = '$id' ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $cars = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $cars[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'cars' => $cars
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener los vehículos: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Manejar las solicitudes según el método HTTP
switch ($method) {
    case 'GET':
        // Solo administradores pueden listar usuarios
        if ($_SESSION['role'] !== 'admin' && !$id) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para ver la lista de usuarios'
            ]);
            break;
        }
        
        // Obtener un usuario específico o todos
        if ($id) {
            // Verificar permisos (solo admin o el propio usuario)
            if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para ver este usuario'
                ]);
                break;
            }
            
            $sql = "SELECT id, nombre, email, role, created_at FROM usuarios WHERE id = '$id'";
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
            }
        } else {
            // Listar todos los usuarios (solo admin)
            $sql = "SELECT id, nombre, email, role, created_at FROM usuarios ORDER BY id";
            $result = mysqli_query($conn, $sql);
            
            if ($result) {
                $users = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $users[] = $row;
                }
                
                echo json_encode([
                    'success' => true,
                    'users' => $users
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener los usuarios: ' . mysqli_error($conn)
                ]);
            }
        }
        break;
    
    case 'POST':
        // Crear un nuevo usuario (solo admin)
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear usuarios'
            ]);
            break;
        }
        
        // Validar datos
        if (!isset($data['nombre']) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan datos obligatorios'
            ]);
            break;
        }
        
        $nombre = sanitize_input($data['nombre']);
        $email = sanitize_input($data['email']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = isset($data['role']) && $data['role'] === 'admin' ? 'admin' : 'usuario';
        
        // Verificar que el email no exista
        $check_sql = "SELECT id FROM usuarios WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe un usuario con ese correo electrónico'
            ]);
            break;
        }
        
        $sql = "INSERT INTO usuarios (nombre, email, password, role) 
                VALUES ('$nombre', '$email', '$password', '$role')";
        
        if (mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado correctamente',
                'id' => $new_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear el usuario: ' . mysqli_error($conn)
            ]);
        }
        break;
    
    case 'PUT':
        // Actualizar un usuario existente
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ]);
            break;
        }
        
        // Verificar permisos (solo admin o el propio usuario)
        if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $id) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar este usuario'
            ]);
            break;
        }
        
        // Verificar que el usuario exista
        $check_sql = "SELECT * FROM usuarios WHERE id = '$id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]);
            break;
        }
        
        $user = mysqli_fetch_assoc($check_result);
        
        // Datos a actualizar
        $updates = [];
        
        if (isset($data['nombre'])) {
            $nombre = sanitize_input($data['nombre']);
            $updates[] = "nombre = '$nombre'";
        }
        
        if (isset($data['email'])) {
            $email = sanitize_input($data['email']);
            
            // Verificar que el email no exista
            if ($email !== $user['email']) {
                $email_check = "SELECT id FROM usuarios WHERE email = '$email' AND id != '$id'";
                $email_result = mysqli_query($conn, $email_check);
                
                if (mysqli_num_rows($email_result) > 0) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ya existe un usuario con ese correo electrónico'
                    ]);
                    exit;
                }
            }
            
            $updates[] = "email = '$email'";
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $updates[] = "password = '$password'";
        }
        
        // Solo los administradores pueden cambiar el rol
        if ($_SESSION['role'] === 'admin' && isset($data['role'])) {
            $role = $data['role'] === 'admin' ? 'admin' : 'usuario';
            $updates[] = "role = '$role'";
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se proporcionaron datos para actualizar'
            ]);
            break;
        }
        
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado correctamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar el usuario: ' . mysqli_error($conn)
            ]);
        }
        break;
    
    case 'DELETE':
        // Eliminar un usuario (solo admin)
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ]);
            break;
        }
        
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar usuarios'
            ]);
            break;
        }
        
        // Verificar que el usuario exista
        $check_sql = "SELECT * FROM usuarios WHERE id = '$id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]);
            break;
        }
        
        // No permitir eliminar al último administrador
        $admin_check = "SELECT COUNT(*) as total FROM usuarios WHERE role = 'admin'";
        $admin_result = mysqli_query($conn, $admin_check);
        $admin_count = mysqli_fetch_assoc($admin_result)['total'];
        
        $user = mysqli_fetch_assoc($check_result);
        if ($user['role'] === 'admin' && $admin_count <= 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar el último administrador'
            ]);
            break;
        }
        
        $sql = "DELETE FROM usuarios WHERE id = '$id'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado correctamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el usuario: ' . mysqli_error($conn)
            ]);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
} 