

// Funciones para punto de venta
class PuntoVenta {
    constructor() {
        this.carrito = [];
        this.total = 0;
        this.subtotal = 0;
        this.clienteId = null;
        this.tipoPago = 'contado';
    }
    
    agregarProducto(producto) {
        const index = this.carrito.findIndex(item => item.id === producto.id);
        
        if (index > -1) {
            this.carrito[index].cantidad += producto.cantidad;
            this.carrito[index].subtotal = this.carrito[index].cantidad * this.carrito[index].precio;
        } else {
            this.carrito.push({
                ...producto,
                subtotal: producto.cantidad * producto.precio
            });
        }
        
        this.calcularTotal();
        this.actualizarVista();
    }
    
    eliminarProducto(index) {
        this.carrito.splice(index, 1);
        this.calcularTotal();
        this.actualizarVista();
    }
    
    actualizarCantidad(index, cantidad) {
        if (cantidad > 0) {
            this.carrito[index].cantidad = cantidad;
            this.carrito[index].subtotal = cantidad * this.carrito[index].precio;
            this.calcularTotal();
            this.actualizarVista();
        }
    }
    
    calcularTotal() {
        this.subtotal = this.carrito.reduce((sum, item) => sum + item.subtotal, 0);
        this.total = this.subtotal; // Sin impuestos por ahora
    }
    
    actualizarVista() {
        // Actualizar tabla del carrito
        const tbody = document.getElementById('carrito-body');
        tbody.innerHTML = '';
        
        this.carrito.forEach((item, index) => {
            const row = `
                <tr>
                    <td>${item.codigo}</td>
                    <td>${item.nombre}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm cantidad-input" 
                               value="${item.cantidad}" min="1" 
                               data-index="${index}" style="width: 80px;">
                    </td>
                    <td>${formatCurrency(item.precio)}</td>
                    <td>${formatCurrency(item.subtotal)}</td>
                    <td>
                        <button class="btn btn-danger btn-sm eliminar-item" 
                                data-index="${index}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
        
        // Actualizar totales
        document.getElementById('subtotal').textContent = formatCurrency(this.subtotal);
        document.getElementById('total').textContent = formatCurrency(this.total);
        
        // Agregar eventos
        this.agregarEventos();
    }
    
    agregarEventos() {
        // Eventos para cambiar cantidad
        document.querySelectorAll('.cantidad-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const index = e.target.dataset.index;
                const cantidad = parseInt(e.target.value);
                this.actualizarCantidad(index, cantidad);
            });
        });
        
        // Eventos para eliminar
        document.querySelectorAll('.eliminar-item').forEach(button => {
            button.addEventListener('click', (e) => {
                const index = e.target.closest('button').dataset.index;
                this.eliminarProducto(index);
            });
        });
    }
    
    limpiar() {
        this.carrito = [];
        this.total = 0;
        this.subtotal = 0;
        this.clienteId = null;
        this.tipoPago = 'contado';
        this.actualizarVista();
    }
    
    generarVenta() {
        if (this.carrito.length === 0) {
            showNotification('El carrito está vacío', 'warning');
            return false;
        }
        
        const ventaData = {
            carrito: this.carrito,
            cliente_id: this.clienteId,
            tipo_pago: this.tipoPago,
            total: this.total,
            subtotal: this.subtotal
        };
        
        return ventaData;
    }
}

// Instancia global del punto de venta
let puntoVenta = new PuntoVenta();

// Función para buscar productos
function buscarProductos() {
    const searchTerm = document.getElementById('buscarProducto').value;
    
    $.ajax({
        url: '../modulos/subpaquetes/buscar.php',
        type: 'POST',
        data: { term: searchTerm },
        success: function(response) {
            mostrarResultadosBusqueda(response);
        },
        error: function() {
            showNotification('Error al buscar productos', 'error');
        }
    });
}

function mostrarResultadosBusqueda(productos) {
    const container = document.getElementById('resultados-busqueda');
    container.innerHTML = '';
    
    productos.forEach(producto => {
        const card = `
            <div class="col-md-4 mb-3">
                <div class="card producto-card">
                    <div class="card-body">
                        <h6 class="card-title">${producto.nombre_color}</h6>
                        <p class="card-text">
                            <small>Código: ${producto.codigo_color}</small><br>
                            <small>Stock: ${producto.stock}</small><br>
                            <strong>${formatCurrency(producto.precio_venta)}</strong>
                        </p>
                        <button class="btn btn-primary btn-sm agregar-carrito" 
                                data-producto='${JSON.stringify(producto)}'>
                            <i class="bi bi-cart-plus"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += card;
    });
    
    // Agregar eventos a los botones
    document.querySelectorAll('.agregar-carrito').forEach(button => {
        button.addEventListener('click', (e) => {
            const producto = JSON.parse(e.target.closest('button').dataset.producto);
            agregarAlCarrito(producto);
        });
    });
}

function agregarAlCarrito(producto) {
    const cantidad = parseInt(prompt('Ingrese la cantidad:', '1'));
    
    if (cantidad > 0 && cantidad <= producto.stock) {
        puntoVenta.agregarProducto({
            id: producto.id,
            codigo: producto.codigo_color,
            nombre: producto.nombre_color,
            precio: producto.precio_venta,
            cantidad: cantidad
        });
        showNotification('Producto agregado al carrito', 'success');
    } else if (cantidad > producto.stock) {
        showNotification('Cantidad excede el stock disponible', 'warning');
    }
}

// Funciones para inventario
function actualizarStock(id, cambio) {
    $.ajax({
        url: '../modulos/inventario/actualizar.php',
        type: 'POST',
        data: {
            id: id,
            cambio: cambio
        },
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                // Actualizar la fila en la tabla
                const fila = document.querySelector(`tr[data-id="${id}"]`);
                if (fila) {
                    const stockCell = fila.querySelector('.stock-actual');
                    stockCell.textContent = response.nuevo_stock;
                    
                    // Actualizar clase según stock
                    if (response.nuevo_stock <= 0) {
                        fila.classList.add('table-danger');
                    } else if (response.nuevo_stock <= 5) {
                        fila.classList.add('table-warning');
                    } else {
                        fila.classList.remove('table-danger', 'table-warning');
                    }
                }
            } else {
                showNotification(response.message, 'error');
            }
        }
    });
}

