<?php
// API REST para recursos de carros

// Obtener ID del recurso si existe
$id = isset($endpoint_parts[1]) ? sanitize_input($endpoint_parts[1]) : null;

// Manejar las solicitudes según el método HTTP
switch ($method) {
    case 'GET':
        // Obtener marcas (para combos)
        if ($id === 'marcas') {
            $sql = "SELECT DISTINCT marca FROM carros ORDER BY marca";
            $result = mysqli_query($conn, $sql);
            
            if ($result) {
                $marcas = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $marcas[] = $row['marca'];
                }
                
                echo json_encode([
                    'success' => true,
                    'marcas' => $marcas
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener las marcas: ' . mysqli_error($conn)
                ]);
            }
            break;
        }
        
        // Endpoint para obtener estadísticas de cambios de aceite por usuario
        if ($id === 'cambios-aceite' && isset($endpoint_parts[2])) {
            $user_id = sanitize_input($endpoint_parts[2]);
            
            // Verificar permisos (solo admin)
            if ($_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para ver estas estadísticas'
                ]);
                break;
            }
            
            // Obtener vehículos del usuario
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM carros c 
                    JOIN usuarios u ON c.id_usuario = u.id 
                    WHERE c.id_usuario = '$user_id'";
                    
            $result = mysqli_query($conn, $sql);
            
            if ($result) {
                $stats = [
                    'total_cambios' => 0,
                    'proximos_cambios' => 0,
                    'vehiculos' => []
                ];
                
                while ($car = mysqli_fetch_assoc($result)) {
                    $stats['total_cambios'] += $car['contador_cambios'];
                    
                    // Verificar si está próximo a un cambio (si le faltan menos de 1000 km)
                    $necesita_cambio = $car['kilometraje'] >= $car['proximo_cambio'] || 
                                      ($car['kilometraje'] >= 10000 && $car['contador_cambios'] == 0);
                    $proximo_cambio = ($car['proximo_cambio'] - $car['kilometraje']) < 1000 && 
                                    $car['kilometraje'] < $car['proximo_cambio'] &&
                                    !$necesita_cambio;
                    
                    if ($proximo_cambio) {
                        $stats['proximos_cambios']++;
                    }
                    
                    $stats['vehiculos'][] = [
                        'id' => $car['id'],
                        'marca' => $car['marca'],
                        'modelo' => $car['modelo'],
                        'kilometraje' => $car['kilometraje'],
                        'contador_cambios' => $car['contador_cambios'],
                        'proximo_cambio' => $car['proximo_cambio'],
                        'km_faltantes' => max(0, $car['proximo_cambio'] - $car['kilometraje']),
                        'necesita_cambio' => $necesita_cambio
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener estadísticas: ' . mysqli_error($conn)
                ]);
            }
            break;
        }
        
        // Obtener todos los carros o uno específico
        if ($id) {
            // Obtener un carro específico
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM carros c 
                    JOIN usuarios u ON c.id_usuario = u.id 
                    WHERE c.id = '$id'";
            
            // Verificar permisos (solo admin o propietario)
            if ($_SESSION['role'] !== 'admin') {
                $user_id = $_SESSION['user_id'];
                $sql .= " AND c.id_usuario = '$user_id'";
            }
            
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $car = mysqli_fetch_assoc($result);
                echo json_encode([
                    'success' => true,
                    'car' => $car
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Vehículo no encontrado o no tienes permiso para verlo'
                ]);
            }
        } else {
            // Obtener todos los carros
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM carros c 
                    JOIN usuarios u ON c.id_usuario = u.id";
            
            // Filtrar por usuario si no es admin
            if ($_SESSION['role'] !== 'admin') {
                $user_id = $_SESSION['user_id'];
                $sql .= " WHERE c.id_usuario = '$user_id'";
            }
            
            $sql .= " ORDER BY c.id DESC";
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
        }
        break;
    
    case 'POST':
        // Endpoint para registrar cambio de aceite
        if ($id === 'registrar-cambio' && isset($endpoint_parts[2])) {
            $car_id = sanitize_input($endpoint_parts[2]);
            
            // Verificar que el carro exista y el usuario tenga permisos
            $check_sql = "SELECT * FROM carros WHERE id = '$car_id'";
            if ($_SESSION['role'] !== 'admin') {
                $user_id = $_SESSION['user_id'];
                $check_sql .= " AND id_usuario = '$user_id'";
            }
            
            $check_result = mysqli_query($conn, $check_sql);
            if (mysqli_num_rows($check_result) == 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Vehículo no encontrado o no tienes permiso para registrar cambios'
                ]);
                break;
            }
            
            $car = mysqli_fetch_assoc($check_result);
            
            // Incrementar contador y establecer próximo cambio (cada 10,000 km)
            $contador_cambios = $car['contador_cambios'] + 1;
            $proximo_cambio = $car['kilometraje'] + 10000;
            
            $sql = "UPDATE carros SET 
                    contador_cambios = '$contador_cambios', 
                    proximo_cambio = '$proximo_cambio', 
                    fecha_ultimo_cambio = CURRENT_TIMESTAMP, 
                    notificado = 0 
                    WHERE id = '$car_id'";
            
            if (mysqli_query($conn, $sql)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Cambio de aceite registrado correctamente',
                    'contador_cambios' => $contador_cambios,
                    'proximo_cambio' => $proximo_cambio
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al registrar el cambio: ' . mysqli_error($conn)
                ]);
            }
            break;
        }
        
        // Crear un nuevo carro
        if (!isset($data['marca']) || !isset($data['modelo']) || !isset($data['año']) || !isset($data['kilometraje'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan datos obligatorios'
            ]);
            break;
        }
        
        $marca = sanitize_input($data['marca']);
        $modelo = sanitize_input($data['modelo']);
        $año = sanitize_input($data['año']);
        $kilometraje = sanitize_input($data['kilometraje']);
        $id_usuario = $_SESSION['user_id'];
        
        // Si es admin y se especificó un usuario
        if ($_SESSION['role'] === 'admin' && isset($data['id_usuario'])) {
            $id_usuario = sanitize_input($data['id_usuario']);
        }
        
        // Verificar si ya existe un registro similar creado recientemente (30 segundos)
        $check_duplicate_sql = "SELECT id FROM carros 
                                WHERE id_usuario = '$id_usuario' 
                                AND marca = '$marca' 
                                AND modelo = '$modelo' 
                                AND año = '$año' 
                                AND kilometraje = '$kilometraje' 
                                AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)";
        
        $duplicate_result = mysqli_query($conn, $check_duplicate_sql);
        
        if ($duplicate_result && mysqli_num_rows($duplicate_result) > 0) {
            // Ya existe un registro similar reciente, devolver ese ID en lugar de crear uno nuevo
            $existing_car = mysqli_fetch_assoc($duplicate_result);
            echo json_encode([
                'success' => true,
                'message' => 'Vehículo registrado correctamente',
                'id' => $existing_car['id'],
                'info' => 'Se evitó la duplicación del registro'
            ]);
            break;
        }
        
        // Calcular próximo cambio de aceite (10,000 km después del kilometraje actual)
        $proximo_cambio = $kilometraje + 10000;
        
        // Insertar con los campos de seguimiento de cambios de aceite inicializados
        $sql = "INSERT INTO carros (id_usuario, marca, modelo, año, kilometraje, proximo_cambio, contador_cambios, created_at) 
                VALUES ('$id_usuario', '$marca', '$modelo', '$año', '$kilometraje', '$proximo_cambio', '0', NOW())";
        
        if (mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode([
                'success' => true,
                'message' => 'Vehículo creado correctamente',
                'id' => $new_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear el vehículo: ' . mysqli_error($conn)
            ]);
        }
        break;
    
    case 'PUT':
        // Actualizar un carro existente
        if (!$id) {
            // Intentar obtener el ID desde los datos enviados
            if (isset($data['id'])) {
                $id = sanitize_input($data['id']);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de vehículo no proporcionado'
                ]);
                break;
            }
        }
        
        // Verificar que el carro exista y el usuario tenga permisos
        $check_sql = "SELECT * FROM carros WHERE id = '$id'";
        if ($_SESSION['role'] !== 'admin') {
            $user_id = $_SESSION['user_id'];
            $check_sql .= " AND id_usuario = '$user_id'";
        }
        
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Vehículo no encontrado o no tienes permiso para editarlo'
            ]);
            break;
        }
        
        $car = mysqli_fetch_assoc($check_result);
        
        // Obtener y validar datos
        if (!isset($data['marca']) || !isset($data['modelo']) || !isset($data['año']) || !isset($data['kilometraje'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan datos obligatorios'
            ]);
            break;
        }
        
        $marca = sanitize_input($data['marca']);
        $modelo = sanitize_input($data['modelo']);
        $año = sanitize_input($data['año']);
        $kilometraje = sanitize_input($data['kilometraje']);
        
        // Si el kilometraje cambia y es mayor a 10,000, actualizar fecha de cambio
        if ($kilometraje != $car['kilometraje']) {
            $sql = "UPDATE carros SET marca = '$marca', modelo = '$modelo', año = '$año', 
                    kilometraje = '$kilometraje', fecha_ultimo_cambio = CURRENT_TIMESTAMP, 
                    notificado = 0 WHERE id = '$id'";
        } else {
            $sql = "UPDATE carros SET marca = '$marca', modelo = '$modelo', año = '$año', 
                    kilometraje = '$kilometraje' WHERE id = '$id'";
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Vehículo actualizado correctamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar el vehículo: ' . mysqli_error($conn)
            ]);
        }
        break;
    
    case 'DELETE':
        // Eliminar un carro
        if (!$id) {
            // Intentar obtener el ID desde los datos enviados
            if (isset($data['id'])) {
                $id = sanitize_input($data['id']);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de vehículo no proporcionado'
                ]);
                break;
            }
        }
        
        // Registrar información de depuración
        error_log("DELETE vehículo: ID = " . $id);
        
        // Verificar que el carro exista y el usuario tenga permisos
        $check_sql = "SELECT * FROM carros WHERE id = '$id'";
        if ($_SESSION['role'] !== 'admin') {
            $user_id = $_SESSION['user_id'];
            $check_sql .= " AND id_usuario = '$user_id'";
        }
        
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Vehículo no encontrado o no tienes permiso para eliminarlo'
            ]);
            break;
        }
        
        $sql = "DELETE FROM carros WHERE id = '$id'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Vehículo eliminado correctamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el vehículo: ' . mysqli_error($conn)
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