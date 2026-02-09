<?php
// dashboard.php - Dashboard principal

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
Funciones::verificarSesion();

// Obtener estadísticas
$estadisticas = Funciones::obtenerEstadisticas();
$notificaciones = Funciones::obtenerNotificaciones(Funciones::obtenerUsuarioId());

// Obtener ventas recientes
$db = getDB();
try {
    $stmt = $db->prepare("SELECT v.*, c.nombre as cliente_nombre, u.nombre as vendedor_nombre 
                         FROM ventas v 
                         LEFT JOIN clientes c ON v.cliente_id = c.id 
                         LEFT JOIN usuarios u ON v.vendedor_id = u.id 
                         WHERE v.anulado = FALSE 
                         ORDER BY v.fecha_hora DESC 
                         LIMIT 10");
    $stmt->execute();
    $ventas_recientes = $stmt->fetchAll();
} catch(PDOException $e) {
    $ventas_recientes = [];
}

// Obtener productos bajos en stock
try {
    $stmt = $db->prepare("SELECT sp.*, p.nombre as paquete_nombre 
                         FROM subpaquetes sp 
                         JOIN paquetes p ON sp.paquete_id = p.id 
                         WHERE sp.stock <= sp.min_stock 
                         AND sp.activo = TRUE 
                         ORDER BY sp.stock ASC 
                         LIMIT 10");
    $stmt->execute();
    $stock_bajo = $stmt->fetchAll();
} catch(PDOException $e) {
    $stock_bajo = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Inventario</title>
    
    <?php include 'header.php'; ?>
    
    <style>
        .card-stat {
            transition: transform 0.3s;
            border: none;
            border-radius: 15px;
        }
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .quick-actions .btn {
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }
        .quick-actions .btn:hover {
            transform: scale(1.05);
        }
        .recent-table th {
            border-top: none;
            background-color: #f8f9fa;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2 text-success">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </h1>
                    <p class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Bienvenido, <?php echo Funciones::obtenerNombreUsuario(); ?> 
                        <span class="badge bg-success ms-2"><?php echo Funciones::obtenerRolUsuario(); ?></span>
                    </p>
                </div>
                <div class="position-relative">
                    <button class="btn btn-light btn-lg position-relative" id="btnNotificaciones" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell text-success"></i>
                        <?php if (!empty($notificaciones)): ?>
                            <span class="notification-badge badge bg-danger rounded-circle">
                                <?php echo count($notificaciones); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 300px;">
                        <h6 class="dropdown-header">
                            <i class="fas fa-bell me-2"></i>Notificaciones
                        </h6>
                        <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($notificaciones)): ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <p>No hay notificaciones</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notificaciones as $notif): ?>
                                    <a href="<?php echo $notif['url']; ?>" 
                                       class="dropdown-item d-flex align-items-center py-2 border-bottom"
                                       onclick="marcarLeida(<?php echo $notif['id']; ?>)">
                                        <div class="me-3">
                                            <i class="fas fa-<?php echo $notif['tipo'] == 'stock' ? 'boxes' : ($notif['tipo'] == 'pago' ? 'dollar-sign' : 'shopping-cart'); ?> 
                                                         text-<?php echo $notif['tipo'] == 'stock' ? 'warning' : ($notif['tipo'] == 'pago' ? 'danger' : 'success'); ?>">
                                            </i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">
                                                <?php echo Funciones::formatearFecha($notif['fecha_hora'], 'd/m H:i'); ?>
                                            </small>
                                            <span class="d-block"><?php echo $notif['mensaje']; ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item text-center text-success" 
                           onclick="marcarTodasLeidas()">
                            <i class="fas fa-check-double me-2"></i>Marcar todas como leídas
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mostrar alertas de sesión -->
            <?php Funciones::mostrarAlertaSesion(); ?>
            
            <!-- Estadísticas principales -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-stat border-start border-success border-4 shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Ventas Hoy
                                    </div>
                                    <div class="h5 mb-0 fw-bold text-gray-800">
                                        <?php echo Funciones::formatearMonedaBolivianos($estadisticas['ventas_hoy']['monto'] ?? 0); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-success me-2">
                                            <i class="fas fa-shopping-cart me-1"></i>
                                            <?php echo $estadisticas['ventas_hoy']['total'] ?? 0; ?> transacciones
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart stat-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-stat border-start border-warning border-4 shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Stock Bajo
                                    </div>
                                    <div class="h5 mb-0 fw-bold text-gray-800">
                                        <?php echo $estadisticas['stock_bajo'] ?? 0; ?> productos
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-warning me-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Necesitan reposición
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-boxes stat-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-stat border-start border-danger border-4 shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                                        Deuda Clientes
                                    </div>
                                    <div class="h5 mb-0 fw-bold text-gray-800">
                                        <?php echo Funciones::formatearMonedaBolivianos($estadisticas['clientes_deuda']['monto'] ?? 0); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-danger me-2">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $estadisticas['clientes_deuda']['total'] ?? 0; ?> clientes
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-friends stat-icon text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-stat border-start border-info border-4 shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                        Ventas Mes
                                    </div>
                                    <div class="h5 mb-0 fw-bold text-gray-800">
                                        <?php echo Funciones::formatearMonedaBolivianos($estadisticas['ventas_mes'] ?? 0); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-info me-2">
                                            <i class="fas fa-chart-line me-1"></i>
                                            Total del mes
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-bar stat-icon text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Acciones rápidas -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row quick-actions text-center">
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_ventas.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-cash-register fa-2x mb-2"></i>
                                        <br>
                                        <small>Nueva Venta</small>
                                    </a>
                                </div>
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_productos.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-boxes fa-2x mb-2"></i>
                                        <br>
                                        <small>Productos</small>
                                    </a>
                                </div>
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_clientes.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <br>
                                        <small>Clientes</small>
                                    </a>
                                </div>
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_cuentas.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                                        <br>
                                        <small>Cuentas</small>
                                    </a>
                                </div>
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_reportes.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-chart-pie fa-2x mb-2"></i>
                                        <br>
                                        <small>Reportes</small>
                                    </a>
                                </div>
                                <?php if (Funciones::esAdmin()): ?>
                                <div class="col-md-2 col-4 mb-3">
                                    <a href="modulo_usuarios.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-user-cog fa-2x mb-2"></i>
                                        <br>
                                        <small>Usuarios</small>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Productos más vendidos -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>Productos Más Vendidos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Código</th>
                                            <th>Ventas</th>
                                            <th>Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($estadisticas['top_productos'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">
                                                    <i class="fas fa-info-circle me-2"></i>No hay datos de ventas
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($estadisticas['top_productos'] as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-circle me-2" style="color: #<?php echo substr(md5($producto['nombre_color']), 0, 6); ?>"></i>
                                                        <?php echo htmlspecialchars($producto['nombre_color']); ?>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark"><?php echo $producto['codigo_color']; ?></span></td>
                                                    <td><?php echo $producto['ventas']; ?> unid.</td>
                                                    <td>
                                                        <?php 
                                                        // Obtener stock actual
                                                        $stmt = $db->prepare("SELECT stock FROM subpaquetes WHERE codigo_color = ?");
                                                        $stmt->execute([$producto['codigo_color']]);
                                                        $stock = $stmt->fetch()['stock'] ?? 0;
                                                        ?>
                                                        <span class="badge bg-<?php echo $stock < 10 ? 'warning' : 'success'; ?>">
                                                            <?php echo $stock; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stock bajo -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-warning text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Stock Bajo
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Paquete</th>
                                            <th>Stock</th>
                                            <th>Mínimo</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($stock_bajo)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">
                                                    <i class="fas fa-check-circle me-2"></i>Todo el stock está en niveles óptimos
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($stock_bajo as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-circle me-2" style="color: #<?php echo substr(md5($producto['nombre_color']), 0, 6); ?>"></i>
                                                        <?php echo htmlspecialchars($producto['nombre_color']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($producto['paquete_nombre']); ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo $producto['stock']; ?></span>
                                                    </td>
                                                    <td><?php echo $producto['min_stock']; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="reponerStock(<?php echo $producto['id']; ?>)">
                                                            <i class="fas fa-plus"></i> Reponer
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ventas recientes -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Ventas Recientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover recent-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Pago</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($ventas_recientes)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">
                                                    <i class="fas fa-info-circle me-2"></i>No hay ventas recientes
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($ventas_recientes as $venta): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-light text-dark">#<?php echo $venta['codigo_venta']; ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor Final'); ?></td>
                                                    <td><?php echo Funciones::formatearFecha($venta['fecha_hora'], 'd/m/Y H:i'); ?></td>
                                                    <td class="fw-bold"><?php echo Funciones::formatearMonedaBolivianos($venta['total']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $venta['tipo_pago'] == 'contado' ? 'success' : ($venta['tipo_pago'] == 'credito' ? 'warning' : 'info'); ?>">
                                                            <?php echo ucfirst($venta['tipo_pago']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $venta['debe'] > 0 ? 'warning' : 'success'; ?>">
                                                            <?php echo $venta['debe'] > 0 ? 'Pendiente' : 'Pagado'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="imprimir_recibo.php?id=<?php echo $venta['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" target="_blank">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                        <a href="modulo_ventas.php?ver=<?php echo $venta['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para reponer stock -->
    <div class="modal fade" id="modalReponerStock" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Reponer Stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formReponerStock">
                        <input type="hidden" id="reponer_subpaquete_id">
                        <div class="mb-3">
                            <label for="reponer_cantidad" class="form-label">Cantidad a agregar:</label>
                            <input type="number" class="form-control" id="reponer_cantidad" 
                                   min="1" max="1000" value="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="reponer_observacion" class="form-label">Observación:</label>
                            <textarea class="form-control" id="reponer_observacion" rows="2" 
                                      placeholder="Motivo de la reposición..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-success" onclick="confirmarReponerStock()">
                        <i class="fas fa-check me-2"></i>Confirmar Reposición
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script>
        // Función para reponer stock
        function reponerStock(subpaqueteId) {
            document.getElementById('reponer_subpaquete_id').value = subpaqueteId;
            const modal = new bootstrap.Modal(document.getElementById('modalReponerStock'));
            modal.show();
        }
        
        // Confirmar reposición de stock
        function confirmarReponerStock() {
            const subpaqueteId = document.getElementById('reponer_subpaquete_id').value;
            const cantidad = document.getElementById('reponer_cantidad').value;
            const observacion = document.getElementById('reponer_observacion').value;
            
            if (!cantidad || cantidad < 1) {
                alert('Ingrese una cantidad válida');
                return;
            }
            
            fetch('ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reponer_stock&subpaquete_id=${subpaqueteId}&cantidad=${cantidad}&observacion=${encodeURIComponent(observacion)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Stock repuesto exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error en la operación');
            });
        }
        
        // Marcar notificación como leída
        function marcarLeida(notificacionId) {
            fetch('ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=marcar_notificacion_leida&id=${notificacionId}`
            });
        }
        
        // Marcar todas las notificaciones como leídas
        function marcarTodasLeidas() {
            fetch('ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=marcar_todas_notificaciones_leidas'
            })
            .then(() => {
                location.reload();
            });
        }
    </script>
</body>
</html>