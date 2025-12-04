<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar si se proporcionó ID del carro
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID de vehículo no proporcionado.";
    $_SESSION['alert_type'] = "danger";
    
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$car_id = mysqli_real_escape_string($conn, $_GET['id']);
$is_admin = isset($_GET['admin']) && $_GET['admin'] == 1;

// Obtener información del carro
$sql = "SELECT c.*, u.nombre as nombre_usuario 
        FROM carros c 
        JOIN usuarios u ON c.id_usuario = u.id 
        WHERE c.id = '$car_id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['message'] = "El vehículo no existe.";
    $_SESSION['alert_type'] = "danger";
    
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$car = mysqli_fetch_assoc($result);

// Verificar si el usuario tiene permiso para ver este carro
if ($_SESSION['role'] !== 'admin' && $car['id_usuario'] != $_SESSION['user_id']) {
    $_SESSION['message'] = "No tienes permiso para ver este vehículo.";
    $_SESSION['alert_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// Incluir header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-car"></i> Detalles del Vehículo</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><?php echo htmlspecialchars($car['marca'] . ' ' . $car['modelo']); ?></h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th>Propietario:</th>
                                <td><?php echo htmlspecialchars($car['nombre_usuario']); ?></td>
                            </tr>
                            <tr>
                                <th>Año:</th>
                                <td><?php echo $car['año']; ?></td>
                            </tr>
                            <tr>
                                <th>Kilometraje:</th>
                                <td><?php echo number_format($car['kilometraje'], 0, ',', '.'); ?> km</td>
                            </tr>
                            <tr>
                                <th>Último cambio de aceite:</th>
                                <td><?php echo date('d/m/Y', strtotime($car['fecha_ultimo_cambio'])); ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php if ($car['kilometraje'] >= $car['proximo_cambio']): ?>
                                        <span class="badge bg-danger">Cambio de aceite pendiente</span>
                                    <?php elseif (($car['proximo_cambio'] - $car['kilometraje']) < 1000): ?>
                                        <span class="badge bg-warning text-dark">Próximo al cambio</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Al día</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Cambios realizados:</th>
                                <td><?php echo $car['contador_cambios']; ?></td>
                            </tr>
                            <tr>
                                <th>Próximo cambio a los:</th>
                                <td><?php echo number_format($car['proximo_cambio'], 0, ',', '.'); ?> km</td>
                            </tr>
                            <tr>
                                <th>Kilómetros restantes:</th>
                                <td>
                                    <?php 
                                    $km_restantes = max(0, $car['proximo_cambio'] - $car['kilometraje']);
                                    echo number_format($km_restantes, 0, ',', '.') . ' km';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="edit_car.php?id=<?php echo $car['id']; ?><?php echo $is_admin ? '&admin=1' : ''; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        </div>
                        <div class="col-md-6">
                            <?php if ($car['kilometraje'] >= $car['proximo_cambio']): ?>
                                <button id="registrar-cambio" class="btn btn-success w-100" data-id="<?php echo $car['id']; ?>">
                                    <i class="fas fa-oil-can"></i> Registrar Cambio de Aceite
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success w-100" disabled title="El vehículo aún no necesita cambio de aceite">
                                    <i class="fas fa-oil-can"></i> Registrar Cambio de Aceite
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-history"></i> Historial de Mantenimiento</h4>
                </div>
                <div class="card-body">
                    <?php if ($car['contador_cambios'] > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Cambio #</th>
                                        <th>Kilometraje</th>
                                        <th>Fecha</th>
                                        <th>Próximo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo $car['contador_cambios']; ?></td>
                                        <td><?php echo number_format($car['kilometraje'], 0, ',', '.'); ?> km</td>
                                        <td><?php echo date('d/m/Y', strtotime($car['fecha_ultimo_cambio'])); ?></td>
                                        <td><?php echo number_format($car['proximo_cambio'], 0, ',', '.'); ?> km</td>
                                    </tr>
                                    <?php for ($i = $car['contador_cambios'] - 1; $i > 0; $i--): ?>
                                        <tr>
                                            <td><?php echo $i; ?></td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> El sistema solo registra información detallada del último cambio de aceite.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No se han registrado cambios de aceite para este vehículo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="fas fa-cog"></i> Opciones Administrativas</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button id="forzar-cambio" class="btn btn-warning" data-id="<?php echo $car['id']; ?>">
                                <i class="fas fa-tools"></i> Forzar Registro de Cambio de Aceite
                            </button>
                            <a href="delete_car.php?id=<?php echo $car['id']; ?>" class="btn btn-danger delete-car">
                                <i class="fas fa-trash"></i> Eliminar Vehículo
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="cambioModal" tabindex="-1" aria-labelledby="cambioModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="cambioModalLabel">Confirmar Cambio de Aceite</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas registrar un cambio de aceite para este vehículo?</p>
                <p>Esto actualizará el contador de cambios y establecerá el próximo cambio a los 10,000 km adicionales.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="confirmar-cambio">Confirmar Cambio</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registrarCambioBtn = document.getElementById('registrar-cambio');
    const forzarCambioBtn = document.getElementById('forzar-cambio');
    const confirmarCambioBtn = document.getElementById('confirmar-cambio');
    const cambioModal = new bootstrap.Modal(document.getElementById('cambioModal'));
    
    let carId = null;
    
    if (registrarCambioBtn) {
        registrarCambioBtn.addEventListener('click', function() {
            carId = this.getAttribute('data-id');
            cambioModal.show();
        });
    }
    
    if (forzarCambioBtn) {
        forzarCambioBtn.addEventListener('click', function() {
            carId = this.getAttribute('data-id');
            cambioModal.show();
        });
    }
    
    confirmarCambioBtn.addEventListener('click', function() {
        if (carId) {
            // Registrar cambio de aceite
            $.ajax({
                url: `api/index.php?resource=cars&action=registrar-cambio&id=${carId}`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(data) {
                    if (data.success) {
                        // Ocultar modal
                        cambioModal.hide();
                        
                        // Mostrar mensaje de éxito
                        alert('Cambio de aceite registrado correctamente');
                        
                        // Recargar página para mostrar información actualizada
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de conexión: ' + error);
                }
            });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?> 