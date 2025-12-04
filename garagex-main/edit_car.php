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
$sql = "SELECT * FROM carros WHERE id = '$car_id'";
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

// Verificar si el usuario tiene permiso para editar este carro
if ($_SESSION['role'] !== 'admin' && $car['id_usuario'] != $_SESSION['user_id']) {
    $_SESSION['message'] = "No tienes permiso para editar este vehículo.";
    $_SESSION['alert_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $marca = mysqli_real_escape_string($conn, $_POST['marca']);
    $modelo = mysqli_real_escape_string($conn, $_POST['modelo']);
    $año = mysqli_real_escape_string($conn, $_POST['año']);
    $kilometraje = mysqli_real_escape_string($conn, $_POST['kilometraje']);
    
    // Validar campos
    if (empty($marca) || empty($modelo) || empty($año) || empty($kilometraje)) {
        $_SESSION['message'] = "Por favor, completa todos los campos.";
        $_SESSION['alert_type'] = "danger";
    } else {
        // Si el kilometraje cambia y es mayor a 10,000, actualizar fecha de cambio
        if ($kilometraje != $car['kilometraje']) {
            // Actualizar carro con nueva fecha de cambio
            $sql = "UPDATE carros SET marca = '$marca', modelo = '$modelo', año = '$año', 
                    kilometraje = '$kilometraje', fecha_ultimo_cambio = CURRENT_TIMESTAMP, 
                    notificado = 0 WHERE id = '$car_id'";
        } else {
            // Actualizar carro sin cambiar la fecha
            $sql = "UPDATE carros SET marca = '$marca', modelo = '$modelo', año = '$año', 
                    kilometraje = '$kilometraje' WHERE id = '$car_id'";
        }
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['message'] = "Vehículo actualizado correctamente.";
            $_SESSION['alert_type'] = "success";
            
            // Verificar si es necesario mostrar alerta de mantenimiento
            if ($kilometraje >= 10000) {
                $_SESSION['maintenance_alert'] = "El vehículo $marca $modelo necesita un cambio de aceite.";
            }
            
            if ($is_admin) {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['message'] = "Error al actualizar el vehículo: " . mysqli_error($conn);
            $_SESSION['alert_type'] = "danger";
        }
    }
}

// Incluir header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Editar Vehículo</h3>
                </div>
                <div class="card-body">
                    <form id="edit-car-form" class="needs-validation" novalidate data-id="<?php echo $car_id; ?>" data-admin="<?php echo $is_admin ? '1' : '0'; ?>">
                        <div class="mb-3">
                            <label for="marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="marca" name="marca" value="<?php echo $car['marca']; ?>" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa la marca del vehículo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" value="<?php echo $car['modelo']; ?>" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa el modelo del vehículo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="año" class="form-label">Año</label>
                            <input type="number" class="form-control" id="año" name="año" min="1900" max="<?php echo date("Y") + 1; ?>" value="<?php echo $car['año']; ?>" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa un año válido.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kilometraje" class="form-label">Kilometraje</label>
                            <input type="number" class="form-control" id="kilometraje" name="kilometraje" min="0" value="<?php echo $car['kilometraje']; ?>" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa el kilometraje actual.
                            </div>
                        </div>
                        
                        <div class="alert alert-maintenance mb-3 <?php echo $car['kilometraje'] >= 10000 ? '' : 'd-none'; ?>" id="alerta-mantenimiento">
                            <i class="fas fa-exclamation-triangle"></i> Vehículo con kilometraje superior a 10,000 km. Se recomienda cambio de aceite.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar Vehículo</button>
                            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('edit-car-form');
    const carId = form.getAttribute('data-id');
    const isAdmin = form.getAttribute('data-admin') === '1';
    
    console.log("Formulario de edición cargado para ID:", carId);
    
    // Validación del kilometraje
    const kilometrajeInput = document.getElementById('kilometraje');
    if (kilometrajeInput) {
        kilometrajeInput.addEventListener('input', function() {
            const valor = parseInt(this.value);
            const alertaMantenimiento = document.getElementById('alerta-mantenimiento');
            
            if (!isNaN(valor) && valor >= 10000 && alertaMantenimiento) {
                alertaMantenimiento.classList.remove('d-none');
            } else if (alertaMantenimiento) {
                alertaMantenimiento.classList.add('d-none');
            }
        });
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        // Obtener datos del formulario
        const formData = new FormData(form);
        const carData = {
            id: carId, // Incluir el ID explícitamente
            marca: document.getElementById('marca').value,
            modelo: document.getElementById('modelo').value,
            año: document.getElementById('año').value,
            kilometraje: document.getElementById('kilometraje').value
        };
        
        console.log("Enviando datos para actualizar:", carData);
        
        // Enviar datos a la API
        fetch(`api/index.php?resource=cars&id=${carId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(carData)
        })
        .then(response => {
            console.log("Respuesta del servidor:", response.status);
            return response.json();
        })
        .then(data => {
            console.log("Datos recibidos:", data);
            if (data.success) {
                // Redirigir según el rol
                window.location.href = isAdmin ? 'admin_dashboard.php?success=1' : 'dashboard.php?success=1';
            } else {
                // Mostrar error
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error("Error en la actualización:", error);
            alert('Error de conexión: ' + error);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?> 