# Contexto funcional de GarageX

## Resumen ejecutivo
GarageX es una aplicación web LAMP (Linux/Apache/MySQL/PHP) orientada a pequeños talleres o propietarios particulares que necesitan llevar control preventivo de mantenimiento de vehículos. La solución ofrece:
- Gestión completa de usuarios con roles `admin` y `usuario`.
- Registro, edición, visualización y eliminación de vehículos asociados a cada cuenta.
- Alertas automáticas para cambios de aceite basadas en kilometraje (`>= 10,000 km` o cuando se supera `proximo_cambio`).
- Panel administrativo con métricas en tiempo real y reportes vía API.
- Interfaz moderna basada en Bootstrap 5, DataTables y Select2.

## Stack y dependencias
- **Backend:** PHP 7+ con sesiones nativas (`$_SESSION`) y MySQL 5.7+ (configurado en `config/database.php`, puerto `3307`).
- **Frontend:** Bootstrap 5.3, Font Awesome 6, jQuery 3.6, DataTables 1.13 y Select2 4.1 (cargados en `includes/header.php` y `includes/footer.php`).
- **Estilos personalizados:** `assets/css/style.css` define layouts responsivos, tarjetas de métricas y alertas.
- **Lógica de interfaz:** `assets/js/script.js` centraliza validaciones de formularios, consumo de APIs via `fetch`, filtros dinámicos y control de modales.

## Modelo de datos
La base `garagex_db` se auto aprovisiona al incluir `config/database.php`:
- **Tabla `usuarios`:** `id`, `nombre`, `email` (único), `password` hash, `role` (`admin`/`usuario`), `created_at`.
- **Tabla `carros`:** `id`, `id_usuario` (FK con borrado en cascada), `marca`, `modelo`, `año`, `kilometraje`, `fecha_ultimo_cambio`, `proximo_cambio`, `contador_cambios`, `notificado`, `created_at`.
- Script crea usuario administrador por defecto (`admin@garagex.com` / `admin123`).

## Roles y experiencia de usuario
- **Visitante:** Accede a `login.php` y `register.php`. Se bloquea cualquier recurso sin sesión activa (middleware sencillo en cada archivo).
- **Usuario registrado:** Ingresa a `dashboard.php` para ver sus vehículos, crear nuevos (`add_car.php`), editar (`edit_car.php`), consultar detalles (`view_car.php`) y recibir alertas.
- **Administrador:** Redirigido a `admin_dashboard.php`, donde visualiza todos los vehículos, aplica filtros por usuario/marca, elimina registros (`delete_car.php`) y accede a reportes avanzados. Puede gestionar autos de cualquier usuario y forzar registros de mantenimiento.

## Flujo funcional principal
1. **Autenticación:** `login.php` valida credenciales contra `usuarios`. Contrasenyas se verifican con `password_verify`. Sesiones guardan `user_id`, `user_name`, `email`, `role`.
2. **Registro:** `register.php` crea usuarios estándar (`role = 'usuario'`) tras validar duplicados y longitud mínima de contraseña.
3. **Dashboard de usuario:** `dashboard.php` carga sus autos (`SELECT * FROM carros WHERE id_usuario = :current`). Determina estado por `kilometraje` vs `proximo_cambio`, genera alertas de mantenimiento y provee acciones (ver, editar, registrar cambios, eliminar vía API).
4. **Panel admin:** `admin_dashboard.php` muestra métricas (usuarios, carros, mantenimiento pendiente), integra DataTables y Select2 para filtros, y se apoya en endpoints de reportes para tarjetas dinámicas.
5. **CRUD de carros:** Formularios HTML disparan peticiones asincrónicas a `api/index.php?resource=cars` usando `fetch` (POST para crear, PUT para actualizar, DELETE para eliminar). No se envían formularios tradicionales: la capa PHP solo renderiza y protege vistas.

## Frontend
- **Layout global:** `includes/header.php` y `includes/footer.php` encapsulan navbar, recursos estáticos y mensajes `$_SESSION`. Las vistas los incluyen para consistencia visual.
- **Validaciones:** `assets/js/script.js` agrega validación en blur, muestra feedback custom, previene envíos múltiples y controla botones (spinners, disable states).
- **Interacciones clave:**
  - Confirmaciones de borrado con `fetch DELETE` y eliminación de filas sin recargar la página.
  - Selectores de búsqueda (usuarios, marcas) poblados desde las APIs y enriquecidos con Select2.
  - DataTables configurada en modo responsive con textos en español para listados extensos.
  - Modales Bootstrap en `view_car.php` para confirmar registro de cambios de aceite.

