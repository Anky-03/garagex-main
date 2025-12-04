<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario ya está logueado
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// Procesar formulario de registro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validar campos
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['message'] = "Por favor, completa todos los campos.";
        $_SESSION['alert_type'] = "danger";
    } elseif ($password != $confirm_password) {
        $_SESSION['message'] = "Las contraseñas no coinciden.";
        $_SESSION['alert_type'] = "danger";
    } elseif (strlen($password) < 6) {
        $_SESSION['message'] = "La contraseña debe tener al menos 6 caracteres.";
        $_SESSION['alert_type'] = "danger";
    } else {
        // Verificar si el correo ya está registrado
        $check_email = "SELECT * FROM usuarios WHERE email = '$email'";
        $result = mysqli_query($conn, $check_email);
        
        if (mysqli_num_rows($result) > 0) {
            $_SESSION['message'] = "Este correo electrónico ya está registrado. Por favor, utiliza otro.";
            $_SESSION['alert_type'] = "danger";
        } else {
            // Encriptar contraseña
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar nuevo usuario
            $sql = "INSERT INTO usuarios (nombre, email, password, role) VALUES ('$nombre', '$email', '$hashed_password', 'usuario')";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Registro exitoso. Ahora puedes iniciar sesión.";
                $_SESSION['alert_type'] = "success";
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['message'] = "Error al registrar: " . mysqli_error($conn);
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - GarageX</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-container bg-white">
                    <div class="text-center mb-4">
                        <img src="assets/img/logok.png" alt="GarageX Logo" height="80">
                        <h2 class="mt-3">Registro en GarageX</h2>
                        <p class="text-muted">Crea tu cuenta para gestionar tus carros</p>
                    </div>
                    
                    <?php
                    // Mostrar mensajes de error
                    if (isset($_SESSION['message'])) {
                        echo '<div class="alert alert-' . $_SESSION['alert_type'] . ' alert-dismissible fade show" role="alert">
                                ' . $_SESSION['message'] . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        unset($_SESSION['message']);
                        unset($_SESSION['alert_type']);
                    }
                    ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa tu nombre.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">
                                Por favor, ingresa un correo electrónico válido.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                            <div class="invalid-feedback">
                                La contraseña debe tener al menos 6 caracteres.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">
                                Las contraseñas deben coincidir.
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Registrarse</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Script personalizado -->
    <script src="assets/js/script.js"></script>
</body>
</html> 