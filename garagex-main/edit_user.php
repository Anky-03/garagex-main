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

if (!isset($_GET['id'])) {
    header('Location: admin_users.php');
    exit();
}

$id = intval($_GET['id']);

// Cargar usuario
$sql = "SELECT id, nombre, email, role FROM usuarios WHERE id = $id";
$res = mysqli_query($conn, $sql);
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['message'] = 'Usuario no encontrado.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: admin_users.php');
    exit();
}
$user = mysqli_fetch_assoc($res);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'usuario';
    $password = $_POST['password'];

    if (empty($nombre) || empty($email)) {
        $_SESSION['message'] = 'Nombre y email son obligatorios.';
        $_SESSION['alert_type'] = 'danger';
    } else {
        // Verificar email único
        $check = "SELECT id FROM usuarios WHERE email = '$email' AND id != $id";
        $r = mysqli_query($conn, $check);
        if ($r && mysqli_num_rows($r) > 0) {
            $_SESSION['message'] = 'Otro usuario ya utiliza ese correo.';
            $_SESSION['alert_type'] = 'danger';
        } else {
            $update = "UPDATE usuarios SET nombre = '$nombre', email = '$email', role = '$role'";
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update .= ", password = '$hash'";
            }
            $update .= " WHERE id = $id";
            if (mysqli_query($conn, $update)) {
                $_SESSION['message'] = 'Usuario actualizado.';
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
                    <h3>Editar Usuario</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input name="nombre" class="form-control" value="<?= htmlspecialchars($user['nombre']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña (dejar en blanco para mantener)</label>
                            <input name="password" type="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select">
                                <option value="usuario" <?= $user['role'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Guardar</button>
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
