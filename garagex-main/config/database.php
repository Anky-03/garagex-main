<?php
// Configuración de la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'garagex_db');

// Conexión a la base de datos
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, '', 3307);

// Verificar conexión
if (!$conn) {
    die("ERROR: No se pudo conectar. " . mysqli_connect_error());
}

if (!defined('SKIP_FEDERATED')) {
    define('SKIP_FEDERATED', false);
}

// Configuración de la base federada
$FED_SERVER = '192.168.137.2';
$FED_PORT   = 3307;
$FED_USER   = 'iram';
$FED_PASS   = 'iram123';
$FED_DB     = 'garagex_fed';

if (!SKIP_FEDERATED) {
    $conn_fed = mysqli_connect($FED_SERVER, $FED_USER, $FED_PASS, $FED_DB, $FED_PORT);

    if (!$conn_fed) {
        die("ERROR BD federada (garagex_fed): " . mysqli_connect_error());
    }
} else {
    $conn_fed = null;
}

// Crear base de datos si no existe
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    // Seleccionar la base de datos
    mysqli_select_db($conn, DB_NAME);
    
    // Crear tabla de usuarios si no existe
    $sql_users = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!mysqli_query($conn, $sql_users)) {
        echo "ERROR: No se pudo ejecutar $sql_users. " . mysqli_error($conn);
    }
    
    // Crear tabla de locks si no existe
    $sql_locks = "CREATE TABLE IF NOT EXISTS locks (
        resource VARCHAR(64) NOT NULL PRIMARY KEY,
        user_id INT(11) NOT NULL,
        locked_at DATETIME NOT NULL,
        CONSTRAINT fk_locks_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )";

    if (!mysqli_query($conn, $sql_locks)) {
        echo "ERROR: No se pudo ejecutar $sql_locks. " . mysqli_error($conn);
    }

    // Crear tabla de carros si no existe
    $sql_cars = "CREATE TABLE IF NOT EXISTS carros (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT(11) NOT NULL,
        marca VARCHAR(50) NOT NULL,
        modelo VARCHAR(50) NOT NULL,
        año INT(4) NOT NULL,
        kilometraje INT(11) NOT NULL,
        fecha_ultimo_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notificado BOOLEAN DEFAULT 0,
        proximo_cambio INT(11) DEFAULT 0,
        contador_cambios INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    
    if (!mysqli_query($conn, $sql_cars)) {
        echo "ERROR: No se pudo ejecutar $sql_cars. " . mysqli_error($conn);
    }
    
    // Verificar si los campos proximo_cambio y contador_cambios existen en la tabla carros
    $check_fields = "SHOW COLUMNS FROM carros LIKE 'proximo_cambio'";
    $result = mysqli_query($conn, $check_fields);
    
    if (mysqli_num_rows($result) == 0) {
        // Agregar campos si no existen
        $add_fields = "ALTER TABLE carros 
                      ADD COLUMN proximo_cambio INT(11) DEFAULT 0,
                      ADD COLUMN contador_cambios INT(11) DEFAULT 0";
        
        if (!mysqli_query($conn, $add_fields)) {
            echo "ERROR: No se pudieron agregar campos a la tabla carros. " . mysqli_error($conn);
        }
        
        // Actualizar valores existentes con próximo cambio a 10,000 km más del kilometraje actual
        $update_existing = "UPDATE carros SET proximo_cambio = kilometraje + 10000 WHERE proximo_cambio = 0";
        mysqli_query($conn, $update_existing);
    }
    
    // Verificar si el campo created_at existe en la tabla carros
    $check_created_at = "SHOW COLUMNS FROM carros LIKE 'created_at'";
    $result = mysqli_query($conn, $check_created_at);
    
    if (mysqli_num_rows($result) == 0) {
        // Agregar campo created_at si no existe
        $add_created_at = "ALTER TABLE carros 
                          ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        
        if (!mysqli_query($conn, $add_created_at)) {
            echo "ERROR: No se pudo agregar el campo created_at a la tabla carros. " . mysqli_error($conn);
        }
    }

    // Verificar si existe usuario admin, si no, crearlo
    $check_admin = "SELECT * FROM usuarios WHERE role='admin' LIMIT 1";
    $result = mysqli_query($conn, $check_admin);
    
    if (mysqli_num_rows($result) == 0) {
        // Crear usuario admin por defecto
        $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
        $create_admin = "INSERT INTO usuarios (nombre, email, password, role) VALUES ('Administrador', 'admin@garagex.com', '$admin_password', 'admin')";
        
        if (!mysqli_query($conn, $create_admin)) {
            echo "ERROR: No se pudo crear el usuario administrador. " . mysqli_error($conn);
        }
    }
} else {
    echo "ERROR: No se pudo crear la base de datos. " . mysqli_error($conn);
}
?> 