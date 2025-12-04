<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar si el usuario es admin, redirigir al dashboard de admin
if ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Obtener los carros del usuario actual usando la base federada
$user_id = mysqli_real_escape_string($conn_fed, $_SESSION['user_id']);
$sql = "SELECT * FROM carros WHERE id_usuario = '$user_id'";
$result = mysqli_query($conn_fed, $sql);

// Verificar notificaciones de mantenimiento
// Consideramos que un vehículo necesita mantenimiento si:
// 1. El kilometraje es mayor o igual al próximo cambio programado, o
// 2. El kilometraje es mayor o igual a 10,000 km y nunca se ha registrado un cambio (contador_cambios = 0)
$maintenance_sql = "SELECT * FROM carros WHERE id_usuario = '$user_id' AND 
                   ((kilometraje >= proximo_cambio) OR 
                   (kilometraje >= 10000 AND contador_cambios = 0)) AND 
                   notificado = 0";
$maintenance_result = mysqli_query($conn_fed, $maintenance_sql);

if (mysqli_num_rows($maintenance_result) > 0) {
    $cars_needing_maintenance = [];
    while ($maintenance_car = mysqli_fetch_assoc($maintenance_result)) {
        $cars_needing_maintenance[] = $maintenance_car['marca'] . ' ' . $maintenance_car['modelo'];
        
        // Actualizar el estado de notificación
        $car_id = $maintenance_car['id'];
        $update_notification = "UPDATE carros SET notificado = 1 WHERE id = '$car_id'";
        mysqli_query($conn_fed, $update_notification);
    }
    
    // Crear mensaje de notificación
    $message = "¡Atención! Los siguientes vehículos necesitan un cambio de aceite: " . implode(", ", $cars_needing_maintenance);
    $_SESSION['message'] = $message;
    $_SESSION['alert_type'] = "warning";
}

// Incluir header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-car"></i> Mis Vehículos</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="add_car.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Agregar Vehículo
            </a>
        </div>
    </div>
    
    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <label for="marca-search" class="form-label">Filtrar por marca:</label>
                <select id="marca-search" class="form-select">
                    <option value="">Todas las marcas</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="filter-maintenance">
                    <label class="form-check-label" for="filter-maintenance">
                        Mostrar solo vehículos que necesitan mantenimiento
                    </label>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="cars-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Año</th>
                        <th>Kilometraje</th>
                        <th>Último Cambio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($car = mysqli_fetch_assoc($result)): ?>
                        <tr data-id="<?php echo $car['id']; ?>" data-kilometraje="<?php echo $car['kilometraje']; ?>">
                            <td><?php echo $car['id']; ?></td>
                            <td><?php echo htmlspecialchars($car['marca']); ?></td>
                            <td><?php echo htmlspecialchars($car['modelo']); ?></td>
                            <td><?php echo $car['año']; ?></td>
                            <td><?php echo number_format($car['kilometraje'], 0, ',', '.'); ?> km</td>
                            <td><?php echo date('d/m/Y', strtotime($car['fecha_ultimo_cambio'])); ?></td>
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
                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($car['kilometraje'] >= $car['proximo_cambio'] || ($car['kilometraje'] >= 10000 && $car['contador_cambios'] == 0)): ?>
                                        <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Registrar cambio de aceite">
                                            <i class="fas fa-oil-can"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger delete-car" data-id="<?php echo $car['id']; ?>" data-bs-toggle="tooltip" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No tienes vehículos registrados. 
            <a href="add_car.php" class="alert-link">¡Agrega tu primer vehículo ahora!</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Select2 para el filtro de marcas
    $('#marca-search').select2({
        theme: 'bootstrap-5',
        placeholder: 'Selecciona una marca',
        allowClear: true
    });
    
    // Cargar marcas desde la API
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
            }
        },
        error: function(xhr, status, error) {
            console.error("Error al cargar marcas:", error);
        }
    });
    
    // Filtro de mantenimiento
    const filterMaintenance = document.getElementById('filter-maintenance');
    if (filterMaintenance) {
        filterMaintenance.addEventListener('change', function() {
            const rows = document.querySelectorAll('#cars-table tbody tr');
            rows.forEach(row => {
                const kilometraje = parseInt(row.getAttribute('data-kilometraje'));
                if (this.checked) {
                    // Mostrar vehículos con kilometraje mayor o igual a 10,000 km o 15,000 km si nunca han tenido cambio
                    const carId = row.getAttribute('data-id');
                    const needsMaintenance = row.querySelector('.badge.bg-danger') !== null;
                    row.style.display = needsMaintenance ? '' : 'none';
                } else {
                    row.style.display = '';
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?> 