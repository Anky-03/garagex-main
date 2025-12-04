# GarageX - Sistema de Gestión de Carros

GarageX es un sistema de gestión de vehículos que permite a los usuarios registrar y monitorear el estado de sus carros, con enfoque en el mantenimiento preventivo y alertas de cambio de aceite.

## Características

- Registro e inicio de sesión de usuarios
- Panel de usuario para gestionar vehículos personales
- Panel de administrador para gestionar todos los vehículos
- Alertas automáticas cuando un vehículo supera los 10,000 km para cambio de aceite
- Gestión completa de vehículos (agregar, editar, ver detalles, eliminar)
- Interfaz responsiva y moderna

## Requisitos de instalación

- Servidor web (Apache, Nginx)
- PHP 7.0 o superior
- MySQL 5.7 o superior
- XAMPP (recomendado para entorno de desarrollo)

## Instalación

1. Clone o descargue este repositorio en su directorio web (htdocs si usa XAMPP)
2. Asegúrese de que su servidor MySQL esté funcionando
3. Acceda a la aplicación a través de su navegador: http://localhost/garagex
4. La base de datos y las tablas se crearán automáticamente en el primer acceso

## Credenciales por defecto

```
Usuario administrador:
Email: admin@garagex.com
Contraseña: admin123
```

## Uso del sistema

### Como usuario regular:
1. Regístrese o inicie sesión
2. Agregue sus vehículos desde el panel de usuario
3. Actualice el kilometraje de sus vehículos cuando sea necesario
4. Reciba alertas cuando sea tiempo de cambiar el aceite

### Como administrador:
1. Inicie sesión con las credenciales de administrador
2. Acceda al panel de administración
3. Visualice todos los vehículos registrados
4. Gestione vehículos de cualquier usuario
5. Vea estadísticas del sistema

## Estructura de archivos

```
garagex/
├── config/
│   └── database.php
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── img/
│       └── logok.png
├── includes/
│   ├── header.php
│   └── footer.php
├── index.php
├── login.php
├── register.php
├── logout.php
├── dashboard.php
├── admin_dashboard.php
├── add_car.php
├── edit_car.php
├── view_car.php
├── delete_car.php
└── README.md
```

## Seguridad

- Las contraseñas se almacenan con hash seguro
- Protección contra inyección SQL
- Validación de formularios en cliente y servidor
- Control de acceso basado en roles

## Autor

GarageX - Sistema de Gestión de Carros 