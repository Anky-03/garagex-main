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

if (isset($_GET['release_lock'])) {
    release_resource_lock($conn, $USER_CRUD_RESOURCE, intval($_SESSION['user_id']));
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin_dashboard.php';
    header('Location: ' . $redirect);
    exit();
}

if (!acquire_resource_lock($conn, $USER_CRUD_RESOURCE, intval($_SESSION['user_id']), $LOCK_TTL_SECONDS)) {
    $holder = get_lock_holder($conn, $USER_CRUD_RESOURCE);
    $who = $holder && !empty($holder['nombre']) ? $holder['nombre'] : 'otro administrador';
    $_SESSION['message'] = 'La sección de usuarios está siendo usada por ' . $who . '. Intenta más tarde.';
    $_SESSION['alert_type'] = 'warning';
    header('Location: admin_dashboard.php');
    exit();
}

// Mensajes y acciones (crear/editar/eliminar) se manejan en páginas dedicadas
// Aquí listamos los usuarios y mostramos acciones
$sql = "SELECT id, nombre, email, role, created_at FROM usuarios ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

include 'includes/header.php';
?>
<div class="container py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['alert_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['alert_type']); ?>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-8">
            <h2><i class="fas fa-users"></i> Gestión de Usuarios</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="add_user.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Agregar Usuario</a>
            <a href="admin_users.php?release_lock=1&redirect=admin_dashboard.php" class="btn btn-secondary">Liberar y volver</a>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Bloqueo activo:</strong> mientras esta página esté abierta, ningún otro administrador podrá modificar usuarios.
    </div>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo $user['role']; ?></td>
                        <td><?php echo $user['created_at']; ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Eliminar usuario?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No hay usuarios registrados.</div>
    <?php endif; ?>
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
