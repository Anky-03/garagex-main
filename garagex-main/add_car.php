<?php
session_start();
require_once 'config/database.php';
require_once 'includes/lock_helper.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$ADMIN_CAR_RESOURCE = 'admin_car_create';
$LOCK_TTL_SECONDS = 300;

if ($isAdmin && isset($_GET['release_lock'])) {
    release_resource_lock($conn, $ADMIN_CAR_RESOURCE, intval($_SESSION['user_id']));
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin_dashboard.php';
    header('Location: ' . $redirect);
    exit();
}

if ($isAdmin && !acquire_resource_lock($conn, $ADMIN_CAR_RESOURCE, intval($_SESSION['user_id']), $LOCK_TTL_SECONDS)) {
    $holder = get_lock_holder($conn, $ADMIN_CAR_RESOURCE);
    $who = $holder && !empty($holder['nombre']) ? $holder['nombre'] : 'otro administrador';
    $_SESSION['message'] = 'El formulario de alta de vehículos está ocupado por ' . $who . '. Intenta más tarde.';
    $_SESSION['alert_type'] = 'warning';
    header('Location: admin_dashboard.php');
    exit();
}

// Redireccionar todas las solicitudes POST al procesamiento por API
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // No hacer nada aquí, dejamos que la API maneje la inserción
    // El JavaScript se encargará de enviar los datos a la API
    $_SESSION['message'] = "Por favor, espera mientras procesamos tu solicitud.";
    $_SESSION['alert_type'] = "info";
    
    // Redirigir al dashboard (la inserción se hará vía JavaScript/API)
    header("Location: dashboard.php");
    exit();
}

// Incluir header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-plus-circle"></i> Agregar Nuevo Vehículo</h3>
                </div>
                <div class="card-body">
                    <form id="car-form" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="marca" name="marca" required 
                                   placeholder="Ej: Toyota, Nissan, Ford...">
                            <div class="invalid-feedback">
                                Por favor, ingresa la marca del vehículo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" required
                                   placeholder="Ej: Corolla, Sentra, Focus...">
                            <div class="invalid-feedback">
                                Por favor, ingresa el modelo del vehículo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="año" class="form-label">Año</label>
                            <input type="number" class="form-control" id="año" name="año" 
                                   min="1900" max="<?php echo date("Y") + 1; ?>" required
                                   placeholder="Ej: 2020">
                            <div class="invalid-feedback" id="año-feedback">
                                Por favor, ingresa un año válido.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kilometraje" class="form-label">Kilometraje</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="kilometraje" name="kilometraje" 
                                       min="0" required placeholder="Ej: 15000">
                                <span class="input-group-text">km</span>
                            </div>
                            <div class="invalid-feedback" id="kilometraje-feedback">
                                Por favor, ingresa el kilometraje actual.
                            </div>
                        </div>
                        
                        <div class="alert alert-maintenance mb-3 d-none" id="alerta-mantenimiento">
                            <i class="fas fa-exclamation-triangle"></i> Vehículo con kilometraje superior a 10,000 km. Se recomienda cambio de aceite.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Vehículo
                            </button>
                            <a href="<?php echo $isAdmin ? 'add_car.php?release_lock=1&redirect=admin_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('car-form');
    const hasAdminLock = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    const lockResource = 'admin_car_create';
    const redirectAfterSave = '<?php echo $isAdmin ? 'admin_dashboard.php' : 'dashboard.php'; ?>';

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        // Obtener datos del formulario de manera segura sin clonar el objeto DOM
        const carData = {
            marca: document.getElementById('marca').value,
            modelo: document.getElementById('modelo').value,
            año: document.getElementById('año').value,
            kilometraje: document.getElementById('kilometraje').value
        };
        
        // Deshabilitar el botón de envío para prevenir múltiples envíos
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        
        // Enviar datos a la API
        fetch('api/index.php?resource=cars', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(carData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const goNext = () => {
                    window.location.href = redirectAfterSave + '?success=1';
                };
                if (hasAdminLock) {
                    fetch('lock_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({action: 'release', resource: lockResource})
                    }).finally(goNext);
                } else {
                    goNext();
                }
            } else {
                // Mostrar error
                alert('Error: ' + data.message);
                // Rehabilitar el botón
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-save"></i> Guardar Vehículo';
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error);
            // Rehabilitar el botón
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-save"></i> Guardar Vehículo';
        });
    });
    
    // Validación del año
    const añoInput = document.getElementById('año');
    añoInput.addEventListener('blur', function() {
        const valor = parseInt(this.value);
        const min = parseInt(this.getAttribute('min'));
        const max = parseInt(this.getAttribute('max'));
        const feedback = document.getElementById('año-feedback');
        
        if (isNaN(valor) || valor < min || valor > max) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            
            if (valor < min) {
                feedback.textContent = `El año no puede ser menor que ${min}`;
            } else if (valor > max) {
                feedback.textContent = `El año no puede ser mayor que ${max}`;
            } else {
                feedback.textContent = 'Por favor, ingresa un año válido';
            }
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
    
    // Validación del kilometraje
    const kilometrajeInput = document.getElementById('kilometraje');
    kilometrajeInput.addEventListener('blur', function() {
        const valor = parseInt(this.value);
        const min = parseInt(this.getAttribute('min'));
        const feedback = document.getElementById('kilometraje-feedback');
        
        if (isNaN(valor) || valor < min) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            
            if (valor < min) {
                feedback.textContent = `El kilometraje no puede ser negativo`;
            } else {
                feedback.textContent = 'Por favor, ingresa un kilometraje válido';
            }
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
    
    // Mostrar alerta de mantenimiento
    kilometrajeInput.addEventListener('input', function() {
        const valor = parseInt(this.value);
        const alertaMantenimiento = document.getElementById('alerta-mantenimiento');
        
        if (!isNaN(valor) && valor >= 10000 && alertaMantenimiento) {
            alertaMantenimiento.classList.remove('d-none');
        } else if (alertaMantenimiento) {
            alertaMantenimiento.classList.add('d-none');
        }
    });

    if (hasAdminLock) {
        const heartbeat = () => {
            fetch('lock_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({action: 'heartbeat', resource: lockResource})
            }).catch(() => {});
        };
        heartbeat();
        setInterval(heartbeat, 60000);
    }
});
</script>

<?php include 'includes/footer.php'; ?> 