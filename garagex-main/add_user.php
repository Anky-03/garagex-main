<?php
session_start();
require_once 'config/database.php';
require_once 'includes/lock_helper.php';

// Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Acceso denegado.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: index.php');
    exit();
}

$USER_CRUD_RESOURCE = 'user_crud';
$LOCK_TTL_SECONDS = 300;

if (!acquire_resource_lock($conn, $USER_CRUD_RESOURCE, intval($_SESSION['user_id']), $LOCK_TTL_SECONDS)) {
    $_SESSION['message'] = 'Otro administrador está modificando usuarios. Intenta más tarde.';
    $_SESSION['alert_type'] = 'warning';
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'usuario';

    if (empty($nombre) || empty($email) || empty($password)) {
        $_SESSION['message'] = 'Completa todos los campos.';
        $_SESSION['alert_type'] = 'danger';
    } else {
        // Verificar duplicado
        $check = "SELECT id FROM usuarios WHERE email = '$email'";
        $r = mysqli_query($conn, $check);
        if ($r && mysqli_num_rows($r) > 0) {
            $_SESSION['message'] = 'Ya existe un usuario con ese correo.';
            $_SESSION['alert_type'] = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nombre, email, password, role) VALUES ('$nombre', '$email', '$hash', '$role')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = 'Usuario creado correctamente.';
                $_SESSION['alert_type'] = 'success';
                header('Location: admin_users.php');
                exit();
            } else {
                $_SESSION['message'] = 'Error: ' . mysqli_error($conn);
                $_SESSION['alert_type'] = 'danger';
            }
        }
    }
}

include 'includes/header.php';
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Agregar Usuario</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input name="password" type="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select">
                                <option value="usuario">Usuario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Crear</button>
                            <a href="admin_users.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const heartbeat = () => {
        fetch('lock_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({action: 'heartbeat', resource: 'user_crud'})
        }).catch(() => {});
    };
    heartbeat();
    setInterval(heartbeat, 60000);
});
</script>