// Funciones para reportes
function generarReporte(tipo, fechaInicio, fechaFin) {
    showLoading();
    
    $.ajax({
        url: '../modulos/reportes/generar_pdf.php',
        type: 'POST',
        data: {
            tipo: tipo,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin
        },
        xhrFields: {
            responseType: 'blob'
        },
        success: function(blob) {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_${tipo}_${fechaInicio}_${fechaFin}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            hideLoading();
        },
        error: function() {
            showNotification('Error al generar el reporte', 'error');
            hideLoading();
        }
    });
}

// Funciones para cuentas
function registrarPago(tipo, id, monto) {
    if (!monto || monto <= 0) {
        showNotification('Ingrese un monto válido', 'warning');
        return;
    }
    
    $.ajax({
        url: '../modulos/cuentas/registrar_pago.php',
        type: 'POST',
        data: {
            tipo: tipo,
            id: id,
            monto: monto
        },
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                // Actualizar saldo en la interfaz
                const saldoElement = document.querySelector(`[data-id="${id}"] .saldo-actual`);
                if (saldoElement) {
                    saldoElement.textContent = formatCurrency(response.nuevo_saldo);
                }
                // Limpiar el campo de monto
                document.getElementById('montoPago').value = '';
            } else {
                showNotification(response.message, 'error');
            }
        }
    });
}

// Funciones de utilidad
function calcularEdad(fechaNacimiento) {
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const mes = hoy.getMonth() - nacimiento.getMonth();
    
    if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }
    
    return edad;
}

function formatoTelefono(telefono) {
    if (!telefono) return '';
    const cleaned = telefono.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return '(' + match[1] + ') ' + match[2] + '-' + match[3];
    }
    return telefono;
}

