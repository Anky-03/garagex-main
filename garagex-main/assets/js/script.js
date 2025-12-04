// Script personalizado para GarageX

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Confirmación para eliminar carros
    const deleteButtons = document.querySelectorAll('.delete-car');
    if (deleteButtons) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('¿Estás seguro de que deseas eliminar este carro? Esta acción no se puede deshacer.')) {
                    e.preventDefault();
                } else {
                    e.preventDefault();
                    const carId = this.getAttribute('data-id');
                    console.log("Eliminando carro ID:", carId);
                    
                    // Usar Fetch para eliminar el carro (API REST DELETE)
                    fetch(`api/index.php?resource=cars&id=${carId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        // Añadir body vacío para asegurar que la solicitud DELETE sea completa
                        body: JSON.stringify({id: carId})
                    })
                    .then(response => {
                        console.log("Respuesta recibida:", response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log("Datos recibidos:", data);
                        if (data.success) {
                            // Eliminar la fila de la tabla sin recargar la página
                            const row = this.closest('tr');
                            if (row) {
                                row.remove();
                            } else {
                                const card = this.closest('.car-card');
                                if (card) card.remove();
                            }
                            
                            // Mostrar mensaje de éxito
                            showAlert('Vehículo eliminado correctamente', 'success');
                        } else {
                            showAlert('Error al eliminar el vehículo: ' + data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error("Error en la eliminación:", error);
                        showAlert('Error de conexión: ' + error, 'danger');
                    });
                }
            });
        });
    }

    // Validación mejorada de formularios con JS
    const forms = document.querySelectorAll('.needs-validation');
    if (forms) {
        Array.from(forms).forEach(form => {
            // Validar cada campo cuando pierde el foco
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
            });

            // Validar el formulario al enviarlo
            form.addEventListener('submit', event => {
                let isValid = true;
                
                // Validar cada campo
                inputs.forEach(input => {
                    if (!validateField(input)) {
                        isValid = false;
                    }
                });
                
                // Detener el envío si hay errores
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // Si es un formulario para añadir o editar carro, usar fetch
                if (form.id === 'car-form') {
                    event.preventDefault();
                    
                    const formData = new FormData(form);
                    const carData = {};
                    formData.forEach((value, key) => {
                        carData[key] = value;
                    });
                    
                    // Determinar si es una actualización o creación
                    const carId = form.getAttribute('data-id');
                    const method = carId ? 'PUT' : 'POST';
                    const url = carId ? `api/index.php?resource=cars&id=${carId}` : 'api/index.php?resource=cars';
                    
                    console.log("Enviando datos:", method, url, carData);
                    
                    // Usar Fetch para enviar los datos (API REST POST/PUT)
                    fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(carData)
                    })
                    .then(response => {
                        console.log("Respuesta:", response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log("Datos recibidos:", data);
                        if (data.success) {
                            // Redirigir al dashboard
                            window.location.href = 'dashboard.php?success=1';
                        } else {
                            showAlert('Error: ' + data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error("Error en la operación:", error);
                        showAlert('Error de conexión: ' + error, 'danger');
                    });
                }
            }, false);
        });
    }

    // Actualizar automáticamente el kilometraje si hay un campo de kilometraje
    const kilometrajeInput = document.getElementById('kilometraje');
    if (kilometrajeInput) {
        kilometrajeInput.addEventListener('input', function() {
            const valor = parseInt(this.value);
            const alertaMantenimiento = document.getElementById('alerta-mantenimiento');
            
            if (valor >= 10000 && alertaMantenimiento) {
                alertaMantenimiento.classList.remove('d-none');
            } else if (alertaMantenimiento) {
                alertaMantenimiento.classList.add('d-none');
            }
        });
    }
    
    // Inicializar combos de búsqueda
    initSearchCombos();
    
    // Inicializar tablas dinámicas
    initDynamicTables();
});

// Función para validar un campo
function validateField(field) {
    // Limpiar errores previos
    const feedbackElement = field.nextElementSibling;
    if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
        feedbackElement.textContent = '';
    }
    
    // Validar según el tipo de campo
    if (field.hasAttribute('required') && !field.value.trim()) {
        setFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    if (field.type === 'email' && field.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(field.value)) {
            setFieldError(field, 'Por favor, ingresa un correo electrónico válido');
            return false;
        }
    }
    
    if (field.type === 'password' && field.id === 'password') {
        if (field.value.length < 6) {
            setFieldError(field, 'La contraseña debe tener al menos 6 caracteres');
            return false;
        }
    }
    
    if (field.id === 'confirmPassword') {
        const password = document.getElementById('password');
        if (password && field.value !== password.value) {
            setFieldError(field, 'Las contraseñas no coinciden');
            return false;
        }
    }
    
    if (field.type === 'number') {
        const min = parseInt(field.getAttribute('min'));
        const max = parseInt(field.getAttribute('max'));
        const value = parseInt(field.value);
        
        if (!isNaN(min) && value < min) {
            setFieldError(field, `El valor mínimo es ${min}`);
            return false;
        }
        
        if (!isNaN(max) && value > max) {
            setFieldError(field, `El valor máximo es ${max}`);
            return false;
        }
    }
    
    // Marcar el campo como válido
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    return true;
}

// Función para mostrar error en un campo
function setFieldError(field, message) {
    field.classList.remove('is-valid');
    field.classList.add('is-invalid');
    
    const feedbackElement = field.nextElementSibling;
    if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
        feedbackElement.textContent = message;
    }
}

// Función para mostrar alertas
function showAlert(message, type) {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show`;
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertContainer, container.firstChild);
        
        // Eliminar la alerta después de 5 segundos
        setTimeout(() => {
            alertContainer.remove();
        }, 5000);
    }
}

// Inicializar combos de búsqueda
function initSearchCombos() {
    // Combo de búsqueda de usuarios (llave primaria)
    const userSearchCombo = document.getElementById('user-search');
    if (userSearchCombo) {
        fetch('api/index.php?resource=users', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Llenar el combo con los usuarios
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.nombre;
                    userSearchCombo.appendChild(option);
                });
                
                // Inicializar select2 para búsqueda mejorada
                $(userSearchCombo).select2({
                    placeholder: 'Selecciona un usuario',
                    allowClear: true
                });
                
                // Evento de cambio
                $(userSearchCombo).on('change', function() {
                    const userId = this.value;
                    if (userId) {
                        // Cargar los carros del usuario seleccionado
                        loadUserCars(userId);
                    }
                });
            }
        });
    }
    
    // Combo de búsqueda de marcas (llave foránea)
    const marcaSearchCombo = document.getElementById('marca-search');
    if (marcaSearchCombo) {
        // Obtener marcas únicas desde la API
        fetch('api/index.php?resource=cars&action=marcas', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Llenar el combo con las marcas
                data.marcas.forEach(marca => {
                    const option = document.createElement('option');
                    option.value = marca;
                    option.textContent = marca;
                    marcaSearchCombo.appendChild(option);
                });
                
                // Inicializar select2
                $(marcaSearchCombo).select2({
                    placeholder: 'Filtrar por marca',
                    allowClear: true
                });
                
                // Evento de cambio
                $(marcaSearchCombo).on('change', function() {
                    const marca = this.value;
                    filterCarsByMarca(marca);
                });
            }
        });
    }
}

// Cargar los carros de un usuario
function loadUserCars(userId) {
    fetch(`api/index.php?resource=users&id=${userId}&action=cars`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCarsTable(data.cars);
        } else {
            showAlert('Error al cargar los vehículos: ' + data.message, 'danger');
        }
    });
}

// Filtrar carros por marca
function filterCarsByMarca(marca) {
    const table = document.getElementById('cars-table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const marcaCell = row.querySelector('td:nth-child(2)');
        if (marca === '' || !marca) {
            row.style.display = '';
        } else if (marcaCell && marcaCell.textContent.trim() === marca) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Actualizar la tabla de carros
function updateCarsTable(cars) {
    const table = document.getElementById('cars-table');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    tbody.innerHTML = '';
    
    if (cars.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="6" class="text-center">No hay vehículos para mostrar</td>';
        tbody.appendChild(row);
        return;
    }
    
    cars.forEach(car => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${car.id}</td>
            <td>${car.marca}</td>
            <td>${car.modelo}</td>
            <td>${car.año}</td>
            <td>${car.kilometraje}</td>
            <td>
                <a href="view_car.php?id=${car.id}" class="btn btn-sm btn-info">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="edit_car.php?id=${car.id}" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i>
                </a>
                <button class="btn btn-sm btn-danger delete-car" data-id="${car.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Reinicializar los botones de eliminar
    const deleteButtons = tbody.querySelectorAll('.delete-car');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que deseas eliminar este carro? Esta acción no se puede deshacer.')) {
                e.preventDefault();
            } else {
                e.preventDefault();
                const carId = this.getAttribute('data-id');
                console.log("Eliminando carro ID:", carId);
                
                // Usar Fetch para eliminar el carro
                fetch(`api/index.php?resource=cars&id=${carId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    // Añadir body vacío para asegurar que la solicitud DELETE sea completa
                    body: JSON.stringify({id: carId})
                })
                .then(response => {
                    console.log("Respuesta recibida:", response.status);
                    return response.json();
                })
                .then(data => {
                    console.log("Datos recibidos:", data);
                    if (data.success) {
                        this.closest('tr').remove();
                        showAlert('Vehículo eliminado correctamente', 'success');
                    } else {
                        showAlert('Error al eliminar el vehículo: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error("Error en la eliminación:", error);
                    showAlert('Error de conexión: ' + error, 'danger');
                });
            }
        });
    });
}

// Inicializar tablas dinámicas
function initDynamicTables() {
    const carsTable = document.getElementById('cars-table');
    if (carsTable) {
        // Inicializar DataTables con retrieve:true para manejar reinicializaciones
        // Usar una configuración de idioma local para evitar problemas CORS
        $(carsTable).DataTable({
            retrieve: true,
            "language": {
                "decimal": "",
                "emptyTable": "No hay datos disponibles en la tabla",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "infoPostFix": "",
                "thousands": ",",
                "lengthMenu": "Mostrar _MENU_ registros",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "search": "Buscar:",
                "zeroRecords": "No se encontraron registros coincidentes",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                },
                "aria": {
                    "sortAscending": ": activar para ordenar la columna ascendente",
                    "sortDescending": ": activar para ordenar la columna descendente"
                }
            },
            "responsive": true,
            "order": [[0, "desc"]]
        });
    }
} 