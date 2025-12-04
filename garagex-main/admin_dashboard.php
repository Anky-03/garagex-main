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
    header("Location: dashboard.php");
    exit();
}

// Mapear usuarios locales para acceder a sus nombres y roles
$users_map = [];
$users_query = "SELECT id, nombre, role FROM usuarios";
$users_query_result = mysqli_query($conn, $users_query);
if ($users_query_result) {
    while ($user_row = mysqli_fetch_assoc($users_query_result)) {
        $users_map[$user_row['id']] = $user_row;
    }
}

// Obtener todos los carros desde la base federada
$cars_sql = "SELECT * FROM carros ORDER BY kilometraje DESC";
$cars_result = mysqli_query($conn_fed, $cars_sql);
$cars = [];
if ($cars_result) {
    while ($car_row = mysqli_fetch_assoc($cars_result)) {
        $car_row['nombre_usuario'] = isset($users_map[$car_row['id_usuario']])
            ? $users_map[$car_row['id_usuario']]['nombre']
            : 'Usuario #' . $car_row['id_usuario'];
        $cars[] = $car_row;
    }
}

// Obtener usuarios con vehículos para los selectores
$users_with_cars = [];
foreach ($cars as $car_row) {
    $uid = $car_row['id_usuario'];
    if (isset($users_map[$uid]) && $users_map[$uid]['role'] === 'usuario') {
        $users_with_cars[$uid] = $users_map[$uid]['nombre'];
    }
}
natcasesort($users_with_cars);

// Estadísticas
$total_users_sql = "SELECT COUNT(*) as total FROM usuarios WHERE role = 'usuario'";
$total_users_result = mysqli_query($conn, $total_users_sql);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_cars_sql = "SELECT COUNT(*) as total FROM carros";
$total_cars_result = mysqli_query($conn_fed, $total_cars_sql);
$total_cars = mysqli_fetch_assoc($total_cars_result)['total'];

// Consideramos que un vehículo necesita mantenimiento si:
// 1. El kilometraje es mayor o igual al próximo cambio programado, o
// 2. El kilometraje es mayor o igual a 10,000 km y nunca se ha registrado un cambio (contador_cambios = 0)
$maintenance_needed_sql = "SELECT COUNT(*) as total FROM carros WHERE (kilometraje >= proximo_cambio) OR (kilometraje >= 10000 AND contador_cambios = 0)";
$maintenance_needed_result = mysqli_query($conn_fed, $maintenance_needed_sql);
$maintenance_needed = mysqli_fetch_assoc($maintenance_needed_result)['total'];

// Incluir header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-tachometer-alt"></i> Panel de Administración</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="add_car.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Agregar Vehículo
            </a>
        </div>
    </div>
    
    <!-- Tarjetas de estadísticas -->
    <div class="row dashboard-stats">
        <div class="col-md-4 mb-4">
            <div class="stats-card bg-primary text-white">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_users; ?></h3>
                <p>Usuarios Registrados</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="stats-card bg-success text-white">
                <div class="stats-icon">
                    <i class="fas fa-car"></i>
                </div>
                <h3><?php echo $total_cars; ?></h3>
                <p>Vehículos en Sistema</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="stats-card bg-danger text-white">
                <div class="stats-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3><?php echo $maintenance_needed; ?></h3>
                <p>Necesitan Mantenimiento</p>
            </div>
        </div>
    </div>
    
    <!-- Tabs para diferentes reportes -->
    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cars-tab" data-bs-toggle="tab" data-bs-target="#cars-tab-pane" type="button" role="tab" aria-controls="cars-tab-pane" aria-selected="true">
                <i class="fas fa-list"></i> Vehículos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-tab-pane" type="button" role="tab" aria-controls="reports-tab-pane" aria-selected="false">
                <i class="fas fa-chart-bar"></i> Reportes
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="reportTabsContent">
        <!-- Tab de vehículos -->
        <div class="tab-pane fade show active" id="cars-tab-pane" role="tabpanel" aria-labelledby="cars-tab" tabindex="0">
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="user-search" class="form-label">Filtrar por usuario:</label>
                    <select id="user-search" class="form-select">
                        <option value="">Todos los usuarios</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="marca-search" class="form-label">Filtrar por marca:</label>
                    <select id="marca-search" class="form-select">
                        <option value="">Todas las marcas</option>
                    </select>
                </div>
            </div>
        
            <div class="table-responsive">
                <table id="cars-table" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Año</th>
                            <th>Kilometraje</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cars as $car): ?>
                            <tr data-id="<?php echo $car['id']; ?>" data-kilometraje="<?php echo $car['kilometraje']; ?>" data-user="<?php echo $car['nombre_usuario']; ?>">
                                <td><?php echo $car['id']; ?></td>
                                <td><?php echo htmlspecialchars($car['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($car['marca']); ?></td>
                                <td><?php echo htmlspecialchars($car['modelo']); ?></td>
                                <td><?php echo $car['año']; ?></td>
                                <td><?php echo number_format($car['kilometraje'], 0, ',', '.'); ?> km</td>
                                <td>
                                    <?php if ($car['kilometraje'] >= $car['proximo_cambio'] || ($car['kilometraje'] >= 10000 && $car['contador_cambios'] == 0)): ?>
                                        <span class="badge bg-danger">Cambio pendiente</span>
                                    <?php elseif (($car['proximo_cambio'] - $car['kilometraje']) < 1000): ?>
                                        <span class="badge bg-warning text-dark">Próximo al cambio</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Al día</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_car.php?id=<?php echo $car['id']; ?>&admin=1" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_car.php?id=<?php echo $car['id']; ?>&admin=1" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger delete-car" data-id="<?php echo $car['id']; ?>" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab de reportes -->
        <div class="tab-pane fade" id="reports-tab-pane" role="tabpanel" aria-labelledby="reports-tab" tabindex="0">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Estadísticas de Usuarios</h5>
                        </div>
                        <div class="card-body" id="users-report">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Cargando datos...</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-primary btn-sm" onclick="loadReport('usuarios')">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-car"></i> Estadísticas de Vehículos</h5>
                        </div>
                        <div class="card-body" id="cars-report">
                            <div class="text-center py-5">
                                <div class="spinner-border text-success" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Cargando datos...</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-success btn-sm" onclick="loadReport('carros')">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Estadísticas de Mantenimiento</h5>
                        </div>
                        <div class="card-body" id="maintenance-report">
                            <div class="text-center py-5">
                                <div class="spinner-border text-danger" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Cargando datos...</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-danger btn-sm" onclick="loadReport('mantenimiento')">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nuevo reporte de cambios de aceite por usuario -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h4 class="mb-0"><i class="fas fa-oil-can"></i> Cambios de Aceite por Usuario</h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cambios-usuario-select" class="form-label">Seleccionar Usuario:</label>
                                    <select id="cambios-usuario-select" class="form-select">
                                        <option value="">Selecciona un usuario</option>
                                        <?php foreach ($users_with_cars as $userId => $userName): ?>
                                            <option value="<?php echo $userId; ?>"><?php echo htmlspecialchars($userName); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button id="cargar-cambios-btn" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Cargar Estadísticas
                                    </button>
                                </div>
                            </div>
                            
                            <div id="cambios-usuario-container">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Selecciona un usuario para ver sus estadísticas de cambios de aceite.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Select2 para los filtros
    $('#user-search, #marca-search').select2({
        theme: 'bootstrap-5',
        allowClear: true
    });
    
    // Inicializar DataTables con retrieve:true para manejar reinicializaciones
    let carsTable = $('#cars-table').DataTable({
        retrieve: true,
        language: {
            "decimal": "",
            "emptyTable": "No hay datos disponibles en la tabla",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No se encontraron registros coincidentes",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            },
            "aria": {
                "sortAscending": ": activar para ordenar la columna ascendente",
                "sortDescending": ": activar para ordenar la columna descendente"
            }
        },
        responsive: true,
        order: [[0, "desc"]]
    });
    
    // Cargar usuarios
    $.ajax({
        url: 'api/index.php?resource=users',
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
            if (data.success) {
                // Reiniciar el selector completamente
                const userSelect = document.getElementById('user-search');
                userSelect.innerHTML = '<option value="">Todos los usuarios</option>';
                
                // Crear un objeto para almacenar usuarios únicos por nombre
                const uniqueUsersByName = {};
                
                // Añadir cada usuario al objeto (solo se guardará uno por nombre)
                data.users.forEach(user => {
                    uniqueUsersByName[user.nombre] = user;
                });
                
                // Convertir los valores del objeto a un array y ordenarlos por nombre
                const uniqueUsers = Object.values(uniqueUsersByName).sort((a, b) => 
                    a.nombre.localeCompare(b.nombre)
                );
                
                // Añadir los usuarios únicos ordenados al selector
                uniqueUsers.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.nombre;
                    option.textContent = user.nombre;
                    userSelect.appendChild(option);
                });
                
                // Evento de cambio
                $('#user-search').off('change').on('change', function() {
                    const userName = this.value;
                    
                    if (userName) {
                        carsTable.column(1).search(userName).draw();
                    } else {
                        carsTable.column(1).search('').draw();
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.error("Error al cargar usuarios:", error);
        }
    });
    
    // Cargar marcas
    $.ajax({
        url: 'api/index.php?resource=cars&action=marcas',
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
            if (data.success) {
                // Reiniciar el selector completamente
                const marcaSelect = document.getElementById('marca-search');
                marcaSelect.innerHTML = '<option value="">Todas las marcas</option>';
                
                // Crear un objeto para almacenar marcas únicas
                const uniqueMarcasByName = {};
                
                // Añadir cada marca al objeto (solo se guardará una por nombre)
                data.marcas.forEach(marca => {
                    uniqueMarcasByName[marca] = marca;
                });
                
                // Convertir los valores del objeto a un array y ordenarlos alfabéticamente
                const uniqueMarcas = Object.values(uniqueMarcasByName).sort((a, b) => 
                    a.localeCompare(b)
                );
                
                // Añadir las marcas únicas ordenadas al selector
                uniqueMarcas.forEach(marca => {
                    const option = document.createElement('option');
                    option.value = marca;
                    option.textContent = marca;
                    marcaSelect.appendChild(option);
                });
                
                // Evento de cambio
                $('#marca-search').off('change').on('change', function() {
                    const marca = this.value;
                    
                    if (marca) {
                        carsTable.column(2).search(marca).draw();
                    } else {
                        carsTable.column(2).search('').draw();
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.error("Error al cargar marcas:", error);
        }
    });
    
    // Cargar reportes iniciales
    loadReport('usuarios');
    loadReport('carros');
    loadReport('mantenimiento');

    // Cargar estadísticas de cambios de aceite por usuario
    const cambiosUsuarioSelect = document.getElementById('cambios-usuario-select');
    const cargarCambiosBtn = document.getElementById('cargar-cambios-btn');
    const cambiosUsuarioContainer = document.getElementById('cambios-usuario-container');
    
    cargarCambiosBtn.addEventListener('click', function() {
        const userId = cambiosUsuarioSelect.value;
        
        if (!userId) {
            cambiosUsuarioContainer.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Por favor, selecciona un usuario.
                </div>
            `;
            return;
        }
        
        // Mostrar loader
        cambiosUsuarioContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-secondary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando datos...</p>
            </div>
        `;
        
        // Cargar datos desde la API
        $.ajax({
            url: `api/index.php?resource=cars&action=cambios-aceite&id=${userId}`,
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(data) {
                if (data.success) {
                    const stats = data.stats;
                    
                    // Crear el contenido HTML
                    let html = `
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h1 class="display-4">${stats.total_cambios}</h1>
                                        <p class="lead">Cambios de Aceite Realizados</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h1 class="display-4">${stats.proximos_cambios}</h1>
                                        <p class="lead">Próximos a Cambio</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h1 class="display-4">${stats.vehiculos.length}</h1>
                                        <p class="lead">Vehículos Registrados</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (stats.vehiculos.length > 0) {
                        html += `
                            <div class="table-responsive mt-3">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vehículo</th>
                                            <th>Kilometraje</th>
                                            <th>Cambios realizados</th>
                                            <th>Próximo cambio</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        stats.vehiculos.forEach(car => {
                            html += `
                                <tr>
                                    <td>${car.marca} ${car.modelo}</td>
                                    <td>${car.kilometraje.toLocaleString()} km</td>
                                    <td>${car.contador_cambios}</td>
                                    <td>${car.proximo_cambio.toLocaleString()} km</td>
                                    <td>
                            `;
                            
                            if (car.kilometraje >= car.proximo_cambio || (car.kilometraje >= 10000 && car.contador_cambios == 0)) {
                                html += `<span class="badge bg-danger">Cambio pendiente</span>`;
                            } else if (car.km_faltantes < 1000) {
                                html += `<span class="badge bg-warning text-dark">Próximo (${car.km_faltantes} km)</span>`;
                            } else {
                                html += `<span class="badge bg-success">Al día (${car.km_faltantes} km)</span>`;
                            }
                            
                            html += `
                                    </td>
                                    <td>
                                        <a href="view_car.php?id=${car.id}&admin=1" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> Este usuario no tiene vehículos registrados.
                            </div>
                        `;
                    }
                    
                    cambiosUsuarioContainer.innerHTML = html;
                } else {
                    cambiosUsuarioContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Error al cargar los datos: ${data.message}
                        </div>
                    `;
                }
            },
            error: function(xhr, status, error) {
                cambiosUsuarioContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error de conexión: ${error}
                    </div>
                `;
            }
        });
    });
});

function loadReport(reportType) {
    const reportContainer = document.getElementById(`${reportType === 'usuarios' ? 'users' : reportType === 'carros' ? 'cars' : 'maintenance'}-report`);
    
    // Mostrar spinner de carga
    reportContainer.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-${reportType === 'usuarios' ? 'primary' : reportType === 'carros' ? 'success' : 'danger'}" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Cargando datos...</p>
        </div>
    `;
    
    // Cargar datos desde la API
    $.ajax({
        url: `api/index.php?resource=reports&action=${reportType}`,
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
            if (data.success) {
                const stats = data.stats;
                
                switch (reportType) {
                    case 'usuarios':
                        reportContainer.innerHTML = `
                            <p><strong>Total de usuarios:</strong> ${stats.total_usuarios}</p>
                            <hr>
                            <h6>Usuarios por rol:</h6>
                            <ul>
                                <li>Administradores: ${stats.usuarios_por_rol.admin || 0}</li>
                                <li>Usuarios regulares: ${stats.usuarios_por_rol.usuario || 0}</li>
                            </ul>
                            <hr>
                            <h6>Usuarios más recientes:</h6>
                            <ul class="list-unstyled">
                                ${stats.usuarios_recientes.map(user => 
                                    `<li><i class="fas fa-user"></i> ${user.nombre} (${user.email})</li>`
                                ).join('')}
                            </ul>
                        `;
                        break;
                        
                    case 'carros':
                        reportContainer.innerHTML = `
                            <p><strong>Total de vehículos:</strong> ${stats.total_carros}</p>
                            <hr>
                            <h6>Vehículos por marca:</h6>
                            <ul>
                                ${Object.entries(stats.carros_por_marca).map(([marca, total]) => 
                                    `<li>${marca}: ${total}</li>`
                                ).join('')}
                            </ul>
                            <hr>
                            <p><strong>Antigüedad promedio:</strong> ${stats.antiguedad_promedio} años</p>
                            <p><strong>Kilometraje promedio:</strong> ${stats.kilometraje_promedio.toLocaleString()} km</p>
                            <p><strong>Necesitan mantenimiento:</strong> ${stats.necesitan_mantenimiento}</p>
                        `;
                        break;
                        
                    case 'mantenimiento':
                        reportContainer.innerHTML = `
                            <h6>Estado de mantenimiento:</h6>
                            <div class="progress mb-3" style="height: 30px;">
                                <div class="progress-bar bg-success" style="width: ${Math.round(stats.estado_mantenimiento.al_dia / (stats.estado_mantenimiento.al_dia + stats.estado_mantenimiento.proximos + stats.estado_mantenimiento.atrasados) * 100)}%">
                                    ${stats.estado_mantenimiento.al_dia}
                                </div>
                                <div class="progress-bar bg-warning" style="width: ${Math.round(stats.estado_mantenimiento.proximos / (stats.estado_mantenimiento.al_dia + stats.estado_mantenimiento.proximos + stats.estado_mantenimiento.atrasados) * 100)}%">
                                    ${stats.estado_mantenimiento.proximos}
                                </div>
                                <div class="progress-bar bg-danger" style="width: ${Math.round(stats.estado_mantenimiento.atrasados / (stats.estado_mantenimiento.al_dia + stats.estado_mantenimiento.proximos + stats.estado_mantenimiento.atrasados) * 100)}%">
                                    ${stats.estado_mantenimiento.atrasados}
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><span class="badge bg-success">Al día</span> ${stats.estado_mantenimiento.al_dia} vehículos</li>
                                <li><span class="badge bg-warning text-dark">Próximos</span> ${stats.estado_mantenimiento.proximos} vehículos</li>
                                <li><span class="badge bg-danger">Atrasados</span> ${stats.estado_mantenimiento.atrasados} vehículos</li>
                            </ul>
                            <hr>
                            <h6>Vehículos que necesitan mantenimiento:</h6>
                            <ul class="list-unstyled small">
                                ${stats.vehiculos_mantenimiento.slice(0, 5).map(car => 
                                    `<li><i class="fas fa-car"></i> ${car.marca} ${car.modelo} (${car.kilometraje.toLocaleString()} km)</li>`
                                ).join('')}
                                ${stats.vehiculos_mantenimiento.length > 5 ? `<li>... y ${stats.vehiculos_mantenimiento.length - 5} más</li>` : ''}
                            </ul>
                        `;
                        break;
                }
            } else {
                reportContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error al cargar el reporte: ${data.message}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            reportContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error de conexión: ${error}
                </div>
            `;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?> 