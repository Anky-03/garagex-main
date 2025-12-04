<?php
// API REST para reportes gerenciales

// Solo administradores pueden acceder a los reportes
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No tienes permiso para acceder a los reportes'
    ]);
    exit;
}

// Verificar si se solicita un reporte específico
$report_type = isset($endpoint_parts[1]) ? sanitize_input($endpoint_parts[1]) : null;

// Solo permitir GET para reportes
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Usa GET para obtener reportes.'
    ]);
    exit;
}

// Manejar diferentes tipos de reportes
switch ($report_type) {
    case 'usuarios':
        // Reporte 1: Estadísticas de usuarios
        $stats = [];
        
        // Total de usuarios
        $total_sql = "SELECT COUNT(*) as total FROM usuarios";
        $total_result = mysqli_query($conn, $total_sql);
        $stats['total_usuarios'] = mysqli_fetch_assoc($total_result)['total'];
        
        // Usuarios por rol
        $role_sql = "SELECT role, COUNT(*) as total FROM usuarios GROUP BY role";
        $role_result = mysqli_query($conn, $role_sql);
        
        $stats['usuarios_por_rol'] = [];
        while ($row = mysqli_fetch_assoc($role_result)) {
            $stats['usuarios_por_rol'][$row['role']] = $row['total'];
        }
        
        // Usuarios más recientes
        $recent_sql = "SELECT id, nombre, email, role, created_at FROM usuarios ORDER BY created_at DESC LIMIT 5";
        $recent_result = mysqli_query($conn, $recent_sql);
        
        $stats['usuarios_recientes'] = [];
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $stats['usuarios_recientes'][] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        break;
    
    case 'carros':
        // Reporte 2: Estadísticas de vehículos
        $stats = [];
        
        // Total de vehículos
        $total_sql = "SELECT COUNT(*) as total FROM carros";
        $total_result = mysqli_query($conn, $total_sql);
        $stats['total_carros'] = mysqli_fetch_assoc($total_result)['total'];
        
        // Vehículos por marca
        $marca_sql = "SELECT marca, COUNT(*) as total FROM carros GROUP BY marca ORDER BY total DESC";
        $marca_result = mysqli_query($conn, $marca_sql);
        
        $stats['carros_por_marca'] = [];
        while ($row = mysqli_fetch_assoc($marca_result)) {
            $stats['carros_por_marca'][$row['marca']] = $row['total'];
        }
        
        // Antigüedad promedio de vehículos
        $year_sql = "SELECT AVG(" . date('Y') . " - año) as promedio FROM carros";
        $year_result = mysqli_query($conn, $year_sql);
        $stats['antiguedad_promedio'] = round(mysqli_fetch_assoc($year_result)['promedio'], 1);
        
        // Kilometraje promedio
        $km_sql = "SELECT AVG(kilometraje) as promedio FROM carros";
        $km_result = mysqli_query($conn, $km_sql);
        $stats['kilometraje_promedio'] = round(mysqli_fetch_assoc($km_result)['promedio']);
        
        // Vehículos que necesitan mantenimiento
        $maintenance_sql = "SELECT COUNT(*) as total FROM carros WHERE kilometraje >= 10000";
        $maintenance_result = mysqli_query($conn, $maintenance_sql);
        $stats['necesitan_mantenimiento'] = mysqli_fetch_assoc($maintenance_result)['total'];
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        break;
    
    case 'mantenimiento':
        // Reporte 3: Estadísticas de mantenimiento
        $stats = [];
        
        // Vehículos por estado de mantenimiento
        $stats['estado_mantenimiento'] = [
            'al_dia' => 0,
            'proximos' => 0,
            'atrasados' => 0
        ];
        
        // Al día (menos de 9,000 km)
        $al_dia_sql = "SELECT COUNT(*) as total FROM carros WHERE kilometraje < 9000";
        $al_dia_result = mysqli_query($conn, $al_dia_sql);
        $stats['estado_mantenimiento']['al_dia'] = mysqli_fetch_assoc($al_dia_result)['total'];
        
        // Próximos (entre 9,000 y 10,000 km)
        $proximos_sql = "SELECT COUNT(*) as total FROM carros WHERE kilometraje >= 9000 AND kilometraje < 10000";
        $proximos_result = mysqli_query($conn, $proximos_sql);
        $stats['estado_mantenimiento']['proximos'] = mysqli_fetch_assoc($proximos_result)['total'];
        
        // Atrasados (más de 10,000 km)
        $atrasados_sql = "SELECT COUNT(*) as total FROM carros WHERE kilometraje >= 10000";
        $atrasados_result = mysqli_query($conn, $atrasados_sql);
        $stats['estado_mantenimiento']['atrasados'] = mysqli_fetch_assoc($atrasados_result)['total'];
        
        // Vehículos que necesitan mantenimiento (detalle)
        $maintenance_sql = "SELECT c.*, u.nombre as nombre_usuario 
                           FROM carros c 
                           JOIN usuarios u ON c.id_usuario = u.id 
                           WHERE c.kilometraje >= 10000 
                           ORDER BY c.kilometraje DESC";
        $maintenance_result = mysqli_query($conn, $maintenance_sql);
        
        $stats['vehiculos_mantenimiento'] = [];
        while ($row = mysqli_fetch_assoc($maintenance_result)) {
            $stats['vehiculos_mantenimiento'][] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        break;
    
    default:
        // Lista de reportes disponibles
        echo json_encode([
            'success' => true,
            'reports' => [
                [
                    'id' => 'usuarios',
                    'name' => 'Estadísticas de Usuarios',
                    'description' => 'Muestra información sobre los usuarios registrados'
                ],
                [
                    'id' => 'carros',
                    'name' => 'Estadísticas de Vehículos',
                    'description' => 'Muestra información general sobre los vehículos registrados'
                ],
                [
                    'id' => 'mantenimiento',
                    'name' => 'Estadísticas de Mantenimiento',
                    'description' => 'Muestra información detallada sobre el estado de mantenimiento de los vehículos'
                ]
            ]
        ]);
        break;
} 