// Inicializar funciones específicas
$(document).ready(function() {
    // Inicializar select2 si existe
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });
    }
    
    // Inicializar datepickers
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            language: 'es'
        });
    }
    
    // Manejar modales
    $('[data-toggle="modal"]').click(function() {
        const target = $(this).data('target');
        $(target).modal('show');
    });
    
    // Auto-numerar filas en tablas
    $('.table tbody tr').each(function(index) {
        $(this).find('td:first').text(index + 1);
    });
});
// Funciones específicas del sistema

// Calcular total en punto de venta
function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.subtotal').forEach(element => {
        total += parseFloat(element.value || element.textContent);
    });
    document.getElementById('total-venta').value = total.toFixed(2);
    document.getElementById('total-display').textContent = formatCurrency(total);
    return total;
}

// Agregar producto al carrito (simulación)
function agregarAlCarrito(productoId, cantidad) {
    const data = {
        producto_id: productoId,
        cantidad: cantidad
    };
    
    fetch('ajax/agregar_carrito.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Producto agregado al carrito', 'success');
            actualizarCarrito();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al agregar producto', 'error');
    });
}

// Actualizar visualización del carrito
function actualizarCarrito() {
    fetch('ajax/obtener_carrito.php')
        .then(response => response.json())
        .then(data => {
            // Actualizar interfaz del carrito
            const carritoElement = document.getElementById('carrito-items');
            if (carritoElement) {
                carritoElement.innerHTML = data.html;
            }
            
            // Actualizar contador
            const contadorElement = document.getElementById('carrito-count');
            if (contadorElement) {
                contadorElement.textContent = data.count;
            }
        });
}

// Buscar productos
function buscarProductos(termino) {
    if (termino.length < 2) return;
    
    fetch(`ajax/buscar_productos.php?q=${encodeURIComponent(termino)}`)
        .then(response => response.json())
        .then(data => {
            const resultadosElement = document.getElementById('resultados-busqueda');
            if (resultadosElement) {
                resultadosElement.innerHTML = data.html;
                resultadosElement.style.display = 'block';
            }
        });
}

// Calcular cambio
function calcularCambio(total, pago) {
    const cambio = pago - total;
    if (cambio > 0) {
        document.getElementById('cambio-display').textContent = formatCurrency(cambio);
        document.getElementById('cambio-display').classList.remove('d-none');
    } else {
        document.getElementById('cambio-display').classList.add('d-none');
    }
}

// Exportar a Excel
function exportarExcel(tablaId, nombreArchivo) {
    const tabla = document.getElementById(tablaId);
    const wb = XLSX.utils.table_to_book(tabla, {sheet: "Sheet1"});
    XLSX.writeFile(wb, `${nombreArchivo}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

// Generar gráfico (si se usa Chart.js)
function generarGrafico(tipo, datos, elementoId) {
    const ctx = document.getElementById(elementoId).getContext('2d');
    
    const config = {
        type: tipo,
        data: datos,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Gráfico de Ventas'
                }
            }
        }
    };
    
    return new Chart(ctx, config);
}

// Validar stock
function validarStock(productoId, cantidad) {
    return fetch(`ajax/verificar_stock.php?producto_id=${productoId}&cantidad=${cantidad}`)
        .then(response => response.json())
        .then(data => data.disponible);
}

// Actualizar precio según producto
function actualizarPrecio(productoId) {
    fetch(`ajax/obtener_precio.php?id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('precio-unitario').value = data.precio;
            document.getElementById('precio-display').textContent = formatCurrency(data.precio);
        });
}

// Formatear input de moneda
function formatCurrencyInput(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    if (value) {
        input.value = formatCurrency(value);
    }
}

// Cargar categorías según proveedor
function cargarCategorias(proveedorId, selectElementId) {
    if (!proveedorId) return;
    
    fetch(`ajax/cargar_categorias.php?proveedor_id=${proveedorId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById(selectElementId).innerHTML = html;
        });
}

// Inicializar DataTable con opciones personalizadas
function initDataTable(tableId, options = {}) {
    const defaultOptions = {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        pageLength: 10,
        responsive: true
    };
    
    return $(`#${tableId}`).DataTable({...defaultOptions, ...options});
}