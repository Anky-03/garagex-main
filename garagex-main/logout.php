<?php
// Iniciar la sesión
session_start();

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

if ($userId !== null) {
    define('SKIP_FEDERATED', true);
    require_once 'config/database.php';
    mysqli_query($conn, "DELETE FROM locks WHERE user_id = $userId");
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, eliminar también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redireccionar a la página de inicio de sesión
header("Location: login.php");
exit(); 