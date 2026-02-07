<?php
// modulo_cuentas.php - Gestión de cuentas por cobrar y pagar
session_start();

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$mensaje = '';
$error = '';

// Procesar filtros
$filtro_tipo = $_GET['tipo'] ?? 'cobrar';
$filtro_estado = $_GET['estado'] ?? 'pendiente';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Cargar clientes y proveedores
try {
    $stmt = $db->query("SELECT id, nombre, telefono, saldo_deuda, limite_credito FROM clientes WHERE activo = TRUE ORDER BY nombre");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT id, nombre, telefono, saldo_deuda FROM proveedores WHERE activo = TRUE ORDER BY nombre");
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
    $clientes = $proveedores = [];
}

// Construir consultas según filtros
try {
    // Cuentas por cobrar
    $sql_cobrar = "SELECT cc.*, c.nombre as cliente_nombre, c.telefono, c.email,
                          u.nombre as usuario_nombre, v.codigo_venta
                   FROM cuentas_cobrar cc
                   JOIN clientes c ON cc.cliente_id = c.id
                   JOIN usuarios u ON cc.usuario_id = u.id
                   LEFT JOIN ventas v ON cc.venta_id = v.id
                   WHERE DATE(cc.fecha_hora) BETWEEN ? AND ?";
    
    // Cuentas por pagar
    $sql_pagar = "SELECT cp.*, p.nombre as proveedor_nombre, p.telefono, p.email,
                         u.nombre as usuario_nombre
                  FROM cuentas_pagar cp
                  JOIN proveedores p ON cp.proveedor_id = p.id
                  JOIN usuarios u ON cp.usuario_id = u.id
                  WHERE DATE(cp.fecha_hora) BETWEEN ? AND ?";
    
    $params_cobrar = [$filtro_fecha_desde, $filtro_fecha_hasta];
    $params_pagar = [$filtro_fecha_desde, $filtro_fecha_hasta];
    
    // Aplicar filtro de estado
    if ($filtro_estado === 'pendiente') {
        $sql_cobrar .= " AND cc.tipo IN ('venta', 'abono')";
        $sql_pagar .= " AND cp.tipo IN ('compra', 'abono')";
    } elseif ($filtro_estado === 'pagada') {
        $sql_cobrar .= " AND cc.tipo = 'pago'";
        $sql_pagar .= " AND cp.tipo = 'pago'";
    }
    
    $sql_cobrar .= " ORDER BY cc.fecha_hora DESC";
    $sql_pagar .= " ORDER BY cp.fecha_hora DESC";
    
    // Ejecutar consultas
    $stmt = $db->prepare($sql_cobrar);
    $stmt->execute($params_cobrar);
    $cuentas_cobrar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare($sql_pagar);
    $stmt->execute($params_pagar);
    $cuentas_pagar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales
    $total_pendiente_cobrar = 0;
    $total_pagado_cobrar = 0;
    
    foreach ($cuentas_cobrar as $cuenta) {
        if ($cuenta['tipo'] === 'pago') {
            $total_pagado_cobrar += $cuenta['monto'];
        } else {
            $total_pendiente_cobrar += $cuenta['monto'];
        }
    }
    
    $total_pendiente_pagar = 0;
    $total_pagado_pagar = 0;
    
    foreach ($cuentas_pagar as $cuenta) {
        if ($cuenta['tipo'] === 'pago') {
            $total_pagado_pagar += $cuenta['monto'];
        } else {
            $total_pendiente_pagar += $cuenta['monto'];
        }
    }
    
    // Clientes con mayor deuda
    $stmt = $db->query("
        SELECT c.*, 
        COALESCE(c.saldo_deuda, 0) as deuda_total
        FROM clientes c
        WHERE c.activo = TRUE
        AND c.saldo_deuda > 0
        ORDER BY c.saldo_deuda DESC
        LIMIT 5
    ");
    $top_deudores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Próximos vencimientos
    $stmt = $db->prepare("
        SELECT v.*, c.nombre as cliente_nombre, c.id as cliente_id,
        DATEDIFF(v.fecha_vencimiento, CURDATE()) as dias_vencimiento,
        v.debe as pagado_total
        FROM ventas v
        JOIN clientes c ON v.cliente_id = c.id
        WHERE v.tipo_pago IN ('credito', 'mixto')
        AND v.debe > 0
        AND v.anulado = FALSE
        AND v.fecha_vencimiento IS NOT NULL
        AND v.fecha_vencimiento >= CURDATE()
        ORDER BY v.fecha_vencimiento ASC
        LIMIT 5
    ");
    $stmt->execute();
    $proximos_vencimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de deudas globales
    $stmt = $db->query("SELECT SUM(saldo_deuda) as total_deuda FROM clientes WHERE activo = TRUE");
    $total_deuda_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total_deuda'] ?? 0;
    
    $stmt = $db->query("SELECT SUM(saldo_deuda) as total_deuda FROM proveedores WHERE activo = TRUE");
    $total_deuda_proveedores = $stmt->fetch(PDO::FETCH_ASSOC)['total_deuda'] ?? 0;
    
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
    $cuentas_cobrar = $cuentas_pagar = $top_deudores = $proximos_vencimientos = [];
    $total_pendiente_cobrar = $total_pagado_cobrar = 0;
    $total_pendiente_pagar = $total_pagado_pagar = 0;
    $total_deuda_clientes = $total_deuda_proveedores = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas - Sistema de Inventario</title>
    
    <!-- Incluir header que contiene Bootstrap, Font Awesome, etc -->
    <?php include 'header.php'; ?>
    
    <style>
        /* ESTILOS ORIGINALES DEL SISTEMA - MODIFICAR AQUÍ PARA CAMBIAR COLORES */
        :root {
            --primary-color: #28a745; /* VERDE - Cambia este valor para cambiar el color principal */
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }
        
        .account-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .account-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .account-type {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        /* COLORES DE TIPOS - MODIFICAR AQUÍ */
        .type-venta { background-color: #e3f2fd; color: #1976d2; }
        .type-pago { background-color: #e8f5e9; color: #388e3c; }
        .type-abono { background-color: #fff3e0; color: #f57c00; }
        .type-compra { background-color: #fce4ec; color: #c2185b; }
        
        .filters-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 15px 15px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-radius: 10px 10px 0 0;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #495057;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: #e9ecef;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color); 
            background-color: white;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .due-date {
            font-size: 0.875rem;
        }
        
        .due-date.urgent {
            color: #dc3545;
            font-weight: bold;
        }
        
        .due-date.warning {
            color: #ffc107;
            font-weight: bold;
        }
        
        .due-date.ok {
            color: #28a745;
        }
        
        .amount {
            font-weight: bold;
        }
        
        .amount.positive {
            color: #28a745;
        }
        
        .amount.negative {
            color: #dc3545;
        }
        
        .payment-method-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }
        
        /* COLORES DE MÉTODOS DE PAGO - MODIFICAR AQUÍ */
        .cash { background-color: #28a745; }
        .transfer { background-color: #007bff; }
        .card { background-color: #ffffff; }
        .check { background-color: #fd7e14; }
        
        .summary-card {
            border-left: 4px solid;
            padding-left: 1rem;
        }
        
        .summary-cobrar { border-color: #28a745; }
        .summary-pagar { border-color: #dc3545; }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--primary-color); /* Usa el color principal */
            border: 2px solid white;
            box-shadow: 0 0 0 3px var(--primary-color); /* Usa el color principal */
        }
        
        /* ESTILOS PARA BOTONES PRINCIPALES */
        .btn-success {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .btn-success:hover {
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
        }
        
        /* ESTILOS PARA ENCABEZADOS */
        .text-success {
            color: var(--primary-color) !important;
        }
        
        .border-success {
            border-color: var(--primary-color) !important;
        }
        
        .bg-success {
            background-color: var(--primary-color) !important;
        }
        
        /* ESTILOS PARA BARRAS DE PROGRESO */
        .progress-bar {
            background-color: var(--primary-color);
        }
        
        /* ESTILOS PARA BADGES */
        .badge-success {
            background-color: var(--primary-color);
        }
        
        /* ESTILOS PARA FECHA EN TABLA */
        .fecha-hora {
            font-size: 0.9rem;
        }
        
        .fecha {
            font-weight: 600;
            color: #495057;
        }
        
        .hora {
            font-size: 0.8rem;
            color: #6c757d;
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
                        <i class="fas fa-hand-holding-usd me-2"></i>Cuentas por Cobrar y Pagar
                    </h1>
                    <p class="text-muted">
                        Gestión de deudas, pagos y estados de cuenta
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoPago">
                        <i class="fas fa-money-bill-wave me-2"></i>Nuevo Pago
                    </button>
                    <button class="btn btn-outline-success" onclick="generarReporteCuentas()">
                        <i class="fas fa-file-export me-2"></i>Exportar Reporte
                    </button>
                </div>
            </div>
            
            <!-- Mostrar mensajes -->
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-success border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-success mb-1">
                                        Por Cobrar
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo Funciones::formatearMoneda($total_pendiente_cobrar); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-success me-2">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo count(array_filter($cuentas_cobrar, fn($c) => $c['tipo'] !== 'pago')); ?> cuentas
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-hand-holding-usd stats-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-danger border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-danger mb-1">
                                        Por Pagar
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo Funciones::formatearMoneda($total_pendiente_pagar); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-danger me-2">
                                            <i class="fas fa-truck me-1"></i>
                                            <?php echo count(array_filter($cuentas_pagar, fn($c) => $c['tipo'] !== 'pago')); ?> cuentas
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice-dollar stats-icon text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-info border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-info mb-1">
                                        Cobrado
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo Funciones::formatearMoneda($total_pagado_cobrar); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-info me-2">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <?php echo count(array_filter($cuentas_cobrar, fn($c) => $c['tipo'] === 'pago')); ?> pagos
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-cash-register stats-icon text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-warning border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-warning mb-1">
                                        Pagado
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo Funciones::formatearMoneda($total_pagado_pagar); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span class="text-warning me-2">
                                            <i class="fas fa-receipt me-1"></i>
                                            <?php echo count(array_filter($cuentas_pagar, fn($c) => $c['tipo'] === 'pago')); ?> pagos
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice stats-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card filters-card mb-4">
                <form method="GET" id="filtersForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Cuenta</label>
                            <select class="form-select" name="tipo" onchange="this.form.submit()">
                                <option value="cobrar" <?php echo $filtro_tipo === 'cobrar' ? 'selected' : ''; ?>>Por Cobrar</option>
                                <option value="pagar" <?php echo $filtro_tipo === 'pagar' ? 'selected' : ''; ?>>Por Pagar</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" onchange="this.form.submit()">
                                <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="pagada" <?php echo $filtro_estado === 'pagada' ? 'selected' : ''; ?>>Pagadas</option>
                                <option value="todas" <?php echo $filtro_estado === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" name="fecha_desde" 
                                   value="<?php echo $filtro_fecha_desde; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" 
                                   value="<?php echo $filtro_fecha_hasta; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Resumen -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card summary-card summary-cobrar">
                        <div class="card-body">
                            <h5 class="card-title text-success">
                                <i class="fas fa-hand-holding-usd me-2"></i>
                                Resumen Por Cobrar
                            </h5>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Pendiente</small>
                                    <h4 class="fw-bold text-success"><?php echo Funciones::formatearMoneda($total_pendiente_cobrar); ?></h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Cobrado</small>
                                    <h4 class="fw-bold text-info"><?php echo Funciones::formatearMoneda($total_pagado_cobrar); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card summary-card summary-pagar">
                        <div class="card-body">
                            <h5 class="card-title text-danger">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                Resumen Por Pagar
                            </h5>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Pendiente</small>
                                    <h4 class="fw-bold text-danger"><?php echo Funciones::formatearMoneda($total_pendiente_pagar); ?></h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Pagado</small>
                                    <h4 class="fw-bold text-warning"><?php echo Funciones::formatearMoneda($total_pagado_pagar); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-3" id="accountsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'cobrar' ? 'active' : ''; ?>" 
                            id="cobrar-tab" data-bs-toggle="tab" data-bs-target="#cobrar" 
                            type="button" role="tab" onclick="cambiarTab('cobrar')">
                        <i class="fas fa-hand-holding-usd me-2"></i>Por Cobrar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'pagar' ? 'active' : ''; ?>" 
                            id="pagar-tab" data-bs-toggle="tab" data-bs-target="#pagar" 
                            type="button" role="tab" onclick="cambiarTab('pagar')">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Por Pagar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="vencimientos-tab" data-bs-toggle="tab" 
                            data-bs-target="#vencimientos" type="button" role="tab">
                        <i class="fas fa-calendar-times me-2"></i>Próximos Vencimientos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deudores-tab" data-bs-toggle="tab" 
                            data-bs-target="#deudores" type="button" role="tab">
                        <i class="fas fa-user-times me-2"></i>Principales Deudores
                    </button>
                </li>
            </ul>
            
            <!-- Contenido de pestañas -->
            <div class="tab-content" id="accountsTabsContent">
                
                <!-- Pestaña Por Cobrar -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'cobrar' ? 'show active' : ''; ?>" 
                     id="cobrar" role="tabpanel">
                    <?php if (empty($cuentas_cobrar)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">No hay cuentas por cobrar</h5>
                            <p class="text-muted">Todas las cuentas están al día</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Tipo</th>
                                        <th>Monto</th>
                                        <th>Saldo Anterior</th>
                                        <th>Saldo Nuevo</th>
                                        <th>Venta</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cuentas_cobrar as $cuenta): 
                                        $tipo_clase = $cuenta['tipo'] === 'pago' ? 'type-pago' : 
                                                    ($cuenta['tipo'] === 'venta' ? 'type-venta' : 'type-abono');
                                        // Formatear fecha y hora
                                        $fecha_hora = $cuenta['fecha_hora'];
                                        $fecha = Funciones::formatearFecha($fecha_hora, 'd/m/Y');
                                        $hora = Funciones::formatearFecha($fecha_hora, 'H:i');
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fecha-hora">
                                                    <div class="fecha"><?php echo $fecha; ?></div>
                                                    <div class="hora"><?php echo $hora; ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($cuenta['cliente_nombre']); ?></div>
                                                <small class="text-muted"><?php echo $cuenta['telefono']; ?></small>
                                            </td>
                                            <td>
                                                <span class="account-type <?php echo $tipo_clase; ?>">
                                                    <?php echo $cuenta['tipo']; ?>
                                                </span>
                                            </td>
                                            <td class="amount <?php echo $cuenta['tipo'] === 'pago' ? 'positive' : 'negative'; ?>">
                                                <?php echo Funciones::formatearMoneda($cuenta['monto']); ?>
                                            </td>
                                            <td><?php echo Funciones::formatearMoneda($cuenta['saldo_anterior']); ?></td>
                                            <td><?php echo Funciones::formatearMoneda($cuenta['saldo_nuevo']); ?></td>
                                            <td>
                                                <?php if ($cuenta['codigo_venta']): ?>
                                                    <span class="badge bg-light text-dark">#<?php echo $cuenta['codigo_venta']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($cuenta['usuario_nombre']); ?></td>
                                            <td class="table-actions">
                                                <?php if ($cuenta['tipo'] !== 'pago'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="registrarPagoClienteDesdeCuenta(<?php echo $cuenta['cliente_id']; ?>)"
                                                            title="Registrar pago">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verDetalleCuenta(<?php echo $cuenta['id']; ?>, 'cobrar')"
                                                        title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pestaña Por Pagar -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'pagar' ? 'show active' : ''; ?>" 
                     id="pagar" role="tabpanel">
                    <?php if (empty($cuentas_pagar)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">No hay cuentas por pagar</h5>
                            <p class="text-muted">Todas las cuentas están al día</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Tipo</th>
                                        <th>Monto</th>
                                        <th>Saldo Anterior</th>
                                        <th>Saldo Nuevo</th>
                                        <th>Método Pago</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cuentas_pagar as $cuenta): 
                                        $tipo_clase = $cuenta['tipo'] === 'pago' ? 'type-pago' : 
                                                    ($cuenta['tipo'] === 'compra' ? 'type-compra' : 'type-abono');
                                        // Formatear fecha y hora
                                        $fecha_hora = $cuenta['fecha_hora'];
                                        $fecha = Funciones::formatearFecha($fecha_hora, 'd/m/Y');
                                        $hora = Funciones::formatearFecha($fecha_hora, 'H:i');
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fecha-hora">
                                                    <div class="fecha"><?php echo $fecha; ?></div>
                                                    <div class="hora"><?php echo $hora; ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($cuenta['proveedor_nombre']); ?></div>
                                                <small class="text-muted"><?php echo $cuenta['telefono']; ?></small>
                                            </td>
                                            <td>
                                                <span class="account-type <?php echo $tipo_clase; ?>">
                                                    <?php echo $cuenta['tipo']; ?>
                                                </span>
                                            </td>
                                            <td class="amount <?php echo $cuenta['tipo'] === 'pago' ? 'negative' : 'positive'; ?>">
                                                <?php echo Funciones::formatearMoneda($cuenta['monto']); ?>
                                            </td>
                                            <td><?php echo Funciones::formatearMoneda($cuenta['saldo_anterior']); ?></td>
                                            <td><?php echo Funciones::formatearMoneda($cuenta['saldo_nuevo']); ?></td>
                                            <td>
                                                <?php if ($cuenta['metodo_pago']): ?>
                                                    <?php
                                                    $icon_class = match($cuenta['metodo_pago']) {
                                                        'efectivo' => 'cash',
                                                        'transferencia' => 'transfer',
                                                        'tarjeta' => 'card',
                                                        'cheque' => 'check',
                                                        default => 'cash'
                                                    };
                                                    ?>
                                                    <span class="payment-method-icon <?php echo $icon_class; ?>">
                                                        <?php echo strtoupper(substr($cuenta['metodo_pago'], 0, 1)); ?>
                                                    </span>
                                                    <small><?php echo ucfirst($cuenta['metodo_pago']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($cuenta['usuario_nombre']); ?></td>
                                            <td class="table-actions">
                                                <?php if ($cuenta['tipo'] !== 'pago'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="registrarPagoProveedorDesdeCuenta(<?php echo $cuenta['proveedor_id']; ?>)"
                                                            title="Registrar pago">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verDetalleCuenta(<?php echo $cuenta['id']; ?>, 'pagar')"
                                                        title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pestaña Próximos Vencimientos -->
                <div class="tab-pane fade" id="vencimientos" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-times me-2"></i>
                                        Próximos Vencimientos
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($proximos_vencimientos)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <p>No hay vencimientos próximos</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Venta</th>
                                                        <th>Cliente</th>
                                                        <th>Fecha Vencimiento</th>
                                                        <th>Días Restantes</th>
                                                        <th>Monto Pendiente</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($proximos_vencimientos as $venta): 
                                                        $dias_class = $venta['dias_vencimiento'] <= 3 ? 'urgent' : 
                                                                    ($venta['dias_vencimiento'] <= 7 ? 'warning' : 'ok');
                                                        $deuda_actual = $venta['debe'] - $venta['pagado_total'];
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-light text-dark">#<?php echo $venta['codigo_venta']; ?></span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                                            <td><?php echo Funciones::formatearFecha($venta['fecha_vencimiento']); ?></td>
                                                            <td class="due-date <?php echo $dias_class; ?>">
                                                                <?php echo $venta['dias_vencimiento']; ?> días
                                                            </td>
                                                            <td class="fw-bold text-danger">
                                                                <?php echo Funciones::formatearMoneda($deuda_actual); ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-success" 
                                                                        onclick="registrarPagoClienteDesdeCuenta(<?php echo $venta['cliente_id']; ?>)">
                                                                    <i class="fas fa-money-bill-wave me-1"></i>Pagar
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bell me-2"></i>
                                        Alertas
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php 
                                        $alertas = [];
                                        
                                        // Alertas por vencimientos próximos
                                        foreach ($proximos_vencimientos as $venta) {
                                            if ($venta['dias_vencimiento'] <= 3) {
                                                $deuda_actual = $venta['debe'] - $venta['pagado_total'];
                                                $alertas[] = [
                                                    'tipo' => 'urgente',
                                                    'mensaje' => "Venta #{$venta['codigo_venta']} vence en {$venta['dias_vencimiento']} días",
                                                    'deuda' => Funciones::formatearMoneda($deuda_actual),
                                                    'fecha' => $venta['fecha_vencimiento'],
                                                    'cliente_id' => $venta['cliente_id']
                                                ];
                                            } elseif ($venta['dias_vencimiento'] <= 7) {
                                                $deuda_actual = $venta['debe'] - $venta['pagado_total'];
                                                $alertas[] = [
                                                    'tipo' => 'advertencia',
                                                    'mensaje' => "Venta #{$venta['codigo_venta']} vence en {$venta['dias_vencimiento']} días",
                                                    'deuda' => Funciones::formatearMoneda($deuda_actual),
                                                    'fecha' => $venta['fecha_vencimiento'],
                                                    'cliente_id' => $venta['cliente_id']
                                                ];
                                            }
                                        }
                                        
                                        // Alertas por deudas altas
                                        foreach ($top_deudores as $deudor) {
                                            if ($deudor['deuda_total'] > 1000) {
                                                $alertas[] = [
                                                    'tipo' => 'deuda',
                                                    'mensaje' => "{$deudor['nombre']} tiene deuda alta",
                                                    'deuda' => Funciones::formatearMoneda($deudor['deuda_total']),
                                                    'fecha' => date('Y-m-d'),
                                                    'cliente_id' => $deudor['id']
                                                ];
                                            }
                                        }
                                        
                                        if (empty($alertas)): ?>
                                            <div class="text-center py-3 text-muted">
                                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                <p>No hay alertas activas</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($alertas as $alerta): ?>
                                                <div class="timeline-item">
                                                    <div class="fw-bold <?php echo $alerta['tipo'] === 'urgente' ? 'text-danger' : ($alerta['tipo'] === 'advertencia' ? 'text-warning' : 'text-info'); ?>">
                                                        <?php echo $alerta['mensaje']; ?>
                                                    </div>
                                                    <small class="text-muted d-block">
                                                        <?php echo $alerta['deuda']; ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <?php echo Funciones::formatearFecha($alerta['fecha']); ?>
                                                    </small>
                                                    <button class="btn btn-sm btn-outline-success mt-1" 
                                                            onclick="registrarPagoClienteDesdeCuenta(<?php echo $alerta['cliente_id']; ?>)">
                                                        <i class="fas fa-money-bill-wave me-1"></i>Pagar
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Principales Deudores -->
                <div class="tab-pane fade" id="deudores" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-times me-2"></i>
                                        Principales Deudores
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_deudores)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <p>No hay clientes con deuda</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Cliente</th>
                                                        <th>Deuda Total</th>
                                                        <th>Límite Crédito</th>
                                                        <th>% Utilizado</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_deudores as $deudor): 
                                                        $limite_credito = $deudor['limite_credito'] ?: 1; // Evitar división por cero
                                                        $porcentaje = ($deudor['deuda_total'] / $limite_credito) * 100;
                                                        $porcentaje_class = $porcentaje > 90 ? 'danger' : 
                                                                          ($porcentaje > 70 ? 'warning' : 'success');
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($deudor['nombre']); ?></div>
                                                                <small class="text-muted"><?php echo $deudor['telefono']; ?></small>
                                                            </td>
                                                            <td class="fw-bold text-danger">
                                                                <?php echo Funciones::formatearMoneda($deudor['deuda_total']); ?>
                                                            </td>
                                                            <td><?php echo Funciones::formatearMoneda($deudor['limite_credito']); ?></td>
                                                            <td>
                                                                <div class="progress" style="height: 20px;">
                                                                    <div class="progress-bar bg-<?php echo $porcentaje_class; ?>" 
                                                                         style="width: <?php echo min($porcentaje, 100); ?>%">
                                                                        <?php echo number_format($porcentaje, 1); ?>%
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-success" 
                                                                        onclick="registrarPagoClienteDesdeCuenta(<?php echo $deudor['id']; ?>)">
                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Distribución de Deudas
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_deudores)): ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                            <p>No hay datos para mostrar</p>
                                        </div>
                                    <?php else: ?>
                                        <canvas id="deudasChart" height="300"></canvas>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para nuevo pago -->
    <div class="modal fade" id="modalNuevoPago" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave me-2"></i>Registrar Nuevo Pago
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pago-cliente-tab" data-bs-toggle="tab" 
                                    data-bs-target="#pago-cliente" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Pago de Cliente
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pago-proveedor-tab" data-bs-toggle="tab" 
                                    data-bs-target="#pago-proveedor" type="button" role="tab">
                                <i class="fas fa-truck me-2"></i>Pago a Proveedor
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3">
                        <!-- Pago de cliente -->
                        <div class="tab-pane fade show active" id="pago-cliente" role="tabpanel">
                            <form id="formPagoClienteRapido">
                                <input type="hidden" name="action" value="registrar_pago_cliente">
                                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuario_id']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cliente *</label>
                                        <select class="form-select" name="cliente_id" required 
                                                onchange="cargarDeudaCliente(this.value)">
                                            <option value="">Seleccionar cliente...</option>
                                            <?php foreach ($clientes as $cliente): 
                                                $deuda = floatval($cliente['saldo_deuda']);
                                                if ($deuda > 0): ?>
                                                <option value="<?php echo $cliente['id']; ?>" data-deuda="<?php echo $deuda; ?>">
                                                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                                                    (Deuda: <?php echo Funciones::formatearMoneda($deuda); ?>)
                                                </option>
                                            <?php endif; endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Deuda Actual</label>
                                        <input type="text" class="form-control" id="deuda_cliente_actual" 
                                               value="$0.00" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Monto a Pagar *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="monto" 
                                                   step="0.01" min="0.01" required id="montoCliente">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Método de Pago *</label>
                                        <select class="form-select" name="metodo_pago" required>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="transferencia">Transferencia</option>
                                            <option value="tarjeta">Tarjeta</option>
                                            <option value="cheque">Cheque</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Referencia (opcional)</label>
                                        <input type="text" class="form-control" name="referencia_pago" 
                                               placeholder="Nº de transferencia, cheque, etc.">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Pago a proveedor -->
                        <div class="tab-pane fade" id="pago-proveedor" role="tabpanel">
                            <form id="formPagoProveedorRapido">
                                <input type="hidden" name="action" value="registrar_pago_proveedor">
                                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuario_id']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Proveedor *</label>
                                        <select class="form-select" name="proveedor_id" required 
                                                onchange="cargarDeudaProveedor(this.value)">
                                            <option value="">Seleccionar proveedor...</option>
                                            <?php foreach ($proveedores as $proveedor): 
                                                $deuda = floatval($proveedor['saldo_deuda']);
                                                if ($deuda > 0): ?>
                                                <option value="<?php echo $proveedor['id']; ?>" data-deuda="<?php echo $deuda; ?>">
                                                    <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                                    (Deuda: <?php echo Funciones::formatearMoneda($deuda); ?>)
                                                </option>
                                            <?php endif; endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Deuda Actual</label>
                                        <input type="text" class="form-control" id="deuda_proveedor_actual" 
                                               value="$0.00" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Monto a Pagar *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="monto" 
                                                   step="0.01" min="0.01" required id="montoProveedor">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Método de Pago *</label>
                                        <select class="form-select" name="metodo_pago" required>
                                            <option value="transferencia">Transferencia</option>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="cheque">Cheque</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Referencia (opcional)</label>
                                        <input type="text" class="form-control" name="referencia_pago" 
                                               placeholder="Nº de transferencia, cheque, etc.">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-success" onclick="registrarPagoRapido()">
                        <i class="fas fa-check me-2"></i>Registrar Pago
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalles de cuenta -->
    <div class="modal fade" id="modalDetalleCuenta" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Detalles de Cuenta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detalleContenido">
                        <!-- Aquí se cargarán los detalles -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3">Cargando detalles...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Obtener datos desde PHP
        const clientes = <?php echo json_encode($clientes ?? []); ?>;
        const proveedores = <?php echo json_encode($proveedores ?? []); ?>;
        const topDeudores = <?php echo json_encode($top_deudores ?? []); ?>;
        
        // Cambiar pestaña
        function cambiarTab(tab) {
            const url = new URL(window.location);
            url.searchParams.set('tipo', tab);
            window.location = url.toString();
        }
        
        // Cargar deuda del cliente
        function cargarDeudaCliente(clienteId) {
            const select = document.querySelector('#formPagoClienteRapido select[name="cliente_id"]');
            const option = select.querySelector(`option[value="${clienteId}"]`);
            
            const deudaInput = document.getElementById('deuda_cliente_actual');
            const montoInput = document.getElementById('montoCliente');
            
            if (option && option.dataset.deuda) {
                const deuda = parseFloat(option.dataset.deuda);
                deudaInput.value = '$' + deuda.toFixed(2);
                if (montoInput) {
                    montoInput.max = deuda;
                    montoInput.value = deuda > 0 ? deuda : '';
                }
            } else {
                deudaInput.value = '$0.00';
                if (montoInput) {
                    montoInput.max = '';
                    montoInput.value = '';
                }
            }
        }
        
        // Cargar deuda del proveedor
        function cargarDeudaProveedor(proveedorId) {
            const select = document.querySelector('#formPagoProveedorRapido select[name="proveedor_id"]');
            const option = select.querySelector(`option[value="${proveedorId}"]`);
            
            const deudaInput = document.getElementById('deuda_proveedor_actual');
            const montoInput = document.getElementById('montoProveedor');
            
            if (option && option.dataset.deuda) {
                const deuda = parseFloat(option.dataset.deuda);
                deudaInput.value = '$' + deuda.toFixed(2);
                if (montoInput) {
                    montoInput.max = deuda;
                    montoInput.value = deuda > 0 ? deuda : '';
                }
            } else {
                deudaInput.value = '$0.00';
                if (montoInput) {
                    montoInput.max = '';
                    montoInput.value = '';
                }
            }
        }
        
        // Registrar pago rápido
        function registrarPagoRapido() {
            const activeTab = document.querySelector('#paymentTabs .nav-link.active').id;
            
            let form, formData;
            
            if (activeTab === 'pago-cliente-tab') {
                form = document.getElementById('formPagoClienteRapido');
            } else {
                form = document.getElementById('formPagoProveedorRapido');
            }
            
            // Validar campos requeridos
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor complete todos los campos requeridos'
                });
                return;
            }
            
            // Validar monto
            const montoInput = form.querySelector('input[name="monto"]');
            const monto = parseFloat(montoInput.value);
            const maxMonto = parseFloat(montoInput.max) || 0;
            
            if (monto <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El monto debe ser mayor a 0'
                });
                montoInput.classList.add('is-invalid');
                return;
            }
            
            if (monto > maxMonto) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El monto no puede ser mayor a la deuda actual'
                });
                montoInput.classList.add('is-invalid');
                return;
            }
            
            // Preparar datos
            formData = new FormData(form);
            
            // Mostrar loading
            Swal.fire({
                title: 'Procesando pago...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar solicitud
            fetch('procesar_pagos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Cerrar modal y recargar página
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevoPago'));
                        modal.hide();
                        setTimeout(() => location.reload(), 500);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al registrar el pago'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor. Verifique su conexión a internet.'
                });
            });
        }
        
        // Registrar pago desde cuenta por cobrar
        function registrarPagoClienteDesdeCuenta(clienteId) {
            const cliente = clientes.find(c => c.id == clienteId);
            if (!cliente) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Cliente no encontrado'
                });
                return;
            }
            
            // Seleccionar pestaña de cliente
            document.querySelector('#pago-cliente-tab').click();
            
            // Llenar datos
            const selectCliente = document.querySelector('#formPagoClienteRapido select[name="cliente_id"]');
            if (selectCliente) {
                selectCliente.value = clienteId;
                setTimeout(() => cargarDeudaCliente(clienteId), 100);
            }
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalNuevoPago'));
            modal.show();
        }
        
        // Registrar pago desde cuenta por pagar
        function registrarPagoProveedorDesdeCuenta(proveedorId) {
            const proveedor = proveedores.find(p => p.id == proveedorId);
            if (!proveedor) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Proveedor no encontrado'
                });
                return;
            }
            
            // Seleccionar pestaña de proveedor
            document.querySelector('#pago-proveedor-tab').click();
            
            // Llenar datos
            const selectProveedor = document.querySelector('#formPagoProveedorRapido select[name="proveedor_id"]');
            if (selectProveedor) {
                selectProveedor.value = proveedorId;
                setTimeout(() => cargarDeudaProveedor(proveedorId), 100);
            }
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalNuevoPago'));
            modal.show();
        }
        
        // Ver detalle de cuenta - FUNCIÓN CORREGIDA
        function verDetalleCuenta(id, tipo) {
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetalleCuenta'));
            
            // Cargar detalles
            cargarDetallesCuenta(id, tipo);
            
            // Mostrar modal
            modal.show();
        }
        
        // Cargar detalles de cuenta
        function cargarDetallesCuenta(id, tipo) {
            const contenido = document.getElementById('detalleContenido');
            
            // Mostrar loading
            contenido.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3">Cargando detalles...</p>
                </div>
            `;
            
            // Hacer petición AJAX
            fetch(`obtener_detalle_cuenta.php?id=${id}&tipo=${tipo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarDetalles(data.detalle);
                    } else {
                        contenido.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${data.message || 'Error al cargar los detalles'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Mostrar datos estáticos si falla la petición
                    mostrarDetallesEstaticos(id, tipo);
                });
        }
        
        // Mostrar detalles de cuenta
        function mostrarDetalles(detalle) {
            const contenido = document.getElementById('detalleContenido');
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Información General</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">ID:</th>
                                <td>${detalle.id}</td>
                            </tr>
                            <tr>
                                <th>Tipo:</th>
                                <td><span class="badge ${detalle.tipo === 'pago' ? 'bg-success' : 'bg-warning'}">${detalle.tipo}</span></td>
                            </tr>
                            <tr>
                                <th>Fecha:</th>
                                <td>${detalle.fecha_hora_formateada}</td>
                            </tr>
                            <tr>
                                <th>Monto:</th>
                                <td class="fw-bold ${detalle.tipo === 'pago' ? 'text-success' : 'text-danger'}">${detalle.monto_formateado}</td>
                            </tr>
            `;
            
            if (detalle.tipo === 'cobrar') {
                html += `
                            <tr>
                                <th>Cliente:</th>
                                <td>${detalle.cliente_nombre}</td>
                            </tr>
                            <tr>
                                <th>Teléfono:</th>
                                <td>${detalle.telefono || 'N/A'}</td>
                            </tr>
                `;
            } else {
                html += `
                            <tr>
                                <th>Proveedor:</th>
                                <td>${detalle.proveedor_nombre}</td>
                            </tr>
                            <tr>
                                <th>Teléfono:</th>
                                <td>${detalle.telefono || 'N/A'}</td>
                            </tr>
                `;
            }
            
            html += `
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Información Financiera</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Saldo Anterior:</th>
                                <td>${detalle.saldo_anterior_formateado}</td>
                            </tr>
                            <tr>
                                <th>Saldo Nuevo:</th>
                                <td>${detalle.saldo_nuevo_formateado}</td>
                            </tr>
                            <tr>
                                <th>Diferencia:</th>
                                <td class="fw-bold ${detalle.diferencia >= 0 ? 'text-success' : 'text-danger'}">
                                    ${detalle.diferencia_formateado}
                                </td>
                            </tr>
            `;
            
            if (detalle.metodo_pago) {
                html += `
                            <tr>
                                <th>Método Pago:</th>
                                <td>
                                    <span class="badge ${detalle.metodo_pago === 'efectivo' ? 'bg-success' : 
                                                         detalle.metodo_pago === 'transferencia' ? 'bg-primary' :
                                                         detalle.metodo_pago === 'tarjeta' ? 'bg-warning' : 'bg-info'}">
                                        ${detalle.metodo_pago}
                                    </span>
                                </td>
                            </tr>
                `;
            }
            
            if (detalle.referencia_pago) {
                html += `
                            <tr>
                                <th>Referencia:</th>
                                <td>${detalle.referencia_pago}</td>
                            </tr>
                `;
            }
            
            html += `
                            <tr>
                                <th>Usuario:</th>
                                <td>${detalle.usuario_nombre}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
            
            if (detalle.observaciones) {
                html += `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-muted">Observaciones</h6>
                            <div class="card">
                                <div class="card-body">
                                    <p class="mb-0">${detalle.observaciones}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            if (detalle.venta_info) {
                html += `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-muted">Información de Venta</h6>
                            <div class="card">
                                <div class="card-body">
                                    <p><strong>Código:</strong> ${detalle.venta_info.codigo}</p>
                                    <p><strong>Total:</strong> ${detalle.venta_info.total}</p>
                                    <p><strong>Pagado:</strong> ${detalle.venta_info.pagado}</p>
                                    <p><strong>Debe:</strong> ${detalle.venta_info.debe}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            contenido.innerHTML = html;
        }
        
        // Mostrar detalles estáticos si falla la petición
        function mostrarDetallesEstaticos(id, tipo) {
            const contenido = document.getElementById('detalleContenido');
            
            contenido.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Mostrando información básica. Para ver detalles completos, asegúrese de que el archivo 
                    <code>obtener_detalle_cuenta.php</code> esté disponible.
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Información Básica</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">ID:</th>
                                <td>${id}</td>
                            </tr>
                            <tr>
                                <th>Tipo:</th>
                                <td>${tipo === 'cobrar' ? 'Por Cobrar' : 'Por Pagar'}</td>
                            </tr>
                            <tr>
                                <th>Fecha Consulta:</th>
                                <td>${new Date().toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Notas</h6>
                        <div class="card">
                            <div class="card-body">
                                <p class="mb-0">
                                    Para ver los detalles completos de esta cuenta, es necesario implementar 
                                    el archivo <code>obtener_detalle_cuenta.php</code> que se conecte a la 
                                    base de datos y obtenga la información específica.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Generar reporte
        function generarReporteCuentas() {
            const tipo = '<?php echo $filtro_tipo; ?>';
            const estado = '<?php echo $filtro_estado; ?>';
            const fechaDesde = '<?php echo $filtro_fecha_desde; ?>';
            const fechaHasta = '<?php echo $filtro_fecha_hasta; ?>';
            
            const url = `generar_reporte_cuentas.php?tipo=${tipo}&estado=${estado}&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`;
            window.open(url, '_blank');
        }
        
        // Gráfico de distribución de deudas
        function inicializarGraficoDeudas() {
            const ctx = document.getElementById('deudasChart');
            if (!ctx || topDeudores.length === 0) return;
            
            const labels = topDeudores.map(d => d.nombre.substring(0, 15) + (d.nombre.length > 15 ? '...' : ''));
            const deudas = topDeudores.map(d => parseFloat(d.deuda_total));
            const colores = ['#ff6b6b', '#ff8e8e', '#ffb5b5', '#ffd6d6', '#ffeaea'];
            
            // Formatear moneda
            const formatter = new Intl.NumberFormat('es-EC', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2
            });
            
            const chart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: deudas,
                        backgroundColor: colores,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${formatter.format(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Inicializar cuando esté listo
        document.addEventListener('DOMContentLoaded', function() {
            inicializarGraficoDeudas();
            
            // Inicializar tooltips de Bootstrap
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Validación en tiempo real de montos
            document.getElementById('montoCliente')?.addEventListener('input', function() {
                const max = parseFloat(this.max) || 0;
                const value = parseFloat(this.value) || 0;
                if (value > max) {
                    this.value = max;
                }
            });
            
            document.getElementById('montoProveedor')?.addEventListener('input', function() {
                const max = parseFloat(this.max) || 0;
                const value = parseFloat(this.value) || 0;
                if (value > max) {
                    this.value = max;
                }
            });
        });
    </script>
</body>
</html>