## Backend y APIs REST
`api/index.php` enruta peticiones según `resource`. Cada recurso valida método HTTP (`$method`), aplica `sanitize_input`, comprueba roles y devuelve JSON estructurado con `success`, `message` y datos.

| Recurso | Método | Ruta (query) | Descripción |
| --- | --- | --- | --- |
| `auth` | `POST` | `api/index.php?resource=auth` | Inicia sesión via API, rellena `$_SESSION` y devuelve info del usuario.
| `cars` | `GET` | `...?resource=cars` | Lista vehículos (limitados al propietario salvo admin). Soporta `...?resource=cars&id={carId}` para detalle.
| `cars` | `GET` | `...?resource=cars&action=marcas` | Devuelve marcas únicas para filtros.
| `cars` | `GET` | `...?resource=cars&action=cambios-aceite&{userId}` | (Sólo admin) Estadísticas de mantenimiento por usuario.
| `cars` | `POST` | `...?resource=cars` | Crea vehículo (anti-duplicado 30s). Inicializa `proximo_cambio = kilometraje + 10000`.
| `cars` | `POST` | `...?resource=cars&action=registrar-cambio&id={carId}` | Registra cambio de aceite, incrementa `contador_cambios`, reinicia `notificado` y recalcula `proximo_cambio`.
| `cars` | `PUT` | `...?resource=cars&id={carId}` | Actualiza datos del vehículo; si cambia kilometraje se actualiza `fecha_ultimo_cambio` y se limpia `notificado`.
| `cars` | `DELETE` | `...?resource=cars&id={carId}` | Elimina vehículo (admin o dueño). Maneja fallback si `id` llega en cuerpo JSON.
| `users` | `GET` | `...?resource=users` | (Admin) Lista usuarios. `...?resource=users&id={userId}` devuelve detalle; `...?resource=users&id={userId}&action=cars` lista sus autos.
| `users` | `POST` | `...?resource=users` | (Admin) Crea usuario; valida duplicados.
| `users` | `PUT/DELETE` | `...?resource=users&id={userId}` | Actualiza datos/roles o elimina usuarios (prohibido eliminar último admin).
| `reports` | `GET` | `...?resource=reports&action=usuarios|carros|mantenimiento` | Devuelve estadísticas para las tarjetas de administración (totales, promedios, listados críticos).

## Lógica de mantenimiento preventivo
- Un vehículo requiere alerta si `kilometraje >= proximo_cambio` o si nunca se registró cambio y ya supera `10,000 km`.
- `dashboard.php` marca estados con `badge` (`bg-danger`, `bg-warning`, `bg-success`).
- Al detonar una alerta, se registra mensaje en `$_SESSION` y se marca `notificado = 1` para evitar spam; se reinicia tras registrar el cambio.
- Registrar cambio vía API incrementa `contador_cambios`, actualiza `fecha_ultimo_cambio` y recalcula `proximo_cambio = kilometraje + 10000`.

## Seguridad y validaciones
- Todas las páginas inician sesión y redirigen si `user_id` no está presente; los paneles verifican rol explícitamente.
- Inputs limpiados con `mysqli_real_escape_string`/`sanitize_input` y parámetros validados en servidor y cliente.
- Contraseñas almacenadas con `password_hash` y verificadas con `password_verify`.
- Las APIs validan método HTTP (`405`), autenticación y permisos (`403`), existencia de registros (`404`) y conflictos (`409`).

## Configuración e instalación
1. Clonar/copiar el proyecto en el directorio del servidor web (e.g., `htdocs/garagex-main/`).
2. Asegurar que Apache + MySQL/ MariaDB estén activos (puerto MySQL `3307` por defecto).
3. Acceder a `http://localhost/garagex-main/`. El include `config/database.php` crea la base, tablas y usuario admin si no existen.
4. Iniciar sesión con `admin@garagex.com` / `admin123` o registrarse como usuario regular.

## Observaciones y oportunidades
- Los formularios de creación/edición delegan 100% en la API. Si el fetch falla no existe fallback HTML; podría añadirse.
- `view_car.php` sólo muestra el último cambio con detalle; el historial anterior está simulado. Considerar tabla dedicada para mantenimientos.
- `config/database.php` usa credenciales sin variables de entorno; recomendable parametrizar.
- Falta CSRF tokenización en formularios tradicionales, aunque la mayoría de operaciones son AJAX autenticadas